-- v4: 19-question test + age range, religion/culture (filter only), college, visibility,
-- flat in mind, private map point + radius, communities, per-field contact sharing (PostgreSQL / Supabase)
ALTER TABLE profiles
  ALTER COLUMN sleep_slot SET DEFAULT 3, ALTER COLUMN cleanliness SET DEFAULT 3,
  ALTER COLUMN smoking SET DEFAULT 'no', ALTER COLUMN drinking SET DEFAULT 'no', ALTER COLUMN pets SET DEFAULT 'no',
  ALTER COLUMN languages SET DEFAULT '',
  ADD COLUMN IF NOT EXISTS answers TEXT,                       -- JSON {"bedtime":3,...}; skipped = absent
  ADD COLUMN IF NOT EXISTS age_min SMALLINT, ADD COLUMN IF NOT EXISTS age_max SMALLINT,
  ADD COLUMN IF NOT EXISTS religion VARCHAR(40), ADD COLUMN IF NOT EXISTS religion_pref VARCHAR(10), ADD COLUMN IF NOT EXISTS culture_note VARCHAR(300),
  ADD COLUMN IF NOT EXISTS college VARCHAR(255), ADD COLUMN IF NOT EXISTS college_status VARCHAR(10),
  ADD COLUMN IF NOT EXISTS visibility VARCHAR(10) NOT NULL DEFAULT 'everyone',
  ADD COLUMN IF NOT EXISTS flat_stage SMALLINT, ADD COLUMN IF NOT EXISTS flat_desc VARCHAR(200), ADD COLUMN IF NOT EXISTS flat_area VARCHAR(100),
  ADD COLUMN IF NOT EXISTS flat_rent INT, ADD COLUMN IF NOT EXISTS flat_photo VARCHAR(255),
  ADD COLUMN IF NOT EXISTS flat_lat DOUBLE PRECISION, ADD COLUMN IF NOT EXISTS flat_lng DOUBLE PRECISION,
  ADD COLUMN IF NOT EXISTS ref_lat DOUBLE PRECISION, ADD COLUMN IF NOT EXISTS ref_lng DOUBLE PRECISION, ADD COLUMN IF NOT EXISTS ref_label VARCHAR(120),
  ADD COLUMN IF NOT EXISTS radius_km SMALLINT,
  ADD COLUMN IF NOT EXISTS contact_email VARCHAR(150), ADD COLUMN IF NOT EXISTS instagram VARCHAR(40);
CREATE TABLE IF NOT EXISTS communities (
  id SERIAL PRIMARY KEY, name VARCHAR(60) NOT NULL, code VARCHAR(16) NOT NULL UNIQUE,
  created_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS community_members (
  community_id INT NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  PRIMARY KEY (community_id, user_id));
CREATE TABLE IF NOT EXISTS contact_shares (
  sharer_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  viewer_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  field VARCHAR(10) NOT NULL CHECK (field IN ('phone', 'email', 'instagram')),
  created_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sharer_id, viewer_id, field));
-- earlier one-way phone shares carry over
INSERT INTO contact_shares (sharer_id, viewer_id, field) SELECT sharer_id, viewer_id, 'phone' FROM phone_shares ON CONFLICT DO NOTHING;
