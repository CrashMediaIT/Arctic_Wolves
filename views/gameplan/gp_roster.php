<?php
/**
 * Game Plan - Roster Management (Coach Only)
 * Manage team rosters including non-user players.
 * Players can be Arctic Wolves users or external (non-user) players.
 * External players can be linked to existing accounts when they join.
 */

if (!$isAnyCoach) {
    echo '<div class="empty-state" style="text-align:center;padding:40px"><i class="fas fa-lock" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px"></i><h3>Coach Access Required</h3><p style="color:var(--text-muted)">You need coach access to manage rosters.</p></div>';
    return;
}

// ── Parameters ────────────────────────────────────────────────
$roster_team_id  = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$roster_action   = isset($_GET['action']) ? preg_replace('/[^a-z_]/', '', $_GET['action']) : 'list';
$roster_season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;

// ── Load teams ────────────────────────────────────────────────
$roster_teams = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, division, season FROM teams WHERE is_active = 1 AND is_managed = 1 ORDER BY name");
    $stmt->execute();
    $roster_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('Roster teams: ' . $e->getMessage()); }

if ($roster_team_id === 0 && !empty($roster_teams)) {
    $roster_team_id = (int)$roster_teams[0]['id'];
}

// ── Load seasons ──────────────────────────────────────────────
$seasons = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, is_active FROM seasons ORDER BY start_date DESC");
    $stmt->execute();
    $seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('Roster seasons: ' . $e->getMessage()); }

// Default to active season if no season selected
if ($roster_season_id === 0 && !empty($seasons)) {
    foreach ($seasons as $s) {
        if (!empty($s['is_active'])) { $roster_season_id = (int)$s['id']; break; }
    }
    if ($roster_season_id === 0) $roster_season_id = (int)$seasons[0]['id'];
}

// ── Load roster players for selected team ─────────────────────
$roster_players = [];
if ($roster_team_id > 0) {
    try {
        $roster_sql = "
            SELECT rp.id, rp.team_id, rp.user_id, rp.first_name, rp.last_name,
                   rp.email, rp.phone, rp.jersey_number, rp.position,
                   rp.date_of_birth, rp.parent_name, rp.parent_email, rp.parent_phone,
                   rp.notes, rp.status, rp.season_id,
                   s.name AS season_name,
                   u.first_name AS linked_first_name, u.last_name AS linked_last_name
            FROM roster_players rp
            LEFT JOIN seasons s ON rp.season_id = s.id
            LEFT JOIN users u ON rp.user_id = u.id
            WHERE rp.team_id = ? AND rp.status = 'active'
        ";
        $roster_params = [$roster_team_id];
        if ($roster_season_id > 0) {
            $roster_sql .= " AND rp.season_id = ?";
            $roster_params[] = $roster_season_id;
        }
        $roster_sql .= " ORDER BY rp.jersey_number ASC, rp.last_name ASC";
        $stmt = $pdo->prepare($roster_sql);
        $stmt->execute($roster_params);
        $roster_players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('Roster players: ' . $e->getMessage()); }
}

// ── Load existing Arctic Wolves users for linking ─────────────
$aw_users = [];
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE is_active = 1 ORDER BY last_name, first_name");
    $stmt->execute();
    $aw_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $aw_users = decryptUserRows($aw_users);
    }
} catch (PDOException $e) { error_log('Roster AW users: ' . $e->getMessage()); }

$current_team_name = '';
foreach ($roster_teams as $t) {
    if ((int)$t['id'] === $roster_team_id) { $current_team_name = $t['name']; break; }
}

// Success/error messages
$roster_msg = '';
$roster_msg_type = '';
if (isset($_GET['success'])) {
    $roster_msg_type = 'success';
    $roster_msg = match($_GET['success']) {
        'player_added' => 'Player added to roster successfully.',
        'player_updated' => 'Player updated successfully.',
        'player_removed' => 'Player removed from roster.',
        'player_linked' => 'Player linked to Arctic Wolves account.',
        default => 'Action completed successfully.'
    };
} elseif (isset($_GET['error'])) {
    $roster_msg_type = 'error';
    $roster_msg = match($_GET['error']) {
        'missing_fields' => 'Please fill in the required fields.',
        'invalid_team' => 'Invalid team selected.',
        'player_not_found' => 'Player not found.',
        default => 'An error occurred.'
    };
}
?>

<!-- Page header -->
<div class="page-header">
    <h1><i class="fas fa-clipboard-list"></i> Roster Management</h1>
    <p>Manage team rosters – add players, link accounts, and track roster data</p>
