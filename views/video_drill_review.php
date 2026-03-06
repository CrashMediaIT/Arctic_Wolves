<?php
/**
 * Athlete Drill Video Library
 * Two views:
 * 1. Browse drills by skill type with filtering (drill name, coach)
 * 2. View session videos in list or calendar view
 */
require_once __DIR__ . '/../lib/image_helper.php';

// Get active view (default: drills)
$active_view = $_GET['view'] ?? 'drills';

// Get filter parameters for drills view
$filter_skill_type = $_GET['skill_type'] ?? 'all';
$filter_coach = $_GET['coach'] ?? 'all';
$search_drill = $_GET['search_drill'] ?? '';

// Get filter parameters for sessions view  
$session_view_mode = $_GET['session_mode'] ?? 'list';
$filter_month = $_GET['month'] ?? date('Y-m');

// Fetch all drill categories (skill types)
$skill_types_stmt = $pdo->prepare("SELECT id, name FROM drill_categories ORDER BY name");
$skill_types_stmt->execute();
$skill_types = $skill_types_stmt->fetchAll();

// Fetch coaches who have uploaded videos for this athlete
$coaches_stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name as coach_first_name, u.last_name as coach_last_name
    FROM users u
    INNER JOIN videos v ON v.coach_id = u.id
    WHERE v.athlete_id = ?
    ORDER BY u.last_name, u.first_name
");
$coaches_stmt->execute([$user_id]);
$coaches = $coaches_stmt->fetchAll();
$coaches = decryptUserRows($coaches);

// ========================================
// VIEW 1: Drills by Skill Type
// ========================================
$drills_query = "
    SELECT v.*, 
           u.first_name as coach_first_name, u.last_name as coach_last_name,
           s.session_date,
           st.name as session_type_name,
           d.title as drill_name,
           dc.id as category_id,
           dc.name as skill_type
    FROM videos v
    LEFT JOIN users u ON v.coach_id = u.id
    LEFT JOIN sessions s ON v.session_id = s.id
    LEFT JOIN session_types st ON s.session_type_id = st.id
    LEFT JOIN drills d ON v.drill_id = d.id
    LEFT JOIN drill_categories dc ON d.category_id = dc.id
    WHERE v.athlete_id = ? AND v.video_type != 'uploaded_by_athlete'
";

$drills_params = [$user_id];

if ($filter_skill_type !== 'all') {
    $drills_query .= " AND dc.id = ?";
    $drills_params[] = $filter_skill_type;
}

if ($filter_coach !== 'all') {
    $drills_query .= " AND v.coach_id = ?";
    $drills_params[] = $filter_coach;
}

if (!empty($search_drill)) {
    $drills_query .= " AND (v.title LIKE ? OR d.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $drills_params[] = "%$search_drill%";
    $drills_params[] = "%$search_drill%";
    $drills_params[] = "%$search_drill%";
    $drills_params[] = "%$search_drill%";
}

$drills_query .= " ORDER BY dc.name, v.upload_date DESC";

$drills_stmt = $pdo->prepare($drills_query);
$drills_stmt->execute($drills_params);
$drill_videos = $drills_stmt->fetchAll();
$drill_videos = decryptUserRows($drill_videos);

// Group videos by skill type
$videos_by_skill = [];
foreach ($drill_videos as $video) {
    $skill = $video['skill_type'] ?? 'Uncategorized';
    if (!isset($videos_by_skill[$skill])) {
        $videos_by_skill[$skill] = [];
    }
    $videos_by_skill[$skill][] = $video;
}

// ========================================
// VIEW 2: Session Videos (List/Calendar)
// ========================================
$sessions_query = "
    SELECT DISTINCT s.id, s.title, s.session_date, s.duration_minutes,
           u.first_name as coach_first_name, u.last_name as coach_last_name,
           st.name as session_type_name,
           COUNT(v.id) as video_count
    FROM sessions s
    INNER JOIN session_attendance sa ON sa.session_id = s.id
    LEFT JOIN users u ON s.coach_id = u.id
    LEFT JOIN session_types st ON s.session_type_id = st.id
    LEFT JOIN videos v ON v.session_id = s.id AND v.athlete_id = ?
    WHERE sa.user_id = ? AND sa.attendance_status = 'present'
";

$session_params = [$user_id, $user_id];

// Filter by month for calendar view
if ($session_view_mode === 'calendar' && !empty($filter_month)) {
    $sessions_query .= " AND DATE_FORMAT(s.session_date, '%Y-%m') = ?";
    $session_params[] = $filter_month;
}

$sessions_query .= " GROUP BY s.id ORDER BY s.session_date DESC";

$sessions_stmt = $pdo->prepare($sessions_query);
$sessions_stmt->execute($session_params);
$attended_sessions = $sessions_stmt->fetchAll();
$attended_sessions = decryptUserRows($attended_sessions);

// Build calendar data if in calendar mode
$calendar_data = [];
if ($session_view_mode === 'calendar') {
    foreach ($attended_sessions as $session) {
        $date = date('Y-m-d', strtotime($session['session_date']));
        if (!isset($calendar_data[$date])) {
            $calendar_data[$date] = [];
        }
        $calendar_data[$date][] = $session;
    }
}

// No demo data - show empty state when no real data exists
$is_demo_data = false;
?>

<!-- Athlete Video Library -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-video"></i> My Session Videos
    </h1>
    <p class="page-description">Watch videos captured from your training sessions</p>
</div>

