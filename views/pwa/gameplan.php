<?php
/**
 * PWA Game Plan - Mobile-native game plan hub
 * Purpose-built for mobile phones, renders within the PWA shell.
 * Provides navigation to all game plan sub-pages and shows
 * quick stats and recent activity.
 */

// Map of sub-pages to gp_ view files
$gp_views = [
    'home'            => 'views/gameplan/gp_home.php',
    'video_review'    => 'views/gameplan/gp_video_review.php',
    'calendar'        => 'views/gameplan/gp_calendar.php',
    'game_plan'       => 'views/gameplan/gp_game_plan.php',
    'film_room'       => 'views/gameplan/gp_film_room.php',
    'review_sessions' => 'views/gameplan/gp_review_sessions.php',
    'my_clips'        => 'views/gameplan/gp_my_clips.php',
    'lines'           => 'views/gameplan/gp_lines.php',
    'roster'          => 'views/gameplan/gp_roster.php',
    'whiteboard'      => 'views/gameplan/gp_whiteboard.php',
];

if ($isAdmin) {
    $gp_views['permissions'] = 'views/gameplan/gp_permissions.php';
}

// Determine sub-page from query parameter, validate against allowed keys
$gp_sub = isset($_GET['gp']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['gp']) : 'home';
if (!isset($gp_views[$gp_sub])) {
    $gp_sub = 'home';
}

// Load recent videos (needed by gp_home.php)
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
    $recentVideos = decryptUserRows($recentVideos);
} catch (PDOException $e) { /* ignore */ }

