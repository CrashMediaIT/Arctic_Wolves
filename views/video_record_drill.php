<?php
/**
 * Coach Drill Video Recording Interface
 * Allows coaches to record drill videos during sessions
 * Supports direct upload to Nextcloud or offline recording mode
 */

// Only coaches can access this page
$allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php?page=video');
    exit;
}

// Get today's sessions
$today = date('Y-m-d');
$sessions_query = "
    SELECT s.*, st.name as session_type_name, 
           u.first_name as coach_first_name, u.last_name as coach_last_name,
           (SELECT COUNT(*) FROM session_attendance sa WHERE sa.session_id = s.id) as attendee_count
    FROM sessions s
    LEFT JOIN session_types st ON s.session_type_id = st.id
    LEFT JOIN users u ON s.coach_id = u.id
    WHERE DATE(s.session_date) = ?
    ORDER BY s.session_time ASC
";
$sessions_stmt = $pdo->prepare($sessions_query);
$sessions_stmt->execute([$today]);
$todays_sessions = $sessions_stmt->fetchAll();
$todays_sessions = decryptUserRows($todays_sessions);

// Get all active sessions for selection (last 7 days)
$recent_sessions_query = "
    SELECT s.*, st.name as session_type_name
    FROM sessions s
    LEFT JOIN session_types st ON s.session_type_id = st.id
    WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND s.status IN ('scheduled', 'completed', 'in_progress')
    ORDER BY s.session_date DESC
";
$recent_sessions = $pdo->query($recent_sessions_query)->fetchAll();

// Get all drills
$drills_query = "
    SELECT d.*, dc.name as category_name
    FROM drills d
    LEFT JOIN drill_categories dc ON d.category_id = dc.id
    ORDER BY dc.name, d.title
";
$drills = $pdo->query($drills_query)->fetchAll();

// Get athletes (either from roster or all athletes)
$athletes_query = "
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    WHERE u.is_active = 1 AND u.role = 'athlete'
    ORDER BY u.last_name, u.first_name
";
$athletes = $pdo->query($athletes_query)->fetchAll();
$athletes = decryptUserRows($athletes);

