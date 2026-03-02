<?php
/**
 * PWA Stats - Mobile-native performance stats for athletes
 * Purpose-built for mobile phones with coach athlete selector and goal management.
 */

// Coach athlete selector
$coachAthletes = [];
if ($isAnyCoach) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.first_name, u.last_name
            FROM users u
            WHERE u.role = 'athlete' AND u.status = 'active'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
        $coachAthletes = decryptUserRows($stmt->fetchAll(PDO::FETCH_ASSOC));
        foreach ($coachAthletes as &$ca) {
            $ca['name'] = trim(($ca['first_name'] ?? '') . ' ' . ($ca['last_name'] ?? ''));
        }
        unset($ca);
    } catch (PDOException $e) { $coachAthletes = []; }
}

// Determine which athlete to show stats for
$statsUserId = $user_id;
$statsUserName = $user_name;
if ($isAnyCoach && !empty($_GET['athlete_id'])) {
    $statsUserId = (int)$_GET['athlete_id'];
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$statsUserId]);
        $row = decryptUserRow($stmt->fetch(PDO::FETCH_ASSOC));
        if ($row) $statsUserName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    } catch (PDOException $e) { /* keep default */ }
} elseif ($isParent && !empty($_SESSION['viewing_athlete_id'])) {
    $statsUserId = (int)$_SESSION['viewing_athlete_id'];
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$statsUserId]);
        $row = decryptUserRow($stmt->fetch(PDO::FETCH_ASSOC));
        if ($row) $statsUserName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    } catch (PDOException $e) { /* keep default */ }
}

// Total sessions attended
$totalSessions = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN sessions s ON s.id = b.session_id
        WHERE b.user_id = ? AND b.status = 'confirmed' AND s.status = 'completed'
    ");
    $stmt->execute([$statsUserId]);
    $totalSessions = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $totalSessions = 0; }

// Sessions this month
$monthSessions = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN sessions s ON s.id = b.session_id
        WHERE b.user_id = ? AND b.status = 'confirmed' AND s.status = 'completed'
          AND s.session_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ");
    $stmt->execute([$statsUserId]);
    $monthSessions = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $monthSessions = 0; }

// Goals stats
$activeGoals = 0;
$completedGoals = 0;
$goalCompletionRate = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE athlete_id = ? AND status = 'active'");
    $stmt->execute([$statsUserId]);
    $activeGoals = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE athlete_id = ? AND status = 'completed'");
    $stmt->execute([$statsUserId]);
    $completedGoals = (int)$stmt->fetchColumn();

    $totalGoals = $activeGoals + $completedGoals;
    $goalCompletionRate = $totalGoals > 0 ? round(($completedGoals / $totalGoals) * 100) : 0;
} catch (PDOException $e) { /* fallback to zeros */ }

// Average goal progress
$avgGoalProgress = 0;
try {
    $stmt = $pdo->prepare("SELECT AVG(completion_percentage) FROM goals WHERE athlete_id = ? AND status = 'active'");
    $stmt->execute([$statsUserId]);
    $avgGoalProgress = round((float)$stmt->fetchColumn());
} catch (PDOException $e) { $avgGoalProgress = 0; }

// Recent skill evaluations
$recentEvals = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.score, es.max_score, es.evaluation_date, es.comments,
               ek.name as skill_name
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC, es.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$statsUserId]);
    $recentEvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentEvals = []; }

// Average score
$avgScore = 0;
$avgMaxScore = 10;
try {
    $stmt = $pdo->prepare("SELECT AVG(score), AVG(max_score) FROM evaluation_scores WHERE athlete_id = ?");
    $stmt->execute([$statsUserId]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row && $row[0] !== null) {
        $avgScore = round((float)$row[0], 1);
        $avgMaxScore = round((float)$row[1], 1);
    }
} catch (PDOException $e) { /* fallback */ }

// Goal filter
$filterStatus = $_GET['status'] ?? 'active';

// All goals (filtered)
$allGoals = [];
try {
    $goalsQuery = "
        SELECT g.*,
               (SELECT COUNT(*) FROM goal_steps WHERE goal_id = g.id) as total_steps,
               (SELECT COUNT(*) FROM goal_steps WHERE goal_id = g.id AND is_completed = 1) as completed_steps
        FROM goals g
        WHERE g.athlete_id = ?
    ";
    $goalsParams = [$statsUserId];
    if ($filterStatus === 'active') {
        $goalsQuery .= " AND g.status = 'active'";
    } elseif ($filterStatus === 'completed') {
        $goalsQuery .= " AND g.status = 'completed'";
    } elseif ($filterStatus === 'archived') {
        $goalsQuery .= " AND g.status = 'archived'";
    }
    $goalsQuery .= " ORDER BY g.created_at DESC";
    $stmt = $pdo->prepare($goalsQuery);
    $stmt->execute($goalsParams);
    $allGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $allGoals = []; }

