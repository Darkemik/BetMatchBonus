<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('ADMIN');

require_once __DIR__ . '/../../backend/connect.php';

$role = $_SESSION['admin_role'];

// Gombnyomásra bónusz státusz módosítása (Aktiválás / Inaktiválás)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_bonus_id'])) {
    $bonus_id = (int)$_POST['toggle_bonus_id'];
    
    // Lekérjük a jelenlegi státuszt
    $stmt = $conn->prepare("SELECT is_active FROM BonusCodes WHERE id = ?");
    $stmt->bind_param("i", $bonus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bonus = $result->fetch_assoc();
    $stmt->close();
    
    if ($bonus) {
        // Ha 1, akkor 0 lesz, ha 0, akkor 1
        $new_status = (int)$bonus['is_active'] === 1 ? 0 : 1;
        
        $updateStmt = $conn->prepare("UPDATE BonusCodes SET is_active = ? WHERE id = ?");
        $updateStmt->bind_param("ii", $new_status, $bonus_id);
        
        if ($updateStmt->execute()) {
            $success_msg = "Bónusz státusza frissítve lett!";
        } else {
            $error_msg = "Hiba történt a frissítés során.";
        }
        $updateStmt->close();
    }
}

// Bónuszok lekérése (Duplikáció megelőzése GROUP BY-al)
$bonuses = $conn->query("
    SELECT bc.*, bt.name AS type_name 
    FROM BonusCodes bc 
    LEFT JOIN BonusTypes bt ON bc.bonus_type_id = bt.id 
    GROUP BY bc.id
    ORDER BY bc.id DESC
");
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Bónuszok Kezelése | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
        .main-content { flex: 1; padding: 24px; min-width: 0; }
        .table-dark th { color: #e94560; text-align: center; }
        .table-dark td { vertical-align: middle; }
        .action-cell { text-align: right; width: 150px; } /* Művelet oszlop jobbra igazítása */
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
        <div class="nav-section">Általános</div>
        <a href="dashboard.php#users" class="nav-link">👥 Felhasználók</a>
        <a href="dashboard.php#tickets" class="nav-link">🎫 Szelvények</a>

        <?php if ($role === 'ADMIN' || $role === 'SUPERADMIN'): ?>
        <div class="nav-section">Pénzügy</div>
        <a href="bonuses.php" class="nav-link" style="color: #fff; background: #0f3460;">🎁 Bónuszok</a>
        <a href="withdrawals.php" class="nav-link">💸 Kifizetések</a>
        <?php endif; ?>

        <?php if ($role === 'SUPERADMIN'): ?>
        <div class="nav-section">Rendszer</div>
        <a href="staff.php" class="nav-link">🛡️ Staff (Adminok)</a>
        <a href="audit_logs.php" class="nav-link">📋 Audit Logs</a>
        <?php endif; ?>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="color: #e94560;">🎁 Bónuszok Kezelése</h2>
            <button class="btn btn-success" disabled><i class="fas fa-plus"></i> Új bónusz hozzáadása</button>
        </div>

        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive shadow-sm" style="border-radius: 8px; overflow: hidden;">
            <table class="table table-dark table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kód</th>
                        <th>Név</th>
                        <th>Típus</th>
                        <th>Bónusz összege</th>
                        <th>Jutalom típus</th>
                        <th>Forgatás</th>
                        <th class="text-center">Státusz</th>
                        <th class="text-end">Művelet</th> <!-- Jobbra igazítva -->
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bonuses && $bonuses->num_rows > 0): ?>
                        <?php while ($b = $bonuses->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center fw-bold"><?= (int)$b['id'] ?></td>
                            <td>
                                <?php if($b['code']): ?>
                                    <span class="badge bg-primary fs-6"><?= htmlspecialchars($b['code']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">NINCS KÓD</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($b['name']) ?></div>
                                <div class="text-muted small" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($b['description']) ?>
                                </div>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($b['type_name'] ?? 'N/A') ?></span></td>
                            <td>
                                <span class="text-success fw-bold"><?= number_format($b['bonus_amount'], 0, ',', ' ') ?> Ft</span><br>
                                <span class="text-muted small">Min. bef: <?= number_format($b['min_deposit'], 0, ',', ' ') ?> Ft</span>
                            </td>
                            <td>
                                <?php if($b['bet_reward_type'] == 'FREE_BET'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-ticket-alt"></i> Ingyenes Fogadás</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><i class="fas fa-coins"></i> Bónusz Pénz</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?= $b['wagering_multiplier'] > 0 ? '<strong>' . (float)$b['wagering_multiplier'] . 'x</strong>' : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-center">
                                <?php if((int)$b['is_active'] === 1): ?>
                                    <span class="badge bg-success" style="font-size: 0.9em;"><i class="fas fa-check-circle"></i> AKTÍV</span>
                                <?php else: ?>
                                    <span class="badge bg-danger" style="font-size: 0.9em;"><i class="fas fa-times-circle"></i> INAKTÍV</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-cell">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="toggle_bonus_id" value="<?= $b['id'] ?>">
                                    <?php if((int)$b['is_active'] === 1): ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-power-off"></i> Kikapcsolás</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-power-off"></i> Bekapcsolás</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Még nincs egyetlen bónusz sem az adatbázisban.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Bootstrap JS for dismissible alerts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>