<?php if ($is_demo_data): ?>
<div class="demo-data-notice">
    <i class="fas fa-info-circle"></i>
    <span>Showing demo videos. Your actual session videos will appear here once recorded.</span>
</div>
<?php endif; ?>

<!-- View Toggle Tabs -->
<div class="view-toggle-container">
    <div class="view-toggle">
        <a href="?page=drill_review&view=drills" class="view-toggle-btn <?= $active_view === 'drills' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> Browse by Skill Type
        </a>
        <a href="?page=drill_review&view=sessions" class="view-toggle-btn <?= $active_view === 'sessions' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> My Sessions
        </a>
        <a href="?page=drill_review&view=ingest" class="view-toggle-btn <?= $active_view === 'ingest' ? 'active' : '' ?>">
            <i class="fas fa-hard-drive"></i> Ingest Device
        </a>
    </div>
</div>

<?php if ($active_view === 'drills'): ?>
<!-- ========================================
     VIEW 1: DRILLS BY SKILL TYPE
     ======================================== -->
<div class="video-content">
    <!-- Filter Bar -->
    <div class="filter-bar filter-box">
        <form method="GET" action="" class="filter-form">
            <input type="hidden" name="page" value="drill_review">
            <input type="hidden" name="view" value="drills">
            
            <div class="filter-group">
                <label class="filter-label">Skill Type</label>
                <select name="skill_type" class="form-input-small" onchange="this.form.submit()">
                    <option value="all">All Skill Types</option>
                    <?php foreach ($skill_types as $type): ?>
                        <option value="<?= $type['id'] ?>" <?= $filter_skill_type == $type['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Coach</label>
                <select name="coach" class="form-input-small" onchange="this.form.submit()">
                    <option value="all">All Coaches</option>
                    <?php foreach ($coaches as $coach): ?>
                        <option value="<?= $coach['id'] ?>" <?= $filter_coach == $coach['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim(($coach['coach_first_name'] ?? '') . ' ' . ($coach['coach_last_name'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group search-group">
                <label class="filter-label">Search</label>
                <div class="search-input-wrapper">
                    <input type="text" name="search_drill" class="form-input-small" 
                           placeholder="Search drill name or coach..." 
                           value="<?= htmlspecialchars($search_drill) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Drill Videos by Skill Type -->
    <div class="skill-sections">
        <?php if (count($videos_by_skill) > 0): ?>
            <?php foreach ($videos_by_skill as $skill_name => $videos): ?>
            <div class="skill-section">
                <h3 class="skill-header">
                    <i class="fas fa-layer-group"></i> 
                    <?= htmlspecialchars($skill_name) ?>
                    <span class="skill-count">(<?= count($videos) ?> videos)</span>
                </h3>
                <div class="video-grid">
                    <?php foreach ($videos as $video): ?>
                    <div class="video-card" data-component="VideoCard" data-video-id="<?= htmlspecialchars($video['id']) ?>">
                        <div class="video-thumbnail">
                            <?php if (!empty($video['thumbnail_url'])): ?>
                                <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url']) ?? '') ?>" alt="Video thumbnail">
                            <?php else: ?>
                                <div class="video-placeholder">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($video['duration'])): ?>
                                <span class="video-duration"><?= htmlspecialchars($video['duration']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="video-info">
                            <h4 class="video-title"><?= htmlspecialchars($video['drill_name'] ?? $video['title'] ?? 'Untitled') ?></h4>
                            <?php if (!empty($video['description']) && empty($video['drill_name'])): ?>
                                <p class="video-description"><?= htmlspecialchars(mb_strimwidth($video['description'], 0, 100, '...')) ?></p>
                            <?php endif; ?>
                            <div class="video-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
                                <?php $video_coach_name = trim(($video['coach_first_name'] ?? '') . ' ' . ($video['coach_last_name'] ?? '')); ?>
                                <?php if (!empty($video_coach_name)): ?>
                                    <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($video_coach_name) ?></span>
                                <?php endif; ?>
                                <?php if (($video['video_type'] ?? '') === 'uploaded_by_athlete'): ?>
                                    <span class="badge-athlete-upload"><i class="fas fa-upload"></i> My Upload</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="video-actions">
                            <?php
                                $dr_video_url = resolveRustfsUrl($pdo, getPreferredVideoUrl($video)) ?? '';
                                $dr_hls_url = '';
                                if (preg_match('/\.m3u8(\?|&|$)/i', $dr_video_url)) {
                                    $orig = resolveRustfsUrl($pdo, $video['video_url'] ?? $video['file_path'] ?? '') ?? '';
                                    if ($orig && $orig !== $dr_video_url) $dr_hls_url = $orig;
                                } else {
                                    if (!empty($video['hls_url'])) {
                                        $hls = resolveRustfsUrl($pdo, $video['hls_url']) ?? '';
                                        if ($hls && $hls !== $dr_video_url) $dr_hls_url = $hls;
                                    }
                                    if (empty($dr_hls_url)) $dr_hls_url = deriveFallbackUrl($dr_video_url);
                                }
                            ?>
                            <button class="btn-primary btn-full" data-action="play-video" 
                                    data-video-id="<?= htmlspecialchars($video['id']) ?>"
                                    data-video-url="<?= htmlspecialchars($dr_video_url) ?>"
                                    data-fallback-url="<?= htmlspecialchars($dr_hls_url) ?>"
                                    data-thumbnail-url="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url'] ?? '') ?? '') ?>"
                                    data-video-description="<?= htmlspecialchars($video['description'] ?? '') ?>"
                                    data-video-coach="<?= htmlspecialchars(trim(($video['coach_first_name'] ?? '') . ' ' . ($video['coach_last_name'] ?? ''))) ?>"
                                    data-video-date="<?= htmlspecialchars(date('M d, Y', strtotime($video['upload_date']))) ?>">
                                <i class="fas fa-play"></i> Watch Video
                            </button>
                            <?php if (($video['video_type'] ?? '') === 'uploaded_by_athlete' && (int)($video['athlete_id'] ?? 0) === (int)$user_id): ?>
                            <button class="btn-danger btn-sm btn-delete-video" data-action="delete-video"
                                    data-video-id="<?= htmlspecialchars($video['id']) ?>"
                                    data-video-title="<?= htmlspecialchars($video['drill_name'] ?? $video['title'] ?? 'this video') ?>">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-video placeholder-icon"></i>
                <p class="placeholder-text">No drill videos found matching your filters.</p>
                <?php if ($filter_skill_type !== 'all' || $filter_coach !== 'all' || !empty($search_drill)): ?>
                    <a href="?page=drill_review&view=drills" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($active_view === 'sessions'): ?>
<!-- ========================================
     VIEW 2: SESSION VIDEOS
     ======================================== -->
<div class="video-content">
    <!-- View Mode Toggle -->
    <div class="session-view-controls">
        <div class="view-mode-toggle">
            <a href="?page=drill_review&view=sessions&session_mode=list" 
               class="mode-btn <?= $session_view_mode === 'list' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> List View
            </a>
            <a href="?page=drill_review&view=sessions&session_mode=calendar" 
               class="mode-btn <?= $session_view_mode === 'calendar' ? 'active' : '' ?>">
                <i class="fas fa-calendar"></i> Calendar View
            </a>
        </div>
        
        <?php if ($session_view_mode === 'calendar'): ?>
        <div class="month-navigation">
            <?php 
            $current_month = new DateTime($filter_month . '-01');
            $prev_month = (clone $current_month)->modify('-1 month')->format('Y-m');
            $next_month = (clone $current_month)->modify('+1 month')->format('Y-m');
            ?>
            <a href="?page=drill_review&view=sessions&session_mode=calendar&month=<?= $prev_month ?>" class="month-nav-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <span class="current-month"><?= $current_month->format('F Y') ?></span>
            <a href="?page=drill_review&view=sessions&session_mode=calendar&month=<?= $next_month ?>" class="month-nav-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($session_view_mode === 'list'): ?>
    <!-- List View -->
    <div class="sessions-list">
        <?php if (count($attended_sessions) > 0): ?>
            <?php foreach ($attended_sessions as $session): ?>
            <div class="session-card" data-session-id="<?= htmlspecialchars($session['id']) ?>">
                <div class="session-date-badge">
                    <span class="date-day"><?= date('d', strtotime($session['session_date'])) ?></span>
                    <span class="date-month"><?= date('M', strtotime($session['session_date'])) ?></span>
                    <span class="date-year"><?= date('Y', strtotime($session['session_date'])) ?></span>
                </div>
                <div class="session-info">
                    <h4 class="session-title"><?= htmlspecialchars($session['title']) ?></h4>
                    <div class="session-meta">
                        <?php if (!empty($session['session_type_name'])): ?>
                            <span class="session-type"><i class="fas fa-tag"></i> <?= htmlspecialchars($session['session_type_name']) ?></span>
                        <?php endif; ?>
                        <?php $session_coach_name = trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')); ?>
                        <?php if (!empty($session_coach_name)): ?>
                            <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($session_coach_name) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-clock"></i> <?= $session['duration_minutes'] ?> min</span>
                    </div>
                </div>
                <div class="session-videos-count">
                    <span class="video-count-badge">
                        <i class="fas fa-video"></i>
                        <?= $session['video_count'] ?> video<?= $session['video_count'] != 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="session-actions">
                    <button class="btn btn-primary btn-sm" data-action="view-session-videos" 
                            data-session-id="<?= htmlspecialchars($session['id']) ?>">
                        <i class="fas fa-play"></i> View Videos
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-calendar-times placeholder-icon"></i>
                <p class="placeholder-text">No attended sessions with recorded videos yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- Calendar View -->
    <div class="calendar-container">
        <?php
        $month_start = new DateTime($filter_month . '-01');
        $month_end = (clone $month_start)->modify('last day of this month');
        $start_dow = (int)$month_start->format('w'); // 0 = Sunday
        $days_in_month = (int)$month_end->format('d');
        ?>
        
        <div class="calendar-header">
            <div class="calendar-day-name">Sun</div>
            <div class="calendar-day-name">Mon</div>
            <div class="calendar-day-name">Tue</div>
            <div class="calendar-day-name">Wed</div>
            <div class="calendar-day-name">Thu</div>
            <div class="calendar-day-name">Fri</div>
            <div class="calendar-day-name">Sat</div>
        </div>
        
        <div class="calendar-grid">
            <?php
            // Empty cells before month start
            for ($i = 0; $i < $start_dow; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor;
            
            // Days of the month
            for ($day = 1; $day <= $days_in_month; $day++):
                $current_date = $filter_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                $has_sessions = isset($calendar_data[$current_date]);
                $is_today = $current_date === date('Y-m-d');
            ?>
                <div class="calendar-day <?= $has_sessions ? 'has-sessions' : '' ?> <?= $is_today ? 'today' : '' ?>">
                    <span class="day-number"><?= $day ?></span>
                    <?php if ($has_sessions): ?>
                        <div class="day-sessions">
                            <?php foreach ($calendar_data[$current_date] as $session): ?>
                                <div class="calendar-session" title="<?= htmlspecialchars($session['title']) ?>">
                                    <span class="session-dot"></span>
                                    <?= htmlspecialchars(substr($session['title'], 0, 15)) ?>
                                    <?= strlen($session['title']) > 15 ? '...' : '' ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor;
            
            // Empty cells after month end
            $remaining = (7 - (($start_dow + $days_in_month) % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($active_view === 'ingest'): ?>
<!-- ========================================
     VIEW 3: INGEST FROM DEVICE
     ======================================== -->
<div class="video-content">
    <div class="ingest-card">
        <h3><i class="fas fa-hard-drive"></i> Ingest Videos from Device</h3>
        <p class="ingest-description">Import videos recorded offline from an SD card, USB drive, or other external storage device. Videos are automatically assigned to the correct area based on the recording metadata.</p>

        <div id="athleteIngestFsaNotSupported" style="display:none;">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>Your browser does not support the File System Access API. Please use Chrome, Edge, or Opera to ingest videos from external devices.</span>
            </div>
        </div>

        <div id="athleteIngestFsaSupported">
            <div class="ingest-step" id="athleteIngestStep1">
                <div class="ingest-step-number">1</div>
                <div class="ingest-step-content">
                    <h4>Select Device / Folder</h4>
                    <p>Connect your SD card or external drive, then select the folder containing the recordings.</p>
                    <button type="button" class="btn btn-primary" id="athleteIngestSelectDirBtn">
                        <i class="fas fa-folder-open"></i> Select Recording Folder
                    </button>
                </div>
            </div>

            <div class="ingest-step" id="athleteIngestStep2" style="display:none;">
                <div class="ingest-step-number">2</div>
                <div class="ingest-step-content">
                    <h4>Review Discovered Videos</h4>
                    <p id="athleteIngestScanStatus">Scanning for videos…</p>
                    <div class="ingest-video-list" id="athleteIngestVideoList"></div>
                    <div class="ingest-actions" id="athleteIngestActions" style="display:none;">
                        <label class="ingest-checkbox-label">
                            <input type="checkbox" id="athleteIngestDeleteAfter" checked>
                            <span>Remove files from device after import</span>
                        </label>
                        <div class="ingest-action-buttons">
                            <button type="button" class="btn btn-secondary" id="athleteIngestCancelBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-primary" id="athleteIngestStartBtn">
                                <i class="fas fa-download"></i> Import &amp; Upload All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ingest-step" id="athleteIngestStep3" style="display:none;">
                <div class="ingest-step-number">3</div>
                <div class="ingest-step-content">
                    <h4>Import &amp; Upload Progress</h4>
                    <div class="ingest-progress-header">
                        <span id="athleteIngestProgressTitle">Importing…</span>
                        <span id="athleteIngestProgressCount">0 / 0</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="athleteIngestProgressFill"></div>
                    </div>
                    <p id="athleteIngestProgressStatus" class="ingest-progress-status"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Video Player Modal -->
<div class="video-modal" id="videoPlayerModal" style="display: none;">
    <div class="modal-overlay" data-action="close-modal"></div>
    <div class="modal-content video-player-modal">
        <div class="modal-header">
            <h3 id="videoModalTitle"><i class="fas fa-play-circle"></i> Video Player</h3>
            <button class="modal-close" aria-label="Close modal" data-action="close-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="video-player-container">
                <video id="videoPlayer" controls preload="none" class="video-player">
                    <source src="" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                <div class="video-player-placeholder" id="videoPlaceholder">
                    <i class="fas fa-play-circle"></i>
                    <p>Select a video to play</p>
                </div>
            </div>
            <div class="video-details-section" id="videoDetails">
                <h4 id="videoDetailTitle">Video Details</h4>
                <div class="video-detail-meta" id="videoDetailMeta"></div>
            </div>
        </div>
    </div>
</div>

<style>
/* =========================================================
   ATHLETE VIDEO LIBRARY - Modern Design
   ========================================================= */

/* View Toggle */
.view-toggle-container {
    margin-bottom: 24px;
}

.view-toggle {
    display: inline-flex;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 4px;
    gap: 4px;
}

.view-toggle-btn {
    padding: 12px 24px;
    border-radius: 8px;
    color: var(--text-dim);
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
}

.view-toggle-btn:hover {
    color: var(--text-white);
    background: rgba(107, 70, 193, 0.1);
}

.view-toggle-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6));
    color: white;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

/* Filter Bar */
.filter-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.filter-form {
    display: flex;
    align-items: flex-end;
    gap: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-dim);
}

.form-input-small {
    min-width: 160px;
    height: 42px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-white);
    font-size: 14px;
    padding: 0 12px;
    transition: all 0.25s ease;
}

.form-input-small:hover {
    border-color: var(--primary);
}

.form-input-small:focus {
    border-color: var(--accent, #8B5CF6);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
    outline: none;
}

.search-group {
    flex: 1;
    min-width: 250px;
}

.search-input-wrapper {
    display: flex;
    gap: 8px;
}

.search-input-wrapper .form-input-small {
    flex: 1;
}

.btn-sm {
    height: 42px;
    padding: 0 16px;
}

/* Skill Sections */
.skill-sections {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.skill-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
}

.skill-header {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}

.skill-header i {
    color: var(--primary);
}

.skill-count {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-dim);
    margin-left: auto;
}

/* Video Grid */
.video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.video-card {
    background: linear-gradient(135deg, var(--bg-main) 0%, rgba(22, 22, 31, 0.8) 100%);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.video-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(107, 70, 193, 0.15);
    border-color: var(--primary);
}

.video-thumbnail {
    position: relative;
    width: 100%;
    padding-top: 56.25%;
    background: var(--bg-main);
    overflow: hidden;
}

.video-thumbnail img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.1), rgba(139, 92, 246, 0.05));
}

