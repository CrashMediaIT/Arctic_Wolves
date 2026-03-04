const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

/* ------------------------------------------------------------------ */
/*  1. Video container 16:9 aspect-ratio                              */
/* ------------------------------------------------------------------ */
test.describe('Video player container 16:9 aspect-ratio', () => {

  test('video_drill_review.php .video-player-container has aspect-ratio 16/9', () => {
    const c = readFile('views/video_drill_review.php');
    const match = c.match(/\.video-player-container\s*\{[^}]*aspect-ratio:\s*16\s*\/\s*9/s);
    expect(match).not.toBeNull();
  });

  test('video_drill_review.php .video-player uses object-fit contain', () => {
    const c = readFile('views/video_drill_review.php');
    const match = c.match(/\.video-player\s*\{[^}]*object-fit:\s*contain/s);
    expect(match).not.toBeNull();
  });

  test('video_drill_review.php .video-player uses height 100%', () => {
    const c = readFile('views/video_drill_review.php');
    const match = c.match(/\.video-player\s*\{[^}]*height:\s*100%/s);
    expect(match).not.toBeNull();
  });

  test('video_coach_reviews.php video container has aspect-ratio 16/9', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('aspect-ratio: 16 / 9');
    // Verify it's on the video player container div
    const containerMatch = c.match(/coachVideoPlayer[\s\S]{0,200}aspect-ratio/);
    const aspectMatch = c.match(/aspect-ratio:\s*16\s*\/\s*9[\s\S]{0,200}coachVideoPlayer/);
    expect(containerMatch || aspectMatch).not.toBeNull();
  });

  test('video_coach_reviews.php video uses object-fit contain', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('object-fit: contain');
  });

  test('gameplan/film_room.php vrVideoPlayer has aspect-ratio 16/9', () => {
    const c = readFile('views/gameplan/film_room.php');
    expect(c).toMatch(/vrVideoPlayer[^>]*aspect-ratio:\s*16\s*\/\s*9/);
  });

  test('gameplan/gp_film_room.php vrVideoPlayer has aspect-ratio 16/9', () => {
    const c = readFile('views/gameplan/gp_film_room.php');
    expect(c).toMatch(/vrVideoPlayer[^>]*aspect-ratio:\s*16\s*\/\s*9/);
  });

  test('gameplan film room videos use object-fit contain', () => {
    const fr = readFile('views/gameplan/film_room.php');
    const gpfr = readFile('views/gameplan/gp_film_room.php');
    expect(fr).toMatch(/vrVideoPlayer[^>]*object-fit:\s*contain/);
    expect(gpfr).toMatch(/vrVideoPlayer[^>]*object-fit:\s*contain/);
  });
});

/* ------------------------------------------------------------------ */
/*  2. hls-player.js aspect-ratio enforcement                         */
/* ------------------------------------------------------------------ */
test.describe('hls-player.js container aspect-ratio enforcement', () => {

  test('_buildCustomControls sets aspectRatio 16/9 when auto', () => {
    const c = readFile('js/hls-player.js');
    expect(c).toContain("container.style.aspectRatio = '16 / 9'");
  });

  test('_buildCustomControls checks existing aspectRatio before setting', () => {
    const c = readFile('js/hls-player.js');
    expect(c).toContain('getComputedStyle(container).aspectRatio');
  });
});

/* ------------------------------------------------------------------ */
/*  3. Poster / thumbnail support                                     */
/* ------------------------------------------------------------------ */
test.describe('Video poster / thumbnail support', () => {

  test('video_drill_review.php play buttons have data-thumbnail-url', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toMatch(/data-action="play-video"[\s\S]*?data-thumbnail-url/);
  });

  test('video_drill_review.php JS reads thumbnailUrl from dataset', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain('this.dataset.thumbnailUrl');
  });

  test('video_drill_review.php JS sets videoPlayer.poster', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain('videoPlayer.poster = thumbnailUrl');
  });

  test('video_coach_reviews.php pending play buttons have data-thumbnail-url', () => {
    const c = readFile('views/video_coach_reviews.php');
    // Both pending and reviewed buttons should have it
    const lines = c.split('\n').filter(l => l.includes('data-action="view-video"') && l.includes('data-thumbnail-url'));
    expect(lines.length).toBeGreaterThanOrEqual(2);
  });

  test('video_coach_reviews.php JS reads thumbnailUrl and sets poster', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('this.dataset.thumbnailUrl');
    expect(c).toContain('vpVideo.poster = thumbnailUrl');
  });

  test('poster is cleared on modal close in video_drill_review.php', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain("videoPlayer.removeAttribute('poster')");
  });

  test('poster is cleared on modal close in video_coach_reviews.php', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain("vpVideo.removeAttribute('poster')");
  });
});

