<!-- Athlete Evaluations View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-check"></i> My Evaluations
    </h1>
    <p class="page-description">View your skill evaluations and progress reports</p>
</div>

<?php
// Fetch athlete evaluations
try {
    $stmt = $pdo->prepare("
        SELECT ae.*, 
            u.first_name as evaluator_first_name, 
            u.last_name as evaluator_last_name
        FROM athlete_evaluations ae
        JOIN users u ON ae.evaluator_id = u.id
        WHERE ae.athlete_id = ?
        AND ae.status = 'published'
        ORDER BY ae.eval_date DESC
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Athlete evaluations fetch error: " . $e->getMessage());
    $evaluations = [];
}
?>

<div class="evaluations-content">
    <?php if (empty($evaluations)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h3>No Evaluations Yet</h3>
            <p>Your coach will create evaluations to track your skill development and progress.</p>
        </div>
    <?php else: ?>
        <!-- Evaluations List -->
        <div class="evaluations-list">
            <?php foreach ($evaluations as $eval): ?>
                <div class="evaluation-card">
                    <div class="evaluation-header">
                        <h3>
                            <i class="fas fa-clipboard-check"></i>
                            Evaluation - <?= date('M j, Y', strtotime($eval['eval_date'])) ?>
                        </h3>
                        <span class="evaluator">
                            by <?= htmlspecialchars($eval['evaluator_first_name'] . ' ' . $eval['evaluator_last_name']) ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($eval['notes'])): ?>
                        <div class="evaluation-notes">
                            <h4>Coach Notes:</h4>
                            <p><?= nl2br(htmlspecialchars($eval['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="evaluation-actions">
                        <button class="btn-primary" data-action="view" data-id="<?= $eval['id'] ?>">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.evaluations-content {
    padding: 24px;
}

.evaluations-list {
    display: grid;
    gap: 20px;
}

.evaluation-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
}

.evaluation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.evaluation-header h3 {
    margin: 0;
    font-size: 18px;
    color: var(--text-primary);
}

.evaluation-header h3 i {
    margin-right: 8px;
    color: var(--primary);
}

.evaluator {
    color: var(--text-secondary);
    font-size: 14px;
}

.evaluation-notes {
    margin-bottom: 20px;
}

.evaluation-notes h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: var(--text-secondary);
    font-weight: 600;
}

.evaluation-notes p {
    margin: 0;
    color: var(--text-primary);
    line-height: 1.6;
}
</style>
