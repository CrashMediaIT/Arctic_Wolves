<?php
/**
 * PWA Admin Age/Skill Groups - Mobile-native CRUD for age groups & skill levels
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$age_groups = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM age_groups ORDER BY display_order ASC");
    $stmt->execute();
    $age_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $age_groups = []; }

$skill_levels = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM skill_levels ORDER BY display_order ASC");
    $stmt->execute();
    $skill_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $skill_levels = []; }
?>
<style>
.m-ageskill { padding: 16px; padding-bottom: 80px; font-family: Inter, sans-serif; }
.m-ageskill-header { margin-bottom: 16px; }
.m-ageskill-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ageskill-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-ageskill-section { margin-bottom: 24px; }
.m-ageskill-section-title {
    font-size: 15px; font-weight: 700; color: #fff; margin: 0 0 12px;
    display: flex; align-items: center; gap: 8px;
}
.m-ageskill-section-title i { color: #8B5CF6; font-size: 14px; }
.m-ageskill-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-ageskill-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(139,92,246,0.15); color: #8B5CF6; font-size: 16px; flex-shrink: 0;
}
.m-ageskill-body { flex: 1; min-width: 0; }
.m-ageskill-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-ageskill-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.m-ageskill-meta { display: flex; gap: 12px; margin-top: 4px; flex-wrap: wrap; }
.m-ageskill-detail { font-size: 12px; color: #A8A8B8; display: inline-flex; align-items: center; gap: 4px; }
.m-ageskill-actions { display: flex; gap: 6px; flex-shrink: 0; }
.m-ageskill-action-btn {
    width: 44px; height: 44px; border-radius: 10px; border: 1px solid #2D2D3F;
    background: rgba(255,255,255,0.05); color: #A8A8B8; display: flex;
    align-items: center; justify-content: center; font-size: 14px; cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}
.m-ageskill-action-btn:active { background: rgba(255,255,255,0.1); }
.m-ageskill-action-btn.m-delete-btn:active { background: rgba(239,68,68,0.2); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }

/* FAB */
.m-ageskill-fab {
    position: fixed; bottom: 60px; right: 20px; z-index: 1000;
    width: 56px; height: 56px; border-radius: 50%; border: none;
    background: #6B46C1; color: #fff; font-size: 24px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    cursor: pointer; -webkit-tap-highlight-color: transparent;
}
.m-ageskill-fab:active { transform: scale(0.93); }

