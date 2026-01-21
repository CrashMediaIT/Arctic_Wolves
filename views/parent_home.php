<?php
/**
 * Parent Dashboard View
 * Shows all managed athletes and their upcoming sessions
 */

require_once __DIR__ . '/../security.php';

// Get all managed athletes for this parent
$athletes_stmt = $pdo->prepare("
    SELECT u.*, ma.relationship, ma.can_book, ma.can_view_stats, ma.id as managed_id,
           (SELECT COUNT(*) FROM bookings b 
            INNER JOIN sessions s ON b.session_id = s.id 
            WHERE b.user_id = u.id AND b.status = 'paid' AND s.session_date >= CURDATE()) as upcoming_sessions,
           (SELECT COUNT(*) FROM notifications WHERE user_id = u.id AND read_status = 0) as unread_notifications
    FROM managed_athletes ma
    INNER JOIN users u ON ma.athlete_id = u.id
    WHERE ma.parent_id = ?
    ORDER BY u.first_name, u.last_name
");
$athletes_stmt->execute([$user_id]);
$athletes = $athletes_stmt->fetchAll();

// Get total upcoming sessions for all athletes
$total_upcoming = 0;
$total_bookings = 0;
foreach ($athletes as $athlete) {
    $total_upcoming += $athlete['upcoming_sessions'];
    
    // Count total bookings for each athlete
    $bookings_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'paid'");
    $bookings_stmt->execute([$athlete['id']]);
    $total_bookings += $bookings_stmt->fetchColumn();
}

// Get unread notifications count for parent
$notif_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
$notif_stmt->execute([$user_id]);
$unread_count = $notif_stmt->fetchColumn();
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
        margin: 0;
    }
    .add-athlete-btn {
        padding: 12px 24px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    .add-athlete-btn:hover {
        background: #e64500;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
    }
    .stat-value {
        font-size: 36px;
        font-weight: 900;
        color: var(--primary);
        display: block;
        margin-bottom: 5px;
    }
    .stat-label {
        font-size: 13px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 700;
    }
    .athletes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .athlete-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        transition: all 0.2s;
    }
    .athlete-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .athlete-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    .athlete-avatar {
        width: 60px;
        height: 60px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 24px;
        color: #fff;
    }
    .athlete-info {
        flex: 1;
    }
    .athlete-name {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
    }
    .athlete-meta {
        font-size: 13px;
        color: #64748b;
    }
    .athlete-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 15px;
        padding: 15px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
    }
    .athlete-stat-item {
        text-align: center;
    }
    .athlete-stat-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--primary);
        display: block;
    }
    .athlete-stat-label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
    }
    .athlete-actions {
        display: flex;
        gap: 10px;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 13px;
        flex: 1;
        transition: all 0.2s;
    }
    .btn-primary {
        background: var(--primary);
        color: #fff;
    }
    .btn-primary:hover {
        background: #e64500;
    }
    .btn-secondary {
        background: #1e293b;
        color: #fff;
    }
    .btn-secondary:hover {
        background: #334155;
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
    .empty-state h2 {
        font-size: 24px;
        color: #fff;
        margin-bottom: 10px;
    }
    .empty-state p {
        color: #64748b;
        margin-bottom: 20px;
    }
    .notification-badge {
        background: var(--primary);
        color: #fff;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
        margin-left: 5px;
    }
    @media (max-width: 768px) {
        .athletes-grid {
            grid-template-columns: 1fr;
        }
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
    }
</style>

<div class="dashboard-header">
    <h1 class="page-title">
        <i class="fas fa-users"></i> Manage Athletes
    </h1>
    <a href="?page=manage_athletes" class="add-athlete-btn">
        <i class="fas fa-plus-circle"></i> Add New Athlete
    </a>
</div>

<!-- Quick Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-value"><?= count($athletes) ?></span>
        <span class="stat-label">Managed Athletes</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $total_upcoming ?></span>
        <span class="stat-label">Upcoming Sessions</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $total_bookings ?></span>
        <span class="stat-label">Total Bookings</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= array_sum(array_column($athletes, 'unread_notifications')) ?></span>
        <span class="stat-label">Total Notifications</span>
    </div>
</div>

<!-- Athletes List -->
<?php if (empty($athletes)): ?>
    <div class="empty-state">
        <i class="fas fa-user-plus"></i>
        <h2>No Athletes Added</h2>
        <p>Start by adding an athlete to manage their training sessions and bookings</p>
        <a href="?page=manage_athletes" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Add Your First Athlete
        </a>
    </div>
<?php else: ?>
    <div class="athletes-grid">
        <?php foreach ($athletes as $athlete): ?>
            <?php
            // Calculate age
            $age = null;
            if ($athlete['birth_date']) {
                $birth = new DateTime($athlete['birth_date']);
                $today = new DateTime();
                $age = $birth->diff($today)->y;
            }
            
            // Get current team
            $team_stmt = $pdo->prepare("SELECT team_name FROM athlete_teams WHERE user_id = ? AND is_current = 1 ORDER BY created_at DESC LIMIT 1");
            $team_stmt->execute([$athlete['id']]);
            $team = $team_stmt->fetch();
            ?>
            
            <div class="athlete-card">
                <div class="athlete-header">
                    <div class="athlete-avatar">
                        <?= strtoupper(substr($athlete['first_name'], 0, 1)) ?>
                    </div>
                    <div class="athlete-info">
                        <div class="athlete-name">
                            <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                            <?php if ($athlete['unread_notifications'] > 0): ?>
                                <span class="notification-badge"><?= $athlete['unread_notifications'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="athlete-meta">
                            <?php if ($age): ?>
                                <?= $age ?> years old
                            <?php endif; ?>
                            <?php if ($athlete['position']): ?>
                                • <?= htmlspecialchars($athlete['position']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($team): ?>
                            <div class="athlete-meta">
                                <i class="fas fa-hockey-puck"></i> <?= htmlspecialchars($team['team_name']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="athlete-stats">
                    <div class="athlete-stat-item">
                        <span class="athlete-stat-value"><?= $athlete['upcoming_sessions'] ?></span>
                        <span class="athlete-stat-label">Upcoming</span>
                    </div>
                    <div class="athlete-stat-item">
                        <?php
                        $total_bookings_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'paid'");
                        $total_bookings_stmt->execute([$athlete['id']]);
                        $athlete_bookings = $total_bookings_stmt->fetchColumn();
                        ?>
                        <span class="athlete-stat-value"><?= $athlete_bookings ?></span>
                        <span class="athlete-stat-label">Total Bookings</span>
                    </div>
                </div>
                
                <div class="athlete-actions">
                    <a href="?page=schedule&athlete_id=<?= $athlete['id'] ?>" class="btn btn-primary" title="Book sessions for this athlete">
                        <i class="fas fa-calendar-plus"></i> Book Session
                    </a>
                    <a href="?page=session_history&athlete_id=<?= $athlete['id'] ?>" class="btn btn-secondary" title="View session history">
                        <i class="fas fa-history"></i> History
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
