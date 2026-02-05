<!-- Create/Edit Drill View -->
<?php
$editDrillId = $_GET['edit'] ?? null;
$editingDrill = null;
$isEditMode = false;

// Fetch center ice logo URL from theme settings for drill designer
// Uses single query with COALESCE for efficiency
$centerLogoUrl = '';
try {
    $logoStmt = $pdo->prepare("
        SELECT COALESCE(
            MAX(CASE WHEN setting_name = 'center_ice_logo_url' AND setting_value != '' THEN setting_value END),
            MAX(CASE WHEN setting_name = 'logo_url' AND setting_value != '' THEN setting_value END)
        ) as logo_url 
        FROM theme_settings 
        WHERE setting_name IN ('center_ice_logo_url', 'logo_url')
    ");
    $logoStmt->execute();
    $logoResult = $logoStmt->fetch(PDO::FETCH_ASSOC);
    if ($logoResult && !empty($logoResult['logo_url'])) {
        $centerLogoUrl = $logoResult['logo_url'];
    }
} catch (PDOException $e) {
    error_log("Error fetching center ice logo URL: " . $e->getMessage());
}

if ($editDrillId) {
    // Fetch drill data for editing
    try {
        $stmt = $pdo->prepare("SELECT * FROM drills WHERE id = ?");
        $stmt->execute([$editDrillId]);
        $editingDrill = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($editingDrill) {
            $isEditMode = true;
        }
    } catch (PDOException $e) {
        error_log("Error fetching drill for edit: " . $e->getMessage());
    }
}
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-<?php echo $isEditMode ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $isEditMode ? 'Edit Drill' : 'Create New Drill'; ?>
    </h1>
    <p class="page-description"><?php echo $isEditMode ? 'Modify the drill using the interactive designer' : 'Design a custom drill with the interactive tool'; ?></p>
</div>

<div class="create-drill-content">
    <!-- Interactive Drill Designer - Now on top -->
    <div class="drill-designer-section">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-drafting-compass"></i> Drill Diagram</h3>
            </div>
            <div class="card-body">
                <!-- Tool Groups -->
                <div class="designer-toolbar">
                    <div class="tool-group">
                        <span class="tool-group-label">Selection</span>
                        <button class="tool-btn active" title="Select" data-tool="select"><i class="fas fa-mouse-pointer"></i></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Players</span>
                        <button class="tool-btn" title="Forward (F)" data-tool="forward"><span class="tool-label">F</span></button>
                        <button class="tool-btn" title="Forward 1 (F1)" data-tool="f1"><span class="tool-label">F1</span></button>
                        <button class="tool-btn" title="Forward 2 (F2)" data-tool="f2"><span class="tool-label">F2</span></button>
                        <button class="tool-btn" title="Forward 3 (F3)" data-tool="f3"><span class="tool-label">F3</span></button>
                        <button class="tool-btn" title="Defense (D)" data-tool="defense"><span class="tool-label">D</span></button>
                        <button class="tool-btn" title="Defense 1 (D1)" data-tool="d1"><span class="tool-label">D1</span></button>
                        <button class="tool-btn" title="Defense 2 (D2)" data-tool="d2"><span class="tool-label">D2</span></button>
                        <button class="tool-btn" title="Coach" data-tool="coach"><i class="fas fa-user-tie"></i></button>
                        <button class="tool-btn" title="Goalie (G)" data-tool="goalie"><span class="tool-label">G</span></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Positions</span>
                        <button class="tool-btn" title="Center (C)" data-tool="center"><span class="tool-label">C</span></button>
                        <button class="tool-btn" title="Left Wing (LW)" data-tool="lw"><span class="tool-label">LW</span></button>
                        <button class="tool-btn" title="Right Wing (RW)" data-tool="rw"><span class="tool-label">RW</span></button>
                        <button class="tool-btn" title="Left Defense (LD)" data-tool="ld"><span class="tool-label">LD</span></button>
                        <button class="tool-btn" title="Right Defense (RD)" data-tool="rd"><span class="tool-label">RD</span></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Equipment</span>
                        <button class="tool-btn" title="Single Puck" data-tool="puck"><i class="fas fa-hockey-puck"></i></button>
                        <button class="tool-btn" title="Puck Group" data-tool="pucks"><i class="fas fa-circle"></i><i class="fas fa-circle" style="margin-left: -8px;"></i></button>
                        <button class="tool-btn" title="Cone" data-tool="cone"><i class="fas fa-play" style="transform: rotate(-90deg);"></i></button>
                        <button class="tool-btn" title="Net" data-tool="net"><i class="fas fa-border-all"></i></button>
                        <button class="tool-btn" title="Mini Net" data-tool="mininet"><i class="fas fa-th-large"></i></button>
                        <button class="tool-btn" title="Tire" data-tool="tire"><i class="fas fa-circle-notch"></i></button>
                        <button class="tool-btn" title="Stick" data-tool="stick">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: block; position: relative; z-index: 1;">
                                <path d="M4 4 L12 18 L18 14" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Drawing</span>
                        <button class="tool-btn" title="Draw Line" data-tool="line"><i class="fas fa-minus"></i></button>
                        <button class="tool-btn" title="Draw Dashed Line" data-tool="dashed"><i class="fas fa-ellipsis-h"></i></button>
                        <button class="tool-btn" title="Squiggly Line (Puck Carry)" data-tool="squiggly"><i class="fas fa-wave-square"></i></button>
                        <button class="tool-btn" title="Arrow" data-tool="arrow"><i class="fas fa-long-arrow-alt-right"></i></button>
                        <button class="tool-btn" title="Add Text" data-tool="text"><i class="fas fa-font"></i></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Skating Patterns</span>
                        <button class="tool-btn" title="Forward Skating" data-tool="skating_forward"><i class="fas fa-arrow-right"></i></button>
                        <button class="tool-btn" title="Backward Skating" data-tool="skating_backward"><i class="fas fa-arrow-left"></i></button>
                        <button class="tool-btn" title="Lateral Skating" data-tool="skating_lateral"><i class="fas fa-arrows-alt-h"></i></button>
                        <button class="tool-btn" title="C-Cuts Skating" data-tool="skating_ccuts"><span class="tool-label" style="font-weight: bold;">C</span></button>
                        <button class="tool-btn" title="Forward Skating with Puck" data-tool="skating_forward_puck"><i class="fas fa-hockey-puck"></i><i class="fas fa-arrow-right" style="font-size: 8px; margin-left: 2px;"></i></button>
                        <button class="tool-btn" title="Backward Skating with Puck" data-tool="skating_backward_puck"><i class="fas fa-arrow-left" style="font-size: 8px; margin-right: 2px;"></i><i class="fas fa-hockey-puck"></i></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Pass/Shot</span>
                        <button class="tool-btn" title="Pass" data-tool="pass"><i class="fas fa-share"></i></button>
                        <button class="tool-btn" title="Shot" data-tool="shot"><i class="fas fa-crosshairs"></i></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Numbers</span>
                        <button class="tool-btn" title="Number 0" data-tool="num0"><span class="tool-label">0</span></button>
                        <button class="tool-btn" title="Number 1" data-tool="num1"><span class="tool-label">1</span></button>
                        <button class="tool-btn" title="Number 2" data-tool="num2"><span class="tool-label">2</span></button>
                        <button class="tool-btn" title="Number 3" data-tool="num3"><span class="tool-label">3</span></button>
                        <button class="tool-btn" title="Number 4" data-tool="num4"><span class="tool-label">4</span></button>
                        <button class="tool-btn" title="Number 5" data-tool="num5"><span class="tool-label">5</span></button>
                        <button class="tool-btn" title="Number 6" data-tool="num6"><span class="tool-label">6</span></button>
                        <button class="tool-btn" title="Number 7" data-tool="num7"><span class="tool-label">7</span></button>
                        <button class="tool-btn" title="Number 8" data-tool="num8"><span class="tool-label">8</span></button>
                        <button class="tool-btn" title="Number 9" data-tool="num9"><span class="tool-label">9</span></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Color</span>
                        <button class="tool-btn" title="Paint Color" data-tool="paint"><i class="fas fa-paint-brush"></i></button>
                        <div class="color-picker-wrapper">
                            <input type="color" id="drill-color-picker" data-drill-action="color-picker" value="#000000" title="Select Color">
                            <div class="active-color-circle" style="background-color: #000000;"></div>
                        </div>
                        <button class="tool-btn color-preset" title="Black" data-color-preset="#000000" style="background-color: #000000;"></button>
                        <button class="tool-btn color-preset" title="Red" data-color-preset="#c41e3a" style="background-color: #c41e3a;"></button>
                        <button class="tool-btn color-preset" title="Blue" data-color-preset="#0033a0" style="background-color: #0033a0;"></button>
                        <button class="tool-btn color-preset" title="Light Blue" data-color-preset="#00bfff" style="background-color: #00bfff;"></button>
                        <button class="tool-btn color-preset" title="Orange" data-color-preset="#ff6600" style="background-color: #ff6600;"></button>
                        <button class="tool-btn color-preset" title="Green" data-color-preset="#10b981" style="background-color: #10b981;"></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Edit</span>
                        <button class="tool-btn" title="Rotate Item" data-tool="rotate" data-drill-action="rotate"><i class="fas fa-sync-alt"></i></button>
                        <button class="tool-btn danger-btn" title="Delete Selected" data-tool="delete" data-drill-action="delete"><i class="fas fa-trash-alt"></i></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Actions</span>
                        <button class="tool-btn danger-btn" title="Clear All" data-tool="clear"><i class="fas fa-trash"></i></button>
                        <button class="tool-btn fullscreen-btn" title="Fullscreen" data-tool="fullscreen"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                
                <!-- Ice View Selector -->
                <div class="ice-view-selector">
                    <label>Ice View:</label>
                    <select class="form-input-small" id="iceViewSelect" data-ice-view>
                        <option value="full" selected>Full Ice</option>
                        <option value="half-top">Half Ice (Top)</option>
                        <option value="half-bottom">Half Ice (Bottom)</option>
                        <option value="left-zone">Left Zone</option>
                        <option value="right-zone">Right Zone</option>
                        <option value="center">Center Ice</option>
                    </select>
                </div>
                <div class="ice-rink-canvas" id="drill-rink-container" data-ice-view="full" data-center-logo="<?php echo htmlspecialchars($centerLogoUrl); ?>">
                    <div class="rink-overlay">
                        <p><i class="fas fa-info-circle"></i> Click the tools above to start designing your drill</p>
                    </div>
                </div>
                <div class="canvas-controls">
                    <button class="btn-secondary" data-drill-action="undo"><i class="fas fa-undo"></i> Undo</button>
                    <button class="btn-secondary" data-drill-action="redo"><i class="fas fa-redo"></i> Redo</button>
                    <button class="btn-secondary" data-drill-action="export"><i class="fas fa-download"></i> Export Image</button>
                    <?php if ($isEditMode): ?>
                    <button class="btn-secondary" data-drill-action="share"><i class="fas fa-share-alt"></i> Share Link</button>
                    <?php endif; ?>
                </div>
                <div class="canvas-help-text">
                    <p><i class="fas fa-lightbulb"></i> <strong>Tips:</strong> Click Rotate/Delete tool then click items to edit. Select item + press <kbd>R</kbd> to rotate or <kbd>Delete</kbd> to remove. Use Freehand for smooth paint-like lines.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Drill Form - Now below the diagram -->
    <div class="drill-form-section">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Drill Information</h3>
            </div>
            <div class="card-body">
                <form class="drill-form" method="POST" action="process_drills.php">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="<?php echo $isEditMode ? 'update' : 'create'; ?>">
                    <?php if ($isEditMode): ?>
                    <input type="hidden" name="drill_id" value="<?php echo htmlspecialchars($editingDrill['id']); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="diagram_data" id="diagram_data" value="<?php echo htmlspecialchars($editingDrill['diagram_data'] ?? ''); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Drill Name *</label>
                            <input type="text" name="drill_name" class="form-input" placeholder="Enter drill name" required value="<?php echo htmlspecialchars($editingDrill['title'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Category (Skill Focus) *</label>
                            <select name="category" class="form-input" required>
                                <option value="">-- Select Category --</option>
                                <?php 
                                // Fetch skill categories from database
                                $skillStmt = $pdo->prepare("SELECT name FROM eval_skills ORDER BY name ASC");
                                $skillStmt->execute();
                                $skillCategories = $skillStmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                // If no skills in database, use default categories
                                if (empty($skillCategories)) {
                                    $skillCategories = ['Skating', 'Shooting', 'Passing', 'Stickhandling', 'Defensive', 'Offensive', 'Conditioning'];
                                }
                                
                                $currentCat = $editingDrill['category'] ?? '';
                                foreach ($skillCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($currentCat === $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Skill Level *</label>
                            <select name="skill_level" class="form-input" required>
                                <option value="">-- Select Level --</option>
                                <?php
                                $levels = ['Beginner', 'Intermediate', 'Advanced'];
                                $currentLevel = $editingDrill['skill_level'] ?? '';
                                foreach ($levels as $level): ?>
                                <option <?php echo ($currentLevel === $level) ? 'selected' : ''; ?>><?php echo $level; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" class="form-input" placeholder="10" min="1" value="<?php echo htmlspecialchars($editingDrill['duration'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Number of Players</label>
                            <input type="text" name="num_players" class="form-input" placeholder="e.g., 6-18" value="<?php echo htmlspecialchars($editingDrill['num_players'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Tags (comma separated)</label>
                            <input type="text" name="tags" class="form-input" placeholder="e.g., warmup, power play, breakout" value="<?php echo htmlspecialchars($editingDrill['tags'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="Describe the drill objectives and key points..." required><?php echo htmlspecialchars($editingDrill['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea name="instructions" class="form-textarea" rows="4" placeholder="Step-by-step instructions for executing the drill..."><?php echo htmlspecialchars($editingDrill['instructions'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Equipment Needed</label>
                        <?php 
                        $currentEquipment = explode(',', $editingDrill['equipment'] ?? '');
                        // Fetch equipment categories from database
                        $equipStmt = $pdo->prepare("SELECT id, name FROM equipment WHERE equipment_type = 'category' ORDER BY name ASC");
                        $equipStmt->execute();
                        $equipmentCategories = $equipStmt->fetchAll();
                        // Define hockey stick SVG icon with accessibility attributes
                        $stickIconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" role="img" aria-label="Hockey stick" style="display: inline-block; vertical-align: middle; margin-right: 8px;"><path d="M4 4 L12 18 L18 14" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                        ?>
                        <div class="equipment-tags">
                            <?php if (count($equipmentCategories) > 0): ?>
                                <?php foreach ($equipmentCategories as $equip): 
                                    $equipValue = strtolower(str_replace(' ', '_', $equip['name']));
                                    $isChecked = in_array($equipValue, $currentEquipment) || in_array($equip['name'], $currentEquipment);
                                    // Get appropriate icon based on equipment name
                                    $icon = 'fa-tools';
                                    $isStick = false;
                                    $nameLower = strtolower($equip['name']);
                                    if (strpos($nameLower, 'puck') !== false) $icon = 'fa-hockey-puck';
                                    elseif (strpos($nameLower, 'cone') !== false) $icon = 'fa-play';
                                    elseif (strpos($nameLower, 'net') !== false) $icon = 'fa-bullseye';
                                    elseif (strpos($nameLower, 'stick') !== false) $isStick = true;
                                    elseif (strpos($nameLower, 'tire') !== false) $icon = 'fa-circle-notch';
                                    elseif (strpos($nameLower, 'goal') !== false) $icon = 'fa-border-all';
                                ?>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="equipment[]" value="<?php echo htmlspecialchars($equipValue); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                    <span><?php if ($isStick): ?><?php echo $stickIconSvg; ?><?php else: ?><i class="fas <?php echo $icon; ?>"></i> <?php endif; ?><?php echo htmlspecialchars($equip['name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Fallback to default equipment if no categories defined -->
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="equipment[]" value="pucks" <?php echo in_array('pucks', $currentEquipment) ? 'checked' : ''; ?>>
                                    <span><i class="fas fa-hockey-puck"></i> Pucks</span>
                                </label>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="equipment[]" value="cones" <?php echo in_array('cones', $currentEquipment) ? 'checked' : ''; ?>>
                                    <span><i class="fas fa-play"></i> Cones</span>
                                </label>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="equipment[]" value="nets" <?php echo in_array('nets', $currentEquipment) ? 'checked' : ''; ?>>
                                    <span><i class="fas fa-bullseye"></i> Nets</span>
                                </label>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="equipment[]" value="sticks" <?php echo in_array('sticks', $currentEquipment) ? 'checked' : ''; ?>>
                                    <span><?php echo $stickIconSvg; ?>Extra Sticks</span>
                                </label>
                                <p class="help-text" style="width: 100%; margin-top: 8px; font-size: 12px; color: var(--text-dim);">
                                    <i class="fas fa-info-circle"></i> <a href="?page=categories&tab=equipment" style="color: var(--primary-light);">Add more equipment categories</a> in the admin settings.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Video Section -->
                    <div class="form-section-header" style="margin-top: 20px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                        <h4 style="font-size: 14px; font-weight: 700; color: var(--text-white); margin: 0;">
                            <i class="fas fa-video" style="color: var(--primary); margin-right: 8px;"></i> Video (Optional)
                        </h4>
                        <p style="font-size: 12px; color: var(--text-dim); margin: 4px 0 0 0;">Add a video to demonstrate this drill</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Video Type</label>
                        <select name="video_type" id="videoTypeSelect" class="form-input" onchange="toggleVideoFields()">
                            <option value="">No Video</option>
                            <option value="youtube" <?php echo (isset($editingDrill['video_url']) && strpos($editingDrill['video_url'] ?? '', 'youtube') !== false) ? 'selected' : ''; ?>>YouTube Embed</option>
                            <option value="upload" <?php echo (isset($editingDrill['video_upload_path']) && !empty($editingDrill['video_upload_path'])) ? 'selected' : ''; ?>>Upload Video File</option>
                            <option value="url" <?php echo (isset($editingDrill['video_url']) && !empty($editingDrill['video_url']) && strpos($editingDrill['video_url'] ?? '', 'youtube') === false) ? 'selected' : ''; ?>>External URL</option>
                        </select>
                    </div>
                    
                    <div id="youtubeFields" class="video-type-fields" style="display: none;">
                        <div class="form-group">
                            <label>YouTube Video URL or Embed Code</label>
                            <input type="text" name="youtube_url" id="youtubeUrl" class="form-input" 
                                   placeholder="https://www.youtube.com/watch?v=... or paste embed code"
                                   value="<?php echo htmlspecialchars((strpos($editingDrill['video_url'] ?? '', 'youtube') !== false) ? $editingDrill['video_url'] : ''); ?>">
                            <p class="help-text" style="font-size: 11px; color: var(--text-dim); margin-top: 4px;">
                                <i class="fas fa-info-circle"></i> Paste a YouTube watch URL (e.g., https://youtube.com/watch?v=xxx) or the full embed iframe code
                            </p>
                        </div>
                        <div id="youtubePreview" class="video-preview" style="display: none; margin-top: 12px;">
                            <label>Preview</label>
                            <div class="video-preview-container" style="background: var(--bg-main); border-radius: 8px; overflow: hidden; max-width: 560px;">
                                <iframe id="youtubeIframe" width="100%" height="315" frameborder="0" allowfullscreen></iframe>
                            </div>
                        </div>
                    </div>
                    
                    <div id="uploadFields" class="video-type-fields" style="display: none;">
                        <div class="form-group">
                            <label>Upload Video File</label>
                            <input type="file" name="video_file" id="videoFileInput" class="form-input" accept="video/mp4,video/webm,video/ogg" onchange="validateVideoFileSize(this)">
                            <p class="help-text" style="font-size: 11px; color: var(--text-dim); margin-top: 4px;">
                                <i class="fas fa-info-circle"></i> Supported formats: MP4, WebM, OGG. Max size: 100MB
                            </p>
                            <p id="videoFileSizeError" style="display: none; color: var(--error); font-size: 12px; margin-top: 4px;">
                                <i class="fas fa-exclamation-circle"></i> File is too large. Maximum size is 100MB.
                            </p>
                            <?php if (!empty($editingDrill['video_upload_path'])): ?>
                            <div class="current-video" style="margin-top: 8px; padding: 8px; background: var(--bg-main); border-radius: 6px;">
                                <i class="fas fa-file-video" style="color: var(--primary);"></i>
                                <span style="color: var(--text-dim); font-size: 12px;">Current: <?php echo htmlspecialchars(basename($editingDrill['video_upload_path'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="urlFields" class="video-type-fields" style="display: none;">
                        <div class="form-group">
                            <label>External Video URL</label>
                            <input type="url" name="video_url" id="externalVideoUrl" class="form-input" 
                                   placeholder="https://..."
                                   value="<?php echo htmlspecialchars((strpos($editingDrill['video_url'] ?? '', 'youtube') === false && !empty($editingDrill['video_url'])) ? $editingDrill['video_url'] : ''); ?>">
                            <p class="help-text" style="font-size: 11px; color: var(--text-dim); margin-top: 4px;">
                                <i class="fas fa-info-circle"></i> Link to a video hosted elsewhere (e.g., Vimeo, direct MP4 link)
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions-bar">
        <button type="button" class="btn btn-secondary" onclick="cancelDrillCreation()"><i class="fas fa-times"></i> Cancel</button>
        <div class="action-group">
            <?php if (!$isEditMode): ?>
            <button type="button" class="btn btn-secondary" onclick="saveDrillDraft()"><i class="fas fa-save"></i> Save Draft</button>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" onclick="submitDrillForm()"><i class="fas fa-check"></i> <?php echo $isEditMode ? 'Update Drill' : 'Create Drill'; ?></button>
        </div>
    </div>
</div>

<style>
/* New stacked layout - diagram on top, form below */
.drill-designer-section {
    margin-bottom: 24px;
}

.drill-form-section {
    min-width: 0;
}

/* Form grid for better organization */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

/* Designer Toolbar Styles */
.designer-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 16px;
}

.tool-group {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
    padding: 8px 12px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
}

.tool-group-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    width: 100%;
    margin-bottom: 6px;
}

.tool-btn {
    width: 36px;
    height: 36px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-white);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.tool-btn:hover {
    background: rgba(107, 70, 193, 0.2);
    border-color: var(--primary);
}

.tool-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.tool-btn .tool-label {
    font-size: 12px;
    font-weight: 700;
}

.tool-btn.danger-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
    color: #ef4444;
}

.tool-btn i {
    font-size: 14px;
}

/* Stick icon special styling - golden color for visibility */
.tool-btn[data-tool="stick"] svg {
    stroke: #D4A76A;
    display: block;
    position: relative;
    z-index: 1;
}

.tool-btn[data-tool="stick"]:hover svg,
.tool-btn[data-tool="stick"].active svg {
    stroke: #fff;
}

.fullscreen-btn {
    margin-left: auto;
}

/* Ice View Selector */
.ice-view-selector {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding: 12px 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.ice-view-selector label {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
}

.ice-view-selector .form-input-small {
    min-width: 160px;
}

.ice-rink-canvas {
    width: 100%;
    min-height: 450px;
    max-height: 600px;
    aspect-ratio: 2/1;
    background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
    border: 3px solid #0033a0;
    border-radius: 80px;
    position: relative;
    margin-bottom: 16px;
    overflow: hidden;
}

/* Canvas element inside takes over rendering */
.ice-rink-canvas canvas {
    border-radius: 77px;
}

/* Fullscreen mode */
.ice-rink-canvas.fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    height: 100vh;
    max-height: 100vh;
    min-height: 100vh;
    z-index: 9999;
    border-radius: 0;
    margin: 0;
    aspect-ratio: auto;
}

.rink-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(6, 8, 11, 0.7);
    backdrop-filter: blur(2px);
    z-index: 10;
}

.rink-overlay p {
    color: var(--text-white);
    font-size: 14px;
    text-align: center;
}

.rink-overlay i {
    color: var(--neon);
    margin-right: 8px;
}

.canvas-controls {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.canvas-help-text {
    margin-top: 12px;
    padding: 10px 15px;
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 6px;
    font-size: 12px;
    color: var(--text-dim);
}

.canvas-help-text kbd {
    display: inline-block;
    padding: 2px 6px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 3px;
    font-family: monospace;
    font-size: 11px;
    color: var(--text-white);
}

/* Color Picker Styles */
.color-picker-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.color-picker-wrapper input[type="color"] {
    width: 36px;
    height: 36px;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 4px;
    cursor: pointer;
    background: transparent;
}

.color-picker-wrapper input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 2px;
}

.color-picker-wrapper input[type="color"]::-webkit-color-swatch {
    border-radius: 2px;
    border: none;
}

.active-color-circle {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    pointer-events: none;
}

.tool-btn.color-preset {
    width: 24px;
    height: 24px;
    min-width: 24px;
    border-radius: 50%;
    border: 2px solid var(--border);
    padding: 0;
    transition: transform 0.2s, border-color 0.2s;
}

.tool-btn.color-preset:hover {
    transform: scale(1.15);
    border-color: #fff;
}

.tool-btn.color-preset.active {
    border-color: #fff;
    box-shadow: 0 0 0 2px var(--primary);
}

.equipment-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.checkbox-tag {
    display: inline-flex;
    align-items: center;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 10px 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.checkbox-tag:hover {
    border-color: var(--neon);
}

.checkbox-tag input {
    display: none;
}

.checkbox-tag input:checked + span {
    color: var(--neon);
}

.checkbox-tag span {
    font-size: 14px;
    color: var(--text-dim);
    transition: all 0.3s;
}

.checkbox-tag i {
    margin-right: 8px;
}

.form-actions-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.action-group {
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .designer-tools {
        justify-content: center;
    }
    
    .form-actions-bar {
        flex-direction: column;
    }
    
    .action-group {
        width: 100%;
        justify-content: center;
    }
}

/* Form validation error styles */
.input-error {
    border-color: #EF4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
}

.field-error {
    color: #EF4444;
    font-size: 12px;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.field-error i {
    font-size: 12px;
}
</style>

<script>
// Notification helper function
function showNotification(message, type = 'info') {
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

// Drill form submission handler
function submitDrillForm() {
    const form = document.querySelector('.drill-form');
    
    // Validate required fields before submission
    const drillName = form.querySelector('input[name="drill_name"]');
    const category = form.querySelector('select[name="category"]');
    const skillLevel = form.querySelector('select[name="skill_level"]');
    const description = form.querySelector('textarea[name="description"]');
    
    let isValid = true;
    let firstInvalidField = null;
    
    // Clear previous error states
    form.querySelectorAll('.field-error').forEach(el => el.remove());
    form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    
    // Validate drill name
    if (!drillName || !drillName.value.trim()) {
        isValid = false;
        showFieldError(drillName, 'Drill name is required');
        if (!firstInvalidField) firstInvalidField = drillName;
    }
    
    // Validate category
    if (!category || !category.value) {
        isValid = false;
        showFieldError(category, 'Category is required');
        if (!firstInvalidField) firstInvalidField = category;
    }
    
    // Validate skill level
    if (!skillLevel || !skillLevel.value) {
        isValid = false;
        showFieldError(skillLevel, 'Skill level is required');
        if (!firstInvalidField) firstInvalidField = skillLevel;
    }
    
    // Validate description
    if (!description || !description.value.trim()) {
        isValid = false;
        showFieldError(description, 'Description is required');
        if (!firstInvalidField) firstInvalidField = description;
    }
    
    if (!isValid) {
        showNotification('Please fill in all required fields before creating the drill.', 'error');
        if (firstInvalidField) {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalidField.focus();
        }
        return false;
    }
    
    // Get diagram data if drill designer is available
    if (window.drillDesigner) {
        document.getElementById('diagram_data').value = window.drillDesigner.getDiagramData();
    }
    
    form.submit();
    return true;
}

// Show field-level validation error
function showFieldError(field, message) {
    if (!field) return;
    
    field.classList.add('input-error');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
    
    // Insert error message after the field
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
}

// Save draft functionality
function saveDrillDraft() {
    const form = document.querySelector('.drill-form');
    const formData = new FormData(form);
    
    // Save to localStorage
    const draftData = {};
    for (let [key, value] of formData.entries()) {
        draftData[key] = value;
    }
    
    // Add diagram data
    if (window.drillDesigner) {
        draftData.diagram_data = window.drillDesigner.getDiagramData();
    }
    
    localStorage.setItem('drill_draft', JSON.stringify(draftData));
    showNotification('Draft saved! Your progress has been saved locally.', 'success');
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in edit mode by looking at URL params or sessionStorage
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    
    // Helper function to load diagram data into designer
    function loadDiagramIntoDesigner(diagramDataStr) {
        if (diagramDataStr && window.drillDesigner) {
            try {
                window.drillDesigner.loadDiagramData(diagramDataStr);
                return true;
            } catch (e) {
                console.log('Failed to load diagram data:', e);
                return false;
            }
        }
        return false;
    }
    
    // If in edit mode, load diagram data after designer initializes
    if (editId) {
        // Wait for drill designer to initialize
        const waitForDesigner = setInterval(function() {
            if (window.drillDesigner) {
                clearInterval(waitForDesigner);
                
                // First try to load from hidden input (PHP-rendered data)
                const diagramDataInput = document.getElementById('diagram_data');
                if (diagramDataInput && diagramDataInput.value) {
                    loadDiagramIntoDesigner(diagramDataInput.value);
                }
                
                // Also check sessionStorage for drill data passed from library
                const editDrill = sessionStorage.getItem('editDrill');
                if (editDrill) {
                    try {
                        const drillData = JSON.parse(editDrill);
                        if (drillData.diagram_data) {
                            loadDiagramIntoDesigner(drillData.diagram_data);
                        }
                        sessionStorage.removeItem('editDrill');
                    } catch (e) {
                        console.log('Failed to parse drill from sessionStorage');
                    }
                }
            }
        }, 100);
        
        // Safety timeout to stop checking after 5 seconds
        setTimeout(function() { clearInterval(waitForDesigner); }, 5000);
        
        return; // Skip draft loading when in edit mode
    }
    
    // Load draft if not in edit mode
    const draft = localStorage.getItem('drill_draft');
    if (draft) {
        const loadDraft = confirm('You have a saved draft. Would you like to load it?');
        if (loadDraft) {
            const draftData = JSON.parse(draft);
            Object.keys(draftData).forEach(key => {
                const input = document.querySelector(`[name="${key}"]`);
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = draftData[key] === input.value;
                    } else {
                        input.value = draftData[key];
                    }
                }
            });
            
            // Load diagram data
            if (draftData.diagram_data && window.drillDesigner) {
                window.drillDesigner.loadDiagramData(draftData.diagram_data);
            }
        }
    }
});

// Cancel with confirmation
function cancelDrillCreation() {
    // Check if form has any data
    const form = document.querySelector('.drill-form');
    const formData = new FormData(form);
    let hasData = false;
    
    for (let [key, value] of formData.entries()) {
        if (value && value.trim() !== '' && key !== 'diagram_data') {
            hasData = true;
            break;
        }
    }
    
    // Check if designer has objects
    if (window.drillDesigner && window.drillDesigner.objects && window.drillDesigner.objects.length > 0) {
        hasData = true;
    }
    
    if (hasData) {
        const confirmed = confirm('You have unsaved changes. Are you sure you want to leave? Your progress will be lost.');
        if (!confirmed) {
            return;
        }
    }
    
    window.location.href = '?page=drill_library';
}

// Video type toggle functionality
function toggleVideoFields() {
    const videoType = document.getElementById('videoTypeSelect').value;
    
    // Hide all video type fields
    document.querySelectorAll('.video-type-fields').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show the selected type's fields
    if (videoType === 'youtube') {
        document.getElementById('youtubeFields').style.display = 'block';
    } else if (videoType === 'upload') {
        document.getElementById('uploadFields').style.display = 'block';
    } else if (videoType === 'url') {
        document.getElementById('urlFields').style.display = 'block';
    }
}

// Parse YouTube URL and extract video ID
function parseYouTubeUrl(url) {
    if (!url) return null;
    
    // Check if it's an iframe embed code
    if (url.includes('<iframe')) {
        const srcMatch = url.match(/src="([^"]+)"/);
        if (srcMatch) {
            url = srcMatch[1];
        }
    }
    
    // Handle various YouTube URL formats
    let videoId = null;
    
    // Standard watch URL: youtube.com/watch?v=VIDEO_ID
    const watchMatch = url.match(/[?&]v=([^&]+)/);
    if (watchMatch) {
        videoId = watchMatch[1];
    }
    
    // Short URL: youtu.be/VIDEO_ID
    const shortMatch = url.match(/youtu\.be\/([^?&]+)/);
    if (shortMatch) {
        videoId = shortMatch[1];
    }
    
    // Embed URL: youtube.com/embed/VIDEO_ID
    const embedMatch = url.match(/youtube\.com\/embed\/([^?&]+)/);
    if (embedMatch) {
        videoId = embedMatch[1];
    }
    
    return videoId;
}

// Preview YouTube video
function previewYouTube() {
    const urlInput = document.getElementById('youtubeUrl');
    const previewDiv = document.getElementById('youtubePreview');
    const iframe = document.getElementById('youtubeIframe');
    
    const videoId = parseYouTubeUrl(urlInput.value);
    
    if (videoId) {
        iframe.src = 'https://www.youtube.com/embed/' + videoId;
        previewDiv.style.display = 'block';
    } else {
        previewDiv.style.display = 'none';
        iframe.src = '';
    }
}

// Validate video file size (max 100MB)
function validateVideoFileSize(input) {
    const maxSize = 100 * 1024 * 1024; // 100MB in bytes
    const errorEl = document.getElementById('videoFileSizeError');
    
    if (input.files && input.files[0]) {
        const fileSize = input.files[0].size;
        if (fileSize > maxSize) {
            errorEl.style.display = 'block';
            input.value = ''; // Clear the file input
            return false;
        } else {
            errorEl.style.display = 'none';
            return true;
        }
    }
    errorEl.style.display = 'none';
    return true;
}

// Initialize video fields on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize video type fields visibility
    toggleVideoFields();
    
    // Add YouTube URL change listener for preview
    const youtubeInput = document.getElementById('youtubeUrl');
    if (youtubeInput) {
        youtubeInput.addEventListener('change', previewYouTube);
        youtubeInput.addEventListener('blur', previewYouTube);
        // Preview if there's already a value
        if (youtubeInput.value) {
            previewYouTube();
        }
    }
});
</script>

<!-- Load Drill Designer JavaScript -->
<script src="js/drill_designer.js"></script>
