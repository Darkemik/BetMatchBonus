<?php
require_once "../../backend/connect.php";

$token = $_GET['token'] ?? '';
$error = '';
$valid = false;
$username = '';

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $error = 'Érvénytelen vagy hiányzó visszaállítási link.';
} else {
    $stmt = $conn->prepare("SELECT id, username FROM Users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $error = 'A visszaállítási link lejárt vagy érvénytelen. Kérjük, igényelj újat!';
    } else {
        $valid = true;
        $username = htmlspecialchars($user['username']);
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jelszó visszaállítás – BetMatchBonus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #1a1a2e;
            color: #eee;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .reset-card {
            background: #16213e;
            padding: 40px;
            border-radius: 16px;
            width: 90%;
            max-width: 440px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .reset-card h2 {
            color: #f5c518;
            text-align: center;
            margin-bottom: 8px;
        }
        .reset-card .subtitle {
            text-align: center;
            color: #889;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .reset-card label {
            color: #ccc;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .reset-card .form-control {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
        }
        .reset-card .form-control:focus {
            border-color: #f5c518;
            box-shadow: 0 0 0 3px rgba(245,197,24,0.15);
            background: rgba(255,255,255,0.08);
        }
        .reset-card .btn-reset {
            background: linear-gradient(135deg, #f5c518, #f39c12);
            color: #1a1a2e;
            font-weight: 700;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s;
        }
        .reset-card .btn-reset:hover {
            background: linear-gradient(135deg, #f7d34a, #f5a623);
            transform: translateY(-1px);
        }
        .reset-card .logo-area {
            text-align: center;
            margin-bottom: 20px;
        }
        .reset-card .logo-area img {
            width: 60px;
            height: 60px;
        }
        .error-card {
            text-align: center;
        }
        .error-card .icon { font-size: 48px; color: #e74c3c; margin-bottom: 16px; }
        .success-card .icon { font-size: 48px; color: #28a745; margin-bottom: 16px; }
        #resultMsg { margin-top: 12px; font-size: 14px; }
        .toggle-password {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: #ccc;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="logo-area">
            <a href="../../frontend/MainMenu/MainMenu.php">
                <img src="../../img/logo.png" alt="BetMatchBonus">
            </a>
        </div>

        <?php if (!$valid): ?>
            <div class="error-card">
                <div class="icon">❌</div>
                <h2>Hiba</h2>
                <p style="color:#ccc;"><?= $error ?></p>
                <a href="../../frontend/MainMenu/MainMenu.php" class="btn btn-outline-warning mt-3">Vissza a főoldalra</a>
            </div>
        <?php else: ?>
            <h2><i class="fas fa-key"></i> Új jelszó</h2>
            <p class="subtitle">Kedves <strong><?= $username ?></strong>, adj meg egy új jelszót!</p>

            <form id="resetPasswordForm">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <label class="form-label">Új jelszó</label>
                <div class="input-group mb-3">
                    <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="Legalább 7 karakter" required minlength="7">
                    <button type="button" class="btn toggle-password" data-target="newPassword" tabindex="-1">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <label class="form-label">Új jelszó újra</label>
                <div class="input-group mb-3">
                    <input type="password" id="newPassword2" class="form-control" placeholder="Jelszó megerősítése" required minlength="7">
                    <button type="button" class="btn toggle-password" data-target="newPassword2" tabindex="-1">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <p id="resultMsg"></p>

                <button type="submit" class="btn-reset">
                    <i class="fas fa-check-circle"></i> Jelszó mentése
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var target = document.getElementById(this.getAttribute('data-target'));
                if (target) {
                    target.type = target.type === 'password' ? 'text' : 'password';
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                }
            });
        });

        var form = document.getElementById('resetPasswordForm');
        if (!form) return;

        var resultMsg = document.getElementById('resultMsg');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var pw1 = document.getElementById('newPassword').value;
            var pw2 = document.getElementById('newPassword2').value;

            if (pw1.length < 7) {
                resultMsg.style.color = '#e74c3c';
                resultMsg.textContent = 'A jelszó legalább 7 karakter legyen!';
                return;
            }

            if (pw1 !== pw2) {
                resultMsg.style.color = '#e74c3c';
                resultMsg.textContent = 'A két jelszó nem egyezik!';
                return;
            }

            resultMsg.style.color = '#f5c518';
            resultMsg.textContent = 'Feldolgozás...';

            var fd = new FormData(form);
            fetch('../../backend/Auth/process_reset_password.php', {
                method: 'POST',
                body: fd
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    resultMsg.style.color = '#28a745';
                    resultMsg.textContent = data.message;
                    form.querySelector('button[type="submit"]').disabled = true;
                    setTimeout(function() {
                        window.location.href = '../../frontend/MainMenu/MainMenu.php';
                    }, 3000);
                } else {
                    resultMsg.style.color = '#e74c3c';
                    resultMsg.textContent = data.message;
                }
            })
            .catch(function(err) {
                resultMsg.style.color = '#e74c3c';
                resultMsg.textContent = 'Hiba történt. Próbáld újra!';
                console.error(err);
            });
        });
    });
    </script>
</body>
</html>
