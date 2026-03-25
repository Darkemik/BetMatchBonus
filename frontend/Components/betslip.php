<div class="betslip-wrapper">
    <div class="betslip-header">
        <h3 class="betslip-title">
            <i class="fas fa-ticket-alt"></i>
            <span id="betslip-label">Ticket</span>
            <span class="betslip-count" id="betslip-count">0</span>
        </h3>
    </div>

    <div class="betslip-tabs">
        <button class="betslip-tab active" data-tab="ticket">🎫 Ticket</button>
        <button class="betslip-tab" data-tab="elozmeny">📊 Előzmények</button>
    </div>

    <!-- TICKET TARTALOM -->
    <div class="betslip-content active" id="betslip-ticket">

        <!-- EGYES / KÖTÉS SUB-TABS -->
        <div class="betslip-type-tabs">
            <button class="betslip-type-tab active" id="tab-egyes" data-type="egyes">Egyes</button>
            <button class="betslip-type-tab" id="tab-kotes" data-type="kotes">Kötés</button>
        </div>

        <div class="betslip-empty" id="betslip-empty">
            <i class="fas fa-inbox"></i>
            <p id="empty-message">Nincs aktív fogadás</p>
            <span style="font-size: 12px; color: #999;">Válassz meccseket és odds-okat!</span>
        </div>

        <div class="betslip-bets" id="betslip-bets" style="display: none;">
            <!-- JS-ből jönnek a betslip-item elemek -->
        </div>

        <div class="betslip-summary" id="betslip-summary" style="display: none;">
            <div class="summary-row">
                <span id="summary-label">Összesített odds:</span>
                <span class="summary-value" id="total-odds">1.00</span>
            </div>
            <div class="summary-row">
                <span id="stake-label">Tét (Ft):</span>
                <input 
                    type="number" 
                    id="stake-input" 
                    class="stake-input" 
                    value="100" 
                    min="100" 
                    step="100"
                    placeholder="100"
                    aria-label="Tét összege"
                >
            </div>
            <div class="summary-row highlight">
                <span id="payout-label">Lehetséges nyeremény:</span>
                <span class="summary-value highlight-value" id="potential-payout">100 Ft</span>
            </div>
        </div>

        <button 
            class="bet-button" 
            id="place-bet-btn" 
            style="display: none;"
            aria-label="Fogadás elhelyezése"
        >
            <i class="fas fa-check"></i> Ticket leadása
        </button>

        <button 
            class="clear-button" 
            id="clear-bets-btn" 
            style="display: none;"
            aria-label="Összes fogadás törlése"
        >
            <i class="fas fa-trash"></i> Összes törlése
        </button>
    </div>

    <!-- FOGADÁSI ELŐZMÉNYEK TARTALOM -->
    <div class="betslip-content" id="betslip-elozmeny">
        <div class="elozmeny-empty" id="elozmeny-empty">
            <i class="fas fa-history"></i>
            <p>Még nincs korábbi fogadás</p>
            <span style="font-size: 12px; color: #999;">Az első ticket itt jelenik meg</span>
        </div>

        <div class="elozmeny-items" id="elozmeny-items" style="display: none;">
            <!-- JS-ből jönnek az elemek -->
        </div>
    </div>
</div>