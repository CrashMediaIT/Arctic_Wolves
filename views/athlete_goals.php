<!-- Athlete Goals View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-bullseye"></i> My Goals
    </h1>
    <p class="page-description">Track and manage your development goals</p>
    <button class="btn-primary" data-action="add" data-modal="add-goal-modal">
        <i class="fas fa-plus"></i> Create New Goal
    </button>
</div>

<?php
// Fetch athlete goals
try {
    $stmt = $pdo->prepare("
        SELECT g.*,
            CASE 
                WHEN g.target_value > 0 THEN ROUND((g.current_value / g.target_value) * 100, 0)
                ELSE 0
            END as progress_percentage
        FROM goals g
        WHERE g.athlete_id = ?
        ORDER BY 
            CASE WHEN g.status = 'active' THEN 1 
                 WHEN g.status = 'paused' THEN 2 
                 ELSE 3 END,
            g.target_date ASC
    ");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Athlete goals fetch error: " . $e->getMessage());
    $goals = [];
}
?>

<div class="goals-content">
    <?php if (empty($goals)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-bullseye"></i>
            <h3>No Goals Yet</h3>
            <p>Start setting goals to track your hockey development and progress.</p>
            <button class="btn-primary" data-action="add" data-modal="add-goal-modal">
                <i class="fas fa-plus"></i> Create Your First Goal
            </button>
        </div>
    <?php else: ?>
        <!-- Goals List -->
        <div class="goals-list">
            <?php foreach ($goals as $goal): ?>
                <div class="goal-card <?= $goal['status'] ?>">
                    <div class="goal-header">
                        <h3><?= htmlspecialchars($goal['goal_title']) ?></h3>
                        <span class="goal-status status-<?= $goal['status'] ?>">
                            <?= ucfirst($goal['status']) ?>
                        </span>
                    </div>
                    
                    <div class="goal-description">
                        <?= htmlspecialchars($goal['goal_description']) ?>
                    </div>
                    
                    <div class="goal-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $goal['progress_percentage'] ?>%"></div>
                        </div>
                        <div class="progress-text">
                            <?= $goal['current_value'] ?> / <?= $goal['target_value'] ?>
                            (<?= $goal['progress_percentage'] ?>%)
                        </div>
                    </div>
                    
                    <?php if ($goal['target_date']): ?>
                        <div class="goal-deadline">
                            <i class="far fa-calendar"></i>
                            Target: <?= date('M j, Y', strtotime($goal['target_date'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="goal-actions">
                        <button class="btn-secondary" data-action="view" data-id="<?= $goal['id'] ?>">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn-secondary" data-action="edit" data-id="<?= $goal['id'] ?>" data-modal="edit-goal-modal">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-secondary" data-action="update-progress" data-id="<?= $goal['id'] ?>" data-modal="progress-modal">
                            <i class="fas fa-arrow-up"></i> Update Progress
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Goal Modal -->
<div id="add-goal-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create New Goal</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-goal-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="add-goal-form" method="POST" action="process_goals.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="goal_title">Goal Title *</label>
                    <input type="text" id="goal_title" name="goal_title" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="goal_description">Description</label>
                    <textarea id="goal_description" name="goal_description" class="form-input" rows="4"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="goal_type">Goal Type</label>
                        <select id="goal_type" name="goal_type" class="form-input">
                            <option value="general">General</option>
                            <option value="skill">Skill Development</option>
                            <option value="fitness">Fitness</option>
                            <option value="performance">Performance</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="target_value">Target Value *</label>
                        <input type="number" id="target_value" name="target_value" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="target_date">Target Date</label>
                    <input type="date" id="target_date" name="target_date" class="form-input">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-goal-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Create Goal
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.goals-content {
    padding: 24px;
}

.goals-list {
    display: grid;
    gap: 20px;
}

.goal-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
}

.goal-card.completed {
    border-color: var(--success);
    opacity: 0.8;
}

.goal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.goal-header h3 {
    margin: 0;
    font-size: 20px;
    color: var(--text-primary);
}

.goal-status {
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: var(--primary);
    color: white;
}

.status-completed {
    background: var(--success);
    color: white;
}

.status-paused {
    background: var(--warning);
    color: white;
}

.goal-description {
    margin-bottom: 16px;
    color: var(--text-secondary);
    line-height: 1.6;
}

.goal-progress {
    margin-bottom: 16px;
}

.progress-bar {
    height: 8px;
    background: var(--bg);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 14px;
    color: var(--text-secondary);
}

.goal-deadline {
    margin-bottom: 16px;
    color: var(--text-secondary);
    font-size: 14px;
}

.goal-deadline i {
    margin-right: 8px;
}

.goal-actions {
    display: flex;
    gap: 12px;
}

.goal-actions button {
    flex: 1;
}
</style>

<!-- Edit Goal Modal -->
<div id="edit-goal-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Goal</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-goal-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="edit-goal-form" method="POST" action="process_goals.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update_goal">
            <input type="hidden" name="goal_id" id="edit-goal-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_goal_title">Goal Title *</label>
                    <input type="text" id="edit_goal_title" name="title" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_goal_description">Description</label>
                    <textarea id="edit_goal_description" name="description" class="form-input" rows="4"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_goal_category">Category</label>
                        <select id="edit_goal_category" name="category" class="form-input">
                            <option value="">Select Category</option>
                            <option value="skating">Skating</option>
                            <option value="shooting">Shooting</option>
                            <option value="passing">Passing</option>
                            <option value="stickhandling">Stickhandling</option>
                            <option value="fitness">Fitness</option>
                            <option value="mental">Mental</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_goal_tags">Tags</label>
                        <input type="text" id="edit_goal_tags" name="tags" class="form-input" placeholder="Comma-separated tags">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_target_value">Target Value</label>
                        <input type="number" id="edit_target_value" name="target_value" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_current_value">Current Value</label>
                        <input type="number" id="edit_current_value" name="current_value" class="form-input">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_target_date">Target Date</label>
                        <input type="date" id="edit_target_date" name="target_date" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_goal_status">Status</label>
                        <select id="edit_goal_status" name="status" class="form-input">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="paused">Paused</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-goal-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Goal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Progress Update Modal -->
<div id="progress-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Progress</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('progress-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="progress-form" method="POST" action="process_goals.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update_progress">
            <input type="hidden" name="goal_id" id="progress-goal-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="progress_value">New Progress Value *</label>
                    <input type="number" id="progress_value" name="current_value" class="form-input" required>
                    <p class="help-text">Enter your current progress value towards the goal target.</p>
                </div>
                
                <div class="form-group">
                    <label for="progress_notes">Notes (Optional)</label>
                    <textarea id="progress_notes" name="notes" class="form-input" rows="3" placeholder="Add notes about this progress update..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('progress-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-arrow-up"></i> Update Progress
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions
function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}

function openModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
    }
}

