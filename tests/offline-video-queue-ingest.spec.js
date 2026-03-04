/**
 * Tests for Offline Video Recording Queue & Device Ingest System
 *
 * Verifies:
 * 1. database_schema.sql: offline_video_queue table definition
 * 2. deployment/sql/add_offline_video_queue.sql: migration file
 * 3. process_video.php: Offline queue handlers (get, register, update status)
 * 4. js/offline-upload-queue.js: IndexedDB queue manager, external storage, ingest
 * 5. views/video_record_drill.php: Save to Device + storage selection
 * 6. views/video_record_athlete.php: Save to Device + storage selection
 * 7. views/video_coach_reviews.php: Ingest Device tab
 * 8. views/video_drill_review.php: Ingest Device tab
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Database Schema: offline_video_queue table
// =====================================================

test.describe('offline_video_queue table in database_schema.sql', () => {
  const content = () => readFile('database_schema.sql');

  test('should define offline_video_queue table', () => {
    expect(content()).toContain('CREATE TABLE IF NOT EXISTS `offline_video_queue`');
  });

  test('should have user_id and user_role columns', () => {
    const c = content();
    expect(c).toContain('`user_id` INT NOT NULL');
    expect(c).toContain('`user_role` VARCHAR(50) NOT NULL');
  });

  test('should have upload_type ENUM with all video types', () => {
    const c = content();
    expect(c).toMatch(/upload_type.*ENUM\('athlete_video','coach_video','drill_video','video_source'\)/);
  });

  test('should have drill metadata columns', () => {
    const c = content();
    expect(c).toContain('`session_id` INT DEFAULT NULL');
    expect(c).toContain('`drill_id` INT DEFAULT NULL');
    expect(c).toContain('`rep_number` INT DEFAULT 1');
  });

  test('should have athlete and coach foreign keys', () => {
    const c = content();
    expect(c).toContain('`athlete_id` INT DEFAULT NULL');
    expect(c).toContain('`coach_id` INT DEFAULT NULL');
  });

  test('should have game metadata columns', () => {
    const c = content();
    expect(c).toContain('`game_date` DATE DEFAULT NULL');
    expect(c).toContain('`team_played_on` VARCHAR(255)');
    expect(c).toContain('`opponent_team` VARCHAR(255)');
  });

  test('should have video source gameplan columns', () => {
    const c = content();
    expect(c).toContain('`camera_angle` VARCHAR(50)');
    expect(c).toContain('`game_id` INT DEFAULT NULL');
  });

  test('should have upload state tracking columns', () => {
    const c = content();
    expect(c).toMatch(/`status`.*ENUM\('pending','uploading','uploaded','failed'\)/);
    expect(c).toContain('`upload_progress` INT DEFAULT 0');
    expect(c).toContain('`error_message` TEXT');
    expect(c).toContain('`object_key` VARCHAR(500)');
  });

  test('should have client_queue_id with unique key for dedup', () => {
    const c = content();
    expect(c).toContain('`client_queue_id` VARCHAR(64) NOT NULL');
    expect(c).toContain('UNIQUE KEY `uq_client_queue` (`client_queue_id`)');
  });

  test('should have recorded_at timestamp', () => {
    expect(content()).toContain('`recorded_at` TIMESTAMP NOT NULL');
  });

  test('should have index on user_id + status', () => {
    expect(content()).toContain('INDEX `idx_user_status` (`user_id`, `status`)');
  });
});

// =====================================================
// 2. Migration File
// =====================================================

test.describe('deployment/sql/add_offline_video_queue.sql migration', () => {
  const content = () => readFile('deployment/sql/add_offline_video_queue.sql');

  test('should exist and contain CREATE TABLE', () => {
    expect(content()).toContain('CREATE TABLE IF NOT EXISTS `offline_video_queue`');
  });

  test('should have all required columns', () => {
    const c = content();
    expect(c).toContain('`client_queue_id`');
    expect(c).toContain('`upload_type`');
    expect(c).toContain('`recorded_at`');
    expect(c).toContain('`user_id`');
  });
});

// =====================================================
// 3. process_video.php: Offline Queue Handlers
// =====================================================

test.describe('process_video.php offline queue handlers', () => {
  const content = () => readFile('process_video.php');

  test('should have get_offline_queue action in switch', () => {
    const c = content();
    expect(c).toContain("case 'get_offline_queue':");
    expect(c).toContain('handleGetOfflineQueue()');
  });

  test('should have register_offline_queue action in switch', () => {
    const c = content();
    expect(c).toContain("case 'register_offline_queue':");
    expect(c).toContain('handleRegisterOfflineQueue()');
  });

  test('should have update_offline_queue_status action in switch', () => {
    const c = content();
    expect(c).toContain("case 'update_offline_queue_status':");
    expect(c).toContain('handleUpdateOfflineQueueStatus()');
  });

  test('handleGetOfflineQueue should filter by user_id', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetOfflineQueue()');
    const funcEnd = c.indexOf('\nfunction handleRegisterOfflineQueue()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('WHERE user_id = ?');
    expect(func).toContain('$user_id');
  });

  test('handleRegisterOfflineQueue should validate client_queue_id', () => {
    const c = content();
    const funcStart = c.indexOf('function handleRegisterOfflineQueue()');
    const funcEnd = c.indexOf('\nfunction handleUpdateOfflineQueueStatus()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('client_queue_id');
    expect(func).toContain('client_queue_id is required');
  });

  test('handleRegisterOfflineQueue should check for duplicates', () => {
    const c = content();
    const funcStart = c.indexOf('function handleRegisterOfflineQueue()');
    const funcEnd = c.indexOf('\nfunction handleUpdateOfflineQueueStatus()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('duplicate');
    expect(func).toContain('SELECT id FROM offline_video_queue WHERE client_queue_id = ?');
  });

  test('handleRegisterOfflineQueue should validate upload_type', () => {
    const c = content();
    const funcStart = c.indexOf('function handleRegisterOfflineQueue()');
    const funcEnd = c.indexOf('\nfunction handleUpdateOfflineQueueStatus()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("'athlete_video', 'coach_video', 'drill_video', 'video_source'");
    expect(func).toContain('Invalid upload type');
  });

  test('handleRegisterOfflineQueue should use Auditor::log', () => {
    const c = content();
    const funcStart = c.indexOf('function handleRegisterOfflineQueue()');
    const funcEnd = c.indexOf('\nfunction handleUpdateOfflineQueueStatus()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('Auditor::log');
  });

  test('handleUpdateOfflineQueueStatus should validate status values', () => {
    const c = content();
    const funcStart = c.indexOf('function handleUpdateOfflineQueueStatus()');
    const funcBody = c.substring(funcStart, funcStart + 2000);
    expect(funcBody).toContain("'pending', 'uploading', 'uploaded', 'failed'");
  });

  test('handleUpdateOfflineQueueStatus should set uploaded_at on upload completion', () => {
    const c = content();
    const funcStart = c.indexOf('function handleUpdateOfflineQueueStatus()');
    const funcBody = c.substring(funcStart, funcStart + 2000);
    expect(funcBody).toContain("uploaded_at = NOW()");
  });

  test('handleUpdateOfflineQueueStatus should scope to user_id', () => {
    const c = content();
    const funcStart = c.indexOf('function handleUpdateOfflineQueueStatus()');
    const funcBody = c.substring(funcStart, funcStart + 2000);
    expect(funcBody).toContain('user_id = ?');
  });
});

// =====================================================
// 4. js/offline-upload-queue.js: Core Queue Manager
// =====================================================

test.describe('js/offline-upload-queue.js core functionality', () => {
  const content = () => readFile('js/offline-upload-queue.js');

  test('should expose AwOfflineQueue on window', () => {
    expect(content()).toContain('window.AwOfflineQueue');
  });

  test('should define IndexedDB database name and store', () => {
    const c = content();
    expect(c).toContain("var DB_NAME = 'aw_offline_videos'");
    expect(c).toContain("var STORE_NAME = 'videos'");
  });

  test('should have multipart upload constants matching the views', () => {
    const c = content();
    expect(c).toContain('var MULTIPART_THRESHOLD = 64 * 1024 * 1024');
    expect(c).toContain('var PART_SIZE           = 64 * 1024 * 1024');
    expect(c).toContain('var CONCURRENT_PARTS    = 3');
    expect(c).toContain('var MAX_PART_RETRIES    = 5');
  });

  test('should have enqueueVideo function', () => {
    expect(content()).toContain('function enqueueVideo(blob, metadata)');
  });

  test('should have getPendingCount function', () => {
    expect(content()).toContain('function getPendingCount()');
  });

  test('should have listQueue function', () => {
    expect(content()).toContain('function listQueue()');
  });

  test('should have processQueue function with sequential upload', () => {
    const c = content();
    expect(c).toContain('function processQueue(opts)');
    expect(c).toContain('_processNext');
  });

  test('should delete blob from device after successful upload', () => {
    const c = content();
    expect(c).toContain('removeItem(fullItem.id)');
    expect(c).toContain('Delete blob from device after successful upload');
  });

  test('should stop at failed video for resume capability', () => {
    const c = content();
    expect(c).toContain("status: 'failed'");
    expect(c).toContain('error_message: err.message');
  });

  test('should use multipart for large files', () => {
    const c = content();
    expect(c).toContain('blob.size > MULTIPART_THRESHOLD');
    expect(c).toContain('_multipartUpload');
    expect(c).toContain('_singleUpload');
  });

  test('should build correct upload params for each type', () => {
    const c = content();
    expect(c).toContain("item.upload_type === 'drill_video'");
    expect(c).toContain("item.upload_type === 'coach_video'");
    expect(c).toContain("item.upload_type === 'video_source'");
    expect(c).toContain('p.session_id = item.session_id');
    expect(c).toContain('p.drill_id = item.drill_id');
  });

  test('should have cancelQueue function', () => {
    expect(content()).toContain('function cancelQueue()');
  });

  test('should have connectivity monitor with auto-prompt', () => {
    const c = content();
    expect(c).toContain('function initConnectivityMonitor()');
    expect(c).toContain("window.addEventListener('online'");
    expect(c).toContain('_showUploadPrompt');
  });

  test('should confirm upload with keepalive', () => {
    expect(content()).toContain("keepalive: true");
  });

  test('should retry failed parts with exponential backoff', () => {
    const c = content();
    expect(c).toContain('Math.pow(2, attempt)');
    expect(c).toContain('MAX_PART_RETRIES');
  });
});

// =====================================================
// 5. External Storage (File System Access API)
// =====================================================

test.describe('js/offline-upload-queue.js external storage', () => {
  const content = () => readFile('js/offline-upload-queue.js');

  test('should have isFileSystemAccessSupported function', () => {
    expect(content()).toContain('function isFileSystemAccessSupported()');
    expect(content()).toContain('showDirectoryPicker');
  });

  test('should have pickStorageDirectory function', () => {
    expect(content()).toContain('function pickStorageDirectory()');
  });

  test('should have saveToExternalStorage function', () => {
    expect(content()).toContain('function saveToExternalStorage(dirHandle, blob, metadata)');
  });

  test('should create ArcticWolves_Recordings subfolder', () => {
    expect(content()).toContain("var folderName = 'ArcticWolves_Recordings'");
    expect(content()).toContain("dirHandle.getDirectoryHandle(folderName, { create: true })");
  });

  test('should write metadata sidecar JSON file', () => {
    const c = content();
    expect(c).toContain('.meta.json');
    expect(c).toContain('JSON.stringify(sidecar');
    expect(c).toContain('SIDECAR_VERSION');
  });

  test('sidecar should contain all routing metadata', () => {
    const c = content();
    // Find the sidecar object definition
    const sidecarStart = c.indexOf('var sidecar = {');
    const sidecarEnd = c.indexOf('};', sidecarStart) + 2;
    const sidecar = c.substring(sidecarStart, sidecarEnd);
    expect(sidecar).toContain('upload_type');
    expect(sidecar).toContain('user_id');
    expect(sidecar).toContain('session_id');
    expect(sidecar).toContain('drill_id');
    expect(sidecar).toContain('athlete_id');
    expect(sidecar).toContain('coach_id');
    expect(sidecar).toContain('game_id');
    expect(sidecar).toContain('video_category');
  });

  test('should have scanForIngest function', () => {
    expect(content()).toContain('function scanForIngest(dirHandle)');
  });

  test('scanForIngest should match video files with meta.json sidecars', () => {
    const c = content();
    expect(c).toContain(".endsWith('.meta.json')");
    expect(c).toContain('_isVideoFile');
    expect(c).toMatch(/\.\(mp4\|mkv\|mov\|avi\|webm\)/);
  });

  test('should have ingestFromDevice function', () => {
    expect(content()).toContain('function ingestFromDevice(pairs, opts)');
  });

  test('ingestFromDevice should process sequentially', () => {
    const c = content();
    const funcStart = c.indexOf('function ingestFromDevice(');
    const funcBody = c.substring(funcStart, funcStart + 1000);
    expect(funcBody).toContain('processNext');
    expect(funcBody).toContain('enqueueVideo');
  });

  test('ingestFromDevice should optionally delete files from device', () => {
    const c = content();
    expect(c).toContain('deleteAfterIngest');
    expect(c).toContain('removeEntry');
  });
});

// =====================================================
// 6. views/video_record_drill.php: Save to Device
// =====================================================

test.describe('views/video_record_drill.php save to device', () => {
  const content = () => readFile('views/video_record_drill.php');

  test('should have Save to Device button', () => {
    const c = content();
    expect(c).toContain('id="saveToDeviceBtn"');
    expect(c).toContain('Save to Device');
  });

  test('should have storage selection panel', () => {
    const c = content();
    expect(c).toContain('id="storageSelectionPanel"');
    expect(c).toContain('Select Storage Location');
  });

  test('should have internal storage option', () => {
    const c = content();
    expect(c).toContain('data-storage="internal"');
    expect(c).toContain('Internal Storage');
  });

  test('should have external storage option', () => {
    const c = content();
    expect(c).toContain('data-storage="external"');
    expect(c).toContain('External Drive / SD Card');
  });

  test('should include offline-upload-queue.js', () => {
    expect(content()).toContain('src="js/offline-upload-queue.js"');
  });

  test('should call AwOfflineQueue.enqueueVideo for internal storage', () => {
    expect(content()).toContain('AwOfflineQueue.enqueueVideo(blob, metadata)');
  });

  test('should call AwOfflineQueue.saveToExternalStorage for external', () => {
    expect(content()).toContain('AwOfflineQueue.saveToExternalStorage');
  });

  test('should call AwOfflineQueue.pickStorageDirectory for external', () => {
    expect(content()).toContain('AwOfflineQueue.pickStorageDirectory()');
  });

  test('should build drill_video metadata with session/drill/athlete', () => {
    const c = content();
    expect(c).toContain("upload_type: 'drill_video'");
    expect(c).toContain('session_id: sessionId');
    expect(c).toContain('drill_id: drillId');
    expect(c).toContain('athlete_id: athleteId');
  });

  test('should auto-increment rep number after save', () => {
    expect(content()).toContain('repInput.value = parseInt(repInput.value || 1) + 1');
  });

  test('should init connectivity monitor', () => {
    expect(content()).toContain('AwOfflineQueue.initConnectivityMonitor()');
  });

  test('storage selection CSS should be present', () => {
    const c = content();
    expect(c).toContain('.storage-selection-panel');
    expect(c).toContain('.storage-option-btn');
    expect(c).toContain('.storage-option-btn.active');
  });
});

// =====================================================
// 7. views/video_record_athlete.php: Save to Device
// =====================================================

test.describe('views/video_record_athlete.php save to device', () => {
  const content = () => readFile('views/video_record_athlete.php');

  test('should have Save to Device button', () => {
    const c = content();
    expect(c).toContain('id="athlete-save-to-device-btn"');
    expect(c).toContain('Save to Device');
  });

  test('should have storage selection panel', () => {
    const c = content();
    expect(c).toContain('id="athleteStoragePanel"');
    expect(c).toContain('Select Storage Location');
  });

  test('should include offline-upload-queue.js', () => {
    expect(content()).toContain('src="js/offline-upload-queue.js"');
  });

  test('should build athlete_video metadata', () => {
    const c = content();
    expect(c).toContain("upload_type: 'athlete_video'");
    expect(c).toContain('title: title');
    expect(c).toContain('video_category: category');
    expect(c).toContain('coach_id:');
  });

  test('should init connectivity monitor', () => {
    expect(content()).toContain('AwOfflineQueue.initConnectivityMonitor()');
  });
});

// =====================================================
// 8. views/video_coach_reviews.php: Ingest Device Tab
// =====================================================

test.describe('views/video_coach_reviews.php ingest device tab', () => {
  const content = () => readFile('views/video_coach_reviews.php');

  test('should have Ingest Device tab button', () => {
    const c = content();
    expect(c).toContain('data-tab="ingest"');
    expect(c).toContain('Ingest Device');
  });

  test('should have ingest tab content', () => {
    const c = content();
    expect(c).toContain('id="ingest-tab"');
    expect(c).toContain('Ingest Videos from Device');
  });

  test('should detect File System Access API support', () => {
    const c = content();
    expect(c).toContain('showDirectoryPicker');
    expect(c).toContain('ingestFsaNotSupported');
    expect(c).toContain('ingestFsaSupported');
  });

  test('should have step 1: select directory', () => {
    const c = content();
    expect(c).toContain('id="ingestSelectDirBtn"');
    expect(c).toContain('Select Recording Folder');
  });

  test('should have step 2: review discovered videos', () => {
    const c = content();
    expect(c).toContain('id="ingestVideoList"');
    expect(c).toContain('Review Discovered Videos');
  });

  test('should have step 3: import progress', () => {
    const c = content();
    expect(c).toContain('id="ingestProgressFill"');
    expect(c).toContain('Import');
  });

  test('should have delete-after-import checkbox', () => {
    const c = content();
    expect(c).toContain('id="ingestDeleteAfter"');
    expect(c).toContain('Remove files from device after import');
  });

  test('should call AwOfflineQueue.scanForIngest', () => {
    expect(content()).toContain('AwOfflineQueue.scanForIngest');
  });

  test('should call AwOfflineQueue.ingestFromDevice', () => {
    expect(content()).toContain('AwOfflineQueue.ingestFromDevice');
  });

  test('should auto-start upload queue after ingest', () => {
    expect(content()).toContain('AwOfflineQueue.processQueue');
  });

  test('should include offline-upload-queue.js', () => {
    expect(content()).toContain('src="js/offline-upload-queue.js"');
  });

  test('should have ingest CSS styles', () => {
    const c = content();
    expect(c).toContain('.ingest-step');
    expect(c).toContain('.ingest-video-item');
    expect(c).toContain('.ingest-video-list');
  });

  test('should have offline upload banner CSS', () => {
    const c = content();
    expect(c).toContain('.offline-upload-banner');
    expect(c).toContain('.offline-upload-banner-content');
  });
});

// =====================================================
// 9. views/video_drill_review.php: Ingest Device Tab
// =====================================================

test.describe('views/video_drill_review.php ingest device tab', () => {
  const content = () => readFile('views/video_drill_review.php');

  test('should have Ingest Device view toggle button', () => {
    const c = content();
    expect(c).toContain("view=ingest");
    expect(c).toContain('Ingest Device');
  });

  test('should have ingest view section', () => {
    const c = content();
    expect(c).toContain("active_view === 'ingest'");
    expect(c).toContain('Ingest Videos from Device');
  });

  test('should have directory selection button', () => {
    expect(content()).toContain('id="athleteIngestSelectDirBtn"');
  });

  test('should have ingest video list', () => {
    expect(content()).toContain('id="athleteIngestVideoList"');
  });

  test('should have delete-after-import checkbox', () => {
    expect(content()).toContain('id="athleteIngestDeleteAfter"');
  });

  test('should call AwOfflineQueue.scanForIngest', () => {
    expect(content()).toContain('AwOfflineQueue.scanForIngest');
  });

  test('should call AwOfflineQueue.ingestFromDevice', () => {
    expect(content()).toContain('AwOfflineQueue.ingestFromDevice');
  });

  test('should include offline-upload-queue.js', () => {
    expect(content()).toContain('src="js/offline-upload-queue.js"');
  });

  test('should have ingest CSS styles', () => {
    const c = content();
    expect(c).toContain('.ingest-card');
    expect(c).toContain('.ingest-step');
    expect(c).toContain('.ingest-video-item');
  });

  test('should auto-start upload queue after ingest', () => {
    expect(content()).toContain('AwOfflineQueue.processQueue');
  });
});
