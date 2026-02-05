/**
 * Drill Designer - Interactive Hockey Drill Drawing Tool
 * Allows coaches to create visual drill diagrams on an ice rink canvas
 * 
 * Features:
 * - Click to rotate items
 * - Delete individual items
 * - Color picker for all items
 * - Squiggly line for puck carrying
 * - Improved hockey stick icon
 * - Shareable drill links
 */

// Line-based drawing tools constant - kept empty for backward compatibility
// All line-based tools now use freehand drawing by default
const LINE_TOOLS = [];

// Freehand drawing tools - can be drawn in any shape/curve
// Now includes all drawing, skating, and pass/shot tools for freehand curves
const FREEHAND_TOOLS = ['freehand', 'freehand_arrow', 'freehand_dashed', 'freehand_skating', 'line', 'arrow', 'dashed', 'squiggly', 'skating_forward', 'skating_backward', 'skating_lateral', 'skating_ccuts', 'skating_forward_puck', 'skating_backward_puck', 'pass', 'shot'];

// NHL/Hockey Canada Rink Proportions (200 ft × 85 ft rink)
// All values are proportional to rink dimensions
const NHL_RINK = {
    // Rink dimensions ratio (length / width)
    ASPECT_RATIO: 200 / 85,
    
    // Goal line position from end (11 ft from 200 ft = 0.055)
    GOAL_LINE: 11 / 200,
    
    // Blue line position from end (64 ft from 200 ft = 0.32)
    BLUE_LINE: 64 / 200,
    
    // Faceoff circle radius (15 ft, relative to width: 15/85 = 0.176)
    FACEOFF_RADIUS: 15 / 85,
    
    // Center circle radius (same as faceoff: 15 ft)
    CENTER_CIRCLE_RADIUS: 15 / 85,
    
    // Goal crease radius (6 ft, relative to width: 6/85 = 0.071)
    CREASE_RADIUS: 6 / 85,
    
    // Faceoff dot distance from goal line (20 ft from 200 ft = 0.10)
    FACEOFF_FROM_GOAL: 20 / 200,
    
    // Faceoff dot distance from boards (22 ft from 85 ft = 0.259)
    FACEOFF_FROM_BOARDS: 22 / 85,
    
    // Corner radius (28 ft, relative to width: 28/85 = 0.329)
    CORNER_RADIUS: 28 / 85,
    
    // Goalie trapezoid dimensions (behind the net)
    // Trapezoid base at goal line: 22 ft wide (11 ft each side of center)
    TRAPEZOID_BASE: 22 / 85,
    // Trapezoid top at boards: 28 ft wide (14 ft each side of center)
    TRAPEZOID_TOP: 28 / 85,
    
    // Faceoff restraint line dimensions
    // L-shaped lines inside faceoff circles, 2 ft long each arm
    RESTRAINT_LINE_LENGTH: 2 / 85
};

