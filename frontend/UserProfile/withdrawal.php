<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';

function normalize_name($name) {
    $name = preg_replace('/\s+/u', ' ', trim((string)$name));
    return mb_strtolower($name, 'UTF-8');
}

// Felhasználó aktuális egyenlege
$hasWinningsBalance = false;
$hasBonusBalance = false;
$winningsColStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'winnings_balance'");
$winningsColStmt->execute();
$winningsColRes = $winningsColStmt->get_result()->fetch_assoc();
$winningsColStmt->close();
if ($winningsColRes && (int)$winningsColRes['cnt'] > 0) {
    $hasWinningsBalance = true;
}

$bonusColStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'bonus_balance'");
$bonusColStmt->execute();
$bonusColRes = $bonusColStmt->get_result()->fetch_assoc();
$bonusColStmt->close();
if ($bonusColRes && (int)$bonusColRes['cnt'] > 0) {
    $hasBonusBalance = true;
}

$query = $hasWinningsBalance
    ? "SELECT balance, winnings_balance" . ($hasBonusBalance ? ", bonus_balance" : "") . ", full_name FROM Users WHERE id = ?"
    : "SELECT balance, full_name FROM Users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$balance = $user['balance'] ?? 0;
$winnings_balance = $hasWinningsBalance ? (float)($user['winnings_balance'] ?? 0) : (float)$balance;
$bonus_balance = $hasBonusBalance ? (float)($user['bonus_balance'] ?? 0) : 0.0;
$deposited_balance = max(0, (float)$balance - (float)$winnings_balance);
$total_deposit_and_winnings = (float)$deposited_balance + (float)$winnings_balance;
$registered_full_name = $user['full_name'] ?? '';
$stmt->close();

// POST kérelmen kivét feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = htmlspecialchars($_POST['payment_method'] ?? '');
    $account_holder = trim($_POST['account_holder'] ?? '');
    $account_number = htmlspecialchars($_POST['account_number'] ?? '');
    $agreement = isset($_POST['agreement']) ? true : false;

    if ($amount <= 0) {
        $error_message = "A kifizetési összeg nagyobb kell legyen, mint 0!";
    } elseif ($amount > $winnings_balance) {
        $error_message = "A kifizetés csak a nyereményegyenlegből történhet!";
    } elseif ($payment_method !== 'bank_transfer') {
        $error_message = "Csak banki átutalás lehetséges!";
    } elseif (empty($account_holder) || empty($account_number)) {
        $error_message = "Tölts ki minden szükséges mezőt!";
    } elseif (normalize_name($account_holder) !== normalize_name($registered_full_name)) {
        $error_message = "Számlán Szereplő Név nem egyezik a regisztráció során megadott Teljes névvel!";
    } elseif (!$agreement) {
        $error_message = "El kell fogadnod a nyereménykifizetési nyilatkozatot!";
    } elseif (!preg_match('/^\d{8}-?\d{8}$/', str_replace(' ', '', $account_number))) {
        $error_message = "A bankszámlaszám nem érvényes! Helyes formátum: 16 számjegy vagy XXXXXXXX-XXXXXXXX";
    } else {
        // Kifizetési kérelem feldolgozása
        $transaction_id = uniqid('WTH_');
        $type = 'withdrawal';
        $status = 'completed';
        $insert_query = "INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("issdss", $user_id, $type, $amount, $payment_method, $status, $transaction_id);
        
        if ($insert_stmt->execute()) {
            // Kifizetés csak a nyereményegyenlegből lehetséges.
            $update_query = $hasWinningsBalance
                ? "UPDATE Users SET balance = balance - ?, winnings_balance = winnings_balance - ? WHERE id = ?"
                : "UPDATE Users SET balance = balance - ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            if ($hasWinningsBalance) {
                $update_stmt->bind_param("ddi", $amount, $amount, $user_id);
            } else {
                $update_stmt->bind_param("di", $amount, $user_id);
            }
            $update_stmt->execute();
            $update_stmt->close();
            
            $_SESSION['success_message'] = "Kifizetési kérelem feldolgozva! -FT " . number_format($amount, 0, ',', ' ') . " Azonosító: " . $transaction_id;
            header("Location: withdrawal.php");
            exit();
        } else {
            $error_message = "Hiba a kifizetés feldolgozása során!";
        }
        $insert_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kifizetés | BetMatchBonus</title>
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
                    <a href="withdrawal.php" class="profile-nav-item active"><i class="fas fa-minus-circle"></i> Kifizetés</a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> Tranzakciótörténet</a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> Bónuszaim</a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> Napló</a>
                    <a href="../../backend/Auth/logout.php" class="profile-nav-item logout"><i class="fas fa-sign-out-alt"></i> Kijelentkezés</a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-minus-circle"></i> Kifizetés</h1>
                    
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
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
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
                            <label for="amount">Kifizetési Összeg (FT)</label>
                            <input type="number" class="form-control" id="amount" name="amount" min="6000" step="1" max="<?php echo $winnings_balance; ?>" required value="6000">
                            <small class="form-text text-white">Minimális kifizetés: <strong>6000 FT</strong> | Maximum (nyereményegyenleg): <?php echo number_format($winnings_balance, 0, ',', ' '); ?> FT</small>
                        </div>
                        
                        <!-- Gyors összeg gombók -->
                        <div class="form-group mb-3">
                            <label>Gyors Összegek</label>
                            <div class="quick-amount-buttons">
                                <button type="button" class="quick-amount-btn" data-amount="7500">
                                    <i class="fas fa-hand-holding-usd"></i> 7500
                                </button>
                                <button type="button" class="quick-amount-btn" data-amount="10000">
                                    <i class="fas fa-hand-holding-usd"></i> 10000
                                </button>
                                <button type="button" class="quick-amount-btn" data-amount="15000">
                                    <i class="fas fa-hand-holding-usd"></i> 15000
                                </button>
                                <button type="button" class="quick-amount-btn" data-amount="20000">
                                    <i class="fas fa-hand-holding-usd"></i> 20000
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="payment_method">Fizetési Mód</label>
                            <select class="form-control" id="payment_method" name="payment_method" required>
                                <option value="bank_transfer" selected>Banki Átutalás</option>
                            </select>
                            <small class="form-text text-white">Kifizetés csak banki átutaláson keresztül lehetséges</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="account_holder">Számlán Szereplő Név</label>
                            <input type="text" class="form-control" id="account_holder" name="account_holder" required placeholder="Írd be a számlán szereplő nevet">
                            <small class="form-text text-white">A névnek pontosan egyeznie kell a regisztrációkor megadott teljes névvel.</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="account_number">Bankszámlaszám</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" required placeholder="Pl: 12345678-87654321">
                            <small class="form-text text-white">Magyar bankszámlaszám: Minimum 16, maximum 24 számjegy (pl: 12345678-87654321)</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agreement" name="agreement" required>
                                <label class="form-check-label" for="agreement">
                                    Nyereményem kiutalását a saját nevemre szóló, törvényi előírásnak megfelelő fizetési számlára kezdeményezem.
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <small><i class="fas fa-check-circle"></i> A kifizetés egy ADMIN által azonnal feldolgozásra kerül. A pénz 1-3 munkanapig feldolgozódhat.</small>
                        </div>
                        
                        <button type="submit" name="submit_withdrawal" class="btn btn-primary"><i class="fas fa-arrow-down"></i> Kifizetés Kérelmezése</button>
                        <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Vissza</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const accHolder = document.getElementById('account_holder');
            if (accHolder) {
                accHolder.addEventListener('input', function(e) {
                    // Csak betűk, szóközök, kötőjelek és pontok engedélyezése (számok kizárva)
                    e.target.value = e.target.value.replace(/[^a-zA-ZáéíóöőüűÁÉÍÓÖŐÜŰ\s.-]/g, '');
                });
            }

            const accNumber = document.getElementById('account_number');
            if (accNumber) {
                accNumber.addEventListener('input', function(e) {
                    // Csak számokat engedünk (a kötőjelet átmenetileg kiszedjük, hogy újraformázzuk)
                    let val = e.target.value.replace(/\D/g, '');
                    
                    // Magyar bankszámla: 16 vagy 24 számjegy lehet (8-8 vagy 8-8-8)
                    // Maximum 24 számjegyet engedünk
                    if (val.length > 24) {
                        val = val.substring(0, 24);
                    }

                    // Kötőjelek beillesztése minden 8. karakter után
                    let formatted = '';
                    for (let i = 0; i < val.length; i++) {
                        if (i > 0 && i % 8 === 0) {
                            formatted += '-';
                        }
                        formatted += val[i];
                    }

                    e.target.value = formatted;
                });
            }
        });
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
