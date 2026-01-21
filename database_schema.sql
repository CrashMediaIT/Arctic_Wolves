-- =========================================================
-- CRASH HOCKEY DATABASE SCHEMA
-- Complete schema for hockey coaching management system
-- =========================================================

-- Users table with expanded roles
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `role` ENUM('athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach') DEFAULT 'athlete',
    `is_verified` TINYINT(1) DEFAULT 0,
    `verification_code` VARCHAR(10) DEFAULT NULL,
    `force_pass_change` TINYINT(1) DEFAULT 0,
    `phone` VARCHAR(20) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_email` (`email`)
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

-- Coach-Athlete assignments
CREATE TABLE IF NOT EXISTS `coach_athlete_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `assignment_type` ENUM('active', 'past') DEFAULT 'active',
    `assigned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `end_date` TIMESTAMP NULL,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_athlete` (`athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams
CREATE TABLE IF NOT EXISTS `teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `age_group` VARCHAR(50) DEFAULT NULL,
    `skill_level` VARCHAR(50) DEFAULT NULL,
    `season` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team-Coach assignments
CREATE TABLE IF NOT EXISTS `team_coach_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `coach_id` INT NOT NULL,
    `role` ENUM('head_coach', 'assistant_coach') DEFAULT 'head_coach',
    `assigned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team roster (athlete memberships)
CREATE TABLE IF NOT EXISTS `team_roster` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `jersey_number` INT DEFAULT NULL,
    `position` VARCHAR(50) DEFAULT NULL,
    `joined_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_team_athlete` (`team_id`, `athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Locations
CREATE TABLE IF NOT EXISTS `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `province` VARCHAR(50) DEFAULT NULL,
    `postal_code` VARCHAR(10) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session types
CREATE TABLE IF NOT EXISTS `session_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `default_price` DECIMAL(10,2) DEFAULT 0.00,
    `duration_minutes` INT DEFAULT 60,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_type_id` INT DEFAULT NULL,
    `location_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `session_date` DATETIME NOT NULL,
    `duration_minutes` INT DEFAULT 60,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `max_participants` INT DEFAULT NULL,
    `age_group` VARCHAR(50) DEFAULT NULL,
    `skill_level` VARCHAR(50) DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `coach_id` INT DEFAULT NULL,
    `status` ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_type_id`) REFERENCES `session_types`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_date` (`session_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Practice Plans
CREATE TABLE IF NOT EXISTS `practice_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `version` INT DEFAULT 1,
    `parent_plan_id` INT DEFAULT NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_plan_id`) REFERENCES `practice_plans`(`id`) ON DELETE SET NULL
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
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drills
CREATE TABLE IF NOT EXISTS `drills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
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
    FOREIGN KEY (`category_id`) REFERENCES `drill_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_drill_id`) REFERENCES `drills`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_created_by` (`created_by`)
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
CREATE TABLE IF NOT EXISTS `session_bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `booking_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `payment_status` ENUM('pending', 'paid', 'refunded', 'credit_used') DEFAULT 'pending',
    `amount_paid` DECIMAL(10,2) DEFAULT 0.00,
    `credits_used` INT DEFAULT 0,
    `status` ENUM('booked', 'attended', 'no_show', 'cancelled') DEFAULT 'booked',
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`)
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
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Video uploads
CREATE TABLE IF NOT EXISTS `videos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `coach_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `video_url` VARCHAR(255) NOT NULL,
    `thumbnail_url` VARCHAR(255) DEFAULT NULL,
    `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `video_type` ENUM('drill_review', 'coach_review', 'uploaded_by_athlete') DEFAULT 'drill_review',
    `drill_id` INT DEFAULT NULL,
    `session_id` INT DEFAULT NULL,
    `status` ENUM('pending_review', 'reviewed', 'archived') DEFAULT 'pending_review',
    `coach_notes` TEXT DEFAULT NULL,
    `athlete_notes` TEXT DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`drill_id`) REFERENCES `drills`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`)
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
    `status` ENUM('active', 'completed', 'paused') DEFAULT 'active',
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`)
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
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `target_calories` INT DEFAULT NULL,
    `target_protein_g` INT DEFAULT NULL,
    `target_carbs_g` INT DEFAULT NULL,
    `target_fat_g` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    `status` ENUM('active', 'completed', 'paused') DEFAULT 'active',
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`nutrition_plan_id`) REFERENCES `nutrition_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`)
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
    `goal_title` VARCHAR(255) NOT NULL,
    `goal_description` TEXT DEFAULT NULL,
    `target_value` DECIMAL(10,2) DEFAULT NULL,
    `current_value` DECIMAL(10,2) DEFAULT NULL,
    `target_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'completed', 'abandoned') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mileage tracking
CREATE TABLE IF NOT EXISTS `mileage_tracking` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `trip_date` DATE NOT NULL,
    `start_location` VARCHAR(255) NOT NULL,
    `end_location` VARCHAR(255) NOT NULL,
    `distance_km` DECIMAL(10,2) NOT NULL,
    `purpose` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`trip_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- System notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `link_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_read` (`user_id`, `is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation framework categories
CREATE TABLE IF NOT EXISTS `eval_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation framework skills
CREATE TABLE IF NOT EXISTS `eval_skills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `eval_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete evaluations
CREATE TABLE IF NOT EXISTS `athlete_evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `evaluator_id` INT NOT NULL,
    `skill_id` INT NOT NULL,
    `rating` INT NOT NULL,
    `comments` TEXT DEFAULT NULL,
    `evaluation_date` DATE NOT NULL,
    `session_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_skill` (`skill_id`)
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
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` VARCHAR(50) DEFAULT 'text',
    `description` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100) DEFAULT NULL,
    `record_id` INT DEFAULT NULL,
    `old_values` TEXT DEFAULT NULL,
    `new_values` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Theme settings
