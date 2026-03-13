<?php
/**
 * PWA HR Complaints - Mobile-native complaint management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessHR) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$complaints = [];
try {
    $stmt = $pdo->prepare("SELECT id, subject, description, status, priority, created_at, reporter_name FROM complaints ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $complaints = []; }

$staffList = [];
try {
    $stStaff = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role IN ('admin','coach','staff') ORDER BY first_name");
    $staffList = $stStaff->fetchAll(PDO::FETCH_ASSOC);
    $staffList = decryptUserRows($staffList);
    foreach ($staffList as &$s) { $s['name'] = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')); }
    unset($s);
} catch (PDOException $e) { $staffList = []; }

$nextComplaintNum = 'COMP-' . str_pad(count($complaints) + 1, 4, '0', STR_PAD_LEFT);
?>
<style>
.m-complaints { padding: 16px; font-family: Inter, sans-serif; }
.m-complaints-header { margin-bottom: 16px; }
.m-complaints-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-complaints-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-complaint-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-complaint-top {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 6px; gap: 8px;
}
.m-complaint-subject { font-size: 14px; font-weight: 600; color: #fff; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-complaint-badges { display: flex; gap: 6px; flex-shrink: 0; }
.m-complaint-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-complaint-priority-high { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-complaint-priority-medium { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-complaint-priority-low { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-complaint-priority-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-complaint-status-open { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-complaint-status-investigating { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-complaint-status-resolved { background: rgba(16,185,129,0.15); color: #10B981; }
.m-complaint-status-closed { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-complaint-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-complaint-desc { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-complaint-meta { font-size: 11px; color: #6B6B7B; display: flex; gap: 12px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-fab { position: fixed; bottom: 60px; right: 20px; background: #6B46C1; color: #fff; border: none; border-radius: 50%; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(107,70,193,0.4); z-index: 999; cursor: pointer; }
.m-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; }
.m-overlay.active { display: block; }
.m-bottom-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh; overflow-y: auto; z-index: 1001; padding: 20px; transform: translateY(100%); transition: transform 0.3s; }
.m-bottom-sheet.active { transform: translateY(0); }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; display: flex; justify-content: space-between; align-items: center; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { font-size: 12px; color: #A8A8B8; margin-bottom: 4px; display: block; }
.m-form-input, .m-form-select, .m-form-textarea { background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box; font-size: 14px; font-family: Inter, sans-serif; }
.m-form-textarea { min-height: 80px; resize: vertical; }
.m-btn-submit { background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px; font-weight: 600; width: 100%; font-size: 14px; cursor: pointer; margin-top: 8px; }
.m-btn-danger { background: #EF4444; }
.m-card-actions { display: flex; gap: 8px; margin-top: 8px; }
.m-btn-sm { font-size: 11px; padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; min-height: 28px; font-weight: 500; }
</style>

<div class="m-complaints">
    <div class="m-complaints-header">
        <h2 class="m-complaints-title">Complaints</h2>
        <p class="m-complaints-sub"><?= count($complaints) ?> complaint<?= count($complaints) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($complaints)): ?>
        <div class="m-empty-state">
            <i class="fas fa-flag"></i>
            No complaints filed
        </div>
    <?php else: ?>
        <?php foreach ($complaints as $comp):
            $priority = strtolower($comp['priority'] ?? 'default');
            $priorityClass = match($priority) {
                'high', 'critical', 'urgent' => 'high',
                'medium', 'normal' => 'medium',
                'low' => 'low',
                default => 'default',
            };
            $status = strtolower($comp['status'] ?? 'default');
            $statusClass = match($status) {
                'open', 'new' => 'open',
                'investigating', 'in_progress', 'in progress' => 'investigating',
                'resolved' => 'resolved',
                'closed', 'dismissed' => 'closed',
                default => 'default',
            };
        ?>
        <div class="m-complaint-card">
            <div class="m-complaint-top">
                <span class="m-complaint-subject"><?= htmlspecialchars($comp['subject'] ?: 'No subject') ?></span>
                <div class="m-complaint-badges">
                    <span class="m-complaint-badge m-complaint-priority-<?= $priorityClass ?>"><?= htmlspecialchars(ucfirst($priority)) ?></span>
                    <span class="m-complaint-badge m-complaint-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
            </div>
            <?php if (!empty($comp['description'])): ?>
                <div class="m-complaint-desc"><?= htmlspecialchars($comp['description']) ?></div>
            <?php endif; ?>
            <div class="m-complaint-meta">
                <?php if (!empty($comp['reporter_name'])): ?>
                    <span><i class="fas fa-user" style="font-size:10px;"></i> <?= htmlspecialchars($comp['reporter_name']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar" style="font-size:10px;"></i> <?= date('M j, Y', strtotime($comp['created_at'])) ?></span>
            </div>
            <div class="m-card-actions">
                <button class="m-btn-sm" style="background:rgba(139,92,246,0.15);color:#8B5CF6;" onclick="openEditComplaint(<?= $comp['id'] ?>, <?= json_encode($comp['status'] ?? '') ?>, <?= json_encode($comp['priority'] ?? '') ?>)">Edit</button>
                <form method="POST" action="process_hr_complaints.php" style="display:inline;" data-confirm="Delete this complaint?">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="complaint_id" value="<?= $comp['id'] ?>">
                    <button type="submit" class="m-btn-sm m-btn-danger" style="color:#fff;">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="openSheet('create')"><i class="fas fa-plus" style="font-size:20px;"></i></button>

<div class="m-overlay" id="mOverlay" onclick="closeSheet()"></div>

<div class="m-bottom-sheet" id="mCreateSheet">
    <div class="m-sheet-title">New Complaint <span onclick="closeSheet()" style="cursor:pointer;font-size:20px;">&times;</span></div>
    <form method="POST" action="process_hr_complaints.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create">
        
        <div class="m-form-group">
            <label class="m-form-label">Type *</label>
            <select name="complaint_type" class="m-form-select" required>
                <option value="">Select type</option>
                <option value="internal">Internal</option>
                <option value="external">External</option>
                <option value="anonymous">Anonymous</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Category *</label>
            <select name="category" class="m-form-select" required>
                <option value="">Select category</option>
                <option value="harassment">Harassment</option>
                <option value="discrimination">Discrimination</option>
                <option value="workplace_safety">Workplace Safety</option>
                <option value="policy_violation">Policy Violation</option>
                <option value="performance">Performance Issues</option>
                <option value="conduct">Misconduct</option>
                <option value="interpersonal_conflict">Interpersonal Conflict</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Severity *</label>
            <select name="severity" class="m-form-select" required>
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Priority</label>
            <select name="priority" class="m-form-select">
                <option value="low">Low</option>
                <option value="normal" selected>Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Complaint Date *</label>
            <input type="date" name="complaint_date" class="m-form-input" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Incident Date</label>
            <input type="date" name="incident_date" class="m-form-input">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Incident Location</label>
            <input type="text" name="incident_location" class="m-form-input" placeholder="Where did it occur?">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description *</label>
            <textarea name="description" class="m-form-textarea" required placeholder="Describe the complaint..."></textarea>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Witnesses</label>
            <textarea name="witnesses" class="m-form-textarea" placeholder="List any witnesses..."></textarea>
        </div>
        <button type="submit" class="m-btn-submit">Submit Complaint</button>
    </form>
</div>

<div class="m-bottom-sheet" id="mEditSheet">
    <div class="m-sheet-title">Update Complaint <span onclick="closeSheet()" style="cursor:pointer;font-size:20px;">&times;</span></div>
    <form method="POST" action="process_hr_complaints.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="complaint_id" id="mEditId">
        
        <div class="m-form-group">
            <label class="m-form-label">Status</label>
            <select name="status" id="mEditStatus" class="m-form-select">
                <option value="received">Received</option>
                <option value="open">Open</option>
                <option value="investigation">Investigation</option>
                <option value="in_progress">In Progress</option>
                <option value="escalated">Escalated</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
                <option value="dismissed">Dismissed</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Severity</label>
            <select name="severity" id="mEditSeverity" class="m-form-select">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Priority</label>
            <select name="priority" id="mEditPriority" class="m-form-select">
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Resolution</label>
            <textarea name="resolution" class="m-form-textarea" placeholder="Describe resolution..."></textarea>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Resolution Date</label>
            <input type="date" name="resolution_date" class="m-form-input">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Add Note</label>
            <textarea name="new_note" class="m-form-textarea" placeholder="Add a note..."></textarea>
        </div>
        <button type="submit" class="m-btn-submit">Update Complaint</button>
    </form>
</div>

<script>
function openSheet(type) {
    document.getElementById('mOverlay').classList.add('active');
    if (type === 'create') document.getElementById('mCreateSheet').classList.add('active');
    else document.getElementById('mEditSheet').classList.add('active');
}
function closeSheet() {
    document.getElementById('mOverlay').classList.remove('active');
    document.getElementById('mCreateSheet').classList.remove('active');
    document.getElementById('mEditSheet').classList.remove('active');
}
function openEditComplaint(id, status, priority) {
    document.getElementById('mEditId').value = id;
    document.getElementById('mEditStatus').value = status || 'open';
    document.getElementById('mEditPriority').value = priority || 'normal';
    openSheet('edit');
}
</script>
