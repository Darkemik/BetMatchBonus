<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Felhasználó adatainak lekérése
$query = "SELECT id, username, email, full_name, mobile_number as phone, country, city, postal_code, address, birth_date, created_at, data_verified, bank_statement_file, data_rejected_at, data_rejection_reason FROM Users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Elutasítás utáni 15 perces várakozás ellenőrzése
$rejectedAt = $user['data_rejected_at'] ? strtotime($user['data_rejected_at']) : null;
$rejectCooldownLeft = 0;
if ($rejectedAt) {
    $rejectCooldownLeft = max(0, ($rejectedAt + 15 * 60) - time());
}

// POST kérelmen adatok ellenőrzésre küldése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // 15 perces cooldown ellenőrzés elutasítás után
    if ($rejectedAt && $rejectCooldownLeft > 0) {
        $_SESSION['error_message'] = "Az elutasítás után még " . ceil($rejectCooldownLeft / 60) . " percet kell várnod az újraküldéshez!";
        header("Location: personal_data.php");
        exit();
    }

    $country = htmlspecialchars($_POST['country'] ?? '');
    $city = htmlspecialchars($_POST['city'] ?? '');
    $postal_code = htmlspecialchars($_POST['postal_code'] ?? '');
    $address = htmlspecialchars($_POST['address'] ?? '');

    if (empty($country) || empty($city) || empty($postal_code) || empty($address)) {
        $_SESSION['error_message'] = "Kérjük, töltsd ki az összes lakcímadatot az ellenőrzés kéréséhez!";
        header("Location: personal_data.php");
        exit();
    }

    // Bankszámlakivonat feltöltés kezelése
    $bank_statement_path = $user['bank_statement_file'] ?? null; // meglévő fájl megtartása
    if (isset($_FILES['bank_statement']) && $_FILES['bank_statement']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5 MB
        $file_type = $_FILES['bank_statement']['type'];
        $file_size = $_FILES['bank_statement']['size'];
        $file_ext = strtolower(pathinfo($_FILES['bank_statement']['name'], PATHINFO_EXTENSION));

        if (!in_array($file_type, $allowed_types) || !in_array($file_ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'])) {
            $_SESSION['error_message'] = "A bankszámlakivonat csak PDF, JPG, PNG vagy WEBP formátumban tölthető fel!";
            header("Location: personal_data.php");
            exit();
        }
        if ($file_size > $max_size) {
            $_SESSION['error_message'] = "A fájl mérete maximum 5 MB lehet!";
            header("Location: personal_data.php");
            exit();
        }

        $upload_dir = "../../backend/uploads/bank_statements/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Régi fájl törlése ha van
        if ($bank_statement_path && file_exists($upload_dir . $bank_statement_path)) {
            unlink($upload_dir . $bank_statement_path);
        }

        $new_filename = 'bank_' . $user_id . '_' . time() . '.' . $file_ext;
        if (move_uploaded_file($_FILES['bank_statement']['tmp_name'], $upload_dir . $new_filename)) {
            $bank_statement_path = $new_filename;
        } else {
            $_SESSION['error_message'] = "Hiba a fájl feltöltése során!";
            header("Location: personal_data.php");
            exit();
        }
    }

    // Token generálás
    $token = bin2hex(random_bytes(32));

    $update_query = "UPDATE Users SET country = ?, city = ?, postal_code = ?, address = ?, data_verification_token = ?, bank_statement_file = ?, data_rejected_at = NULL, data_rejection_reason = NULL WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssssssi", $country, $city, $postal_code, $address, $token, $bank_statement_path, $user_id);
    
    if ($update_stmt->execute()) {
        // Email küldés adminnak a bmbugyfelszolgalat@gmail.com címre
        require_once "../../backend/mail_config.php";
        require_once "../../backend/PHPMailer/Exception.php";
        require_once "../../backend/PHPMailer/PHPMailer.php";
        require_once "../../backend/PHPMailer/SMTP.php";

        $approveUrl = SITE_BASE_URL . '/backend/Auth/approve_data_verification.php?token=' . $token;
        $rejectUrl  = SITE_BASE_URL . '/backend/Auth/reject_data_verification.php?token=' . $token;

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
            $mail->addAddress('bmbugyfelszolgalat@gmail.com', 'BM Ügyfélszolgálat');

            $mail->isHTML(true);
            $mail->Subject = 'Személyes adatok ellenőrzése - ' . htmlspecialchars($user['username']);

            // Bankszámlakivonat csatolása ha van
            $hasAttachment = false;
            if ($bank_statement_path) {
                $attachment_path = "../../backend/uploads/bank_statements/" . $bank_statement_path;
                if (file_exists($attachment_path)) {
                    $mail->addAttachment($attachment_path, 'bankszamlakivonat_' . $user['username'] . '.' . pathinfo($bank_statement_path, PATHINFO_EXTENSION));
                    $hasAttachment = true;
                }
            }

            $bankStatementRow = $hasAttachment
                ? '<tr><td><strong>Bankszámlakivonat</strong></td><td>📎 Csatolva az emailhez</td></tr>'
                : '<tr><td><strong>Bankszámlakivonat</strong></td><td style="color:#dc3545;">Nincs feltöltve</td></tr>';

            $mail->Body = '
                <h2>Személyes adatok ellenőrzési kérelem</h2>
                <p>A következő felhasználó kéri az adatainak ellenőrzését:</p>
                <table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
                    <tr><td><strong>Felhasználónév</strong></td><td>' . htmlspecialchars($user['username']) . '</td></tr>
                    <tr><td><strong>Email</strong></td><td>' . htmlspecialchars($user['email']) . '</td></tr>
                    <tr><td><strong>Teljes név</strong></td><td>' . htmlspecialchars($user['full_name'] ?? '-') . '</td></tr>
                    <tr><td><strong>Ország</strong></td><td>' . htmlspecialchars($country) . '</td></tr>
                    <tr><td><strong>Város</strong></td><td>' . htmlspecialchars($city) . '</td></tr>
                    <tr><td><strong>Irányítószám</strong></td><td>' . htmlspecialchars($postal_code) . '</td></tr>
                    <tr><td><strong>Cím</strong></td><td>' . htmlspecialchars($address) . '</td></tr>
                    ' . $bankStatementRow . '
                </table>
                <br>
                <p>Ha az adatok helyesek, kattints az alábbi gombra a jóváhagyáshoz:</p>
                <a href="' . $approveUrl . '" style="display:inline-block;padding:12px 24px;background:#28a745;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">✅ Adatok Jóváhagyása</a>
                &nbsp;&nbsp;
                <a href="' . $rejectUrl . '" style="display:inline-block;padding:12px 24px;background:#dc3545;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">❌ Elutasítás</a>
                <br><br>
                <p style="color:#888;font-size:12px;">Az elutasításnál meg kell adnod az okot, amit a felhasználó emailben megkap.</p>
            ';

            $mail->send();
            $_SESSION['success_message'] = "Az adataid ellenőrzésre elküldtük! Amint az admin jóváhagyja, kifizetést is kezdeményezhetsz.";
        } catch (MailException $e) {
            $_SESSION['success_message'] = "Az adataid elmentettük, de az értesítő email küldése sikertelen. Kérjük, próbáld újra később.";
        }
        header("Location: personal_data.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Hiba: " . $update_stmt->error;
    }
    $update_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="userProfile.personalData.pageTitle">Személyes Adatok | BetMatchBonus</title>
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
                    <a href="personal_data.php" class="profile-nav-item active"><i class="fas fa-user"></i> <span data-i18n="auth.personalData">Személyes Adatok</span></a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> <span data-i18n="auth.changePassword">Jelszó Módosítás</span></a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> <span data-i18n="auth.deposit">Befizetés</span></a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span></a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span></a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span></a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> <span data-i18n="auth.activityLog">Napló</span></a>
                    <a href="notifications.php" class="profile-nav-item"><i class="fas fa-bell"></i> <span>Értesítések</span></a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> <span data-i18n="auth.logout">Kijelentkezés</span></a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-user"></i> <span data-i18n="auth.personalData">Személyes Adatok</span></h1>

                    <?php if (!(int)($user['data_verified'] ?? 0)): ?>
                    
                    <?php if ($rejectedAt && !empty($user['data_rejection_reason'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-times-circle"></i>
                        <strong data-i18n="userProfile.personalData.rejectedTitle">Az adatellenőrzésed elutasításra került!</strong><br>
                        <strong data-i18n="userProfile.personalData.reason">Ok</strong>: <?php echo htmlspecialchars($user['data_rejection_reason']); ?><br>
                        <?php if ($rejectCooldownLeft > 0): ?>
                        <hr>
                        <i class="fas fa-clock"></i> <span data-i18n="userProfile.personalData.resubmitIn">Újra beküldheted az adataidat</span> <strong><span id="rejectCountdown"></span></strong> <span data-i18n="userProfile.personalData.inSuffix">múlva.</span>
                        <?php else: ?>
                        <hr>
                        <i class="fas fa-check-circle" style="color:#28a745;"></i> <span data-i18n="userProfile.personalData.resubmitNow">Javítsd az adataid és küldd be újra az alábbi űrlap segítségével!</span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span data-i18n="userProfile.personalData.warningNeedAddress">A kifizetés előtt kötelező kitölteni a lakcímadatokat (ország, város, irányítószám, cím). A fiókodat egy admin ellenőrzi, hogy a megadott adatok helyesek-e.</span>
                    </div>
                    <?php endif; /* rejected or warning */ ?>
                    
                    <?php else: ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <span data-i18n="userProfile.personalData.verifiedOk">Az adataid ellenőrizve lettek. Kifizetést kezdeményezhetsz.</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" class="profile-form">
                        <div class="form-group mb-3">
                            <label for="username" data-i18n="registerModal.username">Felhasználónév</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="form-text" style="color: white;" data-i18n="userProfile.personalData.usernameReadonly">A felhasználónév nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="email" data-i18n="registerModal.email">Email</label>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small class="form-text" style="color: white;" data-i18n="userProfile.personalData.emailReadonly">Az email cím nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="full_name" data-i18n="userProfile.personalData.fullName">Teljes Név</label>
                            <input type="text" class="form-control" id="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;" data-i18n="userProfile.personalData.fullNameReadonly">A teljes név nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="phone" data-i18n="userProfile.personalData.phone">Telefon</label>
                            <input type="tel" class="form-control" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;" data-i18n="userProfile.personalData.phoneReadonly">A telefonszám nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="country" data-i18n="userProfile.personalData.country">Ország</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" readonly>
                            <small class="form-text" style="color: #aaa;" data-i18n="userProfile.personalData.autoByPostal">Az irányítószám alapján automatikusan kitöltődik</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="postal_code" data-i18n="userProfile.personalData.postalCode">Irányítószám</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" maxlength="4" placeholder="pl. 1051">
                            <small class="form-text" id="postalFeedback" style="color: #aaa;"></small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="city" data-i18n="userProfile.personalData.city">Város / Község</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" readonly>
                            <small class="form-text" style="color: #aaa;" data-i18n="userProfile.personalData.autoByPostal">Az irányítószám alapján automatikusan kitöltődik</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="address" data-i18n="userProfile.personalData.address">Cím</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="bank_statement"><i class="fas fa-file-invoice"></i> <span data-i18n="userProfile.personalData.bankStatement">Bankszámlakivonat</span></label>
                            <?php if (!empty($user['bank_statement_file'])): ?>
                                <div class="mb-2">
                                    <span class="badge bg-success"><i class="fas fa-check"></i> <span data-i18n="userProfile.personalData.uploaded">Feltöltve</span></span>
                                    <small style="color:#aaa; margin-left:8px;"><?php echo htmlspecialchars($user['bank_statement_file']); ?></small>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="bank_statement" name="bank_statement" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <small class="form-text" style="color: #aaa;" data-i18n="userProfile.personalData.bankStatementHint">PDF, JPG, PNG vagy WEBP — max. 5 MB.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="birth_date" data-i18n="userProfile.personalData.birthDate">Születési Dátum</label>
                            <input type="date" class="form-control" id="birth_date" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;" data-i18n="userProfile.personalData.birthDateReadonly">A születési dátum nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="created_at" data-i18n="userProfile.personalData.createdAt">Fiók Létrehozva</label>
                            <input type="text" class="form-control" id="created_at" value="<?php echo htmlspecialchars($user['created_at']); ?>" disabled>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary" id="submitVerifyBtn" <?php if ($rejectCooldownLeft > 0) echo 'disabled'; ?>><i class="fas fa-check-circle"></i> <span data-i18n="userProfile.personalData.verifyData">Adatok Ellenőrzése</span></button>
                        <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> <span data-i18n="userProfile.changePassword.cancel">Mégse</span></a>
                    </form>

                    <!-- Fiók törlése szekció -->
                    <hr style="border-color:#2a2a3e;margin-top:40px;">
                    <div class="mt-4">
                        <h5 style="color:#dc3545;"><i class="fas fa-exclamation-triangle"></i> <span data-i18n="userProfile.personalData.dangerZone">Veszélyzóna</span></h5>
                        <p style="color:#aaa;font-size:14px;" data-i18n="userProfile.personalData.dangerText">Ha törlöd a fiókodat, az összes adatod, egyenleged, bónuszod és fogadásod véglegesen elvész. Ez a művelet nem vonható vissza.</p>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash-alt"></i> <span data-i18n="userProfile.personalData.deleteAccount">Felhasználóm törlése</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <!-- 1. Fiók törlése - Figyelmeztetés modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background:#1a1a2e;color:#f5c518;border:1px solid #dc3545;">
                <div class="modal-header border-bottom" style="border-color:#dc3545 !important;">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger"></i> <span data-i18n="userProfile.personalData.deleteAccount">Fiók törlése</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Bezár"></button>
                </div>
                <div class="modal-body">
                    <div style="background:#2a1a1a;border:1px solid #dc3545;border-radius:8px;padding:16px;margin-bottom:16px;">
                        <p style="color:#ff6b6b;font-weight:bold;margin-bottom:12px;"><i class="fas fa-exclamation-circle"></i> Figyelem! Ez a művelet visszavonhatatlan!</p>
                        <ul style="color:#ccc;font-size:14px;padding-left:20px;margin-bottom:0;">
                            <li>Az összes <strong style="color:#f5c518;">egyenleged</strong> (befizetett, nyeremény, bónusz) véglegesen elvész</li>
                            <li>Az összes <strong style="color:#f5c518;">fogadásod</strong> és tranzakciód törlésre kerül</li>
                            <li>Az összes <strong style="color:#f5c518;">bónuszod</strong> érvényét veszti</li>
                            <li>A feltöltött <strong style="color:#f5c518;">dokumentumaid</strong> törlésre kerülnek</li>
                            <li>A fiókodat <strong style="color:#ff6b6b;">nem lehet visszaállítani</strong></li>
                        </ul>
                    </div>
                    <p style="color:#ccc;text-align:center;" data-i18n="userProfile.personalData.deleteConfirmQuestion">Biztosan törölni szeretnéd a fiókodat?</p>
                </div>
                <div class="modal-footer border-top d-flex justify-content-between" style="border-color:#dc3545 !important;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-arrow-left"></i> <span data-i18n="userProfile.personalData.notNow">Mégsem</span></button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteStep1">
                        <i class="fas fa-trash-alt"></i> Igen, törölni akarom
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Második megerősítés modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background:#1a1a2e;color:#f5c518;border:1px solid #dc3545;">
                <div class="modal-header border-bottom" style="border-color:#dc3545 !important;">
                    <h5 class="modal-title"><i class="fas fa-skull-crossbones text-danger"></i> <span data-i18n="userProfile.personalData.finalWarning">Utolsó figyelmeztetés</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Bezár"></button>
                </div>
                <div class="modal-body">
                    <p style="color:#ff6b6b;text-align:center;font-size:16px;font-weight:bold;">
                        Ez az utolsó lépés! A törlés után a fiókod véglegesen megszűnik.
                    </p>
                    <p style="color:#ccc;text-align:center;margin-bottom:6px;" data-i18n="userProfile.personalData.enterPassword">A megerősítéshez írd be a jelszavad:</p>
                    <input type="password" id="deleteConfirmPassword" class="form-control mb-2" placeholder="Jelszó..." 
                           style="background:#16213e;border:1px solid #2a2a3e;color:#f5c518;text-align:center;">
                    <small id="deletePasswordError" style="color:#dc3545;display:none;"></small>
                    <hr style="border-color:#2a2a3e;">
                    <p style="color:#aaa;font-size:14px;margin-bottom:6px;" data-i18n="userProfile.personalData.leaveReason">Írd meg nekünk, miért döntöttél a távozás mellett (nem kötelező):</p>
                    <textarea id="deleteReasonText" class="form-control" rows="3" placeholder="Pl. nem használom az oldalt, más okból..." 
                              style="background:#16213e;border:1px solid #2a2a3e;color:#f5c518;resize:vertical;"></textarea>
                </div>
                <div class="modal-footer border-top d-flex justify-content-between" style="border-color:#dc3545 !important;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-arrow-left"></i> <span data-i18n="userProfile.personalData.notNow">Mégsem</span></button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteStep2" disabled>
                        <i class="fas fa-trash-alt"></i> Véglegesen törlöm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Búcsú modal -->
    <div class="modal fade" id="deleteFarewellModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background:#1a1a2e;color:#f5c518;border:1px solid #f5c518;">
                <div class="modal-body text-center" style="padding:40px 30px;">
                    <div style="font-size:3rem;margin-bottom:10px;">👋</div>
                    <h4 style="color:#f5c518;" data-i18n="userProfile.personalData.thanks">Köszönjük, hogy az oldalunkat használtad!</h4>
                    <p style="color:#ccc;" data-i18n="userProfile.personalData.deletedInfo">A fiókod sikeresen törlésre került. Sajnáljuk, hogy távozol.</p>
                    <p style="color:#aaa;font-size:13px;">Automatikus átirányítás 5 másodperc múlva...</p>
                    <a href="/BetMatchBonus/frontend/MainMenu/MainMenu.php" class="btn btn-warning mt-3">
                        <i class="fas fa-home"></i> <span data-i18n="userProfile.personalData.backToHome">Vissza a főoldalra</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>

    <script>
    // --- Fiók törlés flow ---
    (function() {
        // 1. lépés: Első figyelmeztetés → második megerősítés
        document.getElementById('confirmDeleteStep1').addEventListener('click', function() {
            bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal')).hide();
            new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
        });

        // 2. lépés: Jelszó mező - gomb engedélyezés ha van input
        const passwordInput = document.getElementById('deleteConfirmPassword');
        const confirmBtn = document.getElementById('confirmDeleteStep2');
        const errorEl = document.getElementById('deletePasswordError');

        passwordInput.addEventListener('input', function() {
            confirmBtn.disabled = this.value.length === 0;
            errorEl.style.display = 'none';
        });

        // 2. lépés: Végleges törlés - jelszó ellenőrzéssel + reason küldés
        confirmBtn.addEventListener('click', function() {
            const password = passwordInput.value;
            if (!password) return;

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (window.i18n ? window.i18n('userProfile.personalData.deleting', 'Törlés folyamatban...') : 'Törlés folyamatban...');
            errorEl.style.display = 'none';

            const reason = document.getElementById('deleteReasonText').value.trim();

            const formData = new FormData();
            formData.append('delete_confirmed', '1');
            formData.append('password', password);
            formData.append('reason', reason);

            fetch('../../backend/ApiRequest/UserProfile/delete_account.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
                    new bootstrap.Modal(document.getElementById('deleteFarewellModal')).show();
                    setTimeout(function() {
                        window.location.href = '/BetMatchBonus/frontend/MainMenu/MainMenu.php';
                    }, 5000);
                } else {
                    errorEl.textContent = data.message || (window.i18n ? window.i18n('userProfile.personalData.unknownError', 'Ismeretlen hiba.') : 'Ismeretlen hiba.');
                    errorEl.style.display = 'block';
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-trash-alt"></i> ' + (window.i18n ? window.i18n('userProfile.personalData.deletePermanently', 'Véglegesen törlöm') : 'Véglegesen törlöm');
                    passwordInput.value = '';
                    passwordInput.focus();
                }
            })
            .catch(() => {
                errorEl.textContent = window.i18n ? window.i18n('userProfile.personalData.deleteError', 'Hiba történt a törlés során. Kérjük, próbáld újra.') : 'Hiba történt a törlés során. Kérjük, próbáld újra.';
                errorEl.style.display = 'block';
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-trash-alt"></i> ' + (window.i18n ? window.i18n('userProfile.personalData.deletePermanently', 'Véglegesen törlöm') : 'Véglegesen törlöm');
            });
        });
    })();
    </script>

    <?php if ($rejectCooldownLeft > 0): ?>
    <script>
    (function() {
        let left = <?php echo (int)$rejectCooldownLeft; ?>;
        const el = document.getElementById('rejectCountdown');
        const btn = document.getElementById('submitVerifyBtn');
        function tick() {
            if (left <= 0) {
                if (el) el.textContent = '';
                if (btn) btn.disabled = false;
                location.reload();
                return;
            }
            const m = Math.floor(left / 60);
            const s = left % 60;
            if (el) el.textContent = m + ':' + String(s).padStart(2, '0');
            left--;
            setTimeout(tick, 1000);
        }
        tick();
    })();
    </script>
    <?php endif; ?>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
