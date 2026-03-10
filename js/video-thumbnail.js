/**
 * Video Thumbnail Extractor
 * Captures a frame from a video file using HTML5 canvas and returns it as base64 JPEG.
 * Used for generating thumbnails before uploading videos via presigned URLs.
 */
(function(window) {
    'use strict';

    /**
     * Extract a thumbnail from a video File object.
     * @param {File} videoFile - The video file to extract from
     * @param {number} seekTime - Seconds into the video to capture (default 1.0)
     * @param {number} maxWidth - Maximum thumbnail width (default 640)
     * @returns {Promise<string|null>} Base64-encoded JPEG data (no data: prefix), or null on failure
     */
    function extractVideoThumbnail(videoFile, seekTime, maxWidth) {
        seekTime = seekTime || 1.0;
        maxWidth = maxWidth || 640;

        return new Promise(function(resolve) {
            if (!videoFile || !videoFile.type || !videoFile.type.startsWith('video/')) {
                resolve(null);
                return;
            }

            var video = document.createElement('video');
            video.preload = 'metadata';
            video.muted = true;
            video.playsInline = true;

            var objectUrl = URL.createObjectURL(videoFile);
            var resolved = false;

            // Timeout after 15 seconds
            var timeout = setTimeout(function() {
                if (!resolved) {
                    resolved = true;
                    cleanup();
                    resolve(null);
                }
            }, 15000);

            function cleanup() {
                clearTimeout(timeout);
                URL.revokeObjectURL(objectUrl);
                video.removeAttribute('src');
                video.load();
            }

            video.addEventListener('error', function() {
                if (!resolved) {
                    resolved = true;
                    cleanup();
                    resolve(null);
                }
            });

            video.addEventListener('loadedmetadata', function() {
                // Ensure seekTime is within video duration
                var actualSeek = Math.min(seekTime, video.duration * 0.5, video.duration - 0.1);
                if (actualSeek < 0) actualSeek = 0;
                video.currentTime = actualSeek;
            });

            video.addEventListener('seeked', function() {
                if (resolved) return;
                resolved = true;

                try {
                    var canvas = document.createElement('canvas');
                    var w = video.videoWidth;
                    var h = video.videoHeight;

                    if (w > maxWidth) {
                        h = Math.round(h * (maxWidth / w));
                        w = maxWidth;
                    }

                    canvas.width = w;
                    canvas.height = h;
                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, w, h);

                    // Get base64 without the data:image/jpeg;base64, prefix
                    var dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                    var base64 = dataUrl.split(',')[1] || null;

                    cleanup();
                    resolve(base64);
                } catch (e) {
                    cleanup();
                    resolve(null);
                }
            });

            video.src = objectUrl;
        });
    }

    // Export globally
    window.extractVideoThumbnail = extractVideoThumbnail;

})(window);
