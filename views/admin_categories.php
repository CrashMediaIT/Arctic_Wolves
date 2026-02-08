<?php
// Determine which tab should be active based on URL parameter
$activeTab = $_GET['tab'] ?? 'skills';
$validTabs = ['skills', 'drills', 'merchandise', 'teams', 'locations', 'skill_levels'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'skills';
}

// Fetch skill levels for the Skill Levels tab
$skill_levels_stmt = $pdo->query("SELECT * FROM skill_levels ORDER BY display_order ASC, name ASC");
$skill_levels = $skill_levels_stmt->fetchAll();

// Get Google Maps API key from system settings for Locations tab
try {
    $api_key_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'google_maps_api_key'");
    $google_maps_api_key = $api_key_stmt->fetchColumn() ?: '';
} catch (Exception $e) {
    error_log('Failed to retrieve Google Maps API key: ' . $e->getMessage());
    $google_maps_api_key = '';
}

// Fetch teams for the Teams tab
$teams_stmt = $pdo->query("
    SELECT t.*, 
           (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM users u WHERE u.id = t.coach_id) as head_coach_name,
           (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM users u WHERE u.id = t.assistant_coach_id) as assistant_coach_name,
           (SELECT COUNT(*) FROM sessions WHERE team_id = t.id) as session_count
    FROM teams t
    ORDER BY t.is_active DESC, t.name ASC
");
$teams = $teams_stmt->fetchAll();

// Fetch locations for the Locations tab
$locations_stmt = $pdo->query("
    SELECT l.*, 
           (SELECT COUNT(*) FROM sessions WHERE arena = l.name) as session_count
    FROM locations l
    ORDER BY l.city, l.name
");
$locations = $locations_stmt->fetchAll();

// Fetch coaches for team assignment dropdown
$coaches_stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role IN ('coach', 'team_coach', 'admin') ORDER BY last_name, first_name");
$coaches = $coaches_stmt->fetchAll();

// Fetch seasons for team assignment
$seasons_for_teams = [];
try {
    $seasons_stmt = $pdo->query("SELECT id, name, start_date, end_date, is_active FROM seasons ORDER BY is_active DESC, start_date DESC");
    $seasons_for_teams = $seasons_stmt->fetchAll();
} catch (PDOException $e) {
    // seasons table may not exist yet
}

// Fetch team-season associations for display
$team_season_map = [];
try {
    $ts_stmt = $pdo->query("SELECT ts.team_id, ts.season_id, s.name as season_name FROM team_seasons ts INNER JOIN seasons s ON ts.season_id = s.id ORDER BY s.start_date DESC");
    foreach ($ts_stmt->fetchAll() as $ts_row) {
        $team_season_map[$ts_row['team_id']][] = ['season_id' => $ts_row['season_id'], 'season_name' => $ts_row['season_name']];
    }
} catch (PDOException $e) {
    // team_seasons table may not exist yet
}
?>
<!-- Admin Resource Management View -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-layer-group"></i> Resource Management</h1>
        <p class="page-description">Manage skills, drill types, merchandise categories, teams, training locations, and skill levels</p>
    </div>
</div>

<!-- Resource Management Tabs -->
<div class="page-tabs">
    <button type="button" class="page-tab <?= $activeTab === 'skills' ? 'active' : '' ?>" data-tab="skills" data-action="switch-tab" data-tab-handled="true">
        <i class="fas fa-star"></i> Skills
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'drills' ? 'active' : '' ?>" data-tab="drills" data-action="switch-tab" data-tab-handled="true">
        <i class="fas fa-hockey-puck"></i> Drill Types
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'merchandise' ? 'active' : '' ?>" data-tab="merchandise" data-action="switch-tab" data-tab-handled="true">
        <i class="fas fa-shopping-bag"></i> Merchandise
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'teams' ? 'active' : '' ?>" data-tab="teams" data-action="switch-tab" data-tab-handled="true">
        <i class="fas fa-users"></i> Teams
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'locations' ? 'active' : '' ?>" data-tab="locations" data-action="switch-tab" data-tab-handled="true">
        <i class="fas fa-map-marker-alt"></i> Locations
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'skill_levels' ? 'active' : '' ?>" data-tab="skill_levels" data-action="switch-tab" data-tab-handled="true">
        <i class="fas fa-chart-line"></i> Skill Levels
    </button>
</div>

<div class="page-tab-content">
    <!-- Skills Tab -->
    <div class="tab-content <?= $activeTab === 'skills' ? 'active' : '' ?>" id="skills-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Evaluation Skills</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-skill-modal">
                    <i class="fas fa-plus"></i> Add Skill
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Skills defined here are used in athlete evaluation forms to assess player development.
                </p>
                <div class="categories-grid">
                    <?php
                    // Fetch all skills from database
                    $stmt = $pdo->prepare("SELECT es.id, es.name, es.description, ec.name as category_name 
                                          FROM eval_skills es 
                                          LEFT JOIN eval_categories ec ON es.category_id = ec.id 
                                          ORDER BY es.name ASC");
                    $stmt->execute();
                    $skills = $stmt->fetchAll();
                    
                    if (count($skills) > 0):
                        foreach ($skills as $skill):
                    ?>
                    <div class="category-card">
                        <div class="category-card-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="category-card-content">
                            <h4><?= htmlspecialchars($skill['name']) ?></h4>
                            <p><?= htmlspecialchars($skill['description'] ?: 'No description') ?></p>
                            <?php if ($skill['category_name']): ?>
                            <span class="category-tag"><?= htmlspecialchars($skill['category_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $skill['id'] ?>" 
                                    data-type="skill" 
                                    data-name="<?= htmlspecialchars($skill['name']) ?>"
                                    data-description="<?= htmlspecialchars($skill['description'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $skill['id'] ?>" 
                                    data-type="skill" 
                                    data-name="<?= htmlspecialchars($skill['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <h4>No Skills Found</h4>
                        <p>Create your first skill to use in athlete evaluations.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Drill Types Tab -->
    <div class="tab-content <?= $activeTab === 'drills' ? 'active' : '' ?>" id="drills-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hockey-puck"></i> Drill Types</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-drill-type-modal">
                    <i class="fas fa-plus"></i> Add Drill Type
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Drill types help categorize and organize training drills by the skills they develop.
                </p>
                <div class="categories-grid">
                    <?php
                    // Fetch all drill categories from database
                    $stmt = $pdo->prepare("SELECT id, name, description, position_type FROM drill_categories ORDER BY name ASC");
                    $stmt->execute();
                    $drill_types = $stmt->fetchAll();
                    
                    if (count($drill_types) > 0):
                        foreach ($drill_types as $type):
                            // Format position type for display
                            $positionLabel = '';
                            $positionIcon = '';
                            switch ($type['position_type'] ?? 'both') {
                                case 'player':
                                    $positionLabel = 'Player';
                                    $positionIcon = 'fa-skating';
                                    break;
                                case 'goalie':
                                    $positionLabel = 'Goalie';
                                    $positionIcon = 'fa-shield-alt';
                                    break;
                                default:
                                    $positionLabel = 'All Positions';
                                    $positionIcon = 'fa-users';
                            }
                    ?>
                    <div class="category-card">
                        <div class="category-card-icon drill-type">
                            <i class="fas fa-hockey-puck"></i>
                        </div>
                        <div class="category-card-content">
                            <h4><?= htmlspecialchars($type['name']) ?></h4>
                            <p><?= htmlspecialchars($type['description'] ?: 'No description') ?></p>
                            <span class="category-tag position-<?= htmlspecialchars($type['position_type'] ?? 'both') ?>">
                                <i class="fas <?= $positionIcon ?>"></i> <?= $positionLabel ?>
                            </span>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $type['id'] ?>" 
                                    data-type="drill_type" 
                                    data-name="<?= htmlspecialchars($type['name']) ?>"
                                    data-description="<?= htmlspecialchars($type['description'] ?? '') ?>"
                                    data-position-type="<?= htmlspecialchars($type['position_type'] ?? 'both') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $type['id'] ?>" 
                                    data-type="drill_type" 
                                    data-name="<?= htmlspecialchars($type['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <div class="empty-state">
                        <i class="fas fa-hockey-puck"></i>
                        <h4>No Drill Types Found</h4>
                        <p>Create drill types to organize your training drills.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Merchandise Categories Tab -->
    <div class="tab-content <?= $activeTab === 'merchandise' ? 'active' : '' ?>" id="merchandise-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shopping-bag"></i> Merchandise Categories</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-merchandise-modal">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Merchandise categories organize products in the online shop and POS system.
                </p>
                <div class="categories-grid">
                    <?php
                    // Fetch all merchandise categories from database
                    $stmt = $pdo->prepare("SELECT id, name, description, is_active FROM merchandise_categories ORDER BY display_order, name ASC");
                    $stmt->execute();
                    $merchandise_categories = $stmt->fetchAll();
                    
                    if (count($merchandise_categories) > 0):
                        foreach ($merchandise_categories as $merch):
                    ?>
                    <div class="category-card <?= !$merch['is_active'] ? 'inactive' : '' ?>">
                        <div class="category-card-icon merchandise">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="category-card-content">
                            <h4>
                                <?= htmlspecialchars($merch['name']) ?>
                                <?php if (!$merch['is_active']): ?>
                                <span class="status-badge inactive">Inactive</span>
                                <?php endif; ?>
                            </h4>
                            <p><?= htmlspecialchars($merch['description'] ?: 'No description') ?></p>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $merch['id'] ?>" 
                                    data-type="merchandise" 
                                    data-name="<?= htmlspecialchars($merch['name']) ?>"
                                    data-description="<?= htmlspecialchars($merch['description'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $merch['id'] ?>" 
                                    data-type="merchandise" 
                                    data-name="<?= htmlspecialchars($merch['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <h4>No Merchandise Categories Found</h4>
                        <p>Create categories to organize products in your shop.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Teams Tab -->
    <div class="tab-content <?= $activeTab === 'teams' ? 'active' : '' ?>" id="teams-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Teams</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-team-modal">
                    <i class="fas fa-plus"></i> Add Team
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Teams defined here are used for session assignments, packages, and athlete roster management.
                </p>
                <div class="categories-grid">
                    <?php if (count($teams) > 0): ?>
                        <?php foreach ($teams as $team): ?>
                    <div class="category-card <?= !$team['is_active'] ? 'inactive' : '' ?>">
                        <div class="category-card-icon team">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="category-card-content">
                            <h4>
                                <?= htmlspecialchars($team['name']) ?>
                                <?php if (!$team['is_active']): ?>
                                <span class="status-badge inactive">Inactive</span>
                                <?php endif; ?>
                            </h4>
                            <p>
                                <?php if ($team['age_group']): ?>
                                    <strong>Age Group:</strong> <?= htmlspecialchars($team['age_group']) ?><br>
                                <?php endif; ?>
                                <?php if ($team['skill_level']): ?>
                                    <strong>Level:</strong> <?= htmlspecialchars($team['skill_level']) ?><br>
                                <?php endif; ?>
                                <?php if ($team['head_coach_name']): ?>
                                    <strong>Coach:</strong> <?= htmlspecialchars($team['head_coach_name']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($team['division']): ?>
                            <span class="category-tag"><?= htmlspecialchars($team['division']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($team_season_map[$team['id']])): ?>
                                <?php foreach ($team_season_map[$team['id']] as $ts_info): ?>
                                <span class="category-tag" style="background: rgba(107, 70, 193, 0.2); color: #a78bfa;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i><?= htmlspecialchars($ts_info['season_name']) ?>
                                </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if ($team['session_count'] > 0): ?>
                            <span class="category-tag" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa;">
                                <?= $team['session_count'] ?> sessions
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $team['id'] ?>" 
                                    data-type="team" 
                                    data-name="<?= htmlspecialchars($team['name']) ?>"
                                    data-age-group="<?= htmlspecialchars($team['age_group'] ?? '') ?>"
                                    data-skill-level="<?= htmlspecialchars($team['skill_level'] ?? '') ?>"
                                    data-division="<?= htmlspecialchars($team['division'] ?? '') ?>"
                                    data-season="<?= htmlspecialchars($team['season'] ?? '') ?>"
                                    data-season-ids="<?= htmlspecialchars(implode(',', array_column($team_season_map[$team['id']] ?? [], 'season_id'))) ?>"
                                    data-coach-id="<?= $team['coach_id'] ?? '' ?>"
                                    data-assistant-coach-id="<?= $team['assistant_coach_id'] ?? '' ?>"
                                    data-is-active="<?= $team['is_active'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($team['session_count'] == 0): ?>
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $team['id'] ?>" 
                                    data-type="team" 
                                    data-name="<?= htmlspecialchars($team['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No Teams Found</h4>
                        <p>Create your first team to organize athletes and assign sessions.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Locations Tab -->
    <div class="tab-content <?= $activeTab === 'locations' ? 'active' : '' ?>" id="locations-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-map-marker-alt"></i> Training Locations</h3>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="testGoogleAPI()">
                        <i class="fas fa-vial"></i> Test Google API
                    </button>
                    <button type="button" class="btn btn-primary" data-action="add" data-modal="add-location-modal">
                        <i class="fas fa-plus"></i> Add Location
                    </button>
                </div>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Locations defined here are used when creating sessions and can be searched via Google Places API.
                </p>
                <div class="categories-grid">
                    <?php if (count($locations) > 0): ?>
                        <?php foreach ($locations as $location): ?>
                    <div class="category-card">
                        <div class="category-card-icon location">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="category-card-content">
                            <h4><?= htmlspecialchars($location['name']) ?></h4>
                            <p>
                                <?php if ($location['city']): ?>
                                    <strong>City:</strong> <?= htmlspecialchars($location['city']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($location['google_place_id']): ?>
                            <a href="https://www.google.com/maps/place/?q=place_id:<?= htmlspecialchars($location['google_place_id']) ?>" 
                               target="_blank" class="category-tag" style="text-decoration: none;">
                                <i class="fas fa-map"></i> View on Map
                            </a>
                            <?php endif; ?>
                            <?php if ($location['session_count'] > 0): ?>
                            <span class="category-tag" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa;">
                                <?= $location['session_count'] ?> sessions
                            </span>
                            <?php endif; ?>
                            <?php if ($location['image_url']): ?>
                            <div style="margin-top: 10px;">
                                <img src="<?= htmlspecialchars($location['image_url']) ?>" alt="Location" style="max-width: 150px; border-radius: 6px;">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $location['id'] ?>" 
                                    data-type="location" 
                                    data-name="<?= htmlspecialchars($location['name']) ?>"
                                    data-city="<?= htmlspecialchars($location['city'] ?? '') ?>"
                                    data-google-place-id="<?= htmlspecialchars($location['google_place_id'] ?? '') ?>"
                                    data-image-url="<?= htmlspecialchars($location['image_url'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($location['session_count'] == 0): ?>
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $location['id'] ?>" 
                                    data-type="location" 
                                    data-name="<?= htmlspecialchars($location['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>No Locations Found</h4>
                        <p>Add your first training location to get started.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Skill Levels Tab -->
    <div class="tab-content <?= $activeTab === 'skill_levels' ? 'active' : '' ?>" id="skill_levels-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Skill Levels</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-skill-level-modal">
                    <i class="fas fa-plus"></i> Add Skill Level
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Skill levels are used to categorize sessions and filter by athlete proficiency (e.g., Beginner, Intermediate, Advanced).
                </p>
                <div class="categories-grid">
                    <?php if (count($skill_levels) > 0): ?>
                        <?php foreach ($skill_levels as $sl): ?>
                    <div class="category-card">
                        <div class="category-card-icon" style="background: rgba(107, 70, 193, 0.2);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="category-card-content">
                            <h4><?= htmlspecialchars($sl['name']) ?></h4>
                            <p><?= htmlspecialchars($sl['description'] ?: 'No description') ?></p>
                            <span class="category-tag" style="background: rgba(107, 70, 193, 0.2); color: #a78bfa;">
                                Order: <?= $sl['display_order'] ?>
                            </span>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
                                    data-action="delete-skill-level" 
                                    data-id="<?= $sl['id'] ?>" 
                                    data-name="<?= htmlspecialchars($sl['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <h4>No Skill Levels Found</h4>
                        <p>Add your first skill level to get started.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Categories Page Styles - Following Style Guide */
.info-text {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-5);
    padding: var(--space-4);
    background: rgba(107, 70, 193, 0.1);
    border-radius: var(--radius-lg);
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
}

.info-text i {
    color: var(--primary-light);
    font-size: 16px;
}

/* Category Cards Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--space-4);
}

/* Individual Category Card */
.category-card {
    display: flex;
    align-items: flex-start;
    gap: var(--space-4);
    padding: var(--space-5);
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    transition: all var(--transition-normal);
    position: relative;
}

.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary);
    border-radius: var(--radius-xl) 0 0 var(--radius-xl);
    opacity: 0;
    transition: opacity var(--transition-normal);
}

.category-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.category-card:hover::before {
    opacity: 1;
}

.category-card.inactive {
    opacity: 0.6;
}

/* Category Card Icon */
.category-card-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--text-white);
    flex-shrink: 0;
    box-shadow: var(--shadow-primary);
}

.category-card-icon.drill-type {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
}

.category-card-icon.merchandise {
    background: linear-gradient(135deg, #10B981, #059669);
}

.category-card-icon.team {
    background: linear-gradient(135deg, #F59E0B, #D97706);
}

.category-card-icon.location {
    background: linear-gradient(135deg, #EF4444, #DC2626);
}

/* Category Card Content */
.category-card-content {
    flex: 1;
    min-width: 0;
}

.category-card-content h4 {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-2);
    display: flex;
    align-items: center;
    gap: var(--space-2);
    flex-wrap: wrap;
}

.category-card-content p {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0;
}

.category-tag {
    display: inline-block;
    margin-top: var(--space-2);
    padding: 4px 10px;
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light);
    border-radius: var(--radius-md);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
}

/* Category Card Actions */
.category-card-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-normal);
}

.btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--text-white);
}

