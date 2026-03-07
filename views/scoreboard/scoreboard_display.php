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
 */

// Separate home/away penalties for the penalty box display (most recent 2 per team)
$home_penalties = array_filter($game_penalties, function($p) { return ($p['team'] ?? '') === 'home'; });
$away_penalties = array_filter($game_penalties, function($p) { return ($p['team'] ?? '') === 'away'; });
$home_penalties = array_values($home_penalties);
$away_penalties = array_values($away_penalties);
// Most recent 2 for display
$home_pen_display = array_slice($home_penalties, -2);
$away_pen_display = array_slice($away_penalties, -2);
// Power play status
$home_active_pens = count($home_penalties);
$away_active_pens = count($away_penalties);
$home_pp = ($away_active_pens > 0 && $away_active_pens > $home_active_pens);
$away_pp = ($home_active_pens > 0 && $home_active_pens > $away_active_pens);
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
        <a href="?view=scoresheet" class="sb-btn"><i class="fas fa-clipboard-list"></i> Scoresheet</a>
        <a href="?view=video_board" class="sb-btn"><i class="fas fa-tv"></i> Video Board</a>
        <button class="sb-btn sb-btn-danger" onclick="sbEndGame()"><i class="fas fa-flag-checkered"></i> End Game</button>
        <?php else: ?>
        <button class="sb-btn sb-btn-primary" onclick="document.getElementById('sb-new-game-modal').classList.add('active')"><i class="fas fa-plus"></i> New Game</button>
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

    <!-- ── Operator Control Panels ─────────────────────── -->
    <div class="sb-controls">

        <!-- Clock Controls -->
        <div class="sb-panel sb-panel-clock">
            <div class="sb-panel-title"><i class="fas fa-clock"></i> Clock &amp; Period</div>
            <div class="sb-clock-controls">
                <button class="sb-clock-btn sb-clock-start" id="sbClockStart" onclick="sbClockToggle()"><i class="fas fa-play"></i> Start</button>
                <button class="sb-clock-btn sb-clock-reset" onclick="sbClockReset()"><i class="fas fa-redo"></i> Reset 20:00</button>
            </div>
            <div class="sb-period-time-config">
                <label class="sb-config-label">Period Length</label>
                <select id="sbPeriodTimeSelect" onchange="sbSetPeriodTime(this.value)" class="sb-config-select">
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
                <label class="sb-config-label">OT Length</label>
                <select id="sbOTTimeSelect" onchange="sbSetOvertimeTime(this.value)" class="sb-config-select">
                    <option value="3">3:00</option>
                    <option value="5" selected>5:00 (Default)</option>
                    <option value="10">10:00</option>
                </select>
            </div>
            <div class="sb-period-controls">
                <button class="sb-action-btn" onclick="sbPeriodPrev()"><i class="fas fa-chevron-left"></i> Prev Period</button>
                <button class="sb-action-btn" onclick="sbPeriodNext()"><i class="fas fa-chevron-right"></i> Next Period</button>
                <button class="sb-action-btn" onclick="sbSetStatus('intermission')" style="grid-column:span 2;"><i class="fas fa-pause-circle"></i> Intermission</button>
            </div>
            <div class="sb-recurring-buzzer">
                <div class="sb-panel-subtitle"><i class="fas fa-bell"></i> Recurring Buzzer</div>
                <div class="sb-recurring-controls">
                    <select id="sbRecurringSelect" onchange="sbSetRecurringBuzzer(this.value)" class="sb-config-select">
                        <option value="0">Off</option>
                        <option value="60">1:00</option>
                        <option value="90">1:30 (U7)</option>
                        <option value="120">2:00 (U7/U9)</option>
                        <option value="150">2:30</option>
                        <option value="180">3:00</option>
                        <option value="240">4:00</option>
                        <option value="300">5:00</option>
                    </select>
                    <button class="sb-action-btn sb-recurring-toggle" id="sbRecurringToggle" onclick="sbToggleRecurringBuzzer()"><i class="fas fa-play"></i> Resume</button>
                </div>
                <div class="sb-recurring-info">
                    <span class="sb-recurring-status" id="sbRecurringStatus">Off</span>
                    <span class="sb-recurring-countdown" id="sbRecurringCountdown" style="display:none;">--:--</span>
                </div>
            </div>
        </div>

        <!-- Goals & Shots Panel -->
        <div class="sb-panel">
            <div class="sb-panel-title"><i class="fas fa-hockey-puck"></i> Goals &amp; Shots</div>
            <div class="sb-action-grid">
                <button class="sb-action-btn home-btn" onclick="sbAddGoal('home')"><i class="fas fa-plus-circle"></i> Home Goal</button>
                <button class="sb-action-btn away-btn" onclick="sbAddGoal('away')"><i class="fas fa-plus-circle"></i> Away Goal</button>
                <button class="sb-action-btn home-btn" onclick="sbAddShot('home')"><i class="fas fa-bullseye"></i> Home Shot</button>
                <button class="sb-action-btn away-btn" onclick="sbAddShot('away')"><i class="fas fa-bullseye"></i> Away Shot</button>
                <button class="sb-action-btn home-btn" onclick="sbUndoGoal('home')"><i class="fas fa-minus-circle"></i> Undo Goal</button>
                <button class="sb-action-btn away-btn" onclick="sbUndoGoal('away')"><i class="fas fa-minus-circle"></i> Undo Goal</button>
                <button class="sb-buzzer-btn" id="sbBuzzerBtn" onclick="sbBuzzer()"><i class="fas fa-bullhorn"></i> BUZZER / HORN</button>
            </div>
        </div>

        <!-- Penalties Panel -->
        <div class="sb-panel">
            <div class="sb-panel-title"><i class="fas fa-gavel"></i> Penalties</div>
            <div class="sb-penalty-display-toggle">
                <button class="sb-action-btn sb-penalty-toggle-btn" id="sbPenaltyDisplayToggle" onclick="sbTogglePenaltyDisplay()">
                    <i class="fas fa-eye"></i> Penalties Shown on Board
                </button>
            </div>
            <div class="sb-action-grid" style="margin-bottom:12px;">
                <button class="sb-action-btn home-btn" onclick="sbShowPenaltyModal('home')"><i class="fas fa-user-slash"></i> Home Penalty</button>
                <button class="sb-action-btn away-btn" onclick="sbShowPenaltyModal('away')"><i class="fas fa-user-slash"></i> Away Penalty</button>
            </div>
            <div class="sb-penalty-list" id="sbPenaltyList">
                <?php if (empty($game_penalties)): ?>
                <div style="text-align:center;padding:16px;color:#555;font-size:12px;">No penalties</div>
                <?php else: ?>
                <?php foreach ($game_penalties as $pen): ?>
                <div class="sb-penalty-item">
                    <span class="sb-penalty-player">#<?= htmlspecialchars($pen['player_number'] ?? '?') ?> <?= htmlspecialchars($pen['player_name'] ?? '') ?></span>
                    <span class="sb-penalty-info"><?= htmlspecialchars($pen['infraction'] ?? '') ?> (<?= htmlspecialchars($pen['duration_minutes'] ?? '2') ?>min)</span>
                    <span class="sb-penalty-time">P<?= htmlspecialchars($pen['period'] ?? '') ?> <?= htmlspecialchars($pen['game_time'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Music & Audio Panel -->
        <div class="sb-panel">
            <div class="sb-panel-title"><i class="fas fa-music"></i> Music &amp; Audio</div>
            <div class="sb-music-controls">
                <?php if ($spotify_configured): ?>
                <button class="sb-music-btn spotify" onclick="sbSpotifyConnect()"><i class="fab fa-spotify"></i> Spotify Connect</button>
                <?php endif; ?>
                <?php if ($subsonic_configured): ?>
                <button class="sb-music-btn subsonic" onclick="sbSubsonicBrowse()"><i class="fas fa-server"></i> Subsonic Library</button>
                <?php endif; ?>
                <button class="sb-music-btn mic" onclick="sbToggleMic()"><i class="fas fa-microphone"></i> Arena Mic</button>
                <button class="sb-music-btn speaker" onclick="sbSpeakerSettings()"><i class="fas fa-volume-up"></i> Wireless Speakers</button>
                <div class="sb-music-now-playing" id="sbNowPlaying">
                    <i class="fas fa-music"></i> <strong>No music playing</strong>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
                <option value="Fighting">Fighting</option>
                <option value="Misconduct">Misconduct</option>
                <option value="Game Misconduct">Game Misconduct</option>
            </select>
            <label for="sb-pen-duration">Duration (minutes)</label>
            <select id="sb-pen-duration" name="duration_minutes">
                <option value="2">2 min (Minor)</option>
                <option value="4">4 min (Double Minor)</option>
                <option value="5">5 min (Major)</option>
                <option value="10">10 min (Misconduct)</option>
            </select>
            <div class="sb-modal-actions">
                <button type="button" class="sb-btn" onclick="document.getElementById('sb-penalty-modal').classList.remove('active')">Cancel</button>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-gavel"></i> Add Penalty</button>
            </div>
        </form>
    </div>
</div>
