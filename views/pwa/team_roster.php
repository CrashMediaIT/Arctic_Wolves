<?php
/**
 * PWA Team Roster - Mobile-native team coach roster
 * Purpose-built for mobile phones.
 */

if (!$isTeamStaff) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">You do not have access to this roster.</p>';
    echo '</div>';
    return;
}

$athletes = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.position, tm.id as roster_id
        FROM users u
        INNER JOIN team_members tm ON tm.athlete_id = u.id
        WHERE tm.coach_id = ?
        ORDER BY u.first_name
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $athletes = []; }

$totalAthletes = count($athletes);

// Get available athletes for adding
$availableAthletes = [];
$isCoachOrAdmin = in_array($user_role ?? '', ['admin', 'superadmin', 'coach', 'team_coach']);
if ($isCoachOrAdmin) {
    try {
        $stmt2 = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'athlete' ORDER BY last_name, first_name");
        $availableAthletes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $availableAthletes = decryptUserRows($availableAthletes);
    } catch (PDOException $e) { $availableAthletes = []; }
}
?>
<style>
.m-teamroster { padding: 16px; font-family: Inter, sans-serif; }
.m-teamroster-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
}
.m-teamroster-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-teamroster-count { font-size: 12px; color: #A8A8B8; }
.m-teamroster-add-btn {
    min-width: 44px; min-height: 44px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none; font-size: 18px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.m-search-wrap { position: relative; margin-bottom: 16px; }
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
.m-teamroster-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px;
}
.m-teamroster-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.m-teamroster-info { flex: 1; min-width: 0; }
.m-teamroster-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-teamroster-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-teamroster-chevron { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-teamroster-remove-btn {
    min-width: 36px; min-height: 36px; border: none; border-radius: 8px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; background: none; color: #EF4444; flex-shrink: 0;
}
.m-no-results {
    text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px;
    display: none;
}
.m-no-results i { font-size: 24px; display: block; margin-bottom: 8px; }
.m-bs-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 999;
}
.m-bs-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;
    background: #16161F; border-radius: 16px 16px 0 0;
    padding: 20px 16px 32px; display: none;
    max-height: 85vh; overflow-y: auto;
}
.m-bs-handle { width: 40px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-bs-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 12px; }
.m-form-label { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; display: block; }
.m-form-input {
    width: 100%; min-height: 44px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box;
}
.m-form-input:focus { border-color: #8B5CF6; outline: none; }
.m-form-submit {
    width: 100%; min-height: 44px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif; margin-top: 8px;
}
.m-form-submit:disabled { opacity: 0.5; }
.m-alert {
    padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 10px;
    display: none; text-align: center;
}
.m-alert-success { background: rgba(16,185,129,0.15); color: #10B981; }
.m-alert-error { background: rgba(239,68,68,0.15); color: #EF4444; }
</style>

<div class="m-teamroster">
    <div class="m-teamroster-header">
        <div>
            <h2 class="m-teamroster-title">Team Roster</h2>
            <span class="m-teamroster-count"><?= $totalAthletes ?> total</span>
        </div>
        <?php if ($isCoachOrAdmin): ?>
        <button class="m-teamroster-add-btn" type="button" onclick="mRosterOpenAdd()" title="Add Player">
            <i class="fas fa-user-plus"></i>
        </button>
        <?php endif; ?>
    </div>

    <div id="mRosterAlert" class="m-alert"></div>

    <div class="m-search-wrap">
        <i class="fas fa-search m-search-icon"></i>
        <input type="text" class="m-search-input" id="m-teamroster-search" placeholder="Search athletes..." autocomplete="off">
    </div>

    <div id="m-teamroster-list">
        <?php if (empty($athletes)): ?>
            <div style="text-align:center;padding:32px;color:#6B6B7B;">
                <i class="fas fa-users-slash" style="font-size:28px;display:block;margin-bottom:10px;"></i>
                <p style="font-size:13px;">No athletes in your team</p>
            </div>
        <?php else: ?>
            <?php foreach ($athletes as $a):
                $initial = strtoupper(mb_substr($a['first_name'], 0, 1) . mb_substr($a['last_name'], 0, 1));
                $fullName = htmlspecialchars($a['first_name'] . ' ' . $a['last_name']);
            ?>
            <div class="m-teamroster-card" data-name="<?= strtolower($fullName) ?>" id="mRoster-<?= (int)($a['roster_id'] ?? $a['id']) ?>">
                <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" style="display:flex;align-items:center;gap:12px;flex:1;text-decoration:none;min-width:0;">
                    <div class="m-teamroster-avatar"><?= $initial ?></div>
                    <div class="m-teamroster-info">
                        <div class="m-teamroster-name"><?= $fullName ?></div>
                        <div class="m-teamroster-meta">
                            <?php if (!empty($a['position'])): ?>
                            <span><i class="fas fa-hockey-puck" style="font-size:10px;"></i> <?= htmlspecialchars(ucfirst($a['position'])) ?></span>
                            <?php else: ?>
                            <span>Athlete</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right m-teamroster-chevron"></i>
                </a>
                <?php if ($isCoachOrAdmin && !empty($a['roster_id'])): ?>
                <button class="m-teamroster-remove-btn" type="button" onclick="mRosterRemove(<?= (int)$a['roster_id'] ?>)" title="Remove">
                    <i class="fas fa-user-minus"></i>
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-no-results" id="m-teamroster-noresults">
        <i class="fas fa-search"></i>
        No athletes match your search
    </div>
</div>

<?php if ($isCoachOrAdmin): ?>
<div class="m-bs-overlay" id="mRosterOverlay" onclick="mRosterCloseAdd()"></div>
<div class="m-bs-sheet" id="mRosterSheet">
    <div class="m-bs-handle"></div>
    <h3 class="m-bs-title">Add Player</h3>
    <form method="POST" action="process_admin_team_coaches.php" id="mRosterForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="add_roster_athlete">
        <input type="hidden" name="redirect_page" value="team_roster">
        <div class="m-form-group">
            <label class="m-form-label">Athlete *</label>
            <select name="athlete_id" class="m-form-input" required>
                <option value="">Select Athlete</option>
                <?php foreach ($availableAthletes as $aa): ?>
                <option value="<?= $aa['id'] ?>"><?= htmlspecialchars($aa['first_name'] . ' ' . $aa['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div class="m-form-group">
                <label class="m-form-label">Jersey #</label>
                <input type="number" name="jersey_number" class="m-form-input" min="0" max="99" placeholder="Optional">
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Position</label>
                <select name="position" class="m-form-input">
                    <option value="">Select</option>
                    <option value="Forward">Forward</option>
                    <option value="Defense">Defense</option>
                    <option value="Goalie">Goalie</option>
                    <option value="Left Wing">Left Wing</option>
                    <option value="Center">Center</option>
                    <option value="Right Wing">Right Wing</option>
                </select>
            </div>
        </div>
        <button type="submit" class="m-form-submit">
            <i class="fas fa-user-plus"></i> Add to Roster
        </button>
    </form>
</div>
<?php endif; ?>

<script>
(function() {
    var searchInput = document.getElementById('m-teamroster-search');
    var cards = document.querySelectorAll('.m-teamroster-card');
    var noResults = document.getElementById('m-teamroster-noresults');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            var visible = 0;
            cards.forEach(function(card) {
                var name = card.getAttribute('data-name') || '';
                var show = !query || name.indexOf(query) !== -1;
                card.style.display = show ? 'flex' : 'none';
                if (show) visible++;
            });
            if (noResults) {
                noResults.style.display = (visible === 0 && query) ? 'block' : 'none';
            }
        });
    }

    window.mRosterOpenAdd = function() {
        document.getElementById('mRosterSheet').style.display = 'block';
        document.getElementById('mRosterOverlay').style.display = 'block';
    };

    window.mRosterCloseAdd = function() {
        document.getElementById('mRosterSheet').style.display = 'none';
        document.getElementById('mRosterOverlay').style.display = 'none';
    };

    window.mRosterRemove = async function(rosterId) {
        if (!await showConfirmModal('Remove this player from the roster?')) return;
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_admin_team_coaches.php';
        form.style.display = 'none';
        var csrf = document.querySelector('#mRosterForm [name="csrf_token"]');
        form.innerHTML = '<input name="csrf_token" value="' + (csrf ? csrf.value : '') + '">' +
            '<input name="action" value="remove_roster_athlete">' +
            '<input name="roster_id" value="' + rosterId + '">' +
            '<input name="redirect_page" value="team_roster">';
        document.body.appendChild(form);
        form.submit();
    };
})();
</script>
