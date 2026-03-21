<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/ApiRequest/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Felhasználó bónuszainak lekérése az aktív bónuszokkal együtt
$query = "SELECT ub.id, ub.bonus_id, bc.name as bonus_name, ub.granted_amount, ub.status, ub.expires_at, ub.wagering_progress, ub.wagering_required, ub.used, ub.created_at 
          FROM UserBonuses ub
          LEFT JOIN BonusCodes bc ON ub.bonus_id = bc.id
          WHERE ub.user_id = ? 
          ORDER BY ub.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bonuses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Aktív és lejárt bónuszok száma
$active_bonuses = 0;
$expired_bonuses = 0;
$total_bonus_amount = 0;

foreach ($bonuses as $bonus) {
    if ($bonus['status'] === 'ACTIVE' && $bonus['expires_at'] && strtotime($bonus['expires_at']) > time()) {
        $active_bonuses++;
        $total_bonus_amount += $bonus['granted_amount'];
    } else {
        $expired_bonuses++;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bónuszaim | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php require_once "../Components/header.php"; ?>
    <div class="container profile-container">
        <div class="row">
            <div class="col-md-3">
                <nav class="profile-sidebar">
                    <a href="personal_data.php" class="profile-nav-item"><i class="fas fa-user"></i> Személyes Adatok</a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> Jelszó Módosítás</a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> Befizetés</a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> Kifizetés</a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> Tranzakciótörténet</a>
                    <a href="my_bonuses.php" class="profile-nav-item active"><i class="fas fa-gift"></i> Bónuszaim</a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> Napló</a>
                    <a href="../../backend/Auth/logout.php" class="profile-nav-item logout"><i class="fas fa-sign-out-alt"></i> Kijelentkezés</a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-gift"></i> Bónuszaim</h1>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bonus-card">
                                <div class="card-body">
                                    <h6 class="card-title">Aktív Bónuszok</h6>
                                    <h2 class="text-success"><?php echo $active_bonuses; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bonus-card">
                                <div class="card-body">
                                    <h6 class="card-title">Teljes Bónusz Érték</h6>
                                    <h2 class="text-primary"><?php echo number_format($total_bonus_amount, 0, ',', ' '); ?> FT</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bonus-card">
                                <div class="card-body">
                                    <h6 class="card-title">Lejárt Bónuszok</h6>
                                    <h2 class="text-danger"><?php echo $expired_bonuses; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($bonuses)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Jelenleg nincsenek bónuszaid. Látogasd meg a <a href="../../frontend/Bonus/bonus.php">Bónuszok</a> oldalt a lehetőségekért!
                        </div>
                    <?php else: ?>
                        <div class="bonus-list">
                            <?php foreach ($bonuses as $bonus): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h5 class="card-title">
                                                    <i class="fas fa-gift"></i> 
                                                    <?php echo htmlspecialchars($bonus['bonus_name'] ?? 'Ismeretlen Bónusz'); ?>
                                                </h5>
                                                <p class="card-text mb-1">
                                                    <strong>Érték:</strong> <?php echo number_format($bonus['granted_amount'], 0, ',', ' '); ?> FT
                                                </p>
                                                <p class="card-text mb-1">
                                                    <strong>Szükséges forgatás:</strong> 
                                                    <?php 
                                                        if ($bonus['wagering_required']) {
                                                            $progress = $bonus['wagering_progress'] ?? 0;
                                                            $percentage = ($progress / $bonus['wagering_required']) * 100;
                                                            echo $progress . ' / ' . $bonus['wagering_required'] . ' (' . round($percentage, 1) . '%)';
                                                        } else {
                                                            echo 'Nincs szükséges forgatás';
                                                        }
                                                    ?>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <strong>Lejárat:</strong> 
                                                    <?php echo $bonus['expires_at'] ? date('Y-m-d H:i', strtotime($bonus['expires_at'])) : 'Nincs megadva'; ?>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <?php 
                                                    $is_valid = $bonus['expires_at'] && strtotime($bonus['expires_at']) > time();
                                                    if ($bonus['status'] === 'ACTIVE' && $is_valid) {
                                                        echo '<span class="badge bg-success">Aktív</span>';
                                                    } elseif ($bonus['used']) {
                                                        echo '<span class="badge bg-info">Felhasznált</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger">Lejárt</span>';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="../../frontend/Bonus/bonus.php" class="btn btn-primary"><i class="fas fa-plus"></i> Új Bónusz Megtekintése</a>
                    <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Vissza</a>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
</body>
</html>
