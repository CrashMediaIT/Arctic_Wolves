<?php
/**
 * Game Plan - In-Game Whiteboard / Dry Erase Board (Coach Only)
 * Interactive rink canvas for drawing plays in real-time during games.
 * Uses ice_canvas.js for rink rendering with freehand drawing overlay.
 * Game lines are displayed below the canvas when not in fullscreen mode.
 */

if (!$isAnyCoach) {
    echo '<div class="empty-state" style="text-align:center;padding:40px"><i class="fas fa-lock" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px"></i><h3>Coach Access Required</h3><p style="color:var(--text-muted)">You need coach access to use the whiteboard.</p></div>';
    return;
}

// ── Parameters ────────────────────────────────────────────────
$wb_team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$wb_ice_view = isset($_GET['ice_view']) ? preg_replace('/[^a-z\-]/', '', $_GET['ice_view']) : 'full';
if (!in_array($wb_ice_view, ['full', 'left-zone', 'right-zone', 'center'])) $wb_ice_view = 'full';

// ── Load teams ────────────────────────────────────────────────
$wb_teams = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, division FROM teams WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $wb_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('WB teams: ' . $e->getMessage()); }

if ($wb_team_id === 0 && !empty($wb_teams)) {
    $wb_team_id = (int)$wb_teams[0]['id'];
}

// ── Load game lines for selected team ─────────────────────────
$wb_lines = [];
if ($wb_team_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT gpl.line_name, gpl.position, gpl.athlete_id, gpl.roster_player_id,
                   COALESCE(u.first_name, rp.first_name) AS first_name,
                   COALESCE(u.last_name, rp.last_name) AS last_name
            FROM vr_game_plan_lines gpl
            LEFT JOIN users u ON gpl.athlete_id = u.id
            LEFT JOIN roster_players rp ON gpl.roster_player_id = rp.id
            WHERE gpl.team_id = ? AND gpl.game_id IS NULL
            ORDER BY gpl.line_name, gpl.position
        ");
        $stmt->execute([$wb_team_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('decryptUserRows')) {
            $rows = decryptUserRows($rows);
        }
        foreach ($rows as $r) {
            $wb_lines[$r['line_name']][$r['position']] = $r;
        }
    } catch (PDOException $e) { error_log('WB lines: ' . $e->getMessage()); }
}

$wb_current_team = '';
foreach ($wb_teams as $t) {
    if ((int)$t['id'] === $wb_team_id) { $wb_current_team = $t['name']; break; }
}

// Line structure definitions
$wb_forward_lines = ['Line 1' => ['LW', 'C', 'RW'], 'Line 2' => ['LW', 'C', 'RW'], 'Line 3' => ['LW', 'C', 'RW'], 'Line 4' => ['LW', 'C', 'RW']];
$wb_defense_pairs = ['Pair 1' => ['LD', 'RD'], 'Pair 2' => ['LD', 'RD'], 'Pair 3' => ['LD', 'RD']];
$wb_special_teams = ['PP1' => ['LW', 'C', 'RW', 'LD', 'RD'], 'PP2' => ['LW', 'C', 'RW', 'LD', 'RD'], 'PK1' => ['F1', 'F2', 'LD', 'RD'], 'PK2' => ['F1', 'F2', 'LD', 'RD']];
$wb_goalie_lines = ['Goalies' => ['Starter', 'Backup']];
?>

<!-- Page header -->
<div class="page-header" id="wbHeader">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h1><i class="fas fa-chalkboard"></i> Whiteboard</h1>
            <p>In-game dry erase board – draw plays on the rink</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <select class="form-select" style="width:auto;min-width:160px;" onchange="location.href='/gameplan.php?page=whiteboard&ice_view=<?= $wb_ice_view ?>&team_id='+this.value">
                <?php foreach ($wb_teams as $tm): ?>
                <option value="<?= (int)$tm['id'] ?>" <?= $wb_team_id === (int)$tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" style="width:auto;" onchange="location.href='/gameplan.php?page=whiteboard&team_id=<?= $wb_team_id ?>&ice_view='+this.value">
                <option value="full" <?= $wb_ice_view === 'full' ? 'selected' : '' ?>>Full Ice</option>
                <option value="left-zone" <?= $wb_ice_view === 'left-zone' ? 'selected' : '' ?>>Left Zone</option>
                <option value="right-zone" <?= $wb_ice_view === 'right-zone' ? 'selected' : '' ?>>Right Zone</option>
                <option value="center" <?= $wb_ice_view === 'center' ? 'selected' : '' ?>>Center Ice</option>
            </select>
            <button class="btn btn-secondary" onclick="wbToggleFullscreen()" title="Toggle fullscreen" id="wbFullscreenBtn"><i class="fas fa-expand"></i></button>
        </div>
    </div>
