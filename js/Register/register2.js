const dateInput = document.getElementById("date-input");
const submitBtn = document.getElementById("regisztracio");
const result = document.getElementById("result");

const originalBtnText = submitBtn.textContent;

function previewImage(inputId, imgId) {
    const fileInput = document.getElementById(inputId);
    const img = document.getElementById(imgId);
  
    fileInput.addEventListener("change", function () {
      const file = this.files[0];
      if (file) {
        img.src = URL.createObjectURL(file);
      }
    });
  }
  
  previewImage("id_image_first", "id_preview_first");
  previewImage("id_image_second", "id_preview_second");
  previewImage("address_image", "address_preview");
  
// 🔒 Gépelés tiltása
dateInput.addEventListener("keydown", (e) => e.preventDefault());

// Dátumválasztó megnyitása kattintásra (ha támogatott)
dateInput.addEventListener("click", () => {
  if (dateInput.showPicker) dateInput.showPicker();
});

// 📅 Max = mai nap


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