.btn-icon-danger:hover {
    background: var(--error);
    border-color: var(--error);
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    text-transform: uppercase;
}

.status-badge.inactive {
    background: rgba(239, 68, 68, 0.15);
    color: var(--error);
}

/* Position Type Badges */
.category-tag.position-player {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.category-tag.position-goalie {
    background: rgba(168, 85, 247, 0.2);
    color: #c084fc;
}

.category-tag.position-both {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
}

.category-tag i {
    margin-right: 4px;
}

/* Form Help Text */
.form-help {
    display: block;
    margin-top: 4px;
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: var(--space-10) var(--space-6);
    background: var(--bg-main);
    border: 1px dashed var(--border);
    border-radius: var(--radius-xl);
}

.empty-state i {
    font-size: 48px;
    color: var(--text-muted);
    margin-bottom: var(--space-4);
    display: block;
}

.empty-state h4 {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-2);
}

.empty-state p {
    font-size: var(--font-size-base);
    color: var(--text-muted);
    margin-bottom: var(--space-5);
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .category-card {
        flex-wrap: wrap;
    }
    
    .category-card-actions {
        flex-direction: row;
        width: 100%;
        justify-content: flex-end;
        margin-top: var(--space-3);
    }
}
</style>

<!-- Add Skill Modal -->
<div id="add-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-star"></i> Add Skill</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-skill-modal')">&times;</button>
        </div>
        <form id="add-skill-form" method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_skill">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Skill Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Skating, Passing, Shooting">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Describe what this skill evaluates"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-skill-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Skill
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Skill Modal -->
<div id="edit-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Skill</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-skill-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="skill">
            <input type="hidden" name="id" id="edit-skill-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Skill Name *</label>
                    <input type="text" name="name" id="edit-skill-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-skill-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-skill-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Skill
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Drill Type Modal -->
<div id="add-drill-type-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-hockey-puck"></i> Add Drill Type</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-drill-type-modal')">&times;</button>
        </div>
        <form id="add-drill-type-form" method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_drill_type">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Drill Type Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Skating, Shooting, Passing">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Position Category *</label>
                    <select name="position_type" class="form-input" required>
                        <option value="both">All Positions (Player & Goalie)</option>
                        <option value="player">Player Only</option>
                        <option value="goalie">Goalie Only</option>
                    </select>
                    <small class="form-help">Select which position(s) this drill type applies to</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Describe what this drill type focuses on"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-drill-type-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Drill Type
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Drill Type Modal -->
<div id="edit-drill-type-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Drill Type</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-drill-type-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="drill_type">
            <input type="hidden" name="id" id="edit-drill-type-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Drill Type Name *</label>
                    <input type="text" name="name" id="edit-drill-type-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Position Category *</label>
                    <select name="position_type" id="edit-drill-type-position" class="form-input" required>
                        <option value="both">All Positions (Player & Goalie)</option>
                        <option value="player">Player Only</option>
                        <option value="goalie">Goalie Only</option>
                    </select>
                    <small class="form-help">Select which position(s) this drill type applies to</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-drill-type-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-drill-type-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Drill Type
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Merchandise Category Modal -->
<div id="add-merchandise-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-shopping-bag"></i> Add Merchandise Category</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-merchandise-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_merchandise_category">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Apparel, Equipment, Accessories">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Brief description of this category"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-merchandise-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Merchandise Category Modal -->
<div id="edit-merchandise-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Merchandise Category</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-merchandise-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="merchandise">
            <input type="hidden" name="id" id="edit-merchandise-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" id="edit-merchandise-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-merchandise-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-merchandise-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Team Modal -->
