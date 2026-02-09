const dateInput = document.getElementById("date-input");
const submitBtn = document.getElementById("regisztracio");
const result = document.getElementById("result");

const originalBtnText = submitBtn.textContent;

// 🔒 Gépelés tiltása
dateInput.addEventListener("keydown", (e) => e.preventDefault());

// Dátumválasztó megnyitása kattintásra (ha támogatott)
dateInput.addEventListener("click", () => {
  if (dateInput.showPicker) dateInput.showPicker();
});

// 📅 Max = mai nap
const today = new Date().toISOString().split("T")[0];
dateInput.max = today;

// ✅ KÉP ELŐNÉZET — KÜLÖN!
function bindPreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
  
    if (!input || !preview) return;
  
    input.addEventListener("change", function () {
      const file = this.files[0];
  
      if (!file) {
        preview.src = "";
        preview.style.display = "none";
        return;
      }
  
      if (!file.type.startsWith("image/")) {
        alert("Csak képfájlt tölthetsz fel!");
        this.value = "";
        preview.src = "";
        preview.style.display = "none";
        return;
      }
  
      preview.src = URL.createObjectURL(file);
      preview.style.display = "block";
    });
  }
  
  bindPreview("id_image_first", "preview_id_first");
  bindPreview("id_image_second", "preview_id_second");
  bindPreview("address_image_first", "preview_address");
  
// ✔️ Életkor ellenőrzés
dateInput.addEventListener("change", function () {
  if (!this.value) {
    result.textContent = "";
    submitBtn.disabled = true;
    submitBtn.textContent = originalBtnText;
    document.getElementById("calculated_age").value = "";
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

  result.textContent = `Életkor: ${age}`;
});
