    </main>
    
    <!-- Modern Footer - Clean & Minimal -->
    <footer class="modern-footer">
        <div class="container">
            <!-- Main Footer Content - Three Column Layout -->
            <div class="row footer-main-content">
                <!-- Left Column: Über uns (About Us) -->
                <div class="col-lg-4 col-md-12 mb-4 mb-lg-0">
                    <div class="footer-brand">
                        <h6 class="footer-heading">Über uns</h6>
                        <img src="<?= SITE_URL ?>/assets/img/ibc_logo_original.webp" 
                             alt="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> Logo" 
                             class="footer-logo mb-3">
                        <p class="footer-description">
                            Institut für Business Consulting e.V.<br>
                            <span class="footer-tagline">Ihre Plattform für professionelle Zusammenarbeit</span>
                        </p>
                        <!-- Social Media Icons -->
                        <div class="footer-social-icons mt-3">
                            <a href="#" class="social-icon-link" aria-label="LinkedIn" title="LinkedIn">
                                <i class="fab fa-linkedin"></i>
                            </a>
                            <a href="#" class="social-icon-link" aria-label="Instagram" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Center Column: Quick Links -->
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                    <h6 class="footer-heading">Quick-Links</h6>
                    <ul class="footer-nav-links">
                        <?php if (isset($auth) && $auth->isLoggedIn()): ?>
                            <li><a href="index.php?page=home" class="footer-nav-link"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                            <li><a href="index.php?page=events" class="footer-nav-link"><i class="fas fa-calendar-alt me-2"></i>Events</a></li>
                            <li><a href="index.php?page=projects" class="footer-nav-link"><i class="fas fa-briefcase me-2"></i>Projekte</a></li>
                            <li><a href="index.php?page=inventory" class="footer-nav-link"><i class="fas fa-boxes me-2"></i>Inventar</a></li>
                        <?php else: ?>
                            <li><a href="index.php?page=home" class="footer-nav-link"><i class="fas fa-home me-2"></i>Startseite</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Right Column: Status & Login Info -->
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                    <h6 class="footer-heading">Status / Login</h6>
                    <div class="footer-status-info">
                        <?php if (isset($auth) && $auth->isLoggedIn()): ?>
                            <div class="status-item">
                                <i class="fas fa-circle-check status-icon"></i>
                                <span class="status-label">Angemeldet als</span>
                            </div>
                            <div class="status-user">
                                <i class="fas fa-user me-2"></i>
                                <strong><?php echo htmlspecialchars($auth->getUser()['username'] ?? 'Nutzer', ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="status-role">
                                <i class="fas fa-shield-alt me-2"></i>
                                <?php 
                                    $role = $auth->getUser()['role'] ?? 'user';
                                    $roleLabels = [
                                        'admin' => 'Administrator',
                                        'editor' => 'Redakteur',
                                        'user' => 'Nutzer'
                                    ];
                                    echo htmlspecialchars($roleLabels[$role] ?? ucfirst($role), ENT_QUOTES, 'UTF-8'); 
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="status-item">
                                <i class="fas fa-circle-info status-icon"></i>
                                <span class="status-label">Nicht angemeldet</span>
                            </div>
                            <div class="footer-copyright-alt">
                                &copy; <?php echo date('Y'); ?> IBC e.V.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Legal Bar - Separate Lower Section -->
            <div class="footer-legal-bar">
                <div class="legal-links">
                    <a href="index.php?page=impressum" class="legal-link">Impressum</a>
                    <span class="legal-separator">•</span>
                    <a href="index.php?page=datenschutz" class="legal-link">Datenschutz</a>
                    <?php if (isset($auth) && $auth->isLoggedIn()): ?>
                        <span class="legal-separator">•</span>
                        <button type="button" class="btn btn-link legal-link cookie-settings-link p-0" aria-label="Cookie-Einstellungen öffnen">
                            Cookie-Einstellungen
                        </button>
                    <?php endif; ?>
                </div>
                <div class="footer-copyright">
                    &copy; <?php echo date('Y'); ?> Institut für Business Consulting e.V. Alle Rechte vorbehalten.
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Cookie Banner Component -->
    <?php 
    $cookieBannerPath = __DIR__ . '/cookie_banner.php';
    if (file_exists($cookieBannerPath)) {
        include $cookieBannerPath;
    }
    ?>
    
    <!-- Bootstrap JS Bundle (with Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    
    <!-- AOS - Animate On Scroll JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Bundled Application JS (with cache busting) -->
    <?php
    // Cache busting: use file modification time as version
    // This automatically updates when files are rebuilt, forcing browser refresh
    // For very high-traffic sites, consider using a static version number instead
    $jsVersion = file_exists(BASE_PATH . '/assets/js/app.min.js')
        ? '?v=' . filemtime(BASE_PATH . '/assets/js/app.min.js')
        : '?v=' . time();
    
    // Modular JavaScript Structure:
    // The bundled app.min.js includes the following modules (in order):
    // 1. core.js - Global functions (CSRF, toasts, UI initialization)
    // 2. inventory.js - Inventory management (filters, skeletons, CRUD)
    // 3. events.js - Event management (RSVP, helpers, countdown)
    // 4. alumni.js - Alumni database (search, profiles)
    // 5. Other utility modules (navbar, navigation, news, etc.)
    //
    // Each module auto-initializes only when its DOM elements are present on the page,
    // ensuring conditional execution without requiring separate script tags.
    ?>
    <script src="<?= SITE_URL ?>/assets/js/app.min.js<?= $jsVersion ?>"></script>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($page)): ?>
        <?php if ($page === 'home'): ?>
            <script src="<?= SITE_URL ?>/assets/js/home.js"></script>
        <?php elseif ($page === 'admin_dashboard'): ?>
            <script src="<?= SITE_URL ?>/assets/js/admin_dashboard.js"></script>
        <?php endif; ?>
    <?php endif; ?>
    
</body>
</html>
