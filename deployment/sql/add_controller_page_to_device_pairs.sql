-- Add controller_page column to vr_device_pairs for TV viewer navigation sync
ALTER TABLE `vr_device_pairs`
    ADD COLUMN `controller_page` VARCHAR(50) DEFAULT 'home'
    COMMENT 'Current page the controller is navigating'
    AFTER `is_frozen`;