/* Action sheet overlay */
.m-action-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    z-index: 1001; -webkit-tap-highlight-color: transparent;
}
.m-action-overlay.m-active { display: block; }
.m-action-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1002;
    background: #16161F; border-radius: 16px 16px 0 0;
    border-top: 1px solid #2D2D3F; padding: 20px 16px;
    transform: translateY(100%); transition: transform 0.3s ease;
}
.m-action-sheet.m-active { transform: translateY(0); }
.m-action-sheet-handle {
    width: 36px; height: 4px; border-radius: 2px; background: #3D3D4F;
    margin: 0 auto 16px;
}
.m-action-sheet-title { font-size: 15px; font-weight: 700; color: #fff; margin: 0 0 12px; text-align: center; }
.m-action-sheet-btn {
    display: flex; align-items: center; gap: 12px; width: 100%;
    padding: 14px 16px; border: 1px solid #2D2D3F; border-radius: 12px;
    background: #0A0A0F; color: #fff; font-size: 14px; font-weight: 500;
    cursor: pointer; margin-bottom: 8px; min-height: 44px;
    -webkit-tap-highlight-color: transparent;
}
.m-action-sheet-btn i { font-size: 16px; color: #8B5CF6; width: 20px; text-align: center; }
.m-action-sheet-btn:active { background: #1a1a2a; }
.m-action-sheet-cancel {
    display: block; width: 100%; padding: 14px; border: none; border-radius: 12px;
    background: transparent; color: #A8A8B8; font-size: 14px; font-weight: 500;
    cursor: pointer; margin-top: 4px; min-height: 44px;
}

/* Bottom sheet modal */
.m-sheet-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    z-index: 1003; -webkit-tap-highlight-color: transparent;
}
.m-sheet-overlay.m-active { display: block; }
.m-bottom-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1004;
    background: #16161F; border-radius: 16px 16px 0 0;
    border-top: 1px solid #2D2D3F; max-height: 85vh;
    overflow-y: auto; -webkit-overflow-scrolling: touch;
    transform: translateY(100%); transition: transform 0.3s ease;
}
.m-bottom-sheet.m-active { transform: translateY(0); }
.m-sheet-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px; border-bottom: 1px solid #2D2D3F; position: sticky; top: 0;
    background: #16161F; z-index: 1;
}
.m-sheet-header-title { font-size: 15px; font-weight: 700; color: #fff; margin: 0; }
.m-sheet-close {
    width: 44px; height: 44px; border: none; background: transparent;
    color: #A8A8B8; font-size: 20px; cursor: pointer; display: flex;
    align-items: center; justify-content: center; border-radius: 10px;
    -webkit-tap-highlight-color: transparent;
}
.m-sheet-close:active { background: rgba(255,255,255,0.05); }
.m-sheet-body { padding: 16px; }
.m-sheet-form-group { margin-bottom: 14px; }
.m-sheet-label { display: block; font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; }
.m-sheet-input, .m-sheet-textarea {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; color: #fff;
    border-radius: 8px; padding: 12px; font-size: 14px; min-height: 44px;
    font-family: Inter, sans-serif; box-sizing: border-box;
}
.m-sheet-input:focus, .m-sheet-textarea:focus { outline: none; border-color: #6B46C1; }
.m-sheet-textarea { resize: vertical; min-height: 80px; }
.m-sheet-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-sheet-submit {
    width: 100%; padding: 14px; border: none; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 15px; font-weight: 600;
    cursor: pointer; margin-top: 8px; min-height: 44px;
    -webkit-tap-highlight-color: transparent;
}
.m-sheet-submit:active { background: #5a3aab; }
</style>

<div class="m-ageskill">
    <div class="m-ageskill-header">
        <h2 class="m-ageskill-title">Age Groups & Skill Levels</h2>
        <p class="m-ageskill-sub"><?= count($age_groups) ?> age group<?= count($age_groups) !== 1 ? 's' : '' ?>, <?= count($skill_levels) ?> skill level<?= count($skill_levels) !== 1 ? 's' : '' ?></p>
    </div>

    <!-- Age Groups Section -->
    <div class="m-ageskill-section">
        <h3 class="m-ageskill-section-title"><i class="fas fa-birthday-cake"></i> Age Groups</h3>
        <?php if (empty($age_groups)): ?>
            <div class="m-empty-state">
                <i class="fas fa-birthday-cake"></i>
                <p>No age groups defined</p>
            </div>
        <?php else: ?>
            <?php foreach ($age_groups as $ag): ?>
            <div class="m-ageskill-card">
                <div class="m-ageskill-icon"><i class="fas fa-birthday-cake"></i></div>
                <div class="m-ageskill-body">
                    <div class="m-ageskill-name"><?= htmlspecialchars($ag['name'] ?? '') ?></div>
                    <?php if (!empty($ag['description'])): ?>
                        <div class="m-ageskill-desc"><?= htmlspecialchars(mb_strimwidth($ag['description'], 0, 60, '...')) ?></div>
                    <?php endif; ?>
                    <div class="m-ageskill-meta">
                        <?php if ($ag['min_age'] || $ag['max_age']): ?>
                            <span class="m-ageskill-detail"><i class="fas fa-child"></i> <?= (int)($ag['min_age'] ?? 0) ?>–<?= (int)($ag['max_age'] ?? 0) ?> yrs</span>
                        <?php endif; ?>
                        <span class="m-ageskill-detail"><i class="fas fa-sort"></i> #<?= (int)($ag['display_order'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="m-ageskill-actions">
                    <button type="button" class="m-ageskill-action-btn m-edit-age-btn"
                        data-id="<?= (int)$ag['id'] ?>"
                        data-name="<?= htmlspecialchars($ag['name'] ?? '') ?>"
                        data-min-age="<?= htmlspecialchars($ag['min_age'] ?? '') ?>"
                        data-max-age="<?= htmlspecialchars($ag['max_age'] ?? '') ?>"
                        data-description="<?= htmlspecialchars($ag['description'] ?? '') ?>"
                        data-display-order="<?= (int)($ag['display_order'] ?? 0) ?>">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    <button type="button" class="m-ageskill-action-btn m-delete-btn m-delete-age-btn"
                        data-id="<?= (int)$ag['id'] ?>"
                        data-name="<?= htmlspecialchars($ag['name'] ?? '') ?>">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Skill Levels Section -->
    <div class="m-ageskill-section">
        <h3 class="m-ageskill-section-title"><i class="fas fa-chart-line"></i> Skill Levels</h3>
        <?php if (empty($skill_levels)): ?>
            <div class="m-empty-state">
                <i class="fas fa-chart-line"></i>
                <p>No skill levels defined</p>
            </div>
        <?php else: ?>
            <?php foreach ($skill_levels as $sl): ?>
            <div class="m-ageskill-card">
                <div class="m-ageskill-icon"><i class="fas fa-chart-line"></i></div>
                <div class="m-ageskill-body">
                    <div class="m-ageskill-name"><?= htmlspecialchars($sl['name'] ?? '') ?></div>
                    <?php if (!empty($sl['description'])): ?>
                        <div class="m-ageskill-desc"><?= htmlspecialchars(mb_strimwidth($sl['description'], 0, 60, '...')) ?></div>
                    <?php endif; ?>
                    <div class="m-ageskill-meta">
                        <span class="m-ageskill-detail"><i class="fas fa-sort"></i> #<?= (int)($sl['display_order'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="m-ageskill-actions">
                    <button type="button" class="m-ageskill-action-btn m-edit-skill-btn"
                        data-id="<?= (int)$sl['id'] ?>"
                        data-name="<?= htmlspecialchars($sl['name'] ?? '') ?>"
                        data-description="<?= htmlspecialchars($sl['description'] ?? '') ?>"
                        data-display-order="<?= (int)($sl['display_order'] ?? 0) ?>">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    <button type="button" class="m-ageskill-action-btn m-delete-btn m-delete-skill-btn"
                        data-id="<?= (int)$sl['id'] ?>"
                        data-name="<?= htmlspecialchars($sl['name'] ?? '') ?>">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- FAB Button -->
<button type="button" class="m-ageskill-fab" id="m-ageskill-fab" aria-label="Add new">
    <i class="fas fa-plus"></i>
</button>

<!-- Action Sheet: Pick type -->
<div class="m-action-overlay" id="m-action-overlay"></div>
<div class="m-action-sheet" id="m-action-sheet">
    <div class="m-action-sheet-handle"></div>
    <div class="m-action-sheet-title">What would you like to add?</div>
    <button type="button" class="m-action-sheet-btn" id="m-add-age-btn">
        <i class="fas fa-birthday-cake"></i> Age Group
    </button>
    <button type="button" class="m-action-sheet-btn" id="m-add-skill-btn">
        <i class="fas fa-chart-line"></i> Skill Level
    </button>
    <button type="button" class="m-action-sheet-cancel" id="m-action-cancel">Cancel</button>
</div>

<!-- Bottom Sheet: Age Group Form -->
<div class="m-sheet-overlay" id="m-age-sheet-overlay"></div>
<div class="m-bottom-sheet" id="m-age-sheet">
    <div class="m-sheet-header">
        <h3 class="m-sheet-header-title" id="m-age-sheet-title">Add Age Group</h3>
        <button type="button" class="m-sheet-close" id="m-age-sheet-close" aria-label="Close">&times;</button>
    </div>
    <div class="m-sheet-body">
        <form action="process_admin_age_skill.php" method="POST" id="m-age-form">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="m-age-action" value="create_age_group">
            <input type="hidden" name="id" id="m-age-id" value="">
            <div class="m-sheet-form-group">
                <label class="m-sheet-label">Name *</label>
                <input type="text" name="name" id="m-age-name" class="m-sheet-input" required placeholder="e.g., Bantam (U14)">
            </div>
            <div class="m-sheet-row">
                <div class="m-sheet-form-group">
                    <label class="m-sheet-label">Min Age</label>
                    <input type="number" name="min_age" id="m-age-min" class="m-sheet-input" placeholder="e.g., 13">
                </div>
                <div class="m-sheet-form-group">
                    <label class="m-sheet-label">Max Age</label>
                    <input type="number" name="max_age" id="m-age-max" class="m-sheet-input" placeholder="e.g., 14">
                </div>
            </div>
            <div class="m-sheet-form-group">
                <label class="m-sheet-label">Description</label>
                <textarea name="description" id="m-age-desc" class="m-sheet-textarea" placeholder="Brief description..."></textarea>
            </div>
            <div class="m-sheet-form-group">
                <label class="m-sheet-label">Display Order</label>
                <input type="number" name="display_order" id="m-age-order" class="m-sheet-input" value="0" placeholder="0">
            </div>
            <button type="submit" class="m-sheet-submit" id="m-age-submit-btn">
                <i class="fas fa-plus"></i> Add Age Group
            </button>
        </form>
    </div>
</div>

<!-- Bottom Sheet: Skill Level Form -->
<div class="m-sheet-overlay" id="m-skill-sheet-overlay"></div>
<div class="m-bottom-sheet" id="m-skill-sheet">
    <div class="m-sheet-header">
        <h3 class="m-sheet-header-title" id="m-skill-sheet-title">Add Skill Level</h3>
        <button type="button" class="m-sheet-close" id="m-skill-sheet-close" aria-label="Close">&times;</button>
    </div>
    <div class="m-sheet-body">
        <form action="process_admin_age_skill.php" method="POST" id="m-skill-form">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="m-skill-action" value="create_skill_level">
            <input type="hidden" name="id" id="m-skill-id" value="">
            <div class="m-sheet-form-group">
                <label class="m-sheet-label">Name *</label>
                <input type="text" name="name" id="m-skill-name" class="m-sheet-input" required placeholder="e.g., Advanced">
            </div>
            <div class="m-sheet-form-group">
                <label class="m-sheet-label">Description</label>
                <textarea name="description" id="m-skill-desc" class="m-sheet-textarea" placeholder="Brief description..."></textarea>
            </div>
            <div class="m-sheet-form-group">
                <label class="m-sheet-label">Display Order</label>
                <input type="number" name="display_order" id="m-skill-order" class="m-sheet-input" value="0" placeholder="0">
            </div>
            <button type="submit" class="m-sheet-submit" id="m-skill-submit-btn">
                <i class="fas fa-plus"></i> Add Skill Level
            </button>
        </form>
    </div>
</div>

<!-- Hidden delete forms -->
<form id="m-delete-age-form" action="process_admin_age_skill.php" method="POST" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete_age_group">
    <input type="hidden" name="id" id="m-delete-age-id" value="">
</form>
<form id="m-delete-skill-form" action="process_admin_age_skill.php" method="POST" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete_skill_level">
    <input type="hidden" name="id" id="m-delete-skill-id" value="">
</form>

<script>
(function() {
    var fab = document.getElementById('m-ageskill-fab');
    var actionOverlay = document.getElementById('m-action-overlay');
    var actionSheet = document.getElementById('m-action-sheet');

    function openActionSheet() {
        actionOverlay.classList.add('m-active');
        actionSheet.classList.add('m-active');
    }
    function closeActionSheet() {
        actionOverlay.classList.remove('m-active');
        actionSheet.classList.remove('m-active');
    }

    fab.addEventListener('click', openActionSheet);
    actionOverlay.addEventListener('click', closeActionSheet);
    document.getElementById('m-action-cancel').addEventListener('click', closeActionSheet);

    // Age Group sheet
    var ageOverlay = document.getElementById('m-age-sheet-overlay');
    var ageSheet = document.getElementById('m-age-sheet');

    function openAgeSheet() {
        ageOverlay.classList.add('m-active');
        ageSheet.classList.add('m-active');
    }
    function closeAgeSheet() {
        ageOverlay.classList.remove('m-active');
        ageSheet.classList.remove('m-active');
    }
    function resetAgeForm() {
        document.getElementById('m-age-action').value = 'create_age_group';
        document.getElementById('m-age-id').value = '';
        document.getElementById('m-age-name').value = '';
        document.getElementById('m-age-min').value = '';
        document.getElementById('m-age-max').value = '';
        document.getElementById('m-age-desc').value = '';
        document.getElementById('m-age-order').value = '0';
        document.getElementById('m-age-sheet-title').textContent = 'Add Age Group';
        document.getElementById('m-age-submit-btn').innerHTML = '<i class="fas fa-plus"></i> Add Age Group';
    }

    document.getElementById('m-add-age-btn').addEventListener('click', function() {
        closeActionSheet();
        resetAgeForm();
        setTimeout(openAgeSheet, 150);
    });
    ageOverlay.addEventListener('click', closeAgeSheet);
    document.getElementById('m-age-sheet-close').addEventListener('click', closeAgeSheet);

    // Skill Level sheet
    var skillOverlay = document.getElementById('m-skill-sheet-overlay');
    var skillSheet = document.getElementById('m-skill-sheet');

    function openSkillSheet() {
        skillOverlay.classList.add('m-active');
        skillSheet.classList.add('m-active');
    }
    function closeSkillSheet() {
        skillOverlay.classList.remove('m-active');
        skillSheet.classList.remove('m-active');
    }
    function resetSkillForm() {
        document.getElementById('m-skill-action').value = 'create_skill_level';
        document.getElementById('m-skill-id').value = '';
        document.getElementById('m-skill-name').value = '';
        document.getElementById('m-skill-desc').value = '';
        document.getElementById('m-skill-order').value = '0';
        document.getElementById('m-skill-sheet-title').textContent = 'Add Skill Level';
        document.getElementById('m-skill-submit-btn').innerHTML = '<i class="fas fa-plus"></i> Add Skill Level';
    }

    document.getElementById('m-add-skill-btn').addEventListener('click', function() {
        closeActionSheet();
        resetSkillForm();
        setTimeout(openSkillSheet, 150);
    });
    skillOverlay.addEventListener('click', closeSkillSheet);
    document.getElementById('m-skill-sheet-close').addEventListener('click', closeSkillSheet);

    // Edit Age Group buttons
    document.querySelectorAll('.m-edit-age-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('m-age-action').value = 'update_age_group';
            document.getElementById('m-age-id').value = this.getAttribute('data-id');
            document.getElementById('m-age-name').value = this.getAttribute('data-name');
            document.getElementById('m-age-min').value = this.getAttribute('data-min-age');
            document.getElementById('m-age-max').value = this.getAttribute('data-max-age');
            document.getElementById('m-age-desc').value = this.getAttribute('data-description');
            document.getElementById('m-age-order').value = this.getAttribute('data-display-order');
            document.getElementById('m-age-sheet-title').textContent = 'Edit Age Group';
            document.getElementById('m-age-submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Age Group';
            openAgeSheet();
        });
    });

    // Edit Skill Level buttons
    document.querySelectorAll('.m-edit-skill-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('m-skill-action').value = 'update_skill_level';
            document.getElementById('m-skill-id').value = this.getAttribute('data-id');
            document.getElementById('m-skill-name').value = this.getAttribute('data-name');
            document.getElementById('m-skill-desc').value = this.getAttribute('data-description');
            document.getElementById('m-skill-order').value = this.getAttribute('data-display-order');
            document.getElementById('m-skill-sheet-title').textContent = 'Edit Skill Level';
            document.getElementById('m-skill-submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Skill Level';
            openSkillSheet();
        });
    });

    // Delete Age Group buttons
    document.querySelectorAll('.m-delete-age-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            var name = this.getAttribute('data-name');
            if (await showConfirmModal('Delete age group "' + name + '"? Sessions using it will have the field set to NULL.')) {
                document.getElementById('m-delete-age-id').value = this.getAttribute('data-id');
                document.getElementById('m-delete-age-form').submit();
            }
        });
    });

    // Delete Skill Level buttons
    document.querySelectorAll('.m-delete-skill-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            var name = this.getAttribute('data-name');
            if (await showConfirmModal('Delete skill level "' + name + '"? Sessions and teams using it will have the field set to NULL.')) {
                document.getElementById('m-delete-skill-id').value = this.getAttribute('data-id');
                document.getElementById('m-delete-skill-form').submit();
            }
        });
    });

    // Close sheets with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeActionSheet();
            closeAgeSheet();
            closeSkillSheet();
        }
    });
})();
</script>
