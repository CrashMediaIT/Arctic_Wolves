<?php
/**
 * PWA Athlete Detail - Mobile-native athlete profile for coaches
 * Purpose-built for mobile phones.
 */
require_once __DIR__ . '/../../lib/image_helper.php';

// Only coaches/admins can view athlete details
if (!$isAnyCoach && !$isTeamStaff && !$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">You do not have access to this page.</p>';
    echo '</div>';
    return;
}

$athleteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$athlete = null;
$recentSessions = [];
$activeGoals = [];

if ($athleteId > 0) {
    // Get athlete info
    try {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, role, position, profile_image, primary_arena, created_at FROM users WHERE id = ?");
        $stmt->execute([$athleteId]);
        $athlete = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($athlete) {
            $athlete = decryptUserRow($athlete);
        }
    } catch (PDOException $e) { $athlete = null; }

    if ($athlete) {
        // Recent sessions
        try {
            $stmt = $pdo->prepare("
                SELECT s.id, s.title, s.session_date, s.session_time, s.session_type
                FROM bookings b
                JOIN sessions s ON s.id = b.session_id
                WHERE b.user_id = ? AND b.status = 'confirmed'
                ORDER BY s.session_date DESC
                LIMIT 5
            ");
            $stmt->execute([$athleteId]);
            $recentSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $recentSessions = []; }

        // Active goals
        try {
            $stmt = $pdo->prepare("
                SELECT id, COALESCE(title, goal_title) as title, status, completion_percentage
                FROM goals
                WHERE athlete_id = ? AND status = 'active'
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$athleteId]);
            $activeGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $activeGoals = []; }
    }
}
?>
<style>
.m-athlete-detail { padding: 16px; font-family: Inter, sans-serif; }
.m-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: #8B5CF6; font-size: 13px; font-weight: 500;
    text-decoration: none; margin-bottom: 16px;
    min-height: 44px; padding: 8px 0;
}
.m-ad-hero { text-align: center; margin-bottom: 20px; }
.m-ad-avatar {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 28px; font-weight: 700; color: #fff;
    margin-bottom: 12px; overflow: hidden;
}
.m-ad-avatar img { width: 100%; height: 100%; object-fit: cover; }
.m-ad-name { font-size: 20px; font-weight: 700; color: #fff; margin: 0; }
.m-ad-position { font-size: 13px; color: #A8A8B8; margin: 4px 0 0; }
.m-ad-stat-row { display: flex; gap: 10px; margin: 16px 0; }
.m-ad-stat {
    flex: 1; background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px; text-align: center;
}
.m-ad-stat-value { font-size: 20px; font-weight: 700; color: #fff; }
.m-ad-stat-label { font-size: 10px; color: #A8A8B8; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
.m-section { margin-bottom: 20px; }
.m-section-title { font-size: 13px; font-weight: 600; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 10px; padding: 0 4px; }
.m-ad-sess-item {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px;
}
.m-ad-sess-date {
    min-width: 44px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-ad-sess-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-ad-sess-date-day { font-size: 18px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-ad-sess-info { flex: 1; min-width: 0; }
.m-ad-sess-title { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-ad-sess-meta { font-size: 11px; color: #A8A8B8; margin-top: 2px; }
.m-ad-goal-item {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px;
}
.m-ad-goal-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.m-ad-goal-title { font-size: 13px; font-weight: 600; color: #fff; }
.m-ad-goal-pct { font-size: 12px; font-weight: 600; color: #8B5CF6; }
.m-ad-goal-bar { height: 4px; background: #2D2D3F; border-radius: 2px; overflow: hidden; }
.m-ad-goal-bar-fill { height: 100%; border-radius: 2px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-athlete-detail">
    <a href="?page=roster" class="m-back-link">
        <i class="fas fa-chevron-left"></i> Back to Roster
    </a>

    <?php if (!$athlete): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-slash"></i>
            <p>Athlete not found</p>
        </div>
    <?php else:
        $initials = strtoupper(mb_substr($athlete['first_name'], 0, 1) . mb_substr($athlete['last_name'], 0, 1));
        $fullName = $athlete['first_name'] . ' ' . $athlete['last_name'];
    ?>
        <div class="m-ad-hero">
            <div class="m-ad-avatar">
                <?php if (!empty($athlete['profile_image'])): ?>
                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $athlete['profile_image'])) ?>" alt="<?= htmlspecialchars($fullName) ?>">
                <?php else: ?>
                    <?= $initials ?>
                <?php endif; ?>
            </div>
            <h2 class="m-ad-name"><?= htmlspecialchars($fullName) ?></h2>
            <?php if (!empty($athlete['position'])): ?>
            <p class="m-ad-position"><i class="fas fa-hockey-puck" style="font-size:11px;"></i> <?= htmlspecialchars(ucfirst($athlete['position'])) ?></p>
            <?php endif; ?>
        </div>

        <div class="m-ad-stat-row">
            <div class="m-ad-stat">
                <div class="m-ad-stat-value"><?= count($recentSessions) ?></div>
                <div class="m-ad-stat-label">Recent Sessions</div>
            </div>
            <div class="m-ad-stat">
                <div class="m-ad-stat-value"><?= count($activeGoals) ?></div>
                <div class="m-ad-stat-label">Active Goals</div>
            </div>
        </div>

        <!-- Recent Sessions -->
        <div class="m-section">
            <h3 class="m-section-title">Recent Sessions</h3>
            <?php if (empty($recentSessions)): ?>
                <div class="m-empty-state" style="padding:20px;">
                    <i class="fas fa-calendar-xmark" style="font-size:24px;"></i>
                    <p style="font-size:12px;">No recent sessions</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentSessions as $s):
                    $sDate = strtotime($s['session_date']);
                    $sTime = !empty($s['session_time']) ? date('g:i A', strtotime($s['session_time'])) : '';
                ?>
                <a href="?page=session_detail&id=<?= (int)$s['id'] ?>" class="m-ad-sess-item">
                    <div class="m-ad-sess-date">
                        <span class="m-ad-sess-date-month"><?= date('M', $sDate) ?></span>
                        <span class="m-ad-sess-date-day"><?= date('j', $sDate) ?></span>
                    </div>
                    <div class="m-ad-sess-info">
                        <div class="m-ad-sess-title"><?= htmlspecialchars($s['title']) ?></div>
                        <div class="m-ad-sess-meta">
                            <?php if ($sTime): ?><?= $sTime ?><?php endif; ?>
                            <?php if (!empty($s['session_type'])): ?> · <?= htmlspecialchars($s['session_type']) ?><?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Active Goals -->
        <div class="m-section">
            <h3 class="m-section-title">Active Goals</h3>
            <?php if (empty($activeGoals)): ?>
                <div class="m-empty-state" style="padding:20px;">
                    <i class="fas fa-bullseye" style="font-size:24px;"></i>
                    <p style="font-size:12px;">No active goals</p>
                </div>
            <?php else: ?>
                <?php foreach ($activeGoals as $g):
                    $pct = max(0, min(100, (int)($g['completion_percentage'] ?? 0)));
                    $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#8B5CF6');
                ?>
                <div class="m-ad-goal-item">
                    <div class="m-ad-goal-top">
                        <span class="m-ad-goal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled') ?></span>
                        <span class="m-ad-goal-pct"><?= $pct ?>%</span>
                    </div>
                    <div class="m-ad-goal-bar">
                        <div class="m-ad-goal-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
