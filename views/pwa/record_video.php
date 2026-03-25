<?php
/**
 * PWA Record Video - Mobile-native video recording interface
 * Purpose-built for mobile phones. Provides camera access, recording,
 * and upload functionality for athletes and coaches.
 */
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
.m-rec-timer {
    position: absolute; top: 12px; right: 12px; display: none;
    padding: 4px 10px; border-radius: 6px;
    background: rgba(0,0,0,0.6); color: #fff; font-size: 12px; font-weight: 600;
    font-variant-numeric: tabular-nums;
}
.m-rec-timer.m-rec-active { display: block; }
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
.m-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: #8B5CF6; font-size: 13px; font-weight: 600;
    text-decoration: none; margin-bottom: 16px;
}
</style>

<div class="m-record">
    <a href="?page=video" class="m-back-link"><i class="fas fa-arrow-left"></i> Back to Videos</a>

    <div class="m-record-header">
        <h2 class="m-record-title">Record Video</h2>
        <p class="m-record-sub">Capture drills or practice for coach review</p>
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
        <div class="m-rec-timer" id="mRecTimer">00:00</div>
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

    <!-- File Upload Option (alternative to recording) -->
    <div style="text-align:center;margin-bottom:16px;">
        <div style="font-size:12px;color:#6B6B7B;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">Or upload an existing video</div>
        <label style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;background:rgba(59,130,246,0.15);color:#3B82F6;font-size:13px;font-weight:600;cursor:pointer;min-height:44px;border:1px solid rgba(59,130,246,0.3);">
            <i class="fas fa-file-video"></i> Choose File
            <input type="file" accept="video/*" id="mFileInput" style="display:none;" onchange="mFileSelected(this)">
        </label>
        <p id="mFileInfo" style="font-size:12px;color:#A8A8B8;margin-top:6px;"></p>
    </div>

    <div class="m-upload-section" id="mUploadSection">
        <form id="mUploadForm" method="POST" action="process_video.php" enctype="multipart/form-data">
            <label class="m-upload-label" for="mVideoTitle">Video Title</label>
            <input type="text" class="m-upload-input" id="mVideoTitle" name="title" placeholder="Enter video title" required>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRFProtection::generateToken()) ?>">
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
    var timerInterval = null, recordStartTime = 0;

    function updateTimer() {
        var elapsed = Math.floor((Date.now() - recordStartTime) / 1000);
        var mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
        var secs = String(elapsed % 60).padStart(2, '0');
        document.getElementById('mRecTimer').textContent = mins + ':' + secs;
    }

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
            .catch(function() {
                document.getElementById('mCamPlaceholder').querySelector('p').textContent = 'Camera access denied';
            });
    };

    window.mSwitchCamera = function() {
        facingMode = (facingMode === 'environment') ? 'user' : 'environment';
        mStartCamera();
    };

    // Handle file upload selection (alternative to recording)
    window.mFileSelected = function(input) {
        if (!input.files || !input.files.length) return;
        var file = input.files[0];
        if (!file.type.startsWith('video/')) {
            document.getElementById('mFileInfo').textContent = 'Please select a video file.';
            document.getElementById('mFileInfo').style.color = '#EF4444';
            return;
        }
        blob = file;
        document.getElementById('mFileInfo').textContent = file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB)';
        document.getElementById('mFileInfo').style.color = '#A8A8B8';
        document.getElementById('mUploadSection').classList.add('m-upload-visible');
        document.getElementById('mBtnUpload').disabled = false;
        document.getElementById('mUploadStatus').textContent = 'Video ready for upload';
    };

    window.mToggleRecord = function() {
        if (recorder && recorder.state === 'recording') {
            recorder.stop();
            document.getElementById('mRecIndicator').classList.remove('m-rec-active');
            document.getElementById('mRecTimer').classList.remove('m-rec-active');
            document.getElementById('mRecIcon').className = 'fas fa-circle';
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
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
        recordStartTime = Date.now();
        timerInterval = setInterval(updateTimer, 1000);
        document.getElementById('mRecIndicator').classList.add('m-rec-active');
        document.getElementById('mRecTimer').classList.add('m-rec-active');
        document.getElementById('mRecIcon').className = 'fas fa-stop';
    };

    document.getElementById('mUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!blob) return;

        var csrfInput = this.querySelector('input[name="csrf_token"]');
        var csrfToken = csrfInput ? csrfInput.value : '';
        if (!csrfToken) {
            document.getElementById('mUploadStatus').textContent = 'Security token missing. Please reload the page.';
            document.getElementById('mUploadStatus').style.color = '#EF4444';
            return;
        }
        var btn = document.getElementById('mBtnUpload');
        var statusEl = document.getElementById('mUploadStatus');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

        var progressWrap = document.createElement('div');
        progressWrap.style.cssText = 'width:100%;height:8px;background:#2D2D3F;border-radius:4px;margin-top:8px;overflow:hidden;';
        var progressBar = document.createElement('div');
        progressBar.style.cssText = 'width:0%;height:100%;background:linear-gradient(135deg,#6B46C1,#8B5CF6);border-radius:4px;transition:width 0.2s;';
        progressWrap.appendChild(progressBar);
        statusEl.parentNode.insertBefore(progressWrap, statusEl.nextSibling);

        var uploadNonce = null;

        var formMeta = new FormData();
        formMeta.append('action', 'get_video_upload_url');
        formMeta.append('upload_type', 'athlete_video');
        formMeta.append('csrf_token', csrfToken);
        formMeta.append('title', document.getElementById('mVideoTitle').value);
        formMeta.append('video_category', 'drill');
        var isRecordedBlob = (blob instanceof Blob && !(blob instanceof File));
        formMeta.append('file_name', isRecordedBlob ? 'recorded_video.webm' : (blob.name || 'uploaded_video.mp4'));
        formMeta.append('file_size', blob.size);
        formMeta.append('file_type', blob.type || (isRecordedBlob ? 'video/webm' : 'video/mp4'));

        statusEl.textContent = 'Requesting upload URL...';

        fetch('process_video.php', { method: 'POST', body: formMeta })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Failed to get upload URL');
                var presignedUrl = data.presigned_url;
                var contentType = data.content_type || blob.type || 'video/webm';
                uploadNonce = data.upload_nonce;
                var proxyUploadUrl = data.proxy_upload_url || null;
                var proxyToken = data.proxy_token || null;

                statusEl.textContent = 'Uploading to cloud storage...';

                var url = presignedUrl ? presignedUrl : ((proxyUploadUrl && proxyToken) ? proxyUploadUrl : null);
                var useProxy = !presignedUrl && !!(proxyUploadUrl && proxyToken);
                if (!url) throw new Error('No upload URL available');

                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('PUT', url, true);
                    xhr.setRequestHeader('Content-Type', contentType);
                    if (useProxy) xhr.setRequestHeader('X-Upload-Token', proxyToken);
                    xhr.upload.onprogress = function(ev) {
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            progressBar.style.width = pct + '%';
                            statusEl.textContent = pct < 100 ? 'Uploading... ' + pct + '%' : 'Finalizing...';
                        }
                    };
                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 300) resolve();
                        else reject(new Error('Upload failed (HTTP ' + xhr.status + ')'));
                    };
                    xhr.onerror = function() { reject(new Error('Network error during upload')); };
                    xhr.send(blob);
                });
            })
            .then(function() {
                statusEl.textContent = 'Confirming upload...';
                var confirmData = new FormData();
                confirmData.append('action', 'confirm_video_upload');
                confirmData.append('csrf_token', csrfToken);
                confirmData.append('upload_nonce', uploadNonce);
                return fetch('process_video.php', { method: 'POST', body: confirmData })
                    .then(function(r) { return r.json(); });
            })
            .then(function(result) {
                if (result.success) {
                    statusEl.textContent = 'Upload complete!';
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-check"></i> Uploaded';
                    progressWrap.remove();
                    setTimeout(function() { window.location.href = '?page=video'; }, 1000);
                } else {
                    throw new Error(result.error || 'Confirmation failed');
                }
            })
            .catch(function(err) {
                statusEl.textContent = err.message || 'Upload failed';
                statusEl.style.color = '#EF4444';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Upload Video';
                if (progressWrap.parentNode) progressWrap.remove();
            });
    });
})();
</script>