<div id="add-team-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-users"></i> Add Team</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-team-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_team">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Team Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Arctic Wolves U14">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Age Group</label>
                    <select name="age_group" class="form-input">
                        <option value="">Select Age Group</option>
                        <option value="U8">U8</option>
                        <option value="U10">U10</option>
                        <option value="U12">U12</option>
                        <option value="U14">U14</option>
                        <option value="U16">U16</option>
                        <option value="U18">U18</option>
                        <option value="Adult">Adult</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Skill Level</label>
                    <select name="skill_level" class="form-input">
                        <option value="">Select Skill Level</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                        <option value="Elite">Elite</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Division</label>
                    <input type="text" name="division" class="form-input" placeholder="e.g., AAA, AA, A">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Seasons</label>
                    <div class="season-checkboxes" style="max-height: 160px; overflow-y: auto; border: 1px solid var(--border, #1e293b); border-radius: 6px; padding: 12px; background: var(--bg-main, #06080b);">
                        <?php if (!empty($seasons_for_teams)): ?>
                            <?php foreach ($seasons_for_teams as $s): ?>
                            <label style="display: flex; align-items: center; gap: 8px; padding: 6px 0; cursor: pointer; color: #fff; font-size: 14px;">
                                <input type="checkbox" name="season_ids[]" value="<?= $s['id'] ?>" <?= $s['is_active'] ? 'checked' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                                <?php if ($s['is_active']): ?>
                                    <span style="background: rgba(34,197,94,0.2); color: #22c55e; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Active</span>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #64748b; font-size: 13px; margin: 0;">No seasons created yet. Create seasons in Team Coach Management first.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Head Coach</label>
                    <select name="coach_id" class="form-input">
                        <option value="">Select Head Coach</option>
                        <?php foreach ($coaches as $coach): ?>
                        <option value="<?= $coach['id'] ?>"><?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assistant Coach</label>
                    <select name="assistant_coach_id" class="form-input">
                        <option value="">Select Assistant Coach</option>
                        <?php foreach ($coaches as $coach): ?>
                        <option value="<?= $coach['id'] ?>"><?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-team-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Team
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Team Modal -->
<div id="edit-team-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Team</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-team-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="team">
            <input type="hidden" name="id" id="edit-team-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Team Name *</label>
                    <input type="text" name="name" id="edit-team-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Age Group</label>
                    <select name="age_group" id="edit-team-age-group" class="form-input">
                        <option value="">Select Age Group</option>
                        <option value="U8">U8</option>
                        <option value="U10">U10</option>
                        <option value="U12">U12</option>
                        <option value="U14">U14</option>
                        <option value="U16">U16</option>
                        <option value="U18">U18</option>
                        <option value="Adult">Adult</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Skill Level</label>
                    <select name="skill_level" id="edit-team-skill-level" class="form-input">
                        <option value="">Select Skill Level</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                        <option value="Elite">Elite</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Division</label>
                    <input type="text" name="division" id="edit-team-division" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Seasons</label>
                    <div id="edit-team-seasons-container" class="season-checkboxes" style="max-height: 160px; overflow-y: auto; border: 1px solid var(--border, #1e293b); border-radius: 6px; padding: 12px; background: var(--bg-main, #06080b);">
                        <?php if (!empty($seasons_for_teams)): ?>
                            <?php foreach ($seasons_for_teams as $s): ?>
                            <label style="display: flex; align-items: center; gap: 8px; padding: 6px 0; cursor: pointer; color: #fff; font-size: 14px;">
                                <input type="checkbox" name="season_ids[]" value="<?= $s['id'] ?>" class="edit-team-season-cb">
                                <?= htmlspecialchars($s['name']) ?>
                                <?php if ($s['is_active']): ?>
                                    <span style="background: rgba(34,197,94,0.2); color: #22c55e; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Active</span>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #64748b; font-size: 13px; margin: 0;">No seasons created yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Head Coach</label>
                    <select name="coach_id" id="edit-team-coach-id" class="form-input">
                        <option value="">Select Head Coach</option>
                        <?php foreach ($coaches as $coach): ?>
                        <option value="<?= $coach['id'] ?>"><?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assistant Coach</label>
                    <select name="assistant_coach_id" id="edit-team-assistant-coach-id" class="form-input">
                        <option value="">Select Assistant Coach</option>
                        <?php foreach ($coaches as $coach): ?>
                        <option value="<?= $coach['id'] ?>"><?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" id="edit-team-is-active" class="form-input">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-team-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Team
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Location Modal -->
<div id="add-location-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-map-marker-alt"></i> Add Location</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-location-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php" id="add-location-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_location">
            <input type="hidden" name="google_place_id" id="add-google-place-id">
            <input type="hidden" name="image_url" id="add-location-image-url">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Search Location (Google Places)</label>
                    <input type="text" id="add-place-search" class="form-input" placeholder="Search for a location...">
                    <div id="add-place-results" style="display: none; background: var(--bg-main); border: 1px solid var(--border); border-radius: 6px; margin-top: 5px; max-height: 200px; overflow-y: auto;"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Arena Name *</label>
                    <input type="text" name="name" id="add-location-name" class="form-input" required placeholder="e.g., Downtown Ice Arena">
                </div>
                
                <div class="form-group">
                    <label class="form-label">City *</label>
                    <input type="text" name="city" id="add-location-city" class="form-input" required placeholder="e.g., Vancouver">
                </div>
                
                <div id="add-location-preview" style="display: none; margin-bottom: 12px;">
                    <label class="form-label">Location Image</label>
                    <img id="add-preview-image" src="" alt="Location" style="max-width: 100%; border-radius: 6px; margin-top: 5px;">
                    <button type="button" onclick="clearAddLocationImage()" style="margin-top: 5px; padding: 6px 12px; background: var(--error); color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        <i class="fas fa-times"></i> Remove Image
                    </button>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-location-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Location
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Location Modal -->
<div id="edit-location-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Location</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-location-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php" id="edit-location-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="location">
            <input type="hidden" name="id" id="edit-location-id">
            <input type="hidden" name="google_place_id" id="edit-google-place-id">
            <input type="hidden" name="image_url" id="edit-location-image-url">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Search Location (Google Places)</label>
                    <input type="text" id="edit-place-search" class="form-input" placeholder="Search for a location...">
                    <div id="edit-place-results" style="display: none; background: var(--bg-main); border: 1px solid var(--border); border-radius: 6px; margin-top: 5px; max-height: 200px; overflow-y: auto;"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Arena Name *</label>
                    <input type="text" name="name" id="edit-location-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">City *</label>
                    <input type="text" name="city" id="edit-location-city" class="form-input" required>
                </div>
                
                <div id="edit-location-preview" style="display: none; margin-bottom: 12px;">
                    <label class="form-label">Location Image</label>
                    <img id="edit-preview-image" src="" alt="Location" style="max-width: 100%; border-radius: 6px; margin-top: 5px;">
                    <button type="button" onclick="clearEditLocationImage()" style="margin-top: 5px; padding: 6px 12px; background: var(--error); color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        <i class="fas fa-times"></i> Remove Image
                    </button>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-location-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Location
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Skill Level Modal -->
<div id="add-skill-level-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-chart-line"></i> Add Skill Level</h3>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-skill-level-modal')">&times;</button>
        </div>
        <form action="process_admin_age_skill.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="create_skill_level">
            <div class="modal-body">
                <div class="form-group">
                    <label for="skill-level-name">Name *</label>
                    <input type="text" id="skill-level-name" name="name" class="form-input" required placeholder="e.g., Beginner, Intermediate, Advanced">
                </div>
                <div class="form-group">
                    <label for="skill-level-description">Description</label>
                    <textarea id="skill-level-description" name="description" class="form-input" rows="3" placeholder="Brief description of this skill level"></textarea>
                </div>
                <div class="form-group">
                    <label for="skill-level-order">Display Order</label>
                    <input type="number" id="skill-level-order" name="display_order" class="form-input" value="0" placeholder="Lower numbers appear first">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-skill-level-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Skill Level
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Skill Level Form (hidden) -->
<form id="delete-skill-level-form" action="process_admin_age_skill.php" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="action" value="delete_skill_level">
    <input type="hidden" id="delete-skill-level-id" name="id" value="">
