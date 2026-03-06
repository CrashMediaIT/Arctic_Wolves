/**
 * Arctic Wolves Video Player
 * Application-wide video player with HLS.js and dash.js support for
 * adaptive quality streaming across all browsers.
 * YouTube-style custom controls with resolution picker and auto bandwidth detection.
 *
 * Usage:
 *   var hls = window.awInitHlsPlayer(videoElement, videoUrl);
 *   // To destroy: if (hls) hls.destroy();
 *
 * Playback priority:
 *   1. MPEG-DASH via dash.js on Chrome/Edge/Firefox (MSE-native, shared fMP4 segments)
 *   2. Native HLS on Safari/iOS (built-in, zero library overhead)
 *   3. HLS via HLS.js (fallback for all MSE-capable browsers)
 *   4. Direct video file (MP4/WebM/etc.)
 *
 * Requires: HLS.js and/or dash.js loaded before this script (CDN or local).
 */
(function() {
    'use strict';

    /* ---- Helpers ---- */
    function _formatTime(secs) {
        if (!isFinite(secs) || secs < 0) return '0:00';
        var h = Math.floor(secs / 3600);
        var m = Math.floor((secs % 3600) / 60);
        var s = Math.floor(secs % 60);
        if (h > 0) return h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    /**
     * Collect browser / environment diagnostics once for inclusion in logs.
     */
    var _browserInfo = (function() {
        try {
            var ua = navigator.userAgent || '';
            var browser = 'unknown';
            if (/Edg\//i.test(ua))          browser = 'Edge';
            else if (/Chrome\//i.test(ua))  browser = 'Chrome';
            else if (/Firefox\//i.test(ua)) browser = 'Firefox';
            else if (/Safari\//i.test(ua))  browser = 'Safari';
            var mse = typeof MediaSource !== 'undefined';
            var hlsNative = false;
            try { hlsNative = !!document.createElement('video').canPlayType('application/vnd.apple.mpegurl'); } catch (_) {}
            return {
                browser: browser,
                ua: ua.substring(0, 200),
                mse: mse,
                hlsNative: hlsNative,
                hlsJsVersion: (typeof Hls !== 'undefined' && Hls.version) ? Hls.version : 'n/a'
            };
        } catch (_) { return {}; }
    })();

    /**
     * Report a video playback event / error to the server so it appears in
     * the admin Security / error_logs view.  Fire-and-forget — failures are
     * silently ignored to avoid disrupting the user experience.
     *
     * Duplicate-suppressed: truly identical messages within 1 s are dropped
     * to prevent runaway loops, but every *distinct* message is sent so
     * admins get a complete diagnostic trail.
     */
    var _errorLastSent = {};  // throttleKey → timestamp
    var _DEDUP_MS = 1000;     // suppress exact-duplicate within 1 s

    function _reportPlaybackError(message, context) {
        try {
            // De-duplicate truly identical messages within 1 s
            var dedupeKey = String(message).substring(0, 80);
            var now = Date.now();
            if (_errorLastSent[dedupeKey] && (now - _errorLastSent[dedupeKey]) < _DEDUP_MS) {
                return;
            }
            _errorLastSent[dedupeKey] = now;

            // Log to browser console for developer visibility
            var isError = context && context.type && context.type !== 'lifecycle' && context.type !== 'video_event';
            if (isError) {
                console.error('[AW Video] ' + message, context || '');
            } else {
                console.log('[AW Video] ' + message);
            }

            // Always attach browser diagnostics
            if (context && typeof context === 'object') {
                context._browser = _browserInfo;
            }

            var meta = document.querySelector('meta[name="csrf-token"]');
            var token = meta ? meta.getAttribute('content') : '';
            if (!token) return; // No CSRF token available — skip
            var body = 'csrf_token=' + encodeURIComponent(token)
                     + '&message=' + encodeURIComponent(message);
            if (context) {
                body += '&context=' + encodeURIComponent(JSON.stringify(context));
            }
            if (typeof fetch === 'function') {
                fetch('process_log_client_error.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    keepalive: true
                }).catch(function() {});
            }
        } catch (_e) { /* never throw from error reporter */ }
    }

    /**
     * Snapshot the current video / HLS buffer state for diagnostic context.
     */
    function _videoState(video, hls) {
        var state = {};
        try {
            if (video) {
                state.currentTime = video.currentTime;
                state.duration    = video.duration;
                state.paused      = video.paused;
                state.ended       = video.ended;
                state.readyState  = video.readyState;
                state.networkState = video.networkState;
                state.error       = video.error ? { code: video.error.code, message: video.error.message || '' } : null;
                // Buffered ranges
                var buf = video.buffered;
                if (buf && buf.length) {
                    state.buffered = [];
                    for (var i = 0; i < buf.length; i++) {
                        state.buffered.push([+buf.start(i).toFixed(2), +buf.end(i).toFixed(2)]);
                    }
                }
            }
            if (hls) {
                state.hlsLevel       = hls.currentLevel;
                state.hlsLoadLevel   = hls.loadLevel;
                state.hlsBandwidth   = hls.bandwidthEstimate;
                if (hls.levels && hls.levels.length) {
                    state.hlsLevelCount = hls.levels.length;
                }
            }
        } catch (_) { /* best-effort */ }
        return state;
    }

    /**
     * Convert a relative URL to an absolute URL.
     * Player libraries (especially dash.js v5) may fail to resolve relative
     * URLs when the manifest is served through a query-string proxy like
     * media.php?key=…  Using absolute URLs avoids this class of bugs.
     */
    function _toAbsoluteUrl(url) {
        if (!url) return url;
        // Already absolute
        if (/^https?:\/\//i.test(url) || /^blob:/i.test(url)) return url;
        try {
            return new URL(url, window.location.href).href;
        } catch (_) {
            return url;
        }
    }

    /**
     * Initialise a video element for playback.
     * @param {HTMLVideoElement} video  The <video> element.
     * @param {string}           url    Video URL (.m3u8 for HLS, or direct file).
     * @returns {Hls|null}  The HLS.js instance (if used), or null.
     */
    function awInitHlsPlayer(video, url) {
        if (!video || !url) {
            if (video && !url) {
                _reportPlaybackError('HLS init skipped: empty video URL', { element: video.id || 'unknown', type: 'silent_failure' });
            }
            return null;
        }

        // Guard: if url is the page URL (happens when <source src=""> resolves
        // the empty attribute to the current document), bail out and report.
        if (url === window.location.href || url === window.location.origin + window.location.pathname) {
            _reportPlaybackError('HLS init skipped: URL resolved to page URL (source likely empty)', { element: video.id || 'unknown', url: url, type: 'silent_failure' });
            return null;
        }

        // Destroy any previous HLS.js instance attached to this video to avoid
        // two instances fighting for the same MediaSource object.
        if (video._awHls) {
            _reportPlaybackError('HLS init: destroying previous HLS.js instance before re-init', { element: video.id || 'unknown', url: url, type: 'lifecycle' });
            try { video._awHls.destroy(); } catch (e) { /* ignore */ }
            video._awHls = null;
        }
        // Destroy any previous dash.js instance too
        if (video._awDash) {
            _reportPlaybackError('HLS init: destroying previous dash.js instance before re-init', { element: video.id || 'unknown', url: url, type: 'lifecycle' });
            try { video._awDash.reset(); } catch (e) { /* ignore */ }
            video._awDash = null;
        }

        // Convert relative URL to absolute — some player libraries
        // (particularly dash.js v5) may not resolve relative URLs properly
        // through a query-string based media proxy.
        url = _toAbsoluteUrl(url);

        var isHLS = /\.m3u8(\?|$)/i.test(url);

        // ── Smart format selection ──────────────────────────────────
        // Both HLS and DASH manifests reference the same fMP4 segments
        // (single encode).  Choose the optimal manifest per browser:
        //   • Safari / iOS → native HLS (built-in, zero library overhead)
        //   • Chrome / Edge / Firefox → DASH via dash.js (native MSE,
        //     avoids HLS.js overhead when fMP4 segments are shared)
        //   • Fallback → HLS via HLS.js if DASH init fails
        var dashUrl = video.getAttribute('data-dash-url');
        if (dashUrl) dashUrl = _toAbsoluteUrl(dashUrl);
        var isSafari = _browserInfo.browser === 'Safari';
        var preferDash = !isSafari && dashUrl && typeof dashjs !== 'undefined'
                         && typeof MediaSource !== 'undefined'
                         && !video._awDashFallbackAttempted;

        if (isHLS && preferDash) {
            _reportPlaybackError('Format selection: preferring DASH for ' + _browserInfo.browser, {
                element: video.id || 'unknown', hlsUrl: url, dashUrl: dashUrl,
                type: 'lifecycle', browser: _browserInfo.browser
            });
            var dashPlayer = _initDashPlayer(video, dashUrl);
            if (dashPlayer) {
                // Store the HLS URL so we can fall back if DASH errors out
                video._awHlsFallbackUrl = url;
                return dashPlayer;
            }
            // DASH init failed — fall through to HLS
            _reportPlaybackError('DASH preferred but init failed, falling back to HLS', {
                element: video.id || 'unknown', url: url, type: 'lifecycle'
            });
        }

        // HLS source and HLS.js is available
        if (isHLS && typeof Hls !== 'undefined' && Hls.isSupported()) {
            _reportPlaybackError('HLS init: creating HLS.js instance', {
                element: video.id || 'unknown', url: url, type: 'lifecycle',
                isHLS: true, hlsJsVersion: (Hls.version || 'unknown'),
                mseSupported: Hls.isSupported()
            });

            var hls = new Hls({
                maxBufferLength: 30,
                maxMaxBufferLength: 60,
                startLevel: -1, // Auto quality selection
                enableWorker: true,
                // Note: do NOT use xhrSetup with xhr.open() — it resets
                // responseType which causes bufferAppendError on .ts segments.
                // Server-side Cache-Control: no-store on error responses
                // (api/media.php) prevents caching without client-side hacks.
            });

            // Store reference so subsequent calls can clean up
            video._awHls = hls;

            hls.loadSource(url);
            hls.attachMedia(video);

            // --- Lifecycle event logging for diagnostics ---
            hls.on(Hls.Events.MANIFEST_LOADING, function(_event, data) {
                _reportPlaybackError('HLS lifecycle: manifest loading', { url: data.url, type: 'lifecycle' });
            });

            hls.on(Hls.Events.MANIFEST_LOADED, function(_event, data) {
                _reportPlaybackError('HLS lifecycle: manifest loaded', {
                    url: data.url, type: 'lifecycle',
                    levels: data.levels ? data.levels.length : 0,
                    audioTracks: data.audioTracks ? data.audioTracks.length : 0
                });
            });

            hls.on(Hls.Events.MANIFEST_PARSED, function(_event, data) {
                var levelInfo = [];
                if (data.levels) {
                    for (var i = 0; i < data.levels.length; i++) {
                        var lv = data.levels[i];
                        levelInfo.push({
                            index: i,
                            width: lv.width, height: lv.height,
                            bitrate: lv.bitrate,
                            codecs: lv.codecSet || (lv.attrs && lv.attrs.CODECS) || ''
                        });
                    }
                }
                _reportPlaybackError('HLS lifecycle: manifest parsed — starting playback', {
                    url: url, type: 'lifecycle',
                    levels: levelInfo,
                    firstLevel: data.firstLevel,
                    video: data.video, audio: data.audio
                });
                video.play().catch(function() {});
                _buildCustomControls(video, hls, data.levels);
            });

            hls.on(Hls.Events.LEVEL_SWITCHING, function(_event, data) {
                var lv = hls.levels && hls.levels[data.level];
                _reportPlaybackError('HLS lifecycle: level switching to ' + data.level, {
                    url: url, type: 'lifecycle',
                    level: data.level,
                    resolution: lv ? (lv.width + 'x' + lv.height) : 'unknown',
                    bitrate: lv ? lv.bitrate : 0
                });
            });

            hls.on(Hls.Events.FRAG_LOADED, function(_event, data) {
                // Log the first fragment load for diagnostics, then every 10th
                _fragCount++;
                if (_fragCount === 1 || _fragCount % 10 === 0) {
                    _reportPlaybackError('HLS lifecycle: fragment loaded (#' + _fragCount + ')', {
                        url: url, type: 'lifecycle',
                        fragUrl: data.frag ? data.frag.url : '',
                        fragSn: data.frag ? data.frag.sn : '',
                        fragLevel: data.frag ? data.frag.level : '',
                        fragDuration: data.frag ? data.frag.duration : 0,
                        videoState: _videoState(video, hls)
                    });
                }
            });

            hls.on(Hls.Events.BUFFER_CREATED, function(_event, data) {
                var tracks = {};
                if (data.tracks) {
                    for (var k in data.tracks) {
                        if (data.tracks.hasOwnProperty(k)) {
                            tracks[k] = {
                                container: data.tracks[k].container,
                                codec: data.tracks[k].codec || data.tracks[k].levelCodec || ''
                            };
                        }
                    }
                }
                _reportPlaybackError('HLS lifecycle: source buffers created', { url: url, type: 'lifecycle', tracks: tracks });
            });

            // --- Video element event logging ---
            video.addEventListener('playing', function _awPlaying() {
                _reportPlaybackError('HLS video event: playing', { url: url, type: 'video_event', videoState: _videoState(video, hls) });
                video.removeEventListener('playing', _awPlaying);
            });

            video.addEventListener('waiting', function() {
                _reportPlaybackError('HLS video event: waiting (stall/rebuffer)', { url: url, type: 'video_event', videoState: _videoState(video, hls) });
            });

            video.addEventListener('stalled', function() {
                _reportPlaybackError('HLS video event: stalled', { url: url, type: 'video_event', videoState: _videoState(video, hls) });
            });

            // --- Error handling with full diagnostic logging ---
            var _fragCount = 0;
            var _deferredRecovery = false;
            var _networkRetries = 0;
            var _MAX_NETWORK_RETRIES = 4;
            // Time-based media error recovery per HLS.js recommended pattern.
            // Only attempt recovery if at least _MEDIA_RECOVER_COOLDOWN_MS has
            // elapsed since the last attempt, and cap total attempts.
            var _lastMediaRecovery = 0;
            var _mediaRecoveryAttempts = 0;
            var _MAX_MEDIA_RECOVERY = 3;
            var _MEDIA_RECOVER_COOLDOWN_MS = 5000;

            hls.on(Hls.Events.ERROR, function(_event, data) {
                var errDetail = (data.details || 'unknown') + (data.reason ? ' — ' + data.reason : '');
                var errContext = {
                    url: url,
                    type: data.type ? String(data.type) : 'unknown',
                    detail: data.details || 'unknown',
                    fatal: !!data.fatal,
                    videoState: _videoState(video, hls)
                };
                // Include fragment info when available
                if (data.frag) {
                    errContext.frag = { sn: data.frag.sn, url: data.frag.url, level: data.frag.level, duration: data.frag.duration };
                }
                // Include response info when available (network errors)
                if (data.response) {
                    errContext.response = { code: data.response.code, text: (data.response.text || '').substring(0, 200) };
                }

                // --- Log ALL non-fatal errors for diagnostics ---
                if (!data.fatal) {
                    _reportPlaybackError('HLS non-fatal error: ' + errDetail, errContext);
                    return;
                }

                // --- Fatal errors ---
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        if (_networkRetries < _MAX_NETWORK_RETRIES) {
                            _networkRetries++;
                            // Exponential backoff: 500ms, 1s, 2s, 4s
                            var delay = Math.min(500 * Math.pow(2, _networkRetries - 1), 4000);
                            errContext.retry = _networkRetries;
                            errContext.maxRetries = _MAX_NETWORK_RETRIES;
                            errContext.backoffMs = delay;
                            _reportPlaybackError('HLS FATAL network error (retry ' + _networkRetries + '/' + _MAX_NETWORK_RETRIES + '): ' + errDetail, errContext);
                            setTimeout(function() { hls.startLoad(); }, delay);
                        } else {
                            errContext.action = 'destroying_hls';
                            _reportPlaybackError('HLS FATAL network error (retries exhausted): ' + errDetail, errContext);
                            // Exhausted retries — destroy HLS.js and fire
                            // a native error so view-level fallback handlers
                            // (e.g. data-fallback-url retry) can take over.
                            hls.destroy();
                            video.dispatchEvent(new Event('error'));
                        }
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        var now = Date.now();
                        errContext.recoveryAttempts = _mediaRecoveryAttempts;
                        errContext.maxRecovery = _MAX_MEDIA_RECOVERY;
                        errContext.msSinceLastRecovery = _lastMediaRecovery ? (now - _lastMediaRecovery) : null;
                        errContext.cooldownMs = _MEDIA_RECOVER_COOLDOWN_MS;

                        if (_mediaRecoveryAttempts < _MAX_MEDIA_RECOVERY &&
                            (!_lastMediaRecovery || (now - _lastMediaRecovery) > _MEDIA_RECOVER_COOLDOWN_MS)) {
                            _mediaRecoveryAttempts++;
                            _lastMediaRecovery = now;
                            errContext.action = 'recoverMediaError';
                            errContext.attempt = _mediaRecoveryAttempts;
                            _reportPlaybackError('HLS FATAL media error (recovery ' + _mediaRecoveryAttempts + '/' + _MAX_MEDIA_RECOVERY + '): ' + errDetail, errContext);
                            hls.recoverMediaError();
                        } else if (_mediaRecoveryAttempts >= _MAX_MEDIA_RECOVERY) {
                            errContext.action = 'destroying_hls';
                            _reportPlaybackError('HLS FATAL media error (recovery exhausted): ' + errDetail, errContext);
                            hls.destroy();
                            video.dispatchEvent(new Event('error'));
                        } else {
                            // Cooldown not elapsed — schedule a deferred recovery attempt.
                            errContext.action = 'deferred_recovery';
                            _reportPlaybackError('HLS FATAL media error (cooldown active, deferring recovery): ' + errDetail, errContext);
                            if (!_deferredRecovery) {
                                _deferredRecovery = true;
                                setTimeout(function() {
                                    _deferredRecovery = false;
                                    if (hls.media) {
                                        _mediaRecoveryAttempts++;
                                        _lastMediaRecovery = Date.now();
                                        _reportPlaybackError('HLS FATAL media error (deferred recovery ' + _mediaRecoveryAttempts + '/' + _MAX_MEDIA_RECOVERY + '): ' + errDetail, {
                                            url: url, type: 'media', attempt: _mediaRecoveryAttempts, action: 'recoverMediaError',
                                            videoState: _videoState(video, hls)
                                        });
                                        hls.recoverMediaError();
                                    }
                                }, _MEDIA_RECOVER_COOLDOWN_MS);
                            }
                        }
                        break;
                    default:
                        errContext.action = 'destroying_hls';
                        _reportPlaybackError('HLS FATAL error: ' + errDetail, errContext);
                        hls.destroy();
                        // Dispatch a native error so view-level fallback
                        // handlers (e.g. data-fallback-url retry) can take over.
                        // Setting video.src to an m3u8 URL on Chrome would
                        // fail silently since native HLS is unsupported.
                        video.dispatchEvent(new Event('error'));
                        break;
                }
            });

            return hls;
        }

        // Safari native HLS or non-HLS source
        if (isHLS && video.canPlayType('application/vnd.apple.mpegurl')) {
            _reportPlaybackError('HLS init: using native Safari HLS support', { element: video.id || 'unknown', url: url, type: 'lifecycle' });
            video.src = url;
            video.addEventListener('loadedmetadata', function() {
                video.play().catch(function() {});
            }, { once: true });
            _buildCustomControls(video, null, null);
            return null;
        }

        // HLS URL but no player available — try DASH fallback
        if (isHLS) {
            var dashUrl = video.getAttribute('data-dash-url');
            if (dashUrl && typeof dashjs !== 'undefined') {
                _reportPlaybackError('HLS unavailable, trying DASH fallback via dash.js', {
                    element: video.id || 'unknown', hlsUrl: url, dashUrl: dashUrl, type: 'lifecycle'
                });
                return _initDashPlayer(video, dashUrl);
            }
            _reportPlaybackError('HLS stream cannot play: HLS.js not available and browser has no native HLS support', {
                element: video.id || 'unknown',
                url: url,
                hlsJsLoaded: typeof Hls !== 'undefined',
                hlsSupported: (typeof Hls !== 'undefined') ? Hls.isSupported() : false,
                dashJsLoaded: typeof dashjs !== 'undefined',
                dashUrl: dashUrl || '',
                type: 'hls_unavailable'
            });
            return null;
        }

        // Direct video file (MP4/WebM/etc.)
        _reportPlaybackError('HLS init: direct file playback (non-HLS)', { element: video.id || 'unknown', url: url, type: 'lifecycle' });
        var source = video.querySelector('source');
        if (source) {
            source.src = url;
            video.load();
        } else {
            video.src = url;
        }
        video.play().catch(function(err) {
            _reportPlaybackError('Direct video play failed: ' + (err.message || err), { element: video.id || 'unknown', url: url, type: 'direct_play_error' });
        });
        _buildCustomControls(video, null, null);
        return null;
    }

    /**
     * Initialise MPEG-DASH playback via dash.js.
     * Used as a cross-browser fallback when HLS.js is unavailable or fails.
     *
     * @param {HTMLVideoElement} video  The <video> element.
     * @param {string}           url    DASH MPD manifest URL.
     * @returns {object|null}  The dash.js MediaPlayer instance, or null.
     */
    function _initDashPlayer(video, url) {
        if (!video || !url) return null;
        if (typeof dashjs === 'undefined' || !dashjs.MediaPlayer) {
            _reportPlaybackError('DASH init failed: dash.js not loaded', { element: video.id || 'unknown', url: url, type: 'dash_unavailable' });
            return null;
        }

        _reportPlaybackError('DASH init: creating dash.js player', { element: video.id || 'unknown', url: url, type: 'lifecycle' });

        // Destroy any previous HLS.js instance
        if (video._awHls) {
            try { video._awHls.destroy(); } catch (e) { /* ignore */ }
            video._awHls = null;
        }
        // Destroy any previous dash.js instance
        if (video._awDash) {
            try { video._awDash.reset(); } catch (e) { /* ignore */ }
            video._awDash = null;
        }

        // Convert to absolute URL — dash.js v5 may not resolve relative
        // query-string proxy URLs correctly
        url = _toAbsoluteUrl(url);

        try {
            var player = dashjs.MediaPlayer().create();
            player.initialize(video, url, /* autoPlay: false — use manual play() after streamInitialized to avoid browser autoplay restrictions */ false);
            video._awDash = player;

            // Timeout: if DASH doesn't initialise within 10 s, fall back to HLS.
            // This catches silent failures where dash.js never fires an error.
            var dashTimeout = setTimeout(function() {
                if (video._awDash === player && !video._awDashStreamOk) {
                    _reportPlaybackError('DASH timeout: stream not initialized after 10 s, falling back to HLS', {
                        element: video.id || 'unknown', url: url, type: 'dash_error'
                    });
                    var hlsFallback = video._awHlsFallbackUrl;
                    if (hlsFallback && !video._awDashFallbackAttempted) {
                        video._awDashFallbackAttempted = true;
                        try { player.reset(); } catch (_) {}
                        video._awDash = null;
                        awInitHlsPlayer(video, hlsFallback);
                    }
                }
            }, 10000);

            player.on('error', function(e) {
                clearTimeout(dashTimeout);
                _reportPlaybackError('DASH error: ' + (e.error ? e.error.message || e.error.code : 'unknown'), {
                    element: video.id || 'unknown', url: url, type: 'dash_error',
                    error: e.error || e
                });
                // If DASH was the preferred format and it errored, fall back to HLS
                var hlsFallback = video._awHlsFallbackUrl;
                if (hlsFallback && !video._awDashFallbackAttempted) {
                    video._awDashFallbackAttempted = true;
                    _reportPlaybackError('DASH failed, falling back to HLS', {
                        element: video.id || 'unknown', hlsUrl: hlsFallback, type: 'lifecycle'
                    });
                    try { player.reset(); } catch (_) {}
                    video._awDash = null;
                    awInitHlsPlayer(video, hlsFallback);
                }
            });

            player.on('streamInitialized', function() {
                clearTimeout(dashTimeout);
                video._awDashStreamOk = true;
                _reportPlaybackError('DASH lifecycle: stream initialized', { url: url, type: 'lifecycle' });
                video.play().catch(function() {});
                _buildCustomControls(video, null, null);
            });

            return player;
        } catch (err) {
            _reportPlaybackError('DASH init exception: ' + (err.message || err), { element: video.id || 'unknown', url: url, type: 'dash_error' });
            return null;
        }
    }

    /**
     * Try DASH fallback for a video element.
     * Called by view-level error handlers when HLS playback fails completely.
     * Reads the data-dash-url attribute from the video element.
     *
     * @param {HTMLVideoElement} video  The <video> element.
     * @returns {object|null}  The dash.js player instance, or null if DASH unavailable.
     */
    function awTryDashFallback(video) {
        if (!video) return null;
        var dashUrl = video.getAttribute('data-dash-url');
        if (dashUrl) dashUrl = _toAbsoluteUrl(dashUrl);
        if (!dashUrl) {
            _reportPlaybackError('DASH fallback: no data-dash-url attribute', { element: video.id || 'unknown', type: 'dash_unavailable' });
            return null;
        }
        if (typeof dashjs === 'undefined') {
            _reportPlaybackError('DASH fallback: dash.js library not loaded', { element: video.id || 'unknown', dashUrl: dashUrl, type: 'dash_unavailable' });
            return null;
        }
        return _initDashPlayer(video, dashUrl);
    }

    /**
     * Build YouTube-style custom controls overlaid on the video container.
     */
    function _buildCustomControls(video, hls, levels) {
        var container = video.parentElement;
        if (!container) return;

        // Remove existing controls if any
        var existing = container.querySelector('.aw-player-controls');
        if (existing) {
            if (existing._cleanup) existing._cleanup();
            existing.remove();
        }
        // Remove legacy quality wrapper if present
        var legacyQuality = container.querySelector('.aw-quality-wrapper');
        if (legacyQuality) {
            if (legacyQuality._closeHandler) {
                document.removeEventListener('click', legacyQuality._closeHandler);
            }
            legacyQuality.remove();
        }

        // Ensure container is positioned
        var pos = getComputedStyle(container).position;
        if (pos === 'static') container.style.position = 'relative';

        // Ensure container maintains 16:9 ratio for proper controls display
        if (!getComputedStyle(container).aspectRatio || getComputedStyle(container).aspectRatio === 'auto') {
            container.style.aspectRatio = '16 / 9';
        }

        // Hide native controls
        video.removeAttribute('controls');
        video.setAttribute('playsinline', '');

        // --- Build controls DOM ---
        var controls = document.createElement('div');
        controls.className = 'aw-player-controls';

        // Progress bar area
        var progressWrap = document.createElement('div');
        progressWrap.className = 'aw-progress-wrap';

        var progressBar = document.createElement('div');
        progressBar.className = 'aw-progress-bar';

        var progressBuffered = document.createElement('div');
        progressBuffered.className = 'aw-progress-buffered';

        var progressPlayed = document.createElement('div');
        progressPlayed.className = 'aw-progress-played';

        var progressThumb = document.createElement('div');
        progressThumb.className = 'aw-progress-thumb';

        var progressHoverTime = document.createElement('div');
        progressHoverTime.className = 'aw-progress-hover-time';

        progressBar.appendChild(progressBuffered);
        progressBar.appendChild(progressPlayed);
        progressBar.appendChild(progressThumb);
        progressWrap.appendChild(progressBar);
        progressWrap.appendChild(progressHoverTime);

        // Bottom bar with buttons
        var bottomBar = document.createElement('div');
        bottomBar.className = 'aw-controls-bar';

        // Left group
        var leftGroup = document.createElement('div');
        leftGroup.className = 'aw-controls-left';

        // Play/Pause button
        var playBtn = document.createElement('button');
        playBtn.className = 'aw-ctrl-btn aw-play-btn';
        playBtn.type = 'button';
        playBtn.title = 'Play';
        playBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>';

        // Volume group
        var volumeGroup = document.createElement('div');
        volumeGroup.className = 'aw-volume-group';

        var volumeBtn = document.createElement('button');
        volumeBtn.className = 'aw-ctrl-btn aw-volume-btn';
        volumeBtn.type = 'button';
        volumeBtn.title = 'Mute';
        volumeBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>';

        var volumeSlider = document.createElement('input');
        volumeSlider.className = 'aw-volume-slider';
        volumeSlider.type = 'range';
        volumeSlider.min = '0';
        volumeSlider.max = '1';
        volumeSlider.step = '0.05';
        volumeSlider.value = String(video.volume);

        volumeGroup.appendChild(volumeBtn);
        volumeGroup.appendChild(volumeSlider);

        // Time display
        var timeDisplay = document.createElement('span');
        timeDisplay.className = 'aw-time-display';
        timeDisplay.textContent = '0:00 / 0:00';

        leftGroup.appendChild(playBtn);
        leftGroup.appendChild(volumeGroup);
        leftGroup.appendChild(timeDisplay);

        // Right group
        var rightGroup = document.createElement('div');
        rightGroup.className = 'aw-controls-right';

        // Settings (quality) button - only for HLS with multiple levels
        var settingsBtn = null;
        var settingsPanel = null;
        if (levels && levels.length >= 2 && hls) {
            settingsBtn = document.createElement('button');
            settingsBtn.className = 'aw-ctrl-btn aw-settings-btn';
            settingsBtn.type = 'button';
            settingsBtn.title = 'Settings';
            settingsBtn.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.49.49 0 0 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>';

            settingsPanel = document.createElement('div');
            settingsPanel.className = 'aw-settings-panel';
            settingsPanel.style.display = 'none';

            var panelTitle = document.createElement('div');
            panelTitle.className = 'aw-settings-title';
            panelTitle.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" style="opacity:0.7"><path fill="currentColor" d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.49.49 0 0 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg> Quality';

            settingsPanel.appendChild(panelTitle);

            // Auto option
            var autoItem = document.createElement('div');
            autoItem.className = 'aw-quality-item active';
            autoItem.dataset.level = '-1';
            var autoLabel = document.createElement('span');
            autoLabel.className = 'aw-qi-label';
            autoLabel.textContent = 'Auto';
            var autoSublabel = document.createElement('span');
            autoSublabel.className = 'aw-qi-auto-res';
            autoSublabel.textContent = '';
            autoItem.appendChild(autoLabel);
            autoItem.appendChild(autoSublabel);
            settingsPanel.appendChild(autoItem);

            // Quality levels (highest first)
            var sortedLevels = levels.map(function(l, i) { return { index: i, height: l.height, bitrate: l.bitrate }; });
            sortedLevels.sort(function(a, b) { return b.height - a.height; });

            sortedLevels.forEach(function(lvl) {
                var item = document.createElement('div');
                item.className = 'aw-quality-item';
                item.dataset.level = String(lvl.index);
                var label = document.createElement('span');
                label.className = 'aw-qi-label';
                label.textContent = lvl.height + 'p';
                item.appendChild(label);
                settingsPanel.appendChild(item);
            });

            // Quality click handler
            settingsPanel.addEventListener('click', function(e) {
                var target = e.target.closest('.aw-quality-item');
                if (!target) return;
                var level = parseInt(target.dataset.level, 10);
                if (level === -1) {
                    hls.currentLevel = -1;     // unlock auto
                    hls.nextLevel = -1;
                } else {
                    hls.currentLevel = level;  // lock to level
                }
                settingsPanel.querySelectorAll('.aw-quality-item').forEach(function(el) {
                    el.classList.remove('active');
                });
                target.classList.add('active');
                settingsPanel.style.display = 'none';
            });

            // Auto bandwidth detection: update auto label with current resolution
            hls.on(Hls.Events.LEVEL_SWITCHED, function(_event, data) {
                var lvl = hls.levels[data.level];
                if (lvl && autoSublabel) {
                    autoSublabel.textContent = lvl.height + 'p';
                }
                // Highlight current level in menu
                settingsPanel.querySelectorAll('.aw-quality-item').forEach(function(el) {
                    el.classList.remove('aw-qi-current');
                });
                var currentItem = settingsPanel.querySelector('[data-level="' + data.level + '"]');
                if (currentItem) currentItem.classList.add('aw-qi-current');
            });

            settingsBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                settingsPanel.style.display = settingsPanel.style.display === 'none' ? '' : 'none';
            });

            rightGroup.appendChild(settingsBtn);
        }

        // Fullscreen button
        var fullscreenBtn = document.createElement('button');
        fullscreenBtn.className = 'aw-ctrl-btn aw-fullscreen-btn';
        fullscreenBtn.type = 'button';
        fullscreenBtn.title = 'Full screen';
        fullscreenBtn.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
        rightGroup.appendChild(fullscreenBtn);

        bottomBar.appendChild(leftGroup);
        bottomBar.appendChild(rightGroup);

        controls.appendChild(progressWrap);
        controls.appendChild(bottomBar);
        if (settingsPanel) controls.appendChild(settingsPanel);

        // Gradient overlay for controls
        var gradient = document.createElement('div');
        gradient.className = 'aw-controls-gradient';
        container.appendChild(gradient);
        container.appendChild(controls);

        // --- Big play button overlay (circle + triangle, no YouTube shape) ---
        var bigPlay = document.createElement('div');
        bigPlay.className = 'aw-big-play';
        bigPlay.innerHTML = '<svg viewBox="0 0 64 64" width="64" height="64"><circle class="aw-big-play-bg" cx="32" cy="32" r="30"/><path class="aw-big-play-icon" d="M26 18v28l22-14z"/></svg>';
        container.appendChild(bigPlay);

        // --- Touch zones for tap-to-skip and tap-to-play/pause ---
        var SKIP_SECONDS = 10;

        var touchLeft = document.createElement('div');
        touchLeft.className = 'aw-touch-zone aw-touch-zone-left';
        touchLeft.innerHTML = '<span class="aw-skip-indicator"><svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12.5 3C7.81 3 4.01 6.54 3.68 11H1l3.89 3.89.07.14L9 11H6.73C7.06 7.66 9.49 5 12.5 5c3.31 0 6 2.69 6 6s-2.69 6-6 6c-1.66 0-3.16-.67-4.24-1.76l-1.42 1.42A7.987 7.987 0 0 0 12.5 19c4.42 0 8-3.58 8-8s-3.58-8-8-8z"/></svg> ' + SKIP_SECONDS + 's</span>';
        container.appendChild(touchLeft);

        var touchCenter = document.createElement('div');
        touchCenter.className = 'aw-touch-zone aw-touch-zone-center';
        container.appendChild(touchCenter);

        var touchRight = document.createElement('div');
        touchRight.className = 'aw-touch-zone aw-touch-zone-right';
        touchRight.innerHTML = '<span class="aw-skip-indicator">' + SKIP_SECONDS + 's <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M11.5 3c4.69 0 8.49 3.54 8.82 8H23l-3.89 3.89-.07.14L15 11h2.27C16.94 7.66 14.51 5 11.5 5c-3.31 0-6 2.69-6 6s2.69 6 6 6c1.66 0 3.16-.67 4.24-1.76l1.42 1.42A7.987 7.987 0 0 1 11.5 19c-4.42 0-8-3.58-8-8s3.58-8 8-8z"/></svg></span>';
        container.appendChild(touchRight);

        var skipLeftIndicator = touchLeft.querySelector('.aw-skip-indicator');
        var skipRightIndicator = touchRight.querySelector('.aw-skip-indicator');
        var skipLeftTimer = null;
        var skipRightTimer = null;

        function flashSkip(indicator, timerRef) {
            indicator.classList.add('aw-skip-show');
            clearTimeout(timerRef);
            return setTimeout(function() { indicator.classList.remove('aw-skip-show'); }, 600);
        }

        touchLeft.addEventListener('click', function(e) {
            e.stopPropagation();
            video.currentTime = Math.max(0, video.currentTime - SKIP_SECONDS);
            skipLeftTimer = flashSkip(skipLeftIndicator, skipLeftTimer);
            showControls();
            scheduleHide();
        });

        touchRight.addEventListener('click', function(e) {
            e.stopPropagation();
            video.currentTime = Math.min(video.duration || 0, video.currentTime + SKIP_SECONDS);
            skipRightTimer = flashSkip(skipRightIndicator, skipRightTimer);
            showControls();
            scheduleHide();
        });

        touchCenter.addEventListener('click', function(e) {
            e.stopPropagation();
            if (video.paused) video.play().catch(function() {});
            else video.pause();
            showControls();
            scheduleHide();
        });

        // --- Event Wiring ---
        var hideTimeout = null;
        var isSeeking = false;

        function showControls() {
            controls.classList.add('aw-controls-visible');
            gradient.classList.add('aw-controls-visible');
            container.style.cursor = '';
        }

        function hideControls() {
            if (video.paused) return;
            controls.classList.remove('aw-controls-visible');
            gradient.classList.remove('aw-controls-visible');
            container.style.cursor = 'none';
            if (settingsPanel) settingsPanel.style.display = 'none';
        }

        function scheduleHide() {
            clearTimeout(hideTimeout);
            hideTimeout = setTimeout(hideControls, 3000);
        }

        function onMouseMove() {
            showControls();
            scheduleHide();
        }

        container.addEventListener('mousemove', onMouseMove);
        container.addEventListener('mouseleave', function() {
            if (!video.paused) {
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(hideControls, 800);
            }
        });
        container.addEventListener('touchstart', function(e) {
            // Let touch zones handle their own events
            if (e.target.closest('.aw-touch-zone') || e.target.closest('.aw-player-controls') || e.target.closest('.aw-big-play')) return;
            if (controls.classList.contains('aw-controls-visible')) {
                hideControls();
            } else {
                showControls();
                scheduleHide();
            }
        }, { passive: true });

        // Initial state – show controls
        showControls();

        // Play / Pause
        function updatePlayIcon() {
            if (video.paused) {
                playBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>';
                playBtn.title = 'Play';
                bigPlay.style.display = '';
                showControls();
                clearTimeout(hideTimeout);
            } else {
                playBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';
                playBtn.title = 'Pause';
                bigPlay.style.display = 'none';
                scheduleHide();
            }
        }

        playBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (video.paused) video.play().catch(function() {});
            else video.pause();
        });

        bigPlay.addEventListener('click', function(e) {
            e.stopPropagation();
            if (video.paused) video.play().catch(function() {});
            else video.pause();
        });

        // Click on video to toggle play/pause (avoid double-trigger from controls)
        video.addEventListener('click', function(e) {
            if (e.target === video) {
                if (video.paused) video.play().catch(function() {});
                else video.pause();
            }
        });

        video.addEventListener('play', updatePlayIcon);
        video.addEventListener('pause', updatePlayIcon);

        // Time & Progress
        video.addEventListener('timeupdate', function() {
            if (isSeeking) return;
            var dur = video.duration || 0;
            var cur = video.currentTime || 0;
            timeDisplay.textContent = _formatTime(cur) + ' / ' + _formatTime(dur);
            var pct = dur > 0 ? (cur / dur) * 100 : 0;
            progressPlayed.style.width = pct + '%';
            progressThumb.style.left = pct + '%';
        });

        video.addEventListener('loadedmetadata', function() {
            timeDisplay.textContent = '0:00 / ' + _formatTime(video.duration);
        });

        // Buffer progress
        video.addEventListener('progress', function() {
            if (video.buffered.length > 0) {
                var end = video.buffered.end(video.buffered.length - 1);
                var dur = video.duration || 1;
                progressBuffered.style.width = (end / dur) * 100 + '%';
            }
        });

        // Progress bar seeking
        function seekFromEvent(e) {
            var rect = progressBar.getBoundingClientRect();
            var pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            video.currentTime = pct * (video.duration || 0);
            progressPlayed.style.width = (pct * 100) + '%';
            progressThumb.style.left = (pct * 100) + '%';
        }

        progressWrap.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isSeeking = true;
            seekFromEvent(e);
            function onMove(ev) { seekFromEvent(ev); }
            function onUp() {
                isSeeking = false;
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });

        // Hover time tooltip
        progressWrap.addEventListener('mousemove', function(e) {
            var rect = progressBar.getBoundingClientRect();
            var pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            var time = pct * (video.duration || 0);
            progressHoverTime.textContent = _formatTime(time);
            progressHoverTime.style.left = (pct * 100) + '%';
            progressHoverTime.style.display = '';
        });
        progressWrap.addEventListener('mouseleave', function() {
            progressHoverTime.style.display = 'none';
        });

        // Volume
        function updateVolumeIcon() {
            var v = video.muted ? 0 : video.volume;
            if (v === 0) {
                volumeBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>';
            } else if (v < 0.5) {
                volumeBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>';
            } else {
                volumeBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>';
            }
        }

        volumeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            video.muted = !video.muted;
            volumeSlider.value = video.muted ? '0' : String(video.volume);
            updateVolumeIcon();
        });

        volumeSlider.addEventListener('input', function() {
            video.volume = parseFloat(this.value);
            video.muted = video.volume === 0;
            updateVolumeIcon();
        });

        video.addEventListener('volumechange', function() {
            volumeSlider.value = video.muted ? '0' : String(video.volume);
            updateVolumeIcon();
        });

        // Fullscreen
        fullscreenBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (document.fullscreenElement === container) {
                document.exitFullscreen().catch(function() {});
            } else {
                (container.requestFullscreen || container.webkitRequestFullscreen || container.msRequestFullscreen).call(container).catch(function() {});
            }
        });

        function updateFullscreenIcon() {
            if (document.fullscreenElement === container) {
                fullscreenBtn.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>';
                fullscreenBtn.title = 'Exit full screen';
            } else {
                fullscreenBtn.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
                fullscreenBtn.title = 'Full screen';
            }
        }

        document.addEventListener('fullscreenchange', updateFullscreenIcon);

        // Close settings on outside click
        var _closeHandler = function(e) {
            if (settingsPanel && settingsBtn && !settingsBtn.contains(e.target) && !settingsPanel.contains(e.target)) {
                settingsPanel.style.display = 'none';
            }
        };
        document.addEventListener('click', _closeHandler);

        // Keyboard shortcuts
        function onKeyDown(e) {
            if (!container.contains(document.activeElement) && document.activeElement !== document.body) return;
            var handled = true;
            switch (e.key) {
                case ' ':
                case 'k':
                    if (video.paused) video.play().catch(function() {});
                    else video.pause();
                    break;
                case 'ArrowLeft':
                    video.currentTime = Math.max(0, video.currentTime - 5);
                    break;
                case 'ArrowRight':
                    video.currentTime = Math.min(video.duration || 0, video.currentTime + 5);
                    break;
                case 'ArrowUp':
                    video.volume = Math.min(1, video.volume + 0.1);
                    break;
                case 'ArrowDown':
                    video.volume = Math.max(0, video.volume - 0.1);
                    break;
                case 'm':
                    video.muted = !video.muted;
                    break;
                case 'f':
                    fullscreenBtn.click();
                    break;
                default:
                    handled = false;
            }
            if (handled) {
                e.preventDefault();
                showControls();
                scheduleHide();
            }
        }
        document.addEventListener('keydown', onKeyDown);

        // Video ended state
        video.addEventListener('ended', function() {
            playBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg>';
            playBtn.title = 'Replay';
            bigPlay.style.display = '';
            showControls();
        });

        // Store cleanup reference
        controls._cleanup = function() {
            document.removeEventListener('click', _closeHandler);
            document.removeEventListener('fullscreenchange', updateFullscreenIcon);
            document.removeEventListener('keydown', onKeyDown);
            clearTimeout(hideTimeout);
            clearTimeout(skipLeftTimer);
            clearTimeout(skipRightTimer);
            if (gradient.parentElement) gradient.remove();
            if (bigPlay.parentElement) bigPlay.remove();
            if (touchLeft.parentElement) touchLeft.remove();
            if (touchCenter.parentElement) touchCenter.remove();
            if (touchRight.parentElement) touchRight.remove();
        };
    }

    // Expose globally
    window.awInitHlsPlayer = awInitHlsPlayer;
    window.awTryDashFallback = awTryDashFallback;
    window.awReportPlaybackError = _reportPlaybackError;
})();
