/**
 * Drill Designer - Interactive Hockey Drill Drawing Tool
 * Allows coaches to create visual drill diagrams on an ice rink canvas
 */

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
        
        // Configurable branding
        this.brandingText = 'ARCTIC WOLVES';
        this.brandingSubtext = 'HOCKEY';
        
        this.init();
    }
    
    init() {
        // Set canvas size
        this.canvas.width = this.canvas.offsetWidth;
        this.canvas.height = this.canvas.offsetHeight;
        
        // Draw initial rink
        this.drawRink();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Save initial state
        this.saveState();
    }
    
    setupEventListeners() {
        this.canvas.addEventListener('mousedown', this.handleMouseDown.bind(this));
        this.canvas.addEventListener('mousemove', this.handleMouseMove.bind(this));
        this.canvas.addEventListener('mouseup', this.handleMouseUp.bind(this));
        this.canvas.addEventListener('click', this.handleClick.bind(this));
        
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
        
        if (undoBtn) undoBtn.addEventListener('click', () => this.undo());
        if (redoBtn) redoBtn.addEventListener('click', () => this.redo());
        if (exportBtn) exportBtn.addEventListener('click', () => this.exportImage());
    }
    
    setTool(toolName) {
        const toolMap = {
            'Select': 'select',
            'Add Player': 'player',
            'Add Cone': 'cone',
            'Draw Line': 'line',
            'Add Arrow': 'arrow',
            'Clear All': 'clear'
        };
        
        if (toolName === 'Clear All') {
            this.clearAll();
            return;
        }
        
        this.currentTool = toolMap[toolName] || 'select';
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
        } else if (this.currentTool === 'line' || this.currentTool === 'arrow') {
            this.dragStartPos = { x, y };
        }
    }
    
    handleMouseMove(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        if (this.isDragging && this.selectedObject) {
            const dx = x - this.dragStartPos.x;
            const dy = y - this.dragStartPos.y;
            
            this.selectedObject.x += dx;
            this.selectedObject.y += dy;
            
            this.dragStartPos = { x, y };
            this.redraw();
        }
    }
    
    handleMouseUp(e) {
        if (this.isDragging) {
            this.isDragging = false;
            this.saveState();
        }
        
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        if (this.currentTool === 'line' || this.currentTool === 'arrow') {
            if (this.dragStartPos) {
                this.objects.push({
                    type: this.currentTool,
                    x1: this.dragStartPos.x,
                    y1: this.dragStartPos.y,
                    x2: x,
                    y2: y
                });
                this.dragStartPos = null;
                this.redraw();
                this.saveState();
            }
        }
    }
    
    handleClick(e) {
        if (this.currentTool === 'select' || this.currentTool === 'line' || this.currentTool === 'arrow') {
            return;
        }
        
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        if (this.currentTool === 'player') {
            this.objects.push({
                type: 'player',
                x: x,
                y: y,
                color: '#00bfff'
            });
        } else if (this.currentTool === 'cone') {
            this.objects.push({
                type: 'cone',
                x: x,
                y: y,
                color: '#ff6b00'
            });
        }
        
        this.redraw();
        this.saveState();
    }
    
    findObjectAt(x, y) {
        for (let i = this.objects.length - 1; i >= 0; i--) {
            const obj = this.objects[i];
            if (obj.type === 'player' || obj.type === 'cone') {
                const dx = x - obj.x;
                const dy = y - obj.y;
                if (Math.sqrt(dx * dx + dy * dy) < 15) {
                    return obj;
                }
            }
        }
        return null;
    }
    
    drawRink() {
        const w = this.canvas.width;
        const h = this.canvas.height;
        const ctx = this.ctx;
        const iceView = this.iceView || 'full';
        
        // Clear and draw ice background
        ctx.fillStyle = '#e8f4f8';
        ctx.fillRect(0, 0, w, h);
        
        // Draw subtle ice texture grid
        ctx.strokeStyle = 'rgba(0, 51, 160, 0.08)';
        ctx.lineWidth = 1;
        const gridSize = 30;
        for (let x = 0; x < w; x += gridSize) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, h);
            ctx.stroke();
        }
        for (let y = 0; y < h; y += gridSize) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(w, y);
            ctx.stroke();
        }
        
        // Draw center logo (configurable branding - very low opacity)
        ctx.save();
        ctx.globalAlpha = 0.08;
        ctx.fillStyle = '#7000a4';
        ctx.font = 'bold 48px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(this.brandingText, w/2, h/2 - 15);
        ctx.font = '24px Inter, sans-serif';
        ctx.fillText(this.brandingSubtext, w/2, h/2 + 25);
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
        });
        
        // Goal creases (simplified trapezoid)
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        // Left goal crease
        ctx.beginPath();
        ctx.moveTo(w * 0.02, h * 0.35);
        ctx.lineTo(w * 0.08, h * 0.4);
        ctx.lineTo(w * 0.08, h * 0.6);
        ctx.lineTo(w * 0.02, h * 0.65);
        ctx.stroke();
        
        // Right goal crease
        ctx.beginPath();
        ctx.moveTo(w * 0.98, h * 0.35);
        ctx.lineTo(w * 0.92, h * 0.4);
        ctx.lineTo(w * 0.92, h * 0.6);
        ctx.lineTo(w * 0.98, h * 0.65);
        ctx.stroke();
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
        
        // Faceoff circles
        const faceoffRadius = Math.min(w, h) * 0.12;
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        
        ctx.beginPath();
        ctx.arc(w * 0.3, h * 0.5, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.arc(w * 0.7, h * 0.5, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Goal crease
        const goalY = side === 'top' ? h * 0.1 : h * 0.9;
        ctx.beginPath();
        ctx.arc(w * 0.5, goalY, 40, side === 'top' ? 0 : Math.PI, side === 'top' ? Math.PI : 0);
        ctx.stroke();
    }
    
    drawZone(ctx, w, h, side) {
        // Blue line
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 3;
        const lineX = side === 'left' ? w * 0.7 : w * 0.3;
        ctx.beginPath();
        ctx.moveTo(lineX, 0);
        ctx.lineTo(lineX, h);
        ctx.stroke();
        
        // Faceoff circles
        const centerX = side === 'left' ? w * 0.35 : w * 0.65;
        const faceoffRadius = Math.min(w, h) * 0.12;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(centerX, h * 0.35, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.arc(centerX, h * 0.65, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Goal crease
        const goalX = side === 'left' ? w * 0.05 : w * 0.95;
        ctx.beginPath();
        ctx.moveTo(goalX, h * 0.35);
        ctx.lineTo(goalX + (side === 'left' ? 30 : -30), h * 0.4);
        ctx.lineTo(goalX + (side === 'left' ? 30 : -30), h * 0.6);
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
                this.drawPlayer(obj.x, obj.y, obj.color);
            } else if (obj.type === 'cone') {
                this.drawCone(obj.x, obj.y, obj.color);
            } else if (obj.type === 'line') {
                this.drawLine(obj.x1, obj.y1, obj.x2, obj.y2, '#333');
            } else if (obj.type === 'arrow') {
                this.drawArrow(obj.x1, obj.y1, obj.x2, obj.y2, '#333');
            }
        });
        
        if (this.selectedObject) {
            this.ctx.strokeStyle = '#00ff00';
            this.ctx.lineWidth = 2;
            this.ctx.beginPath();
            this.ctx.arc(this.selectedObject.x, this.selectedObject.y, 18, 0, 2 * Math.PI);
            this.ctx.stroke();
        }
    }
    
    drawPlayer(x, y, color) {
        this.ctx.fillStyle = color;
        this.ctx.beginPath();
        this.ctx.arc(x, y, 12, 0, 2 * Math.PI);
        this.ctx.fill();
        this.ctx.strokeStyle = '#fff';
        this.ctx.lineWidth = 2;
        this.ctx.stroke();
    }
    
    drawCone(x, y, color) {
        this.ctx.fillStyle = color;
        this.ctx.beginPath();
        this.ctx.moveTo(x, y - 15);
        this.ctx.lineTo(x - 10, y + 10);
        this.ctx.lineTo(x + 10, y + 10);
        this.ctx.closePath();
        this.ctx.fill();
        this.ctx.strokeStyle = '#333';
        this.ctx.lineWidth = 1;
        this.ctx.stroke();
    }
    
    drawLine(x1, y1, x2, y2, color) {
        this.ctx.strokeStyle = color;
        this.ctx.lineWidth = 2;
        this.ctx.beginPath();
        this.ctx.moveTo(x1, y1);
        this.ctx.lineTo(x2, y2);
        this.ctx.stroke();
    }
    
    drawArrow(x1, y1, x2, y2, color) {
        const headlen = 15;
        const angle = Math.atan2(y2 - y1, x2 - x1);
        
        this.ctx.strokeStyle = color;
        this.ctx.fillStyle = color;
        this.ctx.lineWidth = 2;
        
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
