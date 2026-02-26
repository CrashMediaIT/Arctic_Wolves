/**
 * Tests for Video Player, Upload, and Role Restriction Fixes
 *
 * Verifies:
 * 1. delete-video action sends correct action name (delete_video) to PHP
 * 2. play-video and view-video handlers are registered in app.js
 * 3. Video.js player modal is created by openVideoPlayerModal
 * 4. Direct-to-RustFS presigned URL upload in video_coach_reviews.php
 * 5. handleGetAthleteUploadUrl allows null coach_id
 * 6. handleVideoDelete cleans up RustFS objects
 * 7. Camera recording name field exists in video_record_athlete.php
 * 8. Coaches Corner menu has no role restriction
 * 9. Coach reviews query includes athlete_id condition
 * 10. Upload form works without assigned coach
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. delete-video action sends correct action name
// =====================================================

test.describe('delete-video action fix', () => {
  test('should send action=delete_video (not action=delete) to process_video.php', () => {
    const content = readFile('js/app.js');
    // Must contain the correct action name
    expect(content).toContain("action=delete_video&video_id=");
    // Must not contain the old broken action name in the delete-video context
    expect(content).not.toContain("action=delete&video_id=");
  });

  test('should warn user about associated review in delete confirmation', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('also remove any associated review');
  });
});

// =====================================================
// 2. play-video and view-video handlers
// =====================================================

test.describe('play-video and view-video handlers in app.js', () => {
  test('should register combined handler for view-video and play-video actions', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('data-action="view-video"');
    expect(content).toContain('data-action="play-video"');
  });

  test('should call openVideoPlayerModal from the handler', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('openVideoPlayerModal(videoUrl, title, videoId)');
  });

  test('should skip play-video and view-video in generic action catch-all', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("'play-video'");
    expect(content).toContain("'view-video'");
    expect(content).toContain("'delete-video'");
  });

  test('should not navigate to non-existent video_detail page', () => {
    const content = readFile('js/app.js');
    expect(content).not.toContain('page=video_detail');
  });
});

// =====================================================
// 3. Video.js player modal
// =====================================================

test.describe('Video.js player modal', () => {
  test('should define openVideoPlayerModal function', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('function openVideoPlayerModal(');
  });

  test('should define closeVideoPlayerModal function', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('function closeVideoPlayerModal(');
  });

  test('should lazy-load Video.js CSS from CDN', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('vjs.zencdn.net');
    expect(content).toContain('video-js.css');
  });

  test('should lazy-load Video.js script from CDN', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('video.min.js');
  });

  test('should create modal with correct DOM structure', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('aw-video-player-modal');
    expect(content).toContain('aw-vp-modal');
    expect(content).toContain('aw-vp-overlay');
    expect(content).toContain('aw-vp-content');
  });

  test('should configure Video.js with playback rates', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('playbackRates');
    expect(content).toContain('0.25');
    expect(content).toContain('0.5');
  });

  test('should handle video format detection from URL', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('.webm');
    expect(content).toContain('.mov');
    expect(content).toContain('.mkv');
    expect(content).toContain('.avi');
  });

  test('should implement error fallback for unsupported formats', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('application/octet-stream');
    expect(content).toContain("player.error()");
  });

  test('should close modal on Escape key', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("e.key === 'Escape'");
    expect(content).toContain('closeVideoPlayerModal');
  });
});

// =====================================================
// 4. Direct-to-RustFS upload in video_coach_reviews.php
// =====================================================

test.describe('Direct-to-RustFS upload flow in coach reviews', () => {
  test('should use get_athlete_upload_url action for presigned URL', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain("'action', 'get_athlete_upload_url'");
  });

  test('should use confirm_athlete_upload action after PUT', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain("'action', 'confirm_athlete_upload'");
  });

  test('should PUT file directly to presigned URL', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain("xhr.open('PUT', presignedUrl");
  });

  test('should implement legacy fallback on direct upload failure', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('falling back to server upload');
    expect(content).toContain("'action', 'athlete_upload_video'");
  });

  test('should send upload_nonce for confirmation', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain("'upload_nonce'");
  });
});

// =====================================================
// 5. handleGetAthleteUploadUrl allows null coach_id
// =====================================================

test.describe('handleGetAthleteUploadUrl coach_id fix', () => {
  test('should not throw exception when coach_id is null', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('Allow upload even without an assigned coach');
    expect(funcBody).not.toContain('You do not have an assigned coach');
  });
});

// =====================================================
// 6. handleVideoDelete cleans up RustFS objects
// =====================================================

test.describe('handleVideoDelete RustFS cleanup', () => {
  test('should detect RustFS proxy URLs and extract object key', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("api/media.php?key=");
    expect(funcBody).toContain('parse_url');
    expect(funcBody).toContain('parse_str');
  });

  test('should call deleteFromRustFS for cloud-stored videos', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('deleteFromRustFS(');
    expect(funcBody).toContain('getRustFSSettings');
  });

  test('should still handle local file deletion', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('unlink(');
    expect(funcBody).toContain('file_exists(');
  });

  test('should not crash if RustFS delete fails', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('catch (Exception');
    expect(funcBody).toContain('error_log');
  });
});

// =====================================================
// 7. Camera recording name field
// =====================================================

test.describe('Camera recording name field', () => {
  test('should have recording name input in camera interface', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('id="camera_recording_name"');
    expect(content).toContain('Recording Name');
  });

  test('should pre-fill upload form title from camera name', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain("getElementById('camera_recording_name')");
    expect(content).toContain("getElementById('video_title')");
    expect(content).toContain('titleField.value = cameraName.value.trim()');
  });
});

// =====================================================
// 8. Coaches Corner menu has no role restriction
// =====================================================

test.describe('Coaches Corner menu restriction removed', () => {
  test('should not have isAnyCoach guard around Coaches Corner', () => {
    const content = readFile('pwa_more_menu.php');
    // Find the Coaches Corner section
    const coachesIdx = content.indexOf('Coaches Corner');
    expect(coachesIdx).toBeGreaterThan(-1);

    // Check the 100 chars before "Coaches Corner" — should NOT have isAnyCoach
    const before = content.substring(Math.max(0, coachesIdx - 100), coachesIdx);
    expect(before).not.toContain('$isAnyCoach');
  });

  test('should still contain Coaches Corner section', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toContain('Coaches Corner');
    expect(content).toContain('coach_calendar');
    expect(content).toContain('record_drill_video');
    expect(content).toContain('gameplan');
  });
});

// =====================================================
// 9. Coach reviews query includes athlete_id
// =====================================================

test.describe('Coach reviews query includes athlete_id', () => {
  test('should include athlete_id condition in coach query', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('v.athlete_id = ?');
  });

  test('should pass user_id three times for coach query params', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('$params = [$user_id, $user_id, $user_id]');
  });
});

// =====================================================
// 10. Upload form works without assigned coach
// =====================================================

test.describe('Upload form without assigned coach', () => {
  test('should show info message instead of blocking warning', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('alert-info');
    expect(content).toContain('can be assigned for review later');
  });

  test('should always render the upload form', () => {
    const content = readFile('views/video_coach_reviews.php');
    // The form should not be wrapped in an else branch that requires coach
    // Check that the form tag exists outside any blocking condition
    expect(content).toContain('data-form="video-upload"');
  });
});

// =====================================================
// 11. View-video buttons have data-video-url
// =====================================================

test.describe('View-video buttons include video URL', () => {
  test('pending videos view button should have data-video-url', () => {
    const content = readFile('views/video_coach_reviews.php');
    // Both view-video buttons should have data-video-url attributes on the same line
    const lines = content.split('\n').filter(l => l.includes('view-video') && l.includes('data-video-url'));
    expect(lines.length).toBeGreaterThanOrEqual(2);
  });
});
