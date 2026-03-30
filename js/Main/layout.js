// Nyelvvalto dropdown mukodese - csak UI (toggle open/close)
document.addEventListener('DOMContentLoaded', () => {
    const mainBtn = document.getElementById('btn-hu');
    const dropdown = document.getElementById('lang-dropdown');

    if (mainBtn && dropdown) {
        mainBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('open');
        });
    }

    document.addEventListener('click', (e) => {
        if (!dropdown) return;
        if (!dropdown.contains(e.target) && e.target !== mainBtn) {
            dropdown.classList.remove('open');
        }
    });
});
