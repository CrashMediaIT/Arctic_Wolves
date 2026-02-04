<!-- View Practice Plan - In-depth Practice Plan View -->
<?php
$planId = $_GET['id'] ?? null;
$isShared = isset($_GET['shared']);
$plan = null;
$drills = [];

// Fetch logo URL from theme settings for center ice display
$centerLogoUrl = '';
try {
    $logoStmt = $pdo->prepare("SELECT setting_value FROM theme_settings WHERE setting_name = 'logo_url'");
    $logoStmt->execute();
    $logoResult = $logoStmt->fetch(PDO::FETCH_ASSOC);
    if ($logoResult && !empty($logoResult['setting_value'])) {
        $centerLogoUrl = $logoResult['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error fetching logo URL: " . $e->getMessage());
}

// Validate planId is numeric to prevent injection
if ($planId !== null && !ctype_digit((string)$planId)) {
    $planId = null;
}

if ($planId) {
    try {
        // Get the practice plan
        $stmt = $pdo->prepare("
            SELECT pp.*, 
                   COALESCE(pp.title, pp.name) as title,
                   COALESCE(pp.total_duration, pp.duration_minutes, 60) as total_duration,
                   CONCAT(u.first_name, ' ', u.last_name) as creator_name,
                   u.first_name, u.last_name
            FROM practice_plans pp
            LEFT JOIN users u ON pp.created_by = u.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($plan) {
            // Get the drills for this plan
            $drillStmt = $pdo->prepare("
                SELECT ppd.*, 
                       d.title, d.description, d.setup, d.coaching_points, d.progression,
                       d.diagram_data, d.custom_image, d.video_url, d.ihs_source_url, d.notes,
                       dc.name as category_name,
                       ppd.duration_minutes as drill_duration
                FROM practice_plan_drills ppd
                LEFT JOIN drills d ON ppd.drill_id = d.id
                LEFT JOIN drill_categories dc ON d.category_id = dc.id
                WHERE ppd.practice_plan_id = ?
                ORDER BY ppd.drill_order ASC
            ");
            $drillStmt->execute([$planId]);
            $drills = $drillStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching practice plan: " . $e->getMessage());
    }
}

if (!$plan) {
    echo '<div class="content-card"><div class="card-body"><p class="text-center">Practice plan not found.</p><a href="?page=practice_plans" class="btn btn-primary">Back to Practice Plans</a></div></div>';
    return;
}

$creatorName = htmlspecialchars($plan['creator_name'] ?? '');

// Build share URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = htmlspecialchars($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
$shareUrl = '';
if (!empty($plan['share_token'])) {
    $shareUrl = $protocol . '://' . $host . '/practice_plan_share.php?token=' . urlencode($plan['share_token']);
}

// Calculate total duration from drills if needed
$calculatedDuration = 0;
foreach ($drills as $drill) {
    $calculatedDuration += $drill['drill_duration'] ?? 10;
}
$totalDuration = $plan['total_duration'] ?? $calculatedDuration;
?>

<div class="page-header">
    <div class="page-header-left">
        <a href="?page=practice_plans" class="btn btn-secondary" style="margin-right: 15px;">
            <i class="fas fa-arrow-left"></i> Back to Plans
        </a>
        <div>
            <h1 class="page-title">
                <i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($plan['title'] ?? 'Practice Plan'); ?>
            </h1>
            <p class="page-description">
                <?php if (!empty($plan['age_group'])): ?>
                    <span class="badge badge-primary"><?php echo htmlspecialchars($plan['age_group']); ?></span>
                <?php endif; ?>
                <?php if (!empty($plan['focus_area'])): ?>
                    <span class="badge badge-secondary"><?php echo htmlspecialchars($plan['focus_area']); ?></span>
                <?php endif; ?>
                <?php if (!empty($plan['skill_level'])): ?>
                    <span class="badge"><?php echo htmlspecialchars($plan['skill_level']); ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<div class="view-practice-plan-content">
    <!-- Plan Overview Card -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Plan Overview</h3>
            <div class="card-actions">
                <?php if (!empty($shareUrl)): ?>
                <button class="btn btn-secondary" onclick="copyShareLink()">
                    <i class="fas fa-share-alt"></i> Share
                </button>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) && ($plan['created_by'] == $_SESSION['user_id'] || in_array($user_role ?? '', ['admin', 'coach']))): ?>
                <a href="?page=practice_plans&edit=<?php echo $planId; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Plan
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="plan-overview-grid">
                <div class="overview-item">
                    <span class="overview-icon"><i class="fas fa-clock"></i></span>
                    <div class="overview-info">
                        <span class="overview-label">Total Duration</span>
                        <span class="overview-value"><?php echo intval($totalDuration); ?> minutes</span>
                    </div>
                </div>
                <div class="overview-item">
                    <span class="overview-icon"><i class="fas fa-hockey-puck"></i></span>
                    <div class="overview-info">
                        <span class="overview-label">Drills</span>
                        <span class="overview-value"><?php echo count($drills); ?> drill<?php echo count($drills) != 1 ? 's' : ''; ?></span>
                    </div>
                </div>
                <div class="overview-item">
                    <span class="overview-icon"><i class="fas fa-user"></i></span>
                    <div class="overview-info">
                        <span class="overview-label">Created By</span>
                        <span class="overview-value"><?php echo $creatorName; ?></span>
                    </div>
                </div>
                <div class="overview-item">
                    <span class="overview-icon"><i class="fas fa-calendar"></i></span>
                    <div class="overview-info">
                        <span class="overview-label">Created</span>
                        <span class="overview-value"><?php echo date('F j, Y', strtotime($plan['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($plan['description'])): ?>
            <div class="plan-description-section">
                <h4><i class="fas fa-align-left"></i> Description</h4>
                <p><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($plan['notes'])): ?>
            <div class="plan-description-section">
                <h4><i class="fas fa-sticky-note"></i> Notes</h4>
                <p><?php echo nl2br(htmlspecialchars($plan['notes'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($shareUrl)): ?>
    <!-- Share Link Card -->
    <div class="content-card share-link-card">
        <div class="card-header">
            <h3><i class="fas fa-link"></i> Share This Practice Plan</h3>
        </div>
        <div class="card-body">
            <div class="share-link-wrapper">
                <input type="text" id="share-url-input" class="form-input" value="<?php echo htmlspecialchars($shareUrl); ?>" readonly>
                <button class="btn btn-primary" onclick="copyShareLink()">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
            </div>
            <p class="share-hint"><i class="fas fa-info-circle"></i> Share this link with your team or other coaches to view this practice plan.</p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Drills Section -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Practice Drills (<?php echo count($drills); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($drills)): ?>
                <div class="empty-drills-message">
                    <i class="fas fa-hockey-puck" style="font-size: 48px; color: #64748b; margin-bottom: 15px;"></i>
                    <p>No drills in this practice plan yet.</p>
                </div>
            <?php else: ?>
                <div class="drills-list">
                    <?php foreach ($drills as $index => $drill): ?>
                        <?php
                        $drillNumber = $index + 1;
                        $duration = $drill['drill_duration'] ?? 10;
                        $categoryName = $drill['category_name'] ?? 'General';
                        $description = $drill['description'] ?? 'No description available.';
                        $hasCustomImage = !empty($drill['custom_image']) && trim($drill['custom_image']) !== '';
                        $hasDiagramData = !empty($drill['diagram_data']) && trim($drill['diagram_data']) !== '' && $drill['diagram_data'] !== '[]';
                        ?>
                        <div class="drill-card" id="drill-<?php echo $drillNumber; ?>">
                            <div class="drill-header">
                                <div class="drill-title-section">
                                    <span class="drill-number"><?php echo $drillNumber; ?></span>
                                    <div>
                                        <h4 class="drill-title"><?php echo htmlspecialchars($drill['title'] ?? 'Untitled Drill'); ?></h4>
                                        <span class="drill-category"><?php echo htmlspecialchars($categoryName); ?></span>
                                    </div>
                                </div>
                                <div class="drill-header-actions">
                                    <span class="drill-duration"><i class="fas fa-clock"></i> <?php echo intval($duration); ?> min</span>
                                    <a href="?page=view_drill&id=<?php echo $drill['drill_id']; ?>" class="btn btn-sm btn-primary drill-view-btn">
                                        <i class="fas fa-eye"></i> View Drill
                                    </a>
                                </div>
                            </div>
                            
                            <div class="drill-body">
                                <!-- Drill Diagram -->
                                <div class="drill-diagram-view" id="drill-diagram-container-<?php echo $drillNumber; ?>">
                                    <?php if ($hasCustomImage): ?>
                                        <!-- IHS Imported Image -->
                                        <div class="ihs-diagram-container">
                                            <img src="<?php echo htmlspecialchars($drill['custom_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($drill['title'] ?? 'Drill'); ?> Diagram" 
                                                 class="ihs-drill-image"
                                                 onerror="this.parentElement.innerHTML='<div class=\'no-diagram\'><i class=\'fas fa-image\'></i><span>Image not available</span></div>'">
                                        </div>
                                    <?php elseif ($hasDiagramData): ?>
                                        <!-- Drill Draw Canvas -->
                                        <div class="ice-rink-canvas view-only">
                                            <canvas id="drill-canvas-<?php echo $drillNumber; ?>" 
                                                    class="drill-diagram-canvas"
                                                    data-diagram='<?php echo htmlspecialchars($drill['diagram_data'], ENT_QUOTES); ?>'></canvas>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-diagram">
                                            <i class="fas fa-hockey-puck"></i>
                                            <span>No diagram available</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Drill Details -->
                                <div class="drill-details">
                                    <div class="drill-detail-section">
                                        <h5><i class="fas fa-align-left"></i> Description</h5>
                                        <p><?php echo nl2br(htmlspecialchars($description)); ?></p>
                                    </div>
                                    
                                    <?php if (!empty($drill['setup'])): ?>
                                    <div class="drill-detail-section">
                                        <h5><i class="fas fa-cog"></i> Setup</h5>
                                        <p><?php echo nl2br(htmlspecialchars($drill['setup'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($drill['coaching_points'])): ?>
                                    <div class="drill-detail-section">
                                        <h5><i class="fas fa-bullseye"></i> Coaching Points</h5>
                                        <p><?php echo nl2br(htmlspecialchars($drill['coaching_points'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($drill['progression'])): ?>
                                    <div class="drill-detail-section">
                                        <h5><i class="fas fa-level-up-alt"></i> Progression</h5>
                                        <p><?php echo nl2br(htmlspecialchars($drill['progression'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($drill['notes'])): ?>
                                    <div class="drill-detail-section">
                                        <h5><i class="fas fa-sticky-note"></i> Notes</h5>
                                        <p><?php echo nl2br(htmlspecialchars($drill['notes'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($drill['video_url'])): ?>
                                    <div class="drill-detail-section">
                                        <h5><i class="fas fa-video"></i> Video</h5>
                                        <a href="<?php echo htmlspecialchars($drill['video_url']); ?>" target="_blank" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-play-circle"></i> Watch Video
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="drill-link">
                                        <a href="?page=view_drill&id=<?php echo $drill['drill_id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-external-link-alt"></i> View Full Drill Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* View Practice Plan - Styles consistent with shared_styles.css */

.view-practice-plan-content {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.content-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.content-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-4) var(--space-5);
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
}

.content-card .card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
}

.content-card .card-header h3 i {
    color: var(--primary-light);
    margin-right: 8px;
}

.card-actions {
    display: flex;
    gap: 10px;
}

.content-card .card-body {
    padding: var(--space-5);
}

.plan-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: var(--space-5);
    margin-bottom: var(--space-5);
}

.overview-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: var(--space-4);
    background: var(--bg-main);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.overview-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary);
    border-radius: 8px;
    color: #fff;
    font-size: 16px;
}

.overview-info {
    display: flex;
    flex-direction: column;
}

.overview-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.5px;
}

.overview-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
}

.plan-description-section {
    padding-top: var(--space-5);
    border-top: 1px solid var(--border);
    margin-top: var(--space-5);
}

.plan-description-section h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 10px;
}

.plan-description-section h4 i {
    color: var(--primary-light);
    margin-right: 8px;
}

.plan-description-section p {
    color: var(--text-secondary);
    line-height: 1.6;
}

/* Share Link */
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
    color: var(--text-muted);
    margin: 0;
}

.share-hint i {
    color: var(--primary-light);
}

/* Drills List */
.drills-list {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.drill-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.drill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-4) var(--space-5);
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
}

.drill-title-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.drill-number {
    width: 36px;
    height: 36px;
    background: var(--primary);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
}

.drill-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.drill-category {
    font-size: 12px;
    color: var(--text-muted);
}

.drill-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.drill-view-btn {
    white-space: nowrap;
}

.drill-duration {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--bg-card);
    padding: 6px 12px;
    border-radius: 20px;
}

.drill-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    padding: var(--space-5);
}

/* Drill Diagram - Matching view_drill.php patterns */
.drill-diagram-view {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 350px;
}

.ihs-diagram-container {
    width: 100%;
    max-width: 100%;
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
    max-height: 400px;
    height: auto;
    object-fit: contain;
    border-radius: 8px;
}

.ice-rink-canvas.view-only {
    width: 100%;
    aspect-ratio: 2/1;
    min-height: 300px;
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

.drill-diagram-canvas {
    width: 100%;
    height: 100%;
}

.no-diagram {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    padding: 40px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    width: 100%;
    min-height: 200px;
}

.no-diagram i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Drill Details */
.drill-details {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.drill-detail-section {
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.drill-detail-section:last-of-type {
    border-bottom: none;
}

.drill-detail-section h5 {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.drill-detail-section h5 i {
    color: var(--primary-light);
    margin-right: 6px;
}

.drill-detail-section p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

.drill-link {
    margin-top: auto;
}

.empty-drills-message {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

@media (max-width: 992px) {
    .drill-body {
        grid-template-columns: 1fr;
    }
    
    .drill-diagram-view {
        min-height: 250px;
    }
}

@media (max-width: 768px) {
    .page-header-left {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .content-card .card-header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .share-link-wrapper {
        flex-direction: column;
    }
}
</style>

<script>
// Copy share link
function copyShareLink() {
    const input = document.getElementById('share-url-input');
    if (!input) return;
    
    input.select();
    input.setSelectionRange(0, input.value.length);
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(() => {
            showNotification('Share link copied to clipboard!', 'success');
        }).catch(() => {
            document.execCommand('copy');
            showNotification('Share link copied to clipboard!', 'success');
        });
    } else {
        document.execCommand('copy');
        showNotification('Share link copied to clipboard!', 'success');
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

// Logo for center ice
let centerLogoImage = null;
let centerLogoLoaded = false;
const centerLogoUrl = '<?php echo htmlspecialchars($centerLogoUrl); ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Load center logo if available
    if (centerLogoUrl) {
        centerLogoImage = new Image();
        centerLogoImage.crossOrigin = 'anonymous';
        centerLogoImage.onload = function() {
            centerLogoLoaded = true;
            renderAllCanvases();
        };
        centerLogoImage.onerror = function() {
            centerLogoLoaded = false;
            renderAllCanvases();
        };
        centerLogoImage.src = centerLogoUrl;
    } else {
        renderAllCanvases();
    }
});

function renderAllCanvases() {
    // Find all drill canvases and render them
    const canvases = document.querySelectorAll('.drill-diagram-canvas');
    canvases.forEach(canvas => {
        const diagramData = canvas.dataset.diagram;
        if (diagramData) {
            renderDrillCanvas(canvas, diagramData);
        }
    });
}

function renderDrillCanvas(canvas, diagramDataStr) {
    const container = canvas.parentElement;
    canvas.width = container.offsetWidth || 600;
    canvas.height = 350;
    
    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;
    
    // Draw ice rink
    drawRink(ctx, w, h);
    
    // Draw diagram objects
    try {
        const objects = JSON.parse(diagramDataStr);
        if (Array.isArray(objects)) {
            objects.forEach(obj => drawObject(ctx, obj));
        }
    } catch (e) {
        console.log('Error parsing diagram data:', e);
    }
}

function drawRink(ctx, w, h) {
    // Ice background
    ctx.fillStyle = '#f0f7fa';
    ctx.fillRect(0, 0, w, h);
    
    // Center logo
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
        ctx.font = 'bold 36px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('ARCTIC WOLVES', w/2, h/2 - 12);
        ctx.font = '18px Inter, sans-serif';
        ctx.fillText('HOCKEY', w/2, h/2 + 18);
    }
    ctx.restore();
    
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
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(w/2, h/2, Math.min(w, h) * 0.12, 0, 2 * Math.PI);
    ctx.stroke();
    
    // Rink border
    const cornerRadius = Math.min(w, h) * 0.08;
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
        ctx.strokeStyle = obj.color || '#c41e3a';
        ctx.lineWidth = 3;
        ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.beginPath();
        ctx.moveTo(-20, -15);
        ctx.lineTo(-25, 15);
        ctx.lineTo(25, 15);
        ctx.lineTo(20, -15);
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
    }
    
    ctx.restore();
}

// Handle window resize
window.addEventListener('resize', function() {
    renderAllCanvases();
});
</script>
