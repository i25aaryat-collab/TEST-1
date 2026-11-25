/* ==========================================================================
 * js/e2e.js  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * End-to-end encryption for one-to-one chats, using the browser's own
 * WebCrypto (no library). The server only ever stores scrambled text.
 *
 *   Your keys   An ECDH P-256 key pair made in your browser. The public half
 *               is stored on the server so matches can find it. The private
 *               half is stored too, but locked (AES-GCM) with a key that comes
 *               from your password (PBKDF2, 200,000 rounds, then HKDF with a
 *               salt of your own), so the server can't open it. On each
 *               device it is unlocked once, when you log in, and kept in
 *               IndexedDB as a non-extractable key.
 *   A chat      Both people's keys give the same shared secret (ECDH). HKDF
 *               turns it into an AES-256-GCM key for that chat only.
 *   A message   A random 12-byte IV + AES-GCM, tied to the chat id. Stored as
 *               "e2e:1:" + base64(IV + ciphertext).
 * Stickers and GIFs are bundled pictures, so only their id is sent, as before.
 *
 * window.RME2E = { status(otherId, matchId), encrypt, decrypt, unlock, isEnc }
 * ========================================================================== */
(function () {
    'use strict';
    if (!window.crypto || !crypto.subtle || !window.indexedDB) return;

    var CFG = window.RM_E2E || {};                 // { keys: url, me: userId } (footer)
    var enc = new TextEncoder(), dec = new TextDecoder();
    var b64 = function (buf) { var s = '', a = new Uint8Array(buf); for (var i = 0; i < a.length; i++) s += String.fromCharCode(a[i]); return btoa(s); };
    var unb64 = function (s) { var r = atob(s), a = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) a[i] = r.charCodeAt(i); return a; };
    var PREFIX = 'e2e:1:';

    // ---------- IndexedDB (one small store) ----------
    function db() {
        return new Promise(function (ok, no) {
            var r = indexedDB.open('roomiematch-e2e', 1);
            r.onupgradeneeded = function () { r.result.createObjectStore('keys'); };
            r.onsuccess = function () { ok(r.result); }; r.onerror = function () { no(r.error); };
        });
    }
    function idb(mode, fn) {
        return db().then(function (d) { return new Promise(function (ok, no) {
            var t = d.transaction('keys', mode), s = t.objectStore('keys'), req = fn(s);
            t.oncomplete = function () { ok(req && req.result); }; t.onerror = function () { no(t.error); };
        }); });
    }
    var get = function (k) { return idb('readonly', function (s) { return s.get(k); }); };
    var put = function (k, v) { return idb('readwrite', function (s) { return s.put(v, k); }); };
    var del = function (k) { return idb('readwrite', function (s) { return s.delete(k); }); };
    var clearAll = function () { return idb('readwrite', function (s) { return s.clear(); }); };

    // ---------- Password -> base key (done while logging in) ----------
    function passwordBase(password) {
        return crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveBits'])
            .then(function (k) { return crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt: enc.encode('roomiematch-e2e-v1'), iterations: 200000 }, k, 256); })
            .then(function (bits) { return crypto.subtle.importKey('raw', bits, 'HKDF', false, ['deriveKey']); });
    }
    function wrapKey(base, saltB64) {
        return crypto.subtle.deriveKey({ name: 'HKDF', hash: 'SHA-256', salt: unb64(saltB64), info: enc.encode('rm-e2e-wrap-v1') },
            base, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
    }

    // ---------- Server ----------
    function api(params, post) {
        var url = CFG.keys + (CFG.keys.indexOf('?') < 0 ? '?' : '&') + new URLSearchParams(post ? {} : params);
        var opt = { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' };
        if (post) {
            var f = new FormData(); Object.keys(params).forEach(function (k) { f.append(k, params[k]); });
            var t = document.querySelector('meta[name="csrf-token"]'); if (t) f.append('csrf_token', t.content);
            opt.method = 'POST'; opt.body = f;
        }
        return fetch(url, opt).then(function (r) { return r.json(); });
    }

    // ---------- Set up or unlock my keys, from a password base key ----------
    function setupFrom(base) {
        return api({ mine: 1 }).then(function (mine) {
            if (!mine.ok) throw new Error('not logged in');
            if (mine.pub && mine.priv && mine.salt) {
                return wrapKey(base, mine.salt).then(function (wk) {
                    var blob = unb64(mine.priv);
                    return crypto.subtle.decrypt({ name: 'AES-GCM', iv: blob.slice(0, 12) }, wk, blob.slice(12));
                }).then(function (pkcs8) {
                    return crypto.subtle.importKey('pkcs8', pkcs8, { name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveBits']);
                }).then(function (priv) {
                    return put('me', { user: CFG.me, priv: priv, pub: mine.pub }).then(function () { return true; });
                }).catch(function () { return makeNew(base, true); });      // locked with an older password: start fresh
            }
            return makeNew(base, false);
        });
    }
    function makeNew(base, replace) {
        var salt = b64(crypto.getRandomValues(new Uint8Array(16))), pair, pubB64;
        return crypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveBits']).then(function (p) {
            pair = p;
            return Promise.all([crypto.subtle.exportKey('spki', p.publicKey), crypto.subtle.exportKey('pkcs8', p.privateKey), wrapKey(base, salt)]);
        }).then(function (r) {
            pubB64 = b64(r[0]);
            var iv = crypto.getRandomValues(new Uint8Array(12));
            return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, r[2], r[1]).then(function (ct) {
                var blob = new Uint8Array(12 + ct.byteLength); blob.set(iv); blob.set(new Uint8Array(ct), 12);
                return api({ action: 'setup', pub: pubB64, priv: b64(blob), salt: salt, replace: replace ? 1 : 0 }, true);
            });
        }).then(function (res) {
            if (!res.ok) throw new Error(res.error || 'setup failed');
            // keep a non-extractable copy of the private key on this device
            return crypto.subtle.exportKey('pkcs8', pair.privateKey)
                .then(function (raw) { return crypto.subtle.importKey('pkcs8', raw, { name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveBits']); })
                .then(function (priv) { return put('me', { user: CFG.me, priv: priv, pub: pubB64 }); })
                .then(function () { return true; });
        });
    }

    // ---------- My unlocked key on this device (checked against the server copy) ----------
    var mePromise = null;
    function myKey() {
        if (mePromise) return mePromise;
        mePromise = (CFG.me ? get('pending').then(function (base) {
            if (!base) return null;
            return del('pending').then(function () { return setupFrom(base); }).catch(function () { return null; });
        }) : Promise.resolve(null)).then(function () {
            return Promise.all([get('me'), CFG.me ? api({ mine: 1 }) : null]);
        }).then(function (r) {
            var local = r[0], server = r[1];
            if (!local || local.user !== CFG.me || !server || !server.ok || server.pub !== local.pub) return null;
            return local;
        }).catch(function () { return null; });
        return mePromise;
    }

    // ---------- Chat keys ----------
    var chatKeys = {}, pubs = {};
    function theirPub(userId) {
        if (!pubs[userId]) pubs[userId] = api({ user_id: userId }).then(function (r) { return r.ok && r.pub ? r.pub : null; });
        return pubs[userId];
    }
    function chatKey(me, otherId, matchId) {
        var id = matchId + ':' + otherId;
        if (!chatKeys[id]) chatKeys[id] = theirPub(otherId).then(function (pub) {
            if (!pub) return null;
            return crypto.subtle.importKey('spki', unb64(pub), { name: 'ECDH', namedCurve: 'P-256' }, false, [])
                .then(function (their) { return crypto.subtle.deriveBits({ name: 'ECDH', public: their }, me.priv, 256); })
                .then(function (bits) { return crypto.subtle.importKey('raw', bits, 'HKDF', false, ['deriveKey']); })
                .then(function (h) { return crypto.subtle.deriveKey({ name: 'HKDF', hash: 'SHA-256', salt: enc.encode('match:' + matchId), info: enc.encode('rm-e2e-msg-v1') },
                    h, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']); });
        }).catch(function () { return null; });
        return chatKeys[id];
    }

    /** Where this chat stands: 'on', 'locked' (this device needs your password), 'waiting' (they haven't set up yet) */
    function status(otherId, matchId) {
        return myKey().then(function (me) {
            if (!me) return { state: 'locked' };
            return chatKey(me, otherId, matchId).then(function (k) { return k ? { state: 'on', key: k } : { state: 'waiting' }; });
        });
    }
    function encrypt(key, matchId, text) {
        var iv = crypto.getRandomValues(new Uint8Array(12));
        return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv, additionalData: enc.encode('m' + matchId) }, key, enc.encode(text)).then(function (ct) {
            var blob = new Uint8Array(12 + ct.byteLength); blob.set(iv); blob.set(new Uint8Array(ct), 12);
            return PREFIX + b64(blob);
        });
    }
    var plainCache = {};
    function decrypt(key, matchId, body) {
        if (body.indexOf(PREFIX) !== 0) return Promise.resolve(body);
        if (plainCache[body] !== undefined) return Promise.resolve(plainCache[body]);
        if (!key) return Promise.resolve(null);
        var blob; try { blob = unb64(body.slice(PREFIX.length)); } catch (e) { return Promise.resolve(null); }
        return crypto.subtle.decrypt({ name: 'AES-GCM', iv: blob.slice(0, 12), additionalData: enc.encode('m' + matchId) }, key, blob.slice(12))
            .then(function (pt) { return (plainCache[body] = dec.decode(pt)); }).catch(function () { return null; });
    }

    /** Unlock on this device with a password typed in the chat (checked by the server first) */
    function unlock(password) {
        return api({ action: 'check', password: password }, true).then(function (r) {
            if (!r.ok) throw new Error(r.error || "That password doesn't match.");
            return passwordBase(password);
        }).then(setupFrom).then(function () { mePromise = null; chatKeys = {}; pubs = {}; return myKey(); });
    }

    // ---------- While logging in or signing up: remember a key from the password ----------
    // (on document, so it runs after the form's own checks and only when they passed)
    var busy = false;
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || (form.id !== 'login-form' && form.id !== 'register-form')) return;
        if (e.defaultPrevented || busy || !form.password || !form.password.value) return;
        e.preventDefault(); busy = true;
        passwordBase(form.password.value).then(function (base) { return clearAll().then(function () { return put('pending', base); }); })
            .catch(function () {}).then(function () { HTMLFormElement.prototype.submit.call(form); });
    });
    // Logging out forgets the keys on this device
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href*="auth/logout.php"]'); if (!a) return;
        e.preventDefault(); clearAll().catch(function () {}).then(function () { window.location.href = a.href; });
    });

    // ---------- Anything on the page marked data-e2e (e.g. inbox previews) ----------
    function fillPreviews() {
        var els = document.querySelectorAll('[data-e2e]'); if (!els.length) return;
        myKey().then(function (me) {
            els.forEach(function (el) {
                if (!me) return;
                chatKey(me, el.dataset.other, el.dataset.match).then(function (k) { return decrypt(k, el.dataset.match, el.dataset.e2e); })
                    .then(function (t) { if (t !== null) el.textContent = (el.dataset.prefix || '') + t; });
            });
        });
    }

    window.RME2E = { status: status, encrypt: encrypt, decrypt: decrypt, unlock: unlock, isEnc: function (b) { return typeof b === 'string' && b.indexOf(PREFIX) === 0; } };
    if (CFG.me) { myKey(); fillPreviews(); }
})();
