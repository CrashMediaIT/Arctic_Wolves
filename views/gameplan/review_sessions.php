<?php
/**
 * Game Plan - Review Sessions View (Coach Only)
 * Status filter tabs, session list, and create session modal.
 */

if (!$isAnyCoach) {
    echo '<div class="gp-empty"><i class="fas fa-lock"></i><p>Coach access required to manage review sessions.</p></div>';
    return;
}

// ── Filters ───────────────────────────────────────────────────
$rs_status = isset($_GET['status']) ? preg_replace('/[^a-z_]/', '', $_GET['status']) : 'all';
if (!in_array($rs_status, ['all', 'upcoming', 'completed', 'past_due'])) $rs_status = 'all';

// ── Load sessions ─────────────────────────────────────────────
$rs_sessions = [];
try {
    $q = "
        SELECT rs.id, rs.title, rs.description, rs.session_type, rs.status,
               rs.scheduled_date, rs.completed_date, rs.created_at,
               gs.opponent_team, gs.game_date, t.name AS team_name,
               (SELECT COUNT(*) FROM vr_review_session_clips rsc WHERE rsc.session_id = rs.id) AS clip_count
        FROM vr_review_sessions rs
        LEFT JOIN game_schedules gs ON rs.game_id = gs.id
        LEFT JOIN teams t ON rs.team_id = t.id
        WHERE rs.coach_id = ?
    ";
    $params = [$user_id];

    if ($rs_status === 'upcoming') {
        $q .= " AND rs.status = 'scheduled' AND rs.scheduled_date >= NOW()";
    } elseif ($rs_status === 'completed') {
        $q .= " AND rs.status = 'completed'";
    } elseif ($rs_status === 'past_due') {
        $q .= " AND rs.status = 'scheduled' AND rs.scheduled_date < NOW()";
    }

    $q .= " ORDER BY rs.scheduled_date DESC LIMIT 50";
    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    $rs_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('RS sessions: ' . $e->getMessage()); }

