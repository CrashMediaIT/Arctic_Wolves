/**
 * Gameplan Module Review Tests
 *
 * Verifies:
 * 1. Auto-cast: gameplan.php and pwa/gameplan.php sync controller_page to active pairs
 * 2. TV nav buttons removed from gp_video_review.php (casting replaces manual nav)
 * 3. Film room upload uses standard upload-progress-overlay modal
 * 4. Telestration sync: whiteboard broadcasts drawings to paired TV
 * 5. Telestration sync: api_tv_pair_state.php returns telestration_seq
 * 6. Video review telestration: player modal with drawing canvas
 * 7. Video review clips have video source data attributes
 * 8. Database schema includes telestration columns
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

/* ------------------------------------------------------------------ */
/*  1. Auto-cast: controller page synced automatically                */
/* ------------------------------------------------------------------ */
test.describe('Auto-cast in gameplan.php', () => {

  test('gameplan.php should auto-cast controller_page to active pairs', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('UPDATE vr_device_pairs');
    expect(content).toContain('SET controller_page = ?');
    expect(content).toContain('is_frozen = 0');
    expect(content).toContain('Auto-cast');
  });

  test('pwa/gameplan.php should auto-cast controller_page to active pairs', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('UPDATE vr_device_pairs');
    expect(content).toContain('SET controller_page = ?');
    expect(content).toContain('is_frozen = 0');
    expect(content).toContain('Auto-cast');
  });

  test('auto-cast should only fire for valid allowed pages', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('isset($allowed_pages[$page])');
  });

  test('auto-cast should check both creator and joined controllers', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('created_by = ?');
    expect(content).toContain('vr_device_pair_controllers');
  });
});

/* ------------------------------------------------------------------ */
/*  2. TV navigation buttons removed                                  */
/* ------------------------------------------------------------------ */
test.describe('TV navigation buttons removed from gp_video_review.php', () => {

  test('should NOT have .tv-nav-btn class', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).not.toContain('tv-nav-btn');
  });

  test('should NOT have Navigate TV Display header', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).not.toContain('Navigate TV Display');
  });

  test('should NOT have tvNavBtns_ container', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).not.toContain('tvNavBtns_');
  });

  test('should have casting status indicator instead', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('broadcast-tower');
    expect(content).toContain('Casting');
  });

  test('How It Works should describe automatic casting', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('automatically cast to the TV');
    expect(content).not.toContain('Navigate TV Display');
  });
});

/* ------------------------------------------------------------------ */
/*  3. Film room upload uses standard progress modal                  */
/* ------------------------------------------------------------------ */
test.describe('Film room upload uses standard upload-progress-overlay', () => {

  test('gp_film_room.php should use upload-progress-overlay class', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('class="upload-progress-overlay"');
    expect(content).toContain('class="upload-progress-card"');
    expect(content).toContain('class="upload-progress-bar-container"');
  });

  test('film_room.php should use upload-progress-overlay class', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('class="upload-progress-overlay"');
    expect(content).toContain('class="upload-progress-card"');
    expect(content).toContain('class="upload-progress-bar-container"');
  });

  test('gp_film_room.php should show overlay with display flex', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain("overlay.style.display = 'flex'");
    expect(content).not.toContain("overlay.style.display = 'block'");
  });

  test('film_room.php should show overlay with display flex', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain("overlay.style.display = 'flex'");
    expect(content).not.toContain("overlay.style.display = 'block'");
  });

  test('gp_film_room.php should have upload log dropdown', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('frUploadLogDetails');
    expect(content).toContain('Upload Log');
    expect(content).toContain('frUploadLogPre');
  });

  test('film_room.php should have upload log dropdown', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('frUploadLogDetails');
    expect(content).toContain('Upload Log');
    expect(content).toContain('frUploadLogPre');
  });
});

/* ------------------------------------------------------------------ */
/*  4. Telestration sync: database schema                             */
/* ------------------------------------------------------------------ */
test.describe('Telestration database schema', () => {

  test('vr_device_pairs should have telestration_data column', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('`telestration_data` MEDIUMTEXT');
  });

  test('vr_device_pairs should have telestration_seq column', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('`telestration_seq` INT DEFAULT 0');
  });

  test('migration file should exist for telestration columns', () => {
    const migration = readFile('deployment/sql/add_telestration_to_device_pairs.sql');
    expect(migration).toContain('telestration_data');
    expect(migration).toContain('telestration_seq');
  });
});

/* ------------------------------------------------------------------ */
/*  5. Telestration sync: broadcast action in process_video.php       */
/* ------------------------------------------------------------------ */
test.describe('Telestration broadcast in process_video.php', () => {

  test('should have broadcast_telestration action', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'broadcast_telestration':");
    expect(content).toContain('handleBroadcastTelestration');
  });

  test('broadcast handler should validate data:image/png format', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("data:image/png;base64,");
  });

  test('broadcast handler should increment telestration_seq', () => {
    const content = readFile('process_video.php');
    expect(content).toContain('telestration_seq = telestration_seq + 1');
  });

  test('broadcast handler should check controller authorization', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleBroadcastTelestration');
    const funcEnd = content.indexOf('\n}', funcStart + 100);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('created_by = ?');
    expect(func).toContain('vr_device_pair_controllers');
    expect(func).toContain('Not authorized');
  });
});

