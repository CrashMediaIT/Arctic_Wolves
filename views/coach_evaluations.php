<!-- Coach Evaluations View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-check"></i> Athlete Evaluations
    </h1>
    <p class="page-description">View athlete skill evaluations from the evaluation framework</p>
</div>

<?php
// Fetch athlete list for coach - improved query to include all athletes the coach has worked with
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.date_of_birth
        FROM users u
        WHERE u.role = 'athlete' AND u.is_active = 1
        AND (
            EXISTS (SELECT 1 FROM managed_athletes ma WHERE ma.athlete_id = u.id AND ma.coach_id = ?)
            OR EXISTS (SELECT 1 FROM bookings b INNER JOIN sessions s ON b.session_id = s.id WHERE b.user_id = u.id AND s.coach_id = ?)
        )
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$user_id, $user_id]);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get selected athlete
    $selected_athlete_id = $_GET['athlete_id'] ?? ($athletes[0]['id'] ?? null);
    
    // Get athlete info
    $selected_athlete = null;
    if ($selected_athlete_id) {
        foreach ($athletes as $a) {
            if ($a['id'] == $selected_athlete_id) {
                $selected_athlete = $a;
                break;
            }
        }
    }
    
    $evaluations = [];
    if ($selected_athlete_id) {
        // Only show evaluations created via the eval framework (have skill_id set)
        $stmt = $pdo->prepare("
            SELECT ae.id, ae.athlete_id, ae.evaluator_id, ae.skill_id, ae.rating, 
                   ae.comments as notes, ae.evaluation_date as eval_date, ae.session_id,
                   ae.created_at, 'published' as status,
                   CONCAT(u.first_name, ' ', u.last_name) as evaluator_name,
                   es.name as skill_name
            FROM athlete_evaluations ae
            LEFT JOIN users u ON ae.evaluator_id = u.id
            LEFT JOIN eval_skills es ON ae.skill_id = es.id
            WHERE ae.athlete_id = ? AND ae.skill_id IS NOT NULL
            ORDER BY ae.evaluation_date DESC
        ");
        $stmt->execute([$selected_athlete_id]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get evaluation stats
    $eval_stats = [
        'total' => count($evaluations),
        'published' => 0,
        'draft' => 0
    ];
    foreach ($evaluations as $e) {
        if (($e['status'] ?? 'draft') === 'published') {
            $eval_stats['published']++;
        } else {
            $eval_stats['draft']++;
        }
    }
    
} catch (PDOException $e) {
    error_log("Coach evaluations fetch error: " . $e->getMessage());
    $athletes = [];
    $evaluations = [];
    $selected_athlete = null;
    $eval_stats = ['total' => 0, 'published' => 0, 'draft' => 0];
}
?>

<div class="coach-evaluations-content">
    <!-- Filter and Action Bar -->
    <div class="filter-box">
        <div class="filter-box-header">
            <i class="fas fa-filter"></i> Select Athlete
        </div>
        <div class="filter-box-content">
            <div class="filter-row">
                <div class="filter-field" style="flex: 2;">
                    <label>Athlete</label>
                    <select id="athlete-select" class="form-select" onchange="location.href='?page=coach_evaluations&athlete_id=' + this.value">
                        <option value="">-- Select Athlete --</option>
                        <?php foreach ($athletes as $athlete): ?>
                            <option value="<?= $athlete['id'] ?>" <?= $selected_athlete_id == $athlete['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field filter-actions">
                    <label>&nbsp;</label>
                    <a href="dashboard.php?page=admin_eval_framework" class="btn btn-secondary">
                        <i class="fas fa-cog"></i> Go to Eval Framework
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selected_athlete): ?>
    <!-- Athlete Stats Overview -->
    <div class="eval-stats-grid">
        <div class="eval-stat-card">
            <div class="eval-stat-icon total"><i class="fas fa-clipboard-list"></i></div>
            <div class="eval-stat-info">
                <span class="eval-stat-value"><?= $eval_stats['total'] ?></span>
                <span class="eval-stat-label">Total Evaluations</span>
            </div>
        </div>
        <div class="eval-stat-card">
            <div class="eval-stat-icon published"><i class="fas fa-check-circle"></i></div>
            <div class="eval-stat-info">
                <span class="eval-stat-value"><?= $eval_stats['published'] ?></span>
                <span class="eval-stat-label">Published</span>
            </div>
        </div>
        <div class="eval-stat-card">
            <div class="eval-stat-icon draft"><i class="fas fa-edit"></i></div>
            <div class="eval-stat-info">
                <span class="eval-stat-value"><?= $eval_stats['draft'] ?></span>
                <span class="eval-stat-label">Drafts</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (empty($selected_athlete_id)): ?>
        <!-- No Athlete Selected State -->
        <div class="placeholder-container">
            <i class="fas fa-user-check placeholder-icon"></i>
            <p class="placeholder-text">Select an athlete from the dropdown above to view their evaluations.</p>
        </div>
    <?php elseif (empty($evaluations)): ?>
        <!-- Empty State -->
        <div class="placeholder-container">
            <i class="fas fa-clipboard-check placeholder-icon"></i>
            <h3>No Evaluations Found</h3>
            <p class="placeholder-text">No evaluations have been performed for <?= htmlspecialchars($selected_athlete['first_name'] ?? 'this athlete') ?> yet. Use the Evaluation Framework to create skill evaluations.</p>
            <a href="dashboard.php?page=admin_eval_framework" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-cog"></i> Open Eval Framework
            </a>
        </div>
    <?php else: ?>
        <!-- Evaluations List -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Evaluation History</h3>
            </div>
            <div class="card-body">
                <div class="evaluations-grid">
                    <?php foreach ($evaluations as $eval): 
                        $status = $eval['status'] ?? 'published';
                    ?>
                        <div class="evaluation-card <?= $status ?>">
                            <div class="evaluation-header">
                                <div class="evaluation-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('M j, Y', strtotime($eval['eval_date'])) ?>
                                </div>
                                <?php if (!empty($eval['rating'])): ?>
                                    <span class="rating-badge">
                                        <i class="fas fa-star"></i> <?= htmlspecialchars($eval['rating']) ?>/5
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="evaluation-body">
                                <?php if (!empty($eval['skill_name'])): ?>
                                    <div class="skill-info">
                                        <i class="fas fa-dumbbell"></i> <?= htmlspecialchars($eval['skill_name']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($eval['evaluator_name'])): ?>
                                    <div class="evaluator-info">
                                        <i class="fas fa-user-tie"></i> <?= htmlspecialchars($eval['evaluator_name']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($eval['notes'])): ?>
                                    <div class="evaluation-preview">
                                        <?= nl2br(htmlspecialchars(substr($eval['notes'], 0, 150))) ?><?= strlen($eval['notes']) > 150 ? '...' : '' ?>
                                    </div>
                                <?php else: ?>
                                    <div class="evaluation-preview text-muted">
                                        <em>No comments provided</em>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="evaluation-actions">
                                <button class="btn-sm btn-secondary" data-action="view" data-id="<?= $eval['id'] ?>">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Coach Evaluations - Modern UI */
.coach-evaluations-content {
    max-width: 1400px;
    margin: 0 auto;
}

/* Filter Box */
.filter-box {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
}

.filter-box-header {
    background: var(--bg-main, #0A0A0F);
    padding: 14px 20px;
    font-weight: 700;
    color: var(--text-white, #fff);
    font-size: 14px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-box-header i {
    color: var(--primary, #6B46C1);
}

.filter-box-content {
    padding: 20px;
}

.filter-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 16px;
    align-items: end;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-field label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dim, #6B6B7B);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.filter-actions label {
    display: none;
}

/* Stats Cards */
.eval-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.eval-stat-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
}

.eval-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
}

.eval-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.eval-stat-icon.total { background: rgba(107, 70, 193, 0.15); color: #8B5CF6; }
.eval-stat-icon.published { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.eval-stat-icon.draft { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }

.eval-stat-info {
    display: flex;
    flex-direction: column;
}

.eval-stat-value {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-white, #fff);
}

.eval-stat-label {
    font-size: 12px;
    color: var(--text-dim, #6B6B7B);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Content Card */
.content-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    background: var(--bg-main, #0A0A0F);
    padding: 16px 20px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header h3 i {
    color: var(--primary, #6B46C1);
}

.card-body {
    padding: 20px;
}

/* Evaluations Grid */
.evaluations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.evaluation-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.evaluation-card:hover {
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 8px 20px rgba(107, 70, 193, 0.15);
}

.evaluation-card.draft {
    border-left: 4px solid #f59e0b;
}

.evaluation-card.published {
    border-left: 4px solid #10b981;
}

.evaluation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: rgba(107, 70, 193, 0.05);
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.evaluation-date {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white, #fff);
    display: flex;
    align-items: center;
    gap: 8px;
}

.evaluation-date i {
    color: var(--primary, #6B46C1);
}

.evaluation-body {
    padding: 20px;
}

.evaluator-info {
    font-size: 13px;
    color: var(--text-dim, #6B6B7B);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.evaluator-info i {
    color: var(--primary, #6B46C1);
}

.evaluation-preview {
    font-size: 14px;
    color: var(--text, #A8A8B8);
    line-height: 1.6;
}

.evaluation-preview.text-muted {
    color: var(--text-dim, #6B6B7B);
}

.evaluation-actions {
    padding: 16px 20px;
    background: var(--bg-card, #16161F);
    border-top: 1px solid var(--border, #2D2D3F);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.evaluation-actions button {
    flex: 1;
    min-width: 80px;
}

/* Rating Badge */
.rating-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    background: rgba(107, 70, 193, 0.15);
    color: #8B5CF6;
    display: flex;
    align-items: center;
    gap: 4px;
}

.rating-badge i {
    color: #f59e0b;
}

/* Skill Info */
.skill-info {
    font-size: 14px;
    color: var(--text-white, #fff);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.skill-info i {
    color: var(--primary, #6B46C1);
}

/* Status Badge */
.status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-draft {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.status-published {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

/* Placeholder */
.placeholder-container {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 60px 24px;
    text-align: center;
}

.placeholder-container h3 {
    font-size: 20px;
    color: var(--text-white, #fff);
    margin-bottom: 12px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary, #6B46C1);
    opacity: 0.5;
    display: block;
    margin-bottom: 20px;
}

.placeholder-text {
    font-size: 14px;
    color: var(--text-dim, #6B6B7B);
    line-height: 1.6;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 24px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-dim, #6B6B7B);
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--text-white, #fff);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dim, #6B6B7B);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid var(--border, #2D2D3F);
    margin-top: 24px;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .evaluations-grid {
        grid-template-columns: 1fr;
    }
    
    .eval-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
