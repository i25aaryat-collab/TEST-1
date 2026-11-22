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
            <label for="gender">Gender</label>
            <select id="gender" name="gender" class="<?= invalid($errors, 'gender') ?>" required>
                <option value="">Select…</option>
                <?php foreach (GENDER_OPTIONS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $values['gender'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
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
                    <img src="<?= e(url('css/avatars/' . $id . '.svg')) ?>" alt="Character icon <?= (int) substr($id, 1) ?>" width="64" height="64">
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
                <label class="small cartoon-toggle"><input type="checkbox" id="cartoonize"> Turn my photo into a cartoon avatar</label>
            </div>
        </div>
        <?= error_box($errors, 'photo') ?>
    </fieldset>

    <h3 class="form-section-title"><span class="step-no">2</span> Budget</h3>

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

    <h3 class="form-section-title"><span class="step-no">3</span> How you live</h3>

    <div class="form-group">
        <label for="sleep_slot">When do you usually go to sleep?</label>
        <select id="sleep_slot" name="sleep_slot" class="<?= invalid($errors, 'sleep_slot') ?>" required>
            <option value="">Select a 2-hour window…</option>
            <?php foreach (SLEEP_SLOTS as $slot => $label): ?>
                <option value="<?= $slot ?>" <?= (string) $values['sleep_slot'] === (string) $slot ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?= error_box($errors, 'sleep_slot') ?>
    </div>

    <fieldset class="form-group">
        <legend>How tidy are you, honestly?</legend>
        <?= pills('cleanliness', CLEANLINESS_LEVELS, (string) $values['cleanliness'], $errors) ?>
        <?= error_box($errors, 'cleanliness') ?>
    </fieldset>

    <div class="row-3">
        <fieldset class="form-group"><legend>Do you smoke?</legend><?= pills('smoking', $yesNo, $values['smoking'], $errors) ?><?= error_box($errors, 'smoking') ?></fieldset>
        <fieldset class="form-group"><legend>Do you drink?</legend><?= pills('drinking', $yesNo, $values['drinking'], $errors) ?><?= error_box($errors, 'drinking') ?></fieldset>
        <fieldset class="form-group"><legend>Do you have a pet?</legend><?= pills('pets', $yesNo, $values['pets'], $errors) ?><?= error_box($errors, 'pets') ?></fieldset>
    </div>

    <h3 class="form-section-title"><span class="step-no">4</span> What you're okay with in a flatmate</h3>
    <p class="hint" style="margin-top:-8px">This is what matching uses. Being open-minded gets you more good matches.</p>

    <fieldset class="form-group"><legend>Living with someone who smokes?</legend><?= pills('ok_smoking', TOLERANCE_OPTIONS, $values['ok_smoking'], $errors) ?><?= error_box($errors, 'ok_smoking') ?></fieldset>
    <fieldset class="form-group"><legend>Living with someone who drinks?</legend><?= pills('ok_drinking', TOLERANCE_OPTIONS, $values['ok_drinking'], $errors) ?><?= error_box($errors, 'ok_drinking') ?></fieldset>
    <fieldset class="form-group"><legend>Living with a pet?</legend><?= pills('ok_pets', TOLERANCE_OPTIONS, $values['ok_pets'], $errors) ?><?= error_box($errors, 'ok_pets') ?></fieldset>

    <fieldset class="form-group">
        <legend>The messiest flatmate you could live with</legend>
        <?= pills('min_cleanliness', CLEANLINESS_LEVELS, (string) $values['min_cleanliness'], $errors) ?>
        <?= error_box($errors, 'min_cleanliness') ?>
    </fieldset>

    <fieldset class="form-group">
        <legend>How different can their bedtime be from yours?</legend>
        <?= pills('sleep_flex', SLEEP_FLEX_OPTIONS, (string) $values['sleep_flex'], $errors) ?>
        <?= error_box($errors, 'sleep_flex') ?>
    </fieldset>

    <h3 class="form-section-title"><span class="step-no">5</span> A bit more</h3>

    <fieldset class="form-group">
        <legend>Languages you speak <span class="muted small">(pick at least one)</span></legend>
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

    <div class="form-actions">
        <?php if ($mode === 'edit'): ?>
            <a class="btn" href="<?= e(url('profile/view_profile.php')) ?>">Cancel</a>
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
$extraScripts = <<<HTML
<script src="$avatarizeJs?v=1"></script>
<script>
const ALLOWED_LANGUAGES = $languageList;
const AVATAR_IDS = $avatarIds;
const HAS_UPLOAD = $hasUpload;
const profileForm = document.getElementById('profile-form');
const oneOf = (v, list) => list.includes(v);

RM.validateForm(profileForm, function (form) {
    const errors = {};

    if (!RM.isIntInRange(form.age.value, 18, 80)) errors.age = 'Age must be a whole number from 18 to 80.';
    if (!oneOf(form.gender.value, ['male', 'female', 'other'])) errors.gender = 'Choose an option from the list.';

    const city = form.city.value.trim();
    if (city === '') errors.city = 'City is required.';
    else if (!RM.isLettersAndSpaces(city)) errors.city = 'Use letters and spaces only (e.g. Indore, New Delhi).';

    const pic = RM.radioValue(form, 'avatar');
    const file = form.photo.files[0];
    if (!oneOf(pic, AVATAR_IDS.concat(['upload', 'none']))) errors.avatar = 'Pick an icon, upload a photo, or choose "No picture".';
    if (pic === 'upload') {
        if (!file && !HAS_UPLOAD) errors.photo = 'Choose a JPG or PNG to upload, or pick one of the icons.';
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

    if (!RM.isIntInRange(form.sleep_slot.value, 1, 5)) errors.sleep_slot = 'Pick your usual bedtime.';
    if (!RM.isIntInRange(RM.radioValue(form, 'cleanliness'), 1, 5)) errors.cleanliness = 'Pick how tidy you are.';
    if (!oneOf(RM.radioValue(form, 'smoking'), ['yes', 'no']))  errors.smoking  = 'Tell us whether you smoke.';
    if (!oneOf(RM.radioValue(form, 'drinking'), ['yes', 'no'])) errors.drinking = 'Tell us whether you drink.';
    if (!oneOf(RM.radioValue(form, 'pets'), ['yes', 'no']))     errors.pets     = 'Tell us whether you have pets.';

    const ok = ['yes', 'maybe', 'no'];
    if (!oneOf(RM.radioValue(form, 'ok_smoking'), ok))  errors.ok_smoking  = "Tell us if you're okay living with a smoker.";
    if (!oneOf(RM.radioValue(form, 'ok_drinking'), ok)) errors.ok_drinking = "Tell us if you're okay living with someone who drinks.";
    if (!oneOf(RM.radioValue(form, 'ok_pets'), ok))     errors.ok_pets     = "Tell us if you're okay living with pets.";
    if (!RM.isIntInRange(RM.radioValue(form, 'min_cleanliness'), 1, 5)) errors.min_cleanliness = 'Pick the messiest flatmate you could live with.';
    if (!oneOf(RM.radioValue(form, 'sleep_flex'), ['0', '1', '2', '4'])) errors.sleep_flex = 'Pick how different a bedtime you are okay with.';

    const langs = RM.checkedValues(form, 'languages[]');
    if (langs.length === 0) errors['languages[]'] = 'Pick at least one language.';
    else if (!langs.every(function (l) { return ALLOWED_LANGUAGES.includes(l); })) errors['languages[]'] = 'Pick languages from the list only.';

    if (!RM.maxLength(form.bio.value, 300)) errors.bio = 'Bio can be at most 300 characters.';
    return errors;
});

RM.charCounter(profileForm.bio, document.getElementById('bio-count'), 300);

// Show the upload box only when "Upload photo" is picked
profileForm.querySelectorAll('input[name=avatar]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.getElementById('upload-box').hidden = RM.radioValue(profileForm, 'avatar') !== 'upload';
    });
});
// Show the chosen photo straight away, and optionally turn it into a cartoon avatar
// (js/avatarize.js). The cartoon replaces the file that gets uploaded.
let originalPhoto = null;
const cartoon = document.getElementById('cartoonize');
const preview = document.getElementById('photo-preview');
async function applyPhoto() {
    if (!originalPhoto) return;
    let file = originalPhoto;
    if (cartoon.checked) {
        cartoon.disabled = true;
        try { file = await RMAvatarize.convert(originalPhoto); } catch (e) { RM.toast('Could not convert that photo.', 'error'); }
        cartoon.disabled = false;
    }
    const list = new DataTransfer();          // put the chosen version back into the file input
    list.items.add(file);
    profileForm.photo.files = list.files;
    preview.src = URL.createObjectURL(file);
}
profileForm.photo.addEventListener('change', function () {
    const file = this.files[0];
    if (file && file.type.startsWith('image/') && file.name !== 'avatar.png') { originalPhoto = file; applyPhoto(); }
});
cartoon.addEventListener('change', applyPhoto);
</script>
HTML;
