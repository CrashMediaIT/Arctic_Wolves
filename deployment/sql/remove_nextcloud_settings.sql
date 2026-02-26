-- Remove Nextcloud settings from system_settings on update
-- Nextcloud has been replaced by RustFS S3 for all uploads and storage.
-- Safe to run multiple times (idempotent).

-- Delete all Nextcloud-related system settings
DELETE FROM `system_settings` WHERE `setting_key` IN (
    'nextcloud_url',
    'nextcloud_username',
    'nextcloud_password',
    'nextcloud_receipt_folder',
    'nextcloud_webdav_path',
    'nextcloud_ocr_enabled',
    'nextcloud_auto_sync',
    'nextcloud_backups_dir',
    'nextcloud_videos_dir',
    'nextcloud_receipts_dir',
    'nextcloud_documents_dir',
    'nextcloud_hr_dir',
    'nextcloud_terminations_dir',
    'nextcloud_contracts_dir',
    'nextcloud_images_dir',
    'nextcloud_persistent_path',
    'nextcloud_backup_url',
    'nextcloud_backup_username',
    'nextcloud_backup_password',
    'nextcloud_backup_folder',
    'nextcloud_failover_timeout',
    'nextcloud_sync_interval',
    'nextcloud_scan_subfolders',
    'nextcloud_backup_enabled',
    'nextcloud_enabled'
);

-- Update backup_jobs destination_type ENUM to remove Nextcloud options
-- Migrate any existing 'nextcloud' or 'both_nextcloud' destinations to 's3'
UPDATE `backup_jobs` SET `destination_type` = 's3' WHERE `destination_type` IN ('nextcloud', 'both_nextcloud');

-- Alter the ENUM to remove Nextcloud options (only if the column exists)
-- Note: This ALTER may fail on some MySQL versions if the enum values don't exist.
-- Wrapping in a procedure for safety.
DELIMITER //
DROP PROCEDURE IF EXISTS `_migrate_backup_destination_type`//
CREATE PROCEDURE `_migrate_backup_destination_type`()
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    ALTER TABLE `backup_jobs`
        MODIFY COLUMN `destination_type` ENUM('local', 'smb', 'ftp', 's3', 'both') DEFAULT 's3';
END//
DELIMITER ;

CALL `_migrate_backup_destination_type`();
DROP PROCEDURE IF EXISTS `_migrate_backup_destination_type`;
