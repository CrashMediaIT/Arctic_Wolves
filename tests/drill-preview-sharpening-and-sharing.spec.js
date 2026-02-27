import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for drill draw preview sharpening and public sharing features.
 * 
 * 1. Canvas rendering uses devicePixelRatio for high-DPI support (sharpening)
 * 2. Public drill share page exists and validates tokens
 * 3. Public practice plan share page exists and validates tokens
 * 4. Share token column added to drills table schema
 * 5. Share token generation/removal in process_drills.php
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. High-DPI Canvas Rendering (Preview Sharpening)
// =====================================================

test.describe('High-DPI canvas rendering for sharp previews', () => {
  test('drills_library.php uses devicePixelRatio for thumbnail canvas', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('devicePixelRatio');
    expect(content).toContain('canvas.width = cssWidth * dpr');
    expect(content).toContain('canvas.height = cssHeight * dpr');
    expect(content).toContain('ctx.scale(dpr, dpr)');
    expect(content).toContain("canvas.style.width = cssWidth + 'px'");
    expect(content).toContain("canvas.style.height = cssHeight + 'px'");
  });

  test('view_drill.php uses devicePixelRatio for drill view canvas', () => {
    const content = readFile('views/view_drill.php');
    expect(content).toContain('devicePixelRatio');
    expect(content).toContain('canvas.width = cssWidth * dpr');
    expect(content).toContain('canvas.height = cssHeight * dpr');
    expect(content).toContain('ctx.scale(dpr, dpr)');
  });

  test('practice_plans.php uses devicePixelRatio for drill thumbnails', () => {
    const content = readFile('views/practice_plans.php');
    expect(content).toContain('devicePixelRatio');
    expect(content).toContain('canvas.width = cssWidth * dpr');
    expect(content).toContain('ctx.scale(dpr, dpr)');
  });

  test('practice_create.php uses devicePixelRatio for drill selector thumbnails', () => {
    const content = readFile('views/practice_create.php');
    expect(content).toContain('devicePixelRatio');
    expect(content).toContain('canvas.width = cssWidth * dpr');
    expect(content).toContain('ctx.scale(dpr, dpr)');
  });

  test('view_practice_plan.php uses devicePixelRatio for drill canvases', () => {
    const content = readFile('views/view_practice_plan.php');
    expect(content).toContain('devicePixelRatio');
    expect(content).toContain('canvas.width = cssWidth * dpr');
    expect(content).toContain('ctx.scale(dpr, dpr)');
  });

  test('view_drill.php resize handler uses high-DPI dimensions', () => {
    const content = readFile('views/view_drill.php');
    // The resize handler should call initializeAndRender which uses DPR
    const resizeStart = content.indexOf("window.addEventListener('resize'");
    expect(resizeStart).toBeGreaterThan(-1);
    const resizeEnd = content.indexOf('});', resizeStart);
    const resizeHandler = content.substring(resizeStart, resizeEnd);
    expect(resizeHandler).toContain('initializeAndRender');
  });

  test('view_drill.php uses CSS dimensions for drawing, not raw canvas dimensions', () => {
    const content = readFile('views/view_drill.php');
    // The renderDrill function should use cssWidth/cssHeight, not canvas.width
    expect(content).toContain('drawViewRink(ctx, cssWidth, cssHeight, iceView)');
    expect(content).toContain('cssWidth / sourceWidth');
    expect(content).toContain('cssHeight / sourceHeight');
  });
});

// =====================================================
// 2. Public Drill Share Page
// =====================================================

test.describe('Public drill share page', () => {
  test('drill_share.php exists and is a standalone page', () => {
    const content = readFile('drill_share.php');
    // Should be a full HTML page, not a view fragment
    expect(content).toContain('<!DOCTYPE html>');
    expect(content).toContain('</html>');
    // Should NOT require login
    expect(content).not.toContain("header(\"Location: login.php\")");
    expect(content).not.toContain("$_SESSION['logged_in']");
  });

  test('drill_share.php validates share token format', () => {
    const content = readFile('drill_share.php');
    // Should validate token is 64 hex chars
    expect(content).toContain("preg_match('/^[a-f0-9]{64}$/'");
  });

  test('drill_share.php looks up drill by share_token', () => {
    const content = readFile('drill_share.php');
    expect(content).toContain('share_token');
    expect(content).toContain('WHERE d.share_token = ?');
  });

  test('drill_share.php includes ice_canvas.js for rink rendering', () => {
    const content = readFile('drill_share.php');
    expect(content).toContain('ice_canvas.js');
  });

  test('drill_share.php uses high-DPI canvas rendering', () => {
    const content = readFile('drill_share.php');
    expect(content).toContain('devicePixelRatio');
  });

  test('drill_share.php shows error for invalid token', () => {
    const content = readFile('drill_share.php');
    // Should have a "not found" or "expired" message
    expect(content).toMatch(/not found|expired|invalid/i);
  });

  test('drill_share.php includes required PHP dependencies', () => {
    const content = readFile('drill_share.php');
    expect(content).toContain("require_once __DIR__ . '/db_config.php'");
    expect(content).toContain("require_once __DIR__ . '/lib/image_helper.php'");
    expect(content).toContain("require_once __DIR__ . '/lib/site_branding.php'");
  });
});

