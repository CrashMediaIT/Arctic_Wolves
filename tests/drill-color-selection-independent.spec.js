import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for drill designer color selection independence.
 * 
 * Color selection should be independent from tool selection:
 * 1. Select color → select item → place item in that color
 * 2. Select item → select color → place item in that color (color does NOT deselect the item tool)
 * 3. Place item → select it → change color → item updates to new color
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Color picker does NOT auto-select paint tool
// =====================================================

test.describe('Color selection is independent from tool selection', () => {
  test('color picker input handler does not call selectPaintTool', () => {
    const content = readFile('js/drill_designer.js');
    // Find the color picker input handler
    const inputHandlerStart = content.indexOf("colorPicker.addEventListener('input'");
    const inputHandlerEnd = content.indexOf('});', inputHandlerStart);
    const inputHandler = content.substring(inputHandlerStart, inputHandlerEnd);
    // Should NOT contain selectPaintTool
    expect(inputHandler).not.toContain('selectPaintTool');
    // Should update activeColor
    expect(inputHandler).toContain('this.activeColor = e.target.value');
    // Should update color display
    expect(inputHandler).toContain('this.updateActiveColorDisplay()');
  });

  test('color picker change handler does not call selectPaintTool', () => {
    const content = readFile('js/drill_designer.js');
    // Find the color picker change handler
    const changeHandlerStart = content.indexOf("colorPicker.addEventListener('change'");
    const changeHandlerEnd = content.indexOf('});', changeHandlerStart);
    const changeHandler = content.substring(changeHandlerStart, changeHandlerEnd);
    // Should NOT contain selectPaintTool
    expect(changeHandler).not.toContain('selectPaintTool');
    // Should update activeColor
    expect(changeHandler).toContain('this.activeColor = e.target.value');
  });

  test('color preset click handler does not call selectPaintTool', () => {
    const content = readFile('js/drill_designer.js');
    // Find the color preset handler block up to the paint tool button section
    const presetHandlerStart = content.indexOf("document.querySelectorAll('[data-color-preset]').forEach");
    const paintBtnSection = content.indexOf("// Paint tool button", presetHandlerStart);
    const presetHandler = content.substring(presetHandlerStart, paintBtnSection);
    // Should NOT contain selectPaintTool
    expect(presetHandler).not.toContain('selectPaintTool');
    // Should update activeColor
    expect(presetHandler).toContain('this.activeColor = color');
  });
});

// =====================================================
// 2. Color selection applies to selected objects (use case 3)
// =====================================================

test.describe('Color selection applies to selected objects', () => {
  test('color picker input handler calls applyColorToSelected', () => {
    const content = readFile('js/drill_designer.js');
    const inputHandlerStart = content.indexOf("colorPicker.addEventListener('input'");
    const inputHandlerEnd = content.indexOf('});', inputHandlerStart);
    const inputHandler = content.substring(inputHandlerStart, inputHandlerEnd);
    expect(inputHandler).toContain('this.applyColorToSelected()');
  });

  test('color picker change handler calls applyColorToSelected', () => {
    const content = readFile('js/drill_designer.js');
    const changeHandlerStart = content.indexOf("colorPicker.addEventListener('change'");
    const changeHandlerEnd = content.indexOf('});', changeHandlerStart);
    const changeHandler = content.substring(changeHandlerStart, changeHandlerEnd);
    expect(changeHandler).toContain('this.applyColorToSelected()');
  });

  test('color preset click handler calls applyColorToSelected', () => {
    const content = readFile('js/drill_designer.js');
    const presetHandlerStart = content.indexOf("document.querySelectorAll('[data-color-preset]').forEach");
    const paintBtnSection = content.indexOf("// Paint tool button", presetHandlerStart);
    const presetHandler = content.substring(presetHandlerStart, paintBtnSection);
    expect(presetHandler).toContain('this.applyColorToSelected()');
  });

  test('applyColorToSelected method exists and updates selected object color', () => {
    const content = readFile('js/drill_designer.js');
    // Find the method definition
    const methodStart = content.indexOf('applyColorToSelected() {');
    expect(methodStart).toBeGreaterThan(-1);
    // Extract the method body up to the next method (shareLink)
    const nextMethod = content.indexOf('shareLink()', methodStart);
    const methodBody = content.substring(methodStart, nextMethod);
    expect(methodBody).toContain('this.selectedObject.color = this.activeColor');
    expect(methodBody).toContain('this.redraw()');
    expect(methodBody).toContain('this.saveState()');
  });
});

