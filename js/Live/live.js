/*
document.addEventListener("DOMContentLoaded", () => {

    const soccerBtn = document.getElementById("btn-soccer");
    const container = document.getElementById("matches-container");

    function loadSoccerLive() {
        fetch("../../backend/ApiRequest/live_soccer.php")
            .then(res => res.json())
            .then(data => {
                if (!Array.isArray(data) || data.length === 0) {
                    container.innerHTML = `
                        <p class="text-center mt-3">Jelenleg nincs élő labdarúgó mérkőzés.</p>
                    `;
                    return;
                }

                let html = "";

                data.forEach(m => {
                    html += `
                        <div class="live-match-card">
                            <div class="live-match-header">
                                <span class="live-country">${m.country_name}</span>
                                <span class="live-championship">${m.championship_name}</span>
                            </div>
                            <div class="live-match-body">
                                <div class="live-teams">${m.match_name}</div>
                                <div class="live-extra">
                                    <span class="live-time">${m.live_time || "Élő"}</span>
                                    <span class="live-kickoff">${m.start_utc} UTC</span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                container.innerHTML = html;
            })
            .catch(err => {
                console.error("Hiba:", err);
                container.innerHTML = "<p class='text-center mt-3'>Hiba történt a meccsek betöltésekor.</p>";
            });
    }

    // amikor a foci fülre kattintanak → betölti a meccseket
    soccerBtn.addEventListener("click", (e) => {
        e.preventDefault();
        loadSoccerLive();
    });

    // alapból is töltse be, ha az Élő oldalra érkezel
    loadSoccerLive();
});
*/