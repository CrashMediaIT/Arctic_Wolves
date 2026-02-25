// @ts-check
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Garage S3 Storage Integration Tests
 * 
 * Validates the Garage S3 object storage integration:
 * 1. GarageS3 client library (lib/garage_s3.php)
 * 2. Garage helper functions in cloud_config.php
 * 3. Settings UI tab in admin_system_tools.php
 * 4. Settings processing in process_settings.php
 * 5. Encrypted settings in security.php
 * 6. File serving via serve_file.php
 * 7. Image helper Garage S3 awareness
 */

const BASE = path.resolve(__dirname, '..');

function readFile(relative) {
  return fs.readFileSync(path.join(BASE, relative), 'utf8');
}

// ─── 1. GarageS3 Client Library ──────────────────────────────────────────────

test.describe('GarageS3 client library (lib/garage_s3.php)', () => {
  const content = () => readFile('lib/garage_s3.php');

  test('GarageS3 class exists', () => {
    expect(content()).toContain('class GarageS3');
  });

  test('has putObject method', () => {
    expect(content()).toContain('public function putObject(');
  });

  test('has putObjectStreaming method for large files', () => {
    expect(content()).toContain('public function putObjectStreaming(');
  });

  test('has getObject method', () => {
    expect(content()).toContain('public function getObject(');
  });

  test('has deleteObject method', () => {
    expect(content()).toContain('public function deleteObject(');
  });

  test('has headObject method', () => {
    expect(content()).toContain('public function headObject(');
  });

  test('has getPresignedUrl method for serving files', () => {
    expect(content()).toContain('public function getPresignedUrl(');
  });

  test('has testConnection method', () => {
    expect(content()).toContain('public function testConnection(');
  });

  test('uses AWS Signature V4', () => {
    expect(content()).toContain('AWS4-HMAC-SHA256');
    expect(content()).toContain('aws4_request');
  });

  test('supports path-style bucket URLs', () => {
    expect(content()).toContain('buildUrl(');
  });
});

// ─── 2. Garage Helper Functions in cloud_config.php ──────────────────────────

test.describe('Garage S3 helper functions in cloud_config.php', () => {
  const content = () => readFile('cloud_config.php');

  test('includes garage_s3.php', () => {
    expect(content()).toContain("require_once __DIR__ . '/lib/garage_s3.php'");
  });

  test('has getGarageSettings function', () => {
    expect(content()).toContain('function getGarageSettings(');
  });

  test('getGarageSettings queries correct setting keys', () => {
    const fn = content();
    expect(fn).toContain('garage_endpoint');
    expect(fn).toContain('garage_access_key');
    expect(fn).toContain('garage_secret_key');
    expect(fn).toContain('garage_region');
    expect(fn).toContain('garage_bucket_images');
    expect(fn).toContain('garage_bucket_videos');
  });

  test('has connectGarage function', () => {
    expect(content()).toContain('function connectGarage(');
  });

  test('connectGarage decrypts the secret key', () => {
    const fn = content();
    const start = fn.indexOf('function connectGarage(');
    const section = fn.substring(start, start + 800);
    expect(section).toContain('decryptPassword(');
  });

  test('has uploadToGarage function', () => {
    expect(content()).toContain('function uploadToGarage(');
  });

  test('uploadToGarage uses streaming for videos', () => {
    const fn = content();
    const start = fn.indexOf('function uploadToGarage(');
    const section = fn.substring(start, start + 1000);
    expect(section).toContain('putObjectStreaming(');
  });

  test('has getGarageFileUrl function for pre-signed URLs', () => {
    expect(content()).toContain('function getGarageFileUrl(');
  });

  test('has downloadFromGarage function', () => {
    expect(content()).toContain('function downloadFromGarage(');
  });

  test('has garageFileExists function', () => {
    expect(content()).toContain('function garageFileExists(');
  });

  test('has deleteFromGarage function', () => {
    expect(content()).toContain('function deleteFromGarage(');
  });
});

// ─── 3. persistUploadedFile uses Garage S3 ───────────────────────────────────

