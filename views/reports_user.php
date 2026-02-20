<?php
/**
 * views/reports_user.php - Detailed User Reports
 * View per-user registrations, session history, stats, evaluations and packages
 * Filterable by date range and individual user selection
 */
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
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
.user-reports {
    padding: 20px;
}
.user-reports .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.user-reports .page-header h1 {
    margin: 0;
    color: var(--text-white, #fff);
    font-size: var(--font-size-2xl, 22px);
    font-weight: var(--font-weight-bold, 700);
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-reports .page-header h1 i {
    color: var(--primary, #6B46C1);
}
.user-reports .filter-box {
    background: var(--bg-secondary, #13131A);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl, 12px);
    margin-bottom: 24px;
    overflow: hidden;
}
.user-reports .filter-box-header {
    padding: 12px 20px;
    font-size: var(--font-size-sm, 12px);
    font-weight: var(--font-weight-semibold, 600);
    color: var(--text-secondary, #A8A8B8);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}
.user-reports .filter-box-content {
    padding: 16px 20px;
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.user-reports .filter-field label {
    display: block;
    color: var(--text-secondary, #A8A8B8);
    font-size: var(--font-size-xs, 11px);
    font-weight: var(--font-weight-semibold, 600);
    text-transform: uppercase;
    margin-bottom: 6px;
}
.user-reports .filter-field input[type="date"] {
    padding: 8px 12px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    color: var(--text-white, #fff);
    border-radius: var(--radius-md, 6px);
    font-size: var(--font-size-base, 14px);
}
.user-reports .filter-field input[type="date"]:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
}
.user-reports .content-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 20px;
    min-height: 600px;
}
@media (max-width: 1024px) {
    .user-reports .content-grid {
        grid-template-columns: 1fr;
        min-height: auto;
    }
}
.user-reports .users-panel,
.user-reports .details-panel {
    background: var(--bg-secondary, #13131A);
    border-radius: var(--radius-2xl, 12px);
    padding: 20px;
    border: 1px solid var(--border, #2D2D3F);
    overflow-y: auto;
    max-height: calc(100vh - 280px);
}
.user-reports .users-panel h3 {
    margin: 0 0 16px 0;
    color: var(--text-white, #fff);
    font-size: var(--font-size-base, 14px);
    font-weight: var(--font-weight-bold, 700);
    display: flex;
    align-items: center;
    gap: 8px;
}
.user-reports .users-panel h3 i {
    color: var(--primary, #6B46C1);
}
.user-reports .user-search {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    color: var(--text-white, #fff);
    border-radius: var(--radius-md, 6px);
    margin-bottom: 12px;
    font-size: var(--font-size-base, 14px);
}
.user-reports .user-search:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
}
.user-reports .users-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.user-reports .user-card {
    padding: 14px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    text-decoration: none;
    color: var(--text-white, #fff);
    transition: all var(--transition-normal, 0.2s ease);
    display: block;
}
.user-reports .user-card:hover,
.user-reports .user-card.active {
    background: var(--primary, #6B46C1);
    border-color: var(--primary, #6B46C1);
    color: #fff;
}
.user-reports .user-card .user-name {
    font-weight: var(--font-weight-semibold, 600);
    font-size: var(--font-size-base, 14px);
    margin-bottom: 4px;
}
.user-reports .user-card .user-meta {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-sm, 12px);
    opacity: 0.8;
}
.user-reports .tab-bar {
    display: flex;
    gap: 4px;
    margin-bottom: 20px;
    background: var(--bg-main, #0A0A0F);
    border-radius: var(--radius-lg, 8px);
    padding: 4px;
    border: 1px solid var(--border, #2D2D3F);
}
.user-reports .tab-btn {
    padding: 10px 18px;
    background: transparent;
    border: none;
    color: var(--text-secondary, #A8A8B8);
    border-radius: var(--radius-md, 6px);
    cursor: pointer;
    font-weight: var(--font-weight-semibold, 600);
    font-size: var(--font-size-base, 14px);
    transition: all var(--transition-normal, 0.2s ease);
}
.user-reports .tab-btn.active,
.user-reports .tab-btn:hover {
    background: var(--primary, #6B46C1);
    color: #fff;
}
.user-reports .detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}
.user-reports .detail-header h3 {
    margin: 0;
    color: var(--text-white, #fff);
}
.user-reports .detail-header p {
    margin: 4px 0 0;
    color: var(--text-muted, #6B6B7B);
    font-size: var(--font-size-base, 14px);
}
.user-reports .summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.user-reports .summary-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    padding: 16px;
    border-radius: var(--radius-lg, 8px);
    text-align: center;
}
.user-reports .summary-card .value {
    font-size: var(--font-size-3xl, 28px);
    font-weight: var(--font-weight-black, 900);
    color: var(--text-white, #fff);
}
.user-reports .summary-card .label {
    font-size: var(--font-size-xs, 11px);
    color: var(--text-secondary, #A8A8B8);
    text-transform: uppercase;
    margin-top: 4px;
}
.user-reports .data-table {
    width: 100%;
    border-collapse: collapse;
}
.user-reports .data-table th {
    padding: 12px;
    background: var(--bg-main, #0A0A0F);
    color: var(--text-secondary, #A8A8B8);
    text-align: left;
    font-size: var(--font-size-xs, 11px);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: var(--font-weight-semibold, 600);
}
.user-reports .data-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    color: var(--text-white, #fff);
    font-size: var(--font-size-base, 14px);
}
.user-reports .data-table tr:hover td {
    background: rgba(107, 70, 193, 0.05);
}
.user-reports .badge {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: var(--font-size-xs, 11px);
    font-weight: var(--font-weight-semibold, 600);
    display: inline-block;
}
.user-reports .badge-paid { background: var(--success, #10B981); color: #fff; }
.user-reports .badge-pending { background: var(--warning, #F59E0B); color: #000; }
.user-reports .badge-cancelled { background: var(--error, #EF4444); color: #fff; }
.user-reports .badge-confirmed { background: var(--info, #3B82F6); color: #fff; }
.user-reports .badge-active { background: var(--success, #10B981); color: #fff; }
.user-reports .badge-completed { background: var(--primary, #6B46C1); color: #fff; }
.user-reports .badge-abandoned { background: var(--text-muted, #6B6B7B); color: #fff; }
.user-reports .empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted, #6B6B7B);
}
.user-reports .empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
    color: var(--border, #2D2D3F);
}
.user-reports .btn-export {
    padding: 8px 16px;
    background: var(--border, #2D2D3F);
    color: var(--text-white, #fff);
    border: none;
    border-radius: var(--radius-md, 6px);
    cursor: pointer;
    font-size: var(--font-size-sm, 12px);
    font-weight: var(--font-weight-semibold, 600);
    transition: all var(--transition-normal, 0.2s ease);
}
.user-reports .btn-export:hover {
    background: var(--primary, #6B46C1);
}
.user-reports .tab-content {
    display: none;
}
.user-reports .tab-content.active {
    display: block;
}
</style>

<div class="user-reports">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-users-gear"></i> User Reports</h1>
        <div>
            <a href="process_users_email_export.php" class="btn-export" data-action="link"><i class="fas fa-envelope"></i> Export Emails</a>
            <?php if ($selected_user_id && $selected_user): ?>
            <button onclick="exportUserCSV()" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button onclick="window.print()" class="btn-export"><i class="fas fa-print"></i> Print</button>
            <?php endif; ?>
        </div>
    </div>

    <form method="GET" class="filter-box" id="filterForm">
        <div class="filter-box-header">
            <i class="fas fa-filter"></i> Filter Date Range
        </div>
        <div class="filter-box-content">
            <input type="hidden" name="page" value="reports_user">
            <?php if ($selected_user_id): ?>
            <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
            <?php endif; ?>
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($report_tab); ?>">
            <div class="filter-field">
                <label>From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="filter-field">
                <label>To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="filter-field filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
            </div>
        </div>
    </form>

    <div class="content-grid">
        <!-- Users List Panel -->
        <div class="users-panel">
            <h3><i class="fas fa-users"></i> Users (<?php echo count($all_users); ?>)</h3>
            <input type="text" class="user-search" id="userSearch" placeholder="Search by name or email..." onkeyup="filterUsers()">
            <div class="users-list" id="usersList">
                <?php foreach ($all_users as $u): ?>
                <a href="?page=reports_user&user_id=<?php echo $u['id']; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&tab=<?php echo urlencode($report_tab); ?>"
                   class="user-card <?php echo $selected_user_id == $u['id'] ? 'active' : ''; ?>"
                   data-name="<?php echo htmlspecialchars(strtolower(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?>"
                   data-email="<?php echo htmlspecialchars(strtolower($u['email'] ?? '')); ?>">
                    <div class="user-name"><?php echo htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); ?></div>
                    <div class="user-meta">
                        <span><?php echo ucfirst($u['role']); ?></span>
                        <span><?php echo $u['sessions_count']; ?> sessions</span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($all_users)): ?>
                    <p class="empty-state">No users found</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Details Panel -->
        <div class="details-panel">
            <?php if ($selected_user_id && $selected_user): ?>
                <div class="detail-header">
                    <div>
                        <h3><?php echo htmlspecialchars(($selected_user['first_name'] ?? '') . ' ' . ($selected_user['last_name'] ?? '')); ?></h3>
                        <p><?php echo htmlspecialchars($selected_user['email'] ?? ''); ?> &bull; <?php echo ucfirst($selected_user['role'] ?? ''); ?>
                           &bull; Member since <?php echo $selected_user['created_at'] ? date('M j, Y', strtotime($selected_user['created_at'])) : 'N/A'; ?></p>
                    </div>
                </div>

                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="value"><?php echo count($user_sessions); ?></div>
                        <div class="label">Sessions</div>
                    </div>
                    <div class="summary-card">
                        <div class="value"><?php echo count($user_packages); ?></div>
                        <div class="label">Packages</div>
                    </div>
                    <div class="summary-card">
                        <div class="value">$<?php echo number_format(array_sum(array_column($user_sessions, 'amount_paid')), 2); ?></div>
                        <div class="label">Spent (Sessions)</div>
                    </div>
                    <div class="summary-card">
                        <div class="value"><?php echo count($user_evaluations); ?></div>
                        <div class="label">Evaluations</div>
                    </div>
                    <div class="summary-card">
                        <div class="value"><?php echo count($user_goals); ?></div>
                        <div class="label">Goals</div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tab-bar">
                    <button class="tab-btn <?php echo $report_tab === 'activity' ? 'active' : ''; ?>" onclick="switchTab('activity')">
                        <i class="fas fa-calendar-check"></i> Sessions
                    </button>
                    <button class="tab-btn <?php echo $report_tab === 'stats' ? 'active' : ''; ?>" onclick="switchTab('stats')">
                        <i class="fas fa-chart-bar"></i> Stats
                    </button>
                    <button class="tab-btn <?php echo $report_tab === 'evaluations' ? 'active' : ''; ?>" onclick="switchTab('evaluations')">
                        <i class="fas fa-clipboard-check"></i> Evaluations
                    </button>
                    <button class="tab-btn <?php echo $report_tab === 'packages' ? 'active' : ''; ?>" onclick="switchTab('packages')">
                        <i class="fas fa-box"></i> Packages
                    </button>
                    <button class="tab-btn <?php echo $report_tab === 'goals' ? 'active' : ''; ?>" onclick="switchTab('goals')">
                        <i class="fas fa-bullseye"></i> Goals
                    </button>
                </div>

                <!-- Sessions Tab -->
                <div class="tab-content <?php echo $report_tab === 'activity' ? 'active' : ''; ?>" id="tab-activity">
                    <?php if (!empty($user_sessions)): ?>
                    <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Session</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Duration</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_sessions as $sess): ?>
                            <tr>
                                <td><?php echo $sess['session_date'] ? date('M j, Y', strtotime($sess['session_date'])) : ''; ?></td>
                                <td><?php echo htmlspecialchars($sess['session_title'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($sess['session_type'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($sess['location_name'] ?? ''); ?></td>
                                <td><?php echo ($sess['duration_minutes'] ?? 0) . ' min'; ?></td>
                                <td>$<?php echo number_format($sess['amount_paid'] ?? 0, 2); ?></td>
                                <td><span class="badge badge-<?php echo $sess['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($sess['payment_status'] ?? 'pending'); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No sessions found in this date range</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Stats Tab -->
                <div class="tab-content <?php echo $report_tab === 'stats' ? 'active' : ''; ?>" id="tab-stats">
                    <?php if (!empty($user_stats)): ?>
                    <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Season</th>
                                <th>GP</th>
                                <th>G</th>
                                <th>A</th>
                                <th>PTS</th>
                                <th>PIM</th>
                                <th>SOG</th>
                                <th>+/-</th>
                                <th>SA</th>
                                <th>GA</th>
                                <th>SV</th>
                                <th>SV%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_stats as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['season'] ?? 'N/A'); ?></td>
                                <td><?php echo $stat['games_played'] ?? 0; ?></td>
                                <td><?php echo $stat['goals'] ?? 0; ?></td>
                                <td><?php echo $stat['assists'] ?? 0; ?></td>
                                <td><strong><?php echo $stat['points'] ?? 0; ?></strong></td>
                                <td><?php echo $stat['penalty_minutes'] ?? 0; ?></td>
                                <td><?php echo $stat['shots'] ?? 0; ?></td>
                                <td><?php echo $stat['plus_minus'] ?? 0; ?></td>
                                <td><?php echo $stat['shots_against'] ?? 0; ?></td>
                                <td><?php echo $stat['goals_against'] ?? 0; ?></td>
                                <td><?php echo $stat['saves'] ?? 0; ?></td>
                                <td><?php /* save_percentage is stored as decimal, e.g. 0.923 */ echo number_format(($stat['save_percentage'] ?? 0) * 100, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>No stats recorded for this user</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Evaluations Tab -->
                <div class="tab-content <?php echo $report_tab === 'evaluations' ? 'active' : ''; ?>" id="tab-evaluations">
                    <?php if (!empty($user_evaluations)): ?>
                    <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Skill</th>
                                <th>Category</th>
                                <th>Rating</th>
                                <th>Evaluator</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_evaluations as $eval): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($eval['evaluation_date'])); ?></td>
                                <td><?php echo htmlspecialchars($eval['skill_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($eval['skill_category'] ?? ''); ?></td>
                                <td><strong><?php echo $eval['rating']; ?></strong>/5</td>
                                <td><?php echo htmlspecialchars(($eval['evaluator_first'] ?? '') . ' ' . ($eval['evaluator_last'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(substr($eval['comments'] ?? '', 0, 100)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <p>No evaluations found in this date range</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Packages Tab -->
                <div class="tab-content <?php echo $report_tab === 'packages' ? 'active' : ''; ?>" id="tab-packages">
                    <?php if (!empty($user_packages)): ?>
                    <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Purchase Date</th>
                                <th>Package</th>
                                <th>Credits</th>
                                <th>Remaining</th>
                                <th>Expiry</th>
                                <th>Amount Paid</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_packages as $pkg): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($pkg['purchase_date'])); ?></td>
                                <td><?php echo htmlspecialchars($pkg['package_name'] ?? ''); ?></td>
                                <td><?php echo $pkg['package_credits'] ?? 0; ?></td>
                                <td><?php echo $pkg['credits_remaining'] ?? 0; ?></td>
                                <td><?php echo $pkg['expiry_date'] ? date('M j, Y', strtotime($pkg['expiry_date'])) : 'N/A'; ?></td>
                                <td>$<?php echo number_format($pkg['amount_paid'] ?? 0, 2); ?></td>
                                <td><span class="badge badge-<?php echo $pkg['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($pkg['payment_status'] ?? 'pending'); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>No packages purchased</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Goals Tab -->
                <div class="tab-content <?php echo $report_tab === 'goals' ? 'active' : ''; ?>" id="tab-goals">
                    <?php if (!empty($user_goals)): ?>
                    <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Goal</th>
                                <th>Target Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_goals as $goal): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($goal['goal_title']); ?></strong>
                                    <?php if ($goal['goal_description']): ?>
                                    <br><small style="color: var(--text-muted, #6B6B7B);"><?php echo htmlspecialchars(substr($goal['goal_description'], 0, 80)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $goal['target_date'] ? date('M j, Y', strtotime($goal['target_date'])) : 'N/A'; ?></td>
                                <td>
                                    <?php
                                    $target = floatval($goal['target_value'] ?? 0);
                                    $current = floatval($goal['current_value'] ?? 0);
                                    $pct = $target > 0 ? min(100, round(($current / $target) * 100)) : 0;
                                    ?>
                                    <div style="background: var(--border, #2D2D3F); border-radius: 4px; height: 8px; width: 100px; display: inline-block; vertical-align: middle;">
                                        <div style="background: var(--primary, #6B46C1); border-radius: 4px; height: 100%; width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                    <span style="font-size: 12px; margin-left: 6px;"><?php echo $pct; ?>%</span>
                                </td>
                                <td><span class="badge badge-<?php echo $goal['status']; ?>"><?php echo ucfirst($goal['status']); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($goal['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullseye"></i>
                        <p>No goals set for this user</p>
                    </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-gear"></i>
                    <p>Select a user from the list to view their detailed reports</p>
                    <p style="font-size: var(--font-size-sm, 12px); margin-top: 8px;">View session registrations, stats, evaluations, packages and goals</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterUsers() {
    var query = document.getElementById('userSearch').value.toLowerCase();
    var cards = document.querySelectorAll('.user-reports .user-card');
    cards.forEach(function(card) {
        var name = card.getAttribute('data-name') || '';
        var email = card.getAttribute('data-email') || '';
        card.style.display = (name.indexOf(query) !== -1 || email.indexOf(query) !== -1) ? '' : 'none';
    });
}

function switchTab(tabName) {
    // Update active tab button
    document.querySelectorAll('.user-reports .tab-btn').forEach(function(btn) { btn.classList.remove('active'); });
    // Find the clicked button by tab name
    document.querySelectorAll('.user-reports .tab-btn').forEach(function(btn) {
        if (btn.getAttribute('onclick') && btn.getAttribute('onclick').indexOf("'" + tabName + "'") !== -1) {
            btn.classList.add('active');
        }
    });
    // Show corresponding content
    document.querySelectorAll('.user-reports .tab-content').forEach(function(el) { el.classList.remove('active'); });
    var tabEl = document.getElementById('tab-' + tabName);
    if (tabEl) tabEl.classList.add('active');
    // Update hidden tab input for filter persistence
    var tabInput = document.querySelector('#filterForm input[name="tab"]');
    if (tabInput) tabInput.value = tabName;
}

function exportUserCSV() {
    var activeTab = document.querySelector('.user-reports .tab-content.active');
    if (!activeTab) return;
    var table = activeTab.querySelector('.data-table');
    if (!table) { alert('No data to export'); return; }

    var csv = [];
    var rows = table.querySelectorAll('tr');
    rows.forEach(function(row) {
        var cols = row.querySelectorAll('th, td');
        var rowData = [];
        cols.forEach(function(col) {
            var text = col.innerText.replace(/"/g, '""').replace(/\n/g, ' ');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });

    var csvContent = csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'user_report_<?php echo $selected_user_id ?? 'all'; ?>_' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
}
</script>
