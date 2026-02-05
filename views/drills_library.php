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
                     data-category="<?php echo $drill['category_id'] ?? ''; ?>"
                     data-title="<?php echo htmlspecialchars(strtolower($drill['title'])); ?>"
                     data-coach="<?php echo strtolower($coachName); ?>">
                    <div class="drill-image" data-ice-view="<?php echo htmlspecialchars($drillIceView); ?>">
                        <?php if ($drill['custom_image']): ?>
                            <img src="<?php echo htmlspecialchars($drill['custom_image']); ?>" alt="<?php echo htmlspecialchars($drill['title']); ?>">
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

/* Half ice: Reduced height for better card display in grid layouts
   Original was 117.6% (true aspect ratio), reduced to 75% to prevent cards from being excessively tall */
.drill-image[data-ice-view="half-top"],
.drill-image[data-ice-view="half-bottom"] {
    padding-top: 75%;
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

<script>
// NHL/Hockey Canada Rink Proportions (200 ft × 85 ft rink)
const NHL_RINK = {
    GOAL_LINE: 11 / 200,           // Goal line 11 ft from end
    BLUE_LINE: 64 / 200,           // Blue line 64 ft from end
    FACEOFF_RADIUS: 15 / 85,       // 15 ft radius faceoff circles
    CENTER_CIRCLE_RADIUS: 15 / 85, // 15 ft radius center circle
    CREASE_RADIUS: 6 / 85,         // 6 ft radius goal crease
    FACEOFF_FROM_GOAL: 20 / 200,   // 20 ft from goal line
    FACEOFF_FROM_BOARDS: 22 / 85,  // 22 ft from boards
    TRAPEZOID_BASE: 22 / 85,       // Trapezoid base at goal line: 22 ft wide
    TRAPEZOID_TOP: 28 / 85,        // Trapezoid top at boards: 28 ft wide
    RESTRAINT_LINE_LENGTH: 2 / 85, // 2 ft restraint lines
    CORNER_RADIUS: 28 / 85         // Corner radius 28 ft on 85 ft width
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
    
    // Render ice rink thumbnails for drills
    renderDrillThumbnails();
});

