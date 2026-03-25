<?php
/**
 * PWA Development Programs - Mobile-native browse & enroll
 * Shows available development programs with enrollment status
 */
$user_id = $_SESSION['user_id'] ?? 0;

$enrollments = [];
try {
    $enrollments_stmt = $pdo->prepare("
        SELECT dpe.*, dpe.program_name, dpe.template_id,
               (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
        FROM development_program_enrollments dpe
        WHERE dpe.athlete_id = ? AND dpe.status = 'active'
        ORDER BY dpe.enrolled_at DESC
    ");
    $enrollments_stmt->execute([$user_id]);
    $enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $enrollments = []; }

$completed_enrollments = [];
try {
    $completed_stmt = $pdo->prepare("
        SELECT dpe.*, dpe.program_name, dpe.template_id,
               (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
        FROM development_program_enrollments dpe
        WHERE dpe.athlete_id = ? AND dpe.status IN ('completed', 'paused', 'cancelled')
        ORDER BY dpe.completed_at DESC, dpe.enrolled_at DESC
    ");
    $completed_stmt->execute([$user_id]);
    $completed_enrollments = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $completed_enrollments = []; }

$active_template_ids = [];
foreach ($enrollments as $e) {
    if ($e['template_id']) $active_template_ids[] = (int)$e['template_id'];
}

$dev_products = [];
try {
    $dev_stmt = $pdo->query("SELECT * FROM training_session_templates WHERE is_dev_program = 1 AND is_active = 1 ORDER BY name");
    $dev_products = $dev_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $dev_products = []; }
?>
<style>
.m-devprog { padding: 16px; font-family: Inter, sans-serif; }
.m-devprog-header { margin-bottom: 16px; }
.m-devprog-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-devprog-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-devprog-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 16px; margin-bottom: 12px;
}
.m-devprog-name { font-size: 15px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-devprog-desc { font-size: 12px; color: #A8A8B8; margin-bottom: 10px; line-height: 1.5; }
.m-devprog-meta { font-size: 11px; color: #6B6B7B; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.m-devprog-price { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 10px; }
.m-devprog-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 600;
    text-decoration: none; border: none; cursor: pointer; min-height: 44px;
    font-family: Inter, sans-serif;
}
.m-devprog-btn-enroll { background: #6B46C1; color: #fff; }
.m-devprog-btn-enrolled { background: rgba(16,185,129,0.15); color: #10B981; border: 1px solid rgba(16,185,129,0.3); cursor: default; }
.m-devprog-enroll-info {
    margin-top: 8px; padding: 8px 12px; border-radius: 8px;
    background: rgba(16,185,129,0.08); font-size: 11px; color: #A8A8B8;
}
.m-devprog-enroll-info strong { color: #10B981; }
.m-devprog-section-title { font-size: 14px; font-weight: 600; color: #8B5CF6; margin: 20px 0 10px; }
.m-devprog-completed {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px 14px; margin-bottom: 8px;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;
}
.m-devprog-comp-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-devprog-comp-meta { font-size: 11px; color: #6B6B7B; margin-top: 2px; }
.m-devprog-badge {
    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
}
.m-devprog-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-devprog-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-devprog-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
</style>

<div class="m-devprog">
    <div class="m-devprog-header">
        <h2 class="m-devprog-title"><i class="fas fa-skating" style="color:#8B5CF6;margin-right:6px;"></i> Development Programs</h2>
        <p class="m-devprog-sub">Browse and enroll in available programs</p>
    </div>

    <?php if (empty($dev_products)): ?>
    <div class="m-empty-state">
        <i class="fas fa-hockey-puck"></i>
        <p>No development programs available at this time.</p>
    </div>
    <?php else: ?>
    <?php foreach ($dev_products as $dp):
        $is_enrolled = in_array((int)$dp['id'], $active_template_ids);
        $matching_enrollment = null;
        if ($is_enrolled) {
            foreach ($enrollments as $e) {
                if ((int)$e['template_id'] === (int)$dp['id']) { $matching_enrollment = $e; break; }
            }
        }
    ?>
    <div class="m-devprog-card">
        <div class="m-devprog-name"><?= htmlspecialchars($dp['name']) ?></div>
        <?php if (!empty($dp['description'])): ?>
        <div class="m-devprog-desc"><?= htmlspecialchars($dp['description']) ?></div>
        <?php endif; ?>
        <?php if (!empty($dp['duration_weeks'])): ?>
        <div class="m-devprog-meta"><i class="fas fa-clock"></i> <?= (int)$dp['duration_weeks'] ?> week program</div>
        <?php endif; ?>

        <?php if ($is_enrolled && $matching_enrollment): ?>
            <span class="m-devprog-btn m-devprog-btn-enrolled"><i class="fas fa-check"></i> Currently Enrolled</span>
            <div class="m-devprog-enroll-info">
                <strong>Drills:</strong> <?= (int)$matching_enrollment['drill_count'] ?> assigned &bull;
                <strong>Since:</strong> <?= date('M j, Y', strtotime($matching_enrollment['enrolled_at'])) ?>
            </div>
        <?php else:
            $dp_type = stripos($dp['name'], 'goalie') !== false ? 'goalie_dev' : 'player_dev';
        ?>
            <div class="m-devprog-price"><?= $dp['price'] > 0 ? '$' . number_format($dp['price'], 2) : 'Free' ?></div>
            <form method="POST" action="process_booking.php" style="display:inline;">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="register_dev_program">
                <input type="hidden" name="program_type" value="<?= htmlspecialchars($dp_type) ?>">
                <input type="hidden" name="template_id" value="<?= (int)$dp['id'] ?>">
                <button type="submit" class="m-devprog-btn m-devprog-btn-enroll">
                    <i class="fas fa-shopping-cart"></i> Enroll<?= $dp['price'] > 0 ? ' & Pay' : '' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($completed_enrollments)): ?>
    <div class="m-devprog-section-title"><i class="fas fa-history"></i> Completed Programs</div>
    <?php foreach ($completed_enrollments as $ce):
        $ce_display = $ce['program_name'] ?: 'Development Program';
        $badge_class = $ce['status'] === 'completed' ? 'completed' : ($ce['status'] === 'paused' ? 'paused' : 'cancelled');
    ?>
    <div class="m-devprog-completed">
        <div>
            <div class="m-devprog-comp-name"><?= htmlspecialchars($ce_display) ?></div>
            <div class="m-devprog-comp-meta">
                <?= date('M j, Y', strtotime($ce['enrolled_at'])) ?><?= $ce['completed_at'] ? ' — ' . date('M j, Y', strtotime($ce['completed_at'])) : '' ?>
                &bull; <?= (int)$ce['drill_count'] ?> drills
            </div>
        </div>
        <span class="m-devprog-badge m-devprog-badge-<?= $badge_class ?>"><?= ucfirst(htmlspecialchars($ce['status'])) ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
