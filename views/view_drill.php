<!-- View Drill - Shareable Drill View -->
<?php
$drillId = $_GET['id'] ?? null;
$isShared = isset($_GET['shared']);
$drill = null;

// Fetch center ice logo URL from theme settings for drill display
// Uses single query with COALESCE for efficiency
$centerLogoUrl = '';
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
        $centerLogoUrl = $logoResult['logo_url'];
    }
} catch (PDOException $e) {
    error_log("Error fetching center ice logo URL: " . $e->getMessage());
}

// Validate drillId is numeric to prevent injection
if ($drillId !== null && !ctype_digit((string)$drillId)) {
    $drillId = null;
}

if ($drillId) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, dc.name as category_name, u.first_name, u.last_name
            FROM drills d
            LEFT JOIN drill_categories dc ON d.category_id = dc.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ?
        ");
        $stmt->execute([$drillId]);
        $drill = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching drill: " . $e->getMessage());
    }
}

if (!$drill) {
    echo '<div class="content-card"><div class="card-body"><p class="text-center">Drill not found.</p><a href="?page=drill_library" class="btn btn-primary">Back to Library</a></div></div>';
    return;
}

$coachName = htmlspecialchars(($drill['first_name'] ?? '') . ' ' . ($drill['last_name'] ?? ''));

