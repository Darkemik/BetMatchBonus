<button class="loginbtn" data-i18n="auth.login" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
<button class="registrationbtn" data-i18n="auth.register" data-bs-toggle="modal" data-bs-target="#registerModal">Regisztráció</button>
<div id="userMenu" class="dropdown" style="display:none;">
    <div class="session-login-badge" title="Hátralévő idő (max. 1 óra)">
        <span class="session-login-icon" aria-hidden="true">⏳</span>
        <span id="sessionLoginDurationDisplay">01:00:00</span>
    </div>
    <div class="session-bet-badge" title="Pénztárca egyenleg">
        <span class="session-bet-icon" aria-hidden="true">💰</span>
        <span id="sessionBetDisplay">0 FT</span>
    </div>
    <div class="session-bet-badge" id="bonusBalanceBadge" title="Bónusz egyenleg" style="display:none;background:linear-gradient(135deg,#7c3aed22,#a78bfa33);border-color:#7c3aed44;">
        <span class="session-bet-icon" aria-hidden="true">🎁</span>
        <span id="bonusBalanceDisplay">0 FT</span>
    </div>
    <a href="../../frontend/UserProfile/notifications.php" class="notif-bell-link" id="notifBellLink" title="Értesítések" style="position:relative;color:#ccc;font-size:1.2rem;text-decoration:none;display:none;margin-right:8px;">
        <i class="fas fa-bell"></i>
        <span id="notifBellBadge" style="display:none;position:absolute;top:-5px;right:-8px;background:#dc3545;color:#fff;border-radius:50%;font-size:0.6rem;min-width:16px;height:16px;align-items:center;justify-content:center;font-weight:700;padding:0 4px;"></span>
    </a>
    <button class="profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <div class="profile-avatar" id="profileAvatar">
            <i class="fas fa-user"></i>
        </div>
        <span class="profile-username" id="userMenuUsername">-</span>
    </button>

    <ul class="dropdown-menu dropdown-menu-end profile-dropdown" style="overflow:visible!important;max-height:none!important;z-index:99999!important;">
        <li class="dropdown-header profile-dropdown-header">
            <div class="profile-dropdown-avatar" id="profileDropdownAvatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-dropdown-info">
                <strong id="userFullName">-</strong>
                <span id="userEmail">-</span>
            </div>
        </li>
        <li>
            <hr class="dropdown-divider">
        </li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/personal_data.php"><i class="fas fa-user"></i> <span data-i18n="auth.personalData">Személyes Adatok</span></a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/change_password.php"><i class="fas fa-key"></i> <span data-i18n="auth.changePassword">Jelszó Módosítás</span></a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/deposit.php"><i class="fas fa-plus-circle"></i> <span data-i18n="auth.deposit">Befizetés</span></a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/withdrawal.php"><i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span></a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/transaction_history.php"><i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span></a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/my_bonuses.php"><i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span></a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/activity_log.php"><i class="fas fa-list"></i> <span data-i18n="auth.activityLog">Napló</span></a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/notifications.php" id="notifDropdownItem"><i class="fas fa-bell"></i> <span>Értesítések</span> <span class="notif-header-badge" id="notifDropdownBadge" style="display:none;background:#dc3545;color:#fff;border-radius:50%;font-size:0.65rem;min-width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-weight:700;padding:0 5px;margin-left:6px;"></span></a></li>
        <li>
            <hr class="dropdown-divider">
        </li>
        <li><button class="dropdown-item logout-item" id="logoutBtn" type="button" style="background:#dc3545!important;color:#fff!important;font-weight:700!important;border-radius:6px!important;margin:6px 10px!important;padding:10px 14px!important;display:block!important;width:calc(100% - 20px)!important;"><i class="fas fa-sign-out-alt" style="color:#fff!important;"></i> <span data-i18n="auth.logout">Kijelentkezés</span></button></li>
    </ul>
</div>
</div>