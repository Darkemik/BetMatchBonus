<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";
require_once "../../backend/Auth/settings_helper.php";

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
    } elseif (strlen($new_password) < get_setting_int('min_password_length', 7)) {
        $error_message = "A jelszó legalább " . get_setting_int('min_password_length', 7) . " karakter hosszú kell legyen!";
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
    <link rel="stylesheet" href="../../css/Main/popup.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
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
                    <a href="notifications.php" class="profile-nav-item"><i class="fas fa-bell"></i> <span data-i18n="auth.notifications">Értesítések</span></a>
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
                            <small class="form-text text-white" data-i18n="userProfile.changePassword.minLengthHint">Legalább <?php echo get_setting_int('min_password_length', 7); ?> karakter hosszú kell legyen</small>
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
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('current_password').value='';document.getElementById('new_password').value='';document.getElementById('confirm_password').value='';"><i class="fas fa-undo"></i> <span data-i18n="userProfile.changePassword.cancel">Mégse</span></button>
                    </form>

                    <!-- ===== AKTÍV MUNKAMENETEK ===== -->
                    <hr class="my-4">
                    <h2><i class="fas fa-desktop"></i> <span data-i18n="userProfile.sessions.title">Aktív Munkamenetek</span></h2>
                    <p class="mb-3" style="color:#fff;" data-i18n="userProfile.sessions.description">Az eszközök és böngészők, ahonnan jelenleg be vagy jelentkezve. Bármelyiket visszavonhatod.</p>

                    <div id="sessions-list" class="mb-3">
                        <div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> <span data-i18n="userProfile.sessions.loading">Betöltés...</span></div>
                    </div>

                    <button type="button" class="btn btn-danger" id="revoke-all-sessions-btn">
                        <i class="fas fa-sign-out-alt"></i> <span data-i18n="userProfile.sessions.revokeAll">Kijelentkezés minden más eszközről</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <script src="../../js/Main/popup.js"></script>
    <script>
    // === Jelszó megjelenítés toggle ===
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

    // === Aktív munkamenetek kezelése ===
    (function() {
        var sessionsUrl = '../../backend/ApiRequest/UserProfile/get_user_sessions.php';
        var listEl = document.getElementById('sessions-list');
        var revokeAllBtn = document.getElementById('revoke-all-sessions-btn');

        function timeAgo(dateStr) {
            if (!dateStr) return '–';
            var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
            if (diff < 60) return 'most';
            if (diff < 3600) return Math.floor(diff / 60) + ' perce';
            if (diff < 86400) return Math.floor(diff / 3600) + ' órája';
            return Math.floor(diff / 86400) + ' napja';
        }

        function renderSessions(sessions) {
            if (!sessions || sessions.length === 0) {
                listEl.innerHTML = '<div class="alert alert-info" data-i18n="userProfile.sessions.none">Nincs aktív munkamenet.</div>';
                return;
            }
            var html = '<div class="list-group">';
            sessions.forEach(function(s) {
                var badge = s.is_current
                    ? ' <span class="badge bg-success" data-i18n="userProfile.sessions.current">Ez az eszköz</span>'
                    : '';
                var revokeBtn = s.is_current
                    ? ''
                    : '<button class="btn btn-sm btn-outline-danger revoke-session-btn" data-id="' + s.id + '"><i class="fas fa-times"></i></button>';
                html += '<div class="list-group-item d-flex justify-content-between align-items-center">'
                    + '<div>'
                    + '<strong>' + escHtml(s.device) + '</strong>' + badge + '<br>'
                    + '<small class="text-muted">' + escHtml(s.location || s.ip_address || '–') + ' · '
                    + '<span data-i18n="userProfile.sessions.lastActive">Utolsó aktivitás</span>: ' + timeAgo(s.last_active_at) + '</small>'
                    + '</div>'
                    + revokeBtn
                    + '</div>';
            });
            html += '</div>';
            listEl.innerHTML = html;

            listEl.querySelectorAll('.revoke-session-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    revokeSession(parseInt(this.getAttribute('data-id')));
                });
            });
        }

        function escHtml(str) {
            var d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        function loadSessions() {
            fetch(sessionsUrl)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) renderSessions(data.sessions);
                    else listEl.innerHTML = '<div class="alert alert-warning">' + escHtml(data.message || 'Hiba') + '</div>';
                })
                .catch(function() {
                    listEl.innerHTML = '<div class="alert alert-danger">Nem sikerült betölteni a munkameneteket.</div>';
                });
        }

        function revokeSession(sessionId) {
            BmbPopup.confirm('Biztosan visszavonod ezt a munkamenetet?', function() {
                var fd = new FormData();
                fd.append('action', 'revoke');
                fd.append('session_id', sessionId);
                fetch(sessionsUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            BmbPopup.success(data.message || 'Munkamenet visszavonva.');
                            loadSessions();
                        } else {
                            BmbPopup.error(data.message || 'Hiba történt.');
                        }
                    })
                    .catch(function() { BmbPopup.error('Hálózati hiba!'); });
            });
        }

        if (revokeAllBtn) {
            revokeAllBtn.addEventListener('click', function() {
                BmbPopup.confirm('Biztosan kijelentkezel minden más eszközről?', function() {
                    var fd = new FormData();
                    fd.append('action', 'revoke_all');
                    fetch(sessionsUrl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                BmbPopup.success(data.message || 'Minden más munkamenet visszavonva.');
                                loadSessions();
                            } else {
                                BmbPopup.error(data.message || 'Hiba történt.');
                            }
                        })
                        .catch(function() { BmbPopup.error('Hálózati hiba!'); });
                });
            });
        }

        loadSessions();
    })();
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>