// Season statistics (athlete_stats)
$athleteStats = [];
try {
    $stmt = $pdo->prepare("
        SELECT ast.*, COALESCE(NULLIF(ast.season, ''), s.name) as season_name
        FROM athlete_stats ast
        LEFT JOIN seasons s ON ast.season_id = s.id
        WHERE ast.user_id = ?
        ORDER BY ast.season DESC
    ");
    $stmt->execute([$statsUserId]);
    $athleteStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $athleteStats = []; }

// Determine goalie status from teams
$isGoalie = false;
try {
    $stmt = $pdo->prepare("
        SELECT position FROM athlete_teams WHERE (user_id = ? OR athlete_id = ?) AND position LIKE '%goalie%' LIMIT 1
    ");
    $stmt->execute([$statsUserId, $statsUserId]);
    if ($stmt->fetch()) $isGoalie = true;
} catch (PDOException $e) { /* ignore */ }
if (!$isGoalie) {
    foreach ($athleteStats as $stat) {
        if ((!empty($stat['saves']) && $stat['saves'] > 0) || (!empty($stat['gaa']) && $stat['gaa'] > 0)) {
            $isGoalie = true;
            break;
        }
    }
}

// Performance metrics
$perfStats = [];
try {
    $stmt = $pdo->prepare("
        SELECT ps.id, ps.stat_date, ps.stat_type as metric_name,
               ps.stat_value as value, ps.stat_unit as unit, ps.notes
        FROM performance_stats ps
        WHERE ps.athlete_id = ?
        ORDER BY ps.stat_date DESC
        LIMIT 10
    ");
    $stmt->execute([$statsUserId]);
    $perfStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $perfStats = []; }

// Lap times
$lapTimes = [];
$lapTimeStats = ['best' => null, 'avg' => null, 'count' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT ps.stat_value as lap_time, ps.stat_date, ps.notes, ps.created_at
        FROM performance_stats ps
        WHERE ps.athlete_id = ? AND ps.stat_type = 'lap_time'
        ORDER BY ps.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$statsUserId]);
    $lapTimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT MIN(stat_value) as best, AVG(stat_value) as avg, COUNT(*) as count
        FROM performance_stats
        WHERE athlete_id = ? AND stat_type = 'lap_time'
    ");
    $stmt->execute([$statsUserId]);
    $lapTimeStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $lapTimeStats;
} catch (PDOException $e) { /* ignore */ }

// Shot speeds
$shotSpeeds = [];
$shotSpeedStats = ['max_mph' => null, 'avg_mph' => null, 'max_kmh' => null, 'avg_kmh' => null, 'count' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT ps.stat_value as speed, ps.stat_unit as unit, ps.stat_date, ps.notes, ps.created_at
        FROM performance_stats ps
        WHERE ps.athlete_id = ? AND ps.stat_type = 'shot_speed'
        ORDER BY ps.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$statsUserId]);
    $shotSpeeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT stat_unit, MAX(stat_value) as max_speed, AVG(stat_value) as avg_speed, COUNT(*) as count
        FROM performance_stats
        WHERE athlete_id = ? AND stat_type = 'shot_speed'
        GROUP BY stat_unit
    ");
    $stmt->execute([$statsUserId]);
    $speedsByUnit = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($speedsByUnit as $row) {
        if ($row['stat_unit'] === 'mph') {
            $shotSpeedStats['max_mph'] = $row['max_speed'];
            $shotSpeedStats['avg_mph'] = $row['avg_speed'];
        } elseif ($row['stat_unit'] === 'km/h') {
            $shotSpeedStats['max_kmh'] = $row['max_speed'];
            $shotSpeedStats['avg_kmh'] = $row['avg_speed'];
        }
        $shotSpeedStats['count'] += $row['count'];
    }
} catch (PDOException $e) { /* ignore */ }

