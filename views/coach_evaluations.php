<!-- Coach Evaluations View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-check"></i> Athlete Evaluations
    </h1>
    <p class="page-description">Create and manage athlete skill evaluations</p>
    <button class="btn-primary" data-action="add" data-modal="create-evaluation-modal">
        <i class="fas fa-plus"></i> Create Evaluation
    </button>
</div>

<?php
// Fetch athlete list for coach
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.first_name, u.last_name, u.username
        FROM users u
        INNER JOIN managed_athletes ma ON u.id = ma.athlete_id
        WHERE ma.coach_id = ?
        AND u.role IN ('athlete', 'parent')
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$user_id]);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get selected athlete
    $selected_athlete_id = $_GET['athlete_id'] ?? ($athletes[0]['id'] ?? null);
    
    $evaluations = [];
    if ($selected_athlete_id) {
        $stmt = $pdo->prepare("
            SELECT ae.*
            FROM athlete_evaluations ae
            WHERE ae.athlete_id = ?
            AND ae.evaluator_id = ?
            ORDER BY ae.eval_date DESC
        ");
        $stmt->execute([$selected_athlete_id, $user_id]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("Coach evaluations fetch error: " . $e->getMessage());
    $athletes = [];
    $evaluations = [];
}
?>

<div class="coach-evaluations-content">
    <!-- Athlete Selector -->
    <div class="athlete-selector">
        <label for="athlete-select">Select Athlete:</label>
        <select id="athlete-select" onchange="location.href='?page=coach_evaluations&athlete_id=' + this.value">
            <?php foreach ($athletes as $athlete): ?>
                <option value="<?= $athlete['id'] ?>" <?= $selected_athlete_id == $athlete['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <?php if (empty($evaluations)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h3>No Evaluations Created</h3>
            <p>Start evaluating this athlete's skills to track their development.</p>
            <button class="btn-primary" data-action="add" data-modal="create-evaluation-modal">
                <i class="fas fa-plus"></i> Create First Evaluation
            </button>
        </div>
    <?php else: ?>
        <!-- Evaluations List -->
        <div class="evaluations-grid">
            <?php foreach ($evaluations as $eval): ?>
                <div class="evaluation-card <?= $eval['status'] ?>">
                    <div class="evaluation-header">
                        <h3>
                            <?= date('M j, Y', strtotime($eval['eval_date'])) ?>
                        </h3>
                        <span class="status-badge status-<?= $eval['status'] ?>">
                            <?= ucfirst($eval['status']) ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($eval['notes'])): ?>
                        <div class="evaluation-preview">
                            <?= substr(htmlspecialchars($eval['notes']), 0, 150) ?>...
                        </div>
                    <?php endif; ?>
                    
                    <div class="evaluation-actions">
                        <button class="btn-secondary" data-action="edit" data-id="<?= $eval['id'] ?>" data-modal="edit-evaluation-modal">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-secondary" data-action="view" data-id="<?= $eval['id'] ?>">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php if ($eval['status'] === 'draft'): ?>
                            <button class="btn-primary" data-action="publish" data-id="<?= $eval['id'] ?>">
                                <i class="fas fa-paper-plane"></i> Publish
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Evaluation Modal -->
<div id="create-evaluation-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create Evaluation</h2>
            <button class="modal-close" onclick="closeModal('create-evaluation-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="create-evaluation-form" method="POST" action="process_evaluations.php">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="athlete_id" value="<?= $selected_athlete_id ?>">
            
            <div class="form-group">
                <label for="eval_date">Evaluation Date *</label>
                <input type="date" id="eval_date" name="eval_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="6" placeholder="Enter evaluation notes and observations..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('create-evaluation-modal')">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-check"></i> Create Evaluation
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.coach-evaluations-content {
    padding: 24px;
}

.athlete-selector {
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.athlete-selector label {
    font-weight: 600;
    color: var(--text-primary);
}

.athlete-selector select {
    min-width: 300px;
}

.evaluations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.evaluation-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
}

.evaluation-card.draft {
    border-color: var(--warning);
}

.evaluation-card.published {
    border-color: var(--success);
}

.evaluation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.evaluation-header h3 {
    margin: 0;
    font-size: 16px;
    color: var(--text-primary);
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-draft {
    background: var(--warning);
    color: white;
}

.status-published {
    background: var(--success);
    color: white;
}

.evaluation-preview {
    margin-bottom: 16px;
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.5;
}

.evaluation-actions {
    display: flex;
    gap: 8px;
}

.evaluation-actions button {
    flex: 1;
}
</style>
