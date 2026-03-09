document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("loginModalForm");
  const result = document.getElementById("loginModalResult");

  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const login = form.querySelector('input[name="login"]')?.value?.trim() || "";
    const password = form.querySelector('input[name="password"]')?.value || "";

    if (!login || !password) {
      result.textContent = "Minden mező kitöltése kötelező!";
      result.style.color = "red";
      return;
    }

    const formData = new FormData();
    formData.append("login", login);
    formData.append("password", password);

    try {
      const res = await fetch("../../backend/Auth/login.php", {
        method: "POST",
        body: formData,
      });
      const data = await res.json();

      if (!data.success) {
        result.textContent = data.message || "Sikertelen bejelentkezés.";
        result.style.color = "red";
        return;
      }

      result.textContent = data.message || "Sikeres bejelentkezés!";
      result.style.color = "green";

      // modal bezár
      const modalEl = document.getElementById("loginModal");
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.hide();

      // UI frissítés (header gombok -> user menü)
      document.dispatchEvent(new CustomEvent("auth:changed"));
    } catch (err) {
      console.error(err);
      result.textContent = "Hiba történt a bejelentkezéskor.";
      result.style.color = "red";
    }
  });
});