</div>

<?php if ($roster_msg): ?>
<div style="padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:600;
    background:<?= $roster_msg_type === 'success' ? 'rgba(16,185,129,.1)' : 'rgba(239,68,68,.1)' ?>;
    color:<?= $roster_msg_type === 'success' ? '#10B981' : '#EF4444' ?>;
    border:1px solid <?= $roster_msg_type === 'success' ? 'rgba(16,185,129,.2)' : 'rgba(239,68,68,.2)' ?>;">
    <i class="fas <?= $roster_msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($roster_msg) ?>
</div>
<?php endif; ?>

<!-- Team Selector & Actions -->
<div class="filter-box" style="margin-bottom: 20px;">
    <div class="filter-box-header"><i class="fas fa-users"></i> Select Team</div>
    <div class="filter-box-content">
        <div class="filter-row">
            <div class="filter-field">
                <label>Team</label>
                <select class="form-select" id="rosterTeamSelect" onchange="updateRosterFilter()">
                    <?php foreach ($roster_teams as $tm): ?>
                    <option value="<?= (int)$tm['id'] ?>" <?= $roster_team_id === (int)$tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['name']) ?><?= !empty($tm['division']) ? ' (' . htmlspecialchars($tm['division']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Season</label>
                <select class="form-select" id="rosterSeasonSelect" onchange="updateRosterFilter()">
                    <option value="0">All Seasons</option>
                    <?php foreach ($seasons as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $roster_season_id === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?><?= !empty($s['is_active']) ? ' (Current)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field filter-actions">
                <button type="button" class="btn btn-primary" id="gpAddPlayerBtn"><i class="fas fa-user-plus"></i> Add Player</button>
            </div>
        </div>
    </div>
</div>
<script>
function updateRosterFilter() {
    var teamId = document.getElementById('rosterTeamSelect').value;
    var seasonId = document.getElementById('rosterSeasonSelect').value;
    var url = '/gameplan.php?page=roster&team_id=' + teamId;
    if (seasonId !== '0') url += '&season_id=' + seasonId;
    location.href = url;
}
</script>

<!-- Roster Summary -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
    <div class="card" style="margin-bottom:0;">
        <div class="card-body" style="text-align:center;padding:16px;">
            <div style="font-size:24px;font-weight:900;color:var(--text-white);"><?= count($roster_players) ?></div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;">Total Players</div>
        </div>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div class="card-body" style="text-align:center;padding:16px;">
            <div style="font-size:24px;font-weight:900;color:#10B981;"><?= count(array_filter($roster_players, fn($p) => $p['user_id'])) ?></div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;">Linked Users</div>
        </div>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div class="card-body" style="text-align:center;padding:16px;">
            <div style="font-size:24px;font-weight:900;color:#F59E0B;"><?= count(array_filter($roster_players, fn($p) => !$p['user_id'])) ?></div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;">External Players</div>
        </div>
    </div>
</div>

<!-- Roster Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> <?= htmlspecialchars($current_team_name) ?> Roster</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($roster_players)): ?>
        <div class="empty-state" style="text-align:center;padding:40px;">
            <i class="fas fa-users-slash" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px;"></i>
            <h3 style="color:var(--text-secondary);">No Players Yet</h3>
            <p style="color:var(--text-muted);">Add players to this roster using the button above.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);">
                        <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">#</th>
                        <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Player</th>
                        <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Position</th>
                        <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Status</th>
                        <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Account</th>
                        <th style="padding:12px 16px;text-align:right;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roster_players as $player): ?>
                    <tr style="border-bottom:1px solid var(--border);transition:background .15s;" onmouseover="this.style.background='rgba(107,70,193,.04)'" onmouseout="this.style.background='transparent'">
                        <td style="padding:12px 16px;font-weight:700;color:var(--primary-light);"><?= $player['jersey_number'] ? (int)$player['jersey_number'] : '–' ?></td>
                        <td style="padding:12px 16px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,<?= $player['user_id'] ? 'var(--primary),var(--primary-light)' : '#6B6B7B,#8B8B9B' ?>);color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <?= strtoupper(mb_substr($player['first_name'], 0, 1) . mb_substr($player['last_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;color:var(--text-white);"><?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></div>
                                    <?php if ($player['email']): ?>
                                    <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($player['email']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="padding:12px 16px;color:var(--text-secondary);"><?= htmlspecialchars($player['position'] ?: '–') ?></td>
                        <td style="padding:12px 16px;">
                            <span style="display:inline-flex;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase;
                                background:<?= $player['status'] === 'active' ? 'rgba(16,185,129,.1)' : 'rgba(239,68,68,.1)' ?>;
                                color:<?= $player['status'] === 'active' ? '#10B981' : '#EF4444' ?>;">
                                <?= htmlspecialchars(ucfirst($player['status'])) ?>
                            </span>
                        </td>
                        <td style="padding:12px 16px;">
                            <?php if ($player['user_id']): ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase;background:rgba(107,70,193,.1);color:var(--primary-light);">
                                <i class="fas fa-link" style="font-size:8px;"></i> Linked
                            </span>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary gp-link-btn" data-player-id="<?= (int)$player['id'] ?>" data-player-name="<?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>"
                                style="height:28px;padding:0 12px;font-size:11px;">
                                <i class="fas fa-link"></i> Link Account
                            </button>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px 16px;text-align:right;">
                            <button type="button" class="btn btn-secondary gp-edit-player" data-player='<?= htmlspecialchars(json_encode($player), ENT_QUOTES) ?>'
                                style="height:28px;padding:0 10px;font-size:11px;margin-right:4px;">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" action="/process_video.php" style="display:inline;" onsubmit="return confirm('Remove this player from the roster?')">
                                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                                <input type="hidden" name="action" value="remove_roster_player">
                                <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                                <input type="hidden" name="team_id" value="<?= $roster_team_id ?>">
                                <button type="submit" class="btn btn-secondary" style="height:28px;padding:0 10px;font-size:11px;color:var(--error);">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Player Modal -->
<div class="modal-overlay" id="gpAddPlayerModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
    <div class="modal-content" style="width:90%;max-width:560px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add Player to Roster</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('gpAddPlayerModal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="/process_video.php" id="gpAddPlayerForm">
                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="add_roster_player">
                <input type="hidden" name="team_id" value="<?= $roster_team_id ?>">

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Link to Existing Account (Optional)</label>
                    <select name="user_id" class="form-select" id="gpLinkUserSelect">
                        <option value="">– External Player (No Account) –</option>
                        <?php foreach ($aw_users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" data-fname="<?= htmlspecialchars($u['first_name'] ?? '') ?>" data-lname="<?= htmlspecialchars($u['last_name'] ?? '') ?>" data-email="<?= htmlspecialchars($u['email'] ?? '') ?>">
                            <?= htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?> (<?= htmlspecialchars($u['email'] ?? '') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required id="gpPlayerFirstName">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required id="gpPlayerLastName">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Jersey Number</label>
                        <input type="number" name="jersey_number" class="form-input" min="0" max="99">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Position</label>
                        <select name="position" class="form-select">
                            <option value="">Select Position</option>
                            <option value="C">Center (C)</option>
                            <option value="LW">Left Wing (LW)</option>
                            <option value="RW">Right Wing (RW)</option>
                            <option value="LD">Left Defense (LD)</option>
                            <option value="RD">Right Defense (RD)</option>
                            <option value="G">Goalie (G)</option>
                            <option value="F">Forward (F)</option>
                            <option value="D">Defense (D)</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Email</label>
                    <input type="email" name="email" class="form-input" id="gpPlayerEmail">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-input">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Parent/Guardian Name</label>
                        <input type="text" name="parent_name" class="form-input">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Parent Email</label>
                        <input type="email" name="parent_email" class="form-input">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Phone</label>
                        <input type="tel" name="phone" class="form-input">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Season</label>
                        <select name="season_id" class="form-select">
                            <option value="">No Season</option>
                            <?php foreach ($seasons as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= $s['is_active'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Notes</label>
                    <textarea name="notes" class="form-input" rows="2" style="resize:vertical;"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border);margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Player</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Player Modal -->
<div class="modal-overlay" id="gpEditPlayerModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
    <div class="modal-content" style="width:90%;max-width:560px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3><i class="fas fa-pen"></i> Edit Player</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('gpEditPlayerModal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="/process_video.php" id="gpEditPlayerForm">
                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="update_roster_player">
                <input type="hidden" name="player_id" id="editPlayerId">
                <input type="hidden" name="team_id" value="<?= $roster_team_id ?>">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required id="editPlayerFirstName">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required id="editPlayerLastName">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Jersey Number</label>
                        <input type="number" name="jersey_number" class="form-input" min="0" max="99" id="editPlayerJersey">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Position</label>
                        <select name="position" class="form-select" id="editPlayerPosition">
                            <option value="">Select Position</option>
                            <option value="C">Center (C)</option>
                            <option value="LW">Left Wing (LW)</option>
                            <option value="RW">Right Wing (RW)</option>
                            <option value="LD">Left Defense (LD)</option>
                            <option value="RD">Right Defense (RD)</option>
                            <option value="G">Goalie (G)</option>
                            <option value="F">Forward (F)</option>
                            <option value="D">Defense (D)</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Email</label>
                    <input type="email" name="email" class="form-input" id="editPlayerEmail">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-input" id="editPlayerDOB">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Parent/Guardian Name</label>
                        <input type="text" name="parent_name" class="form-input" id="editPlayerParentName">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Parent Email</label>
                        <input type="email" name="parent_email" class="form-input" id="editPlayerParentEmail">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Phone</label>
                        <input type="tel" name="phone" class="form-input" id="editPlayerPhone">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Season</label>
                        <select name="season_id" class="form-select" id="editPlayerSeason">
                            <option value="">No Season</option>
                            <?php foreach ($seasons as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Notes</label>
                    <textarea name="notes" class="form-input" rows="2" style="resize:vertical;" id="editPlayerNotes"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border);margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link Account Modal -->
<div class="modal-overlay" id="gpLinkModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
    <div class="modal-content" style="width:90%;max-width:480px;">
        <div class="modal-header">
            <h3><i class="fas fa-link"></i> Link to Arctic Wolves Account</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('gpLinkModal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">
                Link <strong id="gpLinkPlayerName"></strong> to an existing Arctic Wolves user account.
                This will associate their roster profile with the user without creating a new account.
            </p>
            <form method="POST" action="/process_video.php">
                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="link_roster_player">
                <input type="hidden" name="player_id" id="gpLinkPlayerId">
                <input type="hidden" name="team_id" value="<?= $roster_team_id ?>">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Select User Account</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Choose an account...</option>
                        <?php foreach ($aw_users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?> (<?= htmlspecialchars($u['email'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border);">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-link"></i> Link Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add Player modal
    var addModal = document.getElementById('gpAddPlayerModal');
    document.getElementById('gpAddPlayerBtn').addEventListener('click', function() { addModal.style.display = 'flex'; });
    addModal.addEventListener('click', function(e) { if (e.target === addModal) addModal.style.display = 'none'; });

    // Auto-fill from linked user selection
    document.getElementById('gpLinkUserSelect').addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        if (this.value) {
            document.getElementById('gpPlayerFirstName').value = opt.dataset.fname || '';
            document.getElementById('gpPlayerLastName').value = opt.dataset.lname || '';
            document.getElementById('gpPlayerEmail').value = opt.dataset.email || '';
        }
    });

    // Edit Player modal
    var editModal = document.getElementById('gpEditPlayerModal');
    document.querySelectorAll('.gp-edit-player').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var p = JSON.parse(this.dataset.player);
            document.getElementById('editPlayerId').value = p.id;
            document.getElementById('editPlayerFirstName').value = p.first_name || '';
            document.getElementById('editPlayerLastName').value = p.last_name || '';
            document.getElementById('editPlayerJersey').value = p.jersey_number || '';
            document.getElementById('editPlayerPosition').value = p.position || '';
            document.getElementById('editPlayerEmail').value = p.email || '';
            document.getElementById('editPlayerDOB').value = p.date_of_birth || '';
            document.getElementById('editPlayerParentName').value = p.parent_name || '';
            document.getElementById('editPlayerParentEmail').value = p.parent_email || '';
            document.getElementById('editPlayerPhone').value = p.phone || '';
            document.getElementById('editPlayerSeason').value = p.season_id || '';
            document.getElementById('editPlayerNotes').value = p.notes || '';
            editModal.style.display = 'flex';
        });
    });
    editModal.addEventListener('click', function(e) { if (e.target === editModal) editModal.style.display = 'none'; });

    // Link Account modal
    var linkModal = document.getElementById('gpLinkModal');
    document.querySelectorAll('.gp-link-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('gpLinkPlayerId').value = this.dataset.playerId;
            document.getElementById('gpLinkPlayerName').textContent = this.dataset.playerName;
            linkModal.style.display = 'flex';
        });
    });
    linkModal.addEventListener('click', function(e) { if (e.target === linkModal) linkModal.style.display = 'none'; });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            addModal.style.display = 'none';
            editModal.style.display = 'none';
            linkModal.style.display = 'none';
        }
    });
});
</script>
