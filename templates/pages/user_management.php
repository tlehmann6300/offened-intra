<?php
/**
 * User Management Page
 * Only accessible for roles with full access (admin, vorstand, 1V, 2V, 3V)
 * 
 * Features:
 * - Create new invitation tokens
 * - View pending invitations
 * - Delete/cancel invitations
 * - Role management for existing users
 */

// Check if user has permission to access this page
if (!$auth->hasFullAccess()) {
    header('Location: index.php?page=home');
    exit;
}

// Get pending invitations
$invitationsResult = $auth->getPendingInvitations(50, 0);
$pendingInvitations = $invitationsResult['success'] ? $invitationsResult['invitations'] : [];

// Get all users for role management
$stmt = $pdo->prepare("
    SELECT u.id, u.firstname, u.lastname, u.email, u.role, u.created_at, u.last_login
    FROM users u
    WHERE u.role != 'none'
    ORDER BY u.lastname, u.firstname
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Role labels in German
$roleLabels = [
    'mitglied' => 'Mitglied',
    'alumni' => 'Alumni',
    'ressortleiter' => 'Ressortleiter',
    'vorstand' => 'Vorstand',
    '1v' => '1. Vorstand',
    '2v' => '2. Vorstand',
    '3v' => '3. Vorstand',
    'admin' => 'Administrator'
];
?>

<div class="container container-xl my-5">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="ibc-heading">
                <span class="word-wrapper"><span class="word">Benutzer</span></span>
                <span class="word-wrapper"><span class="word text-gradient">Verwaltung</span></span>
            </h1>
            <p class="ibc-lead">
                Verwalten Sie Einladungen und Benutzerrollen
            </p>
        </div>
    </div>

    <!-- Invitation Management Section -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <h2 class="h4 mb-4" style="color: var(--ibc-blue);">
                        <i class="fas fa-envelope me-2"></i>Einladungsverwaltung
                        <?php if (count($pendingInvitations) > 0): ?>
                            <span class="badge bg-primary ms-2"><?php echo count($pendingInvitations); ?> ausstehend</span>
                        <?php endif; ?>
                    </h2>
                    
                    <!-- Create Invitation Form -->
                    <div class="mb-4">
                        <h5 class="mb-3">Neue Einladung erstellen</h5>
                        <form id="invitationForm" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= $auth->generateCsrfToken() ?>">
                            
                            <div class="col-md-4">
                                <label for="invite_email" class="form-label">E-Mail-Adresse *</label>
                                <input type="email" class="form-control" id="invite_email" name="email" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="invite_role" class="form-label">Rolle *</label>
                                <select class="form-select" id="invite_role" name="role" required>
                                    <option value="alumni" selected>Alumni</option>
                                    <option value="mitglied">Mitglied</option>
                                    <option value="ressortleiter">Ressortleiter</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="invite_expiration" class="form-label">Gültigkeitsdauer</label>
                                <select class="form-select" id="invite_expiration" name="expiration_hours">
                                    <option value="24">24 Stunden</option>
                                    <option value="48" selected>48 Stunden</option>
                                    <option value="72">72 Stunden</option>
                                    <option value="168">1 Woche</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100" id="inviteSubmitBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Senden
                                </button>
                            </div>
                        </form>
                        
                        <div id="inviteMessage" class="alert mt-3" style="display: none;" role="alert"></div>
                    </div>
                    
                    <!-- Pending Invitations List -->
                    <div class="mt-4">
                        <h5 class="mb-3">Ausstehende Einladungen</h5>
                        
                        <?php if (count($pendingInvitations) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>E-Mail</th>
                                            <th>Rolle</th>
                                            <th>Erstellt von</th>
                                            <th>Erstellt am</th>
                                            <th>Läuft ab</th>
                                            <th>Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody id="invitationsTableBody">
                                        <?php foreach ($pendingInvitations as $invitation): ?>
                                            <tr data-invitation-id="<?= htmlspecialchars((string)$invitation['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                <td><?= htmlspecialchars($invitation['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars(ucfirst($invitation['role']), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars(trim(($invitation['firstname'] ?? '') . ' ' . ($invitation['lastname'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($invitation['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <?php 
                                                    $expiresAt = strtotime($invitation['expires_at']);
                                                    $now = time();
                                                    $hoursRemaining = round(($expiresAt - $now) / 3600);
                                                    $class = $hoursRemaining < 24 ? 'text-danger' : 'text-muted';
                                                    ?>
                                                    <span class="<?= $class ?>">
                                                        <?= htmlspecialchars(date('d.m.Y H:i', $expiresAt), ENT_QUOTES, 'UTF-8') ?>
                                                        <br>
                                                        <small>(in <?= $hoursRemaining ?>h)</small>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger delete-invitation-btn" 
                                                            data-invitation-id="<?= htmlspecialchars((string)$invitation['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-invitation-email="<?= htmlspecialchars($invitation['email'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="fas fa-trash me-1"></i>Löschen
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                Keine ausstehenden Einladungen vorhanden.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Role Management Section -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <h2 class="h4 mb-4" style="color: var(--ibc-blue);">
                        <i class="fas fa-users-cog me-2"></i>Rollenverwaltung
                        <span class="badge bg-secondary ms-2"><?php echo count($users); ?> Benutzer</span>
                    </h2>
                    
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Hinweis:</strong> Die Änderung von Benutzerrollen erfolgt über die Benutzerprofile. Diese Übersicht dient der Information über alle registrierten Benutzer.
                    </div>
                    
                    <?php if (count($users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>E-Mail</th>
                                        <th>Rolle</th>
                                        <th>Registriert</th>
                                        <th>Letzter Login</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php
                                                $roleColor = match($user['role']) {
                                                    'admin', '1v', '2v', '3v' => 'danger',
                                                    'vorstand' => 'warning',
                                                    'ressortleiter' => 'info',
                                                    'mitglied' => 'primary',
                                                    'alumni' => 'secondary',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $roleColor ?>">
                                                    <?= htmlspecialchars($roleLabels[$user['role']] ?? $user['role'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars(date('d.m.Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars(date('d.m.Y H:i', strtotime($user['last_login'])), ENT_QUOTES, 'UTF-8') ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">Noch nie</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            Keine Benutzer vorhanden.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Handle invitation form submission
document.getElementById('invitationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('inviteSubmitBtn');
    const message = document.getElementById('inviteMessage');
    const formData = new FormData(this);
    
    // Hide previous messages
    message.style.display = 'none';
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Wird gesendet...';
    
    try {
        const response = await fetch('<?= SITE_URL ?>/api/send_invitation.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success message
            message.className = 'alert alert-success mt-3';
            message.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + data.message;
            message.style.display = 'block';
            
            // Reset form
            document.getElementById('invitationForm').reset();
            
            // Reload page after 2 seconds to show new invitation
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            // Show error message
            message.className = 'alert alert-danger mt-3';
            message.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + data.message;
            message.style.display = 'block';
        }
    } catch (error) {
        console.error('Error:', error);
        message.className = 'alert alert-danger mt-3';
        message.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
        message.style.display = 'block';
    } finally {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Senden';
    }
});

// Handle invitation deletion
document.querySelectorAll('.delete-invitation-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const invitationId = this.dataset.invitationId;
        const invitationEmail = this.dataset.invitationEmail;
        
        if (!confirm(`Möchten Sie die Einladung für "${invitationEmail}" wirklich löschen?`)) {
            return;
        }
        
        const originalBtnHtml = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        try {
            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('invitation_id', invitationId);
            
            const response = await fetch('<?= SITE_URL ?>/api/delete_invitation.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                const message = document.getElementById('inviteMessage');
                message.className = 'alert alert-success mt-3';
                message.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + data.message;
                message.style.display = 'block';
                
                // Remove the row from the table
                const row = this.closest('tr');
                row.remove();
                
                // If no more rows, show "no invitations" message
                const tbody = document.getElementById('invitationsTableBody');
                if (tbody.children.length === 0) {
                    const tableContainer = tbody.closest('.table-responsive');
                    tableContainer.innerHTML = '<div class="alert alert-info" role="alert"><i class="fas fa-info-circle me-2"></i>Keine ausstehenden Einladungen vorhanden.</div>';
                }
                
                // Hide message after 3 seconds
                setTimeout(() => {
                    message.style.display = 'none';
                }, 3000);
            } else {
                alert('Fehler beim Löschen: ' + data.message);
                this.disabled = false;
                this.innerHTML = originalBtnHtml;
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ein Fehler ist beim Löschen aufgetreten.');
            this.disabled = false;
            this.innerHTML = originalBtnHtml;
        }
    });
});
</script>
