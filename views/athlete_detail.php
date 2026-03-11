<?php
/**
 * Athlete Detail View
 * Detailed athlete profile with stats, evaluations, and management options
 */

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/image_helper.php';

$athlete_id = isset($_GET['id']) ? intval($_GET['id']) : $user_id;

// Get athlete details
$athlete_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('athlete', 'coach', 'coach_plus')");
$athlete_stmt->execute([$athlete_id]);
$athlete = $athlete_stmt->fetch();
$athlete = $athlete ? decryptUserRow($athlete) : $athlete;

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
    $check = $pdo->prepare("SELECT id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
    $check->execute([$user_id, $athlete_id]);
    $can_view = ($check->rowCount() > 0);
}

if (!$can_view) {
    echo "<div class='alert alert-error'>You do not have permission to view this athlete.</div>";
    exit;
}

// Get athlete stats
$stats_stmt = $pdo->prepare("SELECT * FROM athlete_stats WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stats_stmt->execute([$athlete_id]);
$stats = $stats_stmt->fetchAll();

// Get recent evaluations
$eval_stmt = $pdo->prepare("
    SELECT ae.*, u.first_name, u.last_name 
    FROM athlete_evaluations ae 
    LEFT JOIN users u ON ae.evaluator_id = u.id
    WHERE ae.athlete_id = ? 
    ORDER BY ae.evaluation_date DESC 
    LIMIT 5
");
$eval_stmt->execute([$athlete_id]);
$evaluations = $eval_stmt->fetchAll();
$evaluations = decryptUserRows($evaluations);

// Get assigned teams (combine athlete_teams and team_roster for complete view)
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
$teams = $teams_stmt->fetchAll();
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
$initials = strtoupper(mb_substr($athlete['first_name'], 0, 1) . mb_substr($athlete['last_name'], 0, 1));
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
    </div>
</div>

<div class="ath-detail-card">
    <div class="ath-detail-card-header">
        <i class="fas fa-chart-bar"></i>
        <h2>Statistics</h2>
    </div>
    <div class="ath-detail-card-body">
        <?php if (count($stats) > 0): ?>
            <div class="ath-stat-grid">
                <?php foreach ($stats as $stat): ?>
                    <div class="ath-stat-item">
                        <span class="ath-stat-value"><?= htmlspecialchars($stat['stat_value']) ?></span>
                        <span class="ath-stat-label"><?= htmlspecialchars($stat['stat_name']) ?></span>
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
                        <th>Overall Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $eval): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($eval['evaluation_date'])) ?></td>
                            <td><?= htmlspecialchars($eval['first_name'] . ' ' . $eval['last_name']) ?></td>
                            <td><?= htmlspecialchars($eval['overall_rating']) ?>/10</td>
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
                $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
                $logo_stmt = $pdo->prepare("SELECT id, logo_url FROM teams WHERE id IN ($placeholders)");
                $logo_stmt->execute(array_values($team_ids));
                foreach ($logo_stmt->fetchAll() as $lr) {
                    $team_logos[$lr['id']] = $lr['logo_url'];
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

<?php if ($isAdmin || $isCoach): ?>
    <div class="ath-detail-card">
        <div class="ath-detail-card-header">
            <i class="fas fa-cog"></i>
            <h2>Management Actions</h2>
        </div>
        <div class="ath-detail-card-body">
            <div class="ath-actions-grid">
                <a href="?page=manage_athletes&id=<?= $athlete_id ?>" class="ath-action-btn ath-action-primary"><i class="fas fa-user-edit"></i> Edit Profile</a>
                <a href="?page=evaluations_skills&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-clipboard-check"></i> New Evaluation</a>
                <a href="?page=stats&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-chart-line"></i> Update Stats</a>
                <a href="?page=workouts&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-dumbbell"></i> Workouts</a>
                <a href="?page=nutrition&athlete_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-utensils"></i> Nutrition</a>
                <a href="?page=manage_athletes&action=notes&id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-sticky-note"></i> Notes</a>
                <a href="?page=messages&user_id=<?= $athlete_id ?>" class="ath-action-btn"><i class="fas fa-comments"></i> Message</a>
            </div>
        </div>
    </div>
<?php endif; ?>
