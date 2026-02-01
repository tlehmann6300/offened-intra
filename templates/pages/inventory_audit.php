<?php
/**
 * Inventory Audit Page
 * Displays audit trail for all inventory-related actions
 * Only accessible for roles with full access (admin, vorstand, 1V, 2V, 3V)
 * 
 * Features:
 * - Complete audit log of inventory changes
 * - Filter by action type (create, update, delete, adjust_quantity)
 * - Filter by date range
 * - Display who made changes and when
 * - Show target item name
 */

// Check if user has permission to access this page
if (!$auth->hasFullAccess()) {
    header('Location: index.php?page=home');
    exit;
}

// Initialize SystemLogger and Inventory classes
require_once BASE_PATH . '/src/SystemLogger.php';
require_once BASE_PATH . '/src/Inventory.php';
$systemLogger = new SystemLogger($pdo);
$inventory = new Inventory($pdo, $systemLogger);

// Get filter parameters from request
$filters = [
    'target_type' => 'inventory',
    'limit' => 50,
    'offset' => 0
];

if (!empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}

if (!empty($_GET['date_from'])) {
    // Validate date format to prevent SQL injection
    $dateFrom = $_GET['date_from'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $filters['date_from'] = $dateFrom;
    }
}

if (!empty($_GET['date_to'])) {
    // Validate date format to prevent SQL injection
    $dateTo = $_GET['date_to'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $filters['date_to'] = $dateTo . ' 23:59:59';
    }
}

// Get audit logs
$auditLogs = $systemLogger->getLogs($filters);
$totalLogs = $systemLogger->getLogCount($filters);

// Enhance logs with target names
foreach ($auditLogs as &$log) {
    $log['target_name'] = $systemLogger->getTargetName($log['target_type'], (int)$log['target_id']);
}
unset($log); // Break reference

// Action type labels
$actionLabels = [
    'create' => 'Erstellt',
    'update' => 'Aktualisiert',
    'delete' => 'Gelöscht',
    'adjust_quantity' => 'Menge angepasst'
];
?>

<!-- Breadcrumb -->
<div class="breadcrumb-container">
    <nav class="breadcrumb-nav">
        <a href="index.php">Home</a>
        <span>&raquo;</span>
        <a href="index.php?page=inventory">Inventar</a>
        <span>&raquo;</span>
        <span>Inventar-Audit</span>
    </nav>
