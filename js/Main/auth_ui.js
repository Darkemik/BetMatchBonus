async function refreshAuthUI() {
  const authButtons = document.getElementById("authButtons");
  const userMenu = document.getElementById("userMenu");

  if (!authButtons || !userMenu) return;

  try {
    const res = await fetch("../../backend/Auth/me.php", { cache: "no-store" });
    const data = await res.json();

    if (!data.loggedIn) {
      authButtons.style.display = "";
      userMenu.style.display = "none";
      return;
    }

    authButtons.style.display = "none";
    userMenu.style.display = "";

    document.getElementById("userMenuUsername").textContent = data.user.username || "Fiókom";
    document.getElementById("userFullName").textContent = data.user.full_name || data.user.username || "-";
    document.getElementById("userEmail").textContent = data.user.email || "-";
  } catch (e) {
    console.error("me.php hiba:", e);
    // ha valamiért nem elérhető, ne törjük el a UI-t
  }
}

document.addEventListener("DOMContentLoaded", () => {
  refreshAuthUI();

  document.addEventListener("auth:changed", () => {
    refreshAuthUI();
  });

  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", async () => {
      await fetch("../../backend/Auth/logout.php", { method: "POST" });
      document.dispatchEvent(new CustomEvent("auth:changed"));
    });
  }
});