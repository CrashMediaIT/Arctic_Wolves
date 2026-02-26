/**
 * Tests for Video Upload, Browser Confirm, and Select All Fixes
 *
 * Verifies fixes for:
 * 1. Video upload timeout — set_time_limit(0) in process_video.php
 * 2. Video upload file size limit — 10GB client-side across all views
 * 3. MKV format support — backend validator and frontend hints
 * 4. alert() replaced with showToast() in video upload views
 * 5. confirm() replaced with showConfirmModal() in app.js and drill_designer.js
 * 6. Select All button fix in accounting_products.php
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Video upload timeout — set_time_limit(0)
// =====================================================

test.describe('Video upload timeout prevention', () => {
  test('process_video.php should set unlimited execution time', () => {
    const content = readFile('process_video.php');
    expect(content).toContain('set_time_limit(0)');
  });
});

// =====================================================
// 2. Video upload file size limit — 10GB client-side
// =====================================================

test.describe('Video upload client-side limit is 10GB', () => {
  test('video_record_athlete.php JS limit should be 10GB', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('10 * 1024 * 1024 * 1024');
    expect(content).not.toContain('500 * 1024 * 1024');
  });

  test('video_record_drill.php JS limit should be 10GB', () => {
    const content = readFile('views/video_record_drill.php');
    expect(content).toContain('10 * 1024 * 1024 * 1024');
    expect(content).not.toContain('500 * 1024 * 1024');
  });

  test('gp_film_room.php JS limit should be 10GB', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('10 * 1024 * 1024 * 1024');
    expect(content).not.toContain('500 * 1024 * 1024');
  });

  test('film_room.php JS limit should be 10GB', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('10 * 1024 * 1024 * 1024');
    expect(content).not.toContain('500 * 1024 * 1024');
  });

  test('file hint text should show 10GB', () => {
    const athlete = readFile('views/video_record_athlete.php');
    expect(athlete).toContain('max 10GB');

    const coach = readFile('views/video_coach_reviews.php');
    expect(coach).toContain('Max 10GB');

    const drill = readFile('views/video_record_drill.php');
    expect(drill).toContain('Max 10GB');
  });
});

// =====================================================
// 3. MKV format support
// =====================================================

test.describe('MKV video format is supported', () => {
  test('FileUploadValidator should allow mkv extension', () => {
    const content = readFile('lib/file_upload_validator.php');
    expect(content).toContain("'mkv'");
    expect(content).toContain("'video/x-matroska'");
  });

  test('process_drills.php should allow MKV mime type', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain("'video/x-matroska'");
  });

  test('file hint text should include MKV', () => {
    const athlete = readFile('views/video_record_athlete.php');
    expect(athlete).toContain('MKV');

    const coach = readFile('views/video_coach_reviews.php');
    expect(coach).toContain('MKV');

    const drill = readFile('views/video_record_drill.php');
    expect(drill).toContain('MKV');

    const gpFilm = readFile('views/gameplan/gp_film_room.php');
    expect(gpFilm).toContain('MKV');

    const film = readFile('views/gameplan/film_room.php');
    expect(film).toContain('MKV');
  });

  test('drills_create.php should accept MKV uploads', () => {
    const content = readFile('views/drills_create.php');
    expect(content).toContain('video/x-matroska');
    expect(content).toContain('MKV');
  });
});

// =====================================================
// 4. alert() replaced with showToast() in video views
// =====================================================

test.describe('Video upload views use showToast instead of alert', () => {
  test('video_record_athlete.php should not have alert() calls', () => {
    const content = readFile('views/video_record_athlete.php');
    // Extract script section
    const scriptStart = content.indexOf('<script>');
    const scriptEnd = content.indexOf('</script>', scriptStart);
    const script = content.substring(scriptStart, scriptEnd);
    expect(script).not.toContain('alert(');
    expect(script).toContain('showToast(');
  });

  test('video_coach_reviews.php should not have alert() calls', () => {
    const content = readFile('views/video_coach_reviews.php');
    const scriptStart = content.indexOf('<script>');
    const scriptEnd = content.indexOf('</script>', scriptStart);
    const script = content.substring(scriptStart, scriptEnd);
    expect(script).not.toContain('alert(');
    expect(script).toContain('showToast(');
  });

  test('video_record_drill.php should use showToast for upload feedback', () => {
    const content = readFile('views/video_record_drill.php');
    expect(content).toContain("showToast('Video uploaded successfully!'");
    expect(content).toContain("showToast('Upload failed");
    expect(content).not.toContain("alert('Video uploaded successfully!'");
    expect(content).not.toContain("alert('Upload failed");
  });
});

// =====================================================
// 5. confirm() replaced with showConfirmModal() in app.js
// =====================================================

test.describe('Browser confirm() replaced with in-app modal', () => {
  test('app.js should define showConfirmModal function', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('function showConfirmModal(');
    expect(content).toContain('window.showConfirmModal = showConfirmModal');
  });

  test('showConfirmModal should return a Promise', () => {
    const content = readFile('js/app.js');
    const funcStart = content.indexOf('function showConfirmModal(');
    const funcSection = content.substring(funcStart, funcStart + 1500);
    expect(funcSection).toContain('return new Promise');
  });

  test('app.js should not use confirm() for actions', () => {
    const content = readFile('js/app.js');
    // There should be no confirm( calls except in the comment/doc
    const lines = content.split('\n');
    const confirmCalls = lines.filter(line => {
      const trimmed = line.trim();
      // Skip comment lines and the function definition line
      if (trimmed.startsWith('*') || trimmed.startsWith('//') || trimmed.startsWith('/*')) return false;
      return /\bconfirm\s*\(/.test(trimmed);
    });
    expect(confirmCalls).toHaveLength(0);
  });

  test('drill_designer.js should use showConfirmModal for clearAll', () => {
    const content = readFile('js/drill_designer.js');
    expect(content).toContain('showConfirmModal');
    expect(content).not.toContain('window.confirm(');
  });

  test('delete action should use showConfirmModal', () => {
    const content = readFile('js/app.js');
    const deleteSection = content.substring(
      content.indexOf('data-action="delete"][data-action-url]'),
      content.indexOf('data-action="delete"][data-action-url]') + 2000
    );
    expect(deleteSection).toContain('showConfirmModal');
  });

  test('delete-video action should use showConfirmModal', () => {
    const content = readFile('js/app.js');
    const deleteVideoSection = content.substring(
      content.indexOf('data-action="delete-video"'),
      content.indexOf('data-action="delete-video"') + 1500
    );
    expect(deleteVideoSection).toContain('showConfirmModal');
  });
});

// =====================================================
// 6. Select All button fix in accounting_products.php
// =====================================================

test.describe('Select All button works correctly', () => {
  test('toggleSelectAll should accept sourceCheckbox parameter', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('function toggleSelectAll(tab, sourceCheckbox)');
  });

  test('toggleSelectAll should read checked state from sourceCheckbox', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('sourceCheckbox ? sourceCheckbox.checked : false');
  });

  test('all onchange handlers should pass this to toggleSelectAll', () => {
    const content = readFile('views/accounting_products.php');
    const matches = content.match(/onchange="toggleSelectAll\([^"]+\)"/g) || [];
    expect(matches.length).toBeGreaterThanOrEqual(8);
    matches.forEach(match => {
      expect(match).toContain(', this)');
    });
  });

  test('merchandise select-all should pass this', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain("toggleSelectAll('merchandise', this)");
  });

  test('programs_camps select-all should pass this', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain("toggleSelectAll('programs_camps', this)");
  });

  test('discounts select-all should pass this', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain("toggleSelectAll('discounts', this)");
  });
});
