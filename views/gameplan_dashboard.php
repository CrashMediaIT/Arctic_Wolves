<?php
/**
 * Game Plan Dashboard - Integrated Module
 * 
 * Pre/post game planning system with video tagging, hockey lines,
 * calendar, film room, and review sessions.
 * 
 * Integrated into the main dashboard shell, using the site's
 * standard design patterns (page-tabs, filter-box, card, etc.).
 */

// Sub-page routing within the game plan module
$gp_subpage = 'home';
switch ($page) {
    case 'gameplan_video':          $gp_subpage = 'video_review'; break;
    case 'gameplan_calendar':       $gp_subpage = 'calendar'; break;
    case 'gameplan_plans':          $gp_subpage = 'game_plan'; break;
    case 'gameplan_film_room':      $gp_subpage = 'film_room'; break;
    case 'gameplan_review_sessions':$gp_subpage = 'review_sessions'; break;
    case 'gameplan_my_clips':       $gp_subpage = 'my_clips'; break;
    case 'gameplan_permissions':    $gp_subpage = 'permissions'; break;
    case 'gameplan_lines':          $gp_subpage = 'lines'; break;
    default:                        $gp_subpage = 'home'; break;
}

// Load recent videos for the home page
$recentVideos = [];
try {
    $videoWhere = '';
    $videoParams = [];
    if (!$isAnyCoach) {
        $videoWhere = 'WHERE v.athlete_id = ?';
        $videoParams[] = $user_id;
    }
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.filename, v.file_path, v.duration, v.status,
               v.created_at, v.athlete_id,
               u.first_name as athlete_first_name, u.last_name as athlete_last_name
        FROM videos v
        LEFT JOIN users u ON v.athlete_id = u.id
        $videoWhere
        ORDER BY v.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($videoParams);
    $recentVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignore */ }
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-chess-board"></i> Game Plan</h1>
        <p class="page-description">Pre-game strategies, post-game reviews, video tagging, and hockey line management</p>
    </div>
</div>

<!-- Game Plan Navigation Tabs -->
<div class="page-tabs" style="flex-wrap: wrap;">
    <a href="?page=gameplan" class="page-tab <?= $gp_subpage === 'home' ? 'active' : '' ?>">
        <i class="fas fa-house"></i> Dashboard
    </a>
    <a href="?page=gameplan_video" class="page-tab <?= $gp_subpage === 'video_review' ? 'active' : '' ?>">
        <i class="fas fa-film"></i> Video Review
    </a>
    <?php if ($isAnyCoach): ?>
    <a href="?page=gameplan_calendar" class="page-tab <?= $gp_subpage === 'calendar' ? 'active' : '' ?>">
        <i class="fas fa-calendar"></i> Calendar
    </a>
    <a href="?page=gameplan_plans" class="page-tab <?= $gp_subpage === 'game_plan' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list"></i> Game Plans
    </a>
    <a href="?page=gameplan_lines" class="page-tab <?= $gp_subpage === 'lines' ? 'active' : '' ?>">
        <i class="fas fa-users-line"></i> Hockey Lines
    </a>
    <a href="?page=gameplan_film_room" class="page-tab <?= $gp_subpage === 'film_room' ? 'active' : '' ?>">
        <i class="fas fa-video"></i> Film Room
    </a>
    <a href="?page=gameplan_review_sessions" class="page-tab <?= $gp_subpage === 'review_sessions' ? 'active' : '' ?>">
        <i class="fas fa-chalkboard-user"></i> Review Sessions
    </a>
    <?php else: ?>
    <a href="?page=gameplan_my_clips" class="page-tab <?= $gp_subpage === 'my_clips' ? 'active' : '' ?>">
        <i class="fas fa-scissors"></i> My Clips
    </a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <a href="?page=gameplan_permissions" class="page-tab <?= $gp_subpage === 'permissions' ? 'active' : '' ?>">
        <i class="fas fa-user-shield"></i> Permissions
    </a>
    <?php endif; ?>
</div>

<div class="page-tab-content" style="margin-top: 24px;">
<?php
$gp_view_map = [
    'home'            => 'views/gameplan/gp_home.php',
    'video_review'    => 'views/gameplan/gp_video_review.php',
    'calendar'        => 'views/gameplan/gp_calendar.php',
    'game_plan'       => 'views/gameplan/gp_game_plan.php',
    'film_room'       => 'views/gameplan/gp_film_room.php',
    'review_sessions' => 'views/gameplan/gp_review_sessions.php',
    'my_clips'        => 'views/gameplan/gp_my_clips.php',
    'permissions'     => 'views/gameplan/gp_permissions.php',
    'lines'           => 'views/gameplan/gp_lines.php',
];
$gp_view_file = $gp_view_map[$gp_subpage] ?? $gp_view_map['home'];
if (file_exists($gp_view_file)) {
    include $gp_view_file;
} else {
    include $gp_view_map['home'];
}
?>
</div>
