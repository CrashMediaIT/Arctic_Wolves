<?php
/**
 * Public Practice Plan Share Page
 * Allows anyone with a valid share token to view a practice plan without logging in.
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/image_helper.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

$token = $_GET['token'] ?? '';
$plan = null;
$drills = [];

// Validate token format (must be 64 hex chars)
if (!empty($token) && preg_match('/^[a-f0-9]{64}$/', $token) && $db_connected) {
    try {
        $stmt = $pdo->prepare("
            SELECT pp.*, COALESCE(pp.title, pp.name) as title,
                   COALESCE(pp.total_duration, pp.duration_minutes, 60) as total_duration,
                   u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM practice_plans pp
            LEFT JOIN users u ON pp.created_by = u.id
            WHERE pp.share_token = ?
        ");
        $stmt->execute([$token]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($plan) {
            $plan = decryptUserRow($plan);
        }
    } catch (PDOException $e) {
        error_log("Error fetching shared practice plan: " . $e->getMessage());
    }

    // Fetch drills for this plan
    if ($plan) {
        try {
            $drillStmt = $pdo->prepare("
                SELECT ppd.*, d.title, d.description, d.setup, d.coaching_points,
                       d.progression, d.diagram_data, d.custom_image, d.video_url,
                       dc.name as category_name, ppd.duration_minutes as drill_duration
                FROM practice_plan_drills ppd
                LEFT JOIN drills d ON ppd.drill_id = d.id
                LEFT JOIN drill_categories dc ON d.category_id = dc.id
                WHERE ppd.practice_plan_id = ?
                ORDER BY ppd.drill_order ASC
            ");
            $drillStmt->execute([$plan['id']]);
            $drills = $drillStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($drills as &$drillRow) {
                $drillRow = decryptUserRow($drillRow);
            }
            unset($drillRow);
        } catch (PDOException $e) {
            error_log("Error fetching practice plan drills: " . $e->getMessage());
        }
    }
}

// Fetch center ice logo URL
$centerLogoUrl = '';
if ($plan) {
    try {
        $logoStmt = $pdo->prepare("
            SELECT COALESCE(
                MAX(CASE WHEN setting_name = 'center_ice_logo_url' AND setting_value != '' THEN setting_value END),
                MAX(CASE WHEN setting_name = 'logo_url' AND setting_value != '' THEN setting_value END)
            ) as logo_url 
            FROM theme_settings 
            WHERE setting_name IN ('center_ice_logo_url', 'logo_url')
        ");
        $logoStmt->execute();
        $logoResult = $logoStmt->fetch(PDO::FETCH_ASSOC);
        if ($logoResult && !empty($logoResult['logo_url'])) {
            $centerLogoUrl = resolveRustfsUrl($pdo, $logoResult['logo_url']);
        }
    } catch (PDOException $e) {
        error_log("Error fetching center ice logo URL: " . $e->getMessage());
    }
}

// Pre-process drill ice views and custom images
$drillIceViews = [];
$allowedIceViews = ['full', 'left-zone', 'right-zone', 'center'];
foreach ($drills as &$drillRow) {
    $iceView = 'full';
    if (!empty($drillRow['diagram_data'])) {
        $parsed = json_decode($drillRow['diagram_data'], true);
        if (is_array($parsed) && isset($parsed['iceView'])) {
            $parsedView = $parsed['iceView'];
            if (in_array($parsedView, $allowedIceViews, true)) {
                $iceView = $parsedView;
            }
        }
    }
    $drillIceViews[$drillRow['id']] = $iceView;

    if (!empty($drillRow['custom_image'])) {
        $drillRow['custom_image_url'] = resolveRustfsUrl($pdo, $drillRow['custom_image']);
    }
}
unset($drillRow);

$coachName = $plan ? htmlspecialchars(trim(($plan['creator_first_name'] ?? '') . ' ' . ($plan['creator_last_name'] ?? ''))) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $plan ? htmlspecialchars($plan['title']) . ' - ' : ''; ?>Shared Practice Plan</title>
    <link rel="icon" href="<?php echo htmlspecialchars($site_favicon_url); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0A0A0F;
            --bg-secondary: #13131A;
            --bg-card: #16161F;
            --primary: #6B46C1;
            --primary-hover: #7C3AED;
            --primary-light: #8B5CF6;
            --accent: #8B5CF6;
            --text-white: #FFFFFF;
            --text-primary: #FFFFFF;
            --text-secondary: #A8A8B8;
            --text-dim: #A8A8B8;
            --border: #2D2D3F;
            --border-light: #3A3A4F;
            --radius: 12px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px 16px;
            flex: 1;
        }

        .content-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(107, 70, 193, 0.05) 0%, transparent 100%);
            min-height: 64px;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-white);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 i { color: var(--primary); font-size: 18px; }

        .card-body { padding: 24px; }

        /* Page header */
        .page-header {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .page-title {
            font-size: 28px;
            font-weight: 900;
            color: var(--text-white);
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.5px;
        }

        .page-title i { color: var(--primary); font-size: 26px; }

        .page-meta {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
        }

        .badge-primary { background: var(--primary); }
        .badge-blue { background: #1a56db; }
        .badge-green { background: #047857; }
        .badge-orange { background: #b45309; }
        .badge-gray { background: rgba(255,255,255,0.15); color: var(--text-primary); }

        .coach-info {
            color: var(--text-dim);
            font-size: 14px;
        }

        /* Plan details */
        .plan-details-grid {
            display: grid;
            gap: 20px;
        }

        .detail-section h4 {
            margin: 0 0 10px 0;
            color: var(--text-white);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-section h4 i {
            color: var(--primary);
            margin-right: 4px;
        }

        .detail-section p {
            color: var(--text-dim);
            line-height: 1.6;
        }

        .meta-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 12px;
            color: var(--text-dim);
            text-transform: uppercase;
        }

        .meta-value {
            font-weight: 600;
            color: var(--text-white);
        }

        /* Drill cards */
        .drill-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .drill-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 8px;
        }

        .drill-card-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .drill-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .drill-card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-white);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .drill-card-badges {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .drill-card-body { padding: 16px 20px; }

        /* Diagram */
        .drill-diagram-view {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        .ihs-diagram-container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
            border: 3px solid #0033a0;
            border-radius: 12px;
            padding: 12px;
            overflow: hidden;
        }

        .ihs-drill-image {
            max-width: 100%;
            max-height: 350px;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
        }

        .ice-rink-canvas.view-only {
            width: 100%;
            aspect-ratio: 200/85;
            min-height: 200px;
            background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
            border: 3px solid #0033a0;
            border-radius: 60px;
            position: relative;
            overflow: hidden;
            transition: aspect-ratio 0.3s ease-in-out;
        }

        .ice-rink-canvas.view-only[data-ice-view="full"] {
            aspect-ratio: 200/85;
            border-radius: 60px;
        }

        .ice-rink-canvas.view-only[data-ice-view="left-zone"],
        .ice-rink-canvas.view-only[data-ice-view="right-zone"] {
            aspect-ratio: 100/85;
            border-radius: 60px;
        }

        .ice-rink-canvas.view-only[data-ice-view="center"] {
            aspect-ratio: 72/85;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            border-radius: 20px;
        }

        .ice-rink-canvas.view-only canvas {
            width: 100%;
            height: 100%;
            border-radius: inherit;
        }

        .drill-detail-section { margin-top: 12px; }

        .drill-detail-section h5 {
            margin: 0 0 6px 0;
            color: var(--text-white);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .drill-detail-section h5 i {
            color: var(--primary);
            margin-right: 4px;
        }

        .drill-detail-section p {
            color: var(--text-dim);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Error state */
        .error-container {
            text-align: center;
            padding: 60px 20px;
        }

        .error-container i {
            font-size: 48px;
            color: var(--text-dim);
            margin-bottom: 16px;
        }

        .error-container h2 {
            color: var(--text-white);
            margin-bottom: 8px;
        }

        .error-container p {
            color: var(--text-dim);
            font-size: 15px;
        }

        /* Footer */
        .share-footer {
            text-align: center;
            padding: 24px 16px;
            border-top: 1px solid var(--border);
            color: var(--text-dim);
            font-size: 13px;
        }

        .share-footer a {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
        }

        .share-footer a:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .page-title { font-size: 20px; }
            .drill-card-header { flex-direction: column; align-items: flex-start; }
            .ice-rink-canvas.view-only { min-height: 150px; border-radius: 40px; }
            .ice-rink-canvas.view-only[data-ice-view="center"] { border-radius: 14px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($plan): ?>
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($plan['title']); ?>
                </h1>
                <div class="page-meta">
                    <?php if (!empty($plan['total_duration'])): ?>
                        <span class="badge badge-blue"><i class="fas fa-clock"></i> <?php echo (int)$plan['total_duration']; ?> min</span>
                    <?php endif; ?>
                    <?php if (!empty($plan['age_group'])): ?>
                        <span class="badge badge-primary"><?php echo htmlspecialchars($plan['age_group']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($plan['difficulty_level'])): ?>
                        <span class="badge badge-orange"><?php echo htmlspecialchars(ucfirst($plan['difficulty_level'])); ?></span>
                    <?php endif; ?>
                    <?php if ($coachName): ?>
                        <span class="coach-info"><i class="fas fa-user"></i> <?php echo $coachName; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Plan Details -->
            <?php if (!empty($plan['description']) || !empty($plan['focus_area'])): ?>
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Plan Details</h3>
                </div>
                <div class="card-body">
                    <div class="plan-details-grid">
                        <?php if (!empty($plan['description'])): ?>
                        <div class="detail-section">
                            <h4><i class="fas fa-align-left"></i> Description</h4>
                            <p><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($plan['focus_area'])): ?>
                        <div class="detail-section">
                            <h4><i class="fas fa-bullseye"></i> Focus Area</h4>
                            <p><?php echo htmlspecialchars($plan['focus_area']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Drills List -->
            <?php if (!empty($drills)): ?>
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-hockey-puck"></i> Drills (<?php echo count($drills); ?>)</h3>
                </div>
                <div class="card-body" style="padding: 16px;">
                    <?php foreach ($drills as $index => $drill): ?>
                    <div class="drill-card">
                        <div class="drill-card-header">
                            <div class="drill-card-header-left">
                                <span class="drill-number"><?php echo $index + 1; ?></span>
                                <span class="drill-card-title"><?php echo htmlspecialchars($drill['title'] ?? 'Untitled Drill'); ?></span>
                            </div>
                            <div class="drill-card-badges">
                                <?php if (!empty($drill['drill_duration'])): ?>
                                    <span class="badge badge-blue"><i class="fas fa-clock"></i> <?php echo (int)$drill['drill_duration']; ?> min</span>
                                <?php endif; ?>
                                <?php if (!empty($drill['category_name'])): ?>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($drill['category_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="drill-card-body">
                            <?php
                            $hasDiagram = !empty($drill['diagram_data']);
                            $hasCustomImage = !empty($drill['custom_image']);
                            $drillId = (int)$drill['id'];
                            $iceView = $drillIceViews[$drillId] ?? 'full';
                            ?>

                            <?php if ($hasCustomImage): ?>
                            <div class="drill-diagram-view">
                                <div class="ihs-diagram-container">
                                    <img src="<?php echo htmlspecialchars($drill['custom_image_url'] ?? ''); ?>"
                                         alt="<?php echo htmlspecialchars($drill['title'] ?? ''); ?> Diagram"
                                         class="ihs-drill-image">
                                </div>
                            </div>
                            <?php elseif ($hasDiagram): ?>
                            <div class="drill-diagram-view">
                                <div class="ice-rink-canvas view-only"
                                     id="drill-canvas-container-<?php echo $drillId; ?>"
                                     data-ice-view="<?php echo htmlspecialchars($iceView); ?>"
                                     data-center-logo="<?php echo htmlspecialchars($centerLogoUrl); ?>">
                                    <canvas id="drill-canvas-<?php echo $drillId; ?>"></canvas>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($drill['description'])): ?>
                            <div class="drill-detail-section">
                                <h5><i class="fas fa-align-left"></i> Description</h5>
                                <p><?php echo nl2br(htmlspecialchars($drill['description'])); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($drill['coaching_points'])): ?>
                            <div class="drill-detail-section">
                                <h5><i class="fas fa-bullseye"></i> Coaching Points</h5>
                                <p><?php echo nl2br(htmlspecialchars($drill['coaching_points'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="content-card">
                <div class="card-body">
                    <div class="error-container" style="padding: 30px;">
                        <i class="fas fa-hockey-puck"></i>
                        <h2>No Drills</h2>
                        <p>This practice plan doesn't have any drills yet.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Error: Plan Not Found -->
            <div class="content-card">
                <div class="card-body">
                    <div class="error-container">
                        <i class="fas fa-unlink"></i>
                        <h2>Practice Plan Not Found</h2>
                        <p>This practice plan link is invalid or has expired. Please check the URL and try again.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="share-footer">
        <p>Powered by <a href="/">Arctic Wolves</a></p>
    </div>

    <?php if ($plan && !empty($drills)): ?>
    <script src="js/ice_canvas.js"></script>
    <script>
    let centerLogoImage = null;
    let centerLogoLoaded = false;

    const NHL_RINK = window.ICE_CANVAS_NHL_RINK || {
        GOAL_LINE: 11 / 200,
        BLUE_LINE: 64 / 200,
        FACEOFF_RADIUS: 15 / 85,
        CENTER_CIRCLE_RADIUS: 15 / 85,
        CREASE_RADIUS: 6 / 85,
        FACEOFF_FROM_GOAL: 20 / 200,
        FACEOFF_FROM_BOARDS: 22 / 85,
        TRAPEZOID_BASE: 22 / 85,
        TRAPEZOID_TOP: 28 / 85,
        RESTRAINT_LINE_LENGTH: 2 / 85,
        CORNER_RADIUS: 28 / 85
    };

    // Drill diagram data keyed by drill ID
    const drillDiagramData = {};
    <?php foreach ($drills as $drill):
        if (!empty($drill['diagram_data'])):
            $drillId = (int)$drill['id'];
    ?>
    drillDiagramData[<?php echo $drillId; ?>] = <?php echo json_encode($drill['diagram_data']); ?>;
    <?php endif; endforeach; ?>

    document.addEventListener('DOMContentLoaded', function() {
        const centerLogoUrl = <?php echo json_encode($centerLogoUrl); ?>;

        function renderAllCanvases() {
            Object.keys(drillDiagramData).forEach(function(drillId) {
                const container = document.getElementById('drill-canvas-container-' + drillId);
                const canvas = document.getElementById('drill-canvas-' + drillId);
                if (!container || !canvas) return;

                const dpr = window.devicePixelRatio || 1;
                const cssWidth = container.offsetWidth;
                const cssHeight = container.offsetHeight;
                canvas.width = cssWidth * dpr;
                canvas.height = cssHeight * dpr;
                canvas.style.width = cssWidth + 'px';
                canvas.style.height = cssHeight + 'px';

                const ctx = canvas.getContext('2d');
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                const diagramDataRaw = drillDiagramData[drillId];
                let diagramObjects = [];
                let sourceWidth = cssWidth;
                let sourceHeight = cssHeight;
                let iceView = container.dataset.iceView || 'full';

                try {
                    const parsed = JSON.parse(diagramDataRaw);
                    if (Array.isArray(parsed)) {
                        diagramObjects = parsed;
                    } else if (parsed && parsed.objects && Array.isArray(parsed.objects)) {
                        diagramObjects = parsed.objects;
                        sourceWidth = parsed.canvasWidth || cssWidth;
                        sourceHeight = parsed.canvasHeight || cssHeight;
                        if (parsed.iceView) {
                            iceView = parsed.iceView;
                        }
                    }
                } catch (e) {
                    return;
                }

                drawViewRink(ctx, cssWidth, cssHeight, iceView);

                if (diagramObjects.length > 0) {
                    const scaleX = cssWidth / sourceWidth;
                    const scaleY = cssHeight / sourceHeight;
                    const uniformScale = Math.min(scaleX, scaleY);
                    const offsetX = (cssWidth - sourceWidth * uniformScale) / 2;
                    const offsetY = (cssHeight - sourceHeight * uniformScale) / 2;

                    diagramObjects.forEach(function(obj) {
                        const scaledObj = scaleObjectForView(obj, uniformScale, offsetX, offsetY);
                        drawObject(ctx, scaledObj, uniformScale);
                    });
                }
            });
        }

        function scaleObjectForView(obj, scale, offsetX, offsetY) {
            const scaled = Object.assign({}, obj);
            if (scaled.x !== undefined) scaled.x = scaled.x * scale + offsetX;
            if (scaled.y !== undefined) scaled.y = scaled.y * scale + offsetY;
            if (scaled.x1 !== undefined) scaled.x1 = scaled.x1 * scale + offsetX;
            if (scaled.y1 !== undefined) scaled.y1 = scaled.y1 * scale + offsetY;
            if (scaled.x2 !== undefined) scaled.x2 = scaled.x2 * scale + offsetX;
            if (scaled.y2 !== undefined) scaled.y2 = scaled.y2 * scale + offsetY;
            if (scaled.points && Array.isArray(scaled.points)) {
                scaled.points = scaled.points.map(function(pt) {
                    return { x: pt.x * scale + offsetX, y: pt.y * scale + offsetY };
                });
            }
            return scaled;
        }

        function waitForLayoutAndRender() {
            requestAnimationFrame(function() {
                requestAnimationFrame(renderAllCanvases);
            });
        }

        if (centerLogoUrl) {
            centerLogoImage = new Image();
            centerLogoImage.crossOrigin = 'anonymous';
            centerLogoImage.onload = function() {
                centerLogoLoaded = true;
                waitForLayoutAndRender();
            };
            centerLogoImage.onerror = function() {
                centerLogoLoaded = false;
                waitForLayoutAndRender();
            };
            centerLogoImage.src = centerLogoUrl;
        } else {
            waitForLayoutAndRender();
        }

        window.addEventListener('resize', renderAllCanvases);
    });

    function drawViewRink(ctx, w, h, iceView) {
        iceView = iceView || 'full';
        if (window.IceCanvasRenderer) {
            IceCanvasRenderer.drawRink(ctx, w, h, iceView, {
                logoImage: centerLogoImage,
                logoLoaded: centerLogoLoaded,
                lineScale: 1.5
            });
        } else {
            ctx.fillStyle = '#f0f7fa';
            ctx.fillRect(0, 0, w, h);
            ctx.save();
            ctx.globalAlpha = 0.12;
            if (centerLogoLoaded && centerLogoImage) {
                const maxLogoWidth = w * 0.3;
                const maxLogoHeight = h * 0.25;
                const imgAspect = centerLogoImage.width / centerLogoImage.height;
                let logoWidth = maxLogoWidth;
                let logoHeight = logoWidth / imgAspect;
                if (logoHeight > maxLogoHeight) {
                    logoHeight = maxLogoHeight;
                    logoWidth = logoHeight * imgAspect;
                }
                ctx.drawImage(centerLogoImage, (w - logoWidth) / 2, (h - logoHeight) / 2, logoWidth, logoHeight);
            } else {
                ctx.fillStyle = '#7000a4';
                ctx.font = 'bold 28px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('ARCTIC WOLVES', w/2, h/2 - 10);
                ctx.font = '14px Inter, sans-serif';
                ctx.fillText('HOCKEY', w/2, h/2 + 14);
            }
            ctx.restore();
            ctx.strokeStyle = '#0033a0';
            ctx.lineWidth = 3;
            ctx.strokeRect(2, 2, w - 4, h - 4);
        }
    }

    function drawObject(ctx, obj, scale) {
        scale = scale || 1;
        ctx.save();

        if (obj.type === 'player') {
            ctx.translate(obj.x, obj.y);
            ctx.rotate((obj.rotation || 0) * Math.PI / 180);
            ctx.fillStyle = obj.color || '#00bfff';
            ctx.beginPath();
            ctx.arc(0, 0, 14 * scale, 0, 2 * Math.PI);
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2 * scale;
            ctx.stroke();
            if (obj.label) {
                ctx.fillStyle = '#fff';
                ctx.font = 'bold ' + Math.round(10 * scale) + 'px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(obj.label, 0, 0);
            }
        } else if (obj.type === 'cone') {
            ctx.translate(obj.x, obj.y);
            ctx.rotate((obj.rotation || 0) * Math.PI / 180);
            ctx.fillStyle = obj.color || '#ff6b00';
            ctx.beginPath();
            ctx.moveTo(0, -15 * scale);
            ctx.lineTo(-10 * scale, 10 * scale);
            ctx.lineTo(10 * scale, 10 * scale);
            ctx.closePath();
            ctx.fill();
        } else if (obj.type === 'puck') {
            ctx.fillStyle = obj.color || '#000';
            ctx.beginPath();
            ctx.arc(obj.x, obj.y, 8 * scale, 0, 2 * Math.PI);
            ctx.fill();
        } else if (obj.type === 'line') {
            ctx.strokeStyle = obj.color || '#333';
            ctx.lineWidth = 3 * scale;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            if (obj.points && obj.points.length >= 2) {
                ctx.beginPath();
                ctx.moveTo(obj.points[0].x, obj.points[0].y);
                for (var i = 1; i < obj.points.length - 1; i++) {
                    var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                    ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
                }
                ctx.lineTo(obj.points[obj.points.length - 1].x, obj.points[obj.points.length - 1].y);
                ctx.stroke();
            } else if (obj.x1 !== undefined) {
                ctx.beginPath();
                ctx.moveTo(obj.x1, obj.y1);
                ctx.lineTo(obj.x2, obj.y2);
                ctx.stroke();
            }
        } else if (obj.type === 'dashed') {
            ctx.strokeStyle = obj.color || '#333';
            ctx.lineWidth = 3 * scale;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.setLineDash([8 * scale, 5 * scale]);
            if (obj.points && obj.points.length >= 2) {
                ctx.beginPath();
                ctx.moveTo(obj.points[0].x, obj.points[0].y);
                for (var i = 1; i < obj.points.length - 1; i++) {
                    var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                    ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
                }
                ctx.lineTo(obj.points[obj.points.length - 1].x, obj.points[obj.points.length - 1].y);
                ctx.stroke();
            } else if (obj.x1 !== undefined) {
                ctx.beginPath();
                ctx.moveTo(obj.x1, obj.y1);
                ctx.lineTo(obj.x2, obj.y2);
                ctx.stroke();
            }
            ctx.setLineDash([]);
        } else if (obj.type === 'squiggly') {
            ctx.strokeStyle = obj.color || '#333';
            ctx.lineWidth = 3 * scale;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            if (obj.points && obj.points.length >= 2) {
                ctx.beginPath();
                ctx.moveTo(obj.points[0].x, obj.points[0].y);
                for (var i = 1; i < obj.points.length - 1; i++) {
                    var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                    ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
                }
                ctx.lineTo(obj.points[obj.points.length - 1].x, obj.points[obj.points.length - 1].y);
                ctx.stroke();
            } else if (obj.x1 !== undefined) {
                var dx = obj.x2 - obj.x1;
                var dy = obj.y2 - obj.y1;
                var distance = Math.sqrt(dx * dx + dy * dy);
                var angle = Math.atan2(dy, dx);
                var numWaves = Math.max(2, Math.floor(distance / 15));
                ctx.translate(obj.x1, obj.y1);
                ctx.rotate(angle);
                ctx.beginPath();
                ctx.moveTo(0, 0);
                for (var i = 0; i < numWaves; i++) {
                    var segmentEnd = ((i + 1) / numWaves) * distance;
                    var midX = ((i / numWaves) * distance + segmentEnd) / 2;
                    ctx.quadraticCurveTo(midX, (i % 2 === 0 ? 1 : -1) * 6 * scale, segmentEnd, 0);
                }
                ctx.stroke();
            }
        } else if (obj.type === 'arrow') {
            ctx.strokeStyle = obj.color || '#333';
            ctx.fillStyle = obj.color || '#333';
            ctx.lineWidth = 3 * scale;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            var x2, y2, angle;
            var arrowHeadLen = 15 * scale;
            if (obj.points && obj.points.length >= 2) {
                ctx.beginPath();
                ctx.moveTo(obj.points[0].x, obj.points[0].y);
                for (var i = 1; i < obj.points.length - 1; i++) {
                    var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                    ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
                }
                var last = obj.points[obj.points.length - 1];
                ctx.lineTo(last.x, last.y);
                ctx.stroke();
                x2 = last.x;
                y2 = last.y;
                var secondLast = obj.points[obj.points.length - 2];
                angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
            } else if (obj.x1 !== undefined) {
                ctx.beginPath();
                ctx.moveTo(obj.x1, obj.y1);
                ctx.lineTo(obj.x2, obj.y2);
                ctx.stroke();
                x2 = obj.x2;
                y2 = obj.y2;
                angle = Math.atan2(obj.y2 - obj.y1, obj.x2 - obj.x1);
            }
            if (x2 !== undefined) {
                ctx.beginPath();
                ctx.moveTo(x2, y2);
                ctx.lineTo(x2 - arrowHeadLen * Math.cos(angle - Math.PI/6), y2 - arrowHeadLen * Math.sin(angle - Math.PI/6));
                ctx.lineTo(x2 - arrowHeadLen * Math.cos(angle + Math.PI/6), y2 - arrowHeadLen * Math.sin(angle + Math.PI/6));
                ctx.closePath();
                ctx.fill();
            }
        } else if (obj.type === 'net') {
            ctx.translate(obj.x, obj.y);
            ctx.rotate((obj.rotation || 0) * Math.PI / 180);
            var frameColor = obj.color || '#c41e3a';
            var netWidth = 48 * scale;
            var netDepth = 16 * scale;
            ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.strokeStyle = frameColor;
            ctx.lineWidth = 3 * scale;
            ctx.beginPath();
            ctx.moveTo(-netWidth/2, 0);
            ctx.lineTo(netWidth/2, 0);
            ctx.lineTo(netWidth/2 - 4 * scale, -netDepth);
            ctx.quadraticCurveTo(0, -netDepth - 8 * scale, -netWidth/2 + 4 * scale, -netDepth);
            ctx.lineTo(-netWidth/2, 0);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
            ctx.strokeStyle = '#aaa';
            ctx.lineWidth = 0.5 * scale;
            for (var i = -2; i <= 2; i++) {
                var meshX = (netWidth/5) * i;
                ctx.beginPath();
                ctx.moveTo(meshX * 0.85, 0);
                ctx.lineTo(meshX * 0.6, -netDepth);
                ctx.stroke();
            }
            ctx.strokeStyle = frameColor;
            ctx.lineWidth = 4 * scale;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(-netWidth/2, 2 * scale);
            ctx.lineTo(-netWidth/2, -2 * scale);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(netWidth/2, 2 * scale);
            ctx.lineTo(netWidth/2, -2 * scale);
            ctx.stroke();
            ctx.lineWidth = 3 * scale;
            ctx.beginPath();
            ctx.moveTo(-netWidth/2, 0);
            ctx.lineTo(netWidth/2, 0);
            ctx.stroke();
        } else if (obj.type === 'mininet') {
            ctx.translate(obj.x, obj.y);
            ctx.rotate((obj.rotation || 0) * Math.PI / 180);
            var frameColor = obj.color || '#c41e3a';
            var netWidth = 32 * scale;
            var netDepth = 12 * scale;
            ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.strokeStyle = frameColor;
            ctx.lineWidth = 2 * scale;
            ctx.beginPath();
            ctx.moveTo(-netWidth/2, 0);
            ctx.lineTo(netWidth/2, 0);
            ctx.lineTo(netWidth/2 - 3 * scale, -netDepth);
            ctx.quadraticCurveTo(0, -netDepth - 5 * scale, -netWidth/2 + 3 * scale, -netDepth);
            ctx.lineTo(-netWidth/2, 0);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
        } else if (obj.type === 'tire') {
            ctx.strokeStyle = obj.color || '#333';
            ctx.lineWidth = 6 * scale;
            ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
            ctx.beginPath();
            ctx.arc(obj.x, obj.y, 12 * scale, 0, 2 * Math.PI);
            ctx.fill();
            ctx.stroke();
        } else if (obj.type === 'stick') {
            ctx.translate(obj.x, obj.y);
            ctx.rotate((obj.rotation || 0) * Math.PI / 180);
            ctx.strokeStyle = obj.color || '#8B4513';
            ctx.lineWidth = 5 * scale;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(0, -22 * scale);
            ctx.lineTo(0, 12 * scale);
            ctx.stroke();
            ctx.lineWidth = 6 * scale;
            ctx.beginPath();
            ctx.moveTo(0, 12 * scale);
            ctx.quadraticCurveTo(8 * scale, 16 * scale, 14 * scale, 12 * scale);
            ctx.stroke();
        } else if (obj.type === 'text') {
            ctx.translate(obj.x, obj.y);
            ctx.rotate((obj.rotation || 0) * Math.PI / 180);
            ctx.fillStyle = obj.color || '#000';
            ctx.font = 'bold ' + Math.round(14 * scale) + 'px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(obj.text, 0, 0);
        } else if (obj.type === 'number') {
            ctx.translate(obj.x, obj.y);
            ctx.rotate((obj.rotation || 0) * Math.PI / 180);
            ctx.fillStyle = '#fff';
            ctx.beginPath();
            ctx.arc(0, 0, 14 * scale, 0, 2 * Math.PI);
            ctx.fill();
            ctx.strokeStyle = obj.color || '#000';
            ctx.lineWidth = 2 * scale;
            ctx.stroke();
            ctx.fillStyle = obj.color || '#000';
            ctx.font = 'bold ' + Math.round(16 * scale) + 'px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(obj.value, 0, 0);
        } else if (obj.type === 'pucks') {
            ctx.fillStyle = '#000';
            var puckSpacing = 8 * scale;
            var puckRadius = 5 * scale;
            var positions = [
                {x: -puckSpacing, y: -puckSpacing}, {x: puckSpacing, y: -puckSpacing},
                {x: -puckSpacing, y: puckSpacing}, {x: puckSpacing, y: puckSpacing}, {x: 0, y: 0}
            ];
            positions.forEach(function(pos) {
                ctx.beginPath();
                ctx.arc(obj.x + pos.x, obj.y + pos.y, puckRadius, 0, 2 * Math.PI);
                ctx.fill();
            });
        } else if (obj.type === 'skating_forward') {
            drawSkatingLine(ctx, obj, false, true, false, false, scale);
        } else if (obj.type === 'skating_backward') {
            drawSkatingLine(ctx, obj, true, true, false, false, scale);
        } else if (obj.type === 'skating_lateral') {
            drawLateralSkating(ctx, obj, scale);
        } else if (obj.type === 'skating_ccuts') {
            drawCCutsSkating(ctx, obj, scale);
        } else if (obj.type === 'skating_forward_puck') {
            drawSkatingLine(ctx, obj, false, true, true, false, scale);
        } else if (obj.type === 'skating_backward_puck') {
            drawSkatingLine(ctx, obj, true, true, true, true, scale);
        } else if (obj.type === 'pass') {
            drawPassLine(ctx, obj, scale);
        } else if (obj.type === 'shot') {
            drawShotLine(ctx, obj, scale);
        } else if (obj.type === 'freehand' || obj.type === 'freehand_arrow' || obj.type === 'freehand_dashed' || obj.type === 'freehand_skating') {
            if (obj.points && obj.points.length >= 2) {
                var color = obj.color || '#333';
                ctx.strokeStyle = color;
                ctx.lineWidth = 3 * scale;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                if (obj.type === 'freehand_dashed') {
                    ctx.setLineDash([10 * scale, 6 * scale]);
                }
                ctx.beginPath();
                ctx.moveTo(obj.points[0].x, obj.points[0].y);
                for (var i = 1; i < obj.points.length - 1; i++) {
                    var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                    ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
                }
                var last = obj.points[obj.points.length - 1];
                ctx.lineTo(last.x, last.y);
                ctx.stroke();
                if (obj.type === 'freehand_dashed') {
                    ctx.setLineDash([]);
                }
                if ((obj.type === 'freehand_arrow' || obj.type === 'freehand_skating') && obj.points.length >= 2) {
                    var secondLast = obj.points[obj.points.length - 2];
                    var angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                    var headlen = 12 * scale;
                    ctx.fillStyle = color;
                    ctx.beginPath();
                    ctx.moveTo(last.x, last.y);
                    ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 6), last.y - headlen * Math.sin(angle - Math.PI / 6));
                    ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 6), last.y - headlen * Math.sin(angle + Math.PI / 6));
                    ctx.closePath();
                    ctx.fill();
                }
            }
        }

        ctx.restore();
    }

    function drawSkatingLine(ctx, obj, dashed, hasArrow, hasPuck, backwardArrow, scale) {
        scale = scale || 1;
        var color = obj.color || '#333';
        ctx.strokeStyle = color;
        ctx.lineWidth = 3 * scale;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        if (dashed) {
            ctx.setLineDash([12 * scale, 4 * scale, 4 * scale, 4 * scale]);
        }
        var x1, y1, x2, y2, angle;
        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);
            for (var i = 1; i < obj.points.length - 1; i++) {
                var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
            }
            var last = obj.points[obj.points.length - 1];
            ctx.lineTo(last.x, last.y);
            ctx.stroke();
            x1 = obj.points[0].x;
            y1 = obj.points[0].y;
            x2 = last.x;
            y2 = last.y;
            var secondLast = obj.points[obj.points.length - 2];
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
            if (backwardArrow) {
                angle = Math.atan2(secondLast.y - last.y, secondLast.x - last.x);
            }
        } else if (obj.x1 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(obj.x1, obj.y1);
            ctx.lineTo(obj.x2, obj.y2);
            ctx.stroke();
            x1 = obj.x1;
            y1 = obj.y1;
            x2 = obj.x2;
            y2 = obj.y2;
            angle = backwardArrow ? Math.atan2(y1 - y2, x1 - x2) : Math.atan2(y2 - y1, x2 - x1);
        }
        if (dashed) {
            ctx.setLineDash([]);
        }
        if (hasArrow && x2 !== undefined) {
            var headlen = 12 * scale;
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
        if (hasPuck && x1 !== undefined) {
            ctx.fillStyle = '#000';
            ctx.beginPath();
            ctx.arc(x1, y1, 6 * scale, 0, 2 * Math.PI);
            ctx.fill();
        }
    }

    function drawLateralSkating(ctx, obj, scale) {
        scale = scale || 1;
        var color = obj.color || '#10b981';
        ctx.strokeStyle = color;
        ctx.lineWidth = 3 * scale;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);
            for (var i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x, obj.points[i].y);
            }
            ctx.stroke();
        } else if (obj.x1 !== undefined) {
            var dx = obj.x2 - obj.x1;
            var dy = obj.y2 - obj.y1;
            var distance = Math.sqrt(dx * dx + dy * dy);
            var angle = Math.atan2(dy, dx);
            var perpAngle = angle + Math.PI / 2;
            var segments = Math.max(4, Math.floor(distance / 20));
            var zigzagHeight = 8 * scale;
            ctx.beginPath();
            ctx.moveTo(obj.x1, obj.y1);
            for (var i = 1; i <= segments; i++) {
                var t = i / segments;
                var px = obj.x1 + dx * t;
                var py = obj.y1 + dy * t;
                var offset = (i % 2 === 1) ? zigzagHeight : -zigzagHeight;
                ctx.lineTo(px + Math.cos(perpAngle) * offset, py + Math.sin(perpAngle) * offset);
            }
            ctx.lineTo(obj.x2, obj.y2);
            ctx.stroke();
        }
    }

    function drawCCutsSkating(ctx, obj, scale) {
        scale = scale || 1;
        var color = obj.color || '#8b5cf6';
        ctx.strokeStyle = color;
        ctx.lineWidth = 3 * scale;
        ctx.lineCap = 'round';
        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);
            for (var i = 1; i < obj.points.length - 1; i++) {
                var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
            }
            var last = obj.points[obj.points.length - 1];
            ctx.lineTo(last.x, last.y);
            ctx.stroke();
        } else if (obj.x1 !== undefined) {
            var dx = obj.x2 - obj.x1;
            var dy = obj.y2 - obj.y1;
            var distance = Math.sqrt(dx * dx + dy * dy);
            var angle = Math.atan2(dy, dx);
            var numCuts = Math.max(3, Math.floor(distance / 30));
            var cutWidth = distance / numCuts;
            var cutHeight = 12 * scale;
            ctx.save();
            ctx.translate(obj.x1, obj.y1);
            ctx.rotate(angle);
            ctx.beginPath();
            ctx.moveTo(0, 0);
            for (var i = 0; i < numCuts; i++) {
                var startX = i * cutWidth;
                var endX = (i + 1) * cutWidth;
                var direction = (i % 2 === 0) ? 1 : -1;
                ctx.quadraticCurveTo(startX + cutWidth / 2, direction * cutHeight, endX, 0);
            }
            ctx.stroke();
            ctx.restore();
        }
    }

    function drawPassLine(ctx, obj, scale) {
        scale = scale || 1;
        var color = obj.color || '#0033a0';
        ctx.strokeStyle = color;
        ctx.lineWidth = 3 * scale;
        ctx.lineCap = 'round';
        ctx.setLineDash([10 * scale, 5 * scale]);
        var x2, y2, angle;
        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);
            for (var i = 1; i < obj.points.length - 1; i++) {
                var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
            }
            var last = obj.points[obj.points.length - 1];
            ctx.lineTo(last.x, last.y);
            ctx.stroke();
            x2 = last.x;
            y2 = last.y;
            var secondLast = obj.points[obj.points.length - 2];
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
        } else if (obj.x1 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(obj.x1, obj.y1);
            ctx.lineTo(obj.x2, obj.y2);
            ctx.stroke();
            x2 = obj.x2;
            y2 = obj.y2;
            angle = Math.atan2(obj.y2 - obj.y1, obj.x2 - obj.x1);
        }
        ctx.setLineDash([]);
        if (x2 !== undefined) {
            var headlen = 14 * scale;
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
            ctx.stroke();
        }
    }

    function drawShotLine(ctx, obj, scale) {
        scale = scale || 1;
        var color = obj.color || '#c41e3a';
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = 5 * scale;
        ctx.lineCap = 'round';
        var x2, y2, angle;
        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);
            for (var i = 1; i < obj.points.length - 1; i++) {
                var xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                var yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
            }
            var last = obj.points[obj.points.length - 1];
            ctx.lineTo(last.x, last.y);
            ctx.stroke();
            x2 = last.x;
            y2 = last.y;
            var secondLast = obj.points[obj.points.length - 2];
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
        } else if (obj.x1 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(obj.x1, obj.y1);
            ctx.lineTo(obj.x2, obj.y2);
            ctx.stroke();
            x2 = obj.x2;
            y2 = obj.y2;
            angle = Math.atan2(obj.y2 - obj.y1, obj.x2 - obj.x1);
        }
        if (x2 !== undefined) {
            var headlen = 18 * scale;
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 5), y2 - headlen * Math.sin(angle - Math.PI / 5));
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 5), y2 - headlen * Math.sin(angle + Math.PI / 5));
            ctx.closePath();
            ctx.fill();
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>
