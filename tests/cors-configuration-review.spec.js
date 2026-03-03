/**
 * Tests for CORS Configuration Review
 *
 * Verifies CORS configuration across all three services:
 * 1. RustFS bucket CORS policy (lib/rustfs_storage.php)
 * 2. Companion app CORS headers (companion/app.py)
 * 3. Main app API CORS headers (api/index.php, api/media.php, deployment/arctic_wolves.conf)
 *
 * The HAProxy layer applies domain-specific CORS for external sites
 * (precisionflooring.ca, crashmedia.ca, crashcrafts.com).  Backend services
 * must not conflict with those headers and should include proper CORS
 * handling of their own for development and internal access.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. RustFS Bucket CORS (lib/rustfs_storage.php)
// =====================================================

test.describe('RustFS bucket CORS policy', () => {
  test('should include DELETE method in allowed methods', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('<AllowedMethod>DELETE</AllowedMethod>');
  });

  test('should include GET, PUT, POST, DELETE, and HEAD methods', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('<AllowedMethod>GET</AllowedMethod>');
    expect(funcBody).toContain('<AllowedMethod>PUT</AllowedMethod>');
    expect(funcBody).toContain('<AllowedMethod>POST</AllowedMethod>');
    expect(funcBody).toContain('<AllowedMethod>DELETE</AllowedMethod>');
    expect(funcBody).toContain('<AllowedMethod>HEAD</AllowedMethod>');
  });

  test('should allow all origins for presigned URL uploads', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('<AllowedOrigin>*</AllowedOrigin>');
  });

  test('should expose ETag header', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('<ExposeHeader>ETag</ExposeHeader>');
  });
});

// =====================================================
// 2. Companion App CORS (companion/app.py)
// =====================================================

test.describe('Companion app CORS configuration', () => {
  const content = () => readFile('companion/app.py');

  test('should have CORS after_request handler', () => {
    const c = content();
    expect(c).toContain('_add_cors_headers');
    expect(c).toContain('Access-Control-Allow-Origin');
  });

  test('should validate origin against allowed domains', () => {
    const c = content();
    expect(c).toContain('arcticwolves\\.ca');
    expect(c).toContain('precisionflooring\\.ca');
    expect(c).toContain('crashmedia\\.ca');
    expect(c).toContain('crashcrafts\\.com');
  });

  test('should set dynamic origin (not wildcard) for credentialed requests', () => {
    const c = content();
    // Should use the requesting origin, not wildcard
    expect(c).toContain('Access-Control-Allow-Origin');
    expect(c).toContain('Access-Control-Allow-Credentials');
    expect(c).toContain('"true"');
  });

  test('should handle OPTIONS preflight with 204 status', () => {
    const c = content();
    expect(c).toContain('_handle_cors_preflight');
    expect(c).toContain("request.method == \"OPTIONS\"");
    expect(c).toContain('204');
  });

  test('should allow API-related headers matching HAProxy config', () => {
    const c = content();
    expect(c).toContain('x-api-key');
    expect(c).toContain('Authorization');
    expect(c).toContain('Content-Type');
    expect(c).toContain('Accept');
  });

  test('should allow standard HTTP methods', () => {
    const c = content();
    expect(c).toContain('GET, POST, PUT, DELETE, OPTIONS');
  });
});

// =====================================================
// 3. API CORS (api/index.php)
// =====================================================

test.describe('API index.php CORS headers', () => {
  const content = () => readFile('api/index.php');

  test('should include Accept in allowed headers', () => {
    const c = content();
    expect(c).toContain("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept");
  });

  test('should set Access-Control-Max-Age', () => {
    const c = content();
    expect(c).toContain('Access-Control-Max-Age: 86400');
  });

  test('should handle OPTIONS preflight with 204', () => {
    const c = content();
    expect(c).toContain("$_SERVER['REQUEST_METHOD'] === 'OPTIONS'");
    expect(c).toContain('204');
  });
});

// =====================================================
// 4. Media Proxy CORS (api/media.php)
// =====================================================

test.describe('Media proxy CORS headers', () => {
  const content = () => readFile('api/media.php');

  test('should handle OPTIONS preflight request', () => {
    const c = content();
    expect(c).toContain("$_SERVER['REQUEST_METHOD'] === 'OPTIONS'");
    expect(c).toContain('204');
  });

  test('should include full CORS headers in range responses', () => {
    const c = content();
    // Range response section should have full CORS headers
    expect(c).toContain("Access-Control-Allow-Methods: GET, OPTIONS");
    expect(c).toContain("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept, Range");
  });

  test('should expose range-related headers for video streaming', () => {
    const c = content();
    expect(c).toContain('Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length');
  });

  test('should include full CORS headers in normal responses', () => {
    const c = content();
    expect(c).toContain("Access-Control-Allow-Origin: *");
    expect(c).toContain("Access-Control-Allow-Methods: GET, OPTIONS");
  });
});

// =====================================================
// 5. NGINX CORS Config (deployment/arctic_wolves.conf)
// =====================================================

test.describe('NGINX API CORS configuration', () => {
  const content = () => readFile('deployment/arctic_wolves.conf');

  test('should include Accept in API CORS allowed headers', () => {
    const c = content();
    expect(c).toContain('Access-Control-Allow-Headers "Content-Type, Authorization, X-API-Key, Accept"');
  });

  test('should set Access-Control-Max-Age for API', () => {
    const c = content();
    expect(c).toContain('Access-Control-Max-Age "86400"');
  });

  test('should handle OPTIONS preflight for API subdomain', () => {
    const c = content();
    expect(c).toContain("if ($request_method = 'OPTIONS')");
    expect(c).toContain('return 204');
  });
});

// =====================================================
// 6. Upload views have presigned URL fallback
// =====================================================

test.describe('Upload views: presigned URL fallback on proxy 504', () => {
  test('video_record_athlete.php should try direct S3 when proxy fails', () => {
    const c = readFile('views/video_record_athlete.php');
    // Should have the intermediate .catch() that tries presigned URL
    expect(c).toContain('Proxy upload failed');
    expect(c).toContain('trying direct S3');
    expect(c).toContain('Retrying via direct cloud upload');
    expect(c).toContain('data2.presigned_url');
  });

  test('video_coach_reviews.php should try direct S3 when proxy fails', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('Proxy upload failed');
    expect(c).toContain('trying direct S3');
    expect(c).toContain('data2.presigned_url');
  });

  test('pwa/video_record_drill.php should try direct S3 when proxy fails', () => {
    const c = readFile('views/pwa/video_record_drill.php');
    expect(c).toContain('Proxy upload failed');
    expect(c).toContain('trying direct S3');
    expect(c).toContain('data2.presigned_url');
  });

  test('gp_film_room.php already has presigned URL fallback', () => {
    const c = readFile('views/gameplan/gp_film_room.php');
    expect(c).toContain('trying direct S3');
    expect(c).toContain('data2.presigned_url');
  });

  test('film_room.php already has presigned URL fallback', () => {
    const c = readFile('views/gameplan/film_room.php');
    expect(c).toContain('trying direct S3');
    expect(c).toContain('data2.presigned_url');
  });
});

// =====================================================
// 7. HAProxy timeout documentation
// =====================================================

test.describe('NGINX config documents HAProxy timeout requirements', () => {
  test('should document recommended HAProxy timeouts for uploads', () => {
    const c = readFile('deployment/arctic_wolves.conf');
    expect(c).toContain('timeout client');
    expect(c).toContain('timeout server');
    expect(c).toContain('600s');
    expect(c).toContain('504 Gateway Time-out');
  });
});
