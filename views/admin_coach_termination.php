<?php
/**
 * Admin Coach Termination
 * Comprehensive process for terminating coaches and transferring athletes
 */

require_once __DIR__ . '/../security.php';

// Check if user is admin
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get all coaches (not deleted)
$coaches_query = $pdo->query("
    SELECT 
        u.id,
        CONCAT(u.first_name, ' ', u.last_name) as name,
        u.email,
        u.role,
        COUNT(DISTINCT ma.athlete_id) as athlete_count,
        COUNT(DISTINCT g.id) as goal_count,
        COUNT(DISTINCT ae.id) as evaluation_count
    FROM users u
    LEFT JOIN managed_athletes ma ON ma.parent_id = u.id
    LEFT JOIN goals g ON g.created_by = u.id
    LEFT JOIN athlete_evaluations ae ON ae.coach_id = u.id
    WHERE u.role IN ('coach', 'coach_plus', 'team_coach')
    AND (u.is_deleted = 0 OR u.is_deleted IS NULL)
    GROUP BY u.id
    ORDER BY u.first_name, u.last_name
");
$coaches = $coaches_query->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generateCsrfToken();
?>

<style>
    :root {
        --primary: #7000a4;
        --danger: #ef4444;
    }
    
    .termination-header {
        margin-bottom: 30px;
    }
    
    .termination-header h1 {
        font-size: 32px;
        font-weight: 900;
        margin: 0 0 10px 0;
        color: #fff;
    }
    
    .warning-banner {
        background: rgba(239, 68, 68, 0.1);
        border: 2px solid var(--danger);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .warning-banner h3 {
        color: var(--danger);
        margin: 0 0 10px 0;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .warning-banner p {
        color: #94a3b8;
        font-size: 14px;
        margin: 0;
        line-height: 1.6;
    }
    
    .warning-banner ul {
        margin: 10px 0 0 20px;
        color: #94a3b8;
        font-size: 14px;
    }
    
    .termination-form {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 30px;
    }
    
    .form-section {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #1e293b;
    }
    
    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .form-section h2 {
        font-size: 20px;
        font-weight: 700;
        margin: 0 0 20px 0;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .form-label .required {
        color: var(--danger);
    }
    
    .form-select {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-family: inherit;
    }
    
    .form-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .form-textarea {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-family: inherit;
        resize: vertical;
        min-height: 100px;
    }
    
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .help-text {
        font-size: 12px;
        color: #64748b;
        margin-top: 5px;
        line-height: 1.4;
    }
    
    .coach-info {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-top: 10px;
        display: none;
    }
    
    .coach-info.show {
        display: block;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .info-value {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 15px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .checkbox-group:hover {
        border-color: var(--primary);
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .checkbox-group label {
        font-size: 14px;
        color: #fff;
        cursor: pointer;
        flex: 1;
    }
    
    .btn-danger {
        background: var(--danger);
        color: #fff;
        padding: 15px 30px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.2s;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-danger:hover {
        background: #dc2626;
    }
    
    .btn-danger:disabled {
        background: #64748b;
        cursor: not-allowed;
    }
    
    .process-steps {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .process-steps h3 {
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 15px 0;
        color: #fff;
    }
    
    .process-steps ol {
        margin: 0;
        padding-left: 20px;
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.8;
    }
    
    .process-steps li {
        margin-bottom: 8px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #1e293b;
        margin-bottom: 20px;
    }
</style>

<div class="termination-header">
    <h1><i class="fas fa-user-times"></i> Coach Termination Process</h1>
</div>

<div class="warning-banner">
    <h3>
        <i class="fas fa-exclamation-triangle"></i>
        Critical Administrative Action
    </h3>
    <p><strong>Warning:</strong> This process will permanently terminate a coach's access and transfer all their data. This action creates a complete audit trail but cannot be easily undone.</p>
    <ul>
        <li>Automatic database backup will be created BEFORE termination</li>
        <li>All managed athletes will be transferred to a new coach</li>
        <li>Historical notes, evaluations, and goals will be preserved</li>
        <li>Coach account will be soft-deleted (kept for historical reference)</li>
        <li>Complete audit log will be created</li>
    </ul>
</div>

<?php if (empty($coaches)): ?>
    <div class="termination-form">
        <div class="empty-state">
            <i class="fas fa-user-shield"></i>
            <h3>No Coaches Available</h3>
            <p>There are no coaches in the system to terminate</p>
        </div>
    </div>
<?php else: ?>
    <form class="termination-form" id="terminationForm" onsubmit="submitTermination(event)">
        <input type="hidden" name="csrf_token" value="<?= csrfTokenInput() ?>">
        
        <!-- Step 1: Select Coach to Terminate -->
        <div class="form-section">
            <h2><i class="fas fa-user-minus"></i> Step 1: Select Coach to Terminate</h2>
            
            <div class="form-group">
                <label class="form-label">
                    Coach to Terminate <span class="required">*</span>
                </label>
                <select name="coach_to_terminate" id="coachToTerminate" class="form-select" required onchange="showCoachInfo()">
                    <option value="">-- Select a coach --</option>
                    <?php foreach ($coaches as $coach): ?>
                        <option value="<?= $coach['id'] ?>" 
                                data-name="<?= htmlspecialchars($coach['name']) ?>"
                                data-email="<?= htmlspecialchars($coach['email']) ?>"
                                data-role="<?= htmlspecialchars($coach['role']) ?>"
                                data-athletes="<?= $coach['athlete_count'] ?>"
                                data-goals="<?= $coach['goal_count'] ?>"
                                data-evaluations="<?= $coach['evaluation_count'] ?>">
                            <?= htmlspecialchars($coach['name']) ?> (<?= htmlspecialchars($coach['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="coachInfo" class="coach-info">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Role</div>
                        <div class="info-value" id="infoRole">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Managed Athletes</div>
                        <div class="info-value" id="infoAthletes">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Goals Created</div>
                        <div class="info-value" id="infoGoals">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Evaluations</div>
                        <div class="info-value" id="infoEvaluations">-</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Step 2: Select Transfer Coach -->
        <div class="form-section">
            <h2><i class="fas fa-exchange-alt"></i> Step 2: Select Transfer Coach</h2>
            
            <div class="form-group">
                <label class="form-label">
                    New Coach (Transfer Target) <span class="required">*</span>
                </label>
                <select name="transfer_to_coach" id="transferToCoach" class="form-select" required>
                    <option value="">-- Select a coach --</option>
                    <?php foreach ($coaches as $coach): ?>
                        <option value="<?= $coach['id'] ?>">
                            <?= htmlspecialchars($coach['name']) ?> (<?= htmlspecialchars($coach['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">All athletes, goals, and evaluations will be transferred to this coach</div>
            </div>
        </div>
        
        <!-- Step 3: Termination Reason -->
        <div class="form-section">
            <h2><i class="fas fa-file-alt"></i> Step 3: Termination Details</h2>
            
            <div class="form-group">
                <label class="form-label">
                    Reason for Termination <span class="required">*</span>
                </label>
                <textarea name="termination_reason" class="form-textarea" required placeholder="Enter the reason for termination (for audit purposes)"></textarea>
                <div class="help-text">This will be stored in the audit log</div>
            </div>
        </div>
        
        <!-- Step 4: Confirmation -->
        <div class="form-section">
            <h2><i class="fas fa-check-double"></i> Step 4: Confirmation</h2>
            
            <div class="checkbox-group">
                <input type="checkbox" id="confirmBackup" name="confirm_backup" required>
                <label for="confirmBackup">
                    I understand that an automatic database backup will be created before termination
                </label>
            </div>
            
            <div class="checkbox-group" style="margin-top: 10px;">
                <input type="checkbox" id="confirmTransfer" name="confirm_transfer" required>
                <label for="confirmTransfer">
                    I confirm that all athletes and data will be transferred to the selected coach
                </label>
            </div>
            
            <div class="checkbox-group" style="margin-top: 10px;">
                <input type="checkbox" id="confirmPermanent" name="confirm_permanent" required>
                <label for="confirmPermanent">
                    I understand this action will soft-delete the coach account and cannot be easily reversed
                </label>
            </div>
            
            <div class="process-steps">
                <h3>What Will Happen:</h3>
                <ol>
                    <li>Automatic database backup will be created</li>
                    <li>All managed athletes will be transferred to new coach</li>
                    <li>All goals created by coach will be reassigned</li>
                    <li>All evaluations will be reassigned</li>
                    <li>Coach user account will be marked as deleted (soft delete)</li>
                    <li>Complete audit trail will be created</li>
                    <li>Historical data will be preserved</li>
                </ol>
            </div>
        </div>
        
        <button type="submit" class="btn-danger" id="submitBtn">
            <i class="fas fa-user-times"></i>
            Terminate Coach and Transfer Data
        </button>
    </form>
<?php endif; ?>

<script>
function showCoachInfo() {
    const select = document.getElementById('coachToTerminate');
    const option = select.options[select.selectedIndex];
    const info = document.getElementById('coachInfo');
    
    if (option.value) {
        document.getElementById('infoRole').textContent = option.dataset.role || '-';
        document.getElementById('infoAthletes').textContent = option.dataset.athletes || '0';
        document.getElementById('infoGoals').textContent = option.dataset.goals || '0';
        document.getElementById('infoEvaluations').textContent = option.dataset.evaluations || '0';
        info.classList.add('show');
        
        // Disable the same coach in transfer select
        const transferSelect = document.getElementById('transferToCoach');
        for (let opt of transferSelect.options) {
            opt.disabled = (opt.value === option.value);
        }
    } else {
        info.classList.remove('show');
    }
}

function submitTermination(event) {
    event.preventDefault();
    
    const coachSelect = document.getElementById('coachToTerminate');
    const coachOption = coachSelect.options[coachSelect.selectedIndex];
    const coachName = coachOption.dataset.name;
    const athleteCount = coachOption.dataset.athletes;
    
    const confirmMessage = `Are you absolutely sure you want to terminate ${coachName}?\n\n` +
                          `This will:\n` +
                          `- Create an automatic database backup\n` +
                          `- Transfer ${athleteCount} athlete(s) to the new coach\n` +
                          `- Soft-delete the coach account\n` +
                          `- Create a complete audit trail\n\n` +
                          `Type "TERMINATE" to confirm:`;
    
    const confirmation = prompt(confirmMessage);
    
    if (confirmation !== 'TERMINATE') {
        alert('Termination cancelled. You must type "TERMINATE" to confirm.');
        return;
    }
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Termination...';
    
    const formData = new FormData(event.target);
    
    fetch('../process_coach_termination.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('SUCCESS: ' + data.message + '\n\nBackup created: ' + (data.backup_file || 'N/A'));
            window.location.href = '?page=admin_team_coaches';
        } else {
            alert('ERROR: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-user-times"></i> Terminate Coach and Transfer Data';
        }
    })
    .catch(error => {
        alert('ERROR: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-user-times"></i> Terminate Coach and Transfer Data';
    });
}
</script>
