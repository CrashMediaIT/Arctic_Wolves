-- Package Coaches - Multiple coaches per package (camp/program)
CREATE TABLE IF NOT EXISTS `package_coaches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `coach_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_package_coach` (`package_id`, `coach_id`),
    INDEX `idx_coach` (`coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add coach_ids column to camp_daily_schedules for per-day coach assignment
ALTER TABLE `camp_daily_schedules`
ADD COLUMN IF NOT EXISTS `coach_ids` TEXT DEFAULT NULL COMMENT 'Comma-separated coach IDs for this day';

-- Add coach_ids column to multiweek_program_dates for per-session coach assignment
ALTER TABLE `multiweek_program_dates`
ADD COLUMN IF NOT EXISTS `coach_ids` TEXT DEFAULT NULL COMMENT 'Comma-separated coach IDs for this session';
