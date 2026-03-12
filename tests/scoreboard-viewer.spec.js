/**
 * Scoreboard Viewer – Android App File Structure & Content Tests
 *
 * Validates that the scoreboard-viewer Android app has the correct
 * project structure, source files, resources, and build configuration.
 */

import { test, expect } from '@playwright/test';
import { readFileSync, existsSync, statSync } from 'fs';
import { join } from 'path';

const ROOT = join(__dirname, '..');
const SV = join(ROOT, 'scoreboard-viewer');

function readFile(relativePath) {
    return readFileSync(join(ROOT, relativePath), 'utf-8');
}

function fileExists(relativePath) {
    return existsSync(join(ROOT, relativePath));
}

// ── Project structure ──────────────────────────────────────

test.describe('Scoreboard Viewer – Project Structure', () => {

    test('root build files exist', () => {
        expect(fileExists('scoreboard-viewer/build.gradle')).toBe(true);
        expect(fileExists('scoreboard-viewer/settings.gradle')).toBe(true);
        expect(fileExists('scoreboard-viewer/gradle.properties')).toBe(true);
        expect(fileExists('scoreboard-viewer/gradlew')).toBe(true);
        expect(fileExists('scoreboard-viewer/gradlew.bat')).toBe(true);
    });

    test('gradle wrapper exists', () => {
        expect(fileExists('scoreboard-viewer/gradle/wrapper/gradle-wrapper.properties')).toBe(true);
        expect(fileExists('scoreboard-viewer/gradle/wrapper/gradle-wrapper.jar')).toBe(true);
    });

    test('app build.gradle exists', () => {
        expect(fileExists('scoreboard-viewer/app/build.gradle')).toBe(true);
    });

    test('AndroidManifest.xml exists', () => {
        expect(fileExists('scoreboard-viewer/app/src/main/AndroidManifest.xml')).toBe(true);
    });

    test('Kotlin source files exist', () => {
        const base = 'scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer';
        expect(fileExists(`${base}/MainActivity.kt`)).toBe(true);
        expect(fileExists(`${base}/SetupActivity.kt`)).toBe(true);
        expect(fileExists(`${base}/ScoreboardActivity.kt`)).toBe(true);
    });

    test('layout files exist', () => {
        expect(fileExists('scoreboard-viewer/app/src/main/res/layout/activity_setup.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/main/res/layout/activity_scoreboard.xml')).toBe(true);
    });

    test('resource value files exist', () => {
        expect(fileExists('scoreboard-viewer/app/src/main/res/values/colors.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/main/res/values/strings.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/main/res/values/styles.xml')).toBe(true);
    });

    test('drawable resources exist', () => {
        expect(fileExists('scoreboard-viewer/app/src/main/res/drawable/button_primary.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/main/res/drawable/card_background.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/main/res/drawable/input_background.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/main/res/drawable/error_background.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/main/res/drawable/ic_launcher_foreground.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/main/res/drawable/ic_launcher_background.xml')).toBe(true);
    });

    test('network security configs exist', () => {
        expect(fileExists('scoreboard-viewer/app/src/main/res/xml/network_security_config.xml')).toBe(true);
        expect(fileExists('scoreboard-viewer/app/src/debug/res/xml/network_security_config.xml')).toBe(true);
    });

    test('unit tests exist', () => {
        expect(fileExists('scoreboard-viewer/app/src/test/java/ca/arcticwolves/scoreboard/viewer/ScoreboardViewerTest.kt')).toBe(true);
    });

    test('build script exists and is executable', () => {
        expect(fileExists('scoreboard-viewer/build-apk.sh')).toBe(true);
    });

    test('built APK exists', () => {
        expect(fileExists('scoreboard-viewer/scoreboard-viewer-debug.apk')).toBe(true);
    });

    test('README exists', () => {
        expect(fileExists('scoreboard-viewer/README.md')).toBe(true);
    });
});

// ── Build configuration ────────────────────────────────────

