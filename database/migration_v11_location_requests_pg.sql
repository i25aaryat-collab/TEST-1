-- "Request exact location" on a flat: the owner approves or declines
CREATE TABLE IF NOT EXISTS location_requests (
    id           SERIAL PRIMARY KEY,
    listing_id   INT NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
    requester_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status       VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'declined')),
    created_at   TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at   TIMESTAMP(0) NULL,
    UNIQUE (listing_id, requester_id)
);
CREATE INDEX IF NOT EXISTS idx_location_requests_requester ON location_requests (requester_id);
