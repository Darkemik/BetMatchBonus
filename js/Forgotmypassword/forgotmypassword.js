document.addEventListener('DOMContentLoaded', function () {
  // Forgotpassword form submit
  const forgotPasswordForm = document.getElementById('forgotPasswordForm');
  const forgotResult = document.getElementById('forgotPasswordResult');

  if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var email = forgotPasswordForm.querySelector('input[name="email"]').value.trim();
      var username = forgotPasswordForm.querySelector('input[name="username"]').value.trim();

      if (!email || !username) {
        forgotResult.style.color = 'red';
        forgotResult.textContent = 'E-mail cím és felhasználónév megadása kötelező!';
        return;
      }

      var fd = new FormData();
      fd.append('email', email);
      fd.append('username', username);

      fetch('../../backend/Auth/forgotpassword.php', {
        method: 'POST',
        body: fd
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.success) {
            forgotResult.style.color = 'red';
            forgotResult.textContent = data.message || 'Hiba történt.';
            return;
          }

          forgotResult.style.color = 'green';
          forgotResult.textContent = data.message || 'E-mail sikeresen elküldve! Kérjük ellenőrizd a postafiókod.';

          // 2 másodperc után bezárjuk a modalt
          setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
            if (modal) modal.hide();
            forgotPasswordForm.reset();
          }, 2000);
        })
        .catch(function (err) {
          console.error(err);
          forgotResult.style.color = 'red';
          forgotResult.textContent = 'Hiba történt a kérés során.';
        });
    });
  }

  // Forgot username form submit
  const forgotUsernameForm = document.getElementById('forgotUsernameForm');
  const forgotUsernameResult = document.getElementById('forgotUsernameResult');

  if (forgotUsernameForm) {
    forgotUsernameForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var email = forgotUsernameForm.querySelector('input[name="email"]').value.trim();
      var birthdate = forgotUsernameForm.querySelector('input[name="birthdate"]').value.trim();

      if (!email || !birthdate) {
        forgotUsernameResult.style.color = 'red';
        forgotUsernameResult.textContent = 'E-mail cím és születési dátum megadása kötelező!';
        return;
      }

      var fd = new FormData();
      fd.append('email', email);
      fd.append('birthdate', birthdate);

      fetch('../../backend/Auth/forgotusername.php', {
        method: 'POST',
        body: fd
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.success) {
            forgotUsernameResult.style.color = 'red';
            forgotUsernameResult.textContent = data.message || 'Hiba történt.';
            return;
          }

          forgotUsernameResult.style.color = 'green';
          forgotUsernameResult.textContent = data.message || 'E-mail sikeresen elküldve! Kérjük ellenőrizd a postafiókod.';

          // 2 másodperc után bezárjuk a modalt
          setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('forgotUsernameModal'));
            if (modal) modal.hide();
            forgotUsernameForm.reset();
          }, 2000);
        })
        .catch(function (err) {
          console.error(err);
          forgotUsernameResult.style.color = 'red';
          forgotUsernameResult.textContent = 'Hiba történt a kérés során.';
        });
    });
  }

  // Modal switch: login -> forgotpassword
  const switchToForgotPassword = document.getElementById('switchToForgotPassword');
  if (switchToForgotPassword) {
    switchToForgotPassword.addEventListener('click', function (e) {
      e.preventDefault();
      const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
      if (loginModal) loginModal.hide();
      const forgotModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
      forgotModal.show();
    });
  }

  // Modal switch: forgotpassword -> register
  const switchToRegisterFromForgot = document.getElementById('switchToRegisterFromForgot');
  if (switchToRegisterFromForgot) {
    switchToRegisterFromForgot.addEventListener('click', function (e) {
      e.preventDefault();
      const forgotModal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
      if (forgotModal) forgotModal.hide();
      const registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
      registerModal.show();
    });
  }

  // Modal switch: forgotpassword -> login (back button)
  const backToLoginFromForgot = document.getElementById('backToLoginFromForgot');
  if (backToLoginFromForgot) {
    backToLoginFromForgot.addEventListener('click', function (e) {
      e.preventDefault();
      const forgotModal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
      if (forgotModal) forgotModal.hide();
      const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
      loginModal.show();
    });
  }

  // Modal switch: forgotpassword -> forgotusername
  const switchToUsernameHelp = document.getElementById('switchToUsernameHelp');
  if (switchToUsernameHelp) {
    switchToUsernameHelp.addEventListener('click', function (e) {
      e.preventDefault();
      const forgotModal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
      if (forgotModal) forgotModal.hide();
      const forgotUsernameModal = new bootstrap.Modal(document.getElementById('forgotUsernameModal'));
      forgotUsernameModal.show();
    });
  }

  // Modal switch: forgotusername -> forgotpassword (back button)
  const backToForgotPasswordFromUsername = document.getElementById('backToForgotPasswordFromUsername');
  if (backToForgotPasswordFromUsername) {
    backToForgotPasswordFromUsername.addEventListener('click', function (e) {
      e.preventDefault();
      const forgotUsernameModal = bootstrap.Modal.getInstance(document.getElementById('forgotUsernameModal'));
      if (forgotUsernameModal) forgotUsernameModal.hide();
      const forgotModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
      forgotModal.show();
    });
  }

  // Modal switch: forgotusername -> forgotpassword (link)
  const switchBackToForgotPassword = document.getElementById('switchBackToForgotPassword');
  if (switchBackToForgotPassword) {
    switchBackToForgotPassword.addEventListener('click', function (e) {
      e.preventDefault();
      const forgotUsernameModal = bootstrap.Modal.getInstance(document.getElementById('forgotUsernameModal'));
      if (forgotUsernameModal) forgotUsernameModal.hide();
      const forgotModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
      forgotModal.show();
    });
  }
});
