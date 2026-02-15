<?php
/**
 * PWA Athletes - Mobile-native athletes list with create/deactivate
 * Purpose-built for mobile phones.
 */

$is_coach = in_array(($user_role ?? ''), ['coach', 'coach_plus', 'admin']);

$athletes = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, role, is_active
        FROM users
        WHERE role = 'athlete'
        ORDER BY first_name
        LIMIT 50
    ");
    $stmt->execute();
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
} catch (PDOException $e) { $athletes = []; }

$totalAthletes = count($athletes);
?>
<style>
.m-athletes { padding: 16px; padding-bottom: 80px; font-family: Inter, sans-serif; }
.m-athletes-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
}
.m-athletes-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-athletes-count { font-size: 12px; color: #A8A8B8; }
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
.m-athletes-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px;
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
.m-athletes-chevron { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
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
        <span class="m-athletes-count"><?= $totalAthletes ?> total</span>
    </div>

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
            ?>
            <div class="m-athletes-card <?= $isInactive ? 'm-ath-inactive' : '' ?>" data-name="<?= strtolower($fullName) ?>">
                <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" style="display:flex;align-items:center;gap:12px;flex:1;text-decoration:none;min-width:0;">
                    <div class="m-athletes-avatar"><?= $initial ?></div>
                    <div class="m-athletes-info">
                        <div class="m-athletes-name"><?= $fullName ?></div>
                        <div class="m-athletes-meta">
                            <span>Athlete<?= $isInactive ? ' · Inactive' : '' ?></span>
                        </div>
                    </div>
                </a>
                <?php if ($is_coach && !$isInactive): ?>
                <div class="m-athletes-card-actions">
                    <form method="POST" action="process_manage_athletes.php" onsubmit="return confirm('Deactivate this athlete?');">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="action" value="remove_athlete">
                        <input type="hidden" name="athlete_id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="m-ath-action-btn m-ath-btn-deactivate"><i class="fas fa-user-slash"></i></button>
                    </form>
                </div>
                <?php endif; ?>
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
<button type="button" class="m-ath-fab" onclick="mAthOpenCreate()"><i class="fas fa-plus"></i></button>

<div class="m-ath-overlay" id="mAthOverlay" onclick="mAthClose()"></div>
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
    var searchInput = document.getElementById('m-athletes-search');
    var cards = document.querySelectorAll('.m-athletes-card');
    var noResults = document.getElementById('m-athletes-noresults');
    if (!searchInput) return;
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
})();
function mAthOpenCreate() {
    document.getElementById('mAthOverlay').classList.add('active');
    document.getElementById('mAthSheet').classList.add('active');
}
function mAthClose() {
    document.getElementById('mAthOverlay').classList.remove('active');
    document.getElementById('mAthSheet').classList.remove('active');
}
</script>
