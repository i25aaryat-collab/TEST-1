<?php
/* ==========================================================================
 * profile/_profile_form.php  —  Owner: M3 (Profiles and validation)
 * --------------------------------------------------------------------------
 * The profile form, shared by create_profile.php and edit_profile.php.
 * (The "_" at the start means: this file is included, never opened alone.)
 *
 * It is laid out like a short compatibility test:
 *   1. About you (age, gender, city, picture)
 *   2. Budget
 *   3. How you live (your own habits)
 *   4. What you're okay with in a flatmate (used for matching)
 *   5. Languages and bio
 *
 * The including page sets:
 *   $values        current values (empty for create, from the DB for edit)
 *   $errors        server-side errors: [field => message]
 *   $mode          'create' or 'edit'
 *   $currentPhoto  current profiles.photo value, or null
 * ========================================================================== */

/** Adds the red outline class when a field has a server-side error */
function invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

/** The red message box under a field (filled by PHP now, or by JS later) */
function error_box(array $errors, string $field, ?string $jsName = null): string
{
    return '<div class="field-error" data-error-for="' . e($jsName ?? $field) . '">' . e($errors[$field] ?? '') . '</div>';
}

/** A row of pill-shaped radio buttons */
function pills(string $name, array $options, string $value, array $errors): string
{
    $html = '<div class="pill-group' . invalid($errors, $name) . '" data-field="' . e($name) . '" role="radiogroup">';
    foreach ($options as $opt => $label) {
        $checked = (string) $value === (string) $opt ? ' checked' : '';
        $html .= '<label class="pill"><input type="radio" name="' . e($name) . '" value="' . e($opt) . '"' . $checked . '><span>' . e($label) . '</span></label>';
    }
    return $html . '</div>';
}

