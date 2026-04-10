<?php require_once __DIR__ . '/../../backend/config.php'; ?>
<script>window.RECAPTCHA_SITE_KEY = '<?= RECAPTCHA_SITE_KEY ?>';</script>
<script src="https://www.google.com/recaptcha/api.js?render=<?= RECAPTCHA_SITE_KEY ?>"></script>
<div class="modal fade" id="loginModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <a href="../../frontend/MainMenu/MainMenu.php">
            <img src="../../img/logo.png" alt="logo">
          </a>
          <span data-i18n="loginModal.title">Bejelentkezés</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="loginModalForm">
          <label class="form-label" data-i18n="loginModal.usernameOrEmail">Felhasználónév vagy e-mail cím</label>
          <input type="text" name="login" id="login-login" class="form-control mb-3" placeholder="Felhasználónév vagy e-mail" data-i18n-placeholder="loginModal.usernameOrEmailPlaceholder" required>

          <label class="form-label" data-i18n="loginModal.password">Jelszó</label>
          <div class="input-group mb-2">
            <input type="password" name="password" id="login-password" class="form-control" placeholder="Jelszó" data-i18n-placeholder="loginModal.passwordPlaceholder" required>
            <button type="button"
                    class="btn btn-outline-secondary toggle-password"
                    data-target="login-password"
                    tabindex="-1">
              👁
            </button>
          </div>

          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe" value="1">
            <label class="form-check-label" for="rememberMe" data-i18n="loginModal.rememberMe">
              Emlékezz rám
            </label>
          </div>

          <a href="#" class="small modal-link" id="switchToForgotPassword" data-i18n="loginModal.forgotPassword">Elfelejtettem a jelszavam</a>

          <p id="loginModalResult" class="mt-2 mb-0"></p>

          <div class="modal-footer d-flex flex-column px-0">
            <button type="submit" class="btn btn-success w-100 mb-2" data-i18n="loginModal.loginBtn">Bejelentkezés</button>
            <p class="m-0">
              <span data-i18n="loginModal.noAccount">Még nincs fiókod?</span>
              <a href="#" class="modal-link" id="switchToRegister" data-i18n="loginModal.registerLink">Regisztrálj!</a>
            </p>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>