$canManageGoals = $isAnyCoach || ($statsUserId == $user_id);
?>
<style>
.m-stats { padding: 16px; padding-bottom: 100px; font-family: Inter, sans-serif; }
.m-stats-header { margin-bottom: 16px; }
.m-stats-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-stats-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-kpi {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-kpi-icon { font-size: 16px; margin-bottom: 6px; }
.m-kpi-value { font-size: 28px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-kpi-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-section { margin-bottom: 20px; }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-progress-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-progress-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 8px;
}
.m-progress-name { font-size: 13px; color: #fff; font-weight: 500; }
.m-progress-value { font-size: 13px; color: #8B5CF6; font-weight: 600; }
.m-progress-bar {
    height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden;
}
.m-progress-fill {
    height: 100%; border-radius: 3px;
    transition: width 0.5s ease;
}
.m-eval-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-eval-top {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 6px;
}
.m-eval-skill { font-size: 13px; font-weight: 600; color: #fff; }
.m-eval-score { font-size: 14px; font-weight: 700; }
.m-eval-date { font-size: 11px; color: #6B6B7B; }
.m-eval-bar { height: 4px; background: #2D2D3F; border-radius: 2px; margin-top: 6px; overflow: hidden; }
.m-eval-bar-fill { height: 100%; border-radius: 2px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }

/* Goal filter segment control */
.m-filter-bar {
    display: flex; gap: 6px; margin-bottom: 14px; overflow-x: auto;
    -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.m-filter-bar::-webkit-scrollbar { display: none; }
.m-filter-btn {
    padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600;
    border: 1px solid #2D2D3F; background: #16161F; color: #A8A8B8;
    white-space: nowrap; min-height: 44px; display: inline-flex; align-items: center;
    text-decoration: none; transition: all 0.2s;
}
.m-filter-btn.active { background: linear-gradient(135deg,#6B46C1,#8B5CF6); color: #fff; border-color: #8B5CF6; }

/* Goal cards */
.m-goal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; transition: border-color 0.2s;
}
.m-goal-card.completed { border-color: #10B981; opacity: 0.85; }
.m-goal-cat {
    display: inline-block; padding: 2px 8px; background: rgba(107,70,193,0.2);
    border: 1px solid #6B46C1; border-radius: 4px; font-size: 10px;
    color: #8B5CF6; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;
}
.m-goal-title { font-size: 14px; font-weight: 700; color: #fff; margin: 0 0 4px; }
.m-goal-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 8px; line-height: 1.4; }
.m-goal-meta { display: flex; justify-content: space-between; font-size: 11px; color: #6B6B7B; margin-top: 8px; }
.m-goal-expand {
    width: 100%; margin-top: 10px; padding: 8px; background: #0D0D14;
    border: 1px solid #2D2D3F; border-radius: 8px; color: #8B5CF6;
    font-size: 12px; font-weight: 600; min-height: 44px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.m-goal-steps { display: none; margin-top: 10px; }
.m-goal-steps.open { display: block; }
.m-step-item {
    display: flex; align-items: center; gap: 10px; padding: 10px;
    background: #0D0D14; border: 1px solid #2D2D3F; border-radius: 8px;
    margin-bottom: 6px; min-height: 44px;
}
.m-step-item.completed { border-color: #10B981; background: rgba(16,185,129,0.05); }
.m-step-check {
    width: 22px; height: 22px; accent-color: #8B5CF6; cursor: pointer; flex-shrink: 0;
}
.m-step-title { font-size: 13px; color: #fff; flex: 1; }
.m-step-item.completed .m-step-title { color: #6B6B7B; text-decoration: line-through; }
.m-goal-actions { display: flex; gap: 6px; margin-top: 8px; }
.m-goal-btn {
    flex: 1; padding: 8px; border-radius: 8px; font-size: 12px; font-weight: 600;
    min-height: 44px; border: none; cursor: pointer; display: inline-flex;
    align-items: center; justify-content: center; gap: 4px;
}
.m-goal-btn-view { background: transparent; border: 1px solid #6B46C1; color: #8B5CF6; }
.m-goal-btn-edit { background: #6B46C1; color: #fff; }
.m-goal-btn-done { background: #10B981; color: #fff; }

/* FAB (Floating Action Button) */
.m-fab {
    position: fixed; bottom: 80px; right: 20px; width: 56px; height: 56px;
    border-radius: 50%; background: linear-gradient(135deg,#6B46C1,#8B5CF6);
    color: #fff; border: none; font-size: 22px; cursor: pointer; z-index: 500;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.5);
}

/* Bottom sheet modal */
.m-sheet-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.7); z-index: 9999; display: none;
    align-items: flex-end; justify-content: center;
}
.m-sheet-overlay.open { display: flex; }
.m-sheet {
    background: #16161F; border-radius: 20px 20px 0 0; width: 100%;
    max-width: 500px; max-height: 90vh; overflow-y: auto;
    animation: m-sheet-up 0.3s ease;
}
@keyframes m-sheet-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-sheet-handle {
    width: 36px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 10px auto 0;
}
.m-sheet-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 20px 10px;
}
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sheet-close {
    background: none; border: none; color: #A8A8B8; font-size: 22px;
    padding: 4px 8px; cursor: pointer; min-height: 44px; min-width: 44px;
    display: flex; align-items: center; justify-content: center;
}
.m-sheet-body { padding: 0 20px 20px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; }
.m-form-input, .m-form-textarea, .m-form-select {
    width: 100%; padding: 12px 14px; background: #0D0D14; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
}
.m-form-textarea { min-height: 80px; resize: vertical; }
.m-form-input:focus, .m-form-textarea:focus, .m-form-select:focus {
    outline: none; border-color: #8B5CF6;
}
.m-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-steps-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 8px; padding-top: 10px; border-top: 1px solid #2D2D3F;
}
.m-steps-header-label { font-size: 13px; font-weight: 700; color: #fff; }
.m-add-step-btn {
    padding: 6px 12px; border-radius: 8px; background: transparent;
    border: 1px solid #6B46C1; color: #8B5CF6; font-size: 12px;
    font-weight: 600; cursor: pointer; min-height: 36px;
}
.m-step-input-row {
    display: flex; gap: 8px; align-items: center; margin-bottom: 6px;
}
.m-step-input-row input {
    flex: 1; padding: 10px 12px; background: #0D0D14; border: 1px solid #2D2D3F;
    border-radius: 8px; color: #fff; font-size: 13px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
}
.m-step-input-row input:focus { outline: none; border-color: #8B5CF6; }
.m-step-remove {
    background: none; border: none; color: #EF4444; font-size: 16px;
    padding: 4px 8px; cursor: pointer; min-height: 44px; min-width: 44px;
    display: flex; align-items: center; justify-content: center;
}
.m-sheet-submit {
    width: 100%; padding: 14px; background: linear-gradient(135deg,#6B46C1,#8B5CF6);
    color: #fff; border: none; border-radius: 12px; font-size: 15px;
    font-weight: 700; cursor: pointer; min-height: 50px; margin-top: 10px;
}

/* Collapsible sections */
.m-collapse-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 12px; cursor: pointer; min-height: 44px; margin-bottom: 2px;
}
.m-collapse-header.open { border-radius: 12px 12px 0 0; margin-bottom: 0; }
.m-collapse-title { font-size: 14px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
.m-collapse-icon { color: #A8A8B8; font-size: 12px; transition: transform 0.2s; }
.m-collapse-header.open .m-collapse-icon { transform: rotate(180deg); }
.m-collapse-body {
    display: none; background: #16161F; border: 1px solid #2D2D3F;
    border-top: none; border-radius: 0 0 12px 12px; padding: 14px;
    overflow-x: auto; -webkit-overflow-scrolling: touch;
}
.m-collapse-body.open { display: block; }

/* Stat table (mobile) */
.m-stat-table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 500px; }
.m-stat-table th {
    text-align: left; padding: 8px 6px; color: #A8A8B8; font-weight: 600;
    border-bottom: 1px solid #2D2D3F; white-space: nowrap; font-size: 11px;
    text-transform: uppercase; letter-spacing: 0.3px;
}
.m-stat-table td { padding: 8px 6px; color: #fff; border-bottom: 1px solid rgba(45,45,63,0.5); white-space: nowrap; }
.m-stat-table td strong { color: #8B5CF6; }

/* Speed & Power cards */
.m-speed-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
.m-speed-stat {
    background: #0D0D14; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 10px; text-align: center;
}
.m-speed-label { font-size: 10px; color: #A8A8B8; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 4px; }
.m-speed-val { font-size: 16px; font-weight: 700; color: #8B5CF6; font-family: monospace; }
.m-metric-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid rgba(45,45,63,0.4);
}
.m-metric-row:last-child { border-bottom: none; }
.m-metric-val { font-size: 14px; font-weight: 700; font-family: monospace; color: #8B5CF6; }
.m-metric-note { font-size: 11px; color: #6B6B7B; display: block; margin-top: 2px; }
.m-metric-date { font-size: 11px; color: #6B6B7B; white-space: nowrap; }

/* Toast */
.m-toast {
    position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%);
    background: #10B981; color: #fff; padding: 12px 20px; border-radius: 10px;
    font-size: 13px; font-weight: 600; z-index: 10001; animation: m-toast-in 0.3s ease;
    max-width: 90%; text-align: center;
}
.m-toast.error { background: #EF4444; }
@keyframes m-toast-in { from { opacity: 0; transform: translateX(-50%) translateY(20px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
</style>

<div class="m-stats">
    <?php if ($isAnyCoach && !empty($coachAthletes)): ?>
    <div style="margin-bottom:14px;">
        <select onchange="if(this.value)window.location='?page=stats&athlete_id='+this.value" style="width:100%;padding:12px;background:#16161F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;font-size:14px;font-family:Inter,sans-serif;min-height:44px;">
            <option value="">Select Athlete</option>
            <?php foreach ($coachAthletes as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $statsUserId == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="m-stats-header">
        <h2 class="m-stats-title">Performance</h2>
        <p class="m-stats-sub"><?= htmlspecialchars($statsUserName) ?></p>
    </div>

    <!-- KPI Grid -->
    <div class="m-kpi-grid">
        <div class="m-kpi">
            <div class="m-kpi-icon" style="color:#10B981;"><i class="fas fa-check-circle"></i></div>
            <div class="m-kpi-value"><?= $totalSessions ?></div>
            <div class="m-kpi-label">Sessions Done</div>
        </div>
        <div class="m-kpi">
            <div class="m-kpi-icon" style="color:#3B82F6;"><i class="fas fa-calendar"></i></div>
            <div class="m-kpi-value"><?= $monthSessions ?></div>
            <div class="m-kpi-label">This Month</div>
        </div>
        <div class="m-kpi">
            <div class="m-kpi-icon" style="color:#8B5CF6;"><i class="fas fa-bullseye"></i></div>
            <div class="m-kpi-value"><?= $activeGoals ?></div>
            <div class="m-kpi-label">Active Goals</div>
        </div>
        <div class="m-kpi">
            <div class="m-kpi-icon" style="color:#F59E0B;"><i class="fas fa-star"></i></div>
            <div class="m-kpi-value"><?= $avgScore ?><span style="font-size:14px;color:#6B6B7B;">/<?= $avgMaxScore ?></span></div>
            <div class="m-kpi-label">Avg Score</div>
        </div>
    </div>

    <!-- Goal Progress Summary -->
    <div class="m-section">
        <h3 class="m-section-title">Goal Progress</h3>
        <?php if ($activeGoals === 0 && $completedGoals === 0): ?>
            <div class="m-empty-state">
                <i class="fas fa-bullseye"></i>
                No goals set yet
            </div>
        <?php else: ?>
            <div class="m-progress-card">
                <div class="m-progress-header">
                    <span class="m-progress-name">Completion Rate</span>
                    <span class="m-progress-value"><?= $goalCompletionRate ?>%</span>
                </div>
                <div class="m-progress-bar">
                    <div class="m-progress-fill" style="width:<?= $goalCompletionRate ?>%;background:#10B981;"></div>
                </div>
            </div>
            <div class="m-progress-card">
                <div class="m-progress-header">
                    <span class="m-progress-name">Avg Active Goal Progress</span>
                    <span class="m-progress-value"><?= $avgGoalProgress ?>%</span>
                </div>
                <div class="m-progress-bar">
                    <div class="m-progress-fill" style="width:<?= $avgGoalProgress ?>%;background:#8B5CF6;"></div>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:10px;">
                <div style="flex:1;background:#16161F;border:1px solid #2D2D3F;border-radius:10px;padding:12px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#10B981;"><?= $completedGoals ?></div>
                    <div style="font-size:11px;color:#A8A8B8;">Completed</div>
                </div>
                <div style="flex:1;background:#16161F;border:1px solid #2D2D3F;border-radius:10px;padding:12px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#8B5CF6;"><?= $activeGoals ?></div>
                    <div style="font-size:11px;color:#A8A8B8;">Active</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Goal Filter & Cards -->
    <div class="m-section">
        <h3 class="m-section-title">Goals</h3>
        <div class="m-filter-bar">
            <?php
            $baseUrl = '?page=stats' . ($isAnyCoach && $statsUserId != $user_id ? '&athlete_id=' . (int)$statsUserId : '');
            $statuses = ['active' => 'Active', 'completed' => 'Completed', 'archived' => 'Archived', 'all' => 'All'];
            foreach ($statuses as $sKey => $sLabel): ?>
            <a href="<?= $baseUrl ?>&status=<?= $sKey ?>" class="m-filter-btn <?= $filterStatus === $sKey ? 'active' : '' ?>"><?= $sLabel ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($allGoals)): ?>
            <div class="m-empty-state">
                <i class="fas fa-bullseye"></i>
                No <?= $filterStatus !== 'all' ? htmlspecialchars($filterStatus) . ' ' : '' ?>goals found
            </div>
        <?php else: ?>
            <?php foreach ($allGoals as $goal):
                $gPct = round($goal['completion_percentage'] ?? 0);
                $gDone = $goal['status'] === 'completed';
            ?>
            <div class="m-goal-card <?= $gDone ? 'completed' : '' ?>" id="goalCard<?= (int)$goal['id'] ?>">
                <?php if (!empty($goal['category'])): ?>
                    <span class="m-goal-cat"><?= htmlspecialchars($goal['category']) ?></span>
                <?php endif; ?>
                <h4 class="m-goal-title"><?= htmlspecialchars($goal['title'] ?? '') ?></h4>
                <?php if (!empty($goal['description'])): ?>
                    <p class="m-goal-desc"><?= htmlspecialchars(mb_substr($goal['description'], 0, 100)) ?><?= mb_strlen($goal['description']) > 100 ? '...' : '' ?></p>
                <?php endif; ?>
                <div class="m-progress-header">
                    <span class="m-progress-name">Progress</span>
                    <span class="m-progress-value"><?= $gPct ?>%</span>
                </div>
                <div class="m-progress-bar">
                    <div class="m-progress-fill" style="width:<?= $gPct ?>%;background:<?= $gDone ? '#10B981' : '#8B5CF6' ?>;"></div>
                </div>
                <div class="m-goal-meta">
                    <span><i class="fas fa-list-check"></i> <?= (int)$goal['completed_steps'] ?>/<?= (int)$goal['total_steps'] ?> steps</span>
                    <?php if (!empty($goal['target_date'])): ?>
                    <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($goal['target_date'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ((int)$goal['total_steps'] > 0): ?>
                <button class="m-goal-expand" onclick="mToggleGoalSteps(<?= (int)$goal['id'] ?>)">
                    <i class="fas fa-chevron-down" id="expandIcon<?= (int)$goal['id'] ?>"></i> View Steps
                </button>
                <div class="m-goal-steps" id="goalSteps<?= (int)$goal['id'] ?>">
                    <div style="text-align:center;padding:10px;color:#6B6B7B;font-size:12px;">Loading...</div>
                </div>
                <?php endif; ?>
                <?php if ($canManageGoals): ?>
                <div class="m-goal-actions">
                    <button class="m-goal-btn m-goal-btn-view" onclick="mViewGoalDetail(<?= (int)$goal['id'] ?>)"><i class="fas fa-eye"></i> View</button>
                    <button class="m-goal-btn m-goal-btn-edit" onclick="mEditGoal(<?= (int)$goal['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
                    <?php if (!$gDone): ?>
                    <button class="m-goal-btn m-goal-btn-done" onclick="mCompleteGoal(<?= (int)$goal['id'] ?>)"><i class="fas fa-check"></i></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Season Statistics (collapsible) -->
    <?php if (!empty($athleteStats)): ?>
    <div class="m-section">
        <div class="m-collapse-header" onclick="mToggleCollapse('seasonStats')">
            <span class="m-collapse-title"><i class="fas fa-chart-bar"></i> Season Statistics</span>
            <i class="fas fa-chevron-down m-collapse-icon" id="collapseIconseasonStats"></i>
        </div>
        <div class="m-collapse-body" id="collapseseasonStats">
            <?php if ($isGoalie): ?>
            <table class="m-stat-table">
                <thead><tr><th>Season</th><th>GP</th><th>W</th><th>L</th><th>SV</th><th>SV%</th><th>GAA</th><th>SO</th></tr></thead>
                <tbody>
                <?php foreach ($athleteStats as $stat): ?>
                <tr>
                    <td><?= htmlspecialchars($stat['season_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($stat['games_played'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($stat['wins'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($stat['losses'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($stat['saves'] ?? 0) ?></td>
                    <td><strong><?= number_format(($stat['save_percentage'] ?? 0) * 100, 1) ?>%</strong></td>
                    <td><strong><?= number_format($stat['gaa'] ?? 0, 2) ?></strong></td>
                    <td><?= htmlspecialchars($stat['shutouts'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <table class="m-stat-table">
                <thead><tr><th>Season</th><th>GP</th><th>G</th><th>A</th><th>PTS</th><th>+/-</th><th>PIM</th></tr></thead>
                <tbody>
                <?php foreach ($athleteStats as $stat): ?>
                <tr>
                    <td><?= htmlspecialchars($stat['season_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($stat['games_played'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($stat['goals'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($stat['assists'] ?? 0) ?></td>
                    <td><strong><?= htmlspecialchars($stat['points'] ?? (($stat['goals'] ?? 0) + ($stat['assists'] ?? 0))) ?></strong></td>
                    <td><?= htmlspecialchars($stat['plus_minus'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($stat['penalty_minutes'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Lap Times -->
    <?php if (!empty($lapTimes)): ?>
    <div class="m-section">
        <div class="m-collapse-header" onclick="mToggleCollapse('lapTimes')">
            <span class="m-collapse-title"><i class="fas fa-stopwatch"></i> Lap Times</span>
            <i class="fas fa-chevron-down m-collapse-icon" id="collapseIconlapTimes"></i>
        </div>
        <div class="m-collapse-body" id="collapselapTimes">
            <?php if ($lapTimeStats['count'] > 0): ?>
            <div class="m-speed-grid">
                <div class="m-speed-stat">
                    <div class="m-speed-label">Best</div>
                    <div class="m-speed-val"><?= number_format($lapTimeStats['best'], 2) ?>s</div>
                </div>
                <div class="m-speed-stat">
                    <div class="m-speed-label">Average</div>
                    <div class="m-speed-val"><?= number_format($lapTimeStats['avg'], 2) ?>s</div>
                </div>
                <div class="m-speed-stat">
                    <div class="m-speed-label">Total</div>
                    <div class="m-speed-val"><?= $lapTimeStats['count'] ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php foreach (array_slice($lapTimes, 0, 5) as $lap): ?>
            <div class="m-metric-row">
                <div>
                    <span class="m-metric-val"><?= number_format($lap['lap_time'], 2) ?>s</span>
                    <?php if (!empty($lap['notes'])): ?>
                    <span class="m-metric-note"><?= htmlspecialchars($lap['notes']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="m-metric-date"><?= date('M j, Y', strtotime($lap['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Shot Speed -->
    <?php if (!empty($shotSpeeds)): ?>
    <div class="m-section">
        <div class="m-collapse-header" onclick="mToggleCollapse('shotSpeed')">
            <span class="m-collapse-title"><i class="fas fa-hockey-puck"></i> Shot Speed</span>
            <i class="fas fa-chevron-down m-collapse-icon" id="collapseIconshotSpeed"></i>
        </div>
        <div class="m-collapse-body" id="collapseshotSpeed">
            <?php if ($shotSpeedStats['count'] > 0): ?>
            <div class="m-speed-grid">
                <?php if ($shotSpeedStats['max_mph']): ?>
                <div class="m-speed-stat">
                    <div class="m-speed-label">Max</div>
                    <div class="m-speed-val"><?= number_format($shotSpeedStats['max_mph'], 1) ?> mph</div>
                </div>
                <div class="m-speed-stat">
                    <div class="m-speed-label">Avg</div>
                    <div class="m-speed-val"><?= number_format($shotSpeedStats['avg_mph'], 1) ?> mph</div>
                </div>
                <?php endif; ?>
                <?php if ($shotSpeedStats['max_kmh']): ?>
                <div class="m-speed-stat">
                    <div class="m-speed-label">Max</div>
                    <div class="m-speed-val"><?= number_format($shotSpeedStats['max_kmh'], 1) ?> km/h</div>
                </div>
                <?php endif; ?>
                <div class="m-speed-stat">
                    <div class="m-speed-label">Total</div>
                    <div class="m-speed-val"><?= $shotSpeedStats['count'] ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php foreach (array_slice($shotSpeeds, 0, 5) as $shot): ?>
            <div class="m-metric-row">
                <div>
                    <span class="m-metric-val" style="color:#10B981;"><?= number_format($shot['speed'], 1) ?> <?= htmlspecialchars($shot['unit'] ?? '') ?></span>
                    <?php if (!empty($shot['notes'])): ?>
                    <span class="m-metric-note"><?= htmlspecialchars($shot['notes']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="m-metric-date"><?= date('M j, Y', strtotime($shot['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Performance Metrics (collapsible) -->
    <?php if (!empty($perfStats)): ?>
    <div class="m-section">
        <div class="m-collapse-header" onclick="mToggleCollapse('perfMetrics')">
            <span class="m-collapse-title"><i class="fas fa-chart-line"></i> Performance Metrics</span>
            <i class="fas fa-chevron-down m-collapse-icon" id="collapseIconperfMetrics"></i>
        </div>
        <div class="m-collapse-body" id="collapseperfMetrics">
            <table class="m-stat-table" style="min-width:400px;">
                <thead><tr><th>Date</th><th>Metric</th><th>Value</th><th>Notes</th></tr></thead>
                <tbody>
                <?php foreach ($perfStats as $ps): ?>
                <tr>
                    <td><?= date('M j', strtotime($ps['stat_date'])) ?></td>
                    <td><?= htmlspecialchars($ps['metric_name'] ?? 'Performance') ?></td>
                    <td><strong><?= htmlspecialchars($ps['value']) ?></strong> <?= htmlspecialchars($ps['unit'] ?? '') ?></td>
                    <td><?= htmlspecialchars(mb_substr($ps['notes'] ?? '-', 0, 30)) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Evaluations -->
    <div class="m-section">
        <h3 class="m-section-title">Recent Evaluations</h3>
        <?php if (empty($recentEvals)): ?>
            <div class="m-empty-state">
                <i class="fas fa-chart-line"></i>
                No evaluations yet
            </div>
        <?php else: ?>
            <?php foreach ($recentEvals as $ev):
                $score = (float)$ev['score'];
                $maxScore = (float)($ev['max_score'] ?: 10);
                $pct = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;
                $color = $pct >= 70 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
                $skillName = $ev['skill_name'] ?? 'Skill';
            ?>
            <div class="m-eval-card">
                <div class="m-eval-top">
                    <span class="m-eval-skill"><?= htmlspecialchars($skillName) ?></span>
                    <span class="m-eval-score" style="color:<?= $color ?>;"><?= $score ?><span style="font-size:11px;color:#6B6B7B;">/<?= $maxScore ?></span></span>
                </div>
                <div class="m-eval-date">
                    <i class="fas fa-calendar" style="font-size:10px;"></i>
                    <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?>
                </div>
                <div class="m-eval-bar">
                    <div class="m-eval-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- FAB: Create Goal -->
<?php if ($canManageGoals): ?>
<button class="m-fab" onclick="mOpenGoalSheet()" aria-label="Create Goal"><i class="fas fa-plus"></i></button>
<?php endif; ?>

<!-- Bottom Sheet: Create/Edit Goal -->
<div class="m-sheet-overlay" id="goalSheetOverlay">
    <div class="m-sheet">
        <div class="m-sheet-handle"></div>
        <div class="m-sheet-header">
            <h3 class="m-sheet-title" id="mSheetTitle">Create Goal</h3>
            <button class="m-sheet-close" onclick="mCloseGoalSheet()" aria-label="Close">&times;</button>
        </div>
        <div class="m-sheet-body">
            <form id="mGoalForm" method="POST" action="process_goals.php">
                <?= function_exists('csrfTokenInput') ? csrfTokenInput() : '' ?>
                <input type="hidden" name="action" id="mFormAction" value="create_goal">
                <input type="hidden" name="goal_id" id="mGoalId" value="">
                <input type="hidden" name="athlete_id" value="<?= (int)$statsUserId ?>">
                <div class="m-form-group">
                    <label class="m-form-label">Title *</label>
                    <input type="text" name="title" id="mGoalTitle" class="m-form-input" required placeholder="e.g., Improve skating speed">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Description</label>
                    <textarea name="description" id="mGoalDesc" class="m-form-textarea" rows="3" placeholder="Describe your goal..."></textarea>
                </div>
                <div class="m-form-row">
                    <div class="m-form-group">
                        <label class="m-form-label">Category</label>
                        <select name="category" id="mGoalCategory" class="m-form-select">
                            <option value="">Select</option>
                            <option value="Skating">Skating</option>
                            <option value="Shooting">Shooting</option>
                            <option value="Passing">Passing</option>
                            <option value="Stickhandling">Stickhandling</option>
                            <option value="Conditioning">Conditioning</option>
                            <option value="Defense">Defense</option>
                            <option value="Goaltending">Goaltending</option>
                            <option value="Mental">Mental</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label">Target Date</label>
                        <input type="date" name="target_date" id="mGoalDate" class="m-form-input">
                    </div>
                </div>
                <div class="m-steps-header">
                    <span class="m-steps-header-label"><i class="fas fa-list-check"></i> Steps</span>
                    <button type="button" class="m-add-step-btn" onclick="mAddStep()"><i class="fas fa-plus"></i> Add</button>
                </div>
                <div id="mStepsList"></div>
                <button type="submit" class="m-sheet-submit"><i class="fas fa-check"></i> Save Goal</button>
            </form>
        </div>
    </div>
</div>

<!-- Bottom Sheet: Goal Detail -->
<div class="m-sheet-overlay" id="goalDetailOverlay">
    <div class="m-sheet">
        <div class="m-sheet-handle"></div>
        <div class="m-sheet-header">
            <h3 class="m-sheet-title">Goal Details</h3>
            <button class="m-sheet-close" onclick="mCloseDetailSheet()" aria-label="Close">&times;</button>
        </div>
        <div class="m-sheet-body" id="mGoalDetailContent">
            <div style="text-align:center;padding:20px;color:#6B6B7B;">Loading...</div>
        </div>
    </div>
</div>

<script>
const mStatsUserId = <?= json_encode($statsUserId) ?>;
const mCurrentUserId = <?= json_encode($user_id) ?>;
const mIsCoach = <?= json_encode($isAnyCoach) ?>;
const mCanManage = <?= json_encode($canManageGoals) ?>;
let mStepCounter = 0;

function mEscapeHtml(t) {
    if (!t) return '';
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function mFormatDate(ds) {
    if (!ds) return '';
    return new Date(ds).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function mShowToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'm-toast' + (type === 'error' ? ' error' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

function mGetCsrf() {
    const f = document.querySelector('#mGoalForm input[name="csrf_token"]');
    return f ? f.value : '';
}

/* Collapsible sections */
function mToggleCollapse(id) {
    const body = document.getElementById('collapse' + id);
    const header = body ? body.previousElementSibling : null;
    const icon = document.getElementById('collapseIcon' + id);
    if (body) {
        body.classList.toggle('open');
        if (header) header.classList.toggle('open');
        if (icon) icon.style.transform = body.classList.contains('open') ? 'rotate(180deg)' : '';
    }
}

/* Goal steps expand */
const mLoadedSteps = {};
function mToggleGoalSteps(goalId) {
    const el = document.getElementById('goalSteps' + goalId);
    const icon = document.getElementById('expandIcon' + goalId);
    if (!el) return;
    const isOpen = el.classList.contains('open');
    el.classList.toggle('open');
    if (icon) icon.style.transform = isOpen ? '' : 'rotate(180deg)';
    if (!isOpen && !mLoadedSteps[goalId]) {
        mLoadedSteps[goalId] = true;
        fetch('process_goals.php?action=get_goal_detail&goal_id=' + goalId)
            .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
            .then(data => {
                if (data.steps && data.steps.length > 0) {
                    el.innerHTML = data.steps.map(s => `
                        <div class="m-step-item ${s.is_completed ? 'completed' : ''}">
                            ${mCanManage ? `<input type="checkbox" class="m-step-check" ${s.is_completed ? 'checked' : ''}
                                onchange="mToggleStep(${s.id}, ${goalId}, this.checked)">` :
                                `<i class="fas ${s.is_completed ? 'fa-check-circle' : 'fa-circle'}" style="color:${s.is_completed ? '#10B981' : '#6B6B7B'};"></i>`}
                            <span class="m-step-title">${mEscapeHtml(s.title || 'Step')}</span>
                        </div>
                    `).join('');
                } else {
                    el.innerHTML = '<div style="text-align:center;padding:10px;color:#6B6B7B;font-size:12px;">No steps defined</div>';
                }
            })
            .catch(() => { el.innerHTML = '<div style="text-align:center;padding:10px;color:#EF4444;font-size:12px;">Failed to load steps</div>'; });
    }
}

function mToggleStep(stepId, goalId, isCompleted) {
    const fd = new FormData();
    fd.append('action', 'complete_step');
    fd.append('step_id', stepId);
    fd.append('goal_id', goalId);
    fd.append('is_completed', isCompleted ? '1' : '0');
    fd.append('csrf_token', mGetCsrf());
    fetch('process_goals.php', { method: 'POST', body: fd })
        .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
        .then(data => {
            if (data.success) {
                mShowToast('Step updated');
                // Reset cache and re-fetch: close then reopen to trigger fresh load
                mLoadedSteps[goalId] = false;
                const el = document.getElementById('goalSteps' + goalId);
                if (el && el.classList.contains('open')) {
                    el.classList.remove('open');
                }
                mToggleGoalSteps(goalId);
            } else {
                mShowToast(data.message || 'Error updating step', 'error');
            }
        })
        .catch(() => mShowToast('Failed to update step', 'error'));
}

/* Goal sheet (create/edit) */
function mOpenGoalSheet() {
    document.getElementById('mSheetTitle').textContent = 'Create Goal';
    document.getElementById('mFormAction').value = 'create_goal';
    document.getElementById('mGoalId').value = '';
    document.getElementById('mGoalForm').reset();
    document.getElementById('mStepsList').innerHTML = '';
    mStepCounter = 0;
    document.getElementById('goalSheetOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function mCloseGoalSheet() {
    document.getElementById('goalSheetOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function mEditGoal(goalId) {
    fetch('process_goals.php?action=get_goal&goal_id=' + goalId)
        .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
        .then(data => {
            document.getElementById('mSheetTitle').textContent = 'Edit Goal';
            document.getElementById('mFormAction').value = 'update_goal';
            document.getElementById('mGoalId').value = goalId;
            document.getElementById('mGoalTitle').value = data.title || '';
            document.getElementById('mGoalDesc').value = data.description || '';
            document.getElementById('mGoalCategory').value = data.category || '';
            document.getElementById('mGoalDate').value = data.target_date || '';
            document.getElementById('mStepsList').innerHTML = '';
            mStepCounter = 0;
            if (data.steps && data.steps.length > 0) {
                data.steps.forEach(s => {
                    mStepCounter++;
                    const escapedTitle = mEscapeHtml(s.title || '');
                    document.getElementById('mStepsList').insertAdjacentHTML('beforeend', `
                        <div class="m-step-input-row" data-step="${mStepCounter}">
                            <input type="text" name="steps[${mStepCounter}][title]" value="${escapedTitle}" placeholder="Step title" required>
                            <input type="hidden" name="steps[${mStepCounter}][id]" value="${s.id}">
                            <input type="hidden" name="steps[${mStepCounter}][order]" value="${mStepCounter}">
                            <button type="button" class="m-step-remove" onclick="this.closest('.m-step-input-row').remove()"><i class="fas fa-times"></i></button>
                        </div>
                    `);
                });
            }
            document.getElementById('goalSheetOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        })
        .catch(() => mShowToast('Failed to load goal data', 'error'));
}

function mAddStep() {
    mStepCounter++;
    document.getElementById('mStepsList').insertAdjacentHTML('beforeend', `
        <div class="m-step-input-row" data-step="${mStepCounter}">
            <input type="text" name="steps[${mStepCounter}][title]" placeholder="Step title" required>
            <input type="hidden" name="steps[${mStepCounter}][order]" value="${mStepCounter}">
            <button type="button" class="m-step-remove" onclick="this.closest('.m-step-input-row').remove()"><i class="fas fa-times"></i></button>
        </div>
    `);
}

/* Goal detail sheet */
function mViewGoalDetail(goalId) {
    document.getElementById('mGoalDetailContent').innerHTML = '<div style="text-align:center;padding:20px;color:#6B6B7B;">Loading...</div>';
    document.getElementById('goalDetailOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    fetch('process_goals.php?action=get_goal_detail&goal_id=' + goalId)
        .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
        .then(data => { mRenderGoalDetail(data); })
        .catch(() => {
            document.getElementById('mGoalDetailContent').innerHTML = '<div style="text-align:center;padding:20px;color:#EF4444;">Failed to load details</div>';
        });
}

function mRenderGoalDetail(data) {
    let stepsHtml = '';
    if (data.steps && data.steps.length > 0) {
        stepsHtml = data.steps.map(s => `
            <div class="m-step-item ${s.is_completed ? 'completed' : ''}">
                ${mCanManage ? `<input type="checkbox" class="m-step-check" ${s.is_completed ? 'checked' : ''}
                    onchange="mToggleStepDetail(${s.id}, ${data.id}, this.checked)">` :
                    `<i class="fas ${s.is_completed ? 'fa-check-circle' : 'fa-circle'}" style="color:${s.is_completed ? '#10B981' : '#6B6B7B'};"></i>`}
                <div style="flex:1;">
                    <span class="m-step-title">${mEscapeHtml(s.title || 'Step')}</span>
                    ${s.is_completed && s.completed_at ? `<div style="font-size:10px;color:#10B981;margin-top:2px;"><i class="fas fa-check"></i> ${mFormatDate(s.completed_at)}</div>` : ''}
                </div>
            </div>
        `).join('');
    } else {
        stepsHtml = '<div style="text-align:center;padding:10px;color:#6B6B7B;font-size:12px;">No steps defined</div>';
    }

    let progressHtml = '';
    if (data.progress && data.progress.length > 0) {
        progressHtml = `<div style="margin-top:16px;padding-top:12px;border-top:1px solid #2D2D3F;">
            <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:8px;">Progress History</div>
            ${data.progress.map(p => `
                <div style="background:#0D0D14;border-left:3px solid #6B46C1;padding:10px 12px;margin-bottom:8px;border-radius:0 6px 6px 0;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span style="font-size:12px;font-weight:600;color:#fff;">${mEscapeHtml(p.user_name || 'User')}</span>
                        <span style="font-size:11px;color:#6B6B7B;">${mFormatDate(p.created_at)}</span>
                    </div>
                    <div style="font-size:12px;color:#A8A8B8;line-height:1.4;">${mEscapeHtml(p.progress_note || '')}</div>
                </div>
            `).join('')}
        </div>`;
    }

    const statusColors = { active: '#3B82F6', completed: '#10B981', archived: '#64748B' };
    const sc = statusColors[data.status] || '#6B6B7B';
    document.getElementById('mGoalDetailContent').innerHTML = `
        <div style="margin-bottom:12px;">
            ${data.category ? `<span class="m-goal-cat">${mEscapeHtml(data.category)}</span>` : ''}
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <h4 class="m-goal-title">${mEscapeHtml(data.title || 'Goal')}</h4>
                <span style="display:inline-block;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;background:${sc}22;color:${sc};">${mEscapeHtml(data.status || 'active')}</span>
            </div>
            ${data.description ? `<p class="m-goal-desc">${mEscapeHtml(data.description)}</p>` : ''}
        </div>
        <div class="m-progress-header">
            <span class="m-progress-name">Progress</span>
            <span class="m-progress-value">${Math.round(data.completion_percentage || 0)}%</span>
        </div>
        <div class="m-progress-bar">
            <div class="m-progress-fill" style="width:${data.completion_percentage || 0}%;background:#8B5CF6;"></div>
        </div>
        <div style="margin-top:14px;">
            <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:8px;"><i class="fas fa-list-check"></i> Steps</div>
            ${stepsHtml}
        </div>
        ${progressHtml}
    `;
}

function mToggleStepDetail(stepId, goalId, isCompleted) {
    const fd = new FormData();
    fd.append('action', 'complete_step');
    fd.append('step_id', stepId);
    fd.append('goal_id', goalId);
    fd.append('is_completed', isCompleted ? '1' : '0');
    fd.append('csrf_token', mGetCsrf());
    fetch('process_goals.php', { method: 'POST', body: fd })
        .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
        .then(data => {
            if (data.success) {
                mShowToast('Step updated');
                mViewGoalDetail(goalId);
            } else {
                mShowToast(data.message || 'Error', 'error');
            }
        })
        .catch(() => mShowToast('Failed to update step', 'error'));
}

function mCloseDetailSheet() {
    document.getElementById('goalDetailOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function mCompleteGoal(goalId) {
    if (!confirm('Mark this goal as completed?')) return;
    const fd = new FormData();
    fd.append('action', 'complete_goal');
    fd.append('goal_id', goalId);
    fd.append('csrf_token', mGetCsrf());
    fetch('process_goals.php', { method: 'POST', body: fd })
        .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
        .then(data => {
            if (data.success) {
                mShowToast('Goal completed!');
                setTimeout(() => location.reload(), 800);
            } else {
                mShowToast(data.message || 'Error completing goal', 'error');
            }
        })
        .catch(() => mShowToast('Failed to complete goal', 'error'));
}

/* Close overlays on background tap */
document.querySelectorAll('.m-sheet-overlay').forEach(ov => {
    ov.addEventListener('click', e => {
        if (e.target === ov) {
            ov.classList.remove('open');
            document.body.style.overflow = '';
        }
    });
});
</script>
