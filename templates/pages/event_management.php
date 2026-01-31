<?php
/**
 * Event Management Page
 * Create, edit, and delete events
 * Only accessible to users with 'vorstand' or 'ressort' role
 */

// Initialize Event class
require_once BASE_PATH . '/src/Event.php';
require_once BASE_PATH . '/src/NewsService.php';
$newsService = new NewsService($pdo);
$event = new Event($pdo, $newsService);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Unbekannte Aktion'];
    
    // Verify CSRF token for all actions
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$auth->verifyCsrfToken($csrfToken)) {
        $response = ['success' => false, 'message' => 'Ungültiges CSRF-Token. Bitte laden Sie die Seite neu.'];
        echo json_encode($response);
        exit;
    }
    
    // Check permissions
    if (!$auth->can('edit_events')) {
        $response = ['success' => false, 'message' => 'Keine Berechtigung'];
        echo json_encode($response);
        exit;
    }
    
    $userId = $auth->getUserId();
    $userRole = $auth->getUserRole();
    
    // Handle get action - fetch event for editing
    if ($action === 'get') {
        $eventId = (int)($_POST['id'] ?? 0);
        
        if ($eventId <= 0) {
            $response = ['success' => false, 'message' => 'Ungültige Event-ID'];
            echo json_encode($response);
            exit;
        }
        
        $eventData = $event->getById($eventId);
        
        if (!$eventData) {
            $response = ['success' => false, 'message' => 'Event nicht gefunden'];
            echo json_encode($response);
            exit;
        }
        
        $response = ['success' => true, 'event' => $eventData];
        echo json_encode($response);
        exit;
    }
    
    // Handle save action - create or update event
    if ($action === 'save') {
        $eventId = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
        
        // Check if notification should be sent
        $sendNotification = isset($_POST['send_notification']) && $_POST['send_notification'] === '1';
        
        // Prepare data
        $data = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'event_date' => $_POST['event_date'] ?? '',
            'location' => $_POST['location'] ?? null,
            'max_participants' => !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null,
            'created_by' => $userId,
            'user_role' => $userRole
        ];
        
        if ($eventId) {
            $data['id'] = $eventId;
        }
        
        // Handle image upload if provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // For new events without ID, we need to create the event first to get an ID
            if (!$eventId) {
                // Create event first without image to get ID
                $tempData = $data;
                $tempData['image_path'] = null;
                $eventId = $event->save($tempData, false);
                if (!$eventId) {
                    $response = ['success' => false, 'message' => 'Fehler beim Erstellen des Events'];
                    echo json_encode($response);
                    exit;
                }
                $data['id'] = $eventId;
            }
            
            $imagePath = $event->handleImageUpload($_FILES['image'], $eventId);
            if ($imagePath) {
                // Delete old image if updating
                if ($eventId) {
                    $existingEvent = $event->getById($eventId);
                    if ($existingEvent && !empty($existingEvent['image_path'])) {
                        $fullPath = BASE_PATH . '/' . ltrim($existingEvent['image_path'], '/');
                        if (file_exists($fullPath)) {
                            @unlink($fullPath);
                        }
                    }
                }
                $data['image_path'] = $imagePath;
                
                // Update the event with the image path and send notification if requested
                $resultId = $event->save($data, $sendNotification);
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Hochladen des Bildes'];
                echo json_encode($response);
                exit;
            }
        } else {
            // Keep existing image path for updates
            if ($eventId) {
                $existingEvent = $event->getById($eventId);
                if ($existingEvent) {
                    $data['image_path'] = $existingEvent['image_path'];
                }
            }
            
            // Save event without new image and send notification if requested
            $resultId = $event->save($data, $sendNotification);
        }
        
        if ($resultId) {
            $response = [
                'success' => true, 
                'message' => $eventId ? 'Event erfolgreich aktualisiert' : 'Event erfolgreich erstellt',
                'id' => $resultId
            ];
        } else {
            $response = ['success' => false, 'message' => 'Fehler beim Speichern des Events'];
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Handle delete action
    if ($action === 'delete') {
        $eventId = (int)($_POST['id'] ?? 0);
        
        if ($eventId <= 0) {
            $response = ['success' => false, 'message' => 'Ungültige Event-ID'];
            echo json_encode($response);
            exit;
        }
        
        $result = $event->delete($eventId, $userRole, $userId);
        
        if ($result) {
            $response = ['success' => true, 'message' => 'Event erfolgreich gelöscht'];
        } else {
            $response = ['success' => false, 'message' => 'Fehler beim Löschen des Events'];
        }
        
        echo json_encode($response);
        exit;
    }
    
    echo json_encode($response);
    exit;
}

