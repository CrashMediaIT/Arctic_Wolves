const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

/* ------------------------------------------------------------------ */
/*  video_review_detail.php – uses getPreferredVideoUrl (not raw)     */
/* ------------------------------------------------------------------ */
test.describe('video_review_detail.php uses getPreferredVideoUrl for playback', () => {

  test('desktop: resolves video URL via getPreferredVideoUrl, not raw video_url', () => {
    const c = readFile('views/video_review_detail.php');
    // Must call getPreferredVideoUrl
    expect(c).toContain('getPreferredVideoUrl($video)');
    // Must NOT use raw $video['video_url'] for the playback URL variable
    expect(c).not.toMatch(/\$video_url\s*=\s*resolveRustfsUrl\(\$pdo,\s*\$video\['video_url'\]/);
  });

  test('PWA: resolves video URL via getPreferredVideoUrl, not raw video_url', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('getPreferredVideoUrl($video)');
    expect(c).not.toMatch(/\$video_url\s*=\s*resolveRustfsUrl\(\$pdo,\s*\$video\['video_url'\]/);
  });

  test('desktop: wraps getPreferredVideoUrl in resolveRustfsUrl', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toMatch(/resolveRustfsUrl\(\$pdo,\s*getPreferredVideoUrl\(\$video\)\)/);
  });

  test('PWA: wraps getPreferredVideoUrl in resolveRustfsUrl', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toMatch(/resolveRustfsUrl\(\$pdo,\s*getPreferredVideoUrl\(\$video\)\)/);
  });
});

/* ------------------------------------------------------------------ */
/*  All views using videos table must use getPreferredVideoUrl        */
/* ------------------------------------------------------------------ */
test.describe('All video playback views use getPreferredVideoUrl', () => {

  const videoPlaybackViews = [
    'views/video_review_detail.php',
    'views/pwa/video_review_detail.php',
    'views/video_coach_reviews.php',
    'views/video_drill_review.php',
  ];

  for (const viewFile of videoPlaybackViews) {
    test(`${viewFile} uses getPreferredVideoUrl`, () => {
      const c = readFile(viewFile);
      expect(c).toContain('getPreferredVideoUrl');
    });
  }

  test('no view file uses raw $video[video_url] for playback URL assignment', () => {
    for (const viewFile of videoPlaybackViews) {
      const c = readFile(viewFile);
      // Should NOT have: $video_url = resolveRustfsUrl($pdo, $video['video_url']...)
      const badPattern = /\$video_url\s*=\s*resolveRustfsUrl\(\$pdo,\s*\$video\['video_url'\]/;
      expect(c).not.toMatch(badPattern);
    }
  });
});

/* ------------------------------------------------------------------ */
/*  HLS player initialization on detail pages                         */
/* ------------------------------------------------------------------ */
test.describe('Detail pages initialize HLS player', () => {

  test('desktop video_review_detail.php calls awInitHlsPlayer', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('awInitHlsPlayer');
    expect(c).toContain("document.getElementById('detailVideoPlayer')");
  });

  test('PWA video_review_detail.php calls awInitHlsPlayer', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('awInitHlsPlayer');
    expect(c).toContain("document.getElementById('detailVideoPlayer')");
  });
});

/* ------------------------------------------------------------------ */
/*  getPreferredVideoUrl function correctness                         */
/* ------------------------------------------------------------------ */
test.describe('getPreferredVideoUrl returns HLS URL when ready', () => {

  test('returns hls_url when hls_status is ready', () => {
    const c = readFile('lib/image_helper.php');
    // The function should check hls_status and return hls_url only when 'ready'
    expect(c).toMatch(/function getPreferredVideoUrl/);
    expect(c).toContain("'ready'");
    expect(c).toContain('hls_url');
    expect(c).toContain('video_url');
  });

  test('falls back to video_url when hls_status is not ready', () => {
    const c = readFile('lib/image_helper.php');
    const funcMatch = c.match(/function getPreferredVideoUrl[\s\S]*?^}/m);
    expect(funcMatch).not.toBeNull();
    // Should return video_url as fallback
    expect(funcMatch[0]).toContain("video_url");
  });

  test('reconstructs HLS URL from hls_master_url when hls_url is empty', () => {
    const c = readFile('lib/image_helper.php');
    const funcMatch = c.match(/function getPreferredVideoUrl[\s\S]*?^}/m);
    expect(funcMatch).not.toBeNull();
    const func = funcMatch[0];
    // When hls_status is 'ready' but hls_url is empty, should construct from hls_master_url
    expect(func).toContain('hls_master_url');
    expect(func).toContain('rawurlencode');
    expect(func).toContain("api/media.php?key=");
  });
});

/* ------------------------------------------------------------------ */
/*  Callback handler preserves hls_url                                */
/* ------------------------------------------------------------------ */
test.describe('Callback handler preserves hls_url when manifest is empty', () => {

  test('callback handler has conditional hls_url update', () => {
    const c = readFile('api/v1/companion.php');
    // When hls_url is empty, should NOT overwrite existing value
    expect(c).toContain("Preserve existing hls_url");
  });

  test('callback handler still updates hls_url when manifest is provided', () => {
    const c = readFile('api/v1/companion.php');
    // Normal path: update hls_url when manifest is present
    expect(c).toContain("hls_url           = :hls_url");
  });
});

