CREATE TABLE IF NOT EXISTS wearcast_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token CHAR(32) NOT NULL UNIQUE,
    email VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wearcast_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(120) NOT NULL,
    prefecture_name VARCHAR(120) NOT NULL,
    region_name VARCHAR(120) NOT NULL,
    office_code CHAR(6) NOT NULL,
    area_code CHAR(6) NOT NULL,
    lat DECIMAL(10, 7) NULL,
    lng DECIMAL(10, 7) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wearcast_locations_user
        FOREIGN KEY (user_id) REFERENCES wearcast_users(id)
        ON DELETE CASCADE,
    KEY idx_wearcast_locations_user_order (user_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wearcast_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NULL,
    record_date DATE NOT NULL,
    weather_group VARCHAR(20) NOT NULL,
    weather_label VARCHAR(255) NOT NULL,
    weather_code VARCHAR(10) NULL,
    temp_max DECIMAL(4, 1) NOT NULL,
    temp_min DECIMAL(4, 1) NOT NULL,
    outfit_category VARCHAR(80) NOT NULL,
    comfort_vote ENUM('cold', 'just', 'hot') NOT NULL,
    comment_text TEXT NULL,
    free_note TEXT NULL,
    image_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wearcast_records_user
        FOREIGN KEY (user_id) REFERENCES wearcast_users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_wearcast_records_location
        FOREIGN KEY (location_id) REFERENCES wearcast_locations(id)
        ON DELETE SET NULL,
    UNIQUE KEY uniq_wearcast_records_daily (user_id, location_id, record_date),
    KEY idx_wearcast_records_user_date (user_id, record_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
