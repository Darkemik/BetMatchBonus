<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Felhasználó aktuális egyenlege
$hasBonusBalance = false;
$hasWinningsBalance = false;
$colStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'bonus_balance'");
$colStmt->execute();
$colRes = $colStmt->get_result()->fetch_assoc();
$colStmt->close();
if ($colRes && (int)$colRes['cnt'] > 0) {
    $hasBonusBalance = true;
}

$winningsColStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'winnings_balance'");
$winningsColStmt->execute();
$winningsColRes = $winningsColStmt->get_result()->fetch_assoc();
$winningsColStmt->close();
if ($winningsColRes && (int)$winningsColRes['cnt'] > 0) {
    $hasWinningsBalance = true;
}

$query = "SELECT balance"
    . ($hasBonusBalance ? ", bonus_balance" : "")
    . ($hasWinningsBalance ? ", winnings_balance" : "")
    . " FROM Users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$balance = $user['balance'] ?? 0;
$bonus_balance = $hasBonusBalance ? ($user['bonus_balance'] ?? 0) : 0;
$winnings_balance = $hasWinningsBalance ? ($user['winnings_balance'] ?? 0) : 0;
$deposited_balance = max(0, (float)$balance - (float)$winnings_balance);
$total_deposit_and_winnings = (float)$deposited_balance + (float)$winnings_balance;
$total_all = $total_deposit_and_winnings + (float)$bonus_balance;
$stmt->close();

// Aktív befizetési bónuszok
$bonus_query = "SELECT ub.id, bc.name, bc.bonus_amount, bc.match_percent, bc.min_deposit, ub.granted_amount 
                FROM UserBonuses ub
                LEFT JOIN BonusCodes bc ON ub.bonus_id = bc.id
                WHERE ub.user_id = ? AND ub.status = 'PENDING' AND bc.bonus_trigger = 'DEPOSIT' 
                AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
                LIMIT 1";
