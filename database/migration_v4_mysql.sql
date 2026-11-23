-- v4: 19-question test + age range, religion/culture (filter only), college, visibility,
-- flat in mind, private map point + radius, communities, per-field contact sharing (MySQL / XAMPP)
ALTER TABLE profiles
  ALTER COLUMN sleep_slot SET DEFAULT 3, ALTER COLUMN cleanliness SET DEFAULT 3,
  ALTER COLUMN smoking SET DEFAULT 'no', ALTER COLUMN drinking SET DEFAULT 'no', ALTER COLUMN pets SET DEFAULT 'no',
  MODIFY languages VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN answers TEXT,                       -- JSON {"bedtime":3,...}; skipped = absent
  ADD COLUMN age_min SMALLINT, ADD COLUMN age_max SMALLINT,
  ADD COLUMN religion VARCHAR(40), ADD COLUMN religion_pref VARCHAR(10), ADD COLUMN culture_note VARCHAR(300),
  ADD COLUMN college VARCHAR(255), ADD COLUMN college_status VARCHAR(10),
  ADD COLUMN visibility VARCHAR(10) NOT NULL DEFAULT 'everyone',
  ADD COLUMN flat_stage SMALLINT, ADD COLUMN flat_desc VARCHAR(200), ADD COLUMN flat_area VARCHAR(100),
  ADD COLUMN flat_rent INT, ADD COLUMN flat_photo VARCHAR(255),
  ADD COLUMN flat_lat DOUBLE, ADD COLUMN flat_lng DOUBLE,
  ADD COLUMN ref_lat DOUBLE, ADD COLUMN ref_lng DOUBLE, ADD COLUMN ref_label VARCHAR(120),
  ADD COLUMN radius_km SMALLINT,
  ADD COLUMN contact_email VARCHAR(150), ADD COLUMN instagram VARCHAR(40);
CREATE TABLE IF NOT EXISTS communities (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL, code VARCHAR(16) NOT NULL UNIQUE,
  created_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS community_members (
  community_id INT NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  PRIMARY KEY (community_id, user_id));
CREATE TABLE IF NOT EXISTS contact_shares (
  sharer_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  viewer_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  field VARCHAR(10) NOT NULL CHECK (field IN ('phone', 'email', 'instagram')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sharer_id, viewer_id, field));
-- earlier one-way phone shares carry over
INSERT INTO contact_shares (sharer_id, viewer_id, field) SELECT sharer_id, viewer_id, 'phone' FROM phone_shares ON DUPLICATE KEY UPDATE field = field;
