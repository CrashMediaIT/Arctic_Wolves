/**
 * Tests for RustFS Media Proxy and URL Resolution
 *
 * Verifies that:
 * 1. api/media.php proxy endpoint exists and validates inputs
 * 2. CSP media-src allows https: for RustFS video playback
 * 3. resolveRustfsUrl helper exists and is used in views
 * 4. persistUploadedFile returns proxy URLs instead of direct S3 URLs
 * 5. All key views resolve media URLs through the proxy
 * 6. isValidImagePath accepts proxy URLs
 * 7. site_branding resolves logo/favicon URLs through proxy
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

function fileExists(relativePath) {
    return fs.existsSync(path.join(ROOT, relativePath));
}

// =====================================================
// 1. Media proxy endpoint exists
// =====================================================
test.describe('api/media.php proxy endpoint', () => {
    test('api/media.php file exists', () => {
        expect(fileExists('api/media.php')).toBeTruthy();
    });

    test('media proxy requires object key parameter', () => {
        const content = readFile('api/media.php');
        expect(content).toContain("$_GET['key']");
    });

    test('media proxy prevents path traversal attacks', () => {
        const content = readFile('api/media.php');
        expect(content).toMatch(/\.\./);
    });

    test('media proxy loads RustFS settings from database', () => {
        const content = readFile('api/media.php');
        expect(content).toContain('getRustFSSettings');
    });

    test('media proxy signs requests with S3 authentication', () => {
        const content = readFile('api/media.php');
        expect(content).toContain('signRustFSRequest');
    });

    test('media proxy sends proper content-type headers', () => {
        const content = readFile('api/media.php');
        expect(content).toContain('Content-Type');
        expect(content).toContain('image/jpeg');
        expect(content).toContain('video/mp4');
    });

    test('media proxy includes cache headers', () => {
        const content = readFile('api/media.php');
        expect(content).toContain('Cache-Control');
    });
});

// =====================================================
// 2. CSP allows https media sources
// =====================================================
test.describe('CSP media-src allows HTTPS for RustFS videos', () => {
    test('media-src includes https: directive', () => {
        const content = readFile('security.php');
        expect(content).toMatch(/media-src\s+'self'\s+blob:\s+mediastream:\s+https:/);
    });

    test('img-src still allows https: (unchanged)', () => {
        const content = readFile('security.php');
        expect(content).toMatch(/img-src\s+'self'\s+data:\s+https:/);
    });
});

// =====================================================
// 3. resolveRustfsUrl helper function
// =====================================================
test.describe('resolveRustfsUrl helper in image_helper.php', () => {
    test('resolveRustfsUrl function is defined', () => {
        const content = readFile('lib/image_helper.php');
        expect(content).toContain('function resolveRustfsUrl(');
    });

    test('resolveRustfsUrl accepts PDO and URL parameters', () => {
        const content = readFile('lib/image_helper.php');
        expect(content).toMatch(/function resolveRustfsUrl\(\$pdo,\s*\$url\)/);
    });

    test('resolveRustfsUrl returns null for empty input', () => {
        const content = readFile('lib/image_helper.php');
        expect(content).toContain("if (empty($url)) return null");
    });

    test('resolveRustfsUrl passes through existing proxy URLs', () => {
        const content = readFile('lib/image_helper.php');
        expect(content).toContain("strpos($url, 'api/media.php')");
    });

    test('resolveRustfsUrl converts RustFS URLs to proxy URLs', () => {
        const content = readFile('lib/image_helper.php');
        expect(content).toContain("'api/media.php?key='");
    });

    test('resolveRustfsUrl uses getRustFSBaseUrl to detect RustFS URLs', () => {
        const content = readFile('lib/image_helper.php');
        expect(content).toContain('getRustFSBaseUrl');
    });
});

// =====================================================
// 4. persistUploadedFile returns proxy URLs
// =====================================================
test.describe('persistUploadedFile returns proxy-style URLs', () => {
    test('persistUploadedFile stores proxy URL in rustfs_url', () => {
        const content = readFile('cloud_config.php');
        expect(content).toMatch(/\$result\['rustfs_url'\]\s*=\s*\$proxy_url/);
    });

    test('persistUploadedFile builds proxy URL with object key', () => {
        const content = readFile('cloud_config.php');
        expect(content).toContain("'api/media.php?key=' . rawurlencode($object_key)");
    });

    test('persistUploadedFile preserves direct URL for server-side use', () => {
        const content = readFile('cloud_config.php');
        expect(content).toContain("$result['direct_url']");
    });

    test('persistUploadedFile stores object key', () => {
        const content = readFile('cloud_config.php');
        expect(content).toContain("$result['object_key']");
    });
});

// =====================================================
// 5. isValidImagePath accepts proxy URLs
// =====================================================
test.describe('isValidImagePath accepts proxy URLs', () => {
    test('isValidImagePath accepts api/media.php URLs', () => {
        const content = readFile('lib/image_helper.php');
        const fnMatch = content.match(/function isValidImagePath[\s\S]*?return false;\s*\}/);
        expect(fnMatch).toBeTruthy();
        expect(fnMatch[0]).toContain('api/media.php');
    });
});

// =====================================================
// 6. site_branding resolves URLs through proxy
// =====================================================
test.describe('site_branding resolves RustFS URLs', () => {
    test('site_branding includes image_helper', () => {
        const content = readFile('lib/site_branding.php');
        expect(content).toContain("require_once __DIR__ . '/image_helper.php'");
    });

    test('getSiteLogoUrl resolves through resolveRustfsUrl', () => {
        const content = readFile('lib/site_branding.php');
        expect(content).toContain('resolveRustfsUrl');
    });

    test('getSiteFaviconUrl resolves through resolveRustfsUrl', () => {
        const content = readFile('lib/site_branding.php');
        const faviconFn = content.substring(content.indexOf('function getSiteFaviconUrl'));
        expect(faviconFn).toContain('resolveRustfsUrl');
    });
});

// =====================================================
// 7. Key views use resolveRustfsUrl for media rendering
// =====================================================
test.describe('views resolve media URLs through proxy', () => {
    const viewsWithResolver = [
        'views/profile.php',
        'views/admin_users.php',
        'views/admin_system_tools.php',
        'views/admin_theme_settings.php',
        'views/video_drill_review.php',
        'views/merchandise_products.php',
        'views/practice_plans.php',
        'views/evaluations_skills.php',
        'views/shop.php',
        'views/inventory_management.php',
        'views/admin_categories.php',
        'views/library_workouts.php',
        'views/gameplan/film_room.php',
        'views/gameplan/gp_film_room.php',
        'views/gameplan/video_review.php',
        'views/gameplan/gp_video_review.php',
        'views/gameplan/my_clips.php',
        'views/gameplan/gp_my_clips.php',
    ];

    for (const viewPath of viewsWithResolver) {
        test(`${viewPath} uses resolveRustfsUrl`, () => {
            const content = readFile(viewPath);
            expect(content).toContain('resolveRustfsUrl');
        });
    }

    test('profile.php resolves profile_image', () => {
        const content = readFile('views/profile.php');
        expect(content).toContain("resolveRustfsUrl($pdo, $userData['profile_image'])");
    });

    test('admin_system_tools.php resolves theme URL keys', () => {
        const content = readFile('views/admin_system_tools.php');
        expect(content).toContain("resolveRustfsUrl($pdo, $theme_settings[$uk])");
    });

    test('video_drill_review.php resolves video_url for playback', () => {
        const content = readFile('views/video_drill_review.php');
        expect(content).toContain("resolveRustfsUrl($pdo, $video['video_url']");
    });

    test('film_room.php resolves file_path for video source', () => {
        const content = readFile('views/gameplan/film_room.php');
        expect(content).toContain("resolveRustfsUrl($pdo, $vr_edit_source['file_path'])");
    });
});

// =====================================================
// 8. Top-level shop pages resolve media URLs
// =====================================================
test.describe('shop pages resolve media URLs through proxy', () => {
    const shopPages = [
        'shop_product.php',
        'shop.php',
        'shop_cart.php',
        'shop_checkout.php',
    ];

    for (const page of shopPages) {
        test(`${page} includes image_helper`, () => {
            const content = readFile(page);
            expect(content).toContain('image_helper.php');
        });

        test(`${page} uses resolveRustfsUrl`, () => {
            const content = readFile(page);
            expect(content).toContain('resolveRustfsUrl');
        });
    }
});

// =====================================================
// 9. PWA views resolve media URLs
// =====================================================
test.describe('PWA views resolve media URLs through proxy', () => {
    const pwaViews = [
        'views/pwa/profile.php',
        'views/pwa/shop.php',
        'views/pwa/athlete_detail.php',
    ];

    for (const pwaView of pwaViews) {
        test(`${pwaView} uses resolveRustfsUrl`, () => {
            const content = readFile(pwaView);
            expect(content).toContain('resolveRustfsUrl');
        });
    }
});

// =====================================================
// 10. Video upload stores proxy-compatible URLs
// =====================================================
test.describe('video upload stores proxy-compatible URLs', () => {
    test('process_video.php uses persistUploadedFile for coach videos', () => {
        const content = readFile('process_video.php');
        expect(content).toContain("persistUploadedFile($pdo, $file['tmp_name'], 'videos/coach'");
    });

    test('process_video.php uses persistUploadedFile for athlete videos', () => {
        const content = readFile('process_video.php');
        expect(content).toContain("persistUploadedFile($pdo, $file['tmp_name'], 'videos/athlete/' . $athlete_folder");
    });

    test('process_video.php uses persistUploadedFile for gameplan sources', () => {
        const content = readFile('process_video.php');
        expect(content).toContain("persistUploadedFile($pdo, $file['tmp_name'], 'videos/gameplan'");
    });

    test('process_video.php stores rustfs_url from persist result', () => {
        const content = readFile('process_video.php');
        expect(content).toContain("$persist['rustfs_url']");
    });
});

// =====================================================
// 11. Theme upload stores proxy-compatible URLs
// =====================================================
test.describe('theme upload stores proxy-compatible URLs', () => {
    test('process_theme.php handleFileUpload returns rustfs_url', () => {
        const content = readFile('process_theme.php');
        expect(content).toContain("'url' => $persist['rustfs_url']");
    });

    test('process_theme.php saveThemeUploadResult stores URL', () => {
        const content = readFile('process_theme.php');
        expect(content).toContain("updateThemeSetting($pdo, $setting_name, $upload_result['url'])");
    });
});
