<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('SUPERADMIN');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];

// Szűrők
$filterAction = trim($_GET['action_type'] ?? '');
$filterAdmin  = (int)($_GET['admin_id'] ?? 0);
$filterDate   = trim($_GET['date'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

// WHERE összeállítás
$where  = [];
$params = [];
$types  = '';

if ($filterAction !== '') {
    $where[]  = 'a.action_type = ?';
    $params[] = $filterAction;
    $types   .= 's';
}
if ($filterAdmin > 0) {
    $where[]  = 'a.admin_id = ?';
    $params[] = $filterAdmin;
    $types   .= 'i';
}
if ($filterDate !== '') {
    $where[]  = 'DATE(a.created_at) = ?';
    $params[] = $filterDate;
    $types   .= 's';
}

$whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Összesítő
$countSQL = "SELECT COUNT(*) AS c FROM AuditLogs a $whereSQL";
if ($types) {
    $cs = $conn->prepare($countSQL);
    $cs->bind_param($types, ...$params);
    $cs->execute();
    $totalCount = $cs->get_result()->fetch_assoc()['c'];
    $cs->close();
} else {
    $totalCount = $conn->query($countSQL)->fetch_assoc()['c'];
}
$totalPages = max(1, ceil($totalCount / $perPage));

// Log lekérdezés
$sql = "SELECT a.*, au.username AS admin_username
        FROM AuditLogs a
        LEFT JOIN AdminUsers au ON a.admin_id = au.id
        $whereSQL
        ORDER BY a.created_at DESC
        LIMIT $perPage OFFSET $offset";

if ($types) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result();
    $stmt->close();
} else {
    $logs = $conn->query($sql);
}

// Admin lista szűrőhöz
$admins = $conn->query("SELECT id, username FROM AdminUsers ORDER BY username");

// Művelettípusok szűrőhöz
$actionTypes = $conn->query("SELECT DISTINCT action_type FROM AuditLogs WHERE action_type != '' ORDER BY action_type");

// Statisztikák
$todayLogs = $conn->query("SELECT COUNT(*) AS c FROM AuditLogs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
$totalLogs = $conn->query("SELECT COUNT(*) AS c FROM AuditLogs")->fetch_assoc()['c'];

// Művelet típus magyar nevek és színek
$actionMeta = [
    'deposit_manual'    => ['label' => 'Befizetés jóváírás',     'color' => '#52b788', 'icon' => 'fa-plus-circle'],
    'deposit_refund'    => ['label' => 'Befizetés visszatérítés', 'color' => '#e94560', 'icon' => 'fa-undo'],
    'withdrawal_manual' => ['label' => 'Manuális kifizetés',     'color' => '#f5c518', 'icon' => 'fa-hand-holding-usd'],
    'withdrawal_approve'=> ['label' => 'Kifizetés jóváhagyva',   'color' => '#52b788', 'icon' => 'fa-check'],
    'withdrawal_reject' => ['label' => 'Kifizetés elutasítva',   'color' => '#e94560', 'icon' => 'fa-times'],
    'withdrawal_revoke' => ['label' => 'Kifizetés visszavonva',  'color' => '#ff8c00', 'icon' => 'fa-ban'],
    'ticket_void'       => ['label' => 'Szelvény érvénytelenítve','color' => '#e94560', 'icon' => 'fa-ticket-alt'],
    'ticket_close'      => ['label' => 'Szelvény lezárva',       'color' => '#f5c518', 'icon' => 'fa-ticket-alt'],
    'user_update'       => ['label' => 'Felhasználó szerkesztve','color' => '#4cc9f0', 'icon' => 'fa-user-edit'],
    'user_toggle'       => ['label' => 'Felhasználó státusz',    'color' => '#ff8c00', 'icon' => 'fa-user-slash'],
    'user_message'      => ['label' => 'Üzenet küldve',          'color' => '#b794f6', 'icon' => 'fa-envelope'],
    'reg_approve'       => ['label' => 'Regisztráció elfogadva', 'color' => '#52b788', 'icon' => 'fa-user-check'],
    'reg_reject'        => ['label' => 'Regisztráció elutasítva','color' => '#e94560', 'icon' => 'fa-user-times'],
    'staff_create'      => ['label' => 'Admin létrehozva',       'color' => '#52b788', 'icon' => 'fa-user-plus'],
    'staff_update'      => ['label' => 'Admin szerkesztve',      'color' => '#4cc9f0', 'icon' => 'fa-user-cog'],
    'staff_toggle'      => ['label' => 'Admin státusz',          'color' => '#ff8c00', 'icon' => 'fa-user-shield'],
    'staff_reset_pw'    => ['label' => 'Admin jelszó reset',     'color' => '#f5c518', 'icon' => 'fa-key'],
    'staff_delete'      => ['label' => 'Admin törölve',          'color' => '#e94560', 'icon' => 'fa-user-minus'],
    'perm_update'       => ['label' => 'Jogosultság módosítva',  'color' => '#b794f6', 'icon' => 'fa-shield-alt'],
    'settings_update'   => ['label' => 'Beállítás módosítva',   'color' => '#9d4edd', 'icon' => 'fa-cog'],
    'notification_send' => ['label' => 'Értesítés küldve',      'color' => '#52b788', 'icon' => 'fa-paper-plane'],
    'notification_delete'=>['label' => 'Értesítés törölve',     'color' => '#e94560', 'icon' => 'fa-trash'],
];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body { background: #1a1a2e; color: #eee; }
        .navbar-admin { background: #16213e; }
        .sidebar {
            background: #16213e; min-height: calc(100vh - 56px);
            padding: 20px 0; width: 220px; flex-shrink: 0;
        }
        .sidebar .nav-link { color: #ccc; padding: 10px 20px; display: block; }
        .sidebar .nav-link:hover { color: #fff; background: #0f3460; }
        .sidebar .nav-section {
            font-size: 0.7rem; text-transform: uppercase; color: #666;
            padding: 14px 20px 4px; letter-spacing: 1px;
        }
        .stat-card {
            background: #16213e; border-radius: 10px; padding: 24px; text-align: center;
        }
        .stat-card h3 { font-size: 2.5rem; }
        .stat-card p { color: #aaa; margin: 0; }
        .main-content { flex: 1; padding: 24px; min-width: 0; }
        .table-dark th { color: #e94560; }

        .log-row { transition: background 0.2s; }
        .log-row:hover { background: #0f3460 !important; }

        .action-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 6px; font-size: 0.78rem;
            font-weight: 600; white-space: nowrap;
        }

        .target-badge {
            display: inline-block; padding: 2px 8px; border-radius: 4px;
            font-size: 0.75rem; background: #0f3460; color: #4cc9f0;
        }

        .filter-bar {
            background: #16213e; border-radius: 8px; padding: 16px;
            margin-bottom: 20px; display: flex; gap: 12px; align-items: end; flex-wrap: wrap;
        }
        .filter-bar label { color: #aaa; font-size: 0.75rem; display: block; margin-bottom: 4px; }
        .filter-bar select, .filter-bar input {
            background: #0f3460; border: 1px solid #333; color: #fff;
            font-size: 0.85rem; border-radius: 6px; padding: 6px 10px;
        }
        .filter-bar select:focus, .filter-bar input:focus {
            border-color: #e94560; outline: none; box-shadow: 0 0 0 0.2rem rgba(233,69,96,.25);
        }

        .pagination .page-link {
            background: #16213e; border-color: #333; color: #ccc;
        }
        .pagination .page-link:hover { background: #0f3460; color: #fff; }
        .pagination .page-item.active .page-link {
            background: #e94560; border-color: #e94560; color: #fff;
        }
        .pagination .page-item.disabled .page-link { color: #555; }

        .details-cell {
            max-width: 350px; overflow: hidden; text-overflow: ellipsis;
            white-space: nowrap; font-size: 0.82rem; color: #bbb;
        }
        .details-cell:hover { white-space: normal; overflow: visible; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-admin px-4 d-flex justify-content-between" style="height:56px;">
    <div class="d-flex align-items-center gap-3">
        <img src="../../img/logo.png" alt="logo" style="width:40px;">
        <span class="text-white fw-bold fs-5">Admin – Audit Logs</span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted">
            <?= htmlspecialchars($_SESSION['admin_username']) ?>
            <span class="badge bg-danger"><?= htmlspecialchars($role) ?></span>
        </span>
        <a href="/BetMatchBonus/backend/Auth/admin_logout.php" class="btn btn-outline-danger btn-sm">Kijelentkezés</a>
    </div>
</nav>

<div class="d-flex">
    <aside class="sidebar">
        <?php $activePage = 'audit_logs'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <main class="main-content">
        <!-- Stat cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#e94560;"><?= $totalLogs ?></h3>
                    <p>Összes bejegyzés</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#f5c518;"><?= $todayLogs ?></h3>
                    <p>Mai bejegyzés</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#52b788;"><?= $totalCount ?></h3>
                    <p>Szűrt találat</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filter-bar">
            <div>
                <label>Művelet típus</label>
                <select name="action_type">
                    <option value="">Mind</option>
                    <?php while ($at = $actionTypes->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($at['action_type']) ?>"
                        <?= $filterAction === $at['action_type'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($actionMeta[$at['action_type']]['label'] ?? $at['action_type']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label>Admin</label>
                <select name="admin_id">
                    <option value="">Mind</option>
                    <?php while ($adm = $admins->fetch_assoc()): ?>
                    <option value="<?= $adm['id'] ?>" <?= $filterAdmin === (int)$adm['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($adm['username']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label>Dátum</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <button type="submit" class="btn btn-outline-info btn-sm"><i class="fas fa-filter me-1"></i>Szűrés</button>
            <?php if ($filterAction || $filterAdmin || $filterDate): ?>
                <a href="audit_logs.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times me-1"></i>Törlés</a>
            <?php endif; ?>
        </form>

        <!-- Logs table -->
        <div class="table-responsive" style="border-radius: 8px; overflow: hidden;">
            <table class="table table-dark table-striped mb-0">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Időpont</th>
                        <th>Admin</th>
                        <th>Művelet</th>
                        <th>Cél</th>
                        <th>Részletek</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($logs && $logs->num_rows > 0): ?>
                    <?php while ($log = $logs->fetch_assoc()):
                        $meta = $actionMeta[$log['action_type']] ?? ['label' => $log['action_type'], 'color' => '#888', 'icon' => 'fa-circle'];
                    ?>
                    <tr class="log-row">
                        <td class="text-muted"><?= (int)$log['id'] ?></td>
                        <td><small><?= date('Y.m.d H:i:s', strtotime($log['created_at'])) ?></small></td>
                        <td>
                            <span class="fw-bold"><?= htmlspecialchars($log['admin_username'] ?? 'Törölt admin') ?></span>
                        </td>
                        <td>
                            <span class="action-badge" style="background: <?= $meta['color'] ?>22; color: <?= $meta['color'] ?>; border: 1px solid <?= $meta['color'] ?>44;">
                                <i class="fas <?= $meta['icon'] ?>"></i>
                                <?= $meta['label'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['target_type']): ?>
                                <span class="target-badge">
                                    <?= htmlspecialchars($log['target_type']) ?>
                                    <?php if ($log['target_id']): ?>#<?= (int)$log['target_id'] ?><?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="details-cell" title="<?= htmlspecialchars($log['details'] ?? '') ?>">
                            <?= htmlspecialchars($log['details'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                            Nincs naplóbejegyzés<?= ($filterAction || $filterAdmin || $filterDate) ? ' a szűrési feltételekkel' : '' ?>.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a>
                </li>
                <?php
                $startP = max(1, $page - 3);
                $endP   = min($totalPages, $page + 3);
                for ($i = $startP; $i <= $endP; $i++):
                ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
