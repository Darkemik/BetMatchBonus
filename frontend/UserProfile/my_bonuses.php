<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Felhasználó bónusz egyenlegének lekérése
$hasBonusBalance = false;
$colStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'bonus_balance'");
$colStmt->execute();
$colRes = $colStmt->get_result()->fetch_assoc();
$colStmt->close();
if ($colRes && (int)$colRes['cnt'] > 0) {
    $hasBonusBalance = true;
}

$balance_stmt = $conn->prepare($hasBonusBalance
    ? "SELECT balance, bonus_balance FROM Users WHERE id = ?"
    : "SELECT balance FROM Users WHERE id = ?"
);
$balance_stmt->bind_param("i", $user_id);
$balance_stmt->execute();
$balance_result = $balance_stmt->get_result();
$user_balances = $balance_result->fetch_assoc();
$balance_stmt->close();

$regular_balance = $user_balances['balance'] ?? 0;
$bonus_balance   = $hasBonusBalance ? ($user_balances['bonus_balance'] ?? 0) : 0;

// Felhasználó bónuszainak lekérése az aktív bónuszokkal együtt
$query = "SELECT ub.id, ub.bonus_id, bc.name as bonus_name, bc.description as bonus_description,
                 bc.valid_weekdays_only, bc.min_deposit, bc.match_percent, bc.max_bonus_amount,
                 bc.wagering_multiplier, bc.min_combo, bc.min_odds, bc.min_odds_per_event,
                 bc.activation_expire_hours, bc.bonus_type_id, bc.is_step_bonus, bc.step_number,
                 bc.bonus_trigger, bc.bet_reward_type, ub.granted_amount, ub.status, ub.expires_at, ub.wagering_progress,
                 ub.wagering_required, ub.used, ub.created_at 
          FROM UserBonuses ub
          LEFT JOIN BonusCodes bc ON ub.bonus_id = bc.id
          WHERE ub.user_id = ? 
          ORDER BY ub.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bonuses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Aktív és lejárt bónuszok száma
$active_bonuses = 0;
$expired_bonuses = 0;
$total_free_bet_amount = 0;
$current_bonuses = [];
$archived_bonuses = [];

foreach ($bonuses as $bonus) {
    $isExpiredByTime = !empty($bonus['expires_at']) && strtotime($bonus['expires_at']) <= time();
    $isActiveAndValid = ($bonus['status'] === 'ACTIVE')
        && (empty($bonus['expires_at']) || strtotime($bonus['expires_at']) > time());
    $isArchived = ((int)($bonus['used'] ?? 0) === 1)
        || in_array((string)($bonus['status'] ?? ''), ['COMPLETED', 'FAILED', 'EXPIRED'], true)
        || (($bonus['status'] === 'ACTIVE') && $isExpiredByTime);

    if ($isArchived) {
        $archived_bonuses[] = $bonus;
    } else {
        $current_bonuses[] = $bonus;
    }

    if ($isActiveAndValid) {
        $active_bonuses++;
        if (strtoupper((string)($bonus['bet_reward_type'] ?? '')) === 'FREE_BET') {
            $total_free_bet_amount += (float)$bonus['granted_amount'];
        }
    }
}

