-- Row Level Security: data access policies table
-- Defines which roles can access which tables and under what conditions

CREATE TABLE IF NOT EXISTS `data_access_policies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` VARCHAR(50) NOT NULL,
    `table_name` VARCHAR(100) NOT NULL,
    `access_level` ENUM('own', 'managed', 'team', 'all') NOT NULL DEFAULT 'own',
    `owner_column` VARCHAR(100) DEFAULT 'user_id',
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_role_table` (`role`, `table_name`),
    INDEX `idx_role` (`role`),
    INDEX `idx_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default RLS policies
INSERT IGNORE INTO `data_access_policies` (`role`, `table_name`, `access_level`, `owner_column`, `description`) VALUES
('admin', '*', 'all', NULL, 'Admins have full access to all data'),
('coach', '*', 'all', NULL, 'Coaches have full access to all data'),
('staff', 'users', 'all', 'id', 'Staff can view all user profiles'),
('staff', 'athlete_evaluations', 'all', 'athlete_id', 'Staff can view all evaluations'),
('parent', 'users', 'managed', 'id', 'Parents can view their own and managed athlete profiles'),
('parent', 'athlete_evaluations', 'managed', 'athlete_id', 'Parents can view their managed athletes evaluations'),
('parent', 'goals', 'managed', 'user_id', 'Parents can view their managed athletes goals'),
('parent', 'bookings', 'own', 'user_id', 'Parents can view their own bookings'),
('parent', 'transactions', 'own', 'user_id', 'Parents can view their own transactions'),
('athlete', 'users', 'own', 'id', 'Athletes can only view their own profile'),
('athlete', 'athlete_evaluations', 'own', 'athlete_id', 'Athletes can only view their own evaluations'),
('athlete', 'goals', 'own', 'user_id', 'Athletes can only view their own goals'),
('athlete', 'bookings', 'own', 'user_id', 'Athletes can only view their own bookings'),
('athlete', 'transactions', 'own', 'user_id', 'Athletes can only view their own transactions');

-- Ensure security_logs has needed columns for rate limiting
-- (Add request_uri column if not present to support RateLimiter)
ALTER TABLE `security_logs`
    ADD COLUMN IF NOT EXISTS `request_uri` VARCHAR(500) DEFAULT NULL AFTER `description`,
    ADD INDEX IF NOT EXISTS `idx_rate_limit` (`request_uri`, `event_type`, `created_at`);
