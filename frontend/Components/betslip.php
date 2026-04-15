<div class="betslip-wrapper">
    <div class="betslip-header">
        <h3 class="betslip-title">
            <i class="fas fa-ticket-alt"></i>
            <span id="betslip-label" data-i18n="betslip.ticket">Szelvény</span>
            <span class="betslip-count" id="betslip-count">0</span>
        </h3>
    </div>

    <div class="betslip-tabs">
        <button class="betslip-tab active" data-tab="ticket">🎫 <span data-i18n="betslip.ticket">Szelvény</span></button>
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
            <div class="summary-row betslip-balance-row" id="betslip-balance-row" style="display:none;font-size:0.8rem;color:#999;padding:2px 0;">
                <span>💰 Egyenleg:</span>
                <span id="betslip-balance-display" style="font-weight:600;">0 Ft</span>
            </div>
            <div class="summary-row" id="balance-type-row" style="display:none;">
                <div class="balance-type-toggle" style="width:100%;">
                    <select id="balance-type-select" style="width:100%;padding:10px 12px;border-radius:8px;border:2px solid #4caf50;background:#1a1a2e;color:#fff;font-size:0.85rem;font-weight:600;cursor:pointer;outline:none;transition:all .2s;">
                        <option value="real" style="color:#4caf50;">💰 Rendes egyenleg — 0 Ft</option>
                    </select>
                </div>
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
            <i class="fas fa-check"></i> <span data-i18n="betslip.placeBet">Szelvény leadása</span>
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
            <span style="font-size: 12px; color: #999;" data-i18n="betslip.noHistorySubtitle">Az első szelvény itt jelenik meg</span>
        </div>

        <div class="elozmeny-items" id="elozmeny-items" style="display: none;">
            <!-- JS-ből jönnek az elemek -->
        </div>

        <div class="elozmeny-pagination" id="elozmeny-pagination" style="display: none;">
            <button type="button" class="elozmeny-page-btn" id="elozmeny-prev-btn">◀ <span data-i18n="betslip.historyPrev">Előző</span></button>
            <span class="elozmeny-page-info" id="elozmeny-page-info">1 / 1</span>
            <button type="button" class="elozmeny-page-btn" id="elozmeny-next-btn"><span data-i18n="betslip.historyNext">Következő</span> ▶</button>
        </div>
    </div>
</div>
