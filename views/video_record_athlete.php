<?php
/**
 * Athlete Video Recording/Upload Interface
 * Allows athletes to record videos using their device camera or upload existing videos
 * for coach review. Supports direct upload to Nextcloud or offline recording mode.
 */

// Get the current user's assigned coach
$assigned_coach_id = null;
$assigned_coach_name = '';
// Check assigned_coach_id for all user roles, not just athletes/parents
$coach_stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
$coach_stmt->execute([$user_id]);
$coach_row = $coach_stmt->fetch();
$assigned_coach_id = $coach_row['assigned_coach_id'] ?? null;

if ($assigned_coach_id) {
    $coach_name_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $coach_name_stmt->execute([$assigned_coach_id]);
    $coach_row_data = $coach_name_stmt->fetch(PDO::FETCH_ASSOC);
    if ($coach_row_data) {
        $coach_row_data = decryptUserRow($coach_row_data);
        $assigned_coach_name = trim(($coach_row_data['first_name'] ?? '') . ' ' . ($coach_row_data['last_name'] ?? ''));
    }
}

// Get user's teams from profile settings
$user_teams = [];
try {
    $teams_stmt = $pdo->prepare("
        SELECT id, team_name, league, is_current 
        FROM athlete_teams 
        WHERE (user_id = ? OR athlete_id = ?) AND is_current = 1
        ORDER BY team_name
    ");
    $teams_stmt->execute([$user_id, $user_id]);
    $user_teams = $teams_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to load user teams: " . $e->getMessage());
}

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

<div class="record-video-container">
    <div class="record-header">
        <h2><i class="fas fa-circle-dot"></i> Record Video</h2>
        <p>Record a video directly from your device or upload an existing video for coach review</p>
    </div>

    <!-- Connection Status - Same as coach recording interface -->
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

    <?php if (!$assigned_coach_id): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <span>You don't have an assigned coach yet. Videos will be uploaded and can be assigned for review later.</span>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-user-tie"></i>
        <span>Your coach: <strong><?= htmlspecialchars($assigned_coach_name) ?></strong></span>
    </div>
    <?php endif; ?>

    <div class="record-options">
        <!-- Record with Camera Option -->
        <div class="record-option-card" id="camera-record-option">
            <div class="option-icon">
                <i class="fas fa-video"></i>
            </div>
            <h3>Record with Camera</h3>
            <p>Use your device's camera to record a video directly</p>
            <button class="btn btn-primary" id="start-camera-btn">
                <i class="fas fa-camera"></i> Start Recording
            </button>
        </div>

        <!-- Upload Existing Video Option -->
        <div class="record-option-card" id="upload-option">
            <div class="option-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h3>Upload Video</h3>
            <p>Upload an existing video file from your device</p>
            <button class="btn btn-secondary" id="upload-video-btn">
                <i class="fas fa-upload"></i> Choose File
            </button>
        </div>
    </div>

    <!-- Camera Recording Interface (hidden by default) -->
    <div class="camera-interface" id="camera-interface" style="display: none;">
        <div class="form-group" style="margin-bottom:12px;">
            <label for="camera_recording_name">Recording Name <span class="required">*</span></label>
            <input type="text" id="camera_recording_name" placeholder="e.g., Skating Drill Practice" required
                   style="width:100%;padding:10px 14px;background:var(--bg-main,#06080b);border:1px solid var(--border,#1e293b);border-radius:8px;color:var(--text-white,#fff);font-size:14px;">
        </div>
        <div class="camera-preview-container">
            <video id="camera-preview" autoplay playsinline muted></video>
            <div class="recording-indicator" id="recording-indicator" style="display: none;">
                <span class="recording-dot"></span> Recording
            </div>
        </div>
        <div class="camera-controls">
            <button class="btn btn-danger" id="record-btn">
                <i class="fas fa-circle"></i> Start
            </button>
            <button class="btn btn-secondary" id="stop-camera-btn">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="upload-interface" id="upload-interface" style="display: none;">
        <form class="upload-form" method="POST" action="process_video.php" enctype="multipart/form-data" id="video-upload-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="athlete_upload_video">
            <input type="hidden" name="coach_id" value="<?= (int)$assigned_coach_id ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="video_title">Video Title <span class="required">*</span></label>
                    <input type="text" id="video_title" name="title" required placeholder="e.g., Skating Drill Practice">
                </div>
                <div class="form-group">
                    <label for="video_type">Video Type</label>
                    <select id="video_type" name="video_category">
                        <option value="drill">Drill Practice</option>
                        <option value="game">Game Footage</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($user_teams)): ?>
            <div class="form-group">
                <label for="team_id">Team (Optional)</label>
                <select id="team_id" name="team_id">
                    <option value="">-- Select Team --</option>
                    <?php foreach ($user_teams as $team): ?>
                    <option value="<?= (int)$team['id'] ?>">
                        <?= htmlspecialchars($team['team_name']) ?> 
                        <?= $team['league'] ? '(' . htmlspecialchars($team['league']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="video_description">Description / Notes for Coach <span class="required">*</span></label>
                <textarea id="video_description" name="description" rows="3" required
                          placeholder="Describe what you're working on or what feedback you'd like..."></textarea>
            </div>

            <div class="form-group">
                <label for="video_file">Video File <span class="required">*</span></label>
                <div class="file-upload-area" id="file-upload-area">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to select or drag and drop your video</p>
                    <span class="file-hint">Supported formats: MP4, MKV, MOV, AVI, WebM (max 10GB)</span>
                    <input type="file" id="video_file" name="video_file" accept="video/*" required style="display: none;">
                </div>
                <div class="selected-file" id="selected-file" style="display: none;">
                    <i class="fas fa-file-video"></i>
                    <span id="selected-file-name"></span>
                    <button type="button" class="btn-remove" id="remove-file-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="cancel-upload-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-primary" id="submit-upload-btn">
                    <i class="fas fa-upload"></i> Upload for Review
                </button>
            </div>

            <!-- Upload Progress Overlay -->
            <div id="uploadProgressOverlay" class="upload-progress-overlay" style="display: none;">
                <div class="upload-progress-card">
                    <div class="spinner"></div>
                    <h4>Uploading Video...</h4>
                    <p class="upload-progress-text">Uploading your video for coach review. Please do not close this page.</p>
                    <div class="upload-progress-bar-container">
                        <div class="upload-progress-bar" id="uploadProgressBar"></div>
                    </div>
                    <span class="upload-progress-percent" id="uploadProgressPercent">0%</span>
                    <span class="upload-progress-status" id="uploadProgressStatus">Preparing upload...</span>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.record-video-container {
    max-width: 900px;
    margin: 0 auto;
}

.record-header {
    text-align: center;
    margin-bottom: 32px;
}

.record-header h2 {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.record-header h2 i {
    color: var(--primary);
    margin-right: 10px;
}

.record-header p {
    color: var(--text-dim);
}

/* Connection Status - Same styling as coach recording interface */
.connection-status {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.connection-status.status-connected {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.connection-status.status-offline {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.connection-status .status-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-connected .status-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-offline .status-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.connection-status .status-icon i {
    font-size: 20px;
}

.connection-status .status-info {
    flex: 1;
}

.connection-status .status-info strong {
    display: block;
    font-size: 15px;
    color: var(--text-white);
    margin-bottom: 4px;
}

.connection-status .status-info span {
    font-size: 14px;
    color: var(--text-dim);
}

.connection-status .status-error {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #ef4444;
}

.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.alert-info {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.record-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.record-option-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 16px;
    padding: 32px;
    text-align: center;
    transition: all 0.3s ease;
}

.record-option-card:hover {
    border-color: var(--primary, #6B46C1);
    transform: translateY(-2px);
}

.option-icon {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: rgba(107, 70, 193, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.option-icon i {
    font-size: 32px;
    color: var(--primary, #6B46C1);
}

.record-option-card h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 8px;
}

.record-option-card p {
    color: var(--text-dim);
    margin-bottom: 20px;
    font-size: 14px;
}

.camera-interface {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
}

.camera-preview-container {
    position: relative;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 20px;
}

#camera-preview {
    width: 100%;
    max-height: 400px;
    object-fit: contain;
}

.recording-indicator {
    position: absolute;
    top: 16px;
    left: 16px;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
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

.camera-controls {
    display: flex;
    justify-content: center;
    gap: 16px;
}

.upload-interface {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 16px;
    padding: 32px;
}

.upload-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.upload-form .form-group {
    margin-bottom: 20px;
}

.upload-form label {
    display: block;
    font-weight: 500;
    color: var(--text-white);
    margin-bottom: 8px;
}

.upload-form label .required {
    color: #ef4444;
}

.upload-form input[type="text"],
.upload-form select,
.upload-form textarea {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-main, #0F0F14);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    color: var(--text-white);
    font-size: 14px;
}

.upload-form input[type="text"]:focus,
.upload-form select:focus,
.upload-form textarea:focus {
    border-color: var(--primary, #6B46C1);
    outline: none;
}

.file-upload-area {
    border: 2px dashed var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 48px 32px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-upload-area:hover {
    border-color: var(--primary, #6B46C1);
    background: rgba(107, 70, 193, 0.05);
}

.file-upload-area i {
    font-size: 48px;
    color: var(--primary, #6B46C1);
    opacity: 0.5;
    display: block;
    margin-bottom: 16px;
}

.file-upload-area p {
    color: var(--text-dim);
    margin-bottom: 8px;
}

.file-upload-area .file-hint {
    font-size: 12px;
    color: var(--text-muted);
}

.selected-file {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid var(--primary, #6B46C1);
    border-radius: 10px;
}

.selected-file i {
    font-size: 24px;
    color: var(--primary, #6B46C1);
}

.selected-file span {
    flex: 1;
    color: var(--text-white);
    font-weight: 500;
}

.btn-remove {
    background: transparent;
    border: none;
    color: var(--text-dim);
    cursor: pointer;
    padding: 4px 8px;
}

.btn-remove:hover {
    color: #ef4444;
}

.form-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border, #2D2D3F);
}

@media (max-width: 600px) {
    .upload-form .form-row {
        grid-template-columns: 1fr;
    }
    
    .record-options {
        grid-template-columns: 1fr;
    }
}

/* Upload Progress Overlay */
.upload-progress-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.upload-progress-card {
    background: var(--bg-card, #0d1117);
    border: 1px solid var(--border, #1e293b);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    max-width: 420px;
    width: 90%;
}

.upload-progress-card .spinner {
    width: 36px;
    height: 36px;
    margin: 0 auto 16px;
    border: 3px solid var(--border, #1e293b);
    border-top-color: var(--primary, #7c3aed);
    border-radius: 50%;
    animation: upload-spin 0.8s linear infinite;
}

@keyframes upload-spin {
    to { transform: rotate(360deg); }
}

.upload-progress-card h4 {
    color: var(--text-white, #fff);
    font-size: 18px;
    margin-bottom: 8px;
}

.upload-progress-text {
    color: var(--text-dim, #64748b);
    font-size: 13px;
    margin-bottom: 20px;
}

.upload-progress-bar-container {
    width: 100%;
    height: 8px;
    background: var(--bg-main, #06080b);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.upload-progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--primary, #7c3aed), #a78bfa);
    border-radius: 4px;
    transition: width 0.4s ease;
}

.upload-progress-percent {
    display: block;
    color: var(--text-white, #fff);
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 4px;
}

.upload-progress-status {
    color: var(--text-dim, #64748b);
    font-size: 12px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startCameraBtn = document.getElementById('start-camera-btn');
    const uploadVideoBtn = document.getElementById('upload-video-btn');
    const cameraInterface = document.getElementById('camera-interface');
    const uploadInterface = document.getElementById('upload-interface');
    const recordOptions = document.querySelector('.record-options');
    const stopCameraBtn = document.getElementById('stop-camera-btn');
    const cancelUploadBtn = document.getElementById('cancel-upload-btn');
    const fileUploadArea = document.getElementById('file-upload-area');
    const videoFileInput = document.getElementById('video_file');
    const selectedFile = document.getElementById('selected-file');
    const selectedFileName = document.getElementById('selected-file-name');
    const removeFileBtn = document.getElementById('remove-file-btn');
    
    let mediaStream = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    const MAX_FILE_SIZE = 10 * 1024 * 1024 * 1024; // 10GB in bytes
    
    // Show camera recording interface
    if (startCameraBtn) {
        startCameraBtn.addEventListener('click', async function() {
            try {
                // Try user-facing camera first (selfie mode), fall back to environment
                mediaStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user' },
                    audio: true 
                });
                
                const preview = document.getElementById('camera-preview');
                preview.srcObject = mediaStream;
                
                recordOptions.style.display = 'none';
                cameraInterface.style.display = 'block';
            } catch (err) {
                console.error('Camera access error:', err);
                showToast('Unable to access camera. Please check permissions and try again.', 'error');
            }
        });
    }
    
    // Stop camera and go back
    if (stopCameraBtn) {
        stopCameraBtn.addEventListener('click', function() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
                mediaStream = null;
            }
            cameraInterface.style.display = 'none';
            recordOptions.style.display = 'grid';
        });
    }
    
    // Show upload interface
    if (uploadVideoBtn) {
        uploadVideoBtn.addEventListener('click', function() {
            recordOptions.style.display = 'none';
            uploadInterface.style.display = 'block';
        });
    }
    
    // Cancel upload and go back
    if (cancelUploadBtn) {
        cancelUploadBtn.addEventListener('click', function() {
            uploadInterface.style.display = 'none';
            recordOptions.style.display = 'grid';
        });
    }
    
    // Validate file size
    function validateFileSize(file) {
        if (file.size > MAX_FILE_SIZE) {
            showToast('File size exceeds the maximum limit of 10GB. Please choose a smaller file.', 'error');
            return false;
        }
        return true;
    }
    
    // File upload area click
    if (fileUploadArea) {
        fileUploadArea.addEventListener('click', function() {
            videoFileInput.click();
        });
        
        // Drag and drop
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.style.borderColor = 'var(--primary)';
            fileUploadArea.style.background = 'rgba(107, 70, 193, 0.1)';
        });
        
        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.style.borderColor = '';
            fileUploadArea.style.background = '';
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.style.borderColor = '';
            fileUploadArea.style.background = '';
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type.startsWith('video/')) {
                if (validateFileSize(files[0])) {
                    videoFileInput.files = files;
                    showSelectedFile(files[0]);
                }
            }
        });
    }
    
    // File selection change
    if (videoFileInput) {
        videoFileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                if (validateFileSize(this.files[0])) {
                    showSelectedFile(this.files[0]);
                } else {
                    this.value = '';
                }
            }
        });
    }
    
    // Remove selected file
    if (removeFileBtn) {
        removeFileBtn.addEventListener('click', function() {
            videoFileInput.value = '';
            selectedFile.style.display = 'none';
            fileUploadArea.style.display = 'block';
        });
    }
    
    function showSelectedFile(file) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
        selectedFileName.textContent = file.name + ' (' + sizeMB + ' MB)';
        selectedFile.style.display = 'flex';
        fileUploadArea.style.display = 'none';
    }
    
    // Recording functionality
    const recordBtn = document.getElementById('record-btn');
    const recordingIndicator = document.getElementById('recording-indicator');
    let isRecording = false;
    
    if (recordBtn) {
        recordBtn.addEventListener('click', function() {
            if (!isRecording) {
                // Start recording
                recordedChunks = [];
                mediaRecorder = new MediaRecorder(mediaStream);
                
                mediaRecorder.ondataavailable = function(e) {
                    if (e.data.size > 0) {
                        recordedChunks.push(e.data);
                    }
                };
                
                mediaRecorder.onstop = function() {
                    const blob = new Blob(recordedChunks, { type: 'video/webm' });
                    // Generate a descriptive filename with timestamp
                    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
                    const filename = 'athlete-recording-' + timestamp + '.webm';
                    const file = new File([blob], filename, { type: 'video/webm' });
                    
                    // Create a DataTransfer to set the file input
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    videoFileInput.files = dataTransfer.files;
                    
                    // Stop camera
                    if (mediaStream) {
                        mediaStream.getTracks().forEach(track => track.stop());
                        mediaStream = null;
                    }
                    
                    // Switch to upload interface
                    cameraInterface.style.display = 'none';
                    uploadInterface.style.display = 'block';
                    showSelectedFile(file);
                };
                
                mediaRecorder.start();
                isRecording = true;
                recordBtn.innerHTML = '<i class="fas fa-stop"></i> Stop';
                recordBtn.classList.remove('btn-danger');
                recordBtn.classList.add('btn-warning');
                recordingIndicator.style.display = 'flex';
            } else {
                // Stop recording
                mediaRecorder.stop();
                isRecording = false;
                recordBtn.innerHTML = '<i class="fas fa-circle"></i> Start';
                recordBtn.classList.remove('btn-warning');
                recordBtn.classList.add('btn-danger');
                recordingIndicator.style.display = 'none';
            }
        });
    }

    // Video upload — direct-to-RustFS via presigned URL (3-step flow)
    // Bypasses PHP file-size limits so large videos upload reliably.
    var uploadForm = document.getElementById('video-upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var overlay = document.getElementById('uploadProgressOverlay');
            var bar = document.getElementById('uploadProgressBar');
            var percent = document.getElementById('uploadProgressPercent');
            var status = document.getElementById('uploadProgressStatus');
            var submitBtn = document.getElementById('submit-upload-btn');
            var videoFile = document.getElementById('video_file').files[0];

            if (!videoFile) {
                showToast('Please select a video file.', 'error');
                return;
            }

            overlay.style.display = 'flex';
            submitBtn.disabled = true;
            bar.style.width = '0%';
            percent.textContent = '0%';
            status.textContent = 'Requesting upload URL...';

            // Collect form values
            var csrfToken = uploadForm.querySelector('input[name="csrf_token"]')?.value || '';
            var formMeta = new FormData();
            formMeta.append('action', 'get_video_upload_url');
            formMeta.append('upload_type', 'athlete_video');
            formMeta.append('csrf_token', csrfToken);
            formMeta.append('title', document.getElementById('video_title').value);
            formMeta.append('video_category', document.getElementById('video_type').value);
            var descEl = document.getElementById('video_description');
            if (descEl) formMeta.append('description', descEl.value);
            formMeta.append('file_name', videoFile.name);
            formMeta.append('file_size', videoFile.size);
            formMeta.append('file_type', videoFile.type || 'video/mp4');
            var coachInput = uploadForm.querySelector('input[name="coach_id"]');
            if (coachInput && coachInput.value) formMeta.append('coach_id', coachInput.value);
            var teamEl = document.getElementById('team_id');
            if (teamEl && teamEl.value) formMeta.append('team_id', teamEl.value);

            // ---------- Step 1: get presigned URL ----------
            fetch('process_video.php', { method: 'POST', body: formMeta })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) throw new Error(data.error || 'Failed to get upload URL');

                    var presignedUrl = data.presigned_url;
                    var contentType = data.content_type || videoFile.type || 'application/octet-stream';
                    var uploadNonce = data.upload_nonce;

                    status.textContent = 'Uploading to cloud storage...';

                    // ---------- Step 2: PUT file directly to RustFS ----------
                    return new Promise(function(resolve, reject) {
                        var xhr = new XMLHttpRequest();
                        xhr.open('PUT', presignedUrl, true);
                        xhr.setRequestHeader('Content-Type', contentType);

                        xhr.upload.onprogress = function(ev) {
                            if (ev.lengthComputable) {
                                var pct = Math.round((ev.loaded / ev.total) * 100);
                                bar.style.width = pct + '%';
                                percent.textContent = pct + '%';
                                if (pct < 100) {
                                    status.textContent = 'Uploading to cloud storage... ' + pct + '%';
                                } else {
                                    status.textContent = 'Finalizing upload...';
                                }
                            }
                        };

                        xhr.onload = function() {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                resolve(uploadNonce);
                            } else {
                                reject(new Error('Cloud upload failed (HTTP ' + xhr.status + ')'));
                            }
                        };
                        xhr.onerror = function() { reject(new Error('Network error during upload')); };
                        xhr.send(videoFile);
                    });
                })
                .then(function(uploadNonce) {
                    // ---------- Step 3: confirm upload ----------
                    status.textContent = 'Confirming upload...';
                    var confirmData = new FormData();
                    confirmData.append('action', 'confirm_video_upload');
                    confirmData.append('csrf_token', csrfToken);
                    confirmData.append('upload_nonce', uploadNonce);

                    return fetch('process_video.php', { method: 'POST', body: confirmData })
                        .then(function(r) { return r.json(); });
                })
                .then(function(result) {
                    if (result.success) {
                        bar.style.width = '100%';
                        percent.textContent = '100%';
                        status.textContent = 'Upload complete! Redirecting...';
                        window.location.href = result.redirect || 'dashboard.php?page=coaches_reviews&success=video_uploaded';
                    } else {
                        throw new Error(result.error || 'Confirmation failed');
                    }
                })
                .catch(function(err) {
                    // Fall back to legacy server-side upload if presigned flow fails
                    console.warn('Direct upload failed, falling back to server upload:', err.message);
                    status.textContent = 'Retrying via server...';
                    bar.style.width = '0%';
                    percent.textContent = '0%';

                    var legacyData = new FormData(uploadForm);
                    legacyData.set('action', 'athlete_upload_video');
                    var legacyXhr = new XMLHttpRequest();
                    legacyXhr.open('POST', uploadForm.action, true);
                    legacyXhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    legacyXhr.upload.onprogress = function(ev) {
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            bar.style.width = pct + '%';
                            percent.textContent = pct + '%';
                            status.textContent = pct < 100 ? 'Uploading video...' : 'Processing...';
                        }
                    };
                    legacyXhr.onload = function() {
                        try {
                            var resp = JSON.parse(legacyXhr.responseText);
                            if (resp.success) {
                                bar.style.width = '100%';
                                percent.textContent = '100%';
                                status.textContent = 'Upload complete! Redirecting...';
                                window.location.href = resp.redirect || 'dashboard.php?page=coaches_reviews&success=video_uploaded';
                            } else {
                                overlay.style.display = 'none';
                                submitBtn.disabled = false;
                                showToast('Upload failed: ' + (resp.error || 'Please try again.'), 'error');
                            }
                        } catch (parseErr) {
                            overlay.style.display = 'none';
                            submitBtn.disabled = false;
                            showToast('Upload failed: Server error', 'error');
                        }
                    };
                    legacyXhr.onerror = function() {
                        overlay.style.display = 'none';
                        submitBtn.disabled = false;
                        showToast('Upload failed. Please check your connection and try again.', 'error');
                    };
                    legacyXhr.send(legacyData);
                });
        });
    }
});
</script>
