<div class="modal fade" id="loginModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <a href="../../frontend/MainMenu/MainMenu.php">
            <img src="../../img/logo.png" alt="logo">
          </a>
          Bejelentkezés
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="loginModalForm">
          <label class="form-label">Felhasználónév vagy e-mail cím</label>
          <input type="text" name="login" id="login-login" class="form-control mb-3" required>

          <label class="form-label">Jelszó</label>
          <div class="input-group mb-2">
            <input type="password" name="password" id="login-password" class="form-control" required>
            <button type="button"
                    class="btn btn-outline-secondary toggle-password"
                    data-target="login-password"
                    tabindex="-1">
              👁
            </button>
          </div>

          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe" value="1">
            <label class="form-check-label" for="rememberMe">
              Emlékezz rám
            </label>
          </div>

          <a href="#" class="small modal-link">Elfelejtettem a jelszavam</a>

          <p id="loginModalResult" class="mt-2 mb-0"></p>

          <div class="modal-footer d-flex flex-column px-0">
            <button type="submit" class="btn btn-success w-100 mb-2">Bejelentkezés</button>
            <p class="m-0">
              Még nincs fiókod?
              <a href="#" class="modal-link" id="switchToRegister">Regisztrálj!</a>
            </p>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>