.video-placeholder i {
    font-size: 48px;
    color: var(--primary);
    opacity: 0.4;
    transition: all 0.3s ease;
}

.video-card:hover .video-placeholder i {
    opacity: 0.7;
    transform: scale(1.1);
}

.video-duration {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.85);
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.video-info {
    padding: 16px;
}

.video-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
    line-height: 1.4;
}

.video-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.video-meta span {
    font-size: 12px;
    color: var(--text-dim);
    display: flex;
    align-items: center;
    gap: 5px;
}

.video-meta i {
    color: var(--primary);
    font-size: 11px;
}

.video-actions {
    padding: 16px;
    border-top: 1px solid var(--border);
}

.btn-full {
    width: 100%;
    justify-content: center;
}

/* Session View Controls */
.session-view-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.view-mode-toggle {
    display: flex;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 4px;
    gap: 4px;
}

.mode-btn {
    padding: 10px 20px;
    border-radius: 6px;
    color: var(--text-dim);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.mode-btn:hover {
    color: var(--text-white);
}

.mode-btn.active {
    background: var(--primary);
    color: white;
}

.month-navigation {
    display: flex;
    align-items: center;
    gap: 16px;
}

.month-nav-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-dim);
    text-decoration: none;
    transition: all 0.2s ease;
}

