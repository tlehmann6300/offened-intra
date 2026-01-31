<?php
/**
 * News Editor Page
 * Create, edit, and delete news articles
 * Only accessible to users with 'vorstand' or 'ressort' role
 */

// Initialize News class
require_once BASE_PATH . '/src/News.php';
require_once BASE_PATH . '/src/NewsService.php';
require_once BASE_PATH . '/src/SystemLogger.php';
$newsService = new NewsService($pdo);
$systemLogger = new SystemLogger($pdo);
$news = new News($pdo, $newsService, $systemLogger);

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
    if (!$auth->can('edit_news')) {
        $response = ['success' => false, 'message' => 'Keine Berechtigung'];
        echo json_encode($response);
        exit;
    }
    
    $userId = $auth->getUserId();
    $userRole = $auth->getUserRole();
    
    // Handle get action - fetch news article for editing
    if ($action === 'get') {
        $newsId = (int)($_POST['id'] ?? 0);
        
        if ($newsId <= 0) {
            $response = ['success' => false, 'message' => 'Ungültige News-ID'];
            echo json_encode($response);
            exit;
        }
        
        $article = $news->getById($newsId);
        
        if (!$article) {
            $response = ['success' => false, 'message' => 'Artikel nicht gefunden'];
            echo json_encode($response);
            exit;
        }
        
        $response = ['success' => true, 'article' => $article];
        echo json_encode($response);
        exit;
    }
    
    // Handle save action - create or update news article
    if ($action === 'save') {
        $newsId = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
        
        // Check if notification should be sent
        $sendNotification = isset($_POST['send_notification']) && $_POST['send_notification'] === '1';
        
        // Prepare data
        $data = [
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'category' => $_POST['category'] ?? null,
            'cta_link' => $_POST['cta_link'] ?? null,
            'cta_label' => $_POST['cta_label'] ?? null,
            'author_id' => $userId,
            'user_role' => $userRole
        ];
        
        if ($newsId) {
            $data['id'] = $newsId;
        }
        
        // Handle image upload if provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // For new articles without ID, we need to create the article first to get an ID
            if (!$newsId) {
                // Create article first without image to get ID
                // Note: Notification will be sent later with image if checkbox is checked
                $tempData = $data;
                $tempData['image_path'] = null;
                $newsId = $news->save($tempData, false);
                if (!$newsId) {
                    $response = ['success' => false, 'message' => 'Fehler beim Erstellen des Artikels'];
                    echo json_encode($response);
                    exit;
                }
                $data['id'] = $newsId;
            }
            
            $imagePath = $news->handleImageUpload($_FILES['image'], $newsId);
            if ($imagePath) {
                // Delete old image if updating
                if ($newsId) {
                    $existingArticle = $news->getById($newsId);
                    if ($existingArticle && !empty($existingArticle['image_path'])) {
                        $fullPath = BASE_PATH . '/' . ltrim($existingArticle['image_path'], '/');
                        if (file_exists($fullPath)) {
                            @unlink($fullPath);
                        }
                    }
                }
                $data['image_path'] = $imagePath;
                
                // Update the article with the image path and send notification if requested
                $resultId = $news->save($data, $sendNotification);
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Hochladen des Bildes'];
                echo json_encode($response);
                exit;
            }
        } else {
            // Keep existing image path for updates
            if ($newsId) {
                $existingArticle = $news->getById($newsId);
                if ($existingArticle) {
                    $data['image_path'] = $existingArticle['image_path'];
                }
            }
            
            // Save article without new image and send notification if requested
            $resultId = $news->save($data, $sendNotification);
        }
        
        if ($resultId) {
            $response = [
                'success' => true, 
                'message' => $newsId ? 'Artikel erfolgreich aktualisiert' : 'Artikel erfolgreich erstellt',
                'id' => $resultId
            ];
        } else {
            $response = ['success' => false, 'message' => 'Fehler beim Speichern des Artikels'];
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Handle delete action
    if ($action === 'delete') {
        $newsId = (int)($_POST['id'] ?? 0);
        
        if ($newsId <= 0) {
            $response = ['success' => false, 'message' => 'Ungültige News-ID'];
            echo json_encode($response);
            exit;
        }
        
        $result = $news->delete($newsId, $userRole, $userId);
        
        if ($result) {
            $response = ['success' => true, 'message' => 'Artikel erfolgreich gelöscht'];
        } else {
            $response = ['success' => false, 'message' => 'Fehler beim Löschen des Artikels'];
        }
        
        echo json_encode($response);
        exit;
    }
    
    echo json_encode($response);
    exit;
}

// Get all news articles for listing
$allNews = $news->getLatest(100, 0);
?>

<!-- Quill.js CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet" crossorigin="anonymous">

