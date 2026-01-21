<?php
/**
 * Goal-Based Interactive Evaluation Platform (Type 1)
 * Interactive evaluation interface with checklist steps, approval workflow, and media attachments
 */

require_once __DIR__ . '/../security.php';

// Get user info from session
$current_user_id = $user_id;
$current_user_role = $user_role;

// Coaches can switch between athletes
$viewing_athlete_id = $current_user_id;
if ($isCoach && isset($_GET['athlete_id'])) {
    $viewing_athlete_id = intval($_GET['athlete_id']);
}

// Get athlete list for coaches
$athletes = [];
if ($isCoach) {
    $athletes_query = "
        SELECT u.id, u.first_name, u.last_name, u.email
        FROM users u
        WHERE u.role = 'athlete' AND u.is_active = 1
        ORDER BY u.last_name, u.first_name
    ";
    $athletes = $pdo->query($athletes_query)->fetchAll();
}

// Get athlete info
$athlete_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$athlete_stmt->execute([$viewing_athlete_id]);
$athlete_info = $athlete_stmt->fetch();

// Get all evaluations for viewing athlete
$evals_stmt = $pdo->prepare("
    SELECT ge.*, 
           CONCAT(u.first_name, ' ', u.last_name) as creator_name,
           (SELECT COUNT(*) FROM goal_eval_steps WHERE goal_eval_id = ge.id) as total_steps,
           (SELECT COUNT(*) FROM goal_eval_steps WHERE goal_eval_id = ge.id AND is_completed = 1) as completed_steps
    FROM goal_evaluations ge
    LEFT JOIN users u ON ge.created_by = u.id
    WHERE ge.athlete_id = ?
    ORDER BY ge.created_at DESC
");
$evals_stmt->execute([$viewing_athlete_id]);
$evaluations = $evals_stmt->fetchAll();
?>

<style>
    :root {
        --primary: #7000a4;
        --primary-hover: #5a0083;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
        --bg-dark: #0d1117;
        --bg-darker: #06080b;
        --border: #1e293b;
        --text-light: #94a3b8;
    }
    
    .evaluations-container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    
    .header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    
    .athlete-selector {
        padding: 10px 16px;
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .athlete-selector:hover {
        border-color: var(--primary);
    }
    
    .btn-create-evaluation {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-create-evaluation:hover {
        background: var(--primary-hover);
    }
    
    .evaluations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .eval-card {
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .eval-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    
    .eval-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }
    
    .eval-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
    }
    
    .eval-meta {
        font-size: 12px;
        color: var(--text-light);
    }
    
    .eval-status {
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .status-active { background: var(--primary); color: #fff; }
    .status-completed { background: var(--success); color: #fff; }
    .status-archived { background: var(--text-light); color: #fff; }
    
    .eval-progress {
        margin-top: 15px;
    }
    
    .progress-bar-container {
        height: 8px;
        background: var(--bg-darker);
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 8px;
    }
    
    .progress-bar-fill {
        height: 100%;
        background: var(--primary);
        transition: width 0.3s;
    }
    
    .progress-text {
        font-size: 12px;
        color: var(--text-light);
    }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        overflow-y: auto;
    }
    
    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 12px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    }
    
    .modal-header {
        padding: 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-title {
        font-size: 24px;
        font-weight: 900;
        color: #fff;
    }
    
    .modal-close {
        background: none;
        border: none;
        color: var(--text-light);
        font-size: 28px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .modal-close:hover {
        color: #fff;
    }
    
    .modal-body {
        padding: 24px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
        font-size: 14px;
    }
    
    .form-input, .form-textarea, .form-select {
        width: 100%;
        padding: 12px;
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .form-input:focus, .form-textarea:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .form-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .form-checkbox {
        margin-right: 8px;
    }
    
    .btn-primary, .btn-secondary, .btn-danger, .btn-success {
        padding: 12px 24px;
        border-radius: 6px;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: var(--primary);
        color: #fff;
    }
    
    .btn-primary:hover {
        background: var(--primary-hover);
    }
    
    .btn-secondary {
        background: transparent;
        border: 1px solid var(--border);
        color: #fff;
    }
    
    .btn-secondary:hover {
        border-color: var(--text-light);
    }
    
    .btn-danger {
        background: var(--danger);
        color: #fff;
    }
    
    .btn-success {
        background: var(--success);
        color: #fff;
    }
    
    .modal-footer {
        padding: 20px 24px;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }
    
    /* Evaluation Detail View */
    .eval-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 20px;
    }
    
    .eval-actions {
        display: flex;
        gap: 8px;
    }
    
    .btn-icon {
        background: transparent;
        border: 1px solid var(--border);
        color: #fff;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-icon:hover {
        border-color: var(--primary);
        background: rgba(112, 0, 164, 0.1);
    }
    
    .steps-list {
        margin-top: 20px;
    }
    
    .step-item {
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 16px;
        margin-bottom: 12px;
        transition: all 0.2s;
    }
    
    .step-item:hover {
        border-color: var(--primary);
    }
    
    .step-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }
    
    .step-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .step-title {
        flex: 1;
        font-weight: 700;
        color: #fff;
        font-size: 16px;
    }
    
    .step-completed {
        text-decoration: line-through;
        opacity: 0.6;
    }
    
    .step-description {
        color: var(--text-light);
        font-size: 14px;
        margin-left: 32px;
        margin-bottom: 8px;
    }
    
    .step-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-left: 32px;
        margin-top: 12px;
    }
    
    .step-status {
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-needs-approval {
        color: var(--warning);
    }
    
    .status-approved {
        color: var(--success);
    }
    
    .status-rejected {
        color: var(--danger);
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .media-section {
        margin-top: 12px;
        margin-left: 32px;
    }
    
    .media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    
    .media-item {
        position: relative;
        border-radius: 6px;
        overflow: hidden;
        aspect-ratio: 1;
    }
    
    .media-item img, .media-item video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .share-section {
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 16px;
        margin-top: 20px;
    }
    
    .share-link {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .share-link-input {
        flex: 1;
        padding: 8px 12px;
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 4px;
        color: #fff;
        font-size: 12px;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid var(--success);
        color: var(--success);
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-light);
    }
    
    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }
</style>

<div class="evaluations-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Goal-Based Evaluations</h1>
            <?php if ($athlete_info): ?>
                <p style="color: var(--text-light); margin-top: 8px;">
                    Viewing evaluations for: <strong style="color: #fff;"><?php echo htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']); ?></strong>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="header-actions">
            <?php if ($isCoach && count($athletes) > 0): ?>
                <select class="athlete-selector" id="athleteSelector" onchange="switchAthlete(this.value)">
                    <option value="">Select Athlete</option>
                    <?php foreach ($athletes as $athlete): ?>
                        <option value="<?php echo $athlete['id']; ?>" <?php echo ($athlete['id'] == $viewing_athlete_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($athlete['last_name'] . ', ' . $athlete['first_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <?php if ($isCoach): ?>
                <button class="btn-create-evaluation" onclick="openCreateModal()">
                    + Create Evaluation
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (count($evaluations) === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>No Evaluations Yet</h3>
            <p>Get started by creating your first goal-based evaluation.</p>
        </div>
    <?php else: ?>
        <div class="evaluations-grid">
            <?php foreach ($evaluations as $eval): 
                $progress = $eval['total_steps'] > 0 ? ($eval['completed_steps'] / $eval['total_steps']) * 100 : 0;
            ?>
                <div class="eval-card" onclick="viewEvaluation(<?php echo $eval['id']; ?>)">
                    <div class="eval-card-header">
                        <div>
                            <h3 class="eval-title"><?php echo htmlspecialchars($eval['title']); ?></h3>
                            <p class="eval-meta">
                                Created by <?php echo htmlspecialchars($eval['creator_name']); ?><br>
                                <?php echo date('M j, Y', strtotime($eval['created_at'])); ?>
                            </p>
                        </div>
                        <span class="eval-status status-<?php echo $eval['status']; ?>">
                            <?php echo ucfirst($eval['status']); ?>
                        </span>
                    </div>
                    
                    <?php if ($eval['description']): ?>
                        <p style="color: var(--text-light); font-size: 14px; margin-bottom: 15px;">
                            <?php echo htmlspecialchars(substr($eval['description'], 0, 100)) . (strlen($eval['description']) > 100 ? '...' : ''); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="eval-progress">
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <p class="progress-text">
                            <?php echo $eval['completed_steps']; ?> of <?php echo $eval['total_steps']; ?> steps completed (<?php echo round($progress); ?>%)
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create/Edit Evaluation Modal -->
<div id="evaluationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Create Evaluation</h2>
            <button class="modal-close" onclick="closeModal('evaluationModal')">&times;</button>
        </div>
        <form id="evaluationForm">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="create_evaluation">
                <input type="hidden" name="evaluation_id" id="evaluationId">
                <input type="hidden" name="athlete_id" value="<?php echo $viewing_athlete_id; ?>">
                <?php echo csrfTokenInput(); ?>
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="evalTitle" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="evalDescription" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="evalStatus" class="form-select">
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_public" id="evalIsPublic" value="1">
                        Enable public sharing
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('evaluationModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Evaluation</button>
            </div>
        </form>
    </div>
</div>

<!-- Evaluation Detail Modal -->
<div id="evaluationDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="detailTitle">Evaluation Details</h2>
            <button class="modal-close" onclick="closeModal('evaluationDetailModal')">&times;</button>
        </div>
        <div class="modal-body" id="evaluationDetailContent">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<!-- Add Step Modal -->
<div id="addStepModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Step</h2>
            <button class="modal-close" onclick="closeModal('addStepModal')">&times;</button>
        </div>
        <form id="addStepForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_step">
                <input type="hidden" name="goal_eval_id" id="stepEvalId">
                <?php echo csrfTokenInput(); ?>
                
                <div class="form-group">
                    <label class="form-label">Step Title *</label>
                    <input type="text" name="title" id="stepTitle" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="stepDescription" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="needs_approval" id="stepNeedsApproval" value="1">
                        Requires approval when athlete completes
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('addStepModal')">Cancel</button>
                <button type="submit" class="btn-primary">Add Step</button>
            </div>
        </form>
    </div>
</div>

<script>
const isCoach = <?php echo $isCoach ? 'true' : 'false'; ?>;
const currentUserId = <?php echo $current_user_id; ?>;

function switchAthlete(athleteId) {
    if (athleteId) {
        window.location.href = `dashboard.php?page=evaluations_goals&athlete_id=${athleteId}`;
    }
}

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create Evaluation';
    document.getElementById('formAction').value = 'create_evaluation';
    document.getElementById('evaluationForm').reset();
    document.getElementById('evaluationId').value = '';
    openModal('evaluationModal');
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function viewEvaluation(evalId) {
    fetch(`process_eval_goals.php?action=get_evaluation&evaluation_id=${evalId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderEvaluationDetail(data.evaluation, data.steps);
                openModal('evaluationDetailModal');
            } else {
                alert('Error loading evaluation: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Failed to load evaluation');
        });
}

function renderEvaluationDetail(evaluation, steps) {
    const progress = evaluation.total_steps > 0 ? (evaluation.completed_steps / evaluation.total_steps) * 100 : 0;
    
    let html = `
        <div class="eval-detail-header">
            <div>
                <h2 style="color: #fff; margin-bottom: 8px;">${escapeHtml(evaluation.title)}</h2>
                <p style="color: var(--text-light); font-size: 14px;">
                    Created by ${escapeHtml(evaluation.creator_name)} on ${formatDate(evaluation.created_at)}
                </p>
                ${evaluation.description ? `<p style="color: var(--text-light); margin-top: 12px;">${escapeHtml(evaluation.description)}</p>` : ''}
            </div>
            <div class="eval-actions">
                ${isCoach ? `
                    <button class="btn-icon" onclick="editEvaluation(${evaluation.id})">✏️ Edit</button>
                    <button class="btn-icon" onclick="deleteEvaluation(${evaluation.id})">🗑️ Delete</button>
                ` : ''}
            </div>
        </div>
        
        <div class="eval-progress">
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: ${progress}%"></div>
            </div>
            <p class="progress-text">
                ${evaluation.completed_steps} of ${evaluation.total_steps} steps completed (${Math.round(progress)}%)
            </p>
        </div>
    `;
    
    if (isCoach) {
        html += `
            <div style="margin-top: 20px;">
                <button class="btn-primary" onclick="openAddStepModal(${evaluation.id})">+ Add Step</button>
            </div>
        `;
    }
    
    html += '<div class="steps-list">';
    
    if (steps.length === 0) {
        html += '<p style="color: var(--text-light); text-align: center; padding: 40px;">No steps added yet.</p>';
    } else {
        steps.forEach(step => {
            html += renderStep(step);
        });
    }
    
    html += '</div>';
    
    // Share section
    if (isCoach) {
        html += `
            <div class="share-section">
                <h3 style="color: #fff; margin-bottom: 12px;">Share Link</h3>
                ${evaluation.share_token && evaluation.is_public ? `
                    <div class="share-link">
                        <input type="text" class="share-link-input" readonly 
                               value="${window.location.origin}/public_eval.php?token=${evaluation.share_token}" 
                               id="shareLink">
                        <button class="btn-secondary btn-sm" onclick="copyShareLink()">Copy</button>
                        <button class="btn-danger btn-sm" onclick="revokeShareLink(${evaluation.id})">Revoke</button>
                    </div>
                ` : `
                    <button class="btn-primary" onclick="generateShareLink(${evaluation.id})">Generate Share Link</button>
                `}
            </div>
        `;
    }
    
    document.getElementById('evaluationDetailContent').innerHTML = html;
}

function renderStep(step) {
    const needsApproval = step.needs_approval && !isCoach;
    const isPending = step.is_completed && !step.is_approved && needsApproval;
    const isApproved = step.is_approved;
    const isRejected = step.approval_status === 'rejected';
    
    return `
        <div class="step-item">
            <div class="step-header">
                <input type="checkbox" 
                       class="step-checkbox" 
                       ${step.is_completed ? 'checked' : ''} 
                       onchange="toggleStep(${step.id}, this.checked)"
                       ${isApproved ? 'disabled' : ''}>
                <span class="step-title ${step.is_completed ? 'step-completed' : ''}">
                    ${escapeHtml(step.title)}
                </span>
            </div>
            
            ${step.description ? `
                <p class="step-description">${escapeHtml(step.description)}</p>
            ` : ''}
            
            <div class="step-footer">
                <div class="step-status">
                    ${isPending ? '<span class="status-needs-approval">⏳ Pending Approval</span>' : ''}
                    ${isApproved ? '<span class="status-approved">✓ Approved</span>' : ''}
                    ${isRejected ? '<span class="status-rejected">✗ Rejected</span>' : ''}
                </div>
                
                <div>
                    ${isCoach && isPending ? `
                        <button class="btn-success btn-sm" onclick="approveStep(${step.id})">Approve</button>
                        <button class="btn-danger btn-sm" onclick="rejectStep(${step.id})">Reject</button>
                    ` : ''}
                    <button class="btn-secondary btn-sm" onclick="viewStepMedia(${step.id})">📎 Media</button>
                </div>
            </div>
        </div>
    `;
}

function openAddStepModal(evalId) {
    document.getElementById('stepEvalId').value = evalId;
    document.getElementById('addStepForm').reset();
    openModal('addStepModal');
}

function toggleStep(stepId, isChecked) {
    fetch('process_eval_goals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'check_step',
            step_id: stepId,
            is_checked: isChecked ? '1' : '0',
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Reload the evaluation detail
            const evalId = new URLSearchParams(window.location.search).get('evaluation_id');
            viewEvaluation(evalId || data.evaluation_id);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to update step');
    });
}

function approveStep(stepId) {
    if (!confirm('Approve this step?')) return;
    
    fetch('process_eval_goal_approval.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'approve_step',
            step_id: stepId,
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Step approved!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function rejectStep(stepId) {
    const note = prompt('Reason for rejection (optional):');
    
    fetch('process_eval_goal_approval.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'reject_step',
            step_id: stepId,
            rejection_note: note || '',
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Step rejected');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function generateShareLink(evalId) {
    fetch('process_eval_goals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'generate_share_link',
            evaluation_id: evalId,
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            viewEvaluation(evalId);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function copyShareLink() {
    const input = document.getElementById('shareLink');
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(() => {
            alert('Link copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy:', err);
            fallbackCopyText(input);
        });
    } else {
        fallbackCopyText(input);
    }
}

function fallbackCopyText(input) {
    input.select();
    try {
        document.execCommand('copy');
        alert('Link copied to clipboard!');
    } catch (err) {
        alert('Failed to copy link. Please copy manually.');
    }
}

function revokeShareLink(evalId) {
    if (!confirm('Revoke public access to this evaluation?')) return;
    
    fetch('process_eval_goals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'revoke_share_link',
            evaluation_id: evalId,
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            viewEvaluation(evalId);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Form submissions
document.getElementById('evaluationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('process_eval_goals.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to save evaluation');
    });
});

document.getElementById('addStepForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('process_eval_goals.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('addStepModal');
            viewEvaluation(document.getElementById('stepEvalId').value);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to add step');
    });
});

// Close modals on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>