</div>

<!-- Whiteboard area (toolbar + canvas) -->
<div id="wbCanvasContainer" style="position:relative;margin-bottom:20px;">

    <!-- Drawing toolbar -->
    <div class="card" style="margin-bottom:12px;" id="wbToolbar">
        <div class="card-body" style="padding:10px 16px;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <!-- Drawing tools -->
                <div style="display:flex;gap:4px;border-right:1px solid var(--border);padding-right:12px;">
                    <button class="btn btn-secondary wb-tool active" data-tool="freehand" title="Freehand draw" style="height:32px;width:32px;padding:0;font-size:13px;"><i class="fas fa-pencil"></i></button>
                    <button class="btn btn-secondary wb-tool" data-tool="line" title="Straight line" style="height:32px;width:32px;padding:0;font-size:13px;"><i class="fas fa-minus"></i></button>
                    <button class="btn btn-secondary wb-tool" data-tool="arrow" title="Arrow" style="height:32px;width:32px;padding:0;font-size:13px;"><i class="fas fa-arrow-right"></i></button>
                    <button class="btn btn-secondary wb-tool" data-tool="dashed" title="Dashed line" style="height:32px;width:32px;padding:0;font-size:13px;"><i class="fas fa-ellipsis"></i></button>
                </div>
                <!-- Line width -->
                <div style="display:flex;align-items:center;gap:6px;border-right:1px solid var(--border);padding-right:12px;">
                    <label style="font-size:11px;color:var(--text-muted);font-weight:600;">Size</label>
                    <input type="range" min="2" max="12" value="3" id="wbLineWidth" style="width:60px;">
                </div>
                <!-- Colors -->
                <div style="display:flex;gap:4px;border-right:1px solid var(--border);padding-right:12px;" id="wbColors">
                    <button class="wb-color active" data-color="#000000" style="width:24px;height:24px;border-radius:50%;border:2px solid #fff;cursor:pointer;background:#000000;" title="Black"></button>
                    <button class="wb-color" data-color="#EF4444" style="width:24px;height:24px;border-radius:50%;border:2px solid transparent;cursor:pointer;background:#EF4444;" title="Red"></button>
                    <button class="wb-color" data-color="#3B82F6" style="width:24px;height:24px;border-radius:50%;border:2px solid transparent;cursor:pointer;background:#3B82F6;" title="Blue"></button>
                    <button class="wb-color" data-color="#10B981" style="width:24px;height:24px;border-radius:50%;border:2px solid transparent;cursor:pointer;background:#10B981;" title="Green"></button>
                    <button class="wb-color" data-color="#F59E0B" style="width:24px;height:24px;border-radius:50%;border:2px solid transparent;cursor:pointer;background:#F59E0B;" title="Orange"></button>
                    <button class="wb-color" data-color="#A855F7" style="width:24px;height:24px;border-radius:50%;border:2px solid transparent;cursor:pointer;background:#A855F7;" title="Purple"></button>
                    <button class="wb-color" data-color="#FFFFFF" style="width:24px;height:24px;border-radius:50%;border:2px solid #ccc;cursor:pointer;background:#FFFFFF;" title="White (eraser)"></button>
                </div>
                <!-- Actions -->
                <div style="display:flex;gap:4px;">
                    <button class="btn btn-secondary" onclick="wbUndo()" title="Undo" style="height:32px;width:32px;padding:0;font-size:13px;"><i class="fas fa-undo"></i></button>
                    <button class="btn btn-secondary" onclick="wbClear()" title="Clear all drawings" style="height:32px;padding:0 12px;font-size:12px;display:inline-flex;align-items:center;gap:5px;color:var(--error, #EF4444);"><i class="fas fa-trash"></i> Clear All</button>
                    <button class="btn btn-secondary" onclick="wbExport()" title="Save as image" style="height:32px;width:32px;padding:0;font-size:13px;"><i class="fas fa-download"></i></button>
                    <button class="btn btn-secondary" onclick="wbToggleFullscreen()" title="Toggle fullscreen" id="wbFullscreenBtn2" style="height:32px;width:32px;padding:0;font-size:13px;"><i class="fas fa-expand"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Canvas -->
    <div class="card" style="overflow:hidden;" id="wbCanvasCard">
        <div style="position:relative;width:100%;padding-top:42.5%;background:#f0f7fa;">
            <canvas id="wbRinkCanvas" style="position:absolute;top:0;left:0;width:100%;height:100%;"></canvas>
            <canvas id="wbDrawCanvas" style="position:absolute;top:0;left:0;width:100%;height:100%;cursor:crosshair;"></canvas>
        </div>
    </div>
