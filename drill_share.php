<?php
/**
 * Public Drill Share Page
 * Allows anyone with a valid share token to view a drill without logging in.
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/image_helper.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

$token = $_GET['token'] ?? '';
$drill = null;

// Validate token format (must be 64 hex chars)
if (!empty($token) && preg_match('/^[a-f0-9]{64}$/', $token) && $db_connected) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, dc.name as category_name, u.first_name, u.last_name
            FROM drills d
            LEFT JOIN drill_categories dc ON d.category_id = dc.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.share_token = ?
        ");
        $stmt->execute([$token]);
        $drill = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($drill) {
            $drill = decryptUserRow($drill);
        }
    } catch (PDOException $e) {
        error_log("Error fetching shared drill: " . $e->getMessage());
    }
}

// Fetch center ice logo URL
$centerLogoUrl = '';
if ($drill) {
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

// Extract ice view from diagram data
$allowedIceViews = ['full', 'left-zone', 'right-zone', 'center'];
$drillIceView = 'full';
if ($drill && !empty($drill['diagram_data'])) {
    $diagramParsed = json_decode($drill['diagram_data'], true);
    if (is_array($diagramParsed) && isset($diagramParsed['iceView'])) {
        $parsedIceView = $diagramParsed['iceView'];
        if (in_array($parsedIceView, $allowedIceViews, true)) {
            $drillIceView = $parsedIceView;
        }
    }
}

$coachName = $drill ? htmlspecialchars(trim(($drill['first_name'] ?? '') . ' ' . ($drill['last_name'] ?? ''))) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $drill ? htmlspecialchars($drill['title']) . ' - ' : ''; ?>Shared Drill</title>
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

        .card-actions { display: flex; gap: 10px; }

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

        .category-badge {
            background: var(--primary);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .coach-info {
            color: var(--text-dim);
            font-size: 14px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 24px;
            height: 45px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background: var(--border);
            color: #E0E0E0;
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover { background: var(--border-light); border-color: var(--primary); transform: translateY(-1px); }

        /* Diagram */
        .drill-diagram-view {
            display: flex;
            justify-content: center;
        }

        .ihs-diagram-container {
            width: 100%;
            max-width: 900px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
            border: 3px solid #0033a0;
            border-radius: 12px;
            padding: 20px;
            overflow: hidden;
        }

        .ihs-drill-image {
            max-width: 100%;
            max-height: 500px;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
        }

        .ice-rink-canvas.view-only {
            width: 100%;
            max-width: 900px;
            aspect-ratio: 200/85;
            min-height: 350px;
            background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
            border: 3px solid #0033a0;
            border-radius: 80px;
            position: relative;
            overflow: hidden;
            transition: aspect-ratio 0.3s ease-in-out;
        }

        .ice-rink-canvas.view-only[data-ice-view="full"] {
            aspect-ratio: 200/85;
            border-radius: 80px;
        }

        .ice-rink-canvas.view-only[data-ice-view="left-zone"],
        .ice-rink-canvas.view-only[data-ice-view="right-zone"] {
            aspect-ratio: 100/85;
            border-radius: 80px;
        }

        .ice-rink-canvas.view-only[data-ice-view="center"] {
            aspect-ratio: 72/85;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            border-radius: 20px;
        }

        .ice-rink-canvas.view-only canvas {
            width: 100%;
            height: 100%;
            border-radius: inherit;
        }

        /* Details */
        .drill-details-grid {
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

        /* Notification toast */
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.9);
            color: #fff;
        }

        @media (max-width: 768px) {
            .page-title { font-size: 20px; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .card-actions { flex-wrap: wrap; }
            .ice-rink-canvas.view-only { min-height: 200px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($drill): ?>
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-hockey-puck"></i> <?php echo htmlspecialchars($drill['title']); ?>
                </h1>
                <div class="page-meta">
                    <?php if (!empty($drill['category_name'])): ?>
                        <span class="category-badge"><?php echo htmlspecialchars($drill['category_name']); ?></span>
                    <?php endif; ?>
                    <?php if ($coachName): ?>
                        <span class="coach-info"><i class="fas fa-user"></i> <?php echo $coachName; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Drill Diagram -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-drafting-compass"></i> Drill Diagram</h3>
                    <div class="card-actions">
                        <button class="btn btn-secondary" onclick="exportDiagram()">
                            <i class="fas fa-download"></i> Export Image
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="drill-diagram-view">
                        <?php if (!empty($drill['custom_image'])): ?>
                            <!-- IHS Imported Image -->
                            <div class="ihs-diagram-container">
                                <img src="<?php echo htmlspecialchars(resolveRustfsUrl($pdo, $drill['custom_image'])); ?>" alt="<?php echo htmlspecialchars($drill['title']); ?> Diagram" class="ihs-drill-image" id="drill-ihs-image">
                            </div>
                        <?php else: ?>
                            <!-- Drill Draw Canvas -->
                            <div class="ice-rink-canvas view-only" id="drill-view-canvas" data-ice-view="<?php echo htmlspecialchars($drillIceView); ?>" data-center-logo="<?php echo htmlspecialchars($centerLogoUrl); ?>">
                                <canvas id="drill-view-canvas-el"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Drill Details -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Drill Details</h3>
                </div>
                <div class="card-body">
                    <div class="drill-details-grid">
                        <div class="detail-section">
                            <h4>Description</h4>
                            <p><?php echo nl2br(htmlspecialchars($drill['description'] ?? 'No description available.')); ?></p>
                        </div>

                        <?php if (!empty($drill['setup'])): ?>
                        <div class="detail-section">
                            <h4><i class="fas fa-cog"></i> Setup</h4>
                            <p><?php echo nl2br(htmlspecialchars($drill['setup'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($drill['coaching_points'])): ?>
                        <div class="detail-section">
                            <h4><i class="fas fa-bullseye"></i> Coaching Points</h4>
                            <p><?php echo nl2br(htmlspecialchars($drill['coaching_points'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($drill['progression'])): ?>
                        <div class="detail-section">
                            <h4><i class="fas fa-level-up-alt"></i> Progression</h4>
                            <p><?php echo nl2br(htmlspecialchars($drill['progression'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($drill['video_url'])):
                            $videoUrl = $drill['video_url'];
                            $youtubeId = null;
                            if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $matches)) {
                                $youtubeId = $matches[1];
                            }
                        ?>
                        <div class="detail-section">
                            <h4><i class="fas fa-video"></i> Video</h4>
                            <?php if ($youtubeId): ?>
                            <div style="position: relative; width: 100%; max-width: 640px; padding-bottom: 56.25%; height: 0; overflow: hidden; margin-bottom: 12px; border-radius: 8px; background: var(--bg-main);">
                                <iframe
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 8px;"
                                    src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeId); ?>"
                                    title="Drill Video"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen>
                                </iframe>
                            </div>
                            <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($youtubeId); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" style="margin-top: 8px;">
                                <i class="fab fa-youtube"></i> Open on YouTube
                            </a>
                            <?php else: ?>
                            <a href="<?php echo htmlspecialchars($videoUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                                <i class="fas fa-play-circle"></i> Watch Video
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($drill['video_upload_path'])):
                            $videoPath = $drill['video_upload_path'];
                            $videoExt = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
                            $videoMimeTypes = [
                                'mp4' => 'video/mp4',
                                'webm' => 'video/webm',
                                'ogg' => 'video/ogg',
                                'ogv' => 'video/ogg'
                            ];
                            $videoMimeType = $videoMimeTypes[$videoExt] ?? 'video/mp4';
                        ?>
                        <div class="detail-section">
                            <h4><i class="fas fa-film"></i> Uploaded Video</h4>
                            <video controls style="width: 100%; max-width: 640px; border-radius: 8px; background: var(--bg-main);">
                                <source src="<?php echo htmlspecialchars($videoPath); ?>" type="<?php echo $videoMimeType; ?>">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                        <?php endif; ?>

                        <div class="detail-section meta-info">
                            <div class="meta-item">
                                <span class="meta-label">Created</span>
                                <span class="meta-value"><?php echo date('F j, Y', strtotime($drill['created_at'])); ?></span>
                            </div>
                            <?php if (!empty($drill['updated_at']) && $drill['updated_at'] !== $drill['created_at']): ?>
                            <div class="meta-item">
                                <span class="meta-label">Last Updated</span>
                                <span class="meta-value"><?php echo date('F j, Y', strtotime($drill['updated_at'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Error: Drill Not Found -->
            <div class="content-card">
                <div class="card-body">
                    <div class="error-container">
                        <i class="fas fa-unlink"></i>
                        <h2>Drill Not Found</h2>
                        <p>This drill link is invalid or has expired. Please check the URL and try again.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="share-footer">
        <p>Powered by <a href="/">Arctic Wolves</a></p>
    </div>

    <?php if ($drill): ?>
    <script src="js/ice_canvas.js"></script>
    <script>
    // Drill diagram viewer for public share page
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

    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('drill-view-canvas');
        const canvas = document.getElementById('drill-view-canvas-el');
        if (!container || !canvas) return;

        let dpr = window.devicePixelRatio || 1;
        let cssWidth = container.offsetWidth;
        let cssHeight = container.offsetHeight;
        canvas.width = cssWidth * dpr;
        canvas.height = cssHeight * dpr;
        canvas.style.width = cssWidth + 'px';
        canvas.style.height = cssHeight + 'px';

        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        const diagramDataRaw = <?php echo json_encode($drill['diagram_data'] ?? ''); ?>;
        const centerLogoUrl = container.dataset.centerLogo || '';

        let diagramObjects = [];
        let sourceWidth = cssWidth;
        let sourceHeight = cssHeight;
        let iceView = 'full';

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
            console.log('No diagram data to parse');
        }

        container.setAttribute('data-ice-view', iceView);

        function renderDrill() {
            drawViewRink(ctx, cssWidth, cssHeight, iceView);

            if (diagramObjects.length > 0) {
                const scaleX = cssWidth / sourceWidth;
                const scaleY = cssHeight / sourceHeight;
                const uniformScale = Math.min(scaleX, scaleY);
                const offsetX = (cssWidth - sourceWidth * uniformScale) / 2;
                const offsetY = (cssHeight - sourceHeight * uniformScale) / 2;

                diagramObjects.forEach(obj => {
                    const scaledObj = scaleObjectForView(obj, uniformScale, offsetX, offsetY);
                    drawObject(ctx, scaledObj, uniformScale);
                });
            }
        }

        function initializeAndRender() {
            dpr = window.devicePixelRatio || 1;
            cssWidth = container.offsetWidth;
            cssHeight = container.offsetHeight;
            canvas.width = cssWidth * dpr;
            canvas.height = cssHeight * dpr;
            canvas.style.width = cssWidth + 'px';
            canvas.style.height = cssHeight + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            renderDrill();
        }

        function scaleObjectForView(obj, scale, offsetX, offsetY) {
            const scaled = { ...obj };

            if (scaled.x !== undefined) scaled.x = scaled.x * scale + offsetX;
            if (scaled.y !== undefined) scaled.y = scaled.y * scale + offsetY;
            if (scaled.x1 !== undefined) scaled.x1 = scaled.x1 * scale + offsetX;
            if (scaled.y1 !== undefined) scaled.y1 = scaled.y1 * scale + offsetY;
            if (scaled.x2 !== undefined) scaled.x2 = scaled.x2 * scale + offsetX;
            if (scaled.y2 !== undefined) scaled.y2 = scaled.y2 * scale + offsetY;

            if (scaled.points && Array.isArray(scaled.points)) {
                scaled.points = scaled.points.map(pt => ({
                    x: pt.x * scale + offsetX,
                    y: pt.y * scale + offsetY
                }));
            }

            return scaled;
        }

        function waitForLayoutAndRender() {
            requestAnimationFrame(function() {
                requestAnimationFrame(initializeAndRender);
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
                console.warn('Failed to load center logo image');
                centerLogoLoaded = false;
                waitForLayoutAndRender();
            };
            centerLogoImage.src = centerLogoUrl;
        } else {
            waitForLayoutAndRender();
        }

        window.addEventListener('resize', function() {
            initializeAndRender();
        });
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
            console.warn('IceCanvasRenderer not loaded - using basic fallback for drill view');
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

                const logoX = (w - logoWidth) / 2;
                const logoY = (h - logoHeight) / 2;

                ctx.drawImage(centerLogoImage, logoX, logoY, logoWidth, logoHeight);
            } else {
                ctx.fillStyle = '#6B46C1';
                ctx.font = 'bold 48px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('ARCTIC WOLVES', w/2, h/2 - 15);
                ctx.font = '24px Inter, sans-serif';
                ctx.fillText('HOCKEY', w/2, h/2 + 25);
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
                for (let i = 1; i < obj.points.length - 1; i++) {
                    const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
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
                for (let i = 1; i < obj.points.length - 1; i++) {
                    const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
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
                for (let i = 1; i < obj.points.length - 1; i++) {
                    const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                    ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
                }
                ctx.lineTo(obj.points[obj.points.length - 1].x, obj.points[obj.points.length - 1].y);
                ctx.stroke();
            } else if (obj.x1 !== undefined) {
                const dx = obj.x2 - obj.x1;
                const dy = obj.y2 - obj.y1;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const angle = Math.atan2(dy, dx);
                const numWaves = Math.max(2, Math.floor(distance / 15));

                ctx.translate(obj.x1, obj.y1);
                ctx.rotate(angle);
                ctx.beginPath();
                ctx.moveTo(0, 0);
                for (let i = 0; i < numWaves; i++) {
                    const segmentEnd = ((i + 1) / numWaves) * distance;
                    const midX = ((i / numWaves) * distance + segmentEnd) / 2;
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

            let x2, y2, angle;
            const arrowHeadLen = 15 * scale;

            if (obj.points && obj.points.length >= 2) {
                ctx.beginPath();
                ctx.moveTo(obj.points[0].x, obj.points[0].y);
                for (let i = 1; i < obj.points.length - 1; i++) {
                    const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                    ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
                }
                const last = obj.points[obj.points.length - 1];
                ctx.lineTo(last.x, last.y);
                ctx.stroke();

                x2 = last.x;
                y2 = last.y;
                const secondLast = obj.points[obj.points.length - 2];
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

            const frameColor = obj.color || '#c41e3a';
            const netWidth = 48 * scale;
            const netDepth = 16 * scale;

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
            for (let i = -2; i <= 2; i++) {
                const meshX = (netWidth/5) * i;
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

            const frameColor = obj.color || '#c41e3a';
            const netWidth = 32 * scale;
            const netDepth = 12 * scale;

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
            const puckSpacing = 8 * scale;
            const puckRadius = 5 * scale;
            const positions = [
                {x: -puckSpacing, y: -puckSpacing}, {x: puckSpacing, y: -puckSpacing},
                {x: -puckSpacing, y: puckSpacing}, {x: puckSpacing, y: puckSpacing}, {x: 0, y: 0}
            ];
            positions.forEach(pos => {
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
                const color = obj.color || '#333';
                ctx.strokeStyle = color;
                ctx.lineWidth = 3 * scale;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';

                if (obj.type === 'freehand_dashed') {
                    ctx.setLineDash([10 * scale, 6 * scale]);
                }

                ctx.beginPath();
                ctx.moveTo(obj.points[0].x, obj.points[0].y);

                for (let i = 1; i < obj.points.length - 1; i++) {
                    const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                    const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                    ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
                }

                const last = obj.points[obj.points.length - 1];
                ctx.lineTo(last.x, last.y);
                ctx.stroke();

                if (obj.type === 'freehand_dashed') {
                    ctx.setLineDash([]);
                }

                if ((obj.type === 'freehand_arrow' || obj.type === 'freehand_skating') && obj.points.length >= 2) {
                    const secondLast = obj.points[obj.points.length - 2];
                    const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                    const headlen = 12 * scale;

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
        const color = obj.color || '#333';
        ctx.strokeStyle = color;
        ctx.lineWidth = 3 * scale;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (dashed) {
            ctx.setLineDash([12 * scale, 4 * scale, 4 * scale, 4 * scale]);
        }

        let x1, y1, x2, y2, angle;

        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);

            for (let i = 1; i < obj.points.length - 1; i++) {
                const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
            }

            const last = obj.points[obj.points.length - 1];
            ctx.lineTo(last.x, last.y);
            ctx.stroke();

            x1 = obj.points[0].x;
            y1 = obj.points[0].y;
            x2 = last.x;
            y2 = last.y;

            const secondLast = obj.points[obj.points.length - 2];
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
            const headlen = 12 * scale;
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
        const color = obj.color || '#10b981';
        ctx.strokeStyle = color;
        ctx.lineWidth = 3 * scale;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);

            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x, obj.points[i].y);
            }
            ctx.stroke();
        } else if (obj.x1 !== undefined) {
            const dx = obj.x2 - obj.x1;
            const dy = obj.y2 - obj.y1;
            const distance = Math.sqrt(dx * dx + dy * dy);
            const angle = Math.atan2(dy, dx);
            const perpAngle = angle + Math.PI / 2;
            const segments = Math.max(4, Math.floor(distance / 20));
            const zigzagHeight = 8 * scale;

            ctx.beginPath();
            ctx.moveTo(obj.x1, obj.y1);

            for (let i = 1; i <= segments; i++) {
                const t = i / segments;
                const px = obj.x1 + dx * t;
                const py = obj.y1 + dy * t;
                const offset = (i % 2 === 1) ? zigzagHeight : -zigzagHeight;
                ctx.lineTo(px + Math.cos(perpAngle) * offset, py + Math.sin(perpAngle) * offset);
            }

            ctx.lineTo(obj.x2, obj.y2);
            ctx.stroke();
        }
    }

    function drawCCutsSkating(ctx, obj, scale) {
        scale = scale || 1;
        const color = obj.color || '#8b5cf6';
        ctx.strokeStyle = color;
        ctx.lineWidth = 3 * scale;
        ctx.lineCap = 'round';

        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);

            for (let i = 1; i < obj.points.length - 1; i++) {
                const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
            }

            const last = obj.points[obj.points.length - 1];
            ctx.lineTo(last.x, last.y);
            ctx.stroke();
        } else if (obj.x1 !== undefined) {
            const dx = obj.x2 - obj.x1;
            const dy = obj.y2 - obj.y1;
            const distance = Math.sqrt(dx * dx + dy * dy);
            const angle = Math.atan2(dy, dx);
            const numCuts = Math.max(3, Math.floor(distance / 30));
            const cutWidth = distance / numCuts;
            const cutHeight = 12 * scale;

            ctx.save();
            ctx.translate(obj.x1, obj.y1);
            ctx.rotate(angle);

            ctx.beginPath();
            ctx.moveTo(0, 0);

            for (let i = 0; i < numCuts; i++) {
                const startX = i * cutWidth;
                const endX = (i + 1) * cutWidth;
                const direction = (i % 2 === 0) ? 1 : -1;
                ctx.quadraticCurveTo(startX + cutWidth / 2, direction * cutHeight, endX, 0);
            }

            ctx.stroke();
            ctx.restore();
        }
    }

    function drawPassLine(ctx, obj, scale) {
        scale = scale || 1;
        const color = obj.color || '#0033a0';
        ctx.strokeStyle = color;
        ctx.lineWidth = 3 * scale;
        ctx.lineCap = 'round';
        ctx.setLineDash([10 * scale, 5 * scale]);

        let x2, y2, angle;

        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);

            for (let i = 1; i < obj.points.length - 1; i++) {
                const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
            }

            const last = obj.points[obj.points.length - 1];
            ctx.lineTo(last.x, last.y);
            ctx.stroke();

            x2 = last.x;
            y2 = last.y;
            const secondLast = obj.points[obj.points.length - 2];
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
            const headlen = 14 * scale;
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
        const color = obj.color || '#c41e3a';
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = 5 * scale;
        ctx.lineCap = 'round';

        let x2, y2, angle;

        if (obj.points && obj.points.length >= 2) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x, obj.points[0].y);

            for (let i = 1; i < obj.points.length - 1; i++) {
                const xc = (obj.points[i].x + obj.points[i + 1].x) / 2;
                const yc = (obj.points[i].y + obj.points[i + 1].y) / 2;
                ctx.quadraticCurveTo(obj.points[i].x, obj.points[i].y, xc, yc);
            }

            const last = obj.points[obj.points.length - 1];
            ctx.lineTo(last.x, last.y);
            ctx.stroke();

            x2 = last.x;
            y2 = last.y;
            const secondLast = obj.points[obj.points.length - 2];
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
            const headlen = 18 * scale;
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 5), y2 - headlen * Math.sin(angle - Math.PI / 5));
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 5), y2 - headlen * Math.sin(angle + Math.PI / 5));
            ctx.closePath();
            ctx.fill();
        }
    }

    function exportDiagram() {
        const canvas = document.getElementById('drill-view-canvas-el');
        const ihsImage = document.getElementById('drill-ihs-image');
        if (canvas) {
            const link = document.createElement('a');
            link.download = <?php echo json_encode(preg_replace('/[^a-zA-Z0-9]/', '-', $drill['title']) . '-drill.png'); ?>;
            link.href = canvas.toDataURL('image/png');
            link.click();
        } else if (ihsImage) {
            const link = document.createElement('a');
            link.download = <?php echo json_encode(preg_replace('/[^a-zA-Z0-9]/', '-', $drill['title']) . '-drill.png'); ?>;
            link.href = ihsImage.src;
            link.target = '_blank';
            link.click();
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>
