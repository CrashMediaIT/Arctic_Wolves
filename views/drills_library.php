<!-- Drills Library View -->
<?php
// Fetch drills from database
try {
    // Get drill categories for filter
    $stmt = $pdo->query("SELECT id, name FROM drill_categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get drills with category info
    $stmt = $pdo->prepare("
        SELECT d.*, dc.name as category_name, u.first_name, u.last_name
        FROM drills d
        LEFT JOIN drill_categories dc ON d.category_id = dc.id
        LEFT JOIN users u ON d.created_by = u.id
        ORDER BY d.created_at DESC
    ");
    $stmt->execute();
    $drills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Drills fetch error: " . $e->getMessage());
    $categories = [];
    $drills = [];
}
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-book-open"></i> Drill Library
    </h1>
    <p class="page-description">Browse and search hockey drills</p>
</div>

<div class="drills-content">
    <!-- Actions Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input search-input" placeholder="Search drills..." data-search-table="drills">
            <select class="form-select" data-filter-table="drills" data-filter-column="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="action-buttons">
            <button class="btn btn-secondary" data-action="import" onclick="window.location='?page=import_drill'">
                <i class="fas fa-download"></i> Import from IHS
            </button>
            <button class="btn btn-primary" data-action="add" onclick="window.location='?page=create_drill'">
                <i class="fas fa-plus"></i> Create Drill
            </button>
        </div>
    </div>

    <!-- Drills Grid -->
    <div class="drills-grid" id="drills-grid">
        <?php if (count($drills) > 0): ?>
            <?php foreach ($drills as $drill): ?>
                <div class="drill-card" data-category="<?php echo $drill['category_id'] ?? ''; ?>">
                    <div class="drill-image">
                        <?php if ($drill['custom_image']): ?>
                            <img src="<?php echo htmlspecialchars($drill['custom_image']); ?>" alt="<?php echo htmlspecialchars($drill['title']); ?>">
                        <?php else: ?>
                            <div class="drill-diagram">
                                <i class="fas fa-hockey-puck"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="drill-content">
                        <div class="drill-header">
                            <h4 class="drill-title"><?php echo htmlspecialchars($drill['title']); ?></h4>
                            <?php if ($drill['category_name']): ?>
                                <div class="drill-category">
                                    <span class="category-badge"><?php echo htmlspecialchars($drill['category_name']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="drill-description">
                            <?php echo htmlspecialchars(substr($drill['description'] ?? 'No description available', 0, 120)); ?>
                            <?php echo strlen($drill['description'] ?? '') > 120 ? '...' : ''; ?>
                        </p>
                        <div class="drill-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($drill['first_name'] ?? 'Coach'); ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($drill['created_at'])); ?></span>
                            <?php if ($drill['ihs_source_url']): ?>
                                <span class="badge badge-info">IHS Import</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="drill-actions">
                        <button class="btn-sm btn-secondary" data-action="view" data-id="<?php echo $drill['id']; ?>">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn-icon" data-action="add-to-plan" data-id="<?php echo $drill['id']; ?>" title="Add to Practice">
                            <i class="fas fa-plus"></i>
                        </button>
                        <?php if ($drill['created_by'] == $user_id || in_array($user_role, ['admin', 'coach'])): ?>
                            <button class="btn-icon" data-action="edit" data-id="<?php echo $drill['id']; ?>" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list fa-3x"></i>
                <h3>No Drills Yet</h3>
                <p>Start building your drill library by creating or importing drills.</p>
                <button class="btn btn-primary" onclick="window.location='?page=create_drill'">
                    <i class="fas fa-plus"></i> Create Your First Drill
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    gap: 12px;
    flex: 1;
    min-width: 300px;
}

.action-buttons {
    display: flex;
    gap: 12px;
}

.drills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
}

.drill-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.drill-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 70, 193, 0.2);
}

.drill-image {
    position: relative;
    width: 100%;
    padding-top: 60%;
    background: var(--bg-main);
    overflow: hidden;
}

.drill-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.drill-diagram {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.1), rgba(124, 58, 237, 0.1));
}

.drill-diagram i {
    font-size: 48px;
    color: var(--primary);
    opacity: 0.3;
}

.drill-content {
    padding: 20px;
}

.drill-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    gap: 12px;
}

.drill-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    flex: 1;
    margin: 0;
}

.drill-category {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.category-badge {
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.drill-description {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 16px;
}

.drill-meta {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: var(--text-dim);
    padding-top: 16px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
}

.drill-meta i {
    color: var(--primary);
    margin-right: 6px;
}

.drill-actions {
    padding: 16px 20px;
    background: var(--bg-main);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 8px;
    align-items: center;
}

.btn-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-white);
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    background: rgba(107, 70, 193, 0.1);
    border-color: var(--primary);
    color: var(--primary);
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
    color: var(--text-dim);
}

.empty-state i {
    color: var(--primary);
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 24px;
    color: var(--text-white);
    margin-bottom: 12px;
}

.empty-state p {
    font-size: 14px;
    margin-bottom: 24px;
}

@media (max-width: 768px) {
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        flex-direction: column;
    }
    
    .drills-grid {
        grid-template-columns: 1fr;
    }
}
</style>
