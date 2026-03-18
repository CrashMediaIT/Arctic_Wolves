-- =========================================================
-- ARCTIC WOLVES DATABASE SCHEMA
-- Complete schema for hockey coaching management system
-- =========================================================

-- Users table with expanded roles
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(512) NOT NULL,
    `last_name` VARCHAR(512) NOT NULL,
    `role` ENUM('athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach', 'front_desk_staff', 'hr', 'accounting', 'goalie_dev', 'player_dev') DEFAULT 'athlete',
    `is_active` TINYINT(1) DEFAULT 1,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verification_code` VARCHAR(10) DEFAULT NULL,
    `force_pass_change` TINYINT(1) DEFAULT 0,
    `phone` VARCHAR(512) DEFAULT NULL,
    `birth_date` VARCHAR(512) DEFAULT NULL, -- Primary column used by PHP code (VARCHAR to support encryption)
    `date_of_birth` VARCHAR(512) DEFAULT NULL, -- Legacy alias for backward compatibility (VARCHAR to support encryption)
    `position` VARCHAR(50) DEFAULT NULL, -- Player position (forward, defense, goalie, etc.)
    `primary_arena` VARCHAR(255) DEFAULT NULL, -- Home arena/facility
    `assigned_coach_id` INT DEFAULT NULL, -- Primary coach assigned to this athlete
    `created_by_coach_id` INT DEFAULT NULL, -- Coach who created this athlete account
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `job_title` VARCHAR(255) DEFAULT NULL COMMENT 'Job title for business cards and signatures',
    `sip_username` VARCHAR(255) DEFAULT NULL COMMENT 'SIP account username for FusionPBX',
    `sip_domain` VARCHAR(255) DEFAULT NULL COMMENT 'SIP server domain for FusionPBX',
    `sip_extension` VARCHAR(20) DEFAULT NULL COMMENT 'Phone extension number',
    `sip_did` VARCHAR(20) DEFAULT NULL COMMENT 'Direct Inward Dialing number',
    `sip_password` VARCHAR(512) DEFAULT NULL COMMENT 'Encrypted SIP account password for FusionPBX',
    `sip_wss_port` INT DEFAULT 7443 COMMENT 'WebSocket Secure port for SIP/WSS connection to FusionPBX',
    `agreements_accepted` TINYINT(1) DEFAULT 0 COMMENT 'Whether user has accepted waiver and privacy policy',
    `promotional_opt_in` TINYINT(1) DEFAULT 1 COMMENT 'Whether user opts in to promotional material usage',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `two_factor_required` TINYINT(1) DEFAULT 0,
    `nextcloud_image_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for profile image (RustFS URL)',
    FOREIGN KEY (`assigned_coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by_coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_role` (`role`),
    INDEX `idx_email` (`email`),
    INDEX `idx_role_verified` (`role`, `is_verified`),
    INDEX `idx_assigned_coach` (`assigned_coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent-Athlete relationships
CREATE TABLE IF NOT EXISTS `parent_athlete_relationships` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `relationship_type` ENUM('parent', 'guardian', 'other') DEFAULT 'parent',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_parent_athlete` (`parent_id`, `athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email change requests (for secure email address changes)
CREATE TABLE IF NOT EXISTS `email_change_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `old_email` VARCHAR(255) NOT NULL,
    `new_email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_request` (`user_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coach-Athlete assignments
-- Teams
CREATE TABLE IF NOT EXISTS `teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `age_group` VARCHAR(50) DEFAULT NULL,
    `skill_level` VARCHAR(50) DEFAULT NULL,
    `division` VARCHAR(50) DEFAULT NULL,
    `season` VARCHAR(50) DEFAULT NULL,
    `coach_id` INT DEFAULT NULL,
    `assistant_coach_id` INT DEFAULT NULL,
    `logo_url` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_demo` TINYINT(1) DEFAULT 0,
    `is_managed` TINYINT(1) DEFAULT 1 COMMENT '1 = managed team (our teams), 0 = unmanaged (opponent teams)',
    `ical_url` VARCHAR(1000) DEFAULT NULL COMMENT 'Stored iCal URL for calendar re-sync',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `nextcloud_logo_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for team logo (RustFS URL)',
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assistant_coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_managed` (`is_managed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert demo teams
INSERT INTO `teams` (`name`, `division`, `season`, `is_active`, `is_demo`) VALUES
('Arctic Wolves U14', 'U14', '2024-2025', 1, 1),
('Arctic Wolves U16', 'U16', '2024-2025', 1, 1),
('Arctic Wolves U18', 'U18', '2024-2025', 1, 1),
('Arctic Wolves Elite', 'Elite', '2024-2025', 1, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seasons (must be defined before tables that reference it)
CREATE TABLE IF NOT EXISTS `seasons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_dates` (`start_date`, `end_date`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team-Coach assignments
CREATE TABLE IF NOT EXISTS `team_coach_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `coach_id` INT NOT NULL,
    `season_id` INT NOT NULL,
    `role` ENUM('head_coach', 'assistant_coach') DEFAULT 'head_coach',
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_coach_team_season` (`coach_id`, `team_id`, `season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team roster (athlete memberships)
CREATE TABLE IF NOT EXISTS `team_roster` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `season_id` INT DEFAULT NULL,
    `jersey_number` INT DEFAULT NULL,
    `position` VARCHAR(50) DEFAULT NULL,
    `joined_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_team_athlete_season` (`team_id`, `athlete_id`, `season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team-Season junction (allows multiple seasons per team)
CREATE TABLE IF NOT EXISTS `team_seasons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `season_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_team_season` (`team_id`, `season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Player positions (standardized position categories)
CREATE TABLE IF NOT EXISTS `player_positions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `abbreviation` VARCHAR(10) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `position_type` ENUM('forward', 'defense', 'goalie') DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_position_name` (`name`),
    INDEX `idx_position_type` (`position_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default hockey positions
INSERT INTO `player_positions` (`name`, `abbreviation`, `description`, `position_type`) VALUES
('Left Wing', 'LW', 'Left wing forward position', 'forward'),
('Center', 'C', 'Center forward position', 'forward'),
('Right Wing', 'RW', 'Right wing forward position', 'forward'),
('Left Defense', 'LD', 'Left side defenseman', 'defense'),
('Right Defense', 'RD', 'Right side defenseman', 'defense'),
('Goalie', 'G', 'Goaltender position', 'goalie')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Locations
CREATE TABLE IF NOT EXISTS `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `province` VARCHAR(50) DEFAULT NULL,
    `postal_code` VARCHAR(10) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `google_place_id` VARCHAR(255) DEFAULT NULL,
    `image_url` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_demo` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_demo` (`is_demo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session types
CREATE TABLE IF NOT EXISTS `session_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `default_price` DECIMAL(10,2) DEFAULT 0.00,
    `duration_minutes` INT DEFAULT 60,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `max_participants` INT DEFAULT NULL COMMENT 'Maximum participants for this session type',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether this session type is active',
    `show_on_landing` TINYINT(1) DEFAULT 0 COMMENT 'Whether to show on landing page',
    `session_type` ENUM('on_ice', 'off_ice', 'nutrition', 'meeting', 'other') DEFAULT 'on_ice' COMMENT 'Type of session',
    `is_template` TINYINT(1) DEFAULT 0 COMMENT 'Whether this is a reusable template'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_type_id` INT DEFAULT NULL,
    `location_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `session_date` DATE NOT NULL,
    `session_time` TIME DEFAULT NULL,
    `duration_minutes` INT DEFAULT 60,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `max_participants` INT DEFAULT NULL,
    `age_group` VARCHAR(50) DEFAULT NULL,
    `skill_level` VARCHAR(50) DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `coach_id` INT DEFAULT NULL,
    `arena` VARCHAR(255) DEFAULT NULL COMMENT 'Arena/location name for display',
    `city` VARCHAR(100) DEFAULT NULL COMMENT 'City for display',
    `session_type` VARCHAR(100) DEFAULT NULL COMMENT 'Session type name for display',
    `session_plan` TEXT DEFAULT NULL COMMENT 'Session plan/notes',
    `status` ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `show_on_landing` TINYINT(1) DEFAULT 0 COMMENT 'Whether to show on landing page',
    `session_type_category` ENUM('all_players', 'players', 'goalies') DEFAULT 'all_players',
    `enable_child_checkin` TINYINT(1) DEFAULT 0 COMMENT 'Enable child check-in/check-out for this session/camp',
    `is_private` TINYINT(1) DEFAULT 0 COMMENT 'Whether this is a private session',
    `is_semi_private` TINYINT(1) DEFAULT 0 COMMENT 'Whether this is a semi-private session',
    `o365_event_id` VARCHAR(512) DEFAULT NULL COMMENT 'Office 365 iCalUId for sync dedup',
    FOREIGN KEY (`session_type_id`) REFERENCES `session_types`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_date` (`session_date`),
    INDEX `idx_time` (`session_time`),
    INDEX `idx_status` (`status`),
    INDEX `idx_coach_date` (`coach_id`, `session_date`),
    UNIQUE INDEX `idx_o365_event_id` (`o365_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Coaches - Multiple coaches per session
CREATE TABLE IF NOT EXISTS `session_coaches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `coach_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_session_coach` (`session_id`, `coach_id`),
    INDEX `idx_coach` (`coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Practice Plans
CREATE TABLE IF NOT EXISTS `practice_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `focus_area` VARCHAR(255) DEFAULT NULL,
    `age_group` VARCHAR(50) DEFAULT NULL,
    `duration_minutes` INT DEFAULT 60,
    `difficulty_level` ENUM('beginner', 'intermediate', 'advanced', 'elite') DEFAULT 'intermediate',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `version` INT DEFAULT 1,
    `parent_plan_id` INT DEFAULT NULL,
    `share_token` VARCHAR(64) DEFAULT NULL,
    `total_duration` INT DEFAULT 60,
    `title` VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE SET NULL,
    INDEX `idx_focus_area` (`focus_area`),
    INDEX `idx_share_token` (`share_token`),
    INDEX `idx_age_group` (`age_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session-Practice Plan association
CREATE TABLE IF NOT EXISTS `session_practice_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `practice_plan_id` INT NOT NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`practice_plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drill categories
CREATE TABLE IF NOT EXISTS `drill_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `position_type` ENUM('player', 'goalie', 'both') DEFAULT 'both' COMMENT 'Drill category applies to: player, goalie, or both',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add position_type column to existing drill_categories table if it doesn't exist
-- ALTER TABLE `drill_categories` ADD COLUMN IF NOT EXISTS `position_type` ENUM('player', 'goalie', 'both') DEFAULT 'both' AFTER `description`;

-- Drills
CREATE TABLE IF NOT EXISTS `drills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `setup` TEXT DEFAULT NULL,
    `coaching_points` TEXT DEFAULT NULL,
    `progression` TEXT DEFAULT NULL,
    `category_id` INT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `diagram_data` TEXT DEFAULT NULL,
    `custom_image` VARCHAR(255) DEFAULT NULL,
    `video_url` VARCHAR(255) DEFAULT NULL,
    `ihs_source_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `version` INT DEFAULT 1,
    `parent_drill_id` INT DEFAULT NULL,
    `share_token` VARCHAR(64) DEFAULT NULL,
    `video_upload_path` VARCHAR(500) DEFAULT NULL COMMENT 'Path to uploaded video file',
    `nextcloud_image_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for drill media (RustFS URL)',
    `thumbnail_path` VARCHAR(500) DEFAULT NULL COMMENT 'RustFS path to video thumbnail image',
    FOREIGN KEY (`category_id`) REFERENCES `drill_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_drill_id`) REFERENCES `drills`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_share_token` (`share_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Practice Plan-Drill association
CREATE TABLE IF NOT EXISTS `practice_plan_drills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `practice_plan_id` INT NOT NULL,
    `drill_id` INT NOT NULL,
    `drill_order` INT DEFAULT 0,
    `duration_minutes` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`practice_plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`drill_id`) REFERENCES `drills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session bookings
-- Packages
CREATE TABLE IF NOT EXISTS `packages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `credits` INT NOT NULL,
    `age_group` VARCHAR(50) DEFAULT NULL,
    `skill_level` VARCHAR(50) DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `valid_days` INT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `store_credit` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Store credit value included in package',
    `show_on_landing` TINYINT(1) DEFAULT 0 COMMENT 'Whether to show on landing page',
    `package_type` VARCHAR(50) DEFAULT 'credits' COMMENT 'Type of package: credits, bundled, dollar_value',
    `enable_child_checkin` TINYINT(1) DEFAULT 0 COMMENT 'Enable child check-in/check-out for sessions in this package',
    `camp_start_date` DATE DEFAULT NULL COMMENT 'Camp start date',
    `camp_end_date` DATE DEFAULT NULL COMMENT 'Camp end date',
    `daily_start_time` TIME DEFAULT NULL COMMENT 'Default daily start time',
    `daily_end_time` TIME DEFAULT NULL COMMENT 'Default daily end time',
    `age_group_id` INT DEFAULT NULL COMMENT 'Optional age group restriction',
    `skill_level_id` INT DEFAULT NULL COMMENT 'Optional skill level restriction',
    `allow_individual_sessions` TINYINT(1) DEFAULT 0 COMMENT 'Allow purchasing individual sessions from multi-week program',
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User package purchases
CREATE TABLE IF NOT EXISTS `user_packages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `package_id` INT NOT NULL,
    `purchase_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `credits_remaining` INT NOT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `payment_status` ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    `amount_paid` DECIMAL(10,2) DEFAULT 0.00,
    `stripe_session_id` VARCHAR(255) DEFAULT NULL COMMENT 'Stripe checkout session ID for payment tracking',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_stripe_session` (`stripe_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discount codes
CREATE TABLE IF NOT EXISTS `discount_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `discount_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `max_uses` INT DEFAULT NULL,
    `times_used` INT DEFAULT 0,
    `valid_from` DATE DEFAULT NULL,
    `valid_until` DATE DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `store_credit_value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Store credit amount for store_credit type',
    `auto_generate_type` ENUM('none', 'new_registration', 'time_based', 'referral') DEFAULT 'none' COMMENT 'Type of auto-generated code',
    `days_since_registration` INT DEFAULT NULL COMMENT 'Trigger days for time_based codes',
    `description` VARCHAR(255) DEFAULT NULL COMMENT 'User-friendly description of the discount'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Video uploads
CREATE TABLE IF NOT EXISTS `videos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `coach_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `video_url` VARCHAR(255) NOT NULL,
    `hls_url` VARCHAR(500) DEFAULT NULL COMMENT 'HLS master playlist URL (api/media.php proxy path)',
    `hls_status` ENUM('pending', 'processing', 'ready', 'failed') DEFAULT NULL COMMENT 'HLS transcoding status',
    `hls_job_id` VARCHAR(36) DEFAULT NULL COMMENT 'Companion server HLS transcode job ID',
    `hls_master_url` VARCHAR(500) DEFAULT NULL COMMENT 'S3 key to master.m3u8 manifest',
    `hls_segments_path` VARCHAR(500) DEFAULT NULL COMMENT 'S3 prefix containing HLS segments',
    `dash_url` VARCHAR(500) DEFAULT NULL COMMENT 'MPEG-DASH MPD manifest URL (api/media.php proxy path)',
    `dash_manifest_url` VARCHAR(500) DEFAULT NULL COMMENT 'S3 key to DASH manifest.mpd',
    `thumbnail_url` VARCHAR(255) DEFAULT NULL,
    `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `video_type` ENUM('drill_review', 'coach_review', 'uploaded_by_athlete') DEFAULT 'drill_review',
    `video_category` ENUM('drill', 'game') DEFAULT 'drill',
    `drill_id` INT DEFAULT NULL,
    `session_id` INT DEFAULT NULL,
    `rep_number` INT DEFAULT 1,
    `game_date` DATE DEFAULT NULL,
    `team_played_on` VARCHAR(255) DEFAULT NULL,
    `opponent_team` VARCHAR(255) DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL,
    `local_path` VARCHAR(500) DEFAULT NULL,
    `is_uploaded_to_cloud` TINYINT(1) DEFAULT 0,
    `status` ENUM('pending_review', 'reviewed', 'archived') DEFAULT 'pending_review',
    `coach_notes` TEXT DEFAULT NULL,
    `athlete_notes` TEXT DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`drill_id`) REFERENCES `drills`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_video_category` (`video_category`),
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout exercises library
CREATE TABLE IF NOT EXISTS `exercise_library` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `equipment_needed` TEXT DEFAULT NULL,
    `difficulty_level` VARCHAR(50) DEFAULT NULL,
    `video_url` VARCHAR(255) DEFAULT NULL,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `nextcloud_image_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for exercise image (RustFS URL)',
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout plans
CREATE TABLE IF NOT EXISTS `workout_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `duration_weeks` INT DEFAULT NULL,
    `total_workouts` INT DEFAULT NULL,
    `difficulty_level` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout plan exercises
CREATE TABLE IF NOT EXISTS `workout_plan_exercises` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workout_plan_id` INT NOT NULL,
    `exercise_id` INT NOT NULL,
    `day_number` INT DEFAULT 1,
    `sets` INT DEFAULT NULL,
    `reps` VARCHAR(50) DEFAULT NULL,
    `rest_seconds` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `exercise_order` INT DEFAULT 0,
    FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercise_library`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete workout assignments
CREATE TABLE IF NOT EXISTS `athlete_workout_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `workout_plan_id` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `assigned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `start_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('active', 'completed', 'paused') DEFAULT 'active',
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete exercise custom settings (overrides for individual athlete assignments)
CREATE TABLE IF NOT EXISTS `athlete_exercise_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT NOT NULL,
    `exercise_id` INT NOT NULL,
    `custom_sets` INT DEFAULT NULL,
    `custom_reps` VARCHAR(50) DEFAULT NULL,
    `custom_weight` DECIMAL(10,2) DEFAULT NULL,
    `custom_weight_unit` ENUM('lbs', 'kg') DEFAULT 'lbs',
    `custom_rest_seconds` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`assignment_id`) REFERENCES `athlete_workout_assignments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercise_library`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_assignment_exercise` (`assignment_id`, `exercise_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete workout feedback
CREATE TABLE IF NOT EXISTS `athlete_workout_feedback` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT NOT NULL,
    `exercise_id` INT NOT NULL,
    `feedback` TEXT NOT NULL,
    `feedback_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `coach_response` TEXT DEFAULT NULL,
    `responded_at` TIMESTAMP NULL,
    FOREIGN KEY (`assignment_id`) REFERENCES `athlete_workout_assignments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercise_library`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition food library
CREATE TABLE IF NOT EXISTS `food_library` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `calories` DECIMAL(10,2) DEFAULT NULL,
    `protein_g` DECIMAL(10,2) DEFAULT NULL,
    `carbs_g` DECIMAL(10,2) DEFAULT NULL,
    `fat_g` DECIMAL(10,2) DEFAULT NULL,
    `serving_size` VARCHAR(100) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition plans
CREATE TABLE IF NOT EXISTS `nutrition_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL, -- Athlete this plan is assigned to (for legacy code)
    `coach_id` INT DEFAULT NULL, -- Coach who created the plan (for legacy code)
    `name` VARCHAR(255) NOT NULL, -- Official plan name (primary field)
    `title` VARCHAR(255) DEFAULT NULL, -- Legacy alias for backward compatibility
    `description` TEXT DEFAULT NULL,
    `content` TEXT DEFAULT NULL, -- Legacy detailed content field
    `created_by` INT NOT NULL,
    `target_calories` INT DEFAULT NULL,
    `target_protein_g` INT DEFAULT NULL,
    `target_carbs_g` INT DEFAULT NULL,
    `target_fat_g` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition plan meals
CREATE TABLE IF NOT EXISTS `nutrition_plan_meals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nutrition_plan_id` INT NOT NULL,
    `meal_type` ENUM('breakfast', 'lunch', 'dinner', 'snack', 'pre_workout', 'post_workout') DEFAULT 'breakfast',
    `day_number` INT DEFAULT 1,
    `meal_order` INT DEFAULT 0,
    FOREIGN KEY (`nutrition_plan_id`) REFERENCES `nutrition_plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition plan meal foods
CREATE TABLE IF NOT EXISTS `nutrition_plan_meal_foods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `meal_id` INT NOT NULL,
    `food_id` INT NOT NULL,
    `serving_quantity` DECIMAL(10,2) DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`meal_id`) REFERENCES `nutrition_plan_meals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`food_id`) REFERENCES `food_library`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete nutrition assignments
CREATE TABLE IF NOT EXISTS `athlete_nutrition_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `nutrition_plan_id` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `assigned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `start_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('active', 'completed', 'paused') DEFAULT 'active',
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`nutrition_plan_id`) REFERENCES `nutrition_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete meal portion custom settings (overrides for individual athlete nutrition assignments)
CREATE TABLE IF NOT EXISTS `athlete_meal_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT NOT NULL,
    `meal_id` INT NOT NULL,
    `food_id` INT NOT NULL,
    `custom_serving_quantity` DECIMAL(10,2) DEFAULT NULL,
    `custom_portion_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`assignment_id`) REFERENCES `athlete_nutrition_assignments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`meal_id`) REFERENCES `nutrition_plan_meals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`food_id`) REFERENCES `food_library`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_assignment_meal_food` (`assignment_id`, `meal_id`, `food_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete nutrition feedback
CREATE TABLE IF NOT EXISTS `athlete_nutrition_feedback` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT NOT NULL,
    `feedback` TEXT NOT NULL,
    `feedback_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `coach_response` TEXT DEFAULT NULL,
    `responded_at` TIMESTAMP NULL,
    FOREIGN KEY (`assignment_id`) REFERENCES `athlete_nutrition_assignments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance stats
CREATE TABLE IF NOT EXISTS `performance_stats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `stat_date` DATE NOT NULL,
    `stat_type` VARCHAR(100) NOT NULL,
    `stat_value` DECIMAL(10,2) NOT NULL,
    `stat_unit` VARCHAR(50) DEFAULT NULL,
    `session_id` INT DEFAULT NULL,
    `recorded_by` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_stat_type` (`stat_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goals
CREATE TABLE IF NOT EXISTS `goals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `created_by` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `goal_title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `goal_description` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `tags` VARCHAR(500) DEFAULT NULL,
    `target_value` DECIMAL(10,2) DEFAULT NULL,
    `current_value` DECIMAL(10,2) DEFAULT NULL,
    `target_date` DATE DEFAULT NULL,
    `completion_percentage` DECIMAL(5,2) DEFAULT 0.00,
    `status` ENUM('active', 'completed', 'abandoned', 'archived') DEFAULT 'active',
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mileage tracking
-- Expenses
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `expense_date` DATE NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `receipt_url` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `approved_by` INT DEFAULT NULL,
    `approved_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `vendor_name` VARCHAR(255) DEFAULT NULL,
    `subtotal` DECIMAL(10,2) DEFAULT NULL,
    `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) DEFAULT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `reference_number` VARCHAR(100) DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL,
    `ocr_data` JSON DEFAULT NULL,
    `ocr_processed` TINYINT(1) DEFAULT 0,
    `currency` VARCHAR(3) DEFAULT 'CAD',
    `payee_id` INT DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`expense_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions (payments, credits, refunds)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `transaction_type` ENUM('payment', 'credit', 'refund', 'package_purchase', 'session_booking') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `hst_amount` DECIMAL(10,2) DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `transaction_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`transaction_date`),
    INDEX `idx_type` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments (detailed payment tracking)
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `invoice_id` INT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `transaction_id` VARCHAR(255) DEFAULT NULL,
    `payment_status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_invoice` (`invoice_id`),
    INDEX `idx_date` (`payment_date`),
    INDEX `idx_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `read_status` TINYINT(1) DEFAULT 0,
    `link_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_read` (`user_id`, `read_status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation framework categories
CREATE TABLE IF NOT EXISTS `eval_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation framework skills
CREATE TABLE IF NOT EXISTS `eval_skills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `criteria` TEXT DEFAULT NULL COMMENT 'Evaluation criteria for this skill',
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `has_stopwatch` TINYINT(1) DEFAULT 0 COMMENT 'Whether this skill uses a stopwatch for timed evaluation',
    FOREIGN KEY (`category_id`) REFERENCES `eval_categories`(`id`) ON DELETE CASCADE,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation skill-to-category assignments (many-to-many)
CREATE TABLE IF NOT EXISTS `eval_skill_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `skill_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `eval_categories`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_skill_category` (`skill_id`, `category_id`),
    INDEX `idx_skill` (`skill_id`),
    INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete evaluations
CREATE TABLE IF NOT EXISTS `athlete_evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `evaluator_id` INT NOT NULL,
    `created_by` INT DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `skill_id` INT DEFAULT NULL,
    `rating` INT DEFAULT NULL,
    `comments` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `evaluation_date` DATE DEFAULT NULL,
    `eval_date` DATE DEFAULT NULL COMMENT 'Alias for evaluation_date for backward compatibility',
    `session_id` INT DEFAULT NULL,
    `status` ENUM('draft', 'completed', 'reviewed') DEFAULT 'completed',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_skill` (`skill_id`),
    INDEX `idx_athlete_date` (`athlete_id`, `evaluation_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled reports
CREATE TABLE IF NOT EXISTS `scheduled_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `report_name` VARCHAR(255) NOT NULL,
    `report_config` TEXT NOT NULL,
    `schedule_frequency` ENUM('daily', 'weekly', 'monthly') NOT NULL,
    `schedule_day` INT DEFAULT NULL,
    `schedule_time` TIME DEFAULT NULL,
    `recipients` TEXT NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_run_at` TIMESTAMP NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` LONGTEXT DEFAULT NULL,
    `setting_type` VARCHAR(50) DEFAULT 'text',
    `description` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (Nextcloud settings removed — all storage uses RustFS S3)

-- Audit log
-- Theme settings (key-value store for all theme/branding settings)
CREATE TABLE IF NOT EXISTS `theme_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_name` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default theme settings
INSERT IGNORE INTO `theme_settings` (`setting_name`, `setting_value`) VALUES
('theme_name', 'Arctic Wolves'),
('primary_color', '#6B46C1'),
('secondary_color', '#8B5CF6'),
('background_color', '#0A0A0F'),
('card_background_color', '#16161F'),
('text_color', '#FFFFFF'),
('text_muted_color', '#A8A8B8'),
('border_color', '#2D2D3F'),
('sidebar_color', '#0D0D14'),
('button_hover_color', '#7C3AED'),
('success_color', '#10B981'),
('error_color', '#EF4444'),
('warning_color', '#F59E0B'),
('logo_url', NULL),
('favicon_url', NULL),
('use_logo_as_favicon', '1'),
('logo_method', 'upload'),
('site_title', 'Arctic Wolves'),
('site_description', 'Elite Hockey Training'),
('hero_image_url', NULL),
('hero_title', 'Welcome to Arctic Wolves'),
('hero_subtitle', 'Elite Hockey Training Program'),
('hero_cta_text', 'Get Started'),
('hero_cta_url', '/register.php'),
('custom_css', NULL),
('business_card_front_bg_url', NULL),
('business_card_back_bg_url', NULL);

-- Cron jobs
CREATE TABLE IF NOT EXISTS `cron_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_name` VARCHAR(100) NOT NULL,
    `job_description` TEXT DEFAULT NULL,
    `schedule` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_run_at` TIMESTAMP NULL,
    `next_run_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- =========================================================
-- MISSING TABLES - Adding 55+ tables to reach 120+ total
-- =========================================================

-- Age groups for athlete categorization
CREATE TABLE IF NOT EXISTS `age_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `min_age` INT DEFAULT NULL,
    `max_age` INT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default age groups (U7 to U21)
-- min_age and max_age represent typical birth year ranges for hockey
INSERT INTO `age_groups` (`name`, `min_age`, `max_age`, `description`, `display_order`) VALUES
('U7', NULL, 6, 'Under 7 years old - ages 6 and under', 1),
('U8', NULL, 7, 'Under 8 years old - ages 7 and under', 2),
('U9', NULL, 8, 'Under 9 years old - ages 8 and under', 3),
('U10', NULL, 9, 'Under 10 years old - ages 9 and under', 4),
('U11', NULL, 10, 'Under 11 years old - ages 10 and under', 5),
('U12', NULL, 11, 'Under 12 years old - ages 11 and under', 6),
('U13', NULL, 12, 'Under 13 years old - ages 12 and under', 7),
('U14', NULL, 13, 'Under 14 years old - ages 13 and under', 8),
('U15', NULL, 14, 'Under 15 years old - ages 14 and under', 9),
('U16', NULL, 15, 'Under 16 years old - ages 15 and under', 10),
('U17', NULL, 16, 'Under 17 years old - ages 16 and under', 11),
('U18', NULL, 17, 'Under 18 years old - ages 17 and under', 12),
('U19', NULL, 18, 'Under 19 years old - ages 18 and under', 13),
('U20', NULL, 19, 'Under 20 years old - ages 19 and under', 14),
('U21', NULL, 20, 'Under 21 years old - ages 20 and under', 15)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Athlete notes from coaches
CREATE TABLE IF NOT EXISTS `athlete_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `coach_id` INT NOT NULL,
    `note_content` TEXT NOT NULL,
    `is_private` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_coach` (`coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete statistics tracking
CREATE TABLE IF NOT EXISTS `athlete_stats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `team_id` INT DEFAULT NULL,
    `season_id` INT DEFAULT NULL,
    `season` VARCHAR(50) DEFAULT NULL,
    `games_played` INT DEFAULT 0,
    `goals` INT DEFAULT 0,
    `assists` INT DEFAULT 0,
    `points` INT DEFAULT 0,
    `penalty_minutes` INT DEFAULT 0,
    `shots` INT DEFAULT 0,
    `shots_against` INT DEFAULT 0,
    `goals_against` INT DEFAULT 0,
    `saves` INT DEFAULT 0,
    `save_percentage` DECIMAL(5,3) DEFAULT 0.000,
    `gaa` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Goals Against Average for goalies',
    `wins` INT DEFAULT 0 COMMENT 'Wins for goalies',
    `losses` INT DEFAULT 0 COMMENT 'Losses for goalies',
    `ties` INT DEFAULT 0 COMMENT 'Ties for goalies',
    `shutouts` INT DEFAULT 0 COMMENT 'Shutouts for goalies',
    `plus_minus` INT DEFAULT 0,
    `height` INT DEFAULT NULL COMMENT 'Height in inches',
    `weight` INT DEFAULT NULL COMMENT 'Weight in pounds',
    `handedness` ENUM('left', 'right') DEFAULT NULL COMMENT 'Shoots left or right',
    `catching_hand` ENUM('left', 'right') DEFAULT NULL COMMENT 'Goalie catching hand',
    `jersey_number` INT DEFAULT NULL COMMENT 'Jersey number',
    `team` VARCHAR(255) DEFAULT NULL COMMENT 'Current team name',
    `league` VARCHAR(255) DEFAULT NULL COMMENT 'League name',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_team` (`team_id`),
    INDEX `idx_season` (`season`),
    INDEX `idx_season_id` (`season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete team memberships (historical)
CREATE TABLE IF NOT EXISTS `athlete_teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT DEFAULT NULL,
    `user_id` INT DEFAULT NULL COMMENT 'Alternative user reference for backward compatibility',
    `team_id` INT DEFAULT NULL,
    `team_name` VARCHAR(255) DEFAULT NULL COMMENT 'Team name string for backward compatibility',
    `league` VARCHAR(100) DEFAULT NULL COMMENT 'League name (e.g., CSSHL, BCMML)',
    `season` VARCHAR(50) DEFAULT NULL,
    `season_year` VARCHAR(10) DEFAULT NULL COMMENT 'Season year (e.g., 2024)',
    `season_type` VARCHAR(50) DEFAULT NULL COMMENT 'Season type (e.g., Fall, Winter, Spring)',
    `jersey_number` INT DEFAULT NULL,
    `position` VARCHAR(50) DEFAULT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    `is_current` TINYINT(1) DEFAULT 0 COMMENT 'Whether this is the current team',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_team` (`team_id`),
    INDEX `idx_season` (`season`),
    INDEX `idx_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phone directory entries for non-user items (rooms, shared lines, external numbers)
CREATE TABLE IF NOT EXISTS `phone_directory_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `display_name` VARCHAR(255) NOT NULL COMMENT 'Name shown in directory (e.g., Board Room)',
    `extension` VARCHAR(20) DEFAULT NULL COMMENT 'Phone extension number',
    `did` VARCHAR(20) DEFAULT NULL COMMENT 'Direct Inward Dialing number',
    `email` VARCHAR(255) DEFAULT NULL COMMENT 'Contact email address',
    `entry_type` ENUM('room', 'shared', 'external', 'other') DEFAULT 'other' COMMENT 'Type of directory entry',
    `description` VARCHAR(500) DEFAULT NULL COMMENT 'Optional description',
    `created_by` INT DEFAULT NULL COMMENT 'Admin user who created the entry',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs - history and restore point for all admin tasks
-- Tracks all changes with old/new values for potential restoration
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action_type` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100) DEFAULT NULL,
    `record_id` INT DEFAULT NULL,
    `action` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changes` TEXT DEFAULT NULL,
    `old_values` TEXT DEFAULT NULL,
    `new_values` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `session_id` VARCHAR(100) DEFAULT NULL,
    `is_demo` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action_type`),
    INDEX `idx_action_name` (`action`),
    INDEX `idx_table` (`table_name`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_demo` (`is_demo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled backup jobs
CREATE TABLE IF NOT EXISTS `backup_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `schedule` VARCHAR(50) NOT NULL,
    `backup_type` ENUM('full', 'incremental', 'schema_only', 'data_only') DEFAULT 'full',
    `destination_type` ENUM('local', 'smb', 'ftp', 's3', 'both') DEFAULT 's3',
    `nextcloud_folder` VARCHAR(255) DEFAULT NULL,
    `smb_path` VARCHAR(255) DEFAULT NULL,
    `smb_username` VARCHAR(100) DEFAULT NULL,
    `smb_password` VARCHAR(255) DEFAULT NULL,
    `smb_domain` VARCHAR(100) DEFAULT NULL,
    `retention_days` INT DEFAULT 30,
    `keep_count` INT NOT NULL DEFAULT 3 COMMENT 'Number of successful backup copies to retain per job',
    `last_backup` TIMESTAMP NULL,
    `next_backup` TIMESTAMP NULL,
    `status` ENUM('active', 'paused', 'disabled') DEFAULT 'active',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`),
    INDEX `idx_next_backup` (`next_backup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Database backup history
CREATE TABLE IF NOT EXISTS `backup_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `backup_job_id` INT DEFAULT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `destination` VARCHAR(255) DEFAULT NULL,
    `backup_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('success', 'failed', 'partial') DEFAULT 'success',
    `error_message` TEXT DEFAULT NULL,
    `duration_seconds` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`backup_job_id`) REFERENCES `backup_jobs`(`id`) ON DELETE SET NULL,
    INDEX `idx_job` (`backup_job_id`),
    INDEX `idx_date` (`backup_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session bookings (alias/duplicate of session_bookings for compatibility)
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `booking_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `payment_status` ENUM('pending', 'paid', 'refunded', 'cancelled') DEFAULT 'pending',
    `amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Final amount after discounts (original_price - discount)',
    `amount_paid` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Amount actually paid by customer',
    `original_price` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Original session price before any discounts',
    `discount_code` VARCHAR(50) DEFAULT NULL COMMENT 'Applied discount code if any',
    `stripe_session_id` VARCHAR(255) DEFAULT NULL COMMENT 'Stripe checkout session ID for payment tracking',
    `status` ENUM('confirmed', 'cancelled', 'waitlisted') DEFAULT 'confirmed',
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_stripe_session` (`stripe_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cloud receipt storage tracking
CREATE TABLE IF NOT EXISTS `cloud_receipts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `expense_id` INT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `cloud_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sync_status` ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    `last_sync` TIMESTAMP NULL,
    FOREIGN KEY (`expense_id`) REFERENCES `expenses`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_expense` (`expense_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database maintenance logs
CREATE TABLE IF NOT EXISTS `database_maintenance_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `maintenance_type` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('started', 'completed', 'failed') DEFAULT 'started',
    `start_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `end_time` TIMESTAMP NULL,
    `rows_affected` BIGINT DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `performed_by` INT DEFAULT NULL,
    FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_type` (`maintenance_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drill tags for categorization
CREATE TABLE IF NOT EXISTS `drill_tags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `drill_id` INT NOT NULL,
    `tag_name` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`drill_id`) REFERENCES `drills`(`id`) ON DELETE CASCADE,
    INDEX `idx_drill` (`drill_id`),
    INDEX `idx_tag` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email sending logs
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `to_email` VARCHAR(255) NOT NULL,
    `from_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body` TEXT DEFAULT NULL,
    `status` ENUM('sent', 'failed', 'queued') DEFAULT 'queued',
    `error_message` TEXT DEFAULT NULL,
    `sent_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_to_email` (`to_email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation media (photos/videos)
CREATE TABLE IF NOT EXISTS `evaluation_media` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `evaluation_id` INT NOT NULL,
    `media_type` ENUM('photo', 'video', 'document') DEFAULT 'photo',
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `uploaded_by` INT NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for persistent media (RustFS URL)',
    `score_id` INT DEFAULT NULL COMMENT 'FK to evaluation_scores for per-skill media',
    `media_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL or path to the uploaded media file',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`evaluation_id`) REFERENCES `athlete_evaluations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_evaluation` (`evaluation_id`),
    INDEX `idx_media_type` (`media_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation scores (detailed breakdown)
CREATE TABLE IF NOT EXISTS `evaluation_scores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `evaluation_id` INT DEFAULT NULL,
    `athlete_id` INT NOT NULL,
    `evaluator_id` INT NOT NULL,
    `skill_id` INT NOT NULL,
    `score` DECIMAL(5,2) NOT NULL,
    `max_score` DECIMAL(5,2) DEFAULT 10.00,
    `evaluation_date` DATE NOT NULL,
    `comments` TEXT DEFAULT NULL,
    `session_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `public_notes` TEXT DEFAULT NULL COMMENT 'Coach notes visible to athlete',
    `private_notes` TEXT DEFAULT NULL COMMENT 'Coach-only private notes',
    FOREIGN KEY (`evaluation_id`) REFERENCES `athlete_evaluations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_evaluation` (`evaluation_id`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_skill` (`skill_id`),
    INDEX `idx_date` (`evaluation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workouts (workout sessions - distinct from workout_plans)
-- MOVED BEFORE exercises table to satisfy foreign key constraint
CREATE TABLE IF NOT EXISTS `workouts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `coach_id` INT DEFAULT NULL,
    `workout_name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL, 
    `description` TEXT DEFAULT NULL,
    `link` VARCHAR(500) DEFAULT NULL,
    `workout_date` DATE DEFAULT NULL,
    `workout_type` VARCHAR(100) DEFAULT NULL,
    `duration_minutes` INT DEFAULT NULL,
    `status` ENUM('planned', 'completed', 'skipped') DEFAULT 'planned',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_date` (`workout_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exercises (workout exercise entries - distinct from exercise_library)
CREATE TABLE IF NOT EXISTS `exercises` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workout_id` INT NOT NULL,
    `exercise_library_id` INT DEFAULT NULL,
    `exercise_name` VARCHAR(255) NOT NULL,
    `sets` INT DEFAULT NULL,
    `reps` VARCHAR(50) DEFAULT NULL,
    `weight` DECIMAL(10,2) DEFAULT NULL,
    `duration_minutes` INT DEFAULT NULL,
    `rest_seconds` INT DEFAULT NULL,
    `order_num` INT DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`workout_id`) REFERENCES `workouts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_library_id`) REFERENCES `exercise_library`(`id`) ON DELETE SET NULL,
    INDEX `idx_workout` (`workout_id`),
    INDEX `idx_order` (`order_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expense categories
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `display_order` INT DEFAULT 0,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature version tracking
CREATE TABLE IF NOT EXISTS `feature_versions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `feature_name` VARCHAR(255) NOT NULL,
    `version` VARCHAR(50) NOT NULL,
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `applied_by` INT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `release_date` DATE DEFAULT NULL,
    `database_changes` JSON DEFAULT NULL,
    `file_changes` JSON DEFAULT NULL,
    `manifest` JSON DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_feature_version` (`feature_name`, `version`),
    INDEX `idx_feature` (`feature_name`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foods (nutrition food items - distinct from food_library)
CREATE TABLE IF NOT EXISTS `foods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `calories` DECIMAL(10,2) DEFAULT NULL,
    `protein_g` DECIMAL(10,2) DEFAULT NULL,
    `carbs_g` DECIMAL(10,2) DEFAULT NULL,
    `fat_g` DECIMAL(10,2) DEFAULT NULL,
    `fiber_g` DECIMAL(10,2) DEFAULT NULL,
    `sugar_g` DECIMAL(10,2) DEFAULT NULL,
    `serving_size` VARCHAR(100) DEFAULT NULL,
    `barcode` VARCHAR(50) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category`),
    INDEX `idx_barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal evaluations (comprehensive goal assessments)
-- MOVED BEFORE dependent tables to satisfy foreign key constraints
CREATE TABLE IF NOT EXISTS `goal_evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `evaluator_id` INT NOT NULL,
    `evaluation_date` DATE NOT NULL,
    `score` DECIMAL(5,2) DEFAULT NULL,
    `progress_percentage` DECIMAL(5,2) DEFAULT NULL,
    `comments` TEXT DEFAULT NULL,
    `status` ENUM('in_progress', 'completed', 'archived') DEFAULT 'in_progress',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_goal` (`goal_id`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_date` (`evaluation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal evaluation approvals
CREATE TABLE IF NOT EXISTS `goal_eval_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_evaluation_id` INT NOT NULL,
    `approver_id` INT NOT NULL,
    `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `comments` TEXT DEFAULT NULL,
    `approved_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_evaluation_id`) REFERENCES `goal_evaluations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_goal_eval` (`goal_evaluation_id`),
    INDEX `idx_status` (`approval_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal evaluation progress tracking
CREATE TABLE IF NOT EXISTS `goal_eval_progress` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_evaluation_id` INT NOT NULL,
    `progress_date` DATE NOT NULL,
    `progress_percentage` DECIMAL(5,2) DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `recorded_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for eval goal media (RustFS URL)',
    FOREIGN KEY (`goal_evaluation_id`) REFERENCES `goal_evaluations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_goal_eval` (`goal_evaluation_id`),
    INDEX `idx_date` (`progress_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal evaluation steps
CREATE TABLE IF NOT EXISTS `goal_eval_steps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_evaluation_id` INT NOT NULL,
    `step_number` INT NOT NULL,
    `step_description` TEXT NOT NULL,
    `is_completed` TINYINT(1) DEFAULT 0,
    `completed_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_evaluation_id`) REFERENCES `goal_evaluations`(`id`) ON DELETE CASCADE,
    INDEX `idx_goal_eval` (`goal_evaluation_id`),
    INDEX `idx_step_num` (`step_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: goal_evaluations table moved earlier in schema to satisfy FK constraints

-- Goal history (change tracking)
CREATE TABLE IF NOT EXISTS `goal_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `user_id` INT NOT NULL,
    `changes` JSON DEFAULT NULL,
    `field_changed` VARCHAR(100) DEFAULT NULL,
    `old_value` TEXT DEFAULT NULL,
    `new_value` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL COMMENT 'Legacy column - use user_id instead',
    `change_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_goal` (`goal_id`),
    INDEX `idx_date` (`change_date`),
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal progress tracking
CREATE TABLE IF NOT EXISTS `goal_progress` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `progress_date` DATE DEFAULT NULL,
    `progress_value` DECIMAL(10,2) DEFAULT NULL,
    `progress_percentage` DECIMAL(5,2) DEFAULT NULL,
    `progress_note` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `recorded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_goal` (`goal_id`),
    INDEX `idx_date` (`progress_date`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal steps (milestones)
CREATE TABLE IF NOT EXISTS `goal_steps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `step_order` INT NOT NULL DEFAULT 1,
    `step_number` INT DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `step_description` TEXT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `target_date` DATE DEFAULT NULL,
    `is_completed` TINYINT(1) DEFAULT 0,
    `completed_date` DATE DEFAULT NULL,
    `completed_at` TIMESTAMP NULL,
    `completed_by` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_goal` (`goal_id`),
    INDEX `idx_step_order` (`step_order`),
    INDEX `idx_step_num` (`step_number`),
    INDEX `idx_completed` (`is_completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Managed athletes (coach-athlete relationships)
CREATE TABLE IF NOT EXISTS `managed_athletes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id` INT DEFAULT NULL,
    `athlete_id` INT NOT NULL,
    `parent_id` INT DEFAULT NULL COMMENT 'Parent user who manages this athlete',
    `relationship` VARCHAR(50) DEFAULT 'parent' COMMENT 'Relationship type: parent, grandparent, guardian, other',
    `can_book` TINYINT(1) DEFAULT 1 COMMENT 'Whether this parent can book sessions for the athlete',
    `can_view_stats` TINYINT(1) DEFAULT 1 COMMENT 'Whether this parent can view athlete stats',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_coach_athlete` (`coach_id`, `athlete_id`),
    UNIQUE KEY `unique_parent_athlete` (`parent_id`, `athlete_id`),
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_parent` (`parent_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete Coaches (multiple coach assignments to athletes)
-- This table supports assigning multiple coaches to a single athlete
-- A single coach can hold multiple roles for the same athlete (e.g. on-ice coach and health coach)
CREATE TABLE IF NOT EXISTS `athlete_coaches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `coach_id` INT NOT NULL,
    `role_type` ENUM('primary', 'assistant', 'health', 'team') DEFAULT 'primary',
    `assigned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_athlete_coach_role` (`athlete_id`, `coach_id`, `role_type`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mileage logs (trip tracking with multi-stop support)
CREATE TABLE IF NOT EXISTS `mileage_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `trip_date` DATE NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `athlete_id` INT DEFAULT NULL,
    `session_id` INT DEFAULT NULL,
    `purpose` VARCHAR(255) DEFAULT NULL,
    `total_distance_km` DECIMAL(10,2) DEFAULT 0,
    `total_distance_miles` DECIMAL(10,2) DEFAULT 0,
    `reimbursement_rate` DECIMAL(5,2) DEFAULT 0.68,
    `reimbursement_amount` DECIMAL(10,2) DEFAULT 0,
    `is_reimbursed` TINYINT(1) DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`trip_date`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mileage stops (multi-stop trip tracking)
CREATE TABLE IF NOT EXISTS `mileage_stops` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mileage_log_id` INT NOT NULL,
    `stop_order` INT NOT NULL DEFAULT 0,
    `location_name` VARCHAR(255) DEFAULT NULL,
    `address` VARCHAR(255) NOT NULL,
    `arrival_time` TIME DEFAULT NULL,
    `departure_time` TIME DEFAULT NULL,
    `purpose` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`mileage_log_id`) REFERENCES `mileage_logs`(`id`) ON DELETE CASCADE,
    INDEX `idx_log` (`mileage_log_id`),
    INDEX `idx_stop_order` (`stop_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition plan categories
CREATE TABLE IF NOT EXISTS `nutrition_plan_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition templates (parent table - must be created before nutrition_template_items)
CREATE TABLE IF NOT EXISTS `nutrition_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category_id` INT DEFAULT NULL,
    `target_calories` INT DEFAULT NULL,
    `target_protein_g` INT DEFAULT NULL,
    `target_carbs_g` INT DEFAULT NULL,
    `target_fat_g` INT DEFAULT NULL,
    `duration_days` INT DEFAULT 7,
    `created_by` INT NOT NULL,
    `is_public` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `nutrition_plan_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition template items (child table - references nutrition_templates)
CREATE TABLE IF NOT EXISTS `nutrition_template_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NOT NULL,
    `meal_type` ENUM('breakfast', 'lunch', 'dinner', 'snack', 'pre_workout', 'post_workout') DEFAULT 'breakfast',
    `food_id` INT NOT NULL,
    `serving_quantity` DECIMAL(10,2) DEFAULT 1,
    `day_number` INT DEFAULT 1,
    `order_num` INT DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`template_id`) REFERENCES `nutrition_templates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`food_id`) REFERENCES `food_library`(`id`) ON DELETE CASCADE,
    INDEX `idx_template` (`template_id`),
    INDEX `idx_meal_type` (`meal_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package sessions (session credits included in packages)
CREATE TABLE IF NOT EXISTS `package_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `session_id` INT DEFAULT NULL COMMENT 'Specific session linked to package',
    `session_type_id` INT DEFAULT NULL,
    `num_sessions` INT DEFAULT 1,
    `session_description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`session_type_id`) REFERENCES `session_types`(`id`) ON DELETE SET NULL,
    INDEX `idx_package` (`package_id`),
    INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User permissions
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `permission_name` VARCHAR(100) NOT NULL UNIQUE,
    `permission_description` TEXT DEFAULT NULL,
    `module` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Practice plan categories
CREATE TABLE IF NOT EXISTS `practice_plan_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refunds
CREATE TABLE IF NOT EXISTS `refunds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `transaction_id` INT DEFAULT NULL,
    `booking_id` INT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    `requested_by` INT NOT NULL,
    `approved_by` INT DEFAULT NULL,
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processed_at` TIMESTAMP NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report schedules
CREATE TABLE IF NOT EXISTS `report_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `report_name` VARCHAR(255) NOT NULL,
    `report_type` VARCHAR(100) NOT NULL,
    `schedule_frequency` ENUM('daily', 'weekly', 'monthly', 'quarterly', 'annually') NOT NULL,
    `schedule_day` INT DEFAULT NULL,
    `schedule_time` TIME DEFAULT NULL,
    `format` ENUM('pdf', 'excel', 'csv') DEFAULT 'pdf',
    `recipients` TEXT NOT NULL,
    `parameters` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_run` TIMESTAMP NULL,
    `next_run` TIMESTAMP NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_next_run` (`next_run`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generated reports
CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `report_name` VARCHAR(255) NOT NULL,
    `report_type` VARCHAR(100) NOT NULL,
    `generated_by` INT NOT NULL,
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `parameters` TEXT DEFAULT NULL,
    `status` ENUM('generating', 'completed', 'failed') DEFAULT 'generating',
    FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_type` (`report_type`),
    INDEX `idx_generated_at` (`generated_at`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions mapping
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` ENUM('athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach', 'front_desk_staff', 'hr', 'accounting', 'goalie_dev', 'player_dev') NOT NULL,
    `permission_id` INT NOT NULL,
    `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_role_permission` (`role`, `permission_id`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security event logs
CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `severity` ENUM('info', 'warning', 'critical') DEFAULT 'info',
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `request_uri` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`event_type`),
    INDEX `idx_severity` (`severity`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security scan results
CREATE TABLE IF NOT EXISTS `security_scans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `scan_type` VARCHAR(100) NOT NULL,
    `scan_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('passed', 'failed', 'warning') DEFAULT 'passed',
    `findings_count` INT DEFAULT 0,
    `findings_data` TEXT DEFAULT NULL,
    `performed_by` INT DEFAULT NULL,
    `duration_seconds` INT DEFAULT NULL,
    FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_type` (`scan_type`),
    INDEX `idx_date` (`scan_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session templates (reusable session configurations)
CREATE TABLE IF NOT EXISTS `session_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `session_type_id` INT DEFAULT NULL,
    `duration_minutes` INT DEFAULT 60,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `max_participants` INT DEFAULT NULL,
    `age_group` VARCHAR(50) DEFAULT NULL,
    `skill_level` VARCHAR(50) DEFAULT NULL,
    `practice_plan_id` INT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `is_public` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_type_id`) REFERENCES `session_types`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`practice_plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_type` (`session_type_id`),
    INDEX `idx_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skill levels
CREATE TABLE IF NOT EXISTS `skill_levels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `level_order` INT DEFAULT 0,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order` (`level_order`),
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default skill levels
INSERT INTO `skill_levels` (`name`, `description`, `level_order`, `display_order`) VALUES
('C', 'C Level - Entry recreational level', 1, 1),
('B', 'B Level - Intermediate recreational', 2, 2),
('BB', 'BB Level - Advanced recreational', 3, 3),
('A', 'A Level - Competitive entry level', 4, 4),
('AA', 'AA Level - Competitive intermediate', 5, 5),
('AAA', 'AAA Level - Competitive elite', 6, 6),
('Jr C', 'Junior C - Junior entry level', 7, 7),
('Jr B', 'Junior B - Junior intermediate level', 8, 8),
('Jr A', 'Junior A - Junior elite level', 9, 9),
('Recreational', 'Recreational - Casual play', 10, 10),
('House League', 'House League - Organized recreational', 11, 11),
('Pro', 'Professional Level', 12, 12),
('All Levels', 'Suitable for all skill levels', 13, 13)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- System notifications (global announcements)
CREATE TABLE IF NOT EXISTS `system_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `notification_type` ENUM('info', 'warning', 'alert', 'maintenance') DEFAULT 'info',
    `start_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `end_date` TIMESTAMP NULL,
    `target_roles` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_dates` (`start_date`, `end_date`),
    INDEX `idx_type` (`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Testing results (automated tests)
CREATE TABLE IF NOT EXISTS `testing_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `test_suite` VARCHAR(100) NOT NULL,
    `test_name` VARCHAR(255) NOT NULL,
    `status` ENUM('passed', 'failed', 'skipped') DEFAULT 'passed',
    `duration_ms` INT DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `stack_trace` TEXT DEFAULT NULL,
    `run_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_suite` (`test_suite`),
    INDEX `idx_status` (`status`),
    INDEX `idx_date` (`run_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training programs (structured multi-week programs)
CREATE TABLE IF NOT EXISTS `training_programs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `duration_weeks` INT NOT NULL,
    `difficulty_level` VARCHAR(50) DEFAULT NULL,
    `age_group` VARCHAR(50) DEFAULT NULL,
    `program_type` ENUM('skill_development', 'conditioning', 'strength', 'combined') DEFAULT 'combined',
    `created_by` INT NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_type` (`program_type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete program enrollments (links athletes to training programs)
CREATE TABLE IF NOT EXISTS `athlete_programs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `program_id` INT NOT NULL,
    `status` ENUM('active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
    `enrollment_date` DATE NOT NULL,
    `completion_date` DATE DEFAULT NULL,
    `progress_percentage` INT DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`program_id`) REFERENCES `training_programs`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_athlete_program` (`athlete_id`, `program_id`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_program` (`program_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Credits and refunds tracking
CREATE TABLE IF NOT EXISTS `credits_refunds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `transaction_type` ENUM('credit', 'refund') DEFAULT 'credit',
    `amount` DECIMAL(10,2) NOT NULL,
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    `invoice_id` INT DEFAULT NULL,
    `payment_id` INT DEFAULT NULL,
    `processed_by` INT DEFAULT NULL,
    `processed_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`transaction_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee terminations tracking
CREATE TABLE IF NOT EXISTS `employee_terminations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `termination_date` DATE NOT NULL,
    `termination_type` ENUM('voluntary', 'involuntary', 'retirement', 'contract_end', 'mutual') DEFAULT 'voluntary',
    `reason_category` VARCHAR(100) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `notice_period_days` INT DEFAULT NULL,
    `final_pay_date` DATE DEFAULT NULL,
    `final_pay_amount` DECIMAL(10,2) DEFAULT NULL,
    `exit_interview_completed` TINYINT(1) DEFAULT 0,
    `exit_interview_notes` TEXT DEFAULT NULL,
    `equipment_returned` TINYINT(1) DEFAULT 0,
    `access_revoked` TINYINT(1) DEFAULT 0,
    `offboarding_checklist` JSON DEFAULT NULL,
    `final_comments` TEXT DEFAULT NULL,
    `documents_path` VARCHAR(500) DEFAULT NULL,
    `nextcloud_folder` VARCHAR(500) DEFAULT NULL,
    `processed_by` INT NOT NULL,
    `status` ENUM('pending', 'scheduled', 'in_progress', 'completed') DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`termination_date`),
    INDEX `idx_type` (`termination_type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Termination documents tracking
CREATE TABLE IF NOT EXISTS `termination_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `termination_id` INT NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `document_type` VARCHAR(50) DEFAULT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `uploaded_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`termination_id`) REFERENCES `employee_terminations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_termination` (`termination_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User credits (flexible credit system)
CREATE TABLE IF NOT EXISTS `user_credits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `credit_type` VARCHAR(50) NOT NULL,
    `credits` INT NOT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `source` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`credit_type`),
    INDEX `idx_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User package credits (credit tracking per package)
CREATE TABLE IF NOT EXISTS `user_package_credits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_package_id` INT NOT NULL,
    `credits_used` INT DEFAULT 0,
    `credits_remaining` INT NOT NULL,
    `last_used` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_package_id`) REFERENCES `user_packages`(`id`) ON DELETE CASCADE,
    INDEX `idx_package` (`user_package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User-specific permissions (overrides)
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    `granted_by` INT NOT NULL,
    `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_permission` (`user_id`, `permission_id`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User workout items (individual workout exercise logs)
-- User workouts (parent table - workout sessions)
CREATE TABLE IF NOT EXISTS `user_workouts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `workout_plan_id` INT DEFAULT NULL,
    `coach_id` INT DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `assigned_date` DATE DEFAULT NULL,
    `workout_date` DATE NOT NULL,
    `status` ENUM('scheduled', 'in_progress', 'completed', 'skipped') DEFAULT 'scheduled',
    `duration_minutes` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`workout_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User workout items (child table - references user_workouts)
CREATE TABLE IF NOT EXISTS `user_workout_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_workout_id` INT NOT NULL,
    `exercise_id` INT NOT NULL,
    `sets_completed` INT DEFAULT 0,
    `reps_completed` VARCHAR(50) DEFAULT NULL,
    `weight_used` DECIMAL(10,2) DEFAULT NULL,
    `duration_minutes` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_workout_id`) REFERENCES `user_workouts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercise_library`(`id`) ON DELETE CASCADE,
    INDEX `idx_workout` (`user_workout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout plan categories
CREATE TABLE IF NOT EXISTS `workout_plan_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout template items
-- Workout templates (parent table)
CREATE TABLE IF NOT EXISTS `workout_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category_id` INT DEFAULT NULL,
    `duration_weeks` INT DEFAULT NULL,
    `difficulty_level` VARCHAR(50) DEFAULT NULL,
    `created_by` INT NOT NULL,
    `is_public` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `workout_plan_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout template items (child table - references workout_templates)
CREATE TABLE IF NOT EXISTS `workout_template_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NOT NULL,
    `exercise_id` INT NOT NULL,
    `day_number` INT DEFAULT 1,
    `sets` INT DEFAULT NULL,
    `reps` VARCHAR(50) DEFAULT NULL,
    `rest_seconds` INT DEFAULT NULL,
    `order_num` INT DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`template_id`) REFERENCES `workout_templates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercise_library`(`id`) ON DELETE CASCADE,
    INDEX `idx_template` (`template_id`),
    INDEX `idx_day` (`day_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- ADDITIONAL TABLES - Expanding to 120+ tables
-- Generated from comprehensive PHP codebase analysis
-- =========================================================
-- NOTE: workouts table moved earlier in schema to satisfy exercises FK constraint

-- Age groups for athlete categorization

-- =========================================================
-- ADDITIONAL TABLES TO REACH 120+ (21 more tables)
-- =========================================================

-- Session attendance tracking
CREATE TABLE IF NOT EXISTS `session_attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `attendance_status` ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
    `check_in_time` TIMESTAMP NULL,
    `check_out_time` TIMESTAMP NULL,
    `notes` TEXT DEFAULT NULL,
    `recorded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_session` (`session_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`attendance_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Equipment inventory
CREATE TABLE IF NOT EXISTS `equipment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `equipment_type` VARCHAR(100) DEFAULT NULL,
    `quantity` INT DEFAULT 0,
    `condition` ENUM('new', 'good', 'fair', 'poor', 'damaged') DEFAULT 'good',
    `purchase_date` DATE DEFAULT NULL,
    `purchase_price` DECIMAL(10,2) DEFAULT NULL,
    `location_id` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    INDEX `idx_type` (`equipment_type`),
    INDEX `idx_condition` (`condition`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Equipment maintenance logs
CREATE TABLE IF NOT EXISTS `equipment_maintenance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `equipment_id` INT NOT NULL,
    `maintenance_type` VARCHAR(100) NOT NULL,
    `maintenance_date` DATE NOT NULL,
    `description` TEXT DEFAULT NULL,
    `cost` DECIMAL(10,2) DEFAULT NULL,
    `performed_by` INT DEFAULT NULL,
    `next_maintenance_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_equipment` (`equipment_id`),
    INDEX `idx_date` (`maintenance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session feedback
CREATE TABLE IF NOT EXISTS `session_feedback` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` INT DEFAULT NULL,
    `feedback_text` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_session` (`session_id`),
    INDEX `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coach availability
CREATE TABLE IF NOT EXISTS `coach_availability` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id` INT NOT NULL,
    `day_of_week` ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `location_id` INT DEFAULT NULL,
    `is_recurring` TINYINT(1) DEFAULT 1,
    `effective_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_day` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coach certifications
CREATE TABLE IF NOT EXISTS `coach_certifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id` INT NOT NULL,
    `certification_name` VARCHAR(255) NOT NULL,
    `issuing_organization` VARCHAR(255) DEFAULT NULL,
    `issue_date` DATE DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `certification_number` VARCHAR(100) DEFAULT NULL,
    `document_path` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('active', 'expired', 'pending_renewal') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment methods
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `payment_type` ENUM('credit_card', 'debit_card', 'bank_account', 'paypal', 'other') NOT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `card_last_four` VARCHAR(4) DEFAULT NULL,
    `card_brand` VARCHAR(50) DEFAULT NULL,
    `expiry_month` INT DEFAULT NULL,
    `expiry_year` INT DEFAULT NULL,
    `billing_address` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE DEFAULT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `status` ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    `paid_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_invoice_num` (`invoice_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice line items
CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `description` VARCHAR(500) NOT NULL,
    `quantity` INT DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `item_type` VARCHAR(100) DEFAULT NULL,
    `reference_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    INDEX `idx_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Waitlists
CREATE TABLE IF NOT EXISTS `waitlists` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `position` INT NOT NULL,
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notified_at` TIMESTAMP NULL,
    `status` ENUM('waiting', 'offered', 'accepted', 'declined', 'expired') DEFAULT 'waiting',
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_session_user` (`session_id`, `user_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversations (links two users in a messaging thread)
CREATE TABLE IF NOT EXISTS `conversations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `participant_one_id` INT NOT NULL,
    `participant_two_id` INT NOT NULL,
    `last_message_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`participant_one_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`participant_two_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_conversation` (`participant_one_id`, `participant_two_id`),
    INDEX `idx_participant_one` (`participant_one_id`),
    INDEX `idx_participant_two` (`participant_two_id`),
    INDEX `idx_last_message` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages/communication
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT DEFAULT NULL,
    `from_user_id` INT NOT NULL,
    `to_user_id` INT NOT NULL,
    `subject` VARCHAR(512) DEFAULT NULL,
    `message_body` MEDIUMTEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` TIMESTAMP NULL,
    `parent_message_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_message_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL,
    INDEX `idx_conversation` (`conversation_id`),
    INDEX `idx_from` (`from_user_id`),
    INDEX `idx_to` (`to_user_id`),
    INDEX `idx_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message attachments
CREATE TABLE IF NOT EXISTS `message_attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE,
    INDEX `idx_message` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcements
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `announcement_type` ENUM('general', 'event', 'maintenance', 'important') DEFAULT 'general',
    `target_audience` VARCHAR(255) DEFAULT NULL,
    `published_by` INT NOT NULL,
    `published_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`published_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_type` (`announcement_type`),
    INDEX `idx_published` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event calendar
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `event_type` VARCHAR(100) DEFAULT NULL,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME DEFAULT NULL,
    `location_id` INT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `is_public` TINYINT(1) DEFAULT 0,
    `max_participants` INT DEFAULT NULL,
    `registration_required` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_start` (`start_datetime`),
    INDEX `idx_type` (`event_type`),
    INDEX `idx_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event registrations
CREATE TABLE IF NOT EXISTS `event_registrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `registration_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('registered', 'cancelled', 'attended', 'no_show') DEFAULT 'registered',
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_event_user` (`event_id`, `user_id`),
    INDEX `idx_event` (`event_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User login history
CREATE TABLE IF NOT EXISTS `login_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `login_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `logout_time` TIMESTAMP NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `login_status` ENUM('success', 'failed', 'blocked') DEFAULT 'success',
    `failure_reason` VARCHAR(255) DEFAULT NULL,
    `last_activity` TIMESTAMP NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_login_time` (`login_time`),
    INDEX `idx_status` (`login_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `used_at` TIMESTAMP NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API keys
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `api_key` VARCHAR(255) NOT NULL UNIQUE,
    `api_secret` VARCHAR(255) DEFAULT NULL,
    `key_name` VARCHAR(100) DEFAULT NULL,
    `permissions` TEXT DEFAULT NULL,
    `last_used` TIMESTAMP NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_key` (`api_key`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File uploads tracking
CREATE TABLE IF NOT EXISTS `file_uploads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT NOT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `upload_type` VARCHAR(100) DEFAULT NULL,
    `reference_type` VARCHAR(100) DEFAULT NULL,
    `reference_id` INT DEFAULT NULL,
    `is_public` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`upload_type`),
    INDEX `idx_reference` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team stats
CREATE TABLE IF NOT EXISTS `team_stats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `season` VARCHAR(50) DEFAULT NULL,
    `games_played` INT DEFAULT 0,
    `wins` INT DEFAULT 0,
    `losses` INT DEFAULT 0,
    `ties` INT DEFAULT 0,
    `goals_for` INT DEFAULT 0,
    `goals_against` INT DEFAULT 0,
    `points` INT DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_team_season` (`team_id`, `season`),
    INDEX `idx_team` (`team_id`),
    INDEX `idx_season` (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- END OF COMPLETE DATABASE SCHEMA

-- Game schedules
CREATE TABLE IF NOT EXISTS `game_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `opponent_team` VARCHAR(255) NOT NULL,
    `game_date` DATETIME NOT NULL,
    `location_id` INT DEFAULT NULL,
    `game_type` ENUM('regular', 'playoff', 'tournament', 'exhibition', 'practice') DEFAULT 'regular',
    `home_score` INT DEFAULT NULL,
    `away_score` INT DEFAULT NULL,
    `is_home_game` TINYINT(1) DEFAULT 1,
    `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
    `notes` TEXT DEFAULT NULL,
    `season_id` INT DEFAULT NULL,
    `ical_uid` VARCHAR(500) DEFAULT NULL COMMENT 'UID from iCal event for sync/update tracking',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE SET NULL,
    INDEX `idx_team` (`team_id`),
    INDEX `idx_date` (`game_date`),
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`game_type`),
    INDEX `idx_season` (`season_id`),
    UNIQUE INDEX `idx_ical_uid_team` (`ical_uid`, `team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create view for backwards compatibility (programs alias)
CREATE OR REPLACE VIEW `programs` AS SELECT * FROM `training_programs`;

-- Total unique tables: 120+
-- Total lines: 2500+
-- =========================================================

-- =========================================================
-- SCHEMA UPDATES - Added Jan 22 2026
-- Additional athlete profile fields for player information
-- =========================================================

-- Add missing athlete profile fields to athlete_stats table
CREATE TABLE IF NOT EXISTS `user_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `preference_key` VARCHAR(100) NOT NULL,
    `preference_value` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_preference` (`user_id`, `preference_key`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_key` (`preference_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Evaluations - Link evaluations to sessions
CREATE TABLE IF NOT EXISTS `session_evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `name` VARCHAR(255) DEFAULT NULL COMMENT 'Optional custom name for this evaluation session',
    `description` TEXT DEFAULT NULL,
    `status` ENUM('draft', 'active', 'completed') DEFAULT 'draft',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `template_id` INT DEFAULT NULL COMMENT 'Reference to evaluation template used',
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_session` (`session_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Evaluation Athletes - Athletes assigned to a session evaluation (can be non-users)
CREATE TABLE IF NOT EXISTS `session_evaluation_athletes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_evaluation_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL COMMENT 'Link to users table if athlete is a registered user',
    `first_name` VARCHAR(512) NOT NULL,
    `last_name` VARCHAR(512) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL COMMENT 'Email for creating user account if provided',
    `date_of_birth` VARCHAR(512) DEFAULT NULL,
    `external_id` VARCHAR(100) DEFAULT NULL COMMENT 'External identifier for imported athletes',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_evaluation_id`) REFERENCES `session_evaluations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_session_eval` (`session_evaluation_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Evaluation Scores - Store individual skill scores for each athlete in a session evaluation
CREATE TABLE IF NOT EXISTS `session_evaluation_scores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_evaluation_id` INT NOT NULL,
    `athlete_id` INT NOT NULL COMMENT 'References session_evaluation_athletes.id',
    `skill_id` INT NOT NULL,
    `rating` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `evaluator_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_evaluation_id`) REFERENCES `session_evaluations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `session_evaluation_athletes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_athlete_skill` (`session_evaluation_id`, `athlete_id`, `skill_id`),
    INDEX `idx_session_eval` (`session_evaluation_id`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_skill` (`skill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- SCHEMA UPDATES - Added Jan 27 2026
-- Payroll and Onboarding Features for HR Module
-- =========================================================

-- Employee Payroll Information
CREATE TABLE IF NOT EXISTS `employee_payroll` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `employee_type` ENUM('hourly', 'salary', 'contract') DEFAULT 'hourly',
    `pay_rate` DECIMAL(12,2) NOT NULL COMMENT 'Hourly rate or annual salary',
    `pay_frequency` ENUM('weekly', 'bi-weekly', 'semi-monthly', 'monthly') DEFAULT 'bi-weekly',
    `stripe_account_id` VARCHAR(255) DEFAULT NULL COMMENT 'Stripe Connect account ID for payouts',
    `sin_last_four` VARCHAR(4) DEFAULT NULL COMMENT 'Last 4 digits of SIN for verification',
    `start_date` DATE NOT NULL,
    `end_date` DATE DEFAULT NULL,
    `cpp_exempt` TINYINT(1) DEFAULT 0 COMMENT 'Canada Pension Plan exemption',
    `ei_exempt` TINYINT(1) DEFAULT 0 COMMENT 'Employment Insurance exemption',
    `pension_enrolled` TINYINT(1) DEFAULT 0 COMMENT 'Enrolled in company pension',
    `pension_contribution_rate` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Employee pension contribution %',
    `employer_pension_match` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Employer pension match %',
    `tax_province` VARCHAR(2) DEFAULT 'BC' COMMENT 'Province for tax calculations',
    `federal_td1_claim` DECIMAL(10,2) DEFAULT NULL COMMENT 'Federal personal tax credit claim',
    `provincial_td1_claim` DECIMAL(10,2) DEFAULT NULL COMMENT 'Provincial personal tax credit claim',
    `additional_tax_deduction` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Additional tax to withhold per pay',
    `status` ENUM('active', 'on_leave', 'terminated') DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_payroll` (`user_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`employee_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Banking Information
CREATE TABLE IF NOT EXISTS `employee_banking` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `institution_number` VARCHAR(3) NOT NULL COMMENT 'Canadian bank institution number',
    `transit_number` VARCHAR(5) NOT NULL COMMENT 'Canadian bank transit number',
    `account_number_encrypted` BLOB NOT NULL COMMENT 'Encrypted bank account number',
    `account_type` ENUM('checking', 'savings') DEFAULT 'checking',
    `is_primary` TINYINT(1) DEFAULT 1,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Home Address (for T4 and payroll purposes)
CREATE TABLE IF NOT EXISTS `employee_addresses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `address_type` ENUM('home', 'mailing') DEFAULT 'home',
    `street_address` VARCHAR(255) NOT NULL,
    `unit_number` VARCHAR(50) DEFAULT NULL,
    `city` VARCHAR(100) NOT NULL,
    `province` VARCHAR(2) NOT NULL COMMENT 'Province code (BC, ON, etc.)',
    `postal_code` VARCHAR(10) NOT NULL,
    `country` VARCHAR(2) DEFAULT 'CA',
    `is_primary` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`address_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll History/Pay Stubs
CREATE TABLE IF NOT EXISTS `payroll_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `pay_period_start` DATE NOT NULL,
    `pay_period_end` DATE NOT NULL,
    `pay_date` DATE NOT NULL,
    `hours_worked` DECIMAL(8,2) DEFAULT NULL COMMENT 'For hourly employees',
    `regular_hours` DECIMAL(8,2) DEFAULT 0.00,
    `overtime_hours` DECIMAL(8,2) DEFAULT 0.00,
    `gross_pay` DECIMAL(12,2) NOT NULL,
    `cpp_deduction` DECIMAL(10,2) DEFAULT 0.00,
    `ei_deduction` DECIMAL(10,2) DEFAULT 0.00,
    `federal_tax` DECIMAL(10,2) DEFAULT 0.00,
    `provincial_tax` DECIMAL(10,2) DEFAULT 0.00,
    `pension_deduction` DECIMAL(10,2) DEFAULT 0.00,
    `other_deductions` DECIMAL(10,2) DEFAULT 0.00,
    `other_deductions_details` JSON DEFAULT NULL,
    `total_deductions` DECIMAL(12,2) NOT NULL,
    `net_pay` DECIMAL(12,2) NOT NULL,
    `ytd_gross` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Year-to-date gross',
    `ytd_cpp` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Year-to-date CPP',
    `ytd_ei` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Year-to-date EI',
    `ytd_tax` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Year-to-date total tax',
    `stripe_transfer_id` VARCHAR(255) DEFAULT NULL COMMENT 'Stripe transfer ID for payment',
    `payment_status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `payment_method` ENUM('direct_deposit', 'cheque', 'manual') DEFAULT 'direct_deposit',
    `processed_by` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_pay_date` (`pay_date`),
    INDEX `idx_pay_period` (`pay_period_start`, `pay_period_end`),
    INDEX `idx_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRA Tax Rates (updated annually based on CRA standards)
CREATE TABLE IF NOT EXISTS `cra_tax_rates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tax_year` YEAR NOT NULL,
    `rate_type` ENUM('cpp', 'ei', 'federal_basic', 'federal_bracket', 'provincial_basic', 'provincial_bracket') NOT NULL,
    `province` VARCHAR(2) DEFAULT NULL COMMENT 'NULL for federal rates, province code for provincial',
    `bracket_min` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Income bracket minimum',
    `bracket_max` DECIMAL(12,2) DEFAULT NULL COMMENT 'Income bracket maximum (NULL for unlimited)',
    `rate_percentage` DECIMAL(6,4) NOT NULL COMMENT 'Tax rate as percentage',
    `max_pensionable_earnings` DECIMAL(12,2) DEFAULT NULL COMMENT 'For CPP max',
    `max_insurable_earnings` DECIMAL(12,2) DEFAULT NULL COMMENT 'For EI max',
    `basic_exemption` DECIMAL(12,2) DEFAULT NULL COMMENT 'Basic personal exemption amount',
    `effective_date` DATE NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_year` (`tax_year`),
    INDEX `idx_type` (`rate_type`),
    INDEX `idx_province` (`province`),
    UNIQUE KEY `unique_rate` (`tax_year`, `rate_type`, `province`, `bracket_min`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- T4 Slip Records
CREATE TABLE IF NOT EXISTS `t4_slips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `tax_year` YEAR NOT NULL,
    `employer_name` VARCHAR(255) NOT NULL DEFAULT 'Arctic Wolves Hockey',
    `employer_bn` VARCHAR(15) DEFAULT NULL COMMENT 'Business Number',
    `employment_income` DECIMAL(12,2) NOT NULL COMMENT 'Box 14',
    `cpp_contributions` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Box 16',
    `cpp_pensionable_earnings` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Box 26',
    `ei_premiums` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Box 18',
    `ei_insurable_earnings` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Box 24',
    `rpp_contributions` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Box 20 - Registered Pension',
    `income_tax_deducted` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Box 22',
    `union_dues` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Box 44',
    `charitable_donations` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Box 46',
    `other_info` JSON DEFAULT NULL COMMENT 'Other boxes and info',
    `employee_sin_encrypted` BLOB DEFAULT NULL COMMENT 'Encrypted SIN',
    `employee_address` TEXT DEFAULT NULL COMMENT 'Address at year end',
    `province_of_employment` VARCHAR(2) DEFAULT 'BC',
    `generated_at` TIMESTAMP NULL DEFAULT NULL,
    `generated_by` INT DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path (RustFS URL)',
    `status` ENUM('draft', 'generated', 'filed', 'amended') DEFAULT 'draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_user_year` (`user_id`, `tax_year`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_year` (`tax_year`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Onboarding Records
CREATE TABLE IF NOT EXISTS `employee_onboarding` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL COMMENT 'NULL until user account is created',
    `first_name` VARCHAR(512) NOT NULL,
    `last_name` VARCHAR(512) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(512) DEFAULT NULL,
    `role` ENUM('coach', 'health_coach', 'admin', 'team_coach', 'front_desk_staff', 'hr', 'accounting') NOT NULL,
    `job_title` VARCHAR(255) DEFAULT NULL COMMENT 'Job title for business cards and signatures',
    `create_extension` TINYINT(1) DEFAULT 0 COMMENT 'Whether to create a phone extension for this employee',
    `start_date` DATE NOT NULL,
    `employee_type` ENUM('full_time', 'part_time', 'contract', 'seasonal') DEFAULT 'part_time',
    `employment_status` VARCHAR(50) DEFAULT 'new',
    `onboarding_status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    `personal_info_collected` TINYINT(1) DEFAULT 0,
    `banking_info_collected` TINYINT(1) DEFAULT 0,
    `tax_forms_completed` TINYINT(1) DEFAULT 0,
    `payroll_setup_completed` TINYINT(1) DEFAULT 0,
    `equipment_assigned` TINYINT(1) DEFAULT 0,
    `perks_assigned` TINYINT(1) DEFAULT 0,
    `training_completed` TINYINT(1) DEFAULT 0,
    `emergency_contact_name` VARCHAR(512) DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(512) DEFAULT NULL,
    `emergency_contact_relationship` VARCHAR(50) DEFAULT NULL,
    `sin_collected` TINYINT(1) DEFAULT 0,
    `sin_last_four` VARCHAR(4) DEFAULT NULL,
    `date_of_birth` VARCHAR(512) DEFAULT NULL,
    `street_address` VARCHAR(512) DEFAULT NULL,
    `unit_number` VARCHAR(50) DEFAULT NULL,
    `city` VARCHAR(512) DEFAULT NULL,
    `province` VARCHAR(2) DEFAULT NULL,
    `postal_code` VARCHAR(10) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `nextcloud_folder` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for onboarding docs (RustFS URL)',
    `contract_sent` TINYINT(1) DEFAULT 0 COMMENT 'Whether employment contract was sent for signature',
    `contract_id` INT DEFAULT NULL COMMENT 'Link to employee_contracts record if contract was created',
    `processed_by` INT DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`onboarding_status`),
    INDEX `idx_start_date` (`start_date`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Onboarding Equipment Assignments
CREATE TABLE IF NOT EXISTS `onboarding_equipment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `onboarding_id` INT NOT NULL,
    `equipment_type` ENUM('camera', 'tablet', 'laptop', 'phone', 'uniform', 'keys', 'access_card', 'other') NOT NULL,
    `equipment_name` VARCHAR(255) NOT NULL,
    `serial_number` VARCHAR(100) DEFAULT NULL,
    `asset_tag` VARCHAR(50) DEFAULT NULL,
    `condition_on_issue` ENUM('new', 'good', 'fair', 'refurbished') DEFAULT 'new',
    `value` DECIMAL(10,2) DEFAULT NULL,
    `issued_date` DATE DEFAULT NULL,
    `return_expected_date` DATE DEFAULT NULL,
    `returned_date` DATE DEFAULT NULL,
    `condition_on_return` ENUM('good', 'fair', 'damaged', 'lost') DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `assigned_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`onboarding_id`) REFERENCES `employee_onboarding`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_onboarding` (`onboarding_id`),
    INDEX `idx_type` (`equipment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Onboarding Perks
CREATE TABLE IF NOT EXISTS `onboarding_perks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `onboarding_id` INT NOT NULL,
    `perk_type` ENUM('equipment', 'clothing', 'gear', 'membership', 'discount', 'other') NOT NULL,
    `perk_name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `quantity` INT DEFAULT 1,
    `value` DECIMAL(10,2) DEFAULT NULL,
    `is_recurring` TINYINT(1) DEFAULT 0 COMMENT 'Annual perk vs one-time',
    `issued_date` DATE DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `assigned_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`onboarding_id`) REFERENCES `employee_onboarding`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_onboarding` (`onboarding_id`),
    INDEX `idx_type` (`perk_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Onboarding Documents
CREATE TABLE IF NOT EXISTS `onboarding_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `onboarding_id` INT NOT NULL,
    `document_type` ENUM('id', 'sin_card', 'td1_federal', 'td1_provincial', 'banking', 'contract', 'policy_acknowledgment', 'photo', 'certification', 'other') NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `status` ENUM('pending', 'received', 'verified', 'rejected') DEFAULT 'pending',
    `verified_by` INT DEFAULT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `uploaded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`onboarding_id`) REFERENCES `employee_onboarding`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_onboarding` (`onboarding_id`),
    INDEX `idx_type` (`document_type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contract Templates for E-Signature
-- Note: opensign_template_id references external OpenSign system, not a local database table
CREATE TABLE IF NOT EXISTS `contract_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `opensign_template_id` INT DEFAULT NULL COMMENT 'External: OpenSign template ID for API integration (not a local FK)',
    `template_file_path` VARCHAR(500) DEFAULT NULL COMMENT 'Optional: Local PDF backup file path for reference',
    `template_type` ENUM('employment', 'contractor', 'nda', 'other') DEFAULT 'employment',
    `variables` JSON DEFAULT NULL COMMENT 'List of template variables for form filling',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_type` (`template_type`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_opensign` (`opensign_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Contracts (for e-signature workflow)
-- Note: opensign_template_id and opensign_submission_id reference external OpenSign system
CREATE TABLE IF NOT EXISTS `employee_contracts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `onboarding_id` INT DEFAULT NULL COMMENT 'Link to onboarding record if applicable',
    `user_id` INT DEFAULT NULL COMMENT 'Link to user if already created',
    `template_id` INT DEFAULT NULL COMMENT 'Local template reference',
    `opensign_template_id` INT DEFAULT NULL COMMENT 'External: OpenSign template ID used (not a local FK)',
    `opensign_submission_id` INT DEFAULT NULL COMMENT 'External: OpenSign submission ID for tracking (not a local FK)',
    `docuseal_submission_id` INT DEFAULT NULL COMMENT 'External: DocuSeal submission ID for tracking (not a local FK)',
    `employee_name` VARCHAR(255) NOT NULL,
    `employee_email` VARCHAR(255) NOT NULL,
    `contract_title` VARCHAR(255) DEFAULT 'Employment Contract',
    `contract_data` JSON DEFAULT NULL COMMENT 'Data used to fill the contract template',
    `status` ENUM('draft', 'pending_signature', 'signed', 'expired', 'cancelled') DEFAULT 'draft',
    `signing_url` VARCHAR(500) DEFAULT NULL COMMENT 'OpenSign signing URL',
    `signing_token` VARCHAR(64) DEFAULT NULL COMMENT 'Legacy: Unique token for signing URL',
    `signing_token_expires` DATETIME DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for signed contract (RustFS URL)',
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `signed_at` TIMESTAMP NULL DEFAULT NULL,
    `signed_date` DATE DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`onboarding_id`) REFERENCES `employee_onboarding`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`template_id`) REFERENCES `contract_templates`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_onboarding` (`onboarding_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_opensign_submission` (`opensign_submission_id`),
    INDEX `idx_docuseal_submission` (`docuseal_submission_id`),
    INDEX `idx_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default contract template
INSERT INTO `contract_templates` (`name`, `description`, `template_type`, `variables`, `is_active`) VALUES
('Standard Employment Contract', 'Standard employee contract for full-time and part-time staff', 'employment', '["employee_name", "employee_address", "start_date", "position", "salary", "pay_frequency"]', 1),
('Independent Contractor Agreement', 'Agreement for independent contractors', 'contractor', '["contractor_name", "contractor_address", "start_date", "services", "rate", "payment_terms"]', 1),
('Non-Disclosure Agreement', 'Standard NDA for employees and contractors', 'nda', '["party_name", "party_address", "effective_date"]', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Insert default CRA tax rates for 2026 (Canada)
INSERT INTO `cra_tax_rates` (`tax_year`, `rate_type`, `province`, `bracket_min`, `bracket_max`, `rate_percentage`, `max_pensionable_earnings`, `max_insurable_earnings`, `basic_exemption`, `effective_date`, `notes`) VALUES
-- CPP 2026 rates
(2026, 'cpp', NULL, 0, NULL, 5.9500, 71300.00, NULL, 3500.00, '2026-01-01', 'CPP employee contribution rate for 2026'),
-- EI 2026 rates
(2026, 'ei', NULL, 0, NULL, 1.6600, NULL, 64200.00, NULL, '2026-01-01', 'EI employee premium rate for 2026'),
-- Federal Basic Personal Amount 2026
(2026, 'federal_basic', NULL, 0, NULL, 0.0000, NULL, NULL, 16129.00, '2026-01-01', 'Federal Basic Personal Amount for 2026'),
-- Federal tax brackets 2026
(2026, 'federal_bracket', NULL, 0, 55867.00, 15.0000, NULL, NULL, NULL, '2026-01-01', 'Federal bracket 1'),
(2026, 'federal_bracket', NULL, 55867.01, 111733.00, 20.5000, NULL, NULL, NULL, '2026-01-01', 'Federal bracket 2'),
(2026, 'federal_bracket', NULL, 111733.01, 173205.00, 26.0000, NULL, NULL, NULL, '2026-01-01', 'Federal bracket 3'),
(2026, 'federal_bracket', NULL, 173205.01, 246752.00, 29.0000, NULL, NULL, NULL, '2026-01-01', 'Federal bracket 4'),
(2026, 'federal_bracket', NULL, 246752.01, NULL, 33.0000, NULL, NULL, NULL, '2026-01-01', 'Federal bracket 5'),
-- BC Provincial Basic Personal Amount 2026
(2026, 'provincial_basic', 'BC', 0, NULL, 0.0000, NULL, NULL, 12580.00, '2026-01-01', 'BC Basic Personal Amount for 2026'),
-- BC Provincial tax brackets 2026
(2026, 'provincial_bracket', 'BC', 0, 47937.00, 5.0600, NULL, NULL, NULL, '2026-01-01', 'BC bracket 1'),
(2026, 'provincial_bracket', 'BC', 47937.01, 95875.00, 7.7000, NULL, NULL, NULL, '2026-01-01', 'BC bracket 2'),
(2026, 'provincial_bracket', 'BC', 95875.01, 110076.00, 10.5000, NULL, NULL, NULL, '2026-01-01', 'BC bracket 3'),
(2026, 'provincial_bracket', 'BC', 110076.01, 133664.00, 12.2900, NULL, NULL, NULL, '2026-01-01', 'BC bracket 4'),
(2026, 'provincial_bracket', 'BC', 133664.01, 181232.00, 14.7000, NULL, NULL, NULL, '2026-01-01', 'BC bracket 5'),
(2026, 'provincial_bracket', 'BC', 181232.01, 252752.00, 16.8000, NULL, NULL, NULL, '2026-01-01', 'BC bracket 6'),
(2026, 'provincial_bracket', 'BC', 252752.01, NULL, 20.5000, NULL, NULL, NULL, '2026-01-01', 'BC bracket 7'),
-- Ontario Provincial Basic Personal Amount 2026
(2026, 'provincial_basic', 'ON', 0, NULL, 0.0000, NULL, NULL, 12399.00, '2026-01-01', 'ON Basic Personal Amount for 2026'),
-- ON Provincial tax brackets 2026
(2026, 'provincial_bracket', 'ON', 0, 51446.00, 5.0500, NULL, NULL, NULL, '2026-01-01', 'ON bracket 1'),
(2026, 'provincial_bracket', 'ON', 51446.01, 102894.00, 9.1500, NULL, NULL, NULL, '2026-01-01', 'ON bracket 2'),
(2026, 'provincial_bracket', 'ON', 102894.01, 150000.00, 11.1600, NULL, NULL, NULL, '2026-01-01', 'ON bracket 3'),
(2026, 'provincial_bracket', 'ON', 150000.01, 220000.00, 12.1600, NULL, NULL, NULL, '2026-01-01', 'ON bracket 4'),
(2026, 'provincial_bracket', 'ON', 220000.01, NULL, 13.1600, NULL, NULL, NULL, '2026-01-01', 'ON bracket 5'),
-- Alberta Provincial tax (flat rate)
(2026, 'provincial_basic', 'AB', 0, NULL, 0.0000, NULL, NULL, 21003.00, '2026-01-01', 'AB Basic Personal Amount for 2026'),
(2026, 'provincial_bracket', 'AB', 0, 148269.00, 10.0000, NULL, NULL, NULL, '2026-01-01', 'AB bracket 1'),
(2026, 'provincial_bracket', 'AB', 148269.01, 177922.00, 12.0000, NULL, NULL, NULL, '2026-01-01', 'AB bracket 2'),
(2026, 'provincial_bracket', 'AB', 177922.01, 237230.00, 13.0000, NULL, NULL, NULL, '2026-01-01', 'AB bracket 3'),
(2026, 'provincial_bracket', 'AB', 237230.01, 355845.00, 14.0000, NULL, NULL, NULL, '2026-01-01', 'AB bracket 4'),
(2026, 'provincial_bracket', 'AB', 355845.01, NULL, 15.0000, NULL, NULL, NULL, '2026-01-01', 'AB bracket 5')
ON DUPLICATE KEY UPDATE rate_percentage = VALUES(rate_percentage), max_pensionable_earnings = VALUES(max_pensionable_earnings), max_insurable_earnings = VALUES(max_insurable_earnings), basic_exemption = VALUES(basic_exemption);

-- =========================================================
-- SCHEMA UPDATES - Jan 27 2026
-- Enhanced Products Page: Sessions, Packages, and Discounts
-- =========================================================

-- Enhance session_types table with additional fields
CREATE TABLE IF NOT EXISTS `training_session_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `session_type_id` INT DEFAULT NULL,
    `duration_minutes` INT DEFAULT 60,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `max_participants` INT DEFAULT NULL,
    `coach_id` INT DEFAULT NULL,
    `location_id` INT DEFAULT NULL,
    `practice_plan_id` INT DEFAULT NULL,
    `session_type` ENUM('on_ice', 'off_ice', 'nutrition', 'meeting', 'other') DEFAULT 'on_ice',
    `is_active` TINYINT(1) DEFAULT 1,
    `show_on_landing` TINYINT(1) DEFAULT 0,
    `is_dev_program` TINYINT(1) DEFAULT 0 COMMENT '1 if this is a long-term development program product',
    `duration_weeks` INT DEFAULT NULL COMMENT 'Duration in weeks for dev programs (e.g. 4 for a 4-week program)',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_type_id`) REFERENCES `session_types`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`practice_plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_landing` (`show_on_landing`),
    INDEX `idx_type` (`session_type`),
    INDEX `idx_dev_program` (`is_dev_program`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training Session Template Skill Types - Link templates to skill categories
CREATE TABLE IF NOT EXISTS `template_skill_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NOT NULL,
    `skill_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`template_id`) REFERENCES `training_session_templates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_template_skill` (`template_id`, `skill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Skill Types - Link actual sessions to skill categories
CREATE TABLE IF NOT EXISTS `session_skill_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `skill_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_session_skill` (`session_id`, `skill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training Session Dates - Multiple dates per session/template
CREATE TABLE IF NOT EXISTS `training_session_dates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT DEFAULT NULL COMMENT 'Reference to template if from template',
    `session_id` INT DEFAULT NULL COMMENT 'Reference to actual session',
    `session_date` DATETIME NOT NULL,
    `team_id` INT DEFAULT NULL COMMENT 'Team assigned to this specific date',
    `max_participants` INT DEFAULT NULL COMMENT 'Override max participants for this date',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`template_id`) REFERENCES `training_session_templates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    INDEX `idx_template` (`template_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_date` (`session_date`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Date Athletes - Athletes assigned to specific session dates
CREATE TABLE IF NOT EXISTS `session_date_athletes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_date_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_date_id`) REFERENCES `training_session_dates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_date_athlete` (`session_date_id`, `athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhance packages table with store credit and landing page options
CREATE TABLE IF NOT EXISTS `package_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `session_id` INT DEFAULT NULL COMMENT 'Specific session',
    `template_id` INT DEFAULT NULL COMMENT 'Session template',
    `quantity` INT DEFAULT 1 COMMENT 'Number of sessions of this type',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`template_id`) REFERENCES `training_session_templates`(`id`) ON DELETE SET NULL,
    INDEX `idx_package` (`package_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhance discount_codes table with store credit and dynamic code options
ALTER TABLE `discount_codes`
MODIFY COLUMN `discount_type` ENUM('percentage', 'fixed', 'store_credit') DEFAULT 'percentage';

-- User Store Credits - Track user store credit balances
CREATE TABLE IF NOT EXISTS `user_store_credits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `transaction_type` ENUM('earned', 'used', 'expired', 'refund', 'adjustment') NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., package_purchase, discount_code',
    `reference_id` INT DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `expires_at` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`transaction_type`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Registrations from Landing Page - Track registration intent before login
CREATE TABLE IF NOT EXISTS `session_registration_intents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT DEFAULT NULL,
    `template_id` INT DEFAULT NULL,
    `session_date_id` INT DEFAULT NULL,
    `package_id` INT DEFAULT NULL,
    `intent_token` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` INT DEFAULT NULL COMMENT 'Set after login/registration',
    `status` ENUM('pending', 'completed', 'expired') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`template_id`) REFERENCES `training_session_templates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_date_id`) REFERENCES `training_session_dates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`intent_token`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_line_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `expense_id` INT NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(10,2) DEFAULT 1.00,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`expense_id`) REFERENCES `expenses`(`id`) ON DELETE CASCADE,
    INDEX `idx_expense` (`expense_id`),
    INDEX `idx_item` (`item_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ACCOUNTS PAYABLE - PAYEE MANAGEMENT
-- =====================================================

-- Payees table for managing vendors/suppliers
CREATE TABLE IF NOT EXISTS `payees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address_line1` VARCHAR(255) DEFAULT NULL,
    `address_line2` VARCHAR(255) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state_province` VARCHAR(100) DEFAULT NULL,
    `postal_code` VARCHAR(20) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT 'Canada',
    `default_payment_method` ENUM('bank_transfer', 'cheque', 'stripe', 'etransfer', 'cash', 'credit_card') DEFAULT 'bank_transfer',
    `bank_name` VARCHAR(255) DEFAULT NULL,
    `bank_account_number` VARCHAR(255) DEFAULT NULL COMMENT 'Encrypted',
    `bank_routing_number` VARCHAR(50) DEFAULT NULL COMMENT 'Encrypted',
    `stripe_account_id` VARCHAR(255) DEFAULT NULL,
    `etransfer_email` VARCHAR(255) DEFAULT NULL,
    `tax_id` VARCHAR(50) DEFAULT NULL COMMENT 'Business Number / GST/HST',
    `default_currency` VARCHAR(3) DEFAULT 'CAD',
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_name` (`name`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_payment_method` (`default_payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link expenses to payees
-- First add the column (idempotent with IF NOT EXISTS)
CREATE TABLE IF NOT EXISTS `payment_batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_name` VARCHAR(255) NOT NULL,
    `batch_date` DATE NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) DEFAULT 'CAD',
    `status` ENUM('draft', 'pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'draft',
    `payment_method` ENUM('bank_transfer', 'cheque', 'stripe', 'etransfer', 'mixed') DEFAULT 'mixed',
    `notes` TEXT DEFAULT NULL,
    `processed_at` TIMESTAMP NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_date` (`batch_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual payments within a batch
CREATE TABLE IF NOT EXISTS `batch_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_id` INT NOT NULL,
    `payee_id` INT NOT NULL,
    `expense_id` INT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'CAD',
    `payment_method` ENUM('bank_transfer', 'cheque', 'stripe', 'etransfer', 'cash', 'credit_card') NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    `stripe_transfer_id` VARCHAR(255) DEFAULT NULL,
    `reference_number` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `processed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`batch_id`) REFERENCES `payment_batches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payee_id`) REFERENCES `payees`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`expense_id`) REFERENCES `expenses`(`id`) ON DELETE SET NULL,
    INDEX `idx_batch` (`batch_id`),
    INDEX `idx_payee` (`payee_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STRIPE VIRTUAL CARDS
-- =====================================================

-- Stripe Issuing cardholders
CREATE TABLE IF NOT EXISTS `stripe_cardholders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `stripe_cardholder_id` VARCHAR(255) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `type` ENUM('individual', 'company') DEFAULT 'individual',
    `status` ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_stripe_id` (`stripe_cardholder_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe virtual cards
CREATE TABLE IF NOT EXISTS `stripe_virtual_cards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cardholder_id` INT NOT NULL,
    `stripe_card_id` VARCHAR(255) NOT NULL UNIQUE,
    `card_name` VARCHAR(255) DEFAULT NULL,
    `last4` VARCHAR(4) DEFAULT NULL,
    `brand` VARCHAR(50) DEFAULT NULL,
    `exp_month` INT DEFAULT NULL,
    `exp_year` INT DEFAULT NULL,
    `currency` VARCHAR(3) DEFAULT 'CAD',
    `status` ENUM('active', 'inactive', 'canceled') DEFAULT 'inactive',
    `spending_limit` DECIMAL(10,2) DEFAULT NULL,
    `spending_limit_interval` ENUM('per_authorization', 'daily', 'weekly', 'monthly', 'yearly', 'all_time') DEFAULT 'monthly',
    `purpose` VARCHAR(255) DEFAULT NULL COMMENT 'What the card is used for',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`cardholder_id`) REFERENCES `stripe_cardholders`(`id`) ON DELETE CASCADE,
    INDEX `idx_stripe_id` (`stripe_card_id`),
    INDEX `idx_cardholder` (`cardholder_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EXPENSE EXPORTS
-- =====================================================

-- Track expense exports
CREATE TABLE IF NOT EXISTS `expense_exports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `export_name` VARCHAR(255) NOT NULL,
    `export_type` ENUM('week', 'month', 'quarter', 'year', 'custom') NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `year` INT DEFAULT NULL,
    `total_expenses` DECIMAL(12,2) DEFAULT 0.00,
    `expense_count` INT DEFAULT 0,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `includes_receipts` TINYINT(1) DEFAULT 1,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_type` (`export_type`),
    INDEX `idx_period` (`period_start`, `period_end`),
    INDEX `idx_year` (`year`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System activation tracking (for year selection in exports)
CREATE TABLE IF NOT EXISTS `system_activation` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `activation_year` INT NOT NULL DEFAULT 2026,
    `activation_date` DATE NOT NULL,
    `activated_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`activated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system activation for 2026
INSERT IGNORE INTO `system_activation` (`id`, `activation_year`, `activation_date`) 
VALUES (1, 2026, '2026-01-01');

-- Add display_order to expense_categories if not exists
CREATE TABLE IF NOT EXISTS `merchandise_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `parent_id` INT DEFAULT NULL,
    `slug` VARCHAR(255) DEFAULT NULL,
    `nextcloud_image_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for category image (RustFS URL)',
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merchandise Products
CREATE TABLE IF NOT EXISTS `merchandise_products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sku` VARCHAR(100) DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cost_price` DECIMAL(10,2) DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `track_inventory` TINYINT(1) DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `nextcloud_image_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for product image (RustFS URL)',
    FOREIGN KEY (`category_id`) REFERENCES `merchandise_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merchandise Product Sizes (inventory tracking by size)
CREATE TABLE IF NOT EXISTS `merchandise_product_sizes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `size` VARCHAR(50) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `stock_location` ENUM('in_store', 'warehouse') NOT NULL DEFAULT 'in_store',
    `sku_suffix` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `merchandise_products`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_product_size` (`product_id`, `size`, `stock_location`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_size` (`size`),
    INDEX `idx_stock_location` (`stock_location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merchandise Product Images (multiple images per product)
CREATE TABLE IF NOT EXISTS `merchandise_product_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `merchandise_products`(`id`) ON DELETE CASCADE,
    INDEX `idx_product` (`product_id`),
    INDEX `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merchandise Stock Movements (tracks shipments, audits, and adjustments)
CREATE TABLE IF NOT EXISTS `merchandise_stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `size_id` INT DEFAULT NULL,
    `movement_type` ENUM('shipment', 'audit_adjustment', 'manual_adjustment', 'sale', 'return') NOT NULL,
    `quantity_before` INT NOT NULL DEFAULT 0,
    `quantity_change` INT NOT NULL DEFAULT 0,
    `quantity_after` INT NOT NULL DEFAULT 0,
    `reference` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `merchandise_products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`size_id`) REFERENCES `merchandise_product_sizes`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_product` (`product_id`),
    INDEX `idx_size` (`size_id`),
    INDEX `idx_movement_type` (`movement_type`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merchandise Stock Audits (tracks audit sessions comparing system vs actual counts)
CREATE TABLE IF NOT EXISTS `merchandise_stock_audits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `audit_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('in_progress', 'completed') DEFAULT 'completed',
    `notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `merchandise_products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_product` (`product_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merchandise Stock Audit Items (individual size counts within an audit)
CREATE TABLE IF NOT EXISTS `merchandise_stock_audit_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `audit_id` INT NOT NULL,
    `size_id` INT NOT NULL,
    `system_quantity` INT NOT NULL DEFAULT 0,
    `actual_quantity` INT NOT NULL DEFAULT 0,
    `discrepancy` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`audit_id`) REFERENCES `merchandise_stock_audits`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`size_id`) REFERENCES `merchandise_product_sizes`(`id`) ON DELETE CASCADE,
    INDEX `idx_audit` (`audit_id`),
    INDEX `idx_size` (`size_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ONLINE SHOP AND POS SYSTEM TABLES
-- =====================================================

-- Alter merchandise_categories to add parent_id for subcategories

-- Shop Orders (for guest and logged-in user purchases)
CREATE TABLE IF NOT EXISTS `shop_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT DEFAULT NULL,
    `customer_email` VARCHAR(255) NOT NULL,
    `customer_first_name` VARCHAR(512) NOT NULL,
    `customer_last_name` VARCHAR(512) NOT NULL,
    `customer_phone` VARCHAR(512) DEFAULT NULL,
    `billing_address_line1` VARCHAR(512) DEFAULT NULL,
    `billing_address_line2` VARCHAR(512) DEFAULT NULL,
    `billing_city` VARCHAR(512) DEFAULT NULL,
    `billing_state` VARCHAR(100) DEFAULT NULL,
    `billing_postal_code` VARCHAR(20) DEFAULT NULL,
    `billing_country` VARCHAR(2) DEFAULT 'CA',
    `shipping_address_line1` VARCHAR(512) DEFAULT NULL,
    `shipping_address_line2` VARCHAR(512) DEFAULT NULL,
    `shipping_city` VARCHAR(512) DEFAULT NULL,
    `shipping_state` VARCHAR(100) DEFAULT NULL,
    `shipping_postal_code` VARCHAR(20) DEFAULT NULL,
    `shipping_country` VARCHAR(2) DEFAULT 'CA',
    `shipping_same_as_billing` TINYINT(1) DEFAULT 1,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `shipping_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    `payment_status` ENUM('pending', 'paid', 'failed', 'refunded', 'partially_refunded') DEFAULT 'pending',
    `stripe_session_id` VARCHAR(255) DEFAULT NULL,
    `stripe_payment_intent` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `shipping_carrier` VARCHAR(100) DEFAULT NULL,
    `tracking_number` VARCHAR(255) DEFAULT NULL,
    `tracking_url` VARCHAR(500) DEFAULT NULL,
    `shipped_at` TIMESTAMP NULL DEFAULT NULL,
    `delivered_at` TIMESTAMP NULL DEFAULT NULL,
    `fulfillment_notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_email` (`customer_email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_stripe_session` (`stripe_session_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add shipping/fulfillment tracking fields to shop_orders

-- Shop Order Items
CREATE TABLE IF NOT EXISTS `shop_order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `product_sku` VARCHAR(100) DEFAULT NULL,
    `size` VARCHAR(50) DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `shop_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `merchandise_products`(`id`) ON DELETE RESTRICT,
    INDEX `idx_order` (`order_id`),
    INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- POS Transactions (for in-person sales via terminal)
CREATE TABLE IF NOT EXISTS `pos_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_number` VARCHAR(50) NOT NULL UNIQUE,
    `staff_id` INT NOT NULL,
    `customer_user_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `customer_email` VARCHAR(255) DEFAULT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('card', 'cash', 'mixed') DEFAULT 'card',
    `cash_amount` DECIMAL(10,2) DEFAULT NULL,
    `card_amount` DECIMAL(10,2) DEFAULT NULL,
    `change_given` DECIMAL(10,2) DEFAULT NULL,
    `status` ENUM('pending', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    `stripe_payment_intent` VARCHAR(255) DEFAULT NULL,
    `terminal_reader_id` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`customer_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_staff` (`staff_id`),
    INDEX `idx_customer` (`customer_user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_payment_method` (`payment_method`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- POS Transaction Items
CREATE TABLE IF NOT EXISTS `pos_transaction_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `product_sku` VARCHAR(100) DEFAULT NULL,
    `size` VARCHAR(50) DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `merchandise_products`(`id`) ON DELETE RESTRICT,
    INDEX `idx_transaction` (`transaction_id`),
    INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- POS Terminal Readers (for bbpos wisepos e integration)
CREATE TABLE IF NOT EXISTS `pos_terminal_readers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stripe_reader_id` VARCHAR(255) NOT NULL UNIQUE,
    `label` VARCHAR(100) NOT NULL,
    `location_name` VARCHAR(255) DEFAULT NULL,
    `device_type` VARCHAR(50) DEFAULT 'bbpos_wisepos_e',
    `serial_number` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('online', 'offline', 'busy') DEFAULT 'offline',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_seen_at` TIMESTAMP DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_stripe_reader` (`stripe_reader_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- STAFF TIME TRACKING AND SCHEDULING TABLES
-- =========================================================

-- Staff PIN codes for kiosk login
CREATE TABLE IF NOT EXISTS `staff_pins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `pin_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff shifts (time tracking entries)
CREATE TABLE IF NOT EXISTS `staff_shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `shift_date` DATE NOT NULL,
    `clock_in` DATETIME NOT NULL,
    `clock_out` DATETIME DEFAULT NULL,
    `lunch_start` DATETIME DEFAULT NULL,
    `lunch_end` DATETIME DEFAULT NULL,
    `total_hours` DECIMAL(5,2) DEFAULT NULL,
    `status` ENUM('active', 'completed', 'incomplete') DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_staff` (`staff_id`),
    INDEX `idx_date` (`shift_date`),
    INDEX `idx_status` (`status`),
    INDEX `idx_staff_date` (`staff_id`, `shift_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff schedules (planned shifts)
CREATE TABLE IF NOT EXISTS `staff_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `schedule_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `lunch_break_minutes` INT DEFAULT 30,
    `location` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_staff` (`staff_id`),
    INDEX `idx_date` (`schedule_date`),
    INDEX `idx_staff_date` (`staff_id`, `schedule_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- HR COMPLAINTS - Based on Canada's HR Best Practices
-- =========================================================

-- HR Complaints table for tracking internal and external complaints
CREATE TABLE IF NOT EXISTS `hr_complaints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique complaint tracking number',
    `complaint_type` ENUM('internal', 'external') NOT NULL COMMENT 'Internal: between employees, External: from outside party',
    `category` ENUM('harassment', 'discrimination', 'workplace_safety', 'policy_violation', 'performance', 'conduct', 'interpersonal_conflict', 'other') NOT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    `confidentiality_level` ENUM('standard', 'restricted', 'highly_confidential') DEFAULT 'standard' COMMENT 'Per Canadian privacy requirements',
    `complainant_id` INT DEFAULT NULL COMMENT 'Employee filing the complaint (NULL for anonymous or external)',
    `complainant_name` VARCHAR(512) DEFAULT NULL COMMENT 'For external complainants or anonymous tracking',
    `complainant_contact` VARCHAR(512) DEFAULT NULL COMMENT 'Contact info for external complainants',
    `respondent_id` INT DEFAULT NULL COMMENT 'Employee the complaint is about',
    `respondent_name` VARCHAR(512) DEFAULT NULL COMMENT 'For cases where respondent not in system',
    `complaint_date` DATE NOT NULL COMMENT 'Date complaint was filed',
    `incident_date` DATE DEFAULT NULL COMMENT 'Date the incident occurred',
    `incident_location` VARCHAR(255) DEFAULT NULL,
    `description` TEXT NOT NULL COMMENT 'Detailed description of the complaint',
    `witnesses` TEXT DEFAULT NULL COMMENT 'Names/details of any witnesses',
    `evidence_attached` TINYINT(1) DEFAULT 0,
    `status` ENUM('received', 'under_review', 'investigation', 'pending_resolution', 'resolved', 'dismissed', 'escalated') DEFAULT 'received',
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `assigned_to` INT DEFAULT NULL COMMENT 'HR representative handling the case',
    `resolution` TEXT DEFAULT NULL COMMENT 'How the complaint was resolved',
    `resolution_date` DATE DEFAULT NULL,
    `corrective_actions` TEXT DEFAULT NULL COMMENT 'Actions taken to prevent recurrence',
    `appeal_filed` TINYINT(1) DEFAULT 0,
    `appeal_notes` TEXT DEFAULT NULL,
    `legal_consultation` TINYINT(1) DEFAULT 0 COMMENT 'Whether legal was consulted',
    `documentation_complete` TINYINT(1) DEFAULT 0,
    `nextcloud_folder` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for complaint documents (RustFS URL)',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`complainant_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`respondent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_type` (`complaint_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_category` (`category`),
    INDEX `idx_severity` (`severity`),
    INDEX `idx_date` (`complaint_date`),
    INDEX `idx_respondent` (`respondent_id`),
    INDEX `idx_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- HR Complaint Notes (activity log for each complaint)
CREATE TABLE IF NOT EXISTS `hr_complaint_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `note_type` ENUM('general', 'investigation', 'interview', 'update', 'resolution', 'escalation') DEFAULT 'general',
    `note_content` TEXT NOT NULL,
    `is_confidential` TINYINT(1) DEFAULT 0 COMMENT 'Only visible to HR admins',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`complaint_id`) REFERENCES `hr_complaints`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_type` (`note_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- HR Complaint Documents
CREATE TABLE IF NOT EXISTS `hr_complaint_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `document_type` ENUM('evidence', 'statement', 'interview_notes', 'investigation_report', 'resolution', 'correspondence', 'other') NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `is_confidential` TINYINT(1) DEFAULT 1,
    `uploaded_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`complaint_id`) REFERENCES `hr_complaints`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Add video upload support to drills table

-- Add video upload support to personal_drills table

-- =========================================================
-- Evaluation Templates - Saved evaluation configurations
-- =========================================================

-- Evaluation Templates - Reusable evaluation configurations with a title
CREATE TABLE IF NOT EXISTS `evaluation_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation Template Categories - Which categories/skills belong to a saved evaluation
CREATE TABLE IF NOT EXISTS `evaluation_template_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`template_id`) REFERENCES `evaluation_templates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `eval_categories`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_template_category` (`template_id`, `category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add template_id to session_evaluations to link saved evaluations to sessions

-- Fix locations image_url column to support long Google Places API photo URLs
ALTER TABLE `locations` MODIFY COLUMN `image_url` TEXT DEFAULT NULL;

-- Two-Factor Authentication
CREATE TABLE IF NOT EXISTS `two_factor_auth` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `secret` VARCHAR(255) NOT NULL,
    `method` ENUM('app', 'hardware') DEFAULT 'app',
    `is_enabled` TINYINT(1) DEFAULT 0,
    `backup_codes` TEXT DEFAULT NULL,
    `verified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_2fa` (`user_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add two_factor_required flag to users (admin can force 2FA)

-- Add last_activity tracking to login_history for online status

-- Error logs table for comprehensive error tracking
CREATE TABLE IF NOT EXISTS `error_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `error_level` VARCHAR(50) NOT NULL DEFAULT 'ERROR',
    `message` TEXT NOT NULL,
    `file` VARCHAR(500) DEFAULT NULL,
    `line` INT DEFAULT NULL,
    `stack_trace` TEXT DEFAULT NULL,
    `url` VARCHAR(500) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `context` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_level` (`error_level`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- POS Allowed IP Addresses (restrict POS access to specific IPs, admins exempt)
CREATE TABLE IF NOT EXISTS `pos_allowed_ips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `label` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_ip` (`ip_address`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registration restrictions (named groups of block rules)
CREATE TABLE IF NOT EXISTS `registration_restrictions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registration blocklist entries linked to a restriction
CREATE TABLE IF NOT EXISTS `registration_blocklist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `restriction_id` INT NOT NULL,
    `block_type` ENUM('email', 'name', 'ip') NOT NULL,
    `block_value` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`restriction_id`) REFERENCES `registration_restrictions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_block` (`block_type`, `block_value`),
    INDEX `idx_type` (`block_type`),
    INDEX `idx_value` (`block_value`),
    INDEX `idx_restriction` (`restriction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- USER AGREEMENTS (Waivers, Privacy Policies)
-- Tracks waiver/privacy policy acceptance by users
-- =========================================================
CREATE TABLE IF NOT EXISTS `user_agreements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `agreement_type` ENUM('waiver', 'privacy_policy') NOT NULL,
    `agreement_version` VARCHAR(20) DEFAULT '1.0',
    `accepted_at` TIMESTAMP NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `docuseal_submission_id` INT DEFAULT NULL COMMENT 'External: DocuSeal submission ID for e-signature tracking',
    `signing_url` VARCHAR(500) DEFAULT NULL COMMENT 'DocuSeal signing URL',
    `signature_status` ENUM('pending', 'signed', 'expired', 'declined') DEFAULT 'pending',
    `promotional_opt_in` TINYINT(1) DEFAULT 1 COMMENT 'Opt-in for promotional material (photos/videos)',
    `share_evaluations_potential_teams` TINYINT(1) DEFAULT 0 COMMENT 'Allow sharing evaluations with potential teams',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`agreement_type`),
    INDEX `idx_status` (`signature_status`),
    INDEX `idx_promo` (`promotional_opt_in`),
    UNIQUE KEY `unique_user_agreement` (`user_id`, `agreement_type`, `agreement_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- AGREEMENT TEMPLATES (Admin-editable waiver/privacy policy content)
-- =========================================================
CREATE TABLE IF NOT EXISTS `agreement_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `agreement_type` ENUM('waiver', 'privacy_policy') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NOT NULL COMMENT 'HTML content of the agreement',
    `version` VARCHAR(20) DEFAULT '1.0',
    `docuseal_template_id` INT DEFAULT NULL COMMENT 'External: DocuSeal template ID for e-signature',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE INDEX `idx_type` (`agreement_type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default agreement templates
INSERT INTO `agreement_templates` (`agreement_type`, `title`, `content`, `version`, `is_active`) VALUES
('waiver', 'Hockey Player Safety Waiver', '<h3>Hockey Player Safety Waiver</h3>
<p>Based on Hockey Canada Best Practices</p>
<p>By signing this waiver, I acknowledge and agree to the following:</p>
<ol>
<li><strong>Assumption of Risk:</strong> I understand that participation in hockey activities involves inherent risks including, but not limited to, physical injury, concussion, sprains, fractures, and other bodily harm.</li>
<li><strong>Safety Compliance:</strong> I agree to follow all safety guidelines and protocols as outlined by Hockey Canada, including proper equipment usage, fair play rules, and concussion protocols.</li>
<li><strong>Medical Disclosure:</strong> I confirm that I have disclosed any medical conditions that may affect my ability to safely participate in hockey activities.</li>
<li><strong>Equipment Responsibility:</strong> I agree to wear all required protective equipment during practices and games, including helmet with full cage/visor, shoulder pads, shin guards, hockey pants, gloves, and skates in good condition.</li>
<li><strong>Concussion Protocol:</strong> I understand and agree to comply with Hockey Canada''s concussion protocol, including immediate removal from play if a concussion is suspected and obtaining medical clearance before returning to play.</li>
<li><strong>Code of Conduct:</strong> I agree to conduct myself in a respectful and sportsmanlike manner at all times during hockey activities.</li>
<li><strong>Release of Liability:</strong> I release and hold harmless the organization, its coaches, volunteers, and staff from any claims arising from my participation in hockey activities, except in cases of gross negligence.</li>
</ol>
<p>I have read, understood, and agree to the terms of this waiver.</p>', '1.0', 1),
('privacy_policy', 'Privacy Policy & Data Usage Agreement', '<h3>Privacy Policy & Data Usage Agreement</h3>
<p><strong>Your Privacy Matters to Us</strong></p>
<p>We are committed to protecting your personal information. This policy outlines how we collect, use, and safeguard your data.</p>

<h4>Data Collection & Usage</h4>
<ul>
<li>We collect personal information necessary for hockey program registration and participation.</li>
<li><strong>We will NOT share any personal information with outside companies for data mining purposes.</strong></li>
<li>Your data is used solely for the operation of our hockey programs and related services.</li>
</ul>

<h4>Evaluation Sharing</h4>
<ul>
<li><strong>Current Teams:</strong> Your evaluations will be accessible by coaches of teams you are currently rostered on. This is necessary for your development and team management.</li>
<li><strong>Potential Teams:</strong> Evaluations may be shared with potential teams you may play for, but only with your explicit consent (opt-in required below).</li>
</ul>

<h4>Technology & Media Usage</h4>
<ul>
<li>To provide the best experience and development tools, we use technology including photos and videos during sessions, games, and events.</li>
<li>These materials are used for training analysis, skill development, and coaching purposes.</li>
<li><strong>Promotional Material:</strong> We may wish to use photos and videos in promotional materials (website, social media, marketing). You may opt in or out of this below.</li>
</ul>

<h4>Data Security</h4>
<ul>
<li>All personal information is encrypted at rest and in transit.</li>
<li>Access to your data is restricted to authorized personnel only.</li>
<li>We follow Canadian privacy laws (PIPEDA) in all data handling practices.</li>
</ul>

<p>I have read, understood, and agree to the terms of this privacy policy.</p>', '1.0', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- =========================================================
-- RECURRING EXPENSES (Contracts with renewal reminders)
-- =========================================================
CREATE TABLE IF NOT EXISTS `recurring_expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `contract_type` VARCHAR(100) DEFAULT NULL COMMENT 'Type of contract (e.g., lease, software, insurance)',
    `amount` DECIMAL(10,2) NOT NULL,
    `frequency` ENUM('monthly', 'quarterly', 'semi_annual', 'annual') DEFAULT 'monthly',
    `contract_start_date` DATE NOT NULL,
    `contract_end_date` DATE DEFAULT NULL,
    `next_payment_date` DATE DEFAULT NULL,
    `renewal_date` DATE DEFAULT NULL COMMENT 'When the contract needs to be renewed',
    `auto_renew` TINYINT(1) DEFAULT 0,
    `reminder_60_sent` TINYINT(1) DEFAULT 0,
    `reminder_30_sent` TINYINT(1) DEFAULT 0,
    `reminder_15_sent` TINYINT(1) DEFAULT 0,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `account_number` VARCHAR(100) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for contract documents (RustFS URL)',
    `status` ENUM('active', 'paused', 'expired', 'cancelled') DEFAULT 'active',
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `contact_name` VARCHAR(512) DEFAULT NULL COMMENT 'Point of contact name for the contract',
    `contact_email` VARCHAR(255) DEFAULT NULL COMMENT 'Point of contact email',
    `contact_phone` VARCHAR(512) DEFAULT NULL COMMENT 'Point of contact phone number',
    `company_phone` VARCHAR(50) DEFAULT NULL COMMENT 'Company general phone number',
    `company_email` VARCHAR(255) DEFAULT NULL COMMENT 'Company general email address',
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_vendor` (`vendor_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_renewal` (`renewal_date`),
    INDEX `idx_next_payment` (`next_payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- RECURRING EXPENSE DOCUMENTS (Contract uploads, insurance, etc.)
-- =========================================================
CREATE TABLE IF NOT EXISTS `recurring_expense_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recurring_expense_id` INT NOT NULL,
    `document_type` ENUM('contract', 'insurance', 'invoice', 'amendment', 'other') DEFAULT 'contract',
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL COMMENT 'Local file path',
    `nextcloud_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path (RustFS URL)',
    `file_size` INT DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `uploaded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`recurring_expense_id`) REFERENCES `recurring_expenses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_expense` (`recurring_expense_id`),
    INDEX `idx_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- RECURRING EXPENSES - Contact fields for point of contact
-- =========================================================
CREATE TABLE IF NOT EXISTS `business_partners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `company_email` VARCHAR(255) DEFAULT NULL,
    `company_phone` VARCHAR(50) DEFAULT NULL,
    `company_website` VARCHAR(500) DEFAULT NULL,
    `company_address` TEXT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `contact_name` VARCHAR(512) DEFAULT NULL COMMENT 'Point of contact full name',
    `contact_title` VARCHAR(255) DEFAULT NULL COMMENT 'Point of contact title/role',
    `contact_email` VARCHAR(255) DEFAULT NULL COMMENT 'Point of contact email',
    `contact_phone` VARCHAR(512) DEFAULT NULL COMMENT 'Point of contact phone',
    `status` ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_company` (`company_name`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PARTNER CONTRACTS (Contracts associated with business partners)
-- =========================================================
CREATE TABLE IF NOT EXISTS `partner_contracts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `partnership_items` TEXT DEFAULT NULL COMMENT 'Items/benefits included in partnership (e.g., 30 hockey sticks)',
    `value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Monetary value of the contract',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'pending', 'expired', 'cancelled') DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `business_partners`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_partner` (`partner_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Parent-Athlete Multi-Parent Support
-- =========================================================

-- Extend managed_athletes for parent use (parent_id, relationship, permissions)
ALTER TABLE `managed_athletes`
MODIFY COLUMN `coach_id` INT DEFAULT NULL;

-- Parent invitation system for inviting additional parents/grandparents
CREATE TABLE IF NOT EXISTS `parent_invitations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `inviter_id` INT NOT NULL COMMENT 'Parent who sent the invitation',
    `email` VARCHAR(255) NOT NULL COMMENT 'Email address of the invited parent',
    `token` VARCHAR(64) NOT NULL COMMENT 'Unique invitation token',
    `relationship` VARCHAR(50) DEFAULT 'parent' COMMENT 'Relationship: parent, grandparent, guardian, other',
    `status` ENUM('pending', 'accepted', 'expired', 'revoked') DEFAULT 'pending',
    `accepted_by` INT DEFAULT NULL COMMENT 'User ID of the person who accepted',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    `accepted_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`inviter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`accepted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_token` (`token`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athletes linked to each parent invitation (which children the invited parent can manage)
CREATE TABLE IF NOT EXISTS `parent_invitation_athletes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invitation_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    FOREIGN KEY (`invitation_id`) REFERENCES `parent_invitations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_invitation_athlete` (`invitation_id`, `athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Child Check-In/Check-Out System
-- =========================================================

-- Add check-in/check-out toggle to sessions and packages
CREATE TABLE IF NOT EXISTS `camp_checkin_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL COMMENT 'Booking this code is associated with',
    `athlete_id` INT NOT NULL COMMENT 'Athlete being checked in/out',
    `session_id` INT NOT NULL COMMENT 'Session/camp this code is for',
    `parent_id` INT NOT NULL COMMENT 'Parent who generated the code',
    `code_type` ENUM('checkin', 'checkout') NOT NULL COMMENT 'Whether this is a check-in or check-out code',
    `code` VARCHAR(64) NOT NULL COMMENT 'Unique QR code value',
    `items_description` TEXT DEFAULT NULL COMMENT 'Items the child is bringing (lunch box, equipment, etc.)',
    `shared_email` VARCHAR(255) DEFAULT NULL COMMENT 'Email the code was shared with (for alternative pickup)',
    `shared_name` VARCHAR(255) DEFAULT NULL COMMENT 'Name of the person the code was shared with',
    `is_used` TINYINT(1) DEFAULT 0 COMMENT 'Whether this code has been scanned',
    `used_at` DATETIME DEFAULT NULL COMMENT 'When the code was scanned',
    `scanned_by` INT DEFAULT NULL COMMENT 'Staff member who scanned the code',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL COMMENT 'When this code expires',
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`scanned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_code` (`code`),
    INDEX `idx_booking` (`booking_id`),
    INDEX `idx_athlete_session` (`athlete_id`, `session_id`),
    INDEX `idx_code_type` (`code_type`),
    INDEX `idx_used` (`is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Camp & Multi-Week Program Package Types
-- =========================================================

-- Add camp and multi-week program fields to packages table
CREATE TABLE IF NOT EXISTS `camp_daily_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL COMMENT 'Camp package this schedule belongs to',
    `schedule_date` DATE NOT NULL COMMENT 'The specific date',
    `start_time` TIME NOT NULL COMMENT 'Start time for this day',
    `end_time` TIME NOT NULL COMMENT 'End time for this day',
    `title` VARCHAR(255) DEFAULT NULL COMMENT 'Optional title for this day',
    `description` TEXT DEFAULT NULL COMMENT 'Description of activities',
    `location` VARCHAR(255) DEFAULT NULL COMMENT 'Location/place for this day',
    `coach_ids` TEXT DEFAULT NULL COMMENT 'Comma-separated coach IDs for this day',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    INDEX `idx_package_date` (`package_id`, `schedule_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camp schedule assignments (to groups or individuals)
CREATE TABLE IF NOT EXISTS `camp_schedule_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `schedule_id` INT NOT NULL COMMENT 'Daily schedule entry',
    `user_id` INT DEFAULT NULL COMMENT 'Individual athlete assignment',
    `team_id` INT DEFAULT NULL COMMENT 'Team/group assignment',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`schedule_id`) REFERENCES `camp_daily_schedules`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    INDEX `idx_schedule` (`schedule_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_team` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camp add-on options (meal plans, bus transportation, etc.)
CREATE TABLE IF NOT EXISTS `camp_add_ons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL COMMENT 'Camp package this add-on belongs to',
    `name` VARCHAR(255) NOT NULL COMMENT 'Add-on name',
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `is_default` TINYINT(1) DEFAULT 0 COMMENT 'Selected by default',
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    INDEX `idx_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camp registration add-on selections
CREATE TABLE IF NOT EXISTS `camp_registration_add_ons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_package_id` INT NOT NULL,
    `add_on_id` INT NOT NULL,
    `opted_in` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_package_id`) REFERENCES `user_packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`add_on_id`) REFERENCES `camp_add_ons`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_registration_addon` (`user_package_id`, `add_on_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Multi-week program dates
CREATE TABLE IF NOT EXISTS `multiweek_program_dates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `session_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `individual_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Price if purchased individually',
    `auto_session_id` INT DEFAULT NULL COMMENT 'Auto-created session ID',
    `location` VARCHAR(255) DEFAULT NULL COMMENT 'Location/place for this session',
    `coach_ids` TEXT DEFAULT NULL COMMENT 'Comma-separated coach IDs for this session',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`auto_session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_package_date` (`package_id`, `session_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package Coaches - Multiple coaches per package (camp/program)
CREATE TABLE IF NOT EXISTS `package_coaches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `coach_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_package_coach` (`package_id`, `coach_id`),
    INDEX `idx_coach` (`coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Multi-Role Support
-- Allows users to hold multiple roles simultaneously
-- =========================================================
CREATE TABLE IF NOT EXISTS `user_roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `role` ENUM('athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach', 'front_desk_staff', 'hr', 'accounting', 'goalie_dev', 'player_dev') NOT NULL,
    `assigned_by` INT DEFAULT NULL COMMENT 'Admin who assigned this role',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_user_role` (`user_id`, `role`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stopwatch sessions - records a timed session by a coach
CREATE TABLE IF NOT EXISTS `stopwatch_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id` INT NOT NULL,
    `skill_id` INT DEFAULT NULL COMMENT 'Optional link to a skill being timed',
    `session_name` VARCHAR(255) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE SET NULL,
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_skill` (`skill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stopwatch times - individual lap/split times recorded during a session
CREATE TABLE IF NOT EXISTS `stopwatch_times` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `athlete_id` INT DEFAULT NULL COMMENT 'Athlete this time is assigned to',
    `lap_number` INT NOT NULL DEFAULT 1,
    `lap_time_ms` BIGINT NOT NULL COMMENT 'Lap time in milliseconds',
    `total_time_ms` BIGINT NOT NULL COMMENT 'Total elapsed time in milliseconds',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `stopwatch_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_session` (`session_id`),
    INDEX `idx_athlete` (`athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add stopwatch flag to eval_skills
CREATE TABLE IF NOT EXISTS `stallion_shipping_labels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `stallion_shipment_id` VARCHAR(255) DEFAULT NULL COMMENT 'Stallion Express shipment ID',
    `tracking_number` VARCHAR(255) DEFAULT NULL,
    `label_url` VARCHAR(1000) DEFAULT NULL COMMENT 'URL to download shipping label PDF',
    `shipment_data` JSON DEFAULT NULL COMMENT 'Full API response from Stallion Express',
    `status` ENUM('created', 'printed', 'shipped', 'delivered', 'cancelled') DEFAULT 'created',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `shop_orders`(`id`) ON DELETE CASCADE,
    INDEX `idx_order` (`order_id`),
    INDEX `idx_tracking` (`tracking_number`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NDI Cameras for network video input
CREATE TABLE IF NOT EXISTS `ndi_cameras` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL COMMENT 'Display name for the camera',
    `ip_address` VARCHAR(255) NOT NULL COMMENT 'IP address or hostname of the NDI source',
    `port` INT DEFAULT 5960 COMMENT 'NDI port (default 5960)',
    `ndi_name` VARCHAR(255) DEFAULT NULL COMMENT 'NDI source name for discovery',
    `location` VARCHAR(255) DEFAULT NULL COMMENT 'Physical location description',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether the camera is enabled',
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- GAME PLAN MODULE TABLES (Video Review & Planning System)
-- =========================================================

-- Video clip tags (categories: offense, defense, special_teams, etc.)
CREATE TABLE IF NOT EXISTS `vr_tags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `color` VARCHAR(20) DEFAULT '#6B46C1',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Video source files uploaded to the Film Room
CREATE TABLE IF NOT EXISTS `vr_video_sources` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `nextcloud_path` VARCHAR(500) DEFAULT NULL COMMENT 'Cloud storage path for gameplan video (RustFS URL)',
    `camera_angle` VARCHAR(50) DEFAULT NULL,
    `duration` INT DEFAULT NULL COMMENT 'Duration in seconds',
    `file_size` BIGINT DEFAULT NULL,
    `hls_url` VARCHAR(500) DEFAULT NULL COMMENT 'HLS master playlist URL (api/media.php proxy path)',
    `hls_status` ENUM('pending', 'processing', 'ready', 'failed') DEFAULT NULL COMMENT 'HLS transcoding status',
    `hls_job_id` VARCHAR(36) DEFAULT NULL COMMENT 'Companion server HLS transcode job ID',
    `hls_master_url` VARCHAR(500) DEFAULT NULL COMMENT 'S3 key to master.m3u8 manifest',
    `hls_segments_path` VARCHAR(500) DEFAULT NULL COMMENT 'S3 prefix containing HLS segments',
    `dash_url` VARCHAR(500) DEFAULT NULL COMMENT 'MPEG-DASH MPD manifest URL (api/media.php proxy path)',
    `dash_manifest_url` VARCHAR(500) DEFAULT NULL COMMENT 'S3 key to DASH manifest.mpd',
    `game_id` INT DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `uploaded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`game_id`) REFERENCES `game_schedules`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_game` (`game_id`),
    INDEX `idx_team` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Video clips created from source videos
CREATE TABLE IF NOT EXISTS `vr_video_clips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `source_id` INT DEFAULT NULL,
    `game_id` INT DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `start_time` DECIMAL(10,2) DEFAULT 0,
    `end_time` DECIMAL(10,2) DEFAULT 0,
    `thumbnail_path` VARCHAR(500) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`source_id`) REFERENCES `vr_video_sources`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_id`) REFERENCES `game_schedules`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_source` (`source_id`),
    INDEX `idx_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tags applied to clips
CREATE TABLE IF NOT EXISTS `vr_clip_tags` (
    `clip_id` INT NOT NULL,
    `tag_id` INT NOT NULL,
    PRIMARY KEY (`clip_id`, `tag_id`),
    FOREIGN KEY (`clip_id`) REFERENCES `vr_video_clips`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`) REFERENCES `vr_tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athletes tagged in clips
CREATE TABLE IF NOT EXISTS `vr_clip_athletes` (
    `clip_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    PRIMARY KEY (`clip_id`, `athlete_id`),
    FOREIGN KEY (`clip_id`) REFERENCES `vr_video_clips`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roster players (non-user roster management for game plan)
-- Allows teams to have players who are not Arctic Wolves users
-- Players can optionally be linked to existing user accounts
CREATE TABLE IF NOT EXISTS `roster_players` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL COMMENT 'Linked Arctic Wolves user account (NULL if external player)',
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `jersey_number` INT DEFAULT NULL,
    `position` VARCHAR(50) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `parent_name` VARCHAR(200) DEFAULT NULL,
    `parent_email` VARCHAR(255) DEFAULT NULL,
    `parent_phone` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    `season_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE SET NULL,
    INDEX `idx_team` (`team_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_season` (`season_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Game plans (pre-game strategies, post-game reviews, practice plans)
CREATE TABLE IF NOT EXISTS `vr_game_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id` INT NOT NULL,
    `game_id` INT DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `plan_type` ENUM('pre_game', 'post_game', 'practice') DEFAULT 'pre_game',
    `status` ENUM('draft', 'active', 'completed', 'archived') DEFAULT 'draft',
    `offensive_system` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., 1-2-2, 2-1-2, 1-3-1',
    `defensive_system` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., man-on-man, zone, box+1',
    `powerplay_system` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., umbrella, overload, 1-3-1',
    `penalty_kill_system` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., diamond, box',
    `key_players_notes` TEXT DEFAULT NULL COMMENT 'Opponent key players to watch',
    `strategy_notes` TEXT DEFAULT NULL COMMENT 'Additional strategy details',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_id`) REFERENCES `game_schedules`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_game` (`game_id`),
    INDEX `idx_type` (`plan_type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hockey lines / depth chart assignments
CREATE TABLE IF NOT EXISTS `vr_game_plan_lines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plan_id` INT DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `game_id` INT DEFAULT NULL COMMENT 'NULL = default/standard lineup, set = game-specific lines',
    `line_name` VARCHAR(50) NOT NULL COMMENT 'e.g., Line 1, Pair 1, PP1',
    `position` VARCHAR(20) NOT NULL COMMENT 'e.g., LW, C, RW, LD, RD',
    `athlete_id` INT DEFAULT NULL,
    `roster_player_id` INT DEFAULT NULL COMMENT 'References roster_players.id for non-user players',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`plan_id`) REFERENCES `vr_game_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_id`) REFERENCES `game_schedules`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`roster_player_id`) REFERENCES `roster_players`(`id`) ON DELETE SET NULL,
    INDEX `idx_plan` (`plan_id`),
    INDEX `idx_team` (`team_id`),
    INDEX `idx_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Video permissions per user per team
CREATE TABLE IF NOT EXISTS `vr_video_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `team_id` INT NOT NULL,
    `can_upload` TINYINT(1) DEFAULT 0,
    `can_clip` TINYINT(1) DEFAULT 0,
    `can_tag` TINYINT(1) DEFAULT 0,
    `can_publish` TINYINT(1) DEFAULT 0,
    `can_delete` TINYINT(1) DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_team` (`user_id`, `team_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review sessions (team video review presentations)
CREATE TABLE IF NOT EXISTS `vr_review_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id` INT NOT NULL,
    `game_id` INT DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `session_type` VARCHAR(50) DEFAULT 'pre_game',
    `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    `scheduled_date` DATETIME NOT NULL,
    `completed_date` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_id`) REFERENCES `game_schedules`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_scheduled` (`scheduled_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clips linked to review sessions
CREATE TABLE IF NOT EXISTS `vr_review_session_clips` (
    `session_id` INT NOT NULL,
    `clip_id` INT NOT NULL,
    `sort_order` INT DEFAULT 0,
    PRIMARY KEY (`session_id`, `clip_id`),
    FOREIGN KEY (`session_id`) REFERENCES `vr_review_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`clip_id`) REFERENCES `vr_video_clips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Device pairing for video review (viewer/controller casting)
CREATE TABLE IF NOT EXISTS `vr_device_pairs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pair_code` VARCHAR(10) NOT NULL UNIQUE COMMENT 'Short code for pairing devices',
    `session_id` INT DEFAULT NULL COMMENT 'Review session this pair belongs to',
    `controller_token` VARCHAR(64) NOT NULL COMMENT 'Token identifying the controller device',
    `viewer_token` VARCHAR(64) DEFAULT NULL COMMENT 'Token identifying the viewer device',
    `status` ENUM('waiting', 'paired', 'active', 'ended') DEFAULT 'waiting',
    `current_clip_id` INT DEFAULT NULL,
    `current_time` DECIMAL(10,3) DEFAULT 0.000 COMMENT 'Current playback time in seconds',
    `is_frozen` TINYINT(1) DEFAULT 0 COMMENT 'Whether the viewer display is frozen',
    `controller_page` VARCHAR(50) DEFAULT 'home' COMMENT 'Current page the controller is navigating',
    `telestration_data` MEDIUMTEXT DEFAULT NULL COMMENT 'Canvas drawing data URL for telestration sync to TV viewer',
    `telestration_seq` INT DEFAULT 0 COMMENT 'Telestration version counter for efficient polling',
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `vr_review_sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`current_clip_id`) REFERENCES `vr_video_clips`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_pair_code` (`pair_code`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional controllers linked to a device pair (multi-controller support)
-- Allows multiple coaches to telestrate and control a single viewer session
CREATE TABLE IF NOT EXISTS `vr_device_pair_controllers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pair_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `controller_token` VARCHAR(64) NOT NULL COMMENT 'Token identifying this controller device',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`pair_id`) REFERENCES `vr_device_pairs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_pair_user` (`pair_id`, `user_id`),
    INDEX `idx_pair` (`pair_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default video tags
INSERT IGNORE INTO `vr_tags` (`name`, `category`, `color`) VALUES
('Forecheck', 'offense', '#3B82F6'),
('Breakout', 'offense', '#10B981'),
('Power Play', 'special_teams', '#F59E0B'),
('Penalty Kill', 'special_teams', '#EF4444'),
('Faceoff', 'offense', '#8B5CF6'),
('Goal', 'highlight', '#10B981'),
('Scoring Chance', 'highlight', '#3B82F6'),
('Turnover', 'defense', '#EF4444'),
('Defensive Zone', 'defense', '#6366F1'),
('Neutral Zone', 'transition', '#A855F7'),
('Odd Man Rush', 'offense', '#F97316'),
('Board Play', 'offense', '#14B8A6'),
('Save', 'goaltending', '#06B6D4'),
('Rebound', 'goaltending', '#0EA5E9'),
('Dump and Chase', 'offense', '#84CC16'),
('Line Change', 'transition', '#A8A8B8');

-- =========================================================
-- Programs & Camps Enhancements
-- Add location/place to camp schedules and multi-week dates
-- =========================================================

CREATE TABLE IF NOT EXISTS `marketing_email_campaigns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `package_ids` TEXT DEFAULT NULL COMMENT 'JSON array of package IDs included',
    `include_child_pickup` TINYINT(1) DEFAULT 0 COMMENT 'Whether to include child pickup info',
    `recipient_filter` ENUM('all', 'opted_in', 'parents', 'athletes') DEFAULT 'opted_in',
    `sent_count` INT DEFAULT 0,
    `failed_count` INT DEFAULT 0,
    `status` ENUM('draft', 'sending', 'sent', 'failed') DEFAULT 'draft',
    `created_by` INT NOT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('rustfs_endpoint',   NULL, 'text',     'RustFS S3-compatible endpoint URL (e.g., https://rustfs.example.com)'),
('rustfs_access_key', NULL, 'text',     'RustFS S3 access key ID'),
('rustfs_secret_key', NULL, 'password', 'RustFS S3 secret key (stored encrypted)'),
('rustfs_bucket',     NULL, 'text',     'RustFS S3 bucket name'),
('rustfs_region',     'us-east-1', 'text', 'RustFS S3 region'),
('rustfs_use_ssl',    '1', 'boolean',  'Use SSL/HTTPS for RustFS connections'),
('rustfs_path_style', '1', 'boolean',  'Use path-style access (recommended for self-hosted RustFS)');

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('gameplan_companion_url', NULL, 'text', 'Video companion server URL (e.g., http://companion:5100)'),
('gameplan_companion_api_key', NULL, 'password', 'Video companion server API key'),
('gameplan_app_url', NULL, 'text', 'Main application URL for companion callbacks (e.g., https://arcticwolves.ca)');

-- Clean up legacy companion settings with wrong key names (if they exist)
DELETE FROM `system_settings` WHERE `setting_key` IN ('companion_url', 'companion_api_key') AND `setting_value` IS NULL;

-- Offline Video Queue — tracks videos recorded on-device while offline.
-- Stores all metadata needed to auto-assign uploads to the correct application area.
CREATE TABLE IF NOT EXISTS `offline_video_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'User who recorded the video',
    `user_role` VARCHAR(50) NOT NULL COMMENT 'Role at time of recording',
    `upload_type` ENUM('athlete_video','coach_video','drill_video','video_source') NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `video_category` ENUM('drill','game') DEFAULT 'drill',
    `original_filename` VARCHAR(255) DEFAULT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `content_type` VARCHAR(100) DEFAULT 'video/mp4',
    `athlete_id` INT DEFAULT NULL,
    `coach_id` INT DEFAULT NULL,
    `session_id` INT DEFAULT NULL,
    `drill_id` INT DEFAULT NULL,
    `rep_number` INT DEFAULT 1,
    `session_date` DATE DEFAULT NULL,
    `drill_type` VARCHAR(100) DEFAULT NULL,
    `drill_name` VARCHAR(255) DEFAULT NULL,
    `rating` INT DEFAULT NULL,
    `game_date` DATE DEFAULT NULL,
    `team_played_on` VARCHAR(255) DEFAULT NULL,
    `opponent_team` VARCHAR(255) DEFAULT NULL,
    `camera_angle` VARCHAR(50) DEFAULT NULL,
    `game_id` INT DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `status` ENUM('pending','uploading','uploaded','failed') DEFAULT 'pending',
    `upload_progress` INT DEFAULT 0 COMMENT 'Percentage 0-100',
    `error_message` TEXT DEFAULT NULL,
    `video_id` INT DEFAULT NULL COMMENT 'ID in videos table after successful upload',
    `source_id` INT DEFAULT NULL COMMENT 'ID in vr_video_sources after successful upload',
    `object_key` VARCHAR(500) DEFAULT NULL COMMENT 'S3 object key after upload',
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

-- Admin Business Wishlist
CREATE TABLE IF NOT EXISTS `admin_wishlist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) DEFAULT NULL,
    `link` VARCHAR(2048) DEFAULT NULL COMMENT 'Purchase URL or distributor info',
    `display_order` INT DEFAULT 0,
    `purchased` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Personal Development Programs
-- =====================================================

-- Session types for long-term development programs
INSERT IGNORE INTO `session_types` (`name`, `description`, `default_price`, `duration_minutes`) VALUES
('Long Term Goalie Development', 'Structured long-term development program for goalies focusing on technique, positioning, and game sense', 0.00, 60),
('Long Term Player Development', 'Structured long-term development program for players focusing on skating, shooting, and hockey IQ', 0.00, 60);

-- Development program enrollments
CREATE TABLE IF NOT EXISTS `development_program_enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `program_type` ENUM('goalie_dev', 'player_dev') NOT NULL,
    `program_name` VARCHAR(255) DEFAULT NULL COMMENT 'Name of the dev program product from training_session_templates',
    `template_id` INT DEFAULT NULL COMMENT 'Reference to the training_session_templates product',
    `status` ENUM('active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
    `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `start_date` DATE DEFAULT NULL COMMENT 'Auto-calculated program start date',
    `end_date` DATE DEFAULT NULL COMMENT 'Auto-calculated from start_date + duration_weeks',
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_program_type` (`program_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Development program drills (assigned to athletes in a program)
CREATE TABLE IF NOT EXISTS `development_program_drills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `enrollment_id` INT NOT NULL,
    `drill_id` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `sort_order` INT DEFAULT 0,
    `status` ENUM('assigned', 'in_progress', 'completed') DEFAULT 'assigned',
    `coach_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`enrollment_id`) REFERENCES `development_program_enrollments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`drill_id`) REFERENCES `drills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_enrollment` (`enrollment_id`),
    INDEX `idx_drill` (`drill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Development program messages (chat between coach and athlete)
CREATE TABLE IF NOT EXISTS `development_program_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `enrollment_id` INT DEFAULT NULL COMMENT 'NULL for global dev program chat',
    `drill_assignment_id` INT DEFAULT NULL COMMENT 'Non-null for drill-specific comments',
    `sender_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `video_url` VARCHAR(512) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`enrollment_id`) REFERENCES `development_program_enrollments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`drill_assignment_id`) REFERENCES `development_program_drills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_enrollment` (`enrollment_id`),
    INDEX `idx_drill_assignment` (`drill_assignment_id`),
    INDEX `idx_sender` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personal drills (coach-created drills with video, title, description)
CREATE TABLE IF NOT EXISTS `personal_drills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `video_url` VARCHAR(512) DEFAULT NULL,
    `video_upload_path` VARCHAR(500) DEFAULT NULL COMMENT 'Path to uploaded video file',
    `position` ENUM('player', 'goalie') DEFAULT 'player' COMMENT 'Target position for the drill',
    `thumbnail_path` VARCHAR(500) DEFAULT NULL COMMENT 'RustFS path to video thumbnail image',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification templates for development program registration
CREATE TABLE IF NOT EXISTS `development_notification_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `program_type` ENUM('goalie_dev', 'player_dev') NOT NULL,
    `subject` VARCHAR(255) NOT NULL DEFAULT 'New Development Program Registration',
    `body` TEXT NOT NULL,
    `notification_email` VARCHAR(255) DEFAULT NULL COMMENT 'Email address to notify on new registrations (in addition to role-based notifications)',
    `program_duration_weeks` INT DEFAULT NULL COMMENT 'Duration of the program in weeks (e.g. 4 for a 4-week program)',
    `updated_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_program_type` (`program_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default notification templates (sent to ATHLETES on enrollment)
INSERT IGNORE INTO `development_notification_templates` (`program_type`, `subject`, `body`) VALUES
('goalie_dev', 'Welcome to the Goalie Development Program!', 'Welcome! You have been enrolled in the Long Term Goalie Development program. Your coach will be in touch shortly to set up your personalized training plan, including drill programs and video analysis sessions.'),
('player_dev', 'Welcome to the Player Development Program!', 'Welcome! You have been enrolled in the Long Term Player Development program. Your coach will be in touch shortly to set up your personalized training plan, including skating, shooting, and skills coaching with video analysis.');

-- Email templates - customizable email templates for all system emails
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_type` VARCHAR(50) NOT NULL COMMENT 'Template type key matching mailer.php types',
    `label` VARCHAR(100) NOT NULL COMMENT 'Human-readable label for the template',
    `subject` VARCHAR(255) NOT NULL,
    `body_text` TEXT DEFAULT NULL COMMENT 'Plain text version for standard editing',
    `body_html` TEXT DEFAULT NULL COMMENT 'Custom HTML version (advanced editing)',
    `is_custom` TINYINT(1) DEFAULT 0 COMMENT '1 if admin has customized this template',
    `updated_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_template_type` (`template_type`),
    INDEX `idx_template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Development program videos (athlete-uploaded videos for coach review)
CREATE TABLE IF NOT EXISTS `development_program_videos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `enrollment_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `drill_assignment_id` INT DEFAULT NULL COMMENT 'Non-null when video is for a specific drill',
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `video_url` VARCHAR(512) DEFAULT NULL,
    `video_upload_path` VARCHAR(500) DEFAULT NULL,
    `thumbnail_path` VARCHAR(500) DEFAULT NULL COMMENT 'RustFS path to video thumbnail image',
    `status` ENUM('pending_review', 'reviewed', 'feedback_given') DEFAULT 'pending_review',
    `coach_feedback` TEXT DEFAULT NULL,
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`enrollment_id`) REFERENCES `development_program_enrollments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`drill_assignment_id`) REFERENCES `development_program_drills`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_enrollment` (`enrollment_id`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Development appointments (coach-scheduled sessions with athletes)
CREATE TABLE IF NOT EXISTS `development_appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `enrollment_id` INT NOT NULL,
    `coach_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `appointment_type` ENUM('call', 'video_call', 'in_person') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `duration_minutes` INT DEFAULT 30,
    `location` VARCHAR(255) DEFAULT NULL COMMENT 'For in-person appointments',
    `meeting_url` VARCHAR(512) DEFAULT NULL COMMENT 'For video call appointments',
    `phone_number` VARCHAR(50) DEFAULT NULL COMMENT 'For call appointments',
    `status` ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`enrollment_id`) REFERENCES `development_program_enrollments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_enrollment` (`enrollment_id`),
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_date` (`appointment_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Scoreboard Module Tables
-- scoreboard.arcticwolves.ca – In-arena scoreboard display
-- =========================================================

-- Scoreboard games – tracks each game session
CREATE TABLE IF NOT EXISTS `scoreboard_games` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `home_team_name` VARCHAR(100) NOT NULL,
    `away_team_name` VARCHAR(100) NOT NULL,
    `home_team_id` INT DEFAULT NULL COMMENT 'FK to teams table (optional)',
    `away_team_id` INT DEFAULT NULL COMMENT 'FK to teams table (optional)',
    `home_score` INT DEFAULT 0,
    `away_score` INT DEFAULT 0,
    `home_shots` INT DEFAULT 0,
    `away_shots` INT DEFAULT 0,
    `current_period` VARCHAR(5) DEFAULT '1',
    `status` ENUM('warmup', 'in_progress', 'intermission', 'final') DEFAULT 'warmup',
    `is_arctic_wolves_game` TINYINT(1) DEFAULT 0 COMMENT 'If 1, sync scoresheet to Game Plan and player stats',
    `stat_tracking_enabled` TINYINT(1) DEFAULT 1 COMMENT 'If 1, show goal assignment modal on +1 Goal; if 0, just update score',
    `synced_to_gameplan` TINYINT(1) DEFAULT 0,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ended_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scoreboard goals – detailed goal records for scoresheet
CREATE TABLE IF NOT EXISTS `scoreboard_goals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT NOT NULL,
    `period` VARCHAR(5) DEFAULT '1',
    `game_time` VARCHAR(10) DEFAULT NULL COMMENT 'Clock time when goal was scored (e.g. 12:34)',
    `game_time_seconds` INT DEFAULT NULL COMMENT 'Seconds elapsed for sorting',
    `team` ENUM('home', 'away') NOT NULL,
    `scorer_number` VARCHAR(5) DEFAULT NULL,
    `scorer_name` VARCHAR(100) DEFAULT NULL,
    `assist1_number` VARCHAR(5) DEFAULT NULL,
    `assist1_name` VARCHAR(100) DEFAULT NULL,
    `assist2_number` VARCHAR(5) DEFAULT NULL,
    `assist2_name` VARCHAR(100) DEFAULT NULL,
    `goal_type` VARCHAR(50) DEFAULT 'Even Strength',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`game_id`) REFERENCES `scoreboard_games`(`id`) ON DELETE CASCADE,
    INDEX `idx_game` (`game_id`),
    INDEX `idx_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scoreboard penalties – penalty tracking
CREATE TABLE IF NOT EXISTS `scoreboard_penalties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT NOT NULL,
    `period` VARCHAR(5) DEFAULT '1',
    `game_time` VARCHAR(10) DEFAULT NULL,
    `game_time_seconds` INT DEFAULT NULL,
    `team` ENUM('home', 'away') NOT NULL,
    `player_number` VARCHAR(5) DEFAULT NULL,
    `player_name` VARCHAR(100) DEFAULT NULL,
    `infraction` VARCHAR(100) NOT NULL,
    `duration_minutes` INT DEFAULT 2,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`game_id`) REFERENCES `scoreboard_games`(`id`) ON DELETE CASCADE,
    INDEX `idx_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scoreboard shots – per-period shot tracking
CREATE TABLE IF NOT EXISTS `scoreboard_shots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT NOT NULL,
    `period` VARCHAR(5) DEFAULT '1',
    `team` ENUM('home', 'away') NOT NULL,
    `count` INT DEFAULT 0,
    FOREIGN KEY (`game_id`) REFERENCES `scoreboard_games`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_game_period_team` (`game_id`, `period`, `team`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Evaluation Skills module migrations
-- Relax NOT NULL constraints so evaluation_scores rows can be inserted
-- with only evaluation_id + skill_id (new-style inserts)
-- =========================================================

ALTER TABLE `evaluation_scores`
  MODIFY COLUMN `athlete_id` INT DEFAULT NULL,
  MODIFY COLUMN `evaluator_id` INT DEFAULT NULL,
  MODIFY COLUMN `score` DECIMAL(5,2) DEFAULT NULL,
  MODIFY COLUMN `evaluation_date` DATE DEFAULT NULL;

-- Add FK index for evaluation_id in evaluation_scores (if not already present)
ALTER TABLE `evaluation_scores`
  ADD INDEX IF NOT EXISTS `idx_eval_scores_evaluation_id` (`evaluation_id`);

-- Per-user OAuth tokens (e.g. Office365 calendar sync per coach)
CREATE TABLE IF NOT EXISTS `user_oauth_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `provider` VARCHAR(50) NOT NULL COMMENT 'e.g. office365_calendar',
    `access_token` TEXT NOT NULL,
    `refresh_token` TEXT DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `connected_email` VARCHAR(255) DEFAULT NULL,
    `scope` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_provider` (`user_id`, `provider`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office 365 Calendar Events - Read-only events pulled from Outlook (not sessions)
CREATE TABLE IF NOT EXISTS `o365_calendar_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'Coach who synced this event',
    `o365_event_id` VARCHAR(512) NOT NULL COMMENT 'Office 365 iCalUId for dedup',
    `title` VARCHAR(255) NOT NULL DEFAULT 'Office 365 Event',
    `event_date` DATE NOT NULL,
    `event_time` TIME DEFAULT NULL,
    `duration_minutes` INT DEFAULT 60,
    `description` TEXT DEFAULT NULL,
    `location_name` VARCHAR(255) DEFAULT NULL,
    `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_event` (`user_id`, `o365_event_id`),
    INDEX `idx_user_date` (`user_id`, `event_date`),
    INDEX `idx_event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

