<?php
/**
 * Newsroom Page
 * Display news articles with featured section and subscription toggle
 */

// Initialize News class
require_once BASE_PATH . '/src/News.php';
require_once BASE_PATH . '/src/NewsService.php';
require_once BASE_PATH . '/src/SystemLogger.php';
$newsService = new NewsService($pdo);
$systemLogger = new SystemLogger($pdo);
$news = new News($pdo, $newsService, $systemLogger);

// Get featured news (most recent)
$featuredNews = $news->getFeatured();

// Get regular news articles (skip the featured one)
$regularNews = $news->getLatest(6, 1); // Get 6 articles, skip first one

// Check if user is subscribed to news notifications
$userId = $_SESSION['user_id'] ?? null;
$isSubscribed = $userId ? $news->isSubscribed($userId) : false;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $allowedActions = ['subscribe', 'unsubscribe', 'load_more'];
    
    if (!in_array($action, $allowedActions, true)) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
        exit;
    }
    
    // Handle subscription actions
    if ($action === 'subscribe' || $action === 'unsubscribe') {
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
            exit;
        }
        
        $success = false;
        
        if ($action === 'subscribe') {
            $success = $news->subscribe($userId);
        } elseif ($action === 'unsubscribe') {
            $success = $news->unsubscribe($userId);
        }
        
        echo json_encode(['success' => $success]);
        exit;
    }
    
    // Handle load_more action
    if ($action === 'load_more') {
        $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 6;
        
        // Validate parameters
        $offset = max(0, $offset);
        $limit = max(1, min($limit, 20)); // Max 20 articles per request
        
        // Fetch articles
        $articles = $news->getLatest($limit, $offset);
        
        echo json_encode([
            'success' => true,
            'articles' => $articles,
            'count' => count($articles)
        ]);
        exit;
    }
}

// Helper function to truncate content
function truncateContent($content, $maxLength = 150) {
    $stripped = strip_tags($content);
    if (strlen($stripped) <= $maxLength) {
        return $stripped;
    }
    return substr($stripped, 0, $maxLength) . '...';
}

// Helper function to format date
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('d.m.Y');
}
?>

<div class="container container-xl my-5">
    <!-- Page Title -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h1 class="display-4 mb-3">
                <span class="text-gradient-premium">Newsroom</span>
            </h1>
            <p class="lead">Bleiben Sie auf dem Laufenden mit den neuesten Nachrichten und Updates</p>
        </div>
    </div>

    <!-- News Notifications Toggle -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">News-Benachrichtigungen</h5>
                        <p class="text-muted small mb-0">Erhalten Sie Benachrichtigungen über neue Nachrichten</p>
                    </div>
                    <div class="form-check form-switch">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            role="switch" 
                            id="newsSubscriptionToggle"
                            <?php echo $isSubscribed ? 'checked' : ''; ?>
                            style="cursor: pointer; width: 3rem; height: 1.5rem;"
                        >
                        <label class="form-check-label visually-hidden" for="newsSubscriptionToggle">
                            News-Benachrichtigungen aktivieren
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($featuredNews): ?>
    <!-- Featured News Section -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="featured-news-container position-relative rounded-5" style="overflow: hidden; min-height: 25rem;">
                <!-- Background Image -->
                <?php if (!empty($featuredNews['image_path'])): ?>
                    <img 
                        src="/<?php echo htmlspecialchars($featuredNews['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                        alt="<?php echo htmlspecialchars($featuredNews['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        class="position-absolute top-0 start-0 w-100 h-100"
                        style="object-fit: cover; z-index: 1;"
                    >
                <?php else: ?>
                    <div 
                        class="position-absolute top-0 start-0 w-100 h-100 bg-gradient-animated"
                        style="z-index: 1;"
                    ></div>
                <?php endif; ?>
                
                <!-- Glassmorphism Overlay -->
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 2;"></div>
                
                <!-- Content -->
                <div class="position-relative p-4 p-md-5" style="z-index: 3; min-height: 25rem; display: flex; flex-direction: column; justify-content: center;">
                    <span class="badge bg-primary mb-3" style="width: fit-content;">
                        <i class="fas fa-star me-1"></i>Featured
                    </span>
                    <h2 class="display-5 mb-3">
                        <?php echo htmlspecialchars($featuredNews['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </h2>
                    <p class="lead mb-3">
                        <?php echo truncateContent($featuredNews['content'], 250); ?>
                    </p>
                    <div class="d-flex align-items-center mb-4">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo formatDate($featuredNews['created_at']); ?>
                        </small>
                        <?php if (!empty($featuredNews['author_firstname']) || !empty($featuredNews['author_lastname'])): ?>
                            <small class="text-muted ms-3">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars(trim($featuredNews['author_firstname'] . ' ' . $featuredNews['author_lastname']), ENT_QUOTES, 'UTF-8'); ?>
                            </small>
                        <?php endif; ?>
                        <?php if (!empty($featuredNews['category'])): ?>
                            <span class="badge bg-secondary ms-3">
                                <?php echo htmlspecialchars($featuredNews['category'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($featuredNews['cta_link']) && !empty($featuredNews['cta_label'])): ?>
                        <a href="<?php echo htmlspecialchars($featuredNews['cta_link'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" style="width: fit-content;">
                            <?php echo htmlspecialchars($featuredNews['cta_label'], ENT_QUOTES, 'UTF-8'); ?>
                            <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Regular News Cards Grid -->
    <?php if (!empty($regularNews)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="mb-4">Weitere Nachrichten</h3>
        </div>
    </div>
    
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-3 g-md-4 mb-5" id="newsGrid">
        <?php foreach ($regularNews as $article): ?>
        <div class="col">
            <div class="card h-100">
                <?php if (!empty($article['image_path'])): ?>
                    <img 
                        src="/<?php echo htmlspecialchars($article['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                        class="card-img-top" 
                        alt="<?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        style="height: 12.5rem; object-fit: cover;"
                    >
                <?php else: ?>
                    <div class="bg-gradient-animated" style="height: 12.5rem;"></div>
                <?php endif; ?>
                
                <div class="card-body d-flex flex-column">
                    <?php if (!empty($article['category'])): ?>
                        <span class="badge bg-secondary mb-2" style="width: fit-content;">
                            <?php echo htmlspecialchars($article['category'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                    
                    <h5 class="card-title">
                        <?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                    
                    <p class="card-text text-muted flex-grow-1">
                        <?php echo truncateContent($article['content'], 120); ?>
                    </p>
                    
                    <div class="d-flex align-items-center mb-3">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo formatDate($article['created_at']); ?>
                        </small>
                    </div>
                    
                    <?php if (!empty($article['cta_link']) && !empty($article['cta_label'])): ?>
                        <a href="<?php echo htmlspecialchars($article['cta_link'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary w-100">
                            <?php echo htmlspecialchars($article['cta_label'], ENT_QUOTES, 'UTF-8'); ?>
                            <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-2"></i>
                Zurzeit sind keine weiteren Nachrichten verfügbar.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Load More Button -->
    <div class="row mb-5">
        <div class="col-12 text-center">
            <button 
                class="btn btn-outline-primary px-5 py-3" 
                id="loadMoreBtn"
                data-offset="7"
            >
                Ältere Beiträge laden
                <i class="fas fa-chevron-down ms-2"></i>
            </button>
            <div class="spinner-border text-primary mt-3 d-none" role="status" id="loadingSpinner">
                <span class="visually-hidden">Lädt...</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional styles for newsroom */
.featured-news-container {
    transition: transform var(--transition-smooth), box-shadow var(--transition-smooth);
}

.featured-news-container:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

/* Card hover effect with defined border-radius */
.card {
    border-radius: 1.5rem;
}

.card:hover {
    transform: translateY(-10px) scale(1.005);
    box-shadow: var(--shadow-hover);
}

/* Switch styling for better visibility */
.form-check-input:checked {
    background-color: var(--ibc-green);
    border-color: var(--ibc-green);
}

.form-check-input:focus {
    border-color: var(--ibc-green-accessible);
    box-shadow: 0 0 0 0.25rem rgba(var(--rgb-ibc-green), 0.25);
}
</style>