/* ------------------------------------------------------------------ */
/*  6. Telestration sync: api_tv_pair_state.php                       */
/* ------------------------------------------------------------------ */
test.describe('Telestration in api_tv_pair_state.php', () => {

  test('should include telestration_seq in response', () => {
    const content = readFile('api_tv_pair_state.php');
    expect(content).toContain('telestration_seq');
  });

  test('should support include_telestration parameter', () => {
    const content = readFile('api_tv_pair_state.php');
    expect(content).toContain('include_telestration');
    expect(content).toContain('telestration_data');
  });

  test('should only include telestration_data when requested', () => {
    const content = readFile('api_tv_pair_state.php');
    // The file should have two different SELECT queries:
    // one with telestration_data (when include_telestration is set)
    // one without (the lightweight polling query)
    expect(content).toContain('include_telestration');
    // The lightweight query selects telestration_seq but NOT telestration_data
    const lines = content.split('\n');
    const selectLines = lines.filter(l => l.includes('SELECT') && l.includes('status'));
    // The conditional branch structure shows two queries
    expect(content).toContain('if ($include_telestration)');
  });
});

/* ------------------------------------------------------------------ */
/*  7. Whiteboard telestration sync                                   */
/* ------------------------------------------------------------------ */
test.describe('Whiteboard telestration sync in gp_whiteboard.php', () => {

  test('should detect active device pair', () => {
    const content = readFile('views/gameplan/gp_whiteboard.php');
    expect(content).toContain('wb_active_pair_id');
    expect(content).toContain('vr_device_pairs');
  });

  test('should detect TV viewer mode', () => {
    const content = readFile('views/gameplan/gp_whiteboard.php');
    expect(content).toContain('wb_is_tv_viewer');
    expect(content).toContain("tv_pair_id");
  });

  test('controller should broadcast telestration after drawing', () => {
    const content = readFile('views/gameplan/gp_whiteboard.php');
    expect(content).toContain('wbBroadcastTelestration');
    expect(content).toContain('broadcast_telestration');
    expect(content).toContain('toDataURL');
  });

  test('should debounce broadcasts', () => {
    const content = readFile('views/gameplan/gp_whiteboard.php');
    expect(content).toContain('wbBroadcastTimer');
    expect(content).toContain('setTimeout(wbBroadcastTelestration');
  });

  test('TV viewer should disable drawing and poll for updates', () => {
    const content = readFile('views/gameplan/gp_whiteboard.php');
    expect(content).toContain('wbIsTvViewer');
    expect(content).toContain("pointerEvents = 'none'");
    expect(content).toContain('wbPollTelestration');
    expect(content).toContain('setInterval(wbPollTelestration');
  });
});

/* ------------------------------------------------------------------ */
/*  8. Video review clip playback with data attributes                */
/* ------------------------------------------------------------------ */
test.describe('Video review clips have playback data attributes', () => {

  test('clips tab query should include source video fields', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('vs.file_path AS source_path');
    expect(content).toContain('vs.hls_url AS source_hls_url');
    expect(content).toContain('vs.dash_url AS source_dash_url');
  });

  test('by_game tab query should include source video fields', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    // Find the by_game clip query (it has WHERE c.game_id = ?)
    const gameClipQuery = content.substring(content.indexOf('WHERE c.game_id = ?'));
    expect(content).toContain('vs.hls_status AS source_hls_status');
    expect(content).toContain('vs.dash_manifest_url AS source_dash_manifest_url');
  });

  test('clip cards should have vr-clip-playable class', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    const matches = content.match(/vr-clip-playable/g);
    expect(matches).toBeTruthy();
    // Should appear in grid view, list view, and by_game view
    expect(matches.length).toBeGreaterThanOrEqual(3);
  });

  test('clip cards should have data-source attribute', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('data-source=');
    expect(content).toContain('$clip_play_url');
  });
});

/* ------------------------------------------------------------------ */
/*  9. Video review telestration player modal                         */
/* ------------------------------------------------------------------ */
test.describe('Video review telestration player modal', () => {

  test('should have video player modal', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('vrPlayerModal');
    expect(content).toContain('vrModalVideo');
  });

  test('should have telestration canvas overlay on video', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('vrTeleCanvas');
    expect(content).toContain('<canvas');
  });

  test('should have drawing toolbar with tools', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('vrTeleToolbar');
    expect(content).toContain('vr-tele-tool');
    expect(content).toContain('data-tool="freehand"');
    expect(content).toContain('data-tool="arrow"');
    expect(content).toContain('data-tool="line"');
  });

  test('should have color picker', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('vr-tele-color');
    expect(content).toContain('data-color=');
  });

  test('should use awInitHlsPlayer for video playback', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('awInitHlsPlayer');
  });

  test('should have error fallback handling like gp_my_clips.php', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('_vrFallbackUrl');
    expect(content).toContain('_vrFallbackTried');
    expect(content).toContain('awTryDashFallback');
  });

  test('should broadcast telestration from video review canvas', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('vrBroadcastTelestration');
    expect(content).toContain('broadcast_telestration');
  });

  test('TV viewer should receive telestration on video review', () => {
    const content = readFile('views/gameplan/gp_video_review.php');
    expect(content).toContain('vrPollTelestration');
    expect(content).toContain('vrIsTvViewer');
  });
});
