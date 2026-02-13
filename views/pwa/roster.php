<?php
/**
 * PWA Roster - Mobile-native athlete roster for coaches
 * Purpose-built for mobile phones.
 */

// Only coaches/admins should see the full roster
if (!$isAnyCoach && !$isTeamStaff && !$canAccessHealthManagement) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">You do not have access to the roster.</p>';
    echo '</div>';
    return;
}

$athletes = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.role, u.position, u.primary_arena,
               (SELECT MAX(s.session_date)
                FROM bookings b
                JOIN sessions s ON s.id = b.session_id
                WHERE b.user_id = u.id AND b.status = 'confirmed') as last_session
        FROM users u
        WHERE u.role = 'athlete' AND u.is_active = 1
        ORDER BY u.first_name ASC, u.last_name ASC
        LIMIT 50
    ");
    $stmt->execute();
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $athletes = []; }

$totalAthletes = count($athletes);
?>
<style>
.m-roster { padding: 16px; font-family: Inter, sans-serif; }
.m-roster-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
}
.m-roster-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-roster-count { font-size: 12px; color: #A8A8B8; }
.m-search-wrap {
    position: relative; margin-bottom: 16px;
}
.m-search-input {
    width: 100%; padding: 12px 12px 12px 40px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px;
    outline: none;
}
.m-search-input::placeholder { color: #6B6B7B; }
.m-search-input:focus { border-color: #6B46C1; }
.m-search-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #6B6B7B; font-size: 14px; pointer-events: none;
}
.m-athlete-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px;
}
.m-athlete-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff;
    flex-shrink: 0;
}
.m-athlete-info { flex: 1; min-width: 0; }
.m-athlete-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-athlete-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: flex; gap: 8px; flex-wrap: wrap; }
.m-athlete-chevron { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-no-results {
    text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px;
    display: none;
}
.m-no-results i { font-size: 24px; display: block; margin-bottom: 8px; }
</style>

<div class="m-roster">
    <div class="m-roster-header">
        <h2 class="m-roster-title">Athletes</h2>
        <span class="m-roster-count"><?= $totalAthletes ?> total</span>
    </div>

    <div class="m-search-wrap">
        <i class="fas fa-search m-search-icon"></i>
        <input type="text" class="m-search-input" id="m-roster-search" placeholder="Search athletes..." autocomplete="off">
    </div>

    <div id="m-roster-list">
        <?php if (empty($athletes)): ?>
            <div style="text-align:center;padding:32px;color:#6B6B7B;">
                <i class="fas fa-users-slash" style="font-size:28px;display:block;margin-bottom:10px;"></i>
                <p style="font-size:13px;">No athletes found</p>
            </div>
        <?php else: ?>
            <?php foreach ($athletes as $a):
                $initial = strtoupper(mb_substr($a['first_name'], 0, 1) . mb_substr($a['last_name'], 0, 1));
                $fullName = htmlspecialchars($a['first_name'] . ' ' . $a['last_name']);
                $lastSess = $a['last_session'] ? date('M j', strtotime($a['last_session'])) : null;
            ?>
            <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" class="m-athlete-card" data-name="<?= strtolower($fullName) ?>">
                <div class="m-athlete-avatar"><?= $initial ?></div>
                <div class="m-athlete-info">
                    <div class="m-athlete-name"><?= $fullName ?></div>
                    <div class="m-athlete-meta">
                        <?php if (!empty($a['position'])): ?>
                        <span><i class="fas fa-hockey-puck" style="font-size:10px;"></i> <?= htmlspecialchars(ucfirst($a['position'])) ?></span>
                        <?php endif; ?>
                        <?php if ($lastSess): ?>
                        <span><i class="fas fa-calendar-check" style="font-size:10px;"></i> <?= $lastSess ?></span>
                        <?php endif; ?>
                        <?php if (!$lastSess && empty($a['position'])): ?>
                        <span>Athlete</span>
                        <?php endif; ?>
                    </div>
                </div>
                <i class="fas fa-chevron-right m-athlete-chevron"></i>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-no-results" id="m-no-results">
        <i class="fas fa-search"></i>
        No athletes match your search
    </div>
</div>

<script>
(function() {
    var searchInput = document.getElementById('m-roster-search');
    var cards = document.querySelectorAll('.m-athlete-card');
    var noResults = document.getElementById('m-no-results');
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
