/**
 * Tests for Video Upload Multipart, Upload Log, and Companion Retry features
 *
 * Verifies:
 * 1. process_video.php: Multipart upload handlers (initiate, presign_part, complete, abort)
 * 2. video_record_athlete.php: Upload log dropdown and multipart support
 * 3. video_record_drill.php: Upload log dropdown and multipart support
 * 4. companion/templates/history.html: Retry button for failed transcodes
 * 5. Background transcoding messaging in upload views
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. process_video.php: Multipart Upload Handlers
// =====================================================

test.describe('process_video.php multipart upload handlers', () => {
  const content = () => readFile('process_video.php');

  test('should have initiate_multipart action in switch statement', () => {
    const c = content();
    expect(c).toContain("case 'initiate_multipart':");
    expect(c).toContain('handleMultipartInitiate()');
  });

  test('should have presign_part action in switch statement', () => {
    const c = content();
    expect(c).toContain("case 'presign_part':");
    expect(c).toContain('handleMultipartPresignPart()');
  });

  test('should have complete_multipart action in switch statement', () => {
    const c = content();
    expect(c).toContain("case 'complete_multipart':");
    expect(c).toContain('handleMultipartComplete()');
  });

  test('should have abort_multipart action in switch statement', () => {
    const c = content();
    expect(c).toContain("case 'abort_multipart':");
    expect(c).toContain('handleMultipartAbort()');
  });

  test('handleMultipartInitiate should validate file size limit', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartInitiate()');
    const funcEnd = c.indexOf('\nfunction handleMultipartPresignPart()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('10 * 1024 * 1024 * 1024');
    expect(func).toContain('File exceeds 10 GB limit');
  });

  test('handleMultipartInitiate should validate allowed extensions', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartInitiate()');
    const funcEnd = c.indexOf('\nfunction handleMultipartPresignPart()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("'mp4'");
    expect(func).toContain("'mkv'");
    expect(func).toContain("'mov'");
    expect(func).toContain("'webm'");
  });

  test('handleMultipartInitiate should support all upload types', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartInitiate()');
    const funcEnd = c.indexOf('\nfunction handleMultipartPresignPart()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("'athlete_video'");
    expect(func).toContain("'coach_video'");
    expect(func).toContain("'drill_video'");
    expect(func).toContain("'video_source'");
  });

  test('handleMultipartInitiate should perform role checks', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartInitiate()');
    const funcEnd = c.indexOf('\nfunction handleMultipartPresignPart()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('coach_roles');
    expect(func).toContain('permission');
  });

  test('handleMultipartInitiate should call S3 initiate and parse UploadId', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartInitiate()');
    const funcEnd = c.indexOf('\nfunction handleMultipartPresignPart()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("signAndExecMultipartS3('POST'");
    expect(func).toContain('uploads=');
    expect(func).toContain('<UploadId>');
  });

  test('handleMultipartInitiate should store session metadata with upload_nonce', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartInitiate()');
    const funcEnd = c.indexOf('\nfunction handleMultipartPresignPart()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('pending_video_upload_general');
    expect(func).toContain('upload_nonce');
    expect(func).toContain('random_bytes');
  });

  test('handleMultipartPresignPart should validate object key against allowed prefixes', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartPresignPart()');
    const funcEnd = c.indexOf('\nfunction handleMultipartComplete()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('Images/videos/');
    expect(func).toContain('Images/DrillVideos/');
    expect(func).toContain('Invalid object key');
  });

  test('handleMultipartPresignPart should generate presigned URL with partNumber and uploadId', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartPresignPart()');
    const funcEnd = c.indexOf('\nfunction handleMultipartComplete()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('partNumber');
    expect(func).toContain('uploadId');
    expect(func).toContain('X-Amz-Signature');
  });

  test('handleMultipartComplete should build XML and send CompleteMultipartUpload', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartComplete()');
    const funcEnd = c.indexOf('\nfunction handleMultipartAbort()', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('<CompleteMultipartUpload>');
    expect(func).toContain('<Part><PartNumber>');
    expect(func).toContain('<ETag>');
    expect(func).toContain("signAndExecMultipartS3('POST'");
  });

  test('handleMultipartAbort should send DELETE to abort upload', () => {
    const c = content();
    const funcStart = c.indexOf('function handleMultipartAbort()');
    const funcEnd = c.indexOf('\n/**', funcStart + 10);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : funcStart + 500);
    expect(func).toContain("signAndExecMultipartS3('DELETE'");
    expect(func).toContain('uploadId');
  });

  test('should define loadMultipartRustFSConfig helper', () => {
    const c = content();
    expect(c).toContain('function loadMultipartRustFSConfig()');
    expect(c).toContain('decryptPassword');
  });

  test('should define resolveMultipartObjectUrl helper', () => {
    const c = content();
    expect(c).toContain('function resolveMultipartObjectUrl($cfg, $objectKey)');
    expect(c).toContain('public_endpoint');
  });

  test('should define signAndExecMultipartS3 helper', () => {
    const c = content();
    expect(c).toContain('function signAndExecMultipartS3($method, $cfg, $objectKey');
    expect(c).toContain('AWS4-HMAC-SHA256');
  });
});

// =====================================================
// 2. video_record_athlete.php: Upload Log + Multipart
// =====================================================

test.describe('video_record_athlete.php upload log dropdown', () => {
  const content = () => readFile('views/video_record_athlete.php');

  test('should have upload log details element', () => {
    const c = content();
    expect(c).toContain('id="uploadLogDetails"');
    expect(c).toContain('<summary');
    expect(c).toContain('Upload Log');
  });

  test('should have upload log pre element', () => {
    const c = content();
    expect(c).toContain('id="uploadLogPre"');
  });

  test('should have uploadLog function that writes to log pre', () => {
    const c = content();
    expect(c).toContain('function uploadLog(msg)');
    expect(c).toContain('uploadLogPre');
    expect(c).toContain('toLocaleTimeString');
  });

  test('should have uploadLogError and uploadLogWarn functions', () => {
    const c = content();
    expect(c).toContain('function uploadLogError(msg)');
    expect(c).toContain('function uploadLogWarn(msg)');
  });

  test('should define MULTIPART_THRESHOLD constant', () => {
    const c = content();
    expect(c).toContain('MULTIPART_THRESHOLD = 64 * 1024 * 1024');
  });

  test('should check file size against MULTIPART_THRESHOLD', () => {
    const c = content();
    expect(c).toContain('videoFile.size > MULTIPART_THRESHOLD');
  });

  test('should have multipartAthleteUpload function', () => {
    const c = content();
    expect(c).toContain('function multipartAthleteUpload(');
  });

  test('multipartAthleteUpload should call initiate_multipart action', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartAthleteUpload(');
    const funcEnd = c.indexOf('\n    function uploadAllParts(', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("action: 'initiate_multipart'");
    expect(func).toContain("upload_type: 'athlete_video'");
  });

  test('multipartAthleteUpload should call complete_multipart after all parts', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartAthleteUpload(');
    const funcEnd = c.indexOf('\n    function uploadAllParts(', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("action: 'complete_multipart'");
    expect(func).toContain("action: 'confirm_video_upload'");
  });

  test('multipartAthleteUpload should abort on failure', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartAthleteUpload(');
    const funcEnd = c.indexOf('\n    function uploadAllParts(', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("action: 'abort_multipart'");
  });

  test('should have uploadAllParts function with concurrent dispatch', () => {
    const c = content();
    expect(c).toContain('function uploadAllParts(');
    expect(c).toContain('CONCURRENT_PARTS');
    expect(c).toContain('dispatch()');
  });

  test('should have uploadOnePart with retry logic', () => {
    const c = content();
    expect(c).toContain('function uploadOnePart(');
    expect(c).toContain('MAX_PART_RETRIES');
    expect(c).toContain('tryUpload');
  });

  test('should show transcoding in background message after upload', () => {
    const c = content();
    expect(c).toContain('Transcoding in background');
    expect(c).toContain('function showUploadComplete(');
  });

  test('showUploadComplete should update title and hide spinner', () => {
    const c = content();
    const funcStart = c.indexOf('function showUploadComplete(');
    const funcEnd = c.indexOf('\n    }', funcStart + 10);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : funcStart + 500);
    expect(func).toContain('uploadTitle');
    expect(func).toContain('uploadSpinner');
    expect(func).toContain('Upload Complete');
    expect(func).toContain('you can leave this page');
  });

  test('should auto-open log on completion', () => {
    const c = content();
    const funcStart = c.indexOf('function showUploadComplete(');
    const funcEnd = c.indexOf('\n    }', funcStart + 10);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : funcStart + 500);
    expect(func).toContain('logDetails');
    expect(func).toContain('.open = true');
  });

  test('should delay redirect after upload for user to see completion', () => {
    const c = content();
    const funcStart = c.indexOf('function showUploadComplete(');
    const funcEnd = c.indexOf('\n    }', funcStart + 10);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : funcStart + 500);
    expect(func).toContain('setTimeout');
    expect(func).toContain('3000');
  });
});

// =====================================================
// 3. video_record_drill.php: Upload Log + Multipart
// =====================================================

test.describe('video_record_drill.php upload log dropdown', () => {
  const content = () => readFile('views/video_record_drill.php');

  test('should have drill upload log details element', () => {
    const c = content();
    expect(c).toContain('id="drillUploadLogDetails"');
    expect(c).toContain('<summary');
    expect(c).toContain('Upload Log');
  });

  test('should have drill upload log pre element', () => {
    const c = content();
    expect(c).toContain('id="drillUploadLogPre"');
  });

  test('should have drillLog function', () => {
    const c = content();
    expect(c).toContain('function drillLog(msg)');
    expect(c).toContain('drillLogPre');
  });

  test('should have drillLogError and drillLogWarn functions', () => {
    const c = content();
    expect(c).toContain('function drillLogError(msg)');
    expect(c).toContain('function drillLogWarn(msg)');
  });

  test('should define MULTIPART_THRESHOLD for drill uploads', () => {
    const c = content();
    expect(c).toContain('MULTIPART_THRESHOLD = 64 * 1024 * 1024');
  });

  test('should use multipart for large file uploads', () => {
    const c = content();
    expect(c).toContain('videoFile.size > MULTIPART_THRESHOLD');
    expect(c).toContain("action: 'initiate_multipart'");
  });

  test('should have drillUploadAllParts function', () => {
    const c = content();
    expect(c).toContain('function drillUploadAllParts(');
    expect(c).toContain('CONCURRENT_PARTS');
  });

  test('should have drillUploadOnePart with retry', () => {
    const c = content();
    expect(c).toContain('function drillUploadOnePart(');
    expect(c).toContain('MAX_PART_RETRIES');
  });

  test('should show transcoding in background for drill uploads', () => {
    const c = content();
    expect(c).toContain('Transcoding in background');
    expect(c).toContain('function drillShowComplete(');
  });

  test('drillShowComplete should update title', () => {
    const c = content();
    const funcStart = c.indexOf('function drillShowComplete(');
    const funcEnd = c.indexOf('\n    }', funcStart + 10);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : funcStart + 300);
    expect(func).toContain('drillUploadTitle');
    expect(func).toContain('Transcoding in background');
  });

  test('should abort multipart on failure in drill file upload', () => {
    const c = content();
    expect(c).toContain("action: 'abort_multipart'");
  });

  test('camera upload handler should use drillLog', () => {
    const c = content();
    // Find the uploadBtn click handler section
    expect(c).toContain("drillLog('Recording:");
    expect(c).toContain("drillLog('Presigned URL obtained");
  });

  test('should have drillPostAction helper for multipart operations', () => {
    const c = content();
    expect(c).toContain('function drillPostAction(params)');
    expect(c).toContain('process_video.php');
  });
});

// =====================================================
// 4. Companion History: Retry Button
// =====================================================

test.describe('companion/templates/history.html retry button', () => {
  const content = () => readFile('companion/templates/history.html');

  test('should have btn-retry CSS class', () => {
    const c = content();
    expect(c).toContain('.btn-retry');
    expect(c).toContain('btn-retry:hover');
    expect(c).toContain('btn-retry:disabled');
  });

  test('should render retry button for failed jobs in _renderJobLog', () => {
    const c = content();
    const funcStart = c.indexOf('function _renderJobLog(job)');
    const funcEnd = c.indexOf('\n        function', funcStart + 10);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : funcStart + 800);
    expect(func).toContain("job.status === 'failed'");
    expect(func).toContain('btn-retry');
    expect(func).toContain('Retry Transcode');
  });

  test('should have retryJob function', () => {
    const c = content();
    expect(c).toContain('async function retryJob(jobId)');
  });

  test('retryJob should POST to /api/hls/retry', () => {
    const c = content();
    const funcStart = c.indexOf('async function retryJob(');
    const funcEnd = c.indexOf('\n        async function refreshHealth', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('/api/hls/retry');
    expect(func).toContain("method: 'POST'");
    expect(func).toContain('job_id');
  });

  test('retryJob should disable button while retrying', () => {
    const c = content();
    const funcStart = c.indexOf('async function retryJob(');
    const funcEnd = c.indexOf('\n        async function refreshHealth', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('btn.disabled = true');
    expect(func).toContain("btn.textContent = 'Retrying…'");
  });

  test('retryJob should refresh jobs list on success', () => {
    const c = content();
    const funcStart = c.indexOf('async function retryJob(');
    const funcEnd = c.indexOf('\n        async function refreshHealth', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('refreshJobs()');
  });

  test('retryJob should show alert and re-enable button on failure', () => {
    const c = content();
    const funcStart = c.indexOf('async function retryJob(');
    const funcEnd = c.indexOf('\n        async function refreshHealth', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('alert(');
    expect(func).toContain('btn.disabled = false');
  });

  test('retry button should use data-job-id for XSS safety', () => {
    const c = content();
    expect(c).toContain('data-job-id');
    expect(c).toContain('this.dataset.jobId');
    expect(c).toContain('escapeHtml(job.id');
  });

  test('retry button click should stop propagation to prevent row toggle', () => {
    const c = content();
    expect(c).toContain('event.stopPropagation()');
  });

  test('retryJob should send API key header', () => {
    const c = content();
    const funcStart = c.indexOf('async function retryJob(');
    const funcEnd = c.indexOf('\n        async function refreshHealth', funcStart);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('apiHeaders()');
  });
});

// =====================================================
// 5. Old upload code removed from process_video.php
// =====================================================

test.describe('process_video.php old upload handlers removed', () => {
  const content = () => readFile('process_video.php');

  test('should NOT contain handleVideoUpload function', () => {
    const c = content();
    expect(c).not.toContain('function handleVideoUpload()');
  });

  test('should NOT contain handleAthleteVideoUpload function', () => {
    const c = content();
    expect(c).not.toContain('function handleAthleteVideoUpload()');
  });

  test('should NOT contain handleGetAthleteUploadUrl function', () => {
    const c = content();
    expect(c).not.toContain('function handleGetAthleteUploadUrl()');
  });

  test('should NOT contain handleConfirmAthleteUpload function', () => {
    const c = content();
    expect(c).not.toContain('function handleConfirmAthleteUpload()');
  });

  test('should NOT contain handleDrillVideoUpload function', () => {
    const c = content();
    expect(c).not.toContain('function handleDrillVideoUpload()');
  });

  test('should NOT contain upload_video case in switch', () => {
    const c = content();
    expect(c).not.toContain("case 'upload_video':");
  });

  test('should NOT contain athlete_upload_video case in switch', () => {
    const c = content();
    expect(c).not.toContain("case 'athlete_upload_video':");
  });

  test('should NOT contain get_athlete_upload_url case in switch', () => {
    const c = content();
    expect(c).not.toContain("case 'get_athlete_upload_url':");
  });

  test('should NOT contain confirm_athlete_upload case in switch', () => {
    const c = content();
    expect(c).not.toContain("case 'confirm_athlete_upload':");
  });

  test('should NOT contain upload_drill_video case in switch', () => {
    const c = content();
    expect(c).not.toContain("case 'upload_drill_video':");
  });

  test('should still contain new upload handlers', () => {
    const c = content();
    expect(c).toContain('function handleGetVideoUploadUrl()');
    expect(c).toContain('function handleConfirmVideoUpload()');
    expect(c).toContain('function handleMultipartInitiate()');
    expect(c).toContain('function handleMultipartPresignPart()');
    expect(c).toContain('function handleMultipartComplete()');
    expect(c).toContain('function handleMultipartAbort()');
  });
});

// =====================================================
// 6. video_coach_reviews.php uses new upload flow
// =====================================================

test.describe('video_coach_reviews.php new upload flow', () => {
  const content = () => readFile('views/video_coach_reviews.php');

  test('should NOT have legacy athlete_upload_video hidden input', () => {
    const c = content();
    expect(c).not.toContain('value="athlete_upload_video"');
  });

  test('should NOT reference get_athlete_upload_url action', () => {
    const c = content();
    expect(c).not.toContain("'get_athlete_upload_url'");
  });

  test('should NOT reference confirm_athlete_upload action', () => {
    const c = content();
    expect(c).not.toContain("'confirm_athlete_upload'");
  });

  test('should use get_video_upload_url for single uploads', () => {
    const c = content();
    expect(c).toContain("'get_video_upload_url'");
  });

  test('should use confirm_video_upload for confirmation', () => {
    const c = content();
    expect(c).toContain("'confirm_video_upload'");
  });

  test('should use initiate_multipart for large files', () => {
    const c = content();
    expect(c).toContain("'initiate_multipart'");
  });

  test('should have upload log dropdown', () => {
    const c = content();
    expect(c).toContain('crUploadLogDetails');
    expect(c).toContain('crUploadLogPre');
    expect(c).toContain('Upload Log');
  });

  test('should have crLog function', () => {
    const c = content();
    expect(c).toContain('function crLog(msg)');
  });

  test('should show transcoding in background message', () => {
    const c = content();
    expect(c).toContain('Transcoding in background');
    expect(c).toContain('function crShowComplete(');
  });

  test('should have cancel upload button', () => {
    const c = content();
    expect(c).toContain('crCancelUploadBtn');
  });

  test('should have multipart support with concurrent parts', () => {
    const c = content();
    expect(c).toContain('MULTIPART_THRESHOLD');
    expect(c).toContain('CONCURRENT_PARTS');
    expect(c).toContain('function crMultipartUpload(');
    expect(c).toContain('function crUploadAllParts(');
    expect(c).toContain('function crUploadOnePart(');
  });
});

// =====================================================
// 7. PWA drill view legacy code removed
// =====================================================

test.describe('pwa/video_record_drill.php legacy fallback removed', () => {
  const content = () => readFile('views/pwa/video_record_drill.php');

  test('should NOT contain athlete_upload_video action', () => {
    const c = content();
    expect(c).not.toContain("'athlete_upload_video'");
  });

  test('should still use confirm_video_upload for confirmation', () => {
    const c = content();
    expect(c).toContain("'confirm_video_upload'");
  });
});

// =====================================================
// 8. video_record_athlete.php legacy hidden input removed
// =====================================================

test.describe('video_record_athlete.php legacy hidden input removed', () => {
  const content = () => readFile('views/video_record_athlete.php');

  test('should NOT have legacy athlete_upload_video hidden input', () => {
    const c = content();
    expect(c).not.toContain('value="athlete_upload_video"');
  });

  test('should still use get_video_upload_url for single uploads', () => {
    const c = content();
    expect(c).toContain("'get_video_upload_url'");
  });
});
