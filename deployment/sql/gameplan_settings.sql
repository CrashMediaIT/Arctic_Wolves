-- =====================================================================
-- Game Plan Settings Schema
-- =====================================================================
-- Adds gameplan settings to system_settings table.
-- These settings configure the Video Companion Server, hardware
-- acceleration, and NFS/SMB video storage for the Game Plan app.
--
-- This script is idempotent - safe to run multiple times.
-- =====================================================================

-- Ensure system_settings table exists (should already exist in main schema)
-- The system_settings table uses setting_key as unique identifier

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
    ('gameplan_companion_url', '', NOW()),
    ('gameplan_companion_api_key', '', NOW()),
    ('gameplan_app_url', 'https://gameplan.arcticwolves.ca', NOW()),
    ('gameplan_hw_accel_enabled', '0', NOW()),
    ('gameplan_hw_accel_method', 'auto', NOW()),
    ('gameplan_video_storage_type', 'local', NOW()),
    ('gameplan_video_storage_path', '/videos', NOW()),
    ('gameplan_nfs_server', '', NOW()),
    ('gameplan_nfs_export', '', NOW()),
    ('gameplan_nfs_options', 'rw,sync,no_subtree_check', NOW()),
    ('gameplan_smb_server', '', NOW()),
    ('gameplan_smb_share', '', NOW()),
    ('gameplan_smb_username', '', NOW()),
    ('gameplan_smb_domain', '', NOW());
