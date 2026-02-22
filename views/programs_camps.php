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
        <div class="view-toggle-bar">
            <button class="view-btn active" onclick="switchCampView('list')" id="camp-list-btn"><i class="fas fa-th-large"></i> Cards</button>
            <button class="view-btn" onclick="switchCampView('calendar')" id="camp-calendar-btn"><i class="fas fa-calendar"></i> Calendar</button>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="program-filter">
        <button class="filter-btn active" data-type="all">All Programs</button>
        <button class="filter-btn" data-type="camp"><i class="fas fa-campground"></i> Camps</button>
        <button class="filter-btn" data-type="multi_week"><i class="fas fa-calendar-alt"></i> Weekly Programs</button>
    </div>

    <!-- Calendar View -->
    <div id="camp-calendar-view" style="display: none;">
        <div class="camp-calendar-container">
            <div class="camp-calendar-header">
                <button class="btn-icon" onclick="changeCampMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                <h3 id="camp-current-month"></h3>
                <button class="btn-icon" onclick="changeCampMonth(1)"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="camp-calendar-grid" id="camp-calendar-grid"></div>
        </div>
    </div>

    <!-- List/Card View -->
    <div id="camp-list-view">
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
                        
                        // Group dates into consecutive ranges for smart display
                        $date_ranges = [];
                        if (!empty($program_dates)) {
                            $range_start = $program_dates[0]['session_date'];
                            $range_end = $range_start;
                            for ($di = 1; $di < count($program_dates); $di++) {
                                $prev = strtotime($range_end);
                                $curr = strtotime($program_dates[$di]['session_date']);
                                // Check if consecutive (within 1 day)
                                if (($curr - $prev) <= 86400) {
                                    $range_end = $program_dates[$di]['session_date'];
                                } else {
                                    $date_ranges[] = ['start' => $range_start, 'end' => $range_end];
                                    $range_start = $program_dates[$di]['session_date'];
                                    $range_end = $range_start;
                                }
                            }
                            $date_ranges[] = ['start' => $range_start, 'end' => $range_end];
                        }
                        ?>
                        <div class="detail-item">
                            <i class="fas fa-calendar-alt"></i>
                            <?php if (count($date_ranges) === 1): ?>
                                <?php if ($date_ranges[0]['start'] === $date_ranges[0]['end']): ?>
                                    <span><?php echo date('M j, Y', strtotime($date_ranges[0]['start'])); ?></span>
                                <?php else: ?>
                                    <span><?php echo date('M j', strtotime($date_ranges[0]['start'])); ?> - <?php echo date('M j, Y', strtotime($date_ranges[0]['end'])); ?></span>
                                <?php endif; ?>
                            <?php elseif (count($date_ranges) > 1): ?>
                                <span><?php echo count($program_dates); ?> sessions across <?php echo count($date_ranges); ?> weeks (<?php echo date('M j', strtotime($program_dates[0]['session_date'])); ?> - <?php echo date('M j, Y', strtotime(end($program_dates)['session_date'])); ?>)</span>
                            <?php else: ?>
                                <span><?php echo count($program_dates); ?> sessions over multiple weeks</span>
                            <?php endif; ?>
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
    </div><!-- end camp-list-view -->
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
    
    .camp-calendar-grid {
        font-size: 12px;
    }
    
    .camp-calendar-day {
        min-height: 80px;
        padding: 4px;
    }
}

.view-toggle-bar {
    display: flex;
    gap: 5px;
    background: #0d1117;
    border-radius: 8px;
    padding: 4px;
}

.view-toggle-bar .view-btn {
    padding: 8px 16px;
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.3s;
    font-size: 13px;
    font-weight: 600;
}

.view-toggle-bar .view-btn:hover {
    color: #fff;
    background: #1e293b;
}

.view-toggle-bar .view-btn.active {
    background: var(--primary, #7000a4);
    color: white;
}

.camp-calendar-container {
    background: #0d1117;
    border: 1px solid #1e293b;
    border-radius: 12px;
    padding: 20px;
}

.camp-calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.camp-calendar-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
}

.camp-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #1e293b;
    border: 1px solid #1e293b;
    border-radius: 8px;
    overflow: hidden;
    max-width: 100%;
    box-sizing: border-box;
}

.camp-calendar-day-header {
    background: #06080b;
    padding: 12px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
}

.camp-calendar-day {
    background: #0d1117;
    min-height: 100px;
    padding: 8px;
    position: relative;
    min-width: 0;
    overflow: hidden;
    box-sizing: border-box;
}

.camp-calendar-day.empty {
    background: #06080b;
}

