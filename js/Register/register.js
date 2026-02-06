const dateInput = document.getElementById("date-input");
const result = document.getElementById("result");
const submitBtn = document.getElementById("regisztracio");

const form = document.getElementById("registerForm");

// 🔒 Gépelés tiltása
dateInput.addEventListener("keydown", (e) => e.preventDefault());

dateInput.addEventListener("click", () => {
  if (dateInput.showPicker) {
    dateInput.showPicker();
  }
});

// 📅 Max = mai nap
const today = new Date().toISOString().split("T")[0];
dateInput.max = today;

// Eredeti gomb szöveg elmentése
const originalBtnText = submitBtn.textContent;

// ✔️ Életkor ellenőrzés
dateInput.addEventListener("change", function () {
  if (!this.value) {
    result.textContent = "";
    submitBtn.disabled = true;
    submitBtn.textContent = originalBtnText;
    return;
  }

  const birth = new Date(this.value);
  const now = new Date();

  let age = now.getFullYear() - birth.getFullYear();
  const m = now.getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--;

  document.getElementById("calculated_age").value = age;

  if (age < 18) {
    submitBtn.disabled = true;
    submitBtn.textContent = "18 éves kor alatt nem lehet regisztrálni";
  } else {
    submitBtn.disabled = false;
    submitBtn.textContent = originalBtnText;
  }
});

// Induláskor letiltva
submitBtn.disabled = true;



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
