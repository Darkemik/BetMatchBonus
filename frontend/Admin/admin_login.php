<?php session_start(); ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Admin Bejelentkezés | BetMatchBonus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body {
            background: #1a1a2e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #16213e;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .login-card img { width: 60px; }
        .login-card h2 { color: #e94560; }
        .login-card label { color: #ccc; }
        .login-card .form-control {
            background: #0f3460;
            border: 1px solid #333;
            color: #fff;
        }
        .login-card .form-control:focus {
            border-color: #e94560;
            box-shadow: 0 0 0 0.2rem rgba(233,69,96,0.25);
        }
        .login-card .btn-admin {
            background: #e94560;
            border: none;
            color: #fff;
            font-weight: bold;
        }
        .login-card .btn-admin:hover { background: #c73652; }
        #loginResult { min-height: 24px; }
    </style>
</head>
<body>

<div class="login-card text-center">
    <img src="../../img/logo.png" alt="logo" class="mb-3">
    <h2 class="mb-4">Admin Panel</h2>

    <form id="adminLoginForm">
        <div class="mb-3 text-start">
            <label class="form-label">Felhasználónév vagy email</label>
            <input type="text" name="login" class="form-control" required>
        </div>

        <div class="mb-3 text-start">
            <label class="form-label">Jelszó</label>
            <div class="input-group">
                <input type="password" name="password" id="adminPassword" class="form-control" required>
                <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">👁</button>
            </div>
        </div>

        <p id="loginResult"></p>

        <button type="submit" class="btn btn-admin w-100">Bejelentkezés</button>
    </form>

    <a href="../../frontend/MainMenu/MainMenu.php" class="d-block mt-3 small" style="color:#fff;">← Vissza a főoldalra</a>
</div>

<script>
// Jelszó mutatás
document.getElementById('togglePassword').addEventListener('click', function() {
    var pw = document.getElementById('adminPassword');
    pw.type = pw.type === 'password' ? 'text' : 'password';
});

// Login submit
document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var result = document.getElementById('loginResult');
    var fd = new FormData(this);

    result.textContent = 'Bejelentkezés...';
    result.style.color = '#ccc';

    fetch('/BetMatchBonus/backend/Auth/admin_login.php', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            result.style.color = 'lime';
            result.textContent = data.message;
            setTimeout(function() {
                window.location.href = '/BetMatchBonus/frontend/Admin/dashboard.php';
            }, 800);
        } else {
            result.style.color = 'red';
            result.textContent = data.message;
        }
    })
    .catch(function(err) {
        result.style.color = 'red';
        result.textContent = 'Hiba történt.';
        console.error(err);
    });
});
</script>

</body>
</html>