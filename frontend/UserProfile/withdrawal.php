<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";
require_once "../../backend/mail_config.php";
require_once "../../backend/PHPMailer/Exception.php";
require_once "../../backend/PHPMailer/PHPMailer.php";
require_once "../../backend/PHPMailer/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

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
    ? "SELECT balance, winnings_balance" . ($hasBonusBalance ? ", bonus_balance" : "") . ", full_name, data_verified FROM Users WHERE id = ?"
    : "SELECT balance, full_name, data_verified FROM Users WHERE id = ?";
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
$data_verified = (int)($user['data_verified'] ?? 0);
$stmt->close();

// Függőben lévő kifizetési kérelmek lekérdezése
$pendingStmt = $conn->prepare("SELECT id, transaction_id, amount, created_at FROM Transactions WHERE user_id = ? AND type = 'withdrawal' AND status = 'pending' ORDER BY created_at DESC");
$pendingStmt->bind_param("i", $user_id);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pending_withdrawals = $pendingResult->fetch_all(MYSQLI_ASSOC);
$pendingStmt->close();

// Kifizetés visszavonása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_withdrawal'])) {
    $cancel_id = (int)($_POST['cancel_id'] ?? 0);
    if ($cancel_id > 0) {
        // Ellenőrizzük, hogy a tranzakció a felhasználóé és pending
        $checkStmt = $conn->prepare("SELECT id, amount FROM Transactions WHERE id = ? AND user_id = ? AND type = 'withdrawal' AND status = 'pending' LIMIT 1");
        $checkStmt->bind_param("ii", $cancel_id, $user_id);
        $checkStmt->execute();
        $cancelTx = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($cancelTx) {
            $conn->begin_transaction();
            try {
                // Státusz frissítése
                $updStmt = $conn->prepare("UPDATE Transactions SET status = 'cancelled', approval_token = NULL, updated_at = NOW() WHERE id = ?");
                $updStmt->bind_param("i", $cancelTx['id']);
                $updStmt->execute();
                $updStmt->close();

                // Egyenleg visszaírása
                if ($hasWinningsBalance) {
                    $balStmt = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
                    $balStmt->bind_param("ddi", $cancelTx['amount'], $cancelTx['amount'], $user_id);
                } else {
                    $balStmt = $conn->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
                    $balStmt->bind_param("di", $cancelTx['amount'], $user_id);
                }
                $balStmt->execute();
                $balStmt->close();

                $conn->commit();
                $_SESSION['success_message'] = '✅ A kifizetési kérelem sikeresen visszavonva. Az összeg visszakerült az egyenlegedre.';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Hiba történt a visszavonás során.';
            }
        } else {
            $error_message = 'A kifizetési kérelem nem található vagy már feldolgozásra került.';
        }
    }
    header("Location: withdrawal.php");
    exit();
}