<div class="container container-xl my-5">
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="ibc-heading">
                <span class="word-wrapper"><span class="word">News</span></span>
                <span class="word-wrapper"><span class="word text-gradient">Editor</span></span>
            </h1>
            <p class="ibc-lead">
                Verwalten und bearbeiten Sie News-Beiträge für das Intranet
            </p>
        </div>
    </div>

    <?php if (!$auth->can('edit_news')): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Sie haben keine Berechtigung, diese Seite zu besuchen.
        </div>
    <?php else: ?>
        <!-- Create New Article Button -->
        <div class="row mb-4">
            <div class="col-12">
                <button type="button" class="btn btn-primary btn-lg" id="createNewsBtn">
                    <i class="fas fa-plus me-2"></i>Neuen Artikel erstellen
                </button>
            </div>
        </div>

        <!-- News Articles List -->
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3">Alle Artikel</h3>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="newsArticlesGrid">
            <?php if (empty($allNews)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Noch keine Artikel vorhanden. Erstellen Sie den ersten Artikel!
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($allNews as $article): ?>
                <div class="col" data-news-id="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="card h-100 glass-card rounded-4">
                        <?php if (!empty($article['image_path'])): ?>
                            <img 
                                src="/<?php echo htmlspecialchars($article['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                class="card-img-top rounded-top-4" 
                                alt="<?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                style="height: 200px; object-fit: cover;"
                            >
                        <?php else: ?>
                            <div class="bg-gradient-animated rounded-top-4" style="height: 200px;"></div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <?php if (!empty($article['category'])): ?>
                                <span class="badge bg-secondary mb-2">
                                    <?php echo htmlspecialchars($article['category'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endif; ?>
                            
                            <h5 class="card-title">
                                <?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            
                            <p class="card-text text-muted">
                                <?php 
                                $stripped = strip_tags($article['content']);
                                echo htmlspecialchars(substr($stripped, 0, 100) . (strlen($stripped) > 100 ? '...' : ''), ENT_QUOTES, 'UTF-8'); 
                                ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d.m.Y', strtotime($article['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-transparent border-0 d-flex gap-2 p-3">
                            <button type="button" class="btn btn-sm btn-outline-primary flex-fill edit-news-btn" data-id="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-edit me-1"></i>Bearbeiten
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-news-btn" data-id="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>">
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

<!-- News Editor Modal -->
<div class="modal fade" id="newsEditorModal" tabindex="-1" aria-labelledby="newsEditorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="newsEditorModalLabel">News-Artikel erstellen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form id="newsEditorForm" class="needs-validation" novalidate enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="newsId" name="id">
                    
                    <!-- Title -->
                    <div class="mb-3">
                        <label for="newsTitle" class="form-label fw-bold">Titel *</label>
                        <input type="text" class="form-control" id="newsTitle" name="title" required>
                        <div class="invalid-feedback">
                            Bitte geben Sie einen Titel ein.
                        </div>
                    </div>
                    
                    <!-- Category -->
                    <div class="mb-3">
                        <label for="newsCategory" class="form-label fw-bold">Kategorie</label>
                        <input type="text" class="form-control" id="newsCategory" name="category" placeholder="z.B. Veranstaltung, Allgemein, Wichtig">
                    </div>
                    
                    <!-- Content with Quill Editor -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Inhalt *</label>
                        <div id="quillEditor" style="height: 300px; background: white; border-radius: 0.375rem;"></div>
                        <input type="hidden" id="newsContent" name="content" required>
                        <div class="invalid-feedback" id="contentFeedback" style="display: none;">
                            Bitte geben Sie einen Inhalt ein.
                        </div>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="mb-3">
                        <label for="newsImage" class="form-label fw-bold">Bild</label>
                        <input type="file" class="form-control" id="newsImage" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="form-text text-muted">
                            JPG, PNG, GIF oder WebP. Max. 5MB. Wird automatisch auf 1200px Breite optimiert und als WebP gespeichert.
                        </small>
                        <div id="imagePreview" class="mt-2" style="display: none;">
                            <img src="" alt="Preview" style="max-width: 200px; border-radius: 0.5rem;">
                        </div>
                    </div>
                    
                    <!-- CTA Link -->
                    <div class="mb-3">
                        <label for="newsCtaLink" class="form-label fw-bold">CTA-Link (Call-to-Action)</label>
                        <input type="url" class="form-control" id="newsCtaLink" name="cta_link" placeholder="https://...">
                        <small class="form-text text-muted">
                            Optional: Link zu einer Anmeldeseite, einem Dokument oder einer externen Ressource
                        </small>
                    </div>
                    
                    <!-- CTA Button Text -->
                    <div class="mb-3">
                        <label for="newsCtaLabel" class="form-label fw-bold">CTA-Button Text</label>
                        <input type="text" class="form-control" id="newsCtaLabel" name="cta_label" placeholder="z.B. Jetzt anmelden, Mehr erfahren, Dokument herunterladen">
                        <small class="form-text text-muted">
                            Optional: Text, der auf dem CTA-Button angezeigt wird
                        </small>
                    </div>
                    
                    <!-- Email Notification Checkbox -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="checkbox" 
                                id="sendNotification" 
                                name="send_notification"
                                value="1"
                            >
                            <label class="form-check-label fw-bold" for="sendNotification">
                                <i class="fas fa-envelope me-2"></i>Abonnenten über diesen Beitrag per E-Mail informieren
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Hinweis:</strong> E-Mails werden nur bei aktiver Auswahl versendet, um Mehrfach-Benachrichtigungen bei Korrekturen zu vermeiden.
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary" id="newsSubmitBtn">
                        <span id="submitBtnText">Speichern</span>
                        <span id="submitSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                    </button>
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
            placeholder: 'Schreiben Sie hier den Inhalt des Artikels...'
        });
    }
    
    // Create new article button
    const createNewsBtn = document.getElementById('createNewsBtn');
    if (createNewsBtn) {
        createNewsBtn.addEventListener('click', function() {
            openNewsEditorModal();
        });
    }
    
    // Edit buttons
    document.querySelectorAll('.edit-news-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const newsId = this.getAttribute('data-id');
            openNewsEditorModal(newsId);
        });
    });
    
    // Delete buttons
    document.querySelectorAll('.delete-news-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const newsId = this.getAttribute('data-id');
            deleteNewsArticle(newsId);
        });
    });
    
    // Form submission
    const newsForm = document.getElementById('newsEditorForm');
    if (newsForm) {
        newsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveNewsArticle();
        });
    }
    
    // Image preview
    const imageInput = document.getElementById('newsImage');
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

