<button class="loginbtn" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
<button class="registrationbtn" data-bs-toggle="modal" data-bs-target="#registerModal">Regisztráció</button>
<div id="userMenu" class="dropdown" style="display:none;">
    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span id="userMenuUsername">-</span>
    </button>

    <ul class="dropdown-menu dropdown-menu-end">
        <li class="dropdown-header">
            <div><strong id="userFullName">-</strong></div>
            <div style="font-size: 12px;" id="userEmail">-</div>
        </li>
        <li>
            <hr class="dropdown-divider">
        </li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/personal_data.php"><i class="fas fa-user"></i> Személyes Adatok</a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/change_password.php"><i class="fas fa-key"></i> Jelszó Módosítás</a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/deposit.php"><i class="fas fa-plus-circle"></i> Befizetés</a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/withdrawal.php"><i class="fas fa-minus-circle"></i> Kifizetés</a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/transaction_history.php"><i class="fas fa-history"></i> Tranzakciótörténet</a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/my_bonuses.php"><i class="fas fa-gift"></i> Bónuszaim</a></li>
        <li><a class="dropdown-item" href="../../frontend/UserProfile/activity_log.php"><i class="fas fa-list"></i> Napló</a></li>
        <li>
            <hr class="dropdown-divider">
        </li>
        <li><button class="dropdown-item" id="logoutBtn" type="button"><i class="fas fa-sign-out-alt"></i> Kijelentkezés</button></li>
    </ul>
</div>
</div>