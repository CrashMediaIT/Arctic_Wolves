<?php
/**
 * Game Plan - Home / Dashboard View
 * Overview with recent videos, quick stats, and quick actions.
 */

// Load quick stats
$gp_stats = ['videos' => 0, 'plans' => 0, 'reviews' => 0, 'lines' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos" . (!$isAnyCoach ? " WHERE athlete_id = ?" : ""));
    $stmt->execute(!$isAnyCoach ? [$user_id] : []);
    $gp_stats['videos'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}
if ($isAnyCoach) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vr_game_plans WHERE coach_id = ?");
        $stmt->execute([$user_id]);
        $gp_stats['plans'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vr_review_sessions WHERE coach_id = ? AND status = 'scheduled'");
        $stmt->execute([$user_id]);
        $gp_stats['reviews'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT plan_id) FROM vr_game_plan_lines WHERE plan_id IN (SELECT id FROM vr_game_plans WHERE coach_id = ?)");
        $stmt->execute([$user_id]);
        $gp_stats['lines'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}
}
?>

<!-- Quick Stats -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <i class="fas fa-video" style="font-size: 24px; color: var(--primary-light); margin-bottom: 8px; display: block;"></i>
            <div style="font-size: 28px; font-weight: 900; color: var(--text-white);"><?= $gp_stats['videos'] ?></div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Videos</div>
        </div>
    </div>
    <?php if ($isAnyCoach): ?>
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <i class="fas fa-clipboard-list" style="font-size: 24px; color: var(--info); margin-bottom: 8px; display: block;"></i>
            <div style="font-size: 28px; font-weight: 900; color: var(--text-white);"><?= $gp_stats['plans'] ?></div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Game Plans</div>
        </div>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <i class="fas fa-chalkboard-user" style="font-size: 24px; color: var(--warning); margin-bottom: 8px; display: block;"></i>
            <div style="font-size: 28px; font-weight: 900; color: var(--text-white);"><?= $gp_stats['reviews'] ?></div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Upcoming Reviews</div>
        </div>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <i class="fas fa-users-line" style="font-size: 24px; color: var(--success); margin-bottom: 8px; display: block;"></i>
            <div style="font-size: 28px; font-weight: 900; color: var(--text-white);"><?= $gp_stats['lines'] ?></div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Plans with Lines</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Videos -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-film"></i> Recent Videos</h3>
        <a href="?page=gameplan_video" class="btn btn-secondary" style="height: 36px; padding: 0 16px; font-size: 13px;">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentVideos)): ?>
        <div class="empty-state" style="text-align: center; padding: 40px 20px;">
            <i class="fas fa-video-slash" style="font-size: 40px; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
            <h3 style="color: var(--text-secondary); margin-bottom: 8px;">No Videos Yet</h3>
            <p style="color: var(--text-muted); margin-bottom: 16px;">Upload videos from the main dashboard or record them in the app.</p>
            <a href="?page=video" class="btn btn-primary" style="height: 40px; padding: 0 20px;"><i class="fas fa-upload"></i> Go to Video Upload</a>
        </div>
        <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
            <?php foreach (array_slice($recentVideos, 0, 8) as $video): ?>
            <div class="card" style="margin-bottom: 0; transition: border-color 0.2s, transform 0.15s;">
                <div style="width: 100%; aspect-ratio: 16/9; background: var(--bg-main); display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 32px; position: relative;">
                    <i class="fas fa-play-circle"></i>
                    <span style="position: absolute; top: 8px; right: 8px; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; background: <?= ($video['status'] ?? '') === 'reviewed' ? 'rgba(16,185,129,.15)' : 'rgba(59,130,246,.15)' ?>; color: <?= ($video['status'] ?? '') === 'reviewed' ? 'var(--success)' : 'var(--info)' ?>;">
                        <?= htmlspecialchars($video['status'] ?? 'pending') ?>
                    </span>
                </div>
                <div class="card-body" style="padding: 14px 16px;">
                    <div style="font-size: 14px; font-weight: 700; margin-bottom: 4px; color: var(--text-white);"><?= htmlspecialchars($video['title'] ?? 'Untitled Video') ?></div>
                    <div style="font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 12px;">
                        <?php if (!empty($video['athlete_first_name'])): ?>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($video['athlete_first_name'] . ' ' . ($video['athlete_last_name'] ?? '')) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($video['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAnyCoach): ?>
<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <a href="?page=gameplan_plans" style="display: flex; align-items: center; gap: 14px; padding: 16px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: var(--text-white); transition: border-color 0.2s;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(59,130,246,.1); color: var(--info); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Create Game Plan</div>
                    <div style="font-size: 12px; color: var(--text-muted);">Pre/post game strategies</div>
                </div>
            </a>
            <a href="?page=gameplan_lines" style="display: flex; align-items: center; gap: 14px; padding: 16px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: var(--text-white); transition: border-color 0.2s;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16,185,129,.1); color: var(--success); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                    <i class="fas fa-users-line"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Set Hockey Lines</div>
                    <div style="font-size: 12px; color: var(--text-muted);">Forward, defense & special teams</div>
                </div>
            </a>
            <a href="?page=gameplan_film_room" style="display: flex; align-items: center; gap: 14px; padding: 16px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: var(--text-white); transition: border-color 0.2s;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(139,92,246,.1); color: var(--primary-light); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                    <i class="fas fa-video"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Film Room</div>
                    <div style="font-size: 12px; color: var(--text-muted);">Upload & tag video footage</div>
                </div>
            </a>
            <a href="?page=gameplan_review_sessions" style="display: flex; align-items: center; gap: 14px; padding: 16px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: var(--text-white); transition: border-color 0.2s;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(245,158,11,.1); color: var(--warning); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                    <i class="fas fa-chalkboard-user"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Review Sessions</div>
                    <div style="font-size: 12px; color: var(--text-muted);">Plan team video reviews</div>
                </div>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>
