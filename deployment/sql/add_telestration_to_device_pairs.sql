-- Add telestration sync columns to vr_device_pairs for whiteboard/video drawing sync to TV viewer
ALTER TABLE `vr_device_pairs`
    ADD COLUMN `telestration_data` MEDIUMTEXT DEFAULT NULL
    COMMENT 'Canvas drawing data URL for telestration sync to TV viewer'
    AFTER `controller_page`;

ALTER TABLE `vr_device_pairs`
    ADD COLUMN `telestration_seq` INT DEFAULT 0
    COMMENT 'Telestration version counter for efficient polling'
    AFTER `telestration_data`;