</div>

<!-- Game Lines (visible below canvas when not fullscreen) -->
<div id="wbLinesSection">
    <?php if (!empty($wb_lines)): ?>
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <h3><i class="fas fa-users-line"></i> <?= htmlspecialchars($wb_current_team) ?> – Game Lines</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0;">
                <?php
                // Forward lines
                foreach ($wb_forward_lines as $line_name => $positions):
                    $has_players = false;
                    foreach ($positions as $pos) { if (!empty($wb_lines[$line_name][$pos])) $has_players = true; }
                    if (!$has_players) continue;
                ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);border-right:1px solid var(--border);">
                    <div style="font-size:11px;font-weight:700;color:var(--primary-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?= htmlspecialchars($line_name) ?></div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php foreach ($positions as $pos):
                            $p = $wb_lines[$line_name][$pos] ?? null;
                            if (!$p) continue;
                            $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                        ?>
                        <span style="font-size:12px;"><strong style="color:var(--text-muted);font-size:10px;"><?= htmlspecialchars($pos) ?></strong> <?= htmlspecialchars($name) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php
                // Defense pairs
                foreach ($wb_defense_pairs as $line_name => $positions):
                    $has_players = false;
                    foreach ($positions as $pos) { if (!empty($wb_lines[$line_name][$pos])) $has_players = true; }
                    if (!$has_players) continue;
                ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);border-right:1px solid var(--border);">
                    <div style="font-size:11px;font-weight:700;color:var(--primary-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?= htmlspecialchars($line_name) ?></div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php foreach ($positions as $pos):
                            $p = $wb_lines[$line_name][$pos] ?? null;
                            if (!$p) continue;
                            $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                        ?>
                        <span style="font-size:12px;"><strong style="color:var(--text-muted);font-size:10px;"><?= htmlspecialchars($pos) ?></strong> <?= htmlspecialchars($name) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php
                // Special teams
                foreach ($wb_special_teams as $line_name => $positions):
                    $has_players = false;
                    foreach ($positions as $pos) { if (!empty($wb_lines[$line_name][$pos])) $has_players = true; }
                    if (!$has_players) continue;
                ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);border-right:1px solid var(--border);">
                    <div style="font-size:11px;font-weight:700;color:var(--primary-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?= htmlspecialchars($line_name) ?></div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php foreach ($positions as $pos):
                            $p = $wb_lines[$line_name][$pos] ?? null;
                            if (!$p) continue;
                            $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                        ?>
                        <span style="font-size:12px;"><strong style="color:var(--text-muted);font-size:10px;"><?= htmlspecialchars($pos) ?></strong> <?= htmlspecialchars($name) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php
                // Goalies
                foreach ($wb_goalie_lines as $line_name => $positions):
                    $has_players = false;
                    foreach ($positions as $pos) { if (!empty($wb_lines[$line_name][$pos])) $has_players = true; }
                    if (!$has_players) continue;
                ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);border-right:1px solid var(--border);">
                    <div style="font-size:11px;font-weight:700;color:var(--primary-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?= htmlspecialchars($line_name) ?></div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php foreach ($positions as $pos):
                            $p = $wb_lines[$line_name][$pos] ?? null;
                            if (!$p) continue;
                            $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                        ?>
                        <span style="font-size:12px;"><strong style="color:var(--text-muted);font-size:10px;"><?= htmlspecialchars($pos) ?></strong> <?= htmlspecialchars($name) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:24px;">
            <p style="color:var(--text-muted);margin:0;font-size:13px;">
                <i class="fas fa-users-slash" style="margin-right:6px;"></i>
                No game lines set for this team.
                <a href="/gameplan.php?page=lines&team_id=<?= $wb_team_id ?>" style="color:var(--primary-light, #8B5CF6);text-decoration:none;font-weight:600;">Set up lines</a>
            </p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="js/ice_canvas.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var iceView = '<?= $wb_ice_view ?>';
    var rinkCanvas = document.getElementById('wbRinkCanvas');
    var drawCanvas = document.getElementById('wbDrawCanvas');
    var rinkCtx = rinkCanvas.getContext('2d');
    var drawCtx = drawCanvas.getContext('2d');
    var container = rinkCanvas.parentElement;

    // Drawing state
    var isDrawing = false;
    var currentTool = 'freehand';
    var currentColor = '#000000';
    var lineWidth = 3;
    var history = [];
    var startX, startY;
    var points = []; // For freehand

    // Logo
    var logoImage = new Image();
    var logoLoaded = false;
    logoImage.crossOrigin = 'anonymous';
    logoImage.onload = function() { logoLoaded = true; drawRink(); };
    logoImage.onerror = function() { logoLoaded = false; drawRink(); };
    logoImage.src = 'https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png';

    function resizeCanvases() {
        var rect = container.getBoundingClientRect();
        var w = Math.round(rect.width);
        var h = Math.round(rect.height);
        rinkCanvas.width = w;
        rinkCanvas.height = h;
        drawCanvas.width = w;
        drawCanvas.height = h;
        drawRink();
        redrawHistory();
    }

    function drawRink() {
        if (typeof IceCanvasRenderer !== 'undefined') {
            IceCanvasRenderer.drawRink(rinkCtx, rinkCanvas.width, rinkCanvas.height, iceView, {
                logoImage: logoImage,
                logoLoaded: logoLoaded
            });
        }
    }

    function saveToHistory() {
        history.push(drawCtx.getImageData(0, 0, drawCanvas.width, drawCanvas.height));
        if (history.length > 50) history.shift();
    }

    function redrawHistory() {
        if (history.length > 0) {
            // Scale last history entry to current canvas size
            var last = history[history.length - 1];
            // Create temp canvas to draw old data and scale
            var temp = document.createElement('canvas');
            temp.width = last.width;
            temp.height = last.height;
            temp.getContext('2d').putImageData(last, 0, 0);
            drawCtx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
            drawCtx.drawImage(temp, 0, 0, drawCanvas.width, drawCanvas.height);
        }
    }

    function getPos(e) {
        var rect = drawCanvas.getBoundingClientRect();
        var touch = e.touches ? e.touches[0] : e;
        return {
            x: (touch.clientX - rect.left) * (drawCanvas.width / rect.width),
            y: (touch.clientY - rect.top) * (drawCanvas.height / rect.height)
        };
    }

    // Drawing handlers
    function onStart(e) {
        e.preventDefault();
        isDrawing = true;
        var pos = getPos(e);
        startX = pos.x;
        startY = pos.y;
        points = [pos];

        if (currentTool === 'freehand') {
            drawCtx.beginPath();
            drawCtx.moveTo(pos.x, pos.y);
            drawCtx.strokeStyle = currentColor;
            drawCtx.lineWidth = lineWidth;
            drawCtx.lineCap = 'round';
            drawCtx.lineJoin = 'round';
        }
    }

    function onMove(e) {
        if (!isDrawing) return;
        e.preventDefault();
        var pos = getPos(e);

        if (currentTool === 'freehand') {
            drawCtx.lineTo(pos.x, pos.y);
            drawCtx.stroke();
            points.push(pos);
        } else {
            // For line/arrow/dashed: preview by redrawing
            redrawHistory();
            drawStraight(drawCtx, startX, startY, pos.x, pos.y, currentTool, currentColor, lineWidth);
        }
    }

    function onEnd(e) {
        if (!isDrawing) return;
        isDrawing = false;

        if (currentTool !== 'freehand') {
            var pos = e.changedTouches ? getPos(e.changedTouches[0]) : getPos(e);
            redrawHistory();
            drawStraight(drawCtx, startX, startY, pos.x, pos.y, currentTool, currentColor, lineWidth);
        }

        saveToHistory();
    }

    function drawStraight(ctx, x1, y1, x2, y2, tool, color, width) {
        ctx.strokeStyle = color;
        ctx.lineWidth = width;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (tool === 'dashed') {
            ctx.setLineDash([width * 3, width * 2]);
        } else {
            ctx.setLineDash([]);
        }

        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        ctx.setLineDash([]);

        if (tool === 'arrow') {
            var angle = Math.atan2(y2 - y1, x2 - x1);
            var headLen = width * 5;
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headLen * Math.cos(angle - Math.PI / 6), y2 - headLen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headLen * Math.cos(angle + Math.PI / 6), y2 - headLen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
    }

    // Mouse events
    drawCanvas.addEventListener('mousedown', onStart);
    drawCanvas.addEventListener('mousemove', onMove);
    drawCanvas.addEventListener('mouseup', onEnd);
    drawCanvas.addEventListener('mouseleave', onEnd);

    // Touch events
    drawCanvas.addEventListener('touchstart', onStart, { passive: false });
    drawCanvas.addEventListener('touchmove', onMove, { passive: false });
    drawCanvas.addEventListener('touchend', onEnd);

    // Tool selection
    document.querySelectorAll('.wb-tool').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.wb-tool').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentTool = btn.dataset.tool;
        });
    });

    // Color selection
    document.querySelectorAll('.wb-color').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.wb-color').forEach(function(b) { b.style.borderColor = 'transparent'; });
            btn.style.borderColor = '#fff';
            currentColor = btn.dataset.color;
            btn.classList.add('active');
        });
    });

    // Line width
    document.getElementById('wbLineWidth').addEventListener('input', function() {
        lineWidth = parseInt(this.value) || 3;
    });

    // Resize
    window.addEventListener('resize', resizeCanvases);
    resizeCanvases();

    // Global functions
    window.wbUndo = function() {
        if (history.length > 0) {
            history.pop();
            drawCtx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
            if (history.length > 0) {
                redrawHistory();
            }
        }
    };

    window.wbClear = function() {
        history = [];
        drawCtx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
    };

    window.wbExport = function() {
        var exportCanvas = document.createElement('canvas');
        exportCanvas.width = rinkCanvas.width;
        exportCanvas.height = rinkCanvas.height;
        var expCtx = exportCanvas.getContext('2d');
        expCtx.drawImage(rinkCanvas, 0, 0);
        expCtx.drawImage(drawCanvas, 0, 0);
        var link = document.createElement('a');
        link.download = 'whiteboard_' + new Date().toISOString().slice(0, 10) + '.png';
        link.href = exportCanvas.toDataURL('image/png');
        link.click();
    };

    window.wbToggleFullscreen = function() {
        var wrapper = document.getElementById('wbCanvasContainer');
        var linesSection = document.getElementById('wbLinesSection');
        var header = document.getElementById('wbHeader');
        var toolbar = document.getElementById('wbToolbar');
        var sidebar = document.querySelector('.gp-sidebar');
        var btn = document.getElementById('wbFullscreenBtn');
        var btn2 = document.getElementById('wbFullscreenBtn2');

        if (wrapper.classList.contains('wb-fullscreen')) {
            // Exit fullscreen
            wrapper.classList.remove('wb-fullscreen');
            linesSection.style.display = '';
            header.style.display = '';
            if (sidebar) sidebar.style.display = '';
            if (btn) btn.innerHTML = '<i class="fas fa-expand"></i>';
            if (btn2) btn2.innerHTML = '<i class="fas fa-expand"></i>';
            document.body.style.overflow = '';
        } else {
            // Enter fullscreen
            wrapper.classList.add('wb-fullscreen');
            linesSection.style.display = 'none';
            header.style.display = 'none';
            if (sidebar) sidebar.style.display = 'none';
            if (btn) btn.innerHTML = '<i class="fas fa-compress"></i>';
            if (btn2) btn2.innerHTML = '<i class="fas fa-compress"></i>';
            document.body.style.overflow = 'hidden';
        }
        setTimeout(resizeCanvases, 100);
    };

    // Escape key exits fullscreen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var wrapper = document.getElementById('wbCanvasContainer');
            if (wrapper.classList.contains('wb-fullscreen')) {
                wbToggleFullscreen();
            }
        }
    });
});
</script>

<style>
.wb-tool.active {
    background: var(--primary) !important;
    color: #fff !important;
    border-color: var(--primary) !important;
}
.wb-color.active {
    box-shadow: 0 0 0 2px var(--primary-light);
}
#wbCanvasContainer.wb-fullscreen {
    position: fixed !important;
    inset: 0;
    z-index: 1000;
    margin: 0;
    background: var(--bg-main);
    display: flex;
    flex-direction: column;
}
#wbCanvasContainer.wb-fullscreen #wbCanvasCard {
    flex: 1;
    border-radius: 0;
    margin: 0;
}
#wbCanvasContainer.wb-fullscreen #wbCanvasCard > div {
    padding-top: 0 !important;
    height: 100%;
}
#wbCanvasContainer.wb-fullscreen canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}
#wbCanvasContainer.wb-fullscreen #wbToolbar {
    flex-shrink: 0;
    border-radius: 0;
    margin-bottom: 0;
}
</style>