.month-nav-btn:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.current-month {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    min-width: 150px;
    text-align: center;
}

/* Session List */
.sessions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.session-card {
    display: grid;
    grid-template-columns: 80px 1fr auto auto;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.session-card:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 30px rgba(107, 70, 193, 0.15);
}

.session-date-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6));
    border-radius: 12px;
    padding: 12px 8px;
    color: white;
    text-align: center;
}

.date-day {
    font-size: 24px;
    font-weight: 900;
    line-height: 1;
}

.date-month {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 2px;
}

.date-year {
    font-size: 10px;
    opacity: 0.8;
}

.session-info {
    flex: 1;
    min-width: 0;
}

.session-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.session-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.session-meta span {
    font-size: 13px;
    color: var(--text-dim);
    display: flex;
    align-items: center;
    gap: 6px;
}

.session-meta i {
    color: var(--primary);
    font-size: 12px;
}

.session-type {
    background: rgba(107, 70, 193, 0.15);
    padding: 4px 10px;
    border-radius: 20px;
    color: var(--primary-light, #8B5CF6) !important;
}

.video-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(16, 185, 129, 0.15);
    color: #10B981;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.video-count-badge i {
    color: #10B981;
}

/* Calendar View */
.calendar-container {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    overflow: hidden;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 8px;
}