class DrillDesigner {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) {
            console.error('Canvas element not found');
            return;
        }
        
        this.ctx = this.canvas.getContext('2d');
        this.currentTool = 'select';
        this.objects = [];
        this.selectedObject = null;
        this.isDragging = false;
        this.dragStartPos = null;
        this.history = [];
        this.historyIndex = -1;
        
        // Freehand drawing state
        this.isDrawingFreehand = false;
        this.currentFreehandPoints = [];
        
        // Line thickness (increased for visibility)
        this.lineThickness = 3;
        
        // Active color for painting objects
        this.activeColor = '#000000';
        
        // Color presets for quick selection
        this.colorPresets = [
            '#000000', // Black
            '#c41e3a', // Red
            '#0033a0', // Blue
            '#00bfff', // Light Blue
            '#ff6600', // Orange
            '#10b981', // Green
            '#8b5cf6', // Purple
            '#f59e0b', // Yellow
            '#ec4899'  // Pink
        ];
        
        // Configurable branding
        this.brandingText = 'ARCTIC WOLVES';
        this.brandingSubtext = 'HOCKEY';
        
        // Center logo image from settings
        this.centerLogoUrl = '';
        this.centerLogoImage = null;
        this.centerLogoLoaded = false;
        
        this.init();
    }
    
    init() {
        // Set canvas size
        this.canvas.width = this.canvas.offsetWidth;
        this.canvas.height = this.canvas.offsetHeight;
        
        // Load center logo from data attribute if available
        const container = document.getElementById('drill-rink-container');
        if (container && container.dataset.centerLogo) {
            this.centerLogoUrl = container.dataset.centerLogo;
            this.loadCenterLogo();
        }
        
        // Draw initial rink
        this.drawRink();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Save initial state
        this.saveState();
    }
    
    // Load center logo image from URL
    loadCenterLogo() {
        if (!this.centerLogoUrl) return;
        
        this.centerLogoImage = new Image();
        this.centerLogoImage.crossOrigin = 'anonymous';
        this.centerLogoImage.onload = () => {
            this.centerLogoLoaded = true;
            this.redraw();
        };
        this.centerLogoImage.onerror = () => {
            console.warn('Failed to load center logo image, falling back to text');
            this.centerLogoLoaded = false;
        };
        this.centerLogoImage.src = this.centerLogoUrl;
    }
    
    setupEventListeners() {
        this.canvas.addEventListener('mousedown', this.handleMouseDown.bind(this));
        this.canvas.addEventListener('mousemove', this.handleMouseMove.bind(this));
        this.canvas.addEventListener('mouseup', this.handleMouseUp.bind(this));
        this.canvas.addEventListener('click', this.handleClick.bind(this));
        
        // Keyboard events for delete
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
        
        // Tool buttons
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const title = e.currentTarget.getAttribute('title');
                this.setTool(title);
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                e.currentTarget.classList.add('active');
            });
        });
        
        // Canvas controls with data attributes
        const undoBtn = document.querySelector('[data-drill-action="undo"]');
        const redoBtn = document.querySelector('[data-drill-action="redo"]');
        const exportBtn = document.querySelector('[data-drill-action="export"]');
        const deleteBtn = document.querySelector('[data-drill-action="delete"]');
        const rotateBtn = document.querySelector('[data-drill-action="rotate"]');
        const shareBtn = document.querySelector('[data-drill-action="share"]');
        
        if (undoBtn) undoBtn.addEventListener('click', () => this.undo());
        if (redoBtn) redoBtn.addEventListener('click', () => this.redo());
        if (exportBtn) exportBtn.addEventListener('click', () => this.exportImage());
        if (deleteBtn) deleteBtn.addEventListener('click', () => this.deleteSelected());
        if (rotateBtn) rotateBtn.addEventListener('click', () => this.rotateSelected());
        if (shareBtn) shareBtn.addEventListener('click', () => this.shareLink());
        
        // Color picker
        const colorPicker = document.querySelector('[data-drill-action="color-picker"]');
        if (colorPicker) {
            colorPicker.addEventListener('input', (e) => {
                this.activeColor = e.target.value;
                this.updateActiveColorDisplay();
                // Auto-select paint tool when color is selected
                this.selectPaintTool();
            });
            colorPicker.addEventListener('change', (e) => {
                this.activeColor = e.target.value;
                this.updateActiveColorDisplay();
                // Auto-select paint tool when color is selected
                this.selectPaintTool();
            });
        }
        
        // Color presets
        document.querySelectorAll('[data-color-preset]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const color = e.currentTarget.getAttribute('data-color-preset');
                this.activeColor = color;
                this.updateActiveColorDisplay();
                
                // Update color picker input if it exists
                const colorPicker = document.querySelector('[data-drill-action="color-picker"]');
                if (colorPicker) {
                    colorPicker.value = color;
                }
                
                // Update active state on preset buttons
                document.querySelectorAll('[data-color-preset]').forEach(b => b.classList.remove('active'));
                e.currentTarget.classList.add('active');
                
                // Auto-select paint tool when color preset is clicked
                this.selectPaintTool();
            });
        });
        
        // Paint tool button
        const paintBtn = document.querySelector('[data-tool="paint"]');
        if (paintBtn) {
            paintBtn.addEventListener('click', (e) => {
                this.currentTool = 'paint';
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                e.currentTarget.classList.add('active');
            });
        }
    }
    
    // Helper method to automatically select the paint tool when a color is chosen
    selectPaintTool() {
        this.currentTool = 'paint';
        // Update UI to show paint tool as active
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        const paintBtn = document.querySelector('[data-tool="paint"]');
        if (paintBtn) {
            paintBtn.classList.add('active');
        }
    }
    
    handleKeyDown(e) {
        // Delete selected object with Delete key only (Backspace is reserved for browser navigation)
        if (e.key === 'Delete' && this.selectedObject) {
            // Don't delete if user is typing in an input field
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            e.preventDefault();
            this.deleteSelected();
        }
        
        // Rotate with R key
        if (e.key === 'r' || e.key === 'R') {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            if (this.selectedObject) {
                e.preventDefault();
                this.rotateSelected();
            }
        }
    }
    
    updateActiveColorDisplay() {
        const activeColorCircle = document.querySelector('.active-color-circle');
        if (activeColorCircle) {
            activeColorCircle.style.backgroundColor = this.activeColor;
        }
    }
    
    deleteSelected() {
        if (this.selectedObject) {
            const index = this.objects.indexOf(this.selectedObject);
            if (index > -1) {
                this.objects.splice(index, 1);
                this.selectedObject = null;
                this.redraw();
                this.saveState();
            }
        }
    }
    
    rotateSelected(degrees = 45) {
        if (this.selectedObject) {
            // All objects with a rotation property can be rotated
            // Line-based objects (line, arrow, dashed, squiggly) don't have rotation
            if (this.selectedObject.rotation !== undefined) {
                this.selectedObject.rotation = ((this.selectedObject.rotation || 0) + degrees) % 360;
                this.redraw();
                this.saveState();
            }
        }
    }
    
    applyColorToSelected() {
        if (this.selectedObject) {
            this.selectedObject.color = this.activeColor;
            this.redraw();
            this.saveState();
        }
    }
    
    shareLink() {
        // Get the drill ID from the URL or form
        const urlParams = new URLSearchParams(window.location.search);
        const drillId = urlParams.get('edit');
        
        if (drillId) {
            // Generate shareable URL with encoded drill ID
            const shareUrl = window.location.origin + '/dashboard.php?page=view_drill&id=' + encodeURIComponent(drillId) + '&shared=true';
            
            // Copy to clipboard
            navigator.clipboard.writeText(shareUrl).then(() => {
                this.showNotification('Share link copied to clipboard!', 'success');
            }).catch(() => {
                // Fallback for older browsers - execCommand is deprecated but still works in many browsers
                try {
                    const textArea = document.createElement('textarea');
                    textArea.value = shareUrl;
                    // Hide the textarea to prevent visual flashing
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-9999px';
                    textArea.style.top = '0';
                    textArea.style.opacity = '0';
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    this.showNotification('Share link copied to clipboard!', 'success');
                } catch (e) {
                    this.showNotification('Please copy this link manually: ' + shareUrl, 'info');
                }
            });
        } else {
            this.showNotification('Please save the drill first to get a share link.', 'info');
        }
    }
    
    showNotification(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'notification-toast';
        alertDiv.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle') + '"></i> ' + message;
        alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px; padding: 15px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; animation: slideIn 0.3s ease;';
        
        if (type === 'error') {
            alertDiv.style.background = 'rgba(239, 68, 68, 0.9)';
            alertDiv.style.color = '#fff';
        } else if (type === 'success') {
            alertDiv.style.background = 'rgba(16, 185, 129, 0.9)';
            alertDiv.style.color = '#fff';
        } else {
            alertDiv.style.background = 'rgba(59, 130, 246, 0.9)';
            alertDiv.style.color = '#fff';
        }
        
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 4000);
    }
    
    setTool(toolName) {
        const toolMap = {
            'Select': 'select',
            'Add Player': 'player',
            'Add Cone': 'cone',
            'Draw Line': 'line',
            'Add Arrow': 'arrow',
            'Clear All': 'clear',
            'Forward (F)': 'forward',
            'Forward 1 (F1)': 'f1',
            'Forward 2 (F2)': 'f2',
            'Forward 3 (F3)': 'f3',
            'Defense (D)': 'defense',
            'Defense 1 (D1)': 'd1',
            'Defense 2 (D2)': 'd2',
            'Coach': 'coach',
            'Goalie (G)': 'goalie',
            'Center (C)': 'center',
            'Left Wing (LW)': 'lw',
            'Right Wing (RW)': 'rw',
            'Left Defense (LD)': 'ld',
            'Right Defense (RD)': 'rd',
            'Single Puck': 'puck',
            'Puck Group': 'pucks',
            'Cone': 'cone',
            'Net': 'net',
            'Mini Net': 'mininet',
            'Tire': 'tire',
            'Stick': 'stick',
            'Draw Dashed Line': 'dashed',
            'Squiggly Line (Puck Carry)': 'squiggly',
            'Arrow': 'arrow',
            'Add Text': 'text',
            'Paint Color': 'paint',
            'Delete Selected': 'delete',
            'Rotate Item': 'rotate',
            'Number 0': 'num0',
            'Number 1': 'num1',
            'Number 2': 'num2',
            'Number 3': 'num3',
            'Number 4': 'num4',
            'Number 5': 'num5',
            'Number 6': 'num6',
            'Number 7': 'num7',
            'Number 8': 'num8',
            'Number 9': 'num9',
            'Fullscreen': 'fullscreen',
            // New skating pattern lines
            'Forward Skating': 'skating_forward',
            'Backward Skating': 'skating_backward',
            'Lateral Skating': 'skating_lateral',
            'C-Cuts Skating': 'skating_ccuts',
            'Forward Skating with Puck': 'skating_forward_puck',
            'Backward Skating with Puck': 'skating_backward_puck',
            // Pass and shot lines
            'Pass': 'pass',
            'Shot': 'shot',
            // Freehand drawing tools
            'Freehand Draw': 'freehand',
            'Freehand Arrow': 'freehand_arrow',
            'Freehand Dashed': 'freehand_dashed',
            'Freehand Skating Path': 'freehand_skating'
        };
        
        if (toolName === 'Clear All') {
            this.clearAll();
            return;
        }
        
        if (toolName === 'Fullscreen') {
            this.toggleFullscreen();
            return;
        }
        
        if (toolName === 'Delete Selected') {
            this.deleteSelected();
            return;
        }
        
        if (toolName === 'Rotate Item') {
            this.rotateSelected();
            return;
        }
        
        this.currentTool = toolMap[toolName] || 'select';
    }
    
    toggleFullscreen() {
        const container = this.canvas.parentElement;
        if (container) {
            container.classList.toggle('fullscreen');
            // Resize canvas for fullscreen
            setTimeout(() => {
                this.canvas.width = container.offsetWidth;
                this.canvas.height = container.offsetHeight;
                this.redraw();
            }, 100);
        }
    }
    
    handleMouseDown(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        if (this.currentTool === 'select') {
            this.selectedObject = this.findObjectAt(x, y);
            if (this.selectedObject) {
                this.isDragging = true;
                this.dragStartPos = { x, y };
            }
        } else if (LINE_TOOLS.includes(this.currentTool)) {
            this.dragStartPos = { x, y };
        } else if (FREEHAND_TOOLS.includes(this.currentTool)) {
            // Start freehand drawing
            this.isDrawingFreehand = true;
            this.currentFreehandPoints = [{ x, y }];
        }
    }
    
    handleMouseMove(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        if (this.isDragging && this.selectedObject) {
            const dx = x - this.dragStartPos.x;
            const dy = y - this.dragStartPos.y;
            
            // Handle line-based objects (move both endpoints)
            if (LINE_TOOLS.includes(this.selectedObject.type)) {
                this.selectedObject.x1 += dx;
                this.selectedObject.y1 += dy;
                this.selectedObject.x2 += dx;
                this.selectedObject.y2 += dy;
            } else if (FREEHAND_TOOLS.includes(this.selectedObject.type) && this.selectedObject.points) {
                // Move all points in freehand drawing types
                this.selectedObject.points.forEach(pt => {
                    pt.x += dx;
                    pt.y += dy;
                });
            } else {
                this.selectedObject.x += dx;
                this.selectedObject.y += dy;
            }
            
            this.dragStartPos = { x, y };
            this.redraw();
        } else if (this.isDrawingFreehand && FREEHAND_TOOLS.includes(this.currentTool)) {
            // Continue freehand drawing - add point
            this.currentFreehandPoints.push({ x, y });
            
            // Draw preview line
            this.redraw();
            this.drawFreehandPreview();
        }
    }
    
    // Draw freehand preview while drawing
    drawFreehandPreview() {
        if (this.currentFreehandPoints.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = this.activeColor;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(this.currentFreehandPoints[0].x, this.currentFreehandPoints[0].y);
        
        // Use smooth bezier curves for live drawing
        for (let i = 1; i < this.currentFreehandPoints.length - 1; i++) {
            const xc = (this.currentFreehandPoints[i].x + this.currentFreehandPoints[i + 1].x) / 2;
            const yc = (this.currentFreehandPoints[i].y + this.currentFreehandPoints[i + 1].y) / 2;
            ctx.quadraticCurveTo(this.currentFreehandPoints[i].x, this.currentFreehandPoints[i].y, xc, yc);
        }
        
        // Draw the last segment
        if (this.currentFreehandPoints.length > 1) {
            const last = this.currentFreehandPoints[this.currentFreehandPoints.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
    }
    
    handleMouseUp(e) {
        if (this.isDragging) {
            this.isDragging = false;
            this.saveState();
        }
        
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        // Finalize freehand drawing
        if (this.isDrawingFreehand && this.currentFreehandPoints.length > 1) {
            // Smooth the points before saving
            const smoothedPoints = this.smoothFreehandPoints(this.currentFreehandPoints);
            
            // Store the freehand type (freehand, freehand_arrow, freehand_dashed, freehand_skating)
            this.objects.push({
                type: this.currentTool, // Store the actual tool type
                points: smoothedPoints,
                color: this.activeColor
            });
            
            this.currentFreehandPoints = [];
            this.isDrawingFreehand = false;
            this.redraw();
            this.saveState();
            return;
        }
        
        this.isDrawingFreehand = false;
        this.currentFreehandPoints = [];
        
        if (LINE_TOOLS.includes(this.currentTool)) {
            if (this.dragStartPos) {
                this.objects.push({
                    type: this.currentTool,
                    x1: this.dragStartPos.x,
                    y1: this.dragStartPos.y,
                    x2: x,
                    y2: y,
                    color: this.activeColor
                });
                this.dragStartPos = null;
                this.redraw();
                this.saveState();
            }
        }
    }
    
    handleClick(e) {
        // Line tools and select shouldn't add objects on click
        if (this.currentTool === 'select' || LINE_TOOLS.includes(this.currentTool)) {
            return;
        }
        
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        // Handle paint tool - click on object to change its color
        if (this.currentTool === 'paint') {
            const obj = this.findObjectAt(x, y);
            if (obj) {
                obj.color = this.activeColor;
                this.redraw();
                this.saveState();
            }
            return;
        }
        
        // Handle rotate tool - click on object to rotate it
        if (this.currentTool === 'rotate') {
            const obj = this.findObjectAt(x, y);
            if (obj) {
                obj.rotation = ((obj.rotation || 0) + 45) % 360;
                this.redraw();
                this.saveState();
            }
            return;
        }
        
        // Handle delete tool - click on object to delete it
        if (this.currentTool === 'delete') {
            const obj = this.findObjectAt(x, y);
            if (obj) {
                const index = this.objects.indexOf(obj);
                if (index > -1) {
                    this.objects.splice(index, 1);
                    this.redraw();
                    this.saveState();
                }
            }
            return;
        }
        
        // Handle text tool with prompt
        if (this.currentTool === 'text') {
            const text = prompt('Enter text:');
            if (text) {
                this.objects.push({
                    type: 'text',
                    x: x,
                    y: y,
                    text: text,
                    color: this.activeColor
                });
                this.redraw();
                this.saveState();
            }
            return;
        }
        
        // Player position tools
        const playerPositions = {
            'forward': { label: 'F', color: '#00bfff' },
            'f1': { label: 'F1', color: '#00bfff' },
            'f2': { label: 'F2', color: '#00bfff' },
            'f3': { label: 'F3', color: '#00bfff' },
            'defense': { label: 'D', color: '#0066cc' },
            'd1': { label: 'D1', color: '#0066cc' },
            'd2': { label: 'D2', color: '#0066cc' },
            'goalie': { label: 'G', color: '#cc0000' },
            'coach': { label: 'C', color: '#333', isCoach: true },
            'center': { label: 'C', color: '#ff6600' },
            'lw': { label: 'LW', color: '#ff6600' },
            'rw': { label: 'RW', color: '#ff6600' },
            'ld': { label: 'LD', color: '#0066cc' },
            'rd': { label: 'RD', color: '#0066cc' }
        };
        
        if (playerPositions[this.currentTool]) {
            const pos = playerPositions[this.currentTool];
            this.objects.push({
                type: 'player',
                x: x,
                y: y,
                color: pos.color,
                label: pos.label,
                isCoach: pos.isCoach || false,
                rotation: 0
            });
            this.redraw();
            this.saveState();
            return;
        }
        
        // Number tools
        const numberMatch = this.currentTool.match(/^num(\d)$/);
        if (numberMatch) {
            this.objects.push({
                type: 'number',
                x: x,
                y: y,
                value: numberMatch[1],
                color: this.activeColor,
                rotation: 0
            });
            this.redraw();
            this.saveState();
            return;
        }
        
        // Equipment tools
        if (this.currentTool === 'puck') {
            this.objects.push({
                type: 'puck',
                x: x,
                y: y,
                color: this.activeColor || '#000000'
            });
        } else if (this.currentTool === 'pucks') {
            this.objects.push({
                type: 'pucks',
                x: x,
                y: y,
                color: this.activeColor || '#000000'
            });
        } else if (this.currentTool === 'cone') {
            this.objects.push({
                type: 'cone',
                x: x,
                y: y,
                color: this.activeColor || '#ff6b00',
                rotation: 0
            });
        } else if (this.currentTool === 'net') {
            this.objects.push({
                type: 'net',
                x: x,
                y: y,
                rotation: 0,
                color: this.activeColor || '#c41e3a'
            });
        } else if (this.currentTool === 'mininet') {
            this.objects.push({
                type: 'mininet',
                x: x,
                y: y,
                rotation: 0,
                color: this.activeColor || '#c41e3a'
            });
        } else if (this.currentTool === 'tire') {
            this.objects.push({
                type: 'tire',
                x: x,
                y: y,
                color: this.activeColor || '#333333'
            });
        } else if (this.currentTool === 'stick') {
            this.objects.push({
                type: 'stick',
                x: x,
                y: y,
                rotation: 0,
                color: this.activeColor || '#8B4513'
            });
        } else if (this.currentTool === 'player') {
            this.objects.push({
                type: 'player',
                x: x,
                y: y,
                color: this.activeColor || '#00bfff',
                rotation: 0
            });
        }
        
        this.redraw();
        this.saveState();
    }
    
    findObjectAt(x, y) {
        for (let i = this.objects.length - 1; i >= 0; i--) {
            const obj = this.objects[i];
            // Increased hit area for easier selection (30^2 = 900 instead of 20^2 = 400)
            const hitRadiusSquared = 900;
            
            // Check if the object is close to the click position
            if (obj.x !== undefined && obj.y !== undefined) {
                // Standard point-based objects
                const dx = x - obj.x;
                const dy = y - obj.y;
                if (dx * dx + dy * dy < hitRadiusSquared) {
                    return obj;
                }
            } else if (LINE_TOOLS.includes(obj.type)) {
                // Line-based objects - check distance to line segment (increased from 15 to 25)
                const distToLine = this.pointToLineDistance(x, y, obj.x1, obj.y1, obj.x2, obj.y2);
                if (distToLine < 25) {
                    return obj;
                }
            } else if (FREEHAND_TOOLS.includes(obj.type) && obj.points && obj.points.length > 0) {
                // Freehand drawing types - check distance to any point in the path
                for (let p = 0; p < obj.points.length; p++) {
                    const pt = obj.points[p];
                    const dx = x - pt.x;
                    const dy = y - pt.y;
                    if (dx * dx + dy * dy < hitRadiusSquared) {
                        return obj;
                    }
                }
            }
        }
        return null;
    }
    
    // Calculate distance from point to line segment
    pointToLineDistance(px, py, x1, y1, x2, y2) {
        const A = px - x1;
        const B = py - y1;
        const C = x2 - x1;
        const D = y2 - y1;
        
        const dot = A * C + B * D;
        const lenSq = C * C + D * D;
        let param = -1;
        
        if (lenSq !== 0) {
            param = dot / lenSq;
        }
        
        let xx, yy;
        
        if (param < 0) {
            xx = x1;
            yy = y1;
        } else if (param > 1) {
            xx = x2;
            yy = y2;
        } else {
            xx = x1 + param * C;
            yy = y1 + param * D;
        }
        
        const dx = px - xx;
        const dy = py - yy;
        return Math.sqrt(dx * dx + dy * dy);
    }
    
    // Smooth freehand points using Douglas-Peucker algorithm and bezier smoothing
    smoothFreehandPoints(points) {
        if (points.length < 3) return points;
        
        // First, reduce points using a simple distance-based filter
        const simplified = [points[0]];
        const minDistance = 5; // Minimum distance between points
        
        for (let i = 1; i < points.length; i++) {
            const dx = points[i].x - simplified[simplified.length - 1].x;
            const dy = points[i].y - simplified[simplified.length - 1].y;
            if (Math.sqrt(dx * dx + dy * dy) >= minDistance) {
                simplified.push(points[i]);
            }
        }
        
        // Always include the last point
        if (simplified[simplified.length - 1] !== points[points.length - 1]) {
            simplified.push(points[points.length - 1]);
        }
        
        return simplified;
    }
    
    drawRink() {
        const w = this.canvas.width;
        const h = this.canvas.height;
        const ctx = this.ctx;
        const iceView = this.iceView || 'full';
        
        // Clear and draw ice background (light blue ice tone)
        ctx.fillStyle = '#f0f7fa';
        ctx.fillRect(0, 0, w, h);
        
        // Draw center logo (image if available, otherwise text at 12% opacity)
        ctx.save();
        ctx.globalAlpha = 0.12;
        
        if (this.centerLogoLoaded && this.centerLogoImage) {
            // Draw logo image centered on ice
            const maxLogoWidth = w * 0.3;  // Logo takes up 30% of canvas width
            const maxLogoHeight = h * 0.25; // Max 25% of height
            
            // Calculate scaled dimensions maintaining aspect ratio
            const imgAspect = this.centerLogoImage.width / this.centerLogoImage.height;
            let logoWidth = maxLogoWidth;
            let logoHeight = logoWidth / imgAspect;
            
            if (logoHeight > maxLogoHeight) {
                logoHeight = maxLogoHeight;
                logoWidth = logoHeight * imgAspect;
            }
            
            const logoX = (w - logoWidth) / 2;
            const logoY = (h - logoHeight) / 2;
            
            ctx.drawImage(this.centerLogoImage, logoX, logoY, logoWidth, logoHeight);
        } else {
            // Fallback to text branding
            ctx.fillStyle = '#7000a4';
            ctx.font = 'bold 48px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(this.brandingText, w/2, h/2 - 15);
            ctx.font = '24px Inter, sans-serif';
            ctx.fillText(this.brandingSubtext, w/2, h/2 + 25);
        }
        ctx.restore();
        
        // Draw based on ice view
        switch(iceView) {
            case 'half-top':
                this.drawHalfIce(ctx, w, h, 'top');
                break;
            case 'half-bottom':
                this.drawHalfIce(ctx, w, h, 'bottom');
                break;
            case 'left-zone':
                this.drawZone(ctx, w, h, 'left');
                break;
            case 'right-zone':
                this.drawZone(ctx, w, h, 'right');
                break;
            case 'center':
                this.drawCenterIce(ctx, w, h);
                break;
            default:
                this.drawFullIce(ctx, w, h);
        }
        
        // Draw rink boards (adapted to view type)
        this.drawRinkBorder(ctx, w, h, iceView);
    }
    
    // Draw rink border that adapts to view type
    drawRinkBorder(ctx, w, h, iceView) {
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 4;
        
        // NHL corner radius: 28 ft on 85 ft width (~0.329 ratio)
        // For full ice (horizontal layout), use height as reference since width represents length
        // For half ice (vertical layout with net at top/bottom), use width as reference
        let cornerRadius;
        if (iceView === 'half-top' || iceView === 'half-bottom') {
            // Half ice views are oriented vertically - width represents the 85 ft rink width
            cornerRadius = w * NHL_RINK.CORNER_RADIUS;
        } else {
            // Full ice, zones, and center - height represents the 85 ft rink width
            cornerRadius = h * NHL_RINK.CORNER_RADIUS;
        }
        
        if (iceView === 'half-top' || iceView === 'half-bottom') {
            // Half ice views - curved corners at net end, flat at center line end
            const isTop = iceView === 'half-top';
            ctx.beginPath();
            if (isTop) {
                // Curved corners at top (net end), flat at bottom (center line)
                ctx.moveTo(cornerRadius + 2, 2);
                ctx.lineTo(w - cornerRadius - 2, 2);
                ctx.quadraticCurveTo(w - 2, 2, w - 2, cornerRadius + 2);
                ctx.lineTo(w - 2, h - 2);
                ctx.lineTo(2, h - 2);
                ctx.lineTo(2, cornerRadius + 2);
                ctx.quadraticCurveTo(2, 2, cornerRadius + 2, 2);
            } else {
                // Flat at top (center line), curved corners at bottom (net end)
                ctx.moveTo(2, 2);
                ctx.lineTo(w - 2, 2);
                ctx.lineTo(w - 2, h - cornerRadius - 2);
                ctx.quadraticCurveTo(w - 2, h - 2, w - cornerRadius - 2, h - 2);
                ctx.lineTo(cornerRadius + 2, h - 2);
                ctx.quadraticCurveTo(2, h - 2, 2, h - cornerRadius - 2);
                ctx.lineTo(2, 2);
            }
            ctx.closePath();
            ctx.stroke();
        } else if (iceView === 'left-zone' || iceView === 'right-zone') {
            // Zone views - curved corners at net end, flat at blue line end
            const isLeft = iceView === 'left-zone';
            ctx.beginPath();
            if (isLeft) {
                // Curved corners at left (net end), flat at right (blue line side)
                ctx.moveTo(cornerRadius + 2, 2);
                ctx.lineTo(w - 2, 2);
                ctx.lineTo(w - 2, h - 2);
                ctx.lineTo(cornerRadius + 2, h - 2);
                ctx.quadraticCurveTo(2, h - 2, 2, h - cornerRadius - 2);
                ctx.lineTo(2, cornerRadius + 2);
                ctx.quadraticCurveTo(2, 2, cornerRadius + 2, 2);
            } else {
                // Flat at left (blue line side), curved corners at right (net end)
                ctx.moveTo(2, 2);
                ctx.lineTo(w - cornerRadius - 2, 2);
                ctx.quadraticCurveTo(w - 2, 2, w - 2, cornerRadius + 2);
                ctx.lineTo(w - 2, h - cornerRadius - 2);
                ctx.quadraticCurveTo(w - 2, h - 2, w - cornerRadius - 2, h - 2);
                ctx.lineTo(2, h - 2);
                ctx.lineTo(2, 2);
            }
            ctx.closePath();
            ctx.stroke();
        } else {
            // Full ice and center - all corners rounded
            this.roundRect(ctx, 2, 2, w - 4, h - 4, cornerRadius);
            ctx.stroke();
        }
    }
    
    drawFullIce(ctx, w, h) {
        // NHL/Hockey Canada proportions for full ice view
        // Goal line at 5.5% from end (11 ft / 200 ft)
        const goalLinePos = NHL_RINK.GOAL_LINE;
        // Blue lines at 32% from ends (64 ft / 200 ft)
        const blueLinePos = NHL_RINK.BLUE_LINE;
        // Faceoff circles: 20 ft from goal line, 22 ft from boards
        const faceoffFromGoal = goalLinePos + NHL_RINK.FACEOFF_FROM_GOAL;
        const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
        // NHL corner radius: 28 ft on 85 ft width (~0.329 of the height, which represents width in horizontal layout)
        const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
        
        // Center line (red)
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.moveTo(w/2, 0);
        ctx.lineTo(w/2, h);
        ctx.stroke();
        
        // Blue lines (64 ft from each end = 0.32)
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(w * blueLinePos, 0);
        ctx.lineTo(w * blueLinePos, h);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(w * (1 - blueLinePos), 0);
        ctx.lineTo(w * (1 - blueLinePos), h);
        ctx.stroke();
        
        // Center circle (15 ft radius)
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w/2, h/2, h * NHL_RINK.CENTER_CIRCLE_RADIUS, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Center dot (12 inches = 1 ft diameter)
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        ctx.arc(w/2, h/2, 5, 0, 2 * Math.PI);
        ctx.fill();
        
        // Faceoff circles with dots (15 ft radius, positioned 20 ft from goal line, 22 ft from boards)
        const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
        const circles = [
            { x: w * faceoffFromGoal, y: h * faceoffFromBoards, zone: 'left' },
            { x: w * faceoffFromGoal, y: h * (1 - faceoffFromBoards), zone: 'left' },
            { x: w * (1 - faceoffFromGoal), y: h * faceoffFromBoards, zone: 'right' },
            { x: w * (1 - faceoffFromGoal), y: h * (1 - faceoffFromBoards), zone: 'right' }
        ];
        
        circles.forEach(circle => {
            // Faceoff circle
            ctx.strokeStyle = '#c41e3a';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, faceoffRadius, 0, 2 * Math.PI);
            ctx.stroke();
            
            // Faceoff dot (2 ft diameter)
            ctx.fillStyle = '#c41e3a';
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, 4, 0, 2 * Math.PI);
            ctx.fill();
            
            // Draw hash marks around faceoff circles (nets on left/right)
            this.drawHashMarks(ctx, circle.x, circle.y, faceoffRadius, 'horizontal');
            
            // Draw faceoff restraint lines (L-shaped lines inside the circle)
            this.drawRestraintLines(ctx, circle.x, circle.y, faceoffRadius, circle.zone, h);
        });
        
        // Goal creases (6 ft radius semicircle)
        const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
        
        // Left goal crease - semicircle at goal line position
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)'; // Light blue fill
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w * goalLinePos, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
        ctx.fill();
        ctx.stroke();
        
        // Left goal line - extends to the boards but respects curved corners
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        // Calculate the y-offset due to curved corners in full ice view
        // The goal line at x = w * goalLinePos may be within the corner curve
        // Corner radius is based on height (which represents 85 ft width)
        const goalLineX = w * goalLinePos;
        let leftGoalLineStartY = 0;
        let leftGoalLineEndY = h;
        
        if (goalLineX < cornerRadius) {
            // Goal line is in the curved corner region
            // x_offset from corner center = cornerRadius - goalLineX
            const dx = cornerRadius - goalLineX;
            const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
            leftGoalLineStartY = yOffset;
            leftGoalLineEndY = h - yOffset;
        }
        
        ctx.moveTo(goalLineX, leftGoalLineStartY);
        ctx.lineTo(goalLineX, leftGoalLineEndY);
        ctx.stroke();
        
        // Right goal crease - semicircle  
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w * (1 - goalLinePos), h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
        ctx.fill();
        ctx.stroke();
        
        // Right goal line - extends to the boards but respects curved corners
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        // Calculate the y-offset due to curved corners
        const rightGoalLineX = w * (1 - goalLinePos);
        let rightGoalLineStartY = 0;
        let rightGoalLineEndY = h;
        
        if ((w - rightGoalLineX) < cornerRadius) {
            // Goal line is in the curved corner region on the right side
            const dx = cornerRadius - (w - rightGoalLineX);
            const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
            rightGoalLineStartY = yOffset;
            rightGoalLineEndY = h - yOffset;
        }
        
        ctx.moveTo(rightGoalLineX, rightGoalLineStartY);
        ctx.lineTo(rightGoalLineX, rightGoalLineEndY);
        ctx.stroke();
        
        // Draw goalie trapezoids behind each net
        this.drawTrapezoid(ctx, w, h, 'left');
        this.drawTrapezoid(ctx, w, h, 'right');
        
        // Draw neutral zone faceoff dots (5 ft from blue lines)
        // These are just dots, no circles
        const neutralZoneDotOffset = 5 / 200; // 5 ft from blue line
        const neutralDots = [
            { x: w * (blueLinePos + neutralZoneDotOffset), y: h * faceoffFromBoards },
            { x: w * (blueLinePos + neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) },
            { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * faceoffFromBoards },
            { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) }
        ];
        
        ctx.fillStyle = '#c41e3a';
        neutralDots.forEach(dot => {
            ctx.beginPath();
            ctx.arc(dot.x, dot.y, 4, 0, 2 * Math.PI);
            ctx.fill();
        });
    }
    
    // Draw goalie trapezoid behind the net
    drawTrapezoid(ctx, w, h, side) {
        const goalLinePos = NHL_RINK.GOAL_LINE;
        const trapezoidBase = h * NHL_RINK.TRAPEZOID_BASE / 2; // Half width at goal line
        const trapezoidTop = h * NHL_RINK.TRAPEZOID_TOP / 2;   // Half width at boards
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        
        if (side === 'left') {
            const goalX = w * goalLinePos;
            // Left trapezoid - from goal line to left boards
            ctx.beginPath();
            // Top line (from goal line going towards boards)
            ctx.moveTo(goalX, h/2 - trapezoidBase);
            ctx.lineTo(0, h/2 - trapezoidTop);
            ctx.stroke();
            // Bottom line
            ctx.beginPath();
            ctx.moveTo(goalX, h/2 + trapezoidBase);
            ctx.lineTo(0, h/2 + trapezoidTop);
            ctx.stroke();
        } else {
            const goalX = w * (1 - goalLinePos);
            // Right trapezoid - from goal line to right boards
            ctx.beginPath();
            // Top line
            ctx.moveTo(goalX, h/2 - trapezoidBase);
            ctx.lineTo(w, h/2 - trapezoidTop);
            ctx.stroke();
            // Bottom line
            ctx.beginPath();
            ctx.moveTo(goalX, h/2 + trapezoidBase);
            ctx.lineTo(w, h/2 + trapezoidTop);
            ctx.stroke();
        }
    }
    
    // Draw faceoff restraint lines (L-shaped lines inside end zone faceoff circles)
    drawRestraintLines(ctx, cx, cy, radius, zone, canvasHeight) {
        const lineLength = canvasHeight * NHL_RINK.RESTRAINT_LINE_LENGTH * 1.5; // Slightly longer for visibility
        const offset = radius * 0.15; // Distance from center dot
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        
        // There are 4 L-shaped restraint lines in each faceoff circle
        // Two on the goal side point towards the goal
        // Two on the blue line side (away from goal) point towards the blue line (flipped)
        
        // The L-shapes on the goal side point towards the goal (left or right)
        const goalDirection = zone === 'left' ? -1 : 1;
        // The L-shapes on the blue line side point away from the goal (opposite direction)
        const blueLineDirection = -goalDirection;
        
        // For left zone: goal is on left, blue line is on right
        //   - Left L-shapes (cx - offset) point towards goal (left)
        //   - Right L-shapes (cx + offset) point towards blue line (right)
        // For right zone: goal is on right, blue line is on left
        //   - Right L-shapes (cx + offset) point towards goal (right)
        //   - Left L-shapes (cx - offset) point towards blue line (left)
        
        // Top-left L-shape (relative to faceoff dot)
        this.drawLShape(ctx, cx - offset, cy - offset, lineLength, zone === 'left' ? goalDirection : blueLineDirection, -1);
        // Top-right L-shape
        this.drawLShape(ctx, cx + offset, cy - offset, lineLength, zone === 'left' ? blueLineDirection : goalDirection, -1);
        // Bottom-left L-shape
        this.drawLShape(ctx, cx - offset, cy + offset, lineLength, zone === 'left' ? goalDirection : blueLineDirection, 1);
        // Bottom-right L-shape
        this.drawLShape(ctx, cx + offset, cy + offset, lineLength, zone === 'left' ? blueLineDirection : goalDirection, 1);
    }
    
    // Draw a single L-shaped restraint line
    drawLShape(ctx, x, y, length, hDir, vDir) {
        ctx.beginPath();
        // Vertical part of L
        ctx.moveTo(x, y);
        ctx.lineTo(x, y + vDir * length);
        ctx.stroke();
        
        ctx.beginPath();
        // Horizontal part of L (towards goal)
        ctx.moveTo(x, y);
        ctx.lineTo(x + hDir * length, y);
        ctx.stroke();
    }
    
    // Helper function to draw hash marks around faceoff circles
    // Hockey Canada regulation hash marks:
    // - Two 2-foot parallel red lines on each side of the faceoff circle (4 total per circle)
    // - Lines are 3 feet apart (horizontal distance between hash marks in each pair)
    // - Hash marks are 2 feet long
    // - Hash marks positioned perpendicular to the goal line
    // netPosition: 'horizontal' (nets on left/right, hash marks on top/bottom)
    //              'vertical' (nets on top/bottom, hash marks on left/right)
    drawHashMarks(ctx, cx, cy, radius, netPosition) {
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        
        // The faceoff circle radius is 15 feet, so scale factors:
        // 2 feet = radius * (2/15) = radius * 0.1333
        // 3 feet = radius * (3/15) = radius * 0.2
        const hashLength = radius * (2 / 15); // 2 feet scaled (exact calculation)
        const hashSpacing = radius * (3 / 15); // 3 feet spacing between hash marks in pair
        
        // Small gap outside the circle in canvas pixels (visual offset for clarity)
        const gapOutsideCircle = radius * 0.05;
        
        // Hash marks start just outside the circle edge
        const startDistance = radius + gapOutsideCircle;
        
        const sides = [-1, 1];
        
        if (netPosition === 'vertical') {
            // Nets on top/bottom - hash marks on LEFT and RIGHT of circle (horizontal lines)
            sides.forEach(side => {
                const startX = cx + side * startDistance;
                const endX = startX + side * hashLength;
                
                // Top hash mark
                ctx.beginPath();
                ctx.moveTo(startX, cy - hashSpacing / 2);
                ctx.lineTo(endX, cy - hashSpacing / 2);
                ctx.stroke();
                
                // Bottom hash mark
                ctx.beginPath();
                ctx.moveTo(startX, cy + hashSpacing / 2);
                ctx.lineTo(endX, cy + hashSpacing / 2);
                ctx.stroke();
            });
        } else {
            // Nets on left/right (default) - hash marks on TOP and BOTTOM of circle (vertical lines)
            sides.forEach(side => {
                const startY = cy + side * startDistance;
                const endY = startY + side * hashLength;
                
                // Left hash mark
                ctx.beginPath();
                ctx.moveTo(cx - hashSpacing / 2, startY);
                ctx.lineTo(cx - hashSpacing / 2, endY);
                ctx.stroke();
                
                // Right hash mark
                ctx.beginPath();
                ctx.moveTo(cx + hashSpacing / 2, startY);
                ctx.lineTo(cx + hashSpacing / 2, endY);
                ctx.stroke();
            });
        }
    }
    
    drawHalfIce(ctx, w, h, side) {
        // Half ice shows one end zone with faceoff circles
        // Half ice represents approximately half the rink - from boards to center line
        // NHL half ice: ~100 ft (half of 200 ft length) × 85 ft width
        // The canvas now represents this portion properly
        
        // Use width as the 85 ft reference since half-ice is oriented with net at top/bottom
        const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
        const faceoffRadius = w * NHL_RINK.FACEOFF_RADIUS;
        const creaseRadius = w * NHL_RINK.CREASE_RADIUS;
        
        // Calculate positions based on half-ice proportions
        // Half rink: goal line is 11 ft from end, blue line is 64 ft from end
        // In half ice view, we show from end boards to center (100 ft)
        // Goal line: 11 ft from end = 11/100 = 0.11 of half ice height
        // Blue line: 64 ft from end = 64/100 = 0.64 of half ice height
        // Faceoff dot: 31 ft from end (11 + 20) = 31/100 = 0.31 of half ice height
        const goalLineRatio = 11 / 100;      // Goal line at 11% from net end
        const blueLineRatio = 64 / 100;      // Blue line at 64% from net end
        const faceoffYRatio = 31 / 100;      // Faceoff dot at 31% from net end
        
        // Blue line position
        const blueLineY = side === 'top' ? h * blueLineRatio : h * (1 - blueLineRatio);
        
        // Blue line
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(0, blueLineY);
        ctx.lineTo(w, blueLineY);
        ctx.stroke();
        
        // Goal line position
        const goalY = side === 'top' ? h * goalLineRatio : h * (1 - goalLineRatio);
        
        // Faceoff circles - positioned 22 ft from boards on each side
        const faceoffY = side === 'top' ? h * faceoffYRatio : h * (1 - faceoffYRatio);
        
        // Left faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w * faceoffFromBoards, faceoffY, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(w * faceoffFromBoards, faceoffY, 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, 'vertical');
        // Draw restraint lines for half ice (goal is at top or bottom)
        this.drawHalfIceRestraintLines(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, side, w);
        
        // Right faceoff circle  
        ctx.strokeStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(w * (1 - faceoffFromBoards), faceoffY, 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 'vertical');
        this.drawHalfIceRestraintLines(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, side, w);
        
        // Goal crease - 6 ft radius semicircle
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        if (side === 'top') {
            ctx.arc(w * 0.5, goalY, creaseRadius, 0, Math.PI);
        } else {
            ctx.arc(w * 0.5, goalY, creaseRadius, Math.PI, 0);
        }
        ctx.fill();
        ctx.stroke();
        
        // Goal line - extends to boards but respects the curved corners
        // The corners are curved with cornerRadius, so we need to clip the goal line
        // to not exceed the board boundary at this y-position
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        // Calculate the x-offset due to curved corners
        // In half-ice view, width represents the 85 ft rink width
        const cornerRadius = w * NHL_RINK.CORNER_RADIUS;
        
        // Distance from the net end (top or bottom) to the goal line
        const distFromEnd = side === 'top' ? goalY : (h - goalY);
        
        // If the goal line is within the corner curve region, we need to offset the x
        let goalLineStartX = 0;
        let goalLineEndX = w;
        
        if (distFromEnd < cornerRadius) {
            // Goal line is in the curved corner region
            // For a quarter-circle corner with radius R at the corner:
            // x_offset = R - sqrt(R^2 - (R - distFromEnd)^2)
            const dy = cornerRadius - distFromEnd;
            const xOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dy * dy);
            goalLineStartX = xOffset;
            goalLineEndX = w - xOffset;
        }
        
        ctx.moveTo(goalLineStartX, goalY);
        ctx.lineTo(goalLineEndX, goalY);
        ctx.stroke();
        
        // Draw trapezoid behind net (for half ice view)
        this.drawHalfIceTrapezoid(ctx, w, h, side, goalY);
    }
    
    // Draw trapezoid for half ice view (net at top or bottom)
    drawHalfIceTrapezoid(ctx, w, h, side, goalY) {
        const trapezoidBase = w * NHL_RINK.TRAPEZOID_BASE / 2;
        const trapezoidTop = w * NHL_RINK.TRAPEZOID_TOP / 2;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        
        if (side === 'top') {
            // Trapezoid goes from goal line to top edge
            ctx.beginPath();
            ctx.moveTo(w/2 - trapezoidBase, goalY);
            ctx.lineTo(w/2 - trapezoidTop, 0);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(w/2 + trapezoidBase, goalY);
            ctx.lineTo(w/2 + trapezoidTop, 0);
            ctx.stroke();
        } else {
            // Trapezoid goes from goal line to bottom edge
            ctx.beginPath();
            ctx.moveTo(w/2 - trapezoidBase, goalY);
            ctx.lineTo(w/2 - trapezoidTop, h);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(w/2 + trapezoidBase, goalY);
            ctx.lineTo(w/2 + trapezoidTop, h);
            ctx.stroke();
        }
    }
    
    // Draw restraint lines for half ice view (net at top or bottom)
    drawHalfIceRestraintLines(ctx, cx, cy, radius, side, canvasWidth) {
        const lineLength = canvasWidth * NHL_RINK.RESTRAINT_LINE_LENGTH * 1.5;
        const offset = radius * 0.15;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        
        // Goal direction for half ice (top = -1, bottom = 1 for Y direction)
        const goalDirection = side === 'top' ? -1 : 1;
        // Blue line direction is opposite (away from goal)
        const blueLineDirection = -goalDirection;
        
        // L-shapes on the goal side point towards the goal
        // L-shapes on the blue line side (away from goal) point towards the blue line (flipped)
        // For top goal: top L-shapes (cy - offset) point up, bottom L-shapes (cy + offset) point down
        // For bottom goal: bottom L-shapes (cy + offset) point down, top L-shapes (cy - offset) point up
        
        // Left side of faceoff dot
        this.drawHalfIceLShape(ctx, cx - offset, cy - offset, lineLength, side === 'top' ? goalDirection : blueLineDirection);
        this.drawHalfIceLShape(ctx, cx - offset, cy + offset, lineLength, side === 'top' ? blueLineDirection : goalDirection);
        // Right side of faceoff dot
        this.drawHalfIceLShape(ctx, cx + offset, cy - offset, lineLength, side === 'top' ? goalDirection : blueLineDirection);
        this.drawHalfIceLShape(ctx, cx + offset, cy + offset, lineLength, side === 'top' ? blueLineDirection : goalDirection);
    }
    
    drawHalfIceLShape(ctx, x, y, length, vDir) {
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x, y + vDir * length);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(x - length/2, y);
        ctx.lineTo(x + length/2, y);
        ctx.stroke();
    }
    
    drawZone(ctx, w, h, side) {
        // Left/Right Zone view: shows one half of the rink (from end boards to center line)
        // For zone view, the canvas represents half the rink: ~100 ft × 85 ft
        // height = 85 ft (rink width), width = 100 ft (half rink length)
        
        // Use height as the 85 ft reference
        const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
        const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
        const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
        const centerCircleRadius = h * NHL_RINK.CENTER_CIRCLE_RADIUS;
        
        // Calculate positions based on half-rink proportions (100 ft span)
        // Goal line: 11 ft from end = 11/100 = 0.11
        // Blue line: 64 ft from end = 64/100 = 0.64
        // Faceoff dot: 31 ft from end (11 + 20) = 31/100 = 0.31
        const goalLineRatio = 11 / 100;
        const blueLineRatio = 64 / 100;
        const faceoffXRatio = 31 / 100;
        const neutralZoneDotRatio = (64 + 5) / 100; // 5 ft from blue line towards center
        
        // Center line (red) - at the far edge from goal
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 4;
        if (side === 'left') {
            // Center line at right edge for left zone
            ctx.beginPath();
            ctx.moveTo(w, 0);
            ctx.lineTo(w, h);
            ctx.stroke();
        } else {
            // Center line at left edge for right zone
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(0, h);
            ctx.stroke();
        }
        
        // Blue line position
        const blueLineX = side === 'left' ? w * blueLineRatio : w * (1 - blueLineRatio);
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(blueLineX, 0);
        ctx.lineTo(blueLineX, h);
        ctx.stroke();
        
        // Goal line position - respects curved corners
        const goalLineX = side === 'left' ? w * goalLineRatio : w * (1 - goalLineRatio);
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        // Calculate the y-offset due to curved corners
        // In zone view, height represents the 85 ft width, so corner radius is based on height
        const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
        
        // Distance from the net end (left or right) to the goal line
        const distFromEnd = side === 'left' ? goalLineX : (w - goalLineX);
        let zoneGoalLineStartY = 0;
        let zoneGoalLineEndY = h;
        
        if (distFromEnd < cornerRadius) {
            // Goal line is in the curved corner region
            const dx = cornerRadius - distFromEnd;
            const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
            zoneGoalLineStartY = yOffset;
            zoneGoalLineEndY = h - yOffset;
        }
        
        ctx.moveTo(goalLineX, zoneGoalLineStartY);
        ctx.lineTo(goalLineX, zoneGoalLineEndY);
        ctx.stroke();
        
        // Half center circle (at the edge)
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2;
        ctx.beginPath();
        if (side === 'left') {
            // Right half of center circle at right edge
            ctx.arc(w, h/2, centerCircleRadius, Math.PI/2, -Math.PI/2);
        } else {
            // Left half of center circle at left edge
            ctx.arc(0, h/2, centerCircleRadius, -Math.PI/2, Math.PI/2);
        }
        ctx.stroke();
        
        // Center dot (at edge)
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        if (side === 'left') {
            ctx.arc(w, h/2, 5, 0, 2 * Math.PI);
        } else {
            ctx.arc(0, h/2, 5, 0, 2 * Math.PI);
        }
        ctx.fill();
        
        // Faceoff circles in this zone
        const faceoffX = side === 'left' ? w * faceoffXRatio : w * (1 - faceoffXRatio);
        
        // Top faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(faceoffX, h * faceoffFromBoards, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(faceoffX, h * faceoffFromBoards, 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, 'horizontal');
        this.drawRestraintLines(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, side, h);
        
        // Bottom faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(faceoffX, h * (1 - faceoffFromBoards), 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 'horizontal');
        this.drawRestraintLines(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, side, h);
        
        // Neutral zone faceoff dots (between blue line and center line)
        const neutralDotX = side === 'left' ? w * neutralZoneDotRatio : w * (1 - neutralZoneDotRatio);
        
        ctx.fillStyle = '#c41e3a';
        // Top neutral dot
        ctx.beginPath();
        ctx.arc(neutralDotX, h * faceoffFromBoards, 4, 0, 2 * Math.PI);
        ctx.fill();
        // Bottom neutral dot
        ctx.beginPath();
        ctx.arc(neutralDotX, h * (1 - faceoffFromBoards), 4, 0, 2 * Math.PI);
        ctx.fill();
        
        // Goal crease - semicircle
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        if (side === 'left') {
            ctx.arc(goalLineX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
        } else {
            ctx.arc(goalLineX, h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
        }
        ctx.fill();
        ctx.stroke();
        
        // Draw trapezoid behind net
        this.drawZoneTrapezoid(ctx, w, h, side, goalLineX);
    }
    
    // Draw trapezoid for zone view (net at left or right)
    drawZoneTrapezoid(ctx, w, h, side, goalLineX) {
        const trapezoidBase = h * NHL_RINK.TRAPEZOID_BASE / 2;
        const trapezoidTop = h * NHL_RINK.TRAPEZOID_TOP / 2;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        
        if (side === 'left') {
            // Trapezoid goes from goal line to left edge
            ctx.beginPath();
            ctx.moveTo(goalLineX, h/2 - trapezoidBase);
            ctx.lineTo(0, h/2 - trapezoidTop);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(goalLineX, h/2 + trapezoidBase);
            ctx.lineTo(0, h/2 + trapezoidTop);
            ctx.stroke();
        } else {
            // Trapezoid goes from goal line to right edge
            ctx.beginPath();
            ctx.moveTo(goalLineX, h/2 - trapezoidBase);
            ctx.lineTo(w, h/2 - trapezoidTop);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(goalLineX, h/2 + trapezoidBase);
            ctx.lineTo(w, h/2 + trapezoidTop);
            ctx.stroke();
        }
    }
    
    drawCenterIce(ctx, w, h) {
        // Center ice view shows the neutral zone area around center ice
        // The center circle radius is 15 ft on an 85 ft wide rink
        
        // Center line (red)
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.moveTo(w/2, 0);
        ctx.lineTo(w/2, h);
        ctx.stroke();
        
        // Center circle - use NHL proportions (15 ft radius on 85 ft width)
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2;
        const circleRadius = h * NHL_RINK.CENTER_CIRCLE_RADIUS;
        ctx.beginPath();
        ctx.arc(w/2, h/2, circleRadius, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Center dot
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        ctx.arc(w/2, h/2, 6, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    roundRect(ctx, x, y, width, height, radius) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + height - radius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        ctx.lineTo(x + radius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();
    }
    
    setIceView(view) {
        this.iceView = view;
        
        // Update the container's data-ice-view attribute for dynamic CSS aspect ratio
        const container = this.canvas.parentElement;
        if (container) {
            container.setAttribute('data-ice-view', view);
            
            // Wait for CSS transition to complete (300ms defined in CSS), then resize canvas to new container size
            setTimeout(() => {
                this.canvas.width = container.offsetWidth;
                this.canvas.height = container.offsetHeight;
                this.redraw();
            }, 350);
        } else {
            this.redraw();
        }
    }
    
    redraw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.drawRink();
        
        this.objects.forEach(obj => {
            if (obj.type === 'player') {
                this.drawPlayer(obj.x, obj.y, obj.color, obj.label, obj.isCoach, obj.rotation);
            } else if (obj.type === 'cone') {
                this.drawCone(obj.x, obj.y, obj.color, obj.rotation);
            } else if (obj.type === 'line') {
                // Support both old (x1,y1,x2,y2) and new (points) format for backward compatibility
                if (obj.points) {
                    this.drawFreehandLine(obj.points, obj.color || '#333');
                } else if (obj.x1 !== undefined) {
                    this.drawLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#333');
                }
            } else if (obj.type === 'dashed') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandDashed(obj.points, obj.color || '#333');
                } else if (obj.x1 !== undefined) {
                    this.drawDashedLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#333');
                }
            } else if (obj.type === 'squiggly') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandSquiggly(obj.points, obj.color || '#333');
                } else if (obj.x1 !== undefined) {
                    this.drawSquigglyLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#333');
                }
            } else if (obj.type === 'arrow') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandArrow(obj.points, obj.color || '#333');
                } else if (obj.x1 !== undefined) {
                    this.drawArrow(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#333');
                }
            } else if (obj.type === 'skating_forward') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandSkatingForward(obj.points, obj.color || '#0033a0');
                } else if (obj.x1 !== undefined) {
                    this.drawSkatingForward(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#0033a0');
                }
            } else if (obj.type === 'skating_backward') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandSkatingBackward(obj.points, obj.color || '#c41e3a');
                } else if (obj.x1 !== undefined) {
                    this.drawSkatingBackward(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#c41e3a');
                }
            } else if (obj.type === 'skating_lateral') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandSkatingLateral(obj.points, obj.color || '#10b981');
                } else if (obj.x1 !== undefined) {
                    this.drawSkatingLateral(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#10b981');
                }
            } else if (obj.type === 'skating_ccuts') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandSkatingCCuts(obj.points, obj.color || '#8b5cf6');
                } else if (obj.x1 !== undefined) {
                    this.drawSkatingCCuts(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#8b5cf6');
                }
            } else if (obj.type === 'skating_forward_puck') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandSkatingForwardPuck(obj.points, obj.color || '#00bfff');
                } else if (obj.x1 !== undefined) {
                    this.drawSkatingForwardPuck(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#00bfff');
                }
            } else if (obj.type === 'skating_backward_puck') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandSkatingBackwardPuck(obj.points, obj.color || '#ff6600');
                } else if (obj.x1 !== undefined) {
                    this.drawSkatingBackwardPuck(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#ff6600');
                }
            } else if (obj.type === 'pass') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandPassLine(obj.points, obj.color || '#0033a0');
                } else if (obj.x1 !== undefined) {
                    this.drawPassLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#0033a0');
                }
            } else if (obj.type === 'shot') {
                // Support both old and new format
                if (obj.points) {
                    this.drawFreehandShotLine(obj.points, obj.color || '#c41e3a');
                } else if (obj.x1 !== undefined) {
                    this.drawShotLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#c41e3a');
                }
            } else if (obj.type === 'freehand') {
                this.drawFreehand(obj.points, obj.color || '#333');
            } else if (obj.type === 'freehand_arrow') {
                this.drawFreehandArrow(obj.points, obj.color || '#333');
            } else if (obj.type === 'freehand_dashed') {
                this.drawFreehandDashed(obj.points, obj.color || '#333');
            } else if (obj.type === 'freehand_skating') {
                this.drawFreehandSkating(obj.points, obj.color || '#0033a0');
            } else if (obj.type === 'puck') {
                this.drawPuck(obj.x, obj.y, obj.color);
            } else if (obj.type === 'pucks') {
                this.drawPuckGroup(obj.x, obj.y, obj.color);
            } else if (obj.type === 'net') {
                this.drawNet(obj.x, obj.y, obj.rotation, obj.color);
            } else if (obj.type === 'mininet') {
                this.drawMiniNet(obj.x, obj.y, obj.rotation, obj.color);
            } else if (obj.type === 'tire') {
                this.drawTire(obj.x, obj.y, obj.color);
            } else if (obj.type === 'stick') {
                this.drawStick(obj.x, obj.y, obj.rotation, obj.color);
            } else if (obj.type === 'text') {
                this.drawText(obj.x, obj.y, obj.text, obj.color, obj.rotation);
            } else if (obj.type === 'number') {
                this.drawNumber(obj.x, obj.y, obj.value, obj.color, obj.rotation);
            }
        });
        
        if (this.selectedObject) {
            this.ctx.strokeStyle = '#00ff00';
            this.ctx.lineWidth = 2;
            this.ctx.setLineDash([5, 3]);
            
            // Handle line-based objects differently
            if (LINE_TOOLS.includes(this.selectedObject.type)) {
                // For line objects, highlight the line endpoints
                const centerX = (this.selectedObject.x1 + this.selectedObject.x2) / 2;
                const centerY = (this.selectedObject.y1 + this.selectedObject.y2) / 2;
                
                // Draw selection circles at endpoints
                this.ctx.beginPath();
                this.ctx.arc(this.selectedObject.x1, this.selectedObject.y1, 8, 0, 2 * Math.PI);
                this.ctx.stroke();
                
                this.ctx.beginPath();
                this.ctx.arc(this.selectedObject.x2, this.selectedObject.y2, 8, 0, 2 * Math.PI);
                this.ctx.stroke();
                
                // Draw center indicator
                this.ctx.beginPath();
                this.ctx.arc(centerX, centerY, 12, 0, 2 * Math.PI);
                this.ctx.stroke();
            } else if (FREEHAND_TOOLS.includes(this.selectedObject.type) && this.selectedObject.points) {
                // For freehand types, draw selection around bounding box
                const bounds = this.getFreehandBounds(this.selectedObject.points);
                this.ctx.strokeRect(bounds.minX - 5, bounds.minY - 5, bounds.width + 10, bounds.height + 10);
            } else {
                // Standard objects with x, y coordinates
                this.ctx.beginPath();
                this.ctx.arc(this.selectedObject.x, this.selectedObject.y, 22, 0, 2 * Math.PI);
                this.ctx.stroke();
            }
            this.ctx.setLineDash([]);
            
            // Draw rotate indicator only for rotatable objects (not line-based or freehand)
            if (!LINE_TOOLS.includes(this.selectedObject.type) && !FREEHAND_TOOLS.includes(this.selectedObject.type) && this.selectedObject.rotation !== undefined) {
                this.ctx.fillStyle = '#00ff00';
                this.ctx.beginPath();
                this.ctx.arc(this.selectedObject.x + 18, this.selectedObject.y - 18, 6, 0, 2 * Math.PI);
                this.ctx.fill();
                this.ctx.fillStyle = '#fff';
                this.ctx.font = 'bold 8px Inter, sans-serif';
                this.ctx.textAlign = 'center';
                this.ctx.textBaseline = 'middle';
                this.ctx.fillText('R', this.selectedObject.x + 18, this.selectedObject.y - 18);
            }
        }
    }
    
    drawPlayer(x, y, color, label, isCoach, rotation) {
        const ctx = this.ctx;
        
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((rotation || 0) * Math.PI / 180);
        
        if (isCoach) {
            // Draw coach as rectangle
            ctx.fillStyle = color;
            ctx.fillRect(-10, -12, 20, 24);
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.strokeRect(-10, -12, 20, 24);
            
            // Draw C label
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 12px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('C', 0, 0);
        } else {
            // Draw player circle
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.arc(0, 0, 14, 0, 2 * Math.PI);
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();
            
            // Draw label if present
            if (label) {
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 10px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(label, 0, 0);
            }
        }
        ctx.restore();
    }
    
    drawCone(x, y, color, rotation) {
        const ctx = this.ctx;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((rotation || 0) * Math.PI / 180);
        
        ctx.fillStyle = color || '#ff6b00';
        ctx.beginPath();
        ctx.moveTo(0, -15);
        ctx.lineTo(-10, 10);
        ctx.lineTo(10, 10);
        ctx.closePath();
        ctx.fill();
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 1;
        ctx.stroke();
        
        ctx.restore();
    }
    
    drawLine(x1, y1, x2, y2, color) {
        this.ctx.strokeStyle = color;
        this.ctx.lineWidth = this.lineThickness;
        this.ctx.lineCap = 'round';
        this.ctx.beginPath();
        this.ctx.moveTo(x1, y1);
        this.ctx.lineTo(x2, y2);
        this.ctx.stroke();
    }
    
    drawDashedLine(x1, y1, x2, y2, color) {
        this.ctx.strokeStyle = color;
        this.ctx.lineWidth = this.lineThickness;
        this.ctx.lineCap = 'round';
        this.ctx.setLineDash([10, 6]);
        this.ctx.beginPath();
        this.ctx.moveTo(x1, y1);
        this.ctx.lineTo(x2, y2);
        this.ctx.stroke();
        this.ctx.setLineDash([]);
    }
    
    drawSquigglyLine(x1, y1, x2, y2, color) {
        // Draw a squiggly/wavy line for puck carrying
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        
        const dx = x2 - x1;
        const dy = y2 - y1;
        const distance = Math.sqrt(dx * dx + dy * dy);
        const angle = Math.atan2(dy, dx);
        
        // Number of waves based on line length
        const waveLength = 15;
        const amplitude = 6;
        const numWaves = Math.max(2, Math.floor(distance / waveLength));
        
        ctx.save();
        ctx.translate(x1, y1);
        ctx.rotate(angle);
        
        ctx.beginPath();
        ctx.moveTo(0, 0);
        
        for (let i = 0; i < numWaves; i++) {
            const segmentStart = (i / numWaves) * distance;
            const segmentEnd = ((i + 1) / numWaves) * distance;
            const midX = (segmentStart + segmentEnd) / 2;
            const direction = (i % 2 === 0) ? 1 : -1;
            
            ctx.quadraticCurveTo(
                midX, direction * amplitude,
                segmentEnd, 0
            );
        }
        
        ctx.stroke();
        ctx.restore();
    }
    
    drawArrow(x1, y1, x2, y2, color) {
        const headlen = 15;
        const angle = Math.atan2(y2 - y1, x2 - x1);
        
        this.ctx.strokeStyle = color;
        this.ctx.fillStyle = color;
        this.ctx.lineWidth = this.lineThickness;
        this.ctx.lineCap = 'round';
        
        // Line
        this.ctx.beginPath();
        this.ctx.moveTo(x1, y1);
        this.ctx.lineTo(x2, y2);
        this.ctx.stroke();
        
        // Arrow head
        this.ctx.beginPath();
        this.ctx.moveTo(x2, y2);
        this.ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
        this.ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
        this.ctx.closePath();
        this.ctx.fill();
    }
    
    // New skating pattern line types
    drawSkatingForward(x1, y1, x2, y2, color) {
        // Forward skating - solid line with arrow at end
        const ctx = this.ctx;
        const headlen = 12;
        const angle = Math.atan2(y2 - y1, x2 - x1);
        
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        
        // Arrow head
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
        ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
        ctx.closePath();
        ctx.fill();
    }
    
    drawSkatingBackward(x1, y1, x2, y2, color) {
        // Backward skating - double dashed line
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.setLineDash([12, 4, 4, 4]);
        
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Draw arrow pointing backwards (towards start)
        const headlen = 12;
        const angle = Math.atan2(y1 - y2, x1 - x2);
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
        ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
        ctx.closePath();
        ctx.fill();
    }
    
    drawSkatingLateral(x1, y1, x2, y2, color) {
        // Lateral skating - zigzag line
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        const dx = x2 - x1;
        const dy = y2 - y1;
        const distance = Math.sqrt(dx * dx + dy * dy);
        const angle = Math.atan2(dy, dx);
        const perpAngle = angle + Math.PI / 2;
        
        const segments = Math.max(4, Math.floor(distance / 20));
        const zigzagHeight = 8;
        
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        
        for (let i = 1; i <= segments; i++) {
            const t = i / segments;
            const px = x1 + dx * t;
            const py = y1 + dy * t;
            const offset = (i % 2 === 1) ? zigzagHeight : -zigzagHeight;
            ctx.lineTo(px + Math.cos(perpAngle) * offset, py + Math.sin(perpAngle) * offset);
        }
        
        ctx.lineTo(x2, y2);
        ctx.stroke();
        ctx.restore();
    }
    
    drawSkatingCCuts(x1, y1, x2, y2, color) {
        // C-Cuts skating - series of C-shaped curves
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        
        const dx = x2 - x1;
        const dy = y2 - y1;
        const distance = Math.sqrt(dx * dx + dy * dy);
        const angle = Math.atan2(dy, dx);
        
        const numCuts = Math.max(3, Math.floor(distance / 30));
        const cutWidth = distance / numCuts;
        const cutHeight = 12;
        
        ctx.save();
        ctx.translate(x1, y1);
        ctx.rotate(angle);
        
        ctx.beginPath();
        ctx.moveTo(0, 0);
        
        for (let i = 0; i < numCuts; i++) {
            const startX = i * cutWidth;
            const endX = (i + 1) * cutWidth;
            const direction = (i % 2 === 0) ? 1 : -1;
            
            ctx.quadraticCurveTo(
                startX + cutWidth / 2, direction * cutHeight,
                endX, 0
            );
        }
        
        ctx.stroke();
        ctx.restore();
    }
    
    drawSkatingForwardPuck(x1, y1, x2, y2, color) {
        // Forward skating with puck - solid line with puck symbol
        const ctx = this.ctx;
        const headlen = 12;
        const angle = Math.atan2(y2 - y1, x2 - x1);
        
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness + 1;
        ctx.lineCap = 'round';
        
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        
        // Arrow head
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
        ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
        ctx.closePath();
        ctx.fill();
        
        // Draw puck symbol at start
        ctx.fillStyle = '#000';
        ctx.beginPath();
        ctx.arc(x1, y1, 6, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    drawSkatingBackwardPuck(x1, y1, x2, y2, color) {
        // Backward skating with puck - dashed with puck symbol
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness + 1;
        ctx.lineCap = 'round';
        ctx.setLineDash([12, 4, 4, 4]);
        
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Draw arrow pointing backwards
        const headlen = 12;
        const angle = Math.atan2(y1 - y2, x1 - x2);
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
        ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
        ctx.closePath();
        ctx.fill();
        
        // Draw puck symbol at start
        ctx.fillStyle = '#000';
        ctx.beginPath();
        ctx.arc(x1, y1, 6, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    drawPassLine(x1, y1, x2, y2, color) {
        // Pass line - dashed with hollow arrow
        const ctx = this.ctx;
        const headlen = 14;
        const angle = Math.atan2(y2 - y1, x2 - x1);
        
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.setLineDash([10, 5]);
        
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Hollow arrow head
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
        ctx.stroke();
    }
    
    drawShotLine(x1, y1, x2, y2, color) {
        // Shot line - thick solid line with filled arrow
        const ctx = this.ctx;
        const headlen = 18;
        const angle = Math.atan2(y2 - y1, x2 - x1);
        
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = this.lineThickness + 2;
        ctx.lineCap = 'round';
        
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        
        // Large filled arrow head
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 5), y2 - headlen * Math.sin(angle - Math.PI / 5));
        ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 5), y2 - headlen * Math.sin(angle + Math.PI / 5));
        ctx.closePath();
        ctx.fill();
    }
    
    // Freehand drawing
    drawFreehand(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        // Use smooth bezier curves
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        // Draw the last segment
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
    }
    
    // Freehand arrow - freehand line with arrow at the end
    drawFreehandArrow(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        // Draw the path
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        
        // Draw arrow head at the end
        if (points.length >= 2) {
            const last = points[points.length - 1];
            const secondLast = points[points.length - 2];
            const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
            const headlen = 12;
            
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 6), last.y - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 6), last.y - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
    }
    
    // Freehand dashed line
    drawFreehandDashed(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.setLineDash([10, 6]);
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        ctx.setLineDash([]);
    }
    
    // Freehand skating path - skating pattern with arrow at end
    drawFreehandSkating(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness + 1;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        // Draw the path
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        
        // Draw arrow head at the end
        if (points.length >= 2) {
            const last = points[points.length - 1];
            const secondLast = points[points.length - 2];
            const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
            const headlen = 14;
            
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 6), last.y - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 6), last.y - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
        
        // Draw small skating marks along the path (every N points)
        const markInterval = Math.max(5, Math.floor(points.length / 8));
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        for (let i = markInterval; i < points.length - 2; i += markInterval) {
            const pt = points[i];
            const nextPt = points[i + 1] || pt;
            const angle = Math.atan2(nextPt.y - pt.y, nextPt.x - pt.x);
            const perpAngle = angle + Math.PI / 2;
            
            // Draw small perpendicular marks (like skate cuts on ice)
            ctx.beginPath();
            ctx.moveTo(pt.x - 4 * Math.cos(perpAngle), pt.y - 4 * Math.sin(perpAngle));
            ctx.lineTo(pt.x + 4 * Math.cos(perpAngle), pt.y + 4 * Math.sin(perpAngle));
            ctx.stroke();
        }
    }
    
    // Get bounding box for freehand drawing
    getFreehandBounds(points) {
        if (!points || points.length === 0) return { minX: 0, minY: 0, width: 0, height: 0 };
        
        let minX = points[0].x, maxX = points[0].x;
        let minY = points[0].y, maxY = points[0].y;
        
        for (const pt of points) {
            minX = Math.min(minX, pt.x);
            maxX = Math.max(maxX, pt.x);
            minY = Math.min(minY, pt.y);
            maxY = Math.max(maxY, pt.y);
        }
        
        return { minX, minY, width: maxX - minX, height: maxY - minY };
    }
    
    // Freehand line (basic smooth path)
    drawFreehandLine(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
    }
    
    // Freehand squiggly line (wavy path for puck carrying)
    drawFreehandSquiggly(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        // Add slight wave effect along the freehand path
        for (let i = 1; i < points.length; i++) {
            const prev = points[i - 1];
            const curr = points[i];
            const dx = curr.x - prev.x;
            const dy = curr.y - prev.y;
            const perpAngle = Math.atan2(dy, dx) + Math.PI / 2;
            const amplitude = 3 * ((i % 2 === 0) ? 1 : -1);
            const midX = (prev.x + curr.x) / 2 + Math.cos(perpAngle) * amplitude;
            const midY = (prev.y + curr.y) / 2 + Math.sin(perpAngle) * amplitude;
            ctx.quadraticCurveTo(midX, midY, curr.x, curr.y);
        }
        
        ctx.stroke();
    }
    
    // Freehand skating forward (solid line with arrow at end)
    drawFreehandSkatingForward(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        
        // Draw arrow head at the end
        if (points.length >= 2) {
            const last = points[points.length - 1];
            const secondLast = points[points.length - 2];
            const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
            const headlen = 12;
            
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 6), last.y - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 6), last.y - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
    }
    
    // Freehand skating backward (double dashed line with backward arrow)
    drawFreehandSkatingBackward(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.setLineDash([12, 4, 4, 4]);
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Draw arrow pointing backwards (from end point back toward path direction)
        if (points.length >= 2) {
            const last = points[points.length - 1];
            const secondLast = points[points.length - 2];
            const angle = Math.atan2(secondLast.y - last.y, secondLast.x - last.x);
            const headlen = 12;
            
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 6), last.y - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 6), last.y - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
    }
    
    // Freehand skating lateral (zigzag along the freehand path)
    drawFreehandSkatingLateral(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        // Add zigzag effect along the freehand path
        const zigzagHeight = 6;
        for (let i = 1; i < points.length; i++) {
            const pt = points[i];
            const prev = points[i - 1];
            const dx = pt.x - prev.x;
            const dy = pt.y - prev.y;
            const perpAngle = Math.atan2(dy, dx) + Math.PI / 2;
            const offset = zigzagHeight * ((i % 2 === 0) ? 1 : -1);
            ctx.lineTo(pt.x + Math.cos(perpAngle) * offset, pt.y + Math.sin(perpAngle) * offset);
        }
        
        ctx.stroke();
    }
    
    // Freehand skating c-cuts (c-shaped curves along freehand path)
    drawFreehandSkatingCCuts(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        // Add C-cut effect along the freehand path
        const cutHeight = 10;
        for (let i = 1; i < points.length; i++) {
            const pt = points[i];
            const prev = points[i - 1];
            const dx = pt.x - prev.x;
            const dy = pt.y - prev.y;
            const perpAngle = Math.atan2(dy, dx) + Math.PI / 2;
            const midX = (prev.x + pt.x) / 2;
            const midY = (prev.y + pt.y) / 2;
            const direction = (i % 2 === 0) ? 1 : -1;
            ctx.quadraticCurveTo(
                midX + Math.cos(perpAngle) * cutHeight * direction,
                midY + Math.sin(perpAngle) * cutHeight * direction,
                pt.x, pt.y
            );
        }
        
        ctx.stroke();
    }
    
    // Freehand skating forward with puck
    drawFreehandSkatingForwardPuck(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness + 1;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        
        // Draw arrow head at the end
        if (points.length >= 2) {
            const last = points[points.length - 1];
            const secondLast = points[points.length - 2];
            const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
            const headlen = 12;
            
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 6), last.y - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 6), last.y - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
        
        // Draw puck symbol at start
        ctx.fillStyle = '#000';
        ctx.beginPath();
        ctx.arc(points[0].x, points[0].y, 6, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    // Freehand skating backward with puck
    drawFreehandSkatingBackwardPuck(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness + 1;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.setLineDash([12, 4, 4, 4]);
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Draw arrow pointing backwards
        if (points.length >= 2) {
            const last = points[points.length - 1];
            const secondLast = points[points.length - 2];
            const angle = Math.atan2(secondLast.y - last.y, secondLast.x - last.x);
            const headlen = 12;
            
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 6), last.y - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 6), last.y - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
        
        // Draw puck symbol at start
        ctx.fillStyle = '#000';
        ctx.beginPath();
        ctx.arc(points[0].x, points[0].y, 6, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    // Freehand pass line (dashed with hollow arrow)
    drawFreehandPassLine(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.lineWidth = this.lineThickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.setLineDash([10, 5]);
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Hollow arrow head at the end
        if (points.length >= 2) {
            const last = points[points.length - 1];
            const secondLast = points[points.length - 2];
            const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
            const headlen = 14;
            
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 6), last.y - headlen * Math.sin(angle - Math.PI / 6));
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 6), last.y - headlen * Math.sin(angle + Math.PI / 6));
            ctx.stroke();
        }
    }
    
    // Freehand shot line (thick solid with large arrow)
    drawFreehandShotLine(points, color) {
        if (!points || points.length < 2) return;
        
        const ctx = this.ctx;
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = this.lineThickness + 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        
        for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        if (points.length > 1) {
            const last = points[points.length - 1];
            ctx.lineTo(last.x, last.y);
        }
        
        ctx.stroke();
        
        // Large filled arrow head at the end
        if (points.length >= 2) {
            const last = points[points.length - 1];
            const secondLast = points[points.length - 2];
            const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
            const headlen = 18;
            
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(last.x - headlen * Math.cos(angle - Math.PI / 5), last.y - headlen * Math.sin(angle - Math.PI / 5));
            ctx.lineTo(last.x - headlen * Math.cos(angle + Math.PI / 5), last.y - headlen * Math.sin(angle + Math.PI / 5));
            ctx.closePath();
            ctx.fill();
        }
    }
    
    drawPuck(x, y, color) {
        const ctx = this.ctx;
        ctx.fillStyle = color || '#000';
        ctx.beginPath();
        ctx.arc(x, y, 8, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    drawPuckGroup(x, y, color) {
        const ctx = this.ctx;
        ctx.fillStyle = color || '#000';
        // Draw cluster of pucks
        const positions = [
            { dx: 0, dy: -8 },
            { dx: -7, dy: 4 },
            { dx: 7, dy: 4 }
        ];
        positions.forEach(pos => {
            ctx.beginPath();
            ctx.arc(x + pos.dx, y + pos.dy, 6, 0, 2 * Math.PI);
            ctx.fill();
        });
    }
    
    drawNet(x, y, rotation, color) {
        const ctx = this.ctx;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((rotation || 0) * Math.PI / 180);
        
        const frameColor = color || '#c41e3a';
        const netWidth = 48;
        const netDepth = 16;
        
        // Net back frame (curved like real hockey net)
        ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
        ctx.strokeStyle = frameColor;
        ctx.lineWidth = 3;
        
        // Draw the net frame - more realistic D-shape
        ctx.beginPath();
        // Front opening (goal line)
        ctx.moveTo(-netWidth/2, 0);
        ctx.lineTo(netWidth/2, 0);
        // Right side going back
        ctx.lineTo(netWidth/2 - 4, -netDepth);
        // Curved back of net
        ctx.quadraticCurveTo(0, -netDepth - 8, -netWidth/2 + 4, -netDepth);
        // Left side coming forward
        ctx.lineTo(-netWidth/2, 0);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        // Draw net mesh pattern (horizontal lines)
        ctx.strokeStyle = '#aaa';
        ctx.lineWidth = 0.5;
        for (let i = 1; i <= 3; i++) {
            const meshY = -netDepth * (i / 4);
            const meshWidth = netWidth/2 - (i * 2);
            ctx.beginPath();
            ctx.moveTo(-meshWidth, meshY);
            ctx.quadraticCurveTo(0, meshY - 2, meshWidth, meshY);
            ctx.stroke();
        }
        
        // Draw vertical mesh lines
        for (let i = -3; i <= 3; i++) {
            const meshX = (netWidth/6) * i;
            ctx.beginPath();
            ctx.moveTo(meshX * 0.85, 0);
            ctx.lineTo(meshX * 0.6, -netDepth);
            ctx.stroke();
        }
        
        // Red goal posts (front pillars)
        ctx.strokeStyle = frameColor;
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';
        
        // Left post
        ctx.beginPath();
        ctx.moveTo(-netWidth/2, 2);
        ctx.lineTo(-netWidth/2, -2);
        ctx.stroke();
        
        // Right post
        ctx.beginPath();
        ctx.moveTo(netWidth/2, 2);
        ctx.lineTo(netWidth/2, -2);
        ctx.stroke();
        
        // Crossbar
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(-netWidth/2, 0);
        ctx.lineTo(netWidth/2, 0);
        ctx.stroke();
        
        ctx.restore();
    }
    
    drawMiniNet(x, y, rotation, color) {
        const ctx = this.ctx;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((rotation || 0) * Math.PI / 180);
        
        const frameColor = color || '#c41e3a';
        const netWidth = 32;
        const netDepth = 12;
        
        // Mini net - similar D-shape but smaller
        ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
        ctx.strokeStyle = frameColor;
        ctx.lineWidth = 2;
        
        ctx.beginPath();
        ctx.moveTo(-netWidth/2, 0);
        ctx.lineTo(netWidth/2, 0);
        ctx.lineTo(netWidth/2 - 3, -netDepth);
        ctx.quadraticCurveTo(0, -netDepth - 5, -netWidth/2 + 3, -netDepth);
        ctx.lineTo(-netWidth/2, 0);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        // Simple mesh
        ctx.strokeStyle = '#aaa';
        ctx.lineWidth = 0.5;
        ctx.beginPath();
        ctx.moveTo(-netWidth/4, 0);
        ctx.lineTo(-netWidth/5, -netDepth + 2);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.lineTo(0, -netDepth + 1);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(netWidth/4, 0);
        ctx.lineTo(netWidth/5, -netDepth + 2);
        ctx.stroke();
        
        ctx.restore();
    }
    
    drawTire(x, y, color) {
        const ctx = this.ctx;
        ctx.strokeStyle = color || '#333';
        ctx.lineWidth = 6;
        ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
        ctx.beginPath();
        ctx.arc(x, y, 12, 0, 2 * Math.PI);
        ctx.fill();
        ctx.stroke();
    }
    
    drawStick(x, y, rotation, color) {
        const ctx = this.ctx;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((rotation || 0) * Math.PI / 180);
        
        const stickColor = color || '#8B4513';
        
        // Shaft (long part of the stick)
        ctx.strokeStyle = stickColor;
        ctx.lineWidth = 5;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(0, -22);
        ctx.lineTo(0, 12);
        ctx.stroke();
        
        // Blade (curved hockey blade)
        ctx.lineWidth = 6;
        ctx.beginPath();
        ctx.moveTo(0, 12);
        ctx.quadraticCurveTo(8, 16, 14, 12);
        ctx.stroke();
        
        // Tape on blade (darker)
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.moveTo(2, 13);
        ctx.quadraticCurveTo(8, 15, 12, 12);
        ctx.stroke();
        
        ctx.restore();
    }
    
    drawText(x, y, text, color, rotation) {
        const ctx = this.ctx;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((rotation || 0) * Math.PI / 180);
        
        ctx.fillStyle = color || '#000';
        ctx.font = 'bold 14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, 0, 0);
        
        ctx.restore();
    }
    
    drawNumber(x, y, value, color, rotation) {
        const ctx = this.ctx;
        
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((rotation || 0) * Math.PI / 180);
        
        // Background circle
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(0, 0, 14, 0, 2 * Math.PI);
        ctx.fill();
        ctx.strokeStyle = color || '#000';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        // Number
        ctx.fillStyle = color || '#000';
        ctx.font = 'bold 16px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(value, 0, 0);
        
        ctx.restore();
    }
    
    saveState() {
        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(JSON.parse(JSON.stringify(this.objects)));
        this.historyIndex++;
    }
    
    undo() {
        if (this.historyIndex > 0) {
            this.historyIndex--;
            this.objects = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
            this.redraw();
        }
    }
    
    redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            this.objects = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
            this.redraw();
        }
    }
    
    clearAll() {
        // Use a more user-friendly confirmation
        const shouldClear = window.confirm('Are you sure you want to clear the entire drill diagram? This action cannot be undone.');
        if (shouldClear) {
            this.objects = [];
            this.selectedObject = null;
            this.redraw();
            this.saveState();
        }
    }
    
    exportImage() {
        const dataURL = this.canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = 'drill-diagram.png';
        link.href = dataURL;
        link.click();
    }
    
    getDiagramData() {
        // Include canvas dimensions and ice view with the data for proper scaling when rendering
        return JSON.stringify({
            canvasWidth: this.canvas.width,
            canvasHeight: this.canvas.height,
            iceView: this.iceView || 'full',
            objects: this.objects
        });
    }
    
    loadDiagramData(data) {
        try {
            const parsed = JSON.parse(data);
            
            // Handle both old format (array) and new format (object with dimensions)
            if (Array.isArray(parsed)) {
                // Old format - just an array of objects
                this.objects = parsed;
                this.redraw();
                this.saveState();
            } else if (parsed.objects && Array.isArray(parsed.objects)) {
                // New format with canvas dimensions
                const sourceWidth = parsed.canvasWidth || this.canvas.width;
                const sourceHeight = parsed.canvasHeight || this.canvas.height;
                
                // Restore ice view if saved
                if (parsed.iceView) {
                    this.iceView = parsed.iceView;
                    // Update the ice view selector dropdown if it exists
                    const iceViewSelect = document.getElementById('iceViewSelect');
                    if (iceViewSelect) {
                        iceViewSelect.value = parsed.iceView;
                    }
                    // Update container's data-ice-view for dynamic CSS aspect ratio
                    const container = this.canvas.parentElement;
                    if (container) {
                        container.setAttribute('data-ice-view', parsed.iceView);
                        
                        // Wait for CSS aspect-ratio transition to complete (350ms for CSS + buffer)
                        // Then resize canvas and scale objects with uniform scaling to preserve proportions
                        setTimeout(() => {
                            // Update canvas to match new container size after CSS transition
                            this.canvas.width = container.offsetWidth;
                            this.canvas.height = container.offsetHeight;
                            
                            // Use uniform scaling to preserve object proportions
                            // Take the minimum scale to fit content while maintaining aspect ratio
                            const scaleX = this.canvas.width / sourceWidth;
                            const scaleY = this.canvas.height / sourceHeight;
                            const uniformScale = Math.min(scaleX, scaleY);
                            
                            // Calculate offset to center content if aspect ratios don't match exactly
                            const offsetX = (this.canvas.width - sourceWidth * uniformScale) / 2;
                            const offsetY = (this.canvas.height - sourceHeight * uniformScale) / 2;
                            
                            this.objects = parsed.objects.map(obj => {
                                const scaled = { ...obj };
                                
                                // Scale and offset position-based objects
                                if (scaled.x !== undefined) scaled.x = scaled.x * uniformScale + offsetX;
                                if (scaled.y !== undefined) scaled.y = scaled.y * uniformScale + offsetY;
                                
                                // Scale and offset line-based objects
                                if (scaled.x1 !== undefined) scaled.x1 = scaled.x1 * uniformScale + offsetX;
                                if (scaled.y1 !== undefined) scaled.y1 = scaled.y1 * uniformScale + offsetY;
                                if (scaled.x2 !== undefined) scaled.x2 = scaled.x2 * uniformScale + offsetX;
                                if (scaled.y2 !== undefined) scaled.y2 = scaled.y2 * uniformScale + offsetY;
                                
                                // Scale and offset freehand points
                                if (scaled.points && Array.isArray(scaled.points)) {
                                    scaled.points = scaled.points.map(pt => ({
                                        x: pt.x * uniformScale + offsetX,
                                        y: pt.y * uniformScale + offsetY
                                    }));
                                }
                                
                                return scaled;
                            });
                            
                            this.redraw();
                            this.saveState();
                        }, 350);
                        return; // Exit early, the timeout will handle redraw
                    }
                }
                
                // Fallback: no ice view change or no container - use uniform scaling immediately
                const scaleX = this.canvas.width / sourceWidth;
                const scaleY = this.canvas.height / sourceHeight;
                const uniformScale = Math.min(scaleX, scaleY);
                const offsetX = (this.canvas.width - sourceWidth * uniformScale) / 2;
                const offsetY = (this.canvas.height - sourceHeight * uniformScale) / 2;
                
                if (sourceWidth !== this.canvas.width || sourceHeight !== this.canvas.height) {
                    this.objects = parsed.objects.map(obj => {
                        const scaled = { ...obj };
                        
                        // Scale and offset position-based objects
                        if (scaled.x !== undefined) scaled.x = scaled.x * uniformScale + offsetX;
                        if (scaled.y !== undefined) scaled.y = scaled.y * uniformScale + offsetY;
                        
                        // Scale and offset line-based objects
                        if (scaled.x1 !== undefined) scaled.x1 = scaled.x1 * uniformScale + offsetX;
                        if (scaled.y1 !== undefined) scaled.y1 = scaled.y1 * uniformScale + offsetY;
                        if (scaled.x2 !== undefined) scaled.x2 = scaled.x2 * uniformScale + offsetX;
                        if (scaled.y2 !== undefined) scaled.y2 = scaled.y2 * uniformScale + offsetY;
                        
                        // Scale and offset freehand points
                        if (scaled.points && Array.isArray(scaled.points)) {
                            scaled.points = scaled.points.map(pt => ({
                                x: pt.x * uniformScale + offsetX,
                                y: pt.y * uniformScale + offsetY
                            }));
                        }
                        
                        return scaled;
                    });
                } else {
                    this.objects = parsed.objects;
                }
                
                this.redraw();
                this.saveState();
            } else {
                this.objects = [];
                this.redraw();
                this.saveState();
            }
        } catch (e) {
            console.error('Failed to load diagram data:', e);
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDrillDesigner);
} else {
    initDrillDesigner();
}

