/**
 * Arctic Wolves HLS Video Player
 * Application-wide video player with HLS.js support for adaptive quality streaming.
 * YouTube-style custom controls with resolution picker and auto bandwidth detection.
 *
 * Usage:
 *   var hls = window.awInitHlsPlayer(videoElement, videoUrl);
 *   // To destroy: if (hls) hls.destroy();
 *
 * Automatically detects HLS (.m3u8) URLs and uses HLS.js for playback with
 * quality selection. Falls back to native video for non-HLS sources or
 * Safari's native HLS support.
 *
 * Requires: HLS.js loaded before this script (CDN or local).
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
     * Initialise a video element for playback.
     * @param {HTMLVideoElement} video  The <video> element.
     * @param {string}           url    Video URL (.m3u8 for HLS, or direct file).
     * @returns {Hls|null}  The HLS.js instance (if used), or null.
     */
    function awInitHlsPlayer(video, url) {
        if (!video || !url) return null;

        var isHLS = /\.m3u8(\?|$)/i.test(url);

        // HLS source and HLS.js is available
        if (isHLS && typeof Hls !== 'undefined' && Hls.isSupported()) {
            var hls = new Hls({
                maxBufferLength: 30,
                maxMaxBufferLength: 60,
                startLevel: -1, // Auto quality selection
            });

            hls.loadSource(url);
            hls.attachMedia(video);

            hls.on(Hls.Events.MANIFEST_PARSED, function(_event, data) {
                video.play().catch(function() {});
                _buildCustomControls(video, hls, data.levels);
            });

            hls.on(Hls.Events.ERROR, function(_event, data) {
                if (data.fatal) {
                    switch (data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            hls.startLoad();
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            hls.recoverMediaError();
                            break;
                        default:
                            hls.destroy();
                            // Fallback to direct play
                            video.src = url;
                            video.load();
                            break;
                    }
                }
            });

            return hls;
        }

        // Safari native HLS or non-HLS source
        if (isHLS && video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = url;
            video.addEventListener('loadedmetadata', function() {
                video.play().catch(function() {});
            }, { once: true });
            _buildCustomControls(video, null, null);
            return null;
        }

        // Direct video file (MP4/WebM/etc.)
        var source = video.querySelector('source');
        if (source) {
            source.src = url;
            video.load();
        } else {
            video.src = url;
        }
        video.play().catch(function() {});
        _buildCustomControls(video, null, null);
        return null;
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

        // --- Big play button overlay ---
        var bigPlay = document.createElement('div');
        bigPlay.className = 'aw-big-play';
        bigPlay.innerHTML = '<svg viewBox="0 0 68 48" width="68" height="48"><path class="aw-big-play-bg" d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55C3.97 2.33 2.27 4.81 1.48 7.74.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z"/><path class="aw-big-play-icon" d="M45 24L27 14v20z"/></svg>';
        container.appendChild(bigPlay);

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
        container.addEventListener('touchstart', function() {
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
            if (gradient.parentElement) gradient.remove();
            if (bigPlay.parentElement) bigPlay.remove();
        };
    }

    // Expose globally
    window.awInitHlsPlayer = awInitHlsPlayer;
})();
