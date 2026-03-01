/**
 * Tests for Video Upload Fixes
 *
 * Verifies fixes for:
 * 1. Double file explorer (initializeFileUploads skips custom upload areas)
 * 2. Theme settings upload failed errors (proper redirect handling)
 * 3. Upload button on record_drill_video works (uploadFileBtn handler)
 * 4. PWA drill video uploads to correct endpoint (process_video.php)
 * 5. Front card background uploads (consolidated branding form)
 * 6. Theme settings has single save button in branding tab
 * 7. All upload areas have progress bars
 * 8. Contextual drag-and-drop (button by default, drop zone on drag)
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Double file explorer fix
// =====================================================

test.describe('File upload zone skips custom upload areas', () => {
  test('initializeFileUploads should skip inputs inside .file-upload-area', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("input.closest('.file-upload-area')");
  });

  test('initializeFileUploads should skip inputs inside [data-component="FileUpload"]', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("input.closest('[data-component=\"FileUpload\"]')");
  });

  test('initializeFileUploads should skip inputs inside .file-upload-zone', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("input.closest('.file-upload-zone')");
  });
});

// =====================================================
// 2. Contextual drag-and-drop
// =====================================================

test.describe('Contextual drag-and-drop', () => {
  test('should NOT create a choose file button (removed in favor of drop zone)', () => {
    const content = readFile('js/app.js');
    expect(content).not.toContain('file-choose-btn');
    expect(content).not.toContain('Choose File');
    expect(content).not.toContain('fa-folder-open');
  });

  test('drop zone should be visible by default (display:block)', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("display:block;border:2px dashed");
  });

  test('should listen for dragenter on document to show drop zones', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("document.addEventListener('dragenter'");
  });

  test('should listen for dragleave on document to hide drop zones', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("document.addEventListener('dragleave'");
  });

  test('should listen for drop on document to hide drop zones', () => {
    const content = readFile('js/app.js');
    expect(content).toContain("document.addEventListener('drop'");
  });

  test('should use dragCounter to track nested drag events', () => {
    const content = readFile('js/app.js');
    expect(content).toContain('dragCounter');
  });
});

// =====================================================
// 3. Upload button on record_drill_video
// =====================================================

test.describe('record_drill_video upload file button works', () => {
  test('uploadFileBtn should have a click handler that creates FormData', () => {
    const content = readFile('views/video_record_drill.php');
    expect(content).toContain("uploadFileBtn.addEventListener('click'");
    expect(content).toContain('new FormData()');
  });

  test('upload file handler should send presign request to process_video.php', () => {
    const content = readFile('views/video_record_drill.php');
    // Find the uploadFileBtn click handler section
    const handlerStart = content.indexOf("uploadFileBtn.addEventListener('click'");
    const handlerSection = content.substring(handlerStart, handlerStart + 2000);
    expect(handlerSection).toContain("fetch('process_video.php'");
  });

  test('upload file handler should use XHR with progress tracking', () => {
    const content = readFile('views/video_record_drill.php');
    const handlerStart = content.indexOf("uploadFileBtn.addEventListener('click'");
    const handlerSection = content.substring(handlerStart, handlerStart + 3000);
    expect(handlerSection).toContain('XMLHttpRequest');
    expect(handlerSection).toContain('xhr.upload.onprogress');
  });

  test('upload file handler should use presigned URL flow with get_video_upload_url', () => {
    const content = readFile('views/video_record_drill.php');
    const handlerStart = content.indexOf("uploadFileBtn.addEventListener('click'");
    const handlerSection = content.substring(handlerStart, handlerStart + 2000);
    expect(handlerSection).toContain("'action', 'get_video_upload_url'");
    expect(handlerSection).toContain("'upload_type', 'drill_video'");
  });

  test('upload file handler should validate session, drill, and athlete', () => {
    const content = readFile('views/video_record_drill.php');
    const handlerStart = content.indexOf("uploadFileBtn.addEventListener('click'");
    const handlerSection = content.substring(handlerStart, handlerStart + 2000);
    expect(handlerSection).toContain('sessionSelect');
    expect(handlerSection).toContain('drillSelect');
    expect(handlerSection).toContain('athleteSelect');
    expect(handlerSection).toContain('!session || !drill || !athlete');
  });
});

// =====================================================
// 4. PWA drill video upload fix
// =====================================================

test.describe('PWA drill video uploads to correct endpoint', () => {
  test('should submit to process_video.php not process_video_upload.php', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain("'POST', 'process_video.php'");
    expect(content).not.toContain('process_video_upload.php');
  });

  test('should send action=athlete_upload_video', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain("'action', 'athlete_upload_video'");
  });

  test('should send video as video_file field', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain("'video_file', blob");
  });

  test('should use XHR with progress bar', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain('XMLHttpRequest');
    expect(content).toContain('xhr.upload.onprogress');
  });

  test('should have a visual progress bar element', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain('progressBar.style.width');
  });
});

// =====================================================
// 5. Theme settings consolidated branding form
// =====================================================

test.describe('Theme branding tab has single consolidated form', () => {
  test('should have a single brandingForm with action=update_branding_all', () => {
    const content = readFile('views/admin_theme_settings.php');
    expect(content).toContain('id="brandingForm"');
    expect(content).toContain('value="update_branding_all"');
  });

  test('should not have separate centerIceLogoForm or businessCardBgForm', () => {
    const content = readFile('views/admin_theme_settings.php');
    expect(content).not.toContain('id="centerIceLogoForm"');
    expect(content).not.toContain('id="businessCardBgForm"');
  });

  test('brandingForm should contain logo, center ice logo, and business card bg inputs', () => {
    const content = readFile('views/admin_theme_settings.php');
    const formStart = content.indexOf('id="brandingForm"');
    const formEnd = content.indexOf('</form>', formStart);
    const formContent = content.substring(formStart, formEnd);
    expect(formContent).toContain('name="logo"');
    expect(formContent).toContain('name="center_ice_logo"');
    expect(formContent).toContain('name="bc_front_bg"');
    expect(formContent).toContain('name="bc_back_bg"');
  });

  test('should not have individual save button in branding form (uses unified save)', () => {
    const content = readFile('views/admin_theme_settings.php');
    const formStart = content.indexOf('id="brandingForm"');
    const formEnd = content.indexOf('</form>', formStart);
    const formContent = content.substring(formStart, formEnd);
    const saveButtons = formContent.match(/type="submit"/g);
    expect(saveButtons).toBeNull();
  });

  test('unified save button should say "Save All Settings"', () => {
    const content = readFile('views/admin_theme_settings.php');
    expect(content).toContain('Save All Settings');
  });
});

// =====================================================
// 6. Backend handles consolidated branding form
// =====================================================

test.describe('process_theme.php handles update_branding_all', () => {
  test('should have update_branding_all case', () => {
    const content = readFile('process_theme.php');
    expect(content).toContain("case 'update_branding_all':");
  });

  test('update_branding_all should handle logo upload', () => {
    const content = readFile('process_theme.php');
    const caseStart = content.indexOf("case 'update_branding_all':");
    const caseSection = content.substring(caseStart, caseStart + 3000);
    expect(caseSection).toContain("$_FILES['logo']");
    expect(caseSection).toContain("'logo_url'");
  });

  test('update_branding_all should handle center ice logo', () => {
    const content = readFile('process_theme.php');
    const caseStart = content.indexOf("case 'update_branding_all':");
    const caseSection = content.substring(caseStart, caseStart + 3000);
    expect(caseSection).toContain("$_FILES['center_ice_logo']");
    expect(caseSection).toContain("'center_ice_logo_url'");
  });

  test('update_branding_all should handle business card backgrounds', () => {
    const content = readFile('process_theme.php');
    const caseStart = content.indexOf("case 'update_branding_all':");
    const caseSection = content.substring(caseStart, caseStart + 4000);
    expect(caseSection).toContain("$_FILES['bc_front_bg']");
    expect(caseSection).toContain("$_FILES['bc_back_bg']");
    expect(caseSection).toContain("'business_card_front_bg_url'");
    expect(caseSection).toContain("'business_card_back_bg_url'");
  });
});

// =====================================================
// 7. Progress bars on theme settings uploads
// =====================================================

test.describe('Theme settings upload forms have progress bars', () => {
  test('branding form should have upload progress elements', () => {
    const content = readFile('views/admin_theme_settings.php');
    expect(content).toContain('id="brandingUploadProgress"');
    expect(content).toContain('id="brandingUploadBar"');
    expect(content).toContain('id="brandingUploadPercent"');
  });

  test('hero form should have upload progress elements', () => {
    const content = readFile('views/admin_theme_settings.php');
    expect(content).toContain('id="heroUploadProgress"');
    expect(content).toContain('id="heroUploadBar"');
    expect(content).toContain('id="heroUploadPercent"');
  });

  test('branding form should use XHR with upload.onprogress', () => {
    const content = readFile('views/admin_theme_settings.php');
    // Find branding submit handler
    const handlerStart = content.indexOf("document.getElementById('brandingForm').addEventListener('submit'");
    const handlerSection = content.substring(handlerStart, handlerStart + 2000);
    expect(handlerSection).toContain('XMLHttpRequest');
    expect(handlerSection).toContain('xhr.upload.onprogress');
  });

  test('hero form should use XHR with upload.onprogress', () => {
    const content = readFile('views/admin_theme_settings.php');
    const handlerStart = content.indexOf("document.getElementById('heroForm').addEventListener('submit'");
    const handlerSection = content.substring(handlerStart, handlerStart + 2000);
    expect(handlerSection).toContain('XMLHttpRequest');
    expect(handlerSection).toContain('xhr.upload.onprogress');
  });
});

// =====================================================
// 8. Theme settings JS doesn't append duplicate action
// =====================================================

test.describe('Theme settings JS handlers do not append duplicate actions', () => {
  test('colors form handler should not append action=save', () => {
    const content = readFile('views/admin_theme_settings.php');
    const handlerStart = content.indexOf("document.getElementById('colorsForm').addEventListener('submit'");
    const handlerSection = content.substring(handlerStart, handlerStart + 1000);
    expect(handlerSection).not.toContain("formData.append('action'");
  });

  test('branding form handler should not append extra action', () => {
    const content = readFile('views/admin_theme_settings.php');
    const handlerStart = content.indexOf("document.getElementById('brandingForm').addEventListener('submit'");
    const handlerSection = content.substring(handlerStart, handlerStart + 2000);
    expect(handlerSection).not.toContain("formData.append('action'");
  });

  test('hero form handler should not append extra action', () => {
    const content = readFile('views/admin_theme_settings.php');
    const handlerStart = content.indexOf("document.getElementById('heroForm').addEventListener('submit'");
    const handlerSection = content.substring(handlerStart, handlerStart + 2000);
    expect(handlerSection).not.toContain("formData.append('action'");
  });

  test('program form handler should not append extra action', () => {
    const content = readFile('views/admin_theme_settings.php');
    const handlerStart = content.indexOf("document.getElementById('programForm').addEventListener('submit'");
    const handlerSection = content.substring(handlerStart, handlerStart + 1000);
    expect(handlerSection).not.toContain("formData.append('action'");
  });
});
