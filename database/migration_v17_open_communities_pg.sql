-- migration_v17_open_communities_pg.sql  —  communities anyone can ask to join (Postgres)
-- is_public = 1: listed under "Find communities"; joining needs a yes from EVERY member.
-- Invite links (join.php) still let people in directly.
ALTER TABLE communities ADD COLUMN IF NOT EXISTS is_public SMALLINT NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS community_requests (
    id           SERIAL PRIMARY KEY,
    community_id INT NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
    user_id      INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message      VARCHAR(200) NULL,
    status       VARCHAR(9) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'declined')),
    created_at   TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at   TIMESTAMP(0) NULL,
    UNIQUE (community_id, user_id)
);
CREATE TABLE IF NOT EXISTS community_votes (
    request_id INT NOT NULL REFERENCES community_requests(id) ON DELETE CASCADE,
    voter_id   INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    vote       SMALLINT NOT NULL CHECK (vote IN (0, 1)),
    created_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, voter_id)
);
