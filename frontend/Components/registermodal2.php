<div class="modal fade" id="registerModal2" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <a href="../../frontend/MainMenu/MainMenu.php">
                        <img src="../../img/logo.png" alt="logo">
                    </a>
                    <span data-i18n="registerModal2.title">Regisztráció - 2. lépés</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
            <form id="registerModal2Form">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label" data-i18n="registerModal2.preName">Előnév (ha van)</label>
                            <input type="text" name="Pre_name" class="form-control mb-3" placeholder="Előnév" data-i18n-placeholder="registerModal2.preNamePlaceholder">

                            <label class="form-label" data-i18n="registerModal2.familyName">Vezetéknév</label>
                            <input type="text" name="family_name" class="form-control mb-3" placeholder="Vezetéknév" data-i18n-placeholder="registerModal2.familyNamePlaceholder" required>

                            <label class="form-label" data-i18n="registerModal2.sureName">Keresztnév</label>
                            <input type="text" name="Sure_name" class="form-control mb-3" placeholder="Keresztnév" data-i18n-placeholder="registerModal2.sureNamePlaceholder" required>

                            <label class="form-label" data-i18n="registerModal2.motherName">Anyja leánykori neve</label>
                            <input type="text" name="mother_full_name" class="form-control mb-3" placeholder="Anyja leánykori neve" data-i18n-placeholder="registerModal2.motherNamePlaceholder" required>

                            <label class="form-label" data-i18n="registerModal2.birthPlace">Születési hely</label>
                            <div class="city-autocomplete-wrapper">
                                <input type="text" name="birthplace" id="modal2-birthplace" class="form-control mb-3" placeholder="Kezdj el gépelni..." data-i18n-placeholder="registerModal2.birthPlacePlaceholder" autocomplete="off" required>
                                <ul class="city-autocomplete-list" id="city-autocomplete-list"></ul>
                            </div>

                            <label class="form-label" data-i18n="registerModal2.birthDate">Születési dátum</label>
                            <div class="birthdate-shell mb-1">
                                <div class="birthdate-row">
                                    <select id="modal2-birth-year" class="form-select birthdate-select" required>
                                        <option value="">Év</option>
                                    </select>
                                    <select id="modal2-birth-month" class="form-select birthdate-select" required>
                                        <option value="">Hónap</option>
                                    </select>
                                    <select id="modal2-birth-day" class="form-select birthdate-select" required>
                                        <option value="">Nap</option>
                                    </select>
                                </div>
                                <input type="hidden" id="modal2-date-input" name="birthdate" required>
                            </div>
                            <input type="hidden" name="calculated_age" id="modal2-calculated_age">
                            <input type="hidden" name="birthplace_city_id" id="modal2-birthplace_city_id" value="1">
                            <p class="small birthdate-help mb-1">Válaszd ki az évet, hónapot és napot.</p>
                            <p id="modal2-age-result" class="small birthdate-age-result mb-3"></p>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" data-i18n="registerModal2.idFront">Személyi igazolvány (eleje)</label>
                            <input type="file" id="modal2-id_image_first" class="form-control mb-2" accept="image/*" required>
                            <img id="modal2-id_preview_first" class="img-thumbnail mb-3 modal-img-preview">

                            <label class="form-label" data-i18n="registerModal2.idBack">Személyi igazolvány (hátulja)</label>
                            <input type="file" id="modal2-id_image_second" class="form-control mb-2" accept="image/*" required>
                            <img id="modal2-id_preview_second" class="img-thumbnail mb-3 modal-img-preview">

                            <label class="form-label" data-i18n="registerModal2.addressCard">Lakcímkártya</label>
                            <input type="file" id="modal2-address_image" class="form-control mb-2" accept="image/*" required>
                            <img id="modal2-address_preview" class="img-thumbnail mb-3 modal-img-preview">
                        </div>
                    </div>

                    <p id="registerModal2Result"></p>

                    <button type="submit" class="btn btn-success w-100 mb-2" data-i18n="registerModal2.finishBtn">Regisztráció befejezése</button>
                </form>
            </div>

            <div class="modal-footer d-flex flex-column">
                <p class="m-0">
                    <a href="#" class="modal-link" id="backToRegister1" data-i18n="registerModal2.backLink">← Vissza az előző lépéshez</a>
                </p>
            </div>

        </div>
    </div>
</div>