-- Shareable flatmate type + invite links
ALTER TABLE users ADD COLUMN IF NOT EXISTS ref_code VARCHAR(12) NULL UNIQUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by INT NULL REFERENCES users(id) ON DELETE SET NULL;
