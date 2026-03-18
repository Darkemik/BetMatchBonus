<button class="loginbtn" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
<button class="registrationbtn" data-bs-toggle="modal" data-bs-target="#registerModal">Regisztráció</button>
<div id="userMenu" class="dropdown" style="display:none;">
    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span id="userMenuUsername">Fiókom</span>
    </button>

    <ul class="dropdown-menu dropdown-menu-end">
        <li class="dropdown-header">
            <div><strong id="userFullName">-</strong></div>
            <div style="font-size: 12px;" id="userEmail">-</div>
        </li>
        <li>
            <hr class="dropdown-divider">
        </li>
        <li><button class="dropdown-item" id="logoutBtn" type="button">Kijelentkezés</button></li>
    </ul>
</div>
</div>