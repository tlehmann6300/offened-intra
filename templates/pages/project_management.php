<div class="container container-xl my-5">
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="ibc-heading">
                <span class="word-wrapper"><span class="word">Projekt</span></span>
                <span class="word-wrapper"><span class="word text-gradient">Verwaltung</span></span>
            </h1>
            <p class="ibc-lead">
                Verwalten Sie Projekte und Bewerbungen
            </p>
        </div>
    </div>

    <?php if (!$auth->can('edit_projects')): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Sie haben keine Berechtigung, diese Seite zu besuchen.
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-12">
                <div class="card glass-card p-4 h-100">
                    <div class="card-body">
                        <h3>Projekt-Verwaltung</h3>
                        <p class="text-muted">Diese Seite ist in Entwicklung. Hier können Vorstand und Ressortleiter Projekte erstellen, bearbeiten und Bewerbungen verwalten.</p>
                        
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Ihre Berechtigungen:</strong> Sie haben Zugriff auf die Projekt-Verwaltung als <?php echo htmlspecialchars($auth->getUserRole()); ?>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
