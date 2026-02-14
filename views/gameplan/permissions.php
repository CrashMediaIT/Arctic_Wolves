<?php
/**
 * Game Plan - Video Permissions View (Admin Only)
 * Team selector, user grid with permission toggles.
 * Uses vr_video_permissions table.
 */

if (!$isAdmin) {
    echo '<div class="gp-empty"><i class="fas fa-lock"></i><p>Admin access required to manage permissions.</p></div>';
    return;
}

// ── Parameters ────────────────────────────────────────────────
$perm_team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// ── Load teams ────────────────────────────────────────────────
$perm_teams = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, division FROM teams WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $perm_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('Perm teams: ' . $e->getMessage()); }

// Auto-select first team if none chosen
if ($perm_team_id === 0 && !empty($perm_teams)) {
    $perm_team_id = (int)$perm_teams[0]['id'];
}

// ── Load team roster with permissions ─────────────────────────
$perm_users = [];
if ($perm_team_id > 0) {
    try {
        // Get users from team roster using UNION for better index usage
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.role, u.email,
                   vp.can_upload, vp.can_clip, vp.can_tag, vp.can_publish, vp.can_delete
            FROM users u
            LEFT JOIN vr_video_permissions vp ON vp.user_id = u.id AND vp.team_id = ?
            WHERE u.is_active = 1 AND u.id IN (
                SELECT athlete_id FROM athlete_teams WHERE team_id = ? AND athlete_id IS NOT NULL
                UNION
                SELECT user_id FROM athlete_teams WHERE team_id = ? AND user_id IS NOT NULL
                UNION
                SELECT coach_id FROM teams WHERE id = ? AND coach_id IS NOT NULL
                UNION
                SELECT assistant_coach_id FROM teams WHERE id = ? AND assistant_coach_id IS NOT NULL
            )
            ORDER BY u.role DESC, u.last_name, u.first_name
        ");
        $stmt->execute([$perm_team_id, $perm_team_id, $perm_team_id, $perm_team_id, $perm_team_id]);
        $perm_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $perm_users = decryptUserRows($perm_users);
    } catch (PDOException $e) { error_log('Perm users: ' . $e->getMessage()); }

    if (empty($perm_users)) {
        // Fallback: try all active users if team roster query returns empty
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.first_name, u.last_name, u.role, u.email,
                       vp.can_upload, vp.can_clip, vp.can_tag, vp.can_publish, vp.can_delete
                FROM users u
                LEFT JOIN vr_video_permissions vp ON vp.user_id = u.id AND vp.team_id = ?
                WHERE u.is_active = 1
                ORDER BY u.role DESC, u.last_name, u.first_name
                LIMIT 50
            ");
            $stmt->execute([$perm_team_id]);
            $perm_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $perm_users = decryptUserRows($perm_users);
        } catch (PDOException $e) { error_log('Perm fallback: ' . $e->getMessage()); }
    }
}

// Current team name
$perm_team_name = '';
foreach ($perm_teams as $t) {
    if ((int)$t['id'] === $perm_team_id) { $perm_team_name = $t['name']; break; }
}

$perm_columns = [
    'can_upload'  => ['label' => 'Upload',  'icon' => 'fa-cloud-upload-alt', 'desc' => 'Upload video sources'],
    'can_clip'    => ['label' => 'Clip',    'icon' => 'fa-scissors',          'desc' => 'Create video clips'],
    'can_tag'     => ['label' => 'Tag',     'icon' => 'fa-tags',              'desc' => 'Tag clips and athletes'],
    'can_publish' => ['label' => 'Publish', 'icon' => 'fa-share',             'desc' => 'Share clips with team'],
    'can_delete'  => ['label' => 'Delete',  'icon' => 'fa-trash-alt',         'desc' => 'Delete videos and clips'],
];
?>

<!-- Page header -->
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-user-shield"></i> Video Permissions</h1>
    <p class="gp-page-desc">Control who can upload, clip, tag, publish, and delete video content</p>
</div>

<!-- Team Selector -->
<div class="vr-tabs-bar">
    <div class="vr-filters" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;width:100%">
        <label class="vr-filter-label"><i class="fas fa-users"></i> Team:</label>
        <select class="vr-input vr-select" onchange="location.href='/gameplan.php?page=permissions&team_id='+this.value">
            <?php foreach ($perm_teams as $tm): ?>
            <option value="<?= (int)$tm['id'] ?>" <?= $perm_team_id === (int)$tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['name']) ?><?= !empty($tm['division']) ? ' (' . htmlspecialchars($tm['division']) . ')' : '' ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($perm_team_name): ?>
        <span class="vr-team-count"><?= count($perm_users) ?> member<?= count($perm_users) !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </div>
</div>

<!-- Permission Legend -->
<div class="vr-perm-legend">
    <?php foreach ($perm_columns as $col => $info): ?>
    <div class="vr-legend-item" title="<?= htmlspecialchars($info['desc']) ?>">
        <i class="fas <?= $info['icon'] ?>"></i> <?= $info['label'] ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- User Grid -->
<?php if (empty($perm_users)): ?>
<div class="gp-empty">
    <i class="fas fa-users-slash"></i>
    <p>No users found for this team. Assign users to teams in the admin panel.</p>
