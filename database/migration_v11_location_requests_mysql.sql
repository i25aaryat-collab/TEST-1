-- "Request exact location" on a flat: the owner approves or declines
CREATE TABLE IF NOT EXISTS location_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    listing_id   INT NOT NULL,
    requester_id INT NOT NULL,
    status       ENUM('pending', 'approved', 'declined') NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at   TIMESTAMP NULL,
    UNIQUE KEY uq_location_request (listing_id, requester_id),
    KEY idx_location_requests_requester (requester_id),
    CONSTRAINT fk_lr_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_lr_user FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
