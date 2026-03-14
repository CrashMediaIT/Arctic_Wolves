<?php
/**
 * PWA My Program - Mobile-native enrolled program view
 * Shows active enrollments with drill assignments and progress
 */
$user_id = $_SESSION['user_id'] ?? 0;

$enrollments = [];
try {
    $enrollments_stmt = $pdo->prepare("
        SELECT dpe.*, dpe.program_name, dpe.template_id, dpe.start_date, dpe.end_date
        FROM development_program_enrollments dpe
        WHERE dpe.athlete_id = ? AND dpe.status = 'active'
        ORDER BY dpe.enrolled_at DESC
    ");
    $enrollments_stmt->execute([$user_id]);
    $enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $enrollments = []; }

foreach ($enrollments as &$enrollment) {
    $enrollment['drills'] = [];
    try {
        $drills_stmt = $pdo->prepare("
            SELECT dpd.*, d.title as drill_title, d.description as drill_description
            FROM development_program_drills dpd
            JOIN drills d ON dpd.drill_id = d.id
            WHERE dpd.enrollment_id = ?
            ORDER BY dpd.sort_order, dpd.created_at
        ");
        $drills_stmt->execute([$enrollment['id']]);
        $enrollment['drills'] = $drills_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* ignore */ }
}
unset($enrollment);

$completed_programs = [];
try {
    $completed_stmt = $pdo->prepare("
        SELECT dpe.*, dpe.program_name,
               (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
        FROM development_program_enrollments dpe
        WHERE dpe.athlete_id = ? AND dpe.status IN ('completed', 'paused', 'cancelled')
        ORDER BY dpe.completed_at DESC, dpe.enrolled_at DESC
    ");
    $completed_stmt->execute([$user_id]);
    $completed_programs = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $completed_programs = []; }
?>
<style>
.m-myprog { padding: 16px; font-family: Inter, sans-serif; }
.m-myprog-header { margin-bottom: 16px; }
.m-myprog-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-myprog-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-myprog-enroll {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 16px; margin-bottom: 14px;
}
.m-myprog-enroll-name { font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 4px; }
.m-myprog-enroll-meta { font-size: 11px; color: #6B6B7B; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.m-myprog-enroll-meta span { display: flex; align-items: center; gap: 4px; }
.m-myprog-drill {
    background: #1E1E2E; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px; margin-bottom: 8px;
}
.m-myprog-drill-title { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 2px; }
.m-myprog-drill-desc { font-size: 11px; color: #A8A8B8; line-height: 1.4; }
.m-myprog-drill-status {
    display: inline-flex; align-items: center; gap: 4px; margin-top: 6px;
    font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 20px;
}
.m-myprog-drill-status.pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-myprog-drill-status.completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-myprog-drill-status.in_progress { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-myprog-section-title { font-size: 14px; font-weight: 600; color: #8B5CF6; margin: 20px 0 10px; }
.m-myprog-completed {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px 14px; margin-bottom: 8px;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;
}
.m-myprog-comp-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-myprog-comp-meta { font-size: 11px; color: #6B6B7B; margin-top: 2px; }
.m-myprog-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.m-myprog-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-myprog-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-myprog-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-myprog-drills-label { font-size: 12px; font-weight: 600; color: #8B5CF6; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
</style>

<div class="m-myprog">
    <div class="m-myprog-header">
        <h2 class="m-myprog-title"><i class="fas fa-clipboard-list" style="color:#10B981;margin-right:6px;"></i> My Program</h2>
        <p class="m-myprog-sub">Your enrolled programs and assigned drills</p>
    </div>

    <?php if (empty($enrollments)): ?>
    <div class="m-empty-state">
        <i class="fas fa-clipboard-list"></i>
        <p>You are not enrolled in any programs.</p>
        <a href="?page=personal_development_programs" style="color:#8B5CF6;font-size:13px;margin-top:8px;display:inline-block;text-decoration:none;">Browse Programs <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php else: ?>
    <?php foreach ($enrollments as $enrollment):
        $prog_name = $enrollment['program_name'] ?: 'Development Program';
    ?>
    <div class="m-myprog-enroll">
        <div class="m-myprog-enroll-name"><?= htmlspecialchars($prog_name) ?></div>
        <div class="m-myprog-enroll-meta">
            <span><i class="fas fa-calendar"></i> Since <?= date('M j, Y', strtotime($enrollment['enrolled_at'])) ?></span>
            <?php if ($enrollment['start_date']): ?>
            <span><i class="fas fa-play"></i> Start: <?= date('M j', strtotime($enrollment['start_date'])) ?></span>
            <?php endif; ?>
            <?php if ($enrollment['end_date']): ?>
            <span><i class="fas fa-flag-checkered"></i> End: <?= date('M j', strtotime($enrollment['end_date'])) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($enrollment['drills'])): ?>
        <div class="m-myprog-drills-label"><i class="fas fa-dumbbell"></i> Assigned Drills (<?= count($enrollment['drills']) ?>)</div>
        <?php foreach ($enrollment['drills'] as $drill):
            $drill_status = $drill['status'] ?? 'pending';
        ?>
        <div class="m-myprog-drill">
            <div class="m-myprog-drill-title"><?= htmlspecialchars($drill['drill_title'] ?? 'Untitled Drill') ?></div>
            <?php if (!empty($drill['drill_description'])): ?>
            <div class="m-myprog-drill-desc"><?= htmlspecialchars(mb_strimwidth($drill['drill_description'], 0, 120, '...')) ?></div>
            <?php endif; ?>
            <span class="m-myprog-drill-status <?= htmlspecialchars($drill_status) ?>">
                <i class="fas fa-<?= $drill_status === 'completed' ? 'check' : ($drill_status === 'in_progress' ? 'spinner' : 'clock') ?>"></i>
                <?= ucfirst(str_replace('_', ' ', $drill_status)) ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:16px;color:#6B6B7B;font-size:12px;">
            <i class="fas fa-inbox" style="display:block;font-size:20px;margin-bottom:6px;"></i>
            No drills assigned yet
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($completed_programs)): ?>
    <div class="m-myprog-section-title"><i class="fas fa-history"></i> Past Programs</div>
    <?php foreach ($completed_programs as $cp):
        $cp_display = $cp['program_name'] ?: 'Development Program';
        $badge_class = $cp['status'] === 'completed' ? 'completed' : ($cp['status'] === 'paused' ? 'paused' : 'cancelled');
    ?>
    <div class="m-myprog-completed">
        <div>
            <div class="m-myprog-comp-name"><?= htmlspecialchars($cp_display) ?></div>
            <div class="m-myprog-comp-meta">
                <?= date('M j, Y', strtotime($cp['enrolled_at'])) ?><?= $cp['completed_at'] ? ' — ' . date('M j, Y', strtotime($cp['completed_at'])) : '' ?>
                &bull; <?= (int)$cp['drill_count'] ?> drills
            </div>
        </div>
        <span class="m-myprog-badge m-myprog-badge-<?= $badge_class ?>"><?= ucfirst(htmlspecialchars($cp['status'])) ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