/* ------------------------------------------------------------------ */
/*  Video container aspect-ratio on detail pages                      */
/* ------------------------------------------------------------------ */
test.describe('Detail page video containers use 16:9 aspect-ratio', () => {

  test('desktop video_review_detail.php has aspect-ratio 16/9', () => {
    const c = readFile('views/video_review_detail.php');
    // aspect-ratio is on the container div wrapping the video player
    const lines = c.split('\n');
    const containerLine = lines.find(l => l.includes('detailVideoPlayer') || (l.includes('aspect-ratio') && l.includes('16 / 9') && l.includes('Video Player')));
    // Check that the container div near detailVideoPlayer has aspect-ratio
    const playerSection = c.match(/<!-- Video Player -->[\s\S]{0,500}detailVideoPlayer/);
    expect(playerSection).not.toBeNull();
    expect(playerSection[0]).toMatch(/aspect-ratio:\s*16\s*\/\s*9/);
  });

  test('desktop video_review_detail.php video uses object-fit contain', () => {
    const c = readFile('views/video_review_detail.php');
    // The video element style should contain object-fit: contain
    const playerSection = c.match(/detailVideoPlayer[\s\S]{0,300}object-fit:\s*contain/);
    expect(playerSection).not.toBeNull();
  });

  test('PWA video_review_detail.php has aspect-ratio 16/9 on player container', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toMatch(/\.m-vrd-player\s*\{[^}]*aspect-ratio:\s*16\s*\/\s*9/s);
  });

  test('PWA video_review_detail.php player video uses object-fit contain', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toMatch(/\.m-vrd-player\s+video\s*\{[^}]*object-fit:\s*contain/s);
  });
});

/* ------------------------------------------------------------------ */
/*  Poster/thumbnail on detail pages                                  */
/* ------------------------------------------------------------------ */
test.describe('Detail pages show poster thumbnails', () => {

  test('desktop video_review_detail.php resolves thumbnail_url', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toMatch(/\$thumbnail_url\s*=\s*resolveRustfsUrl/);
  });

  test('desktop video_review_detail.php sets poster attribute conditionally', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('poster=');
    expect(c).toContain('$thumbnail_url');
  });

  test('PWA video_review_detail.php resolves thumbnail_url', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toMatch(/\$thumbnail_url\s*=\s*resolveRustfsUrl/);
  });

  test('PWA video_review_detail.php sets poster attribute conditionally', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('poster=');
    expect(c).toContain('$thumbnail_url');
  });
});

/* ------------------------------------------------------------------ */
/*  HLS.js error handler dispatches native error for fallback         */
/* ------------------------------------------------------------------ */
test.describe('HLS player error handling dispatches error for view fallback', () => {

  test('default fatal error dispatches native error event', () => {
    const c = readFile('js/hls-player.js');
    // The default case in the error handler should dispatch error event
    // instead of trying video.src = url (which fails silently on Chrome for m3u8)
    const errorSection = c.match(/default:[\s\S]*?break;/);
    expect(errorSection).not.toBeNull();
    expect(errorSection[0]).toContain("dispatchEvent(new Event('error'))");
    // Should NOT try to set video.src for direct playback of m3u8
    expect(errorSection[0]).not.toContain('video.src = url');
  });

  test('network error also dispatches native error after retries exhausted', () => {
    const c = readFile('js/hls-player.js');
    // Network error handler should also dispatch error after max retries
    const networkSection = c.match(/NETWORK_ERROR[\s\S]*?break;/);
    expect(networkSection).not.toBeNull();
    expect(networkSection[0]).toContain("dispatchEvent(new Event('error'))");
  });
});

/* ------------------------------------------------------------------ */
/*  Video playback errors reported to server error log                 */
/* ------------------------------------------------------------------ */
test.describe('Video playback errors are reported to the server', () => {

  test('hls-player.js has _reportPlaybackError helper', () => {
    const c = readFile('js/hls-player.js');
    expect(c).toContain('function _reportPlaybackError');
    expect(c).toContain('process_log_client_error.php');
    expect(c).toContain('csrf-token');
  });

  test('hls-player.js reports HLS network errors', () => {
    const c = readFile('js/hls-player.js');
    const networkSection = c.match(/NETWORK_ERROR[\s\S]*?break;/);
    expect(networkSection).not.toBeNull();
    expect(networkSection[0]).toContain('_reportPlaybackError');
  });

  test('hls-player.js reports HLS media errors', () => {
    const c = readFile('js/hls-player.js');
    const mediaSection = c.match(/MEDIA_ERROR[\s\S]*?break;/);
    expect(mediaSection).not.toBeNull();
    expect(mediaSection[0]).toContain('_reportPlaybackError');
  });

  test('hls-player.js reports HLS default/fatal errors', () => {
    const c = readFile('js/hls-player.js');
    const defaultSection = c.match(/default:[\s\S]*?break;/);
    expect(defaultSection).not.toBeNull();
    expect(defaultSection[0]).toContain('_reportPlaybackError');
  });

  test('hls-player.js exposes awReportPlaybackError globally', () => {
    const c = readFile('js/hls-player.js');
    expect(c).toContain('window.awReportPlaybackError');
  });

  test('video_review_detail.php (desktop) reports errors on fallback', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('awReportPlaybackError');
  });

  test('video_review_detail.php (PWA) reports errors on fallback', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('awReportPlaybackError');
  });

  test('video_coach_reviews.php reports errors on fallback', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('awReportPlaybackError');
  });

  test('video_drill_review.php reports errors on fallback', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain('awReportPlaybackError');
  });

  test('process_log_client_error.php exists and uses ErrorLogger', () => {
    const c = readFile('process_log_client_error.php');
    expect(c).toContain('ErrorLogger');
    expect(c).toContain('checkCsrfToken');
    expect(c).toContain("$_SESSION['logged_in']");
  });

  test('entry points have csrf-token meta tag', () => {
    for (const file of ['dashboard.php', 'pwa.php', 'pwa_tablet.php', 'gameplan.php']) {
      const c = readFile(file);
      expect(c).toContain('name="csrf-token"');
    }
  });
});