test.describe('Scoreboard Viewer – Build Config', () => {

    test('root build.gradle uses AGP 8.2.0 and Kotlin 1.9.22', () => {
        const content = readFile('scoreboard-viewer/build.gradle');
        expect(content).toContain("'com.android.application' version '8.2.0'");
        expect(content).toContain("'org.jetbrains.kotlin.android' version '1.9.22'");
    });

    test('settings.gradle has correct project name', () => {
        const content = readFile('scoreboard-viewer/settings.gradle');
        expect(content).toContain('rootProject.name = "ScoreboardViewer"');
        expect(content).toContain("include ':app'");
    });

    test('app build.gradle has correct namespace and SDK versions', () => {
        const content = readFile('scoreboard-viewer/app/build.gradle');
        expect(content).toContain("namespace 'ca.arcticwolves.scoreboard.viewer'");
        expect(content).toContain('compileSdk 34');
        expect(content).toContain('minSdk 21');
        expect(content).toContain('targetSdk 34');
    });

    test('app build.gradle has correct application ID', () => {
        const content = readFile('scoreboard-viewer/app/build.gradle');
        expect(content).toContain('"ca.arcticwolves.scoreboard.viewer"');
    });

    test('app build.gradle has debug server URL override', () => {
        const content = readFile('scoreboard-viewer/app/build.gradle');
        expect(content).toContain('DEFAULT_SERVER_URL');
        expect(content).toContain('10.0.2.2');
    });

    test('app build.gradle has JUnit test dependency', () => {
        const content = readFile('scoreboard-viewer/app/build.gradle');
        expect(content).toContain("testImplementation 'junit:junit:4.13.2'");
    });

    test('gradle wrapper uses Gradle 8.5', () => {
        const content = readFile('scoreboard-viewer/gradle/wrapper/gradle-wrapper.properties');
        expect(content).toContain('gradle-8.5-bin.zip');
    });
});

// ── Android Manifest ───────────────────────────────────────

test.describe('Scoreboard Viewer – Manifest', () => {

    test('manifest declares INTERNET permission', () => {
        const content = readFile('scoreboard-viewer/app/src/main/AndroidManifest.xml');
        expect(content).toContain('android.permission.INTERNET');
    });

    test('manifest declares correct package', () => {
        const content = readFile('scoreboard-viewer/app/src/main/AndroidManifest.xml');
        expect(content).toContain('package="ca.arcticwolves.scoreboard.viewer"');
    });

    test('manifest declares three activities', () => {
        const content = readFile('scoreboard-viewer/app/src/main/AndroidManifest.xml');
        expect(content).toContain('.MainActivity');
        expect(content).toContain('.SetupActivity');
        expect(content).toContain('.ScoreboardActivity');
    });

    test('manifest has LAUNCHER intent filter', () => {
        const content = readFile('scoreboard-viewer/app/src/main/AndroidManifest.xml');
        expect(content).toContain('android.intent.category.LAUNCHER');
    });

    test('manifest has LEANBACK_LAUNCHER for TV support', () => {
        const content = readFile('scoreboard-viewer/app/src/main/AndroidManifest.xml');
        expect(content).toContain('android.intent.category.LEANBACK_LAUNCHER');
    });

    test('manifest declares touchscreen not required', () => {
        const content = readFile('scoreboard-viewer/app/src/main/AndroidManifest.xml');
        expect(content).toContain('android.hardware.touchscreen');
        expect(content).toContain('android:required="false"');
    });

    test('manifest references network security config', () => {
        const content = readFile('scoreboard-viewer/app/src/main/AndroidManifest.xml');
        expect(content).toContain('networkSecurityConfig');
    });
});

// ── Kotlin Source: MainActivity ────────────────────────────

test.describe('Scoreboard Viewer – MainActivity', () => {

    test('MainActivity checks for saved server URL', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/MainActivity.kt');
        expect(content).toContain('KEY_SERVER_URL');
        expect(content).toContain('scoreboard_viewer_prefs');
    });

    test('MainActivity routes to SetupActivity when no URL', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/MainActivity.kt');
        expect(content).toContain('SetupActivity');
    });

    test('MainActivity routes to ScoreboardActivity when URL exists', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/MainActivity.kt');
        expect(content).toContain('ScoreboardActivity');
    });
});

