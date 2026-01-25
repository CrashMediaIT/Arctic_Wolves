<?php
// Fetch recently imported drills
$recentImportsQuery = "SELECT d.*, u.first_name, u.last_name 
    FROM drills d 
    LEFT JOIN users u ON d.created_by = u.id 
    WHERE d.ihs_source_url IS NOT NULL 
    ORDER BY d.created_at DESC 
    LIMIT 5";
$recentImports = $pdo->query($recentImportsQuery);

// Sample IHS-style drills library (in production, this would come from an external API)
$sampleDrills = [
    [
        'id' => 'IHS-SK-001',
        'name' => 'Swedish 5-Puck Weave',
        'category' => 'Skating',
        'level' => 'Intermediate',
        'duration' => 12,
        'description' => 'Classic Swedish drill focusing on edge work, puck control, and agility through cone weaving patterns.'
    ],
    [
        'id' => 'IHS-SH-042',
        'name' => 'One-Timer Power Play Setup',
        'category' => 'Shooting',
        'level' => 'Advanced',
        'duration' => 15,
        'description' => 'Develops quick release and power play positioning with emphasis on timing and accuracy.'
    ],
    [
        'id' => 'IHS-PA-017',
        'name' => 'Breakout Passing Circuit',
        'category' => 'Passing',
        'level' => 'Intermediate',
        'duration' => 10,
        'description' => 'Three-station drill for practicing breakout passes, D-to-D movement, and quick transitions.'
    ],
    [
        'id' => 'IHS-SK-023',
        'name' => 'Backward Crossover Drill',
        'category' => 'Skating',
        'level' => 'Beginner',
        'duration' => 8,
        'description' => 'Fundamental backward skating drill focusing on crossover technique and balance.'
    ],
    [
        'id' => 'IHS-TP-008',
        'name' => '2-on-1 Rush with Back Pressure',
        'category' => 'Team Play',
        'level' => 'Advanced',
        'duration' => 20,
        'description' => 'Full speed odd-man rush drill with back checking forward applying pressure.'
    ],
    [
        'id' => 'IHS-GL-003',
        'name' => 'Goalie Lateral Movement',
        'category' => 'Goalie',
        'level' => 'Intermediate',
        'duration' => 15,
        'description' => 'Quick lateral push-offs across the crease with shot tracking exercises.'
    ],
];
?>
<!-- Import Drill from IHS View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-file-import"></i> Import from IHS
    </h1>
    <p class="page-description">Import drills from Ice Hockey Systems database</p>
</div>

