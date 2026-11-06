-- ==============================================================================
-- The Cat-alog Library - Database Schema
-- ==============================================================================
-- Note: Reconstructed schema based on comprehensive analysis of application
-- queries, foreign keys, constraints, and business logic.
-- Compatible with MySQL 5.7+ and MySQL 8.0+.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Table: users (Student Accounts)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL COMMENT 'Student Number / Username',
    `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt / PASSWORD_DEFAULT hash',
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table: admin_users (Administrator Accounts)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt / PASSWORD_DEFAULT hash',
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

-- ------------------------------------------------------------------------------
-- 3. Table: book_titles (Catalog Titles)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `book_titles` (
    `title_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `author` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `published_year` INT NULL DEFAULT NULL,
    `published_month` VARCHAR(20) NULL DEFAULT NULL,
    `book_image` VARCHAR(255) NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Available' COMMENT 'Available, Borrowed, Archived',
    `total_copies` INT NOT NULL DEFAULT 1,
    `available_copies` INT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_titles_category` (`category`),
    INDEX `idx_titles_status` (`status`),
    INDEX `idx_titles_author` (`author`),
    INDEX `idx_titles_year` (`published_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Table: book_copies (Physical Copies of Titles)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `book_copies` (
    `copy_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title_id` INT NOT NULL,
    `book_id` VARCHAR(50) NOT NULL COMMENT 'Auto-generated ID e.g. TEMAR072024-FIC00101',
    `copy_number` INT NOT NULL,
    `condition_status` VARCHAR(50) NOT NULL DEFAULT 'Excellent',
    `acquisition_date` DATE NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Available' COMMENT 'Available, Borrowed, Archived',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_copies_book_id` (`book_id`),
    UNIQUE KEY `uk_copies_title_copy` (`title_id`, `copy_number`),
    INDEX `idx_copies_status` (`status`),
    CONSTRAINT `fk_copies_title` FOREIGN KEY (`title_id`) 
        REFERENCES `book_titles` (`title_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Table: borrowed_books (Loan Transactions)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `borrowed_books` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `copy_id` INT NOT NULL,
    `title_id` INT NOT NULL,
    `book_id` VARCHAR(50) NOT NULL,
    `borrow_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `return_date` DATE NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'borrowed' COMMENT 'borrowed, overdue, renewed, returned',
    `renewal_count` INT NOT NULL DEFAULT 0,
    `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_borrowed_user_status` (`user_id`, `status`),
    INDEX `idx_borrowed_copy_status` (`copy_id`, `status`),
    INDEX `idx_borrowed_title_status` (`title_id`, `status`),
    INDEX `idx_borrowed_due` (`due_date`, `status`),
    CONSTRAINT `fk_borrowed_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_borrowed_copy` FOREIGN KEY (`copy_id`) 
        REFERENCES `book_copies` (`copy_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_borrowed_title` FOREIGN KEY (`title_id`) 
        REFERENCES `book_titles` (`title_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. Table: password_resets (Secure Token-Based Password Resets)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of random reset token',
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_resets_token_hash` (`token_hash`),
    INDEX `idx_resets_user` (`user_id`),
    INDEX `idx_resets_validity` (`token_hash`, `expires_at`, `used_at`),
    CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. Table: rate_limits (Persistent Rate Limiting for Auth Endpoints)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rate_key` VARCHAR(128) NOT NULL,
    `attempt_time` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    INDEX `idx_rate_limits_key_time` (`rate_key`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. Table: sessions (Persistent Session Store for Serverless / Cluster Setups)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(128) NOT NULL PRIMARY KEY,
    `data` MEDIUMTEXT NOT NULL,
    `access` INT UNSIGNED NOT NULL,
    INDEX `idx_sessions_access` (`access`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 9. Trigger: Concurrency-Safe Book ID Auto-Generation on Copy Insertion
-- ------------------------------------------------------------------------------
-- Format: {TITLE_2}{MONTH_3}{DAY_2}{YEAR_4}-{CAT_CODE}{TITLE_ID_3}{COPY_NUM_2}
-- Example: TEMAR072024-FIC00101
-- ------------------------------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_book_copies_before_insert`;

DELIMITER $$

CREATE TRIGGER `trg_book_copies_before_insert`
BEFORE INSERT ON `book_copies`
FOR EACH ROW
BEGIN
    DECLARE v_title VARCHAR(255);
    DECLARE v_author VARCHAR(255);
    DECLARE v_category VARCHAR(100);
    DECLARE v_pub_year INT;
    DECLARE v_pub_month VARCHAR(20);
    DECLARE v_clean_title VARCHAR(255);
    DECLARE v_month_code VARCHAR(3);
    DECLARE v_day_code VARCHAR(2);
    DECLARE v_year_code VARCHAR(4);
    DECLARE v_cat_code VARCHAR(3);
    DECLARE v_title_num VARCHAR(10);
    DECLARE v_copy_num VARCHAR(10);

    -- Only generate if book_id was not explicitly supplied
    IF NEW.book_id IS NULL OR NEW.book_id = '' THEN
        -- Retrieve title metadata
        SELECT `title`, `author`, `category`, `published_year`, `published_month`
        INTO v_title, v_author, v_category, v_pub_year, v_pub_month
        FROM `book_titles`
        WHERE `title_id` = NEW.title_id
        LIMIT 1;

        -- Extract 2 uppercase alpha characters from title (fallback to 'BK')
        SET v_clean_title = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(v_title, 'BK'), ' ', ''), '.', ''), '-', ''), '_', ''));
        IF LENGTH(v_clean_title) < 2 THEN
            SET v_clean_title = 'BK';
        ELSE
            SET v_clean_title = SUBSTRING(v_clean_title, 1, 2);
        END IF;

        -- Determine 3-letter month code
        IF v_pub_month IS NOT NULL AND LENGTH(TRIM(v_pub_month)) >= 3 THEN
            SET v_month_code = UPPER(SUBSTRING(TRIM(v_pub_month), 1, 3));
        ELSE
            SET v_month_code = UPPER(DATE_FORMAT(IFNULL(NEW.acquisition_date, CURDATE()), '%b'));
        END IF;

        -- Day of acquisition (2 digits)
        SET v_day_code = DATE_FORMAT(IFNULL(NEW.acquisition_date, CURDATE()), '%d');

        -- Published year (4 digits, fallback to acquisition year)
        IF v_pub_year IS NOT NULL AND v_pub_year > 0 THEN
            SET v_year_code = LPAD(v_pub_year, 4, '0');
        ELSE
            SET v_year_code = DATE_FORMAT(IFNULL(NEW.acquisition_date, CURDATE()), '%Y');
        END IF;

        -- Category code (3 uppercase letters)
        CASE UPPER(TRIM(IFNULL(v_category, '')))
            WHEN 'FICTION' THEN SET v_cat_code = 'FIC';
            WHEN 'NON-FICTION' THEN SET v_cat_code = 'NON';
            WHEN 'NONFICTION' THEN SET v_cat_code = 'NON';
            WHEN 'ROMANCE' THEN SET v_cat_code = 'ROM';
            WHEN 'CHILDRENS BOOKS' THEN SET v_cat_code = 'CHI';
            WHEN "CHILDREN'S BOOKS" THEN SET v_cat_code = 'CHI';
            WHEN 'SCIENCE AND TECHNOLOGY' THEN SET v_cat_code = 'SCI';
            WHEN 'SCIENCE & TECHNOLOGY' THEN SET v_cat_code = 'SCI';
            ELSE
                SET v_cat_code = UPPER(SUBSTRING(REPLACE(IFNULL(v_category, 'GEN'), ' ', ''), 1, 3));
                IF LENGTH(v_cat_code) < 3 THEN
                    SET v_cat_code = RPAD(v_cat_code, 3, 'X');
                END IF;
        END CASE;

        -- Formatted numbers
        SET v_title_num = LPAD(NEW.title_id, 3, '0');
        SET v_copy_num = LPAD(NEW.copy_number, 2, '0');

        -- Construct complete book_id
        SET NEW.book_id = CONCAT(v_clean_title, v_month_code, v_day_code, v_year_code, '-', v_cat_code, v_title_num, v_copy_num);
    END IF;
END$$

DELIMITER ;
