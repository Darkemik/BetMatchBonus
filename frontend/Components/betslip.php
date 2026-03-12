<aside class="betslip">
    <div class="betslip-tabs">
        <button class="betslip-tab active" data-tab="szelveny">Szelvény</button>
        <button class="betslip-tab" data-tab="naplo">Napló</button>
    </div>

    <!-- Szelvény tartalom -->
    <div class="betslip-content active" id="betslip-szelveny">
        <div class="betslip-empty">
            <i class="fas fa-ticket-alt betslip-empty-icon"></i>
            <p>Nincs fogadás a szelvényen</p>
            <span>Válassz egy mérkőzést és tedd meg a tippet!</span>
        </div>

        <div class="betslip-items" id="betslip-items" style="display:none;">
            <!-- JS-ből jönnek a tételek -->
        </div>

        <div class="betslip-footer" id="betslip-footer" style="display:none;">
            <div class="betslip-stake">
                <label for="stake-input">Tét (Ft):</label>
                <input type="number" id="stake-input" class="stake-input" min="100" step="100" value="500" placeholder="Tét...">
            </div>
            <div class="betslip-summary">
                <div class="betslip-row">
                    <span>Tételek:</span>
                    <span id="betslip-count">0</span>
                </div>
                <div class="betslip-row">
                    <span>Összesített odds:</span>
                    <span id="betslip-total-odds">0.00</span>
                </div>
                <div class="betslip-row betslip-row-highlight">
                    <span>Lehetséges nyeremény:</span>
                    <span id="betslip-potential-win">0 Ft</span>
                </div>
            </div>
            <button class="betslip-submit-btn" id="betslip-submit">
                <i class="fas fa-check"></i> Fogadás elküldése
            </button>
        </div>
    </div>

    <!-- Napló tartalom -->
    <div class="betslip-content" id="betslip-naplo">
        <div class="naplo-empty" id="naplo-empty">
            <i class="fas fa-history naplo-empty-icon"></i>
            <p>Még nincs korábbi fogadásod</p>
            <span>A leadott fogadásaid itt fognak megjelenni.</span>
        </div>

        <div class="naplo-items" id="naplo-items" style="display:none;">
            <!-- JS-ből jönnek a korábbi fogadások -->
        </div>
    </div>
</aside>

<!-- Fogadás visszaigazolás modal -->
<div class="bet-confirm-overlay" id="bet-confirm-overlay">
    <div class="bet-confirm-modal">
        <div class="bet-confirm-header">
            <span class="bet-confirm-icon">✅</span>
            <h3>Fogadás sikeresen leadva!</h3>
            <button class="bet-confirm-close" id="bet-confirm-close">&times;</button>
        </div>
        <div class="bet-confirm-body">
            <div class="bet-confirm-items" id="bet-confirm-items"></div>
            <div class="bet-confirm-summary">
                <div class="bet-confirm-row">
                    <span>Tét:</span>
                    <span id="bet-confirm-stake">-</span>
                </div>
                <div class="bet-confirm-row">
                    <span>Össz. odds:</span>
                    <span id="bet-confirm-odds">-</span>
                </div>
                <div class="bet-confirm-row highlight">
                    <span>Lehetséges nyeremény:</span>
                    <span id="bet-confirm-win">-</span>
                </div>
            </div>
        </div>
        <div class="bet-confirm-footer">
            <button class="bet-confirm-ok-btn" id="bet-confirm-ok">Rendben</button>
        </div>
    </div>
</div>