.calendar-day-name {
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    padding: 12px 0;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-day {
    min-height: 100px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px;
    position: relative;
}

.calendar-day.empty {
    background: transparent;
    border-color: transparent;
}

.calendar-day.today {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.calendar-day.has-sessions {
    background: rgba(16, 185, 129, 0.05);
    border-color: rgba(16, 185, 129, 0.3);
}

.day-number {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
}

.calendar-day.today .day-number {
    color: var(--primary);
}

.calendar-day.has-sessions .day-number {
    color: #10B981;
}

.day-sessions {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.calendar-session {
    font-size: 11px;
    color: var(--text-dim);
    padding: 4px 6px;
    background: rgba(107, 70, 193, 0.15);
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.calendar-session:hover {
    background: var(--primary);
    color: white;
}

.session-dot {
    width: 6px;
    height: 6px;
    background: var(--primary);
    border-radius: 50%;
    flex-shrink: 0;
}

.calendar-session:hover .session-dot {
    background: white;
}

/* Video Player Modal */
.video-player-modal {
    max-width: 1000px;
}

.video-player-container {
    position: relative;
    background: #000;
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 16 / 9;
}

.video-player {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: contain;
}

.video-player-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-main);
    min-height: 300px;
}

.video-player-placeholder i {
    font-size: 64px;
    color: var(--primary);
    opacity: 0.3;
    margin-bottom: 16px;
}

.video-player-placeholder p {
    color: var(--text-dim);
    font-size: 14px;
}

.video-details-section {
    margin-top: 20px;
    padding: 20px;
    background: var(--bg-main);
    border-radius: 8px;
}

.video-details-section h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--text-white);
}

/* Placeholder */
.placeholder-container {
    text-align: center;
    padding: 60px 24px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary);
    opacity: 0.25;
    margin-bottom: 20px;
    display: block;
}

.placeholder-text {
    font-size: 15px;
    color: var(--text-dim);
    margin-bottom: 20px;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Demo Notice */
.demo-data-notice {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 12px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary-light, #8B5CF6);
    font-size: 14px;
}

.demo-data-notice i {
    font-size: 16px;
}

/* Modal Styles */
.video-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
}

.modal-content {
    position: relative;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: var(--primary);
}

.modal-close {
    width: 40px;
    height: 40px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-white);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: var(--primary);
    border-color: var(--primary);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
}

/* Responsive */
@media (max-width: 992px) {
    .session-card {
        grid-template-columns: 70px 1fr;
        grid-template-rows: auto auto;
    }
    
    .session-videos-count,
    .session-actions {
        grid-column: 2;
    }
    
    .calendar-day {
        min-height: 80px;
    }
}

