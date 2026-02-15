<?php
/**
 * Game Plan - Game Lines Builder (Coach Only)
 * Drag-and-drop line builder for forward lines, defense pairs, special teams, goalies.
 * Uses vr_game_plan_lines table for storage.
 * Includes view-only printable mode.
 */

if (!$isAnyCoach) {
    echo '<div class="empty-state" style="text-align:center;padding:40px"><i class="fas fa-lock" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px"></i><h3>Coach Access Required</h3><p style="color:var(--text-muted)">You need coach access to manage lines.</p></div>';
    return;
}

// ── Parameters ────────────────────────────────────────────────
$lines_team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$lines_tab     = isset($_GET['tab']) ? preg_replace('/[^a-z_]/', '', $_GET['tab']) : 'forwards';
if (!in_array($lines_tab, ['forwards', 'defense', 'special', 'goalies'])) $lines_tab = 'forwards';
$lines_view_mode = isset($_GET['mode']) && $_GET['mode'] === 'view' ? 'view' : 'edit';

// ── Load teams ────────────────────────────────────────────────
$lines_teams = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, division FROM teams WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $lines_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('Lines teams: ' . $e->getMessage()); }

if ($lines_team_id === 0 && !empty($lines_teams)) {
    $lines_team_id = (int)$lines_teams[0]['id'];
}

// ── Load roster for selected team ─────────────────────────────
$lines_roster = [];
if ($lines_team_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.role
            FROM users u
            WHERE u.is_active = 1 AND u.id IN (
                SELECT athlete_id FROM athlete_teams WHERE team_id = ? AND athlete_id IS NOT NULL
                UNION
                SELECT user_id FROM athlete_teams WHERE team_id = ? AND user_id IS NOT NULL
            )
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$lines_team_id, $lines_team_id]);
        $lines_roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('decryptUserRows')) {
            $lines_roster = decryptUserRows($lines_roster);
        }
    } catch (PDOException $e) { error_log('Lines roster: ' . $e->getMessage()); }

    // Also load non-user roster players from roster_players table
    try {
        $stmt = $pdo->prepare("
            SELECT rp.id, rp.first_name, rp.last_name, 'roster_player' as role
            FROM roster_players rp
            WHERE rp.team_id = ? AND rp.status = 'active' AND rp.user_id IS NULL
            ORDER BY rp.last_name, rp.first_name
        ");
        $stmt->execute([$lines_team_id]);
        $roster_only_players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $lines_roster = array_merge($lines_roster, $roster_only_players);
    } catch (PDOException $e) {
        // roster_players table may not exist yet
        error_log('Lines roster_players: ' . $e->getMessage());
    }
}

// ── Load existing lines ───────────────────────────────────────
$saved_lines = [];
if ($lines_team_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT gpl.id, gpl.line_name, gpl.position, gpl.athlete_id,
                   u.first_name, u.last_name
            FROM vr_game_plan_lines gpl
            LEFT JOIN users u ON gpl.athlete_id = u.id
            WHERE gpl.team_id = ?
            ORDER BY gpl.line_name, gpl.position
        ");
        $stmt->execute([$lines_team_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('decryptUserRows')) {
            $rows = decryptUserRows($rows);
        }
        foreach ($rows as $r) {
            $saved_lines[$r['line_name']][$r['position']] = $r;
        }
    } catch (PDOException $e) { error_log('Lines load: ' . $e->getMessage()); }
}

// ── Line structure definitions ────────────────────────────────
$forward_lines = [
    'Line 1' => ['LW', 'C', 'RW'],
    'Line 2' => ['LW', 'C', 'RW'],
    'Line 3' => ['LW', 'C', 'RW'],
    'Line 4' => ['LW', 'C', 'RW'],
];
$defense_pairs = [
    'Pair 1' => ['LD', 'RD'],
    'Pair 2' => ['LD', 'RD'],
    'Pair 3' => ['LD', 'RD'],
];
$special_teams = [
    'PP1' => ['LW', 'C', 'RW', 'LD', 'RD'],
    'PP2' => ['LW', 'C', 'RW', 'LD', 'RD'],
    'PK1 (4-man)' => ['F1', 'F2', 'LD', 'RD'],
    'PK2 (4-man)' => ['F1', 'F2', 'LD', 'RD'],
    'PK1 (3-man)' => ['F1', 'LD', 'RD'],
    'PK2 (3-man)' => ['F1', 'LD', 'RD'],
];
$goalie_lines = [
    'Goalies' => ['Starter', 'Backup'],
];

