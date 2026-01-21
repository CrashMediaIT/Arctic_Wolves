<?php
/**
 * Session Detail View
 * Show session information and practice plan details
 */

require_once __DIR__ . '/../security.php';

$session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($session_id == 0) {
    echo '<h2 style="color:#ef4444;">Invalid Session</h2>';
    exit;
}

// Get session details
$stmt = $pdo->prepare("
    SELECT s.*, pp.title as plan_title, pp.description as plan_description,
           b.id as booking_id, b.status as booking_status
    FROM sessions s
    LEFT JOIN practice_plans pp ON s.practice_plan_id = pp.id
    LEFT JOIN bookings b ON s.id = b.session_id AND b.user_id = ? AND b.status != 'cancelled'
    WHERE s.id = ?
");
$stmt->execute([$user_id, $session_id]);
$session = $stmt->fetch();

if (!$session) {
    echo '<h2 style="color:#ef4444;">Session Not Found</h2>';
    exit;
}

// Get practice plan drills if assigned
$drills = [];
if ($session['practice_plan_id']) {
    $drill_stmt = $pdo->prepare("
        SELECT ppd.*, d.title, d.description, d.coaching_points, d.equipment_needed
        FROM practice_plan_drills ppd
        INNER JOIN drills d ON ppd.drill_id = d.id
        WHERE ppd.plan_id = ?
        ORDER BY ppd.order_index ASC
    ");
    $drill_stmt->execute([$session['practice_plan_id']]);
    $drills = $drill_stmt->fetchAll();
}
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .session-meta {
        display: flex;
        gap: 20px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #94a3b8;
    }
    .meta-item i {
        color: var(--primary);
    }
    .content-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 20px;
    }
    .card-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .drill-item {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 20px;
        margin-bottom: 15px;
    }
    .drill-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    .drill-title {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
    }
    .drill-duration {
        background: rgba(255, 77, 0, 0.1);
        color: var(--primary);
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
    }
    .drill-description {
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 12px;
    }
    .drill-section {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #1e293b;
    }
    .drill-section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 8px;
    }
    .drill-section-content {
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.5;
    }
    .btn {
        padding: 12px 24px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
    }
    .btn-secondary {
        background: #1e293b;
    }
    .status-badge {
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-booked {
        background: rgba(0, 255, 136, 0.1);
        color: #00ff88;
    }
    .status-available {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($session['title']) ?></h1>
        <div class="session-meta">
            <span class="meta-item">
                <i class="fas fa-calendar"></i>
                <?= date('l, F j, Y', strtotime($session['session_date'])) ?>
            </span>
            <span class="meta-item">
                <i class="fas fa-clock"></i>
                <?= date('g:i A', strtotime($session['session_time'])) ?>
            </span>
            <span class="meta-item">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($session['arena']) ?>, <?= htmlspecialchars($session['city']) ?>
            </span>
            <span class="meta-item">
                <i class="fas fa-tag"></i>
                <?= htmlspecialchars($session['session_type']) ?>
            </span>
        </div>
    </div>
    <div style="text-align: right;">
        <?php if ($session['booking_id']): ?>
            <span class="status-badge status-booked">
                <i class="fas fa-check-circle"></i> Booked
            </span>
        <?php else: ?>
            <span class="status-badge status-available">
                Available
            </span>
            <br>
            <a href="?page=schedule" class="btn" style="margin-top: 10px;">
                <i class="fas fa-ticket-alt"></i> Book Now - $<?= number_format($session['price'], 2) ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($session['plan_title']): ?>
    <!-- Practice Plan Details -->
    <div class="content-card">
        <h2 class="card-title">
            <i class="fas fa-clipboard-list"></i>
            Practice Plan: <?= htmlspecialchars($session['plan_title']) ?>
        </h2>
        
        <?php if ($session['plan_description']): ?>
            <p style="color: #94a3b8; margin-bottom: 20px; line-height: 1.6;">
                <?= htmlspecialchars($session['plan_description']) ?>
            </p>
        <?php endif; ?>
        
        <?php if (!empty($drills)): ?>
            <h3 style="font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: 15px;">
                Drills (<?= count($drills) ?>)
            </h3>
            
            <?php foreach ($drills as $index => $drill): ?>
                <div class="drill-item">
                    <div class="drill-header">
                        <div>
                            <span style="color: var(--primary); font-weight: 700; margin-right: 10px;">
                                #<?= $index + 1 ?>
                            </span>
                            <span class="drill-title"><?= htmlspecialchars($drill['title']) ?></span>
                        </div>
                        <?php if ($drill['duration_minutes']): ?>
                            <span class="drill-duration">
                                <?= $drill['duration_minutes'] ?> min
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($drill['description']): ?>
                        <div class="drill-description">
                            <?= htmlspecialchars($drill['description']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($drill['coaching_points']): ?>
                        <div class="drill-section">
                            <div class="drill-section-title">
                                <i class="fas fa-lightbulb"></i> Coaching Points
                            </div>
                            <div class="drill-section-content">
                                <?= nl2br(htmlspecialchars($drill['coaching_points'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($drill['equipment_needed']): ?>
                        <div class="drill-section">
                            <div class="drill-section-title">
                                <i class="fas fa-tools"></i> Equipment Needed
                            </div>
                            <div class="drill-section-content">
                                <?= nl2br(htmlspecialchars($drill['equipment_needed'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($drill['notes']): ?>
                        <div class="drill-section">
                            <div class="drill-section-title">
                                <i class="fas fa-sticky-note"></i> Notes
                            </div>
                            <div class="drill-section-content">
                                <?= htmlspecialchars($drill['notes']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <!-- No Practice Plan Assigned -->
    <?php if ($session['session_plan']): ?>
        <div class="content-card">
            <h2 class="card-title">
                <i class="fas fa-file-alt"></i>
                Session Plan
            </h2>
            <div style="color: #94a3b8; line-height: 1.6; white-space: pre-wrap;">
                <?= htmlspecialchars($session['session_plan']) ?>
            </div>
        </div>
    <?php else: ?>
        <div class="content-card" style="text-align: center; padding: 40px;">
            <i class="fas fa-clipboard" style="font-size: 48px; color: #64748b; opacity: 0.3; margin-bottom: 15px;"></i>
            <p style="color: #64748b;">No practice plan has been assigned to this session yet.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-top: 30px;">
    <a href="?page=schedule" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Schedule
    </a>
</div>
