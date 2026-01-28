<!-- Practice Library View -->
<?php
// Fetch practice plans from database
try {
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $filter_team = $_GET['team'] ?? 'all';
    
    // Get teams for filter with fallback
    $teams = [];
    try {
        $teams_query = "SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name";
        $teams_stmt = $pdo->query($teams_query);
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback if is_active column doesn't exist
        try {
            $teams_query = "SELECT id, name FROM teams ORDER BY name";
            $teams_stmt = $pdo->query($teams_query);
            $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            error_log("Teams fetch error: " . $e2->getMessage());
            $teams = [];
        }
    }
    
    // Build practice plans query
    $plans_query = "
        SELECT pp.*, 
               CONCAT(u.first_name, ' ', u.last_name) as creator_name,
               (SELECT COUNT(*) FROM practice_plan_drills WHERE practice_plan_id = pp.id) as drill_count
        FROM practice_plans pp
        LEFT JOIN users u ON pp.created_by = u.id
        WHERE 1=1
    ";
    $params = [];
    
    // Apply search filter
    if (!empty($search)) {
        $plans_query .= " AND (pp.name LIKE ? OR pp.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $plans_query .= " ORDER BY pp.created_at DESC LIMIT 50";
    
    $plans_stmt = $pdo->prepare($plans_query);
    $plans_stmt->execute($params);
    $practice_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Practice plans fetch error: " . $e->getMessage());
    $teams = [];
    $practice_plans = [];
}
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-list"></i> Practice Plans
    </h1>
    <p class="page-description">Browse and manage your practice plans</p>
</div>

<div class="practice-content">
    <!-- Actions Bar -->
    <div class="action-bar">
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="page" value="practice_library">
            <input type="text" name="search" class="form-input-small" placeholder="Search practice plans..." value="<?= htmlspecialchars($search) ?>">
            <select name="team" class="form-input-small">
                <option value="all">All Teams</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= $team['id'] ?>" <?= $filter_team == $team['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($team['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn-primary" data-action="view" data-page="create_practice"><i class="fas fa-plus"></i> Create Practice Plan</button>
    </div>

    <!-- Practice Plans List -->
    <div class="practice-list">
        <?php if (count($practice_plans) > 0): ?>
            <?php foreach ($practice_plans as $plan): ?>
            <div class="practice-card" data-plan-id="<?= $plan['id'] ?>">
                <div class="practice-header">
                    <div class="practice-title-section">
                        <h3 class="practice-title"><?= htmlspecialchars($plan['name']) ?></h3>
                        <div class="practice-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($plan['creator_name'] ?? 'Unknown') ?></span>
                            <span><i class="fas fa-list"></i> <?= $plan['drill_count'] ?> drills</span>
                            <span><i class="fas fa-clock"></i> <?= date('M d, Y', strtotime($plan['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($plan['description'])): ?>
                <div class="practice-body">
                    <p><?= htmlspecialchars(substr($plan['description'], 0, 200)) ?><?= strlen($plan['description']) > 200 ? '...' : '' ?></p>
                </div>
                <?php endif; ?>
                <div class="practice-actions">
                    <button class="btn-secondary btn-sm" data-action="view-plan" data-plan-id="<?= $plan['id'] ?>">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn-secondary btn-sm" data-action="edit-plan" data-plan-id="<?= $plan['id'] ?>">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn-danger btn-sm" data-action="delete-plan" data-plan-id="<?= $plan['id'] ?>">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-clipboard-list placeholder-icon"></i>
                <p class="placeholder-text">No practice plans found. Create your first practice plan to get started!</p>
                <button class="btn btn-primary" style="margin-top: 20px;" data-action="view" data-page="create_practice">
                    <i class="fas fa-plus"></i> Create Practice Plan
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Practice Plan View Modal -->
<div id="practice-view-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-clipboard-list"></i> <span id="view-plan-title">Practice Plan</span></h2>
            <div class="modal-actions">
                <button class="btn-secondary btn-sm" onclick="printPracticePlan()"><i class="fas fa-print"></i> Print</button>
                <button class="modal-close" aria-label="Close modal" onclick="closePlanViewModal()">&times;</button>
            </div>
        </div>
        <div class="modal-body" id="practice-view-content">
            <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading practice plan...</div>
        </div>
    </div>
</div>

<style>
.practice-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.practice-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.practice-card:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.practice-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px;
    background: var(--bg-main);
    border-bottom: 1px solid var(--border);
}

.practice-date {
    flex-shrink: 0;
}

.date-box.completed {
    background: #10b981;
}

.practice-title-section {
    flex: 1;
}

.practice-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.practice-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.practice-meta span {
    font-size: 14px;
    color: var(--text-dim);
}

.practice-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.practice-status {
    flex-shrink: 0;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.upcoming {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-badge.draft {
    background: rgba(148, 163, 184, 0.1);
    color: var(--text-dim);
}

.practice-body {
    padding: 24px;
}

.practice-drills h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.practice-drills h4 i {
    color: var(--neon);
    margin-right: 8px;
}

.drill-list-compact {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.drill-item-compact {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 15px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 4px;
}

.drill-time {
    font-size: 12px;
    font-weight: 700;
    color: var(--neon);
    min-width: 50px;
}

.drill-name {
    font-size: 14px;
    color: var(--text-white);
}

.practice-actions {
    padding: 20px 25px;
    background: var(--bg-main);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Placeholder/Empty State Styles */
.placeholder-container {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 60px 24px;
    text-align: center;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary);
    opacity: 0.5;
    display: block;
    margin-bottom: 20px;
}

.placeholder-text {
    font-size: 16px;
    color: var(--text-dim);
    line-height: 1.6;
    margin-bottom: 24px;
}

.btn-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
}

/* Practice View Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-content.modal-large {
    max-width: 900px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--bg-main);
}

.modal-header .modal-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
}

.modal-header .modal-title i {
    color: var(--neon);
    margin-right: 10px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-dim);
    font-size: 28px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--text-white);
}

.modal-body {
    padding: 24px;
}

.loading-spinner {
    text-align: center;
    padding: 40px;
    color: var(--text-dim);
}

.loading-spinner i {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
    color: var(--neon);
}

/* Practice Plan View Styles */
.plan-view-header {
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--border);
}

.plan-view-header h2 {
    font-size: 24px;
    font-weight: 800;
    color: var(--text-white);
    margin-bottom: 8px;
}

.plan-view-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    font-size: 13px;
    color: var(--text-dim);
}

.plan-view-meta span i {
    color: var(--neon);
    margin-right: 5px;
}

.plan-view-description {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
    color: var(--text-white);
    line-height: 1.6;
}

.drills-list-view {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.drill-view-item {
    display: flex;
    gap: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px;
    align-items: flex-start;
}

.drill-rink-image {
    width: 150px;
    height: 100px;
    background: linear-gradient(135deg, #1a4d2e 0%, #2d5a3d 100%);
    border: 2px solid #3d7a4d;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.drill-rink-image::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
}

.drill-rink-image::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    height: 100%;
    background: rgba(255,255,255,0.2);
}

.drill-rink-image i {
    font-size: 24px;
    color: rgba(255,255,255,0.5);
    z-index: 1;
}

.drill-view-details {
    flex: 1;
}

.drill-view-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.drill-view-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
}

.drill-duration-badge {
    background: rgba(255, 77, 0, 0.15);
    color: var(--neon);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.drill-view-description {
    font-size: 13px;
    color: var(--text-dim);
    line-height: 1.5;
}

.drill-category-tag {
    display: inline-block;
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 8px;
}

/* Print styles */
@media print {
    body * {
        visibility: hidden;
    }
    #practice-view-modal,
    #practice-view-modal * {
        visibility: visible;
    }
    #practice-view-modal {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: white !important;
    }
    .modal-close, .modal-actions button {
        display: none !important;
    }
    .drill-rink-image {
        background: #e0e0e0 !important;
        border-color: #999 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle View Practice Plan button - opens printable view modal
    document.querySelectorAll('[data-action="view-plan"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var planId = this.getAttribute('data-plan-id');
            if (planId) {
                openPlanViewModal(planId);
            }
        });
    });
    
    // Handle Edit Practice Plan button
    document.querySelectorAll('[data-action="edit-plan"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var planId = this.getAttribute('data-plan-id');
            if (planId) {
                window.location.href = 'dashboard.php?page=practice_create&edit=' + planId;
            }
        });
    });
    
    // Handle Delete Practice Plan button
    document.querySelectorAll('[data-action="delete-plan"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var planId = this.getAttribute('data-plan-id');
            
            if (confirm('Are you sure you want to delete this practice plan? This action cannot be undone.')) {
                var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
                
                fetch('process_practice_plans.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=delete&plan_id=' + planId + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the practice card from the DOM
                        var card = document.querySelector('.practice-card[data-plan-id="' + planId + '"]');
                        if (card) {
                            card.style.transition = 'opacity 0.3s, transform 0.3s';
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(-20px)';
                            setTimeout(function() {
                                card.remove();
                            }, 300);
                        }
                        // Show success message
                        showSuccessMessage('Practice plan deleted successfully!');
                    } else {
                        alert('Error: ' + (data.message || 'Failed to delete practice plan'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the practice plan.');
                });
            }
        });
    });
    
    // Handle Create Practice Plan button
    document.querySelectorAll('[data-action="view"][data-page="create_practice"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'dashboard.php?page=practice_create';
        });
    });
});

