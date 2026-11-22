/* ==========================================================================
 * js/avatarize.js  —  Owner: M3 (Profiles and validation)
 * --------------------------------------------------------------------------
 * Turns an uploaded photo into a cartoon-style avatar, in the browser,
 * before it is uploaded (nothing is sent anywhere else):
 *   1. crop the middle square of the photo
 *   2. smooth it and boost the colours
 *   3. "posterize": round every colour to a few flat shades, like a cartoon
 *   4. find the edges (Sobel filter) and draw them as dark outlines
 *   5. place it in a circle on a soft background
 * RMAvatarize.convert(file) returns a Promise for a new PNG File.
 * ========================================================================== */

const RMAvatarize = {
    SIZE: 360,        // working size in pixels
    LEVELS: 5,        // shades per colour channel after posterizing

    /** Load a File into an <img> */
    load: function (file) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = reject;
            img.src = URL.createObjectURL(file);
        });
    },

    convert: async function (file) {
        const img = await RMAvatarize.load(file);
        const S = RMAvatarize.SIZE;

        // 1. Centre square crop
        const side = Math.min(img.naturalWidth, img.naturalHeight);
        const sx = (img.naturalWidth - side) / 2, sy = (img.naturalHeight - side) / 2;
        const base = document.createElement('canvas');
        base.width = base.height = S;
        const bctx = base.getContext('2d', { willReadFrequently: true });
        bctx.drawImage(img, sx, sy, side, side, 0, 0, S, S);
        const sharp = bctx.getImageData(0, 0, S, S);              // kept for edge detection

        // 2. Smooth + richer colours (canvas filters; skipped where unsupported)
        const soft = document.createElement('canvas');
        soft.width = soft.height = S;
        const sctx = soft.getContext('2d', { willReadFrequently: true });
        sctx.filter = 'blur(2px) saturate(1.5) contrast(1.1)';
        sctx.drawImage(base, 0, 0);
        const out = sctx.getImageData(0, 0, S, S);

        // 3. Posterize: round each channel to a few flat shades
        const step = 255 / (RMAvatarize.LEVELS - 1);
        for (let i = 0; i < out.data.length; i += 4) {
            out.data[i]     = Math.round(out.data[i] / step) * step;
            out.data[i + 1] = Math.round(out.data[i + 1] / step) * step;
            out.data[i + 2] = Math.round(out.data[i + 2] / step) * step;
        }

        // 4. Outlines: Sobel edge strength on the brightness of the sharp photo
        const lum = new Float32Array(S * S);
        for (let p = 0, i = 0; p < S * S; p++, i += 4) {
            lum[p] = 0.299 * sharp.data[i] + 0.587 * sharp.data[i + 1] + 0.114 * sharp.data[i + 2];
        }
        for (let y = 1; y < S - 1; y++) {
            for (let x = 1; x < S - 1; x++) {
                const p = y * S + x;
                const gx = -lum[p - S - 1] - 2 * lum[p - 1] - lum[p + S - 1] + lum[p - S + 1] + 2 * lum[p + 1] + lum[p + S + 1];
                const gy = -lum[p - S - 1] - 2 * lum[p - S] - lum[p - S + 1] + lum[p + S - 1] + 2 * lum[p + S] + lum[p + S + 1];
                const edge = Math.min(1, Math.max(0, (Math.hypot(gx, gy) - 90) / 160));
                if (edge > 0) {                                   // blend towards dark navy ink
                    const i = p * 4;
                    out.data[i]     = out.data[i]     * (1 - edge) + 43 * edge;
                    out.data[i + 1] = out.data[i + 1] * (1 - edge) + 45 * edge;
                    out.data[i + 2] = out.data[i + 2] * (1 - edge) + 66 * edge;
                }
            }
        }
        sctx.filter = 'none';
        sctx.putImageData(out, 0, 0);

        // 5. Round avatar on a soft coral background
        const final = document.createElement('canvas');
        final.width = final.height = S;
        const fctx = final.getContext('2d');
        fctx.fillStyle = '#FFE3E4';
        fctx.fillRect(0, 0, S, S);
        fctx.save();
        fctx.beginPath();
        fctx.arc(S / 2, S / 2, S / 2 - 8, 0, Math.PI * 2);
        fctx.clip();
        fctx.drawImage(soft, 0, 0);
        fctx.restore();
        fctx.lineWidth = 8;
        fctx.strokeStyle = '#FF5A5F';
        fctx.beginPath();
        fctx.arc(S / 2, S / 2, S / 2 - 8, 0, Math.PI * 2);
        fctx.stroke();

        const blob = await new Promise(function (resolve) { final.toBlob(resolve, 'image/png'); });
        return new File([blob], 'avatar.png', { type: 'image/png' });
    }
};