test.describe('persistUploadedFile uses Garage S3 as primary storage', () => {
  const content = () => readFile('cloud_config.php');

  test('persistUploadedFile calls uploadToGarage', () => {
    const fn = content();
    const start = fn.indexOf('function persistUploadedFile(');
    const section = fn.substring(start, start + 3500);
    expect(section).toContain('uploadToGarage(');
  });

  test('persistUploadedFile returns garage_path', () => {
    const fn = content();
    const start = fn.indexOf('function persistUploadedFile(');
    const section = fn.substring(start, start + 3500);
    expect(section).toContain("'garage_path'");
  });

  test('persistUploadedFile still falls back to Nextcloud', () => {
    const fn = content();
    const start = fn.indexOf('function persistUploadedFile(');
    const section = fn.substring(start, start + 3500);
    expect(section).toContain('uploadLargeFileToNextcloud(');
    expect(section).toContain('uploadImageToNextcloud(');
  });

  test('persistUploadedFile still falls back to persistent storage', () => {
    const fn = content();
    const start = fn.indexOf('function persistUploadedFile(');
    const section = fn.substring(start, start + 3500);
    expect(section).toContain('saveToPersistentStorage(');
  });

  test('persistUploadedFile populates nextcloud_path with garage:// URI for backward compat', () => {
    const fn = content();
    const start = fn.indexOf('function persistUploadedFile(');
    const section = fn.substring(start, start + 3500);
    expect(section).toContain("'garage://'");
  });
});

// ─── 4. Settings UI in admin_system_tools.php ────────────────────────────────

test.describe('Garage S3 settings tab in admin_system_tools.php', () => {
  const content = () => readFile('views/admin_system_tools.php');

  test('has Garage S3 tab link', () => {
    expect(content()).toContain('tab=garage');
    expect(content()).toContain('Garage S3');
  });

  test('has Garage tab content section', () => {
    expect(content()).toContain('id="garage-tab"');
  });

  test('has garage-form with correct action', () => {
    expect(content()).toContain('id="garage-form"');
    expect(content()).toContain('value="update_garage"');
  });

  test('has endpoint input field', () => {
    expect(content()).toContain('name="garage_endpoint"');
  });

  test('has access key input field', () => {
    expect(content()).toContain('name="garage_access_key"');
  });

  test('has secret key input field (password type)', () => {
    const c = content();
    expect(c).toContain('name="garage_secret_key"');
    expect(c).toContain('type="password"');
  });

  test('has region input field', () => {
    expect(content()).toContain('name="garage_region"');
  });

  test('has images bucket input field', () => {
    expect(content()).toContain('name="garage_bucket_images"');
  });

  test('has videos bucket input field', () => {
    expect(content()).toContain('name="garage_bucket_videos"');
  });

  test('has test connection button', () => {
    expect(content()).toContain('testGarageConnection()');
  });

  test('has testGarageConnection JavaScript function', () => {
    expect(content()).toContain('function testGarageConnection()');
  });

  test('testGarageConnection sends test_garage action', () => {
    const c = content();
    const start = c.indexOf('function testGarageConnection()');
    const section = c.substring(start, start + 800);
    expect(section).toContain("'test_garage'");
  });
});

// ─── 5. Settings processing in process_settings.php ──────────────────────────