// Render ice rink thumbnails for all drill cards
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
                // Get saved ice view
                if (parsed.iceView) {
                    iceView = parsed.iceView;
                }
            }
        } catch (e) {
            diagramData = [];
        }
        
        // Get center logo URL
        const centerLogoUrl = preview.getAttribute('data-center-logo') || '';
        
        // Set canvas size
        canvas.width = preview.offsetWidth || 340;
        canvas.height = preview.offsetHeight || 200;
        
        const ctx = canvas.getContext('2d');
        const w = canvas.width;
        const h = canvas.height;
        
        // Function to render the thumbnail
        function renderThumbnail(logoImage, logoLoaded) {
            // Draw ice background
            ctx.fillStyle = '#f0f7fa';
            ctx.fillRect(0, 0, w, h);
            
            // Draw center branding (logo image or text fallback)
            ctx.save();
            ctx.globalAlpha = 0.12;
            
            if (logoLoaded && logoImage) {
                // Draw logo image centered on ice
                const maxLogoWidth = w * 0.35;
                const maxLogoHeight = h * 0.3;
                
                // Calculate scaled dimensions maintaining aspect ratio
                const imgAspect = logoImage.width / logoImage.height;
                let logoWidth = maxLogoWidth;
                let logoHeight = logoWidth / imgAspect;
                
                if (logoHeight > maxLogoHeight) {
                    logoHeight = maxLogoHeight;
                    logoWidth = logoHeight * imgAspect;
                }
                
                const logoX = (w - logoWidth) / 2;
                const logoY = (h - logoHeight) / 2;
                
                ctx.drawImage(logoImage, logoX, logoY, logoWidth, logoHeight);
            } else {
                // Fallback to text branding
                ctx.fillStyle = '#7000a4';
                ctx.font = 'bold 24px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('ARCTIC WOLVES', w/2, h/2);
            }
            ctx.restore();
            
            // Draw rink markings based on ice view
            switch(iceView) {
                case 'half-top':
                    drawThumbnailHalfIce(ctx, w, h, 'top');
                    break;
                case 'half-bottom':
                    drawThumbnailHalfIce(ctx, w, h, 'bottom');
                    break;
                case 'left-zone':
                    drawThumbnailZone(ctx, w, h, 'left');
                    break;
                case 'right-zone':
                    drawThumbnailZone(ctx, w, h, 'right');
                    break;
                case 'center':
                    drawThumbnailCenterIce(ctx, w, h);
                    break;
                case 'full':
                default:
                    drawThumbnailFullIce(ctx, w, h);
                    break;
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
            
            // Draw rink border that adapts to view type (matching drill designer)
            ctx.strokeStyle = '#0033a0';
            ctx.lineWidth = 2;
            
            // NHL corner radius: 28 ft on 85 ft width (~0.329 ratio)
            // For full ice (horizontal layout), use height as reference since width represents length
            // For half ice (vertical layout with net at top/bottom), use width as reference
            let cornerRadius;
            if (iceView === 'half-top' || iceView === 'half-bottom') {
                // Half ice views are oriented vertically - width represents the 85 ft rink width
                cornerRadius = w * NHL_RINK.CORNER_RADIUS;
            } else {
                // Full ice, zones, and center - height represents the 85 ft rink width
                cornerRadius = h * NHL_RINK.CORNER_RADIUS;
            }
            
            ctx.beginPath();
            if (iceView === 'half-top') {
                // Curved corners at top (net end), flat at bottom (center line)
                ctx.moveTo(cornerRadius, 0);
                ctx.lineTo(w - cornerRadius, 0);
                ctx.quadraticCurveTo(w, 0, w, cornerRadius);
                ctx.lineTo(w, h);
                ctx.lineTo(0, h);
                ctx.lineTo(0, cornerRadius);
                ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
            } else if (iceView === 'half-bottom') {
                // Flat at top (center line), curved corners at bottom (net end)
                ctx.moveTo(0, 0);
                ctx.lineTo(w, 0);
                ctx.lineTo(w, h - cornerRadius);
                ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
                ctx.lineTo(cornerRadius, h);
                ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
                ctx.lineTo(0, 0);
            } else if (iceView === 'left-zone') {
                // Curved corners at left (net end), flat at right (blue line side)
                ctx.moveTo(cornerRadius, 0);
                ctx.lineTo(w, 0);
                ctx.lineTo(w, h);
                ctx.lineTo(cornerRadius, h);
                ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
                ctx.lineTo(0, cornerRadius);
                ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
            } else if (iceView === 'right-zone') {
                // Flat at left (blue line side), curved corners at right (net end)
                ctx.moveTo(0, 0);
                ctx.lineTo(w - cornerRadius, 0);
                ctx.quadraticCurveTo(w, 0, w, cornerRadius);
                ctx.lineTo(w, h - cornerRadius);
                ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
                ctx.lineTo(0, h);
                ctx.lineTo(0, 0);
            } else if (iceView === 'center') {
                // Center ice - all corners rounded (matching drill designer)
                ctx.moveTo(cornerRadius, 0);
                ctx.lineTo(w - cornerRadius, 0);
                ctx.quadraticCurveTo(w, 0, w, cornerRadius);
                ctx.lineTo(w, h - cornerRadius);
                ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
                ctx.lineTo(cornerRadius, h);
                ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
                ctx.lineTo(0, cornerRadius);
                ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
            } else {
                // Full ice - all corners rounded
                ctx.moveTo(cornerRadius, 0);
                ctx.lineTo(w - cornerRadius, 0);
                ctx.quadraticCurveTo(w, 0, w, cornerRadius);
                ctx.lineTo(w, h - cornerRadius);
                ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
                ctx.lineTo(cornerRadius, h);
                ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
                ctx.lineTo(0, cornerRadius);
                ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
            }
            ctx.closePath();
            ctx.stroke();
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

// Helper function to draw hash marks around faceoff circles in thumbnails
// netPosition: 'horizontal' (nets on left/right, hash marks on top/bottom)
//              'vertical' (nets on top/bottom, hash marks on left/right)
function drawThumbnailHashMarks(ctx, cx, cy, radius, netPosition) {
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.lineCap = 'round';
    
    // NHL/Hockey Canada regulations: faceoff circles have 15-foot radius
    // Hash marks are 2 feet long and spaced 3 feet apart
    // We scale these dimensions relative to the drawn circle radius
    const hashLength = radius * (2 / 15); // 2 feet / 15 feet radius = 0.133
    const hashSpacing = radius * (3 / 15); // 3 feet / 15 feet radius = 0.2
    const gapOutsideCircle = radius * 0.05;
    const startDistance = radius + gapOutsideCircle;
    
    const sides = [-1, 1];
    
    if (netPosition === 'vertical') {
        // Nets on top/bottom - hash marks on LEFT and RIGHT of circle (horizontal lines)
        sides.forEach(function(side) {
            const startX = cx + side * startDistance;
            const endX = startX + side * hashLength;
            
            // Top hash mark
            ctx.beginPath();
            ctx.moveTo(startX, cy - hashSpacing / 2);
            ctx.lineTo(endX, cy - hashSpacing / 2);
            ctx.stroke();
            
            // Bottom hash mark
            ctx.beginPath();
            ctx.moveTo(startX, cy + hashSpacing / 2);
            ctx.lineTo(endX, cy + hashSpacing / 2);
            ctx.stroke();
        });
    } else {
        // Nets on left/right (default) - hash marks on TOP and BOTTOM of circle (vertical lines)
        sides.forEach(function(side) {
            const startY = cy + side * startDistance;
            const endY = startY + side * hashLength;
            
            // Left hash mark
            ctx.beginPath();
            ctx.moveTo(cx - hashSpacing / 2, startY);
            ctx.lineTo(cx - hashSpacing / 2, endY);
            ctx.stroke();
            
            // Right hash mark
            ctx.beginPath();
            ctx.moveTo(cx + hashSpacing / 2, startY);
            ctx.lineTo(cx + hashSpacing / 2, endY);
            ctx.stroke();
        });
    }
}

// Draw faceoff restraint lines (L-shaped lines inside end zone faceoff circles) for thumbnails
// For horizontal layout (full ice, zones): zone is 'left' or 'right'
// For vertical layout (half ice): zone is 'top' or 'bottom'
function drawThumbnailRestraintLines(ctx, cx, cy, radius, zone, canvasRefDimension, isVertical) {
    const lineLength = canvasRefDimension * NHL_RINK.RESTRAINT_LINE_LENGTH * 1.5; // Slightly longer for visibility
    const offset = radius * 0.15; // Distance from center dot
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.lineCap = 'round';
    
    if (isVertical) {
        // Vertical layout (half-ice): net at top or bottom
        const goalDirection = zone === 'top' ? -1 : 1;
        
        drawThumbnailLShapeVertical(ctx, cx - offset, cy - offset, lineLength, goalDirection);
        drawThumbnailLShapeVertical(ctx, cx - offset, cy + offset, lineLength, goalDirection);
        drawThumbnailLShapeVertical(ctx, cx + offset, cy - offset, lineLength, goalDirection);
        drawThumbnailLShapeVertical(ctx, cx + offset, cy + offset, lineLength, goalDirection);
    } else {
        // Horizontal layout (full ice, zones): net at left or right
        const goalDirection = zone === 'left' ? -1 : 1;
        
        drawThumbnailLShape(ctx, cx - offset, cy - offset, lineLength, goalDirection, -1);
        drawThumbnailLShape(ctx, cx + offset, cy - offset, lineLength, goalDirection, -1);
        drawThumbnailLShape(ctx, cx - offset, cy + offset, lineLength, goalDirection, 1);
        drawThumbnailLShape(ctx, cx + offset, cy + offset, lineLength, goalDirection, 1);
    }
}

function drawThumbnailLShape(ctx, x, y, length, hDir, vDir) {
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x, y + vDir * length);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x + hDir * length, y);
    ctx.stroke();
}

