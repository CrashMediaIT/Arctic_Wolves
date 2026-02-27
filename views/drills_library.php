<!-- Drills Library View -->
<?php
require_once __DIR__ . '/../lib/image_helper.php';
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
    $drills = decryptUserRows($drills);
} catch (PDOException $e) {
    error_log("Drills fetch error: " . $e->getMessage());
    $categories = [];
    $drills = [];
}

// Fetch center ice logo URL from theme settings for drill thumbnails
$centerLogoUrl = '';
try {
    $logoStmt = $pdo->prepare("
        SELECT COALESCE(
            MAX(CASE WHEN setting_name = 'center_ice_logo_url' AND setting_value != '' THEN setting_value END),
            MAX(CASE WHEN setting_name = 'logo_url' AND setting_value != '' THEN setting_value END)
        ) as logo_url 
        FROM theme_settings 
        WHERE setting_name IN ('center_ice_logo_url', 'logo_url')
    ");
    $logoStmt->execute();
    $logoResult = $logoStmt->fetch(PDO::FETCH_ASSOC);
    if ($logoResult && !empty($logoResult['logo_url'])) {
        $centerLogoUrl = $logoResult['logo_url'];
    }
} catch (PDOException $e) {
    error_log("Error fetching center ice logo URL: " . $e->getMessage());
}

// No demo data - show empty state when no drills exist
$is_demo_drills = false;
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-book-open"></i> Drill Library
    </h1>
    <p class="page-description">Browse and search hockey drills</p>
</div>

<?php if (isset($is_demo_drills) && $is_demo_drills): ?>
<div class="demo-data-notice">
    <i class="fas fa-info-circle"></i>
    <span>Showing demo drills. Create or import drills to build your library.</span>
</div>
<?php endif; ?>

<div class="drills-content">
    <!-- Search Filter Box - Separated from title bar -->
    <div class="filter-box">
        <div class="filter-box-header">
            <i class="fas fa-filter"></i> Search & Filter Drills
        </div>
        <div class="filter-box-content">
            <div class="filter-row">
                <div class="filter-field">
                    <label>Search by Drill Name</label>
                    <input type="text" class="form-input" id="drill-search-name" placeholder="Enter drill name..." data-search-field="title">
                </div>
                <div class="filter-field">
                    <label>Search by Coach</label>
                    <input type="text" class="form-input" id="drill-search-coach" placeholder="Enter coach name..." data-search-field="coach">
                </div>
                <div class="filter-field">
                    <label>Category</label>
                    <select class="form-select" id="drill-filter-category" data-filter-column="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field filter-actions">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-primary" onclick="filterDrills()">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearDrillFilters()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="action-bar">
        <div class="results-info">
            <span id="drill-count-display"><?php echo count($drills); ?> drills found</span>
        </div>
        <div class="action-buttons">
            <button class="btn btn-secondary" data-action="view" data-page="import_drill">
                <i class="fas fa-download"></i> Import from IHS
            </button>
            <button class="btn btn-primary" data-action="view" data-page="create_drill">
                <i class="fas fa-plus"></i> Create Drill
            </button>
        </div>
    </div>

    <!-- Bulk Actions Bar (hidden until selections made) -->
    <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
        <div class="bulk-actions-info">
            <label class="bulk-select-all-label">
                <input type="checkbox" id="selectAllDrills" onchange="toggleSelectAllDrills(this)">
                <span>Select All</span>
            </label>
            <span id="bulkSelectedCount">0 selected</span>
        </div>
        <div class="bulk-actions-buttons">
            <button class="btn btn-primary btn-sm" id="bulkCreatePlanBtn" onclick="bulkCreatePracticePlan()">
                <i class="fas fa-clipboard-list"></i> Create Practice Plan
            </button>
            <button class="btn btn-danger btn-sm" id="bulkDeleteBtn" onclick="bulkDeleteDrills()">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>
    </div>

    <!-- Drills Grid -->
    <div class="drills-grid" id="drills-grid">
        <?php if (count($drills) > 0): ?>
            <?php foreach ($drills as $drill): 
                $coachName = htmlspecialchars(($drill['first_name'] ?? '') . ' ' . ($drill['last_name'] ?? ''));
                // Extract ice view from diagram data
                $drillIceView = 'full';
                if (!empty($drill['diagram_data'])) {
                    $diagramParsed = json_decode($drill['diagram_data'], true);
                    if (is_array($diagramParsed) && isset($diagramParsed['iceView'])) {
                        $drillIceView = $diagramParsed['iceView'];
                    }
                }
            ?>
                <div class="drill-card" 
                     data-drill-id="<?php echo $drill['id']; ?>"
                     data-category="<?php echo $drill['category_id'] ?? ''; ?>"
                     data-title="<?php echo htmlspecialchars(strtolower($drill['title'])); ?>"
                     data-coach="<?php echo strtolower($coachName); ?>">
                    <div class="drill-select-overlay">
                        <input type="checkbox" class="drill-select-checkbox" value="<?php echo $drill['id']; ?>" onchange="updateBulkSelection()">
                    </div>
                    <div class="drill-image" data-ice-view="<?php echo htmlspecialchars($drillIceView); ?>">
                        <?php if ($drill['custom_image']): ?>
                            <img src="<?php echo htmlspecialchars(resolveRustfsUrl($pdo, $drill['custom_image'])); ?>" alt="<?php echo htmlspecialchars($drill['title']); ?>">
                        <?php else: ?>
                            <div class="drill-diagram-preview" data-diagram='<?php echo htmlspecialchars($drill['diagram_data'] ?? '[]'); ?>' data-center-logo="<?php echo htmlspecialchars($centerLogoUrl); ?>">
                                <canvas class="drill-thumbnail-canvas"></canvas>
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
                            <span class="coach-name"><i class="fas fa-user"></i> <?php echo trim($coachName) ?: 'Coach'; ?></span>
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
                        <?php if (($drill['created_by'] ?? null) == $user_id || in_array($user_role, ['admin', 'coach'])): ?>
                            <button class="btn-icon" data-action="edit" data-id="<?php echo $drill['id']; ?>" data-modal="edit-drill-modal" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-icon-danger" data-action="delete" data-id="<?php echo $drill['id']; ?>" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list empty-state-icon"></i>
                <h3>No Drills Yet</h3>
                <p>Start building your drill library by creating or importing drills.</p>
                <button class="btn btn-primary" data-action="view" data-page="create_drill">
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

.bulk-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding: 12px 20px;
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid var(--primary);
    border-radius: 10px;
    flex-wrap: wrap;
}

