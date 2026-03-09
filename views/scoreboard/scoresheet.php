<?php
/**
 * Scoresheet View
 * Full game scoresheet entry for official game records.
 * For Arctic Wolves games, scoresheet data syncs to Game Plan game results
 * and automatically updates player stats in athlete_stats.
 */
$game = $active_game;
$gid = $game ? (int)$game['id'] : 0;
$isAWGame = $game ? (bool)($game['is_arctic_wolves_game'] ?? false) : false;
?>
<div class="sb-scoresheet">
    <div class="sb-topbar">
        <div class="sb-topbar-brand">
            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Arctic Wolves">
            <span>Scoresheet</span>
        </div>
        <div class="sb-topbar-actions">
            <a href="?view=scoreboard" class="sb-btn"><i class="fas fa-tachometer-alt"></i> <span>Scoreboard</span></a>
            <a href="?view=video_board" class="sb-btn"><i class="fas fa-tv"></i> <span>Video Board</span></a>
            <?php if ($isAWGame): ?>
            <button class="sb-btn sb-btn-primary" onclick="sbSyncToGamePlan()"><i class="fas fa-sync"></i> <span>Sync to Game Plan</span></button>
            <?php endif; ?>
            <span class="sb-clock" id="sbClock"></span>
        </div>
    </div>

    <div class="sb-scoresheet-content">
        <?php if (!$game): ?>
        <div class="sb-no-game">
            <i class="fas fa-clipboard-list"></i>
            <h2>No Active Game</h2>
            <p>Start a game from the scoreboard to enter scoresheet data.</p>
            <a href="?view=scoreboard" class="sb-btn sb-btn-primary" style="font-size:16px;padding:12px 24px;text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Go to Scoreboard
            </a>
        </div>
        <?php else: ?>

        <!-- Game Info -->
        <div class="sb-ss-section">
            <h3><i class="fas fa-info-circle"></i> Game Information</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;font-size:13px;">
                <div><strong>Home:</strong> <?= htmlspecialchars($game['home_team_name'] ?? '') ?></div>
                <div><strong>Away:</strong> <?= htmlspecialchars($game['away_team_name'] ?? '') ?></div>
                <div><strong>Date:</strong> <?= htmlspecialchars(date('M j, Y', strtotime($game['created_at'] ?? 'now'))) ?></div>
                <div><strong>Score:</strong> <?= $home_score ?> – <?= $away_score ?></div>
                <div><strong>Period:</strong> <?= htmlspecialchars($game['current_period'] ?? '1') ?></div>
                <div><strong>Status:</strong> <?= htmlspecialchars(ucfirst($game['status'] ?? 'warmup')) ?></div>
            </div>
            <?php if ($isAWGame): ?>
            <div style="margin-top:8px;padding:6px 10px;background:rgba(107,70,193,0.1);border:1px solid rgba(107,70,193,0.2);border-radius:6px;font-size:12px;color:#B794F6;">
                <i class="fas fa-link"></i> Arctic Wolves game — stats will auto-sync to player profiles
            </div>
            <?php endif; ?>
        </div>

        <!-- Goals -->
        <div class="sb-ss-section">
            <h3><i class="fas fa-hockey-puck"></i> Goals</h3>
            <table class="sb-ss-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Time</th>
                        <th>Team</th>
                        <th>Scorer</th>
                        <th>Assist 1</th>
                        <th>Assist 2</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody id="sbGoalRows">
                    <?php foreach ($game_goals as $g): ?>
                    <tr>
                        <td><?= htmlspecialchars($g['period'] ?? '') ?></td>
                        <td><?= htmlspecialchars($g['game_time'] ?? '') ?></td>
                        <td><?= htmlspecialchars($g['team'] ?? '') ?></td>
                        <td>#<?= htmlspecialchars($g['scorer_number'] ?? '') ?> <?= htmlspecialchars($g['scorer_name'] ?? '') ?></td>
                        <td><?= !empty($g['assist1_name']) ? '#' . htmlspecialchars($g['assist1_number'] ?? '') . ' ' . htmlspecialchars($g['assist1_name']) : '—' ?></td>
                        <td><?= !empty($g['assist2_name']) ? '#' . htmlspecialchars($g['assist2_number'] ?? '') . ' ' . htmlspecialchars($g['assist2_name']) : '—' ?></td>
                        <td><?= htmlspecialchars($g['goal_type'] ?? 'Even Strength') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="sb-ss-add-btn" onclick="sbShowGoalDetailModal()"><i class="fas fa-plus"></i> Add Goal Detail</button>
        </div>

        <!-- Penalties -->
        <div class="sb-ss-section">
            <h3><i class="fas fa-gavel"></i> Penalties</h3>
            <table class="sb-ss-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Time</th>
                        <th>Team</th>
                        <th>Player</th>
                        <th>Infraction</th>
                        <th>Minutes</th>
                    </tr>
                </thead>
                <tbody id="sbPenaltyRows">
                    <?php foreach ($game_penalties as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['period'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['game_time'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['team'] ?? '') ?></td>
                        <td>#<?= htmlspecialchars($p['player_number'] ?? '') ?> <?= htmlspecialchars($p['player_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['infraction'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['duration_minutes'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Shots by Period -->
        <div class="sb-ss-section">
            <h3><i class="fas fa-bullseye"></i> Shots on Goal by Period</h3>
            <table class="sb-ss-table">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>1st</th>
                        <th>2nd</th>
                        <th>3rd</th>
                        <th>OT</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?= htmlspecialchars($game['home_team_name'] ?? 'Home') ?></strong></td>
                        <td id="sbHomeShotsP1">0</td>
                        <td id="sbHomeShotsP2">0</td>
                        <td id="sbHomeShotsP3">0</td>
                        <td id="sbHomeShotsOT">0</td>
                        <td><strong id="sbHomeShotsTotal"><?= $home_shots ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong><?= htmlspecialchars($game['away_team_name'] ?? 'Away') ?></strong></td>
                        <td id="sbAwayShotsP1">0</td>
                        <td id="sbAwayShotsP2">0</td>
                        <td id="sbAwayShotsP3">0</td>
                        <td id="sbAwayShotsOT">0</td>
                        <td><strong id="sbAwayShotsTotal"><?= $away_shots ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div>
</div>

<!-- Goal Detail Modal -->
<div class="sb-modal-overlay" id="sb-goal-detail-modal">
    <div class="sb-modal">
        <h2><i class="fas fa-hockey-puck"></i> Goal Details</h2>
        <form id="sbGoalDetailForm" onsubmit="return sbAddGoalDetail(event)">
            <label>Period</label>
            <select name="period">
                <option value="1">1st Period</option>
                <option value="2">2nd Period</option>
                <option value="3">3rd Period</option>
                <option value="OT">Overtime</option>
                <option value="SO">Shootout</option>
            </select>
            <label>Time</label>
            <input type="text" name="game_time" placeholder="12:34" maxlength="5">
            <label>Team</label>
            <select name="team">
                <option value="home"><?= htmlspecialchars($active_game['home_team_name'] ?? 'Home') ?></option>
                <option value="away"><?= htmlspecialchars($active_game['away_team_name'] ?? 'Away') ?></option>
            </select>
            <label>Scorer # / Name</label>
            <div style="display:grid;grid-template-columns:60px 1fr;gap:8px;">
                <input type="text" name="scorer_number" placeholder="#" maxlength="3">
                <input type="text" name="scorer_name" placeholder="Scorer name">
            </div>
            <label>Assist 1 # / Name</label>
            <div style="display:grid;grid-template-columns:60px 1fr;gap:8px;">
                <input type="text" name="assist1_number" placeholder="#" maxlength="3">
                <input type="text" name="assist1_name" placeholder="First assist">
            </div>
            <label>Assist 2 # / Name</label>
            <div style="display:grid;grid-template-columns:60px 1fr;gap:8px;">
                <input type="text" name="assist2_number" placeholder="#" maxlength="3">
                <input type="text" name="assist2_name" placeholder="Second assist">
            </div>
            <label>Goal Type</label>
            <select name="goal_type">
                <option value="Even Strength">Even Strength</option>
                <option value="Power Play">Power Play</option>
                <option value="Short Handed">Short Handed</option>
                <option value="Empty Net">Empty Net</option>
                <option value="Penalty Shot">Penalty Shot</option>
                <option value="Overtime">Overtime</option>
            </select>
            <div class="sb-modal-actions">
                <button type="button" class="sb-btn" onclick="document.getElementById('sb-goal-detail-modal').classList.remove('active')">Cancel</button>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-check"></i> Save Goal</button>
            </div>
        </form>
    </div>
</div>
