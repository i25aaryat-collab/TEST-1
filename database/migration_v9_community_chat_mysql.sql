-- migration_v9_community_chat_mysql.sql  —  group chat inside a community (XAMPP / MySQL)
-- communities/chat.php also creates this table by itself the first time it is needed.
CREATE TABLE IF NOT EXISTS community_messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    sender_id    INT NOT NULL,
    body         VARCHAR(500) NOT NULL,
    kind         ENUM('text', 'sticker', 'gif') NOT NULL DEFAULT 'text',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_community_messages (community_id, id),
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