</form>

<!-- Load Google Maps API with Places library -->
<?php if (!empty($google_maps_api_key)): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($google_maps_api_key) ?>&libraries=places" async defer></script>
<?php endif; ?>

<script>
// Initialize event handlers
(function() {
    // Handle edit buttons for all category types
    document.querySelectorAll('[data-action="edit"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name') || '';
            const description = this.getAttribute('data-description') || '';
            
            if (type === 'skill') {
                document.getElementById('edit-skill-id').value = id;
                document.getElementById('edit-skill-name').value = name;
                document.getElementById('edit-skill-description').value = description;
                document.getElementById('edit-skill-modal').classList.add('active');
            } else if (type === 'drill_type') {
                document.getElementById('edit-drill-type-id').value = id;
                document.getElementById('edit-drill-type-name').value = name;
                document.getElementById('edit-drill-type-description').value = description;
                // Set position type dropdown
                const positionType = this.getAttribute('data-position-type') || 'both';
                document.getElementById('edit-drill-type-position').value = positionType;
                document.getElementById('edit-drill-type-modal').classList.add('active');
            } else if (type === 'merchandise') {
                document.getElementById('edit-merchandise-id').value = id;
                document.getElementById('edit-merchandise-name').value = name;
                document.getElementById('edit-merchandise-description').value = description;
                document.getElementById('edit-merchandise-modal').classList.add('active');
            } else if (type === 'team') {
                document.getElementById('edit-team-id').value = id;
                document.getElementById('edit-team-name').value = name;
                document.getElementById('edit-team-age-group').value = this.getAttribute('data-age-group') || '';
                document.getElementById('edit-team-skill-level').value = this.getAttribute('data-skill-level') || '';
                document.getElementById('edit-team-division').value = this.getAttribute('data-division') || '';
                // Populate season checkboxes
                var seasonIds = (this.getAttribute('data-season-ids') || '').split(',').filter(Boolean);
                document.querySelectorAll('.edit-team-season-cb').forEach(function(cb) {
                    cb.checked = seasonIds.indexOf(cb.value) !== -1;
                });
                document.getElementById('edit-team-coach-id').value = this.getAttribute('data-coach-id') || '';
                document.getElementById('edit-team-assistant-coach-id').value = this.getAttribute('data-assistant-coach-id') || '';
                document.getElementById('edit-team-is-active').value = this.getAttribute('data-is-active') || '1';
                document.getElementById('edit-team-modal').classList.add('active');
            } else if (type === 'location') {
                document.getElementById('edit-location-id').value = id;
                document.getElementById('edit-location-name').value = name;
                document.getElementById('edit-location-city').value = this.getAttribute('data-city') || '';
                document.getElementById('edit-google-place-id').value = this.getAttribute('data-google-place-id') || '';
                const imageUrl = this.getAttribute('data-image-url') || '';
                document.getElementById('edit-location-image-url').value = imageUrl;
                if (imageUrl) {
                    document.getElementById('edit-preview-image').src = imageUrl;
                    document.getElementById('edit-location-preview').style.display = 'block';
                } else {
                    document.getElementById('edit-location-preview').style.display = 'none';
                }
                document.getElementById('edit-location-modal').classList.add('active');
            }
        });
    });
    
    // Handle delete buttons
    document.querySelectorAll('[data-action="delete"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name');
            
            if (confirm('Are you sure you want to delete "' + name + '"? This action cannot be undone.')) {
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                const csrfToken = csrfInput ? csrfInput.value : '';
                
                fetch('process_admin_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=delete&id=' + encodeURIComponent(id) + '&type=' + encodeURIComponent(type) + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Deleted successfully!', 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        showNotification('Error: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    showNotification('An error occurred', 'error');
                });
            }
        });
    });
    
    // Tab switching
    document.querySelectorAll('[data-action="switch-tab"]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // Prevent app.js handler from also running
            
            const tabName = this.getAttribute('data-tab');
            if (!tabName) return;
            
            // Remove active from all tab buttons and contents
            document.querySelectorAll('.page-tab').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });
            
            // Add active to clicked button and corresponding content
            this.classList.add('active');
            const targetContent = document.getElementById(tabName + '-tab');
            if (targetContent) {
                targetContent.classList.add('active');
                targetContent.style.display = 'block';
            }
            
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        });
    });
    
    // Handle add buttons to open modals
    document.querySelectorAll('[data-action="add"][data-modal]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // Prevent app.js handler from also running
            
            const modalId = this.getAttribute('data-modal');
            if (modalId) {
                openModal(modalId);
            }
        });
    });
    
    // Handle delete skill level buttons
    document.querySelectorAll('[data-action="delete-skill-level"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            if (confirm('Are you sure you want to delete skill level "' + name + '"? Sessions using it will have the field set to NULL.')) {
                document.getElementById('delete-skill-level-id').value = id;
                document.getElementById('delete-skill-level-form').submit();
            }
        });
    });
})();

