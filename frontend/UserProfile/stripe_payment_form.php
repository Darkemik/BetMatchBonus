<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

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
    <script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
    <style>
        .payment-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .card-number-display {
            font-size: 24px;
            letter-spacing: 2px;
            height: 40px;
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-info {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #6c757d;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
    <?php include '../../frontend/Components/disclaimer.php'; ?>
    <?php require_once "../Components/header.php"; ?>

    <div class="container" style="max-width: 600px; margin-top: 40px; margin-bottom: 40px;">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="fas fa-credit-card"></i> Bankkártya Adatok</h3>
            </div>
            <div class="card-body p-4">

                <!-- Összeg kijelzés -->
                <div class="alert alert-info">
                    <h5>Befizetési Összeg</h5>
                    <h4 class="mb-0"><strong><?php echo number_format($amount, 0, ',', ' '); ?> FT</strong></h4>
                </div>

                <!-- Bankkártya megjelenítés -->
                <div class="payment-card">
                    <div style="font-size: 12px; opacity: 0.8;">BETMATCHBONUS</div>
                    <div class="card-number-display" id="cardDisplay">●●●● ●●●● ●●●● ●●●●</div>
                    <div class="card-info">
                        <div>
                            <div style="opacity: 0.7; font-size: 11px;">Kártyatulajdonos</div>
                            <div id="cardHolder">Adatok</div>
                        </div>
                        <div>
                            <div style="opacity: 0.7; font-size: 11px;">Lejárat</div>
                            <div id="cardExpiryDisplay">MM/YY</div>
                        </div>
                    </div>
                </div>

                <!-- Befizetési Form -->
                <form method="POST" action="../../backend/ApiRequest/stripe_payment_process.php">
                    <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">

                    <div class="form-group mb-3">
                        <label for="cardholderName">Kártyatulajdonos Neve *</label>
                        <input type="text" class="form-control" id="cardholderName" name="cardholder_name"
                            placeholder="Pl: Kovács János" required maxlength="50" pattern="[a-záéíóöőüűA-ZÁÉÍÓÖŐÜŰ\s]+" title="Csak betűket és szóközöket fogadunk el!">
                    </div>

                    <div class="form-group mb-3">
                        <label for="cardNumber">Kártya Szám *</label>
                        <input type="text" class="form-control" id="cardNumber" name="card_number"
                            placeholder="1234 5678 9012 3456" maxlength="19" inputmode="numeric" pattern="[0-9 ]{19}" title="Csak számokat adhatsz meg a kártyaszámhoz!" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="cardExpiryInput">Lejárat (MM/YY) *</label>
                                <input type="text" class="form-control" id="cardExpiryInput" name="card_expiry"
                                    placeholder="MM/YY" required maxlength="5">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="cardCVC">CVC *</label>
                                <input type="text" class="form-control" id="cardCVC" name="card_cvc" placeholder="123"
                                    maxlength="4" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <small><i class="fas fa-lock"></i> Az adataid a Stripe-on keresztül biztonságosan kerülnek
                            feldolgozásra. Ez az oldal nem tárja az érzékeny adatokat.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-2">
                        <i class="fas fa-check"></i> Befizetés Véghezvitele -
                        <?php echo number_format($amount, 0, ',', ' '); ?> FT
                    </button>
                    <a href="deposit.php" class="btn btn-secondary w-100">
                        <i class="fas fa-undo"></i> Mégsem
                    </a>

                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        <span>Stripe által biztosított biztonság</span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Lejárati dátum formázása (MM/YY) Vanilla JS-el
        const expiryInput = document.getElementById('cardExpiryInput');
        const expiryDisplay = document.getElementById('cardExpiryDisplay');

        expiryInput.addEventListener('input', function (e) {
            // Csak számokat engedünk át
            let value = e.target.value.replace(/\D/g, '');

            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }

            e.target.value = value;
            expiryDisplay.textContent = value || 'MM/YY';
        });

        // Real-time card number formatting
        const cardNumberInput = document.getElementById('cardNumber');
        const cardDisplay = document.getElementById('cardDisplay');

        cardNumberInput.addEventListener('input', function () {
            // Csak számjegyeket engedünk, max 16 karakterig
            let value = this.value.replace(/\D/g, '').substring(0, 16);
            let formattedValue = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            this.value = formattedValue;

            // Display masked
            let masked = value.replace(/\d(?=\d{4})/g, '●');
            let displayValue = masked.replace(/(\d{4})/g, '$1 ').replace(/(.{15})/g, '$1').trim();
            cardDisplay.textContent = displayValue || '●●●● ●●●● ●●●● ●●●●';
        });

        // Cardholder name - only letters and spaces
        const nameInput = document.getElementById('cardholderName');
        const cardHolder = document.getElementById('cardHolder');
        nameInput.addEventListener('input', function () {
            // Csak betűk és szóközök (magyar karakterek is)
            let value = this.value.replace(/[^a-záéíóöőüűA-ZÁÉÍÓÖŐÜŰ\s]/g, '');
            this.value = value;
            cardHolder.textContent = value.toUpperCase() || 'Adatok';
        });

        // Form submit validation
        document.querySelector('form').addEventListener('submit', function (e) {
            const expiryValue = document.getElementById('cardExpiryInput').value;
            const cardNumberValue = document.getElementById('cardNumber').value.replace(/\D/g, '');

            if (!/^\d{16}$/.test(cardNumberValue)) {
                e.preventDefault();
                alert('❌ A kártyaszámnak pontosan 16 számjegyből kell állnia!');
                return false;
            }
            
            // Check expiry date format
            if (!expiryValue.match(/^\d{2}\/\d{2}$/)) {
                e.preventDefault();
                alert('❌ Hibás lejárati dátum formátum! Használd: MM/YY');
                return false;
            }

            // Get current date
            const now = new Date();
            const currentMonth = String(now.getMonth() + 1).padStart(2, '0');
            const currentYear = String(now.getFullYear()).slice(-2);

            // Parse expiry date
            const [expiryMonth, expiryYear] = expiryValue.split('/');

            // Check if expiry date is in the future
            const currentDateStr = currentYear + currentMonth;
            const expiryDateStr = expiryYear + expiryMonth;

            if (parseInt(expiryDateStr) <= parseInt(currentDateStr)) {
                e.preventDefault();
                alert('❌ A kártya lejárati dátuma már lejárt! Minimum 04/26 szükséges.');
                return false;
            }
        });

        // CVC - only numbers
        const cvcInput = document.getElementById('cardCVC');
        cvcInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 4);
        });
    </script>

    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>