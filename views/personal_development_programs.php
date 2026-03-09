<?php
/**
 * Personal Development Programs - Browse & Register
 * Shows all available development programs from training_session_templates (is_dev_program = 1)
 * Athletes can enroll multiple times (after previous programs complete)
 */

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Get user's current ACTIVE enrollments
$enrollments_stmt = $pdo->prepare("
    SELECT dpe.*, dpe.program_name, dpe.template_id,
           (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
    FROM development_program_enrollments dpe
    WHERE dpe.athlete_id = ? AND dpe.status = 'active'
    ORDER BY dpe.enrolled_at DESC
");
$enrollments_stmt->execute([$user_id]);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all completed enrollments for history
$completed_stmt = $pdo->prepare("
    SELECT dpe.*, dpe.program_name, dpe.template_id,
           (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
    FROM development_program_enrollments dpe
    WHERE dpe.athlete_id = ? AND dpe.status IN ('completed', 'paused', 'cancelled')
    ORDER BY dpe.completed_at DESC, dpe.enrolled_at DESC
");
$completed_stmt->execute([$user_id]);
$completed_enrollments = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build map of active template_ids for this user
$active_template_ids = [];
foreach ($enrollments as $e) {
    if ($e['template_id']) $active_template_ids[] = (int)$e['template_id'];
}

// Get all dev program products from training_session_templates
$dev_products = [];
try {
    $dev_stmt = $pdo->query("SELECT * FROM training_session_templates WHERE is_dev_program = 1 AND is_active = 1 ORDER BY name");
    $dev_products = $dev_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* column may not exist */ }

// Determine program_type for each product (based on name containing 'goalie' or default to player_dev)
function getDevProgramType($name) {
    return stripos($name, 'goalie') !== false ? 'goalie_dev' : 'player_dev';
}
?>

<style>
.dev-programs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 24px;
    margin-top: 20px;
}
.dev-program-card {
    background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 12px;
    padding: 28px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.dev-program-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}
.dev-program-card h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 12px;
}
.dev-program-card .program-icon {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
}
.dev-program-card .program-icon.goalie { color: #3b82f6; }
.dev-program-card .program-icon.player { color: #10b981; }
.dev-program-card p {
    color: var(--text-dim, #94a3b8);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 20px;
}
.dev-program-card .btn-register {
    display: inline-block;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.dev-program-card .btn-register.available {
    background: var(--primary, #6B46C1);
    color: #fff;
}
.dev-program-card .btn-register.available:hover {
    opacity: 0.9;
}
.dev-program-card .btn-register.enrolled {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
    cursor: default;
}
.dev-enrollment-info {
    margin-top: 12px;
    padding: 12px 16px;
    background: rgba(16, 185, 129, 0.08);
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-dim, #94a3b8);
}
.dev-enrollment-info strong {
    color: #10b981;
}
.dev-program-price {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 12px;
}
.dev-program-duration {
    font-size: 13px;
    color: var(--text-dim);
    margin-bottom: 8px;
}
</style>

<div class="dev-programs-grid">
    <?php if (empty($dev_products)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-dim);">
        <i class="fas fa-hockey-puck" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.5;"></i>
        <p>No development programs available at this time.</p>
    </div>
    <?php else: ?>
    <?php foreach ($dev_products as $dp):
        $dp_type = getDevProgramType($dp['name']);
        $is_goalie = $dp_type === 'goalie_dev';
        $is_enrolled = in_array((int)$dp['id'], $active_template_ids);
        // Get active enrollment for this template if exists
        $matching_enrollment = null;
        if ($is_enrolled) {
            foreach ($enrollments as $e) {
                if ((int)$e['template_id'] === (int)$dp['id']) { $matching_enrollment = $e; break; }
            }
        }
    ?>
    <div class="dev-program-card">
        <i class="fas <?= $is_goalie ? 'fa-shield-alt' : 'fa-hockey-puck' ?> program-icon <?= $is_goalie ? 'goalie' : 'player' ?>"></i>
        <h3><?= htmlspecialchars($dp['name']) ?></h3>
        <?php if (!empty($dp['description'])): ?>
        <p><?= htmlspecialchars($dp['description']) ?></p>
        <?php endif; ?>
        <?php if (!empty($dp['duration_weeks'])): ?>
        <div class="dev-program-duration"><i class="fas fa-clock"></i> <?= (int)$dp['duration_weeks'] ?> week program</div>
        <?php endif; ?>
        
        <?php if ($is_enrolled && $matching_enrollment): ?>
            <span class="btn-register enrolled"><i class="fas fa-check"></i> Currently Enrolled</span>
            <div class="dev-enrollment-info">
                <strong>Status:</strong> <?= ucfirst(htmlspecialchars($matching_enrollment['status'])) ?> &bull;
                <strong>Drills:</strong> <?= (int)$matching_enrollment['drill_count'] ?> assigned &bull;
                <strong>Since:</strong> <?= date('M j, Y', strtotime($matching_enrollment['enrolled_at'])) ?>
            </div>
        <?php else: ?>
            <div class="dev-program-price">
                <?= $dp['price'] > 0 ? '$' . number_format($dp['price'], 2) : 'Free' ?>
            </div>
            <a href="?page=booking" class="btn-register available">
                <i class="fas fa-shopping-cart"></i> Enroll<?= $dp['price'] > 0 ? ' & Pay' : '' ?> on Booking Page
            </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!empty($completed_enrollments)): ?>
<div style="margin-top: 32px;">
    <h3 style="font-size:18px;font-weight:700;color:var(--text-white,#e2e8f0);margin-bottom:16px;">
        <i class="fas fa-history" style="color:var(--text-dim);margin-right:8px;"></i> Completed Programs
    </h3>
    <?php foreach ($completed_enrollments as $ce):
        $ce_display = $ce['program_name'] ?: ($ce['program_type'] === 'goalie_dev' ? 'Goalie Development' : 'Player Development');
    ?>
    <div style="background:var(--bg-card,#1a1a2e);border:1px solid var(--border,#2d2d44);border-radius:12px;padding:16px 20px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-weight:700;color:var(--text-white,#e2e8f0);font-size:15px;">
                <?= htmlspecialchars($ce_display) ?>
            </div>
            <div style="font-size:12px;color:var(--text-dim);margin-top:4px;">
                <?= date('M j, Y', strtotime($ce['enrolled_at'])) ?><?= $ce['completed_at'] ? ' — ' . date('M j, Y', strtotime($ce['completed_at'])) : '' ?>
                &bull; <?= (int)$ce['drill_count'] ?> drills
            </div>
        </div>
        <span style="padding:3px 12px;border-radius:10px;font-size:11px;font-weight:600;
              background:<?= $ce['status'] === 'completed' ? 'rgba(16,185,129,0.15)' : ($ce['status'] === 'paused' ? 'rgba(245,158,11,0.15)' : 'rgba(239,68,68,0.15)') ?>;
              color:<?= $ce['status'] === 'completed' ? '#10b981' : ($ce['status'] === 'paused' ? '#F59E0B' : '#EF4444') ?>;">
            <?= ucfirst(htmlspecialchars($ce['status'])) ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
