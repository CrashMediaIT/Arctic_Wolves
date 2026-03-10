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
$show_completed = isset($_GET['show_completed']) && $_GET['show_completed'] == '1';

// Get active camp and multi-week packages, hiding completed ones by default
// A program/camp is "completed" if its last session date has passed
$stmt = $pdo->prepare("
    SELECT p.*, 
           ag.name as age_group_name,
           sl.name as skill_level_name,
           GREATEST(
               COALESCE(p.camp_end_date, '1970-01-01'),
               COALESCE((SELECT MAX(schedule_date) FROM camp_daily_schedules WHERE package_id = p.id), '1970-01-01'),
               COALESCE((SELECT MAX(session_date) FROM multiweek_program_dates WHERE package_id = p.id), '1970-01-01')
           ) as last_session_date
    FROM packages p
    LEFT JOIN age_groups ag ON p.age_group_id = ag.id
    LEFT JOIN skill_levels sl ON p.skill_level_id = sl.id
    WHERE p.is_active = 1 AND p.package_type IN ('camp', 'multi_week')
    ORDER BY p.package_type, p.camp_start_date ASC, p.price
");
$stmt->execute();
$all_programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Split into active and completed
$programs = [];
$completed_programs = [];
$today = date('Y-m-d');
foreach ($all_programs as $pkg) {
    if ($pkg['last_session_date'] && $pkg['last_session_date'] > '1970-01-01' && $pkg['last_session_date'] < $today) {
        $completed_programs[] = $pkg;
    } else {
        $programs[] = $pkg;
    }
}

// If show_completed is toggled, include completed programs
if ($show_completed) {
    $programs = array_merge($programs, $completed_programs);
}

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

// Get already purchased package IDs for the current user (and their athletes)
$purchased_package_ids = [];
$check_user_ids = [$user_id];
if ($user_role === 'parent' && !empty($athletes)) {
    $check_user_ids = array_merge($check_user_ids, array_column($athletes, 'id'));
}
$check_placeholders = implode(',', array_fill(0, count($check_user_ids), '?'));
$purchased_stmt = $pdo->prepare("
    SELECT DISTINCT package_id FROM user_packages 
    WHERE user_id IN ($check_placeholders) AND payment_status IN ('pending', 'paid')
");
$purchased_stmt->execute($check_user_ids);
$purchased_package_ids = $purchased_stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if user is staff (admin, coach, front_desk_staff)
$is_staff = in_array($user_role, ['admin', 'coach', 'coach_plus', 'team_coach', 'front_desk_staff']);

// Load development program pricing and enrollment status
$default_goalie_dev_price = 0;
$default_player_dev_price = 0;
$goalie_dev_price = $default_goalie_dev_price;
$player_dev_price = $default_player_dev_price;
$goalie_dev_template_id = 0;
$player_dev_template_id = 0;
try {
    $goalie_dev_tpl = $pdo->query("SELECT id, price FROM training_session_templates WHERE name = 'Goalie Development Program' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $player_dev_tpl = $pdo->query("SELECT id, price FROM training_session_templates WHERE name = 'Player Development Program' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $goalie_dev_price = $goalie_dev_tpl['price'] ?? $default_goalie_dev_price;
    $player_dev_price = $player_dev_tpl['price'] ?? $default_player_dev_price;
    $goalie_dev_template_id = $goalie_dev_tpl['id'] ?? 0;
    $player_dev_template_id = $player_dev_tpl['id'] ?? 0;
} catch (PDOException $e) { /* templates may not exist */ }

$dev_enrolled_types = [];
try {
    $dev_enroll_stmt = $pdo->prepare("SELECT program_type FROM development_program_enrollments WHERE athlete_id = ?");
    $dev_enroll_stmt->execute([intval($_SESSION['user_id'])]);
    $dev_enrolled_types = $dev_enroll_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* table may not exist yet */ }

// Load program duration settings from notification templates
$goalie_dev_duration_weeks = null;
$player_dev_duration_weeks = null;
try {
    $dur_stmt = $pdo->query("SELECT program_type, program_duration_weeks FROM development_notification_templates");
    $dur_rows = $dur_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dur_rows as $dr) {
        if ($dr['program_type'] === 'goalie_dev') $goalie_dev_duration_weeks = $dr['program_duration_weeks'];
        if ($dr['program_type'] === 'player_dev') $player_dev_duration_weeks = $dr['program_duration_weeks'];
    }
} catch (PDOException $e) { /* table/column may not exist */ }
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
        <div style="margin-left: auto; display: flex; align-items: center; gap: 12px;">
            <input type="text" id="program-search-input" placeholder="Search programs..." oninput="filterProgramCards()" style="padding: 8px 14px; background: #0a0f16; border: 1px solid #334155; border-radius: 8px; color: #e2e8f0; font-size: 13px; min-width: 180px;">
            <a href="?page=programs_camps&show_completed=<?php echo $show_completed ? '0' : '1'; ?>" class="filter-btn <?php echo $show_completed ? 'active' : ''; ?>" style="white-space: nowrap; text-decoration: none;">
                <i class="fas fa-<?php echo $show_completed ? 'eye-slash' : 'eye'; ?>"></i> <?php echo $show_completed ? 'Hide Completed' : 'Show Completed'; ?>
            </a>
        </div>
    </div>
    <?php if ($show_completed && !empty($completed_programs)): ?>
    <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 10px 16px; margin-bottom: 16px; color: #f59e0b; font-size: 13px;">
        <i class="fas fa-info-circle"></i> Showing <?php echo count($completed_programs); ?> completed program<?php echo count($completed_programs) !== 1 ? 's' : ''; ?> alongside active ones.
    </div>
    <?php endif; ?>

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

    <!-- Development Programs Section -->
    <div class="dev-programs-section" style="margin-bottom: 32px;">
        <h3 style="font-size: 18px; font-weight: 700; color: #e2e8f0; margin-bottom: 16px;"><i class="fas fa-hockey-puck" style="margin-right: 8px;"></i> Development Programs</h3>
        <p style="color: #94a3b8; font-size: 14px; margin-bottom: 20px;">Long-term personalized development programs with dedicated coaching — specially tailored to each athlete</p>
        <div class="programs-grid">
            <div class="program-card" style="border-color: rgba(59,130,246,0.3);">
                <div class="program-header" style="background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(59,130,246,0.05)); padding: 24px;">
                    <span class="type-badge" style="background: rgba(59,130,246,0.15); color: #3b82f6; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;"><span class="icon-hockey-goalie"></span> Goalie Development</span>
                    <h3 style="margin-top: 12px;">Long Term Goalie Development</h3>
                    <?php if ($goalie_dev_duration_weeks): ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;margin-top:8px;font-size:13px;color:#3b82f6;"><i class="fas fa-clock"></i> <?= (int)$goalie_dev_duration_weeks ?> week program</span>
                    <?php endif; ?>
                </div>
                <div class="program-body" style="padding: 24px;">
                    <div class="program-description">Comprehensive goalie development program — technique, positioning, movement, and game sense. Work directly with our goalie development coaches through personalized drill programs and video feedback.</div>
                    <div class="program-details" style="margin-top: 16px;">
                        <div class="detail-item"><i class="fas fa-clipboard-list"></i> <span>Personalized drill programs</span></div>
                        <div class="detail-item"><i class="fas fa-video"></i> <span>Video analysis & feedback</span></div>
                        <div class="detail-item"><i class="fas fa-comments"></i> <span>Direct coach communication</span></div>
                        <div class="detail-item"><i class="fas fa-user-cog"></i> <span>Tailored to each athlete</span></div>
                    </div>
                </div>
                <div class="program-footer" style="padding: 16px 24px; border-top: 1px solid #1e293b; display: flex; justify-content: space-between; align-items: center;">
                    <div class="program-price" style="font-size: 20px; font-weight: 700; color: #e2e8f0;"><?= $goalie_dev_price > 0 ? '$' . number_format($goalie_dev_price, 2) : 'Free' ?></div>
                    <?php if (in_array('goalie_dev', $dev_enrolled_types)): ?>
                        <span style="padding:10px 20px;background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3);border-radius:8px;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:6px;cursor:default;">
                            <i class="fas fa-check"></i> Enrolled
                        </span>
                    <?php else: ?>
                        <button type="button" class="btn-register" data-action="register-dev-program" data-program-type="goalie_dev" data-template-id="<?= (int)$goalie_dev_template_id ?>" style="padding:10px 20px;background:var(--primary,#6B46C1);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                            <i class="fas fa-shopping-cart"></i> Enroll<?= $goalie_dev_price > 0 ? ' & Pay' : '' ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="program-card" style="border-color: rgba(16,185,129,0.3);">
                <div class="program-header" style="background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.05)); padding: 24px;">
                    <span class="type-badge" style="background: rgba(16,185,129,0.15); color: #10b981; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;"><span class="icon-hockey-player"></span> Player Development</span>
                    <h3 style="margin-top: 12px;">Long Term Player Development</h3>
                    <?php if ($player_dev_duration_weeks): ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;margin-top:8px;font-size:13px;color:#10b981;"><i class="fas fa-clock"></i> <?= (int)$player_dev_duration_weeks ?> week program</span>
                    <?php endif; ?>
                </div>
                <div class="program-body" style="padding: 24px;">
                    <div class="program-description">Structured long-term development for skaters — skating technique, shooting, puck handling, hockey IQ, and on-ice decision making. Receive personalized coaching through drill programs and video analysis.</div>
                    <div class="program-details" style="margin-top: 16px;">
                        <div class="detail-item"><i class="fas fa-clipboard-list"></i> <span>Personalized drill programs</span></div>
                        <div class="detail-item"><i class="fas fa-video"></i> <span>Video analysis & feedback</span></div>
                        <div class="detail-item"><i class="fas fa-comments"></i> <span>Direct coach communication</span></div>
                        <div class="detail-item"><i class="fas fa-user-cog"></i> <span>Tailored to each athlete</span></div>
                    </div>
                </div>
                <div class="program-footer" style="padding: 16px 24px; border-top: 1px solid #1e293b; display: flex; justify-content: space-between; align-items: center;">
                    <div class="program-price" style="font-size: 20px; font-weight: 700; color: #e2e8f0;"><?= $player_dev_price > 0 ? '$' . number_format($player_dev_price, 2) : 'Free' ?></div>
                    <?php if (in_array('player_dev', $dev_enrolled_types)): ?>
                        <span style="padding:10px 20px;background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3);border-radius:8px;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:6px;cursor:default;">
                            <i class="fas fa-check"></i> Enrolled
                        </span>
                    <?php else: ?>
                        <button type="button" class="btn-register" data-action="register-dev-program" data-program-type="player_dev" data-template-id="<?= (int)$player_dev_template_id ?>" style="padding:10px 20px;background:var(--primary,#6B46C1);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                            <i class="fas fa-shopping-cart"></i> Enroll<?= $player_dev_price > 0 ? ' & Pay' : '' ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
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
        <div class="program-card <?php echo $is_target ? 'highlighted' : ''; ?><?php echo (isset($pkg['last_session_date']) && $pkg['last_session_date'] > '1970-01-01' && $pkg['last_session_date'] < $today) ? ' completed-program' : ''; ?>" 
             data-type="<?php echo $pkg['package_type']; ?>"
             data-name="<?php echo htmlspecialchars(strtolower($pkg['name'])); ?>"
             id="package-<?php echo $pkg['id']; ?>">
            
            <div class="program-header <?php echo $pkg['package_type']; ?>">
                <span class="type-badge">
                    <?php if ($pkg['package_type'] === 'camp'): ?>
                        <i class="fas fa-campground"></i> Camp
                    <?php else: ?>
                        <i class="fas fa-calendar-alt"></i> Weekly Program
                    <?php endif; ?>
                </span>
                <?php if (isset($pkg['last_session_date']) && $pkg['last_session_date'] > '1970-01-01' && $pkg['last_session_date'] < $today): ?>
                    <span class="completed-badge"><i class="fas fa-check-circle"></i> Completed</span>
                <?php endif; ?>
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
                                // Check if consecutive (exactly 1 day apart)
                                if (($curr - $prev) === 86400) {
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
                                <span><?php echo count($program_dates); ?> sessions across <?php echo count($date_ranges); ?> date ranges (<?php echo date('M j', strtotime($program_dates[0]['session_date'])); ?> - <?php echo date('M j, Y', strtotime(end($program_dates)['session_date'])); ?>)</span>
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
                <?php
                $is_already_purchased = in_array($pkg['id'], $purchased_package_ids);
                
                // Get registered users count for staff view
                $reg_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_packages WHERE package_id = ? AND payment_status = 'paid'");
                $reg_count_stmt->execute([$pkg['id']]);
                $registered_count = (int)$reg_count_stmt->fetchColumn();
                ?>

                <?php if ($is_already_purchased): ?>
                    <?php
                    // Get user_package_id for cancellation
                    $up_id_stmt = $pdo->prepare("SELECT up.id FROM user_packages up WHERE up.package_id = ? AND up.user_id IN ($check_placeholders) AND up.payment_status = 'paid' LIMIT 1");
                    $up_id_stmt->execute(array_merge([$pkg['id']], $check_user_ids));
                    $user_pkg_id = $up_id_stmt->fetchColumn();
                    
                    // Determine cancellation eligibility
                    $can_cancel = false;
                    $cancel_note = '';
                    if ($pkg['package_type'] === 'camp' && !empty($pkg['camp_start_date'])) {
                        $camp_diff = (new DateTime())->diff(new DateTime($pkg['camp_start_date']));
                        $days_until = $camp_diff->days * ($camp_diff->invert ? -1 : 1);
                        $can_cancel = ($days_until >= 14);
                        $cancel_note = $can_cancel 
                            ? 'Cancellation available until ' . date('M j, Y', strtotime($pkg['camp_start_date'] . ' -14 days'))
                            : 'Camp cancellation deadline has passed (14 days before start)';
                    } elseif ($pkg['package_type'] === 'multi_week') {
                        $can_cancel = true;
                        $cancel_note = 'Remaining sessions beyond 48 hours will be refunded';
                    }
                    ?>
                    <button type="button" class="btn-register" disabled style="background:rgba(0,255,136,0.1);color:#00ff88;cursor:default;opacity:0.8;">
                        <i class="fas fa-check-circle"></i> Already Registered
                    </button>
                    <?php if ($user_pkg_id): ?>
                    <div style="margin-top:8px;font-size:12px;color:#94a3b8;">
                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($cancel_note); ?>
                    </div>
                    <?php if ($can_cancel): ?>
                    <button type="button" onclick="cancelPackageRegistration(<?php echo (int)$user_pkg_id; ?>, '<?php echo $pkg['package_type']; ?>')" 
                            style="margin-top:8px;width:100%;background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.3);padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;">
                        <i class="fas fa-times-circle"></i> Cancel Registration
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
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
                <?php endif; ?>

                <?php if ($is_staff): ?>
                <div class="staff-registration-info" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border, #1e293b);">
                    <button type="button" class="btn-view-registrations" onclick="viewRegistrations(<?php echo $pkg['id']; ?>, <?php echo json_encode($pkg['name']); ?>, <?php echo json_encode($pkg['package_type']); ?>)" style="width:100%;background:rgba(99,102,241,0.15);color:#818cf8;border:1px solid rgba(99,102,241,0.3);padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;">
                        <i class="fas fa-users"></i> View Registrations (<?php echo $registered_count; ?>)
                    </button>
                </div>
                <?php endif; ?>
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
    background: var(--primary, #6B46C1);
    color: white;
    border-color: var(--primary, #6B46C1);
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
    border-color: var(--primary, #6B46C1);
}

.program-card.highlighted {
    border-color: var(--primary, #6B46C1);
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
    color: var(--primary, #6B46C1);
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
    color: var(--primary, #6B46C1);
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
    color: var(--primary, #6B46C1);
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
    border-color: var(--primary, #6B46C1);
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
    color: var(--primary, #6B46C1);
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
    background: var(--primary, #6B46C1);
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
    background: var(--primary, #6B46C1);
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
    border: 2px solid var(--primary, #6B46C1);
}

.camp-calendar-day .day-number {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
}

.camp-calendar-day.today .day-number {
    color: var(--primary, #6B46C1);
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

.completed-badge {
    display: inline-block;
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}

.completed-program {
    opacity: 0.7;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Program filtering
    var filterButtons = document.querySelectorAll('.program-filter .filter-btn[data-type]');
    var programCards = document.querySelectorAll('.program-card');
    
    filterButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterButtons.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            applyProgramFilters();
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

// Shared filter logic for type + search
function applyProgramFilters() {
    var activeBtn = document.querySelector('.program-filter .filter-btn[data-type].active');
    var filterType = activeBtn ? activeBtn.dataset.type : 'all';
    var searchQuery = (document.getElementById('program-search-input').value || '').toLowerCase();
    var cards = document.querySelectorAll('.program-card');
    cards.forEach(function(card) {
        var matchesType = (filterType === 'all' || card.dataset.type === filterType);
        var matchesSearch = (searchQuery === '' || (card.getAttribute('data-name') || '').indexOf(searchQuery) !== -1);
        card.style.display = (matchesType && matchesSearch) ? 'block' : 'none';
    });
}

// Search/filter program cards by name (called from search input)
function filterProgramCards() {
    applyProgramFilters();
}

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

async function cancelPackageRegistration(userPackageId, packageType) {
    var policyMsg = '';
    if (packageType === 'camp') {
        policyMsg = 'Camp cancellation policy: Full refund for cancellations made 14 days or more before camp start.\n\n';
    } else if (packageType === 'multi_week') {
        policyMsg = 'Program cancellation policy: Sessions within 48 hours are not refundable. Remaining sessions will be refunded.\n\n';
    }
    if (!await showConfirmModal(policyMsg + 'Are you sure you want to cancel this registration?')) return;
    
    var csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    fetch('process_packages.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=user_cancel_package&user_package_id=' + userPackageId + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message || 'Registration cancelled successfully.', 'success');
            location.reload();
        } else {
            showToast('Error: ' + (data.message || 'Failed to cancel registration'), 'error');
        }
    })
    .catch(function() { showToast('Failed to process cancellation', 'error'); });
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

// Staff Registration Management
function viewRegistrations(packageId, packageName, packageType) {
    var modal = document.getElementById('registrations-modal');
    if (!modal) return;
    document.getElementById('reg-modal-title').textContent = packageName + ' - Registrations';
    document.getElementById('reg-modal-package-id').value = packageId;
    document.getElementById('reg-modal-package-type').value = packageType;
    modal.style.display = 'flex';
    loadRegistrations(packageId);
}

function closeRegistrationsModal() {
    var modal = document.getElementById('registrations-modal');
    if (modal) modal.style.display = 'none';
}

function loadRegistrations(packageId) {
    var container = document.getElementById('reg-list-container');
    container.innerHTML = '<div style="text-align:center;padding:20px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    fetch('process_packages.php?action=get_registrations&package_id=' + packageId, {
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { container.innerHTML = '<p style="color:#ef4444;">Error loading registrations</p>'; return; }
        var html = '';
        
        // Registered Users
        html += '<h4 style="color:#e2e8f0;margin-bottom:8px;"><i class="fas fa-check-circle" style="color:#00ff88;"></i> Registered (' + data.registered.length + ')</h4>';
        if (data.registered.length === 0) {
            html += '<p style="color:#64748b;font-size:13px;margin-bottom:16px;">No registered users yet.</p>';
        } else {
            html += '<div class="reg-user-list">';
            data.registered.forEach(function(u) {
                html += '<div class="reg-user-item">';
                html += '<div class="reg-user-info"><strong>' + escHtml(u.name) + '</strong><span style="color:#64748b;font-size:12px;"> ' + escHtml(u.email) + '</span></div>';
                html += '<div class="reg-user-actions">';
                html += '<button onclick="cancelRegistration(' + u.user_package_id + ', ' + packageId + ')" class="reg-btn-cancel" title="Cancel & Refund"><i class="fas fa-times-circle"></i> Cancel & Refund</button>';
                html += '<a href="mailto:' + escHtml(u.email) + '" class="reg-btn-email" title="Email"><i class="fas fa-envelope"></i></a>';
                html += '</div></div>';
            });
            html += '</div>';
        }

        // Waitlisted Users
        if (data.waitlisted && data.waitlisted.length > 0) {
            html += '<h4 style="color:#e2e8f0;margin:16px 0 8px;"><i class="fas fa-clock" style="color:#f59e0b;"></i> Waitlisted (' + data.waitlisted.length + ')</h4>';
            html += '<div class="reg-user-list">';
            data.waitlisted.forEach(function(u) {
                html += '<div class="reg-user-item">';
                html += '<div class="reg-user-info"><strong>' + escHtml(u.name) + '</strong><span style="color:#64748b;font-size:12px;"> ' + escHtml(u.email) + '</span></div>';
                html += '<div class="reg-user-actions">';
                html += '<a href="mailto:' + escHtml(u.email) + '" class="reg-btn-email" title="Email"><i class="fas fa-envelope"></i></a>';
                html += '</div></div>';
            });
            html += '</div>';
        }

        // Email all button
        if (data.registered.length > 0) {
            var allEmails = data.registered.map(function(u) { return u.email; }).join(',');
            html += '<div style="margin-top:16px;text-align:center;">';
            html += '<a href="mailto:' + allEmails + '" class="btn-register" style="display:inline-block;text-decoration:none;padding:10px 24px;font-size:13px;"><i class="fas fa-envelope"></i> Email All Registered Users</a>';
            html += '</div>';
        }

        container.innerHTML = html;
    })
    .catch(function() {
        container.innerHTML = '<p style="color:#ef4444;">Failed to load registrations</p>';
    });
}

async function cancelRegistration(userPackageId, packageId) {
    if (!await showConfirmModal('Cancel this registration and automatically refund the user?')) return;
    
    var csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    fetch('process_packages.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=cancel_registration&user_package_id=' + userPackageId + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Registration cancelled and refund initiated.', 'success');
            loadRegistrations(packageId);
        } else {
            showToast('Error: ' + (data.message || 'Failed to cancel registration'), 'error');
        }
    })
    .catch(function() { showToast('Failed to process cancellation', 'error'); });
}

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
}

// Development program enrollment handler
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action="register-dev-program"]');
    if (!btn) return;
    var programType = btn.getAttribute('data-program-type');
    var templateId = btn.getAttribute('data-template-id');
    if (!programType || !templateId || !/^\d+$/.test(templateId)) {
        alert('Invalid program. Please refresh and try again.');
        return;
    }
    var csrfInput = document.querySelector('input[name="csrf_token"]');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfInput ? csrfInput.value : (csrfMeta ? csrfMeta.content : '');
    if (!csrfToken) {
        alert('Security token missing. Please refresh the page.');
        return;
    }
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'process_booking.php';
    var fields = {action: 'register_dev_program', program_type: programType, template_id: templateId, csrf_token: csrfToken};
    for (var key in fields) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
});
</script>

<?php if ($is_staff): ?>
<!-- Staff Registration Management Modal -->
<div id="registrations-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;justify-content:center;align-items:center;">
    <div style="background:#0d1116;border:1px solid #1e293b;border-radius:12px;width:90%;max-width:600px;max-height:80vh;overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:16px 20px;border-bottom:1px solid #1e293b;display:flex;justify-content:space-between;align-items:center;">
            <h3 id="reg-modal-title" style="margin:0;color:#e2e8f0;font-size:16px;"></h3>
            <button onclick="closeRegistrationsModal()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:20px;">&times;</button>
        </div>
        <input type="hidden" id="reg-modal-package-id">
        <input type="hidden" id="reg-modal-package-type">
        <div id="reg-list-container" style="padding:20px;overflow-y:auto;flex:1;"></div>
    </div>
</div>
<style>
.reg-user-list { display:flex; flex-direction:column; gap:8px; margin-bottom:8px; }
.reg-user-item { display:flex; justify-content:space-between; align-items:center; background:#0a0f16; padding:10px 14px; border-radius:8px; border:1px solid #1e293b; }
.reg-user-info { display:flex; flex-direction:column; gap:2px; }
.reg-user-actions { display:flex; gap:8px; align-items:center; }
.reg-btn-cancel { background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid rgba(239,68,68,0.3); padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; }
.reg-btn-cancel:hover { background:rgba(239,68,68,0.25); }
.reg-btn-email { background:rgba(99,102,241,0.15); color:#818cf8; border:1px solid rgba(99,102,241,0.3); padding:6px 10px; border-radius:6px; text-decoration:none; font-size:12px; }
.reg-btn-email:hover { background:rgba(99,102,241,0.25); }
</style>
<?php endif; ?>