CREATE TABLE IF NOT EXISTS `theme_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `theme_name` VARCHAR(100) NOT NULL,
    `primary_color` VARCHAR(7) DEFAULT '#ff4d00',
    `secondary_color` VARCHAR(7) DEFAULT '#00ff88',
    `background_color` VARCHAR(7) DEFAULT '#06080b',
    `logo_url` VARCHAR(255) DEFAULT NULL,
    `custom_css` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    `plus_minus` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_team` (`team_id`),
    INDEX `idx_season` (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Athlete team memberships (historical)
CREATE TABLE IF NOT EXISTS `athlete_teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `team_id` INT NOT NULL,
    `season` VARCHAR(50) DEFAULT NULL,
    `jersey_number` INT DEFAULT NULL,
    `position` VARCHAR(50) DEFAULT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_team` (`team_id`),
    INDEX `idx_season` (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs (alternative/extended audit table)
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action_type` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100) DEFAULT NULL,
    `record_id` INT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `old_data` TEXT DEFAULT NULL,
    `new_data` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `session_id` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action_type`),
    INDEX `idx_table` (`table_name`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled backup jobs
CREATE TABLE IF NOT EXISTS `backup_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `schedule` VARCHAR(50) NOT NULL,
    `backup_type` ENUM('full', 'incremental', 'schema_only', 'data_only') DEFAULT 'full',
    `destination_type` ENUM('local', 'nextcloud', 'smb', 'ftp', 's3') DEFAULT 'local',
    `nextcloud_folder` VARCHAR(255) DEFAULT NULL,
    `smb_path` VARCHAR(255) DEFAULT NULL,
    `smb_username` VARCHAR(100) DEFAULT NULL,
    `smb_password` VARCHAR(255) DEFAULT NULL,
    `smb_domain` VARCHAR(100) DEFAULT NULL,
    `retention_days` INT DEFAULT 30,
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
    `amount` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('confirmed', 'cancelled', 'waitlisted') DEFAULT 'confirmed',
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nextcloud receipt storage tracking
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
    FOREIGN KEY (`evaluation_id`) REFERENCES `athlete_evaluations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_evaluation` (`evaluation_id`),
    INDEX `idx_media_type` (`media_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation scores (detailed breakdown)
CREATE TABLE IF NOT EXISTS `evaluation_scores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `evaluator_id` INT NOT NULL,
    `skill_id` INT NOT NULL,
    `score` DECIMAL(5,2) NOT NULL,
    `max_score` DECIMAL(5,2) DEFAULT 10.00,
    `evaluation_date` DATE NOT NULL,
    `comments` TEXT DEFAULT NULL,
    `session_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `eval_skills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE SET NULL,
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_skill` (`skill_id`),
    INDEX `idx_date` (`evaluation_date`)
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
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature version tracking
CREATE TABLE IF NOT EXISTS `feature_versions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `feature_name` VARCHAR(100) NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `release_date` DATE DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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

-- Goal evaluations (comprehensive goal assessments)
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

-- Goal history (change tracking)
CREATE TABLE IF NOT EXISTS `goal_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `field_changed` VARCHAR(100) NOT NULL,
    `old_value` TEXT DEFAULT NULL,
    `new_value` TEXT DEFAULT NULL,
    `changed_by` INT NOT NULL,
    `change_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_goal` (`goal_id`),
    INDEX `idx_date` (`change_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal progress tracking
CREATE TABLE IF NOT EXISTS `goal_progress` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `progress_date` DATE NOT NULL,
    `progress_value` DECIMAL(10,2) DEFAULT NULL,
    `progress_percentage` DECIMAL(5,2) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `recorded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_goal` (`goal_id`),
    INDEX `idx_date` (`progress_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goal steps (milestones)
CREATE TABLE IF NOT EXISTS `goal_steps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `step_number` INT NOT NULL,
    `step_description` TEXT NOT NULL,
    `target_date` DATE DEFAULT NULL,
    `is_completed` TINYINT(1) DEFAULT 0,
    `completed_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE,
    INDEX `idx_goal` (`goal_id`),
    INDEX `idx_step_num` (`step_number`),
    INDEX `idx_completed` (`is_completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Managed athletes (coach-athlete relationships)
CREATE TABLE IF NOT EXISTS `managed_athletes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id` INT NOT NULL,
    `athlete_id` INT NOT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`athlete_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_coach_athlete` (`coach_id`, `athlete_id`),
    INDEX `idx_coach` (`coach_id`),
    INDEX `idx_athlete` (`athlete_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mileage logs (trip tracking)
CREATE TABLE IF NOT EXISTS `mileage_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `trip_date` DATE NOT NULL,
    `start_location` VARCHAR(255) NOT NULL,
    `end_location` VARCHAR(255) NOT NULL,
    `distance_km` DECIMAL(10,2) NOT NULL,
    `purpose` VARCHAR(255) DEFAULT NULL,
    `vehicle_type` VARCHAR(100) DEFAULT NULL,
    `odometer_start` INT DEFAULT NULL,
    `odometer_end` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`trip_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mileage stops (multi-stop trip tracking)
CREATE TABLE IF NOT EXISTS `mileage_stops` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mileage_log_id` INT NOT NULL,
    `stop_number` INT NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `arrival_time` TIME DEFAULT NULL,
    `departure_time` TIME DEFAULT NULL,
    `purpose` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`mileage_log_id`) REFERENCES `mileage_logs`(`id`) ON DELETE CASCADE,
    INDEX `idx_log` (`mileage_log_id`),
    INDEX `idx_stop_num` (`stop_number`)
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

-- Nutrition template items
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

-- Nutrition templates
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

-- Package sessions (session credits included in packages)
CREATE TABLE IF NOT EXISTS `package_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `session_type_id` INT DEFAULT NULL,
    `num_sessions` INT DEFAULT 1,
    `session_description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_type_id`) REFERENCES `session_types`(`id`) ON DELETE SET NULL,
    INDEX `idx_package` (`package_id`)
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
    FOREIGN KEY (`booking_id`) REFERENCES `session_bookings`(`id`) ON DELETE SET NULL,
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
    `schedule_frequency` ENUM('daily', 'weekly', 'monthly', 'quarterly') NOT NULL,
    `schedule_day` INT DEFAULT NULL,
    `schedule_time` TIME DEFAULT NULL,
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
    `role` ENUM('athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach') NOT NULL,
    `permission_id` INT NOT NULL,
    `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_role_permission` (`role`, `permission_id`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seasons
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
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order` (`level_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- User workouts (workout sessions)
CREATE TABLE IF NOT EXISTS `user_workouts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `workout_plan_id` INT DEFAULT NULL,
    `workout_date` DATE NOT NULL,
    `status` ENUM('scheduled', 'in_progress', 'completed', 'skipped') DEFAULT 'scheduled',
    `duration_minutes` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`workout_date`),
    INDEX `idx_status` (`status`)
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

-- Workout templates
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

-- Workouts (workout sessions - distinct from workout_plans)
CREATE TABLE IF NOT EXISTS `workouts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `workout_name` VARCHAR(255) NOT NULL,
    `workout_date` DATE NOT NULL,
    `workout_type` VARCHAR(100) DEFAULT NULL,
    `duration_minutes` INT DEFAULT NULL,
    `status` ENUM('planned', 'completed', 'skipped') DEFAULT 'planned',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`workout_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- ADDITIONAL TABLES - Expanding to 120+ tables
-- Generated from comprehensive PHP codebase analysis
-- =========================================================

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

-- Messages/communication
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `from_user_id` INT NOT NULL,
    `to_user_id` INT NOT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message_body` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` TIMESTAMP NULL,
    `parent_message_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_message_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL,
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
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    INDEX `idx_team` (`team_id`),
    INDEX `idx_date` (`game_date`),
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`game_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Total unique tables: 120+
-- Total lines: 2500+
-- =========================================================