$yesNo = ['no' => 'No', 'yes' => 'Yes'];
?>
<form id="profile-form" class="card form-card form-grid" method="post" action="" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_PHOTO_BYTES ?>">

    <h3 class="form-section-title"><span class="step-no">1</span> About you</h3>

    <div class="row-2">
        <div class="form-group">
            <label for="age">Age</label>
            <input type="number" id="age" name="age" min="18" max="80" step="1" inputmode="numeric"
                   value="<?= e($values['age']) ?>" class="<?= invalid($errors, 'age') ?>" required>
            <?= error_box($errors, 'age') ?>
        </div>
        <div class="form-group">
            <label id="gender-label">Gender</label>
            <div class="pill-group<?= invalid($errors, 'gender') ?>" data-field="gender" role="radiogroup" aria-labelledby="gender-label">
                <?php foreach (['male', 'female', 'nosay'] as $key): ?>
                    <label class="pill"><input type="radio" name="gender" value="<?= e($key) ?>" <?= $values['gender'] === $key ? 'checked' : '' ?>><span><?= e(GENDER_OPTIONS[$key]) ?></span></label>
                <?php endforeach; ?>
            </div>
            <input id="gender_text" name="gender_text" maxlength="40" class="gender-text" placeholder="Or in your own words (optional)" value="<?= e($values['gender'] === 'other' ? $values['gender_text'] : '') ?>" aria-label="Gender in your own words">
            <?= error_box($errors, 'gender') ?>
        </div>
    </div>

    <div class="form-group">
        <label for="city">City</label>
        <input type="text" id="city" name="city" maxlength="100" placeholder="e.g. Indore"
               value="<?= e($values['city']) ?>" class="<?= invalid($errors, 'city') ?>" autocomplete="address-level2" required>
        <span class="hint">You'll see people in this city first. Letters and spaces only.</span>
        <?= error_box($errors, 'city') ?>
    </div>

    <fieldset class="form-group">
        <legend>Profile picture <span class="muted small">(pick an icon, upload a photo, or skip)</span></legend>
        <div class="avatar-picker<?= invalid($errors, 'avatar') ?>" data-field="avatar" role="radiogroup">
            <?php foreach (AVATAR_IDS as $id): ?>
                <label class="avatar-option">
                    <input type="radio" name="avatar" value="<?= e($id) ?>" <?= $values['avatar'] === $id ? 'checked' : '' ?>>
                    <img src="<?= e(avatar_src($id)) ?>" alt="Avatar <?= (int) substr($id, 1) ?>" width="64" height="64" loading="lazy">
                </label>
            <?php endforeach; ?>
            <label class="avatar-option avatar-text">
                <input type="radio" name="avatar" value="upload" <?= $values['avatar'] === 'upload' ? 'checked' : '' ?>>
                <span>Upload<br>photo</span>
            </label>
            <label class="avatar-option avatar-text">
                <input type="radio" name="avatar" value="none" <?= $values['avatar'] === 'none' ? 'checked' : '' ?>>
                <span>No<br>picture</span>
            </label>
        </div>
        <?= error_box($errors, 'avatar') ?>
        <div class="photo-upload" id="upload-box" <?= $values['avatar'] === 'upload' ? '' : 'hidden' ?>>
            <img id="photo-preview" src="<?= e(photo_url($currentPhoto && !str_starts_with($currentPhoto, 'avatar:') ? $currentPhoto : null)) ?>" alt="Photo preview">
            <div>
                <label class="sr-only" for="photo">Photo file</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" class="<?= invalid($errors, 'photo') ?>">
                <div class="hint">JPG or PNG, up to 2 MB.</div>
                <button type="button" class="btn btn-sm" id="cartoon-start" hidden>🎨 Make a cartoon version</button>
                <input type="hidden" name="cartoon_data" id="cartoon-data">
            </div>
        </div>
        <div class="cartoon-panel card" id="cartoon-panel" hidden>
            <div class="cartoon-pics">
                <figure><img id="cartoon-original" alt="Your photo"><figcaption>Your photo (kept as it is)</figcaption></figure>
                <span aria-hidden="true">→</span>
                <figure><img id="cartoon-preview" alt="Cartoon preview"><figcaption>Cartoon preview</figcaption></figure>
            </div>
            <div class="cartoon-progress" id="cartoon-progress" hidden><i></i></div>
            <p class="small muted" id="cartoon-status" aria-live="polite"></p>
            <div class="btn-row">
                <button type="button" class="btn btn-primary btn-sm" id="cartoon-use">Use cartoon</button>
                <button type="button" class="btn btn-sm" id="cartoon-retry">Try another style</button>
                <button type="button" class="btn btn-ghost btn-sm" id="cartoon-cancel">Keep my photo</button>
            </div>
        </div>
        <?= error_box($errors, 'photo') ?>
    </fieldset>

    <h3 class="form-section-title"><span class="step-no">2</span> Budget and stay</h3>

    <div class="form-group">
        <label>How long do you want to live together?</label>
        <div class="choice-pills<?= isset($errors['stay']) ? ' is-invalid' : '' ?>" role="radiogroup">
            <?php foreach (STAY_OPTIONS as $k => $label): ?>
                <label class="choice-pill"><input type="radio" name="stay" value="<?= $k ?>" <?= (string) $values['stay'] === (string) $k ? 'checked' : '' ?> required><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <p class="hint">Someone here for a semester and someone signing a two-year lease rarely suit each other, so this counts towards your match score.</p>
        <?= error_box($errors, 'stay') ?>
    </div>

    <div class="form-group">
        <label>How many people do you want to live with?</label>
        <div class="choice-pills<?= isset($errors['flatmates']) ? ' is-invalid' : '' ?>" role="radiogroup">
            <?php foreach (FLATMATE_OPTIONS as $k => $label): ?>
                <label class="choice-pill"><input type="radio" name="flatmates" value="<?= $k ?>" <?= (string) $values['flatmates'] === (string) $k ? 'checked' : '' ?> required><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <p class="hint">Not counting you. Once your household has this many people, you can tell us you've found your people and stop showing up in Discover.</p>
        <?= error_box($errors, 'flatmates') ?>
    </div>

    <div class="row-2">
        <div class="form-group">
            <label for="budget_min">Minimum rent (₹ / month)</label>
            <input type="number" id="budget_min" name="budget_min" min="1000" max="200000" step="500" inputmode="numeric"
                   value="<?= e($values['budget_min']) ?>" class="<?= invalid($errors, 'budget_min') ?>" required>
            <?= error_box($errors, 'budget_min') ?>
        </div>
        <div class="form-group">
            <label for="budget_max">Maximum rent (₹ / month)</label>
            <input type="number" id="budget_max" name="budget_max" min="1000" max="200000" step="500" inputmode="numeric"
                   value="<?= e($values['budget_max']) ?>" class="<?= invalid($errors, 'budget_max') ?>" required>
            <?= error_box($errors, 'budget_max') ?>
        </div>
    </div>

    <h3 class="form-section-title"><span class="step-no">3</span> College and who sees you</h3>
    <div class="form-group college-pick">
        <label for="college">Your college <span class="muted">(optional, search all of India)</span></label>
        <input type="text" id="college" name="college" value="<?= e($values['college']) ?>" list="college-list" autocomplete="off" maxlength="255"
               placeholder="Type 3+ letters, e.g. IIM Indore" data-search="<?= e(url('colleges.php')) ?>" class="<?= invalid($errors, 'college') ?>">
        <datalist id="college-list"></datalist>
        <div class="hint">Not listed? Just type its full name.</div>
        <?= error_box($errors, 'college') ?>
    </div>
    <fieldset class="form-group"><legend>You are</legend>
        <?= pills('college_status', ['incoming' => 'Incoming student', 'current' => 'Currently enrolled', 'other' => 'Not a student'], $values['college_status'], $errors) ?>
    </fieldset>
    <fieldset class="form-group"><legend>Who can see your profile?</legend>
        <?= pills('visibility', VISIBILITY_OPTIONS, $values['visibility'], $errors) ?>
        <div class="hint">Your email is never shown because of your college.</div>
        <?= error_box($errors, 'visibility') ?>
    </fieldset>

    <h3 class="form-section-title"><span class="step-no">4</span> A bit more</h3>
    <fieldset class="form-group">
        <legend>Languages you're comfortable chatting in <span class="muted small">(optional, never used for matching)</span></legend>
        <div class="pill-group<?= invalid($errors, 'languages') ?>" data-field="languages[]">
            <?php foreach (LANGUAGE_OPTIONS as $lang): ?>
                <label class="pill">
                    <input type="checkbox" name="languages[]" value="<?= e($lang) ?>" <?= in_array($lang, $values['languages'], true) ? 'checked' : '' ?>>
                    <span><?= e($lang) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?= error_box($errors, 'languages', 'languages[]') ?>
    </fieldset>

    <div class="form-group">
        <label for="bio">Short bio <span class="muted">(optional)</span></label>
        <textarea id="bio" name="bio" placeholder="Work, routine, what makes you a good flatmate…"
                  class="<?= invalid($errors, 'bio') ?>"><?= e($values['bio']) ?></textarea>
        <div class="char-count" id="bio-count"></div>
        <?= error_box($errors, 'bio') ?>
    </div>


    <h3 class="form-section-title"><span class="step-no">5</span> Religious and cultural preferences <span class="muted small">(if any)</span></h3>
    <p class="muted small">All optional. Used only as a Discover filter, never in your score.</p>
    <div class="row-2">
        <div class="form-group"><label for="religion">Your religion</label>
            <select id="religion" name="religion"><?php foreach (RELIGION_OPTIONS as $r): ?><option <?= $values['religion'] === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="religion_pref">In flatmates</label>
            <select id="religion_pref" name="religion_pref"><?php foreach (RELIGION_PREFS as $k => $r): ?><option value="<?= $k ?>" <?= $values['religion_pref'] === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-group"><label for="culture_note">Anything flatmates should know? <span class="muted">(optional)</span></label>
        <textarea id="culture_note" name="culture_note" maxlength="300" placeholder="e.g. I light a diya every evening · I fast during Ramadan"><?= e($values['culture_note']) ?></textarea>
        <?= error_box($errors, 'culture_note') ?></div>

    <h3 class="form-section-title"><span class="step-no">6</span> Flat in mind</h3>
    <fieldset class="form-group"><legend>Where are you with finding a flat?</legend>
        <div class="stage-cards">
            <?php foreach (FLAT_STAGES as $k => [$icon, $label]): ?>
                <label class="stage-card"><input type="radio" name="flat_stage" value="<?= $k ?>" <?= (string) $values['flat_stage'] === (string) $k ? 'checked' : '' ?>><span><i aria-hidden="true"><?= $icon ?></i><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= error_box($errors, 'flat_stage') ?>
    </fieldset>
    <details class="form-group" <?= $values['flat_area'] || $values['flat_desc'] ? 'open' : '' ?>>
        <summary><strong>Flat details</strong> <span class="muted small">(optional, edit or clear any time)</span></summary>
        <div class="form-group"><label for="flat_desc">Short description</label><input id="flat_desc" name="flat_desc" maxlength="200" value="<?= e($values['flat_desc']) ?>" placeholder="e.g. 3BHK, one room free from January"></div>
        <div class="row-2">
            <div class="form-group"><label for="flat_area">Area</label><input id="flat_area" name="flat_area" maxlength="100" value="<?= e($values['flat_area']) ?>"></div>
            <div class="form-group"><label for="flat_rent">Rent for the room (₹/month)</label><input id="flat_rent" name="flat_rent" inputmode="numeric" value="<?= e($values['flat_rent']) ?>" class="<?= invalid($errors, 'flat_rent') ?>"><?= error_box($errors, 'flat_rent') ?></div>
        </div>
        <div class="form-group"><label>Location on the map <span class="muted">(people who haven't connected with you see only the rough area)</span></label>
            <?= location_picker('flat', $values['flat_lat'], $values['flat_lng'], 'Set flat location') ?></div>
        <div class="form-group"><label for="flat_photo">Photo of the flat</label><input type="file" id="flat_photo" name="flat_photo" accept="image/jpeg,image/png"><?= error_box($errors, 'flat_photo') ?>
            <?php if (!empty($values['flat_photo'])): ?><label class="small"><input type="checkbox" name="flat_photo_remove" value="1"> Remove current photo</label><?php endif; ?></div>
        <label class="small"><input type="checkbox" name="flat_clear" value="1"> Clear all flat details</label>
    </details>

    <h3 class="form-section-title"><span class="step-no">7</span> Distance <span class="muted small">(private)</span></h3>
    <div class="form-group"><label>Your campus, workplace or home <span class="muted">(never shown to anyone, only used for distances)</span></label>
        <?= location_picker('ref', $values['ref_lat'], $values['ref_lng'], 'Set my reference point') ?></div>
    <div class="form-group"><label for="radius_km">How far are you willing to live from it? <strong id="radius-out"><?= (int) ($values['radius_km'] ?: 8) ?> km</strong></label>
        <input type="range" id="radius_km" name="radius_km" min="1" max="50" value="<?= (int) ($values['radius_km'] ?: 8) ?>" oninput="document.getElementById('radius-out').textContent=this.value+' km'"></div>

    <h3 class="form-section-title"><span class="step-no">8</span> Contact details <span class="muted small">(private)</span></h3>
    <p class="muted small">Never on your profile. After you connect with someone, you choose which of these to share with them.</p>
    <div class="row-3">
        <div class="form-group"><label for="phone">Phone</label><input type="tel" id="phone" name="phone" value="<?= e($values['phone']) ?>" placeholder="98765 43210" class="<?= invalid($errors, 'phone') ?>"><?= error_box($errors, 'phone') ?></div>
        <div class="form-group"><label for="contact_email">Email to share</label><input type="email" id="contact_email" name="contact_email" value="<?= e($values['contact_email']) ?>" class="<?= invalid($errors, 'contact_email') ?>"><?= error_box($errors, 'contact_email') ?></div>
        <div class="form-group"><label for="instagram">Instagram</label><input type="text" id="instagram" name="instagram" value="<?= e($values['instagram']) ?>" placeholder="@yourname" class="<?= invalid($errors, 'instagram') ?>"><?= error_box($errors, 'instagram') ?></div>
    </div>

    <div class="form-actions">
        <?php if ($mode === 'edit'): ?>
            <a class="btn" href="<?= e(url($cancelUrl ?? 'profile/view_profile.php')) ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
        <?php else: ?>
            <button type="submit" class="btn btn-primary btn-lg">See my matches</button>
        <?php endif; ?>
    </div>
</form>

<?php
// ---- Client-side rules: the same as validate_profile() in PHP ----
$languageList = json_encode(LANGUAGE_OPTIONS);
$avatarIds    = json_encode(AVATAR_IDS);
$hasUpload    = json_encode((bool) ($currentPhoto && !str_starts_with($currentPhoto, 'avatar:')));
$avatarizeJs  = e(url('js/avatarize.js'));
$extraScripts = ($extraScripts ?? '') . <<<HTML
<script src="$avatarizeJs?v=1"></script>
<script>
const ALLOWED_LANGUAGES = $languageList;
const AVATAR_IDS = $avatarIds;
const HAS_UPLOAD = $hasUpload;
const profileForm = document.getElementById('profile-form');
// Gender: typing your own words clears the pills, picking a pill clears the words
profileForm.gender_text.addEventListener('input', function () { if (this.value.trim()) profileForm.querySelectorAll('input[name=gender]').forEach(function (r) { r.checked = false; }); });
profileForm.querySelectorAll('input[name=gender]').forEach(function (r) { r.addEventListener('change', function () { profileForm.gender_text.value = ''; }); });
const oneOf = (v, list) => list.includes(v);

RM.validateForm(profileForm, function (form) {
    const errors = {};

    if (!RM.isIntInRange(form.age.value, 18, 80)) errors.age = 'Age must be a whole number from 18 to 80.';
    if (!form.gender_text.value.trim() && !oneOf(RM.radioValue(form, 'gender'), ['male', 'female', 'nosay'])) errors.gender = 'Pick an option or describe it in your own words.';

    const city = form.city.value.trim();
    if (city === '') errors.city = 'City is required.';
    else if (!RM.isLettersAndSpaces(city)) errors.city = 'Use letters and spaces only (e.g. Indore, New Delhi).';

    const pic = RM.radioValue(form, 'avatar');
    const file = form.photo.files[0];
    if (!oneOf(pic, AVATAR_IDS.concat(['upload', 'none']))) errors.avatar = 'Pick an icon, upload a photo, or choose "No picture".';
    if (pic === 'upload') {
        if (!file && !HAS_UPLOAD && !document.getElementById('cartoon-data').value) errors.photo = 'Choose a JPG or PNG to upload, or pick one of the icons.';
        else if (file && !oneOf(file.type, ['image/jpeg', 'image/png'])) errors.photo = 'Only JPG or PNG images are allowed.';
        else if (file && file.size > 2 * 1024 * 1024) errors.photo = 'Photo must be 2 MB or smaller.';
    }

    const minOk = RM.isIntInRange(form.budget_min.value, 1000, 200000);
    const maxOk = RM.isIntInRange(form.budget_max.value, 1000, 200000);
    if (!minOk) errors.budget_min = 'Enter a whole number of rupees from 1,000 to 2,00,000.';
    if (!maxOk) errors.budget_max = 'Enter a whole number of rupees from 1,000 to 2,00,000.';
    if (minOk && maxOk && parseInt(form.budget_min.value, 10) >= parseInt(form.budget_max.value, 10)) {
        errors.budget_max = 'Maximum must be more than the minimum.';
    }

    if (form.flat_rent.value.trim() !== '' && !RM.isIntInRange(form.flat_rent.value, 500, 200000)) errors.flat_rent = 'Enter a whole number of rupees from 500 to 2,00,000.';
    if (form.contact_email.value.trim() !== '' && !RM.isEmail(form.contact_email.value)) errors.contact_email = 'Enter a valid email.';
    if (form.instagram.value.trim() !== '' && !/^@?[A-Za-z0-9._]{1,30}$/.test(form.instagram.value.trim())) errors.instagram = 'Letters, numbers, dots and underscores only.';

    const langs = RM.checkedValues(form, 'languages[]');
    if (!langs.every(function (l) { return ALLOWED_LANGUAGES.includes(l); })) errors['languages[]'] = 'Pick languages from the list only.';

    if (!RM.maxLength(form.bio.value, 300)) errors.bio = 'Bio can be at most 300 characters.';
    return errors;
});

// College search: suggestions from all of India as you type (colleges.php)
(function () {
    const box = document.getElementById('college'), list = document.getElementById('college-list'); let t;
    box.addEventListener('input', function () {
        clearTimeout(t); if (box.value.trim().length < 3) return;
        t = setTimeout(async function () {
            try {
                const res = await (await fetch(box.dataset.search + '?q=' + encodeURIComponent(box.value))).json();
                list.innerHTML = res.map(function (c) { const o = document.createElement('option'); o.value = c.name; o.label = c.place; return o.outerHTML; }).join('');
            } catch (e) {}
        }, 250);
    });
})();
RM.charCounter(profileForm.bio, document.getElementById('bio-count'), 300);

// Show the upload box only when "Upload photo" is picked
profileForm.querySelectorAll('input[name=avatar]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.getElementById('upload-box').hidden = RM.radioValue(profileForm, 'avatar') !== 'upload';
    });
});
// Show the chosen photo straight away. "Make a cartoon version" converts it in the
// browser (js/avatarize.js) and shows both side by side. Nothing changes until
// "Use cartoon": the cartoon is then sent as a separate field (cartoon_data), so it
// works on phones that don't let a page swap the file inside a file box.
(function () {
    const input = profileForm.photo, preview = document.getElementById('photo-preview');
    const start = document.getElementById('cartoon-start'), panel = document.getElementById('cartoon-panel');
    const orig = document.getElementById('cartoon-original'), out = document.getElementById('cartoon-preview');
    const status = document.getElementById('cartoon-status'), bar = document.getElementById('cartoon-progress');
    const data = document.getElementById('cartoon-data');
    let file = null, style = 0, result = '';
    input.addEventListener('change', function () {
        file = this.files[0] || null; data.value = ''; panel.hidden = true;
        if (file && ['image/jpeg', 'image/png'].includes(file.type) && file.size <= 2 * 1024 * 1024) { preview.src = URL.createObjectURL(file); start.hidden = false; }
        else start.hidden = true;
    });
    async function run() {
        panel.hidden = false; orig.src = URL.createObjectURL(file); out.removeAttribute('src');
        bar.hidden = false; status.textContent = 'Converting…';
        document.getElementById('cartoon-use').disabled = true;
        try {
            RMAvatarize.LEVELS = [5, 4, 6][style % 3];
            const png = await RMAvatarize.convert(file);
            result = await new Promise(function (ok) { const r = new FileReader(); r.onload = function () { ok(r.result); }; r.readAsDataURL(png); });
            out.src = result; status.textContent = 'Happy with it? Use it, try another style, or keep your photo.';
            document.getElementById('cartoon-use').disabled = false;
        } catch (e) { status.textContent = 'Could not convert that photo. Try another JPG or PNG, or keep your photo.'; }
        bar.hidden = true;
    }
    start.addEventListener('click', function () { style = 0; run(); });
    document.getElementById('cartoon-retry').addEventListener('click', function () { style++; run(); });
    document.getElementById('cartoon-use').addEventListener('click', function () {
        data.value = result; preview.src = result; panel.hidden = true; RM.toast('Cartoon ready. Save your profile to keep it.', 'success');
    });
    document.getElementById('cartoon-cancel').addEventListener('click', function () {
        data.value = ''; panel.hidden = true; if (file) preview.src = URL.createObjectURL(file);
    });
})();
</script>
HTML;