@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .form-input-small {
        width: 100%;
    }
    
    .session-card {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .session-date-badge {
        flex-direction: row;
        gap: 8px;
        justify-content: center;
    }
    
    .session-meta {
        justify-content: center;
    }
    
    .session-actions {
        justify-content: center;
    }
    
    .view-toggle {
        flex-direction: column;
        width: 100%;
    }
    
    .view-toggle-btn {
        justify-content: center;
    }
    
    .calendar-day {
        min-height: 60px;
        padding: 4px;
    }
    
    .day-sessions {
        display: none;
    }
    
    .calendar-day.has-sessions::after {
        content: '';
        position: absolute;
        bottom: 4px;
        left: 50%;
        transform: translateX(-50%);
        width: 6px;
        height: 6px;
        background: #10B981;
        border-radius: 50%;
    }
}

/* Athlete Upload Badge */
.badge-athlete-upload {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

/* Video description snippet */
.video-description {
    color: var(--text-dim);
    font-size: 12px;
    margin: 4px 0 0;
    line-height: 1.4;
}

.video-detail-description {
    color: var(--text-dim);
    font-size: 14px;
    line-height: 1.6;
    margin: 0 0 12px;
}

.video-detail-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-dim);
    margin-right: 16px;
}

.video-detail-item i {
    color: var(--primary);
    font-size: 12px;
}

/* Delete button */
.btn-delete-video {
    margin-top: 8px;
    width: 100%;
    padding: 8px 12px;
    background: transparent;
    border: 1px solid rgba(239, 68, 68, 0.4);
    color: #ef4444;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.btn-delete-video:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: #ef4444;
}

/* Ingest Device Styles */
.ingest-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
.ingest-description { color: var(--text-dim); font-size: 14px; margin-bottom: 24px; line-height: 1.6; }
.ingest-step { display: flex; gap: 16px; margin-bottom: 24px; padding: 20px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; }
.ingest-step-number { width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: white; flex-shrink: 0; }
.ingest-step-content { flex: 1; }
.ingest-step-content h4 { font-size: 15px; font-weight: 700; color: var(--text-white); margin-bottom: 6px; }
.ingest-step-content > p { color: var(--text-dim); font-size: 13px; margin-bottom: 12px; }
.ingest-video-list { max-height: 300px; overflow-y: auto; margin: 12px 0; }
.ingest-video-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 6px; background: var(--bg-card); }
.ingest-video-item i { color: var(--primary); font-size: 18px; }
.ingest-video-item .ingest-video-info { flex: 1; }
.ingest-video-item .ingest-video-info strong { display: block; color: var(--text-white); font-size: 13px; }
.ingest-video-item .ingest-video-info small { color: var(--text-dim); font-size: 11px; }
.ingest-checkbox-label { display: flex; align-items: center; gap: 8px; color: var(--text-dim); font-size: 13px; margin-bottom: 12px; cursor: pointer; }
.ingest-action-buttons { display: flex; gap: 8px; justify-content: flex-end; }
.ingest-progress-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; color: var(--text-white); }
.ingest-progress-status { color: var(--text-dim); font-size: 12px; margin-top: 8px; }
</style>

