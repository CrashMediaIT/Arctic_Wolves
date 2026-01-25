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
        
        // Canvas controls
        const undoBtn = document.querySelector('[data-action="undo-drill"]');
        const redoBtn = document.querySelector('[data-action="redo-drill"]');
        const clearBtn = document.querySelector('[data-action="clear-drill"]');
        const exportBtn = document.querySelector('[data-action="export-drill"]');
        
        if (undoBtn) undoBtn.addEventListener('click', () => this.undo());
        if (redoBtn) redoBtn.addEventListener('click', () => this.redo());
        if (clearBtn) clearBtn.addEventListener('click', () => this.clearAll());
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
        
        // Background - ice
        ctx.fillStyle = '#e8f4f8';
        ctx.fillRect(0, 0, w, h);
        
        // Grid
        ctx.strokeStyle = '#d0e8f0';
        ctx.lineWidth = 1;
        for (let x = 0; x < w; x += 20) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, h);
            ctx.stroke();
        }
        for (let y = 0; y < h; y += 20) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(w, y);
            ctx.stroke();
        }
        
        // Center line (red)
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
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
        ctx.beginPath();
        ctx.arc(w/2, h/2, 60, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Faceoff circles
        const circles = [
            { x: w * 0.25, y: h * 0.3 },
            { x: w * 0.25, y: h * 0.7 },
            { x: w * 0.75, y: h * 0.3 },
            { x: w * 0.75, y: h * 0.7 }
        ];
        
        circles.forEach(circle => {
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, 40, 0, 2 * Math.PI);
            ctx.stroke();
        });
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
    canvasContainer.appendChild(canvas);
    
    // Initialize designer
    window.drillDesigner = new DrillDesigner('drill-canvas');
    
    // Update canvas controls
    updateCanvasControls();
    
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

function updateCanvasControls() {
    // Update button actions with more specific selectors
    const canvasControls = document.querySelector('.canvas-controls');
    if (!canvasControls) return;
    
    const buttons = canvasControls.querySelectorAll('.btn-secondary');
    if (buttons.length >= 3) {
        buttons[0].setAttribute('data-action', 'undo-drill');
        buttons[1].setAttribute('data-action', 'redo-drill');
        buttons[2].setAttribute('data-action', 'export-drill');
    }
}