function initDrillDesigner() {
    const canvasContainer = document.querySelector('.ice-rink-canvas');
    if (!canvasContainer) return;
    
    // Remove overlay
    const overlay = canvasContainer.querySelector('.rink-overlay');
    if (overlay) overlay.remove();
    
    // Create canvas element
    const canvas = document.createElement('canvas');
    canvas.id = 'drill-canvas';
    canvas.width = canvasContainer.offsetWidth;
    canvas.height = canvasContainer.offsetHeight;
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvasContainer.appendChild(canvas);
    
    // Initialize designer
    window.drillDesigner = new DrillDesigner('drill-canvas');
    
    // Handle ice view selector
    const iceViewSelect = document.getElementById('iceViewSelect');
    if (iceViewSelect) {
        iceViewSelect.addEventListener('change', function(e) {
            if (window.drillDesigner) {
                window.drillDesigner.setIceView(e.target.value);
            }
        });
    }
    
    // Handle window resize for responsive canvas (prevent duplicate listeners)
    if (!window.drillDesignerResizeHandler) {
        let resizeTimeout;
        window.drillDesignerResizeHandler = function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (window.drillDesigner && window.drillDesigner.canvas) {
                    const container = window.drillDesigner.canvas.parentElement;
                    window.drillDesigner.canvas.width = container.offsetWidth;
                    window.drillDesigner.canvas.height = container.offsetHeight;
                    window.drillDesigner.redraw();
                }
            }, 250);
        };
        window.addEventListener('resize', window.drillDesignerResizeHandler);
    }
    
    // Hook into form submission to save diagram data
    const drillForm = document.querySelector('.drill-form');
    if (drillForm) {
        drillForm.addEventListener('submit', function(e) {
            const diagramData = window.drillDesigner.getDiagramData();
            let diagramInput = document.querySelector('input[name="diagram_data"]');
            if (!diagramInput) {
                diagramInput = document.createElement('input');
                diagramInput.type = 'hidden';
                diagramInput.name = 'diagram_data';
                drillForm.appendChild(diagramInput);
            }
            diagramInput.value = diagramData;
        });
    }
}