<div class="import-content">
    <!-- IHS Connection Status -->
    <div class="connection-status-card">
        <div class="status-icon connected">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="status-details">
            <h3>Drill Library Ready</h3>
            <p>Access to sample hockey drills for import</p>
        </div>
        <button class="btn-secondary" onclick="refreshDrillList()"><i class="fas fa-sync"></i> Refresh</button>
    </div>

    <!-- Search and Filter Box -->
    <div class="filter-box">
        <div class="filter-box-header">
            <i class="fas fa-search"></i> Search IHS Drills
        </div>
        <div class="filter-box-content">
            <div class="filter-row">
                <div class="filter-field" style="flex: 2;">
                    <label>Search by Name</label>
                    <input type="text" class="form-input" id="ihs-search-input" placeholder="Search by drill name or keyword..." onkeyup="filterIHSDrills()">
                </div>
                <div class="filter-field">
                    <label>Category</label>
                    <select class="form-select" id="ihs-category-filter" onchange="filterIHSDrills()">
                        <option value="">All Categories</option>
                        <option value="Skating">Skating</option>
                        <option value="Shooting">Shooting</option>
                        <option value="Passing">Passing</option>
                        <option value="Team Play">Team Play</option>
                        <option value="Goalie">Goalie</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Skill Level</label>
                    <select class="form-select" id="ihs-level-filter" onchange="filterIHSDrills()">
                        <option value="">All Skill Levels</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Results -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Available Drills</h3>
            <div class="results-info">
                <span id="ihs-results-count">Showing <?= count($sampleDrills) ?> drills</span>
            </div>
        </div>
        <div class="card-body">
            <div class="ihs-drill-list" id="ihs-drill-list">
                <?php foreach ($sampleDrills as $drill): ?>
                <div class="ihs-drill-item" 
                     data-name="<?= strtolower(htmlspecialchars($drill['name'])) ?>"
                     data-category="<?= htmlspecialchars($drill['category']) ?>"
                     data-level="<?= htmlspecialchars($drill['level']) ?>">
                    <div class="drill-preview">
                        <div class="drill-thumbnail">
                            <i class="fas fa-hockey-puck"></i>
                        </div>
                        <span class="drill-id"><?= htmlspecialchars($drill['id']) ?></span>
                    </div>
                    <div class="drill-info">
                        <h4><?= htmlspecialchars($drill['name']) ?></h4>
                        <div class="drill-tags">
                            <span class="tag-category"><?= htmlspecialchars($drill['category']) ?></span>
                            <span class="tag-level"><?= htmlspecialchars($drill['level']) ?></span>
                            <span class="tag-duration"><i class="fas fa-clock"></i> <?= $drill['duration'] ?> min</span>
                        </div>
                        <p class="drill-preview-text"><?= htmlspecialchars($drill['description']) ?></p>
                    </div>
                    <div class="drill-import-actions">
                        <button class="btn-secondary" onclick="previewDrill('<?= $drill['id'] ?>')"><i class="fas fa-eye"></i> Preview</button>
                        <form method="POST" action="process_drills.php" style="display: inline;">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="import_ihs">
                            <input type="hidden" name="ihs_id" value="<?= htmlspecialchars($drill['id']) ?>">
                            <input type="hidden" name="drill_name" value="<?= htmlspecialchars($drill['name']) ?>">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($drill['category']) ?>">
                            <input type="hidden" name="description" value="<?= htmlspecialchars($drill['description']) ?>">
                            <input type="hidden" name="duration" value="<?= $drill['duration'] ?>">
                            <input type="hidden" name="skill_level" value="<?= htmlspecialchars($drill['level']) ?>">
                            <button type="submit" class="btn-primary"><i class="fas fa-download"></i> Import</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recently Imported -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recently Imported</h3>
        </div>
        <div class="card-body">
            <div class="recent-imports-list">
                <?php if($recentImports && $recentImports->rowCount() > 0): ?>
                    <?php while($import = $recentImports->fetch()): ?>
                        <div class="import-history-item">
                            <div class="import-icon">
                                <i class="fas fa-file-import"></i>
                            </div>
                            <div class="import-info">
                                <h4><?= htmlspecialchars($import['title'] ?? $import['drill_name'] ?? 'Imported Drill') ?></h4>
                                <span class="import-meta">
                                    Imported by <?= htmlspecialchars(($import['first_name'] ?? '') . ' ' . ($import['last_name'] ?? '')) ?> 
                                    on <?= date('M j, Y', strtotime($import['created_at'])) ?>
                                </span>
                            </div>
                            <a href="?page=drill_library" class="btn-secondary btn-small"><i class="fas fa-eye"></i> View</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="placeholder-text">No recent imports. Drills you import will appear here.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function filterIHSDrills() {
    const searchText = document.getElementById('ihs-search-input').value.toLowerCase();
    const categoryFilter = document.getElementById('ihs-category-filter').value;
    const levelFilter = document.getElementById('ihs-level-filter').value;
    
    const drillItems = document.querySelectorAll('.ihs-drill-item');
    let visibleCount = 0;
    
    drillItems.forEach(item => {
        const name = item.dataset.name || '';
        const category = item.dataset.category || '';
        const level = item.dataset.level || '';
        
        let visible = true;
        
        if (searchText && !name.includes(searchText)) {
            visible = false;
        }
        
        if (categoryFilter && category !== categoryFilter) {
            visible = false;
        }
        
        if (levelFilter && level !== levelFilter) {
            visible = false;
        }
        
        item.style.display = visible ? 'flex' : 'none';
        if (visible) visibleCount++;
    });
    
    document.getElementById('ihs-results-count').textContent = 'Showing ' + visibleCount + ' drills';
}

function previewDrill(drillId) {
    // Find the drill item and show more details
    alert('Preview functionality coming soon. For now, review the drill information and click Import to add it to your library.');
}

function refreshDrillList() {
    // In a real implementation, this would fetch new drills from an API
    location.reload();
}
</script>

<style>
.connection-status-card {
    background: var(--bg-card);
    border: 1px solid #10b981;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.status-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}

.status-icon.connected {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-icon.disconnected {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-details {
    flex: 1;
}

.status-details h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.status-details p {
    font-size: 14px;
    color: var(--text-dim);
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.filter-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.results-info {
    font-size: 14px;
    color: var(--text-dim);
}

.ihs-drill-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.ihs-drill-item {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    align-items: center;
    transition: all 0.3s;
}

.ihs-drill-item:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.drill-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.drill-thumbnail {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
}

.drill-thumbnail i {
    font-size: 36px;
    color: var(--neon);
    opacity: 0.5;
}

.drill-id {
    font-size: 11px;
    color: var(--text-dim);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.drill-info {
    flex: 1;
}

.drill-info h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.drill-tags {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.tag-category {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.tag-level {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.tag-duration {
    color: var(--text-dim);
    font-size: 12px;
    padding: 4px 0;
}

.tag-duration i {
    color: var(--neon);
    margin-right: 5px;
}

.drill-preview-text {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.5;
}

.drill-import-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.recent-imports-list {
    min-height: 100px;
}

.import-history-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 12px;
}

.import-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 77, 0, 0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--neon);
    flex-shrink: 0;
}

.import-info {
    flex: 1;
}

.import-info h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 4px;
}

.import-meta {
    font-size: 12px;
    color: var(--text-dim);
}

/* Filter Box Styles */
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
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    align-items: end;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-field label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-field .form-input,
.filter-field .form-select {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    font-size: 14px;
}

.filter-field .form-input:focus,
.filter-field .form-select:focus {
    outline: none;
    border-color: var(--primary);
}
</style>