// Check RustFS connection status
$storage_available = false;
$storage_message = '';
try {
    require_once __DIR__ . '/../cloud_config.php';
    require_once __DIR__ . '/../lib/rustfs_storage.php';
    $rustfs = getRustFSSettings($pdo);
    if (isRustFSConfigured($rustfs)) {
        $storage_available = true;
    }
} catch (Exception $e) {
    $storage_message = $e->getMessage();
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="fas fa-video"></i> Record Drill Video
        </h1>
        <p class="page-description">Record drill videos during training sessions</p>
    </div>
    <div class="page-header-actions">
        <a href="?page=drill_review" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Videos
        </a>
    </div>
</div>

<!-- Connection Status -->
<div class="connection-status <?= $storage_available ? 'status-connected' : 'status-offline' ?>">
    <div class="status-icon">
        <i class="fas <?= $storage_available ? 'fa-cloud-upload-alt' : 'fa-exclamation-triangle' ?>"></i>
    </div>
    <div class="status-info">
        <?php if ($storage_available): ?>
            <strong>Cloud Connected</strong>
            <span>Videos will upload directly to cloud storage</span>
        <?php else: ?>
            <strong>Offline Mode</strong>
            <span>Videos will be saved locally for later upload</span>
            <?php if ($storage_message): ?>
                <small class="status-error"><?= htmlspecialchars($storage_message) ?></small>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Recording Setup Form -->
<div class="recording-setup-card">
    <h2><i class="fas fa-cog"></i> Recording Setup</h2>
    
    <form id="recordingSetupForm" class="recording-form">
        <?= csrfTokenInput() ?>
        <div class="form-row">
            <div class="form-group">
                <label>Session *</label>
                <select name="session_id" id="sessionSelect" class="form-input" required>
                    <option value="">-- Select Session --</option>
                    <?php if (!empty($todays_sessions)): ?>
                        <optgroup label="Today's Sessions">
                            <?php foreach ($todays_sessions as $session): ?>
                                <option value="<?= $session['id'] ?>" data-date="<?= $session['session_date'] ?? $session['date'] ?>">
                                    <?= htmlspecialchars($session['title'] ?? $session['session_type_name']) ?>
                                    - <?= date('g:i A', strtotime($session['session_time'] ?? '09:00')) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($recent_sessions)): ?>
                        <optgroup label="Recent Sessions">
                            <?php foreach ($recent_sessions as $session): ?>
                                <option value="<?= $session['id'] ?>" data-date="<?= $session['session_date'] ?? $session['date'] ?>">
                                    <?= htmlspecialchars($session['title'] ?? $session['session_type_name']) ?>
                                    - <?= date('M d', strtotime($session['session_date'] ?? $session['date'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Drill *</label>
                <select name="drill_id" id="drillSelect" class="form-input" required>
                    <option value="">-- Select Drill --</option>
                    <?php 
                    $current_category = '';
                    foreach ($drills as $drill): 
                        if ($drill['category_name'] !== $current_category):
                            if ($current_category !== '') echo '</optgroup>';
                            $current_category = $drill['category_name'] ?: 'Uncategorized';
                            echo '<optgroup label="' . htmlspecialchars($current_category) . '">';
                        endif;
                    ?>
                        <option value="<?= $drill['id'] ?>" data-title="<?= htmlspecialchars($drill['title']) ?>">
                            <?= htmlspecialchars($drill['title']) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($current_category !== '') echo '</optgroup>'; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Athlete *</label>
                <select name="athlete_id" id="athleteSelect" class="form-input" required>
                    <option value="">-- Select Athlete --</option>
                    <?php foreach ($athletes as $athlete): ?>
                        <option value="<?= $athlete['id'] ?>" data-name="<?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>">
                            <?= htmlspecialchars($athlete['last_name'] . ', ' . $athlete['first_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Rep Number *</label>
                <input type="number" name="rep_number" id="repNumber" class="form-input" value="1" min="1" max="99" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Recording Mode</label>
            <div class="mode-toggle">
                <button type="button" class="mode-btn active" data-mode="camera">
                    <i class="fas fa-camera"></i> Live Camera
                </button>
                <button type="button" class="mode-btn" data-mode="upload">
                    <i class="fas fa-upload"></i> Upload File
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Camera Recording Section -->
<div class="recording-section" id="cameraSection">
    <div class="video-preview-container">
        <video id="videoPreview" autoplay muted playsinline class="video-preview"></video>
        <div class="preview-overlay" id="previewOverlay">
            <i class="fas fa-video"></i>
            <p>Click "Start Camera" to begin</p>
        </div>
        <div class="recording-indicator" id="recordingIndicator" style="display: none;">
            <span class="recording-dot"></span> Recording
        </div>
    </div>
    
    <div class="recording-controls">
        <button type="button" class="btn btn-secondary" id="startCameraBtn">
            <i class="fas fa-camera"></i> Start Camera
        </button>
        <button type="button" class="btn btn-secondary" id="flipCameraBtn" style="display: none;" title="Flip Camera">
            <i class="fas fa-camera-rotate"></i> Flip
        </button>
        <button type="button" class="btn btn-danger" id="startRecordBtn" disabled>
            <i class="fas fa-circle"></i> Start Recording
        </button>
        <button type="button" class="btn btn-warning" id="stopRecordBtn" style="display: none;">
            <i class="fas fa-stop"></i> Stop Recording
        </button>
        <button type="button" class="btn btn-primary" id="saveVideoBtn" style="display: none;">
            <i class="fas fa-save"></i> Save Video
        </button>
    </div>
    
    <!-- Recorded Video Playback -->
    <div class="recorded-video-container" id="recordedVideoContainer" style="display: none;">
        <h3><i class="fas fa-play-circle"></i> Recorded Video</h3>
        <video id="recordedVideo" controls class="recorded-video"></video>
        <div class="recorded-video-actions">
            <button type="button" class="btn btn-secondary" id="discardBtn">
                <i class="fas fa-trash"></i> Discard
            </button>
            <button type="button" class="btn btn-primary" id="uploadBtn">
                <i class="fas fa-cloud-upload-alt"></i> Upload to Cloud
            </button>
        </div>
    </div>
</div>

<!-- File Upload Section -->
<div class="upload-section" id="uploadSection" style="display: none;">
    <div class="file-upload-area" id="fileDropZone">
        <i class="fas fa-cloud-upload-alt"></i>
        <p>Drag & drop video file here or click to browse</p>
        <p class="file-hint">Supported: MP4, MKV, MOV, AVI, WebM (Max 10GB)</p>
        <input type="file" name="video_file" id="videoFileInput" accept="video/*" style="display: none;">
        <button type="button" class="btn btn-secondary" id="browseFilesBtn">Browse Files</button>
    </div>
    
    <div class="selected-file-info" id="selectedFileInfo" style="display: none;">
        <div class="file-icon"><i class="fas fa-file-video"></i></div>
        <div class="file-details">
            <span class="file-name" id="selectedFileName"></span>
            <span class="file-size" id="selectedFileSize"></span>
        </div>
        <button type="button" class="btn btn-icon" id="removeFileBtn"><i class="fas fa-times"></i></button>
    </div>
    
    <div class="upload-actions">
        <button type="button" class="btn btn-primary" id="uploadFileBtn" disabled>
            <i class="fas fa-upload"></i> Upload Video
        </button>
    </div>
</div>

<!-- Upload Progress -->
<div class="upload-progress" id="uploadProgress" style="display: none;">
    <div class="progress-header">
        <span>Uploading video...</span>
        <span id="uploadPercent">0%</span>
    </div>
    <div class="progress-bar">
        <div class="progress-fill" id="progressFill"></div>
    </div>
    <button type="button" class="btn btn-danger" id="cancelDrillUploadBtn" style="margin-top: 10px; font-size: 13px;">
        <i class="fas fa-times"></i> Cancel Upload
    </button>
</div>

<!-- Recent Recordings -->
<div class="recent-recordings-card">
    <h2><i class="fas fa-history"></i> Recent Recordings</h2>
    <div class="recordings-list" id="recentRecordingsList">
        <div class="placeholder-container">
            <i class="fas fa-video placeholder-icon"></i>
            <p class="placeholder-text">No recordings yet today. Start recording to see them here.</p>
        </div>
    </div>
</div>

<style>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-actions {
    display: flex;
    gap: 12px;
}

/* Connection Status */
.connection-status {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.status-connected {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-offline {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.status-connected .status-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10B981;
}

.status-offline .status-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #F59E0B;
}

.status-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.status-info strong {
    color: var(--text-white);
    font-size: 15px;
}

.status-info span {
    color: var(--text-dim);
    font-size: 13px;
}

.status-error {
    color: #EF4444;
    font-size: 12px;
}

/* Recording Setup Card */
.recording-setup-card, .recent-recordings-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
}

.recording-setup-card h2, .recent-recordings-card h2 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.recording-setup-card h2 i, .recent-recordings-card h2 i {
    color: var(--primary);
}

/* Form Styling */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-input {
    height: 48px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-white);
    font-size: 14px;
    padding: 0 16px;
    transition: all 0.25s ease;
}

.form-input:focus {
    border-color: var(--primary);
    outline: none;
}

/* Mode Toggle */
.mode-toggle {
    display: flex;
    gap: 8px;
}

.mode-btn {
    flex: 1;
    padding: 12px 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-dim);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.mode-btn:hover {
    border-color: var(--primary);
    color: var(--text-white);
}

.mode-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6));
    border-color: transparent;
    color: white;
}

/* Recording Section */
.recording-section, .upload-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
}

