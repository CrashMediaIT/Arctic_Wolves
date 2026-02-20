-- =========================================================
-- Programs & Camps Enhancements Migration
-- Add location/place to camp schedules and multi-week dates
-- Add marketing email campaigns table
-- =========================================================

-- Add location field to camp daily schedules
ALTER TABLE `camp_daily_schedules`
ADD COLUMN IF NOT EXISTS `location` VARCHAR(255) DEFAULT NULL COMMENT 'Location/place for this day';

-- Add location field to multi-week program dates
ALTER TABLE `multiweek_program_dates`
ADD COLUMN IF NOT EXISTS `location` VARCHAR(255) DEFAULT NULL COMMENT 'Location/place for this session';

-- Marketing email campaigns for packages/camps/programs
CREATE TABLE IF NOT EXISTS `marketing_email_campaigns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `package_ids` TEXT DEFAULT NULL COMMENT 'JSON array of package IDs included',
    `include_child_pickup` TINYINT(1) DEFAULT 0 COMMENT 'Whether to include child pickup info',
    `recipient_filter` ENUM('all', 'opted_in', 'parents', 'athletes') DEFAULT 'opted_in',
    `sent_count` INT DEFAULT 0,
    `failed_count` INT DEFAULT 0,
    `status` ENUM('draft', 'sending', 'sent', 'failed') DEFAULT 'draft',
    `created_by` INT NOT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add show_on_landing to packages if not exists
ALTER TABLE `packages`
ADD COLUMN IF NOT EXISTS `show_on_landing` TINYINT(1) DEFAULT 0 COMMENT 'Whether to show on public landing page';