$bonus_stmt = $conn->prepare($bonus_query);
$bonus_stmt->bind_param("i", $user_id);
$bonus_stmt->execute();
$bonus_result = $bonus_stmt->get_result();
$deposit_bonus = $bonus_result->fetch_assoc();
$bonus_stmt->close();
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Befizetés | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .payment-methods-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
        .payment-method-card {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 18px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
        }
        .payment-method-card:hover {
            border-color: #007bff;
            box-shadow: 0 3px 12px rgba(0,123,255,0.12);
        }
        .payment-method-card.selected {
            border-color: #007bff;
            background: #f0f7ff;
            box-shadow: 0 2px 10px rgba(0,123,255,0.15);
        }
        .payment-method-card.selected::after {
            content: '\f058';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 8px;
            right: 10px;
            color: #007bff;
            font-size: 14px;
        }
        .payment-method-card .pm-icon {
            font-size: 2.4rem;
            margin-bottom: 6px;
            display: block;
        }
        .payment-method-card .pm-icon.visa { color: #1a1f71; }
        .payment-method-card .pm-icon.mastercard { color: #eb001b; }
        .payment-method-card .pm-icon.paypal { color: #003087; }
        .payment-method-card .pm-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: #555;
        }
        .payment-method-card.selected .pm-name { color: #007bff; }

        .deposit-quick-btns { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .deposit-quick-btn {
            flex: 1;
            min-width: 80px;
            padding: 10px 8px;
            border: 2px solid #e0e0e0;
            background: #fff;
            color: #333;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }
        .deposit-quick-btn:hover {
            border-color: #007bff;
            color: #007bff;
            background: #f0f7ff;
        }
        .deposit-quick-btn.active {
            background: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        .deposit-input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        .deposit-input-wrapper input {
            background: #fff;
            border: 2px solid #ddd;
            color: #333;
            font-size: 1.5rem;
            font-weight: 700;
            padding: 14px 60px 14px 20px;
            border-radius: 10px;
            width: 100%;
            text-align: center;
        }
        .deposit-input-wrapper input:focus {
            border-color: #007bff;
            box-shadow: 0 0 8px rgba(0,123,255,0.15);
            outline: none;
        }
        .deposit-input-wrapper .currency-label {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #007bff;
            font-weight: 800;
            font-size: 1.1rem;
        }

        .deposit-submit-btn {
            width: 100%;
            padding: 14px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .deposit-submit-btn:hover {
            background: #0056b3;
            box-shadow: 0 4px 14px rgba(0,123,255,0.3);
        }

        .deposit-info-bar {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .deposit-info-bar i { color: #007bff; font-size: 1rem; }
        .deposit-info-bar span { color: #666; font-size: 0.85rem; }

        .deposit-section-title {
            color: #333;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        @media (max-width: 576px) {
            .payment-methods-grid { grid-template-columns: 1fr; }
            .deposit-quick-btns { flex-wrap: wrap; }
            .deposit-quick-btn { min-width: 60px; flex: 0 0 calc(33% - 8px); }
        }
    </style></head>

<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
    <?php include '../../frontend/Components/disclaimer.php'; ?>
    <?php require_once "../Components/header.php"; ?>
    <div class="container profile-container">
        <div class="row">
            <div class="col-md-3">
                <nav class="profile-sidebar">
                    <a href="personal_data.php" class="profile-nav-item"><i class="fas fa-user"></i> Személyes
                        Adatok</a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> Jelszó
                        Módosítás</a>
                    <a href="deposit.php" class="profile-nav-item active"><i class="fas fa-plus-circle"></i>
                        Befizetés</a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> Kifizetés</a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i>
                        Tranzakciótörténet</a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> Bónuszaim</a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> Napló</a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i
                            class="fas fa-sign-out-alt"></i> Kijelentkezés</a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-plus-circle"></i> Befizetés</h1>

                    <!-- Egyenleg kártyák -->
                    <div class="alert alert-info py-3">
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <div class="p-2 rounded" style="background: rgba(13, 110, 253, 0.12); border: 1px solid rgba(13, 110, 253, 0.25);">
                                    <div style="font-weight:700; font-size: 0.9rem;">BEFIZETETT EGYENLEG</div>
                                    <div class="mt-1"><span class="badge bg-secondary"><?php echo number_format($deposited_balance, 0, ',', ' '); ?> FT</span></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-2 rounded" style="background: rgba(25, 135, 84, 0.12); border: 1px solid rgba(25, 135, 84, 0.25);">
                                    <div style="font-weight:700; font-size: 0.9rem;">NYEREMÉNYEGYENLEG</div>
                                    <div class="mt-1"><span class="badge bg-success"><?php echo number_format($winnings_balance, 0, ',', ' '); ?> FT</span></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-2 rounded" style="background: rgba(13, 110, 253, 0.12); border: 1px solid rgba(13, 110, 253, 0.25);">
                                    <div style="font-weight:700; font-size: 0.9rem;">BEFIZETETT ÉS NYEREMÉNYEGYENLEG ÖSSZESEN</div>
                                    <div class="mt-1"><span class="badge bg-primary"><?php echo number_format($total_deposit_and_winnings, 0, ',', ' '); ?> FT</span></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-2 rounded" style="background: rgba(255, 193, 7, 0.14); border: 1px solid rgba(255, 193, 7, 0.35);">
                                    <div style="font-weight:700; font-size: 0.9rem;">BÓNUSZ EGYENLEG (NEM KIUTALHATÓ)</div>
                                    <div class="mt-1"><span class="badge bg-warning text-dark"><?php echo number_format($bonus_balance, 0, ',', ' '); ?> FT</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Befizetési bónusz kijelzés -->
                    <?php if ($deposit_bonus): ?>
                        <div class="deposit-info-bar" style="border-color: rgba(40,167,69,0.3); background: rgba(40,167,69,0.06);">
                            <i class="fas fa-gift" style="color:#28a745;"></i>
                            <span>
                                <strong style="color:#28a745;"><?php echo htmlspecialchars($deposit_bonus['name']); ?></strong> —
                                <?php
                                if ($deposit_bonus['match_percent']) {
                                    echo number_format($deposit_bonus['match_percent'], 0) . "% bónusz a befizetésből";
                                } else {
                                    echo "Bónusz: " . number_format($deposit_bonus['granted_amount'], 0, ',', ' ') . " FT";
                                }
                                if ($deposit_bonus['min_deposit']) {
                                    echo " (min. " . number_format($deposit_bonus['min_deposit'], 0, ',', ' ') . " FT)";
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" onsubmit="return handleDepositSubmit(event)">

                        <!-- Fizetési módok -->
                        <div class="deposit-section-title">Fizetési mód választása</div>
                        <div class="payment-methods-grid">
                            <div class="payment-method-card selected" data-method="visa" onclick="selectPaymentMethod(this)">
                                <i class="fab fa-cc-visa pm-icon visa"></i>
                                <div class="pm-name">Visa</div>
                            </div>
                            <div class="payment-method-card" data-method="mastercard" onclick="selectPaymentMethod(this)">
                                <i class="fab fa-cc-mastercard pm-icon mastercard"></i>
                                <div class="pm-name">Mastercard</div>
                            </div>
                            <div class="payment-method-card" data-method="paypal" onclick="selectPaymentMethod(this)">
                                <i class="fab fa-cc-paypal pm-icon paypal"></i>
                                <div class="pm-name">PayPal</div>
                            </div>
                        </div>
                        <input type="hidden" id="payment_method" name="payment_method" value="visa">

                        <!-- Összeg -->
                        <div class="deposit-section-title">Befizetési összeg</div>
                        <div class="deposit-input-wrapper">
                            <input type="number" id="amount" name="amount" min="3000" max="600000" step="1" required value="3000">
                            <span class="currency-label">FT</span>
                        </div>

                        <!-- Gyors összegek -->
                        <div class="deposit-quick-btns">
                            <button type="button" class="deposit-quick-btn" data-amount="3000">3 000</button>
                            <button type="button" class="deposit-quick-btn" data-amount="5000">5 000</button>
                            <button type="button" class="deposit-quick-btn" data-amount="10000">10 000</button>
                            <button type="button" class="deposit-quick-btn" data-amount="20000">20 000</button>
                            <button type="button" class="deposit-quick-btn" data-amount="50000">50 000</button>
                        </div>

                        <div class="deposit-info-bar">
                            <i class="fas fa-shield-alt"></i>
                            <span>A befizetés biztonságosan, titkosított kapcsolaton keresztül kerül feldolgozásra. Minimális: <strong>3 000 FT</strong> | Maximális: <strong>600 000 FT</strong></span>
                        </div>

                        <button type="submit" class="deposit-submit-btn">
                            <i class="fas fa-lock"></i>&nbsp; Biztonságos Befizetés
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fizetési mód választás
        function selectPaymentMethod(card) {
            document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            document.getElementById('payment_method').value = card.dataset.method;
        }

        // Gyors összeg gombok
        document.addEventListener('DOMContentLoaded', function () {
            const quickBtns = document.querySelectorAll('.deposit-quick-btn');
            const amountInput = document.getElementById('amount');

            quickBtns.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    amountInput.value = this.dataset.amount;
                    quickBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });

        // Form submit
        function handleDepositSubmit(event) {
            event.preventDefault();
            const amount = document.getElementById('amount').value;
            const method = document.getElementById('payment_method').value;

            if (amount < 3000 || amount > 600000) {
                alert('Az összeg nem megfelelő! (3 000 - 600 000 FT)');
                return false;
            }

            window.location.href = 'stripe_payment_form.php?amount=' + amount + '&method=' + method;
            return false;
        }
    </script>

    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>