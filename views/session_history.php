<?php
/**
 * Session History
 * View past training sessions and bookings
 */

require_once __DIR__ . '/../security.php';

$viewing_user_id = $user_id;
$is_parent = ($user_role === 'parent');

// Allow parents to view athlete history
if ($is_parent && isset($_GET['athlete_id'])) {
    $verify_stmt = $pdo->prepare("SELECT athlete_id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
    $verify_stmt->execute([$user_id, intval($_GET['athlete_id'])]);
    if ($verify_stmt->fetch()) {
        $viewing_user_id = intval($_GET['athlete_id']);
    }
}

// Get session history
$history_stmt = $pdo->prepare("
    SELECT s.*, b.id as booking_id, b.amount_paid, b.created_at as booked_at,
           ag.name as age_group_name, sl.name as skill_level_name
    FROM bookings b
    INNER JOIN sessions s ON b.session_id = s.id
    LEFT JOIN age_groups ag ON s.age_group_id = ag.id
    LEFT JOIN skill_levels sl ON s.skill_level_id = sl.id
    WHERE (b.user_id = ? OR b.booked_for_user_id = ?) AND b.status = 'paid'
    AND s.session_date < CURDATE()
    ORDER BY s.session_date DESC, s.session_time DESC
    LIMIT 100
");
$history_stmt->execute([$viewing_user_id, $viewing_user_id]);
$history = $history_stmt->fetchAll();

// Calculate totals
$total_sessions = count($history);
$total_spent = array_sum(array_column($history, 'amount_paid'));
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
        margin-bottom: 10px;
    }
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-card {
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 8px;
        padding: 20px;
        color: #fff;
    }
    .summary-value {
        font-size: 32px;
        font-weight: 900;
        margin-bottom: 5px;
    }
    .summary-label {
        font-size: 13px;
        opacity: 0.9;
    }
    .history-table {
        width: 100%;
        border-collapse: collapse;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
    }
    .history-table thead {
        background: #06080b;
    }
    .history-table th {
        text-align: left;
        padding: 15px;
        color: #94a3b8;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 700;
    }
    .history-table td {
        padding: 15px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
    }
    .history-table tr:last-child td {
        border-bottom: none;
    }
    .history-table tr:hover {
        background: rgba(255, 77, 0, 0.05);
    }
    .session-type-badge {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    .empty-state i {
        font-size: 64px;
        color: #64748b;
        opacity: 0.3;
        margin-bottom: 20px;
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-history"></i> Session History
    </h1>
</div>

<?php if ($total_sessions > 0): ?>
    <div class="stats-summary">
        <div class="summary-card">
            <div class="summary-value"><?= $total_sessions ?></div>
            <div class="summary-label">Total Sessions Attended</div>
        </div>
        <div class="summary-card">
            <div class="summary-value">$<?= number_format($total_spent, 2) ?></div>
            <div class="summary-label">Total Invested</div>
        </div>
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Session</th>
                <th>Type</th>
                <th>Location</th>
                <th>Details</th>
                <th>Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $session): ?>
                <tr>
                    <td style="white-space: nowrap;">
                        <?= date('M d, Y', strtotime($session['session_date'])) ?><br>
                        <small style="color: #64748b;"><?= date('g:i A', strtotime($session['session_time'])) ?></small>
                    </td>
                    <td style="font-weight: 600;">
                        <?= htmlspecialchars($session['title']) ?>
                    </td>
                    <td>
                        <span class="session-type-badge">
                            <?= htmlspecialchars($session['session_type']) ?>
                        </span>
                    </td>
                    <td>
                        <?= htmlspecialchars($session['arena']) ?><br>
                        <small style="color: #64748b;"><?= htmlspecialchars($session['city']) ?></small>
                    </td>
                    <td style="font-size: 13px; color: #94a3b8;">
                        <?php if ($session['age_group_name']): ?>
                            <?= htmlspecialchars($session['age_group_name']) ?>
                        <?php endif; ?>
                        <?php if ($session['skill_level_name']): ?>
                            • <?= htmlspecialchars($session['skill_level_name']) ?>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight: 700; color: var(--primary);">
                        $<?= number_format($session['amount_paid'], 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Session History</h2>
        <p style="color: #64748b;">Your completed sessions will appear here</p>
    </div>
<?php endif; ?>
