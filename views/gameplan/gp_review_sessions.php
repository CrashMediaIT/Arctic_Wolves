<?php
/**
 * Game Plan - Review Sessions View (Coach Only)
 * Restyled with site-standard classes: card, btn, form-input, form-select, page-tabs.
 */

if (!$isAnyCoach) {
    echo '<div class="empty-state" style="text-align:center;padding:40px"><i class="fas fa-lock" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px"></i><h3>Coach Access Required</h3><p style="color:var(--text-muted)">You need coach access to manage review sessions.</p></div>';
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
<div class="page-header">
    <h1><i class="fas fa-chalkboard-user"></i> Review Sessions</h1>
    <p>Plan and manage team video review presentations</p>
</div>

<!-- Status Tabs -->
<div class="page-tabs page-tabs-secondary" style="margin-bottom: 20px;">
    <a class="page-tab <?= $rs_status === 'all' ? 'active' : '' ?>" href="?page=gameplan_review_sessions&status=all">
        All (<?= $rs_counts['all'] ?>)
    </a>
    <a class="page-tab <?= $rs_status === 'upcoming' ? 'active' : '' ?>" href="?page=gameplan_review_sessions&status=upcoming">
        <i class="fas fa-clock"></i> Upcoming (<?= $rs_counts['upcoming'] ?>)
    </a>
    <a class="page-tab <?= $rs_status === 'completed' ? 'active' : '' ?>" href="?page=gameplan_review_sessions&status=completed">
        <i class="fas fa-check-circle"></i> Completed (<?= $rs_counts['completed'] ?>)
    </a>
    <a class="page-tab <?= $rs_status === 'past_due' ? 'active' : '' ?>" href="?page=gameplan_review_sessions&status=past_due">
        <i class="fas fa-exclamation-triangle"></i> Past Due (<?= $rs_counts['past_due'] ?>)
    </a>
</div>

<div style="margin-bottom: 20px; text-align: right;">
    <button type="button" class="btn btn-primary" id="gpCreateSession"><i class="fas fa-plus"></i> New Session</button>
</div>

<!-- Session List -->
<?php if (empty($rs_sessions)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state" style="text-align:center;padding:40px;">
            <i class="fas fa-chalkboard-user" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px;"></i>
            <h3 style="color:var(--text-secondary);">No Review Sessions</h3>
            <p style="color:var(--text-muted);">Create one to plan a video review for your team.</p>
        </div>
    </div>
</div>
<?php else: ?>
<?php foreach ($rs_sessions as $session): ?>
<?php
    $is_past_due = ($session['status'] === 'scheduled' && strtotime($session['scheduled_date']) < time());
    if ($session['status'] === 'completed') {
        $badge_style = 'background:rgba(16,185,129,.1);color:#10B981;border:1px solid rgba(16,185,129,.2);';
        $badge_label = 'Completed';
    } elseif ($is_past_due) {
        $badge_style = 'background:rgba(239,68,68,.1);color:#EF4444;border:1px solid rgba(239,68,68,.2);';
        $badge_label = 'Past Due';
    } else {
        $badge_style = 'background:rgba(59,130,246,.1);color:#3B82F6;border:1px solid rgba(59,130,246,.2);';
        $badge_label = 'Upcoming';
    }
?>
<div class="card" style="margin-bottom:12px;">
    <div class="card-body" style="padding:18px 22px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <div>
                <h4 style="margin:0 0 8px;font-size:15px;font-weight:700;"><?= htmlspecialchars($session['title'] ?? 'Untitled Session') ?></h4>
                <div style="display:flex;flex-wrap:wrap;gap:14px;">
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);"><i class="fas fa-calendar" style="color:var(--primary-light);font-size:11px;"></i> <?= date('M j, Y – g:ia', strtotime($session['scheduled_date'])) ?></span>
                    <?php if (!empty($session['session_type'])): ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);"><i class="fas fa-tag" style="color:var(--primary-light);font-size:11px;"></i> <?= htmlspecialchars(ucfirst($session['session_type'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($session['opponent_team'])): ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);"><i class="fas fa-hockey-puck" style="color:var(--primary-light);font-size:11px;"></i> vs <?= htmlspecialchars($session['opponent_team']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($session['team_name'])): ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);"><i class="fas fa-users" style="color:var(--primary-light);font-size:11px;"></i> <?= htmlspecialchars($session['team_name']) ?></span>
                    <?php endif; ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);"><i class="fas fa-scissors" style="color:var(--primary-light);font-size:11px;"></i> <?= (int)$session['clip_count'] ?> clips</span>
                </div>
            </div>
            <span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:16px;font-size:10px;font-weight:700;text-transform:uppercase;white-space:nowrap;<?= $badge_style ?>"><?= $badge_label ?></span>
        </div>
        <?php if (!empty($session['description'])): ?>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted);line-height:1.5;">
            <?= htmlspecialchars(substr($session['description'], 0, 200)) ?><?= strlen($session['description'] ?? '') > 200 ? '…' : '' ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Create Session Modal -->
<div class="modal-overlay" id="gpSessionModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
    <div class="modal" style="width:90%;max-width:580px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Create Review Session</h3>
            <button type="button" class="modal-close" id="gpCloseSession">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="/process_video.php">
                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="create_review_session">
                <input type="hidden" name="coach_id" value="<?= (int)$user_id ?>">

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Session Title <span style="color:#EF4444;">*</span></label>
                    <input type="text" name="title" class="form-input" placeholder="e.g., Pre-Game Film Review" required>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Description</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Session focus and objectives…" style="height:auto;min-height:80px;resize:vertical;"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Session Type</label>
                        <select name="session_type" class="form-select">
                            <option value="pre_game">Pre-Game</option>
                            <option value="post_game">Post-Game</option>
                            <option value="practice">Practice</option>
                            <option value="scouting">Scouting</option>
                            <option value="individual">Individual</option>
                        </select>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Scheduled Date <span style="color:#EF4444;">*</span></label>
                        <input type="datetime-local" name="scheduled_date" class="form-input" required>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Link to Game</label>
                    <select name="game_id" class="form-select">
                        <option value="">— No Game —</option>
                        <?php foreach ($rs_games as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars(($g['team_name'] ?? '') . ' vs ' . $g['opponent_team'] . ' – ' . date('M j', strtotime($g['game_date']))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Include Clips</label>
                    <select name="clip_ids[]" class="form-select" multiple size="5" style="height:auto;">
                        <?php foreach ($rs_clips as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>"><?= htmlspecialchars($cl['title'] ?? 'Clip #' . $cl['id']) ?> (<?= date('M j', strtotime($cl['created_at'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:var(--text-muted);display:block;margin-top:4px;">Hold Ctrl/Cmd to select multiple clips</small>
                </div>
                <div style="display:flex;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border);margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('gpSessionModal');
    document.getElementById('gpCreateSession').addEventListener('click', function() { modal.style.display = 'flex'; });
    document.getElementById('gpCloseSession').addEventListener('click', function() { modal.style.display = 'none'; });
    modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') modal.style.display = 'none'; });
});
</script>
