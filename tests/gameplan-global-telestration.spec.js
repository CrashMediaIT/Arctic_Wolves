/**
 * Global Telestration Overlay Tests
 *
 * Verifies:
 * 1. gameplan.php detects active pair and renders global telestration overlay
 * 2. gameplan_tv.php renders receive-only telestration overlay
 * 3. views/pwa/gameplan.php detects active pair and renders overlay
 * 4. Overlay has canvas, draw button, toolbar, tools, colors, clear
 * 5. Draw button toggles canvas pointer-events and toolbar visibility
 * 6. Canvas broadcasts telestration to process_video.php
 * 7. TV overlay polls api_tv_pair_state.php with include_telestration=1
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

/* ------------------------------------------------------------------ */
/*  1. gameplan.php — active pair detection                           */
/* ------------------------------------------------------------------ */
test.describe('gameplan.php global telestration overlay', () => {

  test('should detect active pair for telestration', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('gp_active_pair_id');
    expect(content).toContain("SELECT id, is_frozen FROM vr_device_pairs");
    expect(content).toContain("vr_device_pair_controllers");
  });

  test('should render gpTeleCanvas when active pair exists', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('id="gpTeleCanvas"');
    expect(content).toContain('position:fixed');
    expect(content).toContain('z-index:9000');
    expect(content).toContain('pointer-events:none');
  });

  test('should render floating draw button', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('id="gpTeleDrawBtn"');
    expect(content).toContain('fa-pencil');
  });

  test('should render toolbar with drawing tools', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('id="gpTeleToolbar"');
    expect(content).toContain('data-tool="freehand"');
    expect(content).toContain('data-tool="line"');
    expect(content).toContain('data-tool="arrow"');
  });

  test('should have color buttons', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('gp-tele-color');
    expect(content).toContain('data-color="#EF4444"');
    expect(content).toContain('data-color="#3B82F6"');
    expect(content).toContain('data-color="#10B981"');
  });

  test('should have line width slider', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('id="gpTeleWidth"');
    expect(content).toContain('type="range"');
  });

  test('should have clear button', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('id="gpTeleClear"');
    expect(content).toContain('fa-eraser');
  });

  test('draw button should toggle drawing mode on click', () => {
    const content = readFile('gameplan.php');
    // Toggle drawing mode on click
    expect(content).toContain("drawBtn.addEventListener('click'");
    expect(content).toContain("drawing = !drawing");
    expect(content).toContain("drawBtn.classList.toggle('active'");
    expect(content).toContain("toolbar.style.display = drawing ? 'block' : 'none'");
    expect(content).toContain("canvas.style.pointerEvents = drawing ? 'auto' : 'none'");
  });

  test('should broadcast telestration via process_video.php', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('broadcastTelestration');
    expect(content).toContain("action=broadcast_telestration");
    expect(content).toContain('canvas_data');
    expect(content).toContain("toDataURL('image/png')");
  });

  test('should debounce broadcasts', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('broadcastTimer');
    expect(content).toContain('setTimeout(');
    expect(content).toContain('500');
  });

  test('overlay is only rendered when active pair exists', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('if ($gp_active_pair_id > 0)');
  });

  test('should have drawStraight function for line/arrow tools', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('function drawStraight');
    expect(content).toContain('Math.atan2');
  });
});

/* ------------------------------------------------------------------ */
/*  2. gameplan_tv.php — TV receive overlay                           */
/* ------------------------------------------------------------------ */
test.describe('gameplan_tv.php global telestration receive overlay', () => {

  test('should render tvTeleCanvas when paired', () => {
    const content = readFile('gameplan_tv.php');
    expect(content).toContain('id="tvTeleCanvas"');
    expect(content).toContain('position:fixed');
    expect(content).toContain('z-index:9000');
    expect(content).toContain('pointer-events:none');
  });

  test('should poll api_tv_pair_state.php with include_telestration', () => {
    const content = readFile('gameplan_tv.php');
    expect(content).toContain('pollTelestration');
    expect(content).toContain('include_telestration=1');
    expect(content).toContain('api_tv_pair_state.php');
  });

  test('should render received telestration data onto canvas', () => {
    const content = readFile('gameplan_tv.php');
    expect(content).toContain('telestration_seq');
    expect(content).toContain('telestration_data');
    expect(content).toContain('new Image()');
    expect(content).toContain('drawImage');
  });

  test('should poll on interval', () => {
    const content = readFile('gameplan_tv.php');
    expect(content).toContain('setInterval(pollTelestration');
    // Initial poll
    expect(content).toContain('pollTelestration();');
  });

  test('overlay is only rendered when paired', () => {
    const content = readFile('gameplan_tv.php');
    // The canvas is inside a $tv_paired conditional
    expect(content).toContain('if ($tv_paired)');
  });
});