// =====================================================
// 2b. Color handlers use stopPropagation to prevent tool switching
// =====================================================

test.describe('Color handlers prevent event propagation', () => {
  test('color picker input handler calls stopPropagation', () => {
    const content = readFile('js/drill_designer.js');
    const inputHandlerStart = content.indexOf("colorPicker.addEventListener('input'");
    const inputHandlerEnd = content.indexOf('});', inputHandlerStart);
    const inputHandler = content.substring(inputHandlerStart, inputHandlerEnd);
    expect(inputHandler).toContain('e.stopPropagation()');
  });

  test('color picker change handler calls stopPropagation', () => {
    const content = readFile('js/drill_designer.js');
    const changeHandlerStart = content.indexOf("colorPicker.addEventListener('change'");
    const changeHandlerEnd = content.indexOf('});', changeHandlerStart);
    const changeHandler = content.substring(changeHandlerStart, changeHandlerEnd);
    expect(changeHandler).toContain('e.stopPropagation()');
  });

  test('color preset click handler calls stopPropagation', () => {
    const content = readFile('js/drill_designer.js');
    const presetHandlerStart = content.indexOf("document.querySelectorAll('[data-color-preset]').forEach");
    const paintBtnSection = content.indexOf("// Paint tool button", presetHandlerStart);
    const presetHandler = content.substring(presetHandlerStart, paintBtnSection);
    expect(presetHandler).toContain('e.stopPropagation()');
  });
});

// =====================================================
// 3. Paint tool button still works independently
// =====================================================

test.describe('Paint tool button works independently', () => {
  test('paint tool button has its own click handler', () => {
    const content = readFile('js/drill_designer.js');
    expect(content).toContain("const paintBtn = document.querySelector('[data-tool=\"paint\"]')");
    // Find the paint button handler
    const paintHandlerStart = content.indexOf("paintBtn.addEventListener('click'");
    const paintHandlerEnd = content.indexOf('});', paintHandlerStart);
    const paintHandler = content.substring(paintHandlerStart, paintHandlerEnd);
    expect(paintHandler).toContain("this.currentTool = 'paint'");
  });

  test('selectPaintTool method is removed to prevent auto-selection', () => {
    const content = readFile('js/drill_designer.js');
    // selectPaintTool should not exist as a method definition
    expect(content).not.toContain('selectPaintTool() {');
  });
});

// =====================================================
// 4. Items placed on canvas use activeColor
// =====================================================

test.describe('Items use activeColor when placed', () => {
  test('cone tool uses activeColor when placing', () => {
    const content = readFile('js/drill_designer.js');
    const coneSection = content.indexOf("this.currentTool === 'cone'");
    expect(coneSection).toBeGreaterThan(-1);
    // Check a section after the cone tool check for activeColor usage
    const coneBlock = content.substring(coneSection, coneSection + 200);
    expect(coneBlock).toContain('this.activeColor');
  });

  test('puck tool uses activeColor when placing', () => {
    const content = readFile('js/drill_designer.js');
    const puckSection = content.indexOf("this.currentTool === 'puck'");
    expect(puckSection).toBeGreaterThan(-1);
    // Check a section after the puck tool check for activeColor usage
    const puckBlock = content.substring(puckSection, puckSection + 200);
    expect(puckBlock).toContain('this.activeColor');
  });

  test('freehand drawing uses activeColor', () => {
    const content = readFile('js/drill_designer.js');
    // When freehand drawing is finalized, it uses this.activeColor
    const freehandSection = content.indexOf('this.isDrawingFreehand && this.currentFreehandPoints.length > 1');
    const freehandEnd = content.indexOf('return;', freehandSection);
    const freehandHandler = content.substring(freehandSection, freehandEnd);
    expect(freehandHandler).toContain('color: this.activeColor');
  });
});
