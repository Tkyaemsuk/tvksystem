-- TVK69 Database Migration
-- Version: 2026-11-06
-- Features: Realtime presence, Instagram-style Story, Story music, recorded lessons/comments
-- Safe for existing installations: creates missing tables only.

SET NAMES utf8mb4;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS `story_music_library` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `artist` VARCHAR(255) NULL,
  `audio_url` TEXT NOT NULL,
  `cover_url` TEXT NULL,
  `duration` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_story_music_active_created` (`active`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intern_story_moments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(191) NOT NULL,
  `content` TEXT NULL,
  `image_url` TEXT NULL,
  `bg_class` VARCHAR(255) NULL,
  `music_id` INT NULL,
  `music_url` TEXT NULL,
  `music_title` VARCHAR(255) NULL,
  `music_artist` VARCHAR(255) NULL,
  `music_start_seconds` INT NOT NULL DEFAULT 0,
  `music_duration` INT NOT NULL DEFAULT 30,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_story_moments_student` (`student_id`),
  KEY `idx_story_moments_expires` (`expires_at`),
  KEY `idx_story_moments_created` (`created_at`),
  KEY `idx_story_moments_music` (`music_id`),
  CONSTRAINT `fk_story_moment_music`
    FOREIGN KEY (`music_id`) REFERENCES `story_music_library` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `realtime_presence_last_seen` (
  `student_id` VARCHAR(191) NOT NULL,
  `last_seen` DATETIME NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  KEY `idx_realtime_presence_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recorded_lessons` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `teacher` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `recording_date` DATE NOT NULL,
  `url` TEXT NOT NULL,
  `thumbnail` TEXT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recording_date` (`recording_date`),
  KEY `idx_teacher_subject` (`teacher`(64), `subject`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recorded_lesson_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recording_id` BIGINT UNSIGNED NOT NULL,
  `student_id` VARCHAR(100) NOT NULL,
  `comment_text` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rec_comments_recording` (`recording_id`, `created_at`),
  KEY `idx_rec_comments_student` (`student_id`),
  CONSTRAINT `fk_rec_comments_recording`
    FOREIGN KEY (`recording_id`) REFERENCES `recorded_lessons` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
