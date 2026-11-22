-- ==========================================================================
-- database/schema_pg.sql  —  Owner: M1 (Lead)
-- --------------------------------------------------------------------------
-- The same tables as schema.sql, written for PostgreSQL (used by the online
-- copy on Supabase). XAMPP uses schema.sql; names are identical in both.
-- Run this, then seed.sql (minus its first line), then the setval lines at
-- the end of seed.sql's PostgreSQL note.
-- ==========================================================================

DROP TABLE IF EXISTS reports, listings, photos, sessions, login_attempts, messages, matches, swipes, profiles, users CASCADE;

CREATE TABLE users (
    id             SERIAL PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    role           VARCHAR(5)   NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    created_at     TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE profiles (
    id           SERIAL PRIMARY KEY,
    user_id      INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    age          INT NOT NULL,
    gender       VARCHAR(6) NOT NULL CHECK (gender IN ('male', 'female', 'other')),
    city         VARCHAR(100) NOT NULL,
    budget_min   INT NOT NULL,
    budget_max   INT NOT NULL,
    sleep_slot   SMALLINT NOT NULL,
    cleanliness  SMALLINT NOT NULL,
    smoking      VARCHAR(3) NOT NULL CHECK (smoking  IN ('yes', 'no')),
    drinking     VARCHAR(3) NOT NULL CHECK (drinking IN ('yes', 'no')),
    pets         VARCHAR(3) NOT NULL CHECK (pets     IN ('yes', 'no')),
    languages    VARCHAR(255) NOT NULL,
    bio          TEXT,
    photo        VARCHAR(255),
    ok_smoking      VARCHAR(5) NOT NULL DEFAULT 'yes' CHECK (ok_smoking  IN ('yes', 'maybe', 'no')),
    ok_drinking     VARCHAR(5) NOT NULL DEFAULT 'yes' CHECK (ok_drinking IN ('yes', 'maybe', 'no')),
    ok_pets         VARCHAR(5) NOT NULL DEFAULT 'yes' CHECK (ok_pets     IN ('yes', 'maybe', 'no')),
    min_cleanliness SMALLINT NOT NULL DEFAULT 1,
    sleep_flex      SMALLINT NOT NULL DEFAULT 4,
    updated_at   TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_profiles_city ON profiles (city);

CREATE TABLE swipes (
    id          SERIAL PRIMARY KEY,
    swiper_id   INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    swiped_id   INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    direction   VARCHAR(4) NOT NULL CHECK (direction IN ('like', 'pass')),
    created_at  TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (swiper_id, swiped_id)
);

CREATE TABLE matches (
    id          SERIAL PRIMARY KEY,
    user1_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    user2_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at  TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user1_id, user2_id)
);

CREATE TABLE messages (
    id          SERIAL PRIMARY KEY,
    match_id    INT NOT NULL REFERENCES matches(id) ON DELETE CASCADE,
    sender_id   INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body        VARCHAR(500) NOT NULL,
    kind        VARCHAR(7) NOT NULL DEFAULT 'text' CHECK (kind IN ('text', 'sticker', 'gif')),
    read_at     TIMESTAMP(0) NULL,
    created_at  TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_messages_match ON messages (match_id, id);

CREATE TABLE login_attempts (
    id            SERIAL PRIMARY KEY,
    email         VARCHAR(150) NOT NULL,
    ip_address    VARCHAR(45)  NOT NULL,
    attempted_at  TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_attempts_email ON login_attempts (email, attempted_at);

CREATE TABLE sessions (
    id          VARCHAR(128) PRIMARY KEY,
    data        TEXT NOT NULL,
    updated_at  INT NOT NULL
);

CREATE TABLE photos (
    name        VARCHAR(100) PRIMARY KEY,
    mime        VARCHAR(20) NOT NULL,
    data        TEXT NOT NULL
);

CREATE TABLE listings (
    id              SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title           VARCHAR(100) NOT NULL,
    city            VARCHAR(100) NOT NULL,
    area            VARCHAR(100) NOT NULL,
    rent            INT NOT NULL,
    room_type       VARCHAR(7) NOT NULL CHECK (room_type IN ('private', 'shared')),
    furnished       VARCHAR(3) NOT NULL CHECK (furnished IN ('yes', 'no')),
    available_from  DATE NOT NULL,
    description     TEXT NOT NULL,
    photo           VARCHAR(255),
    status          VARCHAR(6) NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed')),
    created_at      TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_listings_city ON listings (city);

CREATE TABLE reports (
    id           SERIAL PRIMARY KEY,
    reporter_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    target_type  VARCHAR(7) NOT NULL CHECK (target_type IN ('message', 'listing', 'user')),
    target_id    INT NOT NULL,
    reason       VARCHAR(300) NOT NULL,
    status       VARCHAR(9) NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'dismissed', 'removed')),
    created_at   TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (reporter_id, target_type, target_id)
);
