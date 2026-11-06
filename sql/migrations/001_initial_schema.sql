-- ==============================================================================
-- Migration 001: Initial Schema & Tables Alignment
-- Safe migration: Uses CREATE TABLE IF NOT EXISTS and non-destructive ALTERs.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_users` (
    `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `last_login` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_admin_username` (`username`),
    UNIQUE KEY `uk_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_titles` (
    `title_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `author` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `published_year` INT NULL DEFAULT NULL,
    `published_month` VARCHAR(20) NULL DEFAULT NULL,
    `book_image` VARCHAR(255) NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Available',
    `total_copies` INT NOT NULL DEFAULT 1,
    `available_copies` INT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_titles_category` (`category`),
    INDEX `idx_titles_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_copies` (
    `copy_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title_id` INT NOT NULL,
    `book_id` VARCHAR(50) NOT NULL,
    `copy_number` INT NOT NULL,
    `condition_status` VARCHAR(50) NOT NULL DEFAULT 'Excellent',
    `acquisition_date` DATE NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Available',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_copies_book_id` (`book_id`),
    UNIQUE KEY `uk_copies_title_copy` (`title_id`, `copy_number`),
    INDEX `idx_copies_status` (`status`),
    CONSTRAINT `fk_copies_title` FOREIGN KEY (`title_id`) 
        REFERENCES `book_titles` (`title_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `borrowed_books` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `copy_id` INT NOT NULL,
    `title_id` INT NOT NULL,
    `book_id` VARCHAR(50) NOT NULL,
    `borrow_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `return_date` DATE NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'borrowed',
    `renewal_count` INT NOT NULL DEFAULT 0,
    `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_borrowed_user_status` (`user_id`, `status`),
    INDEX `idx_borrowed_copy_status` (`copy_id`, `status`),
    INDEX `idx_borrowed_title_status` (`title_id`, `status`),
    CONSTRAINT `fk_borrowed_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_borrowed_copy` FOREIGN KEY (`copy_id`) 
        REFERENCES `book_copies` (`copy_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_borrowed_title` FOREIGN KEY (`title_id`) 
        REFERENCES `book_titles` (`title_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_resets_token_hash` (`token_hash`),
    INDEX `idx_resets_user` (`user_id`),
    INDEX `idx_resets_validity` (`token_hash`, `expires_at`, `used_at`),
    CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rate_key` VARCHAR(128) NOT NULL,
    `attempt_time` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    INDEX `idx_rate_limits_key_time` (`rate_key`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(128) NOT NULL PRIMARY KEY,
    `data` MEDIUMTEXT NOT NULL,
    `access` INT UNSIGNED NOT NULL,
    INDEX `idx_sessions_access` (`access`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

