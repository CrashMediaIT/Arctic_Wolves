<?php
/**
 * PWA Athletes - Mobile-native read-only athletes list
 * Purpose-built for mobile phones.
 */

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
} catch (PDOException $e) { $athletes = []; }

$totalAthletes = count($athletes);
?>
<style>
.m-athletes { padding: 16px; font-family: Inter, sans-serif; }
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
.m-no-results {
    text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px;
    display: none;
}
.m-no-results i { font-size: 24px; display: block; margin-bottom: 8px; }
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
            ?>
            <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" class="m-athletes-card" data-name="<?= strtolower($fullName) ?>">
                <div class="m-athletes-avatar"><?= $initial ?></div>
                <div class="m-athletes-info">
                    <div class="m-athletes-name"><?= $fullName ?></div>
                    <div class="m-athletes-meta">
                        <span>Athlete</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right m-athletes-chevron"></i>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-no-results" id="m-athletes-noresults">
        <i class="fas fa-search"></i>
        No athletes match your search
    </div>
</div>

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
</script>