// ── Count by status ───────────────────────────────────────────
$rs_counts = ['all' => 0, 'upcoming' => 0, 'completed' => 0, 'past_due' => 0];
try {
    $stmt = $pdo->prepare("SELECT status, scheduled_date FROM vr_review_sessions WHERE coach_id = ?");
    $stmt->execute([$user_id]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rs_counts['all'] = count($all);
    foreach ($all as $s) {
        if ($s['status'] === 'completed') $rs_counts['completed']++;
        elseif ($s['status'] === 'scheduled' && strtotime($s['scheduled_date']) >= time()) $rs_counts['upcoming']++;
        elseif ($s['status'] === 'scheduled' && strtotime($s['scheduled_date']) < time()) $rs_counts['past_due']++;
    }
} catch (PDOException $e) { error_log('RS counts: ' . $e->getMessage()); }

// ── Games & clips for create modal ────────────────────────────
$rs_games = [];
try {
    $stmt = $pdo->prepare("
        SELECT gs.id, gs.opponent_team, gs.game_date, t.name AS team_name
        FROM game_schedules gs LEFT JOIN teams t ON gs.team_id = t.id
        ORDER BY gs.game_date DESC LIMIT 30
    ");
    $stmt->execute();
    $rs_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('RS games: ' . $e->getMessage()); }

$rs_clips = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, created_at FROM vr_video_clips ORDER BY created_at DESC LIMIT 50");
    $stmt->execute();
    $rs_clips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('RS clips: ' . $e->getMessage()); }
?>

<!-- Page header -->
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-chalkboard-user"></i> Review Sessions</h1>
    <p class="gp-page-desc">Plan and manage team video review presentations</p>
</div>

<!-- Status Tabs & Create Button -->
<div class="vr-tabs-bar">
    <div class="vr-tabs">
        <a class="vr-tab <?= $rs_status === 'all' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=review_sessions&status=all">
            All (<?= $rs_counts['all'] ?>)
        </a>
        <a class="vr-tab <?= $rs_status === 'upcoming' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=review_sessions&status=upcoming">
            <i class="fas fa-clock"></i> Upcoming (<?= $rs_counts['upcoming'] ?>)
        </a>
        <a class="vr-tab <?= $rs_status === 'completed' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=review_sessions&status=completed">
            <i class="fas fa-check-circle"></i> Completed (<?= $rs_counts['completed'] ?>)
        </a>
        <a class="vr-tab <?= $rs_status === 'past_due' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=review_sessions&status=past_due">
            <i class="fas fa-exclamation-triangle"></i> Past Due (<?= $rs_counts['past_due'] ?>)
        </a>
    </div>
    <button type="button" class="vr-btn-primary" id="vrCreateSession"><i class="fas fa-plus"></i> New Session</button>
</div>

<!-- Session List -->
<?php if (empty($rs_sessions)): ?>
<div class="gp-empty">
    <i class="fas fa-chalkboard-user"></i>
    <p>No review sessions found. Create one to plan a video review for your team.</p>
</div>
<?php else: ?>
<?php foreach ($rs_sessions as $session): ?>
<?php
    $is_past_due = ($session['status'] === 'scheduled' && strtotime($session['scheduled_date']) < time());
    $badge_class = $session['status'] === 'completed' ? 'vr-badge-completed' : ($is_past_due ? 'vr-badge-past-due' : 'vr-badge-upcoming');
    $badge_label = $session['status'] === 'completed' ? 'Completed' : ($is_past_due ? 'Past Due' : 'Upcoming');
?>
<div class="vr-session-card">
    <div class="vr-session-header">
        <div class="vr-session-info">
            <h4><?= htmlspecialchars($session['title'] ?? 'Untitled Session') ?></h4>
            <div class="vr-meta">
                <span><i class="fas fa-calendar"></i> <?= date('M j, Y – g:ia', strtotime($session['scheduled_date'])) ?></span>
                <?php if (!empty($session['session_type'])): ?>
                <span><i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($session['session_type'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($session['opponent_team'])): ?>
                <span><i class="fas fa-hockey-puck"></i> vs <?= htmlspecialchars($session['opponent_team']) ?></span>
                <?php endif; ?>
                <?php if (!empty($session['team_name'])): ?>
                <span><i class="fas fa-users"></i> <?= htmlspecialchars($session['team_name']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-scissors"></i> <?= (int)$session['clip_count'] ?> clips</span>
            </div>
        </div>
        <span class="vr-status-badge <?= $badge_class ?>"><?= $badge_label ?></span>
    </div>
    <?php if (!empty($session['description'])): ?>
    <div class="vr-session-desc"><?= htmlspecialchars(substr($session['description'], 0, 200)) ?><?= strlen($session['description'] ?? '') > 200 ? '…' : '' ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Create Session Modal -->
<div class="vr-modal-overlay" id="vrSessionModal">
    <div class="vr-modal-sheet">
        <div class="vr-modal-header">
            <span class="vr-modal-title">Create Review Session</span>
            <button type="button" class="vr-modal-close" id="vrCloseSession">&times;</button>
        </div>
        <form method="POST" action="/process_video.php" id="vrSessionForm">
            <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_review_session">
            <input type="hidden" name="coach_id" value="<?= (int)$user_id ?>">

            <div class="vr-form-group">
                <label>Session Title <span class="vr-req">*</span></label>
                <input type="text" name="title" class="vr-input" placeholder="e.g., Pre-Game Film Review" required>
            </div>
            <div class="vr-form-group">
                <label>Description</label>
                <textarea name="description" class="vr-input vr-textarea" rows="3" placeholder="Session focus and objectives…"></textarea>
            </div>
            <div class="vr-form-row">
                <div class="vr-form-group">
                    <label>Session Type</label>
                    <select name="session_type" class="vr-input">
                        <option value="pre_game">Pre-Game</option>
                        <option value="post_game">Post-Game</option>
                        <option value="practice">Practice</option>
                        <option value="scouting">Scouting</option>
                        <option value="individual">Individual</option>
                    </select>
                </div>
                <div class="vr-form-group">
                    <label>Scheduled Date <span class="vr-req">*</span></label>
                    <input type="datetime-local" name="scheduled_date" class="vr-input" required>
                </div>
            </div>
            <div class="vr-form-group">
                <label>Link to Game</label>
                <select name="game_id" class="vr-input">
                    <option value="">— No Game —</option>
                    <?php foreach ($rs_games as $g): ?>
                    <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars(($g['team_name'] ?? '') . ' vs ' . $g['opponent_team'] . ' – ' . date('M j', strtotime($g['game_date']))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vr-form-group">
                <label>Include Clips</label>
                <select name="clip_ids[]" class="vr-input" multiple size="5">
                    <?php foreach ($rs_clips as $cl): ?>
                    <option value="<?= (int)$cl['id'] ?>"><?= htmlspecialchars($cl['title'] ?? 'Clip #' . $cl['id']) ?> (<?= date('M j', strtotime($cl['created_at'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <span class="vr-file-hint">Hold Ctrl/Cmd to select multiple clips</span>
            </div>
            <div class="vr-form-actions">
                <button type="submit" class="vr-btn-primary"><i class="fas fa-plus"></i> Create Session</button>
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

.vr-session-card { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; padding: 18px 22px; margin-bottom: 12px; transition: border-color .2s; }
.vr-session-card:hover { border-color: rgba(107,70,193,.4); }
.vr-session-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.vr-session-info h4 { font-size: 15px; font-weight: 700; color: var(--gp-text); margin: 0 0 8px; }
.vr-meta { display: flex; flex-wrap: wrap; gap: 14px; }
.vr-meta span { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--gp-text-muted); }
.vr-meta i { color: var(--gp-primary-light); font-size: 11px; }
.vr-session-desc { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--gp-border); font-size: 13px; color: var(--gp-text-dim); line-height: 1.5; }

.vr-status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 16px; font-size: 10px; font-weight: 700; text-transform: uppercase; white-space: nowrap; flex-shrink: 0; }
.vr-badge-upcoming { background: rgba(59,130,246,.1); color: #3B82F6; border: 1px solid rgba(59,130,246,.2); }
.vr-badge-completed { background: rgba(16,185,129,.1); color: #10B981; border: 1px solid rgba(16,185,129,.2); }
.vr-badge-past-due { background: rgba(239,68,68,.1); color: #EF4444; border: 1px solid rgba(239,68,68,.2); }

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
.vr-textarea { height: auto; min-height: 80px; resize: vertical; }
.vr-file-hint { font-size: 11px; color: var(--gp-text-dim); display: block; margin-top: 4px; }
.vr-form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid var(--gp-border); margin-top: 24px; }

@media (max-width: 768px) {
    .vr-tabs-bar { flex-direction: column; align-items: stretch; }
    .vr-session-header { flex-direction: column; }
    .vr-form-row { grid-template-columns: 1fr; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('vrSessionModal');
    document.getElementById('vrCreateSession').addEventListener('click', function() {
        modal.classList.add('vr-modal-open');
    });
    document.getElementById('vrCloseSession').addEventListener('click', function() {
        modal.classList.remove('vr-modal-open');
    });
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.classList.remove('vr-modal-open');
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') modal.classList.remove('vr-modal-open');
    });
});
</script>