.bulk-actions-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.bulk-select-all-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-white);
}

.bulk-select-all-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

#bulkSelectedCount {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
}

.bulk-actions-buttons {
    display: flex;
    gap: 10px;
}

.drill-select-overlay {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 10;
}

.drill-select-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--primary);
    border-radius: 4px;
}

.drill-card {
    position: relative;
}

.drill-card.selected {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(107, 70, 193, 0.3);
}

.drills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
}

.drill-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    max-width: 380px;
}

.drill-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 70, 193, 0.2);
}

.drill-image {
    position: relative;
    width: 100%;
    /* Default for full ice (200/85 ratio) - width/height ≈ 2.35, so height as % of width ≈ 42.5% */
    padding-top: 42.5%;
    background: var(--bg-main);
    overflow: hidden;
    transition: padding-top 0.3s ease-in-out;
}

/* Dynamic aspect ratios based on ice view for thumbnails */
/* Full ice: 200 ft × 85 ft (horizontal, net on left/right) - height/width = 85/200 = 42.5% */
.drill-image[data-ice-view="full"] {
    padding-top: 42.5%;
}

/* Zone views: 100 ft × 85 ft (horizontal, like half of full ice) - height/width = 85/100 = 85% */
.drill-image[data-ice-view="left-zone"],
.drill-image[data-ice-view="right-zone"] {
    padding-top: 85%;
}

/* Center ice: Reduced height for better card display in grid layouts
   Original was 118.1% (true aspect ratio), reduced to 75% to prevent cards from being excessively tall */
.drill-image[data-ice-view="center"] {
    padding-top: 75%;
}

.drill-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.drill-diagram-preview {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
    overflow: hidden;
}

.drill-diagram-preview canvas.drill-thumbnail-canvas {
    width: 100%;
    height: 100%;
    display: block;
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

.btn-icon-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: #EF4444;
    color: #EF4444;
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
    opacity: 0.5;
}

.empty-state .empty-state-icon {
    font-size: 64px;
    display: block;
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
    
    .filter-row {
        grid-template-columns: 1fr !important;
    }
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

.filter-actions {
    display: flex;
    flex-direction: row !important;
    gap: 8px !important;
    align-items: flex-end;
}

.filter-actions label {
    display: none;
}

.results-info {
    color: var(--text-dim);
    font-size: 14px;
}

.drill-card.hidden {
    display: none !important;
}

/* Demo Data Notice */
.demo-data-notice {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 12px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary-light);
    font-size: 14px;
}

.demo-data-notice i {
    font-size: 16px;
}
</style>

<script>
// Drill Library Search and Filter Functions
function filterDrills() {
    const nameSearch = document.getElementById('drill-search-name').value.toLowerCase().trim();
    const coachSearch = document.getElementById('drill-search-coach').value.toLowerCase().trim();
    const categoryFilter = document.getElementById('drill-filter-category').value;
    
    const drillCards = document.querySelectorAll('.drill-card');
    let visibleCount = 0;
    
    drillCards.forEach(card => {
        const title = card.dataset.title || '';
        const coach = card.dataset.coach || '';
        const category = card.dataset.category || '';
        
        let visible = true;
        
        // Filter by drill name
        if (nameSearch && !title.includes(nameSearch)) {
            visible = false;
        }
        
        // Filter by coach name
        if (coachSearch && !coach.includes(coachSearch)) {
            visible = false;
        }
        
        // Filter by category
        if (categoryFilter && category !== categoryFilter) {
            visible = false;
        }
        
        if (visible) {
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.classList.add('hidden');
        }
    });
    
    // Update count display
    const countDisplay = document.getElementById('drill-count-display');
    if (countDisplay) {
        countDisplay.textContent = visibleCount + ' drill' + (visibleCount !== 1 ? 's' : '') + ' found';
    }
    
    // Show/hide empty state
    const grid = document.getElementById('drills-grid');
    let emptyState = grid.querySelector('.search-empty-state');
    
    if (visibleCount === 0) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'empty-state search-empty-state';
            emptyState.innerHTML = `
                <i class="fas fa-search empty-state-icon"></i>
                <h3>No Drills Found</h3>
                <p>Try adjusting your search criteria or clear the filters.</p>
                <button class="btn btn-secondary" onclick="clearDrillFilters()">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            `;
            grid.appendChild(emptyState);
        }
        emptyState.style.display = 'block';
    } else if (emptyState) {
        emptyState.style.display = 'none';
    }
}

