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
} catch (PDOException $e) { /* ignore */ }

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
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.6); z-index: 200;
}
.m-gp-drawer-overlay.open { display: block; }
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
</style>

<div class="m-gp">
    <!-- Navigation Drawer (mirrors desktop sidebar) -->
    <div class="m-gp-drawer-overlay" id="gpDrawerOverlay" onclick="document.getElementById('gpDrawer').classList.remove('open');this.classList.remove('open');"></div>
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
            <i class="fas fa-clipboard-list"></i> Roster
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
        <button class="m-gp-menu-toggle" onclick="document.getElementById('gpDrawer').classList.add('open');document.getElementById('gpDrawerOverlay').classList.add('open');" title="Navigation">
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
                <i class="fas fa-clipboard-list"></i>
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

    // Rewrite inline onchange handlers that navigate using location.href
    let selects = gpContent.querySelectorAll('select[onchange]');
    for (let m = 0; m < selects.length; m++) {
        let oc = selects[m].getAttribute('onchange');
        if (oc && oc.indexOf('/gameplan.php') !== -1) {
            // Replace /gameplan.php?page=XXX with pwa-compatible URL
            let newOc = oc.replace(/\/gameplan\.php\?page=([a-z0-9_]+)/g, function(match, gpPage) {
                return '?page=gameplan&gp=' + gpPage;
            });
            selects[m].setAttribute('onchange', newOc);
        }
    }
});
</script>
