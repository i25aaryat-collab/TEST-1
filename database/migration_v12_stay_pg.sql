-- How long someone wants to live together (1..5: 1-3 months, 3-6, 6-12, 1-2 years, 2+ years)
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS stay SMALLINT NULL CHECK (stay BETWEEN 1 AND 5);
