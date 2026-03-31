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
$colStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'bonus_balance'");
$colStmt->execute();
$colRes = $colStmt->get_result()->fetch_assoc();
$colStmt->close();
if ($colRes && (int)$colRes['cnt'] > 0) {
    $hasBonusBalance = true;
}

$query = $hasBonusBalance
    ? "SELECT balance, bonus_balance FROM Users WHERE id = ?"
    : "SELECT balance FROM Users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$balance = $user['balance'] ?? 0;
$bonus_balance = $hasBonusBalance ? ($user['bonus_balance'] ?? 0) : 0;
$stmt->close();

// Aktív befizetési bónuszok
$bonus_query = "SELECT ub.id, bc.name, bc.bonus_amount, bc.match_percent, bc.min_deposit, ub.granted_amount 
                FROM UserBonuses ub
                LEFT JOIN BonusCodes bc ON ub.bonus_id = bc.id
                WHERE ub.user_id = ? AND ub.status = 'ACTIVE' AND bc.bonus_trigger = 'DEPOSIT' 
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
</head>

<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
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
                    <a href="../../backend/Auth/logout.php" class="profile-nav-item logout"><i
                            class="fas fa-sign-out-alt"></i> Kijelentkezés</a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-plus-circle"></i> Befizetés</h1>

                    <div class="alert alert-info">
                        <strong>Jelenlegi egyenlege:</strong> <span
                            class="badge bg-success"><?php echo number_format($balance, 0, ',', ' '); ?> FT</span>
                        <?php if ($bonus_balance > 0): ?>
                        &nbsp;|&nbsp;
                        <strong>Bónusz egyenleg:</strong> <span
                            class="badge bg-warning text-dark"><?php echo number_format($bonus_balance, 0, ',', ' '); ?> FT</span>
                        <?php endif; ?>
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
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <h5><i class="fas fa-gift"></i> Aktív Befizetési Bónusz</h5>
                            <p class="mb-2"><strong><?php echo htmlspecialchars($deposit_bonus['name']); ?></strong></p>
                            <p class="mb-2">
                                <?php
                                if ($deposit_bonus['match_percent']) {
                                    echo "Bónusz: " . number_format($deposit_bonus['match_percent'], 0) . "% a befizetésből";
                                } else {
                                    echo "Bónusz érték: FT " . number_format($deposit_bonus['granted_amount'], 0, ',', ' ');
                                }
                                ?>
                            </p>
                            <p class="mb-0">
                                <?php
                                if ($deposit_bonus['min_deposit']) {
                                    echo "Minimális befizetés: FT " . number_format($deposit_bonus['min_deposit'], 0, ',', ' ');
                                }
                                ?>
                            </p>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="profile-form" onsubmit="return handleDepositSubmit(event)">
                        <div class="form-group mb-3">
                            <label for="amount">Befizetési Összeg (FT)</label>
                            <input type="number" class="form-control" id="amount" name="amount" min="3000" max="600000"
                                step="1" required value="3000">
                            <small class="form-text text-white">Minimális: 3000 FT | Maximális: 600 000 FT</small>
                        </div>

                        <!-- Gyors összeg gombók -->
                        <div class="form-group mb-3">
                            <label>Gyors Összegek</label>
                            <div class="quick-amount-buttons">
                                <button type="button" class="quick-amount-btn" data-amount="5000">
                                    <i class="fas fa-hand-holding-usd"></i> 5000
                                </button>
                                <button type="button" class="quick-amount-btn" data-amount="7500">
                                    <i class="fas fa-hand-holding-usd"></i> 7500
                                </button>
                                <button type="button" class="quick-amount-btn" data-amount="10000">
                                    <i class="fas fa-hand-holding-usd"></i> 10000
                                </button>
                                <button type="button" class="quick-amount-btn" data-amount="20000">
                                    <i class="fas fa-hand-holding-usd"></i> 20000
                                </button>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <small><i class="fas fa-check-circle"></i> A befizetés biztonságosan a Stripe-on keresztül
                                kerül feldolgozásra.</small>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-credit-card"></i> Befizetés
                            Folytatása</button>
                        <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Vissza</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quick amount buttons
        document.addEventListener('DOMContentLoaded', function () {
            const quickBtns = document.querySelectorAll('.quick-amount-btn');
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

        // Form submit - redirect to payment form
        function handleDepositSubmit(event) {
            event.preventDefault();
            const amount = document.getElementById('amount').value;

            if (amount < 3000 || amount > 600000) {
                alert('Az összeg nem megfelelő!');
                return false;
            }

            // Redirect to payment form
            window.location.href = 'stripe_payment_form.php?amount=' + amount;
            return false;
        }
    </script>

    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>