function drawThumbnailLShapeVertical(ctx, x, y, length, vDir) {
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x, y + vDir * length);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.moveTo(x - length/2, y);
    ctx.lineTo(x + length/2, y);
    ctx.stroke();
}

// Draw full ice view for thumbnails
function drawThumbnailFullIce(ctx, w, h) {
    // NHL proportions
    const goalLinePos = NHL_RINK.GOAL_LINE;
    const blueLinePos = NHL_RINK.BLUE_LINE;
    const faceoffFromGoal = goalLinePos + NHL_RINK.FACEOFF_FROM_GOAL;
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
    
    // Center line
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w/2, 0);
    ctx.lineTo(w/2, h);
    ctx.stroke();
    
    // Blue lines
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w * blueLinePos, 0);
    ctx.lineTo(w * blueLinePos, h);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(w * (1 - blueLinePos), 0);
    ctx.lineTo(w * (1 - blueLinePos), h);
    ctx.stroke();
    
    // Center circle
    ctx.beginPath();
    ctx.arc(w/2, h/2, h * NHL_RINK.CENTER_CIRCLE_RADIUS, 0, 2 * Math.PI);
    ctx.stroke();
    
    // Center dot
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    ctx.arc(w/2, h/2, 3, 0, 2 * Math.PI);
    ctx.fill();
    
    // Faceoff circles (15 ft radius, 20 ft from goal, 22 ft from boards)
    ctx.strokeStyle = '#c41e3a';
    const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
    const circles = [
        { x: w * faceoffFromGoal, y: h * faceoffFromBoards, zone: 'left' },
        { x: w * faceoffFromGoal, y: h * (1 - faceoffFromBoards), zone: 'left' },
        { x: w * (1 - faceoffFromGoal), y: h * faceoffFromBoards, zone: 'right' },
        { x: w * (1 - faceoffFromGoal), y: h * (1 - faceoffFromBoards), zone: 'right' }
    ];
    circles.forEach(function(circle) {
        ctx.beginPath();
        ctx.arc(circle.x, circle.y, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Draw faceoff dot
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(circle.x, circle.y, 2, 0, 2 * Math.PI);
        ctx.fill();
        
        // Draw hash marks around faceoff circles (nets on left/right)
        drawThumbnailHashMarks(ctx, circle.x, circle.y, faceoffRadius, 'horizontal');
        
        // Draw restraint lines
        drawThumbnailRestraintLines(ctx, circle.x, circle.y, faceoffRadius, circle.zone, h, false);
    });
    
    // Goal creases (6 ft radius)
    const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    
    // Left crease
    ctx.beginPath();
    ctx.arc(w * goalLinePos, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
    ctx.fill();
    ctx.stroke();
    
    // Right crease
    ctx.beginPath();
    ctx.arc(w * (1 - goalLinePos), h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2, true);
    ctx.fill();
    ctx.stroke();
    
    // Goal lines - extends to boards respecting curved corners (matching drill designer)
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    
    // Left goal line
    const leftGoalLineX = w * goalLinePos;
    let leftGoalLineStartY = 0;
    let leftGoalLineEndY = h;
    if (leftGoalLineX < cornerRadius) {
        const dx = cornerRadius - leftGoalLineX;
        const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
        leftGoalLineStartY = yOffset;
        leftGoalLineEndY = h - yOffset;
    }
    ctx.beginPath();
    ctx.moveTo(leftGoalLineX, leftGoalLineStartY);
    ctx.lineTo(leftGoalLineX, leftGoalLineEndY);
    ctx.stroke();
    
    // Right goal line
    const rightGoalLineX = w * (1 - goalLinePos);
    let rightGoalLineStartY = 0;
    let rightGoalLineEndY = h;
    if ((w - rightGoalLineX) < cornerRadius) {
        const dx = cornerRadius - (w - rightGoalLineX);
        const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
        rightGoalLineStartY = yOffset;
        rightGoalLineEndY = h - yOffset;
    }
    ctx.beginPath();
    ctx.moveTo(rightGoalLineX, rightGoalLineStartY);
    ctx.lineTo(rightGoalLineX, rightGoalLineEndY);
    ctx.stroke();
    
    // Goalie trapezoids (matching drill designer)
    drawThumbnailTrapezoid(ctx, w, h, 'left');
    drawThumbnailTrapezoid(ctx, w, h, 'right');
    
    // Neutral zone faceoff dots (5 ft from blue lines)
    const neutralZoneDotOffset = 5 / 200;
    ctx.fillStyle = '#c41e3a';
    const neutralDots = [
        { x: w * (blueLinePos + neutralZoneDotOffset), y: h * faceoffFromBoards },
        { x: w * (blueLinePos + neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) },
        { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * faceoffFromBoards },
        { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) }
    ];
    neutralDots.forEach(function(dot) {
        ctx.beginPath();
        ctx.arc(dot.x, dot.y, 2, 0, 2 * Math.PI);
        ctx.fill();
    });
}

