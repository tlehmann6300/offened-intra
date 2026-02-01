    </main>
    
    <!-- Minimal Footer - Single Line -->
    <footer class="minimal-footer bg-light border-top py-3 mt-auto">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <small class="text-muted">
                        &copy; <?php echo date('Y'); ?> Institut für Business Consulting e.V. Alle Rechte vorbehalten.
                    </small>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <small>
                        <a href="index.php?page=impressum" class="text-muted text-decoration-none me-3">Impressum</a>
                        <a href="index.php?page=datenschutz" class="text-muted text-decoration-none">Datenschutz</a>
                        <?php if (isset($auth) && $auth->isLoggedIn()): ?>
                            <span class="text-muted mx-2">•</span>
                            <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none p-0" style="vertical-align: baseline;" aria-label="Cookie-Einstellungen öffnen">
                                Cookie-Einstellungen
                            </button>
                        <?php endif; ?>
                    </small>
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