/* ------------------------------------------------------------------ */
/*  3. PWA gameplan — telestration overlay                            */
/* ------------------------------------------------------------------ */
test.describe('PWA gameplan telestration overlay', () => {

  test('should detect active pair', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('gp_pwa_active_pair_id');
    expect(content).toContain("SELECT id, is_frozen FROM vr_device_pairs");
  });

  test('should render gpTeleCanvas when active pair exists', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('id="gpTeleCanvas"');
    expect(content).toContain('position:fixed');
  });

  test('should render draw button and toolbar', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('id="gpTeleDrawBtn"');
    expect(content).toContain('id="gpTeleToolbar"');
    expect(content).toContain('data-tool="freehand"');
    expect(content).toContain('data-tool="arrow"');
  });

  test('should broadcast telestration via process_video.php', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('broadcastTelestration');
    expect(content).toContain("action=broadcast_telestration");
  });

  test('overlay is only rendered when active pair exists', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('if ($gp_pwa_active_pair_id > 0)');
  });

  test('PWA draw button bottom offset accounts for mobile nav bar', () => {
    const content = readFile('views/pwa/gameplan.php');
    // PWA overlay bottom should be 80px to clear mobile nav bar
    expect(content).toContain('bottom:80px');
  });
});

/* ------------------------------------------------------------------ */
/*  4. CSS classes are consistent across all three files               */
/* ------------------------------------------------------------------ */
test.describe('Consistent CSS class naming', () => {

  test('all three files use gp-tele-tool class', () => {
    const gp = readFile('gameplan.php');
    const pwa = readFile('views/pwa/gameplan.php');
    expect(gp).toContain('.gp-tele-tool');
    expect(pwa).toContain('.gp-tele-tool');
  });

  test('all three files use gp-tele-color class', () => {
    const gp = readFile('gameplan.php');
    const pwa = readFile('views/pwa/gameplan.php');
    expect(gp).toContain('.gp-tele-color');
    expect(pwa).toContain('.gp-tele-color');
  });

  test('draw button uses consistent ID across controller views', () => {
    const gp = readFile('gameplan.php');
    const pwa = readFile('views/pwa/gameplan.php');
    expect(gp).toContain('gpTeleDrawBtn');
    expect(pwa).toContain('gpTeleDrawBtn');
  });
});

/* ------------------------------------------------------------------ */
/*  5. TV viewer auto-update — immediate polling                      */
/* ------------------------------------------------------------------ */
test.describe('TV viewer auto-update polling', () => {

  test('should poll pair state immediately on load', () => {
    const content = readFile('gameplan_tv.php');
    expect(content).toContain('setInterval(pollPairState');
    expect(content).toContain('pollPairState();');
  });

  test('should detect controller page changes', () => {
    const content = readFile('gameplan_tv.php');
    expect(content).toContain('controller_page');
    expect(content).toContain('currentPage');
    expect(content).toContain('window.location.reload()');
  });

  test('should use seamless AJAX content swap to avoid flicker on page transitions', () => {
    const content = readFile('gameplan_tv.php');
    expect(content).toContain('swapContent');
    expect(content).toContain('tvTransitioning');
    expect(content).toContain("style.opacity = '0'");
    expect(content).toContain('partial=1');
  });

  test('should detect freeze state changes', () => {
    const content = readFile('gameplan_tv.php');
    expect(content).toContain('is_frozen');
    expect(content).toContain('isFrozen');
  });

  test('CSS should prevent white flash during page transitions', () => {
    const css = readFile('css/gameplan-tv.css');
    expect(css).toContain('html:has(body.tv-body)');
    expect(css).toContain('tvFadeIn');
    expect(css).toContain('.tv-content');
  });
});

/* ------------------------------------------------------------------ */
/*  6. Freeze button in telestration controls                         */
/* ------------------------------------------------------------------ */
test.describe('Freeze button in telestration controls', () => {

  test('gameplan.php should have freeze button in telestration toolbar', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('id="gpTeleFreeze"');
    expect(content).toContain('fa-snowflake');
    expect(content).toContain('toggle_freeze_pair');
  });

  test('gameplan.php should fetch is_frozen state with pair detection', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('gp_pair_is_frozen');
    expect(content).toContain('SELECT id, is_frozen FROM vr_device_pairs');
  });

  test('gameplan.php freeze button should toggle state via process_video.php', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('gpTeleFreeze');
    expect(content).toContain("action=toggle_freeze_pair");
    expect(content).toContain('gpFrozen = !gpFrozen');
  });

  test('gameplan.php freeze button should update icon on toggle', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain("'fas fa-play'");
    expect(content).toContain("'fas fa-snowflake'");
    expect(content).toContain("classList.toggle('active'");
  });

  test('PWA gameplan should have freeze button in telestration toolbar', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('id="gpTeleFreeze"');
    expect(content).toContain('fa-snowflake');
    expect(content).toContain('toggle_freeze_pair');
  });

  test('PWA gameplan should fetch is_frozen state with pair detection', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('gp_pwa_pair_is_frozen');
    expect(content).toContain('SELECT id, is_frozen FROM vr_device_pairs');
  });

  test('PWA freeze button should toggle state via process_video.php', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain('gpTeleFreeze');
    expect(content).toContain("action=toggle_freeze_pair");
    expect(content).toContain('gpFrozen = !gpFrozen');
  });
});