// Show notification helper
function showNotification(message, type) {
    var existing = document.querySelector('.notification-widget');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notification-widget';
    div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px;';
    if (type === 'success') {
        div.style.background = 'rgba(16, 185, 129, 0.95)';
        div.style.color = '#fff';
    } else {
        div.style.background = 'rgba(239, 68, 68, 0.95)';
        div.style.color = '#fff';
    }
    // Escape message to prevent XSS (including single quotes)
    var escapedMessage = message.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + escapedMessage + '<button onclick="this.parentElement.remove()" style="margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>';
    document.body.appendChild(div);
    setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
}

// Convert modal forms to AJAX submissions
document.querySelectorAll('.modal form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(form);
        var modal = form.closest('.modal');
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        }
        
        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) {
            // Check if response is ok
            if (!response.ok) {
                throw new Error('Server responded with status: ' + response.status);
            }
            return response.text();
        })
        .then(function(text) {
            // Try to parse as JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                // Don't log full response for security - it may contain sensitive info
                console.error('Invalid JSON response received');
                throw new Error('Server returned invalid response');
            }
        })
        .then(function(data) {
            if (submitBtn) {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
            
            if (data.success) {
                showNotification(data.message || 'Operation completed successfully!', 'success');
                if (modal) closeModal(modal.id);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showNotification('Error: ' + (data.message || 'Operation failed'), 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            if (submitBtn) {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
            showNotification('Error: ' + error.message, 'error');
        });
    });
});

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = '';
        document.body.style.overflow = '';
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// Google Places API Configuration for Locations
const GOOGLE_API_KEY = '<?php echo htmlspecialchars($google_maps_api_key, ENT_QUOTES); ?>';

