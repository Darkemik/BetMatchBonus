<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/ApiRequest/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Felhasználó tevékenységeinek lekérése (pl. bejelentkezések, fogadások, etc.)
$query = "SELECT id, activity_type, description, ip_address, user_agent, created_at FROM ActivityLog WHERE user_id = ? ORDER BY created_at DESC LIMIT 200";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ha nincs ActivityLog adat, akkor kutatunk más lehetőségek után
if (empty($activities)) {
    // Alternatív: UserLogs tábla használata
    $query = "SELECT id, action as activity_type, details as description, created_at FROM UserLogs WHERE user_id = ? ORDER BY created_at DESC LIMIT 100";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $activities = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Napló | BetMatchBonus</title>
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
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> Bónuszaim</a>
                    <a href="activity_log.php" class="profile-nav-item active"><i class="fas fa-list"></i> Napló</a>
                    <a href="../../backend/Auth/logout.php" class="profile-nav-item logout"><i class="fas fa-sign-out-alt"></i> Kijelentkezés</a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-list"></i> Tevékenységi Napló</h1>
                    
                    <?php if (empty($activities)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Még nincs tevékenységi bejegyzés.
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-marker">
                                        <?php 
                                            $icon_class = 'fas fa-circle';
                                            $activity_type = $activity['activity_type'] ?? 'unknown';
                                            
                                            switch ($activity_type) {
                                                case 'login':
                                                    $icon_class = 'fas fa-sign-in-alt text-success';
                                                    break;
                                                case 'logout':
                                                    $icon_class = 'fas fa-sign-out-alt text-danger';
                                                    break;
                                                case 'bet':
                                                    $icon_class = 'fas fa-dice text-primary';
                                                    break;
                                                case 'deposit':
                                                    $icon_class = 'fas fa-plus-circle text-success';
                                                    break;
                                                case 'withdrawal':
                                                    $icon_class = 'fas fa-minus-circle text-danger';
                                                    break;
                                                case 'bonus':
                                                    $icon_class = 'fas fa-gift text-warning';
                                                    break;
                                                default:
                                                    $icon_class = 'fas fa-circle text-secondary';
                                            }
                                        ?>
                                        <i class="<?php echo $icon_class; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6><?php echo htmlspecialchars($activity['activity_type'] ?? 'Unknown'); ?></h6>
                                        <p class="activity-description">
                                            <?php echo htmlspecialchars($activity['description'] ?? 'Nincs leírás'); ?>
                                        </p>
                                        <small class="activity-time">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('Y-m-d H:i:s', strtotime($activity['created_at'])); ?>
                                        </small>
                                        <?php if (!empty($activity['ip_address'])): ?>
                                            <small class="activity-ip d-block">
                                                <i class="fas fa-network-wired"></i> IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
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
