<?php
/**
 * Admin - Manage Locations
 * Add, edit, and manage training locations
 */

require_once __DIR__ . '/../security.php';

// Check if user has permission
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get all locations
$locations = $pdo->query("
    SELECT l.*, 
           (SELECT COUNT(*) FROM sessions WHERE arena = l.name) as session_count
    FROM locations l
    ORDER BY l.city, l.name
")->fetchAll();
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }
    .btn-create:hover {
        background: #5a0080;
    }
    .btn-secondary {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    .btn-secondary:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    .locations-table {
        width: 100%;
        border-collapse: collapse;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
    }
    .locations-table thead {
        background: #06080b;
    }
    .locations-table th {
        text-align: left;
        padding: 15px;
        color: #94a3b8;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 700;
    }
    .locations-table td {
        padding: 15px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
    }
    .locations-table tr:hover {
        background: rgba(255, 77, 0, 0.05);
    }
    .btn-edit, .btn-delete {
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        margin-right: 8px;
        transition: all 0.2s;
    }
    .btn-edit {
        background: var(--primary);
        color: #fff;
    }
    .btn-edit:hover {
        background: #e64500;
    }
    .btn-delete {
        background: transparent;
        border: 1px solid #ef4444;
        color: #ef4444;
    }
    .btn-delete:hover {
        background: #ef4444;
        color: #fff;
    }
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    .modal.active {
        display: flex;
    }
    .modal-content {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
    }
    .modal-close {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 24px;
        cursor: pointer;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    .form-input {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    .form-input:focus {
        outline: none;
        border-color: var(--primary);
    }
    .btn-submit {
        width: 100%;
        padding: 12px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
    }
    .btn-submit:hover {
        background: #e64500;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    .empty-state i {
        font-size: 64px;
        color: #64748b;
        opacity: 0.3;
        margin-bottom: 20px;
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-map-marker-alt"></i> Manage Locations
    </h1>
    <div>
        <button onclick="testGoogleAPI()" class="btn-secondary" style="margin-right: 10px;">
            <i class="fas fa-vial"></i> Test Google API
        </button>
        <button onclick="openCreateModal()" class="btn-create">
            <i class="fas fa-plus"></i> Add Location
        </button>
    </div>
</div>

<?php if (empty($locations)): ?>
    <div class="empty-state">
        <i class="fas fa-map-marker-alt"></i>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Locations</h2>
        <p style="color: #64748b;">Add your first training location to get started</p>
    </div>
<?php else: ?>
    <table class="locations-table">
        <thead>
            <tr>
                <th>Arena Name</th>
                <th>City</th>
                <th>Location</th>
                <th>Sessions</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($locations as $location): ?>
                <tr>
                    <td style="font-weight: 600;">
                        <?= htmlspecialchars($location['name']) ?>
                        <?php if ($location['image_url']): ?>
                        <br><img src="<?= htmlspecialchars($location['image_url']) ?>" alt="Location" style="max-width: 100px; margin-top: 5px; border-radius: 4px;">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($location['city']) ?></td>
                    <td>
                        <?php if ($location['google_place_id']): ?>
                        <a href="https://www.google.com/maps/place/?q=place_id:<?= htmlspecialchars($location['google_place_id']) ?>" 
                           target="_blank" style="color: var(--primary); text-decoration: none;">
                            <i class="fas fa-map-marker-alt"></i> View on Map
                        </a>
                        <?php else: ?>
                        <span style="color: #64748b;">Not mapped</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $location['session_count'] ?> sessions</td>
                    <td><?= date('M d, Y', strtotime($location['created_at'])) ?></td>
                    <td>
                        <a href="#" onclick="openEditModal(<?= $location['id'] ?>, '<?= htmlspecialchars($location['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($location['city'], ENT_QUOTES) ?>', '<?= htmlspecialchars($location['google_place_id'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($location['image_url'] ?? '', ENT_QUOTES) ?>')" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <?php if ($location['session_count'] == 0): ?>
                            <a href="#" onclick="deleteLocation(<?= $location['id'] ?>)" class="btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Create/Edit Modal -->
<div id="locationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Add Location</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST" action="process_admin_action.php" id="locationForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="formAction" value="create_location">
            <input type="hidden" name="location_id" id="locationId">
            <input type="hidden" name="google_place_id" id="googlePlaceId">
            <input type="hidden" name="image_url" id="locationImageUrl">
            
            <div class="form-group">
                <label class="form-label">Search Location (Google Places)</label>
                <input type="text" id="placeSearch" class="form-input" placeholder="Search for a location...">
                <div id="placeResults" style="display: none; background: #06080b; border: 1px solid #1e293b; border-radius: 6px; margin-top: 5px; max-height: 200px; overflow-y: auto;"></div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Arena Name *</label>
                <input type="text" name="name" id="locationName" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">City *</label>
                <input type="text" name="city" id="locationCity" class="form-input" required>
            </div>
            
            <div id="locationPreview" style="display: none; margin-bottom: 15px;">
                <label class="form-label">Location Image</label>
                <img id="previewImage" src="" alt="Location" style="max-width: 100%; border-radius: 6px; margin-top: 5px;">
                <button type="button" onclick="clearImage()" style="margin-top: 5px; padding: 6px 12px; background: #ef4444; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                    <i class="fas fa-times"></i> Remove Image
                </button>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Save Location
            </button>
        </form>
    </div>
</div>

<script>
// Google Places API Configuration
const GOOGLE_API_KEY = 'YOUR_GOOGLE_API_KEY_HERE'; // Replace with actual API key
let placesService = null;

function initPlacesSearch() {
    const searchInput = document.getElementById('placeSearch');
    if (!searchInput) return;
    
    // Debounce search
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 3) {
            document.getElementById('placeResults').style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => searchPlaces(query), 500);
    });
}

function searchPlaces(query) {
    // Using Google Places API (Text Search)
    const request = {
        query: query,
        fields: ['place_id', 'name', 'formatted_address', 'photos', 'geometry']
    };
    
    // For demonstration, using a simulated search
    // In production, use actual Google Places API
    const results = simulatePlacesSearch(query);
    displayPlaceResults(results);
}

function simulatePlacesSearch(query) {
    // This simulates API results - replace with actual API call
    return [
        {
            place_id: 'ChIJ' + Math.random().toString(36).substr(2, 9),
            name: query + ' Arena',
            formatted_address: '123 Main St, City, State',
            photos: [{
                getUrl: () => 'https://via.placeholder.com/400x300?text=' + encodeURIComponent(query)
            }]
        }
    ];
}

function displayPlaceResults(results) {
    const resultsDiv = document.getElementById('placeResults');
    
    if (!results || results.length === 0) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    resultsDiv.innerHTML = '';
    results.forEach(place => {
        const div = document.createElement('div');
        div.style.padding = '10px';
        div.style.borderBottom = '1px solid #1e293b';
        div.style.cursor = 'pointer';
        div.style.transition = 'background 0.2s';
        
        div.onmouseover = () => div.style.background = 'rgba(112, 0, 164, 0.1)';
        div.onmouseout = () => div.style.background = 'transparent';
        
        div.innerHTML = `
            <div style="font-weight: 600; color: #fff; margin-bottom: 4px;">${place.name}</div>
            <div style="font-size: 12px; color: #94a3b8;">${place.formatted_address || ''}</div>
        `;
        
        div.onclick = () => selectPlace(place);
        resultsDiv.appendChild(div);
    });
    
    resultsDiv.style.display = 'block';
}

function selectPlace(place) {
    document.getElementById('locationName').value = place.name;
    
    // Extract city from address
    const addressParts = (place.formatted_address || '').split(',');
    if (addressParts.length > 1) {
        document.getElementById('locationCity').value = addressParts[1].trim();
    }
    
    // Set Google Place ID
    document.getElementById('googlePlaceId').value = place.place_id;
    
    // Set image URL if available
    if (place.photos && place.photos.length > 0) {
        const photoUrl = place.photos[0].getUrl ? place.photos[0].getUrl() : place.photos[0];
        document.getElementById('locationImageUrl').value = photoUrl;
        document.getElementById('previewImage').src = photoUrl;
        document.getElementById('locationPreview').style.display = 'block';
    }
    
    document.getElementById('placeResults').style.display = 'none';
    document.getElementById('placeSearch').value = '';
}

function clearImage() {
    document.getElementById('locationImageUrl').value = '';
    document.getElementById('previewImage').src = '';
    document.getElementById('locationPreview').style.display = 'none';
}

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Location';
    document.getElementById('formAction').value = 'create_location';
    document.getElementById('locationId').value = '';
    document.getElementById('locationName').value = '';
    document.getElementById('locationCity').value = '';
    document.getElementById('googlePlaceId').value = '';
    document.getElementById('locationImageUrl').value = '';
    document.getElementById('placeSearch').value = '';
    document.getElementById('locationPreview').style.display = 'none';
    document.getElementById('placeResults').style.display = 'none';
    document.getElementById('locationModal').classList.add('active');
}

