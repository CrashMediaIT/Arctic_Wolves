<?php
/**
 * Athlete Detail View
 * Detailed athlete profile with stats, evaluations, and management options
 */

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/image_helper.php';

$athlete_id = isset($_GET['id']) ? intval($_GET['id']) : $user_id;

// Get athlete details
try {
    $athlete_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('athlete', 'coach', 'coach_plus')");
    $athlete_stmt->execute([$athlete_id]);
    $athlete = $athlete_stmt->fetch(PDO::FETCH_ASSOC);
    $athlete = $athlete ? decryptUserRow($athlete) : $athlete;
} catch (PDOException $e) {
    error_log("Athlete detail fetch error: " . $e->getMessage());
    $athlete = null;
}

if (!$athlete) {
    echo "<div class='alert alert-error'>Athlete not found.</div>";
    exit;
}

// Check permissions
$can_view = false;
if ($isAdmin || $isCoach) {
    $can_view = true;
} elseif ($user_id == $athlete_id) {
    $can_view = true;
} elseif ($isParent) {
    try {
        $check = $pdo->prepare("SELECT id FROM parent_athlete_relationships WHERE parent_id = ? AND athlete_id = ?");
        $check->execute([$user_id, $athlete_id]);
        if ($check->rowCount() > 0) {
            $can_view = true;
        } else {
            $check2 = $pdo->prepare("SELECT id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
            $check2->execute([$user_id, $athlete_id]);
            $can_view = ($check2->rowCount() > 0);
        }
    } catch (PDOException $e) {
        error_log("Athlete detail permission check error: " . $e->getMessage());
        $can_view = false;
    }
}

if (!$can_view) {
    echo "<div class='alert alert-error'>You do not have permission to view this athlete.</div>";
    exit;
}

// Get athlete stats
$stats = [];
try {
    $stats_stmt = $pdo->prepare("SELECT * FROM athlete_stats WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stats_stmt->execute([$athlete_id]);
    $stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Athlete stats fetch error: " . $e->getMessage());
    $stats = [];
}

// Get recent evaluations
$evaluations = [];
try {
    $eval_stmt = $pdo->prepare("
        SELECT ae.id, ae.athlete_id, ae.evaluator_id, ae.skill_id, ae.rating,
               ae.comments, ae.notes, ae.status,
               COALESCE(ae.evaluation_date, ae.eval_date) as evaluation_date,
               ae.created_at,
               u.first_name as evaluator_first_name, u.last_name as evaluator_last_name
        FROM athlete_evaluations ae 
        LEFT JOIN users u ON ae.evaluator_id = u.id
        WHERE ae.athlete_id = ? 
        ORDER BY COALESCE(ae.evaluation_date, ae.eval_date) DESC 
        LIMIT 5
    ");
    $eval_stmt->execute([$athlete_id]);
    $evaluations = $eval_stmt->fetchAll(PDO::FETCH_ASSOC);
    $evaluations = decryptUserRows($evaluations);
} catch (PDOException $e) {
    error_log("Athlete evaluations fetch error: " . $e->getMessage());
    $evaluations = [];
}

// Get assigned teams (combine athlete_teams and team_roster for complete view)
$teams = [];
try {
    $teams_stmt = $pdo->prepare("
        SELECT at.team_name, at.season, at.position, at.jersey_number, at.is_current, at.status, at.team_id
        FROM athlete_teams at 
        WHERE at.athlete_id = ? OR at.user_id = ?
        UNION
        SELECT t.name as team_name, s.name as season, tr.position, tr.jersey_number, 1 as is_current, 'active' as status, tr.team_id
        FROM team_roster tr
        INNER JOIN teams t ON tr.team_id = t.id
        LEFT JOIN seasons s ON tr.season_id = s.id
        WHERE tr.athlete_id = ?
        ORDER BY is_current DESC, season DESC
    ");
    $teams_stmt->execute([$athlete_id, $athlete_id, $athlete_id]);
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Athlete teams fetch error: " . $e->getMessage());
    $teams = [];
}

// Get recent workouts
$workouts = [];
try {
    $workouts_stmt = $pdo->prepare("
        SELECT uw.id, uw.title, uw.status, uw.duration_minutes,
               COALESCE(uw.assigned_date, uw.workout_date) as workout_date,
               uw.completed_at,
               (SELECT COUNT(*) FROM user_workout_items WHERE user_workout_id = uw.id) as exercise_count,
               (SELECT COUNT(*) FROM user_workout_items WHERE user_workout_id = uw.id AND completed_at IS NOT NULL) as completed_count
        FROM user_workouts uw
        WHERE uw.user_id = ?
        ORDER BY COALESCE(uw.assigned_date, uw.workout_date) DESC
        LIMIT 5
    ");
    $workouts_stmt->execute([$athlete_id]);
    $workouts = $workouts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Athlete workouts fetch error: " . $e->getMessage());
    $workouts = [];
}

// Get session evaluations for this athlete
$session_evals = [];
try {
    $se_stmt = $pdo->prepare("
        SELECT se.id as evaluation_id, se.name as evaluation_name, se.status as evaluation_status,
               se.created_at as evaluation_created,
               s.title as session_title, s.session_date
        FROM session_evaluation_athletes sea
        INNER JOIN session_evaluations se ON sea.session_evaluation_id = se.id
        INNER JOIN sessions s ON se.session_id = s.id
        WHERE sea.user_id = ?
        ORDER BY s.session_date DESC
        LIMIT 5
    ");
    $se_stmt->execute([$athlete_id]);
    $session_evals = $se_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Athlete session evaluations fetch error: " . $e->getMessage());
    $session_evals = [];
}
?>

<style>
    .ath-detail-header {
        background: linear-gradient(135deg, var(--primary, #6B46C1) 0%, #4a0070 100%);
        border-radius: var(--radius-lg, 8px);
        padding: 32px;
        margin-bottom: 24px;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 24px;
    }
    .ath-detail-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 900;
        flex-shrink: 0;
        border: 3px solid rgba(255,255,255,0.3);
    }
    .ath-detail-header-info h1 {
        margin: 0 0 6px 0;
        font-size: 28px;
        font-weight: 900;
    }
    .ath-detail-header-info p {
        margin: 0;
        opacity: 0.85;
        font-size: 14px;
    }
    .ath-detail-card {
        background: var(--bg-card, #16161F);
        border: 1px solid var(--border, #2D2D3F);
        border-radius: var(--radius-lg, 8px);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .ath-detail-card-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border, #2D2D3F);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ath-detail-card-header h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: var(--text-white, #fff);
    }
    .ath-detail-card-header i {
        color: var(--primary, #6B46C1);
        font-size: 16px;
    }
    .ath-detail-card-body {
        padding: 20px;
    }
    .ath-profile-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .ath-profile-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ath-profile-item .ath-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-dim, #A8A8B8);
        font-weight: 600;
    }
    .ath-profile-item .ath-value {
        font-size: 14px;
        color: var(--text-white, #fff);
        font-weight: 500;
    }
    .ath-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
    }
    .ath-stat-item {
        background: var(--bg-main, #0A0A0F);
        padding: 16px;
        border-radius: var(--radius-md, 6px);
        text-align: center;
        border: 1px solid var(--border, #2D2D3F);
    }
    .ath-stat-value {
        font-size: 24px;
        font-weight: 900;
        color: var(--primary, #6B46C1);
        display: block;
    }
    .ath-stat-label {
        font-size: 11px;
        color: var(--text-dim, #A8A8B8);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }
    .ath-detail-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
    }
    .ath-detail-table thead th {
        padding: 10px 16px;
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-dim, #A8A8B8);
        font-weight: 700;
        background: var(--bg-main, #0A0A0F);
        border-bottom: 1px solid var(--border, #2D2D3F);
    }
    .ath-detail-table tbody td {
        padding: 12px 16px;
        color: var(--text-white, #fff);
        border-bottom: 1px solid var(--border, #2D2D3F);
        vertical-align: middle;
    }
    .ath-detail-table tbody tr:last-child td {
        border-bottom: none;
    }
    .ath-detail-table tbody tr:hover {
        background: rgba(107, 70, 193, 0.05);
    }
    .ath-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
    }
    .ath-badge-active {
        background: rgba(16, 185, 129, 0.15);
        color: var(--success, #10B981);
    }
    .ath-badge-inactive {
        background: rgba(100, 116, 139, 0.15);
        color: var(--text-dim, #64748b);
    }
    .ath-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
    }
    .ath-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 16px;
        border-radius: var(--radius-md, 6px);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
        border: 1px solid var(--border, #2D2D3F);
        color: var(--text-white, #e0e0e0);
        background: var(--bg-main, #0A0A0F);
        cursor: pointer;
    }
    .ath-action-btn:hover {
        background: rgba(107, 70, 193, 0.1);
        border-color: var(--primary, #6B46C1);
        color: var(--primary-light, #8B5CF6);
        transform: translateY(-1px);
    }
    .ath-action-btn i {
        font-size: 14px;
        width: 16px;
        text-align: center;
    }
    .ath-action-btn.ath-action-primary {
        background: var(--primary, #6B46C1);
        border-color: var(--primary, #6B46C1);
        color: #fff;
    }
    .ath-action-btn.ath-action-primary:hover {
        background: var(--primary-hover, #7C3AED);
        border-color: var(--primary-hover, #7C3AED);
        color: #fff;
    }
    .ath-table-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 600;
        font-size: 12px;
        background: rgba(107, 70, 193, 0.1);
        color: var(--primary-light, #8B5CF6);
        border: 1px solid rgba(107, 70, 193, 0.2);
        transition: all 0.2s ease;
    }
    .ath-table-btn:hover {
        background: var(--primary, #6B46C1);
        color: #fff;
        border-color: var(--primary, #6B46C1);
    }
    .ath-empty-state {
        color: var(--text-dim, #A8A8B8);
        font-size: 14px;
        padding: 16px 0;
        text-align: center;
    }
    .ath-empty-state i {
        display: block;
        font-size: 28px;
        margin-bottom: 8px;
        opacity: 0.4;
    }
    .ath-quick-links {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    @media (max-width: 768px) {
        .ath-detail-header { flex-direction: column; text-align: center; padding: 24px 16px; }
        .ath-profile-grid { grid-template-columns: 1fr; }
        .ath-actions-grid { grid-template-columns: 1fr; }
        .ath-detail-table { font-size: 12px; }
        .ath-detail-table thead th,
        .ath-detail-table tbody td { padding: 8px 10px; }
    }
</style>

<?php
$initials = strtoupper(mb_substr($athlete['first_name'] ?? '', 0, 1) . mb_substr($athlete['last_name'] ?? '', 0, 1));
?>

<div class="ath-detail-header">
    <div class="ath-detail-avatar"><?= $initials ?></div>
    <div class="ath-detail-header-info">
        <h1><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></h1>
        <p><i class="fas fa-user"></i> <?= ucfirst($athlete['role']) ?> &bull; #<?= (int)$athlete['id'] ?><?php if (!empty($athlete['position'])): ?> &bull; <?= htmlspecialchars($athlete['position']) ?><?php endif; ?></p>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
    <div class="alert alert-success">Action completed successfully!</div>
<?php endif; ?>

<!-- Quick Actions -->
<?php if ($isAdmin || $isCoach): ?>
<div class="ath-quick-links" style="margin-bottom: 20px;">
    <a href="?page=stats&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-chart-line"></i> Stats</a>
    <a href="?page=evaluations_skills&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-clipboard-check"></i> Evaluations</a>
    <a href="?page=workouts&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-dumbbell"></i> Workouts</a>
    <a href="?page=nutrition&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-utensils"></i> Nutrition</a>
    <a href="?page=messages&user_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-comments"></i> Message</a>
</div>
<?php endif; ?>

<div class="ath-detail-card">
    <div class="ath-detail-card-header">
        <i class="fas fa-id-card"></i>
        <h2>Profile Information</h2>
    </div>
    <div class="ath-detail-card-body">
        <?php if (isset($_GET['edit']) && $_GET['edit'] == '1' && ($isAdmin || $isCoach)): ?>
        <!-- Edit Mode -->
        <form method="POST" action="process_profile_update.php" style="max-width: 600px;">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="coach_update_athlete">
            <input type="hidden" name="athlete_id" value="<?= $athlete_id ?>">
            <div class="ath-profile-grid">
                <div class="ath-profile-item">
                    <label class="ath-label" for="edit_first_name">First Name</label>
                    <input type="text" id="edit_first_name" name="first_name" class="form-input" value="<?= htmlspecialchars($athlete['first_name'] ?? '') ?>" required style="background:#06080b;border:1px solid #1e293b;color:#fff;padding:8px 12px;border-radius:6px;width:100%;font-size:14px;">
                </div>
                <div class="ath-profile-item">
                    <label class="ath-label" for="edit_last_name">Last Name</label>
                    <input type="text" id="edit_last_name" name="last_name" class="form-input" value="<?= htmlspecialchars($athlete['last_name'] ?? '') ?>" required style="background:#06080b;border:1px solid #1e293b;color:#fff;padding:8px 12px;border-radius:6px;width:100%;font-size:14px;">
                </div>
                <div class="ath-profile-item">
                    <label class="ath-label" for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" class="form-input" value="<?= htmlspecialchars($athlete['email'] ?? '') ?>" required style="background:#06080b;border:1px solid #1e293b;color:#fff;padding:8px 12px;border-radius:6px;width:100%;font-size:14px;">
                </div>
                <div class="ath-profile-item">
                    <label class="ath-label" for="edit_position">Position</label>
                    <select id="edit_position" name="position" style="background:#06080b;border:1px solid #1e293b;color:#fff;padding:8px 12px;border-radius:6px;width:100%;font-size:14px;">
                        <option value="">Select Position</option>
                        <option value="Forward" <?= ($athlete['position'] ?? '') === 'Forward' ? 'selected' : '' ?>>Forward</option>
                        <option value="Defense" <?= ($athlete['position'] ?? '') === 'Defense' ? 'selected' : '' ?>>Defense</option>
                        <option value="Goalie" <?= ($athlete['position'] ?? '') === 'Goalie' ? 'selected' : '' ?>>Goalie</option>
                    </select>
                </div>
                <div class="ath-profile-item">
                    <label class="ath-label" for="edit_birth_date">Birth Date</label>
                    <input type="date" id="edit_birth_date" name="birth_date" value="<?= htmlspecialchars($athlete['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>" style="background:#06080b;border:1px solid #1e293b;color:#fff;padding:8px 12px;border-radius:6px;width:100%;font-size:14px;">
                </div>
                <div class="ath-profile-item">
                    <label class="ath-label" for="edit_shooting_hand">Shooting Hand</label>
                    <select id="edit_shooting_hand" name="shooting_hand" style="background:#06080b;border:1px solid #1e293b;color:#fff;padding:8px 12px;border-radius:6px;width:100%;font-size:14px;">
                        <option value="">Select</option>
                        <option value="left" <?= ($athlete['shooting_hand'] ?? '') === 'left' ? 'selected' : '' ?>>Left</option>
                        <option value="right" <?= ($athlete['shooting_hand'] ?? '') === 'right' ? 'selected' : '' ?>>Right</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: 16px; display: flex; gap: 12px;">
                <button type="submit" class="ath-action-btn ath-action-primary" style="border:none;"><i class="fas fa-save"></i> Save Changes</button>
                <a href="?page=athlete_detail&id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
        <?php else: ?>
        <!-- View Mode -->
        <div class="ath-profile-grid">
            <div class="ath-profile-item">
                <span class="ath-label">Email</span>
                <span class="ath-value"><?= htmlspecialchars($athlete['email']) ?></span>
            </div>
            <div class="ath-profile-item">
                <span class="ath-label">Position</span>
                <span class="ath-value"><?= htmlspecialchars($athlete['position'] ?? 'N/A') ?></span>
            </div>
            <div class="ath-profile-item">
                <span class="ath-label">Birth Date</span>
                <span class="ath-value"><?= $athlete['birth_date'] ? date('M d, Y', strtotime($athlete['birth_date'])) : 'N/A' ?></span>
            </div>
            <div class="ath-profile-item">
                <span class="ath-label">Shooting Hand</span>
                <span class="ath-value"><?= ucfirst($athlete['shooting_hand'] ?? 'N/A') ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ath-detail-card">
    <div class="ath-detail-card-header">
        <i class="fas fa-chart-bar"></i>
        <h2>Statistics</h2>
    </div>
    <div class="ath-detail-card-body">
        <?php if (count($stats) > 0):
            $stat = $stats[0]; // Most recent stats record
            $stat_items = [
                'Games' => $stat['games_played'] ?? 0,
                'Goals' => $stat['goals'] ?? 0,
                'Assists' => $stat['assists'] ?? 0,
                'Points' => $stat['points'] ?? 0,
                'PIM' => $stat['penalty_minutes'] ?? 0,
                '+/-' => $stat['plus_minus'] ?? 0,
            ];
        ?>
            <div class="ath-stat-grid">
                <?php foreach ($stat_items as $label => $value): ?>
                    <div class="ath-stat-item">
                        <span class="ath-stat-value"><?= htmlspecialchars((string)$value) ?></span>
                        <span class="ath-stat-label"><?= htmlspecialchars($label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="ath-empty-state">
                <i class="fas fa-chart-bar"></i>
                No statistics recorded yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="ath-detail-card">
    <div class="ath-detail-card-header">
        <i class="fas fa-clipboard-check"></i>
        <h2>Recent Evaluations</h2>
    </div>
    <div class="ath-detail-card-body" style="padding: 0;">
        <?php if (count($evaluations) > 0): ?>
            <table class="ath-detail-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Evaluator</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $eval): ?>
                        <tr>
                            <td><?= !empty($eval['evaluation_date']) ? date('M d, Y', strtotime($eval['evaluation_date'])) : 'N/A' ?></td>
                            <td><?= htmlspecialchars(trim(($eval['evaluator_first_name'] ?? '') . ' ' . ($eval['evaluator_last_name'] ?? '')) ?: 'N/A') ?></td>
                            <td><?= !empty($eval['rating']) ? htmlspecialchars($eval['rating']) . '/10' : 'N/A' ?></td>
                            <td><span class="ath-badge <?= ($eval['status'] ?? '') === 'completed' ? 'ath-badge-active' : 'ath-badge-inactive' ?>"><?= ucfirst($eval['status'] ?? 'draft') ?></span></td>
                            <td>
                                <a href="?page=evaluations_skills&athlete_id=<?= $athlete_id ?>" class="ath-table-btn"><i class="fas fa-eye"></i> View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ath-empty-state" style="padding: 24px;">
                <i class="fas fa-clipboard-check"></i>
                No evaluations recorded yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="ath-detail-card">
    <div class="ath-detail-card-header">
        <i class="fas fa-users"></i>
        <h2>Team Assignments</h2>
    </div>
    <div class="ath-detail-card-body" style="padding: 0;">
        <?php if (count($teams) > 0): ?>
            <?php
            // Pre-fetch all team logos to avoid N+1 queries
            $team_ids = array_filter(array_unique(array_column($teams, 'team_id')));
            $team_logos = [];
            if (!empty($team_ids)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
                    $logo_stmt = $pdo->prepare("SELECT id, logo_url FROM teams WHERE id IN ($placeholders)");
                    $logo_stmt->execute(array_values($team_ids));
                    foreach ($logo_stmt->fetchAll(PDO::FETCH_ASSOC) as $lr) {
                        $team_logos[$lr['id']] = $lr['logo_url'];
                    }
                } catch (PDOException $e) {
                    error_log("Team logos fetch error: " . $e->getMessage());
                }
            }
            ?>
            <table class="ath-detail-table">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Season</th>
                        <th>Position</th>
                        <th>Jersey #</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $displayed_teams = [];
                    foreach ($teams as $team): 
                        // Deduplicate by team_name + season
                        $key = ($team['team_name'] ?? '') . '|' . ($team['season'] ?? '');
                        if (isset($displayed_teams[$key])) continue;
                        $displayed_teams[$key] = true;
                    ?>
                        <tr>
                            <td>
                                <?php if (!empty($team['team_id']) && !empty($team_logos[$team['team_id']])): ?>
                                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $team_logos[$team['team_id']])) ?>" alt="" style="width: 22px; height: 22px; border-radius: 4px; object-fit: contain; vertical-align: middle; margin-right: 8px;">
                                <?php endif; ?>
                                <?= htmlspecialchars($team['team_name']) ?>
                            </td>
                            <td><?= htmlspecialchars($team['season'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($team['position'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($team['jersey_number'] ?? 'N/A') ?></td>
                            <td>
                                <?php if (!empty($team['is_current'])): ?>
                                    <span class="ath-badge ath-badge-active">Active</span>
                                <?php else: ?>
                                    <span class="ath-badge ath-badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ath-empty-state" style="padding: 24px;">
                <i class="fas fa-users"></i>
                No team assignments yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="ath-detail-card">
    <div class="ath-detail-card-header">
        <i class="fas fa-dumbbell"></i>
        <h2>Recent Workouts</h2>
    </div>
    <div class="ath-detail-card-body" style="padding: 0;">
        <?php if (count($workouts) > 0): ?>
            <table class="ath-detail-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Workout</th>
                        <th>Exercises</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workouts as $w): ?>
                        <tr>
                            <td><?= !empty($w['workout_date']) ? date('M d, Y', strtotime($w['workout_date'])) : 'N/A' ?></td>
                            <td><?= htmlspecialchars($w['title'] ?? 'Workout') ?></td>
                            <td><?= (int)($w['completed_count'] ?? 0) ?>/<?= (int)($w['exercise_count'] ?? 0) ?></td>
                            <td><span class="ath-badge <?= ($w['status'] ?? '') === 'completed' ? 'ath-badge-active' : 'ath-badge-inactive' ?>"><?= ucfirst($w['status'] ?? 'scheduled') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ath-empty-state" style="padding: 24px;">
                <i class="fas fa-dumbbell"></i>
                No workouts assigned yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="ath-detail-card">
    <div class="ath-detail-card-header">
        <i class="fas fa-clipboard-list"></i>
        <h2>Session Evaluations</h2>
    </div>
    <div class="ath-detail-card-body" style="padding: 0;">
        <?php if (count($session_evals) > 0): ?>
            <table class="ath-detail-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Session</th>
                        <th>Evaluation</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($session_evals as $se): ?>
                        <tr>
                            <td><?= !empty($se['session_date']) ? date('M d, Y', strtotime($se['session_date'])) : 'N/A' ?></td>
                            <td><?= htmlspecialchars($se['session_title'] ?? 'Session') ?></td>
                            <td><?= htmlspecialchars($se['evaluation_name'] ?? 'Evaluation') ?></td>
                            <td><span class="ath-badge <?= ($se['evaluation_status'] ?? '') === 'completed' ? 'ath-badge-active' : 'ath-badge-inactive' ?>"><?= ucfirst($se['evaluation_status'] ?? 'draft') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ath-empty-state" style="padding: 24px;">
                <i class="fas fa-clipboard-list"></i>
                No session evaluations yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin || $isCoach): ?>
    <div class="ath-detail-card">
        <div class="ath-detail-card-header">
            <i class="fas fa-cog"></i>
            <h2>Management Actions</h2>
        </div>
        <div class="ath-detail-card-body">
            <div class="ath-actions-grid">
                <a href="?page=athlete_detail&id=<?= $athlete_id ?>&edit=1" class="ath-action-btn ath-action-primary"><i class="fas fa-user-edit"></i> Edit Profile</a>
                <a href="?page=evaluations_skills&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-clipboard-check"></i> New Evaluation</a>
                <a href="?page=stats&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-chart-line"></i> Update Stats</a>
                <a href="?page=workouts&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-dumbbell"></i> Workouts</a>
                <a href="?page=nutrition&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-utensils"></i> Nutrition</a>
                <a href="?page=athlete_notes&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-sticky-note"></i> Notes</a>
                <a href="?page=messages&user_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-comments"></i> Message</a>
            </div>
        </div>
    </div>
<?php endif; ?>
