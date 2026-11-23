-- migration_v9_community_chat_pg.sql  —  group chat inside a community (Supabase / Postgres)
-- communities/chat.php also creates this table by itself the first time it is needed.
CREATE TABLE IF NOT EXISTS community_messages (
    id           SERIAL PRIMARY KEY,
    community_id INT NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
    sender_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body         VARCHAR(500) NOT NULL,
    kind         VARCHAR(7) NOT NULL DEFAULT 'text' CHECK (kind IN ('text', 'sticker', 'gif')),
    created_at   TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_community_messages ON community_messages (community_id, id);
