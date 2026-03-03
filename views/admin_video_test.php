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
<script>
(function() {
    var csrfToken = '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    var fileInput = document.getElementById('vt-file');
    var uploadBtn = document.getElementById('vt-upload-btn');
    var progressArea = document.getElementById('vt-progress-area');
    var progressBar  = document.getElementById('vt-progress-bar');
    var progressText = document.getElementById('vt-progress-text');
    var logEl = document.getElementById('vt-log');

    var ALLOWED_EXT = ['mp4','mkv','mov','avi','webm'];
    var MAX_SIZE = 10 * 1024 * 1024 * 1024; // 10 GB
    var MULTIPART_THRESHOLD = 64 * 1024 * 1024; // 64 MB – files above this use multipart
    var PART_SIZE = 64 * 1024 * 1024;            // 64 MB per part

    function log(msg) {
        logEl.style.display = 'block';
        logEl.textContent += '[' + new Date().toLocaleTimeString() + '] ' + msg + '\n';
        logEl.scrollTop = logEl.scrollHeight;
    }

    function postAction(params) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        for (var k in params) {
            if (params.hasOwnProperty(k)) fd.append(k, params[k]);
        }
        return fetch('process_video_test.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Request failed');
                return data;
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
        progressText.textContent = 'Requesting presigned URL…';

        postAction({
            file_name: file.name,
            file_size: file.size,
            file_type: file.type || 'application/octet-stream'
        })
        .then(function(data) {
            log('Presigned URL obtained. Object key: ' + data.object_key);
            log('Uploading directly to RustFS…');
            return putToRustFS(data.presigned_url, file, data.content_type, data.object_key);
        })
        .then(function(objectKey) {
            progressBar.style.width = '100%';
            progressText.textContent = 'Upload complete!';
            log('SUCCESS – file stored at: ' + objectKey);
            uploadBtn.disabled = false;
        })
        .catch(function(err) {
            progressText.textContent = 'Upload failed.';
            log('FAILED: ' + err.message);
            uploadBtn.disabled = false;
        });
    }

    function putToRustFS(presignedUrl, file, contentType, objectKey) {
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('PUT', presignedUrl, true);
            xhr.setRequestHeader('Content-Type', contentType);

            xhr.upload.addEventListener('progress', function(ev) {
                if (ev.lengthComputable) {
                    var pct = Math.round((ev.loaded / ev.total) * 100);
                    progressBar.style.width = pct + '%';
                    progressText.textContent = 'Uploading… ' + pct + '% (' + (ev.loaded / 1048576).toFixed(1) + ' / ' + (ev.total / 1048576).toFixed(1) + ' MB)';
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(objectKey);
                } else {
                    reject(new Error('RustFS responded with HTTP ' + xhr.status + ': ' + xhr.responseText));
                }
            });

            xhr.addEventListener('error', function() {
                reject(new Error('Network error during upload to RustFS'));
            });
            xhr.addEventListener('abort', function() {
                reject(new Error('Upload aborted'));
            });

            xhr.send(file);
        });
    }

    // ── Multipart upload (large files > 64 MB) ──────────────────────
    function multipartUpload(file) {
        var totalParts = Math.ceil(file.size / PART_SIZE);
        var objectKey = '';
        var uploadId  = '';

        log('File exceeds ' + (MULTIPART_THRESHOLD / 1048576) + ' MB — using multipart upload (' + totalParts + ' parts of ' + (PART_SIZE / 1048576) + ' MB)');
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
            log('Multipart upload initiated. Object key: ' + objectKey);
            log('Upload ID: ' + uploadId.substring(0, 20) + '…');

            return uploadAllParts(file, objectKey, uploadId, totalParts);
        })
        .then(function(parts) {
            log('All ' + parts.length + ' parts uploaded. Completing multipart upload…');
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
            log('SUCCESS – file stored at: ' + objectKey);
            uploadBtn.disabled = false;
        })
        .catch(function(err) {
            progressText.textContent = 'Upload failed.';
            log('FAILED: ' + err.message);
            uploadBtn.disabled = false;

            if (uploadId) {
                log('Aborting multipart upload…');
                postAction({ action: 'abort', object_key: objectKey, upload_id: uploadId })
                    .then(function() { log('Multipart upload aborted (cleanup done).'); })
                    .catch(function() { log('Warning: abort request also failed.'); });
            }
        });
    }

    function uploadAllParts(file, objectKey, uploadId, totalParts) {
        var parts = [];
        var uploadedBytes = 0;

        function nextPart() {
            var partNumber = parts.length + 1;
            if (partNumber > totalParts) return Promise.resolve(parts);

            var start = (partNumber - 1) * PART_SIZE;
            var end   = Math.min(start + PART_SIZE, file.size);
            var chunk = file.slice(start, end);

            progressText.textContent = 'Requesting presigned URL for part ' + partNumber + '/' + totalParts + '…';

            return postAction({
                action:      'presign_part',
                object_key:  objectKey,
                upload_id:   uploadId,
                part_number: partNumber
            })
            .then(function(data) {
                return uploadPart(data.presigned_url, chunk, partNumber, totalParts, file.size, uploadedBytes);
            })
            .then(function(etag) {
                uploadedBytes += (end - start);
                parts.push({ PartNumber: partNumber, ETag: etag });
                log('Part ' + partNumber + '/' + totalParts + ' uploaded (' + ((end - start) / 1048576).toFixed(1) + ' MB, ETag: ' + (etag || 'none').substring(0, 12) + '…)');
                return nextPart();
            });
        }

        return nextPart();
    }

    function uploadPart(presignedUrl, chunk, partNumber, totalParts, totalSize, prevUploaded) {
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('PUT', presignedUrl, true);

            xhr.upload.addEventListener('progress', function(ev) {
                if (ev.lengthComputable) {
                    var totalUploaded = prevUploaded + ev.loaded;
                    var pct = Math.round((totalUploaded / totalSize) * 100);
                    progressBar.style.width = pct + '%';
                    progressText.textContent = 'Uploading… ' + pct + '% ('
                        + (totalUploaded / 1048576).toFixed(1) + ' / '
                        + (totalSize / 1048576).toFixed(1) + ' MB) — Part '
                        + partNumber + '/' + totalParts;
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    var etag = xhr.getResponseHeader('ETag');
                    if (etag) etag = etag.replace(/"/g, '');
                    resolve(etag);
                } else {
                    reject(new Error('Part ' + partNumber + ' failed: HTTP ' + xhr.status + ' ' + xhr.responseText));
                }
            });

            xhr.addEventListener('error', function() {
                reject(new Error('Network error uploading part ' + partNumber));
            });
            xhr.addEventListener('abort', function() {
                reject(new Error('Part ' + partNumber + ' upload aborted'));
            });

            xhr.send(chunk);
        });
    }
})();
</script>
<?php endif; ?>
