-- TVK69 Database Migration
-- Version: 2026-11-06
-- Features: Realtime Chat + TVK69 AI persistent history
-- Safe for existing installations: creates missing tables only.

SET NAMES utf8mb4;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS `chat_rooms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_type` VARCHAR(20) NOT NULL,
  `name` VARCHAR(80) NULL,
  `direct_key` VARCHAR(400) NULL,
  `created_by` VARCHAR(191) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_direct_key` (`direct_key`),
  KEY `idx_chat_rooms_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_room_members` (
  `room_id` BIGINT UNSIGNED NOT NULL,
  `student_id` VARCHAR(191) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'member',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_read_message_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`room_id`, `student_id`),
  KEY `idx_chat_member_student` (`student_id`),
  CONSTRAINT `fk_chat_member_room`
    FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` VARCHAR(191) NOT NULL,
  `message_text` TEXT NULL,
  `message_type` VARCHAR(20) NOT NULL DEFAULT 'text',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_room` (`room_id`, `id`),
  KEY `idx_chat_messages_sender` (`sender_id`),
  CONSTRAINT `fk_chat_message_room`
    FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NULL,
  `owner_student_id` VARCHAR(191) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `file_path` TEXT NOT NULL,
  `mime_type` VARCHAR(160) NOT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_files_message` (`message_id`),
  KEY `idx_chat_files_owner` (`owner_student_id`),
  KEY `idx_chat_files_expires` (`expires_at`),
  CONSTRAINT `fk_chat_file_message`
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(191) NOT NULL,
  `title` VARCHAR(120) NOT NULL DEFAULT 'แชทใหม่',
  `mode` VARCHAR(20) NOT NULL DEFAULT 'general',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_conversations_student_updated` (`student_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `role` VARCHAR(20) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_messages_conversation` (`conversation_id`, `id`),
  CONSTRAINT `fk_ai_messages_conversation`
    FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
