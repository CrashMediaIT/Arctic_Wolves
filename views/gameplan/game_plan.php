<?php
/**
 * Game Plan - Game Plan Builder View (Coach Only)
 * Three tabs: Pre-Game / Post-Game / Practice
 * Plan list, create/edit modal, plan cards with game info.
 */

if (!$isAnyCoach) {
    echo '<div class="gp-empty"><i class="fas fa-lock"></i><p>Coach access required to build game plans.</p></div>';
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
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-chess-board"></i> Game Plan Builder</h1>
    <p class="gp-page-desc">Create pre-game strategies, post-game reviews, and practice plans</p>
</div>

<!-- Tabs & Create -->
<div class="vr-tabs-bar">
    <div class="vr-tabs">
        <a class="vr-tab <?= $gp_tab === 'pre_game' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=game_plan&tab=pre_game">
            <i class="fas fa-clipboard-list"></i> Pre-Game (<?= $gp_counts['pre_game'] ?>)
        </a>
        <a class="vr-tab <?= $gp_tab === 'post_game' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=game_plan&tab=post_game">
            <i class="fas fa-chart-line"></i> Post-Game (<?= $gp_counts['post_game'] ?>)
        </a>
        <a class="vr-tab <?= $gp_tab === 'practice' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=game_plan&tab=practice">
            <i class="fas fa-dumbbell"></i> Practice (<?= $gp_counts['practice'] ?>)
        </a>
    </div>
    <button type="button" class="vr-btn-primary" id="vrCreatePlan"><i class="fas fa-plus"></i> New Plan</button>
</div>

<!-- Plan Cards -->
<?php if (empty($gp_plans)): ?>
<div class="gp-empty">
    <i class="fas fa-chess-board"></i>
    <p>No <?= str_replace('_', '-', $gp_tab) ?> plans yet. Create one to get started.</p>
</div>
<?php else: ?>
<div class="gp-grid">
    <?php foreach ($gp_plans as $plan): ?>
    <?php
        $status_class = 'vr-badge-draft';
        $status_label = ucfirst($plan['status'] ?? 'draft');
        if (($plan['status'] ?? '') === 'active') $status_class = 'vr-badge-active';
        elseif (($plan['status'] ?? '') === 'completed') $status_class = 'vr-badge-completed';
        elseif (($plan['status'] ?? '') === 'archived') $status_class = 'vr-badge-archived';
    ?>
    <div class="gp-card vr-plan-card">
        <div class="gp-card-body">
            <div class="vr-plan-header">
                <div class="gp-card-title"><?= htmlspecialchars($plan['title'] ?? 'Untitled Plan') ?></div>
                <span class="vr-status-badge <?= $status_class ?>"><?= $status_label ?></span>
            </div>
            <div class="gp-card-meta">
                <?php if (!empty($plan['opponent_team'])): ?>
                <span><i class="fas fa-hockey-puck"></i> vs <?= htmlspecialchars($plan['opponent_team']) ?></span>
                <?php endif; ?>
                <?php if (!empty($plan['game_date'])): ?>
                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($plan['game_date'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($plan['team_name'])): ?>
                <span><i class="fas fa-users"></i> <?= htmlspecialchars($plan['team_name']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-layer-group"></i> <?= (int)$plan['line_count'] ?> lines</span>
            </div>
            <?php if (!empty($plan['description'])): ?>
            <div class="vr-plan-desc"><?= htmlspecialchars(substr($plan['description'], 0, 120)) ?><?= strlen($plan['description'] ?? '') > 120 ? '…' : '' ?></div>
            <?php endif; ?>
            <div class="vr-plan-footer">
                <span class="vr-plan-date">Updated <?= date('M j', strtotime($plan['updated_at'] ?? $plan['created_at'])) ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Plan Modal -->
<div class="vr-modal-overlay" id="vrPlanModal">
    <div class="vr-modal-sheet">
        <div class="vr-modal-header">
            <span class="vr-modal-title">Create Game Plan</span>
            <button type="button" class="vr-modal-close" id="vrClosePlan">&times;</button>
        </div>
        <form method="POST" action="/process_video.php" id="vrPlanForm">
            <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_game_plan">
            <input type="hidden" name="coach_id" value="<?= (int)$user_id ?>">
            <input type="hidden" name="plan_type" value="<?= htmlspecialchars($gp_tab) ?>">

            <div class="vr-form-group">
                <label>Plan Title <span class="vr-req">*</span></label>
                <input type="text" name="title" class="vr-input" placeholder="e.g., Game Strategy vs Thunder Bay" required>
            </div>
            <div class="vr-form-row">
                <div class="vr-form-group">
                    <label>Assign to Game</label>
                    <select name="game_id" class="vr-input">
                        <option value="">— No Game —</option>
                        <?php foreach ($gp_games as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars(($g['team_name'] ?? '') . ' vs ' . $g['opponent_team'] . ' – ' . date('M j', strtotime($g['game_date']))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="vr-form-group">
                    <label>Team</label>
                    <select name="team_id" class="vr-input">
                        <option value="">— Select Team —</option>
                        <?php foreach ($gp_teams as $tm): ?>
                        <option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="vr-form-group">
                <label>Description</label>
                <textarea name="description" class="vr-input vr-textarea" rows="4" placeholder="Key strategies, formations, notes…"></textarea>
            </div>
            <div class="vr-form-group">
                <label>Status</label>
                <select name="status" class="vr-input">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="vr-form-actions">
                <button type="submit" class="vr-btn-primary"><i class="fas fa-plus"></i> Create Plan</button>
            </div>
        </form>
    </div>
</div>

<style>
.vr-tabs-bar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.vr-tabs { display: flex; gap: 4px; background: rgba(10,10,15,.6); padding: 5px; border-radius: 10px; border: 1px solid rgba(45,45,63,.5); flex-wrap: wrap; }
.vr-tab { padding: 10px 18px; background: transparent; border: none; color: var(--gp-text-dim); border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: 7px; font-family: 'Inter', sans-serif; text-decoration: none; }
.vr-tab:hover { color: var(--gp-text); background: rgba(107,70,193,.12); }
.vr-tab.vr-tab-active { color: #fff; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); }
.vr-btn-primary { padding: 10px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); border: none; color: #fff; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-family: 'Inter', sans-serif; transition: opacity .2s; }
.vr-btn-primary:hover { opacity: .9; }

.vr-plan-card { cursor: default; }
.vr-plan-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px; }
.vr-plan-desc { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--gp-border); font-size: 13px; color: var(--gp-text-dim); line-height: 1.5; }
.vr-plan-footer { display: flex; justify-content: flex-end; margin-top: 10px; }
.vr-plan-date { font-size: 11px; color: var(--gp-text-dim); }

.vr-status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 16px; font-size: 10px; font-weight: 700; text-transform: uppercase; white-space: nowrap; flex-shrink: 0; }
.vr-badge-draft { background: rgba(168,168,184,.1); color: var(--gp-text-muted); border: 1px solid rgba(168,168,184,.2); }
.vr-badge-active { background: rgba(59,130,246,.1); color: #3B82F6; border: 1px solid rgba(59,130,246,.2); }
.vr-badge-completed { background: rgba(16,185,129,.1); color: #10B981; border: 1px solid rgba(16,185,129,.2); }
.vr-badge-archived { background: rgba(107,70,193,.1); color: var(--gp-primary-light); border: 1px solid rgba(107,70,193,.2); }

.vr-modal-overlay { display: none; position: fixed; inset: 0; z-index: 200; background: rgba(0,0,0,.65); align-items: center; justify-content: center; }
.vr-modal-overlay.vr-modal-open { display: flex; }
.vr-modal-sheet { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 16px; width: 90%; max-width: 580px; padding: 24px; animation: vrSlideIn .25s ease-out; max-height: 90vh; overflow-y: auto; }
@keyframes vrSlideIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.vr-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.vr-modal-title { font-size: 16px; font-weight: 700; color: var(--gp-text); }
.vr-modal-close { width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--gp-border); background: transparent; color: var(--gp-text-muted); font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; font-family: 'Inter', sans-serif; }
.vr-modal-close:hover { background: var(--gp-primary); border-color: var(--gp-primary); color: #fff; }
.vr-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.vr-form-group { margin-bottom: 18px; }
.vr-form-group label { display: block; font-size: 11px; font-weight: 600; color: var(--gp-text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .5px; }
.vr-req { color: #EF4444; }
.vr-input { background: var(--gp-bg); border: 1px solid var(--gp-border); border-radius: 8px; color: var(--gp-text); font-size: 13px; padding: 9px 14px; font-family: 'Inter', sans-serif; height: 40px; box-sizing: border-box; width: 100%; }
.vr-input:focus { border-color: var(--gp-primary-light); outline: none; }
.vr-textarea { height: auto; min-height: 100px; resize: vertical; }
.vr-form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid var(--gp-border); margin-top: 24px; }

@media (max-width: 768px) {
    .vr-tabs-bar { flex-direction: column; align-items: stretch; }
    .vr-form-row { grid-template-columns: 1fr; }
    .vr-plan-header { flex-direction: column; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('vrPlanModal');
    document.getElementById('vrCreatePlan').addEventListener('click', function() { modal.classList.add('vr-modal-open'); });
    document.getElementById('vrClosePlan').addEventListener('click', function() { modal.classList.remove('vr-modal-open'); });
    modal.addEventListener('click', function(e) { if (e.target === modal) modal.classList.remove('vr-modal-open'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') modal.classList.remove('vr-modal-open'); });
});
</script>