.video-preview-container {
    position: relative;
    width: 100%;
    max-width: 800px;
    margin: 0 auto 20px;
    border-radius: 12px;
    overflow: hidden;
    background: #000;
}

.video-preview {
    width: 100%;
    height: auto;
    min-height: 400px;
    display: block;
    background: #000;
}

.preview-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.8);
    color: var(--text-dim);
}

.preview-overlay i {
    font-size: 64px;
    margin-bottom: 16px;
    color: var(--primary);
    opacity: 0.5;
}

.recording-indicator {
    position: absolute;
    top: 20px;
    left: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.recording-dot {
    width: 10px;
    height: 10px;
    background: white;
    border-radius: 50%;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.recording-controls {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

/* Recorded Video */
.recorded-video-container {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

.recorded-video-container h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.recorded-video-container h3 i {
    color: var(--primary);
}

.recorded-video {
    width: 100%;
    max-width: 600px;
    border-radius: 12px;
    margin: 0 auto 16px;
    display: block;
}

.recorded-video-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
}

/* File Upload Area */
.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 60px 32px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.file-upload-area:hover, .file-upload-area.drag-over {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.file-upload-area i {
    font-size: 52px;
    color: var(--primary);
    opacity: 0.5;
    margin-bottom: 16px;
}

.file-upload-area p {
    color: var(--text-dim);
    margin-bottom: 8px;
}

.file-hint {
    font-size: 12px;
    opacity: 0.7;
}

.selected-file-info {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--bg-main);
    border-radius: 12px;
    margin-top: 20px;
}

.file-icon {
    width: 48px;
    height: 48px;
    background: rgba(107, 70, 193, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
}

.file-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.file-name {
    color: var(--text-white);
    font-weight: 600;
}

.file-size {
    color: var(--text-dim);
    font-size: 13px;
}

.upload-actions {
    margin-top: 20px;
    text-align: center;
}

/* Upload Progress */
.upload-progress {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    color: var(--text-white);
    font-weight: 600;
}

.progress-bar {
    height: 8px;
    background: var(--bg-main);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--accent, #8B5CF6));
    border-radius: 4px;
    width: 0%;
    transition: width 0.3s ease;
}

/* Button Styles */
.btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 14px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6));
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(107, 70, 193, 0.4);
}

.btn-secondary {
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-white);
}

