<?php
/**
 * PWA Admin Team Coaches - Full CRUD mobile-native management
 * Purpose-built for mobile phones with FAB, action sheets, and bottom sheet forms.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

// --- Data Loading (matches desktop) ---
try {
    $seasons_stmt = $pdo->query("SELECT * FROM seasons ORDER BY start_date DESC");
    $seasons = $seasons_stmt->fetchAll();
} catch (PDOException $e) { $seasons = []; }

try {
    $coaches_stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role IN ('coach','team_coach','admin') ORDER BY last_name, first_name");
    $coaches = $coaches_stmt->fetchAll();
    $coaches = decryptUserRows($coaches);
} catch (PDOException $e) { $coaches = []; }

try {
    $teams_stmt = $pdo->query("SELECT id, name, division, season FROM teams WHERE is_active=1 ORDER BY name");
    $teams = $teams_stmt->fetchAll();
} catch (PDOException $e) { $teams = []; }

try {
    $assignments_stmt = $pdo->query("
        SELECT tca.*, u.first_name, u.last_name, u.email, t.name as team_name, s.name as season_name, s.is_active
        FROM team_coach_assignments tca
        INNER JOIN users u ON tca.coach_id = u.id
        INNER JOIN teams t ON tca.team_id = t.id
        INNER JOIN seasons s ON tca.season_id = s.id
        ORDER BY s.is_active DESC, s.start_date DESC, u.last_name, t.name
    ");
    $assignments = $assignments_stmt->fetchAll();
    $assignments = decryptUserRows($assignments);
} catch (PDOException $e) { $assignments = []; }

try {
    $team_seasons_stmt = $pdo->query("
        SELECT ts.*, t.name as team_name, t.division, s.name as season_name, s.is_active as season_active
        FROM team_seasons ts
        INNER JOIN teams t ON ts.team_id = t.id
        INNER JOIN seasons s ON ts.season_id = s.id
        ORDER BY s.is_active DESC, s.start_date DESC, t.name
    ");
    $team_seasons = $team_seasons_stmt->fetchAll();
} catch (PDOException $e) { $team_seasons = []; }

try {
    $athletes_stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role='athlete' ORDER BY last_name, first_name");
    $athletes = $athletes_stmt->fetchAll();
    $athletes = decryptUserRows($athletes);
} catch (PDOException $e) { $athletes = []; }

try {
    $roster_stmt = $pdo->query("
        SELECT tr.*, u.first_name, u.last_name, u.email, t.name as team_name, s.name as season_name, s.is_active as season_active
        FROM team_roster tr
        INNER JOIN users u ON tr.athlete_id = u.id
        INNER JOIN teams t ON tr.team_id = t.id
        LEFT JOIN seasons s ON tr.season_id = s.id
        ORDER BY s.is_active DESC, s.start_date DESC, t.name, u.last_name
    ");
    $roster_entries = $roster_stmt->fetchAll();
    $roster_entries = decryptUserRows($roster_entries);
} catch (PDOException $e) { $roster_entries = []; }

$team_season_combos = [];
foreach ($team_seasons as $ts) {
    $team_season_combos[] = [
        'team_id' => $ts['team_id'],
        'season_id' => $ts['season_id'],
        'label' => $ts['team_name'] . ' — ' . $ts['season_name']
    ];
}
?>
<style>
.m-tc { padding: 16px 16px 120px; font-family: Inter, sans-serif; }
.m-tc-header { margin-bottom: 16px; }
.m-tc-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-tc-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }

/* Collapsible sections */
.m-tc-section { margin-bottom: 12px; }
.m-tc-section-hdr {
    display: flex; align-items: center; justify-content: space-between;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px 16px; min-height: 44px; cursor: pointer;
    -webkit-tap-highlight-color: transparent; user-select: none;
}
.m-tc-section-hdr.m-tc-expanded { border-radius: 12px 12px 0 0; border-bottom: none; }
.m-tc-section-lbl { display: flex; align-items: center; gap: 10px; }
.m-tc-section-lbl i { color: #6B46C1; font-size: 15px; width: 20px; text-align: center; }
.m-tc-section-lbl span { font-size: 14px; font-weight: 700; color: #fff; }
.m-tc-section-cnt { font-size: 11px; color: #A8A8B8; background: rgba(107,70,193,0.15); padding: 2px 8px; border-radius: 10px; }
.m-tc-section-chevron { color: #6B6B7B; font-size: 12px; transition: transform 0.2s; }
.m-tc-section-hdr.m-tc-expanded .m-tc-section-chevron { transform: rotate(180deg); }
.m-tc-section-body {
    display: none; background: #111119; border: 1px solid #2D2D3F;
    border-top: none; border-radius: 0 0 12px 12px; padding: 12px;
}
.m-tc-section-body.m-tc-open { display: block; }

/* Cards */
.m-tc-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px; margin-bottom: 8px;
}
.m-tc-card:last-child { margin-bottom: 0; }
.m-tc-card-row { display: flex; justify-content: space-between; align-items: flex-start; }
.m-tc-card-info { flex: 1; min-width: 0; }
.m-tc-card-primary { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 2px; }
.m-tc-card-secondary { font-size: 12px; color: #A8A8B8; margin-bottom: 2px; }
.m-tc-card-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.m-tc-badge {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
}
.m-tc-badge-active { background: rgba(34,197,94,0.15); color: #22C55E; }
.m-tc-badge-inactive { background: rgba(100,116,139,0.15); color: #64748B; }
.m-tc-badge-info { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-tc-badge-purple { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-tc-card-actions { display: flex; gap: 6px; flex-shrink: 0; margin-left: 8px; }
.m-tc-btn-sm {
    min-width: 44px; min-height: 44px; border: none; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; cursor: pointer; -webkit-tap-highlight-color: transparent;
}
.m-tc-btn-activate { background: rgba(34,197,94,0.15); color: #22C55E; }
.m-tc-btn-delete { background: rgba(239,68,68,0.15); color: #EF4444; }

/* Empty state */
.m-tc-empty { text-align: center; padding: 24px 12px; color: #6B6B7B; }
.m-tc-empty i { font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.4; }
.m-tc-empty p { font-size: 13px; margin: 0; }

/* FAB */
.m-tc-fab {
    position: fixed; bottom: 60px; right: 16px; z-index: 1000;
    width: 56px; height: 56px; border-radius: 50%; border: none;
    background: #6B46C1; color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    cursor: pointer; -webkit-tap-highlight-color: transparent;
}

/* Overlay */
.m-tc-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    z-index: 1001; -webkit-tap-highlight-color: transparent;
}
.m-tc-overlay.m-tc-visible { display: block; }

/* Action sheet */
.m-tc-action-sheet {
    display: none; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1002;
    background: #16161F; border-radius: 16px 16px 0 0; padding: 8px 0;
    padding-bottom: env(safe-area-inset-bottom, 16px);
}
.m-tc-action-sheet.m-tc-visible { display: block; }
.m-tc-action-sheet-handle {
    width: 36px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 8px auto 12px;
}
.m-tc-action-item {
    display: flex; align-items: center; gap: 14px; padding: 14px 20px;
    min-height: 44px; color: #fff; font-size: 15px; font-weight: 500;
    cursor: pointer; -webkit-tap-highlight-color: transparent;
}
.m-tc-action-item:active { background: rgba(107,70,193,0.1); }
.m-tc-action-item i { width: 20px; text-align: center; color: #6B46C1; font-size: 15px; }
.m-tc-action-cancel {
    display: block; width: calc(100% - 32px); margin: 8px 16px 8px;
    padding: 14px; min-height: 44px; border: 1px solid #2D2D3F; border-radius: 12px;
    background: transparent; color: #A8A8B8; font-size: 15px; font-weight: 600;
    text-align: center; cursor: pointer;
}

/* Bottom sheet */
.m-tc-sheet {
    display: none; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1003;
    background: #16161F; border-radius: 16px 16px 0 0;
    max-height: 85vh; overflow-y: auto; -webkit-overflow-scrolling: touch;
    padding: 0 0 env(safe-area-inset-bottom, 16px);
}
.m-tc-sheet.m-tc-visible { display: block; }
.m-tc-sheet-handle {
    width: 36px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 10px auto 0;
}
.m-tc-sheet-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px 12px;
}
.m-tc-sheet-title { font-size: 17px; font-weight: 700; color: #fff; }
.m-tc-sheet-close {
    width: 44px; height: 44px; border: none; background: transparent;
    color: #A8A8B8; font-size: 18px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.m-tc-sheet-body { padding: 0 20px 20px; }
.m-tc-form-group { margin-bottom: 16px; }
.m-tc-form-label { font-size: 12px; font-weight: 700; color: #A8A8B8; text-transform: uppercase; margin-bottom: 6px; display: block; }
.m-tc-form-input, .m-tc-form-select {
    width: 100%; padding: 12px; background: #0A0A0F; border: 1px solid #2D2D3F;
    border-radius: 8px; color: #fff; font-size: 15px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box; -webkit-appearance: none;
}
.m-tc-form-input:focus, .m-tc-form-select:focus { outline: none; border-color: #6B46C1; }
.m-tc-form-submit {
    width: 100%; padding: 14px; min-height: 44px; border: none; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 15px; font-weight: 700;
    font-family: Inter, sans-serif; cursor: pointer; margin-top: 4px;
}
.m-tc-form-submit:active { background: #5A38A8; }
</style>

<div class="m-tc">
    <div class="m-tc-header">
        <h2 class="m-tc-title">Team Coach Management</h2>
        <p class="m-tc-sub">Seasons, assignments, rosters</p>
    </div>

    <!-- Section 1: Seasons -->
    <div class="m-tc-section">
        <div class="m-tc-section-hdr m-tc-expanded" onclick="mTcToggle(this)">
            <div class="m-tc-section-lbl"><i class="fas fa-calendar-alt"></i><span>Seasons</span></div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="m-tc-section-cnt"><?= count($seasons) ?></span>
                <i class="fas fa-chevron-down m-tc-section-chevron"></i>
            </div>
        </div>
        <div class="m-tc-section-body m-tc-open">
            <?php if (empty($seasons)): ?>
                <div class="m-tc-empty"><i class="fas fa-calendar-alt"></i><p>No seasons yet</p></div>
            <?php else: ?>
                <?php foreach ($seasons as $season): ?>
                <div class="m-tc-card">
                    <div class="m-tc-card-row">
                        <div class="m-tc-card-info">
                            <div class="m-tc-card-primary"><?= htmlspecialchars($season['name']) ?></div>
                            <div class="m-tc-card-secondary"><?= date('M j, Y', strtotime($season['start_date'])) ?> — <?= date('M j, Y', strtotime($season['end_date'])) ?></div>
                            <div class="m-tc-card-meta">
                                <span class="m-tc-badge <?= $season['is_active'] ? 'm-tc-badge-active' : 'm-tc-badge-inactive' ?>"><?= $season['is_active'] ? 'Active' : 'Inactive' ?></span>
                            </div>
                        </div>
                        <div class="m-tc-card-actions">
                            <?php if (!$season['is_active']): ?>
                            <form method="POST" action="process_admin_team_coaches.php" style="margin:0;">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="activate_season">
                                <input type="hidden" name="season_id" value="<?= $season['id'] ?>">
                                <button type="submit" class="m-tc-btn-sm m-tc-btn-activate" title="Activate"><i class="fas fa-check"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="process_admin_team_coaches.php" style="margin:0;" data-confirm="Delete this season?">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="delete_season">
                                <input type="hidden" name="season_id" value="<?= $season['id'] ?>">
                                <button type="submit" class="m-tc-btn-sm m-tc-btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 2: Coach Assignments -->
    <div class="m-tc-section">
        <div class="m-tc-section-hdr" onclick="mTcToggle(this)">
            <div class="m-tc-section-lbl"><i class="fas fa-link"></i><span>Coach Assignments</span></div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="m-tc-section-cnt"><?= count($assignments) ?></span>
                <i class="fas fa-chevron-down m-tc-section-chevron"></i>
            </div>
        </div>
        <div class="m-tc-section-body">
            <?php if (empty($assignments)): ?>
                <div class="m-tc-empty"><i class="fas fa-link"></i><p>No assignments yet</p></div>
            <?php else: ?>
                <?php foreach ($assignments as $a): ?>
                <div class="m-tc-card">
                    <div class="m-tc-card-row">
                        <div class="m-tc-card-info">
                            <div class="m-tc-card-primary"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                            <div class="m-tc-card-secondary"><?= htmlspecialchars($a['team_name']) ?></div>
                            <div class="m-tc-card-meta">
                                <span class="m-tc-badge m-tc-badge-info"><?= htmlspecialchars($a['season_name']) ?></span>
                                <?php if ($a['is_active']): ?><span class="m-tc-badge m-tc-badge-active">Active</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="m-tc-card-actions">
                            <form method="POST" action="process_admin_team_coaches.php" style="margin:0;" data-confirm="Remove this assignment?">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="delete_assignment">
                                <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="m-tc-btn-sm m-tc-btn-delete" title="Remove"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 3: Team Seasons -->
    <div class="m-tc-section">
        <div class="m-tc-section-hdr" onclick="mTcToggle(this)">
            <div class="m-tc-section-lbl"><i class="fas fa-layer-group"></i><span>Team Seasons</span></div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="m-tc-section-cnt"><?= count($team_seasons) ?></span>
                <i class="fas fa-chevron-down m-tc-section-chevron"></i>
            </div>
        </div>
        <div class="m-tc-section-body">
            <?php if (empty($team_seasons)): ?>
                <div class="m-tc-empty"><i class="fas fa-layer-group"></i><p>No team seasons yet</p></div>
            <?php else: ?>
                <?php foreach ($team_seasons as $ts): ?>
                <div class="m-tc-card">
                    <div class="m-tc-card-row">
                        <div class="m-tc-card-info">
                            <div class="m-tc-card-primary"><?= htmlspecialchars($ts['team_name']) ?></div>
                            <div class="m-tc-card-secondary"><?= htmlspecialchars($ts['season_name']) ?></div>
                            <div class="m-tc-card-meta">
                                <span class="m-tc-badge <?= $ts['season_active'] ? 'm-tc-badge-active' : 'm-tc-badge-inactive' ?>"><?= $ts['season_active'] ? 'Active' : 'Inactive' ?></span>
                                <?php if (!empty($ts['division'])): ?><span class="m-tc-badge m-tc-badge-purple"><?= htmlspecialchars($ts['division']) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="m-tc-card-actions">
                            <form method="POST" action="process_admin_team_coaches.php" style="margin:0;" data-confirm="Remove this season from the team? This also removes roster entries.">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="remove_team_season">
                                <input type="hidden" name="team_season_id" value="<?= $ts['id'] ?>">
                                <button type="submit" class="m-tc-btn-sm m-tc-btn-delete" title="Remove"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 4: Team Roster -->
    <div class="m-tc-section">
        <div class="m-tc-section-hdr" onclick="mTcToggle(this)">
            <div class="m-tc-section-lbl"><i class="fas fa-running"></i><span>Team Roster</span></div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="m-tc-section-cnt"><?= count($roster_entries) ?></span>
                <i class="fas fa-chevron-down m-tc-section-chevron"></i>
            </div>
        </div>
        <div class="m-tc-section-body">
            <?php if (empty($roster_entries)): ?>
                <div class="m-tc-empty"><i class="fas fa-running"></i><p>No roster entries yet</p></div>
            <?php else: ?>
                <?php foreach ($roster_entries as $r): ?>
                <div class="m-tc-card">
                    <div class="m-tc-card-row">
                        <div class="m-tc-card-info">
                            <div class="m-tc-card-primary"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
                            <div class="m-tc-card-secondary"><?= htmlspecialchars($r['team_name']) ?> · <?= htmlspecialchars($r['season_name'] ?? '—') ?></div>
                            <div class="m-tc-card-meta">
                                <?php if ($r['jersey_number'] !== null): ?><span class="m-tc-badge m-tc-badge-info">#<?= htmlspecialchars($r['jersey_number']) ?></span><?php endif; ?>
                                <?php if (!empty($r['position'])): ?><span class="m-tc-badge m-tc-badge-purple"><?= htmlspecialchars($r['position']) ?></span><?php endif; ?>
                                <?php if (!empty($r['season_active'])): ?><span class="m-tc-badge m-tc-badge-active">Active</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="m-tc-card-actions">
                            <form method="POST" action="process_admin_team_coaches.php" style="margin:0;" data-confirm="Remove this athlete from the roster?">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="remove_roster_athlete">
                                <input type="hidden" name="roster_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="m-tc-btn-sm m-tc-btn-delete" title="Remove"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FAB -->
<button class="m-tc-fab" id="mTcFab" onclick="mTcShowActions()"><i class="fas fa-plus"></i></button>

<!-- Overlay -->
<div class="m-tc-overlay" id="mTcOverlay" onclick="mTcCloseAll()"></div>

<!-- Action Sheet -->
<div class="m-tc-action-sheet" id="mTcActionSheet">
    <div class="m-tc-action-sheet-handle"></div>
    <div class="m-tc-action-item" onclick="mTcOpenSheet('season')"><i class="fas fa-calendar-alt"></i>Create Season</div>
    <div class="m-tc-action-item" onclick="mTcOpenSheet('assignment')"><i class="fas fa-link"></i>Assign Coach</div>
    <div class="m-tc-action-item" onclick="mTcOpenSheet('teamseason')"><i class="fas fa-layer-group"></i>Add Team Season</div>
    <div class="m-tc-action-item" onclick="mTcOpenSheet('roster')"><i class="fas fa-running"></i>Add Roster Athlete</div>
    <button class="m-tc-action-cancel" onclick="mTcCloseAll()">Cancel</button>
</div>

<!-- Bottom Sheet: Create Season -->
<div class="m-tc-sheet" id="mTcSheetSeason">
    <div class="m-tc-sheet-handle"></div>
    <div class="m-tc-sheet-hdr">
        <span class="m-tc-sheet-title">Create Season</span>
        <button class="m-tc-sheet-close" onclick="mTcCloseAll()"><i class="fas fa-times"></i></button>
    </div>
    <div class="m-tc-sheet-body">
        <form method="POST" action="process_admin_team_coaches.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create_season">
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Season Name</label>
                <input type="text" name="season_name" class="m-tc-form-input" placeholder="2024-2025" required>
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Start Date</label>
                <input type="date" name="start_date" class="m-tc-form-input" required>
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">End Date</label>
                <input type="date" name="end_date" class="m-tc-form-input" required>
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Active Season</label>
                <select name="is_active" class="m-tc-form-select">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <button type="submit" class="m-tc-form-submit">Create Season</button>
        </form>
    </div>
</div>

<!-- Bottom Sheet: Assign Coach -->
<div class="m-tc-sheet" id="mTcSheetAssignment">
    <div class="m-tc-sheet-handle"></div>
    <div class="m-tc-sheet-hdr">
        <span class="m-tc-sheet-title">Assign Coach</span>
        <button class="m-tc-sheet-close" onclick="mTcCloseAll()"><i class="fas fa-times"></i></button>
    </div>
    <div class="m-tc-sheet-body">
        <form method="POST" action="process_admin_team_coaches.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create_assignment">
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Team Coach</label>
                <select name="coach_id" class="m-tc-form-select" required>
                    <option value="">Select Coach</option>
                    <?php foreach ($coaches as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Team</label>
                <select name="team_id" class="m-tc-form-select" required>
                    <option value="">Select Team</option>
                    <?php foreach ($teams as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Season</label>
                <select name="season_id" class="m-tc-form-select" required>
                    <option value="">Select Season</option>
                    <?php foreach ($seasons as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['is_active'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?><?= $s['is_active'] ? ' (Active)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="m-tc-form-submit">Create Assignment</button>
        </form>
    </div>
</div>

<!-- Bottom Sheet: Add Team Season -->
<div class="m-tc-sheet" id="mTcSheetTeamseason">
    <div class="m-tc-sheet-handle"></div>
    <div class="m-tc-sheet-hdr">
        <span class="m-tc-sheet-title">Add Team Season</span>
        <button class="m-tc-sheet-close" onclick="mTcCloseAll()"><i class="fas fa-times"></i></button>
    </div>
    <div class="m-tc-sheet-body">
        <form method="POST" action="process_admin_team_coaches.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="add_team_season">
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Team</label>
                <select name="team_id" class="m-tc-form-select" required>
                    <option value="">Select Team</option>
                    <?php foreach ($teams as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Season</label>
                <select name="season_id" class="m-tc-form-select" required>
                    <option value="">Select Season</option>
                    <?php foreach ($seasons as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['is_active'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?><?= $s['is_active'] ? ' (Active)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="m-tc-form-submit">Add Team Season</button>
        </form>
    </div>
</div>

<!-- Bottom Sheet: Add Roster Athlete -->
<div class="m-tc-sheet" id="mTcSheetRoster">
    <div class="m-tc-sheet-handle"></div>
    <div class="m-tc-sheet-hdr">
        <span class="m-tc-sheet-title">Add Roster Athlete</span>
        <button class="m-tc-sheet-close" onclick="mTcCloseAll()"><i class="fas fa-times"></i></button>
    </div>
    <div class="m-tc-sheet-body">
        <form method="POST" action="process_admin_team_coaches.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="add_roster_athlete">
            <input type="hidden" name="team_id" id="mTcRosterTeamId">
            <input type="hidden" name="season_id" id="mTcRosterSeasonId">
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Team &amp; Season</label>
                <select name="team_season_combo" class="m-tc-form-select" required id="mTcTeamSeasonCombo" onchange="mTcSplitCombo()">
                    <option value="">Select Team &amp; Season</option>
                    <?php foreach ($team_season_combos as $combo): ?>
                    <option value="<?= $combo['team_id'] ?>|<?= $combo['season_id'] ?>"><?= htmlspecialchars($combo['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Athlete</label>
                <select name="athlete_id" class="m-tc-form-select" required>
                    <option value="">Select Athlete</option>
                    <?php foreach ($athletes as $ath): ?>
                    <option value="<?= $ath['id'] ?>"><?= htmlspecialchars($ath['first_name'] . ' ' . $ath['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Jersey #</label>
                <input type="number" name="jersey_number" class="m-tc-form-input" placeholder="Optional" min="0" max="99">
            </div>
            <div class="m-tc-form-group">
                <label class="m-tc-form-label">Position</label>
                <select name="position" class="m-tc-form-select">
                    <option value="">Select Position</option>
                    <option value="Forward">Forward</option>
                    <option value="Defense">Defense</option>
                    <option value="Goalie">Goalie</option>
                    <option value="Left Wing">Left Wing</option>
                    <option value="Center">Center</option>
                    <option value="Right Wing">Right Wing</option>
                    <option value="Left Defense">Left Defense</option>
                    <option value="Right Defense">Right Defense</option>
                </select>
            </div>
            <button type="submit" class="m-tc-form-submit">Add to Roster</button>
        </form>
    </div>
</div>

<script>
function mTcToggle(hdr) {
    var body = hdr.nextElementSibling;
    var isOpen = body.classList.contains('m-tc-open');
    body.classList.toggle('m-tc-open');
    hdr.classList.toggle('m-tc-expanded');
}

function mTcShowActions() {
    document.getElementById('mTcOverlay').classList.add('m-tc-visible');
    document.getElementById('mTcActionSheet').classList.add('m-tc-visible');
}

function mTcCloseAll() {
    document.getElementById('mTcOverlay').classList.remove('m-tc-visible');
    document.getElementById('mTcActionSheet').classList.remove('m-tc-visible');
    var sheets = document.querySelectorAll('.m-tc-sheet');
    for (var i = 0; i < sheets.length; i++) sheets[i].classList.remove('m-tc-visible');
}

function mTcOpenSheet(type) {
    document.getElementById('mTcActionSheet').classList.remove('m-tc-visible');
    var id = 'mTcSheet' + type.charAt(0).toUpperCase() + type.slice(1);
    document.getElementById(id).classList.add('m-tc-visible');
}

function mTcSplitCombo() {
    var sel = document.getElementById('mTcTeamSeasonCombo');
    var parts = sel.value.split('|');
    document.getElementById('mTcRosterTeamId').value = parts[0] || '';
    document.getElementById('mTcRosterSeasonId').value = parts[1] || '';
}
</script>
