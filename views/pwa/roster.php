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
    position: relative; margin-bottom: 8px;
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
/* Position filter */
.m-filter-bar { margin-bottom: 16px; }
.m-filter-select {
    width: 100%; padding: 10px 12px; min-height: 44px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; outline: none;
    -webkit-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B6B7B' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-filter-select:focus { border-color: #6B46C1; }
/* Athlete cards */
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
/* Action buttons row */
.m-athlete-actions {
    display: flex; gap: 6px; margin-top: 8px;
}
.m-athlete-actions a {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    background: #0A0A0F; border: 1px solid #2D2D3F;
    color: #A8A8B8; font-size: 12px; text-decoration: none;
    transition: background 0.15s, color 0.15s;
}
.m-athlete-actions a:hover { background: #6B46C1; color: #fff; border-color: #6B46C1; }
.m-athlete-chevron { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-no-results {
    text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px;
    display: none;
}
.m-no-results i { font-size: 24px; display: block; margin-bottom: 8px; }
/* FAB button */
.m-roster-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 100;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border: none; color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    cursor: pointer;
}
.m-roster-fab:active { transform: scale(0.95); }
/* Slide-up modal */
.m-modal-overlay {
    position: fixed; inset: 0; z-index: 200;
    background: rgba(0,0,0,0.6); display: none;
    align-items: flex-end; justify-content: center;
}
.m-modal-overlay.is-open { display: flex; }
.m-modal-sheet {
    width: 100%; max-width: 480px; max-height: 90vh;
    background: #16161F; border-radius: 16px 16px 0 0;
    padding: 20px; overflow-y: auto;
    animation: m-slide-up 0.25s ease-out;
}
@keyframes m-slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-modal-handle {
    width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-modal-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { display: block; font-size: 12px; color: #A8A8B8; margin-bottom: 4px; font-weight: 600; }
.m-form-input, .m-form-select {
    width: 100%; padding: 12px; min-height: 44px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; outline: none;
}
.m-form-select {
    -webkit-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B6B7B' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-form-input:focus, .m-form-select:focus { border-color: #6B46C1; }
.m-form-input::placeholder { color: #6B6B7B; }
.m-form-row { display: flex; gap: 10px; }
.m-form-row .m-form-group { flex: 1; }
.m-btn-submit {
    width: 100%; padding: 14px; min-height: 48px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border: none; border-radius: 12px; color: #fff;
    font-size: 15px; font-weight: 600; font-family: Inter, sans-serif;
    cursor: pointer; margin-top: 4px;
}
.m-btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.m-btn-submit:active:not(:disabled) { transform: scale(0.98); }
.m-form-msg {
    text-align: center; font-size: 13px; margin-top: 10px; display: none;
    padding: 8px; border-radius: 8px;
}
.m-form-msg.is-error { display: block; color: #EF4444; background: rgba(239,68,68,0.1); }
.m-form-msg.is-success { display: block; color: #10B981; background: rgba(16,185,129,0.1); }
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

    <div class="m-filter-bar">
        <select class="m-filter-select" id="m-position-filter">
            <option value="">All Positions</option>
            <option value="forward">Forward</option>
            <option value="defense">Defense</option>
            <option value="goalie">Goalie</option>
        </select>
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
                $pos = strtolower(trim($a['position'] ?? ''));
            ?>
            <div class="m-athlete-card" data-name="<?= strtolower($fullName) ?>" data-position="<?= htmlspecialchars($pos) ?>">
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
                    <div class="m-athlete-actions">
                        <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" title="View Profile" aria-label="View Profile"><i class="fas fa-user"></i></a>
                        <a href="?page=messages&user_id=<?= (int)$a['id'] ?>" title="Message" aria-label="Message"><i class="fas fa-envelope"></i></a>
                        <a href="?page=goals&athlete_id=<?= (int)$a['id'] ?>" title="Goals" aria-label="Goals"><i class="fas fa-bullseye"></i></a>
                    </div>
                </div>
                <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" style="text-decoration:none;">
                    <i class="fas fa-chevron-right m-athlete-chevron"></i>
                </a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-no-results" id="m-no-results">
        <i class="fas fa-search"></i>
        No athletes match your filters
    </div>

    <?php if ($isAnyCoach || $isAdmin): ?>
    <button class="m-roster-fab" id="m-roster-fab" title="Add Athlete" aria-label="Add Athlete">
        <i class="fas fa-plus"></i>
    </button>

    <div class="m-modal-overlay" id="m-add-athlete-modal">
        <div class="m-modal-sheet">
            <div class="m-modal-handle"></div>
            <h3 class="m-modal-title">Add Athlete</h3>
            <form id="m-add-athlete-form" autocomplete="off">
                <?= csrfTokenInput() ?>
                <div class="m-form-row">
                    <div class="m-form-group">
                        <label class="m-form-label" for="m-af-fname">First Name</label>
                        <input type="text" class="m-form-input" id="m-af-fname" name="first_name" required placeholder="First name">
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label" for="m-af-lname">Last Name</label>
                        <input type="text" class="m-form-input" id="m-af-lname" name="last_name" required placeholder="Last name">
                    </div>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label" for="m-af-email">Email</label>
                    <input type="email" class="m-form-input" id="m-af-email" name="email" required placeholder="athlete@example.com">
                </div>
                <div class="m-form-row">
                    <div class="m-form-group">
                        <label class="m-form-label" for="m-af-dob">Birth Date</label>
                        <input type="date" class="m-form-input" id="m-af-dob" name="birth_date">
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label" for="m-af-pos">Position</label>
                        <select class="m-form-select" id="m-af-pos" name="position">
                            <option value="">Select...</option>
                            <option value="forward">Forward</option>
                            <option value="defense">Defense</option>
                            <option value="goalie">Goalie</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="m-btn-submit" id="m-af-submit">Add Athlete</button>
                <div class="m-form-msg" id="m-af-msg"></div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var searchInput = document.getElementById('m-roster-search');
    var posFilter = document.getElementById('m-position-filter');
    var cards = document.querySelectorAll('.m-athlete-card');
    var noResults = document.getElementById('m-no-results');

    function filterCards() {
        var query = (searchInput ? searchInput.value.toLowerCase().trim() : '');
        var pos = (posFilter ? posFilter.value.toLowerCase() : '');
        var visible = 0;
        cards.forEach(function(card) {
            var name = card.getAttribute('data-name') || '';
            var cardPos = card.getAttribute('data-position') || '';
            var matchName = !query || name.indexOf(query) !== -1;
            var matchPos = !pos || cardPos === pos;
            var show = matchName && matchPos;
            card.style.display = show ? 'flex' : 'none';
            if (show) visible++;
        });
        if (noResults) {
            noResults.style.display = (visible === 0 && (query || pos)) ? 'block' : 'none';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterCards);
    if (posFilter) posFilter.addEventListener('change', filterCards);

    // Add athlete modal
    var fab = document.getElementById('m-roster-fab');
    var modal = document.getElementById('m-add-athlete-modal');
    var form = document.getElementById('m-add-athlete-form');

    if (fab && modal) {
        fab.addEventListener('click', function() { modal.classList.add('is-open'); });
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.classList.remove('is-open');
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('m-af-submit');
            var msg = document.getElementById('m-af-msg');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            msg.className = 'm-form-msg';
            msg.style.display = 'none';

            var fd = new FormData(form);
            fetch('process_create_athlete.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function(r) { return r.text().then(function(t) { return {ok: r.ok, status: r.status, text: t}; }); })
            .then(function(res) {
                if (res.ok || res.status === 302) {
                    msg.className = 'm-form-msg is-success';
                    msg.textContent = 'Athlete added successfully!';
                    msg.style.display = 'block';
                    form.reset();
                    location.reload();
                } else {
                    msg.className = 'm-form-msg is-error';
                    msg.textContent = res.text.indexOf('email_taken') !== -1 ? 'Email already in use.' : 'Failed to add athlete. Please try again.';
                    msg.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Add Athlete';
                }
            }).catch(function() {
                msg.className = 'm-form-msg is-error';
                msg.textContent = 'Network error. Please try again.';
                msg.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Add Athlete';
            });
        });
    }
})();
</script>
