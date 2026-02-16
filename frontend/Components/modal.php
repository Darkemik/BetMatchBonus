<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 10px;">

            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <a href="../../frontend/MainMenu/MainMenu.php">
                        <img src="../../img/logo.png" alt="logo" style="height: 28px; cursor: pointer;">
                    </a>
                    Bejelentkezés
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <label class="form-label">Felhasználónév vagy e-mail cím</label>
                <input type="text" class="form-control mb-3">

                <label class="form-label">Jelszó</label>
                <input type="password" class="form-control mb-2">

                <a href="#" class="small" style="color:#3498db;">Elfelejtettem a jelszavam</a>
            </div>

            <div class="modal-footer d-flex flex-column">
                <button class="btn btn-success w-100 mb-2">Bejelentkezés</button>
                <p class="m-0">
                    Még nincs fiókod?
                    <a href="../../frontend/Register/register.php" style="color:#3498db;">Regisztrálj!</a>
                </p>
            </div>

        </div>
    </div>
</div>