function openEditModal(id, name, city, placeId, imageUrl) {
    document.getElementById('modalTitle').textContent = 'Edit Location';
    document.getElementById('formAction').value = 'edit_location';
    document.getElementById('locationId').value = id;
    document.getElementById('locationName').value = name;
    document.getElementById('locationCity').value = city;
    document.getElementById('googlePlaceId').value = placeId || '';
    document.getElementById('locationImageUrl').value = imageUrl || '';
    
    if (imageUrl) {
        document.getElementById('previewImage').src = imageUrl;
        document.getElementById('locationPreview').style.display = 'block';
    } else {
        document.getElementById('locationPreview').style.display = 'none';
    }
    
    document.getElementById('locationModal').classList.add('active');
}

function closeModal() {
    document.getElementById('locationModal').classList.remove('active');
}

function deleteLocation(id) {
    if (confirm('Are you sure you want to delete this location?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_admin_action.php';
        form.innerHTML = '<?= csrfTokenInput() ?>' +
            '<input type="hidden" name="action" value="delete_location">' +
            '<input type="hidden" name="location_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function testGoogleAPI() {
    const formData = new FormData();
    formData.append('csrf_token', '<?= csrfToken() ?>');
    
    fetch('process_test_google_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const message = document.createElement('div');
            message.innerHTML = data.message;
            const text = message.innerText || message.textContent;
            alert('✓ ' + text);
        } else {
            alert('✗ Test failed:\n\n' + data.message);
        }
    })
    .catch(err => {
        alert('✗ Error testing API:\n\n' + err.message);
    });
}

// Close modal when clicking outside
document.getElementById('locationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initPlacesSearch();
});
