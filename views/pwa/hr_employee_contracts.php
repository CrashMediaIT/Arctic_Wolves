<?php
/**
 * PWA HR Employee Contracts - Mobile-native contract management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$contracts = [];
try {
    $stmt = $pdo->prepare("
        SELECT ec.id, ec.contract_type, ec.start_date, ec.end_date, ec.status,
               u.first_name, u.last_name
        FROM employee_contracts ec
        LEFT JOIN users u ON u.id = ec.user_id
        ORDER BY ec.start_date DESC LIMIT 20
    ");
    $stmt->execute();
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $contracts = decryptUserRows($contracts);
} catch (PDOException $e) { $contracts = []; }

$staffList = [];
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE is_active = 1 ORDER BY first_name, last_name");
    $stmt->execute();
    $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $staffList = decryptUserRows($staffList);
} catch (PDOException $e) { $staffList = []; }
?>
<style>
.m-contracts { padding: 16px; font-family: Inter, sans-serif; }
.m-contracts-header { margin-bottom: 16px; }
.m-contracts-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-contracts-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-contract-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-contract-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(139,92,246,0.15); color: #8B5CF6;
}
.m-contract-body { flex: 1; min-width: 0; }
.m-contract-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-contract-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-contract-type {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6;
    display: inline-block; margin-top: 4px;
}
.m-contract-right { text-align: right; flex-shrink: 0; }
.m-contract-status {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-contract-status-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-contract-status-expired { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-contract-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-contract-status-terminated { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-contract-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-contract-dates { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-contract-card { flex-wrap: wrap; }
.m-card-actions { width: 100%; display: flex; gap: 8px; margin-top: 8px; }
.m-btn-sm { font-size: 11px; padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; min-height: 28px; font-weight: 500; }
.m-fab { position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px; border-radius: 50%; background: #6B46C1; color: #fff; border: none; box-shadow: 0 4px 12px rgba(107,70,193,0.4); display: flex; align-items: center; justify-content: center; z-index: 999; cursor: pointer; }
.m-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; }
.m-overlay.active { display: block; }
.m-bottom-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh; overflow-y: auto; z-index: 1001; padding: 20px; transform: translateY(100%); transition: transform 0.3s ease; }
.m-bottom-sheet.active { transform: translateY(0); }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; display: flex; justify-content: space-between; align-items: center; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { font-size: 12px; color: #A8A8B8; margin-bottom: 4px; display: block; }
.m-form-input, .m-form-select { background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box; font-size: 14px; font-family: Inter, sans-serif; }
.m-btn-submit { background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px; font-weight: 600; width: 100%; font-size: 14px; cursor: pointer; margin-top: 8px; }
.m-btn-danger { background: #EF4444; }
</style>

<div class="m-contracts">
    <div class="m-contracts-header">
        <h2 class="m-contracts-title">Employee Contracts</h2>
        <p class="m-contracts-sub"><?= count($contracts) ?> contract<?= count($contracts) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($contracts)): ?>
        <div class="m-empty-state">
            <i class="fas fa-file-contract"></i>
            No contracts found
        </div>
    <?php else: ?>
        <?php foreach ($contracts as $c):
            $status = strtolower($c['status'] ?? 'default');
            $statusClass = match($status) {
                'active' => 'active',
                'expired' => 'expired',
                'pending', 'draft' => 'pending',
                'terminated' => 'terminated',
                default => 'default',
            };
            $staffName = htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: 'Unknown');
            $startDate = $c['start_date'] ? date('M j, Y', strtotime($c['start_date'])) : 'N/A';
            $endDate = $c['end_date'] ? date('M j, Y', strtotime($c['end_date'])) : 'Ongoing';
        ?>
        <div class="m-contract-card">
            <div class="m-contract-icon">
                <i class="fas fa-file-contract"></i>
            </div>
            <div class="m-contract-body">
                <div class="m-contract-name"><?= $staffName ?></div>
                <div class="m-contract-meta"><?= $startDate ?> — <?= $endDate ?></div>
                <?php if (!empty($c['contract_type'])): ?>
                    <span class="m-contract-type"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $c['contract_type']))) ?></span>
                <?php endif; ?>
            </div>
            <div class="m-contract-right">
                <span class="m-contract-status m-contract-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-card-actions">
                <button class="m-btn-sm" style="background:rgba(239,68,68,0.15);color:#EF4444;" onclick="cancelContract(<?= (int)$c['id'] ?>)"><i class="fas fa-ban"></i> Cancel</button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="openSheet('create')"><i class="fas fa-plus" style="font-size:20px;"></i></button>
<div class="m-overlay" id="mOverlay" onclick="closeSheet()"></div>
<div class="m-bottom-sheet" id="mCreateSheet">
    <div class="m-sheet-title">New Contract <span onclick="closeSheet()" style="cursor:pointer;font-size:20px;">&times;</span></div>
    <form id="mCreateContractForm" onsubmit="submitCreateContract(event)">
        <div class="m-form-group">
            <label class="m-form-label">Employee Name *</label>
            <input type="text" name="employee_name" class="m-form-input" required placeholder="Full name">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Employee Email *</label>
            <input type="email" name="employee_email" class="m-form-input" required placeholder="Email address">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Contract Title</label>
            <input type="text" name="contract_title" class="m-form-input" value="Employment Contract">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Position</label>
            <input type="text" name="contract_data[position]" class="m-form-input" placeholder="e.g., Head Coach">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Start Date</label>
            <input type="date" name="contract_data[start_date]" class="m-form-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Salary</label>
            <input type="text" name="contract_data[salary]" class="m-form-input" placeholder="e.g., $50,000/year">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Pay Frequency</label>
            <select name="contract_data[pay_frequency]" class="m-form-select">
                <option value="bi-weekly">Bi-Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="semi-monthly">Semi-Monthly</option>
                <option value="weekly">Weekly</option>
            </select>
        </div>
        <div id="mCreateMsg" style="display:none;padding:8px;border-radius:8px;font-size:12px;margin-bottom:8px;"></div>
        <button type="submit" class="m-btn-submit">Create Contract</button>
    </form>
</div>

<script>
const mCsrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function openSheet(type) {
    document.getElementById('mOverlay').classList.add('active');
    document.getElementById('mCreateSheet').classList.add('active');
}
function closeSheet() {
    document.getElementById('mOverlay').classList.remove('active');
    document.getElementById('mCreateSheet').classList.remove('active');
}
function submitCreateContract(e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    fd.append('action', 'create');
    fd.append('csrf_token', mCsrfToken);
    const msgEl = document.getElementById('mCreateMsg');
    fetch('process_employee_contracts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            msgEl.style.display = 'block';
            if (data.success) {
                msgEl.style.background = 'rgba(16,185,129,0.15)';
                msgEl.style.color = '#10B981';
                msgEl.textContent = data.message || 'Contract created!';
                persistToast(data.message || 'Operation completed successfully', 'success');
                location.reload();
            } else {
                msgEl.style.background = 'rgba(239,68,68,0.15)';
                msgEl.style.color = '#EF4444';
                msgEl.textContent = data.message || 'Error creating contract';
            }
        })
        .catch(() => {
            msgEl.style.display = 'block';
            msgEl.style.background = 'rgba(239,68,68,0.15)';
            msgEl.style.color = '#EF4444';
            msgEl.textContent = 'Network error';
        });
}
function cancelContract(id) {
    if (!confirm('Cancel this contract? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('contract_id', id);
    fd.append('csrf_token', mCsrfToken);
    fetch('process_employee_contracts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            alert(data.message || (data.success ? 'Cancelled' : 'Error'));
            if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
        })
        .catch(() => alert('Network error'));
}
</script>