function showSuccessMessage(message) {
    var alert = document.createElement('div');
    alert.className = 'success-alert';
    alert.style.cssText = 'background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; position: fixed; top: 20px; right: 20px; z-index: 10000;';
    alert.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>' +
        '<span style="color: #10b981; font-weight: 600;">' + message + '</span>' +
        '<button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>';
    document.body.appendChild(alert);
    
    // Auto-remove after 5 seconds
    setTimeout(function() {
        if (alert.parentElement) {
            alert.remove();
        }
    }, 5000);
}

function openPlanViewModal(planId) {
    var modal = document.getElementById('practice-view-modal');
    var content = document.getElementById('practice-view-content');
    
    modal.classList.add('active');
    content.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading practice plan...</div>';
    
    // Fetch practice plan details via AJAX
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    
    fetch('process_practice_plans.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=get_plan&plan_id=' + planId + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.plan) {
            displayPracticePlan(data.plan, data.drills || []);
        } else {
            content.innerHTML = '<div class="error-message" style="color: #ef4444; text-align: center; padding: 40px;"><i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>Failed to load practice plan</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = '<div class="error-message" style="color: #ef4444; text-align: center; padding: 40px;"><i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>An error occurred while loading the practice plan</div>';
    });
}

function displayPracticePlan(plan, drills) {
    var content = document.getElementById('practice-view-content');
    var titleEl = document.getElementById('view-plan-title');
    
    titleEl.textContent = plan.name || 'Practice Plan';
    
    var totalDuration = drills.reduce(function(sum, d) { return sum + (parseInt(d.duration_minutes) || 0); }, 0);
    
    var html = '<div class="plan-view-header">';
    html += '<h2>' + escapeHtml(plan.name) + '</h2>';
    html += '<div class="plan-view-meta">';
    html += '<span><i class="fas fa-user"></i> ' + escapeHtml(plan.creator_name || 'Unknown') + '</span>';
    html += '<span><i class="fas fa-list"></i> ' + drills.length + ' drills</span>';
    html += '<span><i class="fas fa-clock"></i> ' + totalDuration + ' minutes</span>';
    html += '<span><i class="fas fa-calendar"></i> ' + formatDate(plan.created_at) + '</span>';
    html += '</div></div>';
    
    if (plan.description) {
        html += '<div class="plan-view-description">' + escapeHtml(plan.description) + '</div>';
    }
    
    html += '<h3 style="font-size: 16px; font-weight: 700; color: var(--text-white); margin-bottom: 16px;"><i class="fas fa-hockey-puck" style="color: var(--neon); margin-right: 8px;"></i>Drills</h3>';
    
    if (drills.length > 0) {
        html += '<div class="drills-list-view">';
        drills.forEach(function(drill, index) {
            html += '<div class="drill-view-item">';
            html += '<div class="drill-rink-image">';
            if (drill.diagram_data || drill.custom_image) {
                html += '<i class="fas fa-image"></i>';
            } else {
                html += '<i class="fas fa-hockey-puck"></i>';
            }
            html += '</div>';
            html += '<div class="drill-view-details">';
            html += '<div class="drill-view-header">';
            html += '<span class="drill-view-title">' + (index + 1) + '. ' + escapeHtml(drill.title || 'Drill') + '</span>';
            html += '<span class="drill-duration-badge">' + (drill.duration_minutes || 10) + ' min</span>';
            html += '</div>';
            if (drill.description) {
                html += '<div class="drill-view-description">' + escapeHtml(drill.description) + '</div>';
            }
            if (drill.notes) {
                html += '<div class="drill-view-description" style="margin-top: 8px; font-style: italic;"><strong>Notes:</strong> ' + escapeHtml(drill.notes) + '</div>';
            }
            if (drill.category_name) {
                html += '<span class="drill-category-tag">' + escapeHtml(drill.category_name) + '</span>';
            }
            html += '</div></div>';
        });
        html += '</div>';
    } else {
        html += '<div style="text-align: center; padding: 40px; color: var(--text-dim);"><i class="fas fa-info-circle"></i> No drills added to this practice plan</div>';
    }
    
    content.innerHTML = html;
}

function closePlanViewModal() {
    var modal = document.getElementById('practice-view-modal');
    modal.classList.remove('active');
}

function printPracticePlan() {
    window.print();
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return 'Unknown';
    var d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Close modal when clicking outside
document.getElementById('practice-view-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePlanViewModal();
    }
});
</script>
