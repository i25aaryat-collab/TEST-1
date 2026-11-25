-- migration_v15_e2e_pg.sql  —  end-to-end encrypted one-to-one chats (Supabase / Postgres)
-- e2e_pub   the user's ECDH public key (base64 SPKI), shared with matches
-- e2e_priv  their private key, locked in the browser with a key from their password (base64 IV + AES-GCM)
-- e2e_salt  random salt for that lock
-- messages.body grows to TEXT: encrypted text is longer than the 500 characters typed
-- reports.evidence: a reported encrypted message, as unlocked by the reporter's own device
ALTER TABLE users ADD COLUMN IF NOT EXISTS e2e_pub TEXT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS e2e_priv TEXT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS e2e_salt VARCHAR(64) NULL;
ALTER TABLE messages ALTER COLUMN body TYPE TEXT;
ALTER TABLE reports ADD COLUMN IF NOT EXISTS evidence TEXT NULL;
