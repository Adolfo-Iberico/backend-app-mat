-- =====================================================================
--  MathFlow / KawsayMath  ·  schema.sql  ·  v2 (optimizado)
--  30 tablas + 2 vistas   (la v1 tenia 46 tablas)
--  Motor: MariaDB 10.4+ / MySQL 8  ·  InnoDB  ·  utf8mb4_unicode_ci
--
--  USO
--   1) Instalacion nueva:      importar este archivo y listo.
--   2) Actualizar desde v1:    importar este archivo y luego ejecutar
--                              migrate_data.sql (copia los datos).
--  NO ejecutar php artisan migrate sobre esta BD: el baseline SQL v2 no
--  coincide con el historial Laravel actual; ver backend-app-mat/DEPLOYMENT.md.
--  Cambia el nombre de la BD si lo necesitas.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `mentematica_v2`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mentematica_v2`;

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
--  1. ACCESO Y USUARIOS
-- =====================================================================

CREATE TABLE `roles` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        ENUM('admin','teacher','student','parent','coordinador','director') NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB;

-- users: se quitaron `grade` e `institution` (el grado sale de salones.grade
-- y la institucion de institution_configs).
CREATE TABLE `users` (
  `id`                CHAR(36) NOT NULL,
  `email`             VARCHAR(255) NOT NULL,
  `password`          VARCHAR(255) NULL,
  `full_name`         VARCHAR(255) NOT NULL,
  `dni`               VARCHAR(8) NULL,
  `role_id`           BIGINT UNSIGNED NOT NULL,
  `salon_id`          CHAR(36) NULL,
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `last_login`        TIMESTAMP NULL,
  `profile_image`     VARCHAR(255) NULL,
  `google_id`         VARCHAR(255) NULL,
  `google_token`      VARCHAR(255) NULL,
  `provider`          VARCHAR(255) NOT NULL DEFAULT 'email',
  `email_verified_at` TIMESTAMP NULL,
  `remember_token`    VARCHAR(100) NULL,
  `created_at`        TIMESTAMP NULL,
  `updated_at`        TIMESTAMP NULL,
  `deleted_at`        TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_dni` (`dni`),
  UNIQUE KEY `uq_users_google_id` (`google_id`),
  KEY `idx_users_role_active` (`role_id`,`is_active`),
  KEY `idx_users_salon` (`salon_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB;

-- user_profiles = student_profiles + teacher_profiles.
-- Se quitaron columnas derivables (promedio, lecciones completadas, tiempo
-- total, insignias): ahora salen de la vista v_student_stats y de user_achievements.
CREATE TABLE `user_profiles` (
  `user_id`            CHAR(36) NOT NULL,
  `academic_level`     ENUM('basic','intermediate','advanced') NULL,
  `xp`                 BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `level`              INT UNSIGNED NOT NULL DEFAULT 1,
  `rank_points`        INT UNSIGNED NOT NULL DEFAULT 0,
  `current_streak`     INT NOT NULL DEFAULT 0,
  `last_activity_date` DATE NULL,
  `department`         VARCHAR(255) NULL,
  `specialization`     VARCHAR(255) NULL,
  `full_name`          VARCHAR(255) NULL,
  `phone`              VARCHAR(255) NULL,
  `address`            VARCHAR(255) NULL,
  `bio`                TEXT NULL,
  `avatar`             VARCHAR(255) NULL,
  `grade`              VARCHAR(255) NULL,
  `salon`              VARCHAR(255) NULL,
  `years_experience`   INT NOT NULL DEFAULT 0,
  `created_at`         TIMESTAMP NULL,
  `updated_at`         TIMESTAMP NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `parent_student` (
  `parent_id`  CHAR(36) NOT NULL,
  `student_id` CHAR(36) NOT NULL,
  `created_at` TIMESTAMP NULL,
  PRIMARY KEY (`parent_id`,`student_id`),
  KEY `idx_ps_student` (`student_id`),
  CONSTRAINT `fk_ps_parent`  FOREIGN KEY (`parent_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  2. ESTRUCTURA ACADEMICA
-- =====================================================================

CREATE TABLE `academic_periods` (
  `id`          CHAR(36) NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `start_date`  DATETIME NOT NULL,
  `end_date`    DATETIME NOT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 0,
  `description` VARCHAR(255) NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_period_name_start` (`name`,`start_date`)   -- evita duplicados de seeders
) ENGINE=InnoDB;

CREATE TABLE `salones` (
  `id`                 CHAR(36) NOT NULL,
  `grade`              VARCHAR(255) NOT NULL,
  `section`            VARCHAR(255) NOT NULL,
  `academic_period_id` CHAR(36) NULL,
  `coordinator_id`     CHAR(36) NULL,
  `created_at`         TIMESTAMP NULL,
  `updated_at`         TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salon` (`academic_period_id`,`grade`,`section`),
  KEY `idx_salones_coord` (`coordinator_id`),
  CONSTRAINT `fk_salones_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_salones_coord`  FOREIGN KEY (`coordinator_id`)     REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- FK circular users <-> salones: se agrega despues de crear ambas tablas
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_salon` FOREIGN KEY (`salon_id`) REFERENCES `salones` (`id`) ON DELETE SET NULL;

-- enrollments se elimino: los alumnos de un curso son los de su salon
-- (users.salon_id -> salones -> courses).
CREATE TABLE `courses` (
  `id`          CHAR(36) NOT NULL,
  `salon_id`    CHAR(36) NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `code`        VARCHAR(20) NULL,
  `description` TEXT NULL,
  `teacher_id`  CHAR(36) NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courses_code` (`code`),
  KEY `idx_courses_salon` (`salon_id`),
  KEY `idx_courses_teacher` (`teacher_id`),
  CONSTRAINT `fk_courses_salon`   FOREIGN KEY (`salon_id`)   REFERENCES `salones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_courses_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `attendance` (
  `id`          CHAR(36) NOT NULL,
  `course_id`   CHAR(36) NOT NULL,
  `student_id`  CHAR(36) NOT NULL,
  `date`        DATE NOT NULL,
  `status`      ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `note`        VARCHAR(255) NULL,
  `recorded_by` CHAR(36) NOT NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance` (`course_id`,`student_id`,`date`),
  KEY `idx_att_student` (`student_id`),
  KEY `idx_att_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_att_course`   FOREIGN KEY (`course_id`)   REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_student`  FOREIGN KEY (`student_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `academic_events` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     CHAR(36) NOT NULL,
  `course_id`   CHAR(36) NULL,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `start_date`  DATE NOT NULL,
  `end_date`    DATE NOT NULL,
  `start_time`  TIME NULL,
  `end_time`    TIME NULL,
  `type`        VARCHAR(50) NOT NULL DEFAULT 'activity',
  `color`       VARCHAR(20) NULL,
  `all_day`     TINYINT(1) NOT NULL DEFAULT 0,
  `is_public`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_user` (`user_id`),
  KEY `idx_events_course` (`course_id`),
  KEY `idx_events_start` (`start_date`),
  CONSTRAINT `fk_events_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_events_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
--  3. CONTENIDO: LECCIONES
-- =====================================================================

CREATE TABLE `lessons` (
  `id`             CHAR(36) NOT NULL,
  `title`          VARCHAR(255) NOT NULL,
  `description`    TEXT NULL,
  `content`        LONGTEXT NOT NULL,
  `teacher_id`     CHAR(36) NOT NULL,
  `course_id`      CHAR(36) NULL,
  `grade`          VARCHAR(20) NULL,
  `unit`           VARCHAR(255) NULL,
  `topic`          VARCHAR(255) NULL,
  `difficulty`     ENUM('basic','intermediate','advanced') NOT NULL DEFAULT 'basic',
  `tags`           JSON NULL,
  `estimated_time` INT NOT NULL DEFAULT 45,
  `is_published`   TINYINT(1) NOT NULL DEFAULT 0,
  `published_at`   TIMESTAMP NULL,
  `resources`      JSON NULL,           -- [{type,url,title}] pdf / video / link
  `order`          INT NOT NULL DEFAULT 0,
  `views_count`    INT NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  `deleted_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lessons_teacher` (`teacher_id`),
  KEY `idx_lessons_course` (`course_id`),
  KEY `idx_lessons_grade_unit` (`grade`,`unit`),
  KEY `idx_lessons_pub_grade` (`is_published`,`grade`),
  CONSTRAINT `fk_lessons_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_lessons_course`  FOREIGN KEY (`course_id`)  REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `lesson_segments` (
  `id`               CHAR(36) NOT NULL,
  `lesson_id`        CHAR(36) NOT NULL,
  `title`            VARCHAR(255) NOT NULL,
  `type`             ENUM('text','video','pdf','quiz','link') NOT NULL,
  `content`          TEXT NULL,
  `url`              VARCHAR(255) NULL,
  `order`            INT NOT NULL DEFAULT 0,
  `duration_seconds` INT NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lesson_segments_lesson_order` (`lesson_id`,`order`),
  CONSTRAINT `fk_lesson_segments_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `lesson_progress` (
  `id`            CHAR(36) NOT NULL,
  `user_id`       CHAR(36) NOT NULL,
  `lesson_id`     CHAR(36) NOT NULL,
  `progress`      INT NOT NULL DEFAULT 0,
  `status`        ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  `completed_at`  TIMESTAMP NULL,
  `time_spent`    INT NOT NULL DEFAULT 0,
  `last_position` INT NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NULL,
  `updated_at`    TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_progress_user_lesson` (`user_id`,`lesson_id`),
  KEY `idx_progress_lesson_status` (`lesson_id`,`status`),
  CONSTRAINT `fk_progress_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_progress_range` CHECK (`progress` BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- =====================================================================
--  4. EVALUACIONES  (examenes, quizzes, tareas, practicas y juegos)
--     evaluations = evaluations + exams + games
--     evaluation_results = evaluation_results + exam_attempts + game_submissions
--     student_answers sin user_id (se obtiene por evaluation_results)
-- =====================================================================

CREATE TABLE `evaluations` (
  `id`                  CHAR(36) NOT NULL,
  `title`               VARCHAR(255) NOT NULL,
  `description`         TEXT NULL,
  `teacher_id`          CHAR(36) NOT NULL,
  `lesson_id`           CHAR(36) NULL,
  `course_id`           CHAR(36) NULL,
  `type`                ENUM('exam','quiz','homework','practice','game') NOT NULL DEFAULT 'quiz',
  `difficulty`          ENUM('basic','intermediate','advanced') NOT NULL DEFAULT 'basic',
  `unit`                VARCHAR(255) NULL,
  `time_limit`          INT NULL DEFAULT 30,            -- minutos; NULL = sin limite
  `due_date`            TIMESTAMP NULL,
  `is_published`        TINYINT(1) NOT NULL DEFAULT 0,
  `published_at`        TIMESTAMP NULL,
  `is_active`           TINYINT(1) NOT NULL DEFAULT 1,
  `auto_correct`        TINYINT(1) NOT NULL DEFAULT 1,
  `randomize_questions` TINYINT(1) NOT NULL DEFAULT 0,
  `max_attempts`        INT NOT NULL DEFAULT 1,
  `total_questions`     INT NOT NULL DEFAULT 0,         -- contador cacheado
  `total_points`        INT NOT NULL DEFAULT 0,         -- contador cacheado
  `external_url`        VARCHAR(255) NULL,              -- solo type='game' (Quizizz, etc.)
  `pin`                 VARCHAR(50) NULL,               -- solo type='game'
  `created_at`          TIMESTAMP NULL,
  `updated_at`          TIMESTAMP NULL,
  `deleted_at`          TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eval_teacher` (`teacher_id`),
  KEY `idx_eval_lesson` (`lesson_id`),
  KEY `idx_eval_course` (`course_id`),
  KEY `idx_eval_type_pub` (`type`,`is_published`),
  CONSTRAINT `fk_eval_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_eval_lesson`  FOREIGN KEY (`lesson_id`)  REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eval_course`  FOREIGN KEY (`course_id`)  REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `questions` (
  `id`             CHAR(36) NOT NULL,
  `evaluation_id`  CHAR(36) NOT NULL,
  `type`           ENUM('multiple_choice','fill_blank','drag_drop','formula') NOT NULL,
  `question_text`  TEXT NOT NULL,
  `options`        JSON NULL,
  `correct_answer` TEXT NOT NULL,        -- texto de la opcion correcta
  `explanation`    TEXT NULL,
  `points`         INT NOT NULL DEFAULT 1,
  `order`          INT NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  `deleted_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_questions_eval_order` (`evaluation_id`,`order`),
  CONSTRAINT `fk_questions_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `evaluation_results` (
  `id`               CHAR(36) NOT NULL,
  `user_id`          CHAR(36) NOT NULL,
  `evaluation_id`    CHAR(36) NOT NULL,
  `attempt_number`   INT NOT NULL DEFAULT 1,
  `score`            DOUBLE NOT NULL DEFAULT 0,
  `max_score`        DOUBLE NOT NULL,
  `correct_answers`  INT NOT NULL DEFAULT 0,
  `total_questions`  INT NOT NULL DEFAULT 0,
  `time_taken`       INT NOT NULL DEFAULT 0,             -- segundos
  `status`           ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `started_at`       TIMESTAMP NULL,
  `completed_at`     TIMESTAMP NULL,
  `tab_switch_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `cheat_log`        JSON NULL,                          -- antes en exam_attempts
  `screenshot_url`   VARCHAR(255) NULL,                  -- antes en game_submissions
  `teacher_feedback` VARCHAR(255) NULL,
  `xp_awarded`       INT NOT NULL DEFAULT 0,
  `graded_at`        TIMESTAMP NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_result_attempt` (`user_id`,`evaluation_id`,`attempt_number`),
  KEY `idx_result_eval_status` (`evaluation_id`,`status`),
  KEY `idx_result_user_status` (`user_id`,`status`,`created_at`),
  CONSTRAINT `fk_result_user` FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_result_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_result_attempt` CHECK (`attempt_number` >= 1)
) ENGINE=InnoDB;

CREATE TABLE `student_answers` (
  `id`                   CHAR(36) NOT NULL,
  `evaluation_result_id` CHAR(36) NOT NULL,
  `question_id`          CHAR(36) NOT NULL,
  `answer`               TEXT NOT NULL,
  `is_correct`           TINYINT(1) NOT NULL DEFAULT 0,
  `points_earned`        DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `created_at`           TIMESTAMP NULL,
  `updated_at`           TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_answer_result_question` (`evaluation_result_id`,`question_id`),
  KEY `idx_answers_question` (`question_id`),
  CONSTRAINT `fk_answers_result`   FOREIGN KEY (`evaluation_result_id`) REFERENCES `evaluation_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`)          REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `submitted_works` (
  `id`              CHAR(36) NOT NULL,
  `student_id`      CHAR(36) NOT NULL,
  `lesson_id`       CHAR(36) NULL,
  `evaluation_id`   CHAR(36) NULL,
  `work_type`       VARCHAR(255) NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `description`     TEXT NULL,
  `status`          VARCHAR(255) NOT NULL DEFAULT 'pending',
  `score`           INT NULL,
  `max_score`       INT NOT NULL DEFAULT 20,
  `teacher_feedback` TEXT NULL,
  `attachments`     LONGTEXT NULL,
  `submitted_at`    TIMESTAMP NULL,
  `graded_at`       TIMESTAMP NULL,
  `created_at`      TIMESTAMP NULL,
  `updated_at`      TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `submitted_works_evaluation_id_index` (`evaluation_id`),
  KEY `submitted_works_lesson_id_index` (`lesson_id`),
  KEY `submitted_works_student_id_status_index` (`student_id`,`status`),
  KEY `submitted_works_student_type_index` (`student_id`,`work_type`),
  CONSTRAINT `submitted_works_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  CONSTRAINT `submitted_works_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`),
  CONSTRAINT `submitted_works_evaluation_id_foreign` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`)
) ENGINE=InnoDB;

-- =====================================================================
--  5. GAMIFICACION
-- =====================================================================

-- name / description son JSON por idioma: {"es":"...","en":"...","qu":"..."}
-- Agregar un idioma nuevo ya no requiere ALTER TABLE.
CREATE TABLE `achievements` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(100) NOT NULL,
  `name`        JSON NOT NULL,
  `description` JSON NOT NULL,
  `icon`        VARCHAR(50) NOT NULL,
  `xp_reward`   INT UNSIGNED NOT NULL DEFAULT 0,
  `category`    VARCHAR(50) NOT NULL DEFAULT 'general',
  `criteria`    JSON NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_achievements_slug` (`slug`)
) ENGINE=InnoDB;

CREATE TABLE `user_achievements` (
  `user_id`        CHAR(36) NOT NULL,
  `achievement_id` BIGINT UNSIGNED NOT NULL,
  `unlocked_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`achievement_id`),
  KEY `idx_ua_achievement` (`achievement_id`),
  CONSTRAINT `fk_ua_user`        FOREIGN KEY (`user_id`)        REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ua_achievement` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  6. COMUNICACION
--     threads/posts = conversations+messages + forum_threads+forum_posts
-- =====================================================================

CREATE TABLE `threads` (
  `id`              CHAR(36) NOT NULL,
  `type`            ENUM('direct','forum') NOT NULL DEFAULT 'forum',
  `title`           VARCHAR(255) NULL,                 -- solo foros
  `created_by`      CHAR(36) NOT NULL,                 -- docente que abre el hilo
  `author_id`       CHAR(36) NULL,
  `student_id`      CHAR(36) NULL,                     -- solo type='direct'
  `lesson_id`       CHAR(36) NULL,
  `evaluation_id`   CHAR(36) NULL,
  `status`          ENUM('open','closed') NOT NULL DEFAULT 'open',
  `last_message_at` TIMESTAMP NULL,
  `created_at`      TIMESTAMP NULL,
  `updated_at`      TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_threads_type_creator` (`type`,`created_by`),
  KEY `idx_threads_author` (`author_id`),
  KEY `idx_threads_student` (`student_id`),
  KEY `idx_threads_lesson` (`lesson_id`),
  KEY `idx_threads_eval` (`evaluation_id`),
  KEY `idx_threads_last_msg` (`last_message_at`),
  CONSTRAINT `fk_threads_creator` FOREIGN KEY (`created_by`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_threads_author`  FOREIGN KEY (`author_id`)     REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_threads_student` FOREIGN KEY (`student_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_threads_lesson`  FOREIGN KEY (`lesson_id`)     REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_threads_eval`    FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `posts` (
  `id`         CHAR(36) NOT NULL,
  `thread_id`  CHAR(36) NOT NULL,
  `user_id`    CHAR(36) NOT NULL,
  `body`       TEXT NOT NULL,
  `read_at`    TIMESTAMP NULL,                         -- solo mensajes directos
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_posts_thread_created` (`thread_id`,`created_at`),
  KEY `idx_posts_thread_read` (`thread_id`,`read_at`),
  KEY `idx_posts_user` (`user_id`),
  CONSTRAINT `fk_posts_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `announcements` (
  `id`         CHAR(36) NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `body`       TEXT NOT NULL,
  `type`       VARCHAR(255) NOT NULL DEFAULT 'info',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` CHAR(36) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_created_by_foreign` (`created_by`),
  CONSTRAINT `fk_announcements_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Un anuncio publicado genera una notificacion por destinatario para conservar
-- el estado de lectura individual; la entidad canonica vive en announcements.
CREATE TABLE `notifications` (
  `id`         CHAR(36) NOT NULL,
  `user_id`    CHAR(36) NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT NOT NULL,
  `type`       ENUM('info','warning','success','error') NOT NULL DEFAULT 'info',
  `category`   ENUM('system','announcement','grade','lesson','reminder') NOT NULL DEFAULT 'system',
  `link`       VARCHAR(255) NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` CHAR(36) NULL,
  `expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notif_created_by` (`created_by`),
  CONSTRAINT `fk_notif_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- device_tokens = device_tokens + push_subscriptions.
-- Para platform='web', token guarda el endpoint y p256dh/auth las llaves.
CREATE TABLE `device_tokens` (
  `id`           CHAR(36) NOT NULL,
  `user_id`      CHAR(36) NOT NULL,
  `platform`     ENUM('android','ios','web') NOT NULL,
  `token`        VARCHAR(500) NOT NULL,
  `p256dh`       VARCHAR(255) NULL,
  `auth`         VARCHAR(255) NULL,
  `device_name`  VARCHAR(255) NULL,
  `device_model` VARCHAR(255) NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `last_used_at` TIMESTAMP NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_device_token` (`token`),
  KEY `idx_device_user_active` (`user_id`,`is_active`),
  CONSTRAINT `fk_device_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  7. REGISTRO Y CONFIGURACION
-- =====================================================================

-- activity_log = activity_log + audit_logs. Tabla solo-insercion.
-- Ejemplos de type: 'xp_earned', 'evaluation_completed', 'lesson.updated', 'http.request'
-- Los detalles variables (old/new, path, status, user agent) van en metadata (JSON).
CREATE TABLE `activity_log` (
  `id`           CHAR(36) NOT NULL,
  `user_id`      CHAR(36) NULL,
  `type`         VARCHAR(100) NOT NULL,
  `subject_type` VARCHAR(100) NULL,
  `subject_id`   CHAR(36) NULL,
  `metadata`     JSON NULL,
  `ip_address`   VARCHAR(45) NULL,
  `platform`     VARCHAR(20) NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user_created` (`user_id`,`created_at`),
  KEY `idx_log_type_created` (`type`,`created_at`),
  KEY `idx_log_subject` (`subject_type`,`subject_id`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Configuracion unica: `singleton` + UNIQUE impide tener mas de una fila.
CREATE TABLE `institution_configs` (
  `id`                  CHAR(36) NOT NULL,
  `singleton`           TINYINT(1) NOT NULL DEFAULT 1,
  `institution_name`    VARCHAR(255) NOT NULL,
  `primary_color`       VARCHAR(20) NOT NULL DEFAULT '#004AC6',
  `secondary_color`     VARCHAR(20) NOT NULL DEFAULT '#006C49',
  `tertiary_color`      VARCHAR(20) NOT NULL DEFAULT '#996100',
  `background_color`    VARCHAR(20) NOT NULL DEFAULT '#f8f9ff',
  `surface_color`       VARCHAR(20) NOT NULL DEFAULT '#ffffff',
  `logo`                TEXT NULL,
  `email_notifications` JSON NULL,
  `backup_frequency`    VARCHAR(50) NOT NULL DEFAULT 'daily',
  `last_backup`         TIMESTAMP NULL,
  `allow_tab_switches`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`          TIMESTAMP NULL,
  `updated_at`          TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_config_singleton` (`singleton`)
) ENGINE=InnoDB;

CREATE TABLE `system_translations` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(190) NOT NULL,
  `locale`     VARCHAR(8) NOT NULL,
  `value`      TEXT NOT NULL,
  `group`      VARCHAR(100) NOT NULL DEFAULT 'frontend',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_translation_key_locale` (`key`,`locale`),
  KEY `idx_translation_group` (`group`)
) ENGINE=InnoDB;

-- =====================================================================
--  8. TECNICAS DE LARAVEL (solo las imprescindibles)
--     Eliminadas: cache, cache_locks, jobs, job_batches, failed_jobs, sessions.
--     En .env:  CACHE_STORE=file  QUEUE_CONNECTION=sync  SESSION_DRIVER=file
-- =====================================================================

CREATE TABLE `personal_access_tokens` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id`   CHAR(36) NOT NULL,
  `name`           VARCHAR(255) NOT NULL,
  `token`          VARCHAR(64) NOT NULL,
  `abilities`      TEXT NULL,
  `last_used_at`   TIMESTAMP NULL,
  `expires_at`     TIMESTAMP NULL,
  `daily_requests` INT NOT NULL DEFAULT 0,
  `daily_reset_at` TIMESTAMP NULL,
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pat_token` (`token`),
  KEY `idx_pat_tokenable` (`tokenable_type`,`tokenable_id`),
  KEY `idx_pat_expires` (`expires_at`)
) ENGINE=InnoDB;

CREATE TABLE `password_reset_tokens` (
  `email`      VARCHAR(255) NOT NULL,
  `token`      VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB;

CREATE TABLE `migrations` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch`     INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- =====================================================================
--  VISTAS (reemplazan tablas/columnas que solo duplicaban datos)
-- =====================================================================

-- Resumen derivado de evaluaciones y progreso completado.
-- submitted_works conserva entregas manuales y adjuntos.
CREATE OR REPLACE VIEW `v_submitted_works` AS
SELECT r.id, r.user_id AS student_id, e.lesson_id, e.id AS evaluation_id,
       e.type AS work_type, e.title,
       ROUND(r.score) AS score, ROUND(r.max_score) AS max_score,
       r.status, r.completed_at AS submitted_at, r.graded_at
FROM evaluation_results r
JOIN evaluations e ON e.id = r.evaluation_id
UNION ALL
SELECT lp.id, lp.user_id, lp.lesson_id, NULL, 'lesson', l.title,
       NULL, NULL,
       CASE lp.status WHEN 'completed' THEN 'completed' ELSE 'pending' END,
       lp.completed_at, NULL
FROM lesson_progress lp
JOIN lessons l ON l.id = lp.lesson_id;

-- Reemplaza total_lessons_completed, average_score y total_time_spent
-- (promedio en escala vigesimal 0-20)
CREATE OR REPLACE VIEW `v_student_stats` AS
SELECT u.id AS user_id,
       (SELECT COUNT(*) FROM lesson_progress lp
         WHERE lp.user_id = u.id AND lp.status = 'completed')             AS lessons_completed,
       (SELECT COALESCE(SUM(lp.time_spent),0) FROM lesson_progress lp
         WHERE lp.user_id = u.id)                                         AS total_time_spent,
       (SELECT ROUND(AVG(r.score / NULLIF(r.max_score,0)) * 20, 2)
          FROM evaluation_results r
         WHERE r.user_id = u.id AND r.status = 'completed')               AS average_score,
       (SELECT COUNT(*) FROM user_achievements ua WHERE ua.user_id = u.id) AS achievements_count
FROM users u
JOIN roles ro ON ro.id = u.role_id AND ro.name = 'student';

-- =====================================================================
--  DATOS BASE
-- =====================================================================

INSERT INTO `roles` (`name`,`description`,`created_at`,`updated_at`) VALUES
 ('admin',       'Administrador del sistema', NOW(), NOW()),
 ('teacher',     'Docente',                   NOW(), NOW()),
 ('student',     'Estudiante',                NOW(), NOW()),
 ('parent',      'Padre de familia',          NOW(), NOW()),
 ('coordinador', 'Coordinador academico',     NOW(), NOW()),
 ('director',    'Director academico',        NOW(), NOW());

INSERT INTO `migrations` (`migration`,`batch`) VALUES ('2026_10_22_000000_baseline_schema_v2', 1);

-- =====================================================================
--  OPCIONAL: purga automatica del log (requiere event_scheduler=ON)
-- =====================================================================
-- CREATE EVENT IF NOT EXISTS `ev_purge_activity_log`
--   ON SCHEDULE EVERY 1 DAY
--   DO DELETE FROM `activity_log` WHERE `created_at` < NOW() - INTERVAL 180 DAY;

SET FOREIGN_KEY_CHECKS = 1;
