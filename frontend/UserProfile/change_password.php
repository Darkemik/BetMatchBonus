<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// POST kérelmen jelszó módosítása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Érvényesítés
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Összes mező kitöltése kötelező!";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Az új jelszavak nem egyeznek!";
    } elseif (strlen($new_password) < 8) {
        $error_message = "A jelszó legalább 8 karakter hosszú kell legyen!";
    } else {
        // Jelenlegi jelszó és heti limit ellenőrzése
        $query = "SELECT password_hash, password_changed_at FROM Users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Heti egyszer engedélyezett
        if (!empty($user['password_changed_at'])) {
            $lastChange = new DateTime($user['password_changed_at']);
            $now = new DateTime();
            $diff = $now->diff($lastChange);
            if ($diff->days < 7) {
                $nextDate = (clone $lastChange)->modify('+7 days')->format('Y.m.d H:i');
                $error_message = "A jelszót hetente csak egyszer módosíthatod! Legközelebb: " . $nextDate;
            }
        }

        if (empty($error_message) && password_verify($current_password, $user['password_hash'])) {
            // Új jelszó beállítása
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $update_query = "UPDATE Users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $new_password_hash, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Jelszó sikeresen megváltoztatva!";
            } else {
                $error_message = "Hiba a jelszó módosítása során: " . $update_stmt->error;
            }
            $update_stmt->close();
        } elseif (empty($error_message)) {
            $error_message = "A jelenlegi jelszó helytelen!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="userProfile.changePassword.pageTitle">Jelszó Módosítás | BetMatchBonus</title>
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
                    <a href="personal_data.php" class="profile-nav-item"><i class="fas fa-user"></i> <span data-i18n="auth.personalData">Személyes Adatok</span></a>
                    <a href="change_password.php" class="profile-nav-item active"><i class="fas fa-key"></i> <span data-i18n="auth.changePassword">Jelszó Módosítás</span></a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> <span data-i18n="auth.deposit">Befizetés</span></a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span></a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span></a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span></a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> <span data-i18n="auth.activityLog">Napló</span></a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> <span data-i18n="auth.logout">Kijelentkezés</span></a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-key"></i> <span data-i18n="auth.changePassword">Jelszó Módosítás</span></h1>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="profile-form">
                        <div class="form-group mb-3">
                            <label for="current_password" data-i18n="userProfile.changePassword.currentPassword">Jelenlegi Jelszó</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="new_password" data-i18n="userProfile.changePassword.newPassword">Új Jelszó</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted" data-i18n="userProfile.changePassword.minLengthHint">Legalább 8 karakter hosszú kell legyen</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="confirm_password" data-i18n="userProfile.changePassword.confirmPassword">Jelszó Megerősítés</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-save"></i> <span data-i18n="userProfile.changePassword.submit">Jelszó Módosítása</span></button>
                        <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> <span data-i18n="userProfile.changePassword.cancel">Mégse</span></a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <script>
    document.querySelectorAll('.toggle-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = document.getElementById(this.getAttribute('data-target'));
            var icon = this.querySelector('i');
            if (target.type === 'password') {
                target.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                target.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
