<?php
/**
 * Scoreboard Display View – Professional Arena Layout
 *
 * Modeled after Nevco 4770 / Daktronics hockey scoreboards:
 *   - Large score & game clock in center panel
 *   - Dedicated penalty timer boxes (2 per team) with player # + countdown
 *   - Power Play / Short Handed indicators
 *   - Shots on Goal per team
 *   - Timeout indicators
 *   - Goal light flash animation
 *   - Working game clock with operator controls
 *
 * NHL Penalty Rules:
 *   - Max 2 concurrent minor/major penalties per team (5-on-3 max)
 *   - 3rd+ penalties queued until earlier one expires or cleared by PPG
 *   - Minor (2 min) cleared on PPG; Major (5 min) NOT cleared on goals
 *   - Misconduct (10 min) – player sits, team NOT shorthanded
 *   - Coincidental/offsetting – equal penalties cancel, 4-on-4, only unmatched create PP
 */

// Helper: classify penalty type from duration
function sbGetPenaltyType($duration) {
    $d = (int)$duration;
    if ($d <= 2) return 'minor';
    if ($d === 4) return 'double_minor';
    if ($d === 5) return 'major';
    if ($d >= 10) return 'misconduct';
    return 'minor';
}

// Separate home/away penalties for the penalty box display
$home_penalties = array_filter($game_penalties, function($p) { return ($p['team'] ?? '') === 'home'; });
$away_penalties = array_filter($game_penalties, function($p) { return ($p['team'] ?? '') === 'away'; });
$home_penalties = array_values($home_penalties);
$away_penalties = array_values($away_penalties);

// NHL: Only count shorthanded penalties (minors/majors), NOT misconducts
$home_shorthanded = 0;
$away_shorthanded = 0;
foreach ($home_penalties as $p) {
    $pt = sbGetPenaltyType($p['duration_minutes'] ?? 2);
    if ($pt !== 'misconduct') $home_shorthanded++;
}
foreach ($away_penalties as $p) {
    $pt = sbGetPenaltyType($p['duration_minutes'] ?? 2);
    if ($pt !== 'misconduct') $away_shorthanded++;
}

// NHL: Coincidental penalties offset – only unmatched penalties create PP
$offset_count = min($home_shorthanded, $away_shorthanded);
$home_net_penalties = $home_shorthanded - $offset_count;
$away_net_penalties = $away_shorthanded - $offset_count;

// Power play: only when opposing team has MORE net penalties
$home_pp = ($away_net_penalties > 0 && $away_net_penalties > $home_net_penalties);
$away_pp = ($home_net_penalties > 0 && $home_net_penalties > $away_net_penalties);

// Strength display (5v5, 5v4, 5v3, 4v4, etc.)
$home_skaters = max(3, 5 - $home_net_penalties);
$away_skaters = max(3, 5 - $away_net_penalties);
// If coincidental (both have penalties), it's 4v4 or 3v3
if ($offset_count > 0 && $home_net_penalties === 0 && $away_net_penalties === 0) {
    $home_skaters = max(3, 5 - min($offset_count, 2));
    $away_skaters = $home_skaters;
}
$strength_display = $home_skaters . 'v' . $away_skaters;
$is_even_strength = ($home_skaters === $away_skaters && $home_skaters === 5);

// Most recent 2 shorthanded penalties per team for display on the board
$home_pen_display = [];
$away_pen_display = [];
foreach ($home_penalties as $p) {
    $pt = sbGetPenaltyType($p['duration_minutes'] ?? 2);
    if ($pt !== 'misconduct' && count($home_pen_display) < 2) $home_pen_display[] = $p;
}
foreach ($away_penalties as $p) {
    $pt = sbGetPenaltyType($p['duration_minutes'] ?? 2);
    if ($pt !== 'misconduct' && count($away_pen_display) < 2) $away_pen_display[] = $p;
}
?>

<!-- Goal light overlay -->
<div class="sb-goal-light" id="sbGoalLight"></div>

<div class="sb-topbar">
    <div class="sb-topbar-brand">
        <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Arctic Wolves">
        <span>Scoreboard</span>
    </div>
    <div class="sb-topbar-actions">
        <?php if ($active_game): ?>
        <a href="?view=scoresheet" class="sb-btn"><i class="fas fa-clipboard-list"></i> <span>Scoresheet</span></a>
        <a href="?view=video_board" class="sb-btn"><i class="fas fa-tv"></i> <span>Video Board</span></a>
        <button class="sb-btn sb-btn-danger" onclick="sbEndGame()"><i class="fas fa-flag-checkered"></i> <span>End Game</span></button>
        <?php else: ?>
        <button class="sb-btn sb-btn-primary" onclick="document.getElementById('sb-new-game-modal').classList.add('active')"><i class="fas fa-plus"></i> <span>New Game</span></button>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
        <a href="?view=settings" class="sb-btn"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <?php endif; ?>
        <span class="sb-clock" id="sbClock"></span>
    </div>
</div>

<?php if (!$active_game): ?>
<!-- No Active Game -->
<div class="sb-no-game">
    <i class="fas fa-hockey-puck"></i>
    <h2>No Active Game</h2>
    <p>Start a new game to activate the scoreboard.</p>
    <button class="sb-btn sb-btn-primary" onclick="document.getElementById('sb-new-game-modal').classList.add('active')" style="font-size:16px;padding:12px 24px;">
        <i class="fas fa-plus"></i> Start New Game
    </button>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════
     PROFESSIONAL ARENA SCOREBOARD (Nevco / Daktronics style)
     ═══════════════════════════════════════════════════════ -->