.camp-calendar-day.today {
    background: rgba(112, 0, 164, 0.1);
    border: 2px solid var(--primary, #7000a4);
}

.camp-calendar-day .day-number {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
}

.camp-calendar-day.today .day-number {
    color: var(--primary, #7000a4);
}

.camp-event {
    padding: 4px 6px;
    margin-bottom: 4px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}

.camp-event.camp-type {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.camp-event.multi_week-type {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.camp-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
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

// Calendar functionality
var campCalendarDate = new Date();
var campCalendarEvents = <?php
    // Build calendar events from camp and program dates
    $calendarEvents = [];
    foreach ($programs as $pkg) {
        if ($pkg['package_type'] === 'camp') {
            // Get camp daily schedules
            $campSched = $pdo->prepare("SELECT schedule_date, title FROM camp_daily_schedules WHERE package_id = ? ORDER BY schedule_date");
            $campSched->execute([$pkg['id']]);
            $campDays = $campSched->fetchAll(PDO::FETCH_ASSOC);
            foreach ($campDays as $day) {
                $calendarEvents[] = [
                    'date' => $day['schedule_date'],
                    'title' => $pkg['name'] . ($day['title'] ? ' - ' . $day['title'] : ''),
                    'type' => 'camp',
                    'package_id' => $pkg['id']
                ];
            }
            // Also add start/end range if no daily schedules
            if (empty($campDays) && $pkg['camp_start_date'] && $pkg['camp_end_date']) {
                $start = new DateTime($pkg['camp_start_date']);
                $end = new DateTime($pkg['camp_end_date']);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                foreach ($period as $dt) {
                    $calendarEvents[] = [
                        'date' => $dt->format('Y-m-d'),
                        'title' => $pkg['name'],
                        'type' => 'camp',
                        'package_id' => $pkg['id']
                    ];
                }
            }
        } elseif ($pkg['package_type'] === 'multi_week') {
            $mwDates = $pdo->prepare("SELECT session_date, title FROM multiweek_program_dates WHERE package_id = ? ORDER BY session_date");
            $mwDates->execute([$pkg['id']]);
            $programDates = $mwDates->fetchAll(PDO::FETCH_ASSOC);
            foreach ($programDates as $pd) {
                $calendarEvents[] = [
                    'date' => $pd['session_date'],
                    'title' => $pkg['name'] . ($pd['title'] ? ' - ' . $pd['title'] : ''),
                    'type' => 'multi_week',
                    'package_id' => $pkg['id']
                ];
            }
        }
    }
    echo json_encode($calendarEvents);
?>;

function switchCampView(view) {
    document.getElementById('camp-list-view').style.display = view === 'list' ? 'block' : 'none';
    document.getElementById('camp-calendar-view').style.display = view === 'calendar' ? 'block' : 'none';
    document.getElementById('camp-list-btn').classList.toggle('active', view === 'list');
    document.getElementById('camp-calendar-btn').classList.toggle('active', view === 'calendar');
    if (view === 'calendar') {
        renderCampCalendar();
    }
}

function changeCampMonth(delta) {
    campCalendarDate.setMonth(campCalendarDate.getMonth() + delta);
    renderCampCalendar();
}

function renderCampCalendar() {
    var grid = document.getElementById('camp-calendar-grid');
    var monthLabel = document.getElementById('camp-current-month');
    if (!grid) return;
    
    var year = campCalendarDate.getFullYear();
    var month = campCalendarDate.getMonth();
    var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    monthLabel.textContent = monthNames[month] + ' ' + year;
    
    var firstDay = new Date(year, month, 1).getDay();
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var today = new Date();
    today.setHours(0,0,0,0);
    
    var html = '';
    // Day headers
    var dayHeaders = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    dayHeaders.forEach(function(d) {
        html += '<div class="camp-calendar-day-header">' + d + '</div>';
    });
    
    // Empty days before start
    for (var i = 0; i < firstDay; i++) {
        html += '<div class="camp-calendar-day empty"></div>';
    }
    
    // Days
    for (var day = 1; day <= daysInMonth; day++) {
        var dateObj = new Date(year, month, day);
        var isToday = dateObj.getTime() === today.getTime();
        var dateStr = year + '-' + String(month+1).padStart(2,'0') + '-' + String(day).padStart(2,'0');
        
        var dayEvents = campCalendarEvents.filter(function(e) {
            return e.date === dateStr;
        });
        
        html += '<div class="camp-calendar-day' + (isToday ? ' today' : '') + '">';
        html += '<div class="day-number">' + day + '</div>';
        
        dayEvents.forEach(function(evt, idx) {
            if (idx < 3) {
                var safeTitle = evt.title.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                html += '<div class="camp-event ' + (evt.type === 'camp' ? 'camp' : 'multi_week') + '-type" onclick="scrollToPackage(' + parseInt(evt.package_id) + ')" title="' + safeTitle + '">' + safeTitle + '</div>';
            }
        });
        if (dayEvents.length > 3) {
            html += '<div class="camp-event" style="background:#1e293b;color:#94a3b8;cursor:default;">+' + (dayEvents.length - 3) + ' more</div>';
        }
        
        html += '</div>';
    }
    
    grid.innerHTML = html;
}

function scrollToPackage(packageId) {
    switchCampView('list');
    var el = document.getElementById('package-' + packageId);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('highlighted');
        setTimeout(function() { el.classList.remove('highlighted'); }, 3000);
    }
}
</script>
