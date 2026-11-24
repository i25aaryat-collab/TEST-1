-- migration_v13_password_resets_pg.sql  —  "Forgot password" links (Supabase / Postgres)
-- auth/forgot.php also creates this table by itself the first time it is needed.
-- Only a SHA-256 of the token is stored, so a leaked table can't be used to reset anyone.
CREATE TABLE IF NOT EXISTS password_resets (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  CHAR(64) NOT NULL UNIQUE,
    expires_at  TIMESTAMP(0) NOT NULL,
    used_at     TIMESTAMP(0) NULL,
    created_at  TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets (user_id, created_at);
