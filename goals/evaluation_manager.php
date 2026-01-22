<?php
/**
 * Evaluation Manager Class
 * Centralized evaluation management logic for Arctic Wolves
 */

class EvaluationManager {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new evaluation
     */
    public function createEvaluation($athlete_id, $evaluator_id, $data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO athlete_evaluations (
                    athlete_id, evaluator_id, eval_date, notes, status, created_at
                ) VALUES (?, ?, ?, ?, 'draft', NOW())
            ");
            
            $stmt->execute([
                $athlete_id,
                $evaluator_id,
                $data['eval_date'] ?? date('Y-m-d'),
                $data['notes'] ?? ''
            ]);
            
            return ['success' => true, 'evaluation_id' => $this->pdo->lastInsertId()];
            
        } catch (PDOException $e) {
            error_log("EvaluationManager::createEvaluation Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Add skill score to evaluation
     */
    public function addSkillScore($evaluation_id, $skill_id, $score, $notes = '') {
        try {
            // Get athlete_id from evaluation
            $stmt = $this->pdo->prepare("
                SELECT athlete_id FROM athlete_evaluations WHERE id = ?
            ");
            $stmt->execute([$evaluation_id]);
            $eval = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$eval) {
                return ['success' => false, 'error' => 'Evaluation not found'];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO evaluation_scores (
                    athlete_id, evaluation_id, skill_id, score, notes, evaluation_date
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $eval['athlete_id'],
                $evaluation_id,
                $skill_id,
                $score,
                $notes
            ]);
            
            return ['success' => true, 'score_id' => $this->pdo->lastInsertId()];
            
        } catch (PDOException $e) {
            error_log("EvaluationManager::addSkillScore Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Publish an evaluation
     */
    public function publishEvaluation($evaluation_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE athlete_evaluations 
                SET status = 'published', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$evaluation_id]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log("EvaluationManager::publishEvaluation Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get athlete evaluations
     */
    public function getAthleteEvaluations($athlete_id, $status = null) {
        try {
            $sql = "
                SELECT ae.*, 
                    u.first_name as evaluator_first_name, 
                    u.last_name as evaluator_last_name
                FROM athlete_evaluations ae
                JOIN users u ON ae.evaluator_id = u.id
                WHERE ae.athlete_id = ?
            ";
            
            $params = [$athlete_id];
            
            if ($status !== null) {
                $sql .= " AND ae.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY ae.eval_date DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'evaluations' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            
        } catch (PDOException $e) {
            error_log("EvaluationManager::getAthleteEvaluations Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get evaluation with scores
     */
    public function getEvaluationWithScores($evaluation_id) {
        try {
            // Get evaluation
            $stmt = $this->pdo->prepare("
                SELECT ae.*, 
                    u.first_name as evaluator_first_name, 
                    u.last_name as evaluator_last_name,
                    a.first_name as athlete_first_name,
                    a.last_name as athlete_last_name
                FROM athlete_evaluations ae
                JOIN users u ON ae.evaluator_id = u.id
                JOIN users a ON ae.athlete_id = a.id
                WHERE ae.id = ?
            ");
            $stmt->execute([$evaluation_id]);
            $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$evaluation) {
                return ['success' => false, 'error' => 'Evaluation not found'];
            }
            
            // Get scores
            $stmt = $this->pdo->prepare("
                SELECT es.*, efs.name as skill_name, efc.name as category_name
                FROM evaluation_scores es
                LEFT JOIN evaluation_framework_skills efs ON es.skill_id = efs.id
                LEFT JOIN evaluation_framework_categories efc ON efs.category_id = efc.id
                WHERE es.evaluation_id = ?
                ORDER BY efc.name, efs.name
            ");
            $stmt->execute([$evaluation_id]);
            $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $evaluation['scores'] = $scores;
            
            return ['success' => true, 'evaluation' => $evaluation];
            
        } catch (PDOException $e) {
            error_log("EvaluationManager::getEvaluationWithScores Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get evaluation statistics
     */
    public function getEvaluationStats($athlete_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(DISTINCT ae.id) as total_evaluations,
                    COUNT(DISTINCT es.skill_id) as skills_evaluated,
                    AVG(es.score) as avg_score,
                    COUNT(CASE WHEN es.score >= 4 THEN 1 END) as high_scores
                FROM athlete_evaluations ae
                LEFT JOIN evaluation_scores es ON ae.id = es.evaluation_id
                WHERE ae.athlete_id = ?
                AND ae.status = 'published'
            ");
            $stmt->execute([$athlete_id]);
            
            return ['success' => true, 'stats' => $stmt->fetch(PDO::FETCH_ASSOC)];
            
        } catch (PDOException $e) {
            error_log("EvaluationManager::getEvaluationStats Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Delete an evaluation
     */
    public function deleteEvaluation($evaluation_id) {
        try {
            // Delete scores first
            $stmt = $this->pdo->prepare("DELETE FROM evaluation_scores WHERE evaluation_id = ?");
            $stmt->execute([$evaluation_id]);
            
            // Delete evaluation
            $stmt = $this->pdo->prepare("DELETE FROM athlete_evaluations WHERE id = ?");
            $stmt->execute([$evaluation_id]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log("EvaluationManager::deleteEvaluation Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
}
?>