// Build share URL using validated host from SERVER_NAME (more reliable than HTTP_HOST for security)
// Note: For production, consider using a configured BASE_URL constant
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = htmlspecialchars($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
$shareUrl = $protocol . '://' . $host . '/dashboard.php?page=view_drill&id=' . urlencode($drillId) . '&shared=true';
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-hockey-puck"></i> <?php echo htmlspecialchars($drill['title']); ?>
    </h1>
    <p class="page-description">
        <?php if ($drill['category_name']): ?>
            <span class="category-badge"><?php echo htmlspecialchars($drill['category_name']); ?></span>
        <?php endif; ?>
        <?php if ($coachName): ?>
            <span class="coach-info"><i class="fas fa-user"></i> <?php echo $coachName; ?></span>
        <?php endif; ?>
    </p>
</div>

<div class="view-drill-content">
    <!-- Drill Diagram -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-drafting-compass"></i> Drill Diagram</h3>
            <div class="card-actions">
                <button class="btn btn-secondary" onclick="copyShareLink()">
                    <i class="fas fa-share-alt"></i> Share
                </button>
                <button class="btn btn-secondary" onclick="exportDiagram()">
                    <i class="fas fa-download"></i> Export Image
                </button>
                <?php if (isset($_SESSION['user_id']) && ($drill['created_by'] == $_SESSION['user_id'] || in_array($user_role ?? '', ['admin', 'coach']))): ?>
                <a href="?page=create_drill&edit=<?php echo $drillId; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Drill
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="drill-diagram-view">
                <?php if (!empty($drill['custom_image'])): ?>
                    <!-- IHS Imported Image -->
                    <div class="ihs-diagram-container">
                        <img src="<?php echo htmlspecialchars($drill['custom_image']); ?>" alt="<?php echo htmlspecialchars($drill['title']); ?> Diagram" class="ihs-drill-image" id="drill-ihs-image">
                    </div>
                <?php else: ?>
                    <!-- Drill Draw Canvas -->
                    <div class="ice-rink-canvas view-only" id="drill-view-canvas" data-ice-view="full" data-center-logo="<?php echo htmlspecialchars($centerLogoUrl); ?>">
                        <canvas id="drill-view-canvas-el"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (!empty($drill['ihs_source_url'])): ?>
    <!-- IHS Source -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-external-link-alt"></i> Original Source</h3>
        </div>
        <div class="card-body">
            <p class="source-info">
                <i class="fas fa-info-circle"></i> This drill was imported from Ice Hockey Systems.
            </p>
            <a href="<?php echo htmlspecialchars($drill['ihs_source_url']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                <i class="fas fa-external-link-alt"></i> View Original on IHS
            </a>
        </div>
    </div>
    <?php endif; ?>
    
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
                    // Check if it's a YouTube URL
                    $videoUrl = $drill['video_url'];
                    $youtubeId = null;
                    
                    // Extract YouTube video ID from various URL formats
                    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $matches)) {
                        $youtubeId = $matches[1];
                    }
                ?>
                <div class="detail-section video-section">
                    <h4><i class="fas fa-video"></i> Video</h4>
                    <?php if ($youtubeId): ?>
                    <div class="video-embed-container" style="position: relative; width: 100%; max-width: 640px; padding-bottom: 56.25%; height: 0; overflow: hidden; margin-bottom: 12px; border-radius: 8px; background: var(--bg-main);">
                        <iframe 
                            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 8px;"
                            src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeId); ?>" 
                            title="Drill Video"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                    </div>
                    <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($youtubeId); ?>" target="_blank" class="btn btn-secondary" style="margin-top: 8px;">
                        <i class="fab fa-youtube"></i> Open on YouTube
                    </a>
                    <?php else: ?>
                    <a href="<?php echo htmlspecialchars($videoUrl); ?>" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-play-circle"></i> Watch Video
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($drill['video_upload_path'])): 
                    // Determine MIME type from file extension
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
                <div class="detail-section video-section">
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
                    <?php if ($drill['updated_at'] && $drill['updated_at'] !== $drill['created_at']): ?>
                    <div class="meta-item">
                        <span class="meta-label">Last Updated</span>
                        <span class="meta-value"><?php echo date('F j, Y', strtotime($drill['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Share Link Box -->
    <div class="content-card share-link-card">
        <div class="card-header">
            <h3><i class="fas fa-link"></i> Share This Drill</h3>
        </div>
        <div class="card-body">
            <div class="share-link-wrapper">
                <input type="text" id="share-url-input" class="form-input" value="<?php echo htmlspecialchars($shareUrl); ?>" readonly>
                <button class="btn btn-primary" onclick="copyShareLink()">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
            </div>
            <p class="share-hint"><i class="fas fa-info-circle"></i> Share this link with your team or other coaches to view this drill.</p>
        </div>
    </div>
</div>

<style>
.view-drill-content {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

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

.source-info {
    color: var(--text-dim);
    font-size: 14px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.source-info i {
    color: var(--primary-light);
}

.ice-rink-canvas.view-only {
    width: 100%;
    max-width: 900px;
    aspect-ratio: 2/1;
    min-height: 350px;
    background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
    border: 3px solid #0033a0;
    border-radius: 80px;
    position: relative;
    overflow: hidden;
}

.ice-rink-canvas.view-only canvas {
    width: 100%;
    height: 100%;
    border-radius: 77px;
}

.card-actions {
    display: flex;
    gap: 10px;
}

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

.share-link-wrapper {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
}

.share-link-wrapper .form-input {
    flex: 1;
    font-family: monospace;
    font-size: 13px;
}

.share-hint {
    font-size: 12px;
    color: var(--text-dim);
    margin: 0;
}

.share-hint i {
    color: var(--primary-light);
}

.coach-info {
    margin-left: 15px;
    color: var(--text-dim);
}

@media (max-width: 768px) {
    .card-actions {
        flex-wrap: wrap;
    }
    
    .share-link-wrapper {
        flex-direction: column;
    }
}
</style>

<script>
// Simple drill diagram viewer
let centerLogoImage = null;
let centerLogoLoaded = false;

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('drill-view-canvas');
    const canvas = document.getElementById('drill-view-canvas-el');
    if (!container || !canvas) return;
    
    // Set canvas size
    canvas.width = container.offsetWidth;
    canvas.height = container.offsetHeight;
    
    const ctx = canvas.getContext('2d');
    const diagramDataRaw = <?php echo json_encode($drill['diagram_data'] ?? ''); ?>;
    const centerLogoUrl = container.dataset.centerLogo || '';
    
    // Parse diagram data and extract dimensions
    let diagramObjects = [];
    let sourceWidth = canvas.width;
    let sourceHeight = canvas.height;
    let iceView = 'full'; // Default ice view
    
    try {
        const parsed = JSON.parse(diagramDataRaw);
        if (Array.isArray(parsed)) {
            // Old format - just an array of objects
            diagramObjects = parsed;
        } else if (parsed && parsed.objects && Array.isArray(parsed.objects)) {
            // New format with canvas dimensions
            diagramObjects = parsed.objects;
            sourceWidth = parsed.canvasWidth || canvas.width;
            sourceHeight = parsed.canvasHeight || canvas.height;
            // Get saved ice view
            if (parsed.iceView) {
                iceView = parsed.iceView;
            }
        }
    } catch (e) {
        console.log('No diagram data to parse');
    }
    
    // Function to render everything
    function renderDrill() {
        drawViewRink(ctx, canvas.width, canvas.height, iceView);
        
        if (diagramObjects.length > 0) {
            // Calculate scale factors
            const scaleX = canvas.width / sourceWidth;
            const scaleY = canvas.height / sourceHeight;
            
            diagramObjects.forEach(obj => {
                // Create a scaled copy of the object
                const scaledObj = scaleObjectForView(obj, scaleX, scaleY);
                drawObject(ctx, scaledObj);
            });
        }
    }
    
    // Scale object coordinates for view
    function scaleObjectForView(obj, scaleX, scaleY) {
        const scaled = { ...obj };
        
        // Scale position-based objects
        if (scaled.x !== undefined) scaled.x *= scaleX;
        if (scaled.y !== undefined) scaled.y *= scaleY;
        
        // Scale line-based objects
        if (scaled.x1 !== undefined) scaled.x1 *= scaleX;
        if (scaled.y1 !== undefined) scaled.y1 *= scaleY;
        if (scaled.x2 !== undefined) scaled.x2 *= scaleX;
        if (scaled.y2 !== undefined) scaled.y2 *= scaleY;
        
        // Scale freehand points
        if (scaled.points && Array.isArray(scaled.points)) {
            scaled.points = scaled.points.map(pt => ({
                x: pt.x * scaleX,
                y: pt.y * scaleY
            }));
        }
        
        return scaled;
    }
    
    // Load center logo if URL provided
    if (centerLogoUrl) {
        centerLogoImage = new Image();
        centerLogoImage.crossOrigin = 'anonymous';
        centerLogoImage.onload = function() {
            centerLogoLoaded = true;
            renderDrill();
        };
        centerLogoImage.onerror = function() {
            console.warn('Failed to load center logo image');
            centerLogoLoaded = false;
            renderDrill();
        };
        centerLogoImage.src = centerLogoUrl;
    } else {
        renderDrill();
    }
    
    // Handle resize
    window.addEventListener('resize', function() {
        canvas.width = container.offsetWidth;
        canvas.height = container.offsetHeight;
        renderDrill();
    });
});

function drawViewRink(ctx, w, h, iceView) {
    iceView = iceView || 'full';
    
    // Ice background
    ctx.fillStyle = '#f0f7fa';
    ctx.fillRect(0, 0, w, h);
    
    // Center logo (image if available, otherwise text at 12% opacity)
    ctx.save();
    ctx.globalAlpha = 0.12;
    
    if (centerLogoLoaded && centerLogoImage) {
        // Draw logo image centered on ice
        const maxLogoWidth = w * 0.3;  // Logo takes up 30% of canvas width
        const maxLogoHeight = h * 0.25; // Max 25% of height
        
        // Calculate scaled dimensions maintaining aspect ratio
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
        // Fallback to text branding
        ctx.fillStyle = '#7000a4';
        ctx.font = 'bold 48px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('ARCTIC WOLVES', w/2, h/2 - 15);
        ctx.font = '24px Inter, sans-serif';
        ctx.fillText('HOCKEY', w/2, h/2 + 25);
    }
    ctx.restore();
    
    // Draw based on ice view
    switch(iceView) {
        case 'half-top':
            drawHalfIceView(ctx, w, h, 'top');
            break;
        case 'half-bottom':
            drawHalfIceView(ctx, w, h, 'bottom');
            break;
        case 'left-zone':
            drawZoneView(ctx, w, h, 'left');
            break;
        case 'right-zone':
            drawZoneView(ctx, w, h, 'right');
            break;
        case 'full':
        default:
            drawFullIceView(ctx, w, h);
            break;
    }
    
    // Rink border
    const cornerRadius = Math.min(w, h) * 0.1;
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.moveTo(cornerRadius + 2, 2);
    ctx.lineTo(w - cornerRadius - 2, 2);
    ctx.quadraticCurveTo(w - 2, 2, w - 2, cornerRadius + 2);
    ctx.lineTo(w - 2, h - cornerRadius - 2);
    ctx.quadraticCurveTo(w - 2, h - 2, w - cornerRadius - 2, h - 2);
    ctx.lineTo(cornerRadius + 2, h - 2);
    ctx.quadraticCurveTo(2, h - 2, 2, h - cornerRadius - 2);
    ctx.lineTo(2, cornerRadius + 2);
    ctx.quadraticCurveTo(2, 2, cornerRadius + 2, 2);
    ctx.closePath();
    ctx.stroke();
}

function drawFullIceView(ctx, w, h) {
    const cornerRadius = Math.min(w, h) * 0.1;
    
    // Center line
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.moveTo(w/2, 0);
    ctx.lineTo(w/2, h);
    ctx.stroke();
    
    // Blue lines
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(w * 0.25, 0);
    ctx.lineTo(w * 0.25, h);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(w * 0.75, 0);
    ctx.lineTo(w * 0.75, h);
    ctx.stroke();
    
    // Center circle
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(w/2, h/2, Math.min(w, h) * 0.12, 0, 2 * Math.PI);
    ctx.stroke();
    
    // Center dot
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    ctx.arc(w/2, h/2, 5, 0, 2 * Math.PI);
    ctx.fill();
    
    // Goal creases and goal lines
    const creaseRadius = Math.min(w, h) * 0.08;
    
    // Left goal crease
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(w * 0.03, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
    ctx.fill();
    ctx.stroke();
    
    // Left goal line - extends full height within bounds
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(w * 0.03, cornerRadius + 4);
    ctx.lineTo(w * 0.03, h - cornerRadius - 4);
    ctx.stroke();
    
    // Right goal crease
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(w * 0.97, h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
    ctx.fill();
    ctx.stroke();
    
    // Right goal line - extends full height within bounds
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(w * 0.97, cornerRadius + 4);
    ctx.lineTo(w * 0.97, h - cornerRadius - 4);
    ctx.stroke();
    
    // Faceoff circles with dots and hash marks
    const faceoffRadius = Math.min(w, h) * 0.1;
    const circles = [
        { x: w * 0.15, y: h * 0.3 },
        { x: w * 0.15, y: h * 0.7 },
        { x: w * 0.85, y: h * 0.3 },
        { x: w * 0.85, y: h * 0.7 }
    ];
    
    circles.forEach(function(circle) {
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(circle.x, circle.y, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(circle.x, circle.y, 4, 0, 2 * Math.PI);
        ctx.fill();
        
        // Draw NHL-style hash marks (nets on left/right)
        drawHashMarksForCircle(ctx, circle.x, circle.y, faceoffRadius, 'horizontal');
    });
}

function drawHalfIceView(ctx, w, h, side) {
    // Blue line
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 3;
    if (side === 'top') {
        ctx.beginPath();
        ctx.moveTo(0, h * 0.8);
        ctx.lineTo(w, h * 0.8);
        ctx.stroke();
    } else {
        ctx.beginPath();
        ctx.moveTo(0, h * 0.2);
        ctx.lineTo(w, h * 0.2);
        ctx.stroke();
    }
    
    // Faceoff circles with hash marks
    const faceoffRadius = Math.min(w, h) * 0.12;
    const faceoffY = side === 'top' ? h * 0.4 : h * 0.6;
    
    // Left faceoff circle
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(w * 0.3, faceoffY, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(w * 0.3, faceoffY, 4, 0, 2 * Math.PI);
    ctx.fill();
    drawHashMarksForCircle(ctx, w * 0.3, faceoffY, faceoffRadius, 'vertical');
    
    // Right faceoff circle  
    ctx.beginPath();
    ctx.arc(w * 0.7, faceoffY, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(w * 0.7, faceoffY, 4, 0, 2 * Math.PI);
    ctx.fill();
    drawHashMarksForCircle(ctx, w * 0.7, faceoffY, faceoffRadius, 'vertical');
    
    // Goal crease - proper semicircle
    const creaseRadius = Math.min(w, h) * 0.1;
    const goalY = side === 'top' ? h * 0.05 : h * 0.95;
    
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    if (side === 'top') {
        ctx.arc(w * 0.5, goalY, creaseRadius, 0, Math.PI);
    } else {
        ctx.arc(w * 0.5, goalY, creaseRadius, 0, Math.PI, true);
    }
    ctx.fill();
    ctx.stroke();
    
    // Goal line
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(w * 0.35, goalY);
    ctx.lineTo(w * 0.65, goalY);
    ctx.stroke();
}

function drawZoneView(ctx, w, h, side) {
    // Blue line
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 3;
    const lineX = side === 'left' ? w * 0.75 : w * 0.25;
    ctx.beginPath();
    ctx.moveTo(lineX, 0);
    ctx.lineTo(lineX, h);
    ctx.stroke();
    
    // Faceoff circles with hash marks
    const centerX = side === 'left' ? w * 0.35 : w * 0.65;
    const faceoffRadius = Math.min(w, h) * 0.12;
    
    // Top faceoff circle
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(centerX, h * 0.3, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(centerX, h * 0.3, 4, 0, 2 * Math.PI);
    ctx.fill();
    drawHashMarksForCircle(ctx, centerX, h * 0.3, faceoffRadius, 'horizontal');
    
    // Bottom faceoff circle
    ctx.beginPath();
    ctx.arc(centerX, h * 0.7, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(centerX, h * 0.7, 4, 0, 2 * Math.PI);
    ctx.fill();
    drawHashMarksForCircle(ctx, centerX, h * 0.7, faceoffRadius, 'horizontal');
    
    // Goal crease - proper semicircle
    const creaseRadius = Math.min(w, h) * 0.1;
    const goalX = side === 'left' ? w * 0.05 : w * 0.95;
    
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    if (side === 'left') {
        ctx.arc(goalX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
    } else {
        ctx.arc(goalX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2, true);
    }
    ctx.fill();
    ctx.stroke();
    
    // Goal line
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(goalX, h * 0.35);
    ctx.lineTo(goalX, h * 0.65);
    ctx.stroke();
}

// Helper function to draw NHL hash marks around faceoff circles
// netPosition: 'horizontal' (nets on left/right, hash marks on top/bottom)
//              'vertical' (nets on top/bottom, hash marks on left/right)
function drawHashMarksForCircle(ctx, cx, cy, radius, netPosition) {
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    
    // NHL/Hockey Canada regulations: faceoff circles have 15-foot radius
    // Hash marks are 2 feet long and spaced 3 feet apart
    // We scale these dimensions relative to the drawn circle radius
    const hashLength = radius * (2 / 15); // 2 feet / 15 feet radius = 0.133
    const hashSpacing = radius * (3 / 15); // 3 feet / 15 feet radius = 0.2
    const gapOutsideCircle = radius * 0.05;
    const startDistance = radius + gapOutsideCircle;
    
    const sides = [-1, 1];
    
    if (netPosition === 'vertical') {
        // Nets on top/bottom - hash marks on LEFT and RIGHT of circle (horizontal lines)
        sides.forEach(function(side) {
            const startX = cx + side * startDistance;
            const endX = startX + side * hashLength;
            
            // Top hash mark
            ctx.beginPath();
            ctx.moveTo(startX, cy - hashSpacing / 2);
            ctx.lineTo(endX, cy - hashSpacing / 2);
            ctx.stroke();
            
            // Bottom hash mark
            ctx.beginPath();
            ctx.moveTo(startX, cy + hashSpacing / 2);
            ctx.lineTo(endX, cy + hashSpacing / 2);
            ctx.stroke();
        });
    } else {
        // Nets on left/right (default) - hash marks on TOP and BOTTOM of circle (vertical lines)
        sides.forEach(function(side) {
            const startY = cy + side * startDistance;
            const endY = startY + side * hashLength;
            
            // Left hash mark
            ctx.beginPath();
            ctx.moveTo(cx - hashSpacing / 2, startY);
            ctx.lineTo(cx - hashSpacing / 2, endY);
            ctx.stroke();
            
            // Right hash mark
            ctx.beginPath();
            ctx.moveTo(cx + hashSpacing / 2, startY);
            ctx.lineTo(cx + hashSpacing / 2, endY);
            ctx.stroke();
        });
    }
}

function drawObject(ctx, obj) {
    ctx.save();
    
    if (obj.type === 'player') {
        ctx.translate(obj.x, obj.y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        ctx.fillStyle = obj.color || '#00bfff';
        ctx.beginPath();
        ctx.arc(0, 0, 14, 0, 2 * Math.PI);
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
        if (obj.label) {
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 10px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(obj.label, 0, 0);
        }
    } else if (obj.type === 'cone') {
        ctx.translate(obj.x, obj.y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        ctx.fillStyle = obj.color || '#ff6b00';
        ctx.beginPath();
        ctx.moveTo(0, -15);
        ctx.lineTo(-10, 10);
        ctx.lineTo(10, 10);
        ctx.closePath();
        ctx.fill();
    } else if (obj.type === 'puck') {
        ctx.fillStyle = obj.color || '#000';
        ctx.beginPath();
        ctx.arc(obj.x, obj.y, 8, 0, 2 * Math.PI);
        ctx.fill();
    } else if (obj.type === 'line') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(obj.x1, obj.y1);
        ctx.lineTo(obj.x2, obj.y2);
        ctx.stroke();
    } else if (obj.type === 'dashed') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = 2;
        ctx.setLineDash([8, 5]);
        ctx.beginPath();
        ctx.moveTo(obj.x1, obj.y1);
        ctx.lineTo(obj.x2, obj.y2);
        ctx.stroke();
    } else if (obj.type === 'squiggly') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = 2;
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
            ctx.quadraticCurveTo(midX, (i % 2 === 0 ? 1 : -1) * 6, segmentEnd, 0);
        }
        ctx.stroke();
    } else if (obj.type === 'arrow') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.fillStyle = obj.color || '#333';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(obj.x1, obj.y1);
        ctx.lineTo(obj.x2, obj.y2);
        ctx.stroke();
        const angle = Math.atan2(obj.y2 - obj.y1, obj.x2 - obj.x1);
        ctx.beginPath();
        ctx.moveTo(obj.x2, obj.y2);
        ctx.lineTo(obj.x2 - 15 * Math.cos(angle - Math.PI/6), obj.y2 - 15 * Math.sin(angle - Math.PI/6));
        ctx.lineTo(obj.x2 - 15 * Math.cos(angle + Math.PI/6), obj.y2 - 15 * Math.sin(angle + Math.PI/6));
        ctx.closePath();
        ctx.fill();
    } else if (obj.type === 'net') {
        ctx.translate(obj.x, obj.y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        
        const frameColor = obj.color || '#c41e3a';
        const netWidth = 48;
        const netDepth = 16;
        
        // Draw the net frame - D-shape like real hockey net
        ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
        ctx.strokeStyle = frameColor;
        ctx.lineWidth = 3;
        
        ctx.beginPath();
        ctx.moveTo(-netWidth/2, 0);
        ctx.lineTo(netWidth/2, 0);
        ctx.lineTo(netWidth/2 - 4, -netDepth);
        ctx.quadraticCurveTo(0, -netDepth - 8, -netWidth/2 + 4, -netDepth);
        ctx.lineTo(-netWidth/2, 0);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        // Draw mesh lines
        ctx.strokeStyle = '#aaa';
        ctx.lineWidth = 0.5;
        for (let i = -2; i <= 2; i++) {
            const meshX = (netWidth/5) * i;
            ctx.beginPath();
            ctx.moveTo(meshX * 0.85, 0);
            ctx.lineTo(meshX * 0.6, -netDepth);
            ctx.stroke();
        }
        
        // Red posts and crossbar
        ctx.strokeStyle = frameColor;
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(-netWidth/2, 2);
        ctx.lineTo(-netWidth/2, -2);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(netWidth/2, 2);
        ctx.lineTo(netWidth/2, -2);
        ctx.stroke();
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(-netWidth/2, 0);
        ctx.lineTo(netWidth/2, 0);
        ctx.stroke();
    } else if (obj.type === 'mininet') {
        ctx.translate(obj.x, obj.y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        
        const frameColor = obj.color || '#c41e3a';
        const netWidth = 32;
        const netDepth = 12;
        
        ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
        ctx.strokeStyle = frameColor;
        ctx.lineWidth = 2;
        
        ctx.beginPath();
        ctx.moveTo(-netWidth/2, 0);
        ctx.lineTo(netWidth/2, 0);
        ctx.lineTo(netWidth/2 - 3, -netDepth);
        ctx.quadraticCurveTo(0, -netDepth - 5, -netWidth/2 + 3, -netDepth);
        ctx.lineTo(-netWidth/2, 0);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
    } else if (obj.type === 'tire') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = 6;
        ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
        ctx.beginPath();
        ctx.arc(obj.x, obj.y, 12, 0, 2 * Math.PI);
        ctx.fill();
        ctx.stroke();
    } else if (obj.type === 'stick') {
        ctx.translate(obj.x, obj.y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        ctx.strokeStyle = obj.color || '#8B4513';
        ctx.lineWidth = 5;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(0, -22);
        ctx.lineTo(0, 12);
        ctx.stroke();
        ctx.lineWidth = 6;
        ctx.beginPath();
        ctx.moveTo(0, 12);
        ctx.quadraticCurveTo(8, 16, 14, 12);
        ctx.stroke();
    } else if (obj.type === 'text') {
        ctx.translate(obj.x, obj.y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        ctx.fillStyle = obj.color || '#000';
        ctx.font = 'bold 14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(obj.text, 0, 0);
    } else if (obj.type === 'number') {
        ctx.translate(obj.x, obj.y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(0, 0, 14, 0, 2 * Math.PI);
        ctx.fill();
        ctx.strokeStyle = obj.color || '#000';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.fillStyle = obj.color || '#000';
        ctx.font = 'bold 16px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(obj.value, 0, 0);
    } else if (obj.type === 'freehand' || obj.type === 'freehand_arrow' || obj.type === 'freehand_dashed' || obj.type === 'freehand_skating') {
        // Handle all freehand drawing types
        if (obj.points && obj.points.length >= 2) {
            const color = obj.color || '#333';
            ctx.strokeStyle = color;
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            
            // Set line dash for dashed types
            if (obj.type === 'freehand_dashed') {
                ctx.setLineDash([10, 6]);
            }
            
            // Draw the path
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
            
            // Draw arrow for arrow and skating types
            if ((obj.type === 'freehand_arrow' || obj.type === 'freehand_skating') && obj.points.length >= 2) {
                const secondLast = obj.points[obj.points.length - 2];
                const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
                const headlen = 12;
                
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

function copyShareLink() {
    const input = document.getElementById('share-url-input');
    input.select();
    input.setSelectionRange(0, input.value.length);
    
    navigator.clipboard.writeText(input.value).then(() => {
        showNotification('Share link copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback for older browsers - execCommand is deprecated but still works in many browsers
        try {
            document.execCommand('copy');
            showNotification('Share link copied to clipboard!', 'success');
        } catch (e) {
            showNotification('Please copy the link manually using Ctrl+C', 'info');
        }
    });
}

function exportDiagram() {
    const canvas = document.getElementById('drill-view-canvas-el');
    if (canvas) {
        const link = document.createElement('a');
        link.download = '<?php echo preg_replace('/[^a-zA-Z0-9]/', '-', $drill['title']); ?>-drill.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }
}

function showNotification(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'notification-toast';
    alertDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'info-circle') + '"></i> ' + message;
    alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px; padding: 15px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; background: rgba(16, 185, 129, 0.9); color: #fff;';
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 4000);
}
</script>
