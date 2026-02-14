<?php
/**
 * Game Plan - Video Permissions View (Admin Only)
 * Restyled with site-standard classes: card, btn, form-select, filter-box.
 */

if (!$isAdmin) {
    echo '<div class="empty-state" style="text-align:center;padding:40px"><i class="fas fa-lock" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px"></i><h3>Admin Access Required</h3><p style="color:var(--text-muted)">You need admin access to manage permissions.</p></div>';
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
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> Video Permissions</h1>
    <p>Control who can upload, clip, tag, publish, and delete video content</p>
</div>

<!-- Team Selector -->
<div class="filter-box" style="margin-bottom: 20px;">
    <div class="filter-box-header"><i class="fas fa-users"></i> Select Team</div>
    <div class="filter-box-content">
        <div class="filter-row">
            <div class="filter-field">
                <label>Team</label>
                <select class="form-select" onchange="location.href='/gameplan.php?page=permissions&team_id='+this.value">
                    <?php foreach ($perm_teams as $tm): ?>
                    <option value="<?= (int)$tm['id'] ?>" <?= $perm_team_id === (int)$tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['name']) ?><?= !empty($tm['division']) ? ' (' . htmlspecialchars($tm['division']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($perm_team_name): ?>
            <div class="filter-field" style="display:flex;align-items:flex-end;">
                <span style="font-size:12px;color:var(--text-muted);background:rgba(107,70,193,.08);padding:8px 14px;border-radius:8px;"><?= count($perm_users) ?> member<?= count($perm_users) !== 1 ? 's' : '' ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Permission Legend -->
<div style="display:flex;gap:16px;margin-bottom:16px;padding:10px 16px;background:rgba(107,70,193,.06);border-radius:10px;flex-wrap:wrap;">
    <?php foreach ($perm_columns as $col => $info): ?>
    <div style="font-size:12px;color:var(--text-muted);display:inline-flex;align-items:center;gap:6px;" title="<?= htmlspecialchars($info['desc']) ?>">
        <i class="fas <?= $info['icon'] ?>" style="color:var(--primary-light);font-size:11px;"></i> <?= $info['label'] ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- User Grid -->
<?php if (empty($perm_users)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state" style="text-align:center;padding:40px;">
            <i class="fas fa-users-slash" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px;"></i>
            <h3 style="color:var(--text-secondary);">No Users Found</h3>
            <p style="color:var(--text-muted);">Assign users to teams in the admin panel.</p>
        </div>
    </div>
</div>
<?php else: ?>
<form method="POST" action="/process_video.php">
    <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
    <input type="hidden" name="action" value="update_video_permissions">
    <input type="hidden" name="team_id" value="<?= $perm_team_id ?>">

    <div class="card">
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);">
                        <th style="padding:14px 20px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">User</th>
                        <th style="padding:14px 12px;text-align:center;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;width:90px;">Role</th>
                        <?php foreach ($perm_columns as $col => $info): ?>
                        <th style="padding:14px 12px;text-align:center;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;width:80px;" title="<?= htmlspecialchars($info['desc']) ?>">
                            <i class="fas <?= $info['icon'] ?>" style="display:block;margin-bottom:4px;"></i><?= $info['label'] ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($perm_users as $pu): ?>
                    <?php
                        $role = $pu['role'] ?? 'athlete';
                        $role_colors = ['admin' => ['bg' => 'rgba(239,68,68,.1)', 'color' => '#EF4444', 'border' => 'rgba(239,68,68,.2)'], 'coach' => ['bg' => 'rgba(59,130,246,.1)', 'color' => '#3B82F6', 'border' => 'rgba(59,130,246,.2)'], 'team_coach' => ['bg' => 'rgba(16,185,129,.1)', 'color' => '#10B981', 'border' => 'rgba(16,185,129,.2)'], 'athlete' => ['bg' => 'rgba(168,168,184,.1)', 'color' => 'var(--text-muted)', 'border' => 'rgba(168,168,184,.2)']];
                        $rc = $role_colors[$role] ?? $role_colors['athlete'];
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 20px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <?= strtoupper(substr($pu['first_name'] ?? '?', 0, 1) . substr($pu['last_name'] ?? '?', 0, 1)) ?>
                                </div>
                                <span style="font-size:13px;font-weight:600;"><?= htmlspecialchars(trim(($pu['first_name'] ?? '') . ' ' . ($pu['last_name'] ?? ''))) ?></span>
                            </div>
                        </td>
                        <td style="padding:12px;text-align:center;">
                            <span style="padding:3px 8px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase;background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;border:1px solid <?= $rc['border'] ?>;"><?= htmlspecialchars(ucfirst($role)) ?></span>
                        </td>
                        <?php foreach ($perm_columns as $col => $info): ?>
                        <td style="padding:12px;text-align:center;">
                            <label style="position:relative;display:inline-block;width:40px;height:22px;cursor:pointer;">
                                <input type="checkbox" name="perms[<?= (int)$pu['id'] ?>][<?= $col ?>]" value="1" <?= !empty($pu[$col]) ? 'checked' : '' ?> style="opacity:0;width:0;height:0;position:absolute;">
                                <span style="position:absolute;inset:0;background:var(--bg-secondary);border:1px solid var(--border);border-radius:22px;transition:all .25s;"></span>
                            </label>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:20px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Permissions</button>
    </div>
</form>
<?php endif; ?>

<style>
/* Toggle switch styling */
label[style*="width:40px"] input:checked + span {
    background: rgba(107,70,193,.2) !important;
    border-color: var(--primary) !important;
}
label[style*="width:40px"] span::before {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    left: 2px;
    bottom: 2px;
    background: var(--text-muted);
    border-radius: 50%;
    transition: all .25s;
}
label[style*="width:40px"] input:checked + span::before {
    transform: translateX(18px);
    background: var(--primary-light);
}
</style>
