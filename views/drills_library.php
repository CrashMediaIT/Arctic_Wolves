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
            ?>
                <div class="drill-card" 
                     data-category="<?php echo $drill['category_id'] ?? ''; ?>"
                     data-title="<?php echo htmlspecialchars(strtolower($drill['title'])); ?>"
                     data-coach="<?php echo strtolower($coachName); ?>">
                    <div class="drill-image">
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
    FACEOFF_FROM_BOARDS: 22 / 85   // 22 ft from boards
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
                case 'full':
                default:
                    drawThumbnailFullIce(ctx, w, h);
                    break;
            }
            
            // Draw diagram objects if available
            if (diagramData && diagramData.length > 0) {
                // Scale factor for thumbnail using source dimensions
                const scaleX = w / sourceWidth;
                const scaleY = h / sourceHeight;
                
                diagramData.forEach(obj => {
                    const x = (obj.x || 0) * scaleX;
                    const y = (obj.y || 0) * scaleY;
                    
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
                    } else if (obj.type === 'line') {
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo((obj.x1 || 0) * scaleX, (obj.y1 || 0) * scaleY);
                        ctx.lineTo((obj.x2 || 0) * scaleX, (obj.y2 || 0) * scaleY);
                        ctx.stroke();
                    } else if (obj.type === 'arrow') {
                        const x1 = (obj.x1 || 0) * scaleX;
                        const y1 = (obj.y1 || 0) * scaleY;
                        const x2 = (obj.x2 || 0) * scaleX;
                        const y2 = (obj.y2 || 0) * scaleY;
                        const headlen = 6;
                        const angle = Math.atan2(y2 - y1, x2 - x1);
                        
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.fillStyle = obj.color || '#333';
                        ctx.lineWidth = 1;
                        
                        ctx.beginPath();
                        ctx.moveTo(x1, y1);
                        ctx.lineTo(x2, y2);
                        ctx.stroke();
                        
                        ctx.beginPath();
                        ctx.moveTo(x2, y2);
                        ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
                        ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
                        ctx.closePath();
                        ctx.fill();
                    } else if (obj.type === 'dashed') {
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.lineWidth = 1;
                        ctx.setLineDash([4, 3]);
                        ctx.beginPath();
                        ctx.moveTo((obj.x1 || 0) * scaleX, (obj.y1 || 0) * scaleY);
                        ctx.lineTo((obj.x2 || 0) * scaleX, (obj.y2 || 0) * scaleY);
                        ctx.stroke();
                        ctx.setLineDash([]);
                    } else if (obj.type === 'squiggly' || obj.type === 'freehand') {
                        ctx.strokeStyle = obj.color || '#333';
                        ctx.lineWidth = 1;
                        if (obj.points && obj.points.length > 1) {
                            ctx.beginPath();
                            ctx.moveTo(obj.points[0].x * scaleX, obj.points[0].y * scaleY);
                            for (let i = 1; i < obj.points.length; i++) {
                                ctx.lineTo(obj.points[i].x * scaleX, obj.points[i].y * scaleY);
                            }
                            ctx.stroke();
                        } else if (obj.x1 !== undefined) {
                            ctx.beginPath();
                            ctx.moveTo((obj.x1 || 0) * scaleX, (obj.y1 || 0) * scaleY);
                            ctx.lineTo((obj.x2 || 0) * scaleX, (obj.y2 || 0) * scaleY);
                            ctx.stroke();
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
                    } else if (obj.type === 'text') {
                        ctx.fillStyle = obj.color || '#333';
                        ctx.font = 'bold 8px Inter, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(obj.text || '', x, y);
                    }
                });
            }
            
            // Draw rink border
            ctx.strokeStyle = '#0033a0';
            ctx.lineWidth = 2;
            const cornerRadius = Math.min(w, h) * 0.08;
            ctx.beginPath();
            ctx.moveTo(cornerRadius, 0);
            ctx.lineTo(w - cornerRadius, 0);
            ctx.quadraticCurveTo(w, 0, w, cornerRadius);
            ctx.lineTo(w, h - cornerRadius);
            ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
            ctx.lineTo(cornerRadius, h);
            ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
            ctx.lineTo(0, cornerRadius);
            ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
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

