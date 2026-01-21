<?php
/**
 * Nutrition Templates Library
 * View and assign nutrition templates to athletes
 */

require_once __DIR__ . '/../security.php';

// Check if user has permission to view library
if (!in_array($user_role, ['coach', 'coach_plus', 'admin'])) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get nutrition categories
$categories = $pdo->query("SELECT * FROM nutrition_plan_categories ORDER BY display_order")->fetchAll();

// Get filter
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : null;

// Get nutrition templates
$query = "
    SELECT nt.*, npc.name as category_name, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM nutrition_template_items WHERE template_id = nt.id) as food_count
    FROM nutrition_templates nt
    LEFT JOIN nutrition_plan_categories npc ON nt.category_id = npc.id
    LEFT JOIN users u ON nt.created_by_coach_id = u.id
";

if ($category_filter) {
    $query .= " WHERE nt.category_id = ?";
    $stmt = $pdo->prepare($query . " ORDER BY nt.created_at DESC");
    $stmt->execute([$category_filter]);
} else {
    $stmt = $pdo->query($query . " ORDER BY nt.created_at DESC");
}

$templates = $stmt->fetchAll();
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s;
    }
    .btn-create:hover {
        background: #e64500;
    }
    .filters-bar {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 30px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    .filter-btn {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
    }
    .filter-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }
    .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    .template-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        transition: all 0.2s;
    }
    .template-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .template-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }
    .template-category {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 15px;
    }
    .template-meta {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 8px;
    }
    .template-description {
        color: #94a3b8;
        font-size: 14px;
        margin: 15px 0;
        line-height: 1.6;
    }
    .btn-assign {
        width: 100%;
        padding: 10px;
        background: var(--primary);
        color: #fff;
        text-align: center;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        display: block;
        transition: all 0.2s;
    }
    .btn-assign:hover {
        background: #e64500;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    .empty-state i {
        font-size: 64px;
        color: #64748b;
        opacity: 0.3;
        margin-bottom: 20px;
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-book-open"></i> Nutrition Library
    </h1>
    <a href="?page=library&action=create_nutrition" class="btn-create">
        <i class="fas fa-plus"></i> Create Template
    </a>
</div>

<div class="filters-bar">
    <button class="filter-btn <?= !$category_filter ? 'active' : '' ?>" 
            onclick="window.location.href='?page=library_nutrition'">
        All Categories
    </button>
    <?php foreach ($categories as $cat): ?>
        <button class="filter-btn <?= $category_filter === $cat['id'] ? 'active' : '' ?>" 
                onclick="window.location.href='?page=library_nutrition&category=<?= $cat['id'] ?>'">
            <?= htmlspecialchars($cat['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<?php if (empty($templates)): ?>
    <div class="empty-state">
        <i class="fas fa-apple-whole"></i>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Templates Found</h2>
        <p style="color: #64748b;">Create your first nutrition template to get started</p>
    </div>
<?php else: ?>
    <div class="templates-grid">
        <?php foreach ($templates as $template): ?>
            <div class="template-card">
                <?php if ($template['category_name']): ?>
                    <span class="template-category"><?= htmlspecialchars($template['category_name']) ?></span>
                <?php endif; ?>
                
                <h3 class="template-title"><?= htmlspecialchars($template['title']) ?></h3>
                
                <div class="template-meta">
                    <i class="fas fa-utensils"></i>
                    <?= $template['food_count'] ?> food items
                </div>
                
                <?php if ($template['first_name']): ?>
                    <div class="template-meta">
                        <i class="fas fa-user"></i>
                        Created by <?= htmlspecialchars($template['first_name'] . ' ' . $template['last_name']) ?>
                    </div>
                <?php endif; ?>
                
                <div class="template-meta">
                    <i class="fas fa-calendar"></i>
                    <?= date('M d, Y', strtotime($template['created_at'])) ?>
                </div>
                
                <?php if ($template['description']): ?>
                    <div class="template-description">
                        <?= nl2br(htmlspecialchars($template['description'])) ?>
                    </div>
                <?php endif; ?>
                
                <a href="?page=library&action=assign_nutrition&template_id=<?= $template['id'] ?>" class="btn-assign">
                    <i class="fas fa-user-plus"></i> Assign to Athlete
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
