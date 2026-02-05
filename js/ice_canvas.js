/**
 * Ice Canvas - Shared Hockey Rink Drawing Module
 * This module provides consistent ice rink rendering across all views:
 * - Drill Library previews/thumbnails
 * - View Drill page
 * - Practice Plan drill selection
 * - Drill Designer canvas
 * 
 * All areas that display ice rink canvases should use this module
 * to ensure consistent rendering.
 */

// NHL/Hockey Canada Rink Proportions (200 ft × 85 ft rink)
// All values are proportional to rink dimensions
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
 * @param {Object} options - Configuration options
 * @param {number} options.lineWidth - Base line width multiplier (default 1 for thumbnails, 2 for full view)
 */
const IceCanvasRenderer = {
    NHL_RINK: ICE_CANVAS_NHL_RINK,
    
    /**
     * Draw the complete ice rink based on view type
     * @param {CanvasRenderingContext2D} ctx - Canvas 2D context
     * @param {number} w - Canvas width
     * @param {number} h - Canvas height
     * @param {string} iceView - View type: 'full', 'half-top', 'half-bottom', 'left-zone', 'right-zone', 'center'
     * @param {Object} options - Rendering options
     * @param {Image} options.logoImage - Center logo image (optional)
     * @param {boolean} options.logoLoaded - Whether logo is loaded
     * @param {number} options.lineScale - Scale factor for line widths (1 for thumbnails, 1.5-2 for full view)
     */
    drawRink: function(ctx, w, h, iceView, options) {
        options = options || {};
        iceView = iceView || 'full';
        const lineScale = options.lineScale || 1;
        
        // Ice background
        ctx.fillStyle = '#f0f7fa';
        ctx.fillRect(0, 0, w, h);
        
        // Center logo (image if available, otherwise text at 12% opacity)
        this.drawCenterLogo(ctx, w, h, options.logoImage, options.logoLoaded);
        
        // Draw based on ice view
        switch(iceView) {
            case 'half-top':
                this.drawHalfIce(ctx, w, h, 'top', lineScale);
                break;
            case 'half-bottom':
                this.drawHalfIce(ctx, w, h, 'bottom', lineScale);
                break;
            case 'left-zone':
                this.drawZone(ctx, w, h, 'left', lineScale);
                break;
            case 'right-zone':
                this.drawZone(ctx, w, h, 'right', lineScale);
                break;
            case 'center':
                this.drawCenterIce(ctx, w, h, lineScale);
                break;
            case 'full':
            default:
                this.drawFullIce(ctx, w, h, lineScale);
                break;
        }
        
        // Rink border - adapts to view type
        this.drawRinkBorder(ctx, w, h, iceView, lineScale);
    },
    
    /**
     * Draw center logo or branding
     */
    drawCenterLogo: function(ctx, w, h, logoImage, logoLoaded) {
        ctx.save();
        ctx.globalAlpha = 0.12;
        
        if (logoLoaded && logoImage) {
            // Draw logo image centered on ice
            const maxLogoWidth = w * 0.3;
            const maxLogoHeight = h * 0.25;
            
            // Calculate scaled dimensions maintaining aspect ratio
            const imgAspect = logoImage.width / logoImage.height;
            let logoWidth = maxLogoWidth;
            let logoHeight = logoWidth / imgAspect;
            
            if (logoHeight > maxLogoHeight) {
                logoHeight = maxLogoHeight;
                logoWidth = logoHeight * imgAspect;
            }
            
            const logoX = (w - logoWidth) / 2;
            const logoY = (h - logoHeight) / 2;
            
            ctx.drawImage(logoImage, logoX, logoY, logoWidth, logoHeight);
        } else {
            // Fallback to text branding
            ctx.fillStyle = '#7000a4';
            const fontSize = Math.min(48, w * 0.08);
            ctx.font = 'bold ' + fontSize + 'px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('ARCTIC WOLVES', w/2, h/2 - fontSize * 0.3);
            ctx.font = Math.round(fontSize * 0.5) + 'px Inter, sans-serif';
            ctx.fillText('HOCKEY', w/2, h/2 + fontSize * 0.5);
        }
        ctx.restore();
    },
    
    /**
     * Draw rink border that adapts to view type
     */
    drawRinkBorder: function(ctx, w, h, iceView, lineScale) {
        lineScale = lineScale || 1;
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2 * lineScale;
        
        // NHL corner radius: 28 ft on 85 ft width (~0.329 ratio)
        let cornerRadius;
        if (iceView === 'half-top' || iceView === 'half-bottom') {
            cornerRadius = w * this.NHL_RINK.CORNER_RADIUS;
        } else {
            cornerRadius = h * this.NHL_RINK.CORNER_RADIUS;
        }
        
        ctx.beginPath();
        if (iceView === 'half-top') {
            // Curved corners at top (net end), flat at bottom (center line)
            ctx.moveTo(cornerRadius, 0);
            ctx.lineTo(w - cornerRadius, 0);
            ctx.quadraticCurveTo(w, 0, w, cornerRadius);
            ctx.lineTo(w, h);
            ctx.lineTo(0, h);
            ctx.lineTo(0, cornerRadius);
            ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
        } else if (iceView === 'half-bottom') {
            // Flat at top (center line), curved corners at bottom (net end)
            ctx.moveTo(0, 0);
            ctx.lineTo(w, 0);
            ctx.lineTo(w, h - cornerRadius);
            ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
            ctx.lineTo(cornerRadius, h);
            ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
            ctx.lineTo(0, 0);
        } else if (iceView === 'left-zone') {
            // Curved corners at left (net end), flat at right (blue line side)
            ctx.moveTo(cornerRadius, 0);
            ctx.lineTo(w, 0);
            ctx.lineTo(w, h);
            ctx.lineTo(cornerRadius, h);
            ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
            ctx.lineTo(0, cornerRadius);
            ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
        } else if (iceView === 'right-zone') {
            // Flat at left (blue line side), curved corners at right (net end)
            ctx.moveTo(0, 0);
            ctx.lineTo(w - cornerRadius, 0);
            ctx.quadraticCurveTo(w, 0, w, cornerRadius);
            ctx.lineTo(w, h - cornerRadius);
            ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
            ctx.lineTo(0, h);
            ctx.lineTo(0, 0);
        } else {
            // Full ice and center - all corners rounded
            ctx.moveTo(cornerRadius, 0);
            ctx.lineTo(w - cornerRadius, 0);
            ctx.quadraticCurveTo(w, 0, w, cornerRadius);
            ctx.lineTo(w, h - cornerRadius);
            ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
            ctx.lineTo(cornerRadius, h);
            ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
            ctx.lineTo(0, cornerRadius);
            ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
        }
        ctx.closePath();
        ctx.stroke();
    },
    
    /**
     * Draw full ice view
     */
    drawFullIce: function(ctx, w, h, lineScale) {
        lineScale = lineScale || 1;
        const NHL = this.NHL_RINK;
        
        // NHL proportions
        const goalLinePos = NHL.GOAL_LINE;
        const blueLinePos = NHL.BLUE_LINE;
        const faceoffFromGoal = goalLinePos + NHL.FACEOFF_FROM_GOAL;
        const faceoffFromBoards = NHL.FACEOFF_FROM_BOARDS;
        const cornerRadius = h * NHL.CORNER_RADIUS;
        
        // Center line
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2 * lineScale;
        ctx.beginPath();
        ctx.moveTo(w/2, 0);
        ctx.lineTo(w/2, h);
        ctx.stroke();
        
        // Blue lines
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2 * lineScale;
        ctx.beginPath();
        ctx.moveTo(w * blueLinePos, 0);
        ctx.lineTo(w * blueLinePos, h);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(w * (1 - blueLinePos), 0);
        ctx.lineTo(w * (1 - blueLinePos), h);
        ctx.stroke();
        
        // Center circle
        ctx.beginPath();
        ctx.arc(w/2, h/2, h * NHL.CENTER_CIRCLE_RADIUS, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Center dot
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        ctx.arc(w/2, h/2, 3 * lineScale, 0, 2 * Math.PI);
        ctx.fill();
        
        // Faceoff circles
        ctx.strokeStyle = '#c41e3a';
        const faceoffRadius = h * NHL.FACEOFF_RADIUS;
        const circles = [
            { x: w * faceoffFromGoal, y: h * faceoffFromBoards, zone: 'left' },
            { x: w * faceoffFromGoal, y: h * (1 - faceoffFromBoards), zone: 'left' },
            { x: w * (1 - faceoffFromGoal), y: h * faceoffFromBoards, zone: 'right' },
            { x: w * (1 - faceoffFromGoal), y: h * (1 - faceoffFromBoards), zone: 'right' }
        ];
        
        circles.forEach(circle => {
            ctx.strokeStyle = '#c41e3a';
            ctx.lineWidth = 1 * lineScale;
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, faceoffRadius, 0, 2 * Math.PI);
            ctx.stroke();
            
            // Faceoff dot
            ctx.fillStyle = '#c41e3a';
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, 2 * lineScale, 0, 2 * Math.PI);
            ctx.fill();
            
            // Hash marks
            this.drawHashMarks(ctx, circle.x, circle.y, faceoffRadius, 'horizontal', lineScale);
            
            // Restraint lines
            this.drawRestraintLines(ctx, circle.x, circle.y, faceoffRadius, circle.zone, h, false, lineScale);
        });
        
        // Goal creases
        const creaseRadius = h * NHL.CREASE_RADIUS;
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        
        // Left crease
        ctx.beginPath();
        ctx.arc(w * goalLinePos, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
        ctx.fill();
        ctx.stroke();
        
        // Right crease
        ctx.beginPath();
        ctx.arc(w * (1 - goalLinePos), h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
        ctx.fill();
        ctx.stroke();
        
        // Goal lines - extend to boards respecting curved corners
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2 * lineScale;
        
        // Left goal line
        const leftGoalLineX = w * goalLinePos;
        let leftGoalLineStartY = 0;
        let leftGoalLineEndY = h;
        if (leftGoalLineX < cornerRadius) {
            const dx = cornerRadius - leftGoalLineX;
            const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
            leftGoalLineStartY = yOffset;
            leftGoalLineEndY = h - yOffset;
        }
        ctx.beginPath();
        ctx.moveTo(leftGoalLineX, leftGoalLineStartY);
        ctx.lineTo(leftGoalLineX, leftGoalLineEndY);
        ctx.stroke();
        
        // Right goal line
        const rightGoalLineX = w * (1 - goalLinePos);
        let rightGoalLineStartY = 0;
        let rightGoalLineEndY = h;
        if ((w - rightGoalLineX) < cornerRadius) {
            const dx = cornerRadius - (w - rightGoalLineX);
            const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
            rightGoalLineStartY = yOffset;
            rightGoalLineEndY = h - yOffset;
        }
        ctx.beginPath();
        ctx.moveTo(rightGoalLineX, rightGoalLineStartY);
        ctx.lineTo(rightGoalLineX, rightGoalLineEndY);
        ctx.stroke();
        
        // Trapezoids
        this.drawTrapezoid(ctx, w, h, 'left', lineScale);
        this.drawTrapezoid(ctx, w, h, 'right', lineScale);
        
        // Neutral zone faceoff dots
        const neutralZoneDotOffset = 5 / 200;
        ctx.fillStyle = '#c41e3a';
        const neutralDots = [
            { x: w * (blueLinePos + neutralZoneDotOffset), y: h * faceoffFromBoards },
            { x: w * (blueLinePos + neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) },
            { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * faceoffFromBoards },
            { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) }
        ];
        neutralDots.forEach(dot => {
            ctx.beginPath();
            ctx.arc(dot.x, dot.y, 2 * lineScale, 0, 2 * Math.PI);
            ctx.fill();
        });
    },
    
    /**
     * Draw trapezoid for full ice view
     */
    drawTrapezoid: function(ctx, w, h, side, lineScale) {
        lineScale = lineScale || 1;
        const NHL = this.NHL_RINK;
        const goalLinePos = NHL.GOAL_LINE;
        const trapezoidBase = h * NHL.TRAPEZOID_BASE / 2;
        const trapezoidTop = h * NHL.TRAPEZOID_TOP / 2;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        
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
     * Draw hash marks around faceoff circles
     * @param {string} netPosition - 'horizontal' (nets on left/right) or 'vertical' (nets on top/bottom)
     */
    drawHashMarks: function(ctx, cx, cy, radius, netPosition, lineScale) {
        lineScale = lineScale || 1;
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        ctx.lineCap = 'round';
        
        // NHL regulations: hash marks are 2 feet long, spaced 3 feet apart
        const hashLength = radius * (2 / 15);
        const hashSpacing = radius * (3 / 15);
        const gapOutsideCircle = radius * 0.05;
        const startDistance = radius + gapOutsideCircle;
        
        const sides = [-1, 1];
        
        if (netPosition === 'vertical') {
            // Nets on top/bottom - hash marks on LEFT and RIGHT of circle
            sides.forEach(side => {
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
            // Nets on left/right - hash marks on TOP and BOTTOM of circle
            sides.forEach(side => {
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
     * Draw faceoff restraint lines (L-shaped lines)
     * @param {boolean} isVertical - True for half-ice (nets at top/bottom), false for full ice/zones
     */
    drawRestraintLines: function(ctx, cx, cy, radius, zone, canvasRefDimension, isVertical, lineScale) {
        lineScale = lineScale || 1;
        const NHL = this.NHL_RINK;
        const lineLength = canvasRefDimension * NHL.RESTRAINT_LINE_LENGTH * 1.5;
        const offset = radius * 0.15;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        ctx.lineCap = 'round';
        
        if (isVertical) {
            // Vertical layout (half-ice): net at top or bottom
            const goalDirection = zone === 'top' ? -1 : 1;
            
            this.drawLShapeVertical(ctx, cx - offset, cy - offset, lineLength, goalDirection);
            this.drawLShapeVertical(ctx, cx - offset, cy + offset, lineLength, goalDirection);
            this.drawLShapeVertical(ctx, cx + offset, cy - offset, lineLength, goalDirection);
            this.drawLShapeVertical(ctx, cx + offset, cy + offset, lineLength, goalDirection);
        } else {
            // Horizontal layout (full ice, zones): net at left or right
            const goalDirection = zone === 'left' ? -1 : 1;
            
            this.drawLShape(ctx, cx - offset, cy - offset, lineLength, goalDirection, -1);
            this.drawLShape(ctx, cx + offset, cy - offset, lineLength, goalDirection, -1);
            this.drawLShape(ctx, cx - offset, cy + offset, lineLength, goalDirection, 1);
            this.drawLShape(ctx, cx + offset, cy + offset, lineLength, goalDirection, 1);
        }
    },
    
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
    
    drawLShapeVertical: function(ctx, x, y, length, vDir) {
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x, y + vDir * length);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(x - length/2, y);
        ctx.lineTo(x + length/2, y);
        ctx.stroke();
    },
    
    /**
     * Draw half ice view
     */
    drawHalfIce: function(ctx, w, h, side, lineScale) {
        lineScale = lineScale || 1;
        const NHL = this.NHL_RINK;
        
        const faceoffFromBoards = NHL.FACEOFF_FROM_BOARDS;
        const faceoffRadius = w * NHL.FACEOFF_RADIUS;
        const creaseRadius = w * NHL.CREASE_RADIUS;
        const cornerRadius = w * NHL.CORNER_RADIUS;
        
        // Half-ice proportions (100 ft visible)
        const goalLineRatio = 11 / 100;
        const blueLineRatio = 64 / 100;
        const faceoffYRatio = 31 / 100;
        
        // Blue line position
        const blueLineY = side === 'top' ? h * blueLineRatio : h * (1 - blueLineRatio);
        
        // Blue line
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 2 * lineScale;
        ctx.beginPath();
        ctx.moveTo(0, blueLineY);
        ctx.lineTo(w, blueLineY);
        ctx.stroke();
        
        // Goal position
        const goalY = side === 'top' ? h * goalLineRatio : h * (1 - goalLineRatio);
        
        // Faceoff circles
        const faceoffY = side === 'top' ? h * faceoffYRatio : h * (1 - faceoffYRatio);
        
        // Left faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        ctx.beginPath();
        ctx.arc(w * faceoffFromBoards, faceoffY, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(w * faceoffFromBoards, faceoffY, 2 * lineScale, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, 'vertical', lineScale);
        this.drawRestraintLines(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, side, w, true, lineScale);
        
        // Right faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(w * (1 - faceoffFromBoards), faceoffY, 2 * lineScale, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 'vertical', lineScale);
        this.drawRestraintLines(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, side, w, true, lineScale);
        
        // Goal crease
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        ctx.beginPath();
        if (side === 'top') {
            ctx.arc(w * 0.5, goalY, creaseRadius, 0, Math.PI);
        } else {
            ctx.arc(w * 0.5, goalY, creaseRadius, Math.PI, 0);
        }
        ctx.fill();
        ctx.stroke();
        
        // Goal line - respects curved corners
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2 * lineScale;
        ctx.beginPath();
        
        const distFromEnd = side === 'top' ? goalY : (h - goalY);
        let goalLineStartX = 0;
        let goalLineEndX = w;
        
        if (distFromEnd < cornerRadius) {
            const dy = cornerRadius - distFromEnd;
            const xOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dy * dy);
            goalLineStartX = xOffset;
            goalLineEndX = w - xOffset;
        }
        
        ctx.moveTo(goalLineStartX, goalY);
        ctx.lineTo(goalLineEndX, goalY);
        ctx.stroke();
        
        // Trapezoid
        this.drawHalfIceTrapezoid(ctx, w, h, side, goalY, lineScale);
    },
    
    /**
     * Draw trapezoid for half ice view
     */
    drawHalfIceTrapezoid: function(ctx, w, h, side, goalY, lineScale) {
        lineScale = lineScale || 1;
        const NHL = this.NHL_RINK;
        const trapezoidBase = w * NHL.TRAPEZOID_BASE / 2;
        const trapezoidTop = w * NHL.TRAPEZOID_TOP / 2;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        
        const boardY = side === 'top' ? 0 : h;
        
        ctx.beginPath();
        ctx.moveTo(w/2 - trapezoidBase, goalY);
        ctx.lineTo(w/2 - trapezoidTop, boardY);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(w/2 + trapezoidBase, goalY);
        ctx.lineTo(w/2 + trapezoidTop, boardY);
        ctx.stroke();
    },
    
    /**
     * Draw zone view (left or right half of rink)
     */
    drawZone: function(ctx, w, h, side, lineScale) {
        lineScale = lineScale || 1;
        const NHL = this.NHL_RINK;
        
        const faceoffFromBoards = NHL.FACEOFF_FROM_BOARDS;
        const faceoffRadius = h * NHL.FACEOFF_RADIUS;
        const creaseRadius = h * NHL.CREASE_RADIUS;
        const centerCircleRadius = h * NHL.CENTER_CIRCLE_RADIUS;
        const cornerRadius = h * NHL.CORNER_RADIUS;
        
        // Zone view proportions (100 ft visible)
        const goalLineRatio = 11 / 100;
        const blueLineRatio = 64 / 100;
        const faceoffXRatio = 31 / 100;
        const neutralZoneDotRatio = (64 + 5) / 100;
        
        // Center line at edge
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2 * lineScale;
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
        ctx.lineWidth = 2 * lineScale;
        ctx.beginPath();
        ctx.moveTo(blueLineX, 0);
        ctx.lineTo(blueLineX, h);
        ctx.stroke();
        
        // Goal line - respects curved corners
        const goalLineX = side === 'left' ? w * goalLineRatio : w * (1 - goalLineRatio);
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2 * lineScale;
        ctx.beginPath();
        
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
        
        // Half center circle at edge
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 1 * lineScale;
        ctx.beginPath();
        if (side === 'left') {
            ctx.arc(w, h/2, centerCircleRadius, Math.PI/2, -Math.PI/2);
        } else {
            ctx.arc(0, h/2, centerCircleRadius, -Math.PI/2, Math.PI/2);
        }
        ctx.stroke();
        
        // Center dot at edge
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        if (side === 'left') {
            ctx.arc(w, h/2, 3 * lineScale, 0, 2 * Math.PI);
        } else {
            ctx.arc(0, h/2, 3 * lineScale, 0, 2 * Math.PI);
        }
        ctx.fill();
        
        // Faceoff circles
        const faceoffX = side === 'left' ? w * faceoffXRatio : w * (1 - faceoffXRatio);
        
        // Top faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        ctx.beginPath();
        ctx.arc(faceoffX, h * faceoffFromBoards, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(faceoffX, h * faceoffFromBoards, 2 * lineScale, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, 'horizontal', lineScale);
        this.drawRestraintLines(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, side, h, false, lineScale);
        
        // Bottom faceoff circle
        ctx.strokeStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(faceoffX, h * (1 - faceoffFromBoards), 2 * lineScale, 0, 2 * Math.PI);
        ctx.fill();
        this.drawHashMarks(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 'horizontal', lineScale);
        this.drawRestraintLines(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, side, h, false, lineScale);
        
        // Neutral zone faceoff dots
        const neutralDotX = side === 'left' 
            ? w * neutralZoneDotRatio 
            : w * (1 - neutralZoneDotRatio);
        
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(neutralDotX, h * faceoffFromBoards, 2 * lineScale, 0, 2 * Math.PI);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(neutralDotX, h * (1 - faceoffFromBoards), 2 * lineScale, 0, 2 * Math.PI);
        ctx.fill();
        
        // Goal crease
        ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        ctx.beginPath();
        if (side === 'left') {
            ctx.arc(goalLineX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
        } else {
            ctx.arc(goalLineX, h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
        }
        ctx.fill();
        ctx.stroke();
        
        // Trapezoid
        this.drawZoneTrapezoid(ctx, w, h, side, goalLineX, lineScale);
    },
    
    /**
     * Draw trapezoid for zone view
     */
    drawZoneTrapezoid: function(ctx, w, h, side, goalLineX, lineScale) {
        lineScale = lineScale || 1;
        const NHL = this.NHL_RINK;
        const trapezoidBase = h * NHL.TRAPEZOID_BASE / 2;
        const trapezoidTop = h * NHL.TRAPEZOID_TOP / 2;
        
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 1 * lineScale;
        
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
     * Draw center ice view
     */
    drawCenterIce: function(ctx, w, h, lineScale) {
        lineScale = lineScale || 1;
        const NHL = this.NHL_RINK;
        
        // Center line
        ctx.strokeStyle = '#c41e3a';
        ctx.lineWidth = 2 * lineScale;
        ctx.beginPath();
        ctx.moveTo(w/2, 0);
        ctx.lineTo(w/2, h);
        ctx.stroke();
        
        // Center circle
        ctx.strokeStyle = '#0033a0';
        ctx.lineWidth = 1 * lineScale;
        const circleRadius = h * (15 / 85);
        ctx.beginPath();
        ctx.arc(w/2, h/2, circleRadius, 0, 2 * Math.PI);
        ctx.stroke();
        
        // Center dot
        ctx.fillStyle = '#0033a0';
        ctx.beginPath();
        ctx.arc(w/2, h/2, 4 * lineScale, 0, 2 * Math.PI);
        ctx.fill();
    }
};

// Make available globally
if (typeof window !== 'undefined') {
    window.IceCanvasRenderer = IceCanvasRenderer;
    window.ICE_CANVAS_NHL_RINK = ICE_CANVAS_NHL_RINK;
}
