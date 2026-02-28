<div class="modal fade" id="registerModal2" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <a href="../../frontend/MainMenu/MainMenu.php">
                        <img src="../../img/logo.png" alt="logo">
                    </a>
                    Regisztráció - 2. lépés
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="registerModal2Form">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Előnév (ha van)</label>
                            <input type="text" name="Pre_name" class="form-control mb-3" placeholder="Előnév">

                            <label class="form-label">Vezetéknév</label>
                            <input type="text" name="family_name" class="form-control mb-3" placeholder="Vezetéknév" required>

                            <label class="form-label">Keresztnév</label>
                            <input type="text" name="Sure_name" class="form-control mb-3" placeholder="Keresztnév" required>

                            <label class="form-label">Anyja leánykori neve</label>
                            <input type="text" name="mother_full_name" class="form-control mb-3" placeholder="Anyja leánykori neve" required>

                            <label class="form-label">Születési hely</label>
                            <input type="text" name="birthplace" class="form-control mb-3" placeholder="Születési hely" required>

                            <label class="form-label">Születési dátum</label>
                            <input type="date" id="modal2-date-input" name="birthdate" class="form-control mb-1" required>
                            <input type="hidden" name="calculated_age" id="modal2-calculated_age">
                            <p id="modal2-age-result" class="small text-muted mb-3"></p>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Személyi igazolvány (eleje)</label>
                            <input type="file" id="modal2-id_image_first" class="form-control mb-2" accept="image/*" required>
                            <img id="modal2-id_preview_first" class="img-thumbnail mb-3 modal-img-preview">

                            <label class="form-label">Személyi igazolvány (hátulja)</label>
                            <input type="file" id="modal2-id_image_second" class="form-control mb-2" accept="image/*" required>
                            <img id="modal2-id_preview_second" class="img-thumbnail mb-3 modal-img-preview">

                            <label class="form-label">Lakcímkártya</label>
                            <input type="file" id="modal2-address_image" class="form-control mb-2" accept="image/*" required>
                            <img id="modal2-address_preview" class="img-thumbnail mb-3 modal-img-preview">
                        </div>
                    </div>

                    <p id="registerModal2Result"></p>

                    <button type="submit" class="btn btn-success w-100 mb-2">Regisztráció befejezése</button>
                </form>
            </div>

            <div class="modal-footer d-flex flex-column">
                <p class="m-0">
                    <a href="#" class="modal-link" id="backToRegister1">← Vissza az előző lépéshez</a>
                </p>
            </div>

        </div>
    </div>
</div>
