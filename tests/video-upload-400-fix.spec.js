/**
 * Tests for Video Upload 400 Bad Request Fix
 *
 * Verifies fixes for:
 * 1. SQL query in handleDrillVideoUpload uses correct column names (no non-existent 'date' column)
 * 2. FileUploadValidator method calls use correct method name 'validateVideo' (not 'validateVideoUpload')
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. SQL query uses correct session column names
// =====================================================

test.describe('handleDrillVideoUpload SQL query uses correct columns', () => {
  test('sessions query should not reference non-existent date column', () => {
    const content = readFile('process_video.php');
    // Find the handleDrillVideoUpload function
    const funcStart = content.indexOf('function handleDrillVideoUpload()');
    const funcSection = content.substring(funcStart, funcStart + 1000);
    // Should use session_date, not a bare 'date' column
    expect(funcSection).toContain('SELECT title, session_date FROM sessions');
    expect(funcSection).not.toContain('SELECT title, session_date, date FROM sessions');
  });

  test('session_date fallback should not reference non-existent date key', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleDrillVideoUpload()');
    const funcSection = content.substring(funcStart, funcStart + 1000);
    expect(funcSection).not.toContain("session['date']");
  });
});

// =====================================================
// 2. FileUploadValidator uses correct method name
// =====================================================

test.describe('FileUploadValidator uses validateVideo method', () => {
  test('FileUploadValidator should define validateVideo method', () => {
    const content = readFile('lib/file_upload_validator.php');
    expect(content).toContain('function validateVideo(');
  });

  test('process_video.php should not call non-existent validateVideoUpload', () => {
    const content = readFile('process_video.php');
    expect(content).not.toContain('validateVideoUpload');
  });

  test('process_video.php should call validateVideo for all video uploads', () => {
    const content = readFile('process_video.php');
    const matches = content.match(/validateVideo\(/g);
    // Should have at least 4 calls (handleVideoUpload, handleAthleteVideoUpload, handleDrillVideoUpload, gameplan upload)
    expect(matches).not.toBeNull();
    expect(matches.length).toBeGreaterThanOrEqual(4);
  });
});