.btn-secondary:hover {
    border-color: var(--primary);
}

.btn-danger {
    background: #EF4444;
    color: white;
}

.btn-warning {
    background: #F59E0B;
    color: white;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    justify-content: center;
}

/* Placeholder */
.placeholder-container {
    text-align: center;
    padding: 40px;
}

.placeholder-icon {
    font-size: 48px;
    color: var(--primary);
    opacity: 0.25;
    margin-bottom: 16px;
}

.placeholder-text {
    color: var(--text-dim);
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .recording-controls {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .mode-toggle {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let mediaRecorder = null;
    let recordedChunks = [];
    let stream = null;
    let currentFacingMode = 'environment'; // Start on back camera
    
    const videoPreview = document.getElementById('videoPreview');
    const recordedVideo = document.getElementById('recordedVideo');
    const previewOverlay = document.getElementById('previewOverlay');
    const recordingIndicator = document.getElementById('recordingIndicator');
    const recordedVideoContainer = document.getElementById('recordedVideoContainer');

    // Track active XHR for cancel support
    var drillUploadXhr = null;
    var cancelDrillBtn = document.getElementById('cancelDrillUploadBtn');
    if (cancelDrillBtn) {
        cancelDrillBtn.addEventListener('click', function() {
            if (drillUploadXhr) {
                drillUploadXhr.abort();
                drillUploadXhr = null;
            }
            document.getElementById('uploadProgress').style.display = 'none';
            showToast('Upload cancelled.', 'info');
        });
    }
    
    const startCameraBtn = document.getElementById('startCameraBtn');
    const flipCameraBtn = document.getElementById('flipCameraBtn');
    const startRecordBtn = document.getElementById('startRecordBtn');
    const stopRecordBtn = document.getElementById('stopRecordBtn');
    const saveVideoBtn = document.getElementById('saveVideoBtn');
    const discardBtn = document.getElementById('discardBtn');
    const uploadBtn = document.getElementById('uploadBtn');
    
    // Mode toggle
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const mode = this.dataset.mode;
            document.getElementById('cameraSection').style.display = mode === 'camera' ? 'block' : 'none';
            document.getElementById('uploadSection').style.display = mode === 'upload' ? 'block' : 'none';
        });
    });
    
    // Start Camera (defaults to back camera)
    startCameraBtn.addEventListener('click', async function() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: { ideal: currentFacingMode },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }, 
                audio: true 
            });
            videoPreview.srcObject = stream;
            previewOverlay.style.display = 'none';
            startRecordBtn.disabled = false;
            startCameraBtn.textContent = 'Camera Active';
            startCameraBtn.disabled = true;
            flipCameraBtn.style.display = 'inline-flex';
        } catch (err) {
            showToast('Error accessing camera: ' + err.message, 'error');
            console.error('Camera error:', err);
        }
    });

    // Flip Camera
    flipCameraBtn.addEventListener('click', async function() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            flipCameraBtn.style.opacity = '0.5';
            setTimeout(function() { flipCameraBtn.style.opacity = '1'; }, 300);
            return;
        }
        currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
        try {
            // Stop existing tracks
            if (stream) {
                stream.getTracks().forEach(function(track) { track.stop(); });
            }
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: currentFacingMode },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: true
            });
            videoPreview.srcObject = stream;
        } catch (err) {
            // If ideal facingMode fails, try without constraint
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 1920 }, height: { ideal: 1080 } },
                    audio: true
                });
                videoPreview.srcObject = stream;
            } catch (err2) {
                showToast('Error switching camera: ' + err2.message, 'error');
                console.error('Camera flip error:', err2);
            }
        }
    });
    
    // Start Recording
    startRecordBtn.addEventListener('click', function() {
        if (!stream) return;
        
        // Validate form
        const session = document.getElementById('sessionSelect').value;
        const drill = document.getElementById('drillSelect').value;
        const athlete = document.getElementById('athleteSelect').value;
        
        if (!session || !drill || !athlete) {
            showToast('Please select a session, drill, and athlete before recording.', 'error');
            return;
        }
        
        recordedChunks = [];
        const options = { mimeType: 'video/webm;codecs=vp9,opus' };
        
        try {
            mediaRecorder = new MediaRecorder(stream, options);
        } catch (e) {
            // Try fallback codec
            try {
                mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm' });
            } catch (e2) {
                mediaRecorder = new MediaRecorder(stream);
            }
        }
        
        mediaRecorder.ondataavailable = function(e) {
            if (e.data.size > 0) {
                recordedChunks.push(e.data);
            }
        };
        
        mediaRecorder.onstop = function() {
            const blob = new Blob(recordedChunks, { type: 'video/webm' });
            recordedVideo.src = URL.createObjectURL(blob);
            recordedVideoContainer.style.display = 'block';
        };
        
        mediaRecorder.start();
        recordingIndicator.style.display = 'flex';
        startRecordBtn.style.display = 'none';
        stopRecordBtn.style.display = 'inline-flex';
    });
    
    // Stop Recording
    stopRecordBtn.addEventListener('click', function() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            recordingIndicator.style.display = 'none';
            stopRecordBtn.style.display = 'none';
            saveVideoBtn.style.display = 'inline-flex';
        }
    });
    
    // Discard Recording
    discardBtn.addEventListener('click', function() {
        recordedChunks = [];
        recordedVideo.src = '';
        recordedVideoContainer.style.display = 'none';
        startRecordBtn.style.display = 'inline-flex';
        saveVideoBtn.style.display = 'none';
        
        // Increment rep number
        const repInput = document.getElementById('repNumber');
        repInput.value = parseInt(repInput.value) + 1;
    });
    
    // Upload to Cloud — direct-to-RustFS via presigned URL (3-step flow)
    uploadBtn.addEventListener('click', function() {
        if (recordedChunks.length === 0) return;

        var blob = new Blob(recordedChunks, { type: 'video/webm' });
        var csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        var sessionId = document.getElementById('sessionSelect').value;
        var drillId = document.getElementById('drillSelect').value;
        var athleteId = document.getElementById('athleteSelect').value;
        var repNumber = document.getElementById('repNumber').value;

        // Show progress
        document.getElementById('uploadProgress').style.display = 'block';
        var progressFill = document.getElementById('progressFill');
        var uploadPercent = document.getElementById('uploadPercent');
        uploadBtn.disabled = true;

        // Step 1: get presigned URL
        var formMeta = new FormData();
        formMeta.append('action', 'get_video_upload_url');
        formMeta.append('upload_type', 'drill_video');
        formMeta.append('csrf_token', csrfToken);
        formMeta.append('session_id', sessionId);
        formMeta.append('drill_id', drillId);
        formMeta.append('athlete_id', athleteId);
        formMeta.append('rep_number', repNumber);
        formMeta.append('file_name', 'drill_recording.webm');
        formMeta.append('file_size', blob.size);
        formMeta.append('file_type', blob.type || 'video/webm');

        var uploadNonce = null;
        var proxyUploadUrl = null;
        var proxyToken = null;
        var contentType = null;

        fetch('process_video.php', { method: 'POST', body: formMeta })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Failed to get upload URL');
                uploadNonce = data.upload_nonce;
                proxyUploadUrl = data.proxy_upload_url || null;
                proxyToken = data.proxy_token || null;
                contentType = data.content_type || blob.type || 'video/webm';

                // Step 2: upload via proxy (preferred) or direct to RustFS
                var uploadUrl = (proxyUploadUrl && proxyToken) ? proxyUploadUrl : data.presigned_url;
                var useProxy = !!(proxyUploadUrl && proxyToken);
                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    drillUploadXhr = xhr;
                    xhr.open('PUT', uploadUrl, true);
                    xhr.setRequestHeader('Content-Type', contentType);
                    if (useProxy) xhr.setRequestHeader('X-Upload-Token', proxyToken);
                    var uploadStarted = false;
                    var connTimer = setTimeout(function() {
                        if (!uploadStarted) { xhr.abort(); reject(new Error((useProxy ? 'Proxy' : 'Cloud storage') + ' connection timed out')); }
                    }, 30000);
                    xhr.upload.onprogress = function(ev) {
                        if (!uploadStarted) { uploadStarted = true; clearTimeout(connTimer); }
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            progressFill.style.width = pct + '%';
                            uploadPercent.textContent = pct + '%';
                        }
                    };
                    xhr.onload = function() {
                        clearTimeout(connTimer);
                        if (xhr.status >= 200 && xhr.status < 300) resolve();
                        else reject(new Error((useProxy ? 'Proxy' : 'Cloud') + ' upload failed (HTTP ' + xhr.status + ')'));
                    };
                    xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error during upload')); };
                    xhr.send(blob);
                });
            })
            .then(function() {
                // Step 3: confirm upload
                var confirmData = new FormData();
                confirmData.append('action', 'confirm_video_upload');
                confirmData.append('csrf_token', csrfToken);
                confirmData.append('upload_nonce', uploadNonce);
                return fetch('process_video.php', { method: 'POST', body: confirmData })
                    .then(function(r) { return r.json(); });
            })
            .then(function(result) {
                if (result.success) {
                    persistToast('Video uploaded successfully!', 'success');
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    throw new Error(result.error || 'Confirmation failed');
                }
            })
            .catch(function(err) {
                showToast('Upload failed: ' + err.message, 'error');
                uploadBtn.disabled = false;
            })
            .finally(function() {
                document.getElementById('uploadProgress').style.display = 'none';
            });
    });
    
    // File Upload handling
    const fileDropZone = document.getElementById('fileDropZone');
    const videoFileInput = document.getElementById('videoFileInput');
    const browseFilesBtn = document.getElementById('browseFilesBtn');
    const selectedFileInfo = document.getElementById('selectedFileInfo');
    const uploadFileBtn = document.getElementById('uploadFileBtn');
    
    browseFilesBtn.addEventListener('click', () => videoFileInput.click());
    fileDropZone.addEventListener('click', () => videoFileInput.click());
    
    fileDropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileDropZone.classList.add('drag-over');
    });
    
    fileDropZone.addEventListener('dragleave', () => {
        fileDropZone.classList.remove('drag-over');
    });
    
    fileDropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        fileDropZone.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
            videoFileInput.files = e.dataTransfer.files;
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });
    
    videoFileInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFileSelect(this.files[0]);
        }
    });
    
    function handleFileSelect(file) {
        const maxSize = 10 * 1024 * 1024 * 1024; // 10GB
        
        if (file.size > maxSize) {
            showToast('File size exceeds 10GB limit. Please choose a smaller file.', 'error');
            videoFileInput.value = '';
            selectedFileInfo.style.display = 'none';
            uploadFileBtn.disabled = true;
            return;
        }
        
        document.getElementById('selectedFileName').textContent = file.name;
        document.getElementById('selectedFileSize').textContent = formatFileSize(file.size);
        selectedFileInfo.style.display = 'flex';
        uploadFileBtn.disabled = false;
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    document.getElementById('removeFileBtn')?.addEventListener('click', function() {
        videoFileInput.value = '';
        selectedFileInfo.style.display = 'none';
        uploadFileBtn.disabled = true;
    });

    // Upload file button click handler — direct-to-RustFS via presigned URL (3-step flow)
    uploadFileBtn.addEventListener('click', function() {
        if (!videoFileInput.files.length) return;

        var session = document.getElementById('sessionSelect').value;
        var drill = document.getElementById('drillSelect').value;
        var athlete = document.getElementById('athleteSelect').value;

        if (!session || !drill || !athlete) {
            showToast('Please select a session, drill, and athlete before uploading.', 'error');
            return;
        }

        var videoFile = videoFileInput.files[0];
        var csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        // Show progress
        document.getElementById('uploadProgress').style.display = 'block';
        var progressFill = document.getElementById('progressFill');
        var uploadPercent = document.getElementById('uploadPercent');
        uploadFileBtn.disabled = true;

        // Step 1: get presigned URL
        var formMeta = new FormData();
        formMeta.append('action', 'get_video_upload_url');
        formMeta.append('upload_type', 'drill_video');
        formMeta.append('csrf_token', csrfToken);
        formMeta.append('session_id', session);
        formMeta.append('drill_id', drill);
        formMeta.append('athlete_id', athlete);
        formMeta.append('rep_number', document.getElementById('repNumber').value);
        formMeta.append('file_name', videoFile.name);
        formMeta.append('file_size', videoFile.size);
        formMeta.append('file_type', videoFile.type || 'video/mp4');

        var uploadNonce = null;
        var proxyUploadUrl = null;
        var proxyToken = null;
        var contentType = null;

        fetch('process_video.php', { method: 'POST', body: formMeta })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Failed to get upload URL');
                uploadNonce = data.upload_nonce;
                proxyUploadUrl = data.proxy_upload_url || null;
                proxyToken = data.proxy_token || null;
                contentType = data.content_type || videoFile.type || 'application/octet-stream';

                // Step 2: upload via proxy (preferred) or direct to RustFS
                var uploadUrl = (proxyUploadUrl && proxyToken) ? proxyUploadUrl : data.presigned_url;
                var useProxy = !!(proxyUploadUrl && proxyToken);
                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    drillUploadXhr = xhr;
                    xhr.open('PUT', uploadUrl, true);
                    xhr.setRequestHeader('Content-Type', contentType);
                    if (useProxy) xhr.setRequestHeader('X-Upload-Token', proxyToken);
                    var uploadStarted = false;
                    var connTimer = setTimeout(function() {
                        if (!uploadStarted) { xhr.abort(); reject(new Error((useProxy ? 'Proxy' : 'Cloud storage') + ' connection timed out')); }
                    }, 30000);
                    xhr.upload.onprogress = function(ev) {
                        if (!uploadStarted) { uploadStarted = true; clearTimeout(connTimer); }
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            progressFill.style.width = pct + '%';
                            uploadPercent.textContent = pct + '%';
                        }
                    };
                    xhr.onload = function() {
                        clearTimeout(connTimer);
                        if (xhr.status >= 200 && xhr.status < 300) resolve();
                        else reject(new Error((useProxy ? 'Proxy' : 'Cloud') + ' upload failed (HTTP ' + xhr.status + ')'));
                    };
                    xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error during upload')); };
                    xhr.send(videoFile);
                });
            })
            .then(function() {
                // Step 3: confirm upload
                var confirmData = new FormData();
                confirmData.append('action', 'confirm_video_upload');
                confirmData.append('csrf_token', csrfToken);
                confirmData.append('upload_nonce', uploadNonce);
                return fetch('process_video.php', { method: 'POST', body: confirmData })
                    .then(function(r) { return r.json(); });
            })
            .then(function(result) {
                if (result.success) {
                    persistToast('Video uploaded successfully!', 'success');
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    throw new Error(result.error || 'Confirmation failed');
                }
            })
            .catch(function(err) {
                showToast('Upload failed: ' + (err.message || 'Unknown error'), 'error');
                uploadFileBtn.disabled = false;
            })
            .finally(function() {
                document.getElementById('uploadProgress').style.display = 'none';
            });
    });
});
</script>
