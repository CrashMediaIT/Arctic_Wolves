<?php
/**
 * PWA Video Record Drill - Mobile-native video recording for coaches
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAnyCoach):
?>
<style>
.m-denied { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-denied i { font-size: 48px; margin-bottom: 16px; }
.m-denied p { font-size: 15px; margin: 0; }
</style>
<div class="m-denied">
    <i class="fas fa-lock"></i>
    <p>Access denied</p>
</div>
<?php
    return;
endif;
?>
<style>
.m-record { padding: 16px; font-family: Inter, sans-serif; }
.m-record-header { margin-bottom: 16px; }
.m-record-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-record-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-video-preview {
    width: 100%; aspect-ratio: 16/9; background: #16161F;
    border: 1px solid #2D2D3F; border-radius: 12px;
    overflow: hidden; position: relative; margin-bottom: 16px;
}
.m-video-preview video {
    width: 100%; height: 100%; object-fit: cover; display: block;
}
.m-video-placeholder {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: #6B6B7B; gap: 8px;
}
.m-video-placeholder i { font-size: 40px; }
.m-video-placeholder p { font-size: 13px; margin: 0; }
.m-rec-indicator {
    position: absolute; top: 12px; left: 12px;
    display: none; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 6px;
    background: rgba(239,68,68,0.9); color: #fff; font-size: 11px; font-weight: 600;
}
.m-rec-indicator.m-rec-active { display: flex; }
.m-rec-dot { width: 8px; height: 8px; border-radius: 50%; background: #fff; animation: mRecBlink 1s infinite; }
@keyframes mRecBlink { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
.m-rec-controls { display: flex; gap: 12px; justify-content: center; margin-bottom: 20px; flex-wrap: wrap; }
.m-rec-btn {
    min-width: 56px; min-height: 56px; border-radius: 50%;
    border: none; cursor: pointer; font-size: 18px;
    display: flex; align-items: center; justify-content: center;
    font-family: Inter, sans-serif; font-weight: 600;
    padding: 0; width: 56px; height: 56px;
}
.m-rec-btn:active { transform: scale(0.95); }
.m-rec-btn-camera { background: rgba(59,130,246,0.2); color: #3B82F6; border: 1px solid rgba(59,130,246,0.3); }
.m-rec-btn-record { background: #EF4444; color: #fff; }
.m-rec-btn-stop { background: rgba(168,168,184,0.2); color: #A8A8B8; border: 1px solid #2D2D3F; }
.m-rec-btn-switch { background: rgba(107,70,193,0.2); color: #8B5CF6; border: 1px solid rgba(107,70,193,0.3); }
.m-rec-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.m-upload-section {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; display: none;
}
.m-upload-section.m-upload-visible { display: block; }
.m-upload-label { font-size: 13px; color: #fff; font-weight: 600; margin-bottom: 8px; display: block; }
.m-upload-input {
    width: 100%; min-height: 44px; padding: 10px 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    margin-bottom: 12px; box-sizing: border-box;
}
.m-upload-input:focus { border-color: #8B5CF6; outline: none; }
.m-upload-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; min-height: 48px; border-radius: 10px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif;
}
.m-upload-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.m-upload-status { font-size: 12px; color: #A8A8B8; margin-top: 8px; text-align: center; }
</style>

<div class="m-record">
    <div class="m-record-header">
        <h2 class="m-record-title">Record Drill Video</h2>
        <p class="m-record-sub">Capture drill footage for review</p>
    </div>

    <div class="m-video-preview">
        <video id="mCamPreview" autoplay playsinline muted></video>
        <div class="m-video-placeholder" id="mCamPlaceholder">
            <i class="fas fa-video"></i>
            <p>Tap camera button to start</p>
        </div>
        <div class="m-rec-indicator" id="mRecIndicator">
            <span class="m-rec-dot"></span> REC
        </div>
    </div>

    <div class="m-rec-controls">
        <button class="m-rec-btn m-rec-btn-camera" id="mBtnCamera" type="button" onclick="mStartCamera()" title="Start Camera">
            <i class="fas fa-video"></i>
        </button>
        <button class="m-rec-btn m-rec-btn-record" id="mBtnRecord" type="button" onclick="mToggleRecord()" disabled title="Record">
            <i class="fas fa-circle" id="mRecIcon"></i>
        </button>
        <button class="m-rec-btn m-rec-btn-switch" id="mBtnSwitch" type="button" onclick="mSwitchCamera()" disabled title="Switch Camera">
            <i class="fas fa-camera-rotate"></i>
        </button>
    </div>

    <div class="m-upload-section" id="mUploadSection">
        <form id="mUploadForm" method="POST" action="process_video_upload.php" enctype="multipart/form-data">
            <label class="m-upload-label" for="mVideoTitle">Video Title</label>
            <input type="text" class="m-upload-input" id="mVideoTitle" name="title" placeholder="Enter video title" required>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="m-upload-btn" id="mBtnUpload" disabled>
                <i class="fas fa-cloud-arrow-up"></i> Upload Video
            </button>
            <p class="m-upload-status" id="mUploadStatus"></p>
        </form>
    </div>
</div>

<script>
(function() {
    var stream = null, recorder = null, chunks = [], facingMode = 'environment', blob = null;

    window.mStartCamera = function() {
        if (stream) { stream.getTracks().forEach(function(t) { t.stop(); }); }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: facingMode }, audio: true })
            .then(function(s) {
                stream = s;
                var video = document.getElementById('mCamPreview');
                video.srcObject = s;
                document.getElementById('mCamPlaceholder').style.display = 'none';
                document.getElementById('mBtnRecord').disabled = false;
                document.getElementById('mBtnSwitch').disabled = false;
            })
            .catch(function(err) {
                document.getElementById('mCamPlaceholder').querySelector('p').textContent = 'Camera access denied';
            });
    };

    window.mSwitchCamera = function() {
        facingMode = (facingMode === 'environment') ? 'user' : 'environment';
        mStartCamera();
    };

    window.mToggleRecord = function() {
        if (recorder && recorder.state === 'recording') {
            recorder.stop();
            document.getElementById('mRecIndicator').classList.remove('m-rec-active');
            document.getElementById('mRecIcon').className = 'fas fa-circle';
            return;
        }
        chunks = [];
        recorder = new MediaRecorder(stream);
        recorder.ondataavailable = function(e) { if (e.data.size > 0) chunks.push(e.data); };
        recorder.onstop = function() {
            blob = new Blob(chunks, { type: 'video/webm' });
            document.getElementById('mUploadSection').classList.add('m-upload-visible');
            document.getElementById('mBtnUpload').disabled = false;
            document.getElementById('mUploadStatus').textContent = 'Video ready (' + (blob.size / 1048576).toFixed(1) + ' MB)';
        };
        recorder.start();
        document.getElementById('mRecIndicator').classList.add('m-rec-active');
        document.getElementById('mRecIcon').className = 'fas fa-stop';
    };

    document.getElementById('mUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!blob) return;
        var formData = new FormData();
        formData.append('action', 'upload_drill_video');
        formData.append('video_file', blob, 'drill_video.webm');
        formData.append('title', document.getElementById('mVideoTitle').value);
        var csrfInput = this.querySelector('input[name="csrf_token"]');
        if (csrfInput) formData.append('csrf_token', csrfInput.value);
        var btn = document.getElementById('mBtnUpload');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

        // Create progress bar
        var statusEl = document.getElementById('mUploadStatus');
        var progressWrap = document.createElement('div');
        progressWrap.style.cssText = 'width:100%;height:8px;background:#2D2D3F;border-radius:4px;margin-top:8px;overflow:hidden;';
        var progressBar = document.createElement('div');
        progressBar.style.cssText = 'width:0%;height:100%;background:linear-gradient(135deg,#6B46C1,#8B5CF6);border-radius:4px;transition:width 0.2s;';
        progressWrap.appendChild(progressBar);
        statusEl.parentNode.insertBefore(progressWrap, statusEl.nextSibling);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'process_video.php', true);

        xhr.upload.onprogress = function(ev) {
            if (ev.lengthComputable) {
                var pct = Math.round((ev.loaded / ev.total) * 100);
                progressBar.style.width = pct + '%';
                statusEl.textContent = pct < 100 ? 'Uploading... ' + pct + '%' : 'Processing...';
            }
        };

        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                statusEl.textContent = data.success ? 'Upload complete!' : (data.message || 'Upload failed');
                if (data.success) btn.disabled = true;
                else btn.disabled = false;
            } catch(err) {
                statusEl.textContent = 'Upload failed. Please try again.';
                btn.disabled = false;
            }
            btn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Upload Video';
            progressWrap.remove();
        };

        xhr.onerror = function() {
            statusEl.textContent = 'Upload failed. Please try again.';
            btn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Upload Video';
            btn.disabled = false;
            progressWrap.remove();
        };

        xhr.send(formData);
    });
})();
</script>
