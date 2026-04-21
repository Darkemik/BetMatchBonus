<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Olvasatlan értesítések száma a badge-hez
$unreadStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM Notifications WHERE user_id = ? AND is_read = 0");
$unreadStmt->bind_param("i", $user_id);
$unreadStmt->execute();
$unreadCount = (int)$unreadStmt->get_result()->fetch_assoc()['cnt'];
$unreadStmt->close();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="userProfile.notifications.pageTitle">Értesítések | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        .notif-card {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 10px;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
        }
        .notif-card:hover { background: #e9ecef; transform: translateX(3px); }
        .notif-card.unread { background: #eef4ff; border-left-color: #0d6efd; font-weight: 500; }
        .notif-card.unread::after {
            content: '';
            position: absolute;
            top: 14px; right: 14px;
            width: 10px; height: 10px;
            background: #0d6efd;
            border-radius: 50%;
        }
        .notif-card .notif-title { font-size: 0.95rem; color: #333; margin-bottom: 4px; }
        .notif-card .notif-message { font-size: 0.85rem; color: #555; margin-bottom: 6px; }
        .notif-card .notif-time { font-size: 0.75rem; color: #999; }
        .notif-card .notif-type-badge {
            font-size: 0.7rem; padding: 2px 8px; border-radius: 10px;
            display: inline-block; margin-left: 8px;
        }
        .notif-type-ANNOUNCEMENT { background: #d1ecf1; color: #0c5460; }
        .notif-type-SYSTEM { background: #fff3cd; color: #856404; }
        .notif-type-BONUS { background: #d4edda; color: #155724; }
        .notif-type-DEPOSIT { background: #cce5ff; color: #004085; }
        .notif-type-WITHDRAWAL { background: #f8d7da; color: #721c24; }

        .notif-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
        }
        .notif-filter-btns .btn { font-size: 0.8rem; padding: 4px 12px; }
        .notif-empty {
            text-align: center; padding: 40px 20px; color: #999;
        }
        .notif-empty i { font-size: 3rem; margin-bottom: 12px; display: block; color: #ccc; }
        .notif-badge-sidebar {
            background: #dc3545; color: #fff; border-radius: 50%;
            font-size: 0.65rem; min-width: 18px; height: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-left: auto; font-weight: 700; padding: 0 5px;
        }

        .notif-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 8px;
            height: 70px;
            margin-bottom: 10px;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
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
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span></a>
                    <a href="transaction_history.php" class="profile-nav-item"><i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span></a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span></a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> <span data-i18n="auth.activityLog">Napló</span></a>
                    <a href="notifications.php" class="profile-nav-item active"><i class="fas fa-bell"></i> <span data-i18n="auth.notifications">Értesítések</span><?php if ($unreadCount > 0): ?> <span class="notif-badge-sidebar"><?= $unreadCount ?></span><?php endif; ?></a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> <span data-i18n="auth.logout">Kijelentkezés</span></a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <div class="notif-header">
                        <h1 style="margin:0;"><i class="fas fa-bell"></i> <span data-i18n="userProfile.notifications.heading">Értesítések</span></h1>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="notif-filter-btns btn-group">
                                <button class="btn btn-outline-primary btn-sm active" data-filter="all" data-i18n="userProfile.notifications.filterAll">Összes</button>
                                <button class="btn btn-outline-primary btn-sm" data-filter="unread" data-i18n="userProfile.notifications.filterUnread">Olvasatlan</button>
                            </div>
                            <button class="btn btn-outline-success btn-sm" id="markAllBtn" title="Összes olvasottnak jelölése" data-i18n-title="userProfile.notifications.markAllReadTitle" style="display:none;">
                                <i class="fas fa-check-double"></i> <span data-i18n="userProfile.notifications.markAllRead">Mind olvasott</span>
                            </button>
                        </div>
                    </div>

                    <div id="notifLoader">
                        <div class="notif-skeleton"></div>
                        <div class="notif-skeleton"></div>
                        <div class="notif-skeleton"></div>
                    </div>

                    <div id="notifList" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../Components/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Main/language.js"></script>
    <script src="../../js/Main/layout.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <script>
    const NOTIF_API = '../../backend/ApiRequest/UserProfile/get_notifications.php';
    let allNotifications = [];
    let currentFilter = 'all';

    function t(key, fallback) {
        return (window.i18n ? window.i18n(key, fallback) : fallback);
    }

    function getCurrentLang() {
        return (typeof window.i18nLang === 'function') ? window.i18nLang() : 'hu';
    }

    function getTypeLabel(type) {
        const map = {
            ANNOUNCEMENT: t('userProfile.notifications.typeAnnouncement', 'Közlemény'),
            SYSTEM: t('userProfile.notifications.typeSystem', 'Rendszer'),
            BONUS: t('userProfile.notifications.typeBonus', 'Bónusz'),
            DEPOSIT: t('userProfile.notifications.typeDeposit', 'Befizetés'),
            WITHDRAWAL: t('userProfile.notifications.typeWithdrawal', 'Kifizetés')
        };
        return map[type] || type;
    }

    const TYPE_ICONS = {
        ANNOUNCEMENT: 'fa-bullhorn',
        SYSTEM: 'fa-cog',
        BONUS: 'fa-gift',
        DEPOSIT: 'fa-plus-circle',
        WITHDRAWAL: 'fa-minus-circle'
    };

    async function loadNotifications() {
        try {
            const res = await fetch(NOTIF_API);
            const data = await res.json();
            if (!data.success) throw new Error();
            allNotifications = data.notifications;

            // Az oldal megnyitásakor automatikusan olvasottra jelölünk mindent.
            if (allNotifications.some(n => !n.is_read)) {
                await markAllRead(true);
            }

            renderNotifications();
        } catch {
            document.getElementById('notifLoader').innerHTML =
                '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + t('userProfile.notifications.loadError', 'Hiba az értesítések betöltésekor.') + '</div>';
        }
    }

    function renderNotifications() {
        const container = document.getElementById('notifList');
        const loader = document.getElementById('notifLoader');
        loader.style.display = 'none';
        container.style.display = 'block';

        let filtered = allNotifications;
        if (currentFilter === 'unread') {
            filtered = allNotifications.filter(n => !n.is_read);
        }

        const unreadCount = allNotifications.filter(n => !n.is_read).length;
        document.getElementById('markAllBtn').style.display = unreadCount > 0 ? 'inline-flex' : 'none';

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="notif-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>${currentFilter === 'unread' ? t('userProfile.notifications.noUnread', 'Nincs olvasatlan értesítésed.') : t('userProfile.notifications.noNotifications', 'Még nincsenek értesítéseid.')}</p>
                </div>`;
            return;
        }

        container.innerHTML = filtered.map(n => {
            const typeLabel = getTypeLabel(n.type);
            const typeIcon = TYPE_ICONS[n.type] || 'fa-info-circle';
            const timeAgo = formatTimeAgo(n.created_at);
            return `
            <div class="notif-card ${n.is_read ? '' : 'unread'}" data-id="${n.id}" onclick="markRead(${n.id}, this)">
                <div class="notif-title">
                    <i class="fas ${typeIcon}"></i> ${escHtml(n.title)}
                    <span class="notif-type-badge notif-type-${n.type}">${typeLabel}</span>
                </div>
                <div class="notif-message">${escHtml(n.message)}</div>
                <div class="notif-time"><i class="far fa-clock"></i> ${timeAgo}</div>
            </div>`;
        }).join('');
    }

    async function markRead(id, el) {
        if (el && !el.classList.contains('unread')) return;
        try {
            await fetch(NOTIF_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', id })
            });
            const n = allNotifications.find(x => x.id === id);
            if (n) n.is_read = 1;
            renderNotifications();
            updateSidebarBadge();
        } catch {}
    }

    async function markAllRead(silent) {
        try {
            await fetch(NOTIF_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all' })
            });
            allNotifications.forEach(n => n.is_read = 1);
            renderNotifications();
            updateSidebarBadge();
            if (!silent) {
                // Kézi gombnyomásnál maradjon azonnali UI frissítés.
                document.dispatchEvent(new CustomEvent('auth:changed'));
            }
        } catch {}
    }

    function updateSidebarBadge() {
        const unread = allNotifications.filter(n => !n.is_read).length;
        const badge = document.querySelector('.notif-badge-sidebar');
        if (badge) {
            if (unread > 0) {
                badge.textContent = unread;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    function formatTimeAgo(dateStr) {
        const lang = getCurrentLang();
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return t('userProfile.notifications.timeNow', 'Most');
        if (diff < 3600) return Math.floor(diff / 60) + ' ' + t('userProfile.notifications.timeMinutesAgo', 'perce');
        if (diff < 86400) return Math.floor(diff / 3600) + ' ' + t('userProfile.notifications.timeHoursAgo', 'órája');
        if (diff < 604800) return Math.floor(diff / 86400) + ' ' + t('userProfile.notifications.timeDaysAgo', 'napja');

        return date.toLocaleDateString(lang === 'en' ? 'en-US' : 'hu-HU', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // Filter buttons
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            renderNotifications();
        });
    });

    document.getElementById('markAllBtn').addEventListener('click', markAllRead);

    loadNotifications();
    window.addEventListener('languageChanged', function () {
        renderNotifications();
    });
    </script>
</body>
</html>

