document.addEventListener('DOMContentLoaded', function () {
    var dateInput = document.getElementById('modal2-date-input');
    var ageResult = document.getElementById('modal2-age-result');
    var calcAge = document.getElementById('modal2-calculated_age');
    var form = document.getElementById('registerModal2Form');
    var result = document.getElementById('registerModal2Result');

    // Prevent typing in date input
    dateInput.addEventListener('keydown', function (e) { e.preventDefault(); });
    dateInput.addEventListener('click', function () {
        if (dateInput.showPicker) dateInput.showPicker();
    });

    // Age validation
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
        } else {
            result.textContent = '';
        }
    });

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
            return;
        }
        result.textContent = '';
        alert('Regisztráció sikeres!');
        var reg2 = bootstrap.Modal.getInstance(document.getElementById('registerModal2'));
        if (reg2) reg2.hide();
    });
});
