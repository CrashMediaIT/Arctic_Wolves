/**
 * Tests for Transcode Retry and Job Log features
 *
 * Verifies:
 * 1. Companion app.py: _job_log helper and "log" field in HLS jobs
 * 2. Companion app.py: POST /api/hls/retry endpoint
 * 3. Companion app.py: _hls_transcode_s3 logs detailed steps
 * 4. process_video_test.php: retry_transcode action
 * 5. process_video_test.php: transcode_status forwards log
 * 6. admin_video_test.php: Retry button and log panel UI
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Companion app.py: _job_log helper
// =====================================================

test.describe('Companion app.py _job_log helper', () => {
  const content = () => readFile('companion/app.py');

  test('should define _job_log function', () => {
    const c = content();
    expect(c).toContain('def _job_log(job_id: str, message: str, level: str = "info"):');
  });

  test('_job_log should append timestamped entry with ts, level, msg', () => {
    const c = content();
    const funcStart = c.indexOf('def _job_log(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('"ts"');
    expect(func).toContain('"level"');
    expect(func).toContain('"msg"');
    expect(func).toContain('time.time()');
  });

  test('HLS job should initialise with empty log list', () => {
    const c = content();
    // Find the HLS job creation dict
    const hlsRoute = c.indexOf('@app.route("/api/hls"');
    const hlsFunc = c.substring(hlsRoute);
    const jobDict = hlsFunc.substring(hlsFunc.indexOf('"status": "queued"'), hlsFunc.indexOf('with job_lock:\n        jobs[job_id] = job'));
    expect(jobDict).toContain('"log": []');
  });
});

// =====================================================
// 2. Companion app.py: Detailed logging in _hls_transcode_s3
// =====================================================

test.describe('Companion _hls_transcode_s3 detailed logging', () => {
  const content = () => readFile('companion/app.py');

  test('should log HW_ACCEL and HW_ACCEL_DEVICE settings', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('HW_ACCEL setting');
    expect(func).toContain('HW_ACCEL_DEVICE');
  });

  test('should log S3 download with timing and file size', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('Download complete');
    expect(func).toContain('file_size');
    expect(func).toContain('dl_sec');
  });

  test('should log ffprobe video stream details', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('Video stream: codec=');
    expect(func).toContain('pix_fmt');
    expect(func).toContain('r_frame_rate');
  });

  test('should log ffprobe audio stream details', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('Audio stream: codec=');
    expect(func).toContain('sample_rate');
  });

  test('should log container format info', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('Container: format=');
    expect(func).toContain('bit_rate');
  });

  test('should log hardware detection results', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('HW accel available methods');
    expect(func).toContain('HW accel validated encoders');
    expect(func).toContain('HW accel detected decoders');
  });

  test('should log full ffmpeg command for each variant', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('ffmpeg command:');
    expect(func).toContain('decode_flags=');
    expect(func).toContain('encode_flags=');
    expect(func).toContain('vf_flags=');
  });

  test('should log ffmpeg stderr on failure', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('FFmpeg FAILED');
    expect(func).toContain('stderr:');
    expect(func).toContain('"error"');
  });

  test('should log traceback on exception', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('traceback.format_exc()');
    expect(func).toContain('JOB FAILED');
  });

  test('should log variant encode timing', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('enc_sec');
    expect(func).toContain('completed in');
    expect(func).toContain('exit code 0');
  });

  test('should log upload count and timing', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('upload_count');
    expect(func).toContain('upload_sec');
    expect(func).toContain('Upload complete');
  });

  test('should log job completed with total time', () => {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _resolution_width');
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('Job completed successfully');
    expect(func).toContain('total_sec');
  });
});

// =====================================================
// 3. Companion app.py: POST /api/hls/retry endpoint
// =====================================================

test.describe('Companion POST /api/hls/retry endpoint', () => {
  const content = () => readFile('companion/app.py');

  test('should define /api/hls/retry route', () => {
    const c = content();
    expect(c).toContain('@app.route("/api/hls/retry"');
    expect(c).toContain('methods=["POST"]');
  });

  test('should accept job_id to look up original job parameters', () => {
    const c = content();
    const funcStart = c.indexOf('def hls_retry(');
    const funcEnd = c.indexOf('\n@app.', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('old_job_id');
    expect(func).toContain('jobs.get(old_job_id)');
  });

  test('should accept direct source_key and output_prefix', () => {
    const c = content();
    const funcStart = c.indexOf('def hls_retry(');
    const funcEnd = c.indexOf('\n@app.', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('"source_key"');
    expect(func).toContain('"output_prefix"');
  });

  test('should create a new job with log field and return 202', () => {
    const c = content();
    const funcStart = c.indexOf('def hls_retry(');
    const funcEnd = c.indexOf('\n@app.', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('"log": []');
    expect(func).toContain('return jsonify(job), 202');
  });

  test('should track retry_of field referencing the old job', () => {
    const c = content();
    const funcStart = c.indexOf('def hls_retry(');
    const funcEnd = c.indexOf('\n@app.', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('"retry_of"');
  });

  test('should require auth via _require_api_key', () => {
    const c = content();
    const funcStart = c.indexOf('def hls_retry(');
    const funcEnd = c.indexOf('\n@app.', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('_require_api_key');
  });
});

// =====================================================
// 4. process_video_test.php: retry_transcode action
// =====================================================

test.describe('process_video_test.php retry_transcode action', () => {
  const content = () => readFile('process_video_test.php');

  test('should handle retry_transcode action in switch', () => {
    const c = content();
    expect(c).toContain("'retry_transcode'");
    expect(c).toContain('handleTestRetryTranscode');
  });

  test('handleTestRetryTranscode should POST to /api/hls/retry', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTestRetryTranscode');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : c.length);
    expect(func).toContain('/api/hls/retry');
    expect(func).toContain('CURLOPT_POST');
  });

  test('handleTestRetryTranscode should pass job_id and source_key', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTestRetryTranscode');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : c.length);
    expect(func).toContain("'job_id'");
    expect(func).toContain("'source_key'");
  });

  test('handleTestRetryTranscode should store new job in session', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTestRetryTranscode');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : c.length);
    expect(func).toContain("vt_transcode_job");
    expect(func).toContain("'job_id'");
  });
});

// =====================================================
// 5. process_video_test.php: transcode_status forwards log
// =====================================================

test.describe('process_video_test.php transcode_status forwards log', () => {
  const content = () => readFile('process_video_test.php');

  test('handleTestTranscodeStatus should forward log field', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTestTranscodeStatus');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : c.length);
    expect(func).toContain("'log'");
    expect(func).toContain("data['log']");
  });
});

// =====================================================
// 6. UI: Retry button and job log panel
// =====================================================

test.describe('admin_video_test.php Retry button and log panel UI', () => {
  const content = () => readFile('views/admin_video_test.php');

  test('should have retry button element', () => {
    const c = content();
    expect(c).toContain('id="vt-retry-btn"');
    expect(c).toContain('Retry Transcode');
  });

  test('retry button should be hidden by default', () => {
    const c = content();
    const retryBtn = c.substring(c.indexOf('id="vt-retry-btn"') - 100, c.indexOf('id="vt-retry-btn"') + 200);
    expect(retryBtn).toContain('display:none');
  });

  test('should have expandable transcode log details element', () => {
    const c = content();
    expect(c).toContain('id="vt-transcode-log-details"');
    expect(c).toContain('Transcode Job Log');
  });

  test('should have transcode log pre element with dark theme', () => {
    const c = content();
    expect(c).toContain('id="vt-transcode-log"');
    expect(c).toContain('background:#1e1e2e');
  });

  test('should define renderJobLog function', () => {
    const c = content();
    expect(c).toContain('function renderJobLog(');
  });

  test('renderJobLog should color-code log entries by level', () => {
    const c = content();
    const funcStart = c.indexOf('function renderJobLog(');
    const funcEnd = c.indexOf('\n    function ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : funcStart + 1000);
    expect(func).toContain('ERROR');
    expect(func).toContain('WARN');
    expect(func).toContain('INFO');
  });

  test('should reference retry button in JS', () => {
    const c = content();
    expect(c).toContain("document.getElementById('vt-retry-btn')");
  });

  test('retry click handler should call retry_transcode action', () => {
    const c = content();
    expect(c).toContain("action:        'retry_transcode'");
  });

  test('should auto-expand log on completion', () => {
    const c = content();
    expect(c).toContain('transcodeLogDetails.open = true');
  });

  test('should show retry button on failure', () => {
    const c = content();
    // After status === failed, the retry button should be shown
    const failedBlock = c.substring(c.indexOf("status === 'failed'"));
    expect(failedBlock).toContain("retryBtn.style.display = ''");
  });

  test('should track currentTranscodeJob for retry', () => {
    const c = content();
    expect(c).toContain('currentTranscodeJob');
    expect(c).toContain('objectKey');
    expect(c).toContain('jobId');
    expect(c).toContain('outputPrefix');
  });

  test('should render log entries during polling', () => {
    const c = content();
    // The poll handler should call renderJobLog when log data available
    expect(c).toContain('renderJobLog(data.log)');
  });
});