// Draw full ice view for thumbnails
function drawThumbnailFullIce(ctx, w, h) {
    // NHL proportions
    const goalLinePos = NHL_RINK.GOAL_LINE;
    const blueLinePos = NHL_RINK.BLUE_LINE;
    const faceoffFromGoal = goalLinePos + NHL_RINK.FACEOFF_FROM_GOAL;
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    
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
        { x: w * faceoffFromGoal, y: h * faceoffFromBoards },
        { x: w * faceoffFromGoal, y: h * (1 - faceoffFromBoards) },
        { x: w * (1 - faceoffFromGoal), y: h * faceoffFromBoards },
        { x: w * (1 - faceoffFromGoal), y: h * (1 - faceoffFromBoards) }
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
}

// Draw half ice view for thumbnails
function drawThumbnailHalfIce(ctx, w, h, side) {
    // NHL proportions
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    const faceoffRadius = w * NHL_RINK.FACEOFF_RADIUS;
    const creaseRadius = w * NHL_RINK.CREASE_RADIUS;
    
    // Blue line position
    const blueLineY = side === 'top' ? h * 0.85 : h * 0.15;
    
    // Blue line
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0, blueLineY);
    ctx.lineTo(w, blueLineY);
    ctx.stroke();
    
    // Goal and faceoff positions
    const goalY = side === 'top' ? h * 0.08 : h * 0.92;
    const faceoffY = side === 'top' ? h * 0.35 : h * 0.65;
    
    // Left faceoff circle
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(w * faceoffFromBoards, faceoffY, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(w * faceoffFromBoards, faceoffY, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, 'vertical');
    
    // Right faceoff circle
    ctx.beginPath();
    ctx.arc(w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(w * (1 - faceoffFromBoards), faceoffY, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 'vertical');
    
    // Goal crease - 6 ft radius semicircle
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    if (side === 'top') {
        ctx.arc(w * 0.5, goalY, creaseRadius, 0, Math.PI);
    } else {
        ctx.arc(w * 0.5, goalY, creaseRadius, 0, Math.PI, true);
    }
    ctx.fill();
    ctx.stroke();
    
    // Goal line
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w * 0.3, goalY);
    ctx.lineTo(w * 0.7, goalY);
    ctx.stroke();
}

// Draw zone view for thumbnails
function drawThumbnailZone(ctx, w, h, side) {
    // NHL proportions
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
    const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
    
    // Blue line position (far from goal)
    const blueLineX = side === 'left' ? w * 0.85 : w * 0.15;
    
    // Blue line
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(blueLineX, 0);
    ctx.lineTo(blueLineX, h);
    ctx.stroke();
    
    // Goal and faceoff positions
    const goalX = side === 'left' ? w * 0.08 : w * 0.92;
    const faceoffX = side === 'left' ? w * 0.35 : w * 0.65;
    
    // Top faceoff circle
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(faceoffX, h * faceoffFromBoards, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(faceoffX, h * faceoffFromBoards, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, 'horizontal');
    
    // Bottom faceoff circle
    ctx.beginPath();
    ctx.arc(faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(faceoffX, h * (1 - faceoffFromBoards), 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 'horizontal');
    
    // Goal crease - 6 ft radius semicircle
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    if (side === 'left') {
        ctx.arc(goalX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
    } else {
        ctx.arc(goalX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2, true);
    }
    ctx.fill();
    ctx.stroke();
    
    // Goal line
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(goalX, h * 0.3);
    ctx.lineTo(goalX, h * 0.7);
    ctx.stroke();
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