<script src="js/offline-upload-queue.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Video player modal handling
    const modal = document.getElementById('videoPlayerModal');
    const videoPlayer = document.getElementById('videoPlayer');
    const videoPlaceholder = document.getElementById('videoPlaceholder');
    const videoTitle = document.getElementById('videoModalTitle');
    const videoDetails = document.getElementById('videoDetails');
    const videoDetailTitle = document.getElementById('videoDetailTitle');
    const videoDetailMeta = document.getElementById('videoDetailMeta');
    var activeHls = null;
    var drFallbackUrl = '';
    var drFallbackTried = false;
    var drPrimaryUrl = '';   // stash primary URL for reload-on-no-fallback

    function cleanupVideoPlayer() {
        if (activeHls) { activeHls.destroy(); activeHls = null; }
        drFallbackUrl = '';
        drFallbackTried = false;
        drPrimaryUrl = '';
        if (videoPlayer) {
            // Clean up custom controls injected by hls-player.js
            var container = videoPlayer.parentElement;
            if (container) {
                var ctrl = container.querySelector('.aw-player-controls');
                if (ctrl) { if (ctrl._cleanup) ctrl._cleanup(); ctrl.remove(); }
                var grad = container.querySelector('.aw-controls-gradient');
                if (grad) grad.remove();
                var bp = container.querySelector('.aw-big-play');
                if (bp) bp.remove();
                var tzl = container.querySelector('.aw-touch-zone-left');
                if (tzl) tzl.remove();
                var tzr = container.querySelector('.aw-touch-zone-right');
                if (tzr) tzr.remove();
                var tzc = container.querySelector('.aw-touch-zone-center');
                if (tzc) tzc.remove();
            }
            videoPlayer.pause();
            videoPlayer.removeAttribute('src');
            videoPlayer.removeAttribute('poster');
            videoPlayer.setAttribute('controls', '');
            videoPlayer.currentTime = 0;
        }
    }

    // Fallback: if primary video source fails (e.g. 502 because the companion
    // deleted the original after HLS transcode but the callback didn't update
    // hls_status), retry with the pre-set HLS URL.
    // When no fallback exists, try reloading the primary HLS URL once
    // (the failure may have been transient, e.g. a temporary buffer error).
    var drReloadTried = false;
    if (videoPlayer) {
        videoPlayer.addEventListener('error', function() {
            if (drFallbackUrl && !drFallbackTried) {
                drFallbackTried = true;
                if (typeof window.awReportPlaybackError === 'function') {
                    window.awReportPlaybackError('Drill review: primary source failed, trying fallback', { fallback: drFallbackUrl });
                }
                if (typeof window.awInitHlsPlayer === 'function') {
                    activeHls = window.awInitHlsPlayer(videoPlayer, drFallbackUrl);
                }
            } else if (!drFallbackUrl && drPrimaryUrl && !drReloadTried) {
                drReloadTried = true;
                if (typeof window.awReportPlaybackError === 'function') {
                    window.awReportPlaybackError('Drill review: no fallback URL, reloading primary HLS stream', { primary: drPrimaryUrl });
                }
                if (typeof window.awInitHlsPlayer === 'function') {
                    activeHls = window.awInitHlsPlayer(videoPlayer, drPrimaryUrl);
                }
            } else if (typeof window.awReportPlaybackError === 'function') {
                window.awReportPlaybackError('Drill review: video playback failed — no fallback URL', {});
            }
        }, true);
    }
    
    // Play video buttons
    document.querySelectorAll('[data-action="play-video"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const videoUrl = this.dataset.videoUrl;
            const hlsUrl = this.dataset.fallbackUrl || '';
            const thumbnailUrl = this.dataset.thumbnailUrl || '';
            const title = this.closest('.video-card').querySelector('.video-title')?.textContent || 'Video';
            const description = this.dataset.videoDescription || '';
            const coach = this.dataset.videoCoach || '';
            const date = this.dataset.videoDate || '';
            
            if (modal) {
                modal.style.display = 'flex';
                videoTitle.innerHTML = '<i class="fas fa-play-circle"></i> ' + title;
                
                // Populate video details section
                if (videoDetailTitle) videoDetailTitle.textContent = title;
                if (videoDetailMeta) {
                    var metaHtml = '';
                    if (description) {
                        var descEl = document.createElement('p');
                        descEl.className = 'video-detail-description';
                        descEl.textContent = description;
                        metaHtml += descEl.outerHTML;
                    }
                    if (coach) {
                        var coachEl = document.createElement('span');
                        coachEl.className = 'video-detail-item';
                        coachEl.innerHTML = '<i class="fas fa-user-tie"></i> ';
                        coachEl.appendChild(document.createTextNode(coach));
                        metaHtml += coachEl.outerHTML;
                    }
                    if (date) {
                        var dateEl = document.createElement('span');
                        dateEl.className = 'video-detail-item';
                        dateEl.innerHTML = '<i class="fas fa-calendar"></i> ';
                        dateEl.appendChild(document.createTextNode(date));
                        metaHtml += dateEl.outerHTML;
                    }
                    videoDetailMeta.innerHTML = metaHtml;
                }
                if (videoDetails) videoDetails.style.display = (description || coach || date) ? 'block' : 'none';
                
                if (videoUrl) {
                    // Destroy any previous HLS instance and clean up controls
                    cleanupVideoPlayer();
                    drReloadTried = false;

                    // Store HLS fallback URL for error recovery
                    drFallbackUrl = (hlsUrl && hlsUrl !== videoUrl) ? hlsUrl : '';
                    drPrimaryUrl = videoUrl;
                    drFallbackTried = false;

                    // Set poster/thumbnail for preview before video loads
                    if (thumbnailUrl) videoPlayer.poster = thumbnailUrl;

                    if (typeof window.awInitHlsPlayer === 'function') {
                        activeHls = window.awInitHlsPlayer(videoPlayer, videoUrl);
                    } else {
                        videoPlayer.querySelector('source').src = videoUrl;
                        videoPlayer.load();
                    }
                    videoPlayer.style.display = 'block';
                    if (videoPlaceholder) videoPlaceholder.style.display = 'none';
                } else {
                    videoPlayer.style.display = 'none';
                    if (videoPlaceholder) {
                        videoPlaceholder.style.display = 'flex';
                        videoPlaceholder.querySelector('p').textContent = 'Demo video - actual video will be available once recorded';
                    }
                }
            }
        });
    });
    
    // Close modal
    document.querySelectorAll('[data-action="close-modal"]').forEach(el => {
        el.addEventListener('click', function() {
            if (modal) {
                modal.style.display = 'none';
                cleanupVideoPlayer();
            }
        });
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
            modal.style.display = 'none';
            cleanupVideoPlayer();
        }
    });
    
    // View session videos
    document.querySelectorAll('[data-action="view-session-videos"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const sessionId = this.dataset.sessionId;
            // In a real implementation, this would open a modal or navigate to session videos
            showToast('View videos for session ' + sessionId + '\n\nThis would show all videos from this session.', 'info');
        });
    });

    // Delete video buttons
    document.querySelectorAll('[data-action="delete-video"]').forEach(btn => {
        btn.addEventListener('click', async function() {
            const videoId = this.dataset.videoId;
            const videoTitle = this.dataset.videoTitle || 'this video';
            if (!await showConfirmModal('Are you sure you want to delete "' + videoTitle + '"? This cannot be undone.')) {
                return;
            }
            const card = this.closest('.video-card');
            const formData = new FormData();
            formData.append('action', 'delete_video');
            formData.append('video_id', videoId);
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            const csrfToken = (csrfMeta && csrfMeta.content) || (csrfInput && csrfInput.value) || '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
            formData.append('csrf_token', csrfToken);

            fetch('process_video.php', { method: 'POST', body: formData })
                .then(function(r) {
                    if (!r.ok) return r.text().then(function(b) { var m = 'HTTP ' + r.status; try { var j = JSON.parse(b); if (j.error) m = j.error; } catch(e) {} throw new Error(m); });
                    return r.json();
                })
                .then(function(data) {
                    if (data.success) {
                        if (card) card.remove();
                        if (typeof showToast === 'function') showToast('Video deleted successfully.', 'success');
                    } else {
                        if (typeof showToast === 'function') showToast('Delete failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(function(err) {
                    if (typeof showToast === 'function') showToast('Delete failed: ' + (err.message || 'Please try again.'), 'error');
                });
        });
    });
});

