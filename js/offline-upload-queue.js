/**
 * Arctic Wolves — Offline Video Upload Queue Manager
 *
 * Uses IndexedDB to persist video blobs and metadata on-device when offline.
 * On reconnect, automatically prompts the user and sequentially uploads each
 * video via the existing multipart upload pipeline, then auto-assigns it to
 * the correct area of the application (drill review, coach review, athlete
 * review, gameplan film room) based on the stored metadata.
 *
 * Key design decisions:
 *  - Videos are uploaded one at a time (sequential) to avoid memory pressure
 *    on devices that may have 50-100 GB of queued content.
 *  - After each video is fully uploaded and confirmed, its blob is deleted
 *    from IndexedDB before moving to the next video.
 *  - If a failure occurs, the queue resumes from the video that failed; the
 *    user does NOT have to restart the entire batch.
 *  - All metadata (session, drill, athlete, coach, game, etc.) is captured at
 *    record time and travels with the blob so uploads auto-assign correctly.
 */

/* global showToast, persistToast */
/* eslint-disable no-var */

(function(window) {
    'use strict';

    var DB_NAME = 'aw_offline_videos';
    var DB_VERSION = 1;
    var STORE_NAME = 'videos';

    // Multipart constants (match the views)
    var MULTIPART_THRESHOLD = 64 * 1024 * 1024;
    var PART_SIZE           = 64 * 1024 * 1024;
    var CONCURRENT_PARTS    = 3;
    var MAX_PART_RETRIES    = 5;
    var STALL_TIMEOUT_SEC   = 30;
    var STALL_ABORT_SEC     = 90;

    // ─── IndexedDB helpers ──────────────────────────────────────────

    function openDB() {
        return new Promise(function(resolve, reject) {
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function(e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    var store = db.createObjectStore(STORE_NAME, { keyPath: 'id' });
                    store.createIndex('status', 'status', { unique: false });
                    store.createIndex('upload_type', 'upload_type', { unique: false });
                    store.createIndex('recorded_at', 'recorded_at', { unique: false });
                }
            };
            req.onsuccess = function(e) { resolve(e.target.result); };
            req.onerror = function(e) { reject(e.target.error); };
        });
    }

    function dbTransaction(mode, fn) {
        return openDB().then(function(db) {
            return new Promise(function(resolve, reject) {
                var tx = db.transaction(STORE_NAME, mode);
                var store = tx.objectStore(STORE_NAME);
                fn(store, resolve, reject);
                tx.onerror = function(e) { reject(e.target.error); };
            });
        });
    }

    // ─── Public API ─────────────────────────────────────────────────

    /**
     * Save a video blob + metadata to IndexedDB for later upload.
     * @param {Blob}   blob     The video file/blob
     * @param {Object} metadata All the context fields (upload_type, session_id, etc.)
     * @returns {Promise<string>} The queue item ID
     */
    function enqueueVideo(blob, metadata) {
        var id = _generateId();
        var record = {
            id: id,
            blob: blob,
            file_size: blob.size,
            content_type: blob.type || 'video/webm',
            original_filename: metadata.original_filename || 'offline_recording.webm',
            status: 'pending',       // pending | uploading | uploaded | failed
            error_message: null,
            upload_progress: 0,
            recorded_at: new Date().toISOString(),
            queued_at: new Date().toISOString(),

            // Upload routing
            upload_type: metadata.upload_type || 'athlete_video',
            user_id: metadata.user_id,
            user_role: metadata.user_role,

            // Common
            title: metadata.title || '',
            description: metadata.description || '',
            video_category: metadata.video_category || 'drill',

            // People
            athlete_id: metadata.athlete_id || null,
            coach_id: metadata.coach_id || null,

            // Drill/session
            session_id: metadata.session_id || null,
            drill_id: metadata.drill_id || null,
            rep_number: metadata.rep_number || 1,

            // Coach video
            session_date: metadata.session_date || null,
            drill_type: metadata.drill_type || null,
            drill_name: metadata.drill_name || null,
            rating: metadata.rating || null,

            // Athlete game
            game_date: metadata.game_date || null,
            team_played_on: metadata.team_played_on || null,
            opponent_team: metadata.opponent_team || null,

            // Video source (gameplan)
            camera_angle: metadata.camera_angle || null,
            game_id: metadata.game_id || null,
            team_id: metadata.team_id || null
        };

        return dbTransaction('readwrite', function(store, resolve, reject) {
            var req = store.put(record);
            req.onsuccess = function() { resolve(id); };
            req.onerror = function(e) { reject(e.target.error); };
        });
    }

    /**
     * Get the count of videos pending upload.
     * @returns {Promise<number>}
     */
    function getPendingCount() {
        return dbTransaction('readonly', function(store, resolve) {
            var count = 0;
            var idx = store.index('status');
            var range = IDBKeyRange.only('pending');
            var req = idx.openCursor(range);
            req.onsuccess = function(e) {
                var cursor = e.target.result;
                if (cursor) { count++; cursor.continue(); }
                else resolve(count);
            };
        }).then(function(pending) {
            // Also count failed items (they can be retried)
            return dbTransaction('readonly', function(store, resolve) {
                var count = 0;
                var idx = store.index('status');
                var range = IDBKeyRange.only('failed');
                var req = idx.openCursor(range);
                req.onsuccess = function(e) {
                    var cursor = e.target.result;
                    if (cursor) { count++; cursor.continue(); }
                    else resolve(pending + count);
                };
            });
        });
    }

    /**
     * List all queued items (without blob data for memory efficiency).
     * @returns {Promise<Array>}
     */
    function listQueue() {
        return dbTransaction('readonly', function(store, resolve) {
            var items = [];
            var req = store.openCursor();
            req.onsuccess = function(e) {
                var cursor = e.target.result;
                if (cursor) {
                    var item = Object.assign({}, cursor.value);
                    delete item.blob; // Don't load blob into memory for listing
                    items.push(item);
                    cursor.continue();
                } else {
                    resolve(items);
                }
            };
        });
    }

    /**
     * Get a single queue item by ID (includes blob).
     * @param {string} id
     * @returns {Promise<Object|null>}
     */
    function getItem(id) {
        return dbTransaction('readonly', function(store, resolve) {
            var req = store.get(id);
            req.onsuccess = function(e) { resolve(e.target.result || null); };
        });
    }

    /**
     * Remove a video from the queue (delete blob from device).
     * @param {string} id
     * @returns {Promise<void>}
     */
    function removeItem(id) {
        return dbTransaction('readwrite', function(store, resolve) {
            var req = store.delete(id);
            req.onsuccess = function() { resolve(); };
        });
    }

    /**
     * Update the status/progress of a queue item (without touching the blob).
     */
    function updateItemStatus(id, fields) {
        return dbTransaction('readwrite', function(store, resolve, reject) {
            var req = store.get(id);
            req.onsuccess = function(e) {
                var record = e.target.result;
                if (!record) { reject(new Error('Queue item not found: ' + id)); return; }
                for (var k in fields) {
                    if (fields.hasOwnProperty(k)) record[k] = fields[k];
                }
                var putReq = store.put(record);
                putReq.onsuccess = function() { resolve(); };
                putReq.onerror = function(ev) { reject(ev.target.error); };
            };
        });
    }

    /**
     * Get all items that need uploading (pending or failed), ordered by recorded_at.
     * Returns items WITHOUT blob data — call getItem(id) when ready to upload.
     */
    function getUploadableItems() {
        return listQueue().then(function(items) {
            return items
                .filter(function(i) { return i.status === 'pending' || i.status === 'failed'; })
                .sort(function(a, b) {
                    return new Date(a.recorded_at) - new Date(b.recorded_at);
                });
        });
    }

    // ─── Upload engine ──────────────────────────────────────────────

    var _uploading = false;
    var _cancelled = false;
    var _currentXhr = null;
    var _onProgress = null;
    var _onStatusChange = null;

    /**
     * Process the entire offline queue sequentially.
     * Uploads each video one-by-one using multipart for large files.
     * After each success, deletes the blob from the device.
     *
     * @param {Object} opts
     * @param {string} opts.csrfToken
     * @param {Function} [opts.onProgress]  (item, percent, message)
     * @param {Function} [opts.onItemComplete] (item, result)
     * @param {Function} [opts.onItemError]   (item, error)
     * @param {Function} [opts.onQueueComplete] (stats)
     * @param {Function} [opts.onStatusChange] (status) — 'idle'|'uploading'|'paused'|'complete'
     */
    function processQueue(opts) {
        if (_uploading) return Promise.resolve();
        _uploading = true;
        _cancelled = false;
        _onProgress = opts.onProgress || null;
        _onStatusChange = opts.onStatusChange || null;

        if (_onStatusChange) _onStatusChange('uploading');

        var stats = { uploaded: 0, failed: 0, total: 0 };

        return getUploadableItems()
            .then(function(items) {
                stats.total = items.length;
                return _processNext(items, 0, opts, stats);
            })
            .then(function() {
                _uploading = false;
                if (_onStatusChange) _onStatusChange('complete');
                if (opts.onQueueComplete) opts.onQueueComplete(stats);
            })
            .catch(function(err) {
                _uploading = false;
                if (_onStatusChange) _onStatusChange('idle');
                console.error('[OfflineQueue] processQueue error:', err);
            });
    }

    function _processNext(items, index, opts, stats) {
        if (_cancelled || index >= items.length) return Promise.resolve();

        var meta = items[index];
        return getItem(meta.id).then(function(fullItem) {
            if (!fullItem || !fullItem.blob) {
                // Corrupt entry — skip
                return removeItem(meta.id).then(function() {
                    return _processNext(items, index + 1, opts, stats);
                });
            }

            return _uploadOneVideo(fullItem, opts)
                .then(function(result) {
                    stats.uploaded++;
                    // Delete blob from device after successful upload
                    return removeItem(fullItem.id).then(function() {
                        if (opts.onItemComplete) opts.onItemComplete(meta, result);
                        return _processNext(items, index + 1, opts, stats);
                    });
                })
                .catch(function(err) {
                    stats.failed++;
                    return updateItemStatus(fullItem.id, {
                        status: 'failed',
                        error_message: err.message
                    }).then(function() {
                        if (opts.onItemError) opts.onItemError(meta, err);
                        // Stop at this video — user can retry from here
                        _uploading = false;
                        if (_onStatusChange) _onStatusChange('paused');
                    });
                });
        });
    }

    /**
     * Upload a single video from the offline queue.
     * Uses multipart for files > MULTIPART_THRESHOLD, single PUT otherwise.
     */
    function _uploadOneVideo(item, opts) {
        var csrfToken = opts.csrfToken;
        var blob = item.blob;

        return updateItemStatus(item.id, { status: 'uploading', upload_progress: 0 })
            .then(function() {
                if (blob.size > MULTIPART_THRESHOLD) {
                    return _multipartUpload(item, csrfToken, opts);
                } else {
                    return _singleUpload(item, csrfToken, opts);
                }
            });
    }

    // ── Single presigned PUT upload ─────────────────────────────────

    function _singleUpload(item, csrfToken, opts) {
        var blob = item.blob;
        var params = _buildUploadParams(item);
        params.action = 'get_video_upload_url';
        params.file_name = item.original_filename;
        params.file_size = blob.size;
        params.file_type = item.content_type;

        var uploadNonce = null;

        return _postAction(params, csrfToken)
            .then(function(data) {
                uploadNonce = data.upload_nonce;
                var url = data.presigned_url;
                if (!url) throw new Error('No presigned URL returned');

                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    _currentXhr = xhr;
                    xhr.open('PUT', url, true);
                    xhr.setRequestHeader('Content-Type', data.content_type || item.content_type);

                    xhr.upload.onprogress = function(ev) {
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            _reportProgress(item, pct, 'Uploading ' + item.title);
                        }
                    };
                    xhr.onload = function() {
                        _currentXhr = null;
                        if (xhr.status >= 200 && xhr.status < 300) resolve();
                        else reject(new Error('Upload failed: HTTP ' + xhr.status));
                    };
                    xhr.onerror = function() { _currentXhr = null; reject(new Error('Network error')); };
                    xhr.onabort = function() { _currentXhr = null; reject(new Error('Upload cancelled')); };
                    xhr.send(blob);
                });
            })
            .then(function() {
                return _confirmUpload(uploadNonce, csrfToken, item);
            });
    }

    // ── Multipart upload ────────────────────────────────────────────

    function _multipartUpload(item, csrfToken, opts) {
        var blob = item.blob;
        var totalParts = Math.ceil(blob.size / PART_SIZE);
        var params = _buildUploadParams(item);
        params.action = 'initiate_multipart';
        params.file_name = item.original_filename;
        params.file_size = blob.size;
        params.file_type = item.content_type;

        var objectKey = '';
        var uploadId = '';
        var uploadNonce = '';

        return _postAction(params, csrfToken)
            .then(function(data) {
                objectKey = data.object_key;
                uploadId = data.upload_id;
                uploadNonce = data.upload_nonce;
                return _uploadAllParts(blob, objectKey, uploadId, totalParts, item, csrfToken);
            })
            .then(function(parts) {
                return _postAction({
                    action: 'complete_multipart',
                    object_key: objectKey,
                    upload_id: uploadId,
                    parts: JSON.stringify(parts)
                }, csrfToken);
            })
            .then(function() {
                return _confirmUpload(uploadNonce, csrfToken, item);
            })
            .catch(function(err) {
                // Attempt to abort the multipart upload on failure
                if (uploadId) {
                    _postAction({
                        action: 'abort_multipart',
                        object_key: objectKey,
                        upload_id: uploadId
                    }, csrfToken).catch(function() {});
                }
                throw err;
            });
    }

    function _uploadAllParts(blob, objectKey, uploadId, totalParts, item, csrfToken) {
        var results = new Array(totalParts);
        var partBytes = new Array(totalParts);
        for (var i = 0; i < totalParts; i++) partBytes[i] = 0;
        var nextIndex = 0;
        var activeCount = 0;
        var completedCount = 0;

        return new Promise(function(resolve, reject) {
            var failed = false;

            function dispatch() {
                while (!failed && !_cancelled && activeCount < CONCURRENT_PARTS && nextIndex < totalParts) {
                    (function(idx) {
                        var partNumber = idx + 1;
                        activeCount++;
                        _uploadOnePart(blob, objectKey, uploadId, partNumber, totalParts, partBytes, item, csrfToken)
                            .then(function(result) {
                                if (failed) return;
                                partBytes[idx] = result.size;
                                results[idx] = { PartNumber: partNumber, ETag: result.etag };
                                activeCount--;
                                completedCount++;
                                // Update progress
                                var pct = Math.round((completedCount / totalParts) * 100);
                                _reportProgress(item, pct, 'Uploading part ' + completedCount + '/' + totalParts);
                                if (completedCount === totalParts) resolve(results);
                                else dispatch();
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

    function _uploadOnePart(blob, objectKey, uploadId, partNumber, totalParts, partBytes, item, csrfToken) {
        var start = (partNumber - 1) * PART_SIZE;
        var end = Math.min(start + PART_SIZE, blob.size);
        var chunkSize = end - start;
        var attempt = 0;

        function tryUpload() {
            attempt++;
            var chunk = blob.slice(start, end);

            return _postAction({
                action: 'presign_part',
                object_key: objectKey,
                upload_id: uploadId,
                part_number: partNumber
            }, csrfToken)
            .then(function(data) {
                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('PUT', data.presigned_url, true);
                    var partIndex = partNumber - 1;
                    var lastProgressTime = Date.now();

                    var stallTimer = setInterval(function() {
                        var sec = Math.round((Date.now() - lastProgressTime) / 1000);
                        if (sec >= STALL_ABORT_SEC) { xhr.abort(); }
                    }, STALL_TIMEOUT_SEC * 1000);

                    xhr.upload.onprogress = function(ev) {
                        if (ev.lengthComputable) {
                            if (ev.loaded > partBytes[partIndex]) lastProgressTime = Date.now();
                            partBytes[partIndex] = ev.loaded;
                            var totalUploaded = 0;
                            for (var i = 0; i < partBytes.length; i++) totalUploaded += partBytes[i];
                            var pct = Math.round((totalUploaded / blob.size) * 100);
                            _reportProgress(item, pct, 'Uploading… ' + pct + '%');
                        }
                    };
                    xhr.onload = function() {
                        clearInterval(stallTimer);
                        if (xhr.status >= 200 && xhr.status < 300) {
                            var etag = xhr.getResponseHeader('ETag');
                            if (etag) etag = etag.replace(/"/g, '');
                            if (!etag) { reject(new Error('No ETag for part ' + partNumber)); return; }
                            resolve(etag);
                        } else {
                            reject(new Error('Part ' + partNumber + ' failed: HTTP ' + xhr.status));
                        }
                    };
                    xhr.onerror = function() { clearInterval(stallTimer); reject(new Error('Network error part ' + partNumber)); };
                    xhr.onabort = function() { clearInterval(stallTimer); reject(new Error('Part ' + partNumber + ' aborted')); };
                    xhr.send(chunk);
                });
            })
            .then(function(etag) { return { etag: etag, size: chunkSize }; })
            .catch(function(err) {
                if (attempt < MAX_PART_RETRIES) {
                    var delay = Math.min(Math.pow(2, attempt), 30);
                    return new Promise(function(res) { setTimeout(res, delay * 1000); }).then(tryUpload);
                }
                throw err;
            });
        }
        return tryUpload();
    }

    // ── Confirm upload + trigger transcode ─────────────────────────

    function _confirmUpload(uploadNonce, csrfToken, item) {
        return _postAction({
            action: 'confirm_video_upload',
            upload_nonce: uploadNonce,
            offline_queue_id: item.id
        }, csrfToken, { keepalive: true }).then(function(result) {
            if (!result.success) throw new Error(result.error || 'Confirmation failed');

            // Trigger transcode as a separate explicit action (matches PR #533 pattern).
            // Fire-and-forget — don't block the queue on transcode trigger.
            var tp = { action: 'trigger_transcode', object_key: result.object_key || '' };
            if (result.video_id) tp.video_id = result.video_id;
            if (result.source_id) tp.source_id = result.source_id;
            if (tp.object_key) {
                _postAction(tp, csrfToken, { keepalive: true }).catch(function() {});
            }

            return result;
        });
    }

    // ── Build type-specific params for the upload initiation ────────

    function _buildUploadParams(item) {
        var p = { upload_type: item.upload_type };

        if (item.upload_type === 'drill_video') {
            p.session_id = item.session_id;
            p.drill_id = item.drill_id;
            p.athlete_id = item.athlete_id;
            p.rep_number = item.rep_number || 1;
        } else if (item.upload_type === 'coach_video') {
            p.athlete_id = item.athlete_id;
            p.session_date = item.session_date;
            p.drill_type = item.drill_type;
            p.drill_name = item.drill_name;
            p.description = item.description;
            if (item.rating) p.rating = item.rating;
        } else if (item.upload_type === 'video_source') {
            p.camera_angle = item.camera_angle;
            p.game_id = item.game_id;
            p.team_id = item.team_id;
        } else {
            // athlete_video
            p.title = item.title;
            p.video_category = item.video_category;
            if (item.description) p.description = item.description;
            if (item.coach_id) p.coach_id = item.coach_id;
            if (item.team_id) p.team_id = item.team_id;
            if (item.game_date) p.game_date = item.game_date;
            if (item.team_played_on) p.team_played_on = item.team_played_on;
            if (item.opponent_team) p.opponent_team = item.opponent_team;
        }

        return p;
    }

    // ── HTTP helpers ────────────────────────────────────────────────

    function _postAction(params, csrfToken, options) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        for (var k in params) {
            if (params.hasOwnProperty(k) && params[k] != null) {
                fd.append(k, params[k]);
            }
        }
        var fetchOpts = { method: 'POST', body: fd };
        if (options && options.keepalive) fetchOpts.keepalive = true;
        return fetch('process_video.php', fetchOpts)
            .then(function(r) {
                if (!r.ok) {
                    return r.text().then(function(body) {
                        var msg = 'HTTP ' + r.status;
                        try { var j = JSON.parse(body); if (j.error) msg += ': ' + j.error; }
                        catch(e) { if (body) msg += ': ' + body.substring(0, 200); }
                        throw new Error(msg);
                    });
                }
                return r.json();
            })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Request failed');
                return data;
            });
    }

    function _reportProgress(item, pct, message) {
        updateItemStatus(item.id, { upload_progress: pct }).catch(function() {});
        if (_onProgress) _onProgress(item, pct, message);
    }

    function _generateId() {
        return 'oq_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Cancel the current upload queue processing.
     */
    function cancelQueue() {
        _cancelled = true;
        if (_currentXhr) {
            _currentXhr.abort();
            _currentXhr = null;
        }
    }

    /**
     * Check if the queue is currently processing.
     */
    function isUploading() {
        return _uploading;
    }

    // ─── Connectivity monitoring & auto-prompt ──────────────────────

    var _autoPromptShown = false;

    function initConnectivityMonitor() {
        if (typeof window === 'undefined') return;

        window.addEventListener('online', function() {
            if (_autoPromptShown) return;
            getPendingCount().then(function(count) {
                if (count > 0) {
                    _autoPromptShown = true;
                    _showUploadPrompt(count);
                }
            });
        });

        // Also check on page load in case we came back online before the page loaded
        if (navigator.onLine) {
            getPendingCount().then(function(count) {
                if (count > 0) {
                    _autoPromptShown = true;
                    _showUploadPrompt(count);
                }
            });
        }
    }

    function _showUploadPrompt(count) {
        // Create banner at top of page
        var banner = document.createElement('div');
        banner.id = 'offlineUploadBanner';
        banner.className = 'offline-upload-banner';
        banner.innerHTML =
            '<div class="offline-upload-banner-content">' +
                '<div class="offline-upload-banner-icon"><i class="fas fa-cloud-upload-alt"></i></div>' +
                '<div class="offline-upload-banner-text">' +
                    '<strong>' + count + ' offline video' + (count !== 1 ? 's' : '') + ' ready to upload</strong>' +
                    '<span>You\'re back online. Upload your recorded videos now?</span>' +
                '</div>' +
                '<div class="offline-upload-banner-actions">' +
                    '<button type="button" class="btn btn-primary btn-sm" id="offlineUploadStartBtn">' +
                        '<i class="fas fa-upload"></i> Upload Now' +
                    '</button>' +
                    '<button type="button" class="btn btn-secondary btn-sm" id="offlineUploadDismissBtn">' +
                        'Later' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<div class="offline-upload-progress-section" id="offlineUploadProgressSection" style="display:none;">' +
                '<div class="offline-upload-progress-header">' +
                    '<span id="offlineUploadProgressTitle">Preparing upload…</span>' +
                    '<span id="offlineUploadProgressPercent">0%</span>' +
                '</div>' +
                '<div class="progress-bar"><div class="progress-fill" id="offlineUploadProgressFill"></div></div>' +
                '<div class="offline-upload-progress-footer">' +
                    '<span id="offlineUploadProgressStatus"></span>' +
                    '<button type="button" class="btn btn-danger btn-sm" id="offlineUploadCancelBtn">' +
                        '<i class="fas fa-stop"></i> Stop' +
                    '</button>' +
                '</div>' +
            '</div>';

        // Insert at the top of main content area
        var mainContent = document.querySelector('.main-content') || document.querySelector('.content') || document.body;
        mainContent.insertBefore(banner, mainContent.firstChild);

        // Bind button events
        document.getElementById('offlineUploadStartBtn').addEventListener('click', function() {
            _startQueueUpload(banner);
        });
        document.getElementById('offlineUploadDismissBtn').addEventListener('click', function() {
            banner.style.display = 'none';
            _autoPromptShown = false;
        });
    }

    function _startQueueUpload(banner) {
        var startBtn = document.getElementById('offlineUploadStartBtn');
        var dismissBtn = document.getElementById('offlineUploadDismissBtn');
        var progressSection = document.getElementById('offlineUploadProgressSection');
        var progressTitle = document.getElementById('offlineUploadProgressTitle');
        var progressPercent = document.getElementById('offlineUploadProgressPercent');
        var progressFill = document.getElementById('offlineUploadProgressFill');
        var progressStatus = document.getElementById('offlineUploadProgressStatus');

        startBtn.style.display = 'none';
        dismissBtn.style.display = 'none';
        progressSection.style.display = 'block';

        var csrfToken = '';
        var csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput) csrfToken = csrfInput.value;

        document.getElementById('offlineUploadCancelBtn').addEventListener('click', function() {
            cancelQueue();
            progressTitle.textContent = 'Upload cancelled.';
            startBtn.style.display = 'inline-flex';
            startBtn.textContent = ' Resume';
            dismissBtn.style.display = 'inline-flex';
        });

        var uploadedCount = 0;

        processQueue({
            csrfToken: csrfToken,
            onProgress: function(item, pct, msg) {
                progressFill.style.width = pct + '%';
                progressPercent.textContent = pct + '%';
                progressTitle.textContent = msg || ('Uploading: ' + (item.title || item.original_filename));
            },
            onItemComplete: function(item) {
                uploadedCount++;
                progressStatus.textContent = uploadedCount + ' video' + (uploadedCount !== 1 ? 's' : '') + ' uploaded';
                if (typeof showToast === 'function') {
                    showToast('Uploaded: ' + (item.title || item.original_filename), 'success');
                }
            },
            onItemError: function(item, err) {
                progressTitle.textContent = 'Failed: ' + (item.title || item.original_filename);
                progressStatus.textContent = err.message;
                startBtn.style.display = 'inline-flex';
                startBtn.innerHTML = '<i class="fas fa-redo"></i> Retry';
                dismissBtn.style.display = 'inline-flex';
                if (typeof showToast === 'function') {
                    showToast('Upload failed: ' + err.message + '. You can retry from this video.', 'error');
                }
            },
            onQueueComplete: function(stats) {
                progressFill.style.width = '100%';
                progressPercent.textContent = '100%';
                progressTitle.textContent = 'All uploads complete!';
                progressStatus.textContent = stats.uploaded + ' uploaded, ' + stats.failed + ' failed';
                if (stats.failed === 0) {
                    if (typeof persistToast === 'function') {
                        persistToast('All ' + stats.uploaded + ' offline videos uploaded successfully!', 'success');
                    }
                    setTimeout(function() { banner.style.display = 'none'; }, 5000);
                }
            },
            onStatusChange: function(status) {
                banner.setAttribute('data-upload-status', status);
            }
        });
    }

    // ─── External Storage (File System Access API) ────────────────

    /**
     * Metadata version for sidecar JSON files.  When the sidecar format
     * changes, bump this so the ingest reader can handle older files.
     */
    var SIDECAR_VERSION = 1;

    /**
     * Check whether the File System Access API is available.
     * Required for saving to / reading from external storage.
     */
    function isFileSystemAccessSupported() {
        return typeof window.showDirectoryPicker === 'function';
    }

    /**
     * Prompt the user to select a directory on any mounted drive
     * (internal storage, SD card, USB drive).
     * @returns {Promise<FileSystemDirectoryHandle>}
     */
    function pickStorageDirectory() {
        if (!isFileSystemAccessSupported()) {
            return Promise.reject(new Error('File System Access API not supported in this browser'));
        }
        return window.showDirectoryPicker({
            mode: 'readwrite',
            startIn: 'documents'
        });
    }

    /**
     * Save a video + metadata sidecar to an external directory.
     * Creates an "ArcticWolves_Recordings" sub-folder so ingest knows
     * what to scan.  Each video is saved with a unique filename and a
     * matching .json sidecar that carries all the routing metadata.
     *
     * @param {FileSystemDirectoryHandle} dirHandle  Root of the chosen drive
     * @param {Blob}   blob      The video blob
     * @param {Object} metadata  Same metadata object used for enqueueVideo()
     * @returns {Promise<{videoName: string, metaName: string}>}
     */
    function saveToExternalStorage(dirHandle, blob, metadata) {
        var folderName = 'ArcticWolves_Recordings';
        var id = _generateId();
        var ext = _extFromMime(blob.type);
        var videoName = (metadata.title || 'recording').replace(/[^a-zA-Z0-9_\-\s]/g, '').replace(/\s+/g, '_') + '_' + id + '.' + ext;
        var metaName  = videoName + '.meta.json';

        var sidecar = {
            version: SIDECAR_VERSION,
            id: id,
            recorded_at: new Date().toISOString(),
            file_size: blob.size,
            content_type: blob.type || 'video/webm',
            original_filename: videoName,
            upload_type: metadata.upload_type || 'athlete_video',
            user_id: metadata.user_id,
            user_role: metadata.user_role,
            title: metadata.title || '',
            description: metadata.description || '',
            video_category: metadata.video_category || 'drill',
            athlete_id: metadata.athlete_id || null,
            coach_id: metadata.coach_id || null,
            session_id: metadata.session_id || null,
            drill_id: metadata.drill_id || null,
            rep_number: metadata.rep_number || 1,
            session_date: metadata.session_date || null,
            drill_type: metadata.drill_type || null,
            drill_name: metadata.drill_name || null,
            rating: metadata.rating || null,
            game_date: metadata.game_date || null,
            team_played_on: metadata.team_played_on || null,
            opponent_team: metadata.opponent_team || null,
            camera_angle: metadata.camera_angle || null,
            game_id: metadata.game_id || null,
            team_id: metadata.team_id || null
        };

        var subDir;
        return dirHandle.getDirectoryHandle(folderName, { create: true })
            .then(function(dir) {
                subDir = dir;
                return subDir.getFileHandle(videoName, { create: true });
            })
            .then(function(fileHandle) {
                return fileHandle.createWritable();
            })
            .then(function(writable) {
                return writable.write(blob).then(function() { return writable.close(); });
            })
            .then(function() {
                return subDir.getFileHandle(metaName, { create: true });
            })
            .then(function(metaHandle) {
                return metaHandle.createWritable();
            })
            .then(function(writable) {
                var json = JSON.stringify(sidecar, null, 2);
                return writable.write(json).then(function() { return writable.close(); });
            })
            .then(function() {
                return { videoName: videoName, metaName: metaName };
            });
    }

    function _extFromMime(mime) {
        var map = {
            'video/mp4': 'mp4', 'video/webm': 'webm', 'video/quicktime': 'mov',
            'video/x-matroska': 'mkv', 'video/x-msvideo': 'avi'
        };
        return map[mime] || 'mp4';
    }

    // ─── Ingest: scan external drive for videos + sidecars ─────────

    /**
     * Scan a directory (from File System Access picker) for
     * ArcticWolves_Recordings and return an array of ingestable items.
     * Each item has { metadata, videoFileHandle }.
     *
     * @param {FileSystemDirectoryHandle} dirHandle
     * @returns {Promise<Array<{metadata: Object, videoFileHandle: FileSystemFileHandle}>>}
     */
    function scanForIngest(dirHandle) {
        return dirHandle.getDirectoryHandle('ArcticWolves_Recordings', { create: false })
            .then(function(subDir) {
                return _readAllEntries(subDir);
            })
            .then(function(entries) {
                // Build a map of .meta.json files and their companion video files
                var metaFiles = {};
                var videoFiles = {};

                entries.forEach(function(entry) {
                    if (entry.name.endsWith('.meta.json')) {
                        // Key is the video filename (strip .meta.json)
                        var videoKey = entry.name.replace('.meta.json', '');
                        metaFiles[videoKey] = entry;
                    } else if (_isVideoFile(entry.name)) {
                        videoFiles[entry.name] = entry;
                    }
                });

                // Match pairs
                var pairs = [];
                var pairPromises = [];

                Object.keys(metaFiles).forEach(function(videoKey) {
                    if (videoFiles[videoKey]) {
                        pairPromises.push(
                            metaFiles[videoKey].getFile()
                                .then(function(file) { return file.text(); })
                                .then(function(json) {
                                    var metadata = JSON.parse(json);
                                    pairs.push({
                                        metadata: metadata,
                                        videoFileHandle: videoFiles[videoKey],
                                        metaFileHandle: metaFiles[videoKey]
                                    });
                                })
                                .catch(function() { /* skip corrupt sidecar */ })
                        );
                    }
                });

                return Promise.all(pairPromises).then(function() {
                    pairs.sort(function(a, b) {
                        return new Date(a.metadata.recorded_at || 0) - new Date(b.metadata.recorded_at || 0);
                    });
                    return pairs;
                });
            })
            .catch(function(err) {
                if (err.name === 'NotFoundError') return [];
                throw err;
            });
    }

    function _readAllEntries(dirHandle) {
        var entries = [];
        var iter = dirHandle.values();

        function readNext() {
            return iter.next().then(function(result) {
                if (result.done) return entries;
                if (result.value.kind === 'file') {
                    entries.push(result.value);
                }
                return readNext();
            });
        }
        return readNext();
    }

    function _isVideoFile(name) {
        return /\.(mp4|mkv|mov|avi|webm)$/i.test(name);
    }

    /**
     * Ingest videos from scanned pairs — enqueue each into IndexedDB
     * for upload via the standard queue, then optionally delete from device.
     *
     * @param {Array}  pairs            From scanForIngest()
     * @param {Object} opts
     * @param {boolean} opts.deleteAfterIngest  Remove files from external drive after enqueue
     * @param {FileSystemDirectoryHandle} opts.dirHandle  Root directory handle
     * @param {Function} [opts.onProgress]  (index, total, item)
     * @returns {Promise<{ingested: number, failed: number}>}
     */
    function ingestFromDevice(pairs, opts) {
        var stats = { ingested: 0, failed: 0 };
        var index = 0;

        function processNext() {
            if (index >= pairs.length) return Promise.resolve(stats);
            var pair = pairs[index];
            if (opts.onProgress) opts.onProgress(index, pairs.length, pair.metadata);

            return pair.videoFileHandle.getFile()
                .then(function(videoFile) {
                    return enqueueVideo(videoFile, pair.metadata);
                })
                .then(function() {
                    stats.ingested++;
                    // If requested, delete files from external drive
                    if (opts.deleteAfterIngest && opts.dirHandle) {
                        return opts.dirHandle.getDirectoryHandle('ArcticWolves_Recordings', { create: false })
                            .then(function(subDir) {
                                return subDir.removeEntry(pair.videoFileHandle.name)
                                    .then(function() { return subDir.removeEntry(pair.metaFileHandle.name); })
                                    .catch(function() { /* ignore delete errors */ });
                            })
                            .catch(function() {});
                    }
                })
                .catch(function() {
                    stats.failed++;
                })
                .then(function() {
                    index++;
                    return processNext();
                });
        }

        return processNext();
    }

    // ─── Expose public API ──────────────────────────────────────────

    window.AwOfflineQueue = {
        enqueueVideo: enqueueVideo,
        getPendingCount: getPendingCount,
        listQueue: listQueue,
        getItem: getItem,
        removeItem: removeItem,
        processQueue: processQueue,
        cancelQueue: cancelQueue,
        isUploading: isUploading,
        initConnectivityMonitor: initConnectivityMonitor,
        // External storage
        isFileSystemAccessSupported: isFileSystemAccessSupported,
        pickStorageDirectory: pickStorageDirectory,
        saveToExternalStorage: saveToExternalStorage,
        // Ingest from external device
        scanForIngest: scanForIngest,
        ingestFromDevice: ingestFromDevice
    };

})(window);