// Get all events for listing
$allEvents = $event->getUpcoming();
?>

<!-- Quill.js CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet" crossorigin="anonymous">

<div class="container container-xl my-5">
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="ibc-heading">
                <span class="word-wrapper"><span class="word">Event</span></span>
                <span class="word-wrapper"><span class="word text-gradient">Verwaltung</span></span>
            </h1>
            <p class="ibc-lead">
                Verwalten und bearbeiten Sie Events für das Intranet
            </p>
        </div>
    </div>

    <?php if (!$auth->can('edit_events')): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Sie haben keine Berechtigung, diese Seite zu besuchen.
        </div>
    <?php else: ?>
        <!-- Create New Event Button -->
        <div class="row mb-4">
            <div class="col-12">
                <button type="button" class="btn btn-primary btn-lg" id="createEventBtn">
                    <i class="fas fa-plus me-2"></i>Neues Event erstellen
                </button>
            </div>
        </div>

        <!-- Events List -->
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3">Alle Events</h3>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="eventsGrid">
            <?php if (empty($allEvents)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Noch keine Events vorhanden. Erstellen Sie das erste Event!
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($allEvents as $evt): ?>
                <div class="col" data-event-id="<?php echo htmlspecialchars($evt['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="card h-100 glass-card rounded-4">
                        <?php if (!empty($evt['image_path'])): ?>
                            <img 
                                src="/<?php echo htmlspecialchars($evt['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                class="card-img-top rounded-top-4" 
                                alt="<?php echo htmlspecialchars($evt['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                style="height: 200px; object-fit: cover;"
                            >
                        <?php else: ?>
                            <div class="bg-gradient-animated rounded-top-4" style="height: 200px;"></div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php echo htmlspecialchars($evt['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            
                            <p class="card-text text-muted">
                                <?php 
                                $stripped = strip_tags($evt['description']);
                                echo htmlspecialchars(substr($stripped, 0, 100) . (strlen($stripped) > 100 ? '...' : ''), ENT_QUOTES, 'UTF-8'); 
                                ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($evt['event_date'])); ?>
                                </small>
                            </div>
                            
                            <?php if (!empty($evt['location'])): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($evt['location'], ENT_QUOTES, 'UTF-8'); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-transparent border-0 d-flex gap-2 p-3">
                            <button type="button" class="btn btn-sm btn-outline-primary flex-fill edit-event-btn" data-id="<?php echo htmlspecialchars($evt['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-edit me-1"></i>Bearbeiten
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-event-btn" data-id="<?php echo htmlspecialchars($evt['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-trash me-1"></i>Löschen
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Event Editor Modal -->
<div class="modal fade" id="eventEditorModal" tabindex="-1" aria-labelledby="eventEditorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="eventEditorModalLabel">Event erstellen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form id="eventEditorForm" class="needs-validation" novalidate enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="eventId" name="id">
                    
                    <!-- Title -->
                    <div class="mb-3">
                        <label for="eventTitle" class="form-label fw-bold">Titel *</label>
                        <input type="text" class="form-control" id="eventTitle" name="title" required>
                        <div class="invalid-feedback">
                            Bitte geben Sie einen Titel ein.
                        </div>
                    </div>
                    
                    <!-- Event Date -->
                    <div class="mb-3">
                        <label for="eventDate" class="form-label fw-bold">Datum und Uhrzeit *</label>
                        <input type="datetime-local" class="form-control" id="eventDate" name="event_date" required>
                        <div class="invalid-feedback">
                            Bitte geben Sie ein Datum ein.
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="mb-3">
                        <label for="eventLocation" class="form-label fw-bold">Ort</label>
                        <input type="text" class="form-control" id="eventLocation" name="location" placeholder="z.B. Vereinsheim, Online">
                    </div>
                    
                    <!-- Max Participants -->
                    <div class="mb-3">
                        <label for="eventMaxParticipants" class="form-label fw-bold">Maximale Teilnehmerzahl</label>
                        <input type="number" class="form-control" id="eventMaxParticipants" name="max_participants" min="1" placeholder="Leer lassen für unbegrenzt">
                    </div>
                    
                    <!-- Description with Quill Editor -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Beschreibung *</label>
                        <div id="quillEditor" style="height: 300px; background: white; border-radius: 0.375rem;"></div>
                        <input type="hidden" id="eventDescription" name="description" required>
                        <div class="invalid-feedback" id="descriptionFeedback" style="display: none;">
                            Bitte geben Sie eine Beschreibung ein.
                        </div>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="mb-3">
                        <label for="eventImage" class="form-label fw-bold">Bild</label>
                        <input type="file" class="form-control" id="eventImage" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="form-text text-muted">
                            JPG, PNG, GIF oder WebP. Max. 5MB. Wird automatisch auf 1200px Breite optimiert und als WebP gespeichert.
                        </small>
                        <div id="imagePreview" class="mt-2" style="display: none;">
                            <img src="" alt="Preview" style="max-width: 200px; border-radius: 0.5rem;">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <!-- Email Notification Checkbox on the left -->
                    <div class="form-check">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            id="sendNotification" 
                            name="send_notification"
                            value="1"
                        >
                        <label class="form-check-label" for="sendNotification">
                            <i class="fas fa-envelope me-1"></i>Mitglieder über dieses Update/Event per E-Mail informieren
                        </label>
                    </div>
                    
                    <!-- Action buttons on the right -->
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary" id="eventSubmitBtn">
                            <span id="submitBtnText">Speichern</span>
                            <span id="submitSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quill.js JavaScript -->
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js" crossorigin="anonymous"></script>

<script>
// Initialize Quill editor
let quill;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Quill only if the editor div exists
    const editorElement = document.getElementById('quillEditor');
    if (editorElement) {
        quill = new Quill('#quillEditor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'color': [] }, { 'background': [] }],
                    ['link'],
                    ['clean']
                ]
            },
            placeholder: 'Schreiben Sie hier die Beschreibung des Events...'
        });
    }
    
    // Create new event button
    const createEventBtn = document.getElementById('createEventBtn');
    if (createEventBtn) {
        createEventBtn.addEventListener('click', function() {
            openEventEditorModal();
        });
    }
    
    // Edit buttons
    document.querySelectorAll('.edit-event-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const eventId = this.getAttribute('data-id');
            openEventEditorModal(eventId);
        });
    });
    
    // Delete buttons
    document.querySelectorAll('.delete-event-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const eventId = this.getAttribute('data-id');
            deleteEvent(eventId);
        });
    });
    
    // Form submission
    const eventForm = document.getElementById('eventEditorForm');
    if (eventForm) {
        eventForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveEvent();
        });
    }
    
    // Image preview
    const imageInput = document.getElementById('eventImage');
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    const img = preview.querySelector('img');
                    img.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

