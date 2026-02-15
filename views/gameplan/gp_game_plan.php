<?php
/**
 * Game Plan Builder View (Coach Only) — Standard Site Design
 * Three tabs: Pre-Game / Post-Game / Practice
 * Plan list, create/edit modal, plan cards with game info.
 */

if (!$isAnyCoach) {
    echo '<div class="card"><div class="card-body" style="text-align:center;padding:60px 20px;"><i class="fas fa-lock" style="font-size:48px;color:var(--text-dim);margin-bottom:16px;display:block;"></i><p style="color:var(--text-dim);margin:0;">Coach access required to build game plans.</p></div></div>';
    return;
}

// ── Parameters ────────────────────────────────────────────────
$gp_tab = isset($_GET['tab']) ? preg_replace('/[^a-z_]/', '', $_GET['tab']) : 'pre_game';
if (!in_array($gp_tab, ['pre_game', 'post_game', 'practice'])) $gp_tab = 'pre_game';

// ── Load teams ────────────────────────────────────────────────
$gp_teams = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, division FROM teams WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $gp_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('GP teams: ' . $e->getMessage()); }

// ── Load games ────────────────────────────────────────────────
$gp_games = [];
try {
    $stmt = $pdo->prepare("
        SELECT gs.id, gs.opponent_team, gs.game_date, t.name AS team_name
        FROM game_schedules gs LEFT JOIN teams t ON gs.team_id = t.id
        ORDER BY gs.game_date DESC LIMIT 50
    ");
    $stmt->execute();
    $gp_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('GP games: ' . $e->getMessage()); }

// ── Load plans ────────────────────────────────────────────────
$gp_plans = [];
try {
    $stmt = $pdo->prepare("
        SELECT gp.id, gp.title, gp.description, gp.plan_type, gp.status,
               gp.created_at, gp.updated_at,
               gs.opponent_team, gs.game_date, gs.home_score, gs.away_score,
               t.name AS team_name,
               (SELECT COUNT(*) FROM vr_game_plan_lines gpl WHERE gpl.plan_id = gp.id) AS line_count
        FROM vr_game_plans gp
        LEFT JOIN game_schedules gs ON gp.game_id = gs.id
        LEFT JOIN teams t ON gp.team_id = t.id
        WHERE gp.coach_id = ? AND gp.plan_type = ?
        ORDER BY gp.created_at DESC LIMIT 30
    ");
    $stmt->execute([$user_id, $gp_tab]);
    $gp_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('GP plans: ' . $e->getMessage()); }

// Count plans per type
$gp_counts = ['pre_game' => 0, 'post_game' => 0, 'practice' => 0];
try {
    $stmt = $pdo->prepare("SELECT plan_type, COUNT(*) AS cnt FROM vr_game_plans WHERE coach_id = ? GROUP BY plan_type");
    $stmt->execute([$user_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($gp_counts[$r['plan_type']])) $gp_counts[$r['plan_type']] = (int)$r['cnt'];
    }
} catch (PDOException $e) { error_log('GP counts: ' . $e->getMessage()); }
?>

<!-- Page header -->
<div style="margin-bottom: 24px;">
    <h1 style="font-size: 24px; font-weight: 700; color: var(--text-white); margin: 0 0 6px 0; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-chess-board" style="color: var(--accent);"></i> Game Plan Builder
    </h1>
    <p style="color: var(--text-dim); margin: 0; font-size: 14px;">Create pre-game strategies, post-game reviews, and practice plans</p>
</div>

<!-- Sub-tabs -->
<div class="page-tabs page-tabs-secondary" style="margin-bottom: 20px;">
    <a class="page-tab <?= $gp_tab === 'pre_game' ? 'active' : '' ?>" href="/gameplan.php?page=game_plan&tab=pre_game">
        <i class="fas fa-clipboard-list"></i> Pre-Game (<?= $gp_counts['pre_game'] ?>)
    </a>
    <a class="page-tab <?= $gp_tab === 'post_game' ? 'active' : '' ?>" href="/gameplan.php?page=game_plan&tab=post_game">
        <i class="fas fa-chart-line"></i> Post-Game (<?= $gp_counts['post_game'] ?>)
    </a>
    <a class="page-tab <?= $gp_tab === 'practice' ? 'active' : '' ?>" href="/gameplan.php?page=game_plan&tab=practice">
        <i class="fas fa-dumbbell"></i> Practice (<?= $gp_counts['practice'] ?>)
    </a>
</div>

<!-- Create button -->
<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
    <button type="button" class="btn btn-primary" id="gpCreatePlan"><i class="fas fa-plus"></i> New Plan</button>
</div>

<!-- Plan Cards -->
<?php if (empty($gp_plans)): ?>
<div class="card">
    <div class="card-body" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-chess-board" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px; display: block;"></i>
        <p style="color: var(--text-dim); margin: 0;">No <?= str_replace('_', '-', $gp_tab) ?> plans yet. Create one to get started.</p>
    </div>
</div>
<?php else: ?>
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
    <?php foreach ($gp_plans as $plan): ?>
    <?php
        $status_label = ucfirst($plan['status'] ?? 'draft');
        $status_color = '#A8A8B8';
        $status_bg = 'rgba(168,168,184,0.1)';
        $status_border = 'rgba(168,168,184,0.2)';
        if (($plan['status'] ?? '') === 'active') {
            $status_color = '#3B82F6'; $status_bg = 'rgba(59,130,246,0.1)'; $status_border = 'rgba(59,130,246,0.2)';
        } elseif (($plan['status'] ?? '') === 'completed') {
            $status_color = '#10B981'; $status_bg = 'rgba(16,185,129,0.1)'; $status_border = 'rgba(16,185,129,0.2)';
        } elseif (($plan['status'] ?? '') === 'archived') {
            $status_color = '#8B5CF6'; $status_bg = 'rgba(107,70,193,0.1)'; $status_border = 'rgba(107,70,193,0.2)';
        }
    ?>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
            <h3 style="margin: 0; font-size: 15px; font-weight: 700;"><?= htmlspecialchars($plan['title'] ?? 'Untitled Plan') ?></h3>
            <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; flex-shrink: 0; color: <?= $status_color ?>; background: <?= $status_bg ?>; border: 1px solid <?= $status_border ?>;"><?= $status_label ?></span>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 13px; color: var(--text-dim);">
                <?php if (!empty($plan['opponent_team'])): ?>
                <span><i class="fas fa-hockey-puck" style="margin-right: 4px;"></i>vs <?= htmlspecialchars($plan['opponent_team']) ?></span>
                <?php endif; ?>
                <?php if (!empty($plan['game_date'])): ?>
                <span><i class="fas fa-calendar" style="margin-right: 4px;"></i><?= date('M j, Y', strtotime($plan['game_date'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($plan['team_name'])): ?>
                <span><i class="fas fa-users" style="margin-right: 4px;"></i><?= htmlspecialchars($plan['team_name']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-layer-group" style="margin-right: 4px;"></i><?= (int)$plan['line_count'] ?> lines</span>
            </div>
            <?php if (!empty($plan['description'])): ?>
            <p style="margin: 12px 0 0; padding-top: 12px; border-top: 1px solid var(--border); font-size: 13px; color: var(--text-dim); line-height: 1.5;"><?= htmlspecialchars(substr($plan['description'], 0, 120)) ?><?= strlen($plan['description'] ?? '') > 120 ? '…' : '' ?></p>
            <?php endif; ?>
            <div style="display: flex; justify-content: flex-end; margin-top: 12px;">
                <span style="font-size: 11px; color: var(--text-dim);">Updated <?= date('M j', strtotime($plan['updated_at'] ?? $plan['created_at'])) ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Plan Modal -->
<div class="modal-overlay" id="gpPlanModal" style="display: none; position: fixed; inset: 0; z-index: 200; background: rgba(0,0,0,0.65); align-items: center; justify-content: center;">
    <div class="modal-content" style="max-width: 580px;">
        <div class="modal-header">
            <h2 class="modal-title">Create Game Plan</h2>
            <button type="button" class="modal-close" id="gpClosePlan">&times;</button>
        </div>
        <form method="POST" action="/process_video.php" id="gpPlanForm">
            <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_game_plan">
            <input type="hidden" name="coach_id" value="<?= (int)$user_id ?>">
            <input type="hidden" name="plan_type" value="<?= htmlspecialchars($gp_tab) ?>">

            <div class="modal-body">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--text-dim);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Plan Title <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="title" class="form-input" placeholder="e.g., Game Strategy vs Thunder Bay" required>
                </div>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--text-dim);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Assign to Game</label>
                        <select name="game_id" class="form-input">
                            <option value="">— No Game —</option>
                            <?php foreach ($gp_games as $g): ?>
                            <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars(($g['team_name'] ?? '') . ' vs ' . $g['opponent_team'] . ' – ' . date('M j', strtotime($g['game_date']))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--text-dim);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Team</label>
                        <select name="team_id" class="form-input">
                            <option value="">— Select Team —</option>
                            <?php foreach ($gp_teams as $tm): ?>
                            <option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--text-dim);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Description</label>
                    <textarea name="description" class="form-input" rows="4" style="height: auto; min-height: 100px; resize: vertical;" placeholder="Key strategies, formations, notes…"></textarea>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--text-dim);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Status</label>
                    <select name="status" class="form-input">
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create Plan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('gpPlanModal');
    document.getElementById('gpCreatePlan').addEventListener('click', function() { modal.style.display = 'flex'; });
    document.getElementById('gpClosePlan').addEventListener('click', function() { modal.style.display = 'none'; });
    modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') modal.style.display = 'none'; });
});
</script>
