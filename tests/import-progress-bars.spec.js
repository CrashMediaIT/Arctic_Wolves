import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for import progress bars:
 * 1. Drill JSON bulk import progress bar (views/drills_export_import.php)
 * 2. Practice plan JSON bulk import progress bar (views/practice_export_import.php)
 * 3. IHS drill import progress indicator (views/drills_import.php)
 * 4. IHS practice plan import progress indicator (views/practice_import.php)
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Drill JSON bulk import progress bar
// =====================================================

test.describe('Drill JSON import progress bar', () => {
  test('drills_export_import.php contains a progress bar container', () => {
    const content = readFile('views/drills_export_import.php');
    expect(content).toContain('ei-progress-container');
    expect(content).toContain('ei-progress-bar');
    expect(content).toContain('ei-progress-text');
    expect(content).toContain('ei-progress-detail');
  });

  test('drill import reads file client-side to count items', () => {
    const content = readFile('views/drills_export_import.php');
    expect(content).toContain('FileReader');
    expect(content).toContain('JSON.parse');
    expect(content).toContain('json.drills');
  });

  test('drill import shows item count in progress text', () => {
    const content = readFile('views/drills_export_import.php');
    expect(content).toContain("'Importing '");
    expect(content).toContain("' drill'");
  });

  test('drill import has animated progress bar with shimmer effect', () => {
    const content = readFile('views/drills_export_import.php');
    expect(content).toContain('ei-shimmer');
    expect(content).toContain('@keyframes ei-shimmer');
    expect(content).toContain('progressInterval');
  });

  test('drill import progress bar reaches 100% on completion', () => {
    const content = readFile('views/drills_export_import.php');
    expect(content).toContain("progressBar.style.width = '100%'");
    expect(content).toContain("'Import complete!'");
  });

  test('drill import hides progress bar on error', () => {
    const content = readFile('views/drills_export_import.php');
    expect(content).toContain("progressContainer.style.display = 'none'");
  });

  test('drill import mentions cloud storage in progress detail', () => {
    const content = readFile('views/drills_export_import.php');
    expect(content).toContain('cloud storage');
  });
});

// =====================================================
// 2. Practice plan JSON bulk import progress bar
// =====================================================

test.describe('Practice plan JSON import progress bar', () => {
  test('practice_export_import.php contains a progress bar container', () => {
    const content = readFile('views/practice_export_import.php');
    expect(content).toContain('ei-progress-container');
    expect(content).toContain('ei-progress-bar');
    expect(content).toContain('ei-progress-text');
    expect(content).toContain('ei-progress-detail');
  });

  test('practice plan import reads file client-side to count items', () => {
    const content = readFile('views/practice_export_import.php');
    expect(content).toContain('FileReader');
    expect(content).toContain('JSON.parse');
    expect(content).toContain('json.practice_plans');
  });

  test('practice plan import shows plan and drill count in progress text', () => {
    const content = readFile('views/practice_export_import.php');
    expect(content).toContain("' practice plan'");
    expect(content).toContain("' drill'");
  });

  test('practice plan import has animated progress bar with shimmer effect', () => {
    const content = readFile('views/practice_export_import.php');
    expect(content).toContain('ei-shimmer');
    expect(content).toContain('@keyframes ei-shimmer');
    expect(content).toContain('progressInterval');
  });

  test('practice plan import progress bar reaches 100% on completion', () => {
    const content = readFile('views/practice_export_import.php');
    expect(content).toContain("progressBar.style.width = '100%'");
    expect(content).toContain("'Import complete!'");
  });
});

// =====================================================
// 3. IHS drill import progress indicator
// =====================================================

test.describe('IHS drill import progress indicator', () => {
  test('drills_import.php contains an import progress container', () => {
    const content = readFile('views/drills_import.php');
    expect(content).toContain('importProgressContainer');
    expect(content).toContain('importProgressBar');
  });

  test('IHS drill import shows progress on form submit', () => {
    const content = readFile('views/drills_import.php');
    expect(content).toContain("importDrillForm");
    expect(content).toContain("addEventListener('submit'");
    expect(content).toContain("container.style.display = 'block'");
  });

  test('IHS drill import disables button during import', () => {
    const content = readFile('views/drills_import.php');
    expect(content).toContain('btn.disabled = true');
    expect(content).toContain('fa-spinner fa-spin');
  });

  test('IHS drill import has shimmer animation', () => {
    const content = readFile('views/drills_import.php');
    expect(content).toContain('@keyframes importShimmer');
    expect(content).toContain('importShimmer');
  });

  test('IHS drill import progress mentions cloud storage', () => {
    const content = readFile('views/drills_import.php');
    expect(content).toContain('cloud storage');
  });
});

// =====================================================
// 4. IHS practice plan import progress indicator
// =====================================================

test.describe('IHS practice plan import progress indicator', () => {
  test('practice_import.php contains an import progress container', () => {
    const content = readFile('views/practice_import.php');
    expect(content).toContain('importProgressContainer');
    expect(content).toContain('importProgressBar');
  });

  test('IHS practice plan import shows progress on form submit', () => {
    const content = readFile('views/practice_import.php');
    expect(content).toContain("importPlanForm");
    expect(content).toContain("addEventListener('submit'");
    expect(content).toContain("container.style.display = 'block'");
  });

  test('IHS practice plan import disables button during import', () => {
    const content = readFile('views/practice_import.php');
    expect(content).toContain('btn.disabled = true');
    expect(content).toContain('fa-spinner fa-spin');
  });

  test('IHS practice plan import has shimmer animation', () => {
    const content = readFile('views/practice_import.php');
    expect(content).toContain('@keyframes importShimmer');
    expect(content).toContain('importShimmer');
  });

  test('IHS practice plan import progress mentions cloud storage', () => {
    const content = readFile('views/practice_import.php');
    expect(content).toContain('cloud storage');
  });
});
