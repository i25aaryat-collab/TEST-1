-- Place-search cache for geo.php (Nominatim / Photon answers kept 30 days)
CREATE TABLE IF NOT EXISTS geo_cache (
    k          VARCHAR(64) PRIMARY KEY,
    body       MEDIUMTEXT NOT NULL,
    created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
