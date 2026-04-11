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
$query = "SELECT id, username, email, full_name, mobile_number as phone, country, city, postal_code, address, birth_date, created_at, data_verified, bank_statement_file FROM Users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// POST kérelmen adatok ellenőrzésre küldése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
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

    $update_query = "UPDATE Users SET country = ?, city = ?, postal_code = ?, address = ?, data_verification_token = ?, bank_statement_file = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssssssi", $country, $city, $postal_code, $address, $token, $bank_statement_path, $user_id);
    
    if ($update_stmt->execute()) {
        // Email küldés adminnak a bmbugyfelszolgalat@gmail.com címre
        require_once "../../backend/mail_config.php";
        require_once "../../backend/PHPMailer/Exception.php";
        require_once "../../backend/PHPMailer/PHPMailer.php";
        require_once "../../backend/PHPMailer/SMTP.php";

        $approveUrl = SITE_BASE_URL . '/backend/Auth/approve_data_verification.php?token=' . $token;

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
                <br><br>
                <p style="color:#888;font-size:12px;">Ha nem hagyod jóvá, nem kell tenned semmit.</p>
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
    <title>Személyes Adatok | BetMatchBonus</title>
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
                    <a href="personal_data.php" class="profile-nav-item active"><i class="fas fa-user"></i> Személyes Adatok</a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> Jelszó Módosítás</a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> Befizetés</a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> Kifizetés</a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> Tranzakciótörténet</a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> Bónuszaim</a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> Napló</a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> Kijelentkezés</a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-user"></i> Személyes Adatok</h1>

                    <?php if (!(int)($user['data_verified'] ?? 0)): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        A kifizetés előtt kötelező kitölteni a lakcímadatokat (ország, város, irányítószám, cím). A fiókodat egy admin ellenőrzi, hogy a megadott adatok helyesek-e.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i>
                        Az adataid ellenőrizve lettek. Kifizetést kezdeményezhetsz.
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
                            <label for="username">Felhasználónév</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="form-text" style="color: white;">A felhasználónév nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small class="form-text" style="color: white;">Az email cím nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="full_name">Teljes Név</label>
                            <input type="text" class="form-control" id="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;">A teljes név nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="phone">Telefon</label>
                            <input type="tel" class="form-control" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;">A telefonszám nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="country">Ország</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" readonly>
                            <small class="form-text" style="color: #aaa;">Az irányítószám alapján automatikusan kitöltődik</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="postal_code">Irányítószám</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" maxlength="4" placeholder="pl. 1051">
                            <small class="form-text" id="postalFeedback" style="color: #aaa;"></small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="city">Város / Község</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" readonly>
                            <small class="form-text" style="color: #aaa;">Az irányítószám alapján automatikusan kitöltődik</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="address">Cím</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="bank_statement"><i class="fas fa-file-invoice"></i> Bankszámlakivonat</label>
                            <?php if (!empty($user['bank_statement_file'])): ?>
                                <div class="mb-2">
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Feltöltve</span>
                                    <small style="color:#aaa; margin-left:8px;"><?php echo htmlspecialchars($user['bank_statement_file']); ?></small>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="bank_statement" name="bank_statement" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <small class="form-text" style="color: #aaa;">PDF, JPG, PNG vagy WEBP — max. 5 MB. <?php echo !empty($user['bank_statement_file']) ? 'Új feltöltéssel a régi felülíródik.' : 'A kifizetéshez szükséges.'; ?></small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="birth_date">Születési Dátum</label>
                            <input type="date" class="form-control" id="birth_date" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>" disabled>
                            <small class="form-text" style="color: white;">A születési dátum nem módosítható</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="created_at">Fiók Létrehozva</label>
                            <input type="text" class="form-control" id="created_at" value="<?php echo htmlspecialchars($user['created_at']); ?>" disabled>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-check-circle"></i> Adatok Ellenőrzése</button>
                        <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Mégse</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