// ── Athlete Ingest from Device ──────────────────────────────
(function() {
    var selectBtn = document.getElementById('athleteIngestSelectDirBtn');
    var step1 = document.getElementById('athleteIngestStep1');
    var step2 = document.getElementById('athleteIngestStep2');
    var step3 = document.getElementById('athleteIngestStep3');
    var scanStatus = document.getElementById('athleteIngestScanStatus');
    var videoList = document.getElementById('athleteIngestVideoList');
    var actionsDiv = document.getElementById('athleteIngestActions');
    var startBtn = document.getElementById('athleteIngestStartBtn');
    var cancelBtn = document.getElementById('athleteIngestCancelBtn');
    var deleteCheckbox = document.getElementById('athleteIngestDeleteAfter');
    var progressTitle = document.getElementById('athleteIngestProgressTitle');
    var progressCount = document.getElementById('athleteIngestProgressCount');
    var progressFill = document.getElementById('athleteIngestProgressFill');
    var progressStatus = document.getElementById('athleteIngestProgressStatus');
    var fsaNotSupported = document.getElementById('athleteIngestFsaNotSupported');
    var fsaSupported = document.getElementById('athleteIngestFsaSupported');

    if (!selectBtn) return;

    if (typeof window.showDirectoryPicker !== 'function') {
        if (fsaNotSupported) fsaNotSupported.style.display = '';
        if (fsaSupported) fsaSupported.style.display = 'none';
        return;
    }

    var _discovered = [];
    var _dirHandle = null;

    selectBtn.addEventListener('click', async function() {
        try {
            _dirHandle = await window.showDirectoryPicker({ mode: 'readwrite' });
            step1.style.display = 'none';
            step2.style.display = '';
            scanStatus.textContent = 'Scanning for videos…';
            videoList.innerHTML = '';
            actionsDiv.style.display = 'none';

            if (typeof AwOfflineQueue !== 'undefined' && AwOfflineQueue.scanForIngest) {
                _discovered = await AwOfflineQueue.scanForIngest(_dirHandle);
            } else {
                _discovered = [];
            }

            if (_discovered.length === 0) {
                scanStatus.textContent = 'No video files with matching sidecar metadata found.';
                return;
            }

            scanStatus.textContent = 'Found ' + _discovered.length + ' video(s) ready to import:';
            _discovered.forEach(function(item) {
                var div = document.createElement('div');
                div.className = 'ingest-video-item';
                var sizeStr = item.meta && item.meta.file_size ? (item.meta.file_size / (1024*1024)).toFixed(1) + ' MB' : 'unknown size';
                div.innerHTML = '<i class="fas fa-film"></i><div class="ingest-video-info"><strong>' +
                    (item.meta && item.meta.title ? item.meta.title : item.videoFile.name) +
                    '</strong><small>' + item.videoFile.name + ' — ' + sizeStr + '</small></div>';
                videoList.appendChild(div);
            });
            actionsDiv.style.display = '';
        } catch (err) {
            if (err.name !== 'AbortError') scanStatus.textContent = 'Error: ' + err.message;
        }
    });

    if (cancelBtn) cancelBtn.addEventListener('click', function() {
        step2.style.display = 'none';
        step1.style.display = '';
        _discovered = [];
        _dirHandle = null;
    });

    if (startBtn) startBtn.addEventListener('click', async function() {
        if (_discovered.length === 0) return;
        step2.style.display = 'none';
        step3.style.display = '';
        progressTitle.textContent = 'Importing videos to queue…';
        progressCount.textContent = '0 / ' + _discovered.length;
        progressFill.style.width = '0%';
        progressStatus.textContent = '';

        var imported = 0;
        var shouldDelete = deleteCheckbox && deleteCheckbox.checked;
        for (var i = 0; i < _discovered.length; i++) {
            var item = _discovered[i];
            progressStatus.textContent = 'Reading ' + item.videoFile.name + '…';
            try {
                if (typeof AwOfflineQueue !== 'undefined' && AwOfflineQueue.ingestFromDevice) {
                    await AwOfflineQueue.ingestFromDevice(item, { deleteAfterImport: shouldDelete, dirHandle: _dirHandle });
                }
                imported++;
                progressCount.textContent = imported + ' / ' + _discovered.length;
                progressFill.style.width = Math.round((imported / _discovered.length) * 100) + '%';
            } catch (err) {
                progressStatus.textContent = 'Failed: ' + item.videoFile.name + ' — ' + err.message;
            }
        }
        progressTitle.textContent = 'Import complete!';
        progressStatus.textContent = imported + ' video(s) added to upload queue.';

        // Auto-start upload queue for imported videos
        if (typeof AwOfflineQueue !== 'undefined' && AwOfflineQueue.processQueue) {
            AwOfflineQueue.processQueue();
        }
    });
})();
</script>
