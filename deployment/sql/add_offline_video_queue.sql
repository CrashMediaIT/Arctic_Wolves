-- Offline Video Queue
-- Tracks videos recorded offline that need to be uploaded when connectivity is restored.
-- Each row represents one video with all metadata needed to auto-assign it to the correct
-- area of the application (drill review, coach review, athlete review, gameplan) after upload.

CREATE TABLE IF NOT EXISTS `offline_video_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'User who recorded the video',
    `user_role` VARCHAR(50) NOT NULL COMMENT 'Role at time of recording',
    `upload_type` ENUM('athlete_video','coach_video','drill_video','video_source') NOT NULL,

    -- Core metadata (same fields as pending_video_upload_general session)
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `video_category` ENUM('drill','game') DEFAULT 'drill',
    `original_filename` VARCHAR(255) DEFAULT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `content_type` VARCHAR(100) DEFAULT 'video/mp4',

    -- People
    `athlete_id` INT DEFAULT NULL,
    `coach_id` INT DEFAULT NULL,

    -- Drill / session context
    `session_id` INT DEFAULT NULL,
    `drill_id` INT DEFAULT NULL,
    `rep_number` INT DEFAULT 1,

    -- Coach video context
    `session_date` DATE DEFAULT NULL,
    `drill_type` VARCHAR(100) DEFAULT NULL,
    `drill_name` VARCHAR(255) DEFAULT NULL,
    `rating` INT DEFAULT NULL,

    -- Athlete game context
    `game_date` DATE DEFAULT NULL,
    `team_played_on` VARCHAR(255) DEFAULT NULL,
    `opponent_team` VARCHAR(255) DEFAULT NULL,

    -- Video source (gameplan) context
    `camera_angle` VARCHAR(50) DEFAULT NULL,
    `game_id` INT DEFAULT NULL,
    `team_id` INT DEFAULT NULL,

    -- Upload state
    `status` ENUM('pending','uploading','uploaded','failed') DEFAULT 'pending',
    `upload_progress` INT DEFAULT 0 COMMENT 'Percentage 0-100',
    `error_message` TEXT DEFAULT NULL,
    `video_id` INT DEFAULT NULL COMMENT 'ID in videos table after successful upload',
    `source_id` INT DEFAULT NULL COMMENT 'ID in vr_video_sources after successful upload',
    `object_key` VARCHAR(500) DEFAULT NULL COMMENT 'S3 object key after upload',

    -- Client-side tracking
    `client_queue_id` VARCHAR(64) NOT NULL COMMENT 'Unique ID from IndexedDB for dedup',

    `recorded_at` TIMESTAMP NOT NULL COMMENT 'When the video was originally recorded',
    `queued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `uploaded_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`drill_id`) REFERENCES `drills`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE SET NULL,

    UNIQUE KEY `uq_client_queue` (`client_queue_id`),
    INDEX `idx_user_status` (`user_id`, `status`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
