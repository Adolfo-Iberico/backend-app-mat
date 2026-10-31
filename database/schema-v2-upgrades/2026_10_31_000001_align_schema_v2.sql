-- Data-preserving update for databases imported from the earlier schema v2.
-- Run only against a verified schema-v2 database after taking a backup.
-- This file is intentionally separate from Laravel's migration history.

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

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'full_name'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `user_profiles` ADD COLUMN `full_name` VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'phone'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `user_profiles` ADD COLUMN `phone` VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'address'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `user_profiles` ADD COLUMN `address` VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'bio'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `user_profiles` ADD COLUMN `bio` TEXT NULL',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'avatar'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `user_profiles` ADD COLUMN `avatar` VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'grade'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `user_profiles` ADD COLUMN `grade` VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'salon'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `user_profiles` ADD COLUMN `salon` VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'threads'
      AND column_name = 'author_id'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `threads` ADD COLUMN `author_id` CHAR(36) NULL',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_collation = (
    SELECT table_collation
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'lessons'
);
SET @schema_v2_sql = CONCAT(
    'CREATE TABLE IF NOT EXISTS `lesson_segments` (',
    '`id` CHAR(36) NOT NULL,',
    '`lesson_id` CHAR(36) NOT NULL,',
    '`title` VARCHAR(255) NOT NULL,',
    '`type` ENUM(''text'', ''video'', ''pdf'', ''quiz'', ''link'') NOT NULL,',
    '`content` TEXT NULL,',
    '`url` VARCHAR(255) NULL,',
    '`order` INT NOT NULL DEFAULT 0,',
    '`duration_seconds` INT NULL,',
    '`created_at` TIMESTAMP NULL,',
    '`updated_at` TIMESTAMP NULL,',
    'PRIMARY KEY (`id`),',
    'KEY `idx_lesson_segments_lesson_order` (`lesson_id`, `order`),',
    'CONSTRAINT `fk_lesson_segments_lesson` ',
    'FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=',
    @schema_v2_collation
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_collation = (
    SELECT table_collation
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
);
SET @schema_v2_sql = CONCAT(
    'CREATE TABLE IF NOT EXISTS `submitted_works` (',
    '`id` CHAR(36) NOT NULL,',
    '`student_id` CHAR(36) NOT NULL,',
    '`lesson_id` CHAR(36) NULL,',
    '`evaluation_id` CHAR(36) NULL,',
    '`work_type` VARCHAR(255) NOT NULL,',
    '`title` VARCHAR(255) NOT NULL,',
    '`description` TEXT NULL,',
    '`status` VARCHAR(255) NOT NULL DEFAULT ''pending'',',
    '`score` INT NULL,',
    '`max_score` INT NOT NULL DEFAULT 20,',
    '`teacher_feedback` TEXT NULL,',
    '`attachments` LONGTEXT NULL,',
    '`submitted_at` TIMESTAMP NULL,',
    '`graded_at` TIMESTAMP NULL,',
    '`created_at` TIMESTAMP NULL,',
    '`updated_at` TIMESTAMP NULL,',
    'PRIMARY KEY (`id`),',
    'KEY `submitted_works_evaluation_id_index` (`evaluation_id`),',
    'KEY `submitted_works_lesson_id_index` (`lesson_id`),',
    'KEY `submitted_works_student_id_status_index` (`student_id`, `status`),',
    'KEY `submitted_works_student_type_index` (`student_id`, `work_type`),',
    'CONSTRAINT `submitted_works_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),',
    'CONSTRAINT `submitted_works_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`),',
    'CONSTRAINT `submitted_works_evaluation_id_foreign` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=',
    @schema_v2_collation
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_sql = CONCAT(
    'CREATE TABLE IF NOT EXISTS `announcements` (',
    '`id` CHAR(36) NOT NULL,',
    '`title` VARCHAR(255) NOT NULL,',
    '`body` TEXT NOT NULL,',
    '`type` VARCHAR(255) NOT NULL DEFAULT ''info'',',
    '`is_active` TINYINT(1) NOT NULL DEFAULT 1,',
    '`created_by` CHAR(36) NOT NULL,',
    '`created_at` TIMESTAMP NULL,',
    '`updated_at` TIMESTAMP NULL,',
    'PRIMARY KEY (`id`),',
    'KEY `announcements_created_by_foreign` (`created_by`),',
    'CONSTRAINT `fk_announcements_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=',
    @schema_v2_collation
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'threads'
      AND index_name = 'idx_threads_author'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `threads` ADD INDEX `idx_threads_author` (`author_id`)',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SET @schema_v2_exists = (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'threads'
      AND column_name = 'author_id'
      AND referenced_table_name = 'users'
      AND referenced_column_name = 'id'
);
SET @schema_v2_sql = IF(
    @schema_v2_exists = 0,
    'ALTER TABLE `threads` ADD CONSTRAINT `fk_threads_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE schema_v2_stmt FROM @schema_v2_sql;
EXECUTE schema_v2_stmt;
DEALLOCATE PREPARE schema_v2_stmt;

SELECT 'schema v2 update complete' AS status;
