<?php
/**
 * PWA Athletes - Mobile-native athletes list with create/deactivate
 * Purpose-built for mobile phones.
 * Includes filter bar, stats summary, detailed athlete info, and action buttons.
 */

// Permission check — match desktop views/athletes.php
if (!in_array($user_role, ['coach', 'coach_plus', 'admin'])) {
    echo '<div style="text-align:center;padding:60px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.5;"></i>';
    echo '<h3 style="color:#fff;">Access Denied</h3>';
    echo '<p style="font-size:14px;">Coach or admin access required.</p>';
    echo '</div>';
    return;
}

$is_coach = in_array(($user_role ?? ''), ['coach', 'coach_plus', 'admin']);

/**
 * Format athlete position to proper title case
 */
function mPwaFormatPosition($position) {
    $position_map = [
        'forward' => 'Forward',
        'defense' => 'Defense',
        'goalie'  => 'Goalie',
    ];
    $lower = strtolower($position ?? '');
    if (isset($position_map[$lower])) {
        return $position_map[$lower];
    }
    return htmlspecialchars(ucfirst($position));
}

// Get filter parameters
$filter_team = $_GET['filter_team'] ?? '';
$filter_age_group = $_GET['filter_age_group'] ?? '';
$filter_name = $_GET['filter_name'] ?? '';

