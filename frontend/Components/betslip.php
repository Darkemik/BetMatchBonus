<aside class="ticket-panel">
    <div class="ticket-tabs">
        <button class="ticket-tab active" data-tab="ticket">Ticket</button>
        <button class="ticket-tab" data-tab="elozmeny">Fogadási előzmények</button>
    </div>

    <!-- Ticket tartalom -->
    <div class="ticket-content active" id="ticket-ticket">
        <div class="ticket-empty">
            <i class="fas fa-ticket-alt ticket-empty-icon"></i>
            <p>Nincs fogadás a ticketen</p>
            <span>Válassz egy mérkőzést és tedd meg a tippet!</span>
        </div>

        <div class="ticket-items" id="ticket-items" style="display:none;">
            <!-- JS-ből jönnek a tételek -->
        </div>

        <div class="ticket-footer" id="ticket-footer" style="display:none;">
            <div class="ticket-stake">
                <label for="stake-input">Tét (Ft):</label>
                <input type="number" id="stake-input" class="stake-input" min="100" step="100" value="500" placeholder="Tét...">
            </div>
            <div class="ticket-summary">
                <div class="ticket-row">
                    <span>Tételek:</span>
                    <span id="ticket-count">0</span>
                </div>
                <div class="ticket-row">
                    <span>Összesített odds:</span>
                    <span id="ticket-total-odds">0.00</span>
                </div>
                <div class="ticket-row ticket-row-highlight">
                    <span>Lehetséges nyeremény:</span>
                    <span id="ticket-potential-win">0 Ft</span>
                </div>
            </div>
            <button class="ticket-submit-btn" id="ticket-submit">
                <i class="fas fa-check"></i> Fogadás elküldése
            </button>
        </div>
    </div>

    <!-- Fogadási előzmények tartalom -->
    <div class="ticket-content" id="ticket-elozmeny">
        <div class="elozmeny-empty" id="elozmeny-empty">
            <i class="fas fa-history elozmeny-empty-icon"></i>
            <p>Még nincs korábbi fogadásod</p>
            <span>A leadott fogadásaid itt fognak megjelenni.</span>
        </div>

        <div class="elozmeny-items" id="elozmeny-items" style="display:none;">
            <!-- JS-ből jönnek a korábbi fogadások -->
        </div>
    </div>
</aside>

<!-- Fogadás visszaigazolás modal -->
<div class="ticket-confirm-overlay" id="ticket-confirm-overlay">
    <div class="ticket-confirm-modal">
        <div class="ticket-confirm-header">
            <span class="ticket-confirm-icon">✅</span>
            <h3>Fogadás sikeresen leadva!</h3>
            <button class="ticket-confirm-close" id="ticket-confirm-close">&times;</button>
        </div>
        <div class="ticket-confirm-body">
            <div class="ticket-confirm-items" id="ticket-confirm-items"></div>
            <div class="ticket-confirm-summary">
                <div class="ticket-confirm-row">
                    <span>Tét:</span>
                    <span id="ticket-confirm-stake">-</span>
                </div>
                <div class="ticket-confirm-row">
                    <span>Össz. odds:</span>
                    <span id="ticket-confirm-odds">-</span>
                </div>
                <div class="ticket-confirm-row highlight">
                    <span>Lehetséges nyeremény:</span>
                    <span id="ticket-confirm-win">-</span>
                </div>
            </div>
        </div>
        <div class="ticket-confirm-footer">
            <button class="ticket-confirm-ok-btn" id="ticket-confirm-ok">Rendben</button>
        </div>
    </div>
</div>