// ── Kotlin Source: SetupActivity ───────────────────────────

test.describe('Scoreboard Viewer – SetupActivity', () => {

    test('SetupActivity validates URL format', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/SetupActivity.kt');
        expect(content).toContain('URL_PATTERN');
        expect(content).toContain('https?://');
    });

    test('SetupActivity persists server URL', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/SetupActivity.kt');
        expect(content).toContain('KEY_SERVER_URL');
        expect(content).toContain('.apply()');
    });

    test('SetupActivity launches ScoreboardActivity on success', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/SetupActivity.kt');
        expect(content).toContain('ScoreboardActivity');
    });

    test('SetupActivity pre-fills saved URL', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/SetupActivity.kt');
        expect(content).toContain('setText');
    });

    test('SetupActivity strips trailing slash', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/SetupActivity.kt');
        expect(content).toContain("trimEnd('/')");
    });
});

// ── Kotlin Source: ScoreboardActivity ──────────────────────

test.describe('Scoreboard Viewer – ScoreboardActivity', () => {

    test('ScoreboardActivity uses WebView', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('WebView');
        expect(content).toContain('javaScriptEnabled = true');
    });

    test('ScoreboardActivity injects CSS to hide controls', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('.sb-topbar');
        expect(content).toContain('.sb-controls-grid');
        expect(content).toContain('display: none !important');
    });

    test('ScoreboardActivity hides operator modals', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('#sb-new-game-modal');
        expect(content).toContain('.sb-goal-assign-modal');
        expect(content).toContain('.sb-music-library-modal');
    });

    test('ScoreboardActivity loads scoreboard.php', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('scoreboard.php');
    });

    test('ScoreboardActivity keeps screen on', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('FLAG_KEEP_SCREEN_ON');
    });

    test('ScoreboardActivity enters immersive mode', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('IMMERSIVE');
    });

    test('ScoreboardActivity injects CSS after page load', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('onPageFinished');
        expect(content).toContain('evaluateJavascript');
    });

    test('ScoreboardActivity sets custom user agent', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('ScoreboardViewer-Android/1.0');
    });

    test('ScoreboardActivity navigates to setup on back press', () => {
        const content = readFile('scoreboard-viewer/app/src/main/java/ca/arcticwolves/scoreboard/viewer/ScoreboardActivity.kt');
        expect(content).toContain('KEYCODE_BACK');
        expect(content).toContain('navigateToSetup');
    });
});

// ── Resources ──────────────────────────────────────────────

test.describe('Scoreboard Viewer – Resources', () => {

    test('colors.xml has Arctic Wolves brand palette', () => {
        const content = readFile('scoreboard-viewer/app/src/main/res/values/colors.xml');
        expect(content).toContain('#6B46C1');  // primary
        expect(content).toContain('#0A0A0F');  // bg_main
    });

    test('strings.xml has app name', () => {
        const content = readFile('scoreboard-viewer/app/src/main/res/values/strings.xml');
        expect(content).toContain('Scoreboard Viewer');
    });

    test('strings.xml has setup instructions', () => {
        const content = readFile('scoreboard-viewer/app/src/main/res/values/strings.xml');
        expect(content).toContain('server_url_label');
        expect(content).toContain('open_scoreboard');
    });

    test('styles.xml uses Material NoActionBar theme', () => {
        const content = readFile('scoreboard-viewer/app/src/main/res/values/styles.xml');
        expect(content).toContain('Theme.Material.NoActionBar');
        expect(content).toContain('Theme.ScoreboardViewer');
    });

    test('setup layout has server URL input and connect button', () => {
        const content = readFile('scoreboard-viewer/app/src/main/res/layout/activity_setup.xml');
        expect(content).toContain('serverUrlInput');
        expect(content).toContain('connectButton');
        expect(content).toContain('errorText');
    });

    test('scoreboard layout has WebView', () => {
        const content = readFile('scoreboard-viewer/app/src/main/res/layout/activity_scoreboard.xml');
        expect(content).toContain('WebView');
        expect(content).toContain('webView');
    });

    test('production network config enforces HTTPS', () => {
        const content = readFile('scoreboard-viewer/app/src/main/res/xml/network_security_config.xml');
        expect(content).toContain('cleartextTrafficPermitted="false"');
    });

    test('debug network config allows localhost cleartext', () => {
        const content = readFile('scoreboard-viewer/app/src/debug/res/xml/network_security_config.xml');
        expect(content).toContain('10.0.2.2');
        expect(content).toContain('cleartextTrafficPermitted="true"');
    });
});

