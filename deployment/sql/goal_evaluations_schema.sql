-- Goal-Based Evaluation Platform Database Schema
-- Creates tables for Type 1 (Goal-Based Interactive) evaluation system

-- Main evaluations table
CREATE TABLE IF NOT EXISTS goal_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    athlete_id INT NOT NULL,
    created_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    share_token VARCHAR(64) UNIQUE,
    is_public TINYINT(1) DEFAULT 0,
    status ENUM('active', 'completed', 'archived') DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_athlete_id (athlete_id),
    INDEX idx_created_by (created_by),
    INDEX idx_share_token (share_token),
    INDEX idx_status (status),
    FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation steps (checklist items)
CREATE TABLE IF NOT EXISTS goal_eval_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_eval_id INT NOT NULL,
    step_order INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    is_completed TINYINT(1) DEFAULT 0,
    completed_at DATETIME,
    completed_by INT,
    needs_approval TINYINT(1) DEFAULT 0,
    is_approved TINYINT(1) DEFAULT 0,
    approved_by INT,
    approved_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX idx_goal_eval_id (goal_eval_id),
    INDEX idx_step_order (step_order),
    INDEX idx_completed_by (completed_by),
    INDEX idx_approved_by (approved_by),
    FOREIGN KEY (goal_eval_id) REFERENCES goal_evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progress tracking and media attachments
CREATE TABLE IF NOT EXISTS goal_eval_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_eval_step_id INT NOT NULL,
    user_id INT NOT NULL,
    progress_note TEXT,
    media_url VARCHAR(500),
    media_type ENUM('image', 'video'),
    created_at DATETIME NOT NULL,
    INDEX idx_step_id (goal_eval_step_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (goal_eval_step_id) REFERENCES goal_eval_steps(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Approval workflow
CREATE TABLE IF NOT EXISTS goal_eval_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_eval_step_id INT NOT NULL,
    requested_by INT NOT NULL,
    approved_by INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approval_note TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_step_id (goal_eval_step_id),
    INDEX idx_requested_by (requested_by),
    INDEX idx_approved_by (approved_by),
    INDEX idx_status (status),
    FOREIGN KEY (goal_eval_step_id) REFERENCES goal_eval_steps(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
