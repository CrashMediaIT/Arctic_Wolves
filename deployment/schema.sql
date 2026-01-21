-- =========================================================
-- CRASH HOCKEY DATABASE SCHEMA
-- Complete database structure for the application
-- =========================================================

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('athlete', 'coach', 'coach_plus', 'admin', 'parent', 'team_coach') DEFAULT 'athlete',
  `position` ENUM('forward', 'defense', 'goalie') DEFAULT NULL,
  `birth_date` DATE DEFAULT NULL,
  `weight` INT DEFAULT NULL COMMENT 'Weight in pounds',
  `height` INT DEFAULT NULL COMMENT 'Height in centimeters',
  `shooting_hand` ENUM('left', 'right', 'ambidextrous') DEFAULT NULL,
  `catching_hand` ENUM('regular', 'full_right') DEFAULT NULL COMMENT 'For goalies',
  `primary_arena` VARCHAR(255) DEFAULT NULL,
  `profile_pic` VARCHAR(255) DEFAULT NULL,
  `assigned_coach_id` INT DEFAULT NULL,
  `email_notifications` TINYINT(1) DEFAULT 1,
  `is_verified` TINYINT(1) DEFAULT 0,
  `verification_code` VARCHAR(10) DEFAULT NULL,
  `force_pass_change` TINYINT(1) DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0 COMMENT 'Soft delete flag for terminated users',
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_by` INT DEFAULT NULL COMMENT 'Admin who deleted this user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`assigned_coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`),
  INDEX `idx_coach` (`assigned_coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Locations
CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `google_place_id` VARCHAR(255) DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_place_id` (`google_place_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Age Groups
CREATE TABLE IF NOT EXISTS `age_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `min_age` INT DEFAULT NULL,
  `max_age` INT DEFAULT NULL,
  `description` TEXT,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`),
  INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skill Levels
CREATE TABLE IF NOT EXISTS `skill_levels` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`),
  INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Managed Athletes (for Parent/Manager accounts)
CREATE TABLE IF NOT EXISTS `managed_athletes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT NOT NULL,
  `athlete_id` INT NOT NULL,
  `relationship` VARCHAR(100) DEFAULT 'Parent',
  `can_book` TINYINT(1) DEFAULT 1,
  `can_view_stats` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_parent_athlete` (`parent_id`, `athlete_id`),
  INDEX `idx_parent` (`parent_id`),
  INDEX `idx_athlete` (`athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Types
CREATE TABLE IF NOT EXISTS `session_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_type` VARCHAR(100) NOT NULL,
  `session_type_category` ENUM('group', 'private', 'semi-private') DEFAULT 'group',
  `title` VARCHAR(255) NOT NULL,
  `session_date` DATE NOT NULL,
  `session_time` TIME NOT NULL,
  `session_plan` TEXT,
  `practice_plan_id` INT DEFAULT NULL,
  `age_group_id` INT DEFAULT NULL,
  `skill_level_id` INT DEFAULT NULL,
  `arena` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `max_capacity` INT DEFAULT 20,
  `max_athletes` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`practice_plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`age_group_id`) REFERENCES `age_groups`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`skill_level_id`) REFERENCES `skill_levels`(`id`) ON DELETE SET NULL,
  INDEX `idx_date` (`session_date`),
  INDEX `idx_practice_plan` (`practice_plan_id`),
  INDEX `idx_age_skill` (`age_group_id`, `skill_level_id`),
  INDEX `idx_type_category` (`session_type_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `session_id` INT DEFAULT NULL,
  `package_id` INT DEFAULT NULL,
  `stripe_session_id` VARCHAR(255) DEFAULT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `original_price` DECIMAL(10,2) NOT NULL,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `discount_code` VARCHAR(50) DEFAULT NULL,
  `credit_applied` DECIMAL(10,2) DEFAULT 0.00,
  `booked_for_user_id` INT DEFAULT NULL,
  `payment_type` ENUM('session', 'package') DEFAULT 'session',
  `status` ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booked_for_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_session` (`user_id`, `session_id`),
  INDEX `idx_booked_for` (`booked_for_user_id`),
  INDEX `idx_package` (`package_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discount Codes
CREATE TABLE IF NOT EXISTS `discount_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `type` ENUM('percent', 'fixed') NOT NULL,
  `value` DECIMAL(10,2) NOT NULL,
  `usage_limit` INT DEFAULT NULL,
  `times_used` INT DEFAULT 0,
  `expiry_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Packages
CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `package_type` ENUM('bundled', 'credits') NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `credits` INT DEFAULT NULL,
  `valid_days` INT DEFAULT 365,
  `age_group_id` INT DEFAULT NULL,
  `skill_level_id` INT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`age_group_id`) REFERENCES `age_groups`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`skill_level_id`) REFERENCES `skill_levels`(`id`) ON DELETE SET NULL,
  INDEX `idx_type` (`package_type`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package Sessions (for bundled packages)
CREATE TABLE IF NOT EXISTS `package_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `package_id` INT NOT NULL,
  `session_id` INT NOT NULL,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_package_session` (`package_id`, `session_id`),
  INDEX `idx_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Package Credits
CREATE TABLE IF NOT EXISTS `user_package_credits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `package_id` INT NOT NULL,
  `booking_id` INT DEFAULT NULL,
  `credits_purchased` INT NOT NULL,
  `credits_remaining` INT NOT NULL,
  `expiry_date` DATE NOT NULL,
  `purchased_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expense Categories
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `display_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expenses (Accounts Payable)
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `vendor_name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `amount` DECIMAL(10,2) NOT NULL,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `receipt_file` VARCHAR(255) DEFAULT NULL,
  `ocr_data` TEXT DEFAULT NULL,
  `payment_method` VARCHAR(100) DEFAULT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_date` (`expense_date`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expense Line Items (from OCR)
CREATE TABLE IF NOT EXISTS `expense_line_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_id` INT NOT NULL,
  `line_number` INT NOT NULL,
  `item_description` VARCHAR(500) DEFAULT NULL,
  `quantity` DECIMAL(10,2) DEFAULT 1.00,
  `unit_price` DECIMAL(10,2) DEFAULT 0.00,
  `line_total` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`expense_id`) REFERENCES `expenses`(`id`) ON DELETE CASCADE,
  INDEX `idx_expense` (`expense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cloud Receipts (Nextcloud Integration)
CREATE TABLE IF NOT EXISTS `cloud_receipts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `file_path` VARCHAR(500) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_hash` VARCHAR(64) NOT NULL UNIQUE,
  `expense_id` INT DEFAULT NULL,
  `processed` TINYINT(1) DEFAULT 0,
  `ocr_attempted` TINYINT(1) DEFAULT 0,
  `ocr_data` TEXT DEFAULT NULL,
  `detected_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `processed_date` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`expense_id`) REFERENCES `expenses`(`id`) ON DELETE SET NULL,
  INDEX `idx_processed` (`processed`),
  INDEX `idx_hash` (`file_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mileage Logs
CREATE TABLE IF NOT EXISTS `mileage_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `athlete_id` INT DEFAULT NULL,
  `session_id` INT DEFAULT NULL,
  `trip_date` DATE NOT NULL,
  `purpose` VARCHAR(500) DEFAULT NULL,
  `start_location` VARCHAR(500) NOT NULL,
  `end_location` VARCHAR(500) NOT NULL,
  `total_distance_km` DECIMAL(10,2) NOT NULL,
  `total_distance_miles` DECIMAL(10,2) NOT NULL,
  `reimbursement_rate` DECIMAL(10,2) DEFAULT 0.00,
  `reimbursement_amount` DECIMAL(10,2) DEFAULT 0.00,
  `is_reimbursed` TINYINT(1) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_date` (`trip_date`),
  INDEX `idx_reimbursed` (`is_reimbursed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mileage Stops
CREATE TABLE IF NOT EXISTS `mileage_stops` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `mileage_log_id` INT NOT NULL,
  `stop_order` INT NOT NULL,
  `location` VARCHAR(500) NOT NULL,
  `distance_from_previous_km` DECIMAL(10,2) DEFAULT 0.00,
  `distance_from_previous_miles` DECIMAL(10,2) DEFAULT 0.00,
  FOREIGN KEY (`mileage_log_id`) REFERENCES `mileage_logs`(`id`) ON DELETE CASCADE,
  INDEX `idx_log` (`mileage_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refunds
CREATE TABLE IF NOT EXISTS `refunds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `refunded_by` INT NOT NULL,
  `refund_type` ENUM('refund', 'credit', 'exchange') DEFAULT 'refund',
  `original_amount` DECIMAL(10,2) NOT NULL,
  `refund_amount` DECIMAL(10,2) NOT NULL,
  `credit_amount` DECIMAL(10,2) DEFAULT 0.00,
  `exchange_session_id` INT DEFAULT NULL,
  `refund_reason` TEXT DEFAULT NULL,
  `stripe_refund_id` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
  `refund_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`refunded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`exchange_session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
  INDEX `idx_booking` (`booking_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_date` (`refund_date`),
  INDEX `idx_type` (`refund_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Credits (for credit-based refunds)
CREATE TABLE IF NOT EXISTS `user_credits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `credit_amount` DECIMAL(10,2) NOT NULL,
  `credit_source` ENUM('refund', 'bonus', 'adjustment') DEFAULT 'refund',
  `refund_id` INT DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `used_amount` DECIMAL(10,2) DEFAULT 0.00,
  `remaining_amount` DECIMAL(10,2) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`refund_id`) REFERENCES `refunds`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expiry` (`expiry_date`),
  INDEX `idx_remaining` (`remaining_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exercises
CREATE TABLE IF NOT EXISTS `exercises` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `target_area` VARCHAR(100) DEFAULT NULL,
  `video_link` VARCHAR(500) DEFAULT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PLAN CATEGORY TABLES
-- =========================================================

-- Workout Plan Categories
CREATE TABLE IF NOT EXISTS `workout_plan_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition Plan Categories
CREATE TABLE IF NOT EXISTS `nutrition_plan_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Practice Plan Categories
CREATE TABLE IF NOT EXISTS `practice_plan_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout Templates
CREATE TABLE IF NOT EXISTS `workout_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `category_id` INT DEFAULT NULL,
  `created_by_coach_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `workout_plan_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by_coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_coach` (`created_by_coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout Template Items
CREATE TABLE IF NOT EXISTS `workout_template_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `exercise_id` INT NOT NULL,
  `sets` INT DEFAULT 3,
  `reps` INT DEFAULT 10,
  `order_index` INT DEFAULT 0,
  FOREIGN KEY (`template_id`) REFERENCES `workout_templates`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`exercise_id`) REFERENCES `exercises`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Workouts
CREATE TABLE IF NOT EXISTS `user_workouts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `coach_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `assigned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Workout Items
CREATE TABLE IF NOT EXISTS `user_workout_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_workout_id` INT NOT NULL,
  `exercise_id` INT NOT NULL,
  `sets` INT DEFAULT 3,
  `reps` INT DEFAULT 10,
  `weight` DECIMAL(10,2) DEFAULT NULL,
  `is_completed` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`user_workout_id`) REFERENCES `user_workouts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`exercise_id`) REFERENCES `exercises`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foods
CREATE TABLE IF NOT EXISTS `foods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(100) DEFAULT NULL,
  `recipe` TEXT,
  `calories` INT DEFAULT NULL,
  `protein` DECIMAL(10,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition Templates
CREATE TABLE IF NOT EXISTS `nutrition_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `category_id` INT DEFAULT NULL,
  `created_by_coach_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `nutrition_plan_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by_coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_coach` (`created_by_coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition Template Items
CREATE TABLE IF NOT EXISTS `nutrition_template_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `food_id` INT NOT NULL,
  `meal_type` VARCHAR(50) DEFAULT NULL,
  `default_portion` VARCHAR(100) DEFAULT NULL,
  FOREIGN KEY (`template_id`) REFERENCES `nutrition_templates`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`food_id`) REFERENCES `foods`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Templates
CREATE TABLE IF NOT EXISTS `session_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `session_type` VARCHAR(100) DEFAULT NULL,
  `age_group` VARCHAR(50) DEFAULT NULL,
  `description` TEXT,
  `session_plan` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Videos
CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uploader_id` INT NOT NULL,
  `assigned_to_user_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `video_type` VARCHAR(50) DEFAULT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `review_requested` TINYINT(1) DEFAULT 0,
  `reviewed` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`uploader_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_assigned` (`assigned_to_user_id`),
  INDEX `idx_review` (`review_requested`, `reviewed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete Teams
CREATE TABLE IF NOT EXISTS `athlete_teams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `team_name` VARCHAR(255) NOT NULL,
  `season_year` VARCHAR(20) NOT NULL,
  `season_type` VARCHAR(50) DEFAULT NULL,
  `season` VARCHAR(100) DEFAULT NULL,
  `skill_level_id` INT DEFAULT NULL,
  `is_current` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_level_id`) REFERENCES `skill_levels`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_current` (`user_id`, `is_current`),
  INDEX `idx_skill` (`skill_level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete Stats
CREATE TABLE IF NOT EXISTS `athlete_stats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `team_id` INT NOT NULL,
  `games_played` INT DEFAULT 0,
  `goals` INT DEFAULT 0,
  `assists` INT DEFAULT 0,
  `points` INT DEFAULT 0,
  `penalties` INT DEFAULT 0,
  `penalty_minutes` INT DEFAULT 0,
  `shots` INT DEFAULT 0,
  `shooting_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `plus_minus` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`team_id`) REFERENCES `athlete_teams`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_team` (`user_id`, `team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Testing Results
CREATE TABLE IF NOT EXISTS `testing_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `test_name` VARCHAR(255) NOT NULL,
  `weight` DECIMAL(10,2) DEFAULT NULL,
  `reps` INT DEFAULT NULL,
  `sets` INT DEFAULT NULL,
  `time_result` VARCHAR(50) DEFAULT NULL,
  `test_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_date` (`user_id`, `test_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- GOALS AND PROGRESS TRACKING SYSTEM
-- =========================================================

-- Goals Table
CREATE TABLE IF NOT EXISTS `goals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `athlete_id` INT NOT NULL,
  `created_by` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `category` VARCHAR(100) DEFAULT NULL,
  `tags` VARCHAR(255) DEFAULT NULL,
  `target_date` DATE DEFAULT NULL,
  `status` ENUM('active', 'completed', 'archived') DEFAULT 'active',
  `completion_percentage` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_athlete` (`athlete_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal Steps
CREATE TABLE IF NOT EXISTS `goal_steps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `goal_id` INT NOT NULL,
  `step_order` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `is_completed` TINYINT(1) DEFAULT 0,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_goal` (`goal_id`),
  INDEX `idx_order` (`step_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal Progress Logs
CREATE TABLE IF NOT EXISTS `goal_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `goal_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `progress_note` TEXT,
  `progress_percentage` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_goal` (`goal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal History (Archive)
CREATE TABLE IF NOT EXISTS `goal_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `goal_id` INT NOT NULL,
  `action` ENUM('created', 'updated', 'completed', 'archived', 'step_completed') NOT NULL,
  `user_id` INT NOT NULL,
  `changes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_goal` (`goal_id`),
  INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- EVALUATION PLATFORM - TYPE 1 (GOAL-BASED INTERACTIVE)
-- =========================================================

-- Goal-Based Evaluations
CREATE TABLE IF NOT EXISTS `goal_evaluations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `athlete_id` INT NOT NULL,
  `created_by` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `share_token` VARCHAR(32) UNIQUE DEFAULT NULL,
  `is_public` TINYINT(1) DEFAULT 0,
  `status` ENUM('active', 'completed', 'archived') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_athlete` (`athlete_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_share_token` (`share_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal Evaluation Steps
CREATE TABLE IF NOT EXISTS `goal_eval_steps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `goal_eval_id` INT NOT NULL,
  `step_order` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `is_completed` TINYINT(1) DEFAULT 0,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_by` INT DEFAULT NULL,
  `needs_approval` TINYINT(1) DEFAULT 0,
  `is_approved` TINYINT(1) DEFAULT 0,
  `approved_by` INT DEFAULT NULL,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`goal_eval_id`) REFERENCES `goal_evaluations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_goal_eval` (`goal_eval_id`),
  INDEX `idx_order` (`step_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal Evaluation Progress
CREATE TABLE IF NOT EXISTS `goal_eval_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `goal_eval_step_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `progress_note` TEXT,
  `media_url` VARCHAR(500) DEFAULT NULL,
  `media_type` ENUM('image', 'video') DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`goal_eval_step_id`) REFERENCES `goal_eval_steps`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_step` (`goal_eval_step_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal Evaluation Approvals
CREATE TABLE IF NOT EXISTS `goal_eval_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `goal_eval_step_id` INT NOT NULL,
  `requested_by` INT NOT NULL,
  `approved_by` INT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `approval_note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`goal_eval_step_id`) REFERENCES `goal_eval_steps`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_step` (`goal_eval_step_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- EVALUATION PLATFORM - TYPE 2 (SKILLS & ABILITIES)
-- =========================================================

-- Evaluation Categories (Admin-defined)
CREATE TABLE IF NOT EXISTS `eval_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `display_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_order` (`display_order`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation Skills (Admin-defined)
CREATE TABLE IF NOT EXISTS `eval_skills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `criteria` TEXT,
  `display_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `eval_categories`(`id`) ON DELETE CASCADE,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_order` (`display_order`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete Evaluations
CREATE TABLE IF NOT EXISTS `athlete_evaluations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `athlete_id` INT NOT NULL,
  `created_by` INT NOT NULL,
  `evaluation_date` DATE NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `share_token` VARCHAR(32) UNIQUE DEFAULT NULL,
  `is_public` TINYINT(1) DEFAULT 0,
  `status` ENUM('draft', 'completed', 'archived') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_athlete` (`athlete_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_share_token` (`share_token`),
  INDEX `idx_eval_date` (`evaluation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team Evaluations
CREATE TABLE IF NOT EXISTS `team_evaluations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `team_id` INT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `evaluation_date` DATE NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `status` ENUM('draft', 'completed', 'archived') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`team_id`) REFERENCES `athlete_teams`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_team` (`team_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation Scores
CREATE TABLE IF NOT EXISTS `evaluation_scores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `evaluation_id` INT NOT NULL,
  `skill_id` INT NOT NULL,
  `score` INT DEFAULT NULL CHECK (score >= 1 AND score <= 10),
  `public_notes` TEXT,
  `private_notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`evaluation_id`) REFERENCES `athlete_evaluations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE CASCADE,
  INDEX `idx_evaluation` (`evaluation_id`),
  INDEX `idx_skill` (`skill_id`),
  UNIQUE KEY `unique_eval_skill` (`evaluation_id`, `skill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation Media
CREATE TABLE IF NOT EXISTS `evaluation_media` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `evaluation_id` INT DEFAULT NULL,
  `score_id` INT DEFAULT NULL,
  `media_url` VARCHAR(500) NOT NULL,
  `media_type` ENUM('image', 'video') NOT NULL,
  `caption` TEXT,
  `uploaded_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`evaluation_id`) REFERENCES `athlete_evaluations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`score_id`) REFERENCES `evaluation_scores`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_evaluation` (`evaluation_id`),
  INDEX `idx_score` (`score_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete Notes
CREATE TABLE IF NOT EXISTS `athlete_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `coach_id` INT NOT NULL,
  `note_content` TEXT NOT NULL,
  `is_private` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workouts (Generic)
CREATE TABLE IF NOT EXISTS `workouts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `coach_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `link` VARCHAR(500) DEFAULT NULL,
  `is_completed` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrition Plans
CREATE TABLE IF NOT EXISTS `nutrition_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `coach_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Settings
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Logs
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `log_data` TEXT,
  `template_type` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('sent', 'failed') DEFAULT 'sent',
  `error_message` TEXT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_recipient` (`recipient`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Logs
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_type` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `user_id` INT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Video Notes
CREATE TABLE IF NOT EXISTS `video_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `video_id` INT NOT NULL,
  `coach_id` INT NOT NULL,
  `note_content` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_video` (`video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(500) DEFAULT NULL,
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_read` (`user_id`, `read_status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- NEW TABLES FOR DRILL LIBRARY & PRACTICE PLANS
-- =========================================================

-- Drill Categories
CREATE TABLE IF NOT EXISTS `drill_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drills
CREATE TABLE IF NOT EXISTS `drills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `category_id` INT DEFAULT NULL,
  `diagram_data` TEXT,
  `duration_minutes` INT DEFAULT NULL,
  `skill_level` ENUM('beginner', 'intermediate', 'advanced', 'all') DEFAULT 'all',
  `age_group` VARCHAR(50) DEFAULT NULL,
  `equipment_needed` TEXT,
  `coaching_points` TEXT,
  `video_url` VARCHAR(500) DEFAULT NULL,
  `imported_from_ihs` TINYINT(1) DEFAULT 0,
  `ihs_drill_id` VARCHAR(100) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `drill_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_skill` (`skill_level`),
  INDEX `idx_ihs` (`imported_from_ihs`, `ihs_drill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drill Tags
CREATE TABLE IF NOT EXISTS `drill_tags` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `drill_id` INT NOT NULL,
  `tag` VARCHAR(50) NOT NULL,
  FOREIGN KEY (`drill_id`) REFERENCES `drills`(`id`) ON DELETE CASCADE,
  INDEX `idx_drill` (`drill_id`),
  INDEX `idx_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Practice Plans
CREATE TABLE IF NOT EXISTS `practice_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `total_duration` INT DEFAULT 60,
  `age_group` VARCHAR(50) DEFAULT NULL,
  `focus_area` VARCHAR(100) DEFAULT NULL,
  `category_id` INT DEFAULT NULL,
  `share_token` VARCHAR(64) UNIQUE DEFAULT NULL,
  `is_public` TINYINT(1) DEFAULT 0,
  `imported_from_ihs` TINYINT(1) DEFAULT 0,
  `ihs_plan_id` VARCHAR(100) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `practice_plan_categories`(`id`) ON DELETE SET NULL,
  INDEX `idx_share_token` (`share_token`),
  INDEX `idx_public` (`is_public`),
  INDEX `idx_ihs` (`imported_from_ihs`, `ihs_plan_id`),
  INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Practice Plan Drills
CREATE TABLE IF NOT EXISTS `practice_plan_drills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `drill_id` INT NOT NULL,
  `order_index` INT NOT NULL DEFAULT 0,
  `duration_minutes` INT DEFAULT NULL,
  `notes` TEXT,
  FOREIGN KEY (`plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`drill_id`) REFERENCES `drills`(`id`) ON DELETE CASCADE,
  INDEX `idx_plan_order` (`plan_id`, `order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Practice Plan Shares (Track who accessed shared plans)
CREATE TABLE IF NOT EXISTS `practice_plan_shares` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `accessed_by_ip` VARCHAR(45) DEFAULT NULL,
  `accessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE CASCADE,
  INDEX `idx_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PERMISSIONS SYSTEM
-- =========================================================

-- Permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `permission_key` VARCHAR(100) NOT NULL UNIQUE,
  `permission_name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `category` VARCHAR(100) DEFAULT 'general',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_key` (`permission_key`),
  INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role Permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role` ENUM('athlete', 'coach', 'coach_plus', 'admin', 'parent') NOT NULL,
  `permission_id` INT NOT NULL,
  `granted` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_role_permission` (`role`, `permission_id`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Permissions (Override role permissions for specific users)
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  `granted` TINYINT(1) DEFAULT 1,
  `granted_by` INT DEFAULT NULL,
  `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_user_permission` (`user_id`, `permission_id`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- INSERT DEFAULT PERMISSIONS
-- =========================================================

INSERT IGNORE INTO `permissions` (`permission_key`, `permission_name`, `description`, `category`) VALUES
('view_dashboard', 'View Dashboard', 'Access main dashboard', 'general'),
('view_stats', 'View Statistics', 'View performance statistics', 'stats'),
('edit_own_stats', 'Edit Own Statistics', 'Edit personal statistics', 'stats'),
('view_schedule', 'View Schedule', 'View and book sessions', 'schedule'),
('book_sessions', 'Book Sessions', 'Book training sessions', 'schedule'),
('cancel_bookings', 'Cancel Bookings', 'Cancel own bookings', 'schedule'),
('view_workouts', 'View Workouts', 'View assigned workouts', 'training'),
('create_workouts', 'Create Workouts', 'Create workout plans', 'training'),
('assign_workouts', 'Assign Workouts', 'Assign workouts to athletes', 'training'),
('view_nutrition', 'View Nutrition', 'View nutrition plans', 'nutrition'),
('create_nutrition', 'Create Nutrition Plans', 'Create nutrition plans', 'nutrition'),
('assign_nutrition', 'Assign Nutrition Plans', 'Assign nutrition plans to athletes', 'nutrition'),
('view_videos', 'View Videos', 'Access video library', 'media'),
('upload_videos', 'Upload Videos', 'Upload videos', 'media'),
('delete_videos', 'Delete Videos', 'Delete videos', 'media'),
('view_drills', 'View Drills', 'Access drill library', 'drills'),
('create_drills', 'Create Drills', 'Create and edit drills', 'drills'),
('delete_drills', 'Delete Drills', 'Delete drills', 'drills'),
('manage_drill_categories', 'Manage Drill Categories', 'Create and manage drill categories', 'drills'),
('view_practice_plans', 'View Practice Plans', 'View practice plans', 'practice'),
('create_practice_plans', 'Create Practice Plans', 'Create practice plans', 'practice'),
('share_practice_plans', 'Share Practice Plans', 'Generate shareable links for practice plans', 'practice'),
('delete_practice_plans', 'Delete Practice Plans', 'Delete practice plans', 'practice'),
('import_from_ihs', 'Import from IHS', 'Import drills and plans from IHS Hockey', 'integration'),
('manage_athletes', 'Manage Athletes', 'View and manage athlete roster', 'management'),
('create_athletes', 'Create Athlete Accounts', 'Create new athlete accounts', 'management'),
('edit_athlete_profiles', 'Edit Athlete Profiles', 'Edit athlete information', 'management'),
('view_athlete_notes', 'View Athlete Notes', 'View coaching notes', 'management'),
('create_athlete_notes', 'Create Athlete Notes', 'Add coaching notes', 'management'),
('manage_sessions', 'Manage Sessions', 'Create and edit sessions', 'admin'),
('manage_locations', 'Manage Locations', 'Add and edit locations', 'admin'),
('manage_session_types', 'Manage Session Types', 'Add and edit session types', 'admin'),
('manage_discounts', 'Manage Discounts', 'Create and manage discount codes', 'admin'),
('manage_roles', 'Manage User Roles', 'Change user roles', 'admin'),
('manage_permissions', 'Manage Permissions', 'Assign permissions to roles and users', 'admin'),
('view_system_settings', 'View System Settings', 'Access system configuration', 'admin'),
('edit_system_settings', 'Edit System Settings', 'Modify system configuration', 'admin');

-- =========================================================
-- ASSIGN DEFAULT ROLE PERMISSIONS
-- =========================================================

-- Athlete permissions
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`, `granted`)
SELECT 'athlete', id, 1 FROM permissions WHERE permission_key IN (
  'view_dashboard', 'view_stats', 'edit_own_stats', 'view_schedule', 'book_sessions', 
  'cancel_bookings', 'view_workouts', 'view_nutrition', 'view_videos', 'view_drills', 'view_practice_plans'
);

-- Coach permissions (all athlete permissions plus coach-specific)
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`, `granted`)
SELECT 'coach', id, 1 FROM permissions WHERE permission_key IN (
  'view_dashboard', 'view_stats', 'view_schedule', 'view_workouts', 'create_workouts', 'assign_workouts',
  'view_nutrition', 'create_nutrition', 'assign_nutrition', 'view_videos', 'upload_videos',
  'view_drills', 'create_drills', 'view_practice_plans', 'create_practice_plans', 'share_practice_plans',
  'manage_athletes', 'create_athletes', 'view_athlete_notes', 'create_athlete_notes'
);

-- Coach+ permissions (all coach permissions plus extended)
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`, `granted`)
SELECT 'coach_plus', id, 1 FROM permissions WHERE permission_key IN (
  'view_dashboard', 'view_stats', 'view_schedule', 'view_workouts', 'create_workouts', 'assign_workouts',
  'view_nutrition', 'create_nutrition', 'assign_nutrition', 'view_videos', 'upload_videos', 'delete_videos',
  'view_drills', 'create_drills', 'delete_drills', 'view_practice_plans', 'create_practice_plans', 
  'share_practice_plans', 'delete_practice_plans', 'import_from_ihs',
  'manage_athletes', 'create_athletes', 'edit_athlete_profiles', 'view_athlete_notes', 'create_athlete_notes',
  'manage_sessions'
);

-- Admin permissions (all permissions)
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`, `granted`)
SELECT 'admin', id, 1 FROM permissions;

-- =========================================================
-- DEFAULT AGE GROUPS AND SKILL LEVELS
-- =========================================================

INSERT IGNORE INTO `age_groups` (`name`, `min_age`, `max_age`, `description`, `display_order`) VALUES
('Mite (U8)', 5, 8, 'Under 8 years old', 1),
('Squirt (U10)', 9, 10, 'Under 10 years old', 2),
('Peewee (U12)', 11, 12, 'Under 12 years old', 3),
('Bantam (U14)', 13, 14, 'Under 14 years old', 4),
('Midget (U16)', 15, 16, 'Under 16 years old', 5),
('Midget (U18)', 17, 18, 'Under 18 years old', 6),
('Junior (U20)', 19, 20, 'Under 20 years old', 7),
('Adult (18+)', 18, 99, '18 and older', 8);

INSERT IGNORE INTO `skill_levels` (`name`, `description`, `display_order`) VALUES
('Beginner', 'New to hockey or learning fundamentals', 1),
('Intermediate', 'Comfortable with basics, developing skills', 2),
('Advanced', 'High level of skill and hockey IQ', 3),
('Elite', 'Competitive/travel level', 4),
('Pro', 'Professional or aspiring professional', 5);

-- =========================================================
-- DEFAULT SYSTEM SETTINGS
-- =========================================================

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('tax_rate', '13.00'),
('tax_name', 'HST'),
('currency', 'CAD');

-- =========================================================
-- PARENT ROLE PERMISSIONS
-- =========================================================

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`, `granted`)
SELECT 'parent', id, 1 FROM permissions WHERE permission_key IN (
  'view_dashboard', 'view_stats', 'view_schedule', 'book_sessions', 
  'cancel_bookings', 'view_workouts', 'view_nutrition', 'view_videos', 
  'view_drills', 'view_practice_plans', 'manage_athletes'
);

-- =========================================================
-- DEFAULT EXPENSE CATEGORIES
-- =========================================================

INSERT IGNORE INTO `expense_categories` (`name`, `description`, `display_order`) VALUES
('Ice Rental', 'Arena and ice time rental fees', 1),
('Equipment', 'Training equipment, pucks, cones, etc.', 2),
('Coaching Fees', 'Guest coaches and training staff', 3),
('Utilities', 'Electricity, water, internet', 4),
('Marketing', 'Advertising and promotional materials', 5),
('Office Supplies', 'Paper, pens, printer ink, etc.', 6),
('Insurance', 'Liability and business insurance', 7),
('Professional Services', 'Legal, accounting, consulting', 8),
('Travel', 'Transportation and lodging for events', 9),
('Miscellaneous', 'Other business expenses', 10);

-- =========================================================
-- ADDITIONAL SYSTEM SETTINGS FOR NEW FEATURES
-- =========================================================

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('nextcloud_url', ''),
('nextcloud_username', ''),
('nextcloud_password', ''),
('nextcloud_receipt_folder', '/receipts'),
('nextcloud_scan_subfolders', '1'),
('nextcloud_webdav_path', '/remote.php/dav/files/'),
('nextcloud_ocr_enabled', '0'),
('google_maps_api_key', ''),
('mileage_rate_per_km', '0.68'),
('mileage_rate_per_mile', '1.09'),
('receipt_scan_enabled', '0'),
('credit_expiry_days', '365'),
('site_name', 'Crash Hockey'),
('timezone', 'America/Toronto'),
('language', 'en'),
('session_timeout_minutes', '60'),
('maintenance_mode', '0'),
('debug_mode', '0');

-- =========================================================
-- DEFAULT THEME COLORS
-- =========================================================

INSERT IGNORE INTO `theme_settings` (`setting_name`, `setting_value`) VALUES
('primary_color', '#7000a4'),
('secondary_color', '#c0c0c0'),
('background_color', '#06080b'),
('card_background_color', '#0d1117'),
('text_color', '#ffffff'),
('text_muted_color', '#94a3b8'),
('border_color', '#1e293b'),
('sidebar_color', '#020305'),
('button_hover_color', '#a78bfa'),
('success_color', '#22c55e'),
('error_color', '#ef4444'),
('warning_color', '#f59e0b');

-- =========================================================
-- DEFAULT PLAN CATEGORIES
-- =========================================================

-- Default Workout Plan Categories
INSERT IGNORE INTO `workout_plan_categories` (`name`, `description`, `display_order`) VALUES
('Strength', 'Focus on building muscle strength and power', 1),
('Speed', 'Improve skating speed and acceleration', 2),
('Stamina', 'Build cardiovascular endurance', 3),
('Flexibility', 'Increase range of motion and prevent injuries', 4),
('Endurance', 'Long-term sustained performance', 5),
('Recovery', 'Active recovery and regeneration', 6);

-- Default Nutrition Plan Categories
INSERT IGNORE INTO `nutrition_plan_categories` (`name`, `description`, `display_order`) VALUES
('Bulking', 'Increase muscle mass and body weight', 1),
('Cutting', 'Reduce body fat while maintaining muscle', 2),
('Maintenance', 'Maintain current weight and composition', 3),
('Performance', 'Optimize game day nutrition', 4),
('Recovery', 'Post-training nutrition for recovery', 5),
('Vegetarian', 'Plant-based nutrition plans', 6);

-- Default Practice Plan Categories
INSERT IGNORE INTO `practice_plan_categories` (`name`, `description`, `display_order`) VALUES
('Offense', 'Attacking strategies and scoring', 1),
('Defense', 'Defensive positioning and techniques', 2),
('Special Teams', 'Power play and penalty kill', 3),
('Conditioning', 'Physical fitness and endurance', 4),
('Skills', 'Fundamental skill development', 5),
('Game Prep', 'Pre-game preparation and tactics', 6);

-- =========================================================
-- TEAM COACHES ROLE TABLES
-- =========================================================

-- Seasons Table
CREATE TABLE IF NOT EXISTS `seasons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_active` BOOLEAN DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_active` (`is_active`),
  INDEX `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team Coach Assignments Table
CREATE TABLE IF NOT EXISTS `team_coach_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `coach_id` INT NOT NULL,
  `team_id` INT NOT NULL,
  `season_id` INT NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`team_id`) REFERENCES `athlete_teams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE CASCADE,
  INDEX `idx_coach` (`coach_id`),
  INDEX `idx_team` (`team_id`),
  INDEX `idx_season` (`season_id`),
  UNIQUE KEY `unique_coach_team_season` (`coach_id`, `team_id`, `season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PHASE 3 FEATURES
-- =========================================================

-- Reports Table
CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `report_type` VARCHAR(100) NOT NULL,
  `generated_by` INT NOT NULL,
  `parameters` TEXT DEFAULT NULL COMMENT 'JSON encoded parameters',
  `format` ENUM('pdf', 'csv') NOT NULL DEFAULT 'pdf',
  `file_path` VARCHAR(500) DEFAULT NULL,
  `share_token` VARCHAR(64) DEFAULT NULL UNIQUE,
  `scheduled` BOOLEAN DEFAULT 0,
  `schedule_frequency` ENUM('daily', 'weekly', 'monthly') DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_generated_by` (`generated_by`),
  INDEX `idx_report_type` (`report_type`),
  INDEX `idx_share_token` (`share_token`),
  INDEX `idx_scheduled` (`scheduled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report Schedules Table
CREATE TABLE IF NOT EXISTS `report_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `report_type` VARCHAR(100) NOT NULL,
  `parameters` TEXT DEFAULT NULL COMMENT 'JSON encoded parameters',
  `frequency` ENUM('daily', 'weekly', 'monthly') NOT NULL,
  `format` ENUM('pdf', 'csv') NOT NULL DEFAULT 'pdf',
  `email_recipients` TEXT DEFAULT NULL COMMENT 'Comma-separated email addresses',
  `last_run` TIMESTAMP NULL DEFAULT NULL,
  `next_run` TIMESTAMP NULL DEFAULT NULL,
  `is_active` BOOLEAN DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_next_run` (`next_run`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Scans Table
CREATE TABLE IF NOT EXISTS `security_scans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scan_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `vulnerabilities_found` INT DEFAULT 0,
  `details` LONGTEXT DEFAULT NULL COMMENT 'JSON encoded scan results',
  `notified_admins` BOOLEAN DEFAULT 0,
  `scan_status` ENUM('running', 'completed', 'failed') DEFAULT 'completed',
  `scan_duration` INT DEFAULT NULL COMMENT 'Duration in seconds',
  INDEX `idx_scan_date` (`scan_date`),
  INDEX `idx_vulnerabilities` (`vulnerabilities_found`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database Maintenance Logs Table
CREATE TABLE IF NOT EXISTS `database_maintenance_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `run_by` INT NOT NULL,
  `action_type` VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(100) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `status` ENUM('success', 'warning', 'error') DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`run_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_run_by` (`run_by`),
  INDEX `idx_action_type` (`action_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PHASE 4 FEATURES
-- =========================================================

-- Cron Jobs Table
CREATE TABLE IF NOT EXISTS `cron_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `command` VARCHAR(500) NOT NULL,
  `schedule` VARCHAR(100) NOT NULL COMMENT 'Cron expression (e.g., 0 2 * * *)',
  `type` ENUM('report', 'admin', 'backup', 'maintenance') NOT NULL DEFAULT 'admin',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `parameters` TEXT DEFAULT NULL COMMENT 'JSON encoded parameters',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_run` TIMESTAMP NULL DEFAULT NULL,
  `next_run` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_type` (`type`),
  INDEX `idx_next_run` (`next_run`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup Jobs Table
CREATE TABLE IF NOT EXISTS `backup_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `schedule` VARCHAR(100) NOT NULL COMMENT 'Cron expression',
  `backup_type` ENUM('manual', 'scheduled') NOT NULL DEFAULT 'scheduled',
  `destination_type` ENUM('nextcloud', 'smb', 'both') NOT NULL DEFAULT 'nextcloud',
  `nextcloud_folder` VARCHAR(500) DEFAULT '/CrashHockey/Backups/',
  `smb_path` VARCHAR(500) DEFAULT NULL,
  `smb_username` VARCHAR(255) DEFAULT NULL,
  `smb_password` TEXT DEFAULT NULL COMMENT 'AES-256 encrypted',
  `smb_domain` VARCHAR(255) DEFAULT NULL,
  `retention_days` INT DEFAULT 30,
  `last_backup` TIMESTAMP NULL DEFAULT NULL,
  `next_backup` TIMESTAMP NULL DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_next_backup` (`next_backup`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup History Table
CREATE TABLE IF NOT EXISTS `backup_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `backup_job_id` INT DEFAULT NULL,
  `filename` VARCHAR(500) NOT NULL,
  `file_size` BIGINT DEFAULT NULL COMMENT 'Size in bytes',
  `destination` VARCHAR(500) NOT NULL,
  `backup_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
  `error_message` TEXT DEFAULT NULL,
  FOREIGN KEY (`backup_job_id`) REFERENCES `backup_jobs`(`id`) ON DELETE SET NULL,
  INDEX `idx_backup_job` (`backup_job_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_backup_date` (`backup_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PHASE 5 TABLES
-- =========================================================

-- Audit Logs Table
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `action_type` VARCHAR(100) NOT NULL COMMENT 'INSERT, UPDATE, DELETE',
  `table_name` VARCHAR(100) NOT NULL,
  `record_id` INT NOT NULL,
  `old_values` TEXT DEFAULT NULL COMMENT 'JSON encoded old values',
  `new_values` TEXT DEFAULT NULL COMMENT 'JSON encoded new values',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_action` (`action_type`),
  INDEX `idx_table_record` (`table_name`, `record_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Notifications Table (Global Maintenance Notifications)
CREATE TABLE IF NOT EXISTS `system_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `notification_type` ENUM('maintenance', 'update', 'alert') NOT NULL DEFAULT 'maintenance',
  `start_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `end_time` TIMESTAMP NULL DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_active` (`is_active`),
  INDEX `idx_times` (`start_time`, `end_time`),
  INDEX `idx_type` (`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Theme Settings
CREATE TABLE IF NOT EXISTS `theme_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_name` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL COMMENT 'Hex color code or text value',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT DEFAULT NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training Programs Table
CREATE TABLE IF NOT EXISTS `training_programs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `tags` VARCHAR(500) DEFAULT NULL COMMENT 'Comma-separated tags',
  `image_url` VARCHAR(500) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT DEFAULT NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature Versions (for intelligent feature import and migration tracking)
CREATE TABLE IF NOT EXISTS `feature_versions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `feature_name` VARCHAR(255) NOT NULL,
  `version` VARCHAR(50) NOT NULL,
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `applied_by` INT DEFAULT NULL,
  `database_changes` JSON DEFAULT NULL COMMENT 'Tracks table/column renames and migrations',
  `file_changes` JSON DEFAULT NULL COMMENT 'Tracks moved/renamed files',
  `manifest` JSON DEFAULT NULL COMMENT 'Complete manifest of the feature version',
  FOREIGN KEY (`applied_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_feature_version` (`feature_name`, `version`),
  INDEX `idx_feature_name` (`feature_name`),
  INDEX `idx_applied_at` (`applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