$expired_bonuses = count($archived_bonuses);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="userProfile.myBonuses.pageTitle">Bónuszaim | BetMatchBonus</title>
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
                    <a href="personal_data.php" class="profile-nav-item"><i class="fas fa-user"></i> <span data-i18n="auth.personalData">Személyes Adatok</span></a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> <span data-i18n="auth.changePassword">Jelszó Módosítás</span></a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> <span data-i18n="auth.deposit">Befizetés</span></a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span></a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span></a>
                    <a href="my_bonuses.php" class="profile-nav-item active"><i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span></a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> <span data-i18n="auth.activityLog">Napló</span></a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> <span data-i18n="auth.logout">Kijelentkezés</span></a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span></h1>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bonus-card">
                                <div class="card-body">
                                    <h6 class="card-title" data-i18n="userProfile.myBonuses.activeBonuses">Aktív Bónuszok</h6>
                                    <h2 class="text-success"><?php echo $active_bonuses; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bonus-card">
                                <div class="card-body">
                                    <h6 class="card-title" data-i18n="userProfile.myBonuses.freeBets">Ingyenes Fogadások</h6>
                                    <h2 class="text-primary"><?php echo number_format($total_free_bet_amount, 0, ',', ' '); ?> FT</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bonus-card">
                                <div class="card-body">
                                    <h6 class="card-title" data-i18n="userProfile.myBonuses.bonusBalance">Bónusz Egyenleg</h6>
                                    <h2 class="text-warning"><?php echo number_format($bonus_balance, 0, ',', ' '); ?> FT</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bonus-card">
                                <div class="card-body">
                                    <h6 class="card-title" data-i18n="userProfile.myBonuses.expiredUsed">Lejárt/Felhasznált</h6>
                                    <h2 class="text-danger"><?php echo $expired_bonuses; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bónuszkód Beváltó Szekció -->
                    <div class="card mb-4 shadow-sm" style="background-color: #16213e; border: 1px solid #e94560;">
                        <div class="card-body">
                            <h5 class="card-title" style="color: #e94560;"><i class="fas fa-ticket-alt"></i> <span data-i18n="userProfile.myBonuses.havePromoCode">Van promóciós kódod?</span></h5>
                            <form id="claimBonusForm" class="d-flex mt-3 gap-2">
                                <style>
                                    #bonus_code::placeholder { 
                                        color: rgba(255, 255, 255, 0.7) !important; 
                                        opacity: 1; 
                                    }
                                </style>
                                <input type="text" id="bonus_code" name="bonus_code" class="form-control text-white" placeholder="Írd be ide a bónuszkódot" data-i18n-placeholder="userProfile.myBonuses.codePlaceholder" required style="background: #0f3460; color: #ffffff !important; border: 1px solid #333;">
                                <button type="submit" class="btn btn-primary" style="background-color: #e94560; border-color: #e94560; font-weight: bold;" data-i18n="userProfile.myBonuses.redeem">Beváltás</button>
                            </form>
                            <div id="bonusMessage" class="mt-2" style="display:none; font-weight: bold;"></div>
                        </div>
                    </div>
                    
                    <?php if (empty($current_bonuses)): ?>
                        <div class="alert alert-info" style="background: #0f3460; color: #fff; border: none;">
                            <i class="fas fa-info-circle"></i> <span data-i18n="userProfile.myBonuses.noActive">Jelenleg nincs aktív vagy várakozó bónuszod.</span> <a href="../../frontend/Bonus/bonus.php" style="color: #e94560; font-weight: bold;" data-i18n="nav.bonuses">Bónuszok</a> <span data-i18n="userProfile.myBonuses.visitBonuses">oldalt a lehetőségekért!</span>
                        </div>
                    <?php else: ?>
                        <div class="bonus-list">
                            <?php foreach ($current_bonuses as $bonus): ?>
                                <div class="card mb-3" style="background: #16213e; border: 1px solid #333; color: #eee;">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h5 class="card-title" style="color: #e94560;">
                                                    <i class="fas fa-gift"></i> 
                                                    <?php echo htmlspecialchars($bonus['bonus_name'] ?? 'Ismeretlen Bónusz'); ?>
                                                </h5>
                                                <p class="card-text mb-1">
                                                        <strong data-i18n="userProfile.myBonuses.value">Érték:</strong> <span class="text-success"><?php echo number_format($bonus['granted_amount'], 0, ',', ' '); ?> FT</span>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <strong data-i18n="userProfile.myBonuses.wageringRequired">Szükséges forgatás:</strong> 
                                                    <?php 
                                                        if ($bonus['wagering_required'] > 0) {
                                                            $progress = $bonus['wagering_progress'] ?? 0;
                                                            $percentage = min(100, ($progress / $bonus['wagering_required']) * 100);
                                                            echo number_format($progress, 0, ',', ' ') . ' / ' . number_format($bonus['wagering_required'], 0, ',', ' ') . ' FT (' . round($percentage, 1) . '%)';
                                                        } else {
                                                            echo '<span class="text-white" data-i18n="userProfile.myBonuses.noWagering">Nincs szükséges forgatás</span>';
                                                        }
                                                    ?>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <strong data-i18n="userProfile.myBonuses.expiry">Lejárat:</strong> 
                                                    <?php
                                                        if (!empty($bonus['expires_at'])) {
                                                            echo date('Y-m-d H:i', strtotime($bonus['expires_at']));
                                                        } elseif (!empty($bonus['activation_expire_hours']) && (int)$bonus['activation_expire_hours'] > 0 && !empty($bonus['created_at'])) {
                                                            $fallbackExpire = new DateTime($bonus['created_at']);
                                                            $fallbackExpire->modify('+' . (int)$bonus['activation_expire_hours'] . ' hours');
                                                            echo $fallbackExpire->format('Y-m-d H:i');
                                                        } elseif ((int)($bonus['bonus_type_id'] ?? 0) === 1 && (int)($bonus['is_step_bonus'] ?? 0) === 1 && (int)($bonus['step_number'] ?? 0) === 2 && !empty($bonus['created_at'])) {
                                                            // Visszafelé kompatibilis fallback: régi adatoknál is 48 órás lejárat.
                                                            $fallbackExpire = new DateTime($bonus['created_at']);
                                                            $fallbackExpire->modify('+48 hours');
                                                            echo $fallbackExpire->format('Y-m-d H:i');
                                                        } elseif (!empty($bonus['valid_weekdays_only']) && !empty($bonus['created_at'])) {
                                                            $createdAt = new DateTime($bonus['created_at']);
                                                            $weekday = (int)$createdAt->format('N');
                                                            $daysUntilFriday = max(0, 5 - $weekday);
                                                            $createdAt->modify('+' . $daysUntilFriday . ' day');
                                                            $createdAt->setTime(23, 59, 0);
                                                            echo $createdAt->format('Y-m-d H:i');
                                                        } else {
                                                            echo '<span class="text-white" data-i18n="userProfile.myBonuses.notSpecified">Nincs megadva</span>';
                                                        }
                                                    ?>
                                                </p>
                                                <div class="mt-3">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm bonus-desc-btn"
                                                        style="background: linear-gradient(135deg, #1f8f5f 0%, #166748 100%); color: #fff; border: none; font-weight: 600; padding: 8px 12px; border-radius: 8px;"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#bonusDescriptionModal"
                                                        data-bonus-name="<?= htmlspecialchars($bonus['bonus_name'] ?? 'Ismeretlen Bónusz', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-bonus-description="<?= htmlspecialchars($bonus['bonus_description'] ?? 'Nincs külön leírás ehhez a bónuszhoz.', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-min-deposit="<?= number_format((float)($bonus['min_deposit'] ?? 0), 0, ',', ' ') ?>"
                                                        data-match-percent="<?= number_format((float)($bonus['match_percent'] ?? 0), 0, ',', ' ') ?>"
                                                        data-max-bonus="<?= number_format((float)($bonus['max_bonus_amount'] ?? 0), 0, ',', ' ') ?>"
                                                        data-wagering-multiplier="<?= number_format((float)($bonus['wagering_multiplier'] ?? 0), 1, ',', ' ') ?>"
                                                        data-min-combo="<?= (int)($bonus['min_combo'] ?? 0) ?>"
                                                        data-min-odds="<?= number_format((float)($bonus['min_odds'] ?? 0), 2, ',', ' ') ?>"
                                                        data-min-odds-event="<?= number_format((float)($bonus['min_odds_per_event'] ?? 0), 2, ',', ' ') ?>"
                                                        data-bonus-trigger="<?= htmlspecialchars((string)($bonus['bonus_trigger'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    >
                                                        <i class="fas fa-info-circle"></i> <span data-i18n="userProfile.myBonuses.bonusDescription">Bónusz leírása</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <?php 
                                                    $is_valid = $bonus['expires_at'] ? strtotime($bonus['expires_at']) > time() : true;
                                                    
                                                    if ($bonus['status'] === 'PENDING') {
                                                        echo '<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Várakozik (Feltételre)</span>';
                                                    } elseif ($bonus['status'] === 'ACTIVE' && $is_valid) {
                                                        echo '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Aktív</span>';
                                                    } elseif ($bonus['status'] === 'COMPLETED') {
                                                        echo '<span class="badge bg-primary"><i class="fas fa-trophy"></i> Teljesítve</span>';
                                                    } elseif ($bonus['used']) {
                                                        echo '<span class="badge bg-info text-dark"><i class="fas fa-check"></i> Felhasznált</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Lejárt</span>';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($archived_bonuses)): ?>
                        <div class="card mt-4" style="background: #121212; border: 1px solid #3f3f3f; color: #ddd;">
                            <div class="card-body">
                                <h5 class="mb-3" style="color: #f2b705;"><i class="fas fa-archive"></i> <span data-i18n="userProfile.myBonuses.usedOrExpired">Felhasznált / Lejárt Bónuszok</span></h5>
                                <?php foreach ($archived_bonuses as $bonus): ?>
                                    <div class="card mb-3" style="background: #1b2436; border: 1px solid #3d4658; color: #ddd;">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <h6 class="card-title" style="color: #f39c12; margin-bottom: 10px;">
                                                        <i class="fas fa-gift"></i>
                                                        <?php echo htmlspecialchars($bonus['bonus_name'] ?? 'Ismeretlen Bónusz'); ?>
                                                    </h6>
                                                    <p class="card-text mb-1">
                                                        <strong data-i18n="userProfile.myBonuses.value">Érték:</strong> <span class="text-warning"><?php echo number_format($bonus['granted_amount'], 0, ',', ' '); ?> FT</span>
                                                    </p>
                                                    <p class="card-text mb-1">
                                                        <strong data-i18n="userProfile.myBonuses.expiry">Lejárat:</strong>
                                                        <?php
                                                            if (!empty($bonus['expires_at'])) {
                                                                echo date('Y-m-d H:i', strtotime($bonus['expires_at']));
                                                            } elseif (!empty($bonus['activation_expire_hours']) && (int)$bonus['activation_expire_hours'] > 0 && !empty($bonus['created_at'])) {
                                                                $fallbackExpire = new DateTime($bonus['created_at']);
                                                                $fallbackExpire->modify('+' . (int)$bonus['activation_expire_hours'] . ' hours');
                                                                echo $fallbackExpire->format('Y-m-d H:i');
                                                            } elseif ((int)($bonus['bonus_type_id'] ?? 0) === 1 && (int)($bonus['is_step_bonus'] ?? 0) === 1 && (int)($bonus['step_number'] ?? 0) === 2 && !empty($bonus['created_at'])) {
                                                                $fallbackExpire = new DateTime($bonus['created_at']);
                                                                $fallbackExpire->modify('+48 hours');
                                                                echo $fallbackExpire->format('Y-m-d H:i');
                                                            } elseif (!empty($bonus['valid_weekdays_only']) && !empty($bonus['created_at'])) {
                                                                $createdAt = new DateTime($bonus['created_at']);
                                                                $weekday = (int)$createdAt->format('N');
                                                                $daysUntilFriday = max(0, 5 - $weekday);
                                                                $createdAt->modify('+' . $daysUntilFriday . ' day');
                                                                $createdAt->setTime(23, 59, 0);
                                                                echo $createdAt->format('Y-m-d H:i');
                                                            } else {
                                                                echo '<span class="text-white" data-i18n="userProfile.myBonuses.notSpecified">Nincs megadva</span>';
                                                            }
                                                        ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <?php
                                                        $is_valid = $bonus['expires_at'] ? strtotime($bonus['expires_at']) > time() : true;

                                                        if ($bonus['status'] === 'COMPLETED') {
                                                            echo '<span class="badge bg-primary"><i class="fas fa-trophy"></i> Teljesítve</span>';
                                                        } elseif ($bonus['used']) {
                                                            echo '<span class="badge bg-info text-dark"><i class="fas fa-check"></i> Felhasznált</span>';
                                                        } elseif (!$is_valid) {
                                                            echo '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Lejárt</span>';
                                                        } else {
                                                            echo '<span class="badge bg-secondary" data-i18n="userProfile.myBonuses.archived">Archivált</span>';
                                                        }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <a href="personal_data.php" class="btn btn-secondary mt-3"><i class="fas fa-undo"></i> <span data-i18n="common.back">Vissza</span></a>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <div class="modal fade" id="bonusDescriptionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #16213e; color: #eee; border: 1px solid #e94560;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(233, 69, 96, 0.35);">
                    <h5 class="modal-title" id="bonusDescTitle" style="color: #e94560;" data-i18n="userProfile.myBonuses.bonusDescription">Bónusz leírása</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                </div>
                <div class="modal-body">
                    <p id="bonusDescText" class="mb-3" style="line-height: 1.5;" data-i18n="userProfile.myBonuses.noDescription">Nincs leírás.</p>
                    <ul class="list-group" id="bonusRequirementsList">
                    </ul>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(233, 69, 96, 0.35);">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal" data-i18n="common.close">Bezárás</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    
    <!-- Új JavaScript a bónuszkód beváltásához -->
    <script>
    document.getElementById('claimBonusForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const code = document.getElementById('bonus_code').value;
        const msgDiv = document.getElementById('bonusMessage');
        const btn = this.querySelector('button[type="submit"]');
        
        btn.disabled = true;
        msgDiv.style.display = 'none';

        const formData = new FormData();
        formData.append('bonus_code', code);

        fetch('../../backend/ApiRequest/claim_bonus.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            msgDiv.style.display = 'block';
            if(data.success) {
                msgDiv.className = 'mt-2 text-success';
                msgDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                setTimeout(() => location.reload(), 2000); // Újratöltjük az oldalt, hogy megjelenjen a listában
            } else {
                msgDiv.className = 'mt-2 text-danger';
                msgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + data.message;
                btn.disabled = false;
            }
        })
        .catch(error => {
            msgDiv.style.display = 'block';
            msgDiv.className = 'mt-2 text-danger';
            msgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (window.i18n ? window.i18n('userProfile.myBonuses.networkError', 'Hálózati hiba történt.') : 'Hálózati hiba történt.');
            btn.disabled = false;
        });
    });

    document.querySelectorAll('.bonus-desc-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const title = btn.getAttribute('data-bonus-name') || (window.i18n ? window.i18n('userProfile.myBonuses.bonusDescription', 'Bónusz leírása') : 'Bónusz leírása');
            const description = btn.getAttribute('data-bonus-description') || (window.i18n ? window.i18n('userProfile.myBonuses.noDescriptionLong', 'Nincs külön leírás ehhez a bónuszhoz.') : 'Nincs külön leírás ehhez a bónuszhoz.');
            const minDeposit = btn.getAttribute('data-min-deposit') || '0';
            const matchPercent = btn.getAttribute('data-match-percent') || '0';
            const maxBonus = btn.getAttribute('data-max-bonus') || '0';
            const wageringMultiplier = btn.getAttribute('data-wagering-multiplier') || '0';
            const minCombo = btn.getAttribute('data-min-combo') || '0';
            const minOdds = btn.getAttribute('data-min-odds') || '0';
            const minOddsEvent = btn.getAttribute('data-min-odds-event') || '0';
            const bonusTrigger = btn.getAttribute('data-bonus-trigger') || '';

            document.getElementById('bonusDescTitle').textContent = title;
            document.getElementById('bonusDescText').textContent = description;

            const reqList = document.getElementById('bonusRequirementsList');
            reqList.innerHTML = '';

            const addReq = (label, value) => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.style.background = '#0f3460';
                li.style.color = '#fff';
                li.style.border = '1px solid rgba(255,255,255,0.1)';
                li.innerHTML = `<span>${label}</span><strong>${value}</strong>`;
                reqList.appendChild(li);
            };

            addReq(window.i18n ? window.i18n('userProfile.myBonuses.activationMode', 'Aktiválás módja') : 'Aktiválás módja', bonusTrigger === 'DEPOSIT' ? (window.i18n ? window.i18n('userProfile.myBonuses.depositBased', 'Befizetéshez kötött') : 'Befizetéshez kötött') : (window.i18n ? window.i18n('userProfile.myBonuses.instant', 'Azonnali') : 'Azonnali'));
            addReq(window.i18n ? window.i18n('userProfile.myBonuses.minDeposit', 'Minimum befizetés') : 'Minimum befizetés', `${minDeposit} FT`);

            if (Number(String(matchPercent).replace(',', '.')) > 0) {
                addReq(window.i18n ? window.i18n('userProfile.myBonuses.bonusRate', 'Bónusz mértéke') : 'Bónusz mértéke', `${matchPercent}% (max ${maxBonus} FT)`);
            } else {
                addReq(window.i18n ? window.i18n('userProfile.myBonuses.maxBonus', 'Maximális bónusz') : 'Maximális bónusz', `${maxBonus} FT`);
            }

            addReq(window.i18n ? window.i18n('userProfile.myBonuses.wageringRequirement', 'Forgatási követelmény') : 'Forgatási követelmény', `${wageringMultiplier}x`);

            if (parseInt(minCombo, 10) > 0) {
                addReq(window.i18n ? window.i18n('userProfile.myBonuses.minCombo', 'Minimum kötés') : 'Minimum kötés', `${minCombo} ${window.i18n ? window.i18n('userProfile.myBonuses.events', 'esemény') : 'esemény'}`);
            }
            if (Number(String(minOdds).replace(',', '.')) > 0) {
                addReq(window.i18n ? window.i18n('userProfile.myBonuses.minTotalOdds', 'Minimum össz odds') : 'Minimum össz odds', minOdds);
            }
            if (Number(String(minOddsEvent).replace(',', '.')) > 0) {
                addReq(window.i18n ? window.i18n('userProfile.myBonuses.minOddsPerEvent', 'Minimum odds eseményenként') : 'Minimum odds eseményenként', minOddsEvent);
            }
        });
    });
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>