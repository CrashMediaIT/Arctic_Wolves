/**
 * Tests for Companion App Review Changes
 *
 * Verifies:
 * 1. Companion app persistent encrypted config (no env vars needed)
 * 2. API key generation in companion (not shared from main app)
 * 3. Removal of storage path settings from companion (main app controls locations)
 * 4. Bidirectional communication: main app → companion (trigger HLS) and
 *    companion → main app (callback on completion)
 * 5. Correct settings keys in triggerHlsTranscode
 * 6. Push RustFS settings from main app to companion
 * 7. Companion callback webhook endpoint
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Companion .env.example — no env vars required
// =====================================================

test.describe('Companion .env.example', () => {
  const content = () => readFile('companion/.env.example');

  test('should NOT have any variable assignments', () => {
    const c = content();
    // No non-comment lines should have VAR= pattern
    const assignmentLines = c.split('\n').filter(l => {
      const trimmed = l.trim();
      return !trimmed.startsWith('#') && trimmed.length > 0 && /^[A-Z][A-Z_]+=/.test(trimmed);
    });
    expect(assignmentLines.length).toBe(0);
  });

  test('should explain that all settings come from the web UI', () => {
    const c = content();
    expect(c).toContain('web UI');
    expect(c).toContain('encrypted config file');
  });

  test('should explain the setup page flow', () => {
    const c = content();
    expect(c).toContain('setup page');
    expect(c).toContain('Generate API Key');
    expect(c).toContain('Game Plan Settings');
  });

  test('should explain storage paths are controlled by main app', () => {
    const c = content();
    expect(c).toContain('controlled');
    expect(c).toContain('main application');
  });

  test('should state ENCRYPTION_KEY env var is required', () => {
    const c = content();
    expect(c).toContain('ENCRYPTION_KEY');
  });
});

// =====================================================
// 2. Companion app.py — persistent config & key generation
// =====================================================

test.describe('Companion app.py configuration', () => {
  const content = () => readFile('companion/app.py');

  test('should load config from encrypted persistent file', () => {
    const c = content();
    expect(c).toContain('_load_persistent_config');
    expect(c).toContain('_save_persistent_config');
    expect(c).toContain('CONFIG_FILE');
  });

  test('should load encryption key from ENCRYPTION_KEY env var with file fallback', () => {
    const c = content();
    expect(c).toContain('KEY_FILE');
    expect(c).toContain('_read_key_file');
    expect(c).toContain('_write_key_file');
    // Should read ENCRYPTION_KEY env var first, then fall back to key file
    expect(c).toContain("ENCRYPTION_KEY");
  });

  test('should have a first-run setup page', () => {
    const c = content();
    expect(c).toContain('/setup');
    expect(c).toContain('/api/setup');
    expect(c).toContain('def setup_page');
    expect(c).toContain('def setup_save');
  });

  test('should redirect to setup when no key or account exists', () => {
    const c = content();
    expect(c).toContain('_before_request');
    expect(c).toContain('before_request');
  });

  test('should have /api/generate-key endpoint', () => {
    const c = content();
    expect(c).toContain('/api/generate-key');
    expect(c).toContain('def generate_key');
    expect(c).toContain('secrets.token_hex');
  });

  test('should NOT have HLS_STAGING_PREFIX or HLS_POLL_INTERVAL config', () => {
    const c = content();
    // Should not have these as configurable variables (only internal constants or removed)
    expect(c).not.toContain("_pcfg(\"hls_staging_prefix\"");
    expect(c).not.toContain("_pcfg(\"hls_poll_interval\"");
  });

  test('should NOT have the staging watcher', () => {
    const c = content();
    expect(c).not.toContain('_start_staging_watcher');
    expect(c).not.toContain('_watcher_loop');
    expect(c).not.toContain('_watcher_running');
  });

  test('should NOT expose VIDEO_BASE_PATH in config API', () => {
    const c = content();
    // The config GET/PUT should not include video_base_path
    const configGetFunc = c.substring(
      c.indexOf('def get_config'),
      c.indexOf('def update_config')
    );
    expect(configGetFunc).not.toContain('video_base_path');
  });

  test('should include main_app_url in config', () => {
    const c = content();
    expect(c).toContain('MAIN_APP_URL');
    expect(c).toContain('main_app_url');
  });

  test('should load settings from _pcfg (persistent config) not env vars', () => {
    const c = content();
    // API_KEY should use _pcfg, not os.getenv
    expect(c).toContain('API_KEY = _pcfg("api_key")');
    expect(c).toContain('S3_ENDPOINT = _pcfg("s3_endpoint")');
    expect(c).toContain('HW_ACCEL = _pcfg("hw_accel"');
  });
});

// =====================================================
// 3. Companion callback support
// =====================================================

test.describe('Companion app.py callback support', () => {
  const content = () => readFile('companion/app.py');

  test('should have _send_callback function', () => {
    const c = content();
    expect(c).toContain('def _send_callback');
    expect(c).toContain('callback_url');
  });

  test('HLS transcode should accept callback_url parameter', () => {
    const c = content();
    expect(c).toContain('callback_url: str');
  });

  test('/api/hls should accept video_id and callback_url in request', () => {
    const c = content();
    // Look at the full hls_transcode function
    const hlsStart = c.indexOf('def hls_transcode');
    const hlsEnd = c.indexOf('# ------', hlsStart + 1);
    const hlsRoute = c.substring(hlsStart, hlsEnd > -1 ? hlsEnd : undefined);
    expect(hlsRoute).toContain('callback_url');
    expect(hlsRoute).toContain('video_id');
  });

  test('should send callback on transcode completion', () => {
    const c = content();
    expect(c).toContain('_send_callback(cb_url');
    expect(c).toContain('"status": "completed"');
    expect(c).toContain('"hls_manifest"');
    expect(c).toContain('"variants"');
  });

  test('should send callback on transcode failure', () => {
    const c = content();
    expect(c).toContain('"status": "failed"');
    expect(c).toContain('"error"');
  });

  test('should include video_id in callback payload', () => {
    const c = content();
    expect(c).toContain('"video_id": vid_id');
  });
});

// =====================================================
// 4. Main app triggerHlsTranscode — correct settings keys
// =====================================================

test.describe('triggerHlsTranscode in process_video.php', () => {
  const content = () => readFile('process_video.php');

  test('should use gameplan_companion_url setting key', () => {
    const c = content();
    expect(c).toContain("'gameplan_companion_url'");
  });

  test('should use gameplan_companion_api_key setting key', () => {
    const c = content();
    expect(c).toContain("'gameplan_companion_api_key'");
  });

  test('should NOT use bare companion_url setting key', () => {
    const c = content();
    const func = c.substring(c.indexOf('function triggerHlsTranscode'));
    // Should not query for bare 'companion_url' or 'companion_api_key'
    expect(func).not.toContain("'companion_url'");
    expect(func).not.toContain("'companion_api_key'");
  });

  test('should include callback_url in HLS request payload', () => {
    const c = content();
    const func = c.substring(c.indexOf('function triggerHlsTranscode'));
    expect(func).toContain('"callback_url"');
  });

  test('should include video_id in HLS request payload', () => {
    const c = content();
    const func = c.substring(c.indexOf('function triggerHlsTranscode'));
    expect(func).toContain('"video_id"');
  });

  test('should pre-build HLS URL before transcode completes', () => {
    const c = content();
    const func = c.substring(c.indexOf('function triggerHlsTranscode'));
    expect(func).toContain('hls_manifest_url');
    // Should set hls_url before the companion responds
    expect(func).toContain("hls_status = 'pending'");
    expect(func).toContain('hls_url = ?');
  });

  test('should build callback URL from gameplan_app_url setting', () => {
    const c = content();
    const func = c.substring(c.indexOf('function triggerHlsTranscode'));
    expect(func).toContain('gameplan_app_url');
    expect(func).toContain('/api/v1/companion/callback');
  });
});

// =====================================================
// 5. Companion callback webhook endpoint
// =====================================================

test.describe('Companion webhook endpoint (api/v1/companion.php)', () => {
  const content = () => readFile('api/v1/companion.php');

  test('should exist', () => {
    expect(fs.existsSync(path.join(ROOT, 'api/v1/companion.php'))).toBe(true);
  });

  test('should handle POST /v1/companion/callback', () => {
    const c = content();
    expect(c).toContain("'callback'");
    expect(c).toContain('handleCompanionCallback');
  });

  test('should authenticate using gameplan_companion_api_key', () => {
    const c = content();
    expect(c).toContain('authenticateCompanion');
    expect(c).toContain('gameplan_companion_api_key');
    expect(c).toContain('hash_equals');
  });

  test('should update video record on completed status', () => {
    const c = content();
    expect(c).toContain("hls_status");
    expect(c).toContain("'ready'");
    expect(c).toContain('hls_master_url');
    expect(c).toContain('hls_segments_path');
  });

  test('should update video record on failed status', () => {
    const c = content();
    expect(c).toContain("'failed'");
  });

  test('should match video by video_id or hls_job_id', () => {
    const c = content();
    expect(c).toContain('video_id');
    expect(c).toContain('hls_job_id');
  });
});

// =====================================================
// 6. Push RustFS settings to companion
// =====================================================

test.describe('Push RustFS to companion (process_gameplan_settings.php)', () => {
  const content = () => readFile('process_gameplan_settings.php');

  test('should have push_rustfs_to_companion action', () => {
    const c = content();
    expect(c).toContain("'push_rustfs_to_companion'");
  });

  test('should send S3 credentials to companion /api/config', () => {
    const c = content();
    expect(c).toContain('/api/config');
    expect(c).toContain('s3_endpoint');
    expect(c).toContain('s3_access_key');
    expect(c).toContain('s3_secret_key');
    expect(c).toContain('s3_bucket');
  });

  test('should send main_app_url to companion', () => {
    const c = content();
    const pushSection = c.substring(c.indexOf("'push_rustfs_to_companion'"));
    expect(pushSection).toContain("'main_app_url'");
  });

  test('should be registered as a JSON action', () => {
    const c = content();
    expect(c).toContain("'push_rustfs_to_companion'");
    // Should be in the json_actions array
    const jsonActionsLine = c.split('\n').find(l => l.includes('json_actions'));
    expect(jsonActionsLine).toContain('push_rustfs_to_companion');
  });
});

// =====================================================
// 7. Companion Docker configuration
// =====================================================

test.describe('Companion Docker configuration', () => {
  test('docker-compose.yml should have persistent config volume', () => {
    const c = readFile('companion/docker-compose.yml');
    expect(c).toContain('companion_config:/config');
    expect(c).toContain('volumes:');
  });

  test('docker-compose.yml should have ENCRYPTION_KEY environment variable', () => {
    const c = readFile('companion/docker-compose.yml');
    expect(c).not.toContain('env_file');
    expect(c).toContain('ENCRYPTION_KEY');
    expect(c).not.toContain('API_KEY=');
    expect(c).not.toContain('S3_ENDPOINT=');
  });

  test('Dockerfile should NOT set env var defaults for config', () => {
    const c = readFile('companion/Dockerfile');
    expect(c).not.toContain('ENV API_KEY');
    expect(c).not.toContain('ENV VIDEO_BASE_PATH');
    expect(c).not.toContain('ENV HW_ACCEL');
    expect(c).not.toContain('ENV S3_ENDPOINT');
  });

  test('Dockerfile should create /config directory', () => {
    const c = readFile('companion/Dockerfile');
    expect(c).toContain('/config');
  });
});

// =====================================================
// 8. UI updates
// =====================================================

test.describe('Companion web UI (templates/index.html)', () => {
  const content = () => readFile('companion/templates/index.html');

  test('should have Generate API Key button', () => {
    const c = content();
    expect(c).toContain('generateApiKey');
    expect(c).toContain('Generate API Key');
  });

  test('should have Main App URL field', () => {
    const c = content();
    expect(c).toContain('cfg-main-app-url');
    expect(c).toContain('main_app_url');
  });

  test('should NOT have Video Base Path field', () => {
    const c = content();
    expect(c).not.toContain('cfg-video-base');
  });

  test('should NOT have HLS Staging Watcher settings', () => {
    const c = content();
    expect(c).not.toContain('cfg-hls-prefix');
    expect(c).not.toContain('cfg-hls-interval');
    expect(c).not.toContain('HLS Staging Watcher');
  });

  test('should explain settings are persisted and encrypted', () => {
    const c = content();
    expect(c).toContain('encrypted persistent config');
  });
});

test.describe('Main app settings view (views/gameplan_settings.php)', () => {
  const content = () => readFile('views/gameplan_settings.php');

  test('should have Push RustFS to Companion button', () => {
    const c = content();
    expect(c).toContain('pushRustFsToCompanion');
    expect(c).toContain('Push RustFS to Companion');
  });

  test('should describe API key as generated in companion', () => {
    const c = content();
    expect(c).toContain('Generated in the companion');
  });

  test('should describe App URL purpose for callbacks', () => {
    const c = content();
    expect(c).toContain('callback');
  });
});

// =====================================================
// 9. API index includes companion endpoint
// =====================================================

test.describe('API index (api/index.php)', () => {
  test('should list companion endpoint', () => {
    const c = readFile('api/index.php');
    expect(c).toContain("'companion'");
    expect(c).toContain('/v1/companion');
  });
});

// =====================================================
// 10. NGINX routes /api/ on main domain to api/index.php
// =====================================================

test.describe('NGINX main site routes /api/ to API entry point', () => {
  const content = () => readFile('deployment/arctic_wolves.conf');

  test('main site server block should have location /api/ block', () => {
    const c = content();
    // Find the main site server block (arcticwolves.ca / www.arcticwolves.ca)
    const mainStart = c.indexOf('server_name arcticwolves.ca www.arcticwolves.ca;');
    expect(mainStart).toBeGreaterThan(0);
    const mainEnd = c.indexOf('\n}', mainStart);
    const mainBlock = c.substring(mainStart, mainEnd);
    expect(mainBlock).toContain('location /api/');
  });

  test('location /api/ should route to /api/index.php', () => {
    const c = content();
    const mainStart = c.indexOf('server_name arcticwolves.ca www.arcticwolves.ca;');
    const mainEnd = c.indexOf('\n}', mainStart);
    const mainBlock = c.substring(mainStart, mainEnd);
    // The try_files inside location /api/ should fall back to /api/index.php
    const apiLocStart = mainBlock.indexOf('location /api/');
    expect(apiLocStart).toBeGreaterThan(-1);
    const apiLocBlock = mainBlock.substring(apiLocStart, mainBlock.indexOf('}', apiLocStart) + 1);
    expect(apiLocBlock).toContain('/api/index.php');
  });
});

// =====================================================
// 11. Companion _send_callback detects HTML responses
// =====================================================

test.describe('Companion _send_callback HTML response detection', () => {
  test('should check Content-Type for text/html to detect misconfigured routing', () => {
    const c = readFile('companion/app.py');
    const funcStart = c.indexOf('def _send_callback(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('text/html');
    expect(func).toContain('Content-Type');
  });

  test('should provide actionable hint about nginx location /api/ block', () => {
    const c = readFile('companion/app.py');
    const funcStart = c.indexOf('def _send_callback(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('location /api/');
  });
});
