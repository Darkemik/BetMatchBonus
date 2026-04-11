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
        <li>
            <hr class="dropdown-divider">
        </li>
        <li><button class="dropdown-item logout-item" id="logoutBtn" type="button" style="background:#dc3545!important;color:#fff!important;font-weight:700!important;border-radius:6px!important;margin:6px 10px!important;padding:10px 14px!important;display:block!important;width:calc(100% - 20px)!important;"><i class="fas fa-sign-out-alt" style="color:#fff!important;"></i> <span data-i18n="auth.logout">Kijelentkezés</span></button></li>
    </ul>
</div>
</div>