document.addEventListener('DOMContentLoaded', function () {
    var dateInput = document.getElementById('modal2-date-input');
    var yearSelect = document.getElementById('modal2-birth-year');
    var monthSelect = document.getElementById('modal2-birth-month');
    var daySelect = document.getElementById('modal2-birth-day');
    var ageResult = document.getElementById('modal2-age-result');
    var calcAge = document.getElementById('modal2-calculated_age');
    var form = document.getElementById('registerModal2Form');
    var result = document.getElementById('registerModal2Result');
    var birthplaceInput = document.getElementById('modal2-birthplace');
    var dateShell = dateInput ? dateInput.closest('.birthdate-shell') : null;

    function pad2(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function setBirthdateVisualState(state) {
        if (!dateShell || !ageResult) return;
        dateShell.classList.remove('is-valid', 'is-invalid');
        ageResult.classList.remove('is-ok', 'is-error');

        if (state === 'valid') {
            dateShell.classList.add('is-valid');
            ageResult.classList.add('is-ok');
        } else if (state === 'invalid') {
            dateShell.classList.add('is-invalid');
            ageResult.classList.add('is-error');
        }
    }

    function populateYears() {
        if (!yearSelect) return;
        var now = new Date();
        var maxYear = now.getFullYear() - 18;
        var minYear = now.getFullYear() - 100;
        for (var y = maxYear; y >= minYear; y--) {
            var option = document.createElement('option');
            option.value = String(y);
            option.textContent = String(y);
            yearSelect.appendChild(option);
        }
    }

    function populateMonths() {
        if (!monthSelect) return;
        for (var m = 1; m <= 12; m++) {
            var option = document.createElement('option');
            option.value = String(m);
            option.textContent = pad2(m);
            monthSelect.appendChild(option);
        }
    }

    function populateDays(year, month) {
        if (!daySelect) return;
        var existing = daySelect.value;
        daySelect.innerHTML = '<option value="">Nap</option>';

        if (!year || !month) return;
        var daysInMonth = new Date(year, month, 0).getDate();
        for (var d = 1; d <= daysInMonth; d++) {
            var option = document.createElement('option');
            option.value = String(d);
            option.textContent = pad2(d);
            daySelect.appendChild(option);
        }

        if (existing && parseInt(existing, 10) <= daysInMonth) {
            daySelect.value = existing;
        }
    }

    function updateBirthdateValue() {
        if (!yearSelect || !monthSelect || !daySelect) return;
        var year = yearSelect.value;
        var month = monthSelect.value;
        var day = daySelect.value;

        if (!year || !month || !day) {
            dateInput.value = '';
            ageResult.textContent = '';
            calcAge.value = '';
            dateInput.removeAttribute('aria-invalid');
            setBirthdateVisualState('neutral');
            if (result.textContent === '18 éves kor alatt nem lehet regisztrálni!') {
                result.textContent = '';
            }
            return;
        }

        var isoDate = year + '-' + pad2(parseInt(month, 10)) + '-' + pad2(parseInt(day, 10));
        dateInput.value = isoDate;

        var birth = new Date(isoDate);
        var now = new Date();
        var age = now.getFullYear() - birth.getFullYear();
        var m = now.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--;
        calcAge.value = age;
        ageResult.textContent = 'Életkor: ' + age;

        if (age < 18) {
            result.textContent = '18 éves kor alatt nem lehet regisztrálni!';
            dateInput.setAttribute('aria-invalid', 'true');
            setBirthdateVisualState('invalid');
        } else {
            result.textContent = '';
            dateInput.removeAttribute('aria-invalid');
            setBirthdateVisualState('valid');
        }
    }

    populateYears();
    populateMonths();
    populateDays(yearSelect.value, monthSelect.value);

    if (yearSelect && monthSelect && daySelect) {
        yearSelect.addEventListener('change', function () {
            populateDays(yearSelect.value, monthSelect.value);
            updateBirthdateValue();
        });
        monthSelect.addEventListener('change', function () {
            populateDays(yearSelect.value, monthSelect.value);
            updateBirthdateValue();
        });
        daySelect.addEventListener('change', updateBirthdateValue);
    }

    // Image previews
    function setupPreview(inputId, imgId) {
        var fileInput = document.getElementById(inputId);
        var img = document.getElementById(imgId);
        fileInput.addEventListener('change', function () {
            if (this.files[0]) {
                img.src = URL.createObjectURL(this.files[0]);
                img.style.display = 'block';
            }
        });
    }
    setupPreview('modal2-id_image_first', 'modal2-id_preview_first');
    setupPreview('modal2-id_image_second', 'modal2-id_preview_second');
    setupPreview('modal2-address_image', 'modal2-address_preview');

    // Back button
    var backBtn = document.getElementById('backToRegister1');
    if (backBtn) {
        backBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var reg2 = bootstrap.Modal.getInstance(document.getElementById('registerModal2'));
            if (reg2) reg2.hide();
            var reg1 = new bootstrap.Modal(document.getElementById('registerModal'));
            reg1.show();
        });
    }

    // Form submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var age = parseInt(calcAge.value);
        if (isNaN(age) || age < 18) {
            result.textContent = '18 éves kor alatt nem lehet regisztrálni!';
            result.style.color = 'red';
            dateInput.setAttribute('aria-invalid', 'true');
            setBirthdateVisualState('invalid');
            return;
        }

        // Ellenőrzés: 1. lépés adatai megvannak-e
        if (!window.registerStep1Data) {
            result.textContent = 'Hiba: Kérlek kezdd újra a regisztrációt!';
            result.style.color = 'red';
            return;
        }

        // Összes adat összegyűjtése FormData-ba
        var formData = new FormData();

        // 1. lépés adatai (memóriából)
        formData.append('username', window.registerStep1Data.username);
        formData.append('email', window.registerStep1Data.email);
        formData.append('password', window.registerStep1Data.password);
        formData.append('phone', window.registerStep1Data.phone);
        formData.append('terms_rules', window.registerStep1Data.terms_rules);
        formData.append('terms_privacy', window.registerStep1Data.terms_privacy);

        // 2. lépés adatai (formból)
        formData.append('pre_name', form.querySelector('input[name="Pre_name"]').value.trim());
        formData.append('family_name', form.querySelector('input[name="family_name"]').value.trim());
        formData.append('sure_name', form.querySelector('input[name="Sure_name"]').value.trim());
        formData.append('mother_full_name', form.querySelector('input[name="mother_full_name"]').value.trim());
        formData.append('birthplace', birthplaceInput.value.trim());
        formData.append('birthdate', dateInput.value);
        formData.append('calculated_age', calcAge.value);

        // Fájlok csatolása
        var idFirst = document.getElementById('modal2-id_image_first');
        var idSecond = document.getElementById('modal2-id_image_second');
        var addressImg = document.getElementById('modal2-address_image');
        if (idFirst.files[0]) formData.append('id_image_first', idFirst.files[0]);
        if (idSecond.files[0]) formData.append('id_image_second', idSecond.files[0]);
        if (addressImg.files[0]) formData.append('address_image', addressImg.files[0]);

        // Küldés a backendnek
        result.textContent = 'Regisztráció folyamatban...';
        result.style.color = '#666';

        fetch('../../backend/Auth/register.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status + ': ' + res.statusText);
            }
            return res.text();
        })
        .then(function (text) {
            try {
                var data = JSON.parse(text);
                if (data.success) {
                    result.style.color = 'green';
                    result.textContent = data.message;
                    // Modalok bezárása és forma resetelése
                    setTimeout(function () {
                        var reg2 = bootstrap.Modal.getInstance(document.getElementById('registerModal2'));
                        if (reg2) reg2.hide();
                        form.reset();
                        document.getElementById('modal2-id_preview_first').style.display = 'none';
                        document.getElementById('modal2-id_preview_second').style.display = 'none';
                        document.getElementById('modal2-address_preview').style.display = 'none';
                        ageResult.textContent = '';
                        // 1. lépés adatainak törlése
                        window.registerStep1Data = null;
                    }, 1500);
                } else {
                    result.style.color = 'red';
                    result.textContent = data.message;
                }
            } catch (parseErr) {
                result.style.color = 'red';
                result.textContent = 'Szerver hiba történt (500). Kérjük, próbáld újra később.';
                console.error('JSON parse error:', parseErr, 'Response:', text);
            }
        })
        .catch(function (err) {
            result.style.color = 'red';
            result.textContent = 'Hiba történt a regisztráció során.';
            console.error(err);
        });
    });
});
