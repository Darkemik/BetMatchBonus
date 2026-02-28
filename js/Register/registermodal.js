document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerModalForm');
    const result = document.getElementById('registerModalResult');

    var switchToLogin = document.getElementById('switchToLogin');
    if (switchToLogin) {
        switchToLogin.addEventListener('click', function (e) {
            e.preventDefault();
            var regModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
            if (regModal) regModal.hide();
            var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        });
    }

    document.querySelectorAll('#registerModal .toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.getAttribute('data-target'));
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.querySelector('.eye-icon').style.display = isPassword ? 'none' : 'inline';
            btn.querySelector('.eye-slash-icon').style.display = isPassword ? 'inline' : 'none';
        });
    });

    form.addEventListener('submit', function (e) {
        const username = form.querySelector('input[name="username"]').value.trim();
        const email = document.getElementById('modal-email').value.trim();
        const email2 = document.getElementById('modal-email2').value.trim();
        const pass = document.getElementById('modal-password').value;
        const pass2 = document.getElementById('modal-password2').value;

        if (username.length < 2) {
            e.preventDefault();
            result.textContent = 'A felhasználónév legalább 2 karakter legyen!';
            return;
        }

        if (email !== email2) {
            e.preventDefault();
            result.textContent = 'A két email cím nem egyezik!';
            return;
        }

        if (pass.length < 7) {
            e.preventDefault();
            result.textContent = 'A jelszó legalább 7 karakter legyen!';
            return;
        }

        if (pass !== pass2) {
            e.preventDefault();
            result.textContent = 'A két jelszó nem egyezik!';
            return;
        }

        result.textContent = '';

        // If validation passes, open step 2 modal
        e.preventDefault();
        var regModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
        if (regModal) regModal.hide();
        var regModal2 = new bootstrap.Modal(document.getElementById('registerModal2'));
        regModal2.show();
    });
});
