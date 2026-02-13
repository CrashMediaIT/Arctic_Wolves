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
        SELECT u.id, u.first_name, u.last_name, u.position
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
?>
<style>
.m-teamroster { padding: 16px; font-family: Inter, sans-serif; }
.m-teamroster-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
}
.m-teamroster-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-teamroster-count { font-size: 12px; color: #A8A8B8; }
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
.m-no-results {
    text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px;
    display: none;
}
.m-no-results i { font-size: 24px; display: block; margin-bottom: 8px; }
</style>

<div class="m-teamroster">
    <div class="m-teamroster-header">
        <h2 class="m-teamroster-title">Team Roster</h2>
        <span class="m-teamroster-count"><?= $totalAthletes ?> total</span>
    </div>

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
            <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" class="m-teamroster-card" data-name="<?= strtolower($fullName) ?>">
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
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-no-results" id="m-teamroster-noresults">
        <i class="fas fa-search"></i>
        No athletes match your search
    </div>
</div>

<script>
(function() {
    var searchInput = document.getElementById('m-teamroster-search');
    var cards = document.querySelectorAll('.m-teamroster-card');
    var noResults = document.getElementById('m-teamroster-noresults');
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
</script>