// =====================================================
// 3. Public Practice Plan Share Page
// =====================================================

test.describe('Public practice plan share page', () => {
  test('practice_plan_share.php exists and is a standalone page', () => {
    const content = readFile('practice_plan_share.php');
    expect(content).toContain('<!DOCTYPE html>');
    expect(content).toContain('</html>');
    expect(content).not.toContain("header(\"Location: login.php\")");
    expect(content).not.toContain("$_SESSION['logged_in']");
  });

  test('practice_plan_share.php validates share token format', () => {
    const content = readFile('practice_plan_share.php');
    expect(content).toContain("preg_match('/^[a-f0-9]{64}$/'");
  });

  test('practice_plan_share.php looks up plan by share_token', () => {
    const content = readFile('practice_plan_share.php');
    expect(content).toContain('share_token');
    expect(content).toContain('WHERE pp.share_token = ?');
  });

  test('practice_plan_share.php fetches drills for the plan', () => {
    const content = readFile('practice_plan_share.php');
    expect(content).toContain('practice_plan_drills');
    expect(content).toContain('ORDER BY ppd.drill_order ASC');
  });

  test('practice_plan_share.php includes ice_canvas.js', () => {
    const content = readFile('practice_plan_share.php');
    expect(content).toContain('ice_canvas.js');
  });
});

// =====================================================
// 4. Database Schema - Share Token for Drills
// =====================================================

test.describe('Database schema updates', () => {
  test('drills table has share_token column', () => {
    const content = readFile('database_schema.sql');
    // Find the drills table definition
    const drillsTableStart = content.indexOf('CREATE TABLE IF NOT EXISTS `drills`');
    expect(drillsTableStart).toBeGreaterThan(-1);
    const drillsTableEnd = content.indexOf(') ENGINE=InnoDB', drillsTableStart);
    const drillsTable = content.substring(drillsTableStart, drillsTableEnd);
    expect(drillsTable).toContain('`share_token` VARCHAR(64) DEFAULT NULL');
    expect(drillsTable).toContain('idx_share_token');
  });
});

// =====================================================
// 5. Share Token Generation in process_drills.php
// =====================================================

test.describe('Drill share token management', () => {
  test('process_drills.php handles generate_share_token action', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain("action === 'generate_share_token'");
    expect(content).toContain('UPDATE drills SET share_token = ?');
  });

  test('process_drills.php handles remove_share_token action', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain("action === 'remove_share_token'");
    expect(content).toContain('SET share_token = NULL');
  });

  test('process_drills.php uses cryptographically secure token generation', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain('random_bytes');
  });

  test('process_drills.php requires permission for token operations', () => {
    const content = readFile('process_drills.php');
    // Both token actions should require permission
    const generateStart = content.indexOf("action === 'generate_share_token'");
    const generateSection = content.substring(generateStart, generateStart + 500);
    expect(generateSection).toContain('requirePermission');

    const removeStart = content.indexOf("action === 'remove_share_token'");
    const removeSection = content.substring(removeStart, removeStart + 500);
    expect(removeSection).toContain('requirePermission');
  });
});

// =====================================================
// 6. View Drill Share URL uses token-based sharing
// =====================================================

test.describe('Drill view share URL uses tokens', () => {
  test('view_drill.php generates share URL with token parameter', () => {
    const content = readFile('views/view_drill.php');
    expect(content).toContain('drill_share.php?token=');
    expect(content).toContain("drill['share_token']");
  });

  test('view_drill.php shows generate share link button when no token exists', () => {
    const content = readFile('views/view_drill.php');
    expect(content).toContain('Generate Share Link');
    expect(content).toContain("action\" value=\"generate_share_token\"");
  });

  test('view_drill.php shows remove share link button when token exists', () => {
    const content = readFile('views/view_drill.php');
    expect(content).toContain('Remove Share Link');
    expect(content).toContain("action\" value=\"remove_share_token\"");
  });
});

// =====================================================
// 7. Security: generateShareToken function in security.php
// =====================================================

test.describe('generateShareToken function', () => {
  test('security.php defines generateShareToken function', () => {
    const content = readFile('security.php');
    expect(content).toContain('function generateShareToken()');
    expect(content).toContain('bin2hex(random_bytes(32))');
  });
});
