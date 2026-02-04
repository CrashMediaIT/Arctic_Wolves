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

// Line-based drawing tools constant
const LINE_TOOLS = ['line', 'arrow', 'dashed', 'squiggly', 'skating_forward', 'skating_backward', 'skating_lateral', 'skating_ccuts', 'skating_forward_puck', 'skating_backward_puck', 'pass', 'shot'];

// Freehand drawing tools - can be drawn in any shape/curve
const FREEHAND_TOOLS = ['freehand', 'freehand_arrow', 'freehand_dashed', 'freehand_skating'];

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
        
        // Draw rink boards (rounded rectangle)
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 4;
        const cornerRadius = Math.min(w, h) * 0.1;
        this.roundRect(ctx, 2, 2, w - 4, h - 4, cornerRadius);
        ctx.stroke();
    }
    
    drawFullIce(ctx, w, h) {
        // Center line (red)
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.moveTo(w/2, 0);
        ctx.lineTo(w/2, h);
        ctx.stroke();
        
        // Blue lines
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(w * 0.25, 0);
        ctx.lineTo(w * 0.25, h);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(w * 0.75, 0);
        ctx.lineTo(w * 0.75, h);
        ctx.stroke();
        
        // Center circle
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w/2, h/2, Math.min(w, h) * 0.12, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Center dot
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        ctx.arc(w/2, h/2, 5, 0, 2 * Math.PI);
        ctx.fill();
        
        // Faceoff circles with dots
        const faceoffRadius = Math.min(w, h) * 0.1;
        const circles = [
            { x: w * 0.15, y: h * 0.3 },
            { x: w * 0.15, y: h * 0.7 },
            { x: w * 0.85, y: h * 0.3 },
            { x: w * 0.85, y: h * 0.7 }
        ];
        
        circles.forEach(circle => {
            // Faceoff circle
            ctx.strokeStyle = '#c41e3a';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, faceoffRadius, 0, 2 * Math.PI);
            ctx.stroke();
            
            // Faceoff dot
            ctx.fillStyle = '#c41e3a';
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, 4, 0, 2 * Math.PI);
            ctx.fill();
            
            // Draw hash marks around faceoff circles
            this.drawHashMarks(ctx, circle.x, circle.y, faceoffRadius);
        });
        
        // Goal creases (proper semicircle shape)
        const creaseRadius = Math.min(w, h) * 0.08;
        const cornerRadius = Math.min(w, h) * 0.1;
        
        // Left goal crease - semicircle
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)'; // Light blue fill
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w * 0.03, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
        ctx.fill();
        ctx.stroke();
        
        // Left goal line - extends all the way across (within rink bounds)
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(w * 0.03, cornerRadius + 4);
        ctx.lineTo(w * 0.03, h - cornerRadius - 4);
        ctx.stroke();
        
        // Right goal crease - semicircle  
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w * 0.97, h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
        ctx.fill();
        ctx.stroke();
        
        // Right goal line - extends all the way across (within rink bounds)
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(w * 0.97, cornerRadius + 4);
        ctx.lineTo(w * 0.97, h - cornerRadius - 4);
        ctx.stroke();
        
        // Draw neutral zone faceoff dots with hash marks
        const neutralDots = [
            { x: w * 0.25 + 30, y: h * 0.3 },
            { x: w * 0.25 + 30, y: h * 0.7 },
            { x: w * 0.75 - 30, y: h * 0.3 },
            { x: w * 0.75 - 30, y: h * 0.7 }
        ];
        
        ctx.fillStyle = '#c41e3a';
        neutralDots.forEach(dot => {
            ctx.beginPath();
            ctx.arc(dot.x, dot.y, 4, 0, 2 * Math.PI);
            ctx.fill();
        });
    }
    
    // Helper function to draw hash marks around faceoff circles
    // NHL regulation: 4 hash marks positioned on the outside of the circle
    // Two on each side (left and right) with L-shaped marks
    drawHashMarks(ctx, cx, cy, radius) {
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        
        const hashLength = 18;
        const gapFromCircle = 3;
        
        // Four L-shaped hash marks positioned at 2 o'clock, 4 o'clock, 8 o'clock, and 10 o'clock
        // These are the standard NHL faceoff circle hash marks
        const hashPositions = [
            // Top-left (10 o'clock area)
            { startAngle: -2.35, horizontal: 1, vertical: -1 },
            // Top-right (2 o'clock area)  
            { startAngle: -0.79, horizontal: -1, vertical: -1 },
            // Bottom-right (4 o'clock area)
            { startAngle: 0.79, horizontal: -1, vertical: 1 },
            // Bottom-left (8 o'clock area)
            { startAngle: 2.35, horizontal: 1, vertical: 1 }
        ];
        
        hashPositions.forEach(pos => {
            // Starting point just outside the circle
            const x = cx + Math.cos(pos.startAngle) * (radius + gapFromCircle);
            const y = cy + Math.sin(pos.startAngle) * (radius + gapFromCircle);
            
            // Draw the two lines of the L-shape
            // Horizontal line
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x + pos.horizontal * hashLength, y);
            ctx.stroke();
            
            // Vertical line
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x, y + pos.vertical * hashLength);
            ctx.stroke();
        });
    }
    
    drawHalfIce(ctx, w, h, side) {
        // Blue line
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 3;
        if (side === 'top') {
            ctx.beginPath();
            ctx.moveTo(0, h * 0.8);
            ctx.lineTo(w, h * 0.8);
            ctx.stroke();
        } else {
            ctx.beginPath();
            ctx.moveTo(0, h * 0.2);
            ctx.lineTo(w, h * 0.2);
            ctx.stroke();
        }
        
        // Faceoff circles with hash marks
        const faceoffRadius = Math.min(w, h) * 0.12;
        const faceoffY = side === 'top' ? h * 0.4 : h * 0.6;
        
        // Left faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w * 0.3, faceoffY, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(w * 0.3, faceoffY, 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, w * 0.3, faceoffY, faceoffRadius);
        
        // Right faceoff circle  
        ctx.beginPath();
        ctx.arc(w * 0.7, faceoffY, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(w * 0.7, faceoffY, 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, w * 0.7, faceoffY, faceoffRadius);
        
        // Goal crease - proper semicircle
        const creaseRadius = Math.min(w, h) * 0.1;
        const goalY = side === 'top' ? h * 0.05 : h * 0.95;
        
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
        
        // Goal line
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(w * 0.35, goalY);
        ctx.lineTo(w * 0.65, goalY);
        ctx.stroke();
    }
    
    drawZone(ctx, w, h, side) {
        // Blue line
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 3;
        const lineX = side === 'left' ? w * 0.75 : w * 0.25;
        ctx.beginPath();
        ctx.moveTo(lineX, 0);
        ctx.lineTo(lineX, h);
        ctx.stroke();
        
        // Faceoff circles with hash marks
        const centerX = side === 'left' ? w * 0.35 : w * 0.65;
        const faceoffRadius = Math.min(w, h) * 0.12;
        
        // Top faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(centerX, h * 0.3, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(centerX, h * 0.3, 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, centerX, h * 0.3, faceoffRadius);
        
        // Bottom faceoff circle
        ctx.beginPath();
        ctx.arc(centerX, h * 0.7, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(centerX, h * 0.7, 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, centerX, h * 0.7, faceoffRadius);
        
        // Goal crease - proper semicircle
        const creaseRadius = Math.min(w, h) * 0.1;
        const goalX = side === 'left' ? w * 0.05 : w * 0.95;
        
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        if (side === 'left') {
            ctx.arc(goalX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
        } else {
            ctx.arc(goalX, h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
        }
        ctx.fill();
        ctx.stroke();
        
        // Goal line
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(goalX, h * 0.35);
        ctx.lineTo(goalX, h * 0.65);
        ctx.stroke();
    }
    
    drawCenterIce(ctx, w, h) {
        // Center line (red)
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.moveTo(w/2, 0);
        ctx.lineTo(w/2, h);
        ctx.stroke();
        
        // Center circle
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2;
        const circleRadius = Math.min(w, h) * 0.25;
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
        this.redraw();
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
                this.drawLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#333');
            } else if (obj.type === 'dashed') {
                this.drawDashedLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#333');
            } else if (obj.type === 'squiggly') {
                this.drawSquigglyLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#333');
            } else if (obj.type === 'arrow') {
                this.drawArrow(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#333');
            } else if (obj.type === 'skating_forward') {
                this.drawSkatingForward(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#0033a0');
            } else if (obj.type === 'skating_backward') {
                this.drawSkatingBackward(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#c41e3a');
            } else if (obj.type === 'skating_lateral') {
                this.drawSkatingLateral(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#10b981');
            } else if (obj.type === 'skating_ccuts') {
                this.drawSkatingCCuts(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#8b5cf6');
            } else if (obj.type === 'skating_forward_puck') {
                this.drawSkatingForwardPuck(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#00bfff');
            } else if (obj.type === 'skating_backward_puck') {
                this.drawSkatingBackwardPuck(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#ff6600');
            } else if (obj.type === 'pass') {
                this.drawPassLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#0033a0');
            } else if (obj.type === 'shot') {
                this.drawShotLine(obj.x1, obj.y1, obj.x2, obj.y2, obj.color || '#c41e3a');
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
        return JSON.stringify(this.objects);
    }
    
    loadDiagramData(data) {
        try {
            this.objects = JSON.parse(data);
            this.redraw();
            this.saveState();
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
