-- Add shared Laravel cache and session tables to an existing schema-v2 database.
-- Run only after verifying the target database and taking a backup.

SET @schema_v2_marker_count = (
    SELECT COUNT(*)
    FROM `migrations`
    WHERE `migration` = '2026_10_22_000000_baseline_schema_v2'
);
SET @schema_v2_core_tables = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_type = 'BASE TABLE'
      AND table_name IN ('users', 'roles', 'user_profiles', 'lessons', 'threads', 'evaluations')
);
SET @schema_v2_sql = IF(
    @schema_v2_marker_count = 1 AND @schema_v2_core_tables = 6,
    'SELECT 1',
    'SELECT * FROM `__schema_v2_baseline_marker_2026_10_22_required__`'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

CREATE TABLE IF NOT EXISTS `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` CHAR(36) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user_id` (`user_id`),
    KEY `idx_sessions_last_activity` (`last_activity`)
) ENGINE=InnoDB;
