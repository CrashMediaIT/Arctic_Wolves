/**
 * Arctic Wolves HLS Video Player
 * Application-wide video player with HLS.js support for adaptive quality streaming.
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
                _buildQualityMenu(video, hls, data.levels);
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
        return null;
    }

    /**
     * Build a quality selection menu overlaid on the video container.
     */
    function _buildQualityMenu(video, hls, levels) {
        if (!levels || levels.length < 2) return;

        var container = video.parentElement;
        if (!container) return;

        // Remove existing menu if any
        var existing = container.querySelector('.aw-quality-wrapper');
        if (existing) {
            if (existing._closeHandler) {
                document.removeEventListener('click', existing._closeHandler);
            }
            existing.remove();
        }

        // Ensure container is positioned
        var pos = getComputedStyle(container).position;
        if (pos === 'static') container.style.position = 'relative';

        // Quality button
        var btn = document.createElement('button');
        btn.className = 'aw-quality-btn';
        btn.innerHTML = '<i class="fas fa-cog"></i>';
        btn.title = 'Video Quality';
        btn.type = 'button';

        // Dropdown
        var menu = document.createElement('div');
        menu.className = 'aw-quality-menu';
        menu.style.display = 'none';

        // Auto option
        var autoItem = document.createElement('div');
        autoItem.className = 'aw-quality-item active';
        autoItem.textContent = 'Auto';
        autoItem.dataset.level = '-1';
        menu.appendChild(autoItem);

        // Quality levels (highest first)
        var sortedLevels = levels.map(function(l, i) { return { index: i, height: l.height, bitrate: l.bitrate }; });
        sortedLevels.sort(function(a, b) { return b.height - a.height; });

        sortedLevels.forEach(function(lvl) {
            var item = document.createElement('div');
            item.className = 'aw-quality-item';
            item.textContent = lvl.height + 'p';
            item.dataset.level = lvl.index;
            menu.appendChild(item);
        });

        // Click handler
        menu.addEventListener('click', function(e) {
            var target = e.target.closest('.aw-quality-item');
            if (!target) return;
            var level = parseInt(target.dataset.level, 10);
            hls.currentLevel = level;
            menu.querySelectorAll('.aw-quality-item').forEach(function(el) {
                el.classList.remove('active');
            });
            target.classList.add('active');
            menu.style.display = 'none';
        });

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        });

        // Close on outside click — use a single bound handler to avoid leaks
        var _closeHandler = function() { menu.style.display = 'none'; };
        document.addEventListener('click', _closeHandler);

        var wrapper = document.createElement('div');
        wrapper.className = 'aw-quality-wrapper';

        // Store cleanup reference on the wrapper so it can be removed
        wrapper._closeHandler = _closeHandler;

        wrapper.appendChild(btn);
        wrapper.appendChild(menu);
        container.appendChild(wrapper);
    }

    // Expose globally
    window.awInitHlsPlayer = awInitHlsPlayer;
})();
