# RoomieMatch

A flatmate compatibility site. Users register (with reCAPTCHA) and take a short compatibility test about how they live and **what they're okay with in a flatmate**. Everyone in their city is ranked with a score out of 100. They send requests, match when accepted, and chat live with emojis, stickers and GIFs. People with a room can list it on **Flats**. Users can report messages, listings and profiles, and admins review them.

**Pages:** landing (scroll story) · register / login · compatibility test (profile) · Discover (ranked list) · Requests and matches · Chats (live, unread badges) · Flats (browse, post, view) · profile view · admin Users · admin Reports.

**Stack:** PHP 8, MySQL/MariaDB (PDO prepared statements), plain HTML, CSS and JavaScript. No frameworks.

---

## 1. Run it on your laptop (XAMPP)

1. Install XAMPP and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the whole `roommate-match` folder into `C:\xampp\htdocs\` (Mac: `/Applications/XAMPP/htdocs/`).
3. Open <http://localhost/phpmyadmin>.
   - Click **New**, name the database `roommate_match`, choose collation `utf8mb4_general_ci`, click **Create**.
   - With `roommate_match` selected: **Import** → choose `database/schema.sql` → **Go**.
   - **Import** again → choose `database/seed.sql` → **Go**.
4. Open <http://localhost/roommate-match/>.

`config/config.php` already holds XAMPP's default login (`root`, empty password), so nothing needs editing.

**Reset before a demo:** import `schema.sql` and then `seed.sql` again. This wipes everything and puts the demo data back.

> The CAPTCHA needs internet, because Google's checkbox loads from google.com. If the presenting laptop is offline, set `CAPTCHA_ENABLED` to `false` in `config/config.php` and switch it back afterwards.

---

## 1b. Run it with no installs (GitHub Codespaces)

1. Create a new repository on github.com and upload everything inside `roommate-match` (including the `.devcontainer` folder).
2. Click **Code → Codespaces → Create codespace on main**. Wait 2-3 minutes.
3. The site starts by itself. If it doesn't, type `bash start-codespace.sh` in the terminal.
4. Open the **PORTS** tab and click the globe icon next to **8080**. To share the link, right-click 8080 → **Port Visibility → Public**.

The codespace sleeps after about 30 minutes of no use; reopen it from github.com/codespaces and run the script again.

---

## 2. Put it online (free, with a shareable link)

Netlify can't run PHP, so use a free PHP + MySQL host. These steps use **InfinityFree** (free, PHP 8.3, MySQL/MariaDB, phpMyAdmin, free SSL, no ads).

1. **Sign up** at <https://www.infinityfree.com> and create a hosting account. Pick a free subdomain, for example `roomiematch.free.nf`. Activation can take a few minutes.
2. **Create the database.** In the control panel open **MySQL Databases** and create one (for example `roommate`). Write down the four values it shows: **MySQL host** (like `sql123.infinityfree.com`), **database name** (like `if0_12345678_roommate`), **username** (like `if0_12345678`) and **password** (the hosting account password, shown in the control panel).
3. **Import the tables.** Click **phpMyAdmin** next to that database, then **Import** `database/schema.sql` and after that `database/seed.sql`. (Don't create a database called `roommate_match` there. Use the one InfinityFree gave you.)
4. **Edit `config/config.php`** on your computer and replace the four `DB_` values with the ones from step 2.
5. **Upload the files.** Open **Online File Manager** (or FileZilla with the FTP details), go into the `htdocs` folder, and upload **everything inside** `roommate-match` (`index.php`, `auth/`, `css/`, ... `uploads/`) straight into `htdocs`. Don't upload the outer folder itself.
6. **Turn on HTTPS.** Under **SSL Certificates**, request the free certificate for your subdomain. It takes a few minutes.
7. **Real CAPTCHA keys (recommended).** The keys in `config/config.php` are Google's public *test* keys: they work everywhere but always pass, and show a "for testing purposes only" note. For the real thing:
   - Go to <https://www.google.com/recaptcha/admin> → **+** → type **reCAPTCHA v2 → "I'm not a robot" Checkbox**.
   - Add both domains: `localhost` and your subdomain (for example `roomiematch.free.nf`).
   - Paste the **site key** and **secret key** into `config/config.php` and upload that file again.
   - Never paste the secret key into an AI chat or a WhatsApp group.

Open your subdomain and log in with a demo account. Links work automatically both in a sub-folder (XAMPP) and at the domain root (online), because `BASE_URL` is detected in `config/config.php`.

**If something breaks online:**
- *"Database connection failed"*: one of the four `DB_` values is wrong. The host is **not** `localhost` on InfinityFree.
- *CAPTCHA always fails*: your domain isn't in the reCAPTCHA key's domain list, or the site key and secret key come from different key pairs.
- *First visit shows a short "checking your browser" screen*: that's InfinityFree's own bot protection. It's normal.

---

## 3. Demo accounts

Every seed account uses the password **`Roomie@123`**.

| Account | Email | Notes |
| --- | --- | --- |
| Admin | admin@roomiematch.test | Admin dashboard only |
| Demo user A | usera@roomiematch.test | Rohan, Indore. Already matched with Meera, so the chat isn't empty |
| Demo user B | userb@roomiematch.test | Arjun, Indore. **94% compatible with A**, and neither has swiped on the other |

- 15 users: 12 in Indore and 3 in Bhopal, so the city filter is easy to show.
- **Live match with two windows:** log in as A and send Arjun a request (he's top of Discover at 98%). Log in as B in another window, open Matches and accept: the chat opens.
- **Backup match:** Ananya and Rahul have already sent A a request, so they're waiting on A's Matches page.
- **Flats:** 4 sample rooms, and 1 open report for the admin demo.

---

## 3b. What changed from the shared brief

- **No swiping.** Discover is a ranked compatibility list with filters (city, minimum score, budget, habits) and sorting. "Send request" stores a `like` in the brief's `swipes` table and "Not for me" stores a `pass`. When both people have sent a request, `swipe_action.php` creates the match, the same as before.
- **"Okay with" questions.** Each habit (smoking, drinking, pets, tidiness, bedtime) is matched against what the *other* person accepts, in both directions. A non-smoker who is fine with smokers now scores full points with one. The new columns are `ok_smoking`, `ok_drinking`, `ok_pets`, `min_cleanliness` and `sleep_flex`. The full rules are at the top of `swipe/compatibility.php`.
- **Plain tidiness labels:** Untidy, A bit messy, Average, Tidy, Very tidy.
- **Profile picture:** pick one of 12 character icons, upload a photo (optionally turned into a cartoon avatar in the browser, `js/avatarize.js`), or have no picture. An icon is saved as `avatar:c01` in `profiles.photo`.
- **Live chat:** polling every 1.5 s, "Seen" under your last message, red unread badges on Chats and Matches (`chat/unread.php`), and a picker for emojis, 12 stickers and 6 animated GIFs.
- **New tables:** `listings` (Flats) and `reports` (moderation), plus `messages.kind` and `messages.read_at`.
- **Landing page:** character clips that turn toward the cursor, a 4-beat story pinned while you scroll, and 5 animated feature demos (`js/landing.js`). All motion stops for people who turn on reduced motion.

## 4. Demo tricks

| To show | Do this |
| --- | --- |
| Client-side validation | Submit any form with mistakes: red messages appear next to each field, with no page reload |
| Server-side validation | Add `?nojs=1` to a form page's address (for example `profile/edit_profile.php?nojs=1`). The JavaScript checks switch off, and the PHP errors appear after submitting. You can also turn JavaScript off in the browser: every form has `novalidate`, so the browser's own checks don't get in the way |
| Login lockout | Enter a wrong password 5 times for one email. It shows "2 tries left", then locks that email for 10 minutes. To unlock early, run `DELETE FROM login_attempts;` in phpMyAdmin |
| Compatibility explained | Open **Score breakdown** on a Discover card, or a match's profile for the factor-by-factor bars |
| Request and match | As B, send a request to Rohan. As A, open **Matches**: the request is waiting with a red badge. Accept it to open the chat |
| Live chat and unread badge | Two windows (A and B) on the same chat: messages, stickers and GIFs arrive within 1.5 s; the other window's Chats badge counts up |
| Moderation | As a user, Report a message, listing or profile. As admin, open **Reports** and Dismiss or Remove |
| Cartoon avatar | Edit profile → Upload photo → tick "Turn my photo into a cartoon avatar" |
| Keyboard swiping | ← to pass and → to like. You can also drag the card with a mouse or finger |
| Chat polling | Open the same chat as A and B in two windows. Messages appear within 3 seconds |
| Responsive design | DevTools → device toolbar → 360px wide (hamburger menu, stacked cards) |

---

## 5. Files and who owns them

The folder structure and every table, column and session name follow the shared brief. Each file starts with a comment naming its owner.

| Member | Files |
| --- | --- |
| **M1** Lead | `js/characters.js`, `js/landing.js`, `media/characters/`, `css/avatars|stickers|gifs/`, `config/config.php`, `config/db.php`, `includes/header.php`, `includes/footer.php`, `includes/helpers.php`, `css/style.css` (+ `logo.svg`, `default-avatar.svg`), `database/schema.sql`, `database/seed.sql`, `index.php`, `uploads/` |
| **M2** Auth | `auth/register.php`, `auth/login.php`, `auth/logout.php`, `auth/captcha_verify.php`, `includes/auth_check.php` |
| **M3** Profiles | `js/avatarize.js`, `profile/create_profile.php`, `edit_profile.php`, `view_profile.php`, `delete_account.php`, `_profile_form.php`, `profile_helpers.php`, `js/validate.js` |
| **M4** Matching + Flats | `swipe/swipe.php` (Discover), `swipe_action.php`, `matches.php` (requests + matches), `compatibility.php`, `listings/` |
| **M5** Chat + admin | `chat/inbox.php`, `conversation.php`, `send_message.php`, `unmatch.php`, `unread.php`, `chat_helpers.php`, `report.php`, `admin/dashboard.php`, `admin/delete_user.php`, `admin/reports.php`, `admin/report_action.php` |

**Additions to the brief (nothing renamed):**
- `config/config.php`: one place for the DB login, reCAPTCHA keys and base URL, so moving online means editing one file.
- `includes/helpers.php`: `e()` (safe output), flash messages, `url()`, and the option lists.
- `profile/_profile_form.php` and `profile/profile_helpers.php`: one form and one set of validation rules shared by create and edit.
- `chat/chat_helpers.php`: the "is this match mine?" check used by every chat file.
- A `login_attempts` table for the lockout.
- `ON DELETE CASCADE` on every foreign key to `users`, so deleting an account really removes the swipes, matches and messages, as the brief expects.
- `require_profile()`: users without a profile are sent to create one before swiping, because the score needs it.
- CSRF tokens on every form and AJAX call.

---

## 6. Security checklist (brief Section 8)

- [x] **Prepared statements everywhere.** No user input is ever joined into SQL. Sorting uses a fixed list of allowed `ORDER BY` options.
- [x] **`htmlspecialchars()` on all output**, via `e()`. Chat messages are drawn with `textContent` in JavaScript.
- [x] **`password_hash()` / `password_verify()`.**
- [x] **Server-side validation on every form**, with the same rules as the JavaScript.
- [x] **`require_login()` / `require_admin()` / `require_profile()`** at the top of every protected page.
- [x] **Ownership checks.** Profiles are edited by session id only. Chats are checked with `find_my_match()`. You can only delete messages you sent.
- [x] **CAPTCHA verified on the server**, with `siteverify` and the secret key.
- [x] **Extras:** `session_regenerate_id(true)` on login, a generic "Invalid email or password" message, lockout after 5 failures, CSRF tokens, httponly cookies, and uploads checked by their real file type. `.htaccess` blocks scripts in `uploads/` and blocks web access to `config/` and `database/`.

---

## 7. Likely examiner questions

- **SQL injection?** PDO prepared statements: the SQL and the data travel separately, so input can't change the query. Try searching `' OR 1=1 --` in the admin table: it finds nothing.
- **XSS?** Everything users type is passed through `htmlspecialchars()` before it is shown. A bio containing `<script>` just appears as text.
- **Passwords?** `password_hash()` (bcrypt with a random salt). We never store or see the real password.
- **Why validate twice?** JavaScript gives instant feedback, but anyone can switch it off. PHP can't be bypassed (show `?nojs=1`).
- **CAPTCHA?** Google gives the browser a token. Our PHP sends that token plus our secret key to Google's `siteverify` and only continues if Google says it's genuine.
- **Score?** Seven weighted factors adding up to 100: budget overlap 25, languages 20, bedtime 15, cleanliness 15, pets 10, smoking 7.5, drinking 7.5. City is a filter, not a factor. The brief's worked example gives 60%.
- **How does a match happen?** `swipe_action.php` saves the like, then checks whether the other person already liked you. If so, it inserts into `matches` with the smaller id first, and the UNIQUE key stops duplicates.
- **Real-time chat?** `fetch()` polls every 3 seconds. With more time: WebSockets, notifications, a location radius and photo moderation.

---

## 8. Credits

Character icons, stickers and GIFs are drawn in code (`js/characters.js`). The landing-page character clips were generated with Kling AI and cropped to remove app overlays. All seed people are fictional.
