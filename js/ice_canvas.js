/**
 * Ice Canvas - Shared Hockey Rink Drawing Module
 * This module provides consistent ice rink rendering across all views:
 * - Drill Library previews/thumbnails
 * - View Drill page
 * - Practice Plan drill selection
 * - Drill Designer canvas
 * 
 * IMPORTANT: This module uses the EXACT same drawing logic as drill_designer.js
 * to ensure visual consistency across all ice rink displays.
 */

// NHL/Hockey Canada Rink Proportions (200 ft x 85 ft rink)
// All values are proportional to rink dimensions
// This MUST match the NHL_RINK constant in drill_designer.js
const ICE_CANVAS_NHL_RINK = {
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

/**
 * Ice Canvas Renderer - Main object for drawing hockey rinks
 * Uses the EXACT same drawing logic as drill_designer.js for visual consistency
 */
const IceCanvasRenderer = {
    NHL_RINK: ICE_CANVAS_NHL_RINK,
    
    /**
     * Draw the complete ice rink based on view type
     * This function replicates drill_designer.js drawRink() method exactly
     * @param {CanvasRenderingContext2D} ctx - Canvas 2D context
     * @param {number} w - Canvas width
     * @param {number} h - Canvas height
     * @param {string} iceView - View type: 'full', 'left-zone', 'right-zone', 'center'
     * @param {Object} options - Rendering options
     * @param {Image} options.logoImage - Center logo image (optional)
     * @param {boolean} options.logoLoaded - Whether logo is loaded
     * @param {number} options.lineScale - Scale factor (ignored - uses same fixed values as drill_designer.js)
     */
    drawRink: function(ctx, w, h, iceView, options) {
        options = options || {};
        iceView = iceView || 'full';
        const NHL_RINK = this.NHL_RINK;
        
        // Clear and draw ice background (light blue ice tone) - matches drill_designer.js
        ctx.fillStyle = '#f0f7fa';
        ctx.fillRect(0, 0, w, h);
        
        // Draw center logo (image if available, otherwise text at 12% opacity) - matches drill_designer.js
        ctx.save();
        ctx.globalAlpha = 0.12;
        
        if (options.logoLoaded && options.logoImage) {
            // Draw logo image centered on ice
            const maxLogoWidth = w * 0.3;  // Logo takes up 30% of canvas width
            const maxLogoHeight = h * 0.25; // Max 25% of height
            
            // Calculate scaled dimensions maintaining aspect ratio
            const imgAspect = options.logoImage.width / options.logoImage.height;
            let logoWidth = maxLogoWidth;
            let logoHeight = logoWidth / imgAspect;
            
            if (logoHeight > maxLogoHeight) {
                logoHeight = maxLogoHeight;
                logoWidth = logoHeight * imgAspect;
            }
            
            const logoX = (w - logoWidth) / 2;
            const logoY = (h - logoHeight) / 2;
            
            ctx.drawImage(options.logoImage, logoX, logoY, logoWidth, logoHeight);
        } else {
            // Fallback to text branding - matches drill_designer.js exactly
            ctx.fillStyle = '#7000a4';
            ctx.font = 'bold 48px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('ARCTIC WOLVES', w/2, h/2 - 15);
            ctx.font = '24px Inter, sans-serif';
            ctx.fillText('HOCKEY', w/2, h/2 + 25);
        }
        ctx.restore();
        
        // Draw based on ice view - matches drill_designer.js switch statement exactly
        switch(iceView) {
            case 'left-zone':
                this.drawZone(ctx, w, h, 'left', NHL_RINK);
                break;
            case 'right-zone':
                this.drawZone(ctx, w, h, 'right', NHL_RINK);
                break;
            case 'center':
                this.drawCenterIce(ctx, w, h, NHL_RINK);
                break;
            default:
                this.drawFullIce(ctx, w, h, NHL_RINK);
        }
        
        // Draw rink boards (adapted to view type) - matches drill_designer.js exactly
        this.drawRinkBorder(ctx, w, h, iceView, NHL_RINK);
    },
    
    /**
     * Draw rink border - EXACT copy from drill_designer.js drawRinkBorder()
     */
    drawRinkBorder: function(ctx, w, h, iceView, NHL_RINK) {
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 4;
        
        // NHL corner radius: 28 ft on 85 ft width (~0.329 ratio)
        const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
        
        if (iceView === 'left-zone' || iceView === 'right-zone') {
            const isLeft = iceView === 'left-zone';
            ctx.beginPath();
            if (isLeft) {
                ctx.moveTo(cornerRadius + 2, 2);
                ctx.lineTo(w - 2, 2);
                ctx.lineTo(w - 2, h - 2);
                ctx.lineTo(cornerRadius + 2, h - 2);
                ctx.quadraticCurveTo(2, h - 2, 2, h - cornerRadius - 2);
                ctx.lineTo(2, cornerRadius + 2);
                ctx.quadraticCurveTo(2, 2, cornerRadius + 2, 2);
            } else {
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
            this.roundRect(ctx, 2, 2, w - 4, h - 4, cornerRadius);
            ctx.stroke();
        }
    },

    /**
     * Draw full ice view - EXACT copy from drill_designer.js drawFullIce()
     */
    drawFullIce: function(ctx, w, h, NHL_RINK) {
        const goalLinePos = NHL_RINK.GOAL_LINE;
        const blueLinePos = NHL_RINK.BLUE_LINE;
        const faceoffFromGoal = goalLinePos + NHL_RINK.FACEOFF_FROM_GOAL;
        const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
        const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
        
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
        ctx.moveTo(w * blueLinePos, 0);
        ctx.lineTo(w * blueLinePos, h);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(w * (1 - blueLinePos), 0);
        ctx.lineTo(w * (1 - blueLinePos), h);
        ctx.stroke();
        
        // Center circle
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w/2, h/2, h * NHL_RINK.CENTER_CIRCLE_RADIUS, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Center dot
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        ctx.arc(w/2, h/2, 5, 0, 2 * Math.PI);
        ctx.fill();
        
        // Faceoff circles with dots
        const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
        const circles = [
            { x: w * faceoffFromGoal, y: h * faceoffFromBoards, zone: 'left' },
            { x: w * faceoffFromGoal, y: h * (1 - faceoffFromBoards), zone: 'left' },
            { x: w * (1 - faceoffFromGoal), y: h * faceoffFromBoards, zone: 'right' },
            { x: w * (1 - faceoffFromGoal), y: h * (1 - faceoffFromBoards), zone: 'right' }
        ];
        
        const self = this;
        circles.forEach(function(circle) {
            ctx.strokeStyle = '#c41e3a';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, faceoffRadius, 0, 2 * Math.PI);
            ctx.stroke();
            
            ctx.fillStyle = '#c41e3a';
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, 4, 0, 2 * Math.PI);
            ctx.fill();
            
            self.drawHashMarks(ctx, circle.x, circle.y, faceoffRadius, 'horizontal');
            self.drawRestraintLines(ctx, circle.x, circle.y, faceoffRadius, circle.zone, h, NHL_RINK);
        });
        
        // Goal creases
        const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
        
        // Left goal crease
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w * goalLinePos, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
        ctx.fill();
        ctx.stroke();
        
        // Left goal line
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        const goalLineX = w * goalLinePos;
        let leftGoalLineStartY = 0;
        let leftGoalLineEndY = h;
        
        if (goalLineX < cornerRadius) {
            const dx = cornerRadius - goalLineX;
            const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
            leftGoalLineStartY = yOffset;
            leftGoalLineEndY = h - yOffset;
        }
        
        ctx.moveTo(goalLineX, leftGoalLineStartY);
        ctx.lineTo(goalLineX, leftGoalLineEndY);
        ctx.stroke();
        
        // Right goal crease
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(w * (1 - goalLinePos), h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
        ctx.fill();
        ctx.stroke();
        
        // Right goal line
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        const rightGoalLineX = w * (1 - goalLinePos);
        let rightGoalLineStartY = 0;
        let rightGoalLineEndY = h;
        
        if ((w - rightGoalLineX) < cornerRadius) {
            const dx = cornerRadius - (w - rightGoalLineX);
            const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
            rightGoalLineStartY = yOffset;
            rightGoalLineEndY = h - yOffset;
        }
        
        ctx.moveTo(rightGoalLineX, rightGoalLineStartY);
        ctx.lineTo(rightGoalLineX, rightGoalLineEndY);
        ctx.stroke();
        
        // Trapezoids
        this.drawTrapezoid(ctx, w, h, 'left', NHL_RINK);
        this.drawTrapezoid(ctx, w, h, 'right', NHL_RINK);
        
        // Neutral zone faceoff dots
        const neutralZoneDotOffset = 5 / 200;
        const neutralDots = [
            { x: w * (blueLinePos + neutralZoneDotOffset), y: h * faceoffFromBoards },
            { x: w * (blueLinePos + neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) },
            { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * faceoffFromBoards },
            { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) }
        ];
        
        ctx.fillStyle = '#c41e3a';
        neutralDots.forEach(function(dot) {
            ctx.beginPath();
            ctx.arc(dot.x, dot.y, 4, 0, 2 * Math.PI);
            ctx.fill();
        });
    },
    
    /**
     * Draw goalie trapezoid - EXACT copy from drill_designer.js
     */
    drawTrapezoid: function(ctx, w, h, side, NHL_RINK) {
        const goalLinePos = NHL_RINK.GOAL_LINE;
        const trapezoidBase = h * NHL_RINK.TRAPEZOID_BASE / 2;
        const trapezoidTop = h * NHL_RINK.TRAPEZOID_TOP / 2;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        
        if (side === 'left') {
            const goalX = w * goalLinePos;
            ctx.beginPath();
            ctx.moveTo(goalX, h/2 - trapezoidBase);
            ctx.lineTo(0, h/2 - trapezoidTop);
            ctx.stroke();
            
            ctx.beginPath();
            ctx.moveTo(goalX, h/2 + trapezoidBase);
            ctx.lineTo(0, h/2 + trapezoidTop);
            ctx.stroke();
        } else {
            const goalX = w * (1 - goalLinePos);
            ctx.beginPath();
            ctx.moveTo(goalX, h/2 - trapezoidBase);
            ctx.lineTo(w, h/2 - trapezoidTop);
            ctx.stroke();
            
            ctx.beginPath();
            ctx.moveTo(goalX, h/2 + trapezoidBase);
            ctx.lineTo(w, h/2 + trapezoidTop);
            ctx.stroke();
        }
    },
    
    /**
     * Draw faceoff restraint lines - EXACT copy from drill_designer.js
     */
    drawRestraintLines: function(ctx, cx, cy, radius, zone, canvasHeight, NHL_RINK) {
        const lineLength = canvasHeight * NHL_RINK.RESTRAINT_LINE_LENGTH * 1.5;
        const offset = radius * 0.15;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        
        const goalDirection = zone === 'left' ? -1 : 1;
        const blueLineDirection = -goalDirection;
        
        this.drawLShape(ctx, cx - offset, cy - offset, lineLength, zone === 'left' ? goalDirection : blueLineDirection, -1);
        this.drawLShape(ctx, cx + offset, cy - offset, lineLength, zone === 'left' ? blueLineDirection : goalDirection, -1);
        this.drawLShape(ctx, cx - offset, cy + offset, lineLength, zone === 'left' ? goalDirection : blueLineDirection, 1);
        this.drawLShape(ctx, cx + offset, cy + offset, lineLength, zone === 'left' ? blueLineDirection : goalDirection, 1);
    },
    
    /**
     * Draw L-shaped restraint line - EXACT copy from drill_designer.js
     */
    drawLShape: function(ctx, x, y, length, hDir, vDir) {
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x, y + vDir * length);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x + hDir * length, y);
        ctx.stroke();
    },
    
    /**
     * Draw hash marks - EXACT copy from drill_designer.js
     */
    drawHashMarks: function(ctx, cx, cy, radius, netPosition) {
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        
        const hashLength = radius * (2 / 15);
        const hashSpacing = radius * (3 / 15);
        const gapOutsideCircle = radius * 0.05;
        const startDistance = radius + gapOutsideCircle;
        
        const sides = [-1, 1];
        
        if (netPosition === 'vertical') {
            sides.forEach(function(side) {
                const startX = cx + side * startDistance;
                const endX = startX + side * hashLength;
                
                ctx.beginPath();
                ctx.moveTo(startX, cy - hashSpacing / 2);
                ctx.lineTo(endX, cy - hashSpacing / 2);
                ctx.stroke();
                
                ctx.beginPath();
                ctx.moveTo(startX, cy + hashSpacing / 2);
                ctx.lineTo(endX, cy + hashSpacing / 2);
                ctx.stroke();
            });
        } else {
            sides.forEach(function(side) {
                const startY = cy + side * startDistance;
                const endY = startY + side * hashLength;
                
                ctx.beginPath();
                ctx.moveTo(cx - hashSpacing / 2, startY);
                ctx.lineTo(cx - hashSpacing / 2, endY);
                ctx.stroke();
                
                ctx.beginPath();
                ctx.moveTo(cx + hashSpacing / 2, startY);
                ctx.lineTo(cx + hashSpacing / 2, endY);
                ctx.stroke();
            });
        }
    },

    /**
     * Draw zone view - EXACT copy from drill_designer.js
     */
    drawZone: function(ctx, w, h, side, NHL_RINK) {
        const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
        const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
        const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
        const centerCircleRadius = h * NHL_RINK.CENTER_CIRCLE_RADIUS;
        
        const goalLineRatio = 11 / 100;
        const blueLineRatio = 64 / 100;
        const faceoffXRatio = 31 / 100;
        const neutralZoneDotRatio = (64 + 5) / 100;
        
        // Center line
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 4;
        if (side === 'left') {
            ctx.beginPath();
            ctx.moveTo(w, 0);
            ctx.lineTo(w, h);
            ctx.stroke();
        } else {
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(0, h);
            ctx.stroke();
        }
        
        // Blue line
        const blueLineX = side === 'left' ? w * blueLineRatio : w * (1 - blueLineRatio);
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(blueLineX, 0);
        ctx.lineTo(blueLineX, h);
        ctx.stroke();
        
        // Goal line - respects curved corners
        const goalLineX = side === 'left' ? w * goalLineRatio : w * (1 - goalLineRatio);
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
        const distFromEnd = side === 'left' ? goalLineX : (w - goalLineX);
        let zoneGoalLineStartY = 0;
        let zoneGoalLineEndY = h;
        
        if (distFromEnd < cornerRadius) {
            const dx = cornerRadius - distFromEnd;
            const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
            zoneGoalLineStartY = yOffset;
            zoneGoalLineEndY = h - yOffset;
        }
        
        ctx.moveTo(goalLineX, zoneGoalLineStartY);
        ctx.lineTo(goalLineX, zoneGoalLineEndY);
        ctx.stroke();
        
        // Half center circle
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2;
        ctx.beginPath();
        if (side === 'left') {
            ctx.arc(w, h/2, centerCircleRadius, Math.PI/2, -Math.PI/2);
        } else {
            ctx.arc(0, h/2, centerCircleRadius, -Math.PI/2, Math.PI/2);
        }
        ctx.stroke();
        
        // Center dot
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        if (side === 'left') {
            ctx.arc(w, h/2, 5, 0, 2 * Math.PI);
        } else {
            ctx.arc(0, h/2, 5, 0, 2 * Math.PI);
        }
        ctx.fill();
        
        // Faceoff circles
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
        this.drawRestraintLines(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, side, h, NHL_RINK);
        
        // Bottom faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(faceoffX, h * (1 - faceoffFromBoards), 4, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 'horizontal');
        this.drawRestraintLines(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, side, h, NHL_RINK);
        
        // Neutral zone faceoff dots
        const neutralDotX = side === 'left' ? w * neutralZoneDotRatio : w * (1 - neutralZoneDotRatio);
        
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(neutralDotX, h * faceoffFromBoards, 4, 0, 2 * Math.PI);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(neutralDotX, h * (1 - faceoffFromBoards), 4, 0, 2 * Math.PI);
        ctx.fill();
        
        // Goal crease
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
        
        this.drawZoneTrapezoid(ctx, w, h, side, goalLineX, NHL_RINK);
    },
    
    /**
     * Draw trapezoid for zone view - EXACT copy from drill_designer.js
     */
    drawZoneTrapezoid: function(ctx, w, h, side, goalLineX, NHL_RINK) {
        const trapezoidBase = h * NHL_RINK.TRAPEZOID_BASE / 2;
        const trapezoidTop = h * NHL_RINK.TRAPEZOID_TOP / 2;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2;
        
        if (side === 'left') {
            ctx.beginPath();
            ctx.moveTo(goalLineX, h/2 - trapezoidBase);
            ctx.lineTo(0, h/2 - trapezoidTop);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(goalLineX, h/2 + trapezoidBase);
            ctx.lineTo(0, h/2 + trapezoidTop);
            ctx.stroke();
        } else {
            ctx.beginPath();
            ctx.moveTo(goalLineX, h/2 - trapezoidBase);
            ctx.lineTo(w, h/2 - trapezoidTop);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(goalLineX, h/2 + trapezoidBase);
            ctx.lineTo(w, h/2 + trapezoidTop);
            ctx.stroke();
        }
    },
    
    /**
     * Draw center ice view - EXACT copy from drill_designer.js
     */
    drawCenterIce: function(ctx, w, h, NHL_RINK) {
        // Center line
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.moveTo(w/2, 0);
        ctx.lineTo(w/2, h);
        ctx.stroke();
        
        // Center circle
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
    },
    
    /**
     * Draw rounded rectangle - EXACT copy from drill_designer.js
     */
    roundRect: function(ctx, x, y, width, height, radius) {
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
};

// Make available globally
if (typeof window !== 'undefined') {
    window.IceCanvasRenderer = IceCanvasRenderer;
    window.ICE_CANVAS_NHL_RINK = ICE_CANVAS_NHL_RINK;
}