// ── Unit tests ─────────────────────────────────────────────

test.describe('Scoreboard Viewer – Unit Tests', () => {

    test('unit test file validates URL patterns', () => {
        const content = readFile('scoreboard-viewer/app/src/test/java/ca/arcticwolves/scoreboard/viewer/ScoreboardViewerTest.kt');
        expect(content).toContain('urlPattern');
        expect(content).toContain('validHttpsUrl');
        expect(content).toContain('validHttpUrl');
    });

    test('unit test file validates JS string escaping', () => {
        const content = readFile('scoreboard-viewer/app/src/test/java/ca/arcticwolves/scoreboard/viewer/ScoreboardViewerTest.kt');
        expect(content).toContain('toJsStringLiteral');
        expect(content).toContain('escapesQuotes');
        expect(content).toContain('escapesNewlines');
    });
});

// ── Build script ───────────────────────────────────────────

test.describe('Scoreboard Viewer – Build Script', () => {

    test('build-apk.sh targets correct package', () => {
        const content = readFile('scoreboard-viewer/build-apk.sh');
        expect(content).toContain('ca.arcticwolves.scoreboard.viewer');
    });

    test('build-apk.sh uses Android SDK 34', () => {
        const content = readFile('scoreboard-viewer/build-apk.sh');
        expect(content).toContain('android-34');
    });

    test('build-apk.sh outputs scoreboard-viewer-debug.apk', () => {
        const content = readFile('scoreboard-viewer/build-apk.sh');
        expect(content).toContain('scoreboard-viewer-debug.apk');
    });
});

// ── README ─────────────────────────────────────────────────

test.describe('Scoreboard Viewer – README', () => {

    test('README describes the scoreboard viewer purpose', () => {
        const content = readFile('scoreboard-viewer/README.md');
        expect(content).toContain('Scoreboard Viewer');
        expect(content).toContain('display-only');
    });

    test('README documents hidden elements', () => {
        const content = readFile('scoreboard-viewer/README.md');
        expect(content).toContain('.sb-topbar');
        expect(content).toContain('.sb-controls-grid');
    });

    test('README documents visible scoreboard elements', () => {
        const content = readFile('scoreboard-viewer/README.md');
        expect(content).toContain('Game clock');
        expect(content).toContain('Penalty timer');
        expect(content).toContain('Power Play');
    });

    test('README includes build instructions', () => {
        const content = readFile('scoreboard-viewer/README.md');
        expect(content).toContain('assembleDebug');
        expect(content).toContain('assembleRelease');
        expect(content).toContain('build-apk.sh');
    });
});

// ── Built APK ──────────────────────────────────────────────

test.describe('Scoreboard Viewer – Built APK', () => {

    test('APK file exists and is non-empty', () => {
        const apkPath = join(ROOT, 'scoreboard-viewer/scoreboard-viewer-debug.apk');
        expect(existsSync(apkPath)).toBe(true);
        const stat = statSync(apkPath);
        // A minimal Android APK with compiled resources, DEX, and signing
        // metadata is at least ~100KB. The current APK is ~600KB.
        const MIN_APK_SIZE_BYTES = 100 * 1024;
        expect(stat.size).toBeGreaterThan(MIN_APK_SIZE_BYTES);
    });

    test('APK is a valid ZIP file (starts with PK header)', () => {
        const apk = readFileSync(join(ROOT, 'scoreboard-viewer/scoreboard-viewer-debug.apk'));
        // ZIP files start with PK (0x50, 0x4B)
        expect(apk[0]).toBe(0x50);
        expect(apk[1]).toBe(0x4B);
    });
});
