<?php
/**
 * Session Templates Library
 * View and manage session templates
 */

require_once __DIR__ . '/../security.php';

// Check if user has permission
if (!in_array($user_role, ['coach', 'coach_plus', 'admin'])) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get filter parameters
$age_group_filter = isset($_GET['age_group']) ? $_GET['age_group'] : '';
$session_type_filter = isset($_GET['session_type']) ? $_GET['session_type'] : '';

// Build query
$query = "SELECT * FROM session_templates WHERE 1=1";
$params = [];

if ($age_group_filter) {
    $query .= " AND age_group = ?";
    $params[] = $age_group_filter;
}

if ($session_type_filter) {
    $query .= " AND session_type = ?";
    $params[] = $session_type_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$templates = $stmt->fetchAll();

// Get unique age groups and session types for filters
$age_groups = $pdo->query("SELECT DISTINCT age_group FROM session_templates WHERE age_group IS NOT NULL ORDER BY age_group")->fetchAll();
$session_types = $pdo->query("SELECT DISTINCT session_type FROM session_templates WHERE session_type IS NOT NULL ORDER BY session_type")->fetchAll();
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
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    .filter-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    .filter-select {
        width: 100%;
        padding: 10px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
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
    .template-header {
        margin-bottom: 15px;
    }
    .template-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }
    .template-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 15px;
    }
    .template-badge {
        background: var(--primary);
        color: #fff;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 700;
    }
    .template-description {
        color: #94a3b8;
        font-size: 14px;
        margin: 15px 0;
        line-height: 1.6;
    }
    .template-plan {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin: 15px 0;
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.6;
        max-height: 150px;
        overflow-y: auto;
        white-space: pre-wrap;
    }
    .template-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 15px;
    }
    .btn-action {
        padding: 10px;
        background: var(--primary);
        color: #fff;
        text-align: center;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s;
    }
    .btn-action:hover {
        background: #e64500;
    }
    .btn-action.secondary {
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
    }
    .btn-action.secondary:hover {
        background: var(--primary);
        color: #fff;
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
        <i class="fas fa-book-open"></i> Session Templates
    </h1>
    <a href="?page=library&action=create_session_template" class="btn-create">
        <i class="fas fa-plus"></i> Create Template
    </a>
</div>

<div class="filters-bar">
    <div class="filter-group">
        <label class="filter-label">Age Group</label>
        <select class="filter-select" onchange="applyFilters(this, 'age_group')">
            <option value="">All Age Groups</option>
            <?php foreach ($age_groups as $ag): ?>
                <option value="<?= htmlspecialchars($ag['age_group']) ?>" 
                        <?= $age_group_filter === $ag['age_group'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ag['age_group']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label class="filter-label">Session Type</label>
        <select class="filter-select" onchange="applyFilters(this, 'session_type')">
            <option value="">All Types</option>
            <?php foreach ($session_types as $st): ?>
                <option value="<?= htmlspecialchars($st['session_type']) ?>" 
                        <?= $session_type_filter === $st['session_type'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($st['session_type']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if (empty($templates)): ?>
    <div class="empty-state">
        <i class="fas fa-clipboard-list"></i>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Templates Found</h2>
        <p style="color: #64748b;">Create your first session template to get started</p>
    </div>
<?php else: ?>
    <div class="templates-grid">
        <?php foreach ($templates as $template): ?>
            <div class="template-card">
                <div class="template-header">
                    <h3 class="template-title"><?= htmlspecialchars($template['title']) ?></h3>
                    
                    <div class="template-badges">
                        <?php if ($template['session_type']): ?>
                            <span class="template-badge">
                                <?= htmlspecialchars($template['session_type']) ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($template['age_group']): ?>
                            <span class="template-badge" style="background: #10b981;">
                                <?= htmlspecialchars($template['age_group']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($template['description']): ?>
                    <div class="template-description">
                        <?= nl2br(htmlspecialchars($template['description'])) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($template['session_plan']): ?>
                    <div class="template-plan">
                        <?= nl2br(htmlspecialchars($template['session_plan'])) ?>
                    </div>
                <?php endif; ?>
                
                <div class="template-actions">
                    <a href="?page=create_session&template_id=<?= $template['id'] ?>" class="btn-action">
                        <i class="fas fa-calendar-plus"></i> Use Template
                    </a>
                    <a href="?page=library&action=edit_session_template&id=<?= $template['id'] ?>" class="btn-action secondary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function applyFilters(select, filterType) {
    const url = new URL(window.location);
    const value = select.value;
    
    if (value) {
        url.searchParams.set(filterType, value);
    } else {
        url.searchParams.delete(filterType);
    }
    
    window.location = url.toString();
}
</script>
