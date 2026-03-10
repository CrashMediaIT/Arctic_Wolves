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
    gap: var(--space-6);
    margin-top: var(--space-5);
}
.dev-program-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl);
    padding: var(--space-6);
    transition: transform var(--transition-normal), box-shadow var(--transition-normal);
}
.dev-program-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}
.dev-program-card h3 {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-3);
}
.dev-program-card .program-icon {
    font-size: 48px;
    margin-bottom: var(--space-4);
    display: block;
}
.dev-program-card .program-icon.goalie { color: var(--info); }
.dev-program-card .program-icon.player { color: var(--success); }
.dev-program-card p {
    color: var(--text-dim);
    font-size: var(--font-size-base);
    line-height: 1.6;
    margin-bottom: var(--space-5);
}
.dev-program-card .btn-register {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: 0 var(--space-6);
    height: 45px;
    border-radius: var(--radius-lg);
    font-weight: var(--font-weight-bold);
    font-size: var(--font-size-base);
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all var(--transition-normal);
}
.dev-program-card .btn-register.available {
    background: var(--primary);
    color: var(--text-white);
}
.dev-program-card .btn-register.available:hover {
    background: var(--primary-hover);
    transform: translateY(-2px);
    box-shadow: var(--shadow-primary-hover);
}
.dev-program-card .btn-register.enrolled {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.3);
    cursor: default;
}
.dev-enrollment-info {
    margin-top: var(--space-3);
    padding: var(--space-3) var(--space-4);
    background: rgba(16, 185, 129, 0.08);
    border-radius: var(--radius-lg);
    font-size: var(--font-size-sm);
    color: var(--text-dim);
}
.dev-enrollment-info strong {
    color: var(--success);
}
.dev-program-price {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-3);
}
.dev-program-duration {
    font-size: var(--font-size-sm);
    color: var(--text-dim);
    margin-bottom: var(--space-2);
}
/* Empty state */
.dev-programs-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: var(--space-10);
    color: var(--text-dim);
}
.dev-programs-empty i {
    font-size: 48px;
    display: block;
    margin-bottom: var(--space-4);
    opacity: 0.5;
}
/* Completed programs section */
.dev-completed-section {
    margin-top: var(--space-8);
}
.dev-completed-section h3 {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-4);
}
.dev-completed-section h3 i {
    color: var(--text-dim);
    margin-right: var(--space-2);
}
.dev-completed-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl);
    padding: var(--space-4) var(--space-5);
    margin-bottom: var(--space-3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-3);
}
.dev-completed-card .completed-name {
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    font-size: 15px;
}
.dev-completed-card .completed-meta {
    font-size: var(--font-size-sm);
    color: var(--text-dim);
    margin-top: var(--space-1);
}
.dev-completed-card .badge {
    padding: 4px var(--space-3);
    border-radius: var(--radius-2xl);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
}
</style>

<div class="dev-programs-grid">
    <?php if (empty($dev_products)): ?>
    <div class="dev-programs-empty">
        <i class="fas fa-hockey-puck"></i>
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
        <span class="<?= $is_goalie ? 'icon-hockey-goalie' : 'icon-hockey-player' ?> program-icon <?= $is_goalie ? 'goalie' : 'player' ?>"></span>
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
<div class="dev-completed-section">
    <h3>
        <i class="fas fa-history"></i> Completed Programs
    </h3>
    <?php foreach ($completed_enrollments as $ce):
        $ce_display = $ce['program_name'] ?: ($ce['program_type'] === 'goalie_dev' ? 'Goalie Development' : 'Player Development');
    ?>
    <div class="dev-completed-card">
        <div>
            <div class="completed-name">
                <?= htmlspecialchars($ce_display) ?>
            </div>
            <div class="completed-meta">
                <?= date('M j, Y', strtotime($ce['enrolled_at'])) ?><?= $ce['completed_at'] ? ' — ' . date('M j, Y', strtotime($ce['completed_at'])) : '' ?>
                &bull; <?= (int)$ce['drill_count'] ?> drills
            </div>
        </div>
        <span class="badge badge-<?= $ce['status'] === 'completed' ? 'success' : ($ce['status'] === 'paused' ? 'warning' : 'danger') ?>">
            <?= ucfirst(htmlspecialchars($ce['status'])) ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
