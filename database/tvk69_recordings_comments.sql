-- TVK69 Recorded Lessons + Comments
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