</div>
<?php else: ?>
<form method="POST" action="/process_video.php" id="vrPermForm">
    <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
    <input type="hidden" name="action" value="update_video_permissions">
    <input type="hidden" name="team_id" value="<?= $perm_team_id ?>">

    <div class="vr-perm-grid">
        <!-- Header -->
        <div class="vr-perm-row vr-perm-header-row">
            <div class="vr-perm-user-col">User</div>
            <div class="vr-perm-role-col">Role</div>
            <?php foreach ($perm_columns as $col => $info): ?>
            <div class="vr-perm-toggle-col" title="<?= htmlspecialchars($info['desc']) ?>">
                <i class="fas <?= $info['icon'] ?>"></i><br><?= $info['label'] ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($perm_users as $pu): ?>
        <div class="vr-perm-row">
            <div class="vr-perm-user-col">
                <div class="vr-perm-avatar"><?= strtoupper(substr($pu['first_name'] ?? '?', 0, 1) . substr($pu['last_name'] ?? '?', 0, 1)) ?></div>
                <span><?= htmlspecialchars(trim(($pu['first_name'] ?? '') . ' ' . ($pu['last_name'] ?? ''))) ?></span>
            </div>
            <div class="vr-perm-role-col">
                <span class="vr-role-badge vr-role-<?= htmlspecialchars($pu['role'] ?? 'athlete') ?>"><?= htmlspecialchars(ucfirst($pu['role'] ?? 'athlete')) ?></span>
            </div>
            <?php foreach ($perm_columns as $col => $info): ?>
            <div class="vr-perm-toggle-col">
                <label class="vr-toggle">
                    <input type="checkbox" name="perms[<?= (int)$pu['id'] ?>][<?= $col ?>]" value="1" <?= !empty($pu[$col]) ? 'checked' : '' ?>>
                    <span class="vr-toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="vr-form-actions">
        <button type="submit" class="vr-btn-primary"><i class="fas fa-save"></i> Save Permissions</button>
    </div>
</form>
<?php endif; ?>

<style>
.vr-tabs-bar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; }
.vr-filter-label { font-size: 13px; font-weight: 600; color: var(--gp-text-muted); display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
.vr-filter-label i { color: var(--gp-primary-light); }
.vr-input { background: var(--gp-bg); border: 1px solid var(--gp-border); border-radius: 8px; color: var(--gp-text); font-size: 13px; padding: 9px 14px; font-family: 'Inter', sans-serif; height: 40px; box-sizing: border-box; }
.vr-input:focus { border-color: var(--gp-primary-light); outline: none; }
.vr-select { min-width: 200px; }
.vr-team-count { font-size: 12px; color: var(--gp-text-dim); background: rgba(107,70,193,.08); padding: 4px 12px; border-radius: 16px; }

.vr-perm-legend { display: flex; gap: 16px; margin-bottom: 16px; padding: 10px 16px; background: rgba(107,70,193,.06); border-radius: 10px; flex-wrap: wrap; }
.vr-legend-item { font-size: 12px; color: var(--gp-text-muted); display: inline-flex; align-items: center; gap: 6px; }
.vr-legend-item i { color: var(--gp-primary-light); font-size: 11px; }

.vr-perm-grid { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; overflow: hidden; }
.vr-perm-row { display: grid; grid-template-columns: 2fr 100px repeat(5, 80px); align-items: center; padding: 12px 20px; border-bottom: 1px solid var(--gp-border); gap: 8px; }
.vr-perm-row:last-child { border-bottom: none; }
.vr-perm-header-row { background: rgba(10,10,15,.4); font-size: 11px; font-weight: 700; color: var(--gp-text-dim); text-transform: uppercase; letter-spacing: .5px; padding: 14px 20px; }

.vr-perm-user-col { display: flex; align-items: center; gap: 10px; min-width: 0; }
.vr-perm-user-col span { font-size: 13px; font-weight: 600; color: var(--gp-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vr-perm-avatar { width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); color: #fff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.vr-perm-role-col { text-align: center; }
.vr-perm-toggle-col { text-align: center; font-size: 11px; }

.vr-role-badge { padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.vr-role-admin { background: rgba(239,68,68,.1); color: #EF4444; border: 1px solid rgba(239,68,68,.2); }
.vr-role-coach { background: rgba(59,130,246,.1); color: #3B82F6; border: 1px solid rgba(59,130,246,.2); }
.vr-role-team_coach { background: rgba(16,185,129,.1); color: #10B981; border: 1px solid rgba(16,185,129,.2); }
.vr-role-athlete { background: rgba(168,168,184,.1); color: var(--gp-text-muted); border: 1px solid rgba(168,168,184,.2); }

/* Toggle Switch */
.vr-toggle { position: relative; display: inline-block; width: 40px; height: 22px; cursor: pointer; }
.vr-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.vr-toggle-slider { position: absolute; inset: 0; background: var(--gp-bg); border: 1px solid var(--gp-border); border-radius: 22px; transition: all .25s; }
.vr-toggle-slider::before { content: ''; position: absolute; width: 16px; height: 16px; left: 2px; bottom: 2px; background: var(--gp-text-dim); border-radius: 50%; transition: all .25s; }
.vr-toggle input:checked + .vr-toggle-slider { background: rgba(107,70,193,.2); border-color: var(--gp-primary); }
.vr-toggle input:checked + .vr-toggle-slider::before { transform: translateX(18px); background: var(--gp-primary-light); }

.vr-form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; margin-top: 24px; }
.vr-btn-primary { padding: 10px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); border: none; color: #fff; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-family: 'Inter', sans-serif; transition: opacity .2s; }
.vr-btn-primary:hover { opacity: .9; }

@media (max-width: 768px) {
    .vr-perm-row { grid-template-columns: 1fr; gap: 12px; padding: 16px; }
    .vr-perm-header-row { display: none; }
    .vr-perm-toggle-col { display: flex; align-items: center; justify-content: space-between; }
    .vr-perm-toggle-col::before { content: attr(title); font-size: 12px; color: var(--gp-text-muted); }
}
</style>
