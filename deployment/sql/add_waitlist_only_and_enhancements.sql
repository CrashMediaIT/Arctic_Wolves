-- Migration: Add waitlist_only support and extend waitlist system
-- Adds waitlist_only flag to sessions, packages, and training_session_templates
-- Extends waitlists table to support packages and programs
-- Adds token-based purchase links with 48-hour expiration

-- 1. Add waitlist_only to sessions
ALTER TABLE `sessions`
    ADD COLUMN `waitlist_only` TINYINT(1) DEFAULT 0 COMMENT 'When enabled, users must join waitlist instead of booking directly'
    AFTER `is_semi_private`;

-- 2. Add waitlist_only to packages
ALTER TABLE `packages`
    ADD COLUMN `waitlist_only` TINYINT(1) DEFAULT 0 COMMENT 'When enabled, users must join waitlist instead of purchasing directly'
    AFTER `allow_individual_sessions`;

-- 3. Add waitlist_only to training_session_templates
ALTER TABLE `training_session_templates`
    ADD COLUMN `waitlist_only` TINYINT(1) DEFAULT 0 COMMENT 'When enabled, users must join waitlist instead of registering directly'
    AFTER `is_dev_program`;

-- 4. Extend waitlists table to support packages and programs (currently only session_id)
ALTER TABLE `waitlists`
    MODIFY COLUMN `session_id` INT DEFAULT NULL,
    ADD COLUMN `package_id` INT DEFAULT NULL COMMENT 'Package waitlist' AFTER `session_id`,
    ADD COLUMN `template_id` INT DEFAULT NULL COMMENT 'Training template / program waitlist' AFTER `package_id`,
    ADD COLUMN `waitlist_token` VARCHAR(64) DEFAULT NULL COMMENT 'Unique token for purchase link' AFTER `status`,
    ADD COLUMN `token_expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Token expiration (48 hours from offer)' AFTER `waitlist_token`,
    ADD INDEX `idx_package` (`package_id`),
    ADD INDEX `idx_template` (`template_id`),
    ADD UNIQUE INDEX `idx_token` (`waitlist_token`);