function clearDrillFilters() {
    document.getElementById('drill-search-name').value = '';
    document.getElementById('drill-search-coach').value = '';
    document.getElementById('drill-filter-category').value = '';
    
    // Show all drill cards
    const drillCards = document.querySelectorAll('.drill-card');
    drillCards.forEach(card => {
        card.classList.remove('hidden');
    });
    
    // Update count
    const countDisplay = document.getElementById('drill-count-display');
    if (countDisplay) {
        countDisplay.textContent = drillCards.length + ' drill' + (drillCards.length !== 1 ? 's' : '') + ' found';
    }
    
    // Remove search empty state
    const emptyState = document.querySelector('.search-empty-state');
    if (emptyState) {
        emptyState.style.display = 'none';
    }
}

// Add event listeners for real-time filtering
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('drill-search-name');
    const coachInput = document.getElementById('drill-search-coach');
    const categorySelect = document.getElementById('drill-filter-category');
    
    if (nameInput) {
        nameInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') filterDrills();
        });
    }
    
    if (coachInput) {
        coachInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') filterDrills();
        });
    }
    
    if (categorySelect) {
        categorySelect.addEventListener('change', filterDrills);
    }
});
</script>

<!-- Edit Drill Modal (for imported drills - text fields only) -->
<div id="edit-drill-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Drill</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-drill-modal')">&times;</button>
        </div>
        <form method="POST" action="process_drills.php" id="editDrillForm">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="save_drill">
            <input type="hidden" name="drill_id" id="editDrillId">
            
            <div class="modal-body">
                <div class="alert alert-info" style="margin-bottom: 16px; padding: 12px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px; font-size: 13px; color: var(--text-white);">
                    <i class="fas fa-info-circle" style="color: #3b82f6; margin-right: 8px;"></i>
                    <span>This drill was imported from an external source. You can edit the text fields below. The drill diagram cannot be modified.</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Drill Name *</label>
                    <input type="text" name="title" id="editDrillTitle" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" id="editDrillCategory" class="form-input">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                        <option value="Skating">Skating</option>
                        <option value="Shooting">Shooting</option>
                        <option value="Passing">Passing</option>
                        <option value="Stickhandling">Stickhandling</option>
                        <option value="Team Play">Team Play</option>
                        <option value="Goalie">Goalie</option>
                        <option value="Defensive">Defensive</option>
                        <option value="Offensive">Offensive</option>
                        <option value="Conditioning">Conditioning</option>
                        <option value="Puck Control">Puck Control</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="editDrillDescription" class="form-textarea" rows="5" placeholder="Describe the drill objectives and key points..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Setup Instructions</label>
                    <textarea name="setup" id="editDrillSetup" class="form-textarea" rows="3" placeholder="How to set up the drill..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Coaching Points</label>
                    <textarea name="coaching_points" id="editDrillCoachingPoints" class="form-textarea" rows="3" placeholder="Key coaching points to emphasize..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Progression</label>
                    <textarea name="progression" id="editDrillProgression" class="form-textarea" rows="3" placeholder="How to progress the drill..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Video URL (optional)</label>
                    <input type="url" name="video_url" id="editDrillVideoUrl" class="form-input" placeholder="https://youtube.com/watch?v=... or other video URL">
                    <p class="help-text" style="font-size: 11px; color: var(--text-dim); margin-top: 4px;">
                        <i class="fas fa-info-circle"></i> YouTube links will be automatically embedded when viewing the drill.
                    </p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-drill-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- View Drill Modal -->
<div id="view-drill-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-hockey-puck"></i> <span id="viewDrillTitle">Drill Details</span></h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('view-drill-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="viewDrillContent">
                <div class="drill-detail-section">
                    <h4>Category</h4>
                    <p id="viewDrillCategory">-</p>
                </div>
                <div class="drill-detail-section">
                    <h4>Description</h4>
                    <p id="viewDrillDescription">-</p>
                </div>
                <div class="drill-detail-section" id="viewDrillVideoSection" style="display: none;">
                    <h4>Video</h4>
                    <a href="#" id="viewDrillVideoLink" target="_blank" class="btn btn-secondary"><i class="fas fa-play"></i> Watch Video</a>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('view-drill-modal')"><i class="fas fa-times"></i> Close</button>
            <button type="button" class="btn btn-primary" onclick="editDrillFromView()"><i class="fas fa-edit"></i> Edit Drill</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-confirm-modal" class="modal">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-exclamation-triangle" style="color: #EF4444;"></i> Confirm Delete</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeDeleteConfirm()">&times;</button>
        </div>
        <div class="modal-body">
            <p id="deleteConfirmMessage" style="font-size: 15px; color: var(--text-white); margin: 0;">Are you sure?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirm()"><i class="fas fa-times"></i> Cancel</button>
            <button type="button" class="btn btn-danger" id="deleteConfirmBtn" onclick="executeDeleteConfirm()"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
</div>

<!-- Shared Ice Canvas Renderer - ensures consistent rink drawing across all views -->
<script src="js/ice_canvas.js"></script>
<script>
// Use shared NHL_RINK constants from ice_canvas.js
const NHL_RINK = window.ICE_CANVAS_NHL_RINK || {
    GOAL_LINE: 11 / 200,
    BLUE_LINE: 64 / 200,
    FACEOFF_RADIUS: 15 / 85,
    CENTER_CIRCLE_RADIUS: 15 / 85,
    CREASE_RADIUS: 6 / 85,
    FACEOFF_FROM_GOAL: 20 / 200,
    FACEOFF_FROM_BOARDS: 22 / 85,
    TRAPEZOID_BASE: 22 / 85,
    TRAPEZOID_TOP: 28 / 85,
    RESTRAINT_LINE_LENGTH: 2 / 85,
    CORNER_RADIUS: 28 / 85
};

// View drill details
let currentViewDrillId = null;
let drillsData = <?php echo json_encode($drills); ?>;

// Local modal functions (fallback if global ones not loaded)
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
}

