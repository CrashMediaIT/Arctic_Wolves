<?php
/**
 * Game Plan - Redirect to Dashboard-Integrated Module
 *
 * The Game Plan module is now integrated into the main dashboard.
 * This file redirects users to the appropriate dashboard page.
 * Preserves backward compatibility for existing bookmarks.
 *
 * The companion server is optional – the dashboard works without it.
 */

require_once __DIR__ . '/config/session.php';
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: /login.php");
    exit();
}

// Map old standalone page routes to new dashboard routes
$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['page']) : 'home';
$tab = isset($_GET['tab']) ? '&tab=' . urlencode($_GET['tab']) : '';
$extra = '';

// Preserve any extra query parameters
foreach ($_GET as $k => $v) {
    if ($k !== 'page' && $k !== 'tab') {
        $extra .= '&' . urlencode($k) . '=' . urlencode($v);
    }
}

$route_map = [
    'home'            => 'gameplan',
    'video_review'    => 'gameplan_video',
    'calendar'        => 'gameplan_calendar',
    'game_plan'       => 'gameplan_plans',
    'film_room'       => 'gameplan_film_room',
    'review_sessions' => 'gameplan_review_sessions',
    'my_clips'        => 'gameplan_my_clips',
    'permissions'     => 'gameplan_permissions',
];

$dashboard_page = $route_map[$page] ?? 'gameplan';
header("Location: /dashboard.php?page=" . $dashboard_page . $tab . $extra);
exit();
