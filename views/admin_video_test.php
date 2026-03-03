<!-- Admin Video Test - Direct RustFS Upload (fully standalone) -->
<?php
// Check RustFS config directly from the database — no library imports
$vtKeys = ['rustfs_endpoint','rustfs_access_key','rustfs_secret_key','rustfs_bucket'];
$vtPh   = implode(',', array_fill(0, count($vtKeys), '?'));
$vtStmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($vtPh)");
$vtStmt->execute($vtKeys);
$vtCfg = [];
while ($r = $vtStmt->fetch(PDO::FETCH_ASSOC)) { $vtCfg[$r['setting_key']] = $r['setting_value']; }
$rustfsConfigured = !empty($vtCfg['rustfs_endpoint']) && !empty($vtCfg['rustfs_access_key'])
                 && !empty($vtCfg['rustfs_secret_key']) && !empty($vtCfg['rustfs_bucket']);
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fa-solid fa-video"></i> Video Upload Test</h2>
        <p style="margin:4px 0 0;color:var(--text-secondary,#6b7280);">
            Direct browser-to-RustFS upload test. Select a video file and upload it using a presigned URL.
        </p>
    </div>
    <div class="card-body">

        <?php if (!$rustfsConfigured): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                RustFS is not configured. Please configure RustFS settings in System&nbsp;Tools before testing uploads.
            </div>
        <?php else: ?>

        <!-- File picker -->
        <div style="margin-bottom:1rem;">
            <label for="vt-file" style="display:block;font-weight:600;margin-bottom:.35rem;">Choose video file</label>
            <input type="file" id="vt-file" accept="video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo,video/avi,.mp4,.mov,.mkv,.webm,.avi">
        </div>

        <button id="vt-upload-btn" class="btn btn-primary" disabled>
            <i class="fa-solid fa-cloud-arrow-up"></i> Upload to RustFS
        </button>

        <!-- Progress area -->
        <div id="vt-progress-area" style="display:none;margin-top:1rem;">
            <div style="background:var(--bg-secondary,#e5e7eb);border-radius:6px;overflow:hidden;height:22px;">
                <div id="vt-progress-bar"
                     style="height:100%;width:0%;background:var(--primary,#2563eb);transition:width .2s;border-radius:6px;"></div>
            </div>
            <p id="vt-progress-text" style="margin:.5rem 0 0;font-size:.9rem;color:var(--text-secondary,#6b7280);">
                Preparing…
            </p>
        </div>

        <!-- Log / result -->
        <pre id="vt-log" style="margin-top:1rem;max-height:300px;overflow:auto;background:var(--bg-secondary,#f3f4f6);padding:.75rem;border-radius:6px;font-size:.82rem;white-space:pre-wrap;display:none;"></pre>

        <?php endif; ?>
    </div>
</div>

<?php if ($rustfsConfigured): ?>
<!-- Video Viewer / Transcode Test -->
<div class="card" id="vt-viewer-card" style="margin-top:1rem;display:none;">
    <div class="card-header">
        <h2><i class="fa-solid fa-play-circle"></i> Video Viewer — Transcode Test</h2>
        <p style="margin:4px 0 0;color:var(--text-secondary,#6b7280);">
            After upload, triggers companion app HLS transcode, then plays the transcoded video.
        </p>
    </div>
    <div class="card-body">
        <!-- Transcode status -->
        <div id="vt-transcode-area">
            <div id="vt-transcode-status" style="margin-bottom:1rem;">
                <span id="vt-transcode-status-text" style="font-weight:600;color:var(--text-secondary,#6b7280);">
                    Waiting for upload…
                </span>
            </div>
            <div id="vt-transcode-progress" style="display:none;margin-bottom:1rem;">
                <div style="background:var(--bg-secondary,#e5e7eb);border-radius:6px;overflow:hidden;height:18px;">
                    <div id="vt-transcode-bar"
                         style="height:100%;width:0%;background:var(--success,#16a34a);transition:width .3s;border-radius:6px;"></div>
                </div>
                <p id="vt-transcode-text" style="margin:.5rem 0 0;font-size:.85rem;color:var(--text-secondary,#6b7280);">
                    Transcoding…
                </p>
            </div>
        </div>

        <!-- Video player -->
        <div id="vt-player-area" style="display:none;margin-top:1rem;">
            <video id="vt-video-player" controls playsinline
                   style="width:100%;max-height:500px;border-radius:8px;background:#000;">
                Your browser does not support HTML5 video.
            </video>
            <p id="vt-player-info" style="margin:.5rem 0 0;font-size:.85rem;color:var(--text-secondary,#6b7280);"></p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($rustfsConfigured): ?>