// Auto-cast: sync current page to any active unfrozen pairs this user controls.
// This makes TV pairing work like casting — the controller navigates normally and
// the TV automatically follows without needing dedicated navigation buttons.
$gp_pwa_active_pair_id = 0;
if ($isAnyCoach && isset($gp_views[$gp_sub])) {
    try {
        $castStmt = $pdo->prepare("
            UPDATE vr_device_pairs
            SET controller_page = ?, status = 'active'
            WHERE is_frozen = 0
              AND status IN ('paired', 'active')
              AND (created_by = ? OR id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
        ");
        $castStmt->execute([$gp_sub, $user_id, $user_id]);
    } catch (PDOException $e) {
        // Silently ignore — casting is best-effort
    }
}
// Detect active pair for global telestration overlay
if ($isAnyCoach) {
    try {
        $pwaPairDetect = $pdo->prepare("
            SELECT id FROM vr_device_pairs
            WHERE status IN ('paired', 'active')
              AND (created_by = ? OR id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
            LIMIT 1
        ");
        $pwaPairDetect->execute([$user_id, $user_id]);
        $pwaPairRow = $pwaPairDetect->fetch(PDO::FETCH_ASSOC);
        if ($pwaPairRow) $gp_pwa_active_pair_id = (int)$pwaPairRow['id'];
    } catch (PDOException $e) { /* ignore */ }
}

// Sub-page labels for the header
$gp_labels = [
    'home'            => 'Game Plan',
    'video_review'    => 'Video Review',
    'calendar'        => 'Calendar',
    'game_plan'       => 'Game Plans',
    'film_room'       => 'Film Room',
    'review_sessions' => 'Review Sessions',
    'my_clips'        => 'My Clips',
    'lines'           => 'Game Lines',
    'roster'          => 'Roster',
    'whiteboard'      => 'Whiteboard',
    'permissions'     => 'Permissions',
];

$gp_current_label = $gp_labels[$gp_sub] ?? 'Game Plan';
$gp_view_file = $gp_views[$gp_sub] ?? $gp_views['home'];
$gp_is_sub = ($gp_sub !== 'home');
?>

<style>
/* PWA Game Plan mobile styles */
.m-gp { padding: 0; font-family: Inter, sans-serif; }
.m-gp-header {
    padding: 16px; border-bottom: 1px solid var(--border, #2D2D3F);
    display: flex; align-items: center; gap: 12px;
}
.m-gp-back {
    width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border, #2D2D3F);
    background: none; color: #fff; font-size: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    min-height: auto; padding: 0; text-decoration: none;
}
.m-gp-header-info { flex: 1; }
.m-gp-title {
    font-size: 17px; font-weight: 700; color: #fff;
    display: flex; align-items: center; gap: 8px; margin: 0;
}
.m-gp-title i { color: var(--primary-light, #8B5CF6); }
.m-gp-sub { font-size: 12px; color: var(--text-muted, #A8A8B8); margin: 2px 0 0; }

/* Menu toggle button for sub-pages */
.m-gp-menu-toggle {
    width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border, #2D2D3F);
    background: none; color: #fff; font-size: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    min-height: auto; padding: 0;
}

/* Navigation drawer (slide-out, similar to desktop sidebar) */
.m-gp-drawer-overlay {
    display: none !important; position: fixed; inset: 0;
    background: rgba(0,0,0,0.6); z-index: 200;
}
.m-gp-drawer-overlay.open { display: block !important; }
.m-gp-drawer {
    position: fixed; top: 0; left: -280px; width: 270px; height: 100vh;
    background: var(--sidebar, #13131A); border-right: 1px solid var(--border, #2D2D3F);
    z-index: 201; display: flex; flex-direction: column;
    transition: left 0.25s ease; overflow-y: auto;
}
.m-gp-drawer.open { left: 0; }
.m-gp-drawer-brand {
    display: flex; align-items: center; gap: 12px;
    padding: 20px 20px 10px; text-decoration: none; color: #fff;
    font-weight: 900; font-size: 16px;
    border-bottom: 1px solid var(--border, #2D2D3F); margin-bottom: 8px;
}
.m-gp-drawer-brand .hl { color: var(--primary-light, #8B5CF6); }
.m-gp-drawer-brand small {
    display: block; font-size: 10px; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: var(--text-muted, #A8A8B8); margin-top: 2px;
}
.m-gp-drawer-label {
    font-size: 10px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1.5px; color: var(--text-muted, #A8A8B8);
    padding: 16px 20px 6px;
}
.m-gp-drawer-link {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 20px; text-decoration: none;
    color: var(--text-secondary, #C0C0CC); font-size: 13px; font-weight: 600;
    transition: all 0.15s; margin: 0 8px 2px; border-radius: 8px;
}
.m-gp-drawer-link:hover { background: rgba(107,70,193,0.08); color: #fff; }
.m-gp-drawer-link.active { background: rgba(107,70,193,0.15); color: var(--primary-light, #8B5CF6); }
.m-gp-drawer-link i { width: 18px; text-align: center; font-size: 14px; }
.m-gp-drawer-footer {
    margin-top: auto; padding: 12px 8px;
    border-top: 1px solid var(--border, #2D2D3F);
}

/* Navigation cards (home view) */
.m-gp-nav-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
    padding: 16px;
}
.m-gp-nav-card {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 20px 12px; background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F); border-radius: 12px;
    text-decoration: none; color: #fff; transition: border-color 0.2s;
    text-align: center;
}
.m-gp-nav-card:hover, .m-gp-nav-card:active {
    border-color: var(--primary, #6B46C1);
}
.m-gp-nav-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.m-gp-nav-label { font-size: 13px; font-weight: 600; }
.m-gp-nav-count { font-size: 11px; color: var(--text-muted, #A8A8B8); }

/* Stats row */
.m-gp-stats {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)); gap: 10px;
    padding: 16px; border-bottom: 1px solid var(--border, #2D2D3F);
}
.m-gp-stat {
    text-align: center; padding: 12px 4px;
    background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
}
.m-gp-stat-val { font-size: 22px; font-weight: 900; color: #fff; }
.m-gp-stat-lbl { font-size: 10px; color: var(--text-muted, #A8A8B8); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

/* Sub-page content wrapper */
.m-gp-content { padding: 16px; }

/* Override gp_ view styles for mobile context */
.m-gp-content .card { margin-bottom: 16px; }
.m-gp-content .card-header { padding: 14px 16px; }
.m-gp-content .card-body { padding: 14px 16px; }

/* ── Mobile overrides for gp_ sub-views ────────────────────── */

/* Page headers: compact for mobile */
.m-gp-content .page-header { margin-bottom: 16px; }
.m-gp-content .page-header h1 { font-size: 18px; gap: 8px; }
.m-gp-content .page-header p { font-size: 12px; }

/* Page tabs: horizontal scroll instead of wrapping */
.m-gp-content .page-tabs {
    display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch;
    gap: 0; white-space: nowrap; margin-bottom: 16px;
    scrollbar-width: none; -ms-overflow-style: none;
}
.m-gp-content .page-tabs::-webkit-scrollbar { display: none; }
.m-gp-content .page-tab { font-size: 12px; padding: 8px 12px; flex-shrink: 0; }

/* Filter boxes: stack fields vertically */
.m-gp-content .filter-box { margin-bottom: 16px; }
.m-gp-content .filter-row {
    display: flex; flex-direction: column; gap: 10px;
}
.m-gp-content .filter-field { width: 100%; min-width: 0; }
.m-gp-content .filter-actions {
    display: flex; gap: 8px; flex-direction: row;
}

/* Buttons: touch-friendly */
.m-gp-content .btn { min-height: 38px; font-size: 13px; }

/* Modals: full-width on mobile */
.m-gp-content .modal-overlay {
    align-items: flex-end; padding: 0;
}
.m-gp-content .modal-content {
    width: 100% !important; max-width: 100% !important;
    max-height: 92vh; border-radius: 16px 16px 0 0;
}

/* Form grids: stack two-column layouts */
.m-gp-content .form-row,
.m-gp-content [style*="grid-template-columns: 1fr 1fr"],
.m-gp-content [style*="grid-template-columns:1fr 1fr"] {
    display: grid !important; grid-template-columns: 1fr !important;
}

/* Card grids: single column on small screens */
.m-gp-content [style*="minmax(320px"],
.m-gp-content [style*="minmax(300px"],
.m-gp-content [style*="minmax(280px"],
.m-gp-content [style*="minmax(260px"] {
    grid-template-columns: 1fr !important;
}

/* Lines builder: stack roster sidebar + content vertically */
.m-gp-content [style*="grid-template-columns: 250px 1fr"],
.m-gp-content [style*="grid-template-columns:250px 1fr"] {
    display: flex !important; flex-direction: column !important; gap: 16px !important;
}
/* Remove sticky on roster panel in stacked layout */
.m-gp-content .card[style*="sticky"] { position: relative !important; top: auto !important; }

/* Film room: clip editor two-column to stacked */
.m-gp-content [style*="grid-template-columns: 1fr 350px"],
.m-gp-content [style*="grid-template-columns:1fr 350px"] {
    display: flex !important; flex-direction: column !important; gap: 16px !important;
}

/* Source list cards: stack instead of 3-column grid */
.m-gp-content [style*="grid-template-columns: 80px 1fr auto"],
.m-gp-content [style*="grid-template-columns:80px 1fr auto"] {
    display: flex !important; flex-direction: column !important; gap: 10px !important;
    text-align: left;
}
.m-gp-content [style*="grid-template-columns: 80px 1fr auto"] > div:first-child {
    width: 56px !important; height: 40px !important; font-size: 16px;
}

/* Permissions table: horizontally scrollable container */
.m-gp-content table { font-size: 12px; }
.m-gp-content table th { padding: 10px 8px !important; font-size: 10px; }
.m-gp-content table td { padding: 10px 8px !important; }
.m-gp-content .card-body[style*="overflow-x"] { -webkit-overflow-scrolling: touch; }

/* Whiteboard toolbar: wrap buttons, reduce size */
.m-gp-content #wbToolbar .card-body > div {
    gap: 6px !important; justify-content: flex-start;
}
.m-gp-content #wbToolbar .card-body > div > div {
    border-right: none !important; padding-right: 6px !important;
}
.m-gp-content .wb-tool,
.m-gp-content #wbToolbar .btn { height: 36px !important; width: 36px !important; }
.m-gp-content .wb-color { width: 28px !important; height: 28px !important; }

/* Whiteboard selects: full width stacked */
.m-gp-content #wbHeader [style*="display:flex"] { flex-direction: column; gap: 8px; }
.m-gp-content #wbHeader select { width: 100% !important; min-width: 0 !important; }

/* Line position slots: 2-col minimum on small screens */
.m-gp-content .card-body [style*="grid-template-columns:repeat(5"] {
    grid-template-columns: repeat(3, 1fr) !important;
}
.m-gp-content .card-body [style*="grid-template-columns:repeat(4"] {
    grid-template-columns: repeat(2, 1fr) !important;
}

/* Depth chart view: positions stack vertically */
.m-gp-content [style*="min-width:140px"] { min-width: 0 !important; }
.m-gp-content [style*="min-width:90px"] { min-width: auto !important; }

/* Stats grid: 2 columns instead of auto-fit with wide items */
.m-gp-content [style*="minmax(140px"] {
    grid-template-columns: repeat(2, 1fr) !important;
}

/* Game plan action rows: wrap */
.m-gp-content .gp-lines-actions { flex-wrap: wrap; }

/* Fullscreen whiteboard: safe area */
.m-gp-content #wbCanvasContainer.wb-fullscreen {
    padding-bottom: env(safe-area-inset-bottom, 0);
}

/* Clip player modal: full mobile width */
.m-gp-content #gpPlayerModal .modal-content,
.m-gp-content #gpSessionModal .modal-content,
.m-gp-content #gpPlanModal .modal-content {
    width: 100% !important; max-width: 100% !important;
    border-radius: 16px 16px 0 0;
}

/* Forms: textarea and selects full width */
.m-gp-content .form-input,
.m-gp-content .form-select,
.m-gp-content input[type="text"],
.m-gp-content input[type="number"],
.m-gp-content input[type="date"],
.m-gp-content input[type="datetime-local"],
.m-gp-content select,
.m-gp-content textarea { width: 100%; box-sizing: border-box; font-size: 16px; }

/* Prevent zoom on input focus (iOS) by using 16px font-size above */


/* Upcoming games: touch-friendly row */
.m-gp-content [style*="justify-content:space-between"][style*="border-bottom"] {
    flex-direction: column; align-items: flex-start !important; gap: 10px !important;
}

/* Review sessions: compact card layout */
.m-gp-content .card-body [style*="justify-content:space-between"][style*="flex-wrap:wrap"] {
    flex-direction: column; gap: 10px;
}

/* Long text overflow protection */
.m-gp-content h1, .m-gp-content h2, .m-gp-content h3, .m-gp-content h4 {
    overflow-wrap: break-word; word-break: break-word;
}
.m-gp-content { overflow-x: hidden; }
</style>

<div class="m-gp">
    <!-- Navigation Drawer (mirrors desktop sidebar) -->
    <div class="m-gp-drawer-overlay" id="gpDrawerOverlay" tabindex="0" role="button" aria-label="Close navigation"></div>
    <nav class="m-gp-drawer" id="gpDrawer">
        <a href="?page=gameplan" class="m-gp-drawer-brand">
            <div>
                GAME <span class="hl">PLAN</span>
                <small>Arctic Wolves</small>
            </div>
        </a>

        <div class="m-gp-drawer-label">Navigation</div>
        <a href="?page=gameplan" class="m-gp-drawer-link <?= $gp_sub === 'home' ? 'active' : '' ?>">
            <i class="fas fa-house"></i> Dashboard
        </a>
        <a href="?page=gameplan&gp=video_review" class="m-gp-drawer-link <?= $gp_sub === 'video_review' ? 'active' : '' ?>">
            <i class="fas fa-film"></i> Video Review
        </a>
        <?php if ($isAnyCoach): ?>
        <a href="?page=gameplan&gp=calendar" class="m-gp-drawer-link <?= $gp_sub === 'calendar' ? 'active' : '' ?>">
            <i class="fas fa-calendar"></i> Calendar
        </a>
        <a href="?page=gameplan&gp=game_plan" class="m-gp-drawer-link <?= $gp_sub === 'game_plan' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> Game Plans
        </a>
        <a href="?page=gameplan&gp=whiteboard" class="m-gp-drawer-link <?= $gp_sub === 'whiteboard' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard"></i> Whiteboard
        </a>
        <a href="?page=gameplan&gp=lines" class="m-gp-drawer-link <?= $gp_sub === 'lines' ? 'active' : '' ?>">
            <i class="fas fa-users-line"></i> Game Lines
        </a>
        <a href="?page=gameplan&gp=roster" class="m-gp-drawer-link <?= $gp_sub === 'roster' ? 'active' : '' ?>">
            <i class="fas fa-id-card"></i> Roster
        </a>
        <a href="?page=gameplan&gp=film_room" class="m-gp-drawer-link <?= $gp_sub === 'film_room' ? 'active' : '' ?>">
            <i class="fas fa-video"></i> Film Room
        </a>
        <a href="?page=gameplan&gp=review_sessions" class="m-gp-drawer-link <?= $gp_sub === 'review_sessions' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-user"></i> Review Sessions
        </a>
        <?php else: ?>
        <a href="?page=gameplan&gp=my_clips" class="m-gp-drawer-link <?= $gp_sub === 'my_clips' ? 'active' : '' ?>">
            <i class="fas fa-scissors"></i> My Clips
        </a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="m-gp-drawer-label">Admin</div>
        <a href="?page=gameplan&gp=permissions" class="m-gp-drawer-link <?= $gp_sub === 'permissions' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i> Permissions
        </a>
        <?php endif; ?>

        <div class="m-gp-drawer-footer">
            <a href="?page=pwa_more" class="m-gp-drawer-link">
                <i class="fas fa-arrow-left"></i> Main Menu
            </a>
        </div>
    </nav>

    <!-- Header with back navigation for sub-pages -->
    <div class="m-gp-header">
        <?php if ($gp_is_sub): ?>
        <a href="?page=gameplan" class="m-gp-back"><i class="fas fa-arrow-left"></i></a>
        <?php endif; ?>
        <div class="m-gp-header-info">
            <h2 class="m-gp-title"><i class="fas fa-chess-board"></i> <?= htmlspecialchars($gp_current_label) ?></h2>
            <?php if (!$gp_is_sub): ?>
            <p class="m-gp-sub">Pre-game &amp; post-game planning</p>
            <?php endif; ?>
        </div>
        <button class="m-gp-menu-toggle" id="gpMenuToggle" title="Navigation" aria-label="Open navigation">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <?php if ($gp_sub === 'home'): ?>
    <!-- ── HOME: Stats + Navigation Grid ──────────────────── -->

    <!-- Quick Stats -->
    <?php
    $gp_stats = ['videos' => 0, 'plans' => 0, 'reviews' => 0, 'lines' => 0];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos" . (!$isAnyCoach ? " WHERE athlete_id = ?" : ""));
        $stmt->execute(!$isAnyCoach ? [$user_id] : []);
        $gp_stats['videos'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}
    if ($isAnyCoach) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vr_game_plans WHERE coach_id = ?");
            $stmt->execute([$user_id]);
            $gp_stats['plans'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {}
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vr_review_sessions WHERE coach_id = ? AND status = 'scheduled'");
            $stmt->execute([$user_id]);
            $gp_stats['reviews'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {}
        try {
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT plan_id) FROM vr_game_plan_lines WHERE plan_id IN (SELECT id FROM vr_game_plans WHERE coach_id = ?)");
            $stmt->execute([$user_id]);
            $gp_stats['lines'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {}
    }
    ?>
    <div class="m-gp-stats">
        <div class="m-gp-stat">
            <div class="m-gp-stat-val"><?= $gp_stats['videos'] ?></div>
            <div class="m-gp-stat-lbl">Videos</div>
        </div>
        <?php if ($isAnyCoach): ?>
        <div class="m-gp-stat">
            <div class="m-gp-stat-val"><?= $gp_stats['plans'] ?></div>
            <div class="m-gp-stat-lbl">Plans</div>
        </div>
        <div class="m-gp-stat">
            <div class="m-gp-stat-val"><?= $gp_stats['reviews'] ?></div>
            <div class="m-gp-stat-lbl">Reviews</div>
        </div>
        <div class="m-gp-stat">
            <div class="m-gp-stat-val"><?= $gp_stats['lines'] ?></div>
            <div class="m-gp-stat-lbl">Lines</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Navigation Grid -->
    <div class="m-gp-nav-grid">
        <a href="?page=gameplan&gp=video_review" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(139,92,246,.1); color: var(--primary-light, #8B5CF6);">
                <i class="fas fa-film"></i>
            </div>
            <div class="m-gp-nav-label">Video Review</div>
            <div class="m-gp-nav-count"><?= $gp_stats['videos'] ?> videos</div>
        </a>

        <?php if ($isAnyCoach): ?>
        <a href="?page=gameplan&gp=game_plan" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(59,130,246,.1); color: var(--info, #3B82F6);">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="m-gp-nav-label">Game Plans</div>
            <div class="m-gp-nav-count"><?= $gp_stats['plans'] ?> plans</div>
        </a>

        <a href="?page=gameplan&gp=lines" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(16,185,129,.1); color: var(--success, #10B981);">
                <i class="fas fa-users-line"></i>
            </div>
            <div class="m-gp-nav-label">Game Lines</div>
            <div class="m-gp-nav-count">Line management</div>
        </a>

        <a href="?page=gameplan&gp=film_room" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(139,92,246,.1); color: var(--primary-light, #8B5CF6);">
                <i class="fas fa-video"></i>
            </div>
            <div class="m-gp-nav-label">Film Room</div>
            <div class="m-gp-nav-count">Upload &amp; tag</div>
        </a>

        <a href="?page=gameplan&gp=calendar" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(245,158,11,.1); color: var(--warning, #F59E0B);">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="m-gp-nav-label">Calendar</div>
            <div class="m-gp-nav-count">Schedule</div>
        </a>

        <a href="?page=gameplan&gp=review_sessions" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(245,158,11,.1); color: var(--warning, #F59E0B);">
                <i class="fas fa-chalkboard-user"></i>
            </div>
            <div class="m-gp-nav-label">Reviews</div>
            <div class="m-gp-nav-count"><?= $gp_stats['reviews'] ?> upcoming</div>
        </a>

        <a href="?page=gameplan&gp=whiteboard" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(59,130,246,.1); color: var(--info, #3B82F6);">
                <i class="fas fa-chalkboard"></i>
            </div>
            <div class="m-gp-nav-label">Whiteboard</div>
            <div class="m-gp-nav-count">Draw plays</div>
        </a>

        <a href="?page=gameplan&gp=roster" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(16,185,129,.1); color: var(--success, #10B981);">
                <i class="fas fa-id-card"></i>
            </div>
            <div class="m-gp-nav-label">Roster</div>
            <div class="m-gp-nav-count">Team roster</div>
        </a>
        <?php else: ?>
        <a href="?page=gameplan&gp=my_clips" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(16,185,129,.1); color: var(--success, #10B981);">
                <i class="fas fa-scissors"></i>
            </div>
            <div class="m-gp-nav-label">My Clips</div>
            <div class="m-gp-nav-count">Tagged clips</div>
        </a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <a href="?page=gameplan&gp=permissions" class="m-gp-nav-card">
            <div class="m-gp-nav-icon" style="background: rgba(239,68,68,.1); color: var(--error, #EF4444);">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="m-gp-nav-label">Permissions</div>
            <div class="m-gp-nav-count">Access control</div>
        </a>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ── SUB-PAGE: Render the gp_ view file ─────────────── -->
    <div class="m-gp-content">
        <?php
        if (file_exists($gp_view_file)) {
            include $gp_view_file;
        } else {
            echo '<div style="text-align:center;padding:40px 20px;color:var(--text-muted,#A8A8B8);">';
            echo '<i class="fas fa-exclamation-triangle" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
            echo '<p style="font-size:14px;font-weight:600;">Module not available</p>';
            echo '</div>';
        }
        ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Navigation drawer open/close handlers
(function() {
    var drawer = document.getElementById('gpDrawer');
    var overlay = document.getElementById('gpDrawerOverlay');
    var toggle = document.getElementById('gpMenuToggle');

    function openDrawer() {
        drawer.classList.add('open');
        overlay.classList.add('open');
    }
    function closeDrawer() {
        drawer.classList.remove('open');
        overlay.classList.remove('open');
    }

    if (toggle) toggle.addEventListener('click', openDrawer);
    if (overlay) {
        overlay.addEventListener('click', closeDrawer);
        overlay.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === 'Escape') closeDrawer();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
    });
})();

// Rewrite internal gameplan links and forms to work within PWA context
document.addEventListener('DOMContentLoaded', function() {
    let gpContent = document.querySelector('.m-gp-content');
    if (!gpContent) return;

    // Helper: convert /gameplan.php?page=XXX URL to ?page=gameplan&gp=XXX
    function rewriteGameplanUrl(href) {
        try {
            let url = new URL(href, window.location.origin);
            let gpPage = url.searchParams.get('page');
            if (gpPage) {
                let params = new URLSearchParams();
                params.set('page', 'gameplan');
                params.set('gp', gpPage);
                url.searchParams.forEach(function(val, key) {
                    if (key !== 'page') params.set(key, val);
                });
                return '?' + params.toString();
            }
        } catch (e) { /* skip */ }
        return null;
    }

    // Rewrite /gameplan.php?page=XXX links to ?page=gameplan&gp=XXX
    let links = gpContent.querySelectorAll('a[href*="/gameplan.php"]');
    for (let i = 0; i < links.length; i++) {
        let href = links[i].getAttribute('href');
        if (!href) continue;
        let newHref = rewriteGameplanUrl(href);
        if (newHref) links[i].setAttribute('href', newHref);
    }

    // Rewrite /dashboard.php links to pwa.php equivalents
    let dashLinks = gpContent.querySelectorAll('a[href*="/dashboard.php"]');
    for (let j = 0; j < dashLinks.length; j++) {
        let dhref = dashLinks[j].getAttribute('href');
        if (!dhref) continue;
        try {
            let dUrl = new URL(dhref, window.location.origin);
            let dPage = dUrl.searchParams.get('page');
            if (dPage) {
                dashLinks[j].setAttribute('href', '?page=' + encodeURIComponent(dPage));
            }
        } catch (e) { /* skip */ }
    }

    // Fix GET forms: add hidden fields so form submissions stay in PWA gameplan context
    // Forms with action="" submit to current page. We need page=gameplan&gp=<sub-page>
    let forms = gpContent.querySelectorAll('form[method="GET"], form:not([method])');
    for (let k = 0; k < forms.length; k++) {
        let form = forms[k];
        // Only handle forms with empty or no action (submit to current page)
        let action = form.getAttribute('action');
        if (action && action !== '#') continue;

        // Check if the form has a hidden "page" input that maps to a gp sub-page
        let pageInput = form.querySelector('input[name="page"]');
        if (pageInput) {
            // This form's "page" value is the gp sub-page (e.g., "video_review")
            // We need page=gameplan and gp=<sub-page> instead
            let gpSubPage = pageInput.value;
            pageInput.value = 'gameplan';
            // Add the gp hidden field
            let gpInput = document.createElement('input');
            gpInput.type = 'hidden';
            gpInput.name = 'gp';
            gpInput.value = gpSubPage;
            form.insertBefore(gpInput, pageInput.nextSibling);
        }
    }

    // Rewrite inline event handlers (onchange, onclick, onsubmit) that reference /gameplan.php
    ['onchange', 'onclick', 'onsubmit'].forEach(function(attr) {
        var els = gpContent.querySelectorAll('[' + attr + ']');
        for (let m = 0; m < els.length; m++) {
            let val = els[m].getAttribute(attr);
            if (val && val.indexOf('/gameplan.php') !== -1) {
                let newVal = val.replace(/\/gameplan\.php\?page=([a-z0-9_]+)/g, function(match, gpPage) {
                    return '?page=gameplan&gp=' + gpPage;
                });
                els[m].setAttribute(attr, newVal);
            }
        }
    });

    // Rewrite form action attributes that point to /gameplan.php
    var gpForms = gpContent.querySelectorAll('form[action*="/gameplan.php"]');
    for (let n = 0; n < gpForms.length; n++) {
        let action = gpForms[n].getAttribute('action');
        let newAction = rewriteGameplanUrl(action);
        if (newAction) gpForms[n].setAttribute('action', newAction);
    }
});
</script>

<?php if ($gp_pwa_active_pair_id > 0): ?>
<!-- ── Global Telestration Overlay (PWA gameplan) ──────────── -->
<canvas id="gpTeleCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:9000;pointer-events:none;"></canvas>

<div id="gpTeleControls" style="position:fixed;bottom:80px;right:16px;z-index:9010;display:flex;align-items:flex-end;gap:8px;flex-direction:column;">
    <div id="gpTeleToolbar" style="display:none;background:var(--bg-card,#16161F);border:1px solid var(--border,#2D2D3F);border-radius:14px;padding:10px 14px;box-shadow:0 8px 32px rgba(0,0,0,.5);backdrop-filter:blur(12px);">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <div style="display:flex;gap:4px;">
                <button class="gp-tele-tool active" data-tool="freehand" title="Freehand"><i class="fas fa-pencil"></i></button>
                <button class="gp-tele-tool" data-tool="line" title="Line"><i class="fas fa-minus"></i></button>
                <button class="gp-tele-tool" data-tool="arrow" title="Arrow"><i class="fas fa-arrow-right"></i></button>
            </div>
            <div style="width:1px;height:24px;background:var(--border,#2D2D3F);"></div>
            <div style="display:flex;gap:4px;">
                <button class="gp-tele-color active" data-color="#EF4444" style="background:#EF4444;"></button>
                <button class="gp-tele-color" data-color="#3B82F6" style="background:#3B82F6;"></button>
                <button class="gp-tele-color" data-color="#10B981" style="background:#10B981;"></button>
                <button class="gp-tele-color" data-color="#F59E0B" style="background:#F59E0B;"></button>
                <button class="gp-tele-color" data-color="#FFFFFF" style="background:#FFFFFF;"></button>
            </div>
            <div style="width:1px;height:24px;background:var(--border,#2D2D3F);"></div>
            <input type="range" id="gpTeleWidth" min="1" max="8" value="3" style="width:60px;accent-color:var(--primary,#6B46C1);" title="Line width">
            <button class="gp-tele-tool" id="gpTeleClear" title="Clear"><i class="fas fa-eraser"></i></button>
        </div>
    </div>
    <button id="gpTeleDrawBtn" title="Toggle telestration drawing" style="width:56px;height:56px;border-radius:50%;border:2px solid var(--primary,#6B46C1);background:var(--bg-card,#16161F);color:var(--primary-light,#8B5CF6);font-size:20px;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;transition:all .2s;">
        <i class="fas fa-pencil"></i>
    </button>
</div>

<style>
.gp-tele-tool { width:32px;height:32px;border-radius:8px;border:1px solid var(--border,#2D2D3F);background:transparent;color:var(--text-secondary,#ccc);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s;padding:0; }
.gp-tele-tool:hover { background:rgba(107,70,193,.15); color:#fff; }
.gp-tele-tool.active { background:var(--primary,#6B46C1);color:#fff;border-color:var(--primary,#6B46C1); }
.gp-tele-color { width:24px;height:24px;border-radius:50%;border:2px solid transparent;cursor:pointer;padding:0;transition:border-color .15s; }
.gp-tele-color.active { border-color:#fff; }
#gpTeleDrawBtn.active { background:var(--primary,#6B46C1);color:#fff;border-color:var(--primary,#6B46C1);box-shadow:0 0 0 4px rgba(107,70,193,.3),0 4px 20px rgba(0,0,0,.4); }
</style>

<script>
(function() {
    var canvas = document.getElementById('gpTeleCanvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var pairId = <?= (int)$gp_pwa_active_pair_id ?>;
    var toolbar = document.getElementById('gpTeleToolbar');
    var drawBtn = document.getElementById('gpTeleDrawBtn');
    var drawing = false, isDrawing = false;
    var tool = 'freehand', color = '#EF4444', lineWidth = 3;
    var startX, startY, snapshot = null;

    function resizeCanvas() {
        var w = window.innerWidth, h = window.innerHeight;
        if (canvas.width !== w || canvas.height !== h) {
            var temp = document.createElement('canvas');
            temp.width = canvas.width; temp.height = canvas.height;
            temp.getContext('2d').drawImage(canvas, 0, 0);
            canvas.width = w; canvas.height = h;
            ctx.drawImage(temp, 0, 0, temp.width, temp.height, 0, 0, w, h);
        }
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    drawBtn.addEventListener('click', function() {
        drawing = !drawing;
        drawBtn.classList.toggle('active', drawing);
        toolbar.style.display = drawing ? 'block' : 'none';
        canvas.style.pointerEvents = drawing ? 'auto' : 'none';
        canvas.style.cursor = drawing ? 'crosshair' : 'default';
    });

    function getPos(e) { var t = e.touches ? e.touches[0] : (e.changedTouches ? e.changedTouches[0] : e); return { x: t.clientX, y: t.clientY }; }
    function onStart(e) {
        if (!drawing) return; e.preventDefault(); isDrawing = true;
        var pos = getPos(e); startX = pos.x; startY = pos.y;
        if (tool === 'freehand') { ctx.beginPath(); ctx.moveTo(pos.x, pos.y); ctx.strokeStyle = color; ctx.lineWidth = lineWidth; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; }
        else { snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height); }
    }
    function onMove(e) {
        if (!isDrawing) return; e.preventDefault(); var pos = getPos(e);
        if (tool === 'freehand') { ctx.lineTo(pos.x, pos.y); ctx.stroke(); }
        else if (snapshot) { ctx.putImageData(snapshot, 0, 0); drawStraight(ctx, startX, startY, pos.x, pos.y, tool, color, lineWidth); }
    }
    function onEnd(e) {
        if (!isDrawing) return; isDrawing = false;
        if (tool !== 'freehand' && snapshot) { ctx.putImageData(snapshot, 0, 0); var pos = getPos(e); drawStraight(ctx, startX, startY, pos.x, pos.y, tool, color, lineWidth); }
        snapshot = null; broadcastTelestration();
    }
    function drawStraight(ctx, x1, y1, x2, y2, t, c, w) {
        ctx.strokeStyle = c; ctx.lineWidth = w; ctx.lineCap = 'round'; ctx.setLineDash([]);
        ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
        if (t === 'arrow') { var angle = Math.atan2(y2 - y1, x2 - x1), headLen = w * 5; ctx.fillStyle = c; ctx.beginPath(); ctx.moveTo(x2, y2); ctx.lineTo(x2 - headLen * Math.cos(angle - Math.PI / 6), y2 - headLen * Math.sin(angle - Math.PI / 6)); ctx.lineTo(x2 - headLen * Math.cos(angle + Math.PI / 6), y2 - headLen * Math.sin(angle + Math.PI / 6)); ctx.closePath(); ctx.fill(); }
    }
    canvas.addEventListener('mousedown', onStart); canvas.addEventListener('mousemove', onMove);
    canvas.addEventListener('mouseup', onEnd); canvas.addEventListener('mouseleave', onEnd);
    canvas.addEventListener('touchstart', onStart, { passive: false }); canvas.addEventListener('touchmove', onMove, { passive: false }); canvas.addEventListener('touchend', onEnd);

    document.querySelectorAll('.gp-tele-tool[data-tool]').forEach(function(btn) { btn.addEventListener('click', function() { document.querySelectorAll('.gp-tele-tool[data-tool]').forEach(function(b) { b.classList.remove('active'); }); btn.classList.add('active'); tool = btn.dataset.tool; }); });
    document.querySelectorAll('.gp-tele-color').forEach(function(btn) { btn.addEventListener('click', function() { document.querySelectorAll('.gp-tele-color').forEach(function(b) { b.classList.remove('active'); }); btn.classList.add('active'); color = btn.dataset.color; }); });
    var widthInput = document.getElementById('gpTeleWidth'); if (widthInput) widthInput.addEventListener('input', function() { lineWidth = parseInt(this.value) || 3; });
    var clearBtn = document.getElementById('gpTeleClear'); if (clearBtn) clearBtn.addEventListener('click', function() { ctx.clearRect(0, 0, canvas.width, canvas.height); broadcastTelestration(); });

    var broadcastTimer = null;
    function broadcastTelestration() {
        if (!pairId) return;
        if (broadcastTimer) clearTimeout(broadcastTimer);
        broadcastTimer = setTimeout(function() {
            var dataUrl = canvas.toDataURL('image/png');
            var csrf = document.querySelector('input[name="csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
            var token = csrf ? (csrf.value || csrf.content || '') : '';
            if (!token) return;
            fetch('/process_video.php', {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=broadcast_telestration&pair_id=' + pairId + '&canvas_data=' + encodeURIComponent(dataUrl) + '&csrf_token=' + encodeURIComponent(token)
            }).catch(function() {});
        }, 500);
    }
})();
</script>
<?php endif; ?>