$current_team_name = '';
foreach ($lines_teams as $t) {
    if ((int)$t['id'] === $lines_team_id) { $current_team_name = $t['name']; break; }
}
?>

<!-- Page header -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
    <div>
        <h1><i class="fas fa-users-line"></i> Game Lines</h1>
        <p>Build forward lines, defense pairs, and special teams</p>
    </div>
    <div style="display:flex;gap:8px;" class="gp-lines-actions">
        <?php if ($lines_view_mode === 'view'): ?>
        <a href="/gameplan.php?page=lines&tab=<?= $lines_tab ?>&team_id=<?= $lines_team_id ?>&mode=edit" class="btn btn-secondary"><i class="fas fa-pen"></i> Edit</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <?php else: ?>
        <a href="/gameplan.php?page=lines&tab=<?= $lines_tab ?>&team_id=<?= $lines_team_id ?>&mode=view" class="btn btn-secondary"><i class="fas fa-eye"></i> View / Print</a>
        <?php endif; ?>
    </div>
</div>

<!-- Team Selector -->
<div class="filter-box" style="margin-bottom: 20px;">
    <div class="filter-box-header"><i class="fas fa-users"></i> Select Team</div>
    <div class="filter-box-content">
        <div class="filter-row">
            <div class="filter-field">
                <label>Team</label>
                <select class="form-select" onchange="location.href='/gameplan.php?page=lines&tab=<?= $lines_tab ?>&mode=<?= $lines_view_mode ?>&team_id='+this.value">
                    <?php foreach ($lines_teams as $tm): ?>
                    <option value="<?= (int)$tm['id'] ?>" <?= $lines_team_id === (int)$tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['name']) ?><?= !empty($tm['division']) ? ' (' . htmlspecialchars($tm['division']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field" style="display:flex;align-items:flex-end;">
                <span style="font-size:12px;color:var(--text-muted);background:rgba(107,70,193,.08);padding:8px 14px;border-radius:8px;"><?= count($lines_roster) ?> player<?= count($lines_roster) !== 1 ? 's' : '' ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Sub-tabs -->
<div class="page-tabs page-tabs-secondary" style="margin-bottom: 20px;">
    <a class="page-tab <?= $lines_tab === 'forwards' ? 'active' : '' ?>" href="/gameplan.php?page=lines&team_id=<?= $lines_team_id ?>&tab=forwards&mode=<?= $lines_view_mode ?>">
        <i class="fas fa-hockey-puck"></i> Forward Lines
    </a>
    <a class="page-tab <?= $lines_tab === 'defense' ? 'active' : '' ?>" href="/gameplan.php?page=lines&team_id=<?= $lines_team_id ?>&tab=defense&mode=<?= $lines_view_mode ?>">
        <i class="fas fa-shield-halved"></i> Defense Pairs
    </a>
    <a class="page-tab <?= $lines_tab === 'special' ? 'active' : '' ?>" href="/gameplan.php?page=lines&team_id=<?= $lines_team_id ?>&tab=special&mode=<?= $lines_view_mode ?>">
        <i class="fas fa-bolt"></i> Special Teams
    </a>
    <a class="page-tab <?= $lines_tab === 'goalies' ? 'active' : '' ?>" href="/gameplan.php?page=lines&team_id=<?= $lines_team_id ?>&tab=goalies&mode=<?= $lines_view_mode ?>">
        <i class="fas fa-hand"></i> Goalies
    </a>
</div>

<?php if (empty($lines_roster)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state" style="text-align:center;padding:40px;">
            <i class="fas fa-users-slash" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px;"></i>
            <h3 style="color:var(--text-secondary);">No Players Found</h3>
            <p style="color:var(--text-muted);">Assign athletes to this team first.</p>
        </div>
    </div>
</div>
<?php else: ?>

<?php
// Determine which line groups to show
if ($lines_tab === 'forwards') $line_groups = $forward_lines;
elseif ($lines_tab === 'defense') $line_groups = $defense_pairs;
elseif ($lines_tab === 'special') $line_groups = $special_teams;
else $line_groups = $goalie_lines;
?>

<?php if ($lines_view_mode === 'view'): ?>
<!-- ── View-Only / Printable Mode ── -->
<div id="gpLinesPrintArea">
    <div class="gp-print-header" style="display:none;text-align:center;margin-bottom:20px;">
        <h2 style="margin:0;"><?= htmlspecialchars($current_team_name) ?> – Game Lines</h2>
        <p style="margin:4px 0 0;color:#666;font-size:13px;"><?= ucfirst($lines_tab) ?> | <?= date('F j, Y') ?></p>
    </div>

    <?php foreach ($line_groups as $line_name => $positions): ?>
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <h3><i class="fas fa-grip-lines"></i> <?= htmlspecialchars($line_name) ?></h3>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(<?= count($positions) ?>,1fr);gap:12px;">
                <?php foreach ($positions as $pos):
                    $saved = $saved_lines[$line_name][$pos] ?? null;
                    $saved_name = $saved ? htmlspecialchars(trim(($saved['first_name'] ?? '') . ' ' . ($saved['last_name'] ?? ''))) : '';
                ?>
                <div style="text-align:center;">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?= htmlspecialchars($pos) ?></label>
                    <div style="min-height:60px;border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;justify-content:center;padding:8px;">
                        <?php if ($saved_name): ?>
                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                                <?= strtoupper(substr($saved['first_name'] ?? '?', 0, 1) . substr($saved['last_name'] ?? '?', 0, 1)) ?>
                            </div>
                            <span style="font-size:11px;font-weight:600;"><?= $saved_name ?></span>
                        </div>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- ── Edit Mode ── -->
<form method="POST" action="/process_video.php" id="gpLinesForm">
    <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
    <input type="hidden" name="action" value="save_hockey_lines">
    <input type="hidden" name="team_id" value="<?= $lines_team_id ?>">
    <input type="hidden" name="tab" value="<?= $lines_tab ?>">

    <div style="display:grid;grid-template-columns:250px 1fr;gap:20px;align-items:start;">
        <!-- Roster Panel (Left) -->
        <div class="card" style="position:sticky;top:20px;">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Roster</h3>
            </div>
            <div class="card-body" style="padding:0;max-height:500px;overflow-y:auto;">
                <?php foreach ($lines_roster as $player): ?>
                <div class="gp-roster-player" draggable="true" data-player-id="<?= (int)$player['id'] ?>" data-player-name="<?= htmlspecialchars(trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''))) ?>"
                    style="padding:10px 16px;border-bottom:1px solid var(--border);cursor:grab;display:flex;align-items:center;gap:10px;transition:background .15s;">
                    <div style="width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <?= strtoupper(substr($player['first_name'] ?? '?', 0, 1) . substr($player['last_name'] ?? '?', 0, 1)) ?>
                    </div>
                    <span style="font-size:13px;font-weight:600;"><?= htmlspecialchars(trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''))) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Lines Panel (Right) -->
        <div>
            <?php foreach ($line_groups as $line_name => $positions): ?>
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header">
                    <h3><i class="fas fa-grip-lines"></i> <?= htmlspecialchars($line_name) ?></h3>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(<?= count($positions) ?>,1fr);gap:12px;">
                        <?php foreach ($positions as $pos):
                            $saved = $saved_lines[$line_name][$pos] ?? null;
                            $saved_id = $saved ? (int)$saved['athlete_id'] : '';
                            $saved_name = $saved ? htmlspecialchars(trim(($saved['first_name'] ?? '') . ' ' . ($saved['last_name'] ?? ''))) : '';
                            $input_name = 'lines[' . htmlspecialchars($line_name) . '][' . htmlspecialchars($pos) . ']';
                        ?>
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;text-align:center;"><?= htmlspecialchars($pos) ?></label>
                            <div class="gp-line-slot" data-line="<?= htmlspecialchars($line_name) ?>" data-pos="<?= htmlspecialchars($pos) ?>"
                                style="min-height:60px;border:2px dashed var(--border);border-radius:10px;display:flex;align-items:center;justify-content:center;padding:8px;transition:border-color .2s,background .2s;text-align:center;">
                                <?php if ($saved_id): ?>
                                <div class="gp-slot-filled" style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                    <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                                        <?= strtoupper(substr($saved['first_name'] ?? '?', 0, 1) . substr($saved['last_name'] ?? '?', 0, 1)) ?>
                                    </div>
                                    <span style="font-size:11px;font-weight:600;"><?= $saved_name ?></span>
                                    <button type="button" class="gp-slot-clear" style="background:none;border:none;color:var(--text-muted);font-size:10px;cursor:pointer;padding:2px;" title="Remove">&times; remove</button>
                                </div>
                                <?php else: ?>
                                <span style="font-size:11px;color:var(--text-muted);"><i class="fas fa-plus" style="margin-right:4px;"></i>Drop here</span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="<?= $input_name ?>" value="<?= $saved_id ?>" class="gp-slot-input">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Lines</button>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>