<script>
(function() {
    var csrfToken = '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    var fileInput = document.getElementById('vt-file');
    var uploadBtn = document.getElementById('vt-upload-btn');
    var progressArea = document.getElementById('vt-progress-area');
    var progressBar  = document.getElementById('vt-progress-bar');
    var progressText = document.getElementById('vt-progress-text');
    var logEl = document.getElementById('vt-log');

    // Viewer / transcode elements
    var viewerCard       = document.getElementById('vt-viewer-card');
    var transcodeStatus  = document.getElementById('vt-transcode-status-text');
    var transcodeProgress = document.getElementById('vt-transcode-progress');
    var transcodeBar     = document.getElementById('vt-transcode-bar');
    var transcodeText    = document.getElementById('vt-transcode-text');
    var playerArea       = document.getElementById('vt-player-area');
    var videoPlayer      = document.getElementById('vt-video-player');
    var playerInfo       = document.getElementById('vt-player-info');

    var ALLOWED_EXT = ['mp4','mkv','mov','avi','webm'];
    var MAX_SIZE = 10 * 1024 * 1024 * 1024; // 10 GB
    var MULTIPART_THRESHOLD = 64 * 1024 * 1024; // 64 MB – files above this use multipart
    var PART_SIZE = 64 * 1024 * 1024;            // 64 MB per part
    var STALL_TIMEOUT_SEC = 30; // seconds with no progress before warning
    var PROGRESS_LOG_INTERVAL = 10; // log progress to output window every N seconds
    var CONCURRENT_PARTS = 5; // upload this many parts in parallel
    var MAX_PART_RETRIES = 3; // retry each part up to this many times
    var STALL_ABORT_SEC = 60; // abort and retry a part after this many seconds with no progress

    function log(msg) {
        logEl.style.display = 'block';
        logEl.textContent += '[' + new Date().toLocaleTimeString() + '] ' + msg + '\n';
        logEl.scrollTop = logEl.scrollHeight;
    }

    function logError(msg) { log('ERROR: ' + msg); }
    function logWarn(msg)  { log('WARNING: ' + msg); }
    function logDebug(msg) { log('DEBUG: ' + msg); }

    function elapsed(startMs) {
        var s = ((Date.now() - startMs) / 1000).toFixed(1);
        return s + 's';
    }

    function formatSpeed(bytes, ms) {
        if (ms <= 0) return '—';
        var mbps = (bytes / 1048576) / (ms / 1000);
        return mbps.toFixed(1) + ' MB/s';
    }

    function postAction(params) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        for (var k in params) {
            if (params.hasOwnProperty(k)) fd.append(k, params[k]);
        }
        var actionLabel = params.action || 'presign';
        var startMs = Date.now();
        logDebug('POST ' + actionLabel + ' request started…');
        return fetch('process_video_test.php', { method: 'POST', body: fd })
            .then(function(r) {
                logDebug('POST ' + actionLabel + ' response: HTTP ' + r.status + ' (' + elapsed(startMs) + ')');
                if (!r.ok) {
                    return r.text().then(function(body) {
                        var errMsg = 'Server returned HTTP ' + r.status;
                        try { var j = JSON.parse(body); if (j.error) errMsg += ': ' + j.error; }
                        catch(e) { if (body) errMsg += ': ' + body.substring(0, 200); }
                        throw new Error(errMsg);
                    });
                }
                return r.json();
            })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Request failed (success=false)');
                return data;
            })
            .catch(function(err) {
                if (err.message && err.message.indexOf('Failed to fetch') !== -1) {
                    throw new Error(actionLabel + ': Network error – could not reach server (check connectivity)');
                }
                throw err;
            });
    }

    fileInput.addEventListener('change', function() {
        uploadBtn.disabled = !fileInput.files.length;
    });

    uploadBtn.addEventListener('click', function() {
        var file = fileInput.files[0];
        if (!file) return;

        var dotIdx = file.name.lastIndexOf('.');
        var ext = dotIdx > 0 ? file.name.substring(dotIdx + 1).toLowerCase() : '';
        if (!ext || ALLOWED_EXT.indexOf(ext) === -1) {
            log('ERROR: Invalid extension ".' + ext + '". Allowed: ' + ALLOWED_EXT.join(', '));
            return;
        }
        if (file.size > MAX_SIZE) {
            log('ERROR: File exceeds 10 GB limit.');
            return;
        }

        uploadBtn.disabled = true;
        progressArea.style.display = 'block';
        progressBar.style.width = '0%';
        log('Selected file: ' + file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB, ' + (file.type || 'unknown') + ')');

        if (file.size > MULTIPART_THRESHOLD) {
            multipartUpload(file);
        } else {
            singlePutUpload(file);
        }
    });

    // ── Single PUT upload (small files ≤ 64 MB) ──────────────────────
    function singlePutUpload(file) {
        var uploadStart = Date.now();
        progressText.textContent = 'Requesting presigned URL…';

        postAction({
            file_name: file.name,
            file_size: file.size,
            file_type: file.type || 'application/octet-stream'
        })
        .then(function(data) {
            log('Presigned URL obtained (' + elapsed(uploadStart) + '). Object key: ' + data.object_key);
            log('Uploading directly to RustFS…');
            return putToRustFS(data.presigned_url, file, data.content_type, data.object_key);
        })
        .then(function(objectKey) {
            progressBar.style.width = '100%';
            progressText.textContent = 'Upload complete!';
            log('SUCCESS – file stored at: ' + objectKey + ' (total time: ' + elapsed(uploadStart) + ')');
            uploadBtn.disabled = false;
            startTranscodeFlow(objectKey);
        })
        .catch(function(err) {
            progressText.textContent = 'Upload failed.';
            logError(err.message);
            log('Upload failed after ' + elapsed(uploadStart));
            uploadBtn.disabled = false;
        });
    }

    function putToRustFS(presignedUrl, file, contentType, objectKey) {
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('PUT', presignedUrl, true);
            xhr.setRequestHeader('Content-Type', contentType);

            var lastLoaded = 0;
            var lastProgressTime = Date.now();
            var stallTimer = null;
            var stallWarned = false;
            var putStart = Date.now();
            var lastLogTime = 0;

            log('Starting upload to RustFS (' + (file.size / 1048576).toFixed(1) + ' MB)…');

            function checkStall() {
                var now = Date.now();
                var secSinceProgress = Math.round((now - lastProgressTime) / 1000);
                if (secSinceProgress >= STALL_TIMEOUT_SEC) {
                    logWarn('Upload stalled – no progress for ' + secSinceProgress + 's at '
                        + (lastLoaded / 1048576).toFixed(1) + ' MB ('
                        + Math.round((lastLoaded / file.size) * 100) + '%)');
                    stallWarned = true;
                } else if (stallWarned) {
                    log('Upload resumed after stall');
                    stallWarned = false;
                }
            }
            stallTimer = setInterval(checkStall, STALL_TIMEOUT_SEC * 1000);

            xhr.upload.addEventListener('progress', function(ev) {
                if (ev.lengthComputable) {
                    if (ev.loaded > lastLoaded) {
                        lastLoaded = ev.loaded;
                        lastProgressTime = Date.now();
                    }
                    var pct = Math.round((ev.loaded / ev.total) * 100);
                    progressBar.style.width = pct + '%';
                    progressText.textContent = 'Uploading… ' + pct + '% (' + (ev.loaded / 1048576).toFixed(1) + ' / ' + (ev.total / 1048576).toFixed(1) + ' MB)';

                    // Periodic progress to log window
                    var now = Date.now();
                    if (ev.loaded > 0 && (now - lastLogTime) >= PROGRESS_LOG_INTERVAL * 1000) {
                        lastLogTime = now;
                        log('Progress: ' + pct + '% — '
                            + (ev.loaded / 1048576).toFixed(1) + ' / ' + (ev.total / 1048576).toFixed(1) + ' MB'
                            + ' (' + formatSpeed(ev.loaded, now - putStart) + ', ' + elapsed(putStart) + ' elapsed)');
                    }
                }
            });

            xhr.addEventListener('load', function() {
                clearInterval(stallTimer);
                logDebug('PUT response: HTTP ' + xhr.status + ' (' + elapsed(putStart) + ')');
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(objectKey);
                } else {
                    var detail = xhr.responseText ? xhr.responseText.substring(0, 300) : '(empty body)';
                    reject(new Error('RustFS responded with HTTP ' + xhr.status + ': ' + detail));
                }
            });

            xhr.addEventListener('error', function() {
                clearInterval(stallTimer);
                logError('Network error during PUT to RustFS after ' + elapsed(putStart)
                    + ' – possible causes: CORS blocked, connection reset, or server unreachable');
                reject(new Error('Network error during upload to RustFS'));
            });
            xhr.addEventListener('abort', function() {
                clearInterval(stallTimer);
                reject(new Error('Upload aborted after ' + elapsed(putStart)));
            });
            xhr.addEventListener('timeout', function() {
                clearInterval(stallTimer);
                logError('Upload timed out after ' + elapsed(putStart));
                reject(new Error('Upload timed out'));
            });

            xhr.send(file);
        });
    }

    // ── Multipart upload (large files > 64 MB) ──────────────────────
    function multipartUpload(file) {
        var totalParts = Math.ceil(file.size / PART_SIZE);
        var objectKey = '';
        var uploadId  = '';
        var uploadStart = Date.now();

        log('File exceeds ' + (MULTIPART_THRESHOLD / 1048576) + ' MB — using multipart upload (' + totalParts + ' parts of ' + (PART_SIZE / 1048576) + ' MB, ' + CONCURRENT_PARTS + ' concurrent)');
        progressText.textContent = 'Initiating multipart upload…';

        postAction({
            action:    'initiate',
            file_name: file.name,
            file_size: file.size,
            file_type: file.type || 'application/octet-stream'
        })
        .then(function(data) {
            objectKey = data.object_key;
            uploadId  = data.upload_id;
            log('Multipart upload initiated (' + elapsed(uploadStart) + '). Object key: ' + objectKey);
            logDebug('Upload ID: ' + uploadId.substring(0, 20) + '…');

            return uploadAllParts(file, objectKey, uploadId, totalParts);
        })
        .then(function(parts) {
            log('All ' + parts.length + ' parts uploaded (' + elapsed(uploadStart) + '). Completing multipart upload…');
            progressText.textContent = 'Completing multipart upload…';

            return postAction({
                action:     'complete',
                object_key: objectKey,
                upload_id:  uploadId,
                parts:      JSON.stringify(parts)
            });
        })
        .then(function() {
            progressBar.style.width = '100%';
            progressText.textContent = 'Upload complete!';
            log('SUCCESS – file stored at: ' + objectKey + ' (total time: ' + elapsed(uploadStart) + ')');
            uploadBtn.disabled = false;
            startTranscodeFlow(objectKey);
        })
        .catch(function(err) {
            progressText.textContent = 'Upload failed.';
            logError(err.message);
            log('Upload failed after ' + elapsed(uploadStart));
            uploadBtn.disabled = false;

            if (uploadId) {
                log('Aborting multipart upload…');
                postAction({ action: 'abort', object_key: objectKey, upload_id: uploadId })
                    .then(function() { log('Multipart upload aborted (cleanup done).'); })
                    .catch(function(e) { logWarn('Abort request also failed: ' + e.message); });
            }
        });
    }

    function uploadAllParts(file, objectKey, uploadId, totalParts) {
        var results = new Array(totalParts); // indexed by partNumber-1
        var partBytes = new Array(totalParts); // track bytes uploaded per part
        for (var i = 0; i < totalParts; i++) partBytes[i] = 0;
        var nextIndex = 0; // next part index to dispatch (0-based)
        var activeCount = 0;
        var completedCount = 0;

        return new Promise(function(resolve, reject) {
            var failed = false;

            function dispatch() {
                while (!failed && activeCount < CONCURRENT_PARTS && nextIndex < totalParts) {
                    (function(idx) {
                        var partNumber = idx + 1;
                        activeCount++;
                        uploadOnePart(file, objectKey, uploadId, partNumber, totalParts, partBytes)
                            .then(function(result) {
                                if (failed) return;
                                partBytes[idx] = result.size; // mark final size
                                results[idx] = { PartNumber: partNumber, ETag: result.etag };
                                activeCount--;
                                completedCount++;
                                if (completedCount === totalParts) {
                                    resolve(results);
                                } else {
                                    dispatch();
                                }
                            })
                            .catch(function(err) {
                                if (failed) return;
                                failed = true;
                                reject(err);
                            });
                    })(nextIndex);
                    nextIndex++;
                }
            }

            dispatch();
        });
    }

    function uploadOnePart(file, objectKey, uploadId, partNumber, totalParts, partBytes) {
        var start = (partNumber - 1) * PART_SIZE;
        var end   = Math.min(start + PART_SIZE, file.size);
        var chunkSize = end - start;
        var attempt = 0;

        function tryUpload() {
            attempt++;
            var chunk = file.slice(start, end);
            var partStart = Date.now();

            if (attempt > 1) {
                logWarn('Retrying part ' + partNumber + '/' + totalParts + ' (attempt ' + attempt + '/' + MAX_PART_RETRIES + ')');
            }

            return postAction({
                action:      'presign_part',
                object_key:  objectKey,
                upload_id:   uploadId,
                part_number: partNumber
            })
            .then(function(data) {
                logDebug('Presign for part ' + partNumber + ' took ' + elapsed(partStart));
                return uploadPart(data.presigned_url, chunk, partNumber, totalParts, file.size, partBytes);
            })
            .then(function(etag) {
                var etagDisplay = etag ? etag.substring(0, 12) + '…' : 'none';
                log('Part ' + partNumber + '/' + totalParts + ' uploaded (' + (chunkSize / 1048576).toFixed(1) + ' MB, ETag: ' + etagDisplay + ', ' + elapsed(partStart) + ')');
                return { etag: etag, size: chunkSize };
            })
            .catch(function(err) {
                if (attempt < MAX_PART_RETRIES) {
                    var delaySec = Math.pow(2, attempt - 1); // 1s, 2s, 4s backoff
                    logWarn('Part ' + partNumber + ' failed (attempt ' + attempt + '): ' + err.message + ' — retrying in ' + delaySec + 's');
                    return new Promise(function(res) { setTimeout(res, delaySec * 1000); }).then(tryUpload);
                }
                throw err;
            });
        }

        return tryUpload();
    }

    function uploadPart(presignedUrl, chunk, partNumber, totalParts, totalSize, partBytes) {
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('PUT', presignedUrl, true);

            var lastLoaded = 0;
            var lastProgressTime = Date.now();
            var stallTimer = null;
            var stallWarned = false;
            var partPutStart = Date.now();
            var lastLogTime = 0;
            var chunkSize = chunk.size;
            var partIndex = partNumber - 1;

            function sumPartBytes() {
                var total = 0;
                for (var i = 0; i < partBytes.length; i++) total += partBytes[i];
                return total;
            }

            log('Uploading part ' + partNumber + '/' + totalParts + ' (' + (chunkSize / 1048576).toFixed(1) + ' MB)…');

            function checkStall() {
                var now = Date.now();
                var secSinceProgress = Math.round((now - lastProgressTime) / 1000);
                if (secSinceProgress >= STALL_ABORT_SEC) {
                    logWarn('Part ' + partNumber + ' stalled for ' + secSinceProgress + 's — aborting to retry');
                    xhr.abort();
                } else if (secSinceProgress >= STALL_TIMEOUT_SEC) {
                    var totalUploaded = sumPartBytes();
                    logWarn('Part ' + partNumber + ' stalled – no progress for ' + secSinceProgress + 's at '
                        + (totalUploaded / 1048576).toFixed(1) + ' MB total ('
                        + Math.round((totalUploaded / totalSize) * 100) + '%)');
                    stallWarned = true;
                } else if (stallWarned) {
                    log('Part ' + partNumber + ' upload resumed after stall');
                    stallWarned = false;
                }
            }
            stallTimer = setInterval(checkStall, STALL_TIMEOUT_SEC * 1000);

            xhr.upload.addEventListener('progress', function(ev) {
                if (ev.lengthComputable) {
                    if (ev.loaded > lastLoaded) {
                        lastLoaded = ev.loaded;
                        lastProgressTime = Date.now();
                    }
                    partBytes[partIndex] = ev.loaded;
                    var totalUploaded = sumPartBytes();
                    var pct = Math.round((totalUploaded / totalSize) * 100);
                    progressBar.style.width = pct + '%';
                    progressText.textContent = 'Uploading… ' + pct + '% ('
                        + (totalUploaded / 1048576).toFixed(1) + ' / '
                        + (totalSize / 1048576).toFixed(1) + ' MB)';

                    // Periodic progress to log window
                    var now = Date.now();
                    if (ev.loaded > 0 && (now - lastLogTime) >= PROGRESS_LOG_INTERVAL * 1000) {
                        lastLogTime = now;
                        var partPct = Math.round((ev.loaded / chunkSize) * 100);
                        log('Part ' + partNumber + ': ' + partPct + '% — '
                            + (ev.loaded / 1048576).toFixed(1) + ' / ' + (chunkSize / 1048576).toFixed(1) + ' MB'
                            + ' (overall ' + pct + '%, ' + formatSpeed(ev.loaded, now - partPutStart) + ', ' + elapsed(partPutStart) + ' elapsed)');
                    }
                }
            });

            xhr.addEventListener('load', function() {
                clearInterval(stallTimer);
                logDebug('Part ' + partNumber + ' PUT response: HTTP ' + xhr.status + ' (' + elapsed(partPutStart) + ')');
                if (xhr.status >= 200 && xhr.status < 300) {
                    var etag = xhr.getResponseHeader('ETag');
                    if (etag) etag = etag.replace(/"/g, '');
                    if (!etag) {
                        logError('No ETag returned for part ' + partNumber + '. CORS may not expose the ETag header.');
                        reject(new Error('Part ' + partNumber + ': server did not return an ETag header (possible CORS configuration issue). Please retry the upload.'));
                        return;
                    }
                    resolve(etag);
                } else {
                    var detail = xhr.responseText ? xhr.responseText.substring(0, 300) : '(empty body)';
                    reject(new Error('Part ' + partNumber + ' failed: HTTP ' + xhr.status + ' ' + detail));
                }
            });

            xhr.addEventListener('error', function() {
                clearInterval(stallTimer);
                logError('Network error uploading part ' + partNumber + ' after ' + elapsed(partPutStart)
                    + ' – possible causes: CORS blocked, connection reset, request too large, or server unreachable');
                reject(new Error('Network error uploading part ' + partNumber));
            });
            xhr.addEventListener('abort', function() {
                clearInterval(stallTimer);
                reject(new Error('Part ' + partNumber + ' upload aborted after ' + elapsed(partPutStart)));
            });
            xhr.addEventListener('timeout', function() {
                clearInterval(stallTimer);
                logError('Part ' + partNumber + ' upload timed out after ' + elapsed(partPutStart));
                reject(new Error('Part ' + partNumber + ' upload timed out'));
            });

            xhr.send(chunk);
        });
    }

    // ── Transcode flow ──────────────────────────────────────────────
    var TRANSCODE_POLL_SEC = 5;

    function startTranscodeFlow(objectKey) {
        viewerCard.style.display = '';
        transcodeStatus.textContent = 'Triggering companion transcode…';
        transcodeStatus.style.color = 'var(--primary,#2563eb)';
        log('Triggering HLS transcode for: ' + objectKey);

        postAction({
            action:     'transcode',
            object_key: objectKey
        })
        .then(function(data) {
            log('Transcode triggered – job ID: ' + (data.job_id || '(none)'));
            log('Output prefix: ' + (data.output_prefix || ''));
            transcodeStatus.textContent = 'Transcode in progress…';
            transcodeProgress.style.display = '';
            transcodeBar.style.width = '10%';
            transcodeText.textContent = 'Transcoding… (polling every ' + TRANSCODE_POLL_SEC + 's)';
            pollTranscodeStatus(objectKey, data.job_id, data.output_prefix);
        })
        .catch(function(err) {
            transcodeStatus.textContent = 'Transcode trigger failed';
            transcodeStatus.style.color = 'var(--danger,#dc2626)';
            logError('Transcode trigger failed: ' + err.message);
        });
    }

    function pollTranscodeStatus(objectKey, jobId, outputPrefix) {
        var pollStart = Date.now();
        var pollCount = 0;

        function poll() {
            pollCount++;
            postAction({
                action: 'transcode_status',
                object_key: objectKey,
                job_id: jobId || ''
            })
            .then(function(data) {
                var status = data.status || 'unknown';
                var elapsedSec = Math.round((Date.now() - pollStart) / 1000);

                if (status === 'completed' || status === 'ready') {
                    transcodeBar.style.width = '100%';
                    transcodeText.textContent = 'Transcode complete! (' + elapsedSec + 's)';
                    transcodeStatus.textContent = 'Transcode complete ✓';
                    transcodeStatus.style.color = 'var(--success,#16a34a)';
                    log('Transcode completed in ' + elapsedSec + 's');

                    // Delete original file
                    deleteOriginal(objectKey);

                    // Load the video player
                    var hlsUrl = data.hls_url || ('api/media.php?key=' + encodeURIComponent((outputPrefix || data.output_prefix) + '/master.m3u8'));
                    loadVideoPlayer(hlsUrl, outputPrefix || data.output_prefix);
                } else if (status === 'failed') {
                    transcodeBar.style.width = '100%';
                    transcodeBar.style.background = 'var(--danger,#dc2626)';
                    transcodeText.textContent = 'Transcode failed after ' + elapsedSec + 's';
                    transcodeStatus.textContent = 'Transcode failed ✗';
                    transcodeStatus.style.color = 'var(--danger,#dc2626)';
                    logError('Transcode failed: ' + (data.error || 'unknown error'));
                } else {
                    // Still processing
                    var pct = Math.min(10 + pollCount * 5, 90);
                    transcodeBar.style.width = pct + '%';
                    transcodeText.textContent = 'Transcoding… ' + status + ' (' + elapsedSec + 's elapsed)';
                    if (pollCount % 6 === 0) {
                        log('Transcode still in progress — ' + status + ' (' + elapsedSec + 's)');
                    }
                    setTimeout(poll, TRANSCODE_POLL_SEC * 1000);
                }
            })
            .catch(function(err) {
                logWarn('Transcode status poll error: ' + err.message + ' — retrying…');
                setTimeout(poll, TRANSCODE_POLL_SEC * 1000);
            });
        }

        setTimeout(poll, TRANSCODE_POLL_SEC * 1000);
    }

    function deleteOriginal(objectKey) {
        log('Deleting original file: ' + objectKey);
        postAction({
            action:     'delete_original',
            object_key: objectKey
        })
        .then(function() {
            log('Original file deleted successfully: ' + objectKey);
        })
        .catch(function(err) {
            logWarn('Failed to delete original file: ' + err.message);
        });
    }

    function loadVideoPlayer(hlsUrl, outputPrefix) {
        playerArea.style.display = '';
        log('Loading video player: ' + hlsUrl);
        playerInfo.textContent = 'HLS manifest: ' + hlsUrl;

        // Try native HLS (Safari) or fall back to direct URL
        videoPlayer.src = hlsUrl;
        videoPlayer.load();

        videoPlayer.addEventListener('error', function onErr() {
            videoPlayer.removeEventListener('error', onErr);
            logWarn('Video player error — the transcoded file may not be available yet or format unsupported.');
            playerInfo.textContent = 'Playback error. HLS URL: ' + hlsUrl;
        });

        videoPlayer.addEventListener('loadedmetadata', function onMeta() {
            videoPlayer.removeEventListener('loadedmetadata', onMeta);
            log('Video loaded — duration: ' + videoPlayer.duration.toFixed(1) + 's, resolution: ' + videoPlayer.videoWidth + '×' + videoPlayer.videoHeight);
            playerInfo.textContent = 'Duration: ' + videoPlayer.duration.toFixed(1) + 's | Resolution: ' + videoPlayer.videoWidth + '×' + videoPlayer.videoHeight + ' | HLS: ' + hlsUrl;
        });
    }
})();
</script>
<?php endif; ?>
