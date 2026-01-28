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
    LEFT JOIN users u ON nt.created_by = u.id
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
/* Nutrition Page Header - Financial Reports Hub Style */
.nutrition-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.nutrition-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.nutrition-page-header .page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), #5a0080);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}
.nutrition-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.nutrition-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}
.nutrition-page-header .btn-create {
    background: var(--primary);
    color: #fff;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.nutrition-page-header .btn-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(107, 70, 193, 0.3);
}

/* Filter Box - Financial Reports Hub Style */
.filter-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
}
.filter-box-header {
    background: var(--bg-main);
    padding: 14px 20px;
    font-weight: 700;
    color: var(--text-white);
    font-size: 14px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}
.filter-box-header i {
    color: var(--primary);
}
.filter-box-content {
    padding: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.filter-btn {
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-dim);
    padding: 10px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
}
.filter-btn:hover {
    background: rgba(139, 92, 246, 0.1);
    border-color: var(--primary);
    color: var(--text-white);
}
.filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

/* Templates Grid - Financial Reports Hub Style */
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}
.template-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    transition: all 0.3s;
}
.template-card:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}
.template-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}
.template-category {
    display: inline-block;
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 12px;
}
.template-meta {
    font-size: 13px;
    color: var(--text-dim);
    margin-bottom: 8px;
}
.template-description {
    color: var(--text-dim);
    font-size: 14px;
    margin: 12px 0;
    line-height: 1.6;
}
.btn-assign {
    width: 100%;
    padding: 12px;
    background: var(--primary);
    color: #fff;
    text-align: center;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    display: block;
    transition: all 0.3s;
}
.btn-assign:hover {
    background: rgba(107, 70, 193, 0.9);
    transform: translateY(-2px);
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
}
.empty-state i {
    font-size: 64px;
    color: var(--text-dim);
    opacity: 0.3;
    margin-bottom: 20px;
}
</style>

<div class="nutrition-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-utensils"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Nutrition Library</h1>
            <p class="page-description">Create and manage nutrition templates for athletes</p>
        </div>
    </div>
    <a href="?page=library&action=create_nutrition" class="btn-create">
        <i class="fas fa-plus"></i> Create Template
    </a>
</div>

<div class="filter-box">
    <div class="filter-box-header">
        <i class="fas fa-filter"></i> Filter by Category
    </div>
    <div class="filter-box-content">
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
</div>

<?php if (empty($templates)): ?>
    <div class="empty-state">
        <i class="fas fa-apple-whole"></i>
        <h2 style="font-size: 24px; color: var(--text-white); margin-bottom: 10px;">No Templates Found</h2>
        <p style="color: var(--text-dim);">Create your first nutrition template to get started</p>
    </div>
<?php else: ?>
    <div class="templates-grid">
        <?php foreach ($templates as $template): ?>
            <div class="template-card">
                <?php if ($template['category_name']): ?>
                    <span class="template-category"><?= htmlspecialchars($template['category_name']) ?></span>
                <?php endif; ?>
                
                <h3 class="template-title"><?= htmlspecialchars($template['name']) ?></h3>
                
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
