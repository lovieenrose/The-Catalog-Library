-- ==============================================================================
-- The Cat-alog Library - Optional Demo Data
-- ==============================================================================
-- Fictional seed data for testing and demonstrations.
-- Includes sample students, books covering all categories matching existing images,
-- copies, and sample loan transactions.
--
-- Password for demo students is: Student@123
-- (Hash: password_hash('Student@123', PASSWORD_BCRYPT))
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- Demo Students
-- ------------------------------------------------------------------------------
INSERT INTO `users` (`user_id`, `username`, `password`, `first_name`, `last_name`, `email`, `created_at`) VALUES
(1, '2023-00001', '$2y$10$wK1FmR9h9iJ4FhHq8LwSWeP8yZt7MhZ6FfSgWl5L6xUq3eO4a9L0i', 'Whiskers', 'Tabby', 'whiskers@catalog.lib', NOW()),
(2, '2023-00002', '$2y$10$wK1FmR9h9iJ4FhHq8LwSWeP8yZt7MhZ6FfSgWl5L6xUq3eO4a9L0i', 'Luna', 'Calico', 'luna@catalog.lib', NOW()),
(3, '2023-00003', '$2y$10$wK1FmR9h9iJ4FhHq8LwSWeP8yZt7MhZ6FfSgWl5L6xUq3eO4a9L0i', 'Oliver', 'Ginger', 'oliver@catalog.lib', NOW())
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- ------------------------------------------------------------------------------
-- Demo Book Titles
-- ------------------------------------------------------------------------------
INSERT INTO `book_titles` (`title_id`, `title`, `author`, `category`, `published_year`, `published_month`, `book_image`, `status`, `total_copies`, `available_copies`, `created_at`) VALUES
(1, 'The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', 1925, 'April', 'fic1.png', 'Available', 3, 2, NOW()),
(2, 'To Kill a Mockingbird', 'Harper Lee', 'Fiction', 1960, 'July', 'fic2.png', 'Available', 2, 2, NOW()),
(3, '1984', 'George Orwell', 'Fiction', 1949, 'June', 'fic3.png', 'Available', 2, 2, NOW()),
(4, 'Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 'Non-Fiction', 2014, 'September', 'nonfic1.png', 'Available', 3, 3, NOW()),
(5, 'Educated: A Memoir', 'Tara Westover', 'Non-Fiction', 2018, 'February', 'nonfic2.png', 'Available', 2, 1, NOW()),
(6, 'Atomic Habits', 'James Clear', 'Non-Fiction', 2018, 'October', 'nonfic3.png', 'Available', 3, 3, NOW()),
(7, 'The Little Prince', 'Antoine de Saint-Exupéry', 'Childrens Books', 1943, 'April', 'ch1.png', 'Available', 2, 2, NOW()),
(8, 'Where the Wild Things Are', 'Maurice Sendak', 'Childrens Books', 1963, 'November', 'ch2.png', 'Available', 2, 2, NOW()),
(9, 'Pride and Prejudice', 'Jane Austen', 'Romance', 1813, 'January', 'rom1.png', 'Available', 2, 2, NOW()),
(10, 'Outlander', 'Diana Gabaldon', 'Romance', 1991, 'June', 'rom2.png', 'Available', 2, 2, NOW()),
(11, 'A Brief History of Time', 'Stephen Hawking', 'Science and Technology', 1988, 'March', 'st1.png', 'Available', 2, 2, NOW()),
(12, 'Clean Code: A Handbook of Agile Software Craftsmanship', 'Robert C. Martin', 'Science and Technology', 2008, 'August', 'st2.png', 'Available', 2, 2, NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- ------------------------------------------------------------------------------
-- Demo Book Copies
-- ------------------------------------------------------------------------------
INSERT INTO `book_copies` (`copy_id`, `title_id`, `book_id`, `copy_number`, `condition_status`, `acquisition_date`, `status`, `created_at`) VALUES
(1, 1, 'THAPR071925-FIC00101', 1, 'Excellent', CURDATE(), 'Borrowed', NOW()),
(2, 1, 'THAPR071925-FIC00102', 2, 'Good', CURDATE(), 'Available', NOW()),
(3, 1, 'THAPR071925-FIC00103', 3, 'Excellent', CURDATE(), 'Available', NOW()),
(4, 2, 'TOJUL071960-FIC00201', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(5, 2, 'TOJUL071960-FIC00202', 2, 'Good', CURDATE(), 'Available', NOW()),
(6, 3, '19JUN071949-FIC00301', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(7, 3, '19JUN071949-FIC00302', 2, 'Good', CURDATE(), 'Available', NOW()),
(8, 4, 'SASEP072014-NON00401', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(9, 4, 'SASEP072014-NON00402', 2, 'Excellent', CURDATE(), 'Available', NOW()),
(10, 4, 'SASEP072014-NON00403', 3, 'Good', CURDATE(), 'Available', NOW()),
(11, 5, 'EDFEB072018-NON00501', 1, 'Excellent', CURDATE(), 'Borrowed', NOW()),
(12, 5, 'EDFEB072018-NON00502', 2, 'Good', CURDATE(), 'Available', NOW()),
(13, 6, 'ATOCT072018-NON00601', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(14, 6, 'ATOCT072018-NON00602', 2, 'Good', CURDATE(), 'Available', NOW()),
(15, 6, 'ATOCT072018-NON00603', 3, 'Excellent', CURDATE(), 'Available', NOW()),
(16, 7, 'THAPR071943-CHI00701', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(17, 7, 'THAPR071943-CHI00702', 2, 'Good', CURDATE(), 'Available', NOW()),
(18, 8, 'WHNOV071963-CHI00801', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(19, 8, 'WHNOV071963-CHI00802', 2, 'Excellent', CURDATE(), 'Available', NOW()),
(20, 9, 'PRJAN071813-ROM00901', 1, 'Good', CURDATE(), 'Available', NOW()),
(21, 9, 'PRJAN071813-ROM00902', 2, 'Excellent', CURDATE(), 'Available', NOW()),
(22, 10, 'OUJUN071991-ROM01001', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(23, 10, 'OUJUN071991-ROM01002', 2, 'Good', CURDATE(), 'Available', NOW()),
(24, 11, 'ABMAR071988-SCI01101', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(25, 11, 'ABMAR071988-SCI01102', 2, 'Good', CURDATE(), 'Available', NOW()),
(26, 12, 'CLAUGO072008-SCI01201', 1, 'Excellent', CURDATE(), 'Available', NOW()),
(27, 12, 'CLAUGO072008-SCI01202', 2, 'Excellent', CURDATE(), 'Available', NOW())
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- ------------------------------------------------------------------------------
-- Demo Loans
-- ------------------------------------------------------------------------------
INSERT INTO `borrowed_books` (`id`, `user_id`, `copy_id`, `title_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`, `renewal_count`, `fine_amount`, `created_at`) VALUES
(1, 1, 1, 1, 'THAPR071925-FIC00101', DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), NULL, 'borrowed', 0, 0.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 2, 11, 5, 'EDFEB072018-NON00501', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL, 'overdue', 0, 30.00, DATE_SUB(NOW(), INTERVAL 10 DAY))
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

SET FOREIGN_KEY_CHECKS = 1;

