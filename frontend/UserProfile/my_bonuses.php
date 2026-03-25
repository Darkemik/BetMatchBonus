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
    } elseif ($bonus['status'] === 'PENDING') {
        // A pending nem lejárt és nem is aktív még
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
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
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
                                    <h6 class="card-title">Lejárt/Felhasznált</h6>
                                    <h2 class="text-danger"><?php echo $expired_bonuses; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bónuszkód Beváltó Szekció -->
                    <div class="card mb-4 shadow-sm" style="background-color: #16213e; border: 1px solid #e94560;">
                        <div class="card-body">
                            <h5 class="card-title" style="color: #e94560;"><i class="fas fa-ticket-alt"></i> Van promóciós kódod?</h5>
                            <form id="claimBonusForm" class="d-flex mt-3 gap-2">
                                <input type="text" id="bonus_code" name="bonus_code" class="form-control" placeholder="Írd be ide a bónuszkódot" required style="background: #0f3460; color: #fff; border: 1px solid #333;">
                                <button type="submit" class="btn btn-primary" style="background-color: #e94560; border-color: #e94560; font-weight: bold;">Beváltás</button>
                            </form>
                            <div id="bonusMessage" class="mt-2" style="display:none; font-weight: bold;"></div>
                        </div>
                    </div>
                    
                    <?php if (empty($bonuses)): ?>
                        <div class="alert alert-info" style="background: #0f3460; color: #fff; border: none;">
                            <i class="fas fa-info-circle"></i> Jelenleg nincsenek bónuszaid. Látogasd meg a <a href="../../frontend/Bonus/bonus.php" style="color: #e94560; font-weight: bold;">Bónuszok</a> oldalt a lehetőségekért!
                        </div>
                    <?php else: ?>
                        <div class="bonus-list">
                            <?php foreach ($bonuses as $bonus): ?>
                                <div class="card mb-3" style="background: #16213e; border: 1px solid #333; color: #eee;">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h5 class="card-title" style="color: #e94560;">
                                                    <i class="fas fa-gift"></i> 
                                                    <?php echo htmlspecialchars($bonus['bonus_name'] ?? 'Ismeretlen Bónusz'); ?>
                                                </h5>
                                                <p class="card-text mb-1">
                                                    <strong>Érték:</strong> <span class="text-success"><?php echo number_format($bonus['granted_amount'], 0, ',', ' '); ?> FT</span>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <strong>Szükséges forgatás:</strong> 
                                                    <?php 
                                                        if ($bonus['wagering_required'] > 0) {
                                                            $progress = $bonus['wagering_progress'] ?? 0;
                                                            $percentage = min(100, ($progress / $bonus['wagering_required']) * 100);
                                                            echo number_format($progress, 0, ',', ' ') . ' / ' . number_format($bonus['wagering_required'], 0, ',', ' ') . ' FT (' . round($percentage, 1) . '%)';
                                                        } else {
                                                            echo '<span class="text-muted">Nincs szükséges forgatás</span>';
                                                        }
                                                    ?>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <strong>Lejárat:</strong> 
                                                    <?php echo $bonus['expires_at'] ? date('Y-m-d H:i', strtotime($bonus['expires_at'])) : '<span class="text-muted">Nincs megadva</span>'; ?>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <?php 
                                                    $is_valid = $bonus['expires_at'] ? strtotime($bonus['expires_at']) > time() : true;
                                                    
                                                    if ($bonus['status'] === 'PENDING') {
                                                        echo '<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Várakozik (Feltételre)</span>';
                                                    } elseif ($bonus['status'] === 'ACTIVE' && $is_valid) {
                                                        echo '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Aktív</span>';
                                                    } elseif ($bonus['status'] === 'COMPLETED') {
                                                        echo '<span class="badge bg-primary"><i class="fas fa-trophy"></i> Teljesítve</span>';
                                                    } elseif ($bonus['used']) {
                                                        echo '<span class="badge bg-info text-dark"><i class="fas fa-check"></i> Felhasznált</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Lejárt</span>';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="personal_data.php" class="btn btn-secondary mt-3"><i class="fas fa-undo"></i> Vissza</a>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    
    <!-- Új JavaScript a bónuszkód beváltásához -->
    <script>
    document.getElementById('claimBonusForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const code = document.getElementById('bonus_code').value;
        const msgDiv = document.getElementById('bonusMessage');
        const btn = this.querySelector('button[type="submit"]');
        
        btn.disabled = true;
        msgDiv.style.display = 'none';

        const formData = new FormData();
        formData.append('bonus_code', code);

        fetch('../../backend/ApiRequest/claim_bonus.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            msgDiv.style.display = 'block';
            if(data.success) {
                msgDiv.className = 'mt-2 text-success';
                msgDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                setTimeout(() => location.reload(), 2000); // Újratöltjük az oldalt, hogy megjelenjen a listában
            } else {
                msgDiv.className = 'mt-2 text-danger';
                msgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + data.message;
                btn.disabled = false;
            }
        })
        .catch(error => {
            msgDiv.style.display = 'block';
            msgDiv.className = 'mt-2 text-danger';
            msgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Hálózati hiba történt.';
            btn.disabled = false;
        });
    });
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>