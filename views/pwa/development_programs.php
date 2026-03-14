<?php
/**
 * PWA Development Programs - Coach management view
 * Mobile-native view for coaches to manage enrolled athletes
 */

$allowed = false;
if (isset($user_roles_list) && is_array($user_roles_list)) {
    $allowed = array_intersect(['goalie_dev', 'player_dev', 'admin'], $user_roles_list);
}
if (!$allowed) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Development coach access required</div>';
    return;
}

$user_id = $_SESSION['user_id'] ?? 0;
$isGoalieDev = isset($user_roles_list) && in_array('goalie_dev', $user_roles_list);
$isPlayerDev = isset($user_roles_list) && in_array('player_dev', $user_roles_list);

$program_types = [];
if ($isGoalieDev || $isAdmin) $program_types[] = 'goalie_dev';
if ($isPlayerDev || $isAdmin) $program_types[] = 'player_dev';

$athletes = [];
if (!empty($program_types)) {
    $placeholders = implode(',', array_fill(0, count($program_types), '?'));
    try {
        $athletes_stmt = $pdo->prepare("
            SELECT dpe.*, u.first_name, u.last_name, u.email,
                   dpe.program_name, dpe.template_id,
                   (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count,
                   (SELECT COUNT(*) FROM development_program_messages dpm WHERE dpm.enrollment_id = dpe.id) as message_count,
                   (SELECT COUNT(*) FROM development_program_videos dpv WHERE dpv.enrollment_id = dpe.id AND dpv.status = 'pending_review') as pending_video_count
            FROM development_program_enrollments dpe
            JOIN users u ON dpe.athlete_id = u.id
            WHERE dpe.program_type IN ($placeholders) AND dpe.status = 'active'
            ORDER BY dpe.enrolled_at DESC
        ");
        $athletes_stmt->execute($program_types);
        $athletes = $athletes_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('decryptUserRows')) { $athletes = decryptUserRows($athletes); }
        if (class_exists('FieldEncryption')) { $athletes = FieldEncryption::decryptRows($athletes, ['first_name', 'last_name', 'email']); }
    } catch (PDOException $e) { $athletes = []; }
}
?>
<style>
.m-devcoach { padding: 16px; font-family: Inter, sans-serif; }
.m-devcoach-header { margin-bottom: 16px; }
.m-devcoach-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-devcoach-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-devcoach-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 14px 16px; margin-bottom: 10px; min-height: 44px;
}
.m-devcoach-card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-devcoach-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-devcoach-program { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-devcoach-email { font-size: 11px; color: #6B6B7B; margin-bottom: 8px; }
.m-devcoach-stats { display: flex; gap: 12px; flex-wrap: wrap; }
.m-devcoach-stat {
    display: flex; align-items: center; gap: 4px; font-size: 11px; color: #A8A8B8;
    padding: 4px 8px; background: #1E1E2E; border-radius: 6px;
}
.m-devcoach-stat i { font-size: 10px; }
.m-devcoach-stat.has-pending { color: #F59E0B; background: rgba(245,158,11,0.1); }
.m-devcoach-date { font-size: 10px; color: #6B6B7B; margin-top: 8px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
</style>

<div class="m-devcoach">
    <div class="m-devcoach-header">
        <h2 class="m-devcoach-title"><i class="fas fa-chalkboard-teacher" style="color:#8B5CF6;margin-right:6px;"></i> Development Programs</h2>
        <p class="m-devcoach-sub"><?= count($athletes) ?> active enrollment<?= count($athletes) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($athletes)): ?>
    <div class="m-empty-state">
        <i class="fas fa-users"></i>
        <p>No athletes currently enrolled in development programs.</p>
    </div>
    <?php else: ?>
    <?php foreach ($athletes as $ath):
        $ath_name = trim(($ath['first_name'] ?? '') . ' ' . ($ath['last_name'] ?? ''));
        $prog_display = $ath['program_name'] ?: ($ath['program_type'] === 'goalie_dev' ? 'Goalie Dev' : 'Player Dev');
    ?>
    <div class="m-devcoach-card">
        <div class="m-devcoach-card-top">
            <div class="m-devcoach-name"><?= htmlspecialchars($ath_name ?: 'Unknown') ?></div>
            <div class="m-devcoach-program"><?= htmlspecialchars($prog_display) ?></div>
        </div>
        <?php if (!empty($ath['email'])): ?>
        <div class="m-devcoach-email"><?= htmlspecialchars($ath['email']) ?></div>
        <?php endif; ?>
        <div class="m-devcoach-stats">
            <span class="m-devcoach-stat"><i class="fas fa-dumbbell"></i> <?= (int)$ath['drill_count'] ?> drills</span>
            <span class="m-devcoach-stat"><i class="fas fa-comment"></i> <?= (int)$ath['message_count'] ?> msgs</span>
            <?php if ((int)$ath['pending_video_count'] > 0): ?>
            <span class="m-devcoach-stat has-pending"><i class="fas fa-video"></i> <?= (int)$ath['pending_video_count'] ?> pending</span>
            <?php endif; ?>
        </div>
        <div class="m-devcoach-date"><i class="fas fa-calendar"></i> Enrolled <?= date('M j, Y', strtotime($ath['enrolled_at'])) ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