function openEventEditorModal(eventId = null) {
    const modal = new bootstrap.Modal(document.getElementById('eventEditorModal'));
    const form = document.getElementById('eventEditorForm');
    const modalTitle = document.getElementById('eventEditorModalLabel');
    
    // Reset form
    form.reset();
    form.classList.remove('was-validated');
    document.getElementById('imagePreview').style.display = 'none';
    
    if (eventId) {
        // Edit mode
        modalTitle.textContent = 'Event bearbeiten';
        document.getElementById('submitBtnText').textContent = 'Aktualisieren';
        
        // Fetch event data
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('id', eventId);
        formData.append('csrf_token', '<?php echo $auth->getCsrfToken(); ?>');
        
        fetch('index.php?page=event_management', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const evt = data.event;
                document.getElementById('eventId').value = evt.id;
                document.getElementById('eventTitle').value = evt.title;
                document.getElementById('eventLocation').value = evt.location || '';
                document.getElementById('eventMaxParticipants').value = evt.max_participants || '';
                
                // Format datetime for input field
                if (evt.event_date) {
                    const date = new Date(evt.event_date);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    document.getElementById('eventDate').value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
                
                // Explicitly reset notification checkbox for edit mode
                document.getElementById('sendNotification').checked = false;
                
                // Set Quill content
                if (quill) {
                    quill.root.innerHTML = evt.description;
                }
                
                // Show existing image if available
                if (evt.image_path) {
                    const preview = document.getElementById('imagePreview');
                    const img = preview.querySelector('img');
                    img.src = '/' + evt.image_path;
                    preview.style.display = 'block';
                }
            } else {
                showToast(data.message || 'Fehler beim Laden des Events', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Fehler beim Laden des Events', 'danger');
        });
    } else {
        // Create mode
        modalTitle.textContent = 'Event erstellen';
        document.getElementById('submitBtnText').textContent = 'Erstellen';
        document.getElementById('eventId').value = '';
        
        // Clear Quill content
        if (quill) {
            quill.root.innerHTML = '';
        }
    }
    
    modal.show();
}

function saveEvent() {
    const form = document.getElementById('eventEditorForm');
    
    // Get Quill content
    const content = quill.root.innerHTML;
    document.getElementById('eventDescription').value = content;
    
    // Validate content using Quill's text method for more reliable empty check
    const textContent = quill.getText().trim();
    if (textContent.length === 0) {
        document.getElementById('descriptionFeedback').style.display = 'block';
        return;
    } else {
        document.getElementById('descriptionFeedback').style.display = 'none';
    }
    
    // Validate form
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('eventSubmitBtn');
    const submitBtnText = document.getElementById('submitBtnText');
    const submitSpinner = document.getElementById('submitSpinner');
    
    submitBtn.disabled = true;
    submitSpinner.style.display = 'inline-block';
    
    // Prepare form data
    const formData = new FormData(form);
    formData.append('action', 'save');
    formData.append('csrf_token', '<?php echo $auth->getCsrfToken(); ?>');
    
    fetch('index.php?page=event_management', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitSpinner.style.display = 'none';
        
        if (data.success) {
            showToast(data.message, 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('eventEditorModal'));
            modal.hide();
            
            // Reload page to show updated list
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Fehler beim Speichern', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        submitBtn.disabled = false;
        submitSpinner.style.display = 'none';
        showToast('Fehler beim Speichern', 'danger');
    });
}

function deleteEvent(eventId) {
    if (!confirm('Möchten Sie dieses Event wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', eventId);
    formData.append('csrf_token', '<?php echo $auth->getCsrfToken(); ?>');
    
    fetch('index.php?page=event_management', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            
            // Remove card from DOM
            const card = document.querySelector(`[data-event-id="${eventId}"]`);
            if (card) {
                card.remove();
            }
            
            // Check if grid is empty
            const grid = document.getElementById('eventsGrid');
            if (grid.children.length === 0) {
                grid.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Noch keine Events vorhanden. Erstellen Sie das erste Event!
                        </div>
                    </div>
                `;
            }
        } else {
            showToast(data.message || 'Fehler beim Löschen', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Fehler beim Löschen', 'danger');
    });
}

// Toast notification function (fallback if global function doesn't exist)
function showToast(message, type = 'info') {
    // Check if global toast function exists
    if (typeof window.globalShowToast === 'function') {
        window.globalShowToast(message, type);
        return;
    }
    
    // Fallback to alert if no toast system exists
    alert(message);
}

// Store reference to global toast if it exists
if (typeof window.showToast === 'function') {
    window.globalShowToast = window.showToast;
}
</script>

<style>
.glass-modal {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.ql-toolbar {
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
}

.ql-container {
    border-bottom-left-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}

.modal-footer {
    flex-wrap: nowrap;
}

.modal-footer .form-check {
    flex: 1;
    text-align: left;
}
</style>
