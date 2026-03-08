document.addEventListener('DOMContentLoaded', function () {
    var dateInput = document.getElementById('modal2-date-input');
    var ageResult = document.getElementById('modal2-age-result');
    var calcAge = document.getElementById('modal2-calculated_age');
    var form = document.getElementById('registerModal2Form');
    var result = document.getElementById('registerModal2Result');

    // Dátum gépelés tiltása
    dateInput.addEventListener('keydown', function (e) { e.preventDefault(); });
    dateInput.addEventListener('click', function () {
        if (dateInput.showPicker) dateInput.showPicker();
    });

    // Kor számítás
    dateInput.addEventListener('change', function () {
        if (!this.value) {
            ageResult.textContent = '';
            calcAge.value = '';
            return;
        }
        var birth = new Date(this.value);
        var now = new Date();
        var age = now.getFullYear() - birth.getFullYear();
        var m = now.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--;
        calcAge.value = age;
        ageResult.textContent = 'Életkor: ' + age;

        if (age < 18) {
            result.textContent = '18 éves kor alatt nem lehet regisztrálni!';
            result.style.color = 'red';
        } else {
            result.textContent = '';
        }
    });

    // Kép előnézet
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

    // Vissza gomb
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

    // ⚡ REGISZTRÁCIÓ KÜLDÉS – AJAX-szal a backendnek
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var age = parseInt(calcAge.value);
        if (isNaN(age) || age < 18) {
            result.textContent = '18 éves kor alatt nem lehet regisztrálni!';
            result.style.color = 'red';
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
        formData.append('terms_rules', window.registerStep1Data.terms_rules);
        formData.append('terms_privacy', window.registerStep1Data.terms_privacy);

        // 2. lépés adatai (formból)
        formData.append('pre_name', form.querySelector('input[name="Pre_name"]').value.trim());
        formData.append('family_name', form.querySelector('input[name="family_name"]').value.trim());
        formData.append('sure_name', form.querySelector('input[name="Sure_name"]').value.trim());
        formData.append('mother_full_name', form.querySelector('input[name="mother_full_name"]').value.trim());
        formData.append('birthplace', form.querySelector('input[name="birthplace"]').value.trim());
        formData.append('birthdate', dateInput.value);
        formData.append('calculated_age', calcAge.value);

        // Küldés a backendnek
        result.textContent = 'Regisztráció folyamatban...';
        result.style.color = '#666';

        fetch('../../backend/Auth/register.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                result.style.color = 'green';
                result.textContent = data.message;
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                result.style.color = 'red';
                result.textContent = data.message;
            }
        })
        .catch(function (err) {
            result.style.color = 'red';
            result.textContent = 'Hiba történt a regisztráció során.';
            console.error(err);
        });
    });
});