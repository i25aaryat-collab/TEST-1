-- Place-search cache for geo.php (Nominatim / Photon answers kept 30 days)
CREATE TABLE IF NOT EXISTS geo_cache (
    k          VARCHAR(64) PRIMARY KEY,
    body       TEXT NOT NULL,
    created_at BIGINT NOT NULL
);