// Build enhanced query with team names, note counts, sessions attended
$athletes = [];
try {
    $query = "
        SELECT u.id, u.first_name, u.last_name, u.role, u.is_active,
               u.birth_date, u.position, u.height, u.weight, u.shooting_hand,
               (SELECT COUNT(*) FROM athlete_notes WHERE user_id = u.id) AS note_count,
               (SELECT COUNT(*) FROM bookings b INNER JOIN sessions s ON b.session_id = s.id
                WHERE b.user_id = u.id AND b.status IN ('confirmed', 'waitlisted')
                AND s.session_date <= CURDATE()) AS sessions_attended,
               (SELECT GROUP_CONCAT(t.name SEPARATOR ', ')
                FROM athlete_teams at2 INNER JOIN teams t ON at2.team_id = t.id
                WHERE at2.athlete_id = u.id AND at2.status = 'active') AS team_names
        FROM users u
        WHERE u.role = 'athlete'
    ";
    $params = [];

    if (!empty($filter_team)) {
        $query .= " AND EXISTS (SELECT 1 FROM athlete_teams at WHERE at.athlete_id = u.id AND at.team_id = ? AND at.status = 'active')";
        $params[] = $filter_team;
    }
    if (!empty($filter_age_group)) {
        $query .= " AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) BETWEEN
                     (SELECT min_age FROM age_groups WHERE id = ?) AND
                     (SELECT max_age FROM age_groups WHERE id = ?)";
        $params[] = $filter_age_group;
        $params[] = $filter_age_group;
    }
    if (!empty($filter_name)) {
        if (class_exists('FieldEncryption') && FieldEncryption::isConfigured()) {
            $query .= " AND u.email LIKE ?";
            $params[] = '%' . $filter_name . '%';
        } else {
            $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $search_term = '%' . $filter_name . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
    }

    $query .= " ORDER BY u.first_name LIMIT 50";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
} catch (PDOException $e) { $athletes = []; }

// Fetch teams and age groups for filter dropdowns
$teams = [];
$age_groups = [];
try {
    $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $age_groups = $pdo->query("SELECT id, name FROM age_groups ORDER BY min_age")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* dropdowns will be empty */ }

$totalAthletes = count($athletes);
$totalSessions = array_sum(array_column($athletes, 'sessions_attended'));
$totalNotes = array_sum(array_column($athletes, 'note_count'));
$hasActiveFilters = !empty($filter_team) || !empty($filter_age_group) || !empty($filter_name);
?>
<style>
.m-athletes { padding: 16px; padding-bottom: 80px; font-family: Inter, sans-serif; }
.m-athletes-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
}
.m-athletes-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-athletes-count { font-size: 12px; color: #A8A8B8; }

/* Filter bar - collapsible */
.m-filter-bar { background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px; margin-bottom: 12px; overflow: hidden; }
.m-filter-toggle {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 14px; cursor: pointer; min-height: 44px;
    background: none; border: none; width: 100%; color: #fff;
    font-family: Inter, sans-serif; font-size: 13px; font-weight: 600;
}
.m-filter-toggle-left { display: flex; align-items: center; gap: 8px; }
.m-filter-toggle i.fa-filter { color: #6B46C1; font-size: 13px; }
.m-filter-toggle .m-filter-chevron { color: #6B6B7B; font-size: 12px; transition: transform 0.2s; }
.m-filter-bar.m-expanded .m-filter-chevron { transform: rotate(180deg); }
.m-filter-badge {
    background: #6B46C1; color: #fff; font-size: 10px; font-weight: 700;
    border-radius: 10px; padding: 2px 7px; margin-left: 6px;
}
.m-filter-body { display: none; padding: 0 14px 14px; }
.m-filter-bar.m-expanded .m-filter-body { display: block; }
.m-filter-field { margin-bottom: 10px; }
.m-filter-field label { font-size: 11px; font-weight: 600; color: #A8A8B8; text-transform: uppercase; display: block; margin-bottom: 4px; }
.m-filter-field select, .m-filter-field input[type="text"] {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff;
    padding: 10px 12px; min-height: 44px; width: 100%; box-sizing: border-box;
    font-family: Inter, sans-serif; font-size: 13px;
}
.m-filter-actions { display: flex; gap: 8px; margin-top: 4px; }
.m-filter-apply, .m-filter-clear {
    flex: 1; min-height: 40px; border-radius: 10px; border: none;
    font-family: Inter, sans-serif; font-size: 13px; font-weight: 600; cursor: pointer;
}
.m-filter-apply { background: #6B46C1; color: #fff; }
.m-filter-clear { background: transparent; border: 1px solid #2D2D3F; color: #A8A8B8; text-decoration: none; display: flex; align-items: center; justify-content: center; }

/* Stats summary - compact horizontal row */
.m-stats-row { display: flex; gap: 8px; margin-bottom: 12px; }
.m-stat-card {
    flex: 1; background: linear-gradient(135deg, #6B46C1, #4a0070); border-radius: 10px;
    padding: 10px 8px; text-align: center; color: #fff;
}
.m-stat-value { font-size: 20px; font-weight: 900; display: block; letter-spacing: -0.5px; }
.m-stat-label { font-size: 9px; font-weight: 700; text-transform: uppercase; opacity: 0.85; letter-spacing: 0.3px; }

/* Search */
.m-search-wrap { position: relative; margin-bottom: 12px; }
.m-search-input {
    width: 100%; padding: 12px 12px 12px 40px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px; outline: none;
}
.m-search-input::placeholder { color: #6B6B7B; }
.m-search-input:focus { border-color: #6B46C1; }
.m-search-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #6B6B7B; font-size: 14px; pointer-events: none;
}

/* Athlete cards - expandable */
.m-athletes-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-athletes-card-top {
    display: flex; align-items: center; gap: 12px; text-decoration: none;
}
.m-athletes-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.m-athletes-info { flex: 1; min-width: 0; }
.m-athletes-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-athletes-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-athletes-meta i { color: #6B46C1; width: 14px; text-align: center; margin-right: 3px; font-size: 11px; }
.m-athletes-expand-icon { color: #6B6B7B; font-size: 12px; flex-shrink: 0; transition: transform 0.2s; padding: 4px; }
.m-athletes-card.m-card-expanded .m-athletes-expand-icon { transform: rotate(180deg); }

/* Expanded detail section */
.m-athletes-detail { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-athletes-card.m-card-expanded .m-athletes-detail { display: block; }
.m-detail-row { font-size: 12px; color: #A8A8B8; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
.m-detail-row i { color: #6B46C1; width: 14px; text-align: center; font-size: 11px; }

/* Per-card mini stats */
.m-card-stats { display: flex; gap: 6px; margin: 10px 0; }
.m-card-stat {
    flex: 1; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    padding: 8px 4px; text-align: center;
}
.m-card-stat-val { font-size: 16px; font-weight: 900; color: #6B46C1; display: block; }
.m-card-stat-lbl { font-size: 9px; color: #A8A8B8; text-transform: uppercase; font-weight: 700; }

/* Action buttons per athlete */
.m-card-actions-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-top: 8px; }
.m-card-act-btn {
    display: flex; align-items: center; justify-content: center; gap: 4px;
    font-size: 11px; font-weight: 600; padding: 8px 4px; border-radius: 8px;
    text-decoration: none; font-family: Inter, sans-serif; min-height: 36px;
    border: none; cursor: pointer;
}
.m-card-act-primary { background: #6B46C1; color: #fff; }
.m-card-act-secondary { background: transparent; border: 1px solid #2D2D3F; color: #e0e0e0; }
.m-card-act-deactivate { background: rgba(239,68,68,0.15); color: #EF4444; }

.m-athletes-card-actions { display: flex; gap: 8px; flex-shrink: 0; }
.m-ath-action-btn {
    font-size: 11px; padding: 5px 10px; border-radius: 8px; border: none; cursor: pointer;
    font-weight: 600; font-family: Inter, sans-serif;
}
.m-ath-btn-deactivate { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-no-results {
    text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px;
    display: none;
}
.m-no-results i { font-size: 24px; display: block; margin-bottom: 8px; }
.m-ath-fab {
    position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px;
    background: #6B46C1; color: #fff; border: none; border-radius: 50%;
    font-size: 24px; cursor: pointer; z-index: 999;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    display: flex; align-items: center; justify-content: center;
}
.m-ath-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none;
}
.m-ath-overlay.active { display: block; }
.m-ath-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1001;
    background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh;
    overflow-y: auto; transform: translateY(100%); transition: transform 0.3s ease;
    padding: 20px 16px 32px;
}
.m-ath-sheet.active { transform: translateY(0); }
.m-ath-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-ath-field label {
    font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block;
}
.m-ath-field { margin-bottom: 14px; }
.m-ath-field input, .m-ath-field select {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff;
    padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box;
    font-family: Inter, sans-serif; font-size: 14px;
}
.m-ath-submit {
    background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px;
    font-weight: 600; width: 100%; border: none; cursor: pointer;
    font-family: Inter, sans-serif; font-size: 15px; margin-top: 8px;
}
.m-ath-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-ath-inactive { opacity: 0.5; }
</style>

<div class="m-athletes">
    <div class="m-athletes-header">
        <h2 class="m-athletes-title">Athletes</h2>
        <span class="m-athletes-count"><?= (int)$totalAthletes ?> total</span>
    </div>

    <!-- Collapsible Filter Bar -->
    <div class="m-filter-bar <?= $hasActiveFilters ? 'm-expanded' : '' ?>" id="mFilterBar">
        <button type="button" class="m-filter-toggle" id="mFilterToggle">
            <span class="m-filter-toggle-left">
                <i class="fas fa-filter"></i> Filters
                <?php if ($hasActiveFilters): ?><span class="m-filter-badge">ON</span><?php endif; ?>
            </span>
            <i class="fas fa-chevron-down m-filter-chevron"></i>
        </button>
        <div class="m-filter-body">
            <form method="GET" action="pwa.php" id="mFilterForm">
                <input type="hidden" name="page" value="athletes">
                <div class="m-filter-field">
                    <label>Team</label>
                    <select name="filter_team">
                        <option value="">All Teams</option>
                        <?php foreach ($teams as $team): ?>
                        <option value="<?= (int)$team['id'] ?>" <?= $filter_team == $team['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($team['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="m-filter-field">
                    <label>Age Group</label>
                    <select name="filter_age_group">
                        <option value="">All Ages</option>
                        <?php foreach ($age_groups as $ag): ?>
                        <option value="<?= (int)$ag['id'] ?>" <?= $filter_age_group == $ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="m-filter-field">
                    <label>Name / Email</label>
                    <input type="text" name="filter_name" placeholder="Search by name or email"
                           value="<?= htmlspecialchars($filter_name) ?>">
                </div>
                <div class="m-filter-actions">
                    <button type="submit" class="m-filter-apply"><i class="fas fa-search"></i> Apply</button>
                    <a href="?page=athletes" class="m-filter-clear"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="m-stats-row">
        <div class="m-stat-card">
            <span class="m-stat-value"><?= (int)$totalAthletes ?></span>
            <span class="m-stat-label">Athletes</span>
        </div>
        <div class="m-stat-card">
            <span class="m-stat-value"><?= (int)$totalSessions ?></span>
            <span class="m-stat-label">Sessions</span>
        </div>
        <div class="m-stat-card">
            <span class="m-stat-value"><?= (int)$totalNotes ?></span>
            <span class="m-stat-label">Notes</span>
        </div>
    </div>

    <!-- Quick client-side search -->
    <div class="m-search-wrap">
        <i class="fas fa-search m-search-icon"></i>
        <input type="text" class="m-search-input" id="m-athletes-search" placeholder="Search athletes..." autocomplete="off">
    </div>

    <div id="m-athletes-list">
        <?php if (empty($athletes)): ?>
            <div style="text-align:center;padding:32px;color:#6B6B7B;">
                <i class="fas fa-users-slash" style="font-size:28px;display:block;margin-bottom:10px;"></i>
                <p style="font-size:13px;">No athletes found</p>
            </div>
        <?php else: ?>
            <?php foreach ($athletes as $a):
                $initial = strtoupper(mb_substr($a['first_name'], 0, 1) . mb_substr($a['last_name'], 0, 1));
                $fullName = htmlspecialchars($a['first_name'] . ' ' . $a['last_name']);
                $isInactive = empty($a['is_active']);
                $position = !empty($a['position']) ? mPwaFormatPosition($a['position']) : '';
                $age = '';
                if (!empty($a['birth_date'])) {
                    $bd = date_create($a['birth_date']);
                    if ($bd !== false) {
                        $age = (string)date_diff($bd, date_create('today'))->y;
                    }
                }
                $rawTeams = $a['team_names'] ?? '';
                $teamNames = htmlspecialchars($rawTeams);
                // Truncate long team lists for the compact meta line
                $metaTeam = mb_strlen($rawTeams) > 30 ? htmlspecialchars(mb_substr($rawTeams, 0, 27)) . '&hellip;' : $teamNames;
                $height = htmlspecialchars($a['height'] ?? '');
                $weight = htmlspecialchars($a['weight'] ?? '');
                $shootingHand = htmlspecialchars(ucfirst($a['shooting_hand'] ?? ''));
                $sessionsAttended = (int)($a['sessions_attended'] ?? 0);
                $noteCount = (int)($a['note_count'] ?? 0);
                // Build compact meta line
                $metaParts = [];
                if ($position) $metaParts[] = $position;
                if ($age) $metaParts[] = $age . 'y';
                if ($metaTeam) $metaParts[] = $metaTeam;
                $metaLine = $isInactive ? 'Inactive' : implode(' · ', $metaParts);
                if (!$metaLine) $metaLine = 'Athlete';
            ?>
            <div class="m-athletes-card <?= $isInactive ? 'm-ath-inactive' : '' ?>" data-name="<?= strtolower($fullName) ?>" data-id="<?= (int)$a['id'] ?>">
                <div class="m-athletes-card-top">
                    <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" style="display:flex;align-items:center;gap:12px;flex:1;text-decoration:none;min-width:0;">
                        <div class="m-athletes-avatar"><?= $initial ?></div>
                        <div class="m-athletes-info">
                            <div class="m-athletes-name"><?= $fullName ?></div>
                            <div class="m-athletes-meta"><?= $metaLine ?></div>
                        </div>
                    </a>
                    <i class="fas fa-chevron-down m-athletes-expand-icon" data-expand-toggle></i>
                </div>
                <!-- Expandable detail section -->
                <div class="m-athletes-detail">
                    <?php if ($position): ?>
                    <div class="m-detail-row"><i class="fas fa-hockey-puck"></i> <?= $position ?></div>
                    <?php endif; ?>
                    <?php if ($age): ?>
                    <div class="m-detail-row"><i class="fas fa-birthday-cake"></i> <?= $age ?> years old</div>
                    <?php endif; ?>
                    <?php if ($height || $weight): ?>
                    <div class="m-detail-row">
                        <i class="fas fa-ruler-vertical"></i>
                        <?php if ($height) echo $height . 'cm'; ?>
                        <?php if ($height && $weight) echo ' &middot; '; ?>
                        <?php if ($weight) echo $weight . 'lbs'; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($shootingHand): ?>
                    <div class="m-detail-row"><i class="fas fa-hand-point-right"></i> Shoots: <?= $shootingHand ?></div>
                    <?php endif; ?>
                    <?php if ($teamNames): ?>
                    <div class="m-detail-row"><i class="fas fa-users"></i> <?= $teamNames ?></div>
                    <?php endif; ?>

                    <div class="m-card-stats">
                        <div class="m-card-stat">
                            <span class="m-card-stat-val"><?= $sessionsAttended ?></span>
                            <span class="m-card-stat-lbl">Sessions</span>
                        </div>
                        <div class="m-card-stat">
                            <span class="m-card-stat-val"><?= $noteCount ?></span>
                            <span class="m-card-stat-lbl">Notes</span>
                        </div>
                    </div>

                    <div class="m-card-actions-grid">
                        <a href="?page=stats&athlete_id=<?= (int)$a['id'] ?>" class="m-card-act-btn m-card-act-primary"><i class="fas fa-chart-line"></i> Stats</a>
                        <a href="?page=workouts&athlete_id=<?= (int)$a['id'] ?>" class="m-card-act-btn m-card-act-secondary"><i class="fas fa-dumbbell"></i> Workouts</a>
                        <a href="?page=nutrition&athlete_id=<?= (int)$a['id'] ?>" class="m-card-act-btn m-card-act-secondary"><i class="fas fa-apple-whole"></i> Nutrition</a>
                        <a href="?page=athlete_notes&athlete_id=<?= (int)$a['id'] ?>" class="m-card-act-btn m-card-act-secondary"><i class="fas fa-sticky-note"></i> Notes</a>
                        <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" class="m-card-act-btn m-card-act-secondary"><i class="fas fa-user"></i> Profile</a>
                        <?php if ($is_coach && !$isInactive): ?>
                        <form method="POST" action="process_manage_athletes.php" data-confirm="Deactivate this athlete?" style="margin:0;">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="remove_athlete">
                            <input type="hidden" name="athlete_id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="m-card-act-btn m-card-act-deactivate" style="width:100%;"><i class="fas fa-user-slash"></i> Deactivate</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-no-results" id="m-athletes-noresults">
        <i class="fas fa-search"></i>
        No athletes match your search
    </div>
</div>

<?php if ($is_coach): ?>
<button type="button" class="m-ath-fab" id="mAthFab"><i class="fas fa-plus"></i></button>

<div class="m-ath-overlay" id="mAthOverlay"></div>
<div class="m-ath-sheet" id="mAthSheet">
    <h3 class="m-ath-sheet-title">Add Athlete</h3>
    <form method="POST" action="process_create_athlete.php">
        <?= csrfTokenInput() ?>
        <div class="m-ath-row">
            <div class="m-ath-field">
                <label>First Name *</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="m-ath-field">
                <label>Last Name *</label>
                <input type="text" name="last_name" required>
            </div>
        </div>
        <div class="m-ath-field">
            <label>Email *</label>
            <input type="email" name="email" required>
        </div>
        <div class="m-ath-row">
            <div class="m-ath-field">
                <label>Date of Birth</label>
                <input type="date" name="birth_date" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="m-ath-field">
                <label>Position</label>
                <select name="position">
                    <option value="">Select Position</option>
                    <option value="Forward">Forward</option>
                    <option value="Defense">Defense</option>
                    <option value="Goalie">Goalie</option>
                </select>
            </div>
        </div>
        <button type="submit" class="m-ath-submit">Add Athlete</button>
    </form>
</div>
<?php endif; ?>

<script>
(function() {
    /* Client-side search */
    var searchInput = document.getElementById('m-athletes-search');
    var cards = document.querySelectorAll('.m-athletes-card');
    var noResults = document.getElementById('m-athletes-noresults');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            var visible = 0;
            cards.forEach(function(card) {
                var name = card.getAttribute('data-name') || '';
                var show = !query || name.indexOf(query) !== -1;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (noResults) {
                noResults.style.display = (visible === 0 && query) ? 'block' : 'none';
            }
        });
    }

    /* Filter bar toggle */
    var filterToggle = document.getElementById('mFilterToggle');
    var filterBar = document.getElementById('mFilterBar');
    if (filterToggle && filterBar) {
        filterToggle.addEventListener('click', function() {
            filterBar.classList.toggle('m-expanded');
        });
    }

    /* Expandable athlete cards */
    document.querySelectorAll('[data-expand-toggle]').forEach(function(icon) {
        icon.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var card = this.closest('.m-athletes-card');
            if (card) card.classList.toggle('m-card-expanded');
        });
    });

    /* Create athlete sheet */
    var fab = document.getElementById('mAthFab');
    var overlay = document.getElementById('mAthOverlay');
    var sheet = document.getElementById('mAthSheet');
    function openCreate() {
        if (overlay) overlay.classList.add('active');
        if (sheet) sheet.classList.add('active');
    }
    function closeCreate() {
        if (overlay) overlay.classList.remove('active');
        if (sheet) sheet.classList.remove('active');
    }
    if (fab) fab.addEventListener('click', openCreate);
    if (overlay) overlay.addEventListener('click', closeCreate);
})();
</script>
