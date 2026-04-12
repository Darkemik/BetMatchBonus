<header class="header">
    <div class="header-top-row">
        <button class="navbar-toggler navbar-dark" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false" aria-label="Menü">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="logo-box">
            <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])); ?>/MainMenu/MainMenu.php">
                <img class="kep" src="<?php echo dirname(dirname(dirname($_SERVER['PHP_SELF']))); ?>/img/logo.png" alt="logo">
            </a>
            <div class="logo">
                <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])); ?>/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a>
            </div>
        </div>

        <div class="right_side">
          <div class="lang-switcher">
                    <button class="translateBtn" id="btn-hu">
                        <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                            <rect width="9" height="2" y="0" fill="#c8102e" />
                            <rect width="9" height="2" y="2" fill="#ffffff" />
                            <rect width="9" height="2" y="4" fill="#436f4d" />
                        </svg>
                    </button>
                    <div class="lang-dropdown" id="lang-dropdown">
                        <button class="lang-btn" id="btn-hu-switch" title="Magyar">
                            <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                                <rect width="9" height="2" y="0" fill="#c8102e" />
                                <rect width="9" height="2" y="2" fill="#ffffff" />
                                <rect width="9" height="2" y="4" fill="#436f4d" />
                            </svg>
                        </button>
                        <button class="lang-btn" id="btn-en" title="English">
                            <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                                <rect width="9" height="6" fill="#ffffff" />
                                <rect x="4" width="1" height="6" fill="#c8102e" />
                                <rect y="2.5" width="9" height="1" fill="#c8102e" />
                            </svg>
                        </button>
                    </div>
                </div>
            <?php include 'login.php'; ?>
        </div>
    </div>

    <?php
    $is_in_userprofile = strpos($_SERVER['PHP_SELF'], 'UserProfile') !== false;
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    ?>

    <nav class="nav collapse navbar-collapse" id="mainNavbar">
        <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])); ?>/MainMenu/MainMenu.php" <?php if (!$is_in_userprofile && $current_page === 'MainMenu') echo 'class="active"'; ?>><span data-i18n="nav.home">Főoldal</span></a>
        <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])); ?>/Live/live.php" <?php if (!$is_in_userprofile && $current_page === 'live') echo 'class="active"'; ?>><span data-i18n="nav.live">Élő</span></a>
        <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])); ?>/Esport/esport.php" <?php if (!$is_in_userprofile && $current_page === 'esport') echo 'class="active"'; ?>><span data-i18n="nav.esport">eSport</span></a>
        <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])); ?>/Bonus/bonus.php" <?php if (!$is_in_userprofile && $current_page === 'bonus') echo 'class="active"'; ?>><span data-i18n="nav.bonuses">Bónuszok</span></a>
        <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])); ?>/Help/help.php" <?php if (!$is_in_userprofile && $current_page === 'help') echo 'class="active"'; ?>><span data-i18n="nav.help">Segítség</span></a>
    </nav>
</header>

<script src="<?php echo dirname(dirname(dirname($_SERVER['PHP_SELF']))); ?>/js/Main/language.js"></script>
<script src="<?php echo dirname(dirname(dirname($_SERVER['PHP_SELF']))); ?>/js/Main/layout.js"></script>
<script src="<?php echo dirname(dirname(dirname($_SERVER['PHP_SELF']))); ?>/js/Main/auth_ui.js"></script>
