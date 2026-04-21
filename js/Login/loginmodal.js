document.addEventListener('DOMContentLoaded', function () {
  var LOCATION_CACHE_KEY = 'bmb_client_settlement';
  var LOCATION_CACHE_TTL_MS = 24 * 60 * 60 * 1000;

  function withTimeout(promise, timeoutMs) {
    return Promise.race([
      promise,
      new Promise(function(resolve) {
        setTimeout(function() { resolve(null); }, timeoutMs);
      })
    ]);
  }

  function readCachedSettlement() {
    try {
      var raw = localStorage.getItem(LOCATION_CACHE_KEY);
      if (!raw) return '';
      var parsed = JSON.parse(raw);
      if (!parsed || !parsed.name || !parsed.ts) return '';
      if ((Date.now() - parsed.ts) > LOCATION_CACHE_TTL_MS) return '';
      return String(parsed.name);
    } catch (e) {
      return '';
    }
  }

  function writeCachedSettlement(name) {
    try {
      localStorage.setItem(LOCATION_CACHE_KEY, JSON.stringify({
        name: String(name || ''),
        ts: Date.now()
      }));
    } catch (e) {
      // Cache hiba esetén csendben továbblépünk.
    }
  }

  function reverseGeocodeSettlement(lat, lon) {
    var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon);
    return fetch(url, {
      headers: {
        'Accept': 'application/json'
      }
    })
      .then(function(res) {
        if (!res.ok) return null;
        return res.json();
      })
      .then(function(data) {
        if (!data || !data.address) return '';
        var a = data.address;
        var settlement = a.city || a.town || a.village || a.hamlet || a.municipality || a.county || '';
        var countryCode = (a.country_code || '').toUpperCase();
        return settlement ? (countryCode ? (settlement + ', ' + countryCode) : settlement) : '';
      })
      .catch(function() {
        return '';
      });
  }

  function getClientSettlement() {
    var cached = readCachedSettlement();
    if (cached) {
      return Promise.resolve(cached);
    }

    if (!navigator.geolocation || !window.isSecureContext) {
      return Promise.resolve('');
    }

    return withTimeout(new Promise(function(resolve) {
      navigator.geolocation.getCurrentPosition(
        function(pos) {
          var lat = pos.coords && pos.coords.latitude;
          var lon = pos.coords && pos.coords.longitude;
          if (typeof lat !== 'number' || typeof lon !== 'number') {
            resolve('');
            return;
          }

          withTimeout(reverseGeocodeSettlement(lat, lon), 5000)
            .then(function(name) {
              var settlement = String(name || '').trim();
              if (settlement) writeCachedSettlement(settlement);
              resolve(settlement);
            })
            .catch(function() {
              resolve('');
            });
        },
        function() {
          resolve('');
        },
        {
          enableHighAccuracy: false,
          timeout: 30000,
          maximumAge: LOCATION_CACHE_TTL_MS
        }
      );
    }), 32000).then(function(name) {
      return String(name || '');
    });
  }

  // ── Caps Lock detektálás ──
  (function() {
    var pwField = document.getElementById('login-password');
    var warning = document.getElementById('capslock-login');
    if (!pwField || !warning) return;
    pwField.addEventListener('keydown', function(e) {
      if (e.getModifierState && typeof e.getModifierState === 'function') {
        warning.style.display = e.getModifierState('CapsLock') ? 'flex' : 'none';
      }
    });
    pwField.addEventListener('keyup', function(e) {
      if (e.getModifierState && typeof e.getModifierState === 'function') {
        warning.style.display = e.getModifierState('CapsLock') ? 'flex' : 'none';
      }
    });
    pwField.addEventListener('blur', function() { warning.style.display = 'none'; });
  })();

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

    // Böngésző felismerés kliens oldalon (Brave nem küldi a UA-ban)
    var clientBrowser = 'Unknown';
    if (navigator.brave && typeof navigator.brave.isBrave === 'function') {
      clientBrowser = 'Brave';
    } else if (/Edg\//i.test(navigator.userAgent)) {
      clientBrowser = 'Edge';
    } else if (/OPR\//i.test(navigator.userAgent) || /Opera/i.test(navigator.userAgent)) {
      clientBrowser = 'Opera';
    } else if (/Vivaldi/i.test(navigator.userAgent)) {
      clientBrowser = 'Vivaldi';
    } else if (/YaBrowser/i.test(navigator.userAgent)) {
      clientBrowser = 'Yandex';
    } else if (/Firefox/i.test(navigator.userAgent)) {
      clientBrowser = 'Firefox';
    } else if (/Safari/i.test(navigator.userAgent) && !/Chrome/i.test(navigator.userAgent)) {
      clientBrowser = 'Safari';
    } else if (/Chrome/i.test(navigator.userAgent)) {
      clientBrowser = 'Chrome';
    }
    fd.append('client_browser', clientBrowser);

    getClientSettlement().then(function(settlement) {
      if (settlement) {
        fd.append('client_location', settlement);
      }

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