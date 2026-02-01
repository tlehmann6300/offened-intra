<?php
/**
 * Alumni Validation Interface
 * Only accessible for super-admin roles with full access:
 * - admin, 1V, 2V, 3V, alumni-vorstand, vorstand
 * 
 * Features:
 * - List of pending alumni validations (is_alumni_validated = FALSE)
 * - Ability to validate alumni status
 * - Display request date and user information
 * - Action buttons to validate/approve alumni
 */

// Check if user has permission to access this page
// Only super-admins (admin, 1V, 2V, 3V, alumni-vorstand, vorstand) can access
if (!$auth->hasFullAccess()) {
    header('Location: index.php?page=home');
    exit;
}

// Get pending alumni validations
$pendingAlumni = $auth->getPendingAlumniValidations();
$pendingCount = count($pendingAlumni);

// Handle form submission (validation action)
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_alumni'])) {
    // CSRF token validation
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Ungültiges CSRF-Token. Bitte versuchen Sie es erneut.';
    } else {
        $alumniUserId = (int)($_POST['user_id'] ?? 0);
        $validatorUserId = $auth->getUserId();
        
        if ($alumniUserId > 0) {
            $success = $auth->validateAlumniStatus($alumniUserId, $validatorUserId);
            
            if ($success) {
                $successMessage = 'Alumni-Status erfolgreich validiert!';
                // Refresh pending list
                $pendingAlumni = $auth->getPendingAlumniValidations();
                $pendingCount = count($pendingAlumni);
            } else {
                $errorMessage = 'Fehler beim Validieren des Alumni-Status.';
            }
        } else {
            $errorMessage = 'Ungültige Benutzer-ID.';
        }
    }
}
?>

<!-- Breadcrumb -->
<div class="breadcrumb-container">
    <nav class="breadcrumb-nav">
        <a href="index.php">Home</a>
        <span>&raquo;</span>
        <a href="index.php?page=admin_dashboard">Admin Dashboard</a>
        <span>&raquo;</span>
        <span>Alumni Validierung</span>
    </nav>
</div>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-check"></i> Alumni Validierung</h1>
        <p class="page-description">
            Validieren Sie Alumni-Profile, um ihnen vollen Zugang zum Verzeichnis zu gewähren.
        </p>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Card -->
    <div class="stats-card glass-effect">
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Ausstehende Validierungen</div>
                <div class="stat-value"><?= $pendingCount ?></div>
            </div>
        </div>
    </div>

    <!-- Pending Alumni Table -->
    <div class="content-card glass-effect">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Ausstehende Alumni-Validierungen</h2>
        </div>
        <div class="card-body">
            <?php if ($pendingCount === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Keine ausstehenden Alumni-Validierungen.</p>
                    <small>Alle Alumni-Profile sind validiert.</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>E-Mail</th>
                                <th>Beantragt am</th>
                                <th>Mitglied seit</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingAlumni as $alumni): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($alumni['firstname'] . ' ' . $alumni['lastname']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($alumni['email']) ?></td>
                                    <td>
                                        <?php if ($alumni['alumni_status_requested_at']): ?>
                                            <?= date('d.m.Y H:i', strtotime($alumni['alumni_status_requested_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Nicht verfügbar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d.m.Y', strtotime($alumni['created_at'])) ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="validation-form" data-user-name="<?= htmlspecialchars($alumni['firstname'] . ' ' . $alumni['lastname']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($auth->generateCsrfToken()) ?>">
                                            <input type="hidden" name="user_id" value="<?= $alumni['id'] ?>">
                                            <button type="submit" name="validate_alumni" class="btn btn-primary btn-sm">
                                                <i class="fas fa-check"></i> Validieren
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Information Box -->
    <div class="info-box glass-effect">
        <h3><i class="fas fa-info-circle"></i> Alumni-Validierungs-Workflow</h3>
        <ol>
            <li><strong>Antrag:</strong> Wenn ein Mitglied den Alumni-Status beantragt, wird <code>is_alumni_validated</code> auf FALSE gesetzt.</li>
            <li><strong>Zugriffsbeschränkung:</strong> Der Zugriff auf aktive Projektdaten wird sofort entzogen.</li>
            <li><strong>Validierung:</strong> Der Vorstand prüft das Profil und validiert den Alumni-Status hier.</li>
            <li><strong>Freischaltung:</strong> Nach der Validierung wird das Profil im Alumni-Verzeichnis sichtbar und der Alumni erhält vollen Alumni-Zugang.</li>
        </ol>
        <p class="text-muted">
            <strong>Hinweis:</strong> Nur validierte Alumni sind im öffentlichen Verzeichnis sichtbar.
        </p>
    </div>
</div>

<style>
/* Alumni Validation Specific Styles */
.stats-card {
    margin-bottom: 2rem;
    padding: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.empty-state p {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background-color: rgba(0, 0, 0, 0.03);
}

.data-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-secondary);
    border-bottom: 2px solid rgba(0, 0, 0, 0.1);
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.data-table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}

.text-muted {
    color: var(--text-secondary);
}

.info-box {
    margin-top: 2rem;
    padding: 1.5rem;
}

.info-box h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.info-box ol {
    margin-left: 1.5rem;
    margin-bottom: 1rem;
}

.info-box ol li {
    margin-bottom: 0.75rem;
    line-height: 1.6;
}

.info-box code {
    background-color: rgba(0, 0, 0, 0.05);
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9em;
}

.validation-form {
    display: inline;
}
</style>

<script>
// Unobtrusive JavaScript for form validation confirmation
document.addEventListener('DOMContentLoaded', function() {
    const validationForms = document.querySelectorAll('.validation-form');
    
    validationForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const userName = form.getAttribute('data-user-name');
            // Use template literal for proper string handling
            const confirmed = confirm(`Möchten Sie den Alumni-Status für "${userName}" wirklich validieren?`);
            
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        });
    });
});
</script>
