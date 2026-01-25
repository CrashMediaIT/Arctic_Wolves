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

// Add demo drills if none exist
if (count($drills) === 0) {
    $today = new DateTime();
    $categories = [
        ['id' => 'demo-1', 'name' => 'Skating'],
        ['id' => 'demo-2', 'name' => 'Shooting'],
        ['id' => 'demo-3', 'name' => 'Passing'],
        ['id' => 'demo-4', 'name' => 'Stickhandling'],
        ['id' => 'demo-5', 'name' => 'Team Play'],
        ['id' => 'demo-6', 'name' => 'Goalie']
    ];
    
    $drills = [
        [
            'id' => 'demo-1',
            'title' => 'Crossover Speed Drill',
            'category_id' => 'demo-1',
            'category_name' => 'Skating',
            'description' => 'Develop explosive crossover power and speed through tight turns. Players skate figure-8 patterns with emphasis on knee bend and full extension.',
            'first_name' => 'Mike',
            'last_name' => 'Smith',
            'created_at' => (clone $today)->modify('-3 days')->format('Y-m-d H:i:s'),
            'custom_image' => '',
            'ihs_source_url' => '',
            'created_by' => null
        ],
        [
            'id' => 'demo-2',
            'title' => 'One-Timer Practice',
            'category_id' => 'demo-2',
            'category_name' => 'Shooting',
            'description' => 'Work on timing and accuracy with one-timer shots. Partners pass across the slot for quick release shots on goal.',
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'created_at' => (clone $today)->modify('-5 days')->format('Y-m-d H:i:s'),
            'custom_image' => '',
            'ihs_source_url' => '',
            'created_by' => null
        ],
        [
            'id' => 'demo-3',
            'title' => 'Breakout Pattern Drill',
            'category_id' => 'demo-3',
            'category_name' => 'Passing',
            'description' => 'Practice standard breakout patterns with quick, accurate passes. D-to-D movements and rim plays included.',
            'first_name' => 'David',
            'last_name' => 'Williams',
            'created_at' => (clone $today)->modify('-7 days')->format('Y-m-d H:i:s'),
            'custom_image' => '',
            'ihs_source_url' => '',
            'created_by' => null
        ],
        [
            'id' => 'demo-4',
            'title' => 'Tight Space Dangles',
            'category_id' => 'demo-4',
            'category_name' => 'Stickhandling',
            'description' => 'Improve puck control in confined areas. Cone weaves, toe drags, and deking moves through obstacle course.',
            'first_name' => 'Mike',
            'last_name' => 'Smith',
            'created_at' => (clone $today)->modify('-10 days')->format('Y-m-d H:i:s'),
            'custom_image' => '',
            'ihs_source_url' => '',
            'created_by' => null
        ],
        [
            'id' => 'demo-5',
            'title' => '3-on-2 Rush Drill',
            'category_id' => 'demo-5',
            'category_name' => 'Team Play',
            'description' => 'Full speed odd-man rush scenarios. Develop decision-making and finishing skills in offensive zone entries.',
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'created_at' => (clone $today)->modify('-2 days')->format('Y-m-d H:i:s'),
            'custom_image' => '',
            'ihs_source_url' => '',
            'created_by' => null
        ],
        [
            'id' => 'demo-6',
            'title' => 'Goalie Movement Drill',
            'category_id' => 'demo-6',
            'category_name' => 'Goalie',
            'description' => 'Quick lateral pushes across the crease with shot tracking. Butterfly slides and recovery movements.',
            'first_name' => 'David',
            'last_name' => 'Williams',
            'created_at' => (clone $today)->modify('-8 days')->format('Y-m-d H:i:s'),
            'custom_image' => '',
            'ihs_source_url' => '',
            'created_by' => null
        ]
    ];
    $is_demo_drills = true;
} else {
    $is_demo_drills = false;
}
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

<!-- Edit Drill Modal -->
<div id="edit-drill-modal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Drill</h2>
            <button class="modal-close" onclick="closeModal('edit-drill-modal')">&times;</button>
        </div>
        <form method="POST" action="process_drills.php" id="editDrillForm">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="save_drill">
            <input type="hidden" name="drill_id" id="editDrillId">
            
            <div class="modal-body">
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
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="editDrillDescription" class="form-textarea" rows="4" placeholder="Describe the drill..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Video URL (optional)</label>
                    <input type="url" name="video_url" id="editDrillVideoUrl" class="form-input" placeholder="https://...">
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
            <button class="modal-close" onclick="closeModal('view-drill-modal')">&times;</button>
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
// View drill details
let currentViewDrillId = null;
let drillsData = <?php echo json_encode($drills); ?>;

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
    
    document.getElementById('editDrillId').value = drill.id;
    document.getElementById('editDrillTitle').value = drill.title || '';
    document.getElementById('editDrillCategory').value = drill.category_name || '';
    document.getElementById('editDrillDescription').value = drill.description || '';
    document.getElementById('editDrillVideoUrl').value = drill.video_url || '';
    
    openModal('edit-drill-modal');
}

// Handle view and edit button clicks
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-action="view"]').forEach(btn => {
        const drillId = btn.getAttribute('data-id');
        if (drillId) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                viewDrill(drillId);
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
});
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
