-- ==========================================================================
-- database/schema.sql  —  Owner: M1 (Lead)
-- --------------------------------------------------------------------------
-- Creates every table RoomieMatch needs.
--
-- HOW TO RUN (same steps on XAMPP and on the online host):
--   1. phpMyAdmin -> create a database called  roommate_match
--      (collation utf8mb4_general_ci). Online, use the database the host
--      gives you instead.
--   2. Click that database -> Import -> choose this file -> Go.
--   3. Import database/seed.sql the same way.
--
-- Running this file again wipes all data (handy for a fresh demo).
-- Table and column names come from the shared brief: DO NOT RENAME THEM.
-- ==========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reports, listings, photos, sessions, login_attempts, messages, matches, swipes, profiles, users;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------------
-- users: one row per account (login details only)
-- --------------------------------------------------------------------------
CREATE TABLE users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,              -- output of password_hash(), never plain text
    role           ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- profiles: one per user, holds the preferences used for matching
-- ON DELETE CASCADE: deleting a user deletes their profile automatically
-- --------------------------------------------------------------------------
CREATE TABLE profiles (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL UNIQUE,
    age          INT NOT NULL,                          -- 18 to 80
    gender       ENUM('male','female','other') NOT NULL,
    city         VARCHAR(100) NOT NULL,                 -- a filter on the swipe page, not scored
    budget_min   INT NOT NULL,                          -- monthly rent in rupees
    budget_max   INT NOT NULL,                          -- always > budget_min
    sleep_slot   TINYINT NOT NULL,                      -- 1 = 8-10 PM ... 5 = 4-6 AM
    cleanliness  TINYINT NOT NULL,                      -- 1 untidy ... 5 very tidy
    smoking      ENUM('yes','no') NOT NULL,
    drinking     ENUM('yes','no') NOT NULL,
    pets         ENUM('yes','no') NOT NULL,
    languages    VARCHAR(255) NOT NULL,                 -- comma-separated, e.g. Hindi,English
    bio          TEXT,                                  -- max 300 characters (checked in PHP)
    photo        VARCHAR(255) NULL,                     -- file in uploads/, "avatar:c01" (a picked icon), or NULL
    -- What this person is OK with in a flatmate (added on top of the brief's columns):
    ok_smoking      ENUM('yes','maybe','no') NOT NULL DEFAULT 'yes',   -- okay living with a smoker?
    ok_drinking     ENUM('yes','maybe','no') NOT NULL DEFAULT 'yes',   -- okay with a flatmate who drinks?
    ok_pets         ENUM('yes','maybe','no') NOT NULL DEFAULT 'yes',   -- okay with a flatmate's pets?
    min_cleanliness TINYINT NOT NULL DEFAULT 1,           -- messiest flatmate they accept (1-5)
    sleep_flex      TINYINT NOT NULL DEFAULT 4,           -- bedtime gap they accept, in 2-hour slots (4 = any)
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_profiles_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- swipes: every like or pass. The UNIQUE key means nobody can be swiped
-- on twice by the same person.
-- --------------------------------------------------------------------------
CREATE TABLE swipes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    swiper_id   INT NOT NULL,                           -- who swiped
    swiped_id   INT NOT NULL,                           -- who was swiped on
    direction   ENUM('like','pass') NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_swipe (swiper_id, swiped_id),
    CONSTRAINT fk_swipes_swiper FOREIGN KEY (swiper_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_swipes_swiped FOREIGN KEY (swiped_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- matches: created when two users have liked each other.
-- user1_id is ALWAYS the smaller id, so a pair can only be stored one way.
-- --------------------------------------------------------------------------
CREATE TABLE matches (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user1_id    INT NOT NULL,                           -- smaller id
    user2_id    INT NOT NULL,                           -- larger id
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_match (user1_id, user2_id),
    CONSTRAINT fk_matches_user1 FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_user2 FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- messages: chat lines inside one match. Unmatching deletes the match,
-- and CASCADE deletes its messages too.
-- --------------------------------------------------------------------------
CREATE TABLE messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    match_id    INT NOT NULL,
    sender_id   INT NOT NULL,
    body        VARCHAR(500) NOT NULL,
    kind        ENUM('text','sticker','gif') NOT NULL DEFAULT 'text',  -- sticker/gif: body is its id
    read_at     TIMESTAMP NULL DEFAULT NULL,           -- NULL = not read yet (drives the unread badge)
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_match  FOREIGN KEY (match_id)  REFERENCES matches(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id)   ON DELETE CASCADE,
    INDEX idx_messages_match (match_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- login_attempts (extra table, used by M2): records wrong passwords so an
-- email is locked for a few minutes after 5 wrong tries.
-- --------------------------------------------------------------------------
CREATE TABLE login_attempts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(150) NOT NULL,
    ip_address    VARCHAR(45)  NOT NULL,
    attempted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts_email (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- sessions + photos (extra tables): only used when STORE_IN_DB is on, i.e. on
-- serverless hosts like Vercel that can't keep files between requests.
-- --------------------------------------------------------------------------
CREATE TABLE sessions (
    id          VARCHAR(128) PRIMARY KEY,
    data        TEXT NOT NULL,
    updated_at  INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE photos (
    name        VARCHAR(100) PRIMARY KEY,
    mime        VARCHAR(20) NOT NULL,
    data        MEDIUMTEXT NOT NULL                     -- the image, base64-encoded
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- listings: rooms offered by people who already have a flat
-- --------------------------------------------------------------------------
CREATE TABLE listings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    title           VARCHAR(100) NOT NULL,
    city            VARCHAR(100) NOT NULL,
    area            VARCHAR(100) NOT NULL,                  -- neighbourhood, e.g. Vijay Nagar
    rent            INT NOT NULL,                           -- per month, for this room
    room_type       ENUM('private','shared') NOT NULL,
    furnished       ENUM('yes','no') NOT NULL,
    available_from  DATE NOT NULL,
    description     TEXT NOT NULL,
    photo           VARCHAR(255) NULL,
    status          ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_listings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_listings_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- reports: a user flags a message, listing or profile; admins review them
-- --------------------------------------------------------------------------
CREATE TABLE reports (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id  INT NOT NULL,
    target_type  ENUM('message','listing','user') NOT NULL,
    target_id    INT NOT NULL,
    reason       VARCHAR(300) NOT NULL,
    status       ENUM('open','dismissed','removed') NOT NULL DEFAULT 'open',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reports_user FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_report (reporter_id, target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