/* ------------------------------------------------------------------ */
/*  4. Controls cleanup on modal close                                */
/* ------------------------------------------------------------------ */
test.describe('Custom controls cleanup on modal close', () => {

  test('video_drill_review.php has cleanupVideoPlayer function', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain('function cleanupVideoPlayer()');
  });

  test('video_drill_review.php cleanup removes aw-player-controls', () => {
    const c = readFile('views/video_drill_review.php');
    const funcMatch = c.match(/function cleanupVideoPlayer\(\)[\s\S]*?(?=function\s|$)/);
    expect(funcMatch).not.toBeNull();
    expect(funcMatch[0]).toContain('.aw-player-controls');
  });

  test('video_drill_review.php cleanup removes gradient and big play', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain('.aw-controls-gradient');
    expect(c).toContain('.aw-big-play');
  });

  test('video_drill_review.php cleanup removes touch zones', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain('.aw-touch-zone-left');
    expect(c).toContain('.aw-touch-zone-right');
  });

  test('video_drill_review.php cleanup restores native controls', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain("videoPlayer.setAttribute('controls', '')");
  });

  test('video_drill_review.php close modal uses cleanupVideoPlayer', () => {
    const c = readFile('views/video_drill_review.php');
    // Both close-modal and escape should call cleanup
    const closeMatches = c.match(/cleanupVideoPlayer\(\)/g);
    expect(closeMatches).not.toBeNull();
    expect(closeMatches.length).toBeGreaterThanOrEqual(3); // open cleanup + close + escape
  });

  test('video_coach_reviews.php has cleanupCoachVideoPlayer function', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('function cleanupCoachVideoPlayer()');
  });

  test('video_coach_reviews.php cleanup removes custom controls', () => {
    const c = readFile('views/video_coach_reviews.php');
    const funcMatch = c.match(/function cleanupCoachVideoPlayer\(\)[\s\S]*?(?=function\s|$)/);
    expect(funcMatch).not.toBeNull();
    expect(funcMatch[0]).toContain('.aw-player-controls');
    expect(funcMatch[0]).toContain('.aw-controls-gradient');
    expect(funcMatch[0]).toContain('.aw-big-play');
  });

  test('video_coach_reviews.php cleanup restores native controls', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain("vpVideo.setAttribute('controls', '')");
  });

  test('video_coach_reviews.php close and escape use cleanup function', () => {
    const c = readFile('views/video_coach_reviews.php');
    const cleanupCalls = c.match(/cleanupCoachVideoPlayer\(\)/g);
    expect(cleanupCalls).not.toBeNull();
    expect(cleanupCalls.length).toBeGreaterThanOrEqual(3);
  });
});

/* ------------------------------------------------------------------ */
/*  5. HLS error fallback rebuilds controls                           */
/* ------------------------------------------------------------------ */
test.describe('HLS error fallback rebuilds controls', () => {

  test('hls-player.js default error case calls _buildCustomControls', () => {
    const c = readFile('js/hls-player.js');
    // Find the error handler section
    const errorSection = c.match(/default:\s*\n\s*hls\.destroy\(\);[\s\S]*?break;/);
    expect(errorSection).not.toBeNull();
    expect(errorSection[0]).toContain('_buildCustomControls(video, null, null)');
  });

  test('hls-player.js error fallback still sets video.src and calls load', () => {
    const c = readFile('js/hls-player.js');
    const errorSection = c.match(/default:\s*\n\s*hls\.destroy\(\);[\s\S]*?break;/);
    expect(errorSection).not.toBeNull();
    expect(errorSection[0]).toContain('video.src = url');
    expect(errorSection[0]).toContain('video.load()');
  });
});

/* ------------------------------------------------------------------ */
/*  6. No max-height constraint on player (replaced by aspect-ratio)  */
/* ------------------------------------------------------------------ */
test.describe('Video player sizing uses aspect-ratio not max-height', () => {

  test('video_drill_review.php .video-player does not use max-height', () => {
    const c = readFile('views/video_drill_review.php');
    const playerRule = c.match(/\.video-player\s*\{[^}]*\}/s);
    expect(playerRule).not.toBeNull();
    expect(playerRule[0]).not.toContain('max-height');
  });

  test('video_coach_reviews.php coachVideoPlayer does not use max-height', () => {
    const c = readFile('views/video_coach_reviews.php');
    // Find the video element style
    const videoMatch = c.match(/id="coachVideoPlayer"[^>]*style="([^"]*)"/);
    expect(videoMatch).not.toBeNull();
    expect(videoMatch[1]).not.toContain('max-height');
  });

  test('gameplan film room videos do not use max-height', () => {
    const fr = readFile('views/gameplan/film_room.php');
    const gpfr = readFile('views/gameplan/gp_film_room.php');
    const frStyle = fr.match(/id="vrVideoPlayer"[^>]*style="([^"]*)"/);
    const gpfrStyle = gpfr.match(/id="vrVideoPlayer"[^>]*style="([^"]*)"/);
    expect(frStyle).not.toBeNull();
    expect(gpfrStyle).not.toBeNull();
    expect(frStyle[1]).not.toContain('max-height');
    expect(gpfrStyle[1]).not.toContain('max-height');
  });
});
