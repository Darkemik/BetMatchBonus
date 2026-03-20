document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('registerModalForm');
    var result = document.getElementById('registerModalResult');

    // switchToLogin gomb
    var switchToLoginBtn = document.getElementById('switchToLogin');
    if (switchToLoginBtn) {
        switchToLoginBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var reg1 = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
            if (reg1) reg1.hide();
            var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        });
    }

    // Jelszó toggle
    document.querySelectorAll('#registerModal .toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = this.getAttribute('data-target');
            var input = document.getElementById(targetId);
            if (input) {
                input.type = input.type === 'password' ? 'text' : 'password';
            }
        });
    });

    // Form submit – 1. lépés validáció
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var username = form.querySelector('input[name="username"]').value.trim();
            var email = document.getElementById('modal-email').value.trim();
            var email2 = document.getElementById('modal-email2').value.trim();
            var password = document.getElementById('modal-password').value;
            var password2 = document.getElementById('modal-password2').value;
            var phone = document.getElementById('modal-phone').value.trim();
            var termsRules = document.getElementById('modal-terms_rules').checked ? '1' : '';
            var termsPrivacy = document.getElementById('modal-terms_privacy').checked ? '1' : '';

            if (username.length < 2) {
                result.style.color = 'red';
                result.textContent = 'A felhasználónév legalább 2 karakter hosszú kell legyen!';
                return;
            }

            if (email !== email2) {
                result.style.color = 'red';
                result.textContent = 'A két email cím nem egyezik!';
                return;
            }

            if (password.length < 7) {
                result.style.color = 'red';
                result.textContent = 'A jelszó legalább 7 karakter hosszú kell legyen!';
                return;
            }

            if (password !== password2) {
                result.style.color = 'red';
                result.textContent = 'A két jelszó nem egyezik!';
                return;
            }

            if (phone.length < 11) {
                result.style.color = 'red';
                result.textContent = 'A telefonszám legalább 11 számjegy hosszú kell legyen!';
                return;
            }

            if (!termsRules || !termsPrivacy) {
                result.style.color = 'red';
                result.textContent = 'A folytatáshoz el kell fogadnod a szabályzatot és az adatkezelési tájékoztatót!';
                return;
            }

            result.textContent = '';

            // 1. lépés adatainak mentése
            window.registerStep1Data = {
                username: username,
                email: email,
                password: password,
                phone: phone,
                terms_rules: termsRules,
                terms_privacy: termsPrivacy
            };

            // registerModal bezárása, registerModal2 megnyitása
            var reg1 = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
            if (reg1) reg1.hide();
            var reg2 = new bootstrap.Modal(document.getElementById('registerModal2'));
            reg2.show();
        });
    }
});