/**
 * Tests for YouTube-Style Custom Video Player Controls
 *
 * Verifies:
 * 1. js/hls-player.js builds custom controls with all required elements
 * 2. Resolution picker with Auto bandwidth detection
 * 3. CSS uses application theme color variables
 * 4. Controls auto-hide/show, keyboard shortcuts, fullscreen
 * 5. Native controls are removed in favor of custom controls
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Custom controls DOM structure
// =====================================================

test.describe('Custom video player controls structure', () => {
  const content = () => readFile('js/hls-player.js');

  test('should create aw-player-controls container', () => {
    const c = content();
    expect(c).toContain("controls.className = 'aw-player-controls'");
  });

  test('should create progress bar with buffered, played, and thumb elements', () => {
    const c = content();
    expect(c).toContain("'aw-progress-bar'");
    expect(c).toContain("'aw-progress-buffered'");
    expect(c).toContain("'aw-progress-played'");
    expect(c).toContain("'aw-progress-thumb'");
  });

  test('should create play/pause button', () => {
    const c = content();
    expect(c).toContain('aw-play-btn');
    expect(c).toContain("playBtn.title = 'Play'");
  });

  test('should create volume button and slider', () => {
    const c = content();
    expect(c).toContain('aw-volume-btn');
    expect(c).toContain('aw-volume-slider');
    expect(c).toContain("volumeSlider.type = 'range'");
  });

  test('should create time display element', () => {
    const c = content();
    expect(c).toContain("'aw-time-display'");
    expect(c).toContain("'0:00 / 0:00'");
  });

  test('should create fullscreen button', () => {
    const c = content();
    expect(c).toContain('aw-fullscreen-btn');
    expect(c).toContain("fullscreenBtn.title = 'Full screen'");
  });

  test('should create gradient overlay for controls visibility', () => {
    const c = content();
    expect(c).toContain("'aw-controls-gradient'");
  });

  test('should create big play button overlay', () => {
    const c = content();
    expect(c).toContain("'aw-big-play'");
  });

  test('should remove native controls from video element', () => {
    const c = content();
    expect(c).toContain("video.removeAttribute('controls')");
  });

  test('should add playsinline attribute for mobile', () => {
    const c = content();
    expect(c).toContain("video.setAttribute('playsinline', '')");
  });
});

// =====================================================
// 2. Resolution picker and auto bandwidth detection
// =====================================================

test.describe('Resolution picker and auto bandwidth detection', () => {
  const content = () => readFile('js/hls-player.js');

  test('should create settings button with gear icon', () => {
    const c = content();
    expect(c).toContain('aw-settings-btn');
    expect(c).toContain("settingsBtn.title = 'Settings'");
  });

  test('should create settings panel for quality selection', () => {
    const c = content();
    expect(c).toContain("'aw-settings-panel'");
  });

  test('should include Auto quality option with data-level -1', () => {
    const c = content();
    expect(c).toContain("autoItem.dataset.level = '-1'");
    expect(c).toContain("autoLabel.textContent = 'Auto'");
  });

  test('should display auto-detected resolution label', () => {
    const c = content();
    expect(c).toContain("'aw-qi-auto-res'");
    expect(c).toContain("autoSublabel.textContent = lvl.height + 'p'");
  });

  test('should sort quality levels highest first', () => {
    const c = content();
    expect(c).toContain('sortedLevels.sort');
    expect(c).toContain('b.height - a.height');
  });

  test('should listen for LEVEL_SWITCHED to update auto resolution display', () => {
    const c = content();
    expect(c).toContain('Hls.Events.LEVEL_SWITCHED');
  });

  test('should unlock auto when Auto is selected (level -1)', () => {
    const c = content();
    expect(c).toContain('hls.currentLevel = -1');
    expect(c).toContain('hls.nextLevel = -1');
  });

  test('should lock to specific level when manual quality is selected', () => {
    const c = content();
    expect(c).toContain('hls.currentLevel = level');
  });

  test('should highlight current playing level in settings panel', () => {
    const c = content();
    expect(c).toContain('aw-qi-current');
  });

  test('settings panel should only be created when multiple HLS levels exist', () => {
    const c = content();
    expect(c).toContain('levels && levels.length >= 2 && hls');
  });
});

// =====================================================
// 3. Controls behavior: auto-hide, seek, keyboard
// =====================================================

test.describe('Controls behavior and interactivity', () => {
  const content = () => readFile('js/hls-player.js');

  test('should auto-hide controls after timeout when playing', () => {
    const c = content();
    expect(c).toContain('scheduleHide');
    expect(c).toContain('setTimeout(hideControls');
  });

  test('should show controls on mouse move', () => {
    const c = content();
    expect(c).toContain("container.addEventListener('mousemove'");
    expect(c).toContain('showControls');
  });

  test('should support progress bar seeking via mousedown', () => {
    const c = content();
    expect(c).toContain("progressWrap.addEventListener('mousedown'");
    expect(c).toContain('seekFromEvent');
  });

  test('should display hover time tooltip on progress bar', () => {
    const c = content();
    expect(c).toContain("'aw-progress-hover-time'");
    expect(c).toContain('progressHoverTime.textContent');
  });

  test('should support keyboard shortcuts (space, k, arrows, m, f)', () => {
    const c = content();
    expect(c).toContain("case ' ':");
    expect(c).toContain("case 'k':");
    expect(c).toContain("case 'ArrowLeft':");
    expect(c).toContain("case 'ArrowRight':");
    expect(c).toContain("case 'm':");
    expect(c).toContain("case 'f':");
  });

  test('should toggle fullscreen via requestFullscreen', () => {
    const c = content();
    expect(c).toContain('requestFullscreen');
    expect(c).toContain('exitFullscreen');
  });

  test('should handle video ended state with replay icon', () => {
    const c = content();
    expect(c).toContain("video.addEventListener('ended'");
    expect(c).toContain("playBtn.title = 'Replay'");
  });

  test('should update buffer progress on video progress event', () => {
    const c = content();
    expect(c).toContain("video.addEventListener('progress'");
    expect(c).toContain('video.buffered');
    expect(c).toContain('progressBuffered.style.width');
  });

  test('should have time formatter that handles hours', () => {
    const c = content();
    expect(c).toContain('function _formatTime');
    expect(c).toContain('Math.floor(secs / 3600)');
  });
});

// =====================================================
// 4. CSS uses application theme color variables
// =====================================================

test.describe('CSS theme integration for video player controls', () => {
  const content = () => readFile('views/shared_styles.css');

  test('progress bar played state should use --primary-light', () => {
    const c = content();
    expect(c).toContain('.aw-progress-played');
    expect(c).toMatch(/\.aw-progress-played[\s\S]*?var\(--primary-light/);
  });

  test('progress thumb should use --primary-light', () => {
    const c = content();
    expect(c).toContain('.aw-progress-thumb');
    expect(c).toMatch(/\.aw-progress-thumb[\s\S]*?var\(--primary-light/);
  });

  test('settings panel should use --bg-card background', () => {
    const c = content();
    expect(c).toMatch(/\.aw-settings-panel[\s\S]*?var\(--bg-card/);
  });

  test('settings panel border should use --border', () => {
    const c = content();
    expect(c).toMatch(/\.aw-settings-panel[\s\S]*?var\(--border/);
  });

  test('quality items should use --text-secondary for text color', () => {
    const c = content();
    // The .aw-quality-item rule should reference --text-secondary
    const itemSection = c.substring(c.indexOf('.aw-quality-item {'));
    expect(itemSection).toContain('var(--text-secondary');
  });

  test('active quality item should use --primary-light', () => {
    const c = content();
    expect(c).toMatch(/\.aw-quality-item\.active[\s\S]*?var\(--primary-light/);
  });

  test('time display should use --text-secondary', () => {
    const c = content();
    expect(c).toMatch(/\.aw-time-display[\s\S]*?var\(--text-secondary/);
  });

  test('button text color should use --text-primary', () => {
    const c = content();
    expect(c).toMatch(/\.aw-ctrl-btn[\s\S]*?var\(--text-primary/);
  });

  test('button hover should use primary purple tint', () => {
    const c = content();
    expect(c).toMatch(/\.aw-ctrl-btn:hover[\s\S]*?rgba\(107,\s*70,\s*193/);
  });

  test('hover time tooltip should use --bg-card and --border', () => {
    const c = content();
    const hoverSection = c.substring(c.indexOf('.aw-progress-hover-time'));
    expect(hoverSection).toContain('var(--bg-card');
    expect(hoverSection).toContain('var(--border');
  });

  test('auto-resolution sublabel should use --text-muted', () => {
    const c = content();
    expect(c).toMatch(/\.aw-qi-auto-res[\s\S]*?var\(--text-muted/);
  });

  test('gradient overlay should use app background color', () => {
    const c = content();
    // rgba(10,10,15) matches --bg-main: #0A0A0F
    expect(c).toMatch(/\.aw-controls-gradient[\s\S]*?rgba\(10,10,15/);
  });
});

// =====================================================
// 5. Cleanup and event listener management
// =====================================================

test.describe('Controls cleanup and resource management', () => {
  const content = () => readFile('js/hls-player.js');

  test('should store _cleanup function on controls element', () => {
    const c = content();
    expect(c).toContain('controls._cleanup');
  });

  test('cleanup should remove document event listeners', () => {
    const c = content();
    const cleanupStart = c.indexOf('controls._cleanup = function()');
    const cleanupEnd = c.indexOf('};', cleanupStart);
    const cleanupFunc = c.substring(cleanupStart, cleanupEnd);
    expect(cleanupFunc).toContain("document.removeEventListener('click', _closeHandler)");
    expect(cleanupFunc).toContain("document.removeEventListener('fullscreenchange'");
    expect(cleanupFunc).toContain("document.removeEventListener('keydown'");
  });

  test('should remove existing controls before building new ones', () => {
    const c = content();
    expect(c).toContain("container.querySelector('.aw-player-controls')");
    expect(c).toContain('existing._cleanup');
    expect(c).toContain('existing.remove()');
  });

  test('should also clean up legacy aw-quality-wrapper if present', () => {
    const c = content();
    expect(c).toContain("container.querySelector('.aw-quality-wrapper')");
    expect(c).toContain('legacyQuality._closeHandler');
  });

  test('should clear hide timeout on cleanup', () => {
    const c = content();
    expect(c).toContain('clearTimeout(hideTimeout)');
  });
});
