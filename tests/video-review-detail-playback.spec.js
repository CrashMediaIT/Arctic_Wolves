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
    // The function should check hls_status === 'ready' and return hls_url
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
