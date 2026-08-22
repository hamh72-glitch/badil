-- ============================================================
-- نظام إدارة البدلاء - مديرية التربية والتعليم - يطا
-- قاعدة بيانات MySQL/MariaDB
-- يمكن استيراد هذا الملف من phpMyAdmin أو سطر الأوامر
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','directorate','school') NOT NULL DEFAULT 'school',
  `permissions` TEXT DEFAULT NULL COMMENT 'JSON صلاحيات مخصصة للمديرية',
  `phone` VARCHAR(50) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `substitutes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_unit` VARCHAR(120) NOT NULL DEFAULT '',
  `gender` VARCHAR(20) NOT NULL DEFAULT '',
  `subject` VARCHAR(255) NOT NULL DEFAULT '',
  `record_type` VARCHAR(50) NOT NULL DEFAULT '',
  `order_no` INT NOT NULL DEFAULT 0,
  `name` VARCHAR(255) NOT NULL,
  `national_id` VARCHAR(60) DEFAULT NULL,
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `qualification` VARCHAR(255) NOT NULL DEFAULT '',
  `specialization` VARCHAR(255) NOT NULL DEFAULT '',
  `gpa` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `final_score` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `mobile` VARCHAR(50) NOT NULL DEFAULT '',
  `notes` TEXT,
  `available` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'متاح للتكليف = 1',
  `wants_work` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'يرغب في العمل = 1',
  `refuse_date` DATE DEFAULT NULL COMMENT 'تاريخ الرفض للعمل',
  `current_vacancy_id` INT UNSIGNED DEFAULT NULL,
  `total_work_days` INT NOT NULL DEFAULT 0,
  `assignments_count` INT NOT NULL DEFAULT 0,
  `last_assigned_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_national_id` (`national_id`),
  KEY `idx_gender` (`gender`),
  KEY `idx_subject` (`subject`(191)),
  KEY `idx_name` (`name`),
  KEY `idx_available` (`available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vacancies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_name` VARCHAR(255) NOT NULL,
  `school_user_id` INT UNSIGNED DEFAULT NULL,
  `shared_with` VARCHAR(255) NOT NULL DEFAULT '',
  `reason` VARCHAR(255) NOT NULL DEFAULT '',
  `center` VARCHAR(60) NOT NULL DEFAULT '',
  `vacancy_gender` VARCHAR(60) NOT NULL DEFAULT '',
  `original_national_id` VARCHAR(60) NOT NULL DEFAULT '',
  `original_name` VARCHAR(255) NOT NULL DEFAULT '',
  `original_gender` VARCHAR(60) NOT NULL DEFAULT '',
  `original_job_title` VARCHAR(255) NOT NULL DEFAULT '',
  `subject` VARCHAR(255) NOT NULL DEFAULT '',
  `classes` TEXT,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `notes` TEXT,
  `status` ENUM('open','assigned','ended','cancelled') NOT NULL DEFAULT 'open',
  `substitute_id` INT UNSIGNED DEFAULT NULL,
  `document_no` VARCHAR(80) DEFAULT NULL,
  `assigned_at` DATETIME DEFAULT NULL,
  `ended_at` DATETIME DEFAULT NULL,
  `days_worked` INT NOT NULL DEFAULT 0,
  `end_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_school` (`school_name`),
  KEY `idx_status` (`status`),
  KEY `idx_substitute` (`substitute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `skey` VARCHAR(100) NOT NULL,
  `svalue` LONGTEXT,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_skey` (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(40) NOT NULL DEFAULT 'shared_request',
  `message` VARCHAR(500) NOT NULL,
  `vacancy_id` INT UNSIGNED DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`, `is_read`),
  KEY `idx_vacancy` (`vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
