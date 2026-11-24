-- How many people someone wants to live with (1, 2, 3, 4 = 4 or more), and whether they're still looking
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS flatmates SMALLINT NULL CHECK (flatmates BETWEEN 1 AND 4);
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS looking SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE flat_groups ADD COLUMN IF NOT EXISTS settled_at TIMESTAMP(0) NULL;
