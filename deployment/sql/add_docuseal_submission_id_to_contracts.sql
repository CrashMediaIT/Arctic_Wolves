-- Migration: Add docuseal_submission_id column to employee_contracts table
-- Purpose: Support DocuSeal e-signature integration alongside legacy OpenSign
-- Date: 2026-02-04

SET @dbname = DATABASE();
SET @tablename = 'employee_contracts';

-- Add docuseal_submission_id column
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'docuseal_submission_id') > 0,
    'SELECT 1',
    "ALTER TABLE `employee_contracts` ADD COLUMN `docuseal_submission_id` INT DEFAULT NULL COMMENT 'External: DocuSeal submission ID for tracking (not a local FK)' AFTER `opensign_submission_id`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for docuseal_submission_id lookups
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_docuseal_submission') > 0,
    'SELECT 1',
    "CREATE INDEX `idx_docuseal_submission` ON `employee_contracts`(`docuseal_submission_id`)"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
