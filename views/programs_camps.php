<?php
// views/programs_camps.php - Browse and register for Camps & Multi-Week Programs
// Displays camps and multi-week programs as a dedicated product category
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'security.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Handle direct package registration intent (from landing page)
$target_package_id = isset($_GET['package_id']) ? intval($_GET['package_id']) : 0;

// Get active camp and multi-week packages
$stmt = $pdo->prepare("
    SELECT p.*, 
           ag.name as age_group_name,
           sl.name as skill_level_name
    FROM packages p
    LEFT JOIN age_groups ag ON p.age_group_id = ag.id
    LEFT JOIN skill_levels sl ON p.skill_level_id = sl.id
    WHERE p.is_active = 1 AND p.package_type IN ('camp', 'multi_week')
    ORDER BY p.package_type, p.camp_start_date ASC, p.price
");
$stmt->execute();
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tax settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$tax_rate = floatval($settings['tax_rate'] ?? 13.00);
$tax_name = $settings['tax_name'] ?? 'HST';

// Get user's athletes (for parent role)
$athletes = [];
if ($user_role === 'parent') {
    $athletes_stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name 
        FROM users u
        JOIN managed_athletes ma ON u.id = ma.athlete_id
        WHERE ma.parent_id = ? AND ma.can_book = 1
    ");
    $athletes_stmt->execute([$user_id]);
    $athletes = $athletes_stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
}
?>

<div class="programs-camps-container">
    <div class="page-header-bar">
        <div>
            <h2><i class="fas fa-campground"></i> Programs & Camps</h2>
            <p class="page-subtitle">Browse and register for our camps and multi-week training programs</p>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="program-filter">
        <button class="filter-btn active" data-type="all">All Programs</button>
        <button class="filter-btn" data-type="camp"><i class="fas fa-campground"></i> Camps</button>
        <button class="filter-btn" data-type="multi_week"><i class="fas fa-calendar-alt"></i> Weekly Programs</button>
    </div>

    <?php if (empty($programs)): ?>
    <div class="empty-state">
        <i class="fas fa-campground"></i>
        <h3>No Programs Available</h3>
        <p>Check back soon for upcoming camps and programs!</p>
    </div>
    <?php else: ?>
    <div class="programs-grid">
        <?php foreach ($programs as $pkg):
            $price_with_tax = $pkg['price'] * (1 + $tax_rate / 100);
            $is_target = ($target_package_id === intval($pkg['id']));
        ?>
        <div class="program-card <?php echo $is_target ? 'highlighted' : ''; ?>" 
             data-type="<?php echo $pkg['package_type']; ?>"
             id="package-<?php echo $pkg['id']; ?>">
            
            <div class="program-header <?php echo $pkg['package_type']; ?>">
                <span class="type-badge">
                    <?php if ($pkg['package_type'] === 'camp'): ?>
                        <i class="fas fa-campground"></i> Camp
                    <?php else: ?>
                        <i class="fas fa-calendar-alt"></i> Weekly Program
                    <?php endif; ?>
                </span>
                <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                <?php if ($pkg['enable_child_checkin']): ?>
                    <span class="child-pickup-badge"><i class="fas fa-child"></i> Child Pickup Enabled</span>
                <?php endif; ?>
            </div>

            <div class="program-body">
                <?php if ($pkg['description']): ?>
                <div class="program-description">
                    <?php echo nl2br(htmlspecialchars($pkg['description'])); ?>
                </div>
                <?php endif; ?>

                <div class="program-details">
                    <?php if ($pkg['package_type'] === 'camp'): ?>
                        <?php if ($pkg['camp_start_date'] && $pkg['camp_end_date']): ?>
                        <div class="detail-item">
                            <i class="fas fa-calendar-day"></i>
                            <span><?php echo date('M j', strtotime($pkg['camp_start_date'])); ?> - <?php echo date('M j, Y', strtotime($pkg['camp_end_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($pkg['daily_start_time'] && $pkg['daily_end_time']): ?>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('g:i A', strtotime($pkg['daily_start_time'])); ?> - <?php echo date('g:i A', strtotime($pkg['daily_end_time'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php
                        $camp_sched = $pdo->prepare("SELECT * FROM camp_daily_schedules WHERE package_id = ? ORDER BY schedule_date");
                        $camp_sched->execute([$pkg['id']]);
                        $camp_days = $camp_sched->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($camp_days)):
                        ?>
                        <div class="detail-item">
                            <i class="fas fa-list-ol"></i>
                            <span><?php echo count($camp_days); ?> day program</span>
                        </div>
                        
                        <!-- Daily Schedule Expandable -->
                        <div class="schedule-toggle">
                            <a href="#" onclick="toggleSchedule(<?php echo $pkg['id']; ?>); return false;">
                                <i class="fas fa-calendar-day"></i> View Daily Schedule
                            </a>
                        </div>
                        <div id="schedule-<?php echo $pkg['id']; ?>" class="schedule-detail" style="display: none;">
                            <?php foreach ($camp_days as $day): ?>
                            <div class="schedule-day-item">
                                <div class="day-info">
                                    <strong><?php echo date('l, M j', strtotime($day['schedule_date'])); ?></strong>
                                    <span class="time"><?php echo date('g:i A', strtotime($day['start_time'])); ?> - <?php echo date('g:i A', strtotime($day['end_time'])); ?></span>
                                </div>
                                <?php if (!empty($day['title'])): ?>
                                <em class="day-title"><?php echo htmlspecialchars($day['title']); ?></em>
                                <?php endif; ?>
                                <?php if (!empty($day['location'])): ?>
                                <span class="day-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($day['location']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                    <?php elseif ($pkg['package_type'] === 'multi_week'): ?>
                        <?php
                        $mw_dates = $pdo->prepare("SELECT * FROM multiweek_program_dates WHERE package_id = ? ORDER BY session_date");
                        $mw_dates->execute([$pkg['id']]);
                        $program_dates = $mw_dates->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <div class="detail-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo count($program_dates); ?> sessions over multiple weeks</span>
                        </div>
                        <?php if (!empty($program_dates)): ?>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('g:i A', strtotime($program_dates[0]['start_time'])); ?> - <?php echo date('g:i A', strtotime($program_dates[0]['end_time'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($pkg['allow_individual_sessions']): ?>
                        <div class="detail-item highlight">
                            <i class="fas fa-check-circle"></i>
                            <span>Individual sessions available (fill the spot!)</span>
                        </div>
                        <?php endif; ?>

                        <!-- Program Dates Expandable -->
                        <?php if (!empty($program_dates)): ?>
                        <div class="schedule-toggle">
                            <a href="#" onclick="toggleSchedule(<?php echo $pkg['id']; ?>); return false;">
                                <i class="fas fa-calendar-alt"></i> View All Dates
                            </a>
                        </div>
                        <div id="schedule-<?php echo $pkg['id']; ?>" class="schedule-detail" style="display: none;">
                            <?php foreach ($program_dates as $pd): ?>
                            <div class="schedule-day-item">
                                <div class="day-info">
                                    <strong><?php echo date('l, M j', strtotime($pd['session_date'])); ?></strong>
                                    <span class="time"><?php echo date('g:i A', strtotime($pd['start_time'])); ?> - <?php echo date('g:i A', strtotime($pd['end_time'])); ?></span>
                                </div>
                                <?php if (!empty($pd['title'])): ?>
                                <em class="day-title"><?php echo htmlspecialchars($pd['title']); ?></em>
                                <?php endif; ?>
                                <?php if (!empty($pd['location'])): ?>
                                <span class="day-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pd['location']); ?></span>
                                <?php endif; ?>
                                <?php if ($pd['individual_price'] && $pkg['allow_individual_sessions']): ?>
                                <span class="individual-price-badge">Individual: $<?php echo number_format($pd['individual_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($pkg['age_group_name']): ?>
                    <div class="detail-item">
                        <i class="fas fa-users"></i>
                        <span><?php echo htmlspecialchars($pkg['age_group_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($pkg['skill_level_name']): ?>
                    <div class="detail-item">
                        <i class="fas fa-star"></i>
                        <span><?php echo htmlspecialchars($pkg['skill_level_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="program-pricing">
                    <div class="price-main">$<?php echo number_format($pkg['price'], 2); ?></div>
                    <div class="price-tax">+ $<?php echo number_format($pkg['price'] * $tax_rate / 100, 2); ?> <?php echo $tax_name; ?></div>
                    <div class="price-total">Total: $<?php echo number_format($price_with_tax, 2); ?></div>
                </div>
            </div>

            <div class="program-footer">
                <form action="process_purchase_package.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="package_id" value="<?php echo $pkg['id']; ?>">

                    <?php
                    // Show add-on options
                    $addons_stmt = $pdo->prepare("SELECT * FROM camp_add_ons WHERE package_id = ? ORDER BY display_order");
                    $addons_stmt->execute([$pkg['id']]);
                    $addons = $addons_stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($addons)):
                    ?>
                    <div class="addon-options">
                        <h4><i class="fas fa-puzzle-piece"></i> Add-On Options</h4>
                        <?php foreach ($addons as $addon): ?>
                        <label class="addon-option">
                            <input type="checkbox" name="selected_addons[]" value="<?php echo $addon['id']; ?>"
                                   <?php echo $addon['is_default'] ? 'checked' : ''; ?>>
                            <div class="addon-info">
                                <span class="addon-name"><?php echo htmlspecialchars($addon['name']); ?></span>
                                <?php if ($addon['description']): ?>
                                <span class="addon-desc"><?php echo htmlspecialchars($addon['description']); ?></span>
                                <?php endif; ?>
                                <?php if ($addon['price'] > 0): ?>
                                <span class="addon-price">+ $<?php echo number_format($addon['price'], 2); ?></span>
                                <?php else: ?>
                                <span class="addon-price included">Included</span>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($user_role === 'parent' && !empty($athletes)): ?>
                    <div class="athlete-selector">
                        <label>Register for:</label>
                        <?php foreach ($athletes as $athlete): ?>
                        <label class="athlete-option">
                            <input type="checkbox" name="athlete_ids[]" value="<?php echo $athlete['id']; ?>">
                            <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus"></i>
                        <?php echo $pkg['package_type'] === 'camp' ? 'Register for Camp' : 'Enroll in Program'; ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.programs-camps-container {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.page-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-header-bar h2 {
    color: #fff;
    margin: 0;
}

.page-subtitle {
    color: #94a3b8;
    margin-top: 4px;
    font-size: 14px;
}

.program-filter {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.program-filter .filter-btn {
    padding: 10px 20px;
    border: 2px solid #334155;
    background: #0d1117;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s;
    font-weight: 600;
    font-size: 14px;
}

.program-filter .filter-btn.active,
.program-filter .filter-btn:hover {
    background: var(--primary, #7000a4);
    color: white;
    border-color: var(--primary, #7000a4);
}

.programs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 24px;
}

.program-card {
    background: #0d1117;
    border: 1px solid #1e293b;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
}

.program-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    border-color: var(--primary, #7000a4);
}

.program-card.highlighted {
    border-color: var(--primary, #7000a4);
    box-shadow: 0 0 20px rgba(112, 0, 164, 0.3);
}

.program-header {
    padding: 24px;
    color: white;
    position: relative;
}

.program-header.camp {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.program-header.multi_week {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.program-header h3 {
    margin: 8px 0 0;
    font-size: 1.3rem;
}

.type-badge {
    display: inline-block;
    background: rgba(255,255,255,0.25);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.child-pickup-badge {
    display: inline-block;
    margin-top: 8px;
    background: rgba(255,255,255,0.2);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.program-body {
    padding: 20px 24px;
}

.program-description {
    color: #94a3b8;
    font-size: 14px;
    margin-bottom: 16px;
    line-height: 1.5;
}

.program-details {
    margin-bottom: 16px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
    color: #cbd5e1;
    font-size: 14px;
}

.detail-item i {
    color: var(--primary, #7000a4);
    width: 20px;
    text-align: center;
}

.detail-item.highlight {
    color: #10b981;
}

.detail-item.highlight i {
    color: #10b981;
}

.schedule-toggle {
    margin-top: 8px;
}

.schedule-toggle a {
    color: var(--primary, #7000a4);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.schedule-toggle a:hover {
    text-decoration: underline;
}

.schedule-detail {
    margin-top: 8px;
    background: #06080b;
    border: 1px solid #1e293b;
    border-radius: 8px;
    padding: 12px;
    max-height: 300px;
    overflow-y: auto;
}

.schedule-day-item {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid #1e293b;
    font-size: 13px;
    color: #94a3b8;
    align-items: center;
}

.schedule-day-item:last-child {
    border-bottom: none;
}

.day-info strong {
    color: #e2e8f0;
    min-width: 130px;
    display: inline-block;
}

.day-info .time {
    color: #94a3b8;
}

.day-title {
    color: #cbd5e1;
}

.day-location {
    color: #8B5CF6;
    font-size: 12px;
}

.day-location i {
    margin-right: 4px;
}

.individual-price-badge {
    background: #10b981;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

.program-pricing {
    text-align: center;
    padding: 16px;
    background: #06080b;
    border-radius: 8px;
    margin-bottom: 16px;
}

.price-main {
    font-size: 2.2rem;
    font-weight: 900;
    color: #fff;
}

.price-tax {
    color: #64748b;
    font-size: 13px;
    margin-top: 4px;
}

.price-total {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary, #7000a4);
    margin-top: 4px;
}

.program-footer {
    padding: 16px 24px 24px;
    border-top: 1px solid #1e293b;
}

.addon-options {
    background: #06080b;
    border: 1px solid #1e293b;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
}

.addon-options h4 {
    color: #e2e8f0;
    font-size: 14px;
    margin: 0 0 12px;
}

.addon-option {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    background: #0d1117;
    border: 1px solid #1e293b;
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.addon-option:hover {
    border-color: var(--primary, #7000a4);
}

.addon-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.addon-name {
    font-weight: 600;
    color: #e2e8f0;
    font-size: 14px;
}

.addon-desc {
    font-size: 12px;
    color: #64748b;
}

.addon-price {
    font-size: 13px;
    font-weight: 600;
    color: var(--primary, #7000a4);
}

.addon-price.included {
    color: #10b981;
}

.athlete-selector {
    margin-bottom: 16px;
}

.athlete-selector > label {
    color: #94a3b8;
    font-weight: 600;
    font-size: 14px;
    display: block;
    margin-bottom: 8px;
}

.athlete-option {
    display: block;
    padding: 8px;
    color: #e2e8f0;
    cursor: pointer;
}

.btn-register {
    width: 100%;
    padding: 14px;
    background: var(--primary, #7000a4);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-register:hover {
    background: #e64400;
    transform: translateY(-1px);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #64748b;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.empty-state h3 {
    color: #94a3b8;
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .programs-grid {
        grid-template-columns: 1fr;
    }
    
    .program-filter {
        flex-direction: column;
    }
    
    .program-filter .filter-btn {
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Program filtering
    var filterButtons = document.querySelectorAll('.program-filter .filter-btn');
    var programCards = document.querySelectorAll('.program-card');
    
    filterButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterButtons.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            
            var filterType = this.dataset.type;
            programCards.forEach(function(card) {
                if (filterType === 'all' || card.dataset.type === filterType) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    // Scroll to target package if specified
    <?php if ($target_package_id > 0): ?>
    var targetCard = document.getElementById('package-<?php echo $target_package_id; ?>');
    if (targetCard) {
        targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    <?php endif; ?>
});

function toggleSchedule(packageId) {
    var el = document.getElementById('schedule-' + packageId);
    if (el) {
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
}
</script>
