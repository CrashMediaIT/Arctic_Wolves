-- Migration: Add contract fields to employee_onboarding table
-- Purpose: Allow tracking of employment contracts created during onboarding
-- Date: 2026-02-04

-- Add contract_sent flag to track if a contract was sent during onboarding
-- Check first if column exists before adding
SET @dbname = DATABASE();
SET @tablename = 'employee_onboarding';

-- Add contract_sent column
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'contract_sent') > 0,
    'SELECT 1',
    "ALTER TABLE `employee_onboarding` ADD COLUMN `contract_sent` TINYINT(1) DEFAULT 0 COMMENT 'Whether employment contract was sent for signature' AFTER `nextcloud_folder`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add contract_id column
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'contract_id') > 0,
    'SELECT 1',
    "ALTER TABLE `employee_onboarding` ADD COLUMN `contract_id` INT DEFAULT NULL COMMENT 'Link to employee_contracts record if contract was created' AFTER `contract_sent`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for contract_id lookups (check if exists first)
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_contract') > 0,
    'SELECT 1',
    "CREATE INDEX `idx_contract` ON `employee_onboarding`(`contract_id`)"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