// Handle add goal buttons
document.querySelectorAll('[data-action="add"][data-modal="add-goal-modal"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var form = document.getElementById('add-goal-form');
        if (form) form.reset();
        openModal('add-goal-modal');
    });
});

// Handle edit buttons
document.querySelectorAll('[data-action="edit"][data-modal="edit-goal-modal"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var goalId = this.getAttribute('data-id');
        document.getElementById('edit-goal-id').value = goalId;
        
        // Fetch goal data via AJAX and populate form
        fetch('process_goals.php?action=get_goal&goal_id=' + goalId)
            .then(function(response) { return response.json(); })
            .then(function(goal) {
                document.getElementById('edit_goal_title').value = goal.title || '';
                document.getElementById('edit_goal_description').value = goal.description || '';
                document.getElementById('edit_goal_category').value = goal.category || '';
                document.getElementById('edit_goal_tags').value = goal.tags || '';
                document.getElementById('edit_target_value').value = goal.target_value || '';
                document.getElementById('edit_current_value').value = goal.current_value || '';
                document.getElementById('edit_target_date').value = goal.target_date || '';
                document.getElementById('edit_goal_status').value = goal.status || 'active';
            })
            .catch(function(err) {
                console.error('Error fetching goal data:', err);
            });
        
        openModal('edit-goal-modal');
    });
});

// Handle progress update buttons
document.querySelectorAll('[data-action="update-progress"][data-modal="progress-modal"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var goalId = this.getAttribute('data-id');
        document.getElementById('progress-goal-id').value = goalId;
        openModal('progress-modal');
    });
});


</script>
