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
        <form id="mUploadForm" method="POST" action="process_video.php" enctype="multipart/form-data">
            <label class="m-upload-label" for="mVideoTitle">Video Title</label>
            <input type="text" class="m-upload-input" id="mVideoTitle" name="title" placeholder="Enter video title" required>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="m-upload-btn" id="mBtnUpload" disabled>
                <i class="fas fa-cloud-arrow-up"></i> Upload Video
            </button>
            <p class="m-upload-status" id="mUploadStatus"></p>
            <details id="pwaUploadLogDetails" style="width:100%;margin-top:8px;text-align:left;display:none;">
                <summary style="cursor:pointer;font-weight:600;font-size:12px;color:var(--text-dim,#6b7280);user-select:none;">
                    <i class="fas fa-terminal"></i> Upload Log
                </summary>
                <pre id="pwaUploadLogPre" style="margin-top:6px;max-height:180px;overflow:auto;background:#0a0a0f;color:#cdd6f4;padding:10px;border-radius:6px;font-size:11px;white-space:pre-wrap;line-height:1.5;"></pre>
            </details>
        </form>
    </div>
</div>

<script>
(function() {
    var stream = null, recorder = null, chunks = [], facingMode = 'environment', blob = null;

    var PROGRESS_LOG_INTERVAL = 10;
    var pwaLogPre = document.getElementById('pwaUploadLogPre');
    var pwaLogDetails = document.getElementById('pwaUploadLogDetails');
    function pwaLog(msg) {
        if (pwaLogPre) {
            pwaLogPre.textContent += '[' + new Date().toLocaleTimeString() + '] ' + msg + '\n';
            pwaLogPre.scrollTop = pwaLogPre.scrollHeight;
        }
        console.log('[PWADrillUpload] ' + msg);
    }
    function pwaLogError(msg) { pwaLog('ERROR: ' + msg); }
    function pwaLogWarn(msg) { pwaLog('WARNING: ' + msg); }
    function pwaElapsed(start) {
        var s = Math.round((Date.now() - start) / 1000);
        return s < 60 ? s + 's' : Math.floor(s / 60) + 'm ' + (s % 60) + 's';
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

        var csrfInput = this.querySelector('input[name="csrf_token"]');
        var csrfToken = csrfInput ? csrfInput.value : '';
        var btn = document.getElementById('mBtnUpload');
        var statusEl = document.getElementById('mUploadStatus');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        if (pwaLogDetails) pwaLogDetails.style.display = '';
        if (pwaLogPre) pwaLogPre.textContent = '';
        pwaLog('Recording: ' + (blob.size / 1048576).toFixed(1) + ' MB');

        var progressWrap = document.createElement('div');
        progressWrap.style.cssText = 'width:100%;height:8px;background:#2D2D3F;border-radius:4px;margin-top:8px;overflow:hidden;';
        var progressBar = document.createElement('div');
        progressBar.style.cssText = 'width:0%;height:100%;background:linear-gradient(135deg,#6B46C1,#8B5CF6);border-radius:4px;transition:width 0.2s;';
        progressWrap.appendChild(progressBar);
        statusEl.parentNode.insertBefore(progressWrap, statusEl.nextSibling);

        // Step 1: get presigned URL
        var formMeta = new FormData();
        formMeta.append('action', 'get_video_upload_url');
        formMeta.append('upload_type', 'athlete_video');
        formMeta.append('csrf_token', csrfToken);
        formMeta.append('title', document.getElementById('mVideoTitle').value);
        formMeta.append('video_category', 'drill');
        formMeta.append('file_name', 'drill_video.webm');
        formMeta.append('file_size', blob.size);
        formMeta.append('file_type', blob.type || 'video/webm');

        // Shared state across upload steps
        var uploadNonce = null;
        var proxyUploadUrl = null;
        var proxyToken = null;
        var contentType = null;

        statusEl.textContent = 'Requesting upload URL...';

        fetch('process_video.php', { method: 'POST', body: formMeta })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Failed to get upload URL');
                var presignedUrl = data.presigned_url;
                contentType = data.content_type || blob.type || 'video/webm';
                uploadNonce = data.upload_nonce;
                proxyUploadUrl = data.proxy_upload_url || null;
                proxyToken = data.proxy_token || null;
                pwaLog('Presigned URL obtained.' + (data.object_key ? ' Object key: ' + data.object_key : ''));

                statusEl.textContent = 'Uploading to cloud storage...';

                // Step 2: upload direct to RustFS (preferred) or via proxy
                var url = presignedUrl ? presignedUrl : ((proxyUploadUrl && proxyToken) ? proxyUploadUrl : null);
                var useProxy = !presignedUrl && !!(proxyUploadUrl && proxyToken);
                if (!url) throw new Error('No upload URL available');
                pwaLog('Uploading ' + (useProxy ? 'via proxy' : 'direct to cloud') + '…');
                var uploadStart = Date.now();
                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('PUT', url, true);
                    xhr.setRequestHeader('Content-Type', contentType);
                    if (useProxy) xhr.setRequestHeader('X-Upload-Token', proxyToken);
                    var uploadStarted = false;
                    var lastLogTime = 0;
                    var connTimer = setTimeout(function() {
                        if (!uploadStarted) { xhr.abort(); reject(new Error((useProxy ? 'Proxy' : 'Cloud storage') + ' connection timed out')); }
                    }, 30000);
                    xhr.upload.onprogress = function(ev) {
                        if (!uploadStarted && ev.loaded > 0) { uploadStarted = true; clearTimeout(connTimer); }
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            progressBar.style.width = pct + '%';
                            statusEl.textContent = pct < 100 ? (useProxy ? 'Uploading via proxy... ' : 'Uploading... ') + pct + '%' : 'Finalizing...';
                            var now = Date.now();
                            if (ev.loaded > 0 && (now - lastLogTime) >= PROGRESS_LOG_INTERVAL * 1000) {
                                lastLogTime = now;
                                pwaLog('Progress: ' + pct + '% — ' + (ev.loaded / 1048576).toFixed(1) + ' / ' + (ev.total / 1048576).toFixed(1) + ' MB');
                            }
                        }
                    };
                    xhr.onload = function() {
                        clearTimeout(connTimer);
                        if (xhr.status >= 200 && xhr.status < 300) { pwaLog('Upload completed in ' + pwaElapsed(uploadStart)); resolve(); }
                        else reject(new Error((useProxy ? 'Proxy' : 'Cloud') + ' upload failed (HTTP ' + xhr.status + ')'));
                    };
                    xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error during upload')); };
                    xhr.send(blob);
                });
            })
            .catch(function(uploadErr) {
                // Direct RustFS upload failed — fall back to same-origin proxy
                if (!proxyUploadUrl || !proxyToken) throw uploadErr;
                pwaLogWarn('Direct upload failed: ' + uploadErr.message + ' — trying server proxy');
                console.warn('[Upload] Direct upload failed:', uploadErr.message, '— trying server proxy');
                statusEl.textContent = 'Retrying via server...';
                progressBar.style.width = '0%';

                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('PUT', proxyUploadUrl, true);
                    xhr.setRequestHeader('Content-Type', contentType);
                    xhr.setRequestHeader('X-Upload-Token', proxyToken);
                    var uploadStarted = false;
                    var lastLogTime = 0;
                    var connTimer = setTimeout(function() {
                        if (!uploadStarted) { xhr.abort(); reject(new Error('Proxy connection timed out')); }
                    }, 30000);
                    xhr.upload.onprogress = function(ev) {
                        if (!uploadStarted && ev.loaded > 0) { uploadStarted = true; clearTimeout(connTimer); }
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            progressBar.style.width = pct + '%';
                            statusEl.textContent = pct < 100 ? 'Uploading via server... ' + pct + '%' : 'Finalizing...';
                            var now = Date.now();
                            if (ev.loaded > 0 && (now - lastLogTime) >= PROGRESS_LOG_INTERVAL * 1000) {
                                lastLogTime = now;
                                pwaLog('Proxy progress: ' + pct + '% — ' + (ev.loaded / 1048576).toFixed(1) + ' / ' + (ev.total / 1048576).toFixed(1) + ' MB');
                            }
                        }
                    };
                    xhr.onload = function() {
                        clearTimeout(connTimer);
                        if (xhr.status >= 200 && xhr.status < 300) { pwaLog('Proxy upload completed.'); resolve(); }
                        else reject(new Error('Proxy upload failed (HTTP ' + xhr.status + ')'));
                    };
                    xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error during proxy upload')); };
                    xhr.send(blob);
                });
            })
            .then(function() {
                // Step 3: confirm upload
                pwaLog('Confirming upload with server…');
                statusEl.textContent = 'Confirming upload...';
                var confirmData = new FormData();
                confirmData.append('action', 'confirm_video_upload');
                confirmData.append('csrf_token', csrfToken);
                confirmData.append('upload_nonce', uploadNonce);
                return fetch('process_video.php', { method: 'POST', body: confirmData, keepalive: true })
                    .then(function(r) { return r.json(); });
            })
            .then(function(result) {
                if (result.success) {
                    pwaLog('Upload confirmed! Triggering background transcode…');
                    statusEl.textContent = 'Upload complete!';
                    // Trigger transcode as a separate explicit action
                    var tp = new FormData();
                    tp.append('action', 'trigger_transcode');
                    tp.append('csrf_token', csrfToken);
                    tp.append('object_key', result.object_key || '');
                    if (result.video_id) tp.append('video_id', result.video_id);
                    if (result.source_id) tp.append('source_id', result.source_id);
                    fetch('process_video.php', { method: 'POST', body: tp, keepalive: true })
                        .then(function(r) { return r.json(); })
                        .then(function(t) { pwaLog('Transcode triggered (job: ' + (t.hls_job_id || 'N/A') + ')'); })
                        .catch(function(e) { pwaLogWarn('Transcode trigger: ' + e.message); });
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Upload Video';
                    progressWrap.remove();
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    throw new Error(result.error || 'Confirmation failed');
                }
            })
            .catch(function(err) {
                // Proxy upload failed, trying direct S3
                pwaLogWarn('Upload attempt failed: ' + err.message + ' — trying direct S3');
                console.warn('[Upload] Proxy upload failed:', err.message, '— trying direct S3');
                statusEl.textContent = 'Retrying via direct cloud upload...';
                progressBar.style.width = '0%';

                var retryMeta = new FormData();
                retryMeta.append('action', 'get_video_upload_url');
                retryMeta.append('upload_type', 'drill_video');
                retryMeta.append('csrf_token', csrfToken);
                retryMeta.append('file_name', 'drill_video.webm');
                retryMeta.append('file_size', blob.size);
                retryMeta.append('file_type', blob.type || 'video/webm');

                return fetch('process_video.php', { method: 'POST', body: retryMeta })
                    .then(function(r) { return r.json(); })
                    .then(function(data2) {
                        if (!data2.presigned_url) throw new Error('No presigned URL available');
                        uploadNonce = data2.upload_nonce || uploadNonce;
                        pwaLog('Retry presigned URL obtained. Uploading…');
                        var retryStart = Date.now();
                        return new Promise(function(resolve, reject) {
                            var xhr = new XMLHttpRequest();
                            xhr.open('PUT', data2.presigned_url, true);
                            xhr.setRequestHeader('Content-Type', contentType);
                            xhr.upload.onprogress = function(ev) {
                                if (ev.lengthComputable) {
                                    var pct = Math.round((ev.loaded / ev.total) * 100);
                                    progressBar.style.width = pct + '%';
                                    statusEl.textContent = pct < 100 ? 'Uploading to cloud... ' + pct + '%' : 'Finalizing...';
                                }
                            };
                            xhr.onload = function() {
                                if (xhr.status >= 200 && xhr.status < 300) { pwaLog('Retry upload completed in ' + pwaElapsed(retryStart)); resolve(); }
                                else reject(new Error('Cloud upload failed (HTTP ' + xhr.status + ')'));
                            };
                            xhr.onerror = function() { reject(new Error('Network error during cloud upload')); };
                            xhr.send(blob);
                        });
                    })
                    .then(function() {
                        pwaLog('Confirming retry upload…');
                        statusEl.textContent = 'Confirming upload...';
                        var confirmData = new FormData();
                        confirmData.append('action', 'confirm_video_upload');
                        confirmData.append('csrf_token', csrfToken);
                        confirmData.append('upload_nonce', uploadNonce);
                        return fetch('process_video.php', { method: 'POST', body: confirmData, keepalive: true })
                            .then(function(r) { return r.json(); });
                    })
                    .then(function(result) {
                        if (result.success) {
                            pwaLog('Upload confirmed! Triggering transcode…');
                            statusEl.textContent = 'Upload complete!';
                            // Trigger transcode as a separate explicit action
                            var tp = new FormData();
                            tp.append('action', 'trigger_transcode');
                            tp.append('csrf_token', csrfToken);
                            tp.append('object_key', result.object_key || '');
                            if (result.video_id) tp.append('video_id', result.video_id);
                            if (result.source_id) tp.append('source_id', result.source_id);
                            fetch('process_video.php', { method: 'POST', body: tp, keepalive: true }).catch(function() {});
                            progressWrap.remove();
                            setTimeout(function() { location.reload(); }, 500);
                        } else {
                            throw new Error(result.error || 'Confirmation failed');
                        }
                    });
            })
            .catch(function(err) {
                pwaLogError(err.message);
                statusEl.textContent = 'Upload failed: ' + err.message;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Upload Video';
                progressWrap.remove();
            });
    });
})();
</script>