// Draw goalie trapezoid for thumbnails (matching drill designer)
function drawThumbnailTrapezoid(ctx, w, h, side) {
    const goalLinePos = NHL_RINK.GOAL_LINE;
    const trapezoidBase = h * NHL_RINK.TRAPEZOID_BASE / 2;
    const trapezoidTop = h * NHL_RINK.TRAPEZOID_TOP / 2;
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    
    if (side === 'left') {
        const goalX = w * goalLinePos;
        ctx.beginPath();
        ctx.moveTo(goalX, h/2 - trapezoidBase);
        ctx.lineTo(0, h/2 - trapezoidTop);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(goalX, h/2 + trapezoidBase);
        ctx.lineTo(0, h/2 + trapezoidTop);
        ctx.stroke();
    } else {
        const goalX = w * (1 - goalLinePos);
        ctx.beginPath();
        ctx.moveTo(goalX, h/2 - trapezoidBase);
        ctx.lineTo(w, h/2 - trapezoidTop);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(goalX, h/2 + trapezoidBase);
        ctx.lineTo(w, h/2 + trapezoidTop);
        ctx.stroke();
    }
}

// Draw half ice view for thumbnails
function drawThumbnailHalfIce(ctx, w, h, side) {
    // Use NHL proportions for half ice (matching drill designer)
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    const faceoffRadius = w * NHL_RINK.FACEOFF_RADIUS;
    const creaseRadius = w * NHL_RINK.CREASE_RADIUS;
    const cornerRadius = w * NHL_RINK.CORNER_RADIUS;
    
    // Calculate positions based on half-ice proportions (matching drill designer)
    const goalLineRatio = 11 / 100;      // Goal line at 11% from net end
    const blueLineRatio = 64 / 100;      // Blue line at 64% from net end
    const faceoffYRatio = 31 / 100;      // Faceoff dot at 31% from net end
    
    // Blue line position
    const blueLineY = side === 'top' ? h * blueLineRatio : h * (1 - blueLineRatio);
    
    // Blue line
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0, blueLineY);
    ctx.lineTo(w, blueLineY);
    ctx.stroke();
    
    // Goal position (goal line)
    const goalY = side === 'top' ? h * goalLineRatio : h * (1 - goalLineRatio);
    
    // Faceoff circles - positioned 22 ft from boards on each side
    const faceoffY = side === 'top' ? h * faceoffYRatio : h * (1 - faceoffYRatio);
    
    // Left faceoff circle
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.arc(w * faceoffFromBoards, faceoffY, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(w * faceoffFromBoards, faceoffY, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, 'vertical');
    drawThumbnailRestraintLines(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, side, w, true);
    
    // Right faceoff circle
    ctx.strokeStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(w * (1 - faceoffFromBoards), faceoffY, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 'vertical');
    drawThumbnailRestraintLines(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, side, w, true);
    
    // Goal crease - 6 ft radius semicircle
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.beginPath();
    if (side === 'top') {
        ctx.arc(w * 0.5, goalY, creaseRadius, 0, Math.PI);
    } else {
        ctx.arc(w * 0.5, goalY, creaseRadius, Math.PI, 0);
    }
    ctx.fill();
    ctx.stroke();
    
    // Goal line - extends to boards respecting curved corners (matching drill designer)
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    
    const distFromEnd = side === 'top' ? goalY : (h - goalY);
    let goalLineStartX = 0;
    let goalLineEndX = w;
    
    if (distFromEnd < cornerRadius) {
        const dy = cornerRadius - distFromEnd;
        const xOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dy * dy);
        goalLineStartX = xOffset;
        goalLineEndX = w - xOffset;
    }
    
    ctx.moveTo(goalLineStartX, goalY);
    ctx.lineTo(goalLineEndX, goalY);
    ctx.stroke();
    
    // Draw trapezoid behind net (matching drill designer)
    drawThumbnailHalfIceTrapezoid(ctx, w, h, side, goalY);
}

