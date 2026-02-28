<?php
/**
 * PWA Reports User - Mobile-native user reports
 * Purpose-built for mobile phones.
 * Mirrors views/reports_user.php with mobile-first card-based layout.
 */

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

require_once 'security.php';

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$report_tab = isset($_GET['tab']) ? $_GET['tab'] : 'activity';

// Get all users for the selector
$users_stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.created_at,
           (SELECT COUNT(*) FROM bookings b
            INNER JOIN sessions s ON b.session_id = s.id
            WHERE b.user_id = u.id
            AND s.session_date BETWEEN ? AND ?) as sessions_count
    FROM users u
    ORDER BY u.last_name, u.first_name
");
$users_stmt->execute([$date_from, $date_to]);
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_users = decryptUserRows($all_users);

// Get selected user details
$selected_user = null;
$user_sessions = [];
$user_stats = [];
$user_evaluations = [];
$user_packages = [];
$user_goals = [];

if ($selected_user_id) {
    // User info
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$selected_user_id]);
    $selected_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($selected_user) {
        $selected_user = decryptUserRow($selected_user);
    }

    // Sessions registered for in the date range
    $sess_stmt = $pdo->prepare("
        SELECT s.id, s.title as session_title, s.session_date, s.session_time,
               s.duration_minutes, s.price,
               st.name as session_type, l.name as location_name,
               b.amount as amount_paid, b.booking_date, b.payment_status, b.status as booking_status
        FROM bookings b
        INNER JOIN sessions s ON b.session_id = s.id
        LEFT JOIN session_types st ON s.session_type_id = st.id
        LEFT JOIN locations l ON s.location_id = l.id
        WHERE b.user_id = ?
        AND s.session_date BETWEEN ? AND ?
        ORDER BY s.session_date DESC
    ");
    $sess_stmt->execute([$selected_user_id, $date_from, $date_to]);
    $user_sessions = $sess_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Athlete stats
    $stats_stmt = $pdo->prepare("
        SELECT * FROM athlete_stats WHERE user_id = ? ORDER BY season DESC
    ");
    $stats_stmt->execute([$selected_user_id]);
    $user_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Evaluations
    $eval_stmt = $pdo->prepare("
        SELECT ae.*, es.name as skill_name, ec.name as skill_category,
               eu.first_name as evaluator_first, eu.last_name as evaluator_last
        FROM athlete_evaluations ae
        LEFT JOIN eval_skills es ON ae.skill_id = es.id
        LEFT JOIN eval_categories ec ON es.category_id = ec.id
        LEFT JOIN users eu ON ae.evaluator_id = eu.id
        WHERE ae.athlete_id = ?
        AND ae.evaluation_date BETWEEN ? AND ?
        ORDER BY ae.evaluation_date DESC
    ");
    $eval_stmt->execute([$selected_user_id, $date_from, $date_to]);
    $user_evaluations = $eval_stmt->fetchAll(PDO::FETCH_ASSOC);
    $user_evaluations = decryptUserRows($user_evaluations);

    // Packages purchased
    $pkg_stmt = $pdo->prepare("
        SELECT up.*, p.name as package_name, p.price as package_price, p.credits as package_credits
        FROM user_packages up
        INNER JOIN packages p ON up.package_id = p.id
        WHERE up.user_id = ?
        ORDER BY up.purchase_date DESC
    ");
    $pkg_stmt->execute([$selected_user_id]);
    $user_packages = $pkg_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Goals
    $goals_stmt = $pdo->prepare("
        SELECT * FROM goals WHERE athlete_id = ? ORDER BY created_at DESC
    ");
    $goals_stmt->execute([$selected_user_id]);
    $user_goals = $goals_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$csrf_token = generateCsrfToken();
?>
<style>
.m-rpt-user { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 100px; }
.m-rpt-user-header { margin-bottom: 16px; }
.m-rpt-user-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-rpt-user-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }

/* Filter bar */
.m-rpt-user-filters { margin-bottom: 16px; }
.m-rpt-user-filters label {
    font-size: 11px; color: #A8A8B8; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.5px;
    display: block; margin-bottom: 4px;
}
.m-rpt-user-filters select,
.m-rpt-user-filters input[type="date"] {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #fff; padding: 12px;
    min-height: 44px; font-size: 14px; -webkit-appearance: none;
    box-sizing: border-box;
}
.m-rpt-user-filters select:focus,
.m-rpt-user-filters input[type="date"]:focus {
    outline: none; border-color: #6B46C1;
}
.m-rpt-user-date-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;
}
.m-rpt-user-btn-apply {
    width: 100%; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; padding: 12px; font-size: 14px; font-weight: 600;
    min-height: 44px; cursor: pointer; margin-top: 10px;
}

/* Summary stats */
.m-rpt-user-summary {
    display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 16px;
}
.m-rpt-user-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; text-align: center;
}
.m-rpt-user-stat-val { font-size: 20px; font-weight: 900; color: #fff; }
.m-rpt-user-stat-lbl { font-size: 10px; color: #A8A8B8; text-transform: uppercase; margin-top: 2px; }

/* User detail header */
.m-rpt-user-detail-hdr {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 12px;
}
.m-rpt-user-detail-name { font-size: 15px; font-weight: 700; color: #fff; }
.m-rpt-user-detail-meta { font-size: 12px; color: #A8A8B8; margin-top: 4px; }

/* Tab bar */
.m-rpt-user-tabs {
    display: flex; gap: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch;
    margin-bottom: 16px; padding-bottom: 4px;
    position: sticky; top: 0; z-index: 10; background: #0A0A0F;
    padding-top: 4px;
}
.m-rpt-user-tabs::-webkit-scrollbar { display: none; }
.m-rpt-user-tab {
    flex-shrink: 0; padding: 10px 16px; background: #16161F;
    border: 1px solid #2D2D3F; border-radius: 10px; color: #A8A8B8;
    font-size: 13px; font-weight: 600; cursor: pointer; min-height: 44px;
    display: flex; align-items: center; gap: 6px; white-space: nowrap;
}
.m-rpt-user-tab.active { background: #6B46C1; border-color: #6B46C1; color: #fff; }

/* Cards */
.m-rpt-user-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-rpt-user-card-row {
    display: flex; justify-content: space-between; align-items: center;
}
.m-rpt-user-card-title { font-size: 14px; font-weight: 600; color: #fff; }
.m-rpt-user-card-sub { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-rpt-user-card-meta { font-size: 11px; color: #6B6B7B; margin-top: 6px; }
.m-rpt-user-card-meta span { margin-right: 10px; }

/* Badges */
.m-rpt-user-badge {
    padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;
    display: inline-block; white-space: nowrap;
}
.m-rpt-user-badge-paid { background: #10B981; color: #fff; }
.m-rpt-user-badge-pending { background: #F59E0B; color: #000; }
.m-rpt-user-badge-cancelled { background: #EF4444; color: #fff; }
.m-rpt-user-badge-confirmed { background: #3B82F6; color: #fff; }
.m-rpt-user-badge-active { background: #10B981; color: #fff; }
.m-rpt-user-badge-completed { background: #6B46C1; color: #fff; }
.m-rpt-user-badge-abandoned { background: #6B6B7B; color: #fff; }

/* Progress bar */
.m-rpt-user-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; margin-top: 8px; }
.m-rpt-user-bar-fill { height: 100%; border-radius: 3px; }

/* Stats grid for athlete stats card */
.m-rpt-user-stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 8px;
}
.m-rpt-user-stats-item { text-align: center; }
.m-rpt-user-stats-item-val { font-size: 14px; font-weight: 700; color: #fff; }
.m-rpt-user-stats-item-lbl { font-size: 9px; color: #6B6B7B; text-transform: uppercase; }

/* Tab content */
.m-rpt-user-tab-content { display: none; }
.m-rpt-user-tab-content.active { display: block; }

/* Empty state */
.m-rpt-user-empty { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-rpt-user-empty i { font-size: 32px; display: block; margin-bottom: 12px; color: #2D2D3F; }
.m-rpt-user-empty p { font-size: 14px; margin: 0; }

/* Section label */
.m-rpt-user-section {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
</style>

<div class="m-rpt-user">
    <div class="m-rpt-user-header">
        <h2 class="m-rpt-user-title"><i class="fas fa-users-gear" style="color:#6B46C1;margin-right:6px;"></i>User Reports</h2>
        <p class="m-rpt-user-sub">Per-user activity &amp; analytics</p>
    </div>

    <!-- Filter bar -->
    <form method="GET" class="m-rpt-user-filters" id="mRptUserFilterForm">
        <input type="hidden" name="page" value="reports_user">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($report_tab); ?>">

        <div style="margin-bottom:10px;">
            <label>Select User</label>
            <select name="user_id" id="mRptUserSelect" onchange="document.getElementById('mRptUserFilterForm').submit();">
                <option value="">-- Choose user --</option>
                <?php foreach ($all_users as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $selected_user_id == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); ?> (<?php echo ucfirst($u['role']); ?> &bull; <?php echo $u['sessions_count']; ?> sessions)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="m-rpt-user-date-row">
            <div>
                <label>From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div>
                <label>To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
        </div>

        <button type="submit" class="m-rpt-user-btn-apply"><i class="fas fa-filter"></i> Apply Filter</button>
    </form>

    <?php if ($selected_user_id && $selected_user): ?>
        <!-- User detail header -->
        <div class="m-rpt-user-detail-hdr">
            <div class="m-rpt-user-detail-name"><?php echo htmlspecialchars(($selected_user['first_name'] ?? '') . ' ' . ($selected_user['last_name'] ?? '')); ?></div>
            <div class="m-rpt-user-detail-meta">
                <?php echo htmlspecialchars($selected_user['email'] ?? ''); ?> &bull; <?php echo ucfirst($selected_user['role'] ?? ''); ?>
                &bull; Since <?php echo $selected_user['created_at'] ? date('M j, Y', strtotime($selected_user['created_at'])) : 'N/A'; ?>
            </div>
        </div>

        <!-- Summary stats -->
        <div class="m-rpt-user-summary">
            <div class="m-rpt-user-stat">
                <div class="m-rpt-user-stat-val"><?php echo count($user_sessions); ?></div>
                <div class="m-rpt-user-stat-lbl">Sessions</div>
            </div>
            <div class="m-rpt-user-stat">
                <div class="m-rpt-user-stat-val"><?php echo count($user_packages); ?></div>
                <div class="m-rpt-user-stat-lbl">Packages</div>
            </div>
            <div class="m-rpt-user-stat">
                <div class="m-rpt-user-stat-val" style="font-size:16px;">$<?php echo number_format(array_sum(array_column($user_sessions, 'amount_paid')), 2); ?></div>
                <div class="m-rpt-user-stat-lbl">Spent</div>
            </div>
            <div class="m-rpt-user-stat">
                <div class="m-rpt-user-stat-val"><?php echo count($user_evaluations); ?></div>
                <div class="m-rpt-user-stat-lbl">Evals</div>
            </div>
            <div class="m-rpt-user-stat">
                <div class="m-rpt-user-stat-val"><?php echo count($user_goals); ?></div>
                <div class="m-rpt-user-stat-lbl">Goals</div>
            </div>
        </div>

        <!-- Tab bar -->
        <div class="m-rpt-user-tabs">
            <button class="m-rpt-user-tab <?php echo $report_tab === 'activity' ? 'active' : ''; ?>" onclick="mRptUserTab('activity')">
                <i class="fas fa-calendar-check"></i> Sessions
            </button>
            <button class="m-rpt-user-tab <?php echo $report_tab === 'stats' ? 'active' : ''; ?>" onclick="mRptUserTab('stats')">
                <i class="fas fa-chart-bar"></i> Stats
            </button>
            <button class="m-rpt-user-tab <?php echo $report_tab === 'evaluations' ? 'active' : ''; ?>" onclick="mRptUserTab('evaluations')">
                <i class="fas fa-clipboard-check"></i> Evals
            </button>
            <button class="m-rpt-user-tab <?php echo $report_tab === 'packages' ? 'active' : ''; ?>" onclick="mRptUserTab('packages')">
                <i class="fas fa-box"></i> Packages
            </button>
            <button class="m-rpt-user-tab <?php echo $report_tab === 'goals' ? 'active' : ''; ?>" onclick="mRptUserTab('goals')">
                <i class="fas fa-bullseye"></i> Goals
            </button>
        </div>

        <!-- Sessions Tab -->
        <div class="m-rpt-user-tab-content <?php echo $report_tab === 'activity' ? 'active' : ''; ?>" id="mTab-activity">
            <?php if (!empty($user_sessions)): ?>
                <h3 class="m-rpt-user-section"><?php echo count($user_sessions); ?> Session<?php echo count($user_sessions) !== 1 ? 's' : ''; ?></h3>
                <?php foreach ($user_sessions as $sess): ?>
                <div class="m-rpt-user-card">
                    <div class="m-rpt-user-card-row">
                        <div>
                            <div class="m-rpt-user-card-title"><?php echo htmlspecialchars($sess['session_title'] ?? ''); ?></div>
                            <div class="m-rpt-user-card-sub"><?php echo htmlspecialchars($sess['session_type'] ?? ''); ?></div>
                        </div>
                        <span class="m-rpt-user-badge m-rpt-user-badge-<?php echo $sess['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($sess['payment_status'] ?? 'pending'); ?></span>
                    </div>
                    <div class="m-rpt-user-card-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo $sess['session_date'] ? date('M j, Y', strtotime($sess['session_date'])) : ''; ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($sess['location_name'] ?? 'N/A'); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo ($sess['duration_minutes'] ?? 0) . ' min'; ?></span>
                        <span><i class="fas fa-dollar-sign"></i> $<?php echo number_format($sess['amount_paid'] ?? 0, 2); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="m-rpt-user-empty">
                    <i class="fas fa-calendar-times"></i>
                    <p>No sessions found in this date range</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats Tab -->
        <div class="m-rpt-user-tab-content <?php echo $report_tab === 'stats' ? 'active' : ''; ?>" id="mTab-stats">
            <?php if (!empty($user_stats)): ?>
                <h3 class="m-rpt-user-section"><?php echo count($user_stats); ?> Season<?php echo count($user_stats) !== 1 ? 's' : ''; ?></h3>
                <?php foreach ($user_stats as $stat): ?>
                <div class="m-rpt-user-card">
                    <div class="m-rpt-user-card-row">
                        <div class="m-rpt-user-card-title"><?php echo htmlspecialchars($stat['season'] ?? 'N/A'); ?></div>
                        <div style="font-size:12px;color:#A8A8B8;"><?php echo ($stat['games_played'] ?? 0); ?> GP</div>
                    </div>
                    <div class="m-rpt-user-stats-grid">
                        <div class="m-rpt-user-stats-item">
                            <div class="m-rpt-user-stats-item-val"><?php echo $stat['goals'] ?? 0; ?></div>
                            <div class="m-rpt-user-stats-item-lbl">G</div>
                        </div>
                        <div class="m-rpt-user-stats-item">
                            <div class="m-rpt-user-stats-item-val"><?php echo $stat['assists'] ?? 0; ?></div>
                            <div class="m-rpt-user-stats-item-lbl">A</div>
                        </div>
                        <div class="m-rpt-user-stats-item">
                            <div class="m-rpt-user-stats-item-val" style="color:#8B5CF6;"><?php echo $stat['points'] ?? 0; ?></div>
                            <div class="m-rpt-user-stats-item-lbl">PTS</div>
                        </div>
                        <div class="m-rpt-user-stats-item">
                            <div class="m-rpt-user-stats-item-val"><?php echo $stat['penalty_minutes'] ?? 0; ?></div>
                            <div class="m-rpt-user-stats-item-lbl">PIM</div>
                        </div>
                        <div class="m-rpt-user-stats-item">
                            <div class="m-rpt-user-stats-item-val"><?php echo $stat['shots'] ?? 0; ?></div>
                            <div class="m-rpt-user-stats-item-lbl">SOG</div>
                        </div>
                        <div class="m-rpt-user-stats-item">
                            <div class="m-rpt-user-stats-item-val"><?php echo $stat['plus_minus'] ?? 0; ?></div>
                            <div class="m-rpt-user-stats-item-lbl">+/-</div>
                        </div>
                        <div class="m-rpt-user-stats-item">
                            <div class="m-rpt-user-stats-item-val"><?php echo $stat['saves'] ?? 0; ?></div>
                            <div class="m-rpt-user-stats-item-lbl">SV</div>
                        </div>
                        <div class="m-rpt-user-stats-item">
                            <div class="m-rpt-user-stats-item-val"><?php echo number_format(($stat['save_percentage'] ?? 0) * 100, 1); ?>%</div>
                            <div class="m-rpt-user-stats-item-lbl">SV%</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="m-rpt-user-empty">
                    <i class="fas fa-chart-bar"></i>
                    <p>No stats recorded for this user</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Evaluations Tab -->
        <div class="m-rpt-user-tab-content <?php echo $report_tab === 'evaluations' ? 'active' : ''; ?>" id="mTab-evaluations">
            <?php if (!empty($user_evaluations)): ?>
                <h3 class="m-rpt-user-section"><?php echo count($user_evaluations); ?> Evaluation<?php echo count($user_evaluations) !== 1 ? 's' : ''; ?></h3>
                <?php foreach ($user_evaluations as $eval): ?>
                <div class="m-rpt-user-card">
                    <div class="m-rpt-user-card-row">
                        <div>
                            <div class="m-rpt-user-card-title"><?php echo htmlspecialchars($eval['skill_name'] ?? ''); ?></div>
                            <div class="m-rpt-user-card-sub"><?php echo htmlspecialchars($eval['skill_category'] ?? ''); ?></div>
                        </div>
                        <div style="font-size:16px;font-weight:700;color:#8B5CF6;"><?php echo $eval['rating']; ?><span style="font-size:12px;color:#6B6B7B;">/5</span></div>
                    </div>
                    <div class="m-rpt-user-card-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($eval['evaluation_date'])); ?></span>
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars(($eval['evaluator_first'] ?? '') . ' ' . ($eval['evaluator_last'] ?? '')); ?></span>
                    </div>
                    <?php if (!empty($eval['comments'])): ?>
                    <div style="font-size:12px;color:#A8A8B8;margin-top:6px;line-height:1.4;"><?php echo htmlspecialchars(substr($eval['comments'], 0, 100)); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="m-rpt-user-empty">
                    <i class="fas fa-clipboard-check"></i>
                    <p>No evaluations found in this date range</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Packages Tab -->
        <div class="m-rpt-user-tab-content <?php echo $report_tab === 'packages' ? 'active' : ''; ?>" id="mTab-packages">
            <?php if (!empty($user_packages)): ?>
                <h3 class="m-rpt-user-section"><?php echo count($user_packages); ?> Package<?php echo count($user_packages) !== 1 ? 's' : ''; ?></h3>
                <?php foreach ($user_packages as $pkg): ?>
                <div class="m-rpt-user-card">
                    <div class="m-rpt-user-card-row">
                        <div>
                            <div class="m-rpt-user-card-title"><?php echo htmlspecialchars($pkg['package_name'] ?? ''); ?></div>
                            <div class="m-rpt-user-card-sub">Purchased <?php echo date('M j, Y', strtotime($pkg['purchase_date'])); ?></div>
                        </div>
                        <span class="m-rpt-user-badge m-rpt-user-badge-<?php echo $pkg['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($pkg['payment_status'] ?? 'pending'); ?></span>
                    </div>
                    <div class="m-rpt-user-card-meta">
                        <span><i class="fas fa-ticket-alt"></i> <?php echo ($pkg['credits_remaining'] ?? 0); ?>/<?php echo ($pkg['package_credits'] ?? 0); ?> credits</span>
                        <span><i class="fas fa-dollar-sign"></i> $<?php echo number_format($pkg['amount_paid'] ?? 0, 2); ?></span>
                        <span><i class="fas fa-hourglass-end"></i> <?php echo $pkg['expiry_date'] ? date('M j, Y', strtotime($pkg['expiry_date'])) : 'No expiry'; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="m-rpt-user-empty">
                    <i class="fas fa-box-open"></i>
                    <p>No packages purchased</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Goals Tab -->
        <div class="m-rpt-user-tab-content <?php echo $report_tab === 'goals' ? 'active' : ''; ?>" id="mTab-goals">
            <?php if (!empty($user_goals)): ?>
                <h3 class="m-rpt-user-section"><?php echo count($user_goals); ?> Goal<?php echo count($user_goals) !== 1 ? 's' : ''; ?></h3>
                <?php foreach ($user_goals as $goal):
                    $target = floatval($goal['target_value'] ?? 0);
                    $current = floatval($goal['current_value'] ?? 0);
                    $pct = $target > 0 ? min(100, round(($current / $target) * 100)) : 0;
                    $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#8B5CF6');
                ?>
                <div class="m-rpt-user-card">
                    <div class="m-rpt-user-card-row">
                        <div style="flex:1;min-width:0;">
                            <div class="m-rpt-user-card-title"><?php echo htmlspecialchars($goal['goal_title']); ?></div>
                            <?php if ($goal['goal_description']): ?>
                            <div class="m-rpt-user-card-sub"><?php echo htmlspecialchars(substr($goal['goal_description'], 0, 80)); ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="m-rpt-user-badge m-rpt-user-badge-<?php echo $goal['status']; ?>"><?php echo ucfirst($goal['status']); ?></span>
                    </div>
                    <div class="m-rpt-user-bar">
                        <div class="m-rpt-user-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $barColor; ?>;"></div>
                    </div>
                    <div class="m-rpt-user-card-meta">
                        <span style="font-weight:600;color:<?php echo $barColor; ?>;"><?php echo $pct; ?>%</span>
                        <?php if ($goal['target_date']): ?>
                        <span><i class="fas fa-flag-checkered"></i> <?php echo date('M j, Y', strtotime($goal['target_date'])); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar-plus"></i> <?php echo date('M j, Y', strtotime($goal['created_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="m-rpt-user-empty">
                    <i class="fas fa-bullseye"></i>
                    <p>No goals set for this user</p>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="m-rpt-user-empty">
            <i class="fas fa-users-gear"></i>
            <p>Select a user above to view their reports</p>
            <p style="font-size:12px;margin-top:8px;color:#6B6B7B;">Sessions, stats, evaluations, packages &amp; goals</p>
        </div>
    <?php endif; ?>
</div>

<script>
function mRptUserTab(tabName) {
    document.querySelectorAll('.m-rpt-user-tab').forEach(function(btn) { btn.classList.remove('active'); });
    document.querySelectorAll('.m-rpt-user-tab').forEach(function(btn) {
        if (btn.getAttribute('onclick') && btn.getAttribute('onclick').indexOf("'" + tabName + "'") !== -1) {
            btn.classList.add('active');
        }
    });
    document.querySelectorAll('.m-rpt-user-tab-content').forEach(function(el) { el.classList.remove('active'); });
    var tabEl = document.getElementById('mTab-' + tabName);
    if (tabEl) tabEl.classList.add('active');
    var tabInput = document.querySelector('#mRptUserFilterForm input[name="tab"]');
    if (tabInput) tabInput.value = tabName;
}
</script>
