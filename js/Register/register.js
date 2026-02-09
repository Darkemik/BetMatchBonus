
const result = document.getElementById("result");
const submitBtn = document.getElementById("regisztracio");

const form = document.getElementById("registerForm");





// ✔️ Gomb elküldéskor: email + jelszó egyezzen
form.addEventListener("submit", function (e) {
  const email = document.getElementById("email").value.trim();
  const email2 = document.getElementById("email2").value.trim();
  const pass = document.getElementById("password").value;
  const pass2 = document.getElementById("password2").value;

  // Email ellenőrzés
  if (email !== email2) {
    e.preventDefault();
    result.textContent = "A két email cím nem egyezik!"; 
    return;
  }

  // Jelszó ellenőrzés
  if (pass !== pass2) {
    e.preventDefault();
    result.textContent = "A két jelszó nem egyezik!";
    return;
  }
  console.log("form:", form);
  // Ha minden rendben → submit mehet
});
