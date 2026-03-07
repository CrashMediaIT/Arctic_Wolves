<?php
/**
 * Scoreboard Display View
 * Primary arena display with score, clock, controls, penalties, and music.
 */
?>
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
<!-- Active Scoreboard -->
<div class="sb-main">
    <!-- Score Display -->
    <div class="sb-score-area">
        <div class="sb-team home">
            <div class="sb-team-name"><?= htmlspecialchars($active_game['home_team_name'] ?? 'Home') ?></div>
            <div class="sb-team-score" id="sbHomeScore"><?= $home_score ?></div>
            <div class="sb-team-shots">SOG: <span id="sbHomeShots"><?= $home_shots ?></span></div>
        </div>

        <div class="sb-center-display">
            <div class="sb-period" id="sbPeriod">Period <?= htmlspecialchars($active_game['current_period'] ?? '1') ?></div>
            <div class="sb-game-clock" id="sbGameClock">20:00</div>
            <div class="sb-game-status <?= ($active_game['status'] === 'intermission') ? 'intermission' : '' ?>" id="sbStatus">
                <?= htmlspecialchars(ucfirst($active_game['status'] ?? 'warmup')) ?>
            </div>
        </div>

        <div class="sb-team away">
            <div class="sb-team-name"><?= htmlspecialchars($active_game['away_team_name'] ?? 'Away') ?></div>
            <div class="sb-team-score" id="sbAwayScore"><?= $away_score ?></div>
            <div class="sb-team-shots">SOG: <span id="sbAwayShots"><?= $away_shots ?></span></div>
        </div>
    </div>

    <!-- Control Panels -->
    <div class="sb-controls">
        <!-- Goals & Shots Panel -->
        <div class="sb-panel">
            <div class="sb-panel-title"><i class="fas fa-hockey-puck"></i> Goals &amp; Shots</div>
            <div class="sb-action-grid">
                <button class="sb-action-btn home-btn" onclick="sbAddGoal('home')"><i class="fas fa-plus-circle"></i> Home Goal</button>
                <button class="sb-action-btn away-btn" onclick="sbAddGoal('away')"><i class="fas fa-plus-circle"></i> Away Goal</button>
                <button class="sb-action-btn home-btn" onclick="sbAddShot('home')"><i class="fas fa-bullseye"></i> Home Shot</button>
                <button class="sb-action-btn away-btn" onclick="sbAddShot('away')"><i class="fas fa-bullseye"></i> Away Shot</button>
                <button class="sb-action-btn home-btn" onclick="sbUndoGoal('home')"><i class="fas fa-minus-circle"></i> Undo Home Goal</button>
                <button class="sb-action-btn away-btn" onclick="sbUndoGoal('away')"><i class="fas fa-minus-circle"></i> Undo Away Goal</button>
                <button class="sb-buzzer-btn" id="sbBuzzerBtn" onclick="sbBuzzer()"><i class="fas fa-bullhorn"></i> BUZZER / HORN</button>
            </div>
        </div>

        <!-- Penalties Panel -->
        <div class="sb-panel">
            <div class="sb-panel-title"><i class="fas fa-gavel"></i> Penalties</div>
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
