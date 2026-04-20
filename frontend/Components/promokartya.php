        <!-- Jobb oldali sáv -->
        <aside class="right-sidebar">
            <div class="promo-kartya">
                <img src="../../img/oddsspaceship.jpeg" alt="Oddsűrhajó">
                <h3 data-i18n="promo.oddsShipTitle">ODDSŰRHAJÓ!</h3>
                <p data-i18n="promo.oddsShipDesc">A legjobb szorzók, kizárólag nálunk!</p>
                <button class="tobb-info-gomb" onclick="goToOddsShipMatch()">
                    <span data-i18n="promo.details">TOVÁBB</span>
                </button>
            </div>
            <div class="promo-kartya">
                <img src="../../img/cashout.jpeg" alt="Cashout">
                <h3 data-i18n="promo.cashoutTitle">CASH OUT - AZONNALI KIFIZETÉS</h3>
                <p><span data-i18n="promo.cashoutDesc">A Cash Out használatával megjátszott fogadásaidat még az esemény vége előtt, saját döntésedre lezárhatod, ezzel pedig biztosíthatod a nyereményedet</span></p>
                <button class="tobb-info-gomb" onclick="location.href='../../frontend/MainMenu/MainMenu.php'">
                    <span data-i18n="promo.details">TOVÁBB</span>
                </button>
            </div>
            <div class="promo-kartya">
                <img src="../../img/oddspiramid.jpeg" alt="Odds piramis">
                <h3 data-i18n="promo.oddsPyramidTitle">ODDSPIRAMIS</h3>
                <p data-i18n="promo.oddsPyramidDesc">Növelnéd a nyereményed? Keress aktuális ajánlatunkat a promóciók között!</p>
                <button class="tobb-info-gomb" onclick="location.href='../../frontend/MainMenu/MainMenu.php'">
                    <span data-i18n="promo.details">TOVÁBB</span>
                </button>
            </div>
        </aside>

        <script>
        function goToOddsShipMatch() {
            const baseUrl = '../../frontend/MainMenu/MainMenu.php';
            const fallbackUrl = `${baseUrl}?boosted=1`;

            fetch('../../backend/ApiRequest/get_boosted_match.php', { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    const eventId = parseInt(data && data.eventId, 10);
                    if (Number.isFinite(eventId) && eventId > 0) {
                        window.location.href = `${baseUrl}?eventId=${eventId}&boosted=1`;
                        return;
                    }
                    window.location.href = fallbackUrl;
                })
                .catch(() => {
                    window.location.href = fallbackUrl;
                });
        }
        </script>