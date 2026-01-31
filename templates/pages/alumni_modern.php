<?php
/**
 * Modern Alumni Database Template
 * Features: Masonry Grid / Bootstrap Cards with Images, Graduation Year, Profile View Button
 * Design: Clean, lots of white space, IBC-Blue accent color
 */

// Initialize Alumni class
require_once BASE_PATH . '/src/Alumni.php';
require_once BASE_PATH . '/src/SystemLogger.php';
$systemLogger = new SystemLogger($pdo);
$alumni = new Alumni($pdo, $systemLogger);

// Get filter parameters
$selectedYear = $_GET['year'] ?? 'all';
$selectedCategory = $_GET['category'] ?? 'all';

// Prepare filters
$filters = [];
if ($selectedYear !== 'all') {
    $filters['graduation_year'] = $selectedYear;
}
if ($selectedCategory !== 'all') {
    $filters['industry'] = $selectedCategory;
}

// Get all active alumni profiles (published only) - fetch once
$allProfiles = $alumni->getAllActive();

// Get unique years and categories for filters from all profiles
$years = [];
$categories = [];
foreach ($allProfiles as $profile) {
    if (!empty($profile['graduation_year']) && !in_array($profile['graduation_year'], $years)) {
        $years[] = $profile['graduation_year'];
    }
    if (!empty($profile['industry']) && !in_array($profile['industry'], $categories)) {
        $categories[] = $profile['industry'];
    }
}
sort($years);
rsort($years); // Most recent first
sort($categories);

// Filter profiles based on selection
$profiles = $allProfiles;
if (!empty($filters)) {
    $profiles = array_filter($profiles, function($profile) use ($filters) {
        if (isset($filters['graduation_year']) && $profile['graduation_year'] != $filters['graduation_year']) {
            return false;
        }
        if (isset($filters['industry']) && $profile['industry'] != $filters['industry']) {
            return false;
        }
        return true;
    });
}
?>

<!-- Custom CSS for Alumni Database -->
<style>
    /* IBC-Blue CSS Variable */
    :root {
        --ibc-blue-accent: #1a1f3a;
        --ibc-blue-light: #2d3561;
        --ibc-blue-lighter: #4d5578;
    }
    
    /* Clean, modern design with lots of white space */
    .alumni-modern-container {
        padding: 4rem 0;
        background-color: #fafbfc;
    }
    
    .alumni-header {
        text-align: center;
        margin-bottom: 4rem;
    }
    
    .alumni-header h1 {
        font-size: 3rem;
        font-weight: 700;
        color: var(--ibc-blue-accent);
        margin-bottom: 1rem;
    }
    
    .alumni-header p {
        font-size: 1.25rem;
        color: #6c757d;
    }
    
    /* Filter Buttons */
    .alumni-filters {
        background: white;
        padding: 2rem;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        margin-bottom: 3rem;
    }
    
    .filter-group-label {
        font-weight: 600;
        color: var(--ibc-blue-accent);
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .btn-filter {
        border: 2px solid #e9ecef;
        background: white;
        color: #495057;
        padding: 0.5rem 1.25rem;
        border-radius: 2rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn-filter:hover {
        border-color: var(--ibc-blue-accent);
        color: var(--ibc-blue-accent);
        background: #f8f9fa;
    }
    
    .btn-filter.active {
        background: var(--ibc-blue-accent);
        border-color: var(--ibc-blue-accent);
        color: white;
    }
    
    /* Masonry Grid */
    .alumni-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
    }
    
    /* Alumni Card */
    .alumni-card-modern {
        background: white;
        border-radius: 1.25rem;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .alumni-card-modern:hover {
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        transform: translateY(-4px);
    }
    
    .alumni-card-image {
        width: 100%;
        height: 280px;
        background: linear-gradient(135deg, var(--ibc-blue-light) 0%, var(--ibc-blue-lighter) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    
    .alumni-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .alumni-card-image .placeholder-icon {
        font-size: 5rem;
        color: rgba(255, 255, 255, 0.3);
    }
    
    .alumni-year-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.95);
        color: var(--ibc-blue-accent);
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        font-weight: 700;
        font-size: 0.875rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .alumni-card-body {
        padding: 2rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .alumni-card-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--ibc-blue-accent);
        margin-bottom: 0.5rem;
    }
    
    .alumni-card-position {
        color: #6c757d;
        margin-bottom: 0.25rem;
        font-size: 1rem;
    }
    
    .alumni-card-company {
        color: #adb5bd;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }
    
    .alumni-card-bio {
        color: #6c757d;
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 1.5rem;
        flex: 1;
    }
    
    .alumni-card-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }
    
    .alumni-tag {
        background: #f8f9fa;
        color: #495057;
        padding: 0.375rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .btn-profile-view {
        width: 100%;
        padding: 0.875rem;
        background: var(--ibc-blue-accent);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        transition: all 0.2s ease;
        text-decoration: none;
        text-align: center;
        display: block;
    }
    
    .btn-profile-view:hover {
        background: var(--ibc-blue-light);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 31, 58, 0.3);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }
    
    .empty-state-icon {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 1rem;
    }
    
    .empty-state h3 {
        color: var(--ibc-blue-accent);
        margin-bottom: 0.5rem;
    }
    
    .empty-state p {
        color: #6c757d;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .alumni-header h1 {
            font-size: 2rem;
        }
        
        .alumni-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .alumni-filters {
            padding: 1.5rem;
        }
    }
</style>

<div class="alumni-modern-container">
    <div class="container">
        <!-- Header -->
        <div class="alumni-header">
            <h1>Alumni Datenbank</h1>
            <p>Entdecken Sie unser Netzwerk erfolgreicher Alumni</p>
        </div>
        
        <!-- Filters -->
        <div class="alumni-filters">
            <div class="row">
                <!-- Year Filter -->
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="filter-group-label">Nach Jahrgang filtern</div>
                    <div class="btn-group flex-wrap" role="group" aria-label="Jahr Filter">
                        <a href="?page=alumni_modern&year=all&category=<?php echo htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8'); ?>" 
                           class="btn btn-filter <?php echo $selectedYear === 'all' ? 'active' : ''; ?>">
                            Alle
                        </a>
                        <?php foreach ($years as $year): ?>
                            <a href="?page=alumni_modern&year=<?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?>&category=<?php echo htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8'); ?>" 
                               class="btn btn-filter <?php echo $selectedYear == $year ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div class="col-md-6">
                    <div class="filter-group-label">Nach Branche filtern</div>
                    <div class="btn-group flex-wrap" role="group" aria-label="Kategorie Filter">
                        <a href="?page=alumni_modern&year=<?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>&category=all" 
                           class="btn btn-filter <?php echo $selectedCategory === 'all' ? 'active' : ''; ?>">
                            Alle
                        </a>
                        <?php foreach ($categories as $category): ?>
                            <a href="?page=alumni_modern&year=<?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>&category=<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>" 
                               class="btn btn-filter <?php echo $selectedCategory === $category ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alumni Grid -->
        <?php if (empty($profiles)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <h3>Keine Alumni gefunden</h3>
                <p>Für die ausgewählten Filter wurden keine Alumni-Profile gefunden.</p>
            </div>
        <?php else: ?>
            <div class="alumni-grid">
                <?php foreach ($profiles as $profile): ?>
                    <div class="alumni-card-modern">
                        <!-- Image -->
                        <div class="alumni-card-image">
                            <?php if (!empty($profile['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($profile['profile_picture'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?php echo htmlspecialchars($profile['firstname'] . ' ' . $profile['lastname'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <i class="fas fa-user placeholder-icon"></i>
                            <?php endif; ?>
                            
                            <!-- Year Badge -->
                            <?php if (!empty($profile['graduation_year'])): ?>
                                <div class="alumni-year-badge">
                                    <i class="fas fa-graduation-cap me-1"></i>
                                    <?php echo htmlspecialchars($profile['graduation_year'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="alumni-card-body">
                            <h3 class="alumni-card-name">
                                <?php echo htmlspecialchars($profile['firstname'] . ' ' . $profile['lastname'], ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            
                            <?php if (!empty($profile['position'])): ?>
                                <div class="alumni-card-position">
                                    <?php echo htmlspecialchars($profile['position'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($profile['company'])): ?>
                                <div class="alumni-card-company">
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo htmlspecialchars($profile['company'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($profile['bio'])): ?>
                                <div class="alumni-card-bio">
                                    <?php 
                                    $bioPreview = substr($profile['bio'], 0, 120);
                                    if (strlen($profile['bio']) > 120) {
                                        $bioPreview .= '...';
                                    }
                                    echo htmlspecialchars($bioPreview, ENT_QUOTES, 'UTF-8'); 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Tags -->
                            <?php if (!empty($profile['industry']) || !empty($profile['location'])): ?>
                                <div class="alumni-card-tags">
                                    <?php if (!empty($profile['industry'])): ?>
                                        <span class="alumni-tag">
                                            <i class="fas fa-briefcase me-1"></i>
                                            <?php echo htmlspecialchars($profile['industry'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($profile['location'])): ?>
                                        <span class="alumni-tag">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($profile['location'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Profile View Button -->
                            <a href="?page=alumni&profile_id=<?php echo $profile['id']; ?>" class="btn-profile-view">
                                <i class="fas fa-user-circle me-2"></i>Profil ansehen
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
