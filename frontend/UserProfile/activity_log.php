<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$query = "SELECT id, activity_type, description, created_at FROM ActivityLog WHERE user_id = ? ORDER BY created_at DESC LIMIT 200";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Magyar típus nevek
$typeLabels = [
    'login' => 'Bejelentkezés',
    'logout' => 'Kijelentkezés',
    'bet' => 'Fogadás',
    'deposit' => 'Befizetés',
    'withdrawal' => 'Kifizetés',
    'bonus' => 'Bónusz',
    'profile_update' => 'Profil frissítés',
    'password_change' => 'Jelszó módosítás'
];

$typeIcons = [
    'login' => 'fa-sign-in-alt',
    'logout' => 'fa-sign-out-alt',
    'bet' => 'fa-dice',
    'deposit' => 'fa-plus-circle',
    'withdrawal' => 'fa-minus-circle',
    'bonus' => 'fa-gift',
    'profile_update' => 'fa-user-edit',
    'password_change' => 'fa-key'
];

$typeColors = [
    'login' => '#52b788',
    'logout' => '#e94560',
    'bet' => '#5b9bd5',
    'deposit' => '#52b788',
    'withdrawal' => '#e94560',
    'bonus' => '#f5c518',
    'profile_update' => '#9aa6b2',
    'password_change' => '#c77dff'
];

// Csoportosítás dátum szerint
$grouped = [];
foreach ($activities as $a) {
    $day = date('Y-m-d', strtotime($a['created_at']));
    $grouped[$day][] = $a;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Napló | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        .log-filter-bar {
            display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .log-filter-btn {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: #aaa; padding: 5px 14px; border-radius: 20px; font-size: 0.8rem;
            cursor: pointer; transition: all 0.15s;
        }
        .log-filter-btn:hover { border-color: #e94560; color: #e94560; }
        .log-filter-btn.active { background: #e94560; color: #fff; border-color: #e94560; }

        .log-day-header {
            color: #9aa6b2; font-size: 0.78rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 1px; padding: 12px 0 6px; border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 8px; margin-top: 16px;
        }
        .log-day-header:first-child { margin-top: 0; }

        .log-entry {
            display: flex; align-items: flex-start; gap: 14px; padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.03); transition: background 0.15s;
        }
        .log-entry:hover { background: rgba(255,255,255,0.02); border-radius: 8px; padding-left: 8px; }
        .log-entry:last-child { border-bottom: none; }

        .log-icon {
            width: 38px; height: 38px; border-radius: 10px; display: flex;
            align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem;
        }
        .log-body { flex: 1; min-width: 0; }
        .log-type { font-weight: 700; font-size: 0.85rem; color: #eee; margin-bottom: 2px; }
        .log-desc { font-size: 0.82rem; color: #9aa6b2; line-height: 1.4; word-break: break-word; }
        .log-time { font-size: 0.72rem; color: #666; margin-top: 3px; }

        .log-empty {
            text-align: center; padding: 50px 20px; color: #666;
        }
        .log-empty i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: #444; }

        .log-count-badge {
            background: rgba(255,255,255,0.06); color: #9aa6b2; font-size: 0.72rem;
            padding: 2px 8px; border-radius: 10px; margin-left: 6px;
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
                    <a href="personal_data.php" class="profile-nav-item"><i class="fas fa-user"></i> <span>Személyes Adatok</span></a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> <span>Jelszó Módosítás</span></a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> <span>Befizetés</span></a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> <span>Kifizetés</span></a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> <span>Tranzakciótörténet</span></a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> <span>Bónuszaim</span></a>
                    <a href="activity_log.php" class="profile-nav-item active"><i class="fas fa-list"></i> <span>Napló</span></a>
                    <a href="notifications.php" class="profile-nav-item"><i class="fas fa-bell"></i> <span data-i18n="auth.notifications">Értesítések</span></a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> <span>Kijelentkezés</span></a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-list"></i> Tevékenységnapló <span class="log-count-badge"><?= count($activities) ?> bejegyzés</span></h1>

                    <?php if (empty($activities)): ?>
                        <div class="log-empty">
                            <i class="fas fa-clipboard-list"></i>
                            <p>Még nincs tevékenységi bejegyzés.</p>
                        </div>
                    <?php else: ?>
                        <!-- Szűrő gombok -->
                        <div class="log-filter-bar">
                            <button class="log-filter-btn active" data-filter="all">Összes</button>
                            <?php
                            $usedTypes = array_unique(array_column($activities, 'activity_type'));
                            foreach ($usedTypes as $t):
                                $label = $typeLabels[$t] ?? ucfirst($t);
                            ?>
                                <button class="log-filter-btn" data-filter="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($label) ?></button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Napló bejegyzések -->
                        <div id="logEntries">
                        <?php foreach ($grouped as $day => $dayActivities): ?>
                            <?php
                            $today = date('Y-m-d');
                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                            if ($day === $today) $dayLabel = 'Ma';
                            elseif ($day === $yesterday) $dayLabel = 'Tegnap';
                            else {
                                $dt = new DateTime($day);
                                $dayLabel = $dt->format('Y. m. d.') . ' — ' . ['Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat','Vasárnap'][(int)$dt->format('N') - 1];
                            }
                            ?>
                            <div class="log-day-header"><?= $dayLabel ?></div>
                            <?php foreach ($dayActivities as $a):
                                $type = $a['activity_type'] ?? 'unknown';
                                $icon = $typeIcons[$type] ?? 'fa-circle';
                                $color = $typeColors[$type] ?? '#666';
                                $label = $typeLabels[$type] ?? ucfirst($type);
                                $time = date('H:i', strtotime($a['created_at']));
                            ?>
                            <div class="log-entry" data-type="<?= htmlspecialchars($type) ?>">
                                <div class="log-icon" style="background:<?= $color ?>20; color:<?= $color ?>;">
                                    <i class="fas <?= $icon ?>"></i>
                                </div>
                                <div class="log-body">
                                    <div class="log-type"><?= htmlspecialchars($label) ?></div>
                                    <div class="log-desc"><?= htmlspecialchars($a['description'] ?? '') ?></div>
                                    <div class="log-time"><i class="far fa-clock"></i> <?= $time ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <script>
    document.querySelectorAll('.log-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.log-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            document.querySelectorAll('.log-entry').forEach(entry => {
                entry.style.display = (filter === 'all' || entry.dataset.type === filter) ? '' : 'none';
            });
            document.querySelectorAll('.log-day-header').forEach(header => {
                let next = header.nextElementSibling;
                let hasVisible = false;
                while (next && !next.classList.contains('log-day-header')) {
                    if (next.classList.contains('log-entry') && next.style.display !== 'none') hasVisible = true;
                    next = next.nextElementSibling;
                }
                header.style.display = hasVisible ? '' : 'none';
            });
        });
    });
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>

