<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/ApiRequest/connect.php';

// Statisztikák
$userCount = $conn->query("SELECT COUNT(*) as c FROM Users")->fetch_assoc()['c'];
$matchCount = $conn->query("SELECT COUNT(*) as c FROM Matches")->fetch_assoc()['c'];
$champCount = $conn->query("SELECT COUNT(*) as c FROM Championships")->fetch_assoc()['c'];

// Utolsó 10 regisztrált user
$recentUsers = $conn->query("SELECT id, username, email, full_name, created_at, is_active FROM Users ORDER BY id DESC LIMIT 10");
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
        .stat-card {
            background: #16213e;
            border-radius: 10px;
            padding: 24px;
            text-align: center;
        }
        .stat-card h3 { color: #e94560; font-size: 2.5rem; }
        .stat-card p { color: #aaa; margin: 0; }
        .table-dark th { color: #e94560; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-admin px-4 d-flex justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <img src="../../img/logo.png" alt="logo" style="width:40px;">
        <span class="text-white fw-bold fs-5">Admin Dashboard</span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted">
            <?= htmlspecialchars($_SESSION['admin_username']) ?>
            <span class="badge bg-danger"><?= htmlspecialchars($_SESSION['admin_role']) ?></span>
        </span>
        <a href="/BetMatchBonus/backend/Auth/admin_logout.php" class="btn btn-outline-danger btn-sm">Kijelentkezés</a>
    </div>
</nav>

<div class="container mt-4">

    <!-- Stat kártyák -->
    <div class="row g-4 mb-4">
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

    <!-- Utolsó userek -->
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
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
                <td><?= $u['created_at'] ?></td>
                <td><?= $u['is_active'] ? '✅' : '❌' ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="text-muted">Még nincs regisztrált felhasználó.</p>
    <?php endif; ?>

</div>

</body>
</html>