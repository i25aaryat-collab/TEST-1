-- Community topic + short description (PostgreSQL / Supabase)
ALTER TABLE communities ADD COLUMN IF NOT EXISTS topic VARCHAR(30), ADD COLUMN IF NOT EXISTS about VARCHAR(200);
-- Gender: "prefer not to say" + own words
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS gender_text VARCHAR(40);
ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_gender_check;
ALTER TABLE profiles ADD CONSTRAINT profiles_gender_check CHECK (gender IN ('male','female','nosay','other'));
