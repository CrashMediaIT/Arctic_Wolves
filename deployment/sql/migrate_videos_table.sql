-- Migration script to add missing columns to videos table
-- Run this on existing databases to support the coaches review page

-- Add drill_name column if it doesn't exist
ALTER TABLE `videos`
ADD COLUMN IF NOT EXISTS `drill_name` VARCHAR(255) DEFAULT NULL AFTER `reviewed_at`;

-- Add drill_type column if it doesn't exist
ALTER TABLE `videos`
ADD COLUMN IF NOT EXISTS `drill_type` VARCHAR(100) DEFAULT NULL AFTER `drill_name`;

-- Add duration column if it doesn't exist
ALTER TABLE `videos`
ADD COLUMN IF NOT EXISTS `duration` VARCHAR(50) DEFAULT NULL AFTER `drill_type`;

-- Add rating column if it doesn't exist
ALTER TABLE `videos`
ADD COLUMN IF NOT EXISTS `rating` INT DEFAULT 0 AFTER `duration`;

-- Add created_at column if it doesn't exist
ALTER TABLE `videos`
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `rating`;