function viewDrill(drillId) {
    const drill = drillsData.find(d => d.id == drillId);
    if (!drill) return;
    
    currentViewDrillId = drillId;
    document.getElementById('viewDrillTitle').textContent = drill.title || 'Drill Details';
    document.getElementById('viewDrillCategory').textContent = drill.category_name || 'General';
    document.getElementById('viewDrillDescription').textContent = drill.description || 'No description available.';
    
    const videoSection = document.getElementById('viewDrillVideoSection');
    const videoLink = document.getElementById('viewDrillVideoLink');
    if (drill.video_url) {
        videoSection.style.display = 'block';
        videoLink.href = drill.video_url;
    } else {
        videoSection.style.display = 'none';
    }
    
    openModal('view-drill-modal');
}

function editDrillFromView() {
    if (currentViewDrillId) {
        closeModal('view-drill-modal');
        loadDrillForEdit(currentViewDrillId);
    }
}

function loadDrillForEdit(drillId) {
    const drill = drillsData.find(d => d.id == drillId);
    if (!drill) return;
    
    // Check if this is an imported drill (has ihs_source_url or custom_image but no diagram_data)
    const isImported = drill.ihs_source_url || (drill.custom_image && !drill.diagram_data);
    
    if (isImported) {
        // For imported drills, open the text-only edit modal instead of drill designer
        openImportedDrillEditModal(drill);
    } else {
        // For regular drills, redirect to drill designer with edit mode
        // Store drill data in sessionStorage for the designer to pick up
        sessionStorage.setItem('editDrill', JSON.stringify(drill));
        window.location.href = '?page=create_drill&edit=' + drillId;
    }
}

// Open edit modal for imported drills (text fields only)
function openImportedDrillEditModal(drill) {
    document.getElementById('editDrillId').value = drill.id;
    document.getElementById('editDrillTitle').value = drill.title || '';
    document.getElementById('editDrillDescription').value = drill.description || '';
    document.getElementById('editDrillVideoUrl').value = drill.video_url || '';
    
    // Populate additional fields for imported drills
    const setupField = document.getElementById('editDrillSetup');
    if (setupField) setupField.value = drill.setup || '';
    
    const coachingPointsField = document.getElementById('editDrillCoachingPoints');
    if (coachingPointsField) coachingPointsField.value = drill.coaching_points || '';
    
    const progressionField = document.getElementById('editDrillProgression');
    if (progressionField) progressionField.value = drill.progression || '';
    
    // Set category
    const categorySelect = document.getElementById('editDrillCategory');
    if (categorySelect && drill.category_name) {
        // Try to find and select the matching category
        for (let option of categorySelect.options) {
            if (option.text === drill.category_name || option.value === drill.category_name) {
                option.selected = true;
                break;
            }
        }
    }
    
    // Update modal title to indicate imported drill
    const modalTitle = document.querySelector('#edit-drill-modal .modal-title');
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Imported Drill';
    }
    
    // Show the modal
    const modal = document.getElementById('edit-drill-modal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }
}

// Handle view and edit button clicks
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-action="view"]').forEach(btn => {
        const drillId = btn.getAttribute('data-id');
        if (drillId) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                // Redirect to detailed drill view page
                window.location.href = '?page=view_drill&id=' + drillId;
            });
        }
    });
    
    document.querySelectorAll('[data-action="edit"]').forEach(btn => {
        const drillId = btn.getAttribute('data-id');
        if (drillId) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                loadDrillForEdit(drillId);
            });
        }
    });
    
    document.querySelectorAll('[data-action="add-to-plan"]').forEach(btn => {
        const drillId = btn.getAttribute('data-id');
        if (drillId) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                addDrillToPracticePlan(drillId);
            });
        }
    });
    
    document.querySelectorAll('[data-action="delete"]').forEach(btn => {
        const drillId = btn.getAttribute('data-id');
        if (drillId) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                deleteDrill(drillId);
            });
        }
    });
    
    // Render ice rink thumbnails for drills
    renderDrillThumbnails();
});

// Add drill to practice plan
function addDrillToPracticePlan(drillId) {
    // Find the drill data
    const drill = drillsData.find(d => d.id === drillId);
    if (!drill) {
        // Show error using existing notification system if available
        if (typeof showNotification === 'function') {
            showNotification('Error: Drill not found', 'error');
        } else {
            console.error('Drill not found:', drillId);
        }
        return;
    }
    
    // Store drill ID in sessionStorage to be picked up by practice plan page
    const drillsToAdd = sessionStorage.getItem('drillsToAdd');
    let drillIds = drillsToAdd ? JSON.parse(drillsToAdd) : [];
    
    // Avoid duplicates
    if (!drillIds.includes(parseInt(drillId))) {
        drillIds.push(parseInt(drillId));
        sessionStorage.setItem('drillsToAdd', JSON.stringify(drillIds));
    }
    
    // Redirect to practice plan creation page
    window.location.href = '?page=practice_create';
}

// Get CSRF token from the page
function getDrillCsrfToken() {
    if (typeof csrfToken !== 'undefined') return csrfToken;
    var el = document.querySelector('input[name="csrf_token"]');
    return el ? el.value : '';
}

// --- In-App Delete Confirmation Modal ---
var _deleteConfirmCallback = null;

function showDeleteConfirm(message, onConfirm) {
    document.getElementById('deleteConfirmMessage').textContent = message;
    _deleteConfirmCallback = onConfirm;
    openModal('delete-confirm-modal');
}

function closeDeleteConfirm() {
    _deleteConfirmCallback = null;
    closeModal('delete-confirm-modal');
}

function executeDeleteConfirm() {
    var cb = _deleteConfirmCallback;
    closeDeleteConfirm();
    if (typeof cb === 'function') cb();
}

// Delete a drill with confirmation (uses JSON response)
function deleteDrill(drillId) {
    showDeleteConfirm('Delete this drill? This cannot be undone.', function() {
        performDeleteDrill(drillId);
    });
}

function performDeleteDrill(drillId) {
    var body = new URLSearchParams();
    body.set('action', 'delete_drill');
    body.set('drill_id', drillId);
    body.set('csrf_token', getDrillCsrfToken());
    fetch('process_drills.php', {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(function(r) {
            if (r.ok) {
                // Remove card from DOM with animation
                var safeId = parseInt(drillId, 10);
                var card = safeId ? document.querySelector('.drill-card[data-drill-id="' + safeId + '"]') : null;
                if (card) {
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(function() { card.remove(); updateDrillCount(); }, 300);
                } else {
                    window.location.reload();
                }
            } else {
                return r.json().then(function(data) {
                    throw new Error(data.message || 'Server returned ' + r.status);
                });
            }
        })
        .catch(function(err) { alert('Delete failed: ' + err.message); });
}

// Update drill count display
function updateDrillCount() {
    var visible = document.querySelectorAll('.drill-card:not(.hidden)').length;
    var el = document.getElementById('drill-count-display');
    if (el) el.textContent = visible + ' drills found';
}

// --- Multi-Select / Bulk Actions ---

function updateBulkSelection() {
    var checkboxes = document.querySelectorAll('.drill-select-checkbox');
    var checked = document.querySelectorAll('.drill-select-checkbox:checked');
    var bar = document.getElementById('bulkActionsBar');
    var countEl = document.getElementById('bulkSelectedCount');
    var selectAll = document.getElementById('selectAllDrills');
    
    if (checked.length > 0) {
        bar.style.display = 'flex';
        countEl.textContent = checked.length + ' selected';
    } else {
        bar.style.display = 'none';
    }
    
    // Update select all checkbox state
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
    }
    
    // Toggle selected class on cards
    checkboxes.forEach(function(cb) {
        var card = cb.closest('.drill-card');
        if (card) {
            if (cb.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        }
    });
}

function toggleSelectAllDrills(selectAllCb) {
    var checkboxes = document.querySelectorAll('.drill-select-checkbox');
    checkboxes.forEach(function(cb) {
        // Only toggle visible (not filtered out) cards
        var card = cb.closest('.drill-card');
        if (card && !card.classList.contains('hidden')) {
            cb.checked = selectAllCb.checked;
        }
    });
    updateBulkSelection();
}

function getSelectedDrillIds() {
    var checked = document.querySelectorAll('.drill-select-checkbox:checked');
    return Array.from(checked).map(function(cb) { return cb.value; });
}

function bulkCreatePracticePlan() {
    var ids = getSelectedDrillIds();
    if (ids.length === 0) {
        alert('Please select at least one drill.');
        return;
    }
    
    // Store selected drill IDs in sessionStorage for the practice plan create page
    sessionStorage.setItem('drillsToAdd', JSON.stringify(ids.map(Number)));
    window.location.href = '?page=practice_create';
}

function bulkDeleteDrills() {
    var ids = getSelectedDrillIds();
    if (ids.length === 0) {
        alert('Please select at least one drill.');
        return;
    }
    
    showDeleteConfirm('Delete ' + ids.length + ' selected drill(s)? This cannot be undone.', function() {
        performBulkDeleteDrills(ids);
    });
}

function performBulkDeleteDrills(ids) {
    var body = new URLSearchParams();
    body.set('action', 'bulk_delete_drills');
    body.set('csrf_token', getDrillCsrfToken());
    ids.forEach(function(id) { body.append('drill_ids[]', id); });
    
    fetch('process_drills.php', {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(function(r) {
            if (!r.ok) throw new Error('Server returned ' + r.status);
            return r.json();
        })
        .then(function(data) {
            if (data.success) {
                // Remove deleted cards from DOM
                ids.forEach(function(id) {
                    var safeId = parseInt(id, 10);
                    var card = safeId ? document.querySelector('.drill-card[data-drill-id="' + safeId + '"]') : null;
                    if (card) card.remove();
                });
                updateDrillCount();
                updateBulkSelection();
                if (typeof showNotification === 'function') {
                    showNotification(data.message, 'success');
                }
            } else {
                alert('Delete failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(function(err) { alert('Delete failed: ' + err.message); });
}

// Render ice rink thumbnails for all drill cards using shared IceCanvasRenderer
function renderDrillThumbnails() {
    const previews = document.querySelectorAll('.drill-diagram-preview');
    
    previews.forEach(preview => {
        const canvas = preview.querySelector('.drill-thumbnail-canvas');
        if (!canvas) return;
        
        // Get diagram data
        let diagramData = [];
        let sourceWidth = 800;  // Default fallback
        let sourceHeight = 400; // Default fallback
        let iceView = 'full';   // Default ice view
        
        // First, check if parent .drill-image has data-ice-view attribute (set by PHP)
        const drillImageParent = preview.closest('.drill-image');
        if (drillImageParent && drillImageParent.dataset.iceView) {
            iceView = drillImageParent.dataset.iceView;
        }
        
        try {
            const dataStr = preview.getAttribute('data-diagram') || '[]';
            const parsed = JSON.parse(dataStr);
            
            // Handle both old format (array) and new format (object with dimensions)
            if (Array.isArray(parsed)) {
                // Old format - just an array of objects
                diagramData = parsed;
            } else if (parsed && parsed.objects && Array.isArray(parsed.objects)) {
                // New format with canvas dimensions
                diagramData = parsed.objects;
                sourceWidth = parsed.canvasWidth || 800;
                sourceHeight = parsed.canvasHeight || 400;
                // Get saved ice view (overrides parent attribute if present)
                if (parsed.iceView) {
                    iceView = parsed.iceView;
                }
            }
        } catch (e) {
            diagramData = [];
        }
        
        // Get center logo URL
        const centerLogoUrl = preview.getAttribute('data-center-logo') || '';
        
        // Set canvas size with high-DPI support for sharp rendering
        const dpr = window.devicePixelRatio || 1;
        const cssWidth = preview.offsetWidth || 340;
        const cssHeight = preview.offsetHeight || 200;
        canvas.width = cssWidth * dpr;
        canvas.height = cssHeight * dpr;
        canvas.style.width = cssWidth + 'px';
        canvas.style.height = cssHeight + 'px';
        
        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        const w = cssWidth;
        const h = cssHeight;
        
        // Function to render the thumbnail using shared IceCanvasRenderer
        function renderThumbnail(logoImage, logoLoaded) {
            // Use the shared IceCanvasRenderer for consistent rink drawing
            if (window.IceCanvasRenderer) {
                IceCanvasRenderer.drawRink(ctx, w, h, iceView, {
                    logoImage: logoImage,
                    logoLoaded: logoLoaded,
                    lineScale: 1 // Thumbnail scale
                });
            } else {
                // Fallback if shared module not loaded - draw basic ice
                console.warn('IceCanvasRenderer not loaded - using basic fallback for drill thumbnail');
                ctx.fillStyle = '#f0f7fa';
                ctx.fillRect(0, 0, w, h);
                ctx.strokeStyle = '#0033a0';
                ctx.lineWidth = 2;
                ctx.strokeRect(2, 2, w - 4, h - 4);
            }
            
            // Draw diagram objects if available
            if (diagramData && diagramData.length > 0) {
                // Use uniform scaling to preserve object proportions
                const scaleX = w / sourceWidth;
                const scaleY = h / sourceHeight;
                const uniformScale = Math.min(scaleX, scaleY);
                
                // Calculate offset to center content
                const offsetX = (w - sourceWidth * uniformScale) / 2;
                const offsetY = (h - sourceHeight * uniformScale) / 2;
                
                diagramData.forEach(obj => {
                    const x = (obj.x || 0) * uniformScale + offsetX;
                    const y = (obj.y || 0) * uniformScale + offsetY;
                    
                    if (obj.type === 'player') {
                        // Draw player circle
                        ctx.fillStyle = obj.color || '#00bfff';
                        ctx.beginPath();
                        ctx.arc(x, y, 8, 0, 2 * Math.PI);
                        ctx.fill();
                        ctx.strokeStyle = '#fff';
                        ctx.lineWidth = 1;
                        ctx.stroke();
                        
                        // Draw label
                        if (obj.label) {
                            ctx.fillStyle = '#fff';
                            ctx.font = 'bold 6px Inter, sans-serif';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(obj.label, x, y);
                        }
                    } else if (obj.type === 'cone') {
                        ctx.fillStyle = obj.color || '#ff6b00';
                        ctx.beginPath();
                        ctx.moveTo(x, y - 8);
                        ctx.lineTo(x - 5, y + 5);
                        ctx.lineTo(x + 5, y + 5);
                        ctx.closePath();
                        ctx.fill();
                    } else if (obj.type === 'puck') {
                        ctx.fillStyle = '#000';
                        ctx.beginPath();
                        ctx.arc(x, y, 4, 0, 2 * Math.PI);
                        ctx.fill();
                    } else if (obj.type === 'pucks') {
                        // Group of pucks
                        ctx.fillStyle = '#000';
                        const positions = [
                            {x: -4, y: -4}, {x: 4, y: -4},
                            {x: -4, y: 4}, {x: 4, y: 4}, {x: 0, y: 0}
                        ];
                        positions.forEach(pos => {
                            ctx.beginPath();
                            ctx.arc(x + pos.x, y + pos.y, 3, 0, 2 * Math.PI);
                            ctx.fill();
                        });
                    } else if (obj.type === 'line') {
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                        } else if (obj.x1 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo((obj.x1 || 0) * uniformScale + offsetX, (obj.y1 || 0) * uniformScale + offsetY);
                            ctx.lineTo((obj.x2 || 0) * uniformScale + offsetX, (obj.y2 || 0) * uniformScale + offsetY);
                            ctx.stroke();
                        }
                    } else if (obj.type === 'arrow' || obj.type === 'freehand_arrow') {
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.fillStyle = obj.color || '#333';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        
                        let x2, y2, angle;
                        const headlen = 6;
                        
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                            
                            const last = obj.points[obj.points.length - 1];
                            const secondLast = obj.points[obj.points.length - 2];
                            x2 = last.x * uniformScale + offsetX;
                            y2 = last.y * uniformScale + offsetY;
                            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                        } else if (obj.x1 !== undefined) {
                            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
                            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
                            x2 = (obj.x2 || 0) * uniformScale + offsetX;
                            y2 = (obj.y2 || 0) * uniformScale + offsetY;
                            angle = Math.atan2(y2 - y1, x2 - x1);
                            
                            ctx.beginPath();
                            ctx.moveTo(x1, y1);
                            ctx.lineTo(x2, y2);
                            ctx.stroke();
                        }
                        
                        if (x2 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo(x2, y2);
                            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
                            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
                            ctx.closePath();
                            ctx.fill();
                        }
                    } else if (obj.type === 'dashed' || obj.type === 'freehand_dashed') {
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        ctx.setLineDash([4, 3]);
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                        } else if (obj.x1 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo((obj.x1 || 0) * uniformScale + offsetX, (obj.y1 || 0) * uniformScale + offsetY);
                            ctx.lineTo((obj.x2 || 0) * uniformScale + offsetX, (obj.y2 || 0) * uniformScale + offsetY);
                            ctx.stroke();
                        }
                        ctx.setLineDash([]);
                    } else if (obj.type === 'squiggly' || obj.type === 'freehand') {
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                        } else if (obj.x1 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo((obj.x1 || 0) * uniformScale + offsetX, (obj.y1 || 0) * uniformScale + offsetY);
                            ctx.lineTo((obj.x2 || 0) * uniformScale + offsetX, (obj.y2 || 0) * uniformScale + offsetY);
                            ctx.stroke();
                        }
                    } else if (obj.type === 'freehand_skating' || obj.type === 'skating_forward') {
                        // Skating lines with arrows
                        ctx.strokeStyle = obj.color || '#0033a0';
                        ctx.fillStyle = obj.color || '#0033a0';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        
                        let x2, y2, angle;
                        const headlen = 6;
                        
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                            
                            const last = obj.points[obj.points.length - 1];
                            const secondLast = obj.points[obj.points.length - 2];
                            x2 = last.x * uniformScale + offsetX;
                            y2 = last.y * uniformScale + offsetY;
                            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                        } else if (obj.x1 !== undefined) {
                            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
                            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
                            x2 = (obj.x2 || 0) * uniformScale + offsetX;
                            y2 = (obj.y2 || 0) * uniformScale + offsetY;
                            angle = Math.atan2(y2 - y1, x2 - x1);
                            
                            ctx.beginPath();
                            ctx.moveTo(x1, y1);
                            ctx.lineTo(x2, y2);
                            ctx.stroke();
                        }
                        
                        if (x2 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo(x2, y2);
                            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
                            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
                            ctx.closePath();
                            ctx.fill();
                        }
                    } else if (obj.type === 'skating_backward') {
                        // Backward skating - dashed with arrow
                        ctx.strokeStyle = obj.color || '#c41e3a';
                        ctx.fillStyle = obj.color || '#c41e3a';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.setLineDash([6, 3]);
                        
                        let x2, y2, angle;
                        const headlen = 6;
                        
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                            
                            const last = obj.points[obj.points.length - 1];
                            const secondLast = obj.points[obj.points.length - 2];
                            x2 = last.x * uniformScale + offsetX;
                            y2 = last.y * uniformScale + offsetY;
                            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                        } else if (obj.x1 !== undefined) {
                            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
                            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
                            x2 = (obj.x2 || 0) * uniformScale + offsetX;
                            y2 = (obj.y2 || 0) * uniformScale + offsetY;
                            angle = Math.atan2(y2 - y1, x2 - x1);
                            
                            ctx.beginPath();
                            ctx.moveTo(x1, y1);
                            ctx.lineTo(x2, y2);
                            ctx.stroke();
                        }
                        ctx.setLineDash([]);
                        
                        if (x2 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo(x2, y2);
                            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
                            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
                            ctx.closePath();
                            ctx.fill();
                        }
                    } else if (obj.type === 'skating_lateral' || obj.type === 'skating_ccuts') {
                        // Lateral/C-cuts skating - solid line
                        ctx.strokeStyle = obj.color || '#10b981';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                        } else if (obj.x1 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo((obj.x1 || 0) * uniformScale + offsetX, (obj.y1 || 0) * uniformScale + offsetY);
                            ctx.lineTo((obj.x2 || 0) * uniformScale + offsetX, (obj.y2 || 0) * uniformScale + offsetY);
                            ctx.stroke();
                        }
                    } else if (obj.type === 'skating_forward_puck' || obj.type === 'skating_backward_puck') {
                        // Skating with puck - line with arrow and puck circle
                        const color = obj.color || '#00bfff';
                        ctx.strokeStyle = color;
                        ctx.fillStyle = color;
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        
                        if (obj.type === 'skating_backward_puck') {
                            ctx.setLineDash([6, 3]);
                        }
                        
                        let x1, y1, x2, y2, angle;
                        const headlen = 6;
                        
                        if (obj.points && obj.points.length > 1) {
                            x1 = obj.points[0].x * uniformScale + offsetX;
                            y1 = obj.points[0].y * uniformScale + offsetY;
                            
                            ctx.beginPath();
                            ctx.moveTo(x1, y1);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                            
                            const last = obj.points[obj.points.length - 1];
                            const secondLast = obj.points[obj.points.length - 2];
                            x2 = last.x * uniformScale + offsetX;
                            y2 = last.y * uniformScale + offsetY;
                            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                        } else if (obj.x1 !== undefined) {
                            x1 = (obj.x1 || 0) * uniformScale + offsetX;
                            y1 = (obj.y1 || 0) * uniformScale + offsetY;
                            x2 = (obj.x2 || 0) * uniformScale + offsetX;
                            y2 = (obj.y2 || 0) * uniformScale + offsetY;
                            angle = Math.atan2(y2 - y1, x2 - x1);
                            
                            ctx.beginPath();
                            ctx.moveTo(x1, y1);
                            ctx.lineTo(x2, y2);
                            ctx.stroke();
                        }
                        ctx.setLineDash([]);
                        
                        // Arrow
                        if (x2 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo(x2, y2);
                            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
                            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
                            ctx.closePath();
                            ctx.fill();
                        }
                        
                        // Puck at start
                        if (x1 !== undefined) {
                            ctx.fillStyle = '#000';
                            ctx.beginPath();
                            ctx.arc(x1, y1, 3, 0, 2 * Math.PI);
                            ctx.fill();
                        }
                    } else if (obj.type === 'pass') {
                        // Pass - dashed with hollow arrow
                        ctx.strokeStyle = obj.color || '#0033a0';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.setLineDash([5, 3]);
                        
                        let x2, y2, angle;
                        const headlen = 6;
                        
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                            
                            const last = obj.points[obj.points.length - 1];
                            const secondLast = obj.points[obj.points.length - 2];
                            x2 = last.x * uniformScale + offsetX;
                            y2 = last.y * uniformScale + offsetY;
                            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                        } else if (obj.x1 !== undefined) {
                            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
                            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
                            x2 = (obj.x2 || 0) * uniformScale + offsetX;
                            y2 = (obj.y2 || 0) * uniformScale + offsetY;
                            angle = Math.atan2(y2 - y1, x2 - x1);
                            
                            ctx.beginPath();
                            ctx.moveTo(x1, y1);
                            ctx.lineTo(x2, y2);
                            ctx.stroke();
                        }
                        ctx.setLineDash([]);
                        
                        // Hollow arrow
                        if (x2 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo(x2, y2);
                            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
                            ctx.moveTo(x2, y2);
                            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
                            ctx.stroke();
                        }
                    } else if (obj.type === 'shot') {
                        // Shot - thick solid with large arrow
                        ctx.strokeStyle = obj.color || '#c41e3a';
                        ctx.fillStyle = obj.color || '#c41e3a';
                        ctx.lineWidth = 3;
                        ctx.lineCap = 'round';
                        
                        let x2, y2, angle;
                        const headlen = 8;
                        
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
                            }
                            ctx.stroke();
                            
                            const last = obj.points[obj.points.length - 1];
                            const secondLast = obj.points[obj.points.length - 2];
                            x2 = last.x * uniformScale + offsetX;
                            y2 = last.y * uniformScale + offsetY;
                            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                        } else if (obj.x1 !== undefined) {
                            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
                            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
                            x2 = (obj.x2 || 0) * uniformScale + offsetX;
                            y2 = (obj.y2 || 0) * uniformScale + offsetY;
                            angle = Math.atan2(y2 - y1, x2 - x1);
                            
                            ctx.beginPath();
                            ctx.moveTo(x1, y1);
                            ctx.lineTo(x2, y2);
                            ctx.stroke();
                        }
                        
                        // Large filled arrow
                        if (x2 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo(x2, y2);
                            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 5), y2 - headlen * Math.sin(angle - Math.PI / 5));
                            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 5), y2 - headlen * Math.sin(angle + Math.PI / 5));
                            ctx.closePath();
                            ctx.fill();
                        }
                    } else if (obj.type === 'net' || obj.type === 'mininet') {
                        const netWidth = obj.type === 'mininet' ? 20 : 30;
                        const netDepth = obj.type === 'mininet' ? 8 : 10;
                        ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
                        ctx.strokeStyle = obj.color || '#c41e3a';
                        ctx.lineWidth = 2;
                        ctx.beginPath();
                        ctx.rect(x - netWidth/2, y - netDepth/2, netWidth, netDepth);
                        ctx.fill();
                        ctx.stroke();
                    } else if (obj.type === 'tire') {
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.lineWidth = 3;
                        ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
                        ctx.beginPath();
                        ctx.arc(x, y, 6, 0, 2 * Math.PI);
                        ctx.fill();
                        ctx.stroke();
                    } else if (obj.type === 'stick') {
                        ctx.strokeStyle = obj.color || '#8B4513';
                        ctx.lineWidth = 2;
                        ctx.lineCap = 'round';
                        ctx.beginPath();
                        ctx.moveTo(x, y - 10);
                        ctx.lineTo(x, y + 6);
                        ctx.stroke();
                        ctx.lineWidth = 3;
                        ctx.beginPath();
                        ctx.moveTo(x, y + 6);
                        ctx.quadraticCurveTo(x + 4, y + 8, x + 7, y + 6);
                        ctx.stroke();
                    } else if (obj.type === 'text') {
                        ctx.fillStyle = obj.color || '#333';
                        ctx.font = 'bold 8px Inter, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(obj.text || '', x, y);
                    } else if (obj.type === 'number') {
                        ctx.fillStyle = '#fff';
                        ctx.beginPath();
                        ctx.arc(x, y, 8, 0, 2 * Math.PI);
                        ctx.fill();
                        ctx.strokeStyle = obj.color || '#000';
                        ctx.lineWidth = 1;
                        ctx.stroke();
                        ctx.fillStyle = obj.color || '#000';
                        ctx.font = 'bold 8px Inter, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(obj.value || '', x, y);
                    }
                });
            }
        }
        
        // Load center logo if URL provided, then render
        if (centerLogoUrl) {
            const logoImage = new Image();
            logoImage.crossOrigin = 'anonymous';
            logoImage.onload = function() {
                renderThumbnail(logoImage, true);
            };
            logoImage.onerror = function() {
                renderThumbnail(null, false);
            };
            logoImage.src = centerLogoUrl;
        } else {
            renderThumbnail(null, false);
        }
    });
}
</script>

<style>
/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.modal-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-title i {
    color: var(--primary);
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-dim);
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.modal-close:hover {
    color: var(--text-white);
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    background: var(--bg-main);
}

.drill-detail-section {
    margin-bottom: 20px;
}

.drill-detail-section h4 {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.drill-detail-section p {
    font-size: 14px;
    color: var(--text-white);
    line-height: 1.6;
}
</style>
