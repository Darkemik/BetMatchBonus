<div class="modal fade" id="forgotPasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <a href="#" id="backToLoginFromForgot"
                    style="color: #ffa200; text-decoration: none; font-size: 24px; margin-right: 10px;">←</a>
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <a href="../../frontend/MainMenu/MainMenu.php">
                        <img src="../../img/logo.png" alt="logo">
                    </a>
                    <span data-i18n="forgotPassword.title">Elfelejtettem a jelszavam</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p style="color: #b0b8c8; font-size: 14px; margin-bottom: 20px;" data-i18n="forgotPassword.description">
                    Semmi probléma! Csak add meg az e-mail címedet a felhasználóneved-del vagy a születési dátumoddal, és kövesd az e-mailben kapott utasításokat!
                </p>

                <form id="forgotPasswordForm">
                    <label class="form-label" data-i18n="forgotPassword.email">E-mail cím</label>
                    <input type="email" name="email" id="forgot-email" class="form-control mb-3"
                        placeholder="E-mail cím" data-i18n-placeholder="forgotPassword.emailPlaceholder" required>

                    <label class="form-label" data-i18n="forgotPassword.username">Felhasználónév</label>
                    <input type="text" name="username" id="forgot-username" class="form-control mb-3"
                        placeholder="Felhasználónév" data-i18n-placeholder="forgotPassword.usernamePlaceholder" required>

                    <a href="#" class="small modal-link" id="switchToUsernameHelp"
                        style="display: block; margin-bottom: 15px;" data-i18n="forgotPassword.dontKnowUsername">Nem tudom a felhasználó nevem</a>

                    <p id="forgotPasswordResult" class="mt-2 mb-3"></p>

                    <div class="modal-footer d-flex flex-column px-0">
                        <button type="submit" class="btn btn-success w-100 mb-2" data-i18n="forgotPassword.submitBtn">Új jelszó beállítása</button>
                        <p class="m-0" style="margin-top: 10px !important;">
                            <span data-i18n="forgotPassword.noAccount">Még nincs fiókod?</span>
                            <a href="#" class="modal-link" id="switchToRegisterFromForgot" data-i18n="forgotPassword.registerLink">Regisztrálj!</a>
                        </p>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="forgotUsernameModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <a href="#" id="backToForgotPasswordFromUsername"
                    style="color: #ffa200; text-decoration: none; font-size: 24px; margin-right: 10px;">←</a>
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <a href="../../frontend/MainMenu/MainMenu.php">
                        <img src="../../img/logo.png" alt="logo">
                    </a>
                    <span data-i18n="forgotUsername.title">Felhasználónév helyreállítása</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p style="color: #b0b8c8; font-size: 14px; margin-bottom: 20px;" data-i18n="forgotUsername.description">
                    Semmi probléma! Csak add meg az e-mail címedet a születési dátumoddal, és el fogjuk küldeni a felhasználónevedet!
                </p>

                <form id="forgotUsernameForm">
                    <label class="form-label" data-i18n="forgotUsername.email">E-mail cím</label>
                    <input type="email" name="email" id="username-help-email" class="form-control mb-3"
                        placeholder="E-mail cím" data-i18n-placeholder="forgotUsername.emailPlaceholder" required>

                    <label class="form-label" data-i18n="forgotUsername.birthDate">Születési idő</label>
                    <input type="date" name="birthdate" id="username-help-birthdate" class="form-control mb-3"
                        placeholder="Dátum kiválasztása" required>

                    <p id="forgotUsernameResult" class="mt-2 mb-3"></p>

                    <div class="modal-footer d-flex flex-column px-0">
                        <button type="submit" class="btn btn-success w-100 mb-2" data-i18n="forgotUsername.submitBtn">Felhasználónév megküldése</button>
                        <p class="m-0" style="margin-top: 10px !important;">
                            <a href="#" class="small modal-link" id="switchBackToForgotPassword" data-i18n="forgotUsername.backLink">← Vissza a jelszó helyreállításához</a>
                        </p>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>