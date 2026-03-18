<?php
/**
 * PWA Manage Athletes - Mobile-native athlete management
 * Purpose-built for mobile phones.
 */

if (!$isAnyCoach && !$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">You do not have access to manage athletes.</p>';
    echo '</div>';
    return;
}

$athletes = [];
try {
    if ($user_role === 'parent') {
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.role, u.is_active,
                   COALESCE(ma.relationship, 'parent') as relationship,
                   ma.id as managed_id
            FROM users u
            INNER JOIN managed_athletes ma ON ma.athlete_id = u.id
            WHERE ma.parent_id = ?
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.role, u.is_active, 'coach' as relationship, ma.id as managed_id
            FROM managed_athletes ma
            INNER JOIN users u ON ma.athlete_id = u.id
            WHERE ma.coach_id = ? AND ma.status = 'active'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$user_id]);
    }
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
} catch (PDOException $e) { $athletes = []; }

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

$totalAthletes = count($athletes);
?>
<style>
.m-manage-ath { padding: 16px; padding-bottom: 100px; font-family: Inter, sans-serif; }
.m-manage-ath-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
}
.m-manage-ath-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-manage-ath-count { font-size: 12px; color: #A8A8B8; }
.m-manage-ath-alert {
    padding: 12px 14px; border-radius: 10px; font-size: 13px;
    margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.m-manage-ath-alert-success { background: rgba(16,185,129,0.15); color: #10B981; }
.m-manage-ath-alert-error { background: rgba(239,68,68,0.15); color: #EF4444; }
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
.m-manage-ath-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px; position: relative;
}
.m-manage-ath-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.m-manage-ath-info { flex: 1; min-width: 0; }
.m-manage-ath-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-manage-ath-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: flex; gap: 8px; }
.m-manage-ath-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-manage-ath-badge-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-manage-ath-badge-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-manage-ath-remove {
    width: 44px; height: 44px; border: none; background: rgba(239,68,68,0.12);
    border-radius: 10px; color: #EF4444; font-size: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.m-manage-ath-remove:active { background: rgba(239,68,68,0.25); }
.m-manage-ath-chevron { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-no-results {
    text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px;
    display: none;
}
.m-no-results i { font-size: 24px; display: block; margin-bottom: 8px; }

/* FAB */
.m-manage-ath-fab {
    position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px;
    border-radius: 50%; background: #6B46C1; color: #fff; border: none;
    font-size: 22px; cursor: pointer; z-index: 1000;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
}
.m-manage-ath-fab:active { background: #5A38A8; }

/* Overlay */
.m-manage-ath-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 1001;
}
.m-manage-ath-overlay.m-active { display: block; }

/* Bottom sheet */
.m-manage-ath-sheet {
    display: none; position: fixed; bottom: 0; left: 0; right: 0;
    background: #16161F; border-radius: 16px 16px 0 0; z-index: 1002;
    max-height: 90vh; overflow-y: auto; padding: 0 20px 24px;
    transform: translateY(100%); transition: transform 0.3s ease;
}
.m-manage-ath-sheet.m-active { display: block; transform: translateY(0); }
.m-manage-ath-sheet-handle {
    width: 36px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 12px auto 16px;
}
.m-manage-ath-sheet-title {
    font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px;
}
.m-manage-ath-sheet-option {
    display: flex; align-items: center; gap: 14px; padding: 14px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 12px;
    margin-bottom: 10px; cursor: pointer; min-height: 44px;
    color: #fff; text-decoration: none; font-family: Inter, sans-serif;
    width: 100%; font-size: 14px;
}
.m-manage-ath-sheet-option:active { background: #1A1A2A; }
.m-manage-ath-sheet-option i {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.m-manage-ath-sheet-option .m-icon-link { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-manage-ath-sheet-option .m-icon-create { background: rgba(16,185,129,0.15); color: #10B981; }
.m-manage-ath-sheet-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-manage-ath-sheet-close {
    width: 100%; padding: 14px; background: none; border: 1px solid #2D2D3F;
    border-radius: 12px; color: #A8A8B8; font-size: 14px; font-weight: 600;
    cursor: pointer; min-height: 44px; margin-top: 6px; font-family: Inter, sans-serif;
}
.m-manage-ath-sheet-close:active { background: #1A1A2A; }

/* Form styles */
.m-manage-ath-form-group { margin-bottom: 14px; }
.m-manage-ath-form-label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;
}
.m-manage-ath-form-input {
    width: 100%; padding: 12px 14px; background: #0A0A0F;
    border: 1px solid #2D2D3F; border-radius: 10px; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px; outline: none;
}
.m-manage-ath-form-input::placeholder { color: #6B6B7B; }
.m-manage-ath-form-input:focus { border-color: #6B46C1; }
.m-manage-ath-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-manage-ath-form-submit {
    width: 100%; padding: 14px; background: #6B46C1; color: #fff; border: none;
    border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer;
    min-height: 44px; font-family: Inter, sans-serif; margin-top: 4px;
}
.m-manage-ath-form-submit:active { background: #5A38A8; }
</style>

<div class="m-manage-ath">
    <div class="m-manage-ath-header">
        <h2 class="m-manage-ath-title">Manage Athletes</h2>
        <span class="m-manage-ath-count"><?= $totalAthletes ?> total</span>
    </div>

    <?php if ($success): ?>
        <div class="m-manage-ath-alert m-manage-ath-alert-success">
            <i class="fas fa-check-circle"></i>
            <?php
            switch ($success) {
                case 'athlete_added': echo 'Athlete linked successfully'; break;
                case 'athlete_created': echo 'Athlete created and linked'; break;
                case 'athlete_removed': echo 'Athlete removed from your list'; break;
                default: echo 'Operation completed';
            }
            ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="m-manage-ath-alert m-manage-ath-alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php
            switch ($error) {
                case 'athlete_not_found': echo 'No athlete found with that email'; break;
                case 'already_managed': echo 'Athlete already in your list'; break;
                case 'invalid_data': echo 'Please fill in all required fields'; break;
                case 'email_exists': echo 'An account with this email exists'; break;
                case 'permission_denied': echo 'Permission denied'; break;
                default: echo 'An error occurred. Please try again.';
            }
            ?>
        </div>
    <?php endif; ?>

    <div class="m-search-wrap">
        <i class="fas fa-search m-search-icon"></i>
        <input type="text" class="m-search-input" id="m-manage-ath-search" placeholder="Search athletes..." autocomplete="off">
    </div>

    <div id="m-manage-ath-list">
        <?php if (empty($athletes)): ?>
            <div style="text-align:center;padding:32px;color:#6B6B7B;">
                <i class="fas fa-users-slash" style="font-size:28px;display:block;margin-bottom:10px;"></i>
                <p style="font-size:13px;">No athletes found</p>
            </div>
        <?php else: ?>
            <?php foreach ($athletes as $a):
                $initial = strtoupper(mb_substr($a['first_name'], 0, 1) . mb_substr($a['last_name'], 0, 1));
                $fullName = htmlspecialchars($a['first_name'] . ' ' . $a['last_name']);
                $isActive = (int)($a['is_active'] ?? 1);
                $relationship = htmlspecialchars($a['relationship'] ?? '');
            ?>
            <div class="m-manage-ath-card" data-name="<?= strtolower($fullName) ?>">
                <a href="?page=athlete_detail&id=<?= (int)$a['id'] ?>" style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit;">
                    <div class="m-manage-ath-avatar"><?= $initial ?></div>
                    <div class="m-manage-ath-info">
                        <div class="m-manage-ath-name"><?= $fullName ?></div>
                        <div class="m-manage-ath-meta">
                            <span><?= htmlspecialchars(ucfirst($a['role'] ?? 'athlete')) ?></span>
                            <?php if ($relationship): ?><span>&bull; <?= $relationship ?></span><?php endif; ?>
                        </div>
                    </div>
                    <span class="m-manage-ath-badge m-manage-ath-badge-<?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                </a>
                <form method="POST" action="process_manage_athletes.php" class="m-manage-ath-remove-form">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="remove_athlete">
                    <input type="hidden" name="managed_id" value="<?= (int)$a['managed_id'] ?>">
                    <button type="button" class="m-manage-ath-remove" onclick="mManageAthConfirmRemove(this)" aria-label="Remove athlete">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-no-results" id="m-manage-ath-noresults">
        <i class="fas fa-search"></i>
        No athletes match your search
    </div>
</div>

<!-- FAB Button -->
<button class="m-manage-ath-fab" id="m-manage-ath-fab" aria-label="Add athlete">
    <i class="fas fa-plus"></i>
</button>

<!-- Overlay -->
<div class="m-manage-ath-overlay" id="m-manage-ath-overlay"></div>

<!-- Action Picker Sheet -->
<div class="m-manage-ath-sheet" id="m-manage-ath-picker">
    <div class="m-manage-ath-sheet-handle"></div>
    <h3 class="m-manage-ath-sheet-title">Add Athlete</h3>
    <button type="button" class="m-manage-ath-sheet-option" id="m-manage-ath-pick-link">
        <i class="m-icon-link fas fa-link"></i>
        <div>
            <div>Link Existing Athlete</div>
            <div class="m-manage-ath-sheet-desc">Add by email address</div>
        </div>
    </button>
    <button type="button" class="m-manage-ath-sheet-option" id="m-manage-ath-pick-create">
        <i class="m-icon-create fas fa-user-plus"></i>
        <div>
            <div>Create New Athlete</div>
            <div class="m-manage-ath-sheet-desc">Create a new account</div>
        </div>
    </button>
    <button type="button" class="m-manage-ath-sheet-close" id="m-manage-ath-picker-close">Cancel</button>
</div>

<!-- Link Athlete Sheet -->
<div class="m-manage-ath-sheet" id="m-manage-ath-link-sheet">
    <div class="m-manage-ath-sheet-handle"></div>
    <h3 class="m-manage-ath-sheet-title">Link Existing Athlete</h3>
    <form method="POST" action="process_manage_athletes.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="add_athlete">
        <div class="m-manage-ath-form-group">
            <label class="m-manage-ath-form-label">Athlete Email</label>
            <input type="email" name="athlete_email" class="m-manage-ath-form-input" placeholder="athlete@example.com" required>
        </div>
        <div class="m-manage-ath-form-group">
            <label class="m-manage-ath-form-label">Relationship</label>
            <input type="text" name="relationship" class="m-manage-ath-form-input" value="Parent" placeholder="e.g., Parent, Guardian">
        </div>
        <button type="submit" class="m-manage-ath-form-submit"><i class="fas fa-link"></i> Link Athlete</button>
    </form>
    <button type="button" class="m-manage-ath-sheet-close m-manage-ath-close-btn" style="margin-top:12px;">Cancel</button>
</div>

<!-- Create Athlete Sheet -->
<div class="m-manage-ath-sheet" id="m-manage-ath-create-sheet">
    <div class="m-manage-ath-sheet-handle"></div>
    <h3 class="m-manage-ath-sheet-title">Create New Athlete</h3>
    <form method="POST" action="process_manage_athletes.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create_athlete">
        <div class="m-manage-ath-form-row">
            <div class="m-manage-ath-form-group">
                <label class="m-manage-ath-form-label">First Name</label>
                <input type="text" name="first_name" class="m-manage-ath-form-input" required>
            </div>
            <div class="m-manage-ath-form-group">
                <label class="m-manage-ath-form-label">Last Name</label>
                <input type="text" name="last_name" class="m-manage-ath-form-input" required>
            </div>
        </div>
        <div class="m-manage-ath-form-group">
            <label class="m-manage-ath-form-label">Email</label>
            <input type="email" name="email" class="m-manage-ath-form-input" required>
        </div>
        <div class="m-manage-ath-form-row">
            <div class="m-manage-ath-form-group">
                <label class="m-manage-ath-form-label">Birth Date</label>
                <input type="date" name="birth_date" class="m-manage-ath-form-input">
            </div>
            <div class="m-manage-ath-form-group">
                <label class="m-manage-ath-form-label">Position</label>
                <input type="text" name="position" class="m-manage-ath-form-input" placeholder="e.g., Forward">
            </div>
        </div>
        <div class="m-manage-ath-form-group">
            <label class="m-manage-ath-form-label">Relationship</label>
            <input type="text" name="relationship" class="m-manage-ath-form-input" value="Parent" placeholder="e.g., Parent, Guardian">
        </div>
        <button type="submit" class="m-manage-ath-form-submit"><i class="fas fa-user-plus"></i> Create Athlete</button>
    </form>
    <button type="button" class="m-manage-ath-sheet-close m-manage-ath-close-btn" style="margin-top:12px;">Cancel</button>
</div>

<script>
(function() {
    var searchInput = document.getElementById('m-manage-ath-search');
    var cards = document.querySelectorAll('.m-manage-ath-card');
    var noResults = document.getElementById('m-manage-ath-noresults');
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

    // Sheet management
    var overlay = document.getElementById('m-manage-ath-overlay');
    var picker = document.getElementById('m-manage-ath-picker');
    var linkSheet = document.getElementById('m-manage-ath-link-sheet');
    var createSheet = document.getElementById('m-manage-ath-create-sheet');
    var fab = document.getElementById('m-manage-ath-fab');

    function openSheet(sheet) {
        overlay.classList.add('m-active');
        sheet.style.display = 'block';
        requestAnimationFrame(function() {
            sheet.classList.add('m-active');
        });
    }
    function closeAll() {
        overlay.classList.remove('m-active');
        [picker, linkSheet, createSheet].forEach(function(s) {
            s.classList.remove('m-active');
            setTimeout(function() { if (!s.classList.contains('m-active')) s.style.display = 'none'; }, 300);
        });
    }

    fab.addEventListener('click', function() { openSheet(picker); });
    overlay.addEventListener('click', closeAll);
    document.getElementById('m-manage-ath-picker-close').addEventListener('click', closeAll);
    document.querySelectorAll('.m-manage-ath-close-btn').forEach(function(btn) {
        btn.addEventListener('click', closeAll);
    });

    document.getElementById('m-manage-ath-pick-link').addEventListener('click', function() {
        picker.classList.remove('m-active');
        setTimeout(function() { picker.style.display = 'none'; openSheet(linkSheet); }, 200);
    });
    document.getElementById('m-manage-ath-pick-create').addEventListener('click', function() {
        picker.classList.remove('m-active');
        setTimeout(function() { picker.style.display = 'none'; openSheet(createSheet); }, 200);
    });

    // Remove confirmation
    window.mManageAthConfirmRemove = async function(btn) {
        if (await showConfirmModal('Remove this athlete from your managed list?')) {
            btn.closest('form').submit();
        }
    };
})();
</script>
