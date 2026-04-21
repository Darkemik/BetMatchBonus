<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('bonuses');
$perms = get_role_permissions();
date_default_timezone_set('Europe/Budapest');

$role = $_SESSION['admin_role'];

// Hétköznap-only bónuszok automatikus aktiválása: daily_start_time figyelembevételével
// admin_force_active = 1 esetén nem írjuk felül
$isWeekday = ((int)date('N') <= 5) ? 1 : 0;
if ($isWeekday) {
    $conn->query("UPDATE BonusCodes SET admin_force_active = 0 WHERE valid_weekdays_only = 1 AND admin_force_active = 1 AND (daily_start_time IS NULL OR CURTIME() >= daily_start_time)");
}
$conn->query("
    UPDATE BonusCodes
    SET is_active = CASE
        WHEN admin_force_active = 1 THEN 1
        WHEN {$isWeekday} = 1 AND (daily_start_time IS NULL OR CURTIME() >= daily_start_time) THEN 1
        ELSE 0
    END
    WHERE valid_weekdays_only = 1
");

// Kód nélküli születésnapi bónuszok legyenek alapból aktívak.
$conn->query(" 
    UPDATE BonusCodes
    SET is_active = 1
        WHERE birthday_bonus = 1
      AND (code IS NULL OR code = '')
");

// Esport bónusz legyen fixen aktív az admin felületen is.
$conn->query(" 
    UPDATE BonusCodes
    SET admin_force_active = 1,
        is_active = 1
    WHERE code = 'ESPORT5K'
");

// Bónusz adatok szerkesztése (mentés gombnyomásra)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_bonus_id'])) {
    $edit_id = (int)$_POST['edit_bonus_id'];
    $edit_code = isset($_POST['edit_code']) ? trim($_POST['edit_code']) : null;
    $edit_name = isset($_POST['edit_name']) ? trim($_POST['edit_name']) : '';
    $edit_desc = isset($_POST['edit_description']) ? trim($_POST['edit_description']) : '';
    $edit_match_percent = isset($_POST['edit_match_percent']) ? (float)$_POST['edit_match_percent'] : 0;
    $edit_max_bonus = isset($_POST['edit_max_bonus_amount']) ? (float)$_POST['edit_max_bonus_amount'] : 0;
    $edit_min_deposit = isset($_POST['edit_min_deposit']) ? (float)$_POST['edit_min_deposit'] : 0;
    $edit_wagering = isset($_POST['edit_wagering_multiplier']) ? (float)$_POST['edit_wagering_multiplier'] : 0;
    $edit_max_win = isset($_POST['edit_max_win_multiplier']) ? (float)$_POST['edit_max_win_multiplier'] : 5;
    $edit_daily_start = isset($_POST['edit_daily_start_time']) ? trim($_POST['edit_daily_start_time']) : null;

    if ($edit_code === '') $edit_code = null;
    if ($edit_daily_start === '') $edit_daily_start = null;

    $newImageUrl = null;
    $imageUploadError = null;
    if (isset($_FILES['edit_bonus_image']) && (int)($_FILES['edit_bonus_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $imgFile = $_FILES['edit_bonus_image'];
        if ((int)$imgFile['error'] !== UPLOAD_ERR_OK) {
            $imageUploadError = 'Kép feltöltési hiba (kód: ' . (int)$imgFile['error'] . ').';
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
            $maxSize = 2 * 1024 * 1024;
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($imgFile['tmp_name']);

            if (!in_array($mimeType, $allowedTypes, true)) {
                $imageUploadError = 'Nem támogatott képtípus. (JPG, PNG, GIF, SVG, WebP)';
            } elseif ((int)$imgFile['size'] > $maxSize) {
                $imageUploadError = 'A kép túl nagy. Maximum 2 MB.';
            } else {
                $ext = match ($mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/svg+xml' => 'svg',
                    'image/webp' => 'webp',
                    default => 'png'
                };

                $uploadDir = __DIR__ . '/../../backend/uploads/bonuses/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    $imageUploadError = 'A képmappa nem hozható létre.';
                } elseif (!is_writable($uploadDir)) {
                    $imageUploadError = 'A képmappa nem írható.';
                } else {
                    $filename = 'bonus_' . $edit_id . '_' . str_replace('.', '', (string)microtime(true)) . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $targetPath = $uploadDir . $filename;
                    if (!move_uploaded_file($imgFile['tmp_name'], $targetPath)) {
                        $imageUploadError = 'A kép mentése sikertelen.';
                    } else {
                        $newImageUrl = '../../backend/uploads/bonuses/' . $filename;

                        $oldStmt = $conn->prepare("SELECT image_url FROM BonusCodes WHERE id = ? LIMIT 1");
                        if ($oldStmt) {
                            $oldStmt->bind_param("i", $edit_id);
                            $oldStmt->execute();
                            $oldRow = $oldStmt->get_result()->fetch_assoc();
                            $oldStmt->close();
                            $oldUrl = (string)($oldRow['image_url'] ?? '');
                            if ($oldUrl !== '' && strpos($oldUrl, 'uploads/bonuses/') !== false) {
                                $oldPath = $uploadDir . basename($oldUrl);
                                if (is_file($oldPath)) {
                                    @unlink($oldPath);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($imageUploadError !== null) {
        $error_msg = $imageUploadError;
    } else {
        if ($newImageUrl !== null) {
            $editStmt = $conn->prepare(" 
                UPDATE BonusCodes 
                SET code = ?, name = ?, description = ?, image_url = ?, match_percent = ?, max_bonus_amount = ?,
                    min_deposit = ?, wagering_multiplier = ?, max_win_multiplier = ?, daily_start_time = ?
                WHERE id = ?
            ");
            $editStmt->bind_param(
                "ssssddddssi",
                $edit_code,
                $edit_name,
                $edit_desc,
                $newImageUrl,
                $edit_match_percent,
                $edit_max_bonus,
                $edit_min_deposit,
                $edit_wagering,
                $edit_max_win,
                $edit_daily_start,
                $edit_id
            );
        } else {
            $editStmt = $conn->prepare(" 
                UPDATE BonusCodes 
                SET code = ?, name = ?, description = ?, match_percent = ?, max_bonus_amount = ?,
                    min_deposit = ?, wagering_multiplier = ?, max_win_multiplier = ?, daily_start_time = ?
                WHERE id = ?
            ");
            $editStmt->bind_param("sssdddddsi",
                $edit_code, $edit_name, $edit_desc, $edit_match_percent, $edit_max_bonus,
                $edit_min_deposit, $edit_wagering, $edit_max_win, $edit_daily_start, $edit_id
            );
        }

        if ($editStmt && $editStmt->execute()) {
            $success_msg = "Bónusz (#$edit_id) sikeresen frissítve az adatbázisban!";
            if ($newImageUrl !== null) {
                $success_msg .= " A kép is frissítve lett.";
            }
        } else {
            $error_msg = "Hiba történt a bónusz mentésekor: " . ($editStmt ? $editStmt->error : $conn->error);
        }
        if ($editStmt) {
            $editStmt->close();
        }
    }
}

// Gombnyomásra bónusz státusz módosítása (Aktiválás / Inaktiválás)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_bonus_id'])) {
    $bonus_id = (int)$_POST['toggle_bonus_id'];
    
    // Lekérjük a jelenlegi státuszt
    $stmt = $conn->prepare("SELECT is_active, valid_weekdays_only, daily_start_time FROM BonusCodes WHERE id = ?");
    $stmt->bind_param("i", $bonus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bonus = $result->fetch_assoc();
    $stmt->close();
    
    if ($bonus) {
        // Ha 1, akkor 0 lesz, ha 0, akkor 1
        $new_status = (int)$bonus['is_active'] === 1 ? 0 : 1;

        // Ha admin hétköznapi bónuszt aktivál az auto-toggle időablakon kívül → admin_force_active = 1
        // Ez megakadályozza, hogy az auto-toggle visszakapcsolja inaktívra
        $adminForce = 0;
        if ($new_status === 1 && !empty($bonus['valid_weekdays_only'])) {
            $dailyStart = $bonus['daily_start_time'] ?? null;
            $isAfterDailyStart = ($dailyStart === null || date('H:i:s') >= $dailyStart);
            $isInNormalWindow = ($isWeekday && $isAfterDailyStart);
            if (!$isInNormalWindow) {
                $adminForce = 1;
            }
        }
        
        $updateStmt = $conn->prepare("UPDATE BonusCodes SET is_active = ?, admin_force_active = ? WHERE id = ?");
        $updateStmt->bind_param("iii", $new_status, $adminForce, $bonus_id);
        
        if ($updateStmt->execute()) {
            $success_msg = "Bónusz státusza frissítve lett!";
        } else {
            $error_msg = "Hiba történt a frissítés során.";
        }
        $updateStmt->close();
    }
}

// Bónuszok lekérése (Duplikáció megelőzése GROUP BY-al, admin freebet rejtett)
$bonuses = $conn->query("
    SELECT bc.*, bt.name AS type_name 
    FROM BonusCodes bc 
    LEFT JOIN BonusTypes bt ON bc.bonus_type_id = bt.id 
    WHERE (bc.code IS NULL OR bc.code NOT LIKE '%ADMIN_FREEBET%')
    GROUP BY bc.id
    ORDER BY bc.id DESC
");
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Bónuszok Kezelése | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body { background: #1a1a2e; color: #eee; }
        p { color: #e6e6e6 !important; }
        .text-muted { color: #9aa6b2 !important; }
        .navbar-admin { background: #16213e; }
        .sidebar {
            background: #16213e;
            min-height: calc(100vh - 56px);
            padding: 20px 0;
            width: 220px;
            flex-shrink: 0;
        }
        .sidebar .nav-link {
            color: #ccc;
            padding: 10px 20px;
            display: block;
        }
        .sidebar .nav-link:hover { color: #fff; background: #0f3460; }
        .sidebar .nav-section {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #e94560;
            padding: 14px 20px 4px;
            letter-spacing: 1px;
        }
        .main-content { flex: 1; padding: 24px; min-width: 0; }
        .table-dark th { color: #e94560; text-align: center; }
        .table-dark td { vertical-align: middle; }
        .table-dark .text-muted,
        .table-dark .fst-italic,
        .table-dark .small,
        .table-dark .text-dark {
            color: #ffffff !important;
            opacity: 1 !important;
        }
        .action-cell { text-align: right; width: 200px; }
        .bonus-edit-panel {
            background: #16213e;
            border-radius: 10px;
            padding: 20px;
            position: sticky;
            top: 80px;
            display: none;
        }
        .bonus-edit-panel.active { display: block; }
        .bonus-edit-panel label { color: #aaa; font-size: 0.8rem; margin-bottom: 2px; }
        .bonus-edit-panel .form-control,
        .bonus-edit-panel .form-select {
            background: #0f3460; border: 1px solid #333; color: #fff; font-size: 0.9rem;
        }
        .bonus-edit-panel .form-control:focus,
        .bonus-edit-panel .form-select:focus {
            background: #0f3460; border-color: #e94560; color: #fff; box-shadow: 0 0 0 0.2rem rgba(233,69,96,.25);
        }
        .bonus-edit-panel textarea { resize: vertical; min-height: 100px; }
        .bonus-edit-panel h5 { color: #e94560; margin-bottom: 16px; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-admin px-4 d-flex justify-content-between" style="height:56px;">
    <div class="d-flex align-items-center gap-3">
        <img src="../../img/logo.png" alt="logo" style="width:40px;">
        <span class="text-white fw-bold fs-5">Admin Dashboard</span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white fw-semibold d-inline-flex align-items-center gap-2">
            <?= htmlspecialchars($_SESSION['admin_username']) ?>
            <span class="badge rounded-pill bg-danger"><?= htmlspecialchars($role) ?></span>
        </span>
        <a href="/BetMatchBonus/backend/Auth/admin_logout.php" class="btn btn-outline-danger btn-sm">Kijelentkezés</a>
    </div>
</nav>

<div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar">
        <?php $activePage = 'bonuses'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="color: #e94560;">🎁 Bónuszok Kezelése</h2>
            <button class="btn btn-success" disabled><i class="fas fa-plus"></i> Új bónusz hozzáadása</button>
        </div>

        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-3">
        <!-- Bal: Táblázat -->
        <div style="flex: 1; min-width: 0;">
        <div class="table-responsive shadow-sm" style="border-radius: 8px; overflow: hidden;">
            <table class="table table-dark table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kód</th>
                        <th>Név</th>
                        <th>Típus</th>
                        <th>Bónusz összege</th>
                        <th>Jutalom típus</th>
                        <th>Forgatás</th>
                        <th class="text-center">Státusz</th>
                        <th class="text-end">Művelet</th> <!-- Jobbra igazítva -->
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bonuses && $bonuses->num_rows > 0): ?>
                        <?php while ($b = $bonuses->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center fw-bold"><?= (int)$b['id'] ?></td>
                            <td>
                                <?php if($b['code']): ?>
                                    <span class="badge bg-primary fs-6"><?= htmlspecialchars($b['code']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">NINCS KÓD</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($b['name']) ?></div>
                                <div class="text-muted small" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($b['description']) ?>
                                </div>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($b['type_name'] ?? 'N/A') ?></span></td>
                            <td>
                                <?php 
                                    $mp = (float)($b['match_percent'] ?? 0);
                                    $mb = (float)($b['max_bonus_amount'] ?? 0);
                                    $ba = (float)($b['bonus_amount'] ?? 0);
                                ?>
                                <?php if($mp > 0 && $mb > 0): ?>
                                    <span class="text-success fw-bold"><?= number_format($mp, 0, ',', ' ') ?>% max <?= number_format($mb, 0, ',', ' ') ?> Ft</span><br>
                                <?php elseif($ba > 0): ?>
                                    <span class="text-success fw-bold"><?= number_format($ba, 0, ',', ' ') ?> Ft</span><br>
                                <?php else: ?>
                                    <span class="text-muted">-</span><br>
                                <?php endif; ?>
                                <span class="text-muted small" style="color: #ffffff !important;">Min. bef: <?= number_format($b['min_deposit'], 0, ',', ' ') ?> Ft</span>
                            </td>
                            <td>
                                <?php if($b['bet_reward_type'] == 'FREE_BET'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-ticket-alt"></i> Ingyenes Fogadás</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><i class="fas fa-coins"></i> Bónusz Pénz</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?= $b['wagering_multiplier'] > 0 ? '<strong>' . (float)$b['wagering_multiplier'] . 'x</strong>' : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-center">
                                <?php if((int)$b['is_active'] === 1): ?>
                                    <span class="badge bg-success" style="font-size: 0.9em;"><i class="fas fa-check-circle"></i> AKTÍV</span>
                                <?php else: ?>
                                    <span class="badge bg-danger" style="font-size: 0.9em;"><i class="fas fa-times-circle"></i> INAKTÍV</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-cell">
                                <div class="d-flex flex-column gap-1">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="toggle_bonus_id" value="<?= $b['id'] ?>">
                                    <?php if((int)$b['is_active'] === 1): ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-power-off"></i> Kikapcsolás</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-power-off"></i> Bekapcsolás</button>
                                    <?php endif; ?>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-warning w-100 btn-edit-bonus"
                                    data-id="<?= (int)$b['id'] ?>"
                                    data-code="<?= htmlspecialchars($b['code'] ?? '') ?>"
                                    data-name="<?= htmlspecialchars($b['name']) ?>"
                                    data-description="<?= htmlspecialchars($b['description'] ?? '') ?>"
                                    data-match-percent="<?= (float)($b['match_percent'] ?? 0) ?>"
                                    data-max-bonus="<?= (float)($b['max_bonus_amount'] ?? 0) ?>"
                                    data-min-deposit="<?= (float)($b['min_deposit'] ?? 0) ?>"
                                    data-wagering="<?= (float)($b['wagering_multiplier'] ?? 0) ?>"
                                    data-max-win="<?= (float)($b['max_win_multiplier'] ?? 5) ?>"
                                    data-daily-start="<?= htmlspecialchars($b['daily_start_time'] ?? '') ?>"
                                    data-image-url="<?= htmlspecialchars($b['image_url'] ?? '') ?>"
                                ><i class="fas fa-edit"></i> Szerkesztés</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Még nincs egyetlen bónusz sem az adatbázisban.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div><!-- /Bal: Táblázat -->

        <!-- Jobb: Szerkesztő panel -->
        <div id="editPanel" class="bonus-edit-panel" style="width: 380px; flex-shrink: 0;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Bónusz szerkesztése</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="closeEditPanel">&times;</button>
            </div>
            <form method="POST" id="editBonusForm" enctype="multipart/form-data">
                <input type="hidden" name="edit_bonus_id" id="editBonusId">

                <div class="mb-2">
                    <label for="editCode">Aktiváló kód</label>
                    <input type="text" class="form-control form-control-sm" id="editCode" name="edit_code" placeholder="pl. BONUSZHETKOZNAP5K">
                </div>

                <div class="mb-2">
                    <label for="editName">Bónusz neve</label>
                    <input type="text" class="form-control form-control-sm" id="editName" name="edit_name" required>
                </div>

                <div class="mb-2">
                    <label for="editDescription">Leírás</label>
                    <textarea class="form-control form-control-sm" id="editDescription" name="edit_description" rows="4"></textarea>
                </div>

                <!-- Bónusz kép feltöltés -->
                <div class="mb-3">
                    <label class="d-block">Bónusz kép</label>
                    <div id="bonusImagePreview" class="mb-2" style="text-align:center;">
                        <img id="bonusImageThumb" src="" alt="Bónusz kép" 
                             style="max-width:100%; max-height:140px; border-radius:8px; background:#0a1628; padding:8px; display:none;">
                    </div>
                    <div class="d-flex gap-2">
                           <input type="file" class="form-control form-control-sm" id="bonusImageFile" name="edit_bonus_image"
                               accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-info" id="uploadBonusImageBtn" disabled>
                            <i class="fas fa-upload"></i> Feltöltés
                        </button>
                    </div>
                    <div id="imageUploadStatus" class="mt-1" style="font-size:0.8rem;"></div>
                </div>

                <div class="row mb-2">
                    <div class="col-6">
                        <label for="editMatchPercent">Bónusz % <small class="text-muted">(pl. 100)</small></label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="1" min="0" class="form-control" id="editMatchPercent" name="edit_match_percent">
                            <span class="input-group-text" style="background:#0f3460;color:#aaa;border-color:#333;">%</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label for="editMaxBonus">Max bónusz <small class="text-muted">(Ft)</small></label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="100" min="0" class="form-control" id="editMaxBonus" name="edit_max_bonus_amount">
                            <span class="input-group-text" style="background:#0f3460;color:#aaa;border-color:#333;">Ft</span>
                        </div>
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-6">
                        <label for="editMinDeposit">Min. befizetés <small class="text-muted">(Ft)</small></label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="100" min="0" class="form-control" id="editMinDeposit" name="edit_min_deposit">
                            <span class="input-group-text" style="background:#0f3460;color:#aaa;border-color:#333;">Ft</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label for="editWagering">Forgatás <small class="text-muted">(x-szeres)</small></label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" min="0" class="form-control" id="editWagering" name="edit_wagering_multiplier">
                            <span class="input-group-text" style="background:#0f3460;color:#aaa;border-color:#333;">x</span>
                        </div>
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-6">
                        <label for="editMaxWin">Max nyeremény <small class="text-muted">(x-szeres)</small></label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" min="1" class="form-control" id="editMaxWin" name="edit_max_win_multiplier">
                            <span class="input-group-text" style="background:#0f3460;color:#aaa;border-color:#333;">x</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label for="editDailyStart">Napi kezdés</label>
                        <input type="time" class="form-control form-control-sm" id="editDailyStart" name="edit_daily_start_time">
                    </div>
                </div>

                <!-- Előnézet -->
                <div class="mt-3 p-2 rounded" style="background: #0a1628; font-size: 0.82rem;">
                    <div class="text-muted mb-1"><i class="fas fa-calculator"></i> Előnézet (számított értékek):</div>
                    <div id="previewCalc" style="color: #4fc3f7;"></div>
                </div>

                <button type="submit" class="btn btn-warning w-100 mt-3 fw-bold">
                    <i class="fas fa-database"></i> Adatbázis frissítése
                </button>
            </form>
        </div>
        </div><!-- /d-flex gap-3 -->
    </main>
</div>

<!-- Bootstrap JS for dismissible alerts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const panel = document.getElementById('editPanel');
    const form = document.getElementById('editBonusForm');
    const fields = {
        id: document.getElementById('editBonusId'),
        code: document.getElementById('editCode'),
        name: document.getElementById('editName'),
        description: document.getElementById('editDescription'),
        matchPercent: document.getElementById('editMatchPercent'),
        maxBonus: document.getElementById('editMaxBonus'),
        minDeposit: document.getElementById('editMinDeposit'),
        wagering: document.getElementById('editWagering'),
        maxWin: document.getElementById('editMaxWin'),
        dailyStart: document.getElementById('editDailyStart')
    };
    const preview = document.getElementById('previewCalc');
    const bonusImageThumb = document.getElementById('bonusImageThumb');
    const bonusImageFile = document.getElementById('bonusImageFile');
    const uploadBonusImageBtn = document.getElementById('uploadBonusImageBtn');
    const imageUploadStatus = document.getElementById('imageUploadStatus');

    function updatePreview() {
        const pct = parseFloat(fields.matchPercent.value) || 0;
        const maxB = parseFloat(fields.maxBonus.value) || 0;
        const minD = parseFloat(fields.minDeposit.value) || 0;
        const wag = parseFloat(fields.wagering.value) || 0;
        const maxW = parseFloat(fields.maxWin.value) || 5;

        let lines = [];
        if (pct > 0 && maxB > 0) {
            lines.push(`💰 ${minD.toLocaleString('hu')} Ft befizetés → ${Math.min(minD * pct / 100, maxB).toLocaleString('hu')} Ft bónusz`);
            lines.push(`💰 Max bónusz: ${maxB.toLocaleString('hu')} Ft (${pct}%)`);
        }
        if (wag > 0 && maxB > 0) {
            lines.push(`🔄 Forgatás: ${(maxB * wag).toLocaleString('hu')} Ft (${wag}x × ${maxB.toLocaleString('hu')})`);
        }
        if (maxW > 0 && maxB > 0) {
            lines.push(`🏆 Max nyeremény: ${(maxB * maxW).toLocaleString('hu')} Ft (${maxW}x)`);
        }
        preview.innerHTML = lines.join('<br>') || 'Töltsd ki a mezőket...';
    }

    // Szerkesztés gomb kezelése
    document.querySelectorAll('.btn-edit-bonus').forEach(btn => {
        btn.addEventListener('click', function() {
            fields.id.value = this.dataset.id;
            fields.code.value = this.dataset.code;
            fields.name.value = this.dataset.name;
            fields.description.value = this.dataset.description;
            fields.matchPercent.value = this.dataset.matchPercent;
            fields.maxBonus.value = this.dataset.maxBonus;
            fields.minDeposit.value = this.dataset.minDeposit;
            fields.wagering.value = this.dataset.wagering;
            fields.maxWin.value = this.dataset.maxWin;
            fields.dailyStart.value = this.dataset.dailyStart || '';

            // Kép előnézet betöltése
            var imgUrl = this.dataset.imageUrl || '';
            if (imgUrl) {
                bonusImageThumb.src = imgUrl + (imgUrl.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
                bonusImageThumb.style.display = 'block';
            } else {
                bonusImageThumb.style.display = 'none';
            }
            bonusImageFile.value = '';
            uploadBonusImageBtn.disabled = true;
            imageUploadStatus.textContent = '';

            panel.classList.add('active');
            updatePreview();
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Bezárás
    document.getElementById('closeEditPanel').addEventListener('click', function() {
        panel.classList.remove('active');
    });

    // Előnézet frissítés inputoknál
    [fields.matchPercent, fields.maxBonus, fields.minDeposit, fields.wagering, fields.maxWin].forEach(el => {
        el.addEventListener('input', updatePreview);
    });

    // Kép feltöltés kezelése
    bonusImageFile.addEventListener('change', function() {
        uploadBonusImageBtn.disabled = !this.files.length;
        if (this.files.length) {
            var reader = new FileReader();
            reader.onload = function(e) {
                bonusImageThumb.src = e.target.result;
                bonusImageThumb.style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    uploadBonusImageBtn.addEventListener('click', function() {
        var bonusId = fields.id.value;
        var file = bonusImageFile.files[0];
        if (!bonusId || !file) return;

        var formData = new FormData();
        formData.append('bonus_id', bonusId);
        formData.append('bonus_image', file);

        uploadBonusImageBtn.disabled = true;
        imageUploadStatus.innerHTML = '<span style="color:#4fc3f7;"><i class="fas fa-spinner fa-spin"></i> Feltöltés...</span>';

        fetch('../../backend/ApiRequest/admin_bonus_image.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) {
            if (!r.ok) {
                return r.text().then(function(txt) {
                    throw new Error('HTTP ' + r.status + ': ' + (txt || 'ismeretlen válasz'));
                });
            }
            return r.text().then(function(txt) {
                try {
                    return JSON.parse(txt);
                } catch (e) {
                    throw new Error('Érvénytelen JSON válasz: ' + txt);
                }
            });
        })
        .then(function(data) {
            if (data.success) {
                imageUploadStatus.innerHTML = '<span style="color:#4caf50;"><i class="fas fa-check-circle"></i> ' + data.message + '</span>';
                bonusImageThumb.src = data.image_url + '?t=' + Date.now();
                bonusImageThumb.style.display = 'block';
                // Frissítjük az edit gomb data attribútumát is
                var editBtn = document.querySelector('.btn-edit-bonus[data-id="' + bonusId + '"]');
                if (editBtn) editBtn.dataset.imageUrl = data.image_url;
            } else {
                imageUploadStatus.innerHTML = '<span style="color:#e94560;"><i class="fas fa-exclamation-triangle"></i> ' + data.error + '</span>';
            }
            uploadBonusImageBtn.disabled = false;
        })
        .catch(function(err) {
            imageUploadStatus.innerHTML = '<span style="color:#e94560;"><i class="fas fa-exclamation-triangle"></i> Feltöltési hiba: ' + err.message + '</span>';
            uploadBonusImageBtn.disabled = false;
        });
    });
});
</script>
</body>
</html>