-- Migration: Add team_seasons junction table and season_id to team_roster and team_coach_assignments
-- This allows multiple seasons per team and per-season athlete/coach assignments

-- Add season_id to team_coach_assignments if not exists
ALTER TABLE `team_coach_assignments`
    ADD COLUMN IF NOT EXISTS `season_id` INT DEFAULT NULL AFTER `coach_id`,
    ADD CONSTRAINT `fk_tca_season` FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE CASCADE;

-- Rename assigned_date to assigned_at if the old column exists
-- MySQL doesn't support IF EXISTS for CHANGE COLUMN, so we use a safe approach
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_coach_assignments' AND COLUMN_NAME = 'assigned_date');
SET @rename_sql = IF(@col_exists > 0, 
    'ALTER TABLE `team_coach_assignments` CHANGE COLUMN `assigned_date` `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @rename_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add unique constraint for coach-team-season
ALTER TABLE `team_coach_assignments`
    ADD UNIQUE KEY `unique_coach_team_season` (`coach_id`, `team_id`, `season_id`);

-- Add season_id to team_roster if not exists
ALTER TABLE `team_roster`
    ADD COLUMN IF NOT EXISTS `season_id` INT DEFAULT NULL AFTER `athlete_id`,
    ADD CONSTRAINT `fk_tr_season` FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE SET NULL;

-- Drop old unique key and add new one with season_id
ALTER TABLE `team_roster`
    DROP INDEX IF EXISTS `unique_team_athlete`,
    ADD UNIQUE KEY `unique_team_athlete_season` (`team_id`, `athlete_id`, `season_id`);

-- Create team_seasons junction table
CREATE TABLE IF NOT EXISTS `team_seasons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `season_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_team_season` (`team_id`, `season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
