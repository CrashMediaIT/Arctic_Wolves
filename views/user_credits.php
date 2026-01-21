<?php
// views/user_credits.php - User store credits management
require_once __DIR__ . '/../security.php';

$viewing_user_id = $user_id;
$is_admin_view = false;

// Admin can search any user's credits
if ($user_role === 'admin' && isset($_GET['user_id'])) {
    $viewing_user_id = intval($_GET['user_id']);
    $is_admin_view = true;
}

// Get user info
$user_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$user_stmt->execute([$viewing_user_id]);
$viewing_user = $user_stmt->fetch();

if (!$viewing_user) {
    die('User not found');
}

// Get active credits (not expired, has remaining amount)
$active_credits_stmt = $pdo->prepare("
    SELECT uc.*, r.refund_reason, r.refund_date, b.session_id, s.title as session_name
    FROM user_credits uc
    LEFT JOIN refunds r ON uc.refund_id = r.id
    LEFT JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN sessions s ON b.session_id = s.id
    WHERE uc.user_id = ? 
    AND uc.remaining_amount > 0 
    AND (uc.expiry_date IS NULL OR uc.expiry_date >= CURDATE())
    ORDER BY uc.expiry_date ASC, uc.created_at ASC
");
$active_credits_stmt->execute([$viewing_user_id]);
$active_credits = $active_credits_stmt->fetchAll();

// Calculate total available credits
$total_credits = array_sum(array_column($active_credits, 'remaining_amount'));

// Get credit usage history
$usage_stmt = $pdo->prepare("
    SELECT b.id, b.created_at, b.credit_applied, s.title as session_name, s.session_date
    FROM bookings b
    LEFT JOIN sessions s ON b.session_id = s.id
    WHERE b.user_id = ? AND b.credit_applied > 0
    ORDER BY b.created_at DESC
    LIMIT 50
");
$usage_stmt->execute([$viewing_user_id]);
$credit_usage = $usage_stmt->fetchAll();

// Get expired credits
$expired_credits_stmt = $pdo->prepare("
    SELECT uc.*, r.refund_reason, r.refund_date
    FROM user_credits uc
    LEFT JOIN refunds r ON uc.refund_id = r.id
    WHERE uc.user_id = ? 
    AND (uc.remaining_amount <= 0 OR uc.expiry_date < CURDATE())
    ORDER BY uc.expiry_date DESC
    LIMIT 20
");
$expired_credits_stmt->execute([$viewing_user_id]);
$expired_credits = $expired_credits_stmt->fetchAll();

// Get all users for admin search
$all_users = [];
if ($user_role === 'admin') {
    $all_users_stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email 
        FROM users 
        WHERE role IN ('athlete', 'parent')
        ORDER BY first_name, last_name
    ");
    $all_users_stmt->execute();
    $all_users = $all_users_stmt->fetchAll();
}
?>

<style>
    .credits-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .page-header {
        margin-bottom: 30px;
    }
    
    .page-header h2 {
        color: white;
        font-size: 1.8rem;
        font-weight: 900;
        margin-bottom: 10px;
    }
    
    .page-header p {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.95rem;
    }
    
    .total-credits-card {
        background: linear-gradient(135deg, var(--primary) 0%, #e64500 100%);
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 30px;
        text-align: center;
    }
    
    .total-credits-card h3 {
        color: white;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 10px;
        opacity: 0.9;
    }
    
    .total-credits-amount {
        color: white;
        font-size: 3rem;
        font-weight: 900;
        margin: 10px 0;
    }
    
    .card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
    }
    
    .card h3 {
        color: white;
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .card h3 i {
        color: var(--primary);
    }
    
    .credits-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .credits-table th {
        background: rgba(255, 77, 0, 0.1);
        color: var(--primary);
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
        border-bottom: 2px solid var(--primary);
    }
    
    .credits-table td {
        padding: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.9rem;
    }
    
    .credits-table tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    
    .badge {
        padding: 5px 12px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .badge-success {
        background: #10b981;
        color: white;
    }
    
    .badge-warning {
        background: #f59e0b;
        color: white;
    }
    
    .badge-info {
        background: #3b82f6;
        color: white;
    }
    
    .badge-danger {
        background: #ef4444;
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: rgba(255, 255, 255, 0.5);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }
    
    .empty-state h3 {
        font-size: 1.3rem;
        margin-bottom: 10px;
    }
    
    .user-search {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        padding: 12px;
        width: 100%;
        max-width: 400px;
        color: white;
        font-size: 1rem;
        margin-bottom: 20px;
    }
    
    .user-search:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    @media (max-width: 768px) {
        .credits-table {
            font-size: 0.85rem;
        }
        
        .credits-table th,
        .credits-table td {
            padding: 10px 8px;
        }
        
        .total-credits-amount {
            font-size: 2.5rem;
        }
    }
</style>

<div class="dash-content credits-container">
    <div class="page-header">
        <h2><i class="fas fa-wallet"></i> Store Credits</h2>
        <?php if ($is_admin_view): ?>
            <p>Viewing credits for: <?= htmlspecialchars($viewing_user['first_name'] . ' ' . $viewing_user['last_name']) ?> (<?= htmlspecialchars($viewing_user['email']) ?>)</p>
        <?php else: ?>
            <p>Your available store credits and usage history</p>
        <?php endif; ?>
    </div>
    
    <?php if ($user_role === 'admin'): ?>
        <div class="card">
            <h3><i class="fas fa-search"></i> Search User Credits</h3>
            <select class="user-search" onchange="if(this.value) window.location.href='dashboard.php?page=user_credits&user_id='+this.value">
                <option value="">-- Select a user --</option>
                <?php foreach ($all_users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $u['id'] == $viewing_user_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' - ' . $u['email']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    
    <div class="total-credits-card">
        <h3>Total Available Credits</h3>
        <div class="total-credits-amount">$<?= number_format($total_credits, 2) ?></div>
        <p style="color: white; opacity: 0.8; margin-top: 10px;">
            Ready to use on your next booking
        </p>
    </div>
    
    <!-- Active Credits -->
    <div class="card">
        <h3><i class="fas fa-coins"></i> Active Credits</h3>
        
        <?php if (empty($active_credits)): ?>
            <div class="empty-state">
                <i class="fas fa-wallet"></i>
                <h3>No Active Credits</h3>
                <p>You don't have any active store credits at the moment.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="credits-table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Original Amount</th>
                            <th>Used</th>
                            <th>Remaining</th>
                            <th>Expiry Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_credits as $credit): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-info"><?= ucfirst($credit['credit_source']) ?></span>
                                    <?php if ($credit['session_name']): ?>
                                        <br><small style="color: rgba(255,255,255,0.5);"><?= htmlspecialchars($credit['session_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>$<?= number_format($credit['credit_amount'], 2) ?></td>
                                <td>$<?= number_format($credit['used_amount'], 2) ?></td>
                                <td><strong style="color: #10b981;">$<?= number_format($credit['remaining_amount'], 2) ?></strong></td>
                                <td>
                                    <?php if ($credit['expiry_date']): ?>
                                        <?= date('M j, Y', strtotime($credit['expiry_date'])) ?>
                                        <?php
                                        $days_until_expiry = (strtotime($credit['expiry_date']) - time()) / (60 * 60 * 24);
                                        if ($days_until_expiry <= 30):
                                        ?>
                                            <br><span class="badge badge-warning">Expires soon</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: rgba(255,255,255,0.5);">No expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="color: rgba(255,255,255,0.7);">
                                        <?= htmlspecialchars($credit['notes'] ?: '-') ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Usage History -->
    <div class="card">
        <h3><i class="fas fa-history"></i> Credit Usage History</h3>
        
        <?php if (empty($credit_usage)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>No Usage History</h3>
                <p>You haven't used any store credits yet.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="credits-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Session</th>
                            <th>Session Date</th>
                            <th>Credit Applied</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credit_usage as $usage): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($usage['created_at'])) ?></td>
                                <td><?= htmlspecialchars($usage['session_name'] ?: 'N/A') ?></td>
                                <td>
                                    <?= $usage['session_date'] ? date('M j, Y', strtotime($usage['session_date'])) : '-' ?>
                                </td>
                                <td><strong style="color: var(--primary);">-$<?= number_format($usage['credit_applied'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Expired/Depleted Credits -->
    <?php if (!empty($expired_credits)): ?>
        <div class="card">
            <h3><i class="fas fa-clock"></i> Expired/Depleted Credits</h3>
            
            <div style="overflow-x: auto;">
                <table class="credits-table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Original Amount</th>
                            <th>Used</th>
                            <th>Status</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expired_credits as $credit): ?>
                            <tr style="opacity: 0.6;">
                                <td><span class="badge badge-info"><?= ucfirst($credit['credit_source']) ?></span></td>
                                <td>$<?= number_format($credit['credit_amount'], 2) ?></td>
                                <td>$<?= number_format($credit['used_amount'], 2) ?></td>
                                <td>
                                    <?php if ($credit['remaining_amount'] <= 0): ?>
                                        <span class="badge badge-success">Fully Used</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($credit['expiry_date']): ?>
                                        <?= date('M j, Y', strtotime($credit['expiry_date'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