function initPlacesSearch() {
    // Initialize search for add location modal
    const addSearchInput = document.getElementById('add-place-search');
    if (addSearchInput) {
        let searchTimeout;
        addSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 3) {
                document.getElementById('add-place-results').style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => searchPlaces(query, 'add'), 500);
        });
    }
    
    // Initialize search for edit location modal
    const editSearchInput = document.getElementById('edit-place-search');
    if (editSearchInput) {
        let searchTimeout;
        editSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 3) {
                document.getElementById('edit-place-results').style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => searchPlaces(query, 'edit'), 500);
        });
    }
}

function searchPlaces(query, prefix) {
    // Check if Google Maps API is loaded
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
        console.error('Google Maps API not loaded');
        displayPlaceResults([], prefix);
        return;
    }
    
    // Initialize Places Service if not already done
    if (!window.placesService) {
        // Create a hidden div for PlacesService (it requires a map or div element)
        const div = document.createElement('div');
        div.style.display = 'none';
        document.body.appendChild(div);
        window.placesService = new google.maps.places.PlacesService(div);
    }
    
    // Use PlacesService textSearch
    const request = {
        query: query,
        fields: ['place_id', 'name', 'formatted_address', 'photos', 'geometry']
    };
    
    window.placesService.textSearch(request, function(results, status) {
        if (status === google.maps.places.PlacesServiceStatus.OK) {
            displayPlaceResults(results, prefix);
        } else {
            console.error('Places search failed:', status);
            displayPlaceResults([], prefix);
        }
    });
}

