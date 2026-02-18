-- =========================================================
-- Camp & Multi-Week Program Package Types
-- =========================================================

-- Add camp and multi-week program fields to packages table
ALTER TABLE `packages`
ADD COLUMN IF NOT EXISTS `camp_start_date` DATE DEFAULT NULL COMMENT 'Camp start date',
ADD COLUMN IF NOT EXISTS `camp_end_date` DATE DEFAULT NULL COMMENT 'Camp end date',
ADD COLUMN IF NOT EXISTS `daily_start_time` TIME DEFAULT NULL COMMENT 'Default daily start time',
ADD COLUMN IF NOT EXISTS `daily_end_time` TIME DEFAULT NULL COMMENT 'Default daily end time',
ADD COLUMN IF NOT EXISTS `age_group_id` INT DEFAULT NULL COMMENT 'Optional age group restriction',
ADD COLUMN IF NOT EXISTS `skill_level_id` INT DEFAULT NULL COMMENT 'Optional skill level restriction',
ADD COLUMN IF NOT EXISTS `allow_individual_sessions` TINYINT(1) DEFAULT 0 COMMENT 'Allow purchasing individual sessions from multi-week program';

-- Camp daily schedules - hours and activities for each day of a camp
CREATE TABLE IF NOT EXISTS `camp_daily_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL COMMENT 'Camp package this schedule belongs to',
    `schedule_date` DATE NOT NULL COMMENT 'The specific date',
    `start_time` TIME NOT NULL COMMENT 'Start time for this day',
    `end_time` TIME NOT NULL COMMENT 'End time for this day',
    `title` VARCHAR(255) DEFAULT NULL COMMENT 'Optional title for this day',
    `description` TEXT DEFAULT NULL COMMENT 'Description of activities',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    INDEX `idx_package_date` (`package_id`, `schedule_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camp schedule assignments - assign daily schedules to groups or individuals
CREATE TABLE IF NOT EXISTS `camp_schedule_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `schedule_id` INT NOT NULL COMMENT 'Daily schedule entry',
    `user_id` INT DEFAULT NULL COMMENT 'Individual athlete assignment (NULL for group)',
    `team_id` INT DEFAULT NULL COMMENT 'Team/group assignment (NULL for individual)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`schedule_id`) REFERENCES `camp_daily_schedules`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    INDEX `idx_schedule` (`schedule_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_team` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camp add-on options (meal plans, bus transportation, etc.)
CREATE TABLE IF NOT EXISTS `camp_add_ons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL COMMENT 'Camp package this add-on belongs to',
    `name` VARCHAR(255) NOT NULL COMMENT 'Add-on name (e.g., Meal Plan, Bus Transportation)',
    `description` TEXT DEFAULT NULL COMMENT 'Description of the add-on',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Additional cost for this add-on',
    `is_default` TINYINT(1) DEFAULT 0 COMMENT 'Whether this is selected by default',
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    INDEX `idx_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camp registration add-on selections - tracks which add-ons each registrant opted into
CREATE TABLE IF NOT EXISTS `camp_registration_add_ons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_package_id` INT NOT NULL COMMENT 'The user package purchase',
    `add_on_id` INT NOT NULL COMMENT 'The add-on selected',
    `opted_in` TINYINT(1) DEFAULT 1 COMMENT '1 = opted in, 0 = opted out',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_package_id`) REFERENCES `user_packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`add_on_id`) REFERENCES `camp_add_ons`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_registration_addon` (`user_package_id`, `add_on_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Multi-week program dates - individual session dates selectable from a calendar
CREATE TABLE IF NOT EXISTS `multiweek_program_dates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL COMMENT 'Multi-week program package',
    `session_date` DATE NOT NULL COMMENT 'Date of this session',
    `start_time` TIME NOT NULL COMMENT 'Start time',
    `end_time` TIME NOT NULL COMMENT 'End time',
    `title` VARCHAR(255) DEFAULT NULL COMMENT 'Optional session title',
    `individual_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Price if purchased individually',
    `auto_session_id` INT DEFAULT NULL COMMENT 'Auto-created session ID for individual purchase',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`auto_session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_package_date` (`package_id`, `session_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