test.describe('Garage S3 settings processing in process_settings.php', () => {
  const content = () => readFile('process_settings.php');

  test('has update_garage case', () => {
    expect(content()).toContain("case 'update_garage':");
  });

  test('has test_garage case', () => {
    expect(content()).toContain("case 'test_garage':");
  });

  test('test_garage is in json_actions list', () => {
    expect(content()).toContain("'test_garage'");
  });

  test('update_garage saves all Garage settings', () => {
    const c = content();
    const start = c.indexOf("case 'update_garage':");
    const section = c.substring(start, start + 1500);
    expect(section).toContain("'garage_endpoint'");
    expect(section).toContain("'garage_access_key'");
    expect(section).toContain("'garage_secret_key'");
    expect(section).toContain("'garage_region'");
    expect(section).toContain("'garage_bucket_images'");
    expect(section).toContain("'garage_bucket_videos'");
  });

  test('update_garage encrypts the secret key', () => {
    const c = content();
    const start = c.indexOf("case 'update_garage':");
    const section = c.substring(start, start + 1500);
    expect(section).toContain('encryptPassword(');
  });

  test('update_garage logs to audit trail', () => {
    const c = content();
    const start = c.indexOf("case 'update_garage':");
    const section = c.substring(start, start + 1500);
    expect(section).toContain('Auditor::log(');
  });

  test('update_garage redirects to garage tab', () => {
    const c = content();
    const start = c.indexOf("case 'update_garage':");
    const section = c.substring(start, start + 2000);
    expect(section).toContain('tab=garage');
  });

  test('test_garage creates GarageS3 instance and tests connection', () => {
    const c = content();
    const start = c.indexOf("case 'test_garage':");
    const section = c.substring(start, start + 1500);
    expect(section).toContain('new GarageS3(');
    expect(section).toContain('testConnection(');
  });

  test('test_garage decrypts stored secret key when not provided', () => {
    const c = content();
    const start = c.indexOf("case 'test_garage':");
    const section = c.substring(start, start + 1000);
    expect(section).toContain('decryptPassword(');
  });
});

// ─── 6. Encrypted settings in security.php ───────────────────────────────────

test.describe('Garage secret key encryption in security.php', () => {
  const content = () => readFile('security.php');

  test('garage_secret_key is in the encrypted settings keys list', () => {
    const c = content();
    const start = c.indexOf('function getEncryptedSettingKeys()');
    const section = c.substring(start, start + 600);
    expect(section).toContain("'garage_secret_key'");
  });
});

// ─── 7. File serving via serve_file.php ──────────────────────────────────────

test.describe('File serving via serve_file.php', () => {
  const content = () => readFile('serve_file.php');

  test('serve_file.php exists', () => {
    expect(fs.existsSync(path.join(BASE, 'serve_file.php'))).toBe(true);
  });

  test('includes cloud_config.php for Garage access', () => {
    expect(content()).toContain('cloud_config.php');
  });

  test('calls getGarageFileUrl for pre-signed URLs', () => {
    expect(content()).toContain('getGarageFileUrl(');
  });

  test('redirects to pre-signed URL when Garage is available', () => {
    expect(content()).toContain('Location:');
    expect(content()).toContain('302');
  });

  test('has path traversal prevention', () => {
    expect(content()).toContain("'..'");
  });

  test('falls back to local file system', () => {
    expect(content()).toContain('file_exists(');
    expect(content()).toContain('readfile(');
  });

  test('requires authentication', () => {
    expect(content()).toContain("'logged_in'");
  });
});

// ─── 8. Image helper Garage awareness ────────────────────────────────────────

test.describe('Image helper supports Garage S3 (lib/image_helper.php)', () => {
  const content = () => readFile('lib/image_helper.php');

  test('has parseGaragePath function', () => {
    expect(content()).toContain('function parseGaragePath(');
  });

  test('parseGaragePath handles garage:// URIs', () => {
    const c = content();
    const start = c.indexOf('function parseGaragePath(');
    const section = c.substring(start, start + 500);
    expect(section).toContain("'garage://'");
  });

  test('has getGarageServingUrl function', () => {
    expect(content()).toContain('function getGarageServingUrl(');
  });

  test('isValidImagePath accepts garage:// paths', () => {
    const c = content();
    const start = c.indexOf('function isValidImagePath(');
    const section = c.substring(start, start + 400);
    expect(section).toContain("'garage://'");
  });

  test('isValidImagePath accepts videos/ paths', () => {
    const c = content();
    const start = c.indexOf('function isValidImagePath(');
    const section = c.substring(start, start + 400);
    expect(section).toContain("'videos/'");
  });

  test('resolveProfileImage checks for garage:// paths', () => {
    const c = content();
    const start = c.indexOf('function resolveProfileImage(');
    const section = c.substring(start, start + 1500);
    expect(section).toContain("'garage://'");
    expect(section).toContain('parseGaragePath(');
  });
});