<?php endif; ?>

<style>
.gp-roster-player:hover { background: rgba(107,70,193,.08); }
.gp-roster-player:active { cursor: grabbing; }
.gp-line-slot.drag-over { border-color: var(--primary-light) !important; background: rgba(107,70,193,.06); }

@media print {
    .gp-sidebar, .gp-topbar, .gp-overlay, .page-tabs, .filter-box, .gp-lines-actions { display: none !important; }
    .gp-main { display: block !important; }
    .gp-content { padding: 0 !important; overflow: visible !important; }
    .gp-print-header { display: block !important; }
    body { background: #fff !important; color: #000 !important; display: block !important; height: auto !important; overflow: visible !important; }
    .card { border: 1px solid #ccc !important; box-shadow: none !important; background: #fff !important; break-inside: avoid; }
    .card-header h3 { color: #000 !important; }
    .page-header h1, .page-header p { color: #000 !important; }
    .page-header h1 i { color: #333 !important; }
}
</style>

<?php if ($lines_view_mode === 'edit'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var players = document.querySelectorAll('.gp-roster-player');
    var slots = document.querySelectorAll('.gp-line-slot');

    players.forEach(function(player) {
        player.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', JSON.stringify({
                id: player.dataset.playerId,
                name: player.dataset.playerName
            }));
            e.dataTransfer.effectAllowed = 'copy';
            player.style.opacity = '0.5';
        });
        player.addEventListener('dragend', function() {
            player.style.opacity = '1';
        });
    });

    slots.forEach(function(slot) {
        slot.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            slot.classList.add('drag-over');
        });
        slot.addEventListener('dragleave', function() {
            slot.classList.remove('drag-over');
        });
        slot.addEventListener('drop', function(e) {
            e.preventDefault();
            slot.classList.remove('drag-over');
            try {
                var data = JSON.parse(e.dataTransfer.getData('text/plain'));
                var initials = data.name.split(' ').map(function(n){ return n.charAt(0).toUpperCase(); }).join('');
                slot.innerHTML =
                    '<div class="gp-slot-filled" style="display:flex;flex-direction:column;align-items:center;gap:4px;">' +
                        '<div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;">' + initials + '</div>' +
                        '<span style="font-size:11px;font-weight:600;">' + data.name + '</span>' +
                        '<button type="button" class="gp-slot-clear" style="background:none;border:none;color:var(--text-muted);font-size:10px;cursor:pointer;padding:2px;" title="Remove">&times; remove</button>' +
                    '</div>';
                var input = slot.parentElement.querySelector('.gp-slot-input');
                if (input) input.value = data.id;
                bindClearButtons();
            } catch(err) {}
        });
    });

    function bindClearButtons() {
        document.querySelectorAll('.gp-slot-clear').forEach(function(btn) {
            btn.onclick = function(e) {
                e.preventDefault();
                var slot = btn.closest('.gp-line-slot');
                slot.innerHTML = '<span style="font-size:11px;color:var(--text-muted);"><i class="fas fa-plus" style="margin-right:4px;"></i>Drop here</span>';
                var input = slot.parentElement.querySelector('.gp-slot-input');
                if (input) input.value = '';
            };
        });
    }
    bindClearButtons();
});
</script>
<?php endif; ?>
