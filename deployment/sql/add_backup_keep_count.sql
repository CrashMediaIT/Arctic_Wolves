-- Migration: Add backup keep_count, both_nextcloud destination type,
--            and secondary Nextcloud settings for scheduled backups.
-- Run this once against an existing Arctic Wolves database to apply the
-- changes introduced by the Galera / backup-retention update.
-- Safe to run multiple times (uses ALTER IGNORE / ADD COLUMN IF NOT EXISTS).

-- 1. Add keep_count column to backup_jobs (default 3 – keep 3 copies of each schedule)
ALTER TABLE `backup_jobs`
    ADD COLUMN IF NOT EXISTS `keep_count` INT NOT NULL DEFAULT 3
    COMMENT 'Number of successful backup copies to retain per job (oldest are pruned)'
    AFTER `retention_days`;

-- 2. Extend destination_type to support both Nextcloud instances simultaneously
ALTER TABLE `backup_jobs`
    MODIFY COLUMN `destination_type`
        ENUM('local', 'nextcloud', 'smb', 'ftp', 's3', 'both', 'both_nextcloud')
        NOT NULL DEFAULT 'nextcloud';

-- 3. Register secondary (backup) Nextcloud connection settings
--    These mirror the existing nextcloud_* keys but for the second instance.
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('nextcloud_backup_url',      NULL, 'text',     'URL of the secondary/backup Nextcloud instance'),
('nextcloud_backup_username', NULL, 'text',     'Username for the secondary Nextcloud instance'),
('nextcloud_backup_password', NULL, 'password', 'Password for the secondary Nextcloud instance'),
('nextcloud_backup_folder',   '/ArcticWolves/Backups/', 'text', 'Default backup folder on the secondary Nextcloud instance');