function displayPlaceResults(results, prefix) {
    const resultsDiv = document.getElementById(prefix + '-place-results');
    
    if (!results || results.length === 0) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    resultsDiv.innerHTML = '';
    results.forEach(place => {
        const div = document.createElement('div');
        div.style.padding = '10px';
        div.style.borderBottom = '1px solid var(--border)';
        div.style.cursor = 'pointer';
        div.style.transition = 'background 0.2s';
        
        div.onmouseover = () => div.style.background = 'rgba(112, 0, 164, 0.1)';
        div.onmouseout = () => div.style.background = 'transparent';
        
        div.innerHTML = `
            <div style="font-weight: 600; color: var(--text-white); margin-bottom: 4px;">${place.name}</div>
            <div style="font-size: 12px; color: var(--text-muted);">${place.formatted_address || ''}</div>
        `;
        
        div.onclick = () => selectPlace(place, prefix);
        resultsDiv.appendChild(div);
    });
    
    resultsDiv.style.display = 'block';
}

function selectPlace(place, prefix) {
    document.getElementById(prefix + '-location-name').value = place.name;
    
    // Extract city from address
    const addressParts = (place.formatted_address || '').split(',');
    if (addressParts.length > 1) {
        document.getElementById(prefix + '-location-city').value = addressParts[1].trim();
    }
    
    // Set Google Place ID
    document.getElementById(prefix + '-google-place-id').value = place.place_id;
    
    // Set image URL if available from Google Places photos
    if (place.photos && place.photos.length > 0 && place.photos[0].getUrl) {
        // Get photo URL with proper dimensions
        const photoUrl = place.photos[0].getUrl({maxWidth: 800, maxHeight: 600});
        document.getElementById(prefix + '-location-image-url').value = photoUrl;
        document.getElementById(prefix + '-preview-image').src = photoUrl;
        document.getElementById(prefix + '-location-preview').style.display = 'block';
    }
    
    document.getElementById(prefix + '-place-results').style.display = 'none';
    document.getElementById(prefix + '-place-search').value = '';
}

