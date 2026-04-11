<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$method = isset($_GET['method']) ? htmlspecialchars($_GET['method']) : 'visa';

if (!in_array($method, ['visa', 'mastercard', 'paypal'])) {
    $method = 'visa';
}

// Validate amount
if ($amount < 3000 || $amount > 600000) {
    header("Location: deposit.php?error=invalid_amount");
    exit();
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bankkártya Adatok | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .payment-wrapper {
            max-width: 520px;
            margin: 40px auto 50px;
        }

        /* === KÁRTYA === */
        .credit-card {
            width: 100%;
            aspect-ratio: 1.586;
            border-radius: 16px;
            padding: 28px 30px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 14px 40px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            margin-bottom: 30px;
            transition: background 0.4s ease;
        }
        .credit-card::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -20%;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .credit-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -15%;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        .credit-card.visa-card {
            background: linear-gradient(135deg, #1a1f71 0%, #2e3b8e 50%, #4158a6 100%);
        }
        .credit-card.mastercard-card {
            background: linear-gradient(135deg, #1a1a2e 0%, #cc2b1c 50%, #eb001b 100%);
        }
        .credit-card.paypal-card {
            background: linear-gradient(135deg, #003087 0%, #005cbf 50%, #009cde 100%);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        .card-chip {
            width: 46px;
            height: 34px;
            background: linear-gradient(135deg, #d4a94b 0%, #c09a3e 50%, #e6c76a 100%);
            border-radius: 6px;
            position: relative;
        }
        .card-chip::after {
            content: '';
            position: absolute;
            top: 6px; left: 6px; right: 6px; bottom: 6px;
            border: 1px solid rgba(0,0,0,0.15);
            border-radius: 3px;
        }
        .card-brand {
            font-size: 2rem;
            opacity: 0.9;
        }

        .card-number-row {
            display: flex;
            gap: 16px;
            font-size: 1.35rem;
            font-family: 'Courier New', Courier, monospace;
            letter-spacing: 2.5px;
            font-weight: 600;
            position: relative;
            z-index: 1;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        .card-number-row .group { flex: 1; text-align: center; }
        .card-number-row.paypal-row {
            font-size: 1rem;
            letter-spacing: 0;
            justify-content: center;
            gap: 0;
        }
        .card-number-row.paypal-row .group {
            flex: none;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }

        .card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }
        .card-label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            margin-bottom: 2px;
        }
        .card-holder-name {
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .card-expiry-value {
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Courier New', Courier, monospace;
            letter-spacing: 1px;
        }

        /* === FORM === */
        .payment-form-card {
            background: #fff;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #e8e8e8;
        }
        .payment-form-card label {
            font-weight: 600;
            color: #333;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .payment-form-card .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .payment-form-card .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        .payment-form-card .form-control::placeholder { color: #bbb; }

        .amount-display {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        .amount-display .amount-label { color: #666; font-size: 0.85rem; font-weight: 600; }
        .amount-display .amount-value { font-size: 1.4rem; font-weight: 800; color: #333; }

        .pay-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-bottom: 10px;
        }
        .pay-btn.visa-btn { background: #1a1f71; color: #fff; }
        .pay-btn.visa-btn:hover { background: #2e3b8e; box-shadow: 0 4px 14px rgba(26,31,113,0.3); }
        .pay-btn.mastercard-btn { background: #eb001b; color: #fff; }
        .pay-btn.mastercard-btn:hover { background: #cc2b1c; box-shadow: 0 4px 14px rgba(235,0,27,0.3); }
        .pay-btn.paypal-btn { background: #003087; color: #fff; }
        .pay-btn.paypal-btn:hover { background: #005cbf; box-shadow: 0 4px 14px rgba(0,48,135,0.3); }

        .back-btn {
            width: 100%;
            padding: 12px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            color: #555;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .back-btn:hover { background: #e4e4e4; color: #333; }

        .security-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            color: #999;
            font-size: 0.78rem;
        }
        .security-footer i { color: #28a745; }

        /* PayPal stílus */
        .paypal-login-box {
            background: #f5f7fa;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .paypal-login-box .paypal-logo {
            text-align: center;
            margin-bottom: 16px;
        }
        .paypal-login-box .paypal-logo i {
            font-size: 2.6rem;
            color: #003087;
        }
        .paypal-login-box .paypal-logo span {
            display: block;
            font-size: 0.8rem;
            color: #888;
            margin-top: 4px;
        }
        .paypal-login-box .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
        }
        .paypal-login-box .form-control:focus {
            border-color: #003087;
            box-shadow: 0 0 0 3px rgba(0,48,135,0.1);
        }

        @media (max-width: 576px) {
            .payment-wrapper { margin: 20px 12px 40px; }
            .credit-card { padding: 20px; }
            .card-number-row { font-size: 1.05rem; gap: 8px; letter-spacing: 1px; }
        }
    </style>
</head>

<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
    <?php include '../../frontend/Components/disclaimer.php'; ?>
    <?php require_once "../Components/header.php"; ?>

    <div class="payment-wrapper">

        <!-- Kártya vizualizáció -->
        <div class="credit-card <?php echo $method; ?>-card" id="creditCard">
            <div class="card-top">
                <div class="card-chip"></div>
                <div class="card-brand">
                    <?php if ($method === 'visa'): ?>
                        <i class="fab fa-cc-visa"></i>
                    <?php elseif ($method === 'mastercard'): ?>
                        <i class="fab fa-cc-mastercard"></i>
                    <?php else: ?>
                        <i class="fab fa-cc-paypal"></i>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($method === 'paypal'): ?>
            <div class="card-number-row paypal-row" id="cardNumberDisplay">
                <span class="group" id="paypalEmailDisplay">pelda@email.com</span>
            </div>
            <div class="card-bottom">
                <div>
                    <div class="card-label">Fióktulajdonos</div>
                    <div class="card-holder-name" id="cardHolderDisplay">PAYPAL FIÓK</div>
                </div>
                <div style="text-align:right;">
                    <div class="card-label">Típus</div>
                    <div class="card-expiry-value">PAYPAL</div>
                </div>
            </div>
            <?php else: ?>
            <div class="card-number-row" id="cardNumberDisplay">
                <span class="group">••••</span>
                <span class="group">••••</span>
                <span class="group">••••</span>
                <span class="group">••••</span>
            </div>
            <div class="card-bottom">
                <div>
                    <div class="card-label">Kártyatulajdonos</div>
                    <div class="card-holder-name" id="cardHolderDisplay">NÉV</div>
                </div>
                <div style="text-align:right;">
                    <div class="card-label">Lejárat</div>
                    <div class="card-expiry-value" id="cardExpiryDisplay">MM/YY</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Form -->
        <div class="payment-form-card">

            <div class="amount-display">
                <span class="amount-label">Befizetési összeg</span>
                <span class="amount-value"><?php echo number_format($amount, 0, ',', ' '); ?> FT</span>
            </div>

            <?php if ($method === 'paypal'): ?>
                <!-- PayPal login form -->
                <div class="paypal-login-box">
                    <div class="paypal-logo">
                        <i class="fab fa-paypal"></i>
                        <span>Jelentkezz be a PayPal fiókoddal</span>
                    </div>
                    <form method="POST" action="../../backend/ApiRequest/stripe_payment_process.php" id="paypalForm">
                        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                        <input type="hidden" name="payment_method" value="paypal">

                        <div class="mb-3">
                            <label for="paypalEmail" style="font-weight:600;color:#333;font-size:0.85rem;">PayPal email cím</label>
                            <input type="email" class="form-control" id="paypalEmail" name="paypal_email"
                                placeholder="pelda@email.com" required>
                        </div>

                        <div class="mb-3">
                            <label for="paypalPassword" style="font-weight:600;color:#333;font-size:0.85rem;">PayPal jelszó</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="paypalPassword" name="paypal_password"
                                    placeholder="Jelszó" required>
                                <button type="button" class="btn btn-outline-secondary" id="togglePaypalPw" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="pay-btn paypal-btn">
                            <i class="fab fa-paypal"></i>&nbsp; Befizetés — <?php echo number_format($amount, 0, ',', ' '); ?> FT
                        </button>
                        <a href="deposit.php" class="back-btn" style="margin-top:10px;"><i class="fas fa-arrow-left"></i>&nbsp; Vissza</a>
                    </form>
                </div>
            <?php else: ?>
                <!-- Kártya form (Visa / Mastercard) -->
                <form method="POST" action="../../backend/ApiRequest/stripe_payment_process.php" id="cardForm">
                    <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                    <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($method); ?>">

                    <div class="mb-3">
                        <label for="cardholderName">Kártyatulajdonos neve</label>
                        <input type="text" class="form-control" id="cardholderName" name="cardholder_name"
                            placeholder="pl. Kovács János" required maxlength="50">
                    </div>

                    <div class="mb-3">
                        <label for="cardNumber">Kártyaszám</label>
                        <input type="text" class="form-control" id="cardNumber" name="card_number"
                            placeholder="0000 0000 0000 0000" maxlength="19" inputmode="numeric" required>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="cardExpiry">Lejárat</label>
                                <input type="text" class="form-control" id="cardExpiry" name="card_expiry"
                                    placeholder="MM/YY" required maxlength="5">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="cardCVC">CVC</label>
                                <input type="text" class="form-control" id="cardCVC" name="card_cvc"
                                    placeholder="•••" maxlength="4" inputmode="numeric" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="pay-btn <?php echo $method; ?>-btn">
                        <i class="fas fa-lock"></i>&nbsp; Befizetés — <?php echo number_format($amount, 0, ',', ' '); ?> FT
                    </button>
                    <a href="deposit.php" class="back-btn"><i class="fas fa-arrow-left"></i>&nbsp; Vissza</a>
                </form>
            <?php endif; ?>

            <div class="security-footer">
                <i class="fas fa-shield-alt"></i>
                <span>Biztonságos, titkosított fizetés</span>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        // Kártyaszám formázás + kártya frissítés
        var numInput = document.getElementById('cardNumber');
        var numDisplay = document.getElementById('cardNumberDisplay');

        if (numInput) {
            numInput.addEventListener('input', function() {
                var raw = this.value.replace(/\D/g, '').substring(0, 16);
                var formatted = raw.replace(/(\d{4})(?=\d)/g, '$1 ');
                this.value = formatted;

                var groups = ['••••','••••','••••','••••'];
                for (var i = 0; i < 4; i++) {
                    var chunk = raw.substring(i*4, (i+1)*4);
                    if (chunk.length > 0) {
                        groups[i] = chunk.padEnd(4, '•');
                    }
                }
                numDisplay.innerHTML = '<span class="group">' + groups.join('</span><span class="group">') + '</span>';
            });
        }

        // Név
        var nameInput = document.getElementById('cardholderName');
        var nameDisplay = document.getElementById('cardHolderDisplay');

        if (nameInput) {
            nameInput.addEventListener('input', function() {
                nameDisplay.textContent = this.value.toUpperCase() || 'NÉV';
            });
        }

        // Lejárat formázás
        var expInput = document.getElementById('cardExpiry');
        var expDisplay = document.getElementById('cardExpiryDisplay');

        if (expInput) {
            expInput.addEventListener('input', function() {
                var val = this.value.replace(/\D/g, '');
                if (val.length > 2) {
                    val = val.substring(0,2) + '/' + val.substring(2,4);
                }
                this.value = val;
                expDisplay.textContent = val || 'MM/YY';
            });
        }

        // CVC csak szám
        var cvcInput = document.getElementById('cardCVC');
        if (cvcInput) {
            cvcInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 4);
            });

            // CVC hover → kártya "megfordul" effekt
            cvcInput.addEventListener('focus', function() {
                document.getElementById('creditCard').style.opacity = '0.85';
            });
            cvcInput.addEventListener('blur', function() {
                document.getElementById('creditCard').style.opacity = '1';
            });
        }

        // Validáció
        var form = document.getElementById('cardForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                var num = document.getElementById('cardNumber').value.replace(/\D/g, '');
                if (num.length !== 16) {
                    e.preventDefault();
                    alert('A kártyaszámnak pontosan 16 számjegyből kell állnia!');
                    return;
                }

                var exp = document.getElementById('cardExpiry').value;
                if (!/^\d{2}\/\d{2}$/.test(exp)) {
                    e.preventDefault();
                    alert('Hibás lejárati dátum! Használd: MM/YY');
                    return;
                }

                var parts = exp.split('/');
                var month = parseInt(parts[0]);
                var year = parseInt(parts[1]);
                if (month < 1 || month > 12) {
                    e.preventDefault();
                    alert('Érvénytelen hónap!');
                    return;
                }

                var now = new Date();
                var curYear = now.getFullYear() % 100;
                var curMonth = now.getMonth() + 1;
                if (year < curYear || (year === curYear && month < curMonth)) {
                    e.preventDefault();
                    alert('A kártya lejárt!');
                    return;
                }

                var cvc = document.getElementById('cardCVC').value;
                if (cvc.length < 3) {
                    e.preventDefault();
                    alert('A CVC legalább 3 számjegy!');
                    return;
                }
            });
        }
    })();

    // PayPal form kezelés
    (function() {
        var ppEmail = document.getElementById('paypalEmail');
        var ppEmailDisplay = document.getElementById('paypalEmailDisplay');
        var ppHolderDisplay = document.getElementById('cardHolderDisplay');

        if (ppEmail) {
            ppEmail.addEventListener('input', function() {
                ppEmailDisplay.textContent = this.value || 'pelda@email.com';
                // Fiók név = email @ előtti rész
                var parts = this.value.split('@');
                ppHolderDisplay.textContent = (parts[0] || 'PAYPAL FIÓK').toUpperCase();
            });
        }

        // Szem gomb
        var toggleBtn = document.getElementById('togglePaypalPw');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                var pw = document.getElementById('paypalPassword');
                var icon = this.querySelector('i');
                if (pw.type === 'password') {
                    pw.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    pw.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
    })();
    </script>

    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>