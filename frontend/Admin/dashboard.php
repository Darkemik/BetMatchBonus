<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/ApiRequest/connect.php';

// Statistics
$userCount  = $conn->query("SELECT COUNT(*) AS c FROM Users")->fetch_assoc()['c'];
$matchCount = $conn->query("SELECT COUNT(*) AS c FROM Events")->fetch_assoc()['c'];
$champCount = $conn->query("SELECT COUNT(*) AS c FROM Competitions")->fetch_assoc()['c'];

// Last 10 registered users
$recentUsers = $conn->query("SELECT id, username, email, full_name, created_at, is_active FROM Users ORDER BY id DESC LIMIT 10");

$role = $_SESSION['admin_role'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | BetMatchBonus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body { background: #1a1a2e; color: #eee; }
        .navbar-admin { background: #16213e; }
        .sidebar {
            background: #16213e;
            min-height: calc(100vh - 56px);
            padding: 20px 0;
            width: 220px;
            flex-shrink: 0;
        }
        .sidebar .nav-link {
            color: #ccc;
            padding: 10px 20px;
            display: block;
        }
        .sidebar .nav-link:hover { color: #fff; background: #0f3460; }
        .sidebar .nav-section {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #666;
            padding: 14px 20px 4px;
            letter-spacing: 1px;
        }
        .stat-card {
            background: #16213e;
            border-radius: 10px;
            padding: 24px;
            text-align: center;
        }
        .stat-card h3 { color: #e94560; font-size: 2.5rem; }
        .stat-card p  { color: #aaa; margin: 0; }
        .table-dark th { color: #e94560; }
        .main-content { flex: 1; padding: 24px; min-width: 0; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-admin px-4 d-flex justify-content-between" style="height:56px;">
    <div class="d-flex align-items-center gap-3">
        <img src="../../img/logo.png" alt="logo" style="width:40px;">
        <span class="text-white fw-bold fs-5">Admin Dashboard</span>
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

    <!-- Sidebar -->
    <aside class="sidebar">

        <!-- All roles -->
        <div class="nav-section">Általános</div>
        <a href="#users" class="nav-link">👥 Felhasználók</a>
        <a href="#tickets" class="nav-link">🎫 Szelvények</a>

        <!-- ADMIN and SUPERADMIN -->
        <?php if ($role === 'ADMIN' || $role === 'SUPERADMIN'): ?>
        <div class="nav-section">Pénzügy</div>
        <a href="bonuses.php" class="nav-link">🎁 Bónuszok</a>
        <a href="withdrawals.php" class="nav-link">💸 Kifizetések</a>
        <?php endif; ?>

        <!-- SUPERADMIN only -->
        <?php if ($role === 'SUPERADMIN'): ?>
        <div class="nav-section">Rendszer</div>
        <a href="staff.php" class="nav-link">🛡️ Staff (Adminok)</a>
        <a href="audit_logs.php" class="nav-link">📋 Audit Logs</a>
        <?php endif; ?>

    </aside>

    <!-- Main content -->
    <main class="main-content">

        <!-- Stat cards -->
        <div class="row g-4 mb-4" id="users">
            <div class="col-md-4">
                <div class="stat-card">
                    <h3><?= $userCount ?></h3>
                    <p>Regisztrált felhasználó</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3><?= $matchCount ?></h3>
                    <p>Meccs az adatbázisban</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3><?= $champCount ?></h3>
                    <p>Bajnokság</p>
                </div>
            </div>
        </div>

        <!-- Last registered users -->
        <h4 class="mb-3">Utolsó 10 regisztrált felhasználó</h4>
        <?php if ($recentUsers && $recentUsers->num_rows > 0): ?>
        <table class="table table-dark table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Teljes név</th>
                    <th>Regisztráció</th>
                    <th>Aktív?</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($u = $recentUsers->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($u['created_at']) ?></td>
                    <td><?= $u['is_active'] ? '✅' : '❌' ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted">Még nincs regisztrált felhasználó.</p>
        <?php endif; ?>

    </main>
</div>

</body>
</html>
