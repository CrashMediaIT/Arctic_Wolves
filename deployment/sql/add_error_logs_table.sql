-- Migration: Add error_logs table for database-backed error logging
-- This enables the Security > Error Logs tab to display errors from the database

CREATE TABLE IF NOT EXISTS `error_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `error_level` VARCHAR(50) NOT NULL DEFAULT 'ERROR',
    `message` TEXT NOT NULL,
    `file` VARCHAR(500) DEFAULT NULL,
    `line` INT DEFAULT NULL,
    `stack_trace` TEXT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `url` VARCHAR(2048) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `context` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_error_level` (`error_level`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