function clearAddLocationImage() {
    document.getElementById('add-location-image-url').value = '';
    document.getElementById('add-preview-image').src = '';
    document.getElementById('add-location-preview').style.display = 'none';
}

function clearEditLocationImage() {
    document.getElementById('edit-location-image-url').value = '';
    document.getElementById('edit-preview-image').src = '';
    document.getElementById('edit-location-preview').style.display = 'none';
}

function testGoogleAPI() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    
    fetch('process_test_google_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('Google API test successful!', 'success');
        } else {
            showNotification('Google API test failed: ' + data.message, 'error');
        }
    })
    .catch(err => {
        showNotification('Error testing API: ' + err.message, 'error');
    });
}

// Initialize places search on page load
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Google Maps API to load (handles async/defer loading)
    var initAttempts = 0;
    var MAX_INIT_ATTEMPTS = 20;
    var INIT_RETRY_DELAY_MS = 250;
    
    function tryInit() {
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            initPlacesSearch();
        } else if (initAttempts < MAX_INIT_ATTEMPTS) {
            initAttempts++;
            setTimeout(tryInit, INIT_RETRY_DELAY_MS);
        } else {
            console.warn('Google Maps API failed to load after', MAX_INIT_ATTEMPTS, 'attempts');
        }
    }
    
    tryInit();
});
</script>
