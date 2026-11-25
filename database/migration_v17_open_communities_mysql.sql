ALTER TABLE communities ADD COLUMN is_public TINYINT NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS community_requests (
    id INT AUTO_INCREMENT PRIMARY KEY, community_id INT NOT NULL, user_id INT NOT NULL, message VARCHAR(200) NULL,
    status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, decided_at TIMESTAMP NULL,
    UNIQUE KEY uq_creq (community_id, user_id),
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS community_votes (
    request_id INT NOT NULL, voter_id INT NOT NULL, vote TINYINT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, voter_id),
    FOREIGN KEY (request_id) REFERENCES community_requests(id) ON DELETE CASCADE, FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE CASCADE);