<div class="sb-main">

    <!-- ── Top Board: Score + Clock + Penalty Timers ────── -->
    <div class="sb-board">

        <!-- HOME TEAM SIDE -->
        <div class="sb-board-team home">
            <div class="sb-board-team-header">
                <span class="sb-board-label">HOME</span>
                <?php if ($home_pp): ?><span class="sb-pp-indicator">PP</span><?php endif; ?>
                <span class="sb-board-indicator" id="sbDelayedHome" style="display:none;background:#F59E0B;color:#000;">DEL</span>
                <span class="sb-board-indicator" id="sbEmptyNetHome" style="display:none;background:#EF4444;">EN</span>
            </div>
            <div class="sb-board-team-name"><?= htmlspecialchars($active_game['home_team_name'] ?? 'Home') ?></div>
            <div class="sb-board-score" id="sbHomeScore"><?= $home_score ?></div>
            <div class="sb-board-stats">
                <div class="sb-stat-box">
                    <span class="sb-stat-label">SOG</span>
                    <span class="sb-stat-value" id="sbHomeShots"><?= $home_shots ?></span>
                </div>
                <div class="sb-stat-box sb-timeout-box" id="sbHomeTimeout">
                    <span class="sb-stat-label">T/O</span>
                    <span class="sb-stat-value">●</span>
                </div>
            </div>
        </div>

        <!-- HOME PENALTY BOX -->
        <div class="sb-board-penalty-stack home">
            <div class="sb-penalty-timer-label">HOME PENALTIES</div>
            <?php for ($i = 0; $i < 2; $i++): ?>
            <div class="sb-penalty-timer-box <?= isset($home_pen_display[$i]) ? 'active' : '' ?>" id="sbHomePen<?= $i ?>">
                <span class="sb-pen-player"><?= isset($home_pen_display[$i]) ? '#' . htmlspecialchars($home_pen_display[$i]['player_number'] ?? '?') : '—' ?></span>
                <span class="sb-pen-countdown" id="sbHomePenTime<?= $i ?>"><?= isset($home_pen_display[$i]) ? htmlspecialchars($home_pen_display[$i]['duration_minutes'] ?? '2') . ':00' : '--:--' ?></span>
            </div>
            <?php endfor; ?>
        </div>

        <!-- CENTER CLOCK -->
        <div class="sb-board-center">
            <div class="sb-board-period" id="sbPeriod">
                <span class="sb-period-label">PERIOD</span>
                <span class="sb-period-value"><?= htmlspecialchars($active_game['current_period'] ?? '1') ?></span>
            </div>
            <div class="sb-board-clock" id="sbGameClock">20:00</div>
            <?php if (!$is_even_strength): ?>
            <div class="sb-board-strength" id="sbStrength"><?= $strength_display ?></div>
            <?php endif; ?>
            <div class="sb-board-status <?= ($active_game['status'] === 'intermission') ? 'intermission' : '' ?>" id="sbStatus">
                <?= htmlspecialchars(strtoupper($active_game['status'] ?? 'WARMUP')) ?>
            </div>
        </div>

        <!-- AWAY PENALTY BOX -->
        <div class="sb-board-penalty-stack away">
            <div class="sb-penalty-timer-label">AWAY PENALTIES</div>
            <?php for ($i = 0; $i < 2; $i++): ?>
            <div class="sb-penalty-timer-box <?= isset($away_pen_display[$i]) ? 'active' : '' ?>" id="sbAwayPen<?= $i ?>">
                <span class="sb-pen-player"><?= isset($away_pen_display[$i]) ? '#' . htmlspecialchars($away_pen_display[$i]['player_number'] ?? '?') : '—' ?></span>
                <span class="sb-pen-countdown" id="sbAwayPenTime<?= $i ?>"><?= isset($away_pen_display[$i]) ? htmlspecialchars($away_pen_display[$i]['duration_minutes'] ?? '2') . ':00' : '--:--' ?></span>
            </div>
            <?php endfor; ?>
        </div>

        <!-- AWAY TEAM SIDE -->
        <div class="sb-board-team away">
            <div class="sb-board-team-header">
                <?php if ($away_pp): ?><span class="sb-pp-indicator">PP</span><?php endif; ?>
                <span class="sb-board-indicator" id="sbDelayedAway" style="display:none;background:#F59E0B;color:#000;">DEL</span>
                <span class="sb-board-indicator" id="sbEmptyNetAway" style="display:none;background:#EF4444;">EN</span>
                <span class="sb-board-label">GUEST</span>
            </div>
            <div class="sb-board-team-name"><?= htmlspecialchars($active_game['away_team_name'] ?? 'Away') ?></div>
            <div class="sb-board-score" id="sbAwayScore"><?= $away_score ?></div>
            <div class="sb-board-stats">
                <div class="sb-stat-box sb-timeout-box" id="sbAwayTimeout">
                    <span class="sb-stat-label">T/O</span>
                    <span class="sb-stat-value">●</span>
                </div>
                <div class="sb-stat-box">
                    <span class="sb-stat-label">SOG</span>
                    <span class="sb-stat-value" id="sbAwayShots"><?= $away_shots ?></span>
                </div>
            </div>
        </div>

    </div><!-- /.sb-board -->

    <!-- ── Operator Controls – 4-Column Layout ─────────── -->
    <style>
        .sb-controls-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: clamp(8px, 1.2vw, 16px);
            padding: clamp(8px, 1.2vw, 16px);
            padding-bottom: 24px;
        }
        @media (max-width: 1200px) {
            .sb-controls-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .sb-controls-grid { grid-template-columns: 1fr; }
        }
        .sb-ctrl-panel {
            background: #111118;
            border: 1px solid #2D2D3F;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .sb-ctrl-panel-title {
            font-size: 15px;
            font-weight: 700;
            color: #E2E8F0;
            padding-bottom: 8px;
            border-bottom: 1px solid #2D2D3F;
        }
        .sb-ctrl-section-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #8B8BA3;
            margin: 0;
        }
        .sb-ctrl-btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 52px;
            padding: clamp(8px, 1vw, 12px) clamp(10px, 1.2vw, 16px);
            font-size: clamp(14px, 1.2vw, 16px);
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            color: #fff;
            background: #6B46C1;
        }
        .sb-ctrl-btn-primary:hover { background: #7C5DD4; }
        .sb-ctrl-btn-primary:active { transform: scale(0.97); }
        .sb-ctrl-btn-primary.home-accent { background: #2563EB; }
        .sb-ctrl-btn-primary.home-accent:hover { background: #3B82F6; }
        .sb-ctrl-btn-primary.away-accent { background: #DC2626; }
        .sb-ctrl-btn-primary.away-accent:hover { background: #EF4444; }
        .sb-ctrl-btn-primary.goal-btn { min-height: 56px; font-size: 18px; }
        .sb-ctrl-btn-primary.buzzer-btn {
            background: #B91C1C;
            min-height: 56px;
            font-size: 18px;
            letter-spacing: 0.04em;
        }
        .sb-ctrl-btn-primary.buzzer-btn:hover { background: #DC2626; }
        .sb-ctrl-btn-primary.horn-btn {
            background: #D97706;
            min-height: 56px;
            font-size: 18px;
            letter-spacing: 0.04em;
        }
        .sb-ctrl-btn-primary.horn-btn:hover { background: #F59E0B; }
        .sb-ctrl-btn-primary.announce-btn {
            background: #0D9488;
            min-height: 56px;
            font-size: 18px;
        }
        .sb-ctrl-btn-primary.announce-btn:hover { background: #14B8A6; }
        .sb-ctrl-btn-primary.announce-btn.active {
            background: #DC2626;
            animation: sb-announce-pulse 1.5s ease-in-out infinite;
        }
        @keyframes sb-announce-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,0.4); }
            50% { box-shadow: 0 0 0 10px rgba(220,38,38,0); }
        }
        .sb-ctrl-btn-secondary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 48px;
            padding: clamp(6px, 0.8vw, 10px) clamp(8px, 1vw, 14px);
            font-size: clamp(12px, 1.1vw, 14px);
            font-weight: 600;
            border: 1px solid #2D2D3F;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
            color: #C4C4D4;
            background: #1A1A24;
        }
        .sb-ctrl-btn-secondary:hover {
            background: #222233;
            border-color: #6B46C1;
            color: #E2E8F0;
        }
        .sb-ctrl-btn-secondary:active { transform: scale(0.97); }
        .sb-ctrl-btn-row {
            display: flex;
            gap: 8px;
        }
        .sb-ctrl-btn-row > * { flex: 1; }
        .sb-ctrl-config-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .sb-ctrl-config-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sb-ctrl-config-row label {
            font-size: 12px;
            font-weight: 600;
            color: #8B8BA3;
            white-space: nowrap;
            min-width: 50px;
        }
        .sb-ctrl-config-row select,
        .sb-ctrl-config-row input[type="number"] {
            flex: 1;
            min-height: 40px;
            padding: 6px 10px;
            font-size: 14px;
            color: #E2E8F0;
            background: #0A0A0F;
            border: 1px solid #2D2D3F;
            border-radius: 6px;
        }
        .sb-ctrl-config-row select:focus,
        .sb-ctrl-config-row input[type="number"]:focus {
            outline: none;
            border-color: #6B46C1;
        }
        .sb-ctrl-penalty-list {
            max-height: 200px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #2D2D3F;
            border-radius: 6px;
            background: #0A0A0F;
        }
        .sb-ctrl-penalty-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 10px;
            font-size: 12px;
            color: #C4C4D4;
            border-bottom: 1px solid #1A1A24;
            gap: 6px;
        }
        .sb-ctrl-penalty-item:last-child { border-bottom: none; }
        /* NHL: Queued penalties (3rd+) shown dimmed with QUEUED badge */
        .sb-ctrl-penalty-item.sb-penalty-queued {
            opacity: 0.55;
            border-left: 3px solid #F59E0B;
        }
        .sb-ctrl-penalty-info { color: #8B8BA3; white-space: nowrap; font-size: 11px; }
        .sb-ctrl-penalty-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }
        .sb-ctrl-penalty-vis-btn {
            width: 26px;
            height: 26px;
            border: 1px solid #4B5563;
            border-radius: 5px;
            background: rgba(75, 85, 99, 0.1);
            color: #9CA3AF;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            transition: background 0.15s;
        }
        .sb-ctrl-penalty-vis-btn:hover { background: rgba(75, 85, 99, 0.25); color: #E2E8F0; }
        .sb-ctrl-penalty-clear-btn {
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            border: 1px solid #DC2626;
            border-radius: 5px;
            background: rgba(220, 38, 38, 0.1);
            color: #DC2626;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            transition: background 0.15s, transform 0.1s;
        }
        .sb-ctrl-penalty-clear-btn:hover {
            background: rgba(220, 38, 38, 0.25);
        }
        .sb-ctrl-penalty-clear-btn:active { transform: scale(0.9); }
        .sb-ctrl-penalty-empty {
            text-align: center;
            padding: 12px;
            font-size: 12px;
            color: #555;
        }
        .sb-ctrl-music-library {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sb-ctrl-now-playing {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #0A0A0F;
            border: 1px solid #2D2D3F;
            border-radius: 8px;
            font-size: 13px;
            color: #8B8BA3;
        }
        .sb-ctrl-now-playing i { color: #6B46C1; }
        .sb-ctrl-now-playing strong { color: #E2E8F0; }
        .sb-ctrl-divider {
            border: none;
            border-top: 1px solid #2D2D3F;
            margin: 4px 0;
        }
        .sb-ctrl-recurring-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #8B8BA3;
            padding: 4px 0;
        }
        .sb-ctrl-period-nav {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .sb-ctrl-period-nav .sb-ctrl-intermission-btn {
            grid-column: span 2;
        }
    </style>

    <div class="sb-controls-grid">

        <!-- ════════ COLUMN 1: HOME TEAM ════════ -->
        <div class="sb-ctrl-panel">
            <div class="sb-ctrl-panel-title">🏠 Home — <?= htmlspecialchars($active_game['home_team_name'] ?? 'Home') ?></div>

            <span class="sb-ctrl-section-label">Goals</span>
            <button class="sb-ctrl-btn-primary home-accent goal-btn" onclick="sbAddGoal('home')">
                <i class="fas fa-plus-circle"></i> +1 Home Goal
            </button>
            <div class="sb-ctrl-btn-row">
                <button class="sb-ctrl-btn-secondary" onclick="sbShowScoreEdit('home')">
                    <i class="fas fa-edit"></i> Edit Score
                </button>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Shots on Goal</span>
            <button class="sb-ctrl-btn-primary home-accent" onclick="sbAddShot('home')">
                <i class="fas fa-bullseye"></i> +1 Shot
            </button>
            <div class="sb-ctrl-btn-row">
                <button class="sb-ctrl-btn-secondary" onclick="sbShowShotEdit('home')">
                    <i class="fas fa-edit"></i> Edit Shots
                </button>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Penalties</span>
            <button class="sb-ctrl-btn-primary home-accent" onclick="sbShowPenaltyModal('home')">
                <i class="fas fa-user-slash"></i> Add Home Penalty
            </button>
            <button class="sb-ctrl-btn-secondary" id="sbPenaltyDisplayToggle" onclick="sbTogglePenaltyDisplay()">
                <i class="fas fa-eye"></i> Penalties Shown on Board
            </button>

            <div class="sb-ctrl-penalty-list">
                <?php if (empty($home_penalties)): ?>
                <div class="sb-ctrl-penalty-empty">No home penalties</div>
                <?php else: ?>
                <?php $home_pen_idx = 0; foreach ($home_penalties as $pen):
                    $home_pen_idx++;
                    $pen_type = sbGetPenaltyType((int)($pen['duration_minutes'] ?? 2));
                    $is_queued = ($pen_type !== 'misconduct' && $pen_type !== 'game_misconduct' && $home_pen_idx > 2);
                ?>
                <div class="sb-ctrl-penalty-item <?= $is_queued ? 'sb-penalty-queued' : '' ?>"
                     data-team="home" data-penalty-id="<?= (int)($pen['id'] ?? 0) ?>"
                     data-penalty-type="<?= htmlspecialchars($pen_type) ?>"
                     data-duration="<?= (int)($pen['duration_minutes'] ?? 2) ?>">
                    <span>#<?= htmlspecialchars($pen['player_number'] ?? '?') ?> <?= htmlspecialchars($pen['player_name'] ?? '') ?></span>
                    <span class="sb-ctrl-penalty-info"><?= htmlspecialchars($pen['infraction'] ?? '') ?> (<?= htmlspecialchars($pen['duration_minutes'] ?? '2') ?>min<?= ($pen_type === 'major') ? ' MAJ' : '' ?><?= $is_queued ? ' QUEUED' : '' ?>)</span>
                    <span class="sb-ctrl-penalty-actions">
                        <button class="sb-ctrl-penalty-vis-btn" data-penalty-id="<?= (int)($pen['id'] ?? 0) ?>" onclick="sbTogglePenaltyItemVisibility(<?= (int)($pen['id'] ?? 0) ?>)" title="Toggle board visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="sb-ctrl-penalty-clear-btn" onclick="sbClearPenalty(<?= (int)($pen['id'] ?? 0) ?>)" title="Clear penalty">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Game Situation</span>
            <div class="sb-ctrl-btn-row">
                <button class="sb-ctrl-btn-secondary" onclick="sbToggleDelayedPenalty('home')">
                    <i class="fas fa-hand-paper"></i> Delayed Pen
                </button>
                <button class="sb-ctrl-btn-secondary" onclick="sbToggleEmptyNet('home')">
                    <i class="fas fa-door-open"></i> Empty Net
                </button>
            </div>
        </div>
        <div class="sb-ctrl-panel sb-panel-clock">
            <div class="sb-ctrl-panel-title"><i class="fas fa-clock"></i> Clock &amp; Period</div>

            <div class="sb-ctrl-btn-row">
                <button class="sb-ctrl-btn-primary" id="sbClockStart" onclick="sbClockToggle()">
                    <i class="fas fa-play"></i> Start
                </button>
                <button class="sb-ctrl-btn-secondary" onclick="sbClockReset()">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>

            <hr class="sb-ctrl-divider">

            <div class="sb-ctrl-config-group">
                <span class="sb-ctrl-section-label">Period Length</span>
                <div class="sb-ctrl-config-row">
                    <select id="sbPeriodTimeSelect" onchange="sbSetPeriodTime(this.value)">
                        <option value="1">1:00 (U5)</option>
                        <option value="2">2:00</option>
                        <option value="3">3:00</option>
                        <option value="5">5:00</option>
                        <option value="8">8:00</option>
                        <option value="10">10:00</option>
                        <option value="12">12:00</option>
                        <option value="15">15:00</option>
                        <option value="20" selected>20:00 (Default)</option>
                        <option value="25">25:00</option>
                    </select>
                </div>
                <div class="sb-ctrl-config-row">
                    <label for="sbPeriodCustomMin">Custom</label>
                    <input type="number" id="sbPeriodCustomMin" min="1" max="60" placeholder="min" onchange="sbApplyCustomPeriod(this.value)">
                </div>
            </div>

            <div class="sb-ctrl-config-group">
                <span class="sb-ctrl-section-label">OT Length</span>
                <div class="sb-ctrl-config-row">
                    <select id="sbOTTimeSelect" onchange="sbSetOvertimeTime(this.value)">
                        <option value="3">3:00</option>
                        <option value="5" selected>5:00 (Default)</option>
                        <option value="10">10:00</option>
                        <option value="20">20:00 (Playoff)</option>
                    </select>
                </div>
            </div>

            <div class="sb-ctrl-config-group">
                <span class="sb-ctrl-section-label">Clock Mode</span>
                <div class="sb-ctrl-config-row">
                    <select id="sbClockModeSelect" onchange="sbSetClockMode(this.value)">
                        <option value="stop_time">Stop Time (NHL)</option>
                        <option value="running_time">Running Time (Beer/Minor League)</option>
                    </select>
                </div>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Period Navigation</span>
            <div class="sb-ctrl-period-nav">
                <button class="sb-ctrl-btn-secondary" onclick="sbPeriodPrev()">
                    <i class="fas fa-chevron-left"></i> Prev Period
                </button>
                <button class="sb-ctrl-btn-secondary" onclick="sbPeriodNext()">
                    <i class="fas fa-chevron-right"></i> Next Period
                </button>
                <button class="sb-ctrl-btn-secondary sb-ctrl-intermission-btn" onclick="sbSetStatus('intermission')">
                    <i class="fas fa-pause-circle"></i> Intermission
                </button>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label"><i class="fas fa-bell"></i> Recurring Buzzer</span>
            <div class="sb-ctrl-config-row">
                <select id="sbRecurringSelect" onchange="sbSetRecurringBuzzer(this.value)">
                    <option value="0">Off</option>
                    <option value="60">1:00</option>
                    <option value="90">1:30 (U7)</option>
                    <option value="120">2:00 (U7/U9)</option>
                    <option value="150">2:30</option>
                    <option value="180">3:00</option>
                    <option value="240">4:00</option>
                    <option value="300">5:00</option>
                </select>
                <button class="sb-ctrl-btn-secondary" id="sbRecurringToggle" onclick="sbToggleRecurringBuzzer()" style="flex:0 0 auto;min-width:100px;">
                    <i class="fas fa-play"></i> Resume
                </button>
            </div>
            <div class="sb-ctrl-recurring-info">
                <span id="sbRecurringStatus">Off</span>
                <span id="sbRecurringCountdown" style="display:none;">--:--</span>
            </div>

            <hr class="sb-ctrl-divider">

            <button class="sb-ctrl-btn-primary buzzer-btn" id="sbBuzzerBtn" onclick="sbBuzzer()">
                <i class="fas fa-bell"></i> BUZZER
            </button>
            <button class="sb-ctrl-btn-primary horn-btn" id="sbHornBtn" onclick="sbGoalHorn()">
                <i class="fas fa-bullhorn"></i> GOAL HORN
            </button>
        </div>

        <!-- ════════ COLUMN 3: MUSIC & AUDIO ════════ -->
        <div class="sb-ctrl-panel">
            <div class="sb-ctrl-panel-title"><i class="fas fa-music"></i> Music &amp; Audio</div>

            <button class="sb-ctrl-btn-secondary" onclick="sbShowAudioSettings()">
                <i class="fas fa-volume-up"></i> Audio Settings
            </button>
            <button class="sb-ctrl-btn-secondary" onclick="sbSpeakerSettings()">
                <i class="fas fa-broadcast-tower"></i> Wireless Speakers
            </button>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Music Library</span>
            <div class="sb-ctrl-music-library">
                <?php if ($spotify_configured): ?>
                <button class="sb-ctrl-btn-secondary" onclick="sbSpotifyConnect()" style="color:#1DB954;border-color:#1DB954;">
                    <i class="fab fa-spotify"></i> Spotify Connect
                </button>
                <?php endif; ?>
                <?php if ($apple_music_configured): ?>
                <button class="sb-ctrl-btn-secondary" onclick="sbAppleMusicConnect()" style="color:#FC3C44;border-color:#FC3C44;">
                    <i class="fab fa-apple"></i> Apple Music
                </button>
                <?php endif; ?>
                <?php if ($subsonic_configured): ?>
                <button class="sb-ctrl-btn-secondary" onclick="sbSubsonicBrowse()">
                    <i class="fas fa-server"></i> Subsonic Library
                </button>
                <?php endif; ?>
                <?php if (!$spotify_configured && !$subsonic_configured && !$apple_music_configured): ?>
                <div style="text-align:center;padding:12px;color:#555;font-size:12px;">No music sources configured<?php if ($isAdmin): ?> — <a href="?view=settings" style="color:#6B46C1;">Configure in Settings</a><?php endif; ?></div>
                <?php endif; ?>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Now Playing</span>
            <div class="sb-ctrl-now-playing" id="sbNowPlaying">
                <i class="fas fa-music"></i> <strong>No music playing</strong>
            </div>

            <hr class="sb-ctrl-divider">

            <button class="sb-ctrl-btn-primary announce-btn" id="sbAnnounceBtn" onclick="sbToggleAnnounce()">
                📢 Announce
            </button>
        </div>

        <!-- ════════ COLUMN 4: AWAY TEAM ════════ -->
        <div class="sb-ctrl-panel">
            <div class="sb-ctrl-panel-title">🏒 Away — <?= htmlspecialchars($active_game['away_team_name'] ?? 'Away') ?></div>

            <span class="sb-ctrl-section-label">Goals</span>
            <button class="sb-ctrl-btn-primary away-accent goal-btn" onclick="sbAddGoal('away')">
                <i class="fas fa-plus-circle"></i> +1 Away Goal
            </button>
            <div class="sb-ctrl-btn-row">
                <button class="sb-ctrl-btn-secondary" onclick="sbShowScoreEdit('away')">
                    <i class="fas fa-edit"></i> Edit Score
                </button>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Shots on Goal</span>
            <button class="sb-ctrl-btn-primary away-accent" onclick="sbAddShot('away')">
                <i class="fas fa-bullseye"></i> +1 Shot
            </button>
            <div class="sb-ctrl-btn-row">
                <button class="sb-ctrl-btn-secondary" onclick="sbShowShotEdit('away')">
                    <i class="fas fa-edit"></i> Edit Shots
                </button>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Penalties</span>
            <button class="sb-ctrl-btn-primary away-accent" onclick="sbShowPenaltyModal('away')">
                <i class="fas fa-user-slash"></i> Add Away Penalty
            </button>
            <button class="sb-ctrl-btn-secondary" id="sbAwayPenaltyDisplayToggle" onclick="sbTogglePenaltyDisplay()">
                <i class="fas fa-eye"></i> Penalties Shown on Board
            </button>

            <div class="sb-ctrl-penalty-list">
                <?php if (empty($away_penalties)): ?>
                <div class="sb-ctrl-penalty-empty">No away penalties</div>
                <?php else: ?>
                <?php $away_pen_idx = 0; foreach ($away_penalties as $pen):
                    $away_pen_idx++;
                    $pen_type = sbGetPenaltyType((int)($pen['duration_minutes'] ?? 2));
                    $is_queued = ($pen_type !== 'misconduct' && $pen_type !== 'game_misconduct' && $away_pen_idx > 2);
                ?>
                <div class="sb-ctrl-penalty-item <?= $is_queued ? 'sb-penalty-queued' : '' ?>"
                     data-team="away" data-penalty-id="<?= (int)($pen['id'] ?? 0) ?>"
                     data-penalty-type="<?= htmlspecialchars($pen_type) ?>"
                     data-duration="<?= (int)($pen['duration_minutes'] ?? 2) ?>">
                    <span>#<?= htmlspecialchars($pen['player_number'] ?? '?') ?> <?= htmlspecialchars($pen['player_name'] ?? '') ?></span>
                    <span class="sb-ctrl-penalty-info"><?= htmlspecialchars($pen['infraction'] ?? '') ?> (<?= htmlspecialchars($pen['duration_minutes'] ?? '2') ?>min<?= ($pen_type === 'major') ? ' MAJ' : '' ?><?= $is_queued ? ' QUEUED' : '' ?>)</span>
                    <span class="sb-ctrl-penalty-actions">
                        <button class="sb-ctrl-penalty-vis-btn" data-penalty-id="<?= (int)($pen['id'] ?? 0) ?>" onclick="sbTogglePenaltyItemVisibility(<?= (int)($pen['id'] ?? 0) ?>)" title="Toggle board visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="sb-ctrl-penalty-clear-btn" onclick="sbClearPenalty(<?= (int)($pen['id'] ?? 0) ?>)" title="Clear penalty">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr class="sb-ctrl-divider">

            <span class="sb-ctrl-section-label">Game Situation</span>
            <div class="sb-ctrl-btn-row">
                <button class="sb-ctrl-btn-secondary" onclick="sbToggleDelayedPenalty('away')">
                    <i class="fas fa-hand-paper"></i> Delayed Pen
                </button>
                <button class="sb-ctrl-btn-secondary" onclick="sbToggleEmptyNet('away')">
                    <i class="fas fa-door-open"></i> Empty Net
                </button>
            </div>
        </div>

</div><!-- /.sb-controls-grid -->
</div><!-- /.sb-main -->
<?php endif; ?>

<!-- Score Edit Modal -->
<div class="sb-modal-overlay" id="sb-score-edit-modal">
    <div class="sb-modal" style="max-width:380px;">
        <h2><i class="fas fa-hockey-puck"></i> Edit Score — <span id="sbScoreEditTeamLabel">Home</span></h2>
        <input type="hidden" id="sbScoreEditTeam" value="">
        <div style="display:flex;flex-direction:column;gap:12px;">
            <label style="font-size:13px;font-weight:600;color:#8B8BA3;">Set Score Directly</label>
            <input type="number" id="sbScoreEditValue" min="0" max="99" value="0"
                   style="width:100%;min-height:48px;padding:10px;font-size:24px;font-weight:700;text-align:center;color:#E2E8F0;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:8px;">
            <div style="display:flex;gap:8px;">
                <button class="sb-ctrl-btn-secondary" onclick="sbScoreEditAdjust(-1)" style="flex:1;">
                    <i class="fas fa-minus"></i> −1
                </button>
                <button class="sb-ctrl-btn-secondary" onclick="document.getElementById('sbScoreEditValue').value=0" style="flex:1;">
                    <i class="fas fa-undo"></i> Reset to 0
                </button>
            </div>
            <div class="sb-modal-actions" style="margin-top:8px;">
                <button type="button" class="sb-btn" onclick="document.getElementById('sb-score-edit-modal').classList.remove('active')">Cancel</button>
                <button type="button" class="sb-btn sb-btn-primary" onclick="sbApplyScoreEdit()"><i class="fas fa-check"></i> Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Shot Edit Modal -->
<div class="sb-modal-overlay" id="sb-shot-edit-modal">
    <div class="sb-modal" style="max-width:380px;">
        <h2><i class="fas fa-bullseye"></i> Edit Shots — <span id="sbShotEditTeamLabel">Home</span></h2>
        <input type="hidden" id="sbShotEditTeam" value="">
        <div style="display:flex;flex-direction:column;gap:12px;">
            <label style="font-size:13px;font-weight:600;color:#8B8BA3;">Set Shots Directly</label>
            <input type="number" id="sbShotEditValue" min="0" max="999" value="0"
                   style="width:100%;min-height:48px;padding:10px;font-size:24px;font-weight:700;text-align:center;color:#E2E8F0;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:8px;">
            <div style="display:flex;gap:8px;">
                <button class="sb-ctrl-btn-secondary" onclick="sbShotEditAdjust(-1)" style="flex:1;">
                    <i class="fas fa-minus"></i> −1
                </button>
                <button class="sb-ctrl-btn-secondary" onclick="document.getElementById('sbShotEditValue').value=0" style="flex:1;">
                    <i class="fas fa-undo"></i> Reset to 0
                </button>
            </div>
            <div class="sb-modal-actions" style="margin-top:8px;">
                <button type="button" class="sb-btn" onclick="document.getElementById('sb-shot-edit-modal').classList.remove('active')">Cancel</button>
                <button type="button" class="sb-btn sb-btn-primary" onclick="sbApplyShotEdit()"><i class="fas fa-check"></i> Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Audio Settings Modal -->
<div class="sb-modal-overlay" id="sb-audio-settings-modal">
    <div class="sb-modal" style="max-width:440px;">
        <h2><i class="fas fa-volume-up"></i> Audio Settings</h2>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:flex;flex-direction:column;gap:6px;">
                <label style="font-size:13px;font-weight:600;color:#8B8BA3;">Microphone Input</label>
                <select id="sbAudioMicSelect"
                        style="width:100%;min-height:44px;padding:8px 10px;font-size:14px;color:#E2E8F0;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:8px;">
                    <option value="">— Select Mic —</option>
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <label style="font-size:13px;font-weight:600;color:#8B8BA3;">Speaker Output</label>
                <select id="sbAudioSpeakerSelect"
                        style="width:100%;min-height:44px;padding:8px 10px;font-size:14px;color:#E2E8F0;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:8px;">
                    <option value="">— Select Speaker —</option>
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <label style="font-size:13px;font-weight:600;color:#8B8BA3;">Music Volume</label>
                <div style="display:flex;align-items:center;gap:12px;">
                    <input type="range" id="sbAudioMusicVolume" min="0" max="100" value="80"
                           style="flex:1;accent-color:#6B46C1;">
                    <span id="sbAudioVolumeLabel" style="font-size:14px;font-weight:700;color:#E2E8F0;min-width:40px;text-align:right;">80%</span>
                </div>
            </div>
            <div class="sb-modal-actions" style="margin-top:8px;">
                <button type="button" class="sb-btn" onclick="document.getElementById('sb-audio-settings-modal').classList.remove('active')">Cancel</button>
                <button type="button" class="sb-btn sb-btn-primary" onclick="sbApplyAudioSettings()"><i class="fas fa-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<!-- New Game Modal -->
<div class="sb-modal-overlay" id="sb-new-game-modal">
    <div class="sb-modal">
        <h2><i class="fas fa-hockey-puck"></i> New Game</h2>
        <form id="sbNewGameForm" onsubmit="return sbStartGame(event)">
            <label for="sb-home-team">Home Team</label>
            <input type="text" id="sb-home-team" name="home_team_name" placeholder="Home team name" required>

            <label for="sb-away-team">Away Team</label>
            <input type="text" id="sb-away-team" name="away_team_name" placeholder="Away team name" required>

            <label for="sb-home-team-id">Link to Team (Optional)</label>
            <select id="sb-home-team-id" name="home_team_id">
                <option value="">— Not linked —</option>
                <?php foreach ($teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['team_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="sb-away-team-id">Away Team Link (Optional)</label>
            <select id="sb-away-team-id" name="away_team_id">
                <option value="">— Not linked —</option>
                <?php foreach ($teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['team_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>
                <input type="checkbox" id="sb-is-aw-game" name="is_arctic_wolves_game" value="1">
                This is an Arctic Wolves game (auto-sync stats)
            </label>

            <div class="sb-modal-actions">
                <button type="button" class="sb-btn" onclick="document.getElementById('sb-new-game-modal').classList.remove('active')">Cancel</button>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-play"></i> Start Game</button>
            </div>
        </form>
    </div>
</div>

<!-- Penalty Modal -->
<div class="sb-modal-overlay" id="sb-penalty-modal">
    <div class="sb-modal">
        <h2><i class="fas fa-gavel"></i> Add Penalty</h2>
        <form id="sbPenaltyForm" onsubmit="return sbAddPenalty(event)">
            <input type="hidden" id="sb-penalty-team" name="team">
            <label for="sb-pen-player">Player Number</label>
            <input type="text" id="sb-pen-player" name="player_number" placeholder="#" maxlength="3">
            <label for="sb-pen-name">Player Name</label>
            <input type="text" id="sb-pen-name" name="player_name" placeholder="Player name">
            <label for="sb-pen-infraction">Infraction</label>
            <select id="sb-pen-infraction" name="infraction">
                <option value="Tripping">Tripping</option>
                <option value="Hooking">Hooking</option>
                <option value="Slashing">Slashing</option>
                <option value="Interference">Interference</option>
                <option value="Holding">Holding</option>
                <option value="High-Sticking">High-Sticking</option>
                <option value="Cross-Checking">Cross-Checking</option>
                <option value="Roughing">Roughing</option>
                <option value="Boarding">Boarding</option>
                <option value="Delay of Game">Delay of Game</option>
                <option value="Too Many Men">Too Many Men</option>
                <option value="Unsportsmanlike">Unsportsmanlike Conduct</option>
                <option value="Charging">Charging</option>
                <option value="Elbowing">Elbowing</option>
                <option value="Kneeing">Kneeing</option>
                <option value="Spearing">Spearing</option>
                <option value="Butt-Ending">Butt-Ending</option>
                <option value="Head Contact">Head Contact</option>
                <option value="Clipping">Clipping</option>
                <option value="Fighting">Fighting</option>
                <option value="Misconduct">Misconduct</option>
                <option value="Game Misconduct">Game Misconduct</option>
                <option value="Match Penalty">Match Penalty</option>
                <option value="Bench Minor">Bench Minor</option>
            </select>
            <label for="sb-pen-duration">Duration</label>
            <div style="display:flex;gap:8px;align-items:stretch;margin-bottom:12px;">
                <select id="sb-pen-duration" name="duration_minutes" onchange="sbPenDurationPresetChanged(this)" style="flex:1;margin-bottom:0;">
                    <option value="2">2 min (Minor – NHL)</option>
                    <option value="3">3 min (Minor – Beer/Minor League)</option>
                    <option value="4">4 min (Double Minor – NHL)</option>
                    <option value="5">5 min (Major)</option>
                    <option value="6">6 min (Double Minor – Beer League)</option>
                    <option value="7">7 min (Major – Beer League)</option>
                    <option value="10">10 min (Misconduct)</option>
                    <option value="custom">Custom&hellip;</option>
                </select>
                <input type="number" id="sb-pen-duration-custom" name="duration_minutes_custom"
                       min="1" max="60" placeholder="min" style="display:none;width:80px;margin-bottom:0;text-align:center;">
            </div>
            <label for="sb-pen-served-by">Served By (optional – for bench/goalie penalties)</label>
            <input type="text" id="sb-pen-served-by" name="served_by" placeholder="Player serving penalty">
            <div class="sb-modal-actions">
                <button type="button" class="sb-btn" onclick="document.getElementById('sb-penalty-modal').classList.remove('active')">Cancel</button>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-gavel"></i> Add Penalty</button>
            </div>
        </form>
    </div>
</div>

<script>
function sbShowScoreEdit(team) {
    var label = team === 'home' ? 'Home' : 'Away';
    document.getElementById('sbScoreEditTeamLabel').textContent = label;
    document.getElementById('sbScoreEditTeam').value = team;
    var currentScore = parseInt(document.getElementById(team === 'home' ? 'sbHomeScore' : 'sbAwayScore').textContent) || 0;
    document.getElementById('sbScoreEditValue').value = currentScore;
    document.getElementById('sb-score-edit-modal').classList.add('active');
}

function sbScoreEditAdjust(delta) {
    var input = document.getElementById('sbScoreEditValue');
    var val = parseInt(input.value) || 0;
    val = Math.max(0, val + delta);
    input.value = val;
}

function sbApplyScoreEdit() {
    var team = document.getElementById('sbScoreEditTeam').value;
    var newScore = parseInt(document.getElementById('sbScoreEditValue').value) || 0;
    if (newScore < 0) newScore = 0;
    if (typeof sbSetScore === 'function') {
        sbSetScore(team, newScore);
    }
    document.getElementById('sb-score-edit-modal').classList.remove('active');
}

function sbShowShotEdit(team) {
    var label = team === 'home' ? 'Home' : 'Away';
    document.getElementById('sbShotEditTeamLabel').textContent = label;
    document.getElementById('sbShotEditTeam').value = team;
    var currentShots = parseInt(document.getElementById(team === 'home' ? 'sbHomeShots' : 'sbAwayShots').textContent) || 0;
    document.getElementById('sbShotEditValue').value = currentShots;
    document.getElementById('sb-shot-edit-modal').classList.add('active');
}

function sbShotEditAdjust(delta) {
    var input = document.getElementById('sbShotEditValue');
    var val = parseInt(input.value) || 0;
    val = Math.max(0, val + delta);
    input.value = val;
}

function sbApplyShotEdit() {
    var team = document.getElementById('sbShotEditTeam').value;
    var newShots = parseInt(document.getElementById('sbShotEditValue').value) || 0;
    if (newShots < 0) newShots = 0;
    if (typeof sbSetShots === 'function') {
        sbSetShots(team, newShots);
    }
    document.getElementById('sb-shot-edit-modal').classList.remove('active');
}

function sbShowAudioSettings() {
    document.getElementById('sb-audio-settings-modal').classList.add('active');
    if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
        navigator.mediaDevices.enumerateDevices().then(function(devices) {
            var micSelect = document.getElementById('sbAudioMicSelect');
            var spkSelect = document.getElementById('sbAudioSpeakerSelect');
            micSelect.innerHTML = '<option value="">— Select Mic —</option>';
            spkSelect.innerHTML = '<option value="">— Select Speaker —</option>';
            var micCount = 0;
            var spkCount = 0;
            devices.forEach(function(device) {
                if (device.kind === 'audioinput') {
                    micCount++;
                    var opt = document.createElement('option');
                    opt.value = device.deviceId;
                    opt.textContent = device.label || ('Mic ' + micCount);
                    micSelect.appendChild(opt);
                }
                if (device.kind === 'audiooutput') {
                    spkCount++;
                    var opt = document.createElement('option');
                    opt.value = device.deviceId;
                    opt.textContent = device.label || ('Speaker ' + spkCount);
                    spkSelect.appendChild(opt);
                }
            });
        });
    }
    var volumeSlider = document.getElementById('sbAudioMusicVolume');
    var volumeLabel = document.getElementById('sbAudioVolumeLabel');
    volumeSlider.oninput = function() {
        volumeLabel.textContent = this.value + '%';
    };
}

function sbApplyAudioSettings() {
    var mic = document.getElementById('sbAudioMicSelect').value;
    var speaker = document.getElementById('sbAudioSpeakerSelect').value;
    var volume = document.getElementById('sbAudioMusicVolume').value;
    if (typeof sbSetAudioConfig === 'function') {
        sbSetAudioConfig({ mic: mic, speaker: speaker, volume: volume });
    }
    document.getElementById('sb-audio-settings-modal').classList.remove('active');
}

function sbApplyCustomPeriod(value) {
    var mins = parseInt(value, 10);
    if (!isNaN(mins) && mins >= 1 && mins <= 60) {
        sbSetPeriodTime(mins);
    }
}

function sbToggleAnnounce() {
    var btn = document.getElementById('sbAnnounceBtn');
    if (typeof sbToggleMic === 'function') {
        sbToggleMic();
    }
    // Update button state after mic toggle attempt
    var micActive = window._sbMicStream ? true : false;
    btn.classList.toggle('active', micActive);
    btn.innerHTML = micActive ? '🔴 LIVE — Tap to End' : '📢 Announce';
}

// ── Custom penalty duration toggle ────────────────────────
function sbPenDurationPresetChanged(select) {
    var customInput = document.getElementById('sb-pen-duration-custom');
    if (select.value === 'custom') {
        customInput.style.display = '';
        customInput.focus();
    } else {
        customInput.style.display = 'none';
        customInput.value = '';
    }
}

// ── Delayed penalty indicator toggle ──────────────────────
function sbToggleDelayedPenalty(team) {
    var el = document.getElementById('sbDelayed' + (team === 'home' ? 'Home' : 'Away'));
    if (!el) return;
    var isActive = el.style.display !== 'none';
    el.style.display = isActive ? 'none' : 'inline-flex';
}

// ── Empty net indicator toggle ────────────────────────────
function sbToggleEmptyNet(team) {
    var el = document.getElementById('sbEmptyNet' + (team === 'home' ? 'Home' : 'Away'));
    if (!el) return;
    var isActive = el.style.display !== 'none';
    el.style.display = isActive ? 'none' : 'inline-flex';
}
</script>
