<div class="betslip-wrapper">
    <div class="betslip-header">
        <h3 class="betslip-title">
            <i class="fas fa-ticket-alt"></i>
            <span id="betslip-label" data-i18n="betslip.ticket">Ticket</span>
            <span class="betslip-count" id="betslip-count">0</span>
        </h3>
    </div>

    <div class="betslip-tabs">
        <button class="betslip-tab active" data-tab="ticket">🎫 <span data-i18n="betslip.ticket">Ticket</span></button>
        <button class="betslip-tab" data-tab="elozmeny">📊 <span data-i18n="betslip.history">Előzmények</span></button>
    </div>

    <!-- TICKET TARTALOM -->
    <div class="betslip-content active" id="betslip-ticket">

        <!-- EGYES / KÖTÉS SUB-TABS -->
        <div class="betslip-type-tabs">
            <button class="betslip-type-tab active" id="tab-egyes" data-type="egyes" data-i18n="betslip.single">Egyes</button>
            <button class="betslip-type-tab" id="tab-kotes" data-type="kotes" data-i18n="betslip.combo">Kötés</button>
        </div>

        <div class="betslip-empty" id="betslip-empty">
            <i class="fas fa-inbox"></i>
            <p id="empty-message" data-i18n="betslip.emptyTitle">Nincs aktív fogadás</p>
            <span style="font-size: 12px; color: #999;" data-i18n="betslip.emptySubtitle">Válassz meccseket és odds-okat!</span>
        </div>

        <div class="betslip-bets" id="betslip-bets" style="display: none;">
            <!-- JS-ből jönnek a betslip-item elemek -->
        </div>

        <div class="betslip-summary" id="betslip-summary" style="display: none;">
            <div class="summary-row">
                <span id="summary-label" data-i18n="betslip.totalOdds">Összesített odds:</span>
                <span class="summary-value" id="total-odds">1.00</span>
            </div>
            <div class="summary-row">
                <span id="stake-label" data-i18n="betslip.stake">Tét (Ft):</span>
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
            <div class="summary-row" id="freebet-option-row" style="display:none;">
                <label style="display:flex; align-items:center; gap:8px; color:#9be7ff; font-weight:600; cursor:pointer;">
                    <input type="checkbox" id="use-freebet-toggle">
                    <span>Ingyenes fogadás:</span>
                    <span class="summary-value" id="freebet-amount-display">0 Ft</span>
                </label>
            </div>
            <div class="summary-row highlight">
                <span id="payout-label" data-i18n="betslip.potentialPayout">Lehetséges nyeremény:</span>
                <span class="summary-value highlight-value" id="potential-payout">100 Ft</span>
            </div>
        </div>

        <button 
            class="bet-button" 
            id="place-bet-btn" 
            style="display: none;"
            aria-label="Fogadás elhelyezése"
        >
            <i class="fas fa-check"></i> <span data-i18n="betslip.placeBet">Ticket leadása</span>
        </button>

        <button 
            class="clear-button" 
            id="clear-bets-btn" 
            style="display: none;"
            aria-label="Összes fogadás törlése"
        >
            <i class="fas fa-trash"></i> <span data-i18n="betslip.clearAll">Összes törlése</span>
        </button>
    </div>

    <!-- FOGADÁSI ELŐZMÉNYEK TARTALOM -->
    <div class="betslip-content" id="betslip-elozmeny">
        <div class="elozmeny-empty" id="elozmeny-empty">
            <i class="fas fa-history"></i>
            <p data-i18n="betslip.noHistory">Még nincs korábbi fogadás</p>
            <span style="font-size: 12px; color: #999;" data-i18n="betslip.noHistorySubtitle">Az első ticket itt jelenik meg</span>
        </div>

        <div class="elozmeny-items" id="elozmeny-items" style="display: none;">
            <!-- JS-ből jönnek az elemek -->
        </div>
    </div>
</div>