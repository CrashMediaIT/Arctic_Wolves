<!-- View Drill - Shareable Drill View -->
<?php
$drillId = $_GET['id'] ?? null;
$isShared = isset($_GET['shared']);
$drill = null;

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
                <div class="ice-rink-canvas view-only" id="drill-view-canvas" data-ice-view="full">
                    <canvas id="drill-view-canvas-el"></canvas>
                </div>
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
                
                <?php if (!empty($drill['video_url'])): ?>
                <div class="detail-section">
                    <h4>Video</h4>
                    <a href="<?php echo htmlspecialchars($drill['video_url']); ?>" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-play-circle"></i> Watch Video
                    </a>
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
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('drill-view-canvas');
    const canvas = document.getElementById('drill-view-canvas-el');
    if (!container || !canvas) return;
    
    // Set canvas size
    canvas.width = container.offsetWidth;
    canvas.height = container.offsetHeight;
    
    const ctx = canvas.getContext('2d');
    const diagramData = <?php echo json_encode($drill['diagram_data'] ?? ''); ?>;
    
    // Draw the rink
    drawViewRink(ctx, canvas.width, canvas.height);
    
    // Parse and draw diagram objects
    try {
        const objects = JSON.parse(diagramData);
        if (Array.isArray(objects)) {
            objects.forEach(obj => drawObject(ctx, obj));
        }
    } catch (e) {
        console.log('No diagram data to display');
    }
    
    // Handle resize
    window.addEventListener('resize', function() {
        canvas.width = container.offsetWidth;
        canvas.height = container.offsetHeight;
        drawViewRink(ctx, canvas.width, canvas.height);
        try {
            const objects = JSON.parse(diagramData);
            if (Array.isArray(objects)) {
                objects.forEach(obj => drawObject(ctx, obj));
            }
        } catch (e) {}
    });
});

function drawViewRink(ctx, w, h) {
    // Ice background
    ctx.fillStyle = '#f0f7fa';
    ctx.fillRect(0, 0, w, h);
    
    // Center logo
    ctx.save();
    ctx.globalAlpha = 0.12;
    ctx.fillStyle = '#7000a4';
    ctx.font = 'bold 48px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('ARCTIC WOLVES', w/2, h/2 - 15);
    ctx.font = '24px Inter, sans-serif';
    ctx.fillText('HOCKEY', w/2, h/2 + 25);
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
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(w/2, h/2, Math.min(w, h) * 0.12, 0, 2 * Math.PI);
    ctx.stroke();
    
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
