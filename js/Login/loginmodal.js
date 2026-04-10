document.addEventListener('DOMContentLoaded', function () {
  // váltás regisztrációra
  var switchBtn = document.getElementById('switchToRegister');
  if (switchBtn) {
    switchBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
      if (loginModal) loginModal.hide();
      var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
      registerModal.show();
    });
  }

  // jelszó mutatás
  document.querySelectorAll('#loginModal .toggle-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = this.getAttribute('data-target');
      var input = document.getElementById(targetId);
      if (input) input.type = input.type === 'password' ? 'text' : 'password';
    });
  });

  // bejelentkezés submit
  var form = document.getElementById('loginModalForm');
  var result = document.getElementById('loginModalResult');

  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var login = form.querySelector('input[name="login"]').value.trim();
    var password = form.querySelector('input[name="password"]').value;
    var rememberMe = form.querySelector('input[name="rememberMe"]').checked ? '1' : '0';

    if (!login || !password) {
      result.style.color = 'red';
      result.textContent = 'Minden mező kitöltése kötelező!';
      return;
    }

    var fd = new FormData();
    fd.append('login', login);
    fd.append('password', password);
    fd.append('rememberMe', rememberMe);

    // reCAPTCHA v3 token lekérése, majd bejelentkezés
    if (typeof grecaptcha !== 'undefined') {
      grecaptcha.ready(function () {
        grecaptcha.execute(window.RECAPTCHA_SITE_KEY, { action: 'login' }).then(function (token) {
          fd.append('recaptcha_token', token);
          doLogin(fd);
        });
      });
    } else {
      doLogin(fd);
    }
  });

  function doLogin(fd) {
    var result = document.getElementById('loginModalResult');

    fetch('../../backend/Auth/login.php', {
      method: 'POST',
      body: fd
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success) {
          result.style.color = 'red';
          result.textContent = data.message || 'Sikertelen bejelentkezés.';
          return;
        }

        result.style.color = 'green';
        result.textContent = data.message || 'Sikeres bejelentkezés!';

        // modal bezár
        var loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
        if (loginModal) loginModal.hide();

        // UI frissítés (auth menü)
        document.dispatchEvent(new CustomEvent('auth:changed'));
      })
      .catch(function (err) {
        console.error(err);
        result.style.color = 'red';
        result.textContent = 'Hiba történt a bejelentkezéskor.';
      });
  }
});