// Draw trapezoid for half ice thumbnail (net at top or bottom)
function drawThumbnailHalfIceTrapezoid(ctx, w, h, side, goalY) {
    const trapezoidBase = w * NHL_RINK.TRAPEZOID_BASE / 2;
    const trapezoidTop = w * NHL_RINK.TRAPEZOID_TOP / 2;
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    
    const boardY = side === 'top' ? 0 : h;
    
    // Left trapezoid line
    ctx.beginPath();
    ctx.moveTo(w/2 - trapezoidBase, goalY);
    ctx.lineTo(w/2 - trapezoidTop, boardY);
    ctx.stroke();
    
    // Right trapezoid line
    ctx.beginPath();
    ctx.moveTo(w/2 + trapezoidBase, goalY);
    ctx.lineTo(w/2 + trapezoidTop, boardY);
    ctx.stroke();
}

// Draw zone view for thumbnails - LEFT/RIGHT HALF of full rink (matching drill designer)
function drawThumbnailZone(ctx, w, h, side) {
    // Zone view shows one half of the rink (from end boards to center line)
    // Use height as the 85 ft reference
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
    const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
    const centerCircleRadius = h * NHL_RINK.CENTER_CIRCLE_RADIUS;
    const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
    
    // Calculate positions based on half-rink proportions (100 ft span, matching drill designer)
    const goalLineRatio = 11 / 100;
    const blueLineRatio = 64 / 100;
    const faceoffXRatio = 31 / 100;
    const neutralZoneDotRatio = (64 + 5) / 100;
    
    // Center line (red) - at the edge of the visible half
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    if (side === 'left') {
        ctx.beginPath();
        ctx.moveTo(w, 0);
        ctx.lineTo(w, h);
        ctx.stroke();
    } else {
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.lineTo(0, h);
        ctx.stroke();
    }
    
    // Blue line position
    const blueLineX = side === 'left' ? w * blueLineRatio : w * (1 - blueLineRatio);
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(blueLineX, 0);
    ctx.lineTo(blueLineX, h);
    ctx.stroke();
    
    // Goal line position - respects curved corners (matching drill designer)
    const goalLineX = side === 'left' ? w * goalLineRatio : w * (1 - goalLineRatio);
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    
    const distFromEnd = side === 'left' ? goalLineX : (w - goalLineX);
    let zoneGoalLineStartY = 0;
    let zoneGoalLineEndY = h;
    
    if (distFromEnd < cornerRadius) {
        const dx = cornerRadius - distFromEnd;
        const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
        zoneGoalLineStartY = yOffset;
        zoneGoalLineEndY = h - yOffset;
    }
    
    ctx.moveTo(goalLineX, zoneGoalLineStartY);
    ctx.lineTo(goalLineX, zoneGoalLineEndY);
    ctx.stroke();
    
    // Half center circle (at the edge, only half visible)
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 1;
    ctx.beginPath();
    if (side === 'left') {
        ctx.arc(w, h/2, centerCircleRadius, Math.PI/2, -Math.PI/2);
    } else {
        ctx.arc(0, h/2, centerCircleRadius, -Math.PI/2, Math.PI/2);
    }
    ctx.stroke();
    
    // Center dot (at edge)
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    if (side === 'left') {
        ctx.arc(w, h/2, 3, 0, 2 * Math.PI);
    } else {
        ctx.arc(0, h/2, 3, 0, 2 * Math.PI);
    }
    ctx.fill();
    
    // Faceoff circles in this zone
    const faceoffX = side === 'left' ? w * faceoffXRatio : w * (1 - faceoffXRatio);
    
    // Top faceoff circle
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.arc(faceoffX, h * faceoffFromBoards, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(faceoffX, h * faceoffFromBoards, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, 'horizontal');
    drawThumbnailRestraintLines(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, side, h, false);
    
    // Bottom faceoff circle
    ctx.strokeStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(faceoffX, h * (1 - faceoffFromBoards), 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 'horizontal');
    drawThumbnailRestraintLines(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, side, h, false);
    
    // Neutral zone dots
    const neutralDotX = side === 'left' ? w * neutralZoneDotRatio : w * (1 - neutralZoneDotRatio);
    
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(neutralDotX, h * faceoffFromBoards, 2, 0, 2 * Math.PI);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(neutralDotX, h * (1 - faceoffFromBoards), 2, 0, 2 * Math.PI);
    ctx.fill();
    
    // Goal crease
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.beginPath();
    if (side === 'left') {
        ctx.arc(goalLineX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
    } else {
        ctx.arc(goalLineX, h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
    }
    ctx.fill();
    ctx.stroke();
    
    // Draw trapezoid behind net (matching drill designer)
    drawThumbnailZoneTrapezoid(ctx, w, h, side, goalLineX);
}

// Draw trapezoid for zone view thumbnail (net at left or right)
function drawThumbnailZoneTrapezoid(ctx, w, h, side, goalLineX) {
    const trapezoidBase = h * NHL_RINK.TRAPEZOID_BASE / 2;
    const trapezoidTop = h * NHL_RINK.TRAPEZOID_TOP / 2;
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    
    const boardX = side === 'left' ? 0 : w;
    
    // Top trapezoid line
    ctx.beginPath();
    ctx.moveTo(goalLineX, h/2 - trapezoidBase);
    ctx.lineTo(boardX, h/2 - trapezoidTop);
    ctx.stroke();
    
    // Bottom trapezoid line
    ctx.beginPath();
    ctx.moveTo(goalLineX, h/2 + trapezoidBase);
    ctx.lineTo(boardX, h/2 + trapezoidTop);
    ctx.stroke();
}

// Draw center ice view for thumbnails (neutral zone around center)
function drawThumbnailCenterIce(ctx, w, h) {
    // Center line (red)
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w/2, 0);
    ctx.lineTo(w/2, h);
    ctx.stroke();
    
    // Center circle - use NHL proportions constant
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 1;
    const circleRadius = h * NHL_RINK.CENTER_CIRCLE_RADIUS;
    ctx.beginPath();
    ctx.arc(w/2, h/2, circleRadius, 0, 2 * Math.PI);
    ctx.stroke();
    
    // Center dot
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    ctx.arc(w/2, h/2, 4, 0, 2 * Math.PI);
    ctx.fill();
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
