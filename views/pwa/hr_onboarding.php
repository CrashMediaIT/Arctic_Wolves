<?php
/**
 * PWA HR Onboarding - Mobile-native new employee onboarding
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessHR) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>HR access required</div>';
    return;
}

$newUsers = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.role, u.created_at
        FROM users u
        WHERE u.is_active = 1 AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY u.created_at DESC LIMIT 20
    ");
    $stmt->execute();
    $newUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $newUsers = decryptUserRows($newUsers);
} catch (PDOException $e) { $newUsers = []; }

$onboardingRecords = [];
try {
    $stmt = $pdo->prepare("
        SELECT eo.id, eo.first_name, eo.last_name, eo.email, eo.role, eo.onboarding_status, eo.created_at
        FROM employee_onboarding eo
        WHERE eo.onboarding_status IN ('pending', 'in_progress')
        ORDER BY eo.created_at DESC LIMIT 20
    ");
    $stmt->execute();
    $onboardingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $onboardingRecords = decryptUserRows($onboardingRecords);
} catch (PDOException $e) { $onboardingRecords = []; }
?>
<style>
.m-onboard { padding: 16px; font-family: Inter, sans-serif; }
.m-onboard-header { margin-bottom: 16px; }
.m-onboard-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-onboard-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-onboard-summary {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px; padding: 20px; margin-bottom: 16px;
    text-align: center;
}
.m-onboard-summary-label { font-size: 12px; color: rgba(255,255,255,0.7); }
.m-onboard-summary-value { font-size: 28px; font-weight: 700; color: #fff; margin-top: 4px; }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-onboard-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-onboard-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-onboard-body { flex: 1; min-width: 0; }
.m-onboard-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-onboard-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-onboard-role {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    flex-shrink: 0;
}
.m-onboard-role-admin { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-onboard-role-coach { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-onboard-role-athlete { background: rgba(16,185,129,0.15); color: #10B981; }
.m-onboard-role-parent { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-onboard-role-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-onboard-checklist {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 20px;
}
.m-onboard-check-title { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 10px; }
.m-onboard-check-item {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 0; font-size: 12px; color: #A8A8B8;
}
.m-onboard-check-item i { font-size: 14px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-fab { position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px; border-radius: 50%; background: #6B46C1; color: #fff; border: none; box-shadow: 0 4px 12px rgba(107,70,193,0.4); display: flex; align-items: center; justify-content: center; z-index: 999; cursor: pointer; }
.m-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; }
.m-overlay.active { display: block; }
.m-bottom-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh; overflow-y: auto; z-index: 1001; padding: 20px; transform: translateY(100%); transition: transform 0.3s ease; }
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
.m-onboard-card { flex-wrap: wrap; }
</style>

<div class="m-onboard">
    <div class="m-onboard-header">
        <h2 class="m-onboard-title">Onboarding</h2>
        <p class="m-onboard-sub">New members in the last 30 days</p>
    </div>

    <div class="m-onboard-summary">
        <div class="m-onboard-summary-label">New Members (30 days)</div>
        <div class="m-onboard-summary-value"><?= count($newUsers) ?></div>
    </div>

    <div class="m-onboard-checklist">
        <div class="m-onboard-check-title"><i class="fas fa-clipboard-check" style="color:#8B5CF6;"></i> Onboarding Checklist</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#10B981;"></i> Account created &amp; activated</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#10B981;"></i> Role assigned</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#F59E0B;"></i> Contract signed</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#F59E0B;"></i> Equipment issued</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#6B6B7B;"></i> First session scheduled</div>
    </div>

    <?php if (!empty($onboardingRecords)): ?>
        <h3 class="m-section-title">Active Onboarding</h3>
        <?php foreach ($onboardingRecords as $rec): ?>
        <div class="m-onboard-card">
            <div class="m-onboard-avatar"><?= strtoupper(mb_substr($rec['first_name'] ?? '?', 0, 1)) ?></div>
            <div class="m-onboard-body">
                <div class="m-onboard-name"><?= htmlspecialchars(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')) ?></div>
                <div class="m-onboard-meta"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $rec['onboarding_status'] ?? ''))) ?> · <?= htmlspecialchars(ucwords(str_replace('_', ' ', $rec['role'] ?? ''))) ?></div>
            </div>
            <div class="m-card-actions" style="width:100%;">
                <form method="POST" action="process_onboarding.php" style="display:inline;" data-confirm="Mark as completed?">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="onboarding_id" value="<?= $rec['id'] ?>">
                    <button type="submit" class="m-btn-sm" style="background:rgba(16,185,129,0.15);color:#10B981;"><i class="fas fa-check"></i> Complete</button>
                </form>
                <form method="POST" action="process_onboarding.php" style="display:inline;" data-confirm="Cancel this onboarding?">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="onboarding_id" value="<?= $rec['id'] ?>">
                    <button type="submit" class="m-btn-sm" style="background:rgba(239,68,68,0.15);color:#EF4444;"><i class="fas fa-times"></i> Cancel</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3 class="m-section-title">Recent New Members</h3>
    <?php if (empty($newUsers)): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-plus"></i>
            No new members in the last 30 days
        </div>
    <?php else: ?>
        <?php foreach ($newUsers as $nu):
            $role = strtolower($nu['role'] ?? 'default');
            $roleClass = match($role) {
                'admin' => 'admin',
                'coach', 'head_coach', 'team_coach', 'health_coach' => 'coach',
                'athlete' => 'athlete',
                'parent' => 'parent',
                default => 'default',
            };
            $initial = strtoupper(mb_substr($nu['first_name'] ?? '?', 0, 1));
            $fullName = htmlspecialchars(($nu['first_name'] ?? '') . ' ' . ($nu['last_name'] ?? ''));
        ?>
        <div class="m-onboard-card">
            <div class="m-onboard-avatar"><?= $initial ?></div>
            <div class="m-onboard-body">
                <div class="m-onboard-name"><?= $fullName ?></div>
                <div class="m-onboard-meta">
                    <i class="fas fa-calendar" style="font-size:10px;"></i> Joined <?= date('M j, Y', strtotime($nu['created_at'])) ?>
                </div>
            </div>
            <span class="m-onboard-role m-onboard-role-<?= $roleClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="openSheet()"><i class="fas fa-plus" style="font-size:20px;"></i></button>

<div class="m-overlay" id="mOverlay" onclick="closeSheet()"></div>

<div class="m-bottom-sheet" id="mCreateSheet">
    <div class="m-sheet-title">Start Onboarding <span onclick="closeSheet()" style="cursor:pointer;font-size:20px;">&times;</span></div>
    <form method="POST" action="process_onboarding.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create">
        <div class="m-form-group">
            <label class="m-form-label">First Name *</label>
            <input type="text" name="first_name" class="m-form-input" required placeholder="John">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Last Name *</label>
            <input type="text" name="last_name" class="m-form-input" required placeholder="Smith">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Email *</label>
            <input type="email" name="email" class="m-form-input" required placeholder="john@example.com">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Phone</label>
            <input type="tel" name="phone" class="m-form-input" placeholder="(604) 555-0123">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Role *</label>
            <select name="role" class="m-form-select" required>
                <option value="">Select role</option>
                <option value="coach">Coach</option>
                <option value="head_coach">Head Coach</option>
                <option value="team_coach">Team Coach</option>
                <option value="health_coach">Health Coach</option>
                <option value="front_desk_staff">Front Desk Staff</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Employee Type *</label>
            <select name="employee_type" class="m-form-select" required>
                <option value="full_time">Full Time</option>
                <option value="part_time">Part Time</option>
                <option value="contract">Contract</option>
                <option value="seasonal">Seasonal</option>
                <option value="volunteer">Volunteer</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Start Date *</label>
            <input type="date" name="start_date" class="m-form-input" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Street Address *</label>
            <input type="text" name="street_address" class="m-form-input" required placeholder="123 Main St">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">City *</label>
            <input type="text" name="city" class="m-form-input" required placeholder="Vancouver">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Province *</label>
            <select name="province" class="m-form-select" required>
                <option value="BC">British Columbia</option>
                <option value="AB">Alberta</option>
                <option value="SK">Saskatchewan</option>
                <option value="MB">Manitoba</option>
                <option value="ON">Ontario</option>
                <option value="QC">Quebec</option>
                <option value="NS">Nova Scotia</option>
                <option value="NB">New Brunswick</option>
                <option value="PE">Prince Edward Island</option>
                <option value="NL">Newfoundland</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Postal Code *</label>
            <input type="text" name="postal_code" class="m-form-input" required placeholder="V6B 1A1">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Notes</label>
            <textarea name="notes" class="m-form-textarea" placeholder="Additional notes..."></textarea>
        </div>
        <button type="submit" class="m-btn-submit">Start Onboarding</button>
    </form>
</div>

<script>
function openSheet() {
    document.getElementById('mOverlay').classList.add('active');
    document.getElementById('mCreateSheet').classList.add('active');
}
function closeSheet() {
    document.getElementById('mOverlay').classList.remove('active');
    document.getElementById('mCreateSheet').classList.remove('active');
}
</script>
