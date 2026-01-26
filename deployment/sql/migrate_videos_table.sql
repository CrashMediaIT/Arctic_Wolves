-- Migration script to add missing columns to videos table
-- Run this on existing databases to support the coaches review page

-- Add drill_name column
ALTER TABLE `videos`
ADD COLUMN `drill_name` VARCHAR(255) DEFAULT NULL AFTER `reviewed_at`;

-- Add drill_type column
ALTER TABLE `videos`
ADD COLUMN `drill_type` VARCHAR(100) DEFAULT NULL AFTER `drill_name`;

-- Add duration column
ALTER TABLE `videos`
ADD COLUMN `duration` VARCHAR(50) DEFAULT NULL AFTER `drill_type`;

-- Add rating column
ALTER TABLE `videos`
ADD COLUMN `rating` INT DEFAULT 0 AFTER `duration`;

-- Add created_at column
ALTER TABLE `videos`
ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `rating`;

