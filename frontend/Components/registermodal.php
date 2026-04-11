<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <a href="../../frontend/MainMenu/MainMenu.php">
                        <img src="../../img/logo.png" alt="logo">
                    </a>
                    <span data-i18n="registerModal.title">Regisztráció</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                
                <form id="registerModalForm">
                    <label class="form-label" data-i18n="registerModal.username">Felhasználónév</label>
                    <input type="text" name="username" class="form-control mb-3" placeholder="Felhasználónév" data-i18n-placeholder="registerModal.usernamePlaceholder" required>

                    <label class="form-label" data-i18n="registerModal.email">Email</label>
                    <input type="email" id="modal-email" name="email" class="form-control mb-3" placeholder="Email" data-i18n-placeholder="registerModal.emailPlaceholder" required>

                    <label class="form-label" data-i18n="registerModal.emailAgain">Email újra</label>
                    <input type="email" id="modal-email2" class="form-control mb-3" placeholder="Email újra" data-i18n-placeholder="registerModal.emailAgainPlaceholder" required>

                    <label class="form-label" data-i18n="registerModal.phone">Telefonszám</label>
                    <input type="tel" name="phone" id="modal-phone" class="form-control mb-3" placeholder="Pl.:06308469165" data-i18n-placeholder="registerModal.phonePlaceholder" inputmode="numeric" pattern="[0-9]+" minlength="11" required>

                    <label class="form-label" data-i18n="registerModal.password">Jelszó</label>
                    <div class="input-group mb-1">
                        <input type="password" id="modal-password" name="password" class="form-control" placeholder="Jelszó" data-i18n-placeholder="registerModal.passwordPlaceholder" required>
                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="modal-password" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="eye-icon" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="eye-slash-icon" viewBox="0 0 16 16">
                                <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                                <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                                <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>
                            </svg>
                        </button>
                    </div>

                    <div class="capslock-warning" id="capslock-register" style="display:none;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <span data-i18n="common.capsLockOn">A Caps Lock be van kapcsolva!</span>
                    </div>

                    <label class="form-label" data-i18n="registerModal.passwordAgain">Jelszó újra</label>
                    <div class="input-group mb-1">
                        <input type="password" id="modal-password2" class="form-control" placeholder="Jelszó újra" data-i18n-placeholder="registerModal.passwordAgainPlaceholder" required>
                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="modal-password2" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="eye-icon" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="eye-slash-icon" viewBox="0 0 16 16">
                                <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                                <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                                <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="capslock-warning" id="capslock-register2" style="display:none;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <span data-i18n="common.capsLockOn">A Caps Lock be van kapcsolva!</span>
                    </div>

                    <p id="registerModalResult"></p>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modal-terms_rules" name="terms_rules" value="1" required>
                            <label class="form-check-label" for="modal-terms_rules">
                                <span data-i18n="registerModal.acceptRules">Elolvastam és elfogadom a</span>
                                <a href="../../frontend/Help/reszveteli-szabalyzat.php" target="_blank" class="modal-link" data-i18n="registerModal.participationRules">Részvételi szabályzatot</a>
                            </label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modal-terms_privacy" name="terms_privacy" value="1" required>
                            <label class="form-check-label" for="modal-terms_privacy">
                                <span data-i18n="registerModal.acceptPrivacy">Elolvastam és elfogadom az</span>
                                <a href="../../frontend/Help/adatkezelesi_tajekoztatok.php" target="_blank" class="modal-link" data-i18n="registerModal.privacyPolicy">Adatkezelési tájékoztatót</a>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 mb-2" data-i18n="registerModal.continueBtn">Folytatás</button>
                </form>
            </div>

            <div class="modal-footer d-flex flex-column">
                <p class="m-0">
                    <span data-i18n="registerModal.hasAccount">Már van fiókod?</span>
                    <a href="#" class="modal-link" id="switchToLogin" data-i18n="registerModal.loginLink">Jelentkezz be!</a>
                </p>
            </div>

        </div>
    </div>
</div>