<?php
/**
 * Goals Manager Class
 * Centralized goal management logic for Arctic Wolves
 */

class GoalsManager {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new goal
     */
    public function createGoal($athlete_id, $data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO goals (
                    athlete_id, title, description, category,
                    target_value, current_value, target_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([
                $athlete_id,
                $data['title'],
                $data['description'] ?? '',
                $data['category'] ?? 'general',
                $data['target_value'] ?? 100,
                $data['current_value'] ?? 0,
                $data['target_date'] ?? null
            ]);
            
            return ['success' => true, 'goal_id' => $this->pdo->lastInsertId()];
            
        } catch (PDOException $e) {
            error_log("GoalsManager::createGoal Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Update goal progress
     */
    public function updateProgress($goal_id, $current_value) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE goals 
                SET current_value = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$current_value, $goal_id]);
            
            // Check if goal is completed
            $stmt = $this->pdo->prepare("
                SELECT target_value FROM goals WHERE id = ?
            ");
            $stmt->execute([$goal_id]);
            $goal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($goal && $current_value >= $goal['target_value']) {
                $this->completeGoal($goal_id);
            }
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log("GoalsManager::updateProgress Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Complete a goal
     */
    public function completeGoal($goal_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE goals 
                SET status = 'completed', completed_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$goal_id]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log("GoalsManager::completeGoal Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get athlete goals
     */
    public function getAthleteGoals($athlete_id, $status = null) {
        try {
            $sql = "
                SELECT g.*,
                    CASE 
                        WHEN g.target_value > 0 THEN ROUND((g.current_value / g.target_value) * 100, 0)
                        ELSE 0
                    END as progress_percentage
                FROM goals g
                WHERE g.athlete_id = ?
            ";
            
            $params = [$athlete_id];
            
            if ($status !== null) {
                $sql .= " AND g.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY g.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'goals' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            
        } catch (PDOException $e) {
            error_log("GoalsManager::getAthleteGoals Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get goal by ID
     */
    public function getGoal($goal_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT g.*,
                    CASE 
                        WHEN g.target_value > 0 THEN ROUND((g.current_value / g.target_value) * 100, 0)
                        ELSE 0
                    END as progress_percentage
                FROM goals g
                WHERE g.id = ?
            ");
            $stmt->execute([$goal_id]);
            $goal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$goal) {
                return ['success' => false, 'error' => 'Goal not found'];
            }
            
            return ['success' => true, 'goal' => $goal];
            
        } catch (PDOException $e) {
            error_log("GoalsManager::getGoal Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Delete a goal
     */
    public function deleteGoal($goal_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM goals WHERE id = ?");
            $stmt->execute([$goal_id]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log("GoalsManager::deleteGoal Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get goal statistics
     */
    public function getGoalStats($athlete_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_goals,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_goals,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
                    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_goals,
                    AVG(CASE WHEN status = 'active' AND target_value > 0 
                        THEN (current_value / target_value) * 100 
                        ELSE NULL 
                    END) as avg_progress
                FROM goals 
                WHERE athlete_id = ?
            ");
            $stmt->execute([$athlete_id]);
            
            return ['success' => true, 'stats' => $stmt->fetch(PDO::FETCH_ASSOC)];
            
        } catch (PDOException $e) {
            error_log("GoalsManager::getGoalStats Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
}
?>