function openNewsEditorModal(newsId = null) {
    const modal = new bootstrap.Modal(document.getElementById('newsEditorModal'));
    const form = document.getElementById('newsEditorForm');
    const modalTitle = document.getElementById('newsEditorModalLabel');
    
    // Reset form
    form.reset();
    form.classList.remove('was-validated');
    document.getElementById('imagePreview').style.display = 'none';
    
    if (newsId) {
        // Edit mode
        modalTitle.textContent = 'News-Artikel bearbeiten';
        document.getElementById('submitBtnText').textContent = 'Aktualisieren';
        
        // Fetch article data
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('id', newsId);
        formData.append('csrf_token', '<?php echo $auth->getCsrfToken(); ?>');
        
        fetch('index.php?page=news_editor', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const article = data.article;
                document.getElementById('newsId').value = article.id;
                document.getElementById('newsTitle').value = article.title;
                document.getElementById('newsCategory').value = article.category || '';
                document.getElementById('newsCtaLink').value = article.cta_link || '';
                document.getElementById('newsCtaLabel').value = article.cta_label || '';
                
                // Explicitly reset notification checkbox for edit mode
                document.getElementById('sendNotification').checked = false;
                
                // Set Quill content
                if (quill) {
                    quill.root.innerHTML = article.content;
                }
                
                // Show existing image if available
                if (article.image_path) {
                    const preview = document.getElementById('imagePreview');
                    const img = preview.querySelector('img');
                    img.src = '/' + article.image_path;
                    preview.style.display = 'block';
                }
            } else {
                showToast(data.message || 'Fehler beim Laden des Artikels', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Fehler beim Laden des Artikels', 'danger');
        });
    } else {
        // Create mode
        modalTitle.textContent = 'News-Artikel erstellen';
        document.getElementById('submitBtnText').textContent = 'Erstellen';
        document.getElementById('newsId').value = '';
        
        // Clear Quill content
        if (quill) {
            quill.root.innerHTML = '';
        }
    }
    
    modal.show();
}

function saveNewsArticle() {
    const form = document.getElementById('newsEditorForm');
    
    // Get Quill content
    const content = quill.root.innerHTML;
    document.getElementById('newsContent').value = content;
    
    // Validate content using Quill's text method for more reliable empty check
    const textContent = quill.getText().trim();
    if (textContent.length === 0) {
        document.getElementById('contentFeedback').style.display = 'block';
        return;
    } else {
        document.getElementById('contentFeedback').style.display = 'none';
    }
    
    // Validate form
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('newsSubmitBtn');
    const submitBtnText = document.getElementById('submitBtnText');
    const submitSpinner = document.getElementById('submitSpinner');
    
    submitBtn.disabled = true;
    submitSpinner.style.display = 'inline-block';
    
    // Prepare form data
    const formData = new FormData(form);
    formData.append('action', 'save');
    formData.append('csrf_token', '<?php echo $auth->getCsrfToken(); ?>');
    
    fetch('index.php?page=news_editor', {
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
            const modal = bootstrap.Modal.getInstance(document.getElementById('newsEditorModal'));
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

function deleteNewsArticle(newsId) {
    if (!confirm('Möchten Sie diesen Artikel wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', newsId);
    formData.append('csrf_token', '<?php echo $auth->getCsrfToken(); ?>');
    
    fetch('index.php?page=news_editor', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            
            // Remove card from DOM
            const card = document.querySelector(`[data-news-id="${newsId}"]`);
            if (card) {
                card.remove();
            }
            
            // Check if grid is empty
            const grid = document.getElementById('newsArticlesGrid');
            if (grid.children.length === 0) {
                grid.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Noch keine Artikel vorhanden. Erstellen Sie den ersten Artikel!
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
    // Use global toast function if available
    if (typeof window.showToast === 'function' && window.showToast !== showToast) {
        window.showToast(message, type);
        return;
    }
    
    // Fallback to alert if no toast system exists
    alert(message);
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
</style>
