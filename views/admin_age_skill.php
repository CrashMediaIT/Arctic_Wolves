<?php
// views/admin_age_skill.php - Admin interface for managing age groups and skill levels
require_once __DIR__ . '/../security.php';

// Check permission
requirePermission($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 'manage_sessions');
?>

<div class="dash-content">
    <div class="dash-header">
        <h2><i class="fas fa-users-cog"></i> Manage Age Groups & Skill Levels</h2>
        <p style="color: rgba(255, 255, 255, 0.6);">Configure session filtering options</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php
            switch ($_GET['success']) {
                case 'age_group_created':
                    echo 'Age group created successfully!';
                    break;
                case 'age_group_updated':
                    echo 'Age group updated successfully!';
                    break;
                case 'age_group_deleted':
                    echo 'Age group deleted successfully!';
                    break;
                case 'skill_level_created':
                    echo 'Skill level created successfully!';
                    break;
                case 'skill_level_updated':
                    echo 'Skill level updated successfully!';
                    break;
                case 'skill_level_deleted':
                    echo 'Skill level deleted successfully!';
                    break;
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <style>
        .age-skill-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 24px;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 24px;
        }

        .section-card h3 {
            color: white;
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-card h3 i {
            color: var(--primary);
        }

        .add-form {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 12px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            color: white;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-add {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            background: #e64500;
            transform: scale(1.02);
        }

        .items-list {
            list-style: none;
            padding: 0;
        }

        .item-card {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-info h4 {
            color: white;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .item-info p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            margin: 3px 0;
        }

        .item-actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-icon.delete:hover {
            background: #dc3545;
            border-color: #dc3545;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.4);
            color: #5dff7f;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.4);
            color: #6B46C1;
        }

        @media (max-width: 1024px) {
            .age-skill-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modal-content {
            position: relative;
        }
    </style>

    <div class="age-skill-grid">
        <!-- Age Groups Section -->
        <div class="section-card">
            <h3><i class="fas fa-birthday-cake"></i> Age Groups</h3>
            
            <div class="add-form">
                <h4 style="color: white; margin-bottom: 12px;">Add New Age Group</h4>
                <form action="process_admin_age_skill.php" method="POST">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="create_age_group">
                    
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="name" required placeholder="e.g., Bantam (U14)">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Min Age</label>
                            <input type="number" name="min_age" placeholder="e.g., 13">
                        </div>
                        <div class="form-group">
                            <label>Max Age</label>
                            <input type="number" name="max_age" placeholder="e.g., 14">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Brief description..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" name="display_order" value="0" placeholder="0">
                    </div>
                    
                    <button type="submit" class="btn-add">
                        <i class="fas fa-plus"></i> Add Age Group
                    </button>
                </form>
            </div>

            <h4 style="color: white; margin-bottom: 12px;">Existing Age Groups</h4>
            <ul class="items-list">
                <?php
                $age_groups = $pdo->query("SELECT * FROM age_groups ORDER BY display_order ASC")->fetchAll();
                foreach ($age_groups as $ag):
                ?>
                <li class="item-card">
                    <div class="item-info">
                        <h4><?= htmlspecialchars($ag['name']) ?></h4>
                        <?php if ($ag['min_age'] || $ag['max_age']): ?>
                            <p>Ages: <?= $ag['min_age'] ?? '?' ?> - <?= $ag['max_age'] ?? '?' ?></p>
                        <?php endif; ?>
                        <?php if ($ag['description']): ?>
                            <p><?= htmlspecialchars($ag['description']) ?></p>
                        <?php endif; ?>
                        <p style="font-size: 0.8rem;">Order: <?= $ag['display_order'] ?></p>
                    </div>
                    <div class="item-actions">
                        <button type="button" class="btn-icon edit-age-group-btn" 
                                data-id="<?= $ag['id'] ?>" 
                                data-name="<?= htmlspecialchars($ag['name']) ?>" 
                                data-min-age="<?= $ag['min_age'] ?? '' ?>" 
                                data-max-age="<?= $ag['max_age'] ?? '' ?>" 
                                data-description="<?= htmlspecialchars($ag['description'] ?? '') ?>" 
                                data-display-order="<?= $ag['display_order'] ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="process_admin_age_skill.php" method="POST" style="display: inline;">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="delete_age_group">
                            <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                            <button type="submit" class="btn-icon delete" 
                                    onclick="return confirm('Delete this age group? Sessions using it will have the field set to NULL.')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Skill Levels Section -->
        <div class="section-card">
            <h3><i class="fas fa-chart-line"></i> Skill Levels</h3>
            
            <div class="add-form">
                <h4 style="color: white; margin-bottom: 12px;">Add New Skill Level</h4>
                <form action="process_admin_age_skill.php" method="POST">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="create_skill_level">
                    
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="name" required placeholder="e.g., Advanced">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Brief description..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" name="display_order" value="0" placeholder="0">
                    </div>
                    
                    <button type="submit" class="btn-add">
                        <i class="fas fa-plus"></i> Add Skill Level
                    </button>
                </form>
            </div>

            <h4 style="color: white; margin-bottom: 12px;">Existing Skill Levels</h4>
            <ul class="items-list">
                <?php
                $skill_levels = $pdo->query("SELECT * FROM skill_levels ORDER BY display_order ASC")->fetchAll();
                foreach ($skill_levels as $sl):
                ?>
                <li class="item-card">
                    <div class="item-info">
                        <h4><?= htmlspecialchars($sl['name']) ?></h4>
                        <?php if ($sl['description']): ?>
                            <p><?= htmlspecialchars($sl['description']) ?></p>
                        <?php endif; ?>
                        <p style="font-size: 0.8rem;">Order: <?= $sl['display_order'] ?></p>
                    </div>
                    <div class="item-actions">
                        <button type="button" class="btn-icon edit-skill-level-btn" 
                                data-id="<?= $sl['id'] ?>" 
                                data-name="<?= htmlspecialchars($sl['name']) ?>" 
                                data-description="<?= htmlspecialchars($sl['description'] ?? '') ?>" 
                                data-display-order="<?= $sl['display_order'] ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="process_admin_age_skill.php" method="POST" style="display: inline;">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="delete_skill_level">
                            <input type="hidden" name="id" value="<?= $sl['id'] ?>">
                            <button type="submit" class="btn-icon delete" 
                                    onclick="return confirm('Delete this skill level? Sessions and teams using it will have the field set to NULL.')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Edit Age Group Modal -->
    <div id="edit-age-group-modal" class="modal" style="display: none;" role="dialog" aria-hidden="true" aria-labelledby="edit-age-group-title">
        <div class="modal-content" style="background: rgba(20, 20, 30, 0.98); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 12px; max-width: 500px; margin: 100px auto; padding: 0;">
            <div style="padding: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h3 id="edit-age-group-title" style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-birthday-cake"></i> Edit Age Group
                </h3>
                <button type="button" class="close-edit-age-group" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: rgba(255, 255, 255, 0.6); font-size: 24px; cursor: pointer;" aria-label="Close modal">&times;</button>
            </div>
            <form action="process_admin_age_skill.php" method="POST" style="padding: 24px;">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="update_age_group">
                <input type="hidden" name="id" id="edit-age-group-id">
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="edit-age-group-name" required placeholder="e.g., Bantam (U14)">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Min Age</label>
                        <input type="number" name="min_age" id="edit-age-group-min-age" placeholder="e.g., 13">
                    </div>
                    <div class="form-group">
                        <label>Max Age</label>
                        <input type="number" name="max_age" id="edit-age-group-max-age" placeholder="e.g., 14">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit-age-group-description" placeholder="Brief description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="edit-age-group-display-order" value="0" placeholder="0">
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn-icon close-edit-age-group" style="padding: 10px 20px;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-add">
                        <i class="fas fa-save"></i> Update Age Group
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Skill Level Modal -->
    <div id="edit-skill-level-modal" class="modal" style="display: none;" role="dialog" aria-hidden="true" aria-labelledby="edit-skill-level-title">
        <div class="modal-content" style="background: rgba(20, 20, 30, 0.98); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 12px; max-width: 500px; margin: 100px auto; padding: 0;">
            <div style="padding: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h3 id="edit-skill-level-title" style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-line"></i> Edit Skill Level
                </h3>
                <button type="button" class="close-edit-skill-level" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: rgba(255, 255, 255, 0.6); font-size: 24px; cursor: pointer;" aria-label="Close modal">&times;</button>
            </div>
            <form action="process_admin_age_skill.php" method="POST" style="padding: 24px;">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="update_skill_level">
                <input type="hidden" name="id" id="edit-skill-level-id">
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="edit-skill-level-name" required placeholder="e.g., Advanced">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit-skill-level-description" placeholder="Brief description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="edit-skill-level-display-order" value="0" placeholder="0">
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn-icon close-edit-skill-level" style="padding: 10px 20px;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-add">
                        <i class="fas fa-save"></i> Update Skill Level
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        // Edit Age Group - Event Listeners
        document.querySelectorAll('.edit-age-group-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var name = this.getAttribute('data-name');
                var minAge = this.getAttribute('data-min-age');
                var maxAge = this.getAttribute('data-max-age');
                var description = this.getAttribute('data-description');
                var displayOrder = this.getAttribute('data-display-order');
                
                document.getElementById('edit-age-group-id').value = id;
                document.getElementById('edit-age-group-name').value = name;
                document.getElementById('edit-age-group-min-age').value = minAge || '';
                document.getElementById('edit-age-group-max-age').value = maxAge || '';
                document.getElementById('edit-age-group-description').value = description || '';
                document.getElementById('edit-age-group-display-order').value = displayOrder;
                
                var modal = document.getElementById('edit-age-group-modal');
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
                
                // Set focus to first input for accessibility
                setTimeout(function() {
                    document.getElementById('edit-age-group-name').focus();
                }, 100);
            });
        });

        // Close Age Group Modal
        function closeAgeGroupModal() {
            var modal = document.getElementById('edit-age-group-modal');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        
        document.querySelectorAll('.close-edit-age-group').forEach(function(btn) {
            btn.addEventListener('click', closeAgeGroupModal);
        });

        // Edit Skill Level - Event Listeners
        document.querySelectorAll('.edit-skill-level-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var name = this.getAttribute('data-name');
                var description = this.getAttribute('data-description');
                var displayOrder = this.getAttribute('data-display-order');
                
                document.getElementById('edit-skill-level-id').value = id;
                document.getElementById('edit-skill-level-name').value = name;
                document.getElementById('edit-skill-level-description').value = description || '';
                document.getElementById('edit-skill-level-display-order').value = displayOrder;
                
                var modal = document.getElementById('edit-skill-level-modal');
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
                
                // Set focus to first input for accessibility
                setTimeout(function() {
                    document.getElementById('edit-skill-level-name').focus();
                }, 100);
            });
        });

        // Close Skill Level Modal
        function closeSkillLevelModal() {
            var modal = document.getElementById('edit-skill-level-modal');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        
        document.querySelectorAll('.close-edit-skill-level').forEach(function(btn) {
            btn.addEventListener('click', closeSkillLevelModal);
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            var ageGroupModal = document.getElementById('edit-age-group-modal');
            var skillLevelModal = document.getElementById('edit-skill-level-modal');
            
            if (event.target === ageGroupModal) {
                closeAgeGroupModal();
            }
            if (event.target === skillLevelModal) {
                closeSkillLevelModal();
            }
        });
        
        // Close modals with Escape key for keyboard accessibility
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                var ageGroupModal = document.getElementById('edit-age-group-modal');
                var skillLevelModal = document.getElementById('edit-skill-level-modal');
                
                if (ageGroupModal.style.display === 'block') {
                    closeAgeGroupModal();
                }
                if (skillLevelModal.style.display === 'block') {
                    closeSkillLevelModal();
                }
            }
        });
    })();
    </script>
</div>
