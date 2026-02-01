<?php
/**
 * Inventory Configuration Management Page
 * Only accessible for roles with full access (admin, vorstand, 1V, 2V, 3V)
 * 
 * Allows managing locations and categories for the inventory system
 */

// Check if user has permission to access this page
if (!$auth->hasFullAccess()) {
    header('Location: index.php?page=home');
    exit;
}

// Initialize Inventory class
require_once BASE_PATH . '/src/Inventory.php';
require_once BASE_PATH . '/src/SystemLogger.php';
$systemLogger = new SystemLogger($pdo);
$inventory = new Inventory($pdo, $systemLogger);

// Get all locations and categories
$locations = $inventory->getAllLocations();
$categories = $inventory->getAllCategories();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventar-Konfiguration - IBC-Intra</title>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h2 mb-0" style="color: var(--ibc-blue);">
                            <i class="fas fa-cog me-2"></i>Inventar-Konfiguration
                        </h1>
                        <a href="index.php?page=inventory" class="btn btn-outline-ibc">
                            <i class="fas fa-arrow-left me-2"></i>Zurück zum Inventar
                        </a>
                    </div>
                    
                    <p class="text-muted mb-4">
                        Verwalten Sie Standorte und Kategorien für das Inventarsystem. 
                        Standorte und Kategorien können nicht gelöscht werden, wenn sie noch in Verwendung sind.
                    </p>

                    <!-- Locations Section -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header" style="background-color: var(--ibc-blue); color: white;">
                                    <h3 class="h4 mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i>Standorte verwalten
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <!-- Add Location Form -->
                                    <div class="mb-4 p-3 bg-light rounded">
                                        <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Neuen Standort hinzufügen</h5>
                                        <form id="addLocationForm" class="row g-3">
                                            <div class="col-md-8">
                                                <input 
                                                    type="text" 
                                                    class="form-control" 
                                                    id="newLocationName" 
                                                    name="location_name"
                                                    placeholder="z.B. Lager 2, Büro Nord, etc."
                                                    required
                                                >
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" class="btn btn-ibc w-100">
                                                    <i class="fas fa-plus me-2"></i>Hinzufügen
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Locations List -->
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th style="color: var(--ibc-blue);">Name</th>
                                                    <th style="color: var(--ibc-blue);">Status</th>
                                                    <th style="color: var(--ibc-blue);">Erstellt am</th>
                                                    <th style="color: var(--ibc-blue);">Aktion</th>
                                                </tr>
                                            </thead>
                                            <tbody id="locationsTableBody">
                                                <?php foreach ($locations as $location): ?>
                                                    <tr data-location-id="<?php echo $location['id']; ?>">
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($location['is_active']): ?>
                                                                <span class="badge bg-success">
                                                                    <i class="fas fa-check-circle me-1"></i>Aktiv
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">
                                                                    <i class="fas fa-times-circle me-1"></i>Inaktiv
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('d.m.Y', strtotime($location['created_at'])); ?>
                                                        </td>
                                                        <td>
                                                            <button 
                                                                class="btn btn-sm btn-outline-danger delete-location-btn" 
                                                                data-location-id="<?php echo $location['id']; ?>"
                                                                data-location-name="<?php echo htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            >
                                                                <i class="fas fa-trash me-1"></i>Löschen
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categories Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header" style="background-color: var(--ibc-green); color: white;">
                                    <h3 class="h4 mb-0">
                                        <i class="fas fa-tags me-2"></i>Kategorien verwalten
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <!-- Add Category Form -->
                                    <div class="mb-4 p-3 bg-light rounded">
                                        <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Neue Kategorie hinzufügen</h5>
                                        <form id="addCategoryForm" class="row g-3">
                                            <div class="col-md-4">
                                                <label for="newCategoryKeyName" class="form-label">Schlüsselname</label>
                                                <input 
                                                    type="text" 
                                                    class="form-control" 
                                                    id="newCategoryKeyName" 
                                                    name="key_name"
                                                    placeholder="z.B. werkzeuge"
                                                    pattern="[a-z_]+"
                                                    title="Nur Kleinbuchstaben und Unterstriche erlaubt"
                                                    required
                                                >
                                                <small class="text-muted">Nur Kleinbuchstaben und Unterstriche</small>
                                            </div>
                                            <div class="col-md-5">
                                                <label for="newCategoryDisplayName" class="form-label">Anzeigename</label>
                                                <input 
                                                    type="text" 
                                                    class="form-control" 
                                                    id="newCategoryDisplayName" 
                                                    name="display_name"
                                                    placeholder="z.B. Werkzeuge"
                                                    required
                                                >
                                            </div>
                                            <div class="col-md-3 d-flex align-items-end">
                                                <button type="submit" class="btn btn-ibc w-100">
                                                    <i class="fas fa-plus me-2"></i>Hinzufügen
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Categories List -->
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th style="color: var(--ibc-green);">Schlüsselname</th>
                                                    <th style="color: var(--ibc-green);">Anzeigename</th>
                                                    <th style="color: var(--ibc-green);">Status</th>
                                                    <th style="color: var(--ibc-green);">Erstellt am</th>
                                                    <th style="color: var(--ibc-green);">Aktion</th>
                                                </tr>
                                            </thead>
                                            <tbody id="categoriesTableBody">
                                                <?php foreach ($categories as $category): ?>
                                                    <tr data-category-id="<?php echo $category['id']; ?>">
                                                        <td>
                                                            <code><?php echo htmlspecialchars($category['key_name'], ENT_QUOTES, 'UTF-8'); ?></code>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($category['display_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($category['is_active']): ?>
                                                                <span class="badge bg-success">
                                                                    <i class="fas fa-check-circle me-1"></i>Aktiv
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">
                                                                    <i class="fas fa-times-circle me-1"></i>Inaktiv
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('d.m.Y', strtotime($category['created_at'])); ?>
                                                        </td>
                                                        <td>
                                                            <button 
                                                                class="btn btn-sm btn-outline-danger delete-category-btn" 
                                                                data-category-id="<?php echo $category['id']; ?>"
                                                                data-category-name="<?php echo htmlspecialchars($category['display_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            >
                                                                <i class="fas fa-trash me-1"></i>Löschen
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = '<?php echo $auth->generateCsrfToken(); ?>';

        // Add Location
        document.getElementById('addLocationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const locationName = document.getElementById('newLocationName').value.trim();
            
            if (!locationName) {
                alert('Bitte geben Sie einen Standort-Namen ein');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'add_location');
                formData.append('location_name', locationName);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('index.php?page=inventory', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Validate that we have a valid location ID
                    if (!result.location_id) {
                        showAlert('warning', 'Standort hinzugefügt, aber ID fehlt. Bitte Seite neu laden.');
                        return;
                    }
                    
                    // Add new row to table with proper ID
                    const tbody = document.getElementById('locationsTableBody');
                    const newRow = document.createElement('tr');
                    newRow.setAttribute('data-location-id', result.location_id);
                    newRow.innerHTML = `
                        <td><strong>${escapeHtml(locationName)}</strong></td>
                        <td>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i>Aktiv
                            </span>
                        </td>
                        <td>${new Date().toLocaleDateString('de-DE')}</td>
                        <td>
                            <button 
                                class="btn btn-sm btn-outline-danger delete-location-btn" 
                                data-location-id="${result.location_id}"
                                data-location-name="${escapeHtml(locationName)}"
                            >
                                <i class="fas fa-trash me-1"></i>Löschen
                            </button>
                        </td>
                    `;
                    tbody.appendChild(newRow);
                    
                    // Reset form
                    document.getElementById('newLocationName').value = '';
                    
                    // Show success message
                    showAlert('success', result.message);
                } else {
                    showAlert('danger', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Ein Fehler ist aufgetreten');
            }
        });

        // Add Category
        document.getElementById('addCategoryForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const keyName = document.getElementById('newCategoryKeyName').value.trim();
            const displayName = document.getElementById('newCategoryDisplayName').value.trim();
            
            if (!keyName || !displayName) {
                alert('Bitte füllen Sie alle Felder aus');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'add_category');
                formData.append('key_name', keyName);
                formData.append('display_name', displayName);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('index.php?page=inventory', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Validate that we have a valid category ID
                    if (!result.category || !result.category.id) {
                        showAlert('warning', 'Kategorie hinzugefügt, aber ID fehlt. Bitte Seite neu laden.');
                        return;
                    }
                    
                    // Add new row to table with proper ID
                    const tbody = document.getElementById('categoriesTableBody');
                    const newRow = document.createElement('tr');
                    newRow.setAttribute('data-category-id', result.category.id);
                    newRow.innerHTML = `
                        <td><code>${escapeHtml(keyName)}</code></td>
                        <td><strong>${escapeHtml(displayName)}</strong></td>
                        <td>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i>Aktiv
                            </span>
                        </td>
                        <td>${new Date().toLocaleDateString('de-DE')}</td>
                        <td>
                            <button 
                                class="btn btn-sm btn-outline-danger delete-category-btn" 
                                data-category-id="${result.category.id}"
                                data-category-name="${escapeHtml(displayName)}"
                            >
                                <i class="fas fa-trash me-1"></i>Löschen
                            </button>
                        </td>
                    `;
                    tbody.appendChild(newRow);
                    
                    // Reset form
                    document.getElementById('newCategoryKeyName').value = '';
                    document.getElementById('newCategoryDisplayName').value = '';
                    
                    // Show success message
                    showAlert('success', result.message);
                } else {
                    showAlert('danger', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Ein Fehler ist aufgetreten');
            }
        });

        // Delete Location (Event Delegation)
        document.getElementById('locationsTableBody').addEventListener('click', async function(e) {
            const btn = e.target.closest('.delete-location-btn');
            if (!btn) return;

            const locationId = btn.getAttribute('data-location-id');
            const locationName = btn.getAttribute('data-location-name');

            if (!confirm(`Möchten Sie den Standort "${locationName}" wirklich löschen?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_location');
                formData.append('location_id', locationId);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('index.php?page=inventory', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Remove row from table
                    const row = btn.closest('tr');
                    row.remove();
                    
                    showAlert('success', result.message);
                } else {
                    showAlert('danger', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Ein Fehler ist aufgetreten');
            }
        });

        // Delete Category (Event Delegation)
        document.getElementById('categoriesTableBody').addEventListener('click', async function(e) {
            const btn = e.target.closest('.delete-category-btn');
            if (!btn) return;

            const categoryId = btn.getAttribute('data-category-id');
            const categoryName = btn.getAttribute('data-category-name');

            if (!confirm(`Möchten Sie die Kategorie "${categoryName}" wirklich löschen?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_category');
                formData.append('category_id', categoryId);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('index.php?page=inventory', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Remove row from table
                    const row = btn.closest('tr');
                    row.remove();
                    
                    showAlert('success', result.message);
                } else {
                    showAlert('danger', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Ein Fehler ist aufgetreten');
            }
        });

        // Helper function to escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Helper function to show alerts
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    });
    </script>
</body>
</html>
