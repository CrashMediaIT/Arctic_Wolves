-- Migration: Add roster_players table for non-user roster management
-- Allows teams to have players who are not Arctic Wolves users
-- Players can optionally be linked to existing user accounts

CREATE TABLE IF NOT EXISTS `roster_players` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL COMMENT 'Linked Arctic Wolves user account (NULL if external player)',
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `jersey_number` INT DEFAULT NULL,
    `position` VARCHAR(50) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `parent_name` VARCHAR(200) DEFAULT NULL,
    `parent_email` VARCHAR(255) DEFAULT NULL,
    `parent_phone` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    `season_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE SET NULL,
    INDEX `idx_team` (`team_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_season` (`season_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add season_id to game_schedules for season tracking on imports
ALTER TABLE `game_schedules`
    ADD COLUMN IF NOT EXISTS `season_id` INT DEFAULT NULL AFTER `notes`,
    ADD INDEX `idx_season` (`season_id`);
