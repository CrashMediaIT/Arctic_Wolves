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
            margin-top: 30px;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 25px;
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
            margin-bottom: 25px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
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
            padding: 15px;
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
            padding: 15px;
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
            color: #7000a4;
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
    </style>

    <div class="age-skill-grid">
        <!-- Age Groups Section -->
        <div class="section-card">
            <h3><i class="fas fa-birthday-cake"></i> Age Groups</h3>
            
            <div class="add-form">
                <h4 style="color: white; margin-bottom: 15px;">Add New Age Group</h4>
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

            <h4 style="color: white; margin-bottom: 15px;">Existing Age Groups</h4>
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
                <h4 style="color: white; margin-bottom: 15px;">Add New Skill Level</h4>
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

            <h4 style="color: white; margin-bottom: 15px;">Existing Skill Levels</h4>
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
</div>