</div>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Inventar-Audit</h1>
        <p class="page-description">
            Vollständiger Verlauf aller Änderungen am Inventar - wer, was, wann.
        </p>
    </div>

    <!-- Statistics Card -->
    <div class="stats-card glass-effect mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-item">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Gesamt Einträge</div>
                        <div class="stat-value"><?= $totalLogs ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-item">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-filter"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Angezeigte Einträge</div>
                        <div class="stat-value"><?= count($auditLogs) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-item">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Zeitraum</div>
                        <div class="stat-value">
                            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                                Gefiltert
                            <?php else: ?>
                                Alle
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="content-card glass-effect mb-4">
        <div class="card-header">
            <h2><i class="fas fa-filter"></i> Filter</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="index.php">
                <input type="hidden" name="page" value="inventory_audit">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="action" class="form-label">Aktion</label>
                        <select name="action" id="action" class="form-select">
                            <option value="">Alle Aktionen</option>
                            <option value="create" <?= htmlspecialchars($_GET['action'] ?? '', ENT_QUOTES, 'UTF-8') === 'create' ? 'selected' : '' ?>>Erstellt</option>
                            <option value="update" <?= htmlspecialchars($_GET['action'] ?? '', ENT_QUOTES, 'UTF-8') === 'update' ? 'selected' : '' ?>>Aktualisiert</option>
                            <option value="delete" <?= htmlspecialchars($_GET['action'] ?? '', ENT_QUOTES, 'UTF-8') === 'delete' ? 'selected' : '' ?>>Gelöscht</option>
                            <option value="adjust_quantity" <?= htmlspecialchars($_GET['action'] ?? '', ENT_QUOTES, 'UTF-8') === 'adjust_quantity' ? 'selected' : '' ?>>Menge angepasst</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="date_from" class="form-label">Von Datum</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="date_to" class="form-label">Bis Datum</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filtern
                    </button>
                    <a href="index.php?page=inventory_audit" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Filter zurücksetzen
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Log Table -->
    <div class="content-card glass-effect">
        <div class="card-header">
            <h2><i class="fas fa-clipboard-list"></i> Audit-Protokoll</h2>
        </div>
        <div class="card-body">
            <?php if (count($auditLogs) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Keine Audit-Einträge gefunden.</p>
                    <small>Versuchen Sie andere Filtereinstellungen.</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Zeitstempel</th>
                                <th>Aktion</th>
                                <th>Gegenstand</th>
                                <th>Benutzer</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td>
                                        <span class="text-nowrap">
                                            <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                            <?= date('d.m.Y', strtotime($log['timestamp'])) ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('H:i:s', strtotime($log['timestamp'])) ?> Uhr
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $actionClass = 'primary';
                                        $actionIcon = 'fa-info-circle';
                                        switch ($log['action']) {
                                            case 'create':
                                                $actionClass = 'success';
                                                $actionIcon = 'fa-plus-circle';
                                                break;
                                            case 'update':
                                                $actionClass = 'info';
                                                $actionIcon = 'fa-edit';
                                                break;
                                            case 'delete':
                                                $actionClass = 'danger';
                                                $actionIcon = 'fa-trash';
                                                break;
                                            case 'adjust_quantity':
                                                $actionClass = 'warning';
                                                $actionIcon = 'fa-balance-scale';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $actionClass ?>">
                                            <i class="fas <?= $actionIcon ?> me-1"></i>
                                            <?= htmlspecialchars($actionLabels[$log['action']] ?? $log['action']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($log['target_name'] ?? 'Unbekannt') ?></strong>
                                        <br>
                                        <small class="text-muted">ID: <?= $log['target_id'] ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-user-circle text-primary"></i>
                                            <div>
                                                <strong><?= htmlspecialchars($log['firstname'] . ' ' . $log['lastname']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($log['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($log['action'] === 'delete'): ?>
                                            <span class="text-danger">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Datensatz gelöscht
                                            </span>
                                        <?php else: ?>
                                            <a href="index.php?page=inventory" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-arrow-right me-1"></i>Zum Inventar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalLogs > count($auditLogs)): ?>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Es werden die ersten <?= count($auditLogs) ?> von <?= $totalLogs ?> Einträgen angezeigt.
                        Verwenden Sie Filter für eine detailliertere Ansicht.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Information Box -->
    <div class="info-box glass-effect mt-4">
        <h3><i class="fas fa-info-circle"></i> Über Inventar-Audit</h3>
        <p>
            Diese Seite zeigt alle Änderungen, die am Inventar vorgenommen wurden. Jede Aktion wird automatisch protokolliert und kann hier nachvollzogen werden.
        </p>
        <ul>
            <li><strong>Erstellt:</strong> Ein neuer Gegenstand wurde dem Inventar hinzugefügt</li>
            <li><strong>Aktualisiert:</strong> Informationen eines Gegenstands wurden geändert</li>
            <li><strong>Gelöscht:</strong> Ein Gegenstand wurde aus dem Inventar entfernt</li>
            <li><strong>Menge angepasst:</strong> Die Anzahl eines Gegenstands wurde erhöht oder verringert</li>
        </ul>
        <p class="text-muted mb-0">
            <strong>Hinweis:</strong> Diese Daten dienen der Nachvollziehbarkeit und Transparenz aller Inventar-Aktivitäten.
        </p>
    </div>
</div>

<style>
/* Inventory Audit Specific Styles */
.stats-card {
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
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.bg-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.bg-success {
    background: linear-gradient(135deg, #6dd544 0%, #4caf50 100%);
}

.stat-icon.bg-info {
    background: linear-gradient(135deg, #3481b9 0%, #2196f3 100%);
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
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
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
    vertical-align: top;
}

.data-table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.badge {
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
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

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}

.btn-outline-primary {
    background: transparent;
    border: 1px solid var(--primary-color);
    color: var(--primary-color);
}

.btn-outline-primary:hover {
    background: var(--primary-color);
    color: white;
}

.info-box {
    padding: 1.5rem;
}

.info-box h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.info-box ul {
    margin-left: 1.5rem;
    margin-bottom: 1rem;
}

.info-box ul li {
    margin-bottom: 0.75rem;
    line-height: 1.6;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-control,
.form-select {
    padding: 0.6rem 1rem;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    transition: border-color 0.3s ease;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.text-nowrap {
    white-space: nowrap;
}
</style>
