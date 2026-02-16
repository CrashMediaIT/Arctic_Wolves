<?php
/**
 * PWA HR Time Tracking - Mobile-native admin time tracking overview
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$todayHours = 0;
$weekHours = 0;

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_hours), 0) FROM time_entries WHERE DATE(clock_in_time) = CURDATE()");
    $stmt->execute();
    $todayHours = round((float)$stmt->fetchColumn(), 1);
} catch (PDOException $e) { $todayHours = 0; }

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_hours), 0) FROM time_entries WHERE clock_in_time >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)");
    $stmt->execute();
    $weekHours = round((float)$stmt->fetchColumn(), 1);
} catch (PDOException $e) { $weekHours = 0; }

$entries = [];
try {
    $stmt = $pdo->prepare("
        SELECT te.id, te.clock_in_time, te.clock_out_time, te.total_hours,
               u.first_name, u.last_name
        FROM time_entries te
        LEFT JOIN users u ON u.id = te.user_id
        ORDER BY te.clock_in_time DESC LIMIT 20
    ");
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $entries = []; }

$activeShift = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM staff_shifts WHERE staff_id = ? AND shift_date = CURDATE() AND status = 'active'");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $activeShift = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $activeShift = null; }
?>
<style>
.m-hrtime { padding: 16px; font-family: Inter, sans-serif; }
.m-hrtime-header { margin-bottom: 16px; }
.m-hrtime-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-hrtime-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-hrtime-kpi { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-hrtime-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-hrtime-stat-icon { font-size: 16px; margin-bottom: 6px; }
.m-hrtime-stat-value { font-size: 28px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-hrtime-stat-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-hrtime-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-hrtime-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-hrtime-body { flex: 1; min-width: 0; }
.m-hrtime-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-hrtime-times { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-hrtime-hours { font-size: 14px; font-weight: 700; color: #8B5CF6; flex-shrink: 0; }
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
.m-form-input { background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box; font-size: 14px; font-family: Inter, sans-serif; }
.m-btn-submit { background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px; font-weight: 600; width: 100%; font-size: 14px; cursor: pointer; margin-top: 8px; }
.m-btn-danger { background: #EF4444; }
.m-clock-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-clock-btn { padding: 14px; border-radius: 12px; border: none; font-weight: 600; font-size: 14px; cursor: pointer; min-height: 44px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.m-card-actions { display: flex; gap: 8px; margin-top: 8px; }
.m-btn-sm { font-size: 11px; padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; min-height: 28px; font-weight: 500; }
.m-status-msg { padding: 10px; border-radius: 10px; font-size: 13px; text-align: center; margin-bottom: 12px; display: none; }
</style>

<div class="m-hrtime">
    <div class="m-hrtime-header">
        <h2 class="m-hrtime-title">Time Tracking</h2>
        <p class="m-hrtime-sub">All staff time entries</p>
    </div>

    <div class="m-hrtime-kpi">
        <div class="m-hrtime-stat">
            <div class="m-hrtime-stat-icon" style="color:#10B981;"><i class="fas fa-clock"></i></div>
            <div class="m-hrtime-stat-value"><?= $todayHours ?>h</div>
            <div class="m-hrtime-stat-label">Today Total</div>
        </div>
        <div class="m-hrtime-stat">
            <div class="m-hrtime-stat-icon" style="color:#3B82F6;"><i class="fas fa-calendar-week"></i></div>
            <div class="m-hrtime-stat-value"><?= $weekHours ?>h</div>
            <div class="m-hrtime-stat-label">This Week</div>
        </div>
    </div>

    <div class="m-clock-actions">
        <?php if (!$activeShift): ?>
            <button class="m-clock-btn" style="background:#10B981;color:#fff;grid-column:span 2;" onclick="clockAction('clock_in')">
                <i class="fas fa-sign-in-alt"></i> Clock In
            </button>
        <?php else: ?>
            <?php if (empty($activeShift['lunch_start'])): ?>
                <button class="m-clock-btn" style="background:#F59E0B;color:#fff;" onclick="clockAction('start_lunch')">
                    <i class="fas fa-utensils"></i> Start Lunch
                </button>
            <?php elseif (empty($activeShift['lunch_end'])): ?>
                <button class="m-clock-btn" style="background:#3B82F6;color:#fff;" onclick="clockAction('end_lunch')">
                    <i class="fas fa-utensils"></i> End Lunch
                </button>
            <?php else: ?>
                <button class="m-clock-btn" style="background:rgba(168,168,184,0.15);color:#A8A8B8;" disabled>
                    <i class="fas fa-check"></i> Lunch Done
                </button>
            <?php endif; ?>
            <button class="m-clock-btn" style="background:#EF4444;color:#fff;" onclick="clockAction('end_shift')">
                <i class="fas fa-sign-out-alt"></i> Clock Out
            </button>
        <?php endif; ?>
    </div>
    <div class="m-status-msg" id="mClockMsg"></div>

    <h3 class="m-section-title">Recent Entries</h3>
    <?php if (empty($entries)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clock"></i>
            No time entries found
        </div>
    <?php else: ?>
        <?php foreach ($entries as $entry):
            $staffName = htmlspecialchars(trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?: 'Unknown');
            $initial = strtoupper(mb_substr($entry['first_name'] ?? '?', 0, 1));
            $inTime = $entry['clock_in_time'] ? date('M j, g:i A', strtotime($entry['clock_in_time'])) : '--';
            $outTime = $entry['clock_out_time'] ? date('g:i A', strtotime($entry['clock_out_time'])) : 'Active';
            $hours = $entry['total_hours'] !== null ? number_format((float)$entry['total_hours'], 1) . 'h' : 'Active';
        ?>
        <div class="m-hrtime-card">
            <div class="m-hrtime-avatar"><?= $initial ?></div>
            <div class="m-hrtime-body">
                <div class="m-hrtime-name"><?= $staffName ?></div>
                <div class="m-hrtime-times">
                    <i class="fas fa-sign-in-alt" style="color:#10B981;font-size:10px;"></i> <?= $inTime ?>
                    · <i class="fas fa-sign-out-alt" style="color:#EF4444;font-size:10px;"></i> <?= $outTime ?>
                </div>
            </div>
            <div class="m-hrtime-hours"><?= $hours ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="openSheet()"><i class="fas fa-clock" style="font-size:20px;"></i></button>
<div class="m-overlay" id="mOverlay" onclick="closeSheet()"></div>
<div class="m-bottom-sheet" id="mShiftSheet">
    <div class="m-sheet-title">Shift Actions <span onclick="closeSheet()" style="cursor:pointer;font-size:20px;">&times;</span></div>
    <div id="mShiftStatus" style="padding:12px;background:#0A0A0F;border-radius:10px;margin-bottom:14px;font-size:13px;color:#A8A8B8;">
        <?php if ($activeShift): ?>
            <div style="color:#10B981;font-weight:600;margin-bottom:4px;">&#9679; Currently Clocked In</div>
            <div>Since: <?= date('g:i A', strtotime($activeShift['clock_in'])) ?></div>
            <?php if (!empty($activeShift['lunch_start']) && empty($activeShift['lunch_end'])): ?>
                <div style="color:#F59E0B;margin-top:4px;">On lunch break</div>
            <?php endif; ?>
        <?php else: ?>
            <div style="color:#6B6B7B;">Not clocked in today</div>
        <?php endif; ?>
    </div>
    <div style="display:grid;gap:10px;">
        <button class="m-btn-submit" style="background:#10B981;" onclick="clockAction('clock_in')"><i class="fas fa-sign-in-alt"></i> Clock In</button>
        <button class="m-btn-submit" style="background:#F59E0B;" onclick="clockAction('start_lunch')"><i class="fas fa-utensils"></i> Start Lunch</button>
        <button class="m-btn-submit" style="background:#3B82F6;" onclick="clockAction('end_lunch')"><i class="fas fa-utensils"></i> End Lunch</button>
        <button class="m-btn-submit" style="background:#EF4444;" onclick="clockAction('end_shift')"><i class="fas fa-sign-out-alt"></i> Clock Out</button>
    </div>
</div>

<script>
const mCsrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const mShiftId = <?= json_encode($activeShift['id'] ?? null) ?>;

function openSheet() {
    document.getElementById('mOverlay').classList.add('active');
    document.getElementById('mShiftSheet').classList.add('active');
}
function closeSheet() {
    document.getElementById('mOverlay').classList.remove('active');
    document.getElementById('mShiftSheet').classList.remove('active');
}
function clockAction(action) {
    const body = { action: action, csrf_token: mCsrfToken };
    if (mShiftId) body.shift_id = mShiftId;
    const msgEl = document.getElementById('mClockMsg');
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        msgEl.style.display = 'block';
        if (data.success) {
            msgEl.style.background = 'rgba(16,185,129,0.15)';
            msgEl.style.color = '#10B981';
            msgEl.textContent = data.message || 'Success';
            location.reload();
        } else {
            msgEl.style.background = 'rgba(239,68,68,0.15)';
            msgEl.style.color = '#EF4444';
            msgEl.textContent = data.message || 'Error';
        }
    })
    .catch(() => {
        msgEl.style.display = 'block';
        msgEl.style.background = 'rgba(239,68,68,0.15)';
        msgEl.style.color = '#EF4444';
        msgEl.textContent = 'Network error';
    });
}
</script>
