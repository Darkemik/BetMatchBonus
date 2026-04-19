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
                 bc.bonus_trigger, bc.bet_reward_type, ub.granted_amount, ub.bonus_balance AS individual_balance,
                 COALESCE(ub.free_bet_amount, 0) AS free_bet_amount, COALESCE(bc.match_percent, 0) AS match_percent,
                 COALESCE(bc.min_deposit, 0) AS min_deposit_val, COALESCE(bc.min_odds, 0) AS min_odds_val,
                 ub.status, ub.expires_at, ub.wagering_progress,
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
            $total_free_bet_amount += (float)($bonus['free_bet_amount'] ?? $bonus['granted_amount'] ?? 0);
        }
    }
}

$expired_bonuses = count($archived_bonuses);

// LOSS trigger bónuszokhoz: mai cashback free betek lekérdezése
$lossCashbackStats = [];
foreach ($current_bonuses as $cb) {
    if (strtoupper($cb['bonus_trigger'] ?? '') !== 'LOSS') continue;
    $cbBonusId = (int)$cb['bonus_id'];
    // Ma kapott free betek száma és összege
    $cbStatsStmt = $conn->prepare("
        SELECT COUNT(*) AS cnt, COALESCE(SUM(free_bet_amount), 0) AS total_amount
        FROM UserBonuses
        WHERE user_id = ? AND bonus_id = ?
          AND COALESCE(free_bet_amount, 0) > 0
          AND DATE(created_at) = CURDATE()
          AND id != ?
    ");
    $cbStatsStmt->bind_param("iii", $user_id, $cbBonusId, $cb['id']);
    $cbStatsStmt->execute();
    $cbStatsRow = $cbStatsStmt->get_result()->fetch_assoc();
    $cbStatsStmt->close();
    // Utolsó kapott free bet
    $cbLastStmt = $conn->prepare("
        SELECT free_bet_amount, created_at FROM UserBonuses
        WHERE user_id = ? AND bonus_id = ?
          AND COALESCE(free_bet_amount, 0) > 0
          AND id != ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $cbLastStmt->bind_param("iii", $user_id, $cbBonusId, $cb['id']);
    $cbLastStmt->execute();
    $cbLastRow = $cbLastStmt->get_result()->fetch_assoc();
    $cbLastStmt->close();
    $lossCashbackStats[$cb['id']] = [
        'today_count' => (int)($cbStatsRow['cnt'] ?? 0),
        'today_total' => (float)($cbStatsRow['total_amount'] ?? 0),
        'last_amount' => $cbLastRow ? (float)$cbLastRow['free_bet_amount'] : 0,
        'last_date' => $cbLastRow ? $cbLastRow['created_at'] : null,
    ];
}
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
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
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
                    <a href="notifications.php" class="profile-nav-item"><i class="fas fa-bell"></i> <span data-i18n="auth.notifications">Értesítések</span></a>
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
                                                    <?php
                                                        $bonusNameRaw = (string)($bonus['bonus_name'] ?? 'Ismeretlen Bónusz');
                                                        if (trim($bonusNameRaw) === 'Vesztes fogadás cashback (30% Free Bet)') {
                                                            echo '<span data-i18n="userProfile.myBonuses.lossBetCashbackName">Vesztes fogadás cashback (30% Free Bet)</span>';
                                                        } else {
                                                            echo '<span class="js-bonus-name" data-bonus-name="' . htmlspecialchars($bonusNameRaw, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($bonusNameRaw) . '</span>';
                                                        }
                                                    ?>
                                                </h5>
                                                <p class="card-text mb-1">
                                                        <strong data-i18n="userProfile.myBonuses.value">Érték:</strong> 
                                                        <?php if (strtoupper($bonus['bonus_trigger'] ?? '') === 'LOSS'): ?>
                                                            <span class="text-info"><i class="fas fa-sync-alt"></i> <span data-i18n="userProfile.myBonuses.lossBetValuePrefix">Vesztes fogadásból</span> <?php echo number_format((float)($bonus['match_percent'] ?? 0), 0); ?>% Free Bet</span>
                                                        <?php elseif ($bonus['status'] === 'PENDING' && strtoupper($bonus['bonus_trigger'] ?? '') === 'DEPOSIT' && (float)$bonus['granted_amount'] == 0): ?>
                                                            <span class="text-warning"><i class="fas fa-hourglass-half"></i> Befizetés után derül ki</span>
                                                        <?php else: ?>
                                                            <span class="text-success"><?php echo number_format($bonus['granted_amount'], 0, ',', ' '); ?> FT</span>
                                                        <?php endif; ?>
                                                </p>
                                                <?php
                                                    $indivBal = (float)($bonus['individual_balance'] ?? 0);
                                                    $isActiveBonusMoney = ($bonus['status'] === 'ACTIVE') && strtoupper($bonus['bet_reward_type'] ?? '') !== 'FREE_BET';
                                                    if ($isActiveBonusMoney && $indivBal > 0):
                                                ?>
                                                <p class="card-text mb-1">
                                                    <strong style="color:#7c3aed;">🎁 Bónusz egyenleg:</strong>
                                                    <span style="color:#7c3aed;font-weight:700;"><?php echo number_format($indivBal, 0, ',', ' '); ?> FT</span>
                                                </p>
                                                <?php endif; ?>

                                                <?php
                                                // LOSS trigger (cashback) bónusz speciális megjelenítés
                                                $isLossTrigger = strtoupper($bonus['bonus_trigger'] ?? '') === 'LOSS';
                                                if ($isLossTrigger && $bonus['status'] === 'ACTIVE'):
                                                    $cbStats = $lossCashbackStats[$bonus['id']] ?? ['today_count' => 0, 'today_total' => 0, 'last_amount' => 0, 'last_date' => null];
                                                    $cbPercent = (float)($bonus['match_percent'] ?? 0);
                                                    $cbMinStake = (float)($bonus['min_deposit_val'] ?? 0);
                                                    $cbMinOdds = (float)($bonus['min_odds_val'] ?? 0);
                                                ?>
                                                <div class="mt-2 mb-2 p-3" style="background: #0f3460; border-radius: 10px; border: 1px solid rgba(233,69,96,0.3);">
                                                    <p class="mb-2" style="font-size:0.9rem;"><i class="fas fa-shield-alt" style="color:#e94560;"></i> <strong data-i18n="userProfile.myBonuses.cashbackConditionsTitle">Cashback feltételek:</strong></p>
                                                    <ul class="mb-2" style="font-size:0.85rem; padding-left: 1.2rem; margin-bottom: 0;">
                                                        <li><span data-i18n="userProfile.myBonuses.cashbackMinStake">Min. tét:</span> <strong><?php echo number_format($cbMinStake, 0, ',', ' '); ?> <span data-i18n="userProfile.myBonuses.currencyFt">Ft</span></strong></li>
                                                        <li><span data-i18n="userProfile.myBonuses.cashbackMinOdds">Min. odds:</span> <strong><?php echo number_format($cbMinOdds, 2, ',', ''); ?></strong></li>
                                                        <li><span data-i18n="userProfile.myBonuses.cashbackRefund">Visszatérítés:</span> <strong><?php echo number_format($cbPercent, 0); ?>%</strong> <span data-i18n="userProfile.myBonuses.cashbackFreeBetForm">Free Bet formájában</span></li>
                                                        <li><span data-i18n="userProfile.myBonuses.cashbackDailyLimit">Napi limit:</span> <strong data-i18n="userProfile.myBonuses.cashbackDailyLimitValue">1 alkalom</strong></li>
                                                    </ul>
                                                    <hr style="border-color: rgba(255,255,255,0.15); margin: 8px 0;">
                                                    <?php if ($cbStats['today_count'] > 0): ?>
                                                        <p class="mb-1" style="font-size:0.85rem;"><i class="fas fa-check-circle text-success"></i> <strong data-i18n="userProfile.myBonuses.todayCashbackLabel">Mai cashback:</strong> <?php echo number_format($cbStats['today_total'], 0, ',', ' '); ?> Ft <span data-i18n="userProfile.myBonuses.freeBetCredited">Free Bet jóváírva</span></p>
                                                        <p class="mb-0" style="font-size:0.8rem; color: #aaa;"><i class="fas fa-info-circle"></i> Ma már kaptál cashback-et. Holnap újra elérhető.</p>
                                                    <?php else: ?>
                                                        <p class="mb-1" style="font-size:0.85rem;"><i class="fas fa-hourglass-half text-warning"></i> <strong data-i18n="userProfile.myBonuses.todayCashbackLabel">Mai cashback:</strong> <span data-i18n="userProfile.myBonuses.notActivatedYet">Még nem aktiválódott</span></p>
                                                        <p class="mb-0" style="font-size:0.8rem; color: #aaa;"><i class="fas fa-info-circle"></i> <span data-i18n="userProfile.myBonuses.betAtLeast">Fogadj legalább</span> <?php echo number_format($cbMinStake, 0, ',', ' '); ?> Ft-ot (min. <?php echo number_format($cbMinOdds, 2, ',', ''); ?> odds). <span data-i18n="userProfile.myBonuses.ifLostYouGet">Ha veszít, megkapod a</span> <?php echo number_format($cbPercent, 0); ?>%-ot <span data-i18n="userProfile.myBonuses.freeBetAs">Free Bet-ként</span>.</p>
                                                    <?php endif; ?>
                                                    <?php if ($cbStats['last_amount'] > 0): ?>
                                                        <p class="mb-0 mt-1" style="font-size:0.8rem; color: #bbb;"><i class="fas fa-history"></i> Utolsó cashback: <?php echo number_format($cbStats['last_amount'], 0, ',', ' '); ?> Ft (<?php echo date('Y-m-d H:i', strtotime($cbStats['last_date'])); ?>)</p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (strtoupper($bonus['bonus_trigger'] ?? '') !== 'LOSS'): ?>
                                                <p class="card-text mb-1">
                                                    <strong data-i18n="userProfile.myBonuses.wageringRequired">Szükséges forgatás:</strong> 
                                                    <?php 
                                                        if ($bonus['status'] === 'PENDING' && strtoupper($bonus['bonus_trigger'] ?? '') === 'DEPOSIT' && (float)$bonus['wagering_required'] == 0) {
                                                            $wagerMultiplier = (float)($bonus['wagering_multiplier'] ?? 0);
                                                            if ($wagerMultiplier > 0) {
                                                                echo '<span class="text-warning"><i class="fas fa-hourglass-half"></i> ' . number_format($wagerMultiplier, 0) . 'x forgatás (befizetés után számolódik)</span>';
                                                            } else {
                                                                echo '<span class="text-warning"><i class="fas fa-hourglass-half"></i> Befizetés után derül ki</span>';
                                                            }
                                                        } elseif ($bonus['wagering_required'] > 0) {
                                                            $progress = $bonus['wagering_progress'] ?? 0;
                                                            $percentage = min(100, ($progress / $bonus['wagering_required']) * 100);
                                                            echo number_format($progress, 0, ',', ' ') . ' / ' . number_format($bonus['wagering_required'], 0, ',', ' ') . ' FT (' . round($percentage, 1) . '%)';
                                                        } else {
                                                            echo '<span class="text-white" data-i18n="userProfile.myBonuses.noWagering">Nincs szükséges forgatás</span>';
                                                        }
                                                    ?>
                                                </p>
                                                <?php if ($bonus['wagering_required'] > 0): ?>
                                                    <?php
                                                        $progress = $bonus['wagering_progress'] ?? 0;
                                                        $percentage = min(100, ($progress / $bonus['wagering_required']) * 100);
                                                        $barColor = $percentage >= 100 ? '#4caf50' : ($percentage >= 50 ? '#ff9800' : '#e94560');
                                                    ?>
                                                    <div class="progress mt-2 mb-2" style="height: 22px; background: #0f3460; border-radius: 12px; overflow: hidden;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?= round($percentage, 1) ?>%; background: <?= $barColor ?>; font-weight: 700; font-size: 0.75rem; transition: width 0.5s ease;"
                                                             aria-valuenow="<?= round($percentage, 1) ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?= round($percentage, 1) ?>%
                                                        </div>
                                                    </div>
                                                    <p class="card-text mb-1" style="font-size: 0.82rem; color: #aaa;">
                                                        <i class="fas fa-info-circle"></i> 
                                                        <?php
                                                            $remaining = max(0, $bonus['wagering_required'] - $progress);
                                                            if ($remaining > 0) {
                                                                $remainingFmt = number_format($remaining, 0, ',', ' ');
                                                                echo '<span class="js-remaining-wager" data-remaining="' . htmlspecialchars($remainingFmt, ENT_QUOTES, 'UTF-8') . '">Még ' . $remainingFmt . ' FT bónusz tétet kell megtenned.</span>';
                                                            } else {
                                                                echo '<span class="text-success" data-i18n="userProfile.myBonuses.wageringDone">Forgatási követelmény teljesítve! A bónusz átkerül a rendes egyenlegbe.</span>';
                                                            }
                                                        ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php endif; /* end if not LOSS trigger */ ?>
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
                                                        echo '<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> <span data-i18n="userProfile.myBonuses.pendingConditional">Várakozik (Feltételre)</span></span>';
                                                    } elseif ($bonus['status'] === 'ACTIVE' && $is_valid && strtoupper($bonus['bonus_trigger'] ?? '') === 'LOSS') {
                                                        echo '<span class="badge" style="background:#7c3aed;"><i class="fas fa-shield-alt"></i> <span data-i18n="userProfile.myBonuses.cashbackActive">Cashback aktív</span></span>';
                                                    } elseif ($bonus['status'] === 'ACTIVE' && $is_valid) {
                                                        echo '<span class="badge bg-success"><i class="fas fa-check-circle"></i> <span data-i18n="userProfile.myBonuses.active">Aktív</span></span>';
                                                    } elseif ($bonus['status'] === 'COMPLETED') {
                                                        echo '<span class="badge bg-primary"><i class="fas fa-trophy"></i> <span data-i18n="userProfile.myBonuses.completed">Teljesítve</span></span>';
                                                    } elseif ($bonus['used']) {
                                                        echo '<span class="badge bg-info text-dark"><i class="fas fa-check"></i> <span data-i18n="userProfile.myBonuses.used">Felhasznált</span></span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> <span data-i18n="userProfile.myBonuses.expired">Lejárt</span></span>';
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
                                                        <span class="js-bonus-name" data-bonus-name="<?= htmlspecialchars((string)($bonus['bonus_name'] ?? 'Ismeretlen Bónusz'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($bonus['bonus_name'] ?? 'Ismeretlen Bónusz'); ?></span>
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
                                                            echo '<span class="badge bg-primary"><i class="fas fa-trophy"></i> <span data-i18n="userProfile.myBonuses.completed">Teljesítve</span></span>';
                                                        } elseif ($bonus['used']) {
                                                            echo '<span class="badge bg-info text-dark"><i class="fas fa-check"></i> <span data-i18n="userProfile.myBonuses.used">Felhasznált</span></span>';
                                                        } elseif (!$is_valid) {
                                                            echo '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> <span data-i18n="userProfile.myBonuses.expired">Lejárt</span></span>';
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
            const localizeBonusTitle = (raw, lang) => {
                const src = String(raw || '').trim();
                if (lang !== 'en') return src;
                if (src === 'Vesztes fogadás cashback (30% Free Bet)') {
                    return (window.i18n ? window.i18n('userProfile.myBonuses.lossBetCashbackName', 'Losing Bet Cashback (30% Free Bet)') : 'Losing Bet Cashback (30% Free Bet)');
                }
                const dartsPattern = /^DARTS\s+B[ÓO]NUSZ\s*\(([^)]+)\)$/i;
                const match = src.match(dartsPattern);
                if (!match) return src;
                const details = match[1]
                    .replace(/fogadás/gi, 'bet')
                    .replace(/bónusz/gi, 'bonus')
                    .replace(/Ft/gi, 'FT');
                return `DARTS BONUS (${details})`;
            };

            const localizeBonusDescription = (rawTitle, rawDescription, lang) => {
                const src = String(rawDescription || '').trim();
                if (lang !== 'en') return src;

                const normalizedTitle = String(rawTitle || '').trim();
                const isDartsBonus = /^DARTS\s+B[ÓO]NUSZ\s*\(([^)]+)\)$/i.test(normalizedTitle);
                if (isDartsBonus && src.includes('Darts rajongóknak szóló exkluzív bónusz')) {
                    return 'Exclusive bonus for darts fans! How to get it? 1) Place a bet worth at least 10,000 FT only on darts matches. 2) Your bet must include at least 2 events (2-leg combo) with a minimum total odds of 2.00. 3) After your bet is settled and evaluated, you receive 5,000 FT bonus money to your bonus balance. 4) The received 5,000 FT bonus must be wagered 2x (10,000 FT wager volume) before it becomes withdrawable. 5) Maximum withdrawable amount from this bonus is 5x (25,000 FT). Important: after activation, you have 48 hours to use this bonus.';
                }

                return src;
            };

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

            const currentLang = (typeof window.i18nLang === 'function') ? window.i18nLang() : 'hu';
            const finalTitle = localizeBonusTitle(title, currentLang);
            let finalDescription = description;
            if (bonusTrigger === 'LOSS' && currentLang === 'en') {
                const safeMinDeposit = (minDeposit || '0').toString().trim();
                const safeMinOdds = (minOdds || '0').toString().trim().replace(',', '.');
                const safeMatchPercent = (matchPercent || '0').toString().trim();
                finalDescription = `If a bet of at least ${safeMinDeposit} FT loses (min. odds: ${safeMinOdds}), you get back ${safeMatchPercent}% as Free Bet. It activates automatically once per day when the losing slip is settled. You can use the received Free Bet on any bet.`;
            } else {
                finalDescription = localizeBonusDescription(title, description, currentLang);
            }

            document.getElementById('bonusDescTitle').textContent = finalTitle;
            document.getElementById('bonusDescText').textContent = finalDescription;

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

    (function localizeRenderedBonusNamesAndHints() {
        const currentLang = (typeof window.i18nLang === 'function') ? window.i18nLang() : 'hu';
        if (currentLang !== 'en') return;

        const localizeBonusTitle = (raw) => {
            const src = String(raw || '').trim();
            if (src === 'Vesztes fogadás cashback (30% Free Bet)') {
                return (window.i18n ? window.i18n('userProfile.myBonuses.lossBetCashbackName', 'Losing Bet Cashback (30% Free Bet)') : 'Losing Bet Cashback (30% Free Bet)');
            }
            const dartsPattern = /^DARTS\s+B[ÓO]NUSZ\s*\(([^)]+)\)$/i;
            const match = src.match(dartsPattern);
            if (!match) return src;
            const details = match[1]
                .replace(/fogadás/gi, 'bet')
                .replace(/bónusz/gi, 'bonus')
                .replace(/Ft/gi, 'FT');
            return `DARTS BONUS (${details})`;
        };

        document.querySelectorAll('.js-bonus-name').forEach((el) => {
            const raw = el.getAttribute('data-bonus-name') || el.textContent || '';
            el.textContent = localizeBonusTitle(raw);
        });

        document.querySelectorAll('.js-remaining-wager').forEach((el) => {
            const remaining = el.getAttribute('data-remaining') || '0';
            el.textContent = `You still need to place ${remaining} FT in bonus stakes.`;
        });
    })();
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
