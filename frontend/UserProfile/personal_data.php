<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Felhasználó adatainak lekérése
$query = "SELECT id, username, email, full_name, mobile_number as phone, country, city, postal_code, address, birth_date, created_at FROM Users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// POST kérelmen adatok módosítása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $country = htmlspecialchars($_POST['country'] ?? '');
    $city = htmlspecialchars($_POST['city'] ?? '');
    $postal_code = htmlspecialchars($_POST['postal_code'] ?? '');
    $address = htmlspecialchars($_POST['address'] ?? '');

    $update_query = "UPDATE Users SET country = ?, city = ?, postal_code = ?, address = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssssi", $country, $city, $postal_code, $address, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Személyes adatok sikeresen frissítve!";
        header("Location: personal_data.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Hiba: " . $update_stmt->error;
    }
    $update_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Személyes Adatok | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
    <?php include '../../frontend/Components/disclaimer.php'; ?>
    <?php require_once "../Components/header.php"; ?>
    <div class="container profile-container">
        <div class="row">
            <div class="col-md-3">
                <nav class="profile-sidebar">
                    <a href="personal_data.php" class="profile-nav-item active"><i class="fas fa-user"></i> Személyes Adatok</a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> Jelszó Módosítás</a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> Befizetés</a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> Kifizetés</a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> Tranzakciótörténet</a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> Bónuszaim</a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> Napló</a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> Kijelentkezés</a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-user"></i> Személyes Adatok</h1>

                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        A kifizetés előtt kötelező kitölteni a lakcímadatokat (ország, város, irányítószám, cím). A fiókodat egy admin ellenőrzi, hogy a megadott adatok helyesek-e.
                    </div>
                    
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="profile-form">
                        <div class="form-group mb-3">
                            <label for="username">Felhasználónév</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="form-text" style="color: white;">A felhasználónév nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small class="form-text" style="color: white;">Az email cím nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="full_name">Teljes Név</label>
                            <input type="text" class="form-control" id="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;">A teljes név nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="phone">Telefon</label>
                            <input type="tel" class="form-control" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;">A telefonszám nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="country">Ország</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" readonly>
                            <small class="form-text" style="color: #aaa;">Az irányítószám alapján automatikusan kitöltődik</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="postal_code">Irányítószám</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" maxlength="4" placeholder="pl. 1051">
                            <small class="form-text" id="postalFeedback" style="color: #aaa;"></small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="city">Város / Község</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" readonly>
                            <small class="form-text" style="color: #aaa;">Az irányítószám alapján automatikusan kitöltődik</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="address">Cím</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="birth_date">Születési Dátum</label>
                            <input type="date" class="form-control" id="birth_date" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;">A születési dátum nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="created_at">Fiók Létrehozva</label>
                            <input type="text" class="form-control" id="created_at" value="<?php echo htmlspecialchars($user['created_at']); ?>" disabled>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Adatok Módosítása</button>
                        <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Mégse</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
