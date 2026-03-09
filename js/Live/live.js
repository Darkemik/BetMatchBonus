document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("matches-container");
    let refreshInterval = null;

    /**
     * 3 másodpercenként: API frissítés + új tábla HTML betöltése.
     * Az API adja a pontos meccsidőt (pl. 55'), azt mutatjuk.
     */
    function refreshLiveMatches() {
        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(() => {
                return fetch("../../backend/ApiRequest/live_table.php");
            })
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
            })
            .catch(err => {
                console.error("Hiba a meccsek frissítésekor:", err);
                container.innerHTML = `
                    <p class="text-center mt-3">Hiba történt a meccsek betöltésekor.</p>
                `;
            });
    }

    function startAutoRefresh() {
        refreshInterval = setInterval(refreshLiveMatches, 3000);
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });

    startAutoRefresh();
});