// POST kérelmen kivét feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = htmlspecialchars($_POST['payment_method'] ?? '');
    $account_holder = trim($_POST['account_holder'] ?? '');
    $account_number = htmlspecialchars($_POST['account_number'] ?? '');
    $agreement = isset($_POST['agreement']) ? true : false;

    if (!$data_verified) {
        $error_message = "A kifizetéshez először az adminnak ellenőriznie kell a személyes adataidat! Kérjük, a Személyes Adatok oldalon kérd az ellenőrzést.";
    } elseif ($amount <= 0) {
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
        // Kifizetési kérelem feldolgozása — PENDING státuszba kerül, admin jóváhagyásra vár
        $transaction_id = uniqid('WTH_');
        $approval_token = bin2hex(random_bytes(32));
        $type = 'withdrawal';
        $status = 'pending';
        $insert_query = "INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id, approval_token, account_holder, account_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("isdssssss", $user_id, $type, $amount, $payment_method, $status, $transaction_id, $approval_token, $account_holder, $account_number);
        
        if ($insert_stmt->execute()) {
            // Egyenleg zárolása (levonás azonnal, hogy ne költhesse el)
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

            // Felhasználó email címe
            $userStmt = $conn->prepare("SELECT username, email FROM Users WHERE id = ?");
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userData = $userStmt->get_result()->fetch_assoc();
            $userStmt->close();
            $user_username = $userData['username'] ?? 'Ismeretlen';
            $user_email = $userData['email'] ?? '';

            // Email küldése az adminnak
            $approve_url = SITE_BASE_URL . '/backend/Auth/approve_withdrawal.php?token=' . $approval_token . '&action=approve';
            $reject_url = SITE_BASE_URL . '/backend/Auth/approve_withdrawal.php?token=' . $approval_token . '&action=reject';

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = MAIL_SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_SMTP_USERNAME;
                $mail->Password   = MAIL_SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_SMTP_PORT;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                $mail->addAddress('bmbugyfelszolgalat@gmail.com', 'BetMatchBonus Admin');
                $mail->isHTML(true);
                $mail->Subject = 'Kifizetési kérelem - ' . $user_username . ' - ' . number_format($amount, 0, ',', ' ') . ' FT';
                $mail->Body = '
                <html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px;">
                <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                    <div style="background:#007bff;color:#fff;padding:20px 30px;">
                        <h2 style="margin:0;">💰 Új kifizetési kérelem</h2>
                    </div>
                    <div style="padding:25px 30px;">
                        <table style="width:100%;border-collapse:collapse;">
                            <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Felhasználó:</td><td style="padding:8px 0;">' . htmlspecialchars($user_username) . '</td></tr>
                            <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Email:</td><td style="padding:8px 0;">' . htmlspecialchars($user_email) . '</td></tr>
                            <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Összeg:</td><td style="padding:8px 0;font-size:1.2rem;font-weight:bold;color:#dc3545;">' . number_format($amount, 0, ',', ' ') . ' FT</td></tr>
                            <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Számlán szereplő név:</td><td style="padding:8px 0;">' . htmlspecialchars($account_holder) . '</td></tr>
                            <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Bankszámlaszám:</td><td style="padding:8px 0;font-family:monospace;font-size:1.1rem;">' . htmlspecialchars($account_number) . '</td></tr>
                            <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Tranzakció ID:</td><td style="padding:8px 0;font-family:monospace;">' . htmlspecialchars($transaction_id) . '</td></tr>
                            <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Dátum:</td><td style="padding:8px 0;">' . date('Y.m.d H:i') . '</td></tr>
                        </table>
                        <div style="margin-top:25px;text-align:center;">
                            <a href="' . $approve_url . '" style="display:inline-block;padding:12px 30px;background:#28a745;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:1rem;margin-right:10px;">✅ Jóváhagyás</a>
                            <a href="' . $reject_url . '" style="display:inline-block;padding:12px 30px;background:#dc3545;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:1rem;">❌ Elutasítás</a>
                        </div>
                    </div>
                </div>
                </body></html>';

                $mail->send();
            } catch (MailException $e) {
                // Email küldés sikertelen, de a kérelem rögzítve van
                error_log('Withdrawal email error: ' . $e->getMessage());
            }
            
            $_SESSION['success_message'] = "✅ Kifizetési kérelmed beérkezett! Az adminisztrátor hamarosan elbírálja, és emailben értesítünk az eredményről. Azonosító: " . $transaction_id;
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
    <title data-i18n="userProfile.withdrawal.pageTitle">Kifizetés | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .withdrawal-section-title {
            color: #333;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .withdrawal-input-wrapper {
            position: relative;
            margin-bottom: 8px;
        }
        .withdrawal-input-wrapper input {
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
        .withdrawal-input-wrapper input:focus {
            border-color: #007bff;
            box-shadow: 0 0 8px rgba(0,123,255,0.15);
            outline: none;
        }
        .withdrawal-input-wrapper .currency-label {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #007bff;
            font-weight: 800;
            font-size: 1.1rem;
        }
        .withdrawal-hint {
            color: #888;
            font-size: 0.8rem;
            margin-bottom: 16px;
        }

        .withdrawal-quick-btns {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .withdrawal-quick-btn {
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
        .withdrawal-quick-btn:hover {
            border-color: #007bff;
            color: #007bff;
            background: #f0f7ff;
        }
        .withdrawal-quick-btn.active {
            background: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        .withdrawal-field label {
            font-weight: 600;
            color: #333;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .withdrawal-field .form-control {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 8px;
            color: #333;
            padding: 10px 14px;
            font-size: 0.95rem;
        }
        .withdrawal-field .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 6px rgba(0,123,255,0.12);
        }
        .withdrawal-field .field-hint {
            color: #999;
            font-size: 0.78rem;
            margin-top: 4px;
        }

        .withdrawal-agreement {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .withdrawal-agreement .form-check-label {
            color: #555;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .withdrawal-info-bar {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .withdrawal-info-bar i { color: #007bff; font-size: 1rem; }
        .withdrawal-info-bar span { color: #666; font-size: 0.85rem; }

        .withdrawal-submit-btn {
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
        .withdrawal-submit-btn:hover {
            background: #0056b3;
            box-shadow: 0 4px 14px rgba(0,123,255,0.3);
        }
        .withdrawal-back-btn {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: #888;
            font-size: 0.9rem;
            text-decoration: none;
            font-weight: 600;
        }
        .withdrawal-back-btn:hover { color: #007bff; }

        @media (max-width: 576px) {
            .withdrawal-quick-btns { flex-wrap: wrap; }
            .withdrawal-quick-btn { min-width: 60px; flex: 0 0 calc(50% - 6px); }
        }
    </style>
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
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> <span data-i18n="auth.changePassword">Jelszó Módosítás</span></a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> <span data-i18n="auth.deposit">Befizetés</span></a>
                    <a href="withdrawal.php" class="profile-nav-item active"><i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span></a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span></a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span></a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> <span data-i18n="auth.activityLog">Napló</span></a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> <span data-i18n="auth.logout">Kijelentkezés</span></a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span></h1>
                    
                    <div class="alert alert-info py-3">
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <div class="p-2 rounded" style="background: rgba(13, 110, 253, 0.12); border: 1px solid rgba(13, 110, 253, 0.25);">
                                    <div style="font-weight:700; font-size: 0.9rem;" data-i18n="userProfile.balance.deposited">BEFIZETETT EGYENLEG</div>
                                    <div class="mt-1"><span id="balDeposited" class="badge bg-secondary"><?php echo number_format($deposited_balance, 0, ',', ' '); ?> FT</span></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-2 rounded" style="background: rgba(25, 135, 84, 0.12); border: 1px solid rgba(25, 135, 84, 0.25);">
                                    <div style="font-weight:700; font-size: 0.9rem;" data-i18n="userProfile.balance.winnings">NYEREMÉNYEGYENLEG</div>
                                    <div class="mt-1"><span id="balWinnings" class="badge bg-success"><?php echo number_format($winnings_balance, 0, ',', ' '); ?> FT</span></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-2 rounded" style="background: rgba(13, 110, 253, 0.12); border: 1px solid rgba(13, 110, 253, 0.25);">
                                    <div style="font-weight:700; font-size: 0.9rem;" data-i18n="userProfile.balance.total">BEFIZETETT ÉS NYEREMÉNYEGYENLEG ÖSSZESEN</div>
                                    <div class="mt-1"><span id="balTotal" class="badge bg-primary"><?php echo number_format($total_deposit_and_winnings, 0, ',', ' '); ?> FT</span></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-2 rounded" style="background: rgba(255, 193, 7, 0.14); border: 1px solid rgba(255, 193, 7, 0.35);">
                                    <div style="font-weight:700; font-size: 0.9rem;" data-i18n="userProfile.balance.bonus">BÓNUSZ EGYENLEG (NEM KIUTALHATÓ)</div>
                                    <div class="mt-1"><span id="balBonus" class="badge bg-warning text-dark"><?php echo number_format($bonus_balance, 0, ',', ' '); ?> FT</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                        $can_withdraw = $winnings_balance >= 6000;
                        $withdrawable = $winnings_balance;
                    ?>

                    <!-- Kiutalható összeg -->
                    <div id="withdrawableSection" class="p-3 rounded mb-3" style="background: <?php echo $can_withdraw ? 'rgba(25,135,84,0.08)' : 'rgba(220,53,69,0.08)'; ?>; border: 2px solid <?php echo $can_withdraw ? 'rgba(25,135,84,0.3)' : 'rgba(220,53,69,0.3)'; ?>; text-align:center;">
                        <div id="withdrawableLabel" style="font-weight:700; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; color:<?php echo $can_withdraw ? '#198754' : '#dc3545'; ?>; margin-bottom:4px;">
                            <i class="fas fa-<?php echo $can_withdraw ? 'check-circle' : 'times-circle'; ?>"></i>
                            <span data-i18n="userProfile.withdrawal.withdrawable">Kiutalható összeg</span>
                        </div>
                        <div id="withdrawableAmount" style="font-size:1.6rem; font-weight:800; color:<?php echo $can_withdraw ? '#198754' : '#dc3545'; ?>;">
                            <?php echo number_format($withdrawable, 0, ',', ' '); ?> FT
                        </div>
                        <?php if (!$can_withdraw): ?>
                            <div style="font-size:0.8rem; color:#dc3545; margin-top:6px;">
                                <i class="fas fa-info-circle"></i> <span data-i18n-html="userProfile.withdrawal.minRequiredInfo">A kifizetéshez legalább <strong>6 000 FT</strong> nyereményegyenleg szükséges.</span>
                            </div>
                        <?php else: ?>
                            <div style="font-size:0.8rem; color:#666; margin-top:6px;">
                                <span data-i18n-html="userProfile.withdrawal.winningsOnlyInfo">Kifizetés csak a nyereményegyenlegből lehetséges. Minimum: <strong>6 000 FT</strong></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="pendingWithdrawalsContainer">
                    <?php if (!empty($pending_withdrawals)): ?>
                    <div class="withdrawal-info-bar" style="border-color:rgba(255,193,7,0.4);background:rgba(255,193,7,0.08);">
                        <i class="fas fa-clock" style="color:#ffc107;font-size:1.2rem;"></i>
                        <div>
                            <div style="font-weight:700;color:#856404;font-size:0.9rem;margin-bottom:4px;" data-i18n="userProfile.withdrawal.pendingRequests">Függőben lévő kifizetési kérelmek</div>
                            <?php foreach ($pending_withdrawals as $pw): ?>
                                <div style="color:#666;font-size:0.85rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                                    <span>
                                        <strong><?php echo number_format((float)$pw['amount'], 0, ',', ' '); ?> FT</strong>
                                        — <?php echo date('Y.m.d H:i', strtotime($pw['created_at'])); ?>
                                        <span style="color:#999;font-size:0.75rem;">(<?php echo htmlspecialchars($pw['transaction_id']); ?>)</span>
                                    </span>
                                    <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm(window.i18n ? window.i18n('userProfile.withdrawal.confirmCancelRequest', 'Biztosan visszavonod ezt a kifizetési kérelmet?') : 'Biztosan visszavonod ezt a kifizetési kérelmet?');">
                                        <input type="hidden" name="cancel_id" value="<?php echo (int)$pw['id']; ?>">
                                        <button type="submit" name="cancel_withdrawal" value="1" style="background:#dc3545;color:#fff;border:none;border-radius:4px;padding:2px 10px;font-size:0.75rem;font-weight:600;cursor:pointer;">
                                            <i class="fas fa-times"></i> <span data-i18n="userProfile.withdrawal.cancelRequest">Visszavonás</span>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                            <div style="color:#856404;font-size:0.78rem;margin-top:4px;">
                                <i class="fas fa-info-circle"></i> <span data-i18n="userProfile.withdrawal.pendingInfo">Az admin hamarosan elbírálja, emailben értesítünk.</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    </div>

                    <?php if (!$data_verified): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        A kifizetéshez először az adminnak ellenőriznie kell a személyes adataidat. Kérjük, a <a href="personal_data.php" class="alert-link">Személyes Adatok</a> oldalon kérd az ellenőrzést.
                    </div>
                    <?php endif; ?>

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
                    
                    <form id="withdrawalForm" method="POST" <?php if (!$can_withdraw) echo 'style="opacity:0.5; pointer-events:none;"'; ?>>
                        <input type="hidden" name="payment_method" value="bank_transfer">

                        <!-- Összeg -->
                        <div class="withdrawal-section-title" data-i18n="userProfile.withdrawal.amount">Kifizetési összeg</div>
                        <div class="withdrawal-input-wrapper">
                            <input type="number" id="amount" name="amount" min="6000" step="1" max="<?php echo $winnings_balance; ?>" required value="6000">
                            <span class="currency-label">FT</span>
                        </div>
                        <div class="withdrawal-hint" id="withdrawalHint">
                            <span data-i18n="userProfile.withdrawal.minLabel">Min</span>: <strong>6 000 FT</strong> &nbsp;|&nbsp; <span data-i18n="userProfile.withdrawal.maxWinningsLabel">Max (nyereményegyenleg)</span>: <strong><?php echo number_format($winnings_balance, 0, ',', ' '); ?> FT</strong>
                        </div>

                        <!-- Gyors összegek -->
                        <div class="withdrawal-quick-btns">
                            <button type="button" class="withdrawal-quick-btn" data-amount="7500">7 500</button>
                            <button type="button" class="withdrawal-quick-btn" data-amount="10000">10 000</button>
                            <button type="button" class="withdrawal-quick-btn" data-amount="15000">15 000</button>
                            <button type="button" class="withdrawal-quick-btn" data-amount="20000">20 000</button>
                        </div>

                        <!-- Bankszámla adatok -->
                        <div class="withdrawal-section-title" data-i18n="userProfile.withdrawal.bankData">Bankszámla adatok</div>

                        <div class="withdrawal-field mb-3">
                            <label for="account_holder"><i class="fas fa-user"></i> <span data-i18n="userProfile.withdrawal.accountHolder">Számlán szereplő név</span></label>
                            <input type="text" class="form-control" id="account_holder" name="account_holder" required placeholder="pl. Kovács János" data-i18n-placeholder="userProfile.withdrawal.accountHolderPlaceholder">
                            <div class="field-hint" data-i18n="userProfile.withdrawal.accountHolderHint">A névnek egyeznie kell a regisztrációkor megadott teljes névvel.</div>
                        </div>

                        <div class="withdrawal-field mb-3">
                            <label for="account_number"><i class="fas fa-university"></i> <span data-i18n="userProfile.withdrawal.accountNumber">Bankszámlaszám</span></label>
                            <input type="text" class="form-control" id="account_number" name="account_number" required placeholder="12345678-87654321" data-i18n-placeholder="userProfile.withdrawal.accountNumberPlaceholder">
                            <div class="field-hint" data-i18n="userProfile.withdrawal.accountNumberHint">16 vagy 24 számjegy (pl: 12345678-87654321)</div>
                        </div>

                        <!-- Nyilatkozat -->
                        <div class="withdrawal-agreement">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agreement" name="agreement" required>
                                <label class="form-check-label" for="agreement">
                                    <span data-i18n="userProfile.withdrawal.agreement">Kijelentem, hogy a kifizetést a saját nevemre szóló bankszámlára kérem, és az megfelel a hatályos jogszabályi előírásoknak.</span>
                                </label>
                            </div>
                        </div>

                        <div class="withdrawal-info-bar">
                            <i class="fas fa-shield-alt"></i>
                            <span data-i18n-html="userProfile.withdrawal.processingInfo">A kifizetést egy admin dolgozza fel. A pénz <strong>1–3 munkanap</strong> alatt érkezik meg a bankszámládra.</span>
                        </div>

                        <button type="submit" name="submit_withdrawal" class="withdrawal-submit-btn">
                            <i class="fas fa-arrow-down"></i>&nbsp; <span data-i18n="userProfile.withdrawal.submit">Kifizetés Kérelmezése</span>
                        </button>
                        <a href="personal_data.php" class="withdrawal-back-btn"><i class="fas fa-arrow-left"></i>&nbsp; <span data-i18n="common.back">Vissza</span></a>
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
            // Gyors összeg gombok
            const quickBtns = document.querySelectorAll('.withdrawal-quick-btn');
            const amountInput = document.getElementById('amount');
            quickBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    amountInput.value = this.dataset.amount;
                    quickBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                });
            });

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

            // Pendingek automatikus frissítése 15 másodpercenként
            function refreshPending() {
                fetch('../../backend/ApiRequest/UserProfile/get_pending_withdrawals.php')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) return;
                        const container = document.getElementById('pendingWithdrawalsContainer');

                        // Egyenleg frissítése
                        function fmt(n) { return Number(n).toLocaleString('hu-HU') + ' FT'; }
                        var el, w = data.winnings_balance, canW = w >= 6000;
                        if ((el = document.getElementById('balDeposited'))) el.textContent = fmt(data.deposited_balance);
                        if ((el = document.getElementById('balWinnings'))) el.textContent = fmt(w);
                        if ((el = document.getElementById('balTotal'))) el.textContent = fmt(data.total_deposit_and_winnings);
                        if ((el = document.getElementById('balBonus'))) el.textContent = fmt(data.bonus_balance);
                        if ((el = document.getElementById('withdrawableAmount'))) {
                            el.textContent = fmt(w);
                            el.style.color = canW ? '#198754' : '#dc3545';
                        }

                        // Kiutalható szekció szín
                        var sec = document.getElementById('withdrawableSection');
                        if (sec) {
                            sec.style.background = canW ? 'rgba(25,135,84,0.08)' : 'rgba(220,53,69,0.08)';
                            sec.style.borderColor = canW ? 'rgba(25,135,84,0.3)' : 'rgba(220,53,69,0.3)';
                        }
                        var lbl = document.getElementById('withdrawableLabel');
                        if (lbl) {
                            lbl.style.color = canW ? '#198754' : '#dc3545';
                            lbl.innerHTML = '<i class="fas fa-' + (canW ? 'check-circle' : 'times-circle') + '"></i> ' + (window.i18n ? window.i18n('userProfile.withdrawal.withdrawable', 'Kiutalható összeg') : 'Kiutalható összeg');
                        }

                        // Input max + hint frissítése
                        var amtInput = document.getElementById('amount');
                        if (amtInput) amtInput.max = w;
                        var hint = document.getElementById('withdrawalHint');
                        if (hint) {
                            const minText = window.i18n ? window.i18n('userProfile.withdrawal.minLabel', 'Min') : 'Min';
                            const maxText = window.i18n ? window.i18n('userProfile.withdrawal.maxWinningsLabel', 'Max (nyereményegyenleg)') : 'Max (nyereményegyenleg)';
                            hint.innerHTML = minText + ': <strong>6 000 FT</strong> &nbsp;|&nbsp; ' + maxText + ': <strong>' + fmt(w) + '</strong>';
                        }

                        // Form engedélyezése/letiltása
                        var form = document.getElementById('withdrawalForm');
                        if (form) {
                            form.style.opacity = canW ? '1' : '0.5';
                            form.style.pointerEvents = canW ? 'auto' : 'none';
                        }

                        if (!container) return;

                        if (data.pending.length === 0) {
                            container.innerHTML = '';
                            return;
                        }

                        let html = '<div class="withdrawal-info-bar" style="border-color:rgba(255,193,7,0.4);background:rgba(255,193,7,0.08);">';
                        html += '<i class="fas fa-clock" style="color:#ffc107;font-size:1.2rem;"></i><div>';
                        html += '<div style="font-weight:700;color:#856404;font-size:0.9rem;margin-bottom:4px;">' + (window.i18n ? window.i18n('userProfile.withdrawal.pendingRequests', 'Függőben lévő kifizetési kérelmek') : 'Függőben lévő kifizetési kérelmek') + '</div>';
                        data.pending.forEach(pw => {
                            const amt = Number(pw.amount).toLocaleString('hu-HU');
                            const date = pw.created_at.substring(0, 16).replace(/-/g, '.');
                            html += '<div style="color:#666;font-size:0.85rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">';
                            html += '<span><strong>' + amt + ' FT</strong> — ' + date + ' <span style="color:#999;font-size:0.75rem;">(' + pw.transaction_id + ')</span></span>';
                            html += '<form method="POST" style="display:inline;margin:0;" onsubmit="return confirm(\'' + (window.i18n ? window.i18n('userProfile.withdrawal.confirmCancelRequest', 'Biztosan visszavonod ezt a kifizetési kérelmet?') : 'Biztosan visszavonod ezt a kifizetési kérelmet?') + '\');">';
                            html += '<input type="hidden" name="cancel_id" value="' + pw.id + '">';
                            html += '<button type="submit" name="cancel_withdrawal" value="1" style="background:#dc3545;color:#fff;border:none;border-radius:4px;padding:2px 10px;font-size:0.75rem;font-weight:600;cursor:pointer;">';
                            html += '<i class="fas fa-times"></i> ' + (window.i18n ? window.i18n('userProfile.withdrawal.cancelRequest', 'Visszavonás') : 'Visszavonás') + '</button></form></div>';
                        });
                        html += '<div style="color:#856404;font-size:0.78rem;margin-top:4px;"><i class="fas fa-info-circle"></i> ' + (window.i18n ? window.i18n('userProfile.withdrawal.pendingInfo', 'Az admin hamarosan elbírálja, emailben értesítünk.') : 'Az admin hamarosan elbírálja, emailben értesítünk.') + '</div>';
                        html += '</div></div>';
                        container.innerHTML = html;
                    })
                    .catch(() => {});
            }
            setInterval(refreshPending, 15000);
        });
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
