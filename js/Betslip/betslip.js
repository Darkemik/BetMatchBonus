document.addEventListener('DOMContentLoaded', function () {

    // ===== TAB VÁLTÁS =====
    const tabs = document.querySelectorAll('.betslip-tab');
    const contents = document.querySelectorAll('.betslip-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('betslip-' + target).classList.add('active');
        });
    });

    // ===== SZELVÉNY KEZELÉS =====
    let betslipItems = JSON.parse(localStorage.getItem('betslip') || '[]');
    let betHistory = JSON.parse(localStorage.getItem('betHistory') || '[]');

    function renderBetslip() {
        const itemsContainer = document.getElementById('betslip-items');
        const emptyState = document.querySelector('.betslip-empty');
        const footer = document.getElementById('betslip-footer');

        if (betslipItems.length === 0) {
            emptyState.style.display = 'flex';
            itemsContainer.style.display = 'none';
            footer.style.display = 'none';
            return;
        }

        emptyState.style.display = 'none';
        itemsContainer.style.display = 'flex';
        footer.style.display = 'block';

        itemsContainer.innerHTML = '';
        let totalOdds = 1;

        betslipItems.forEach((item, index) => {
            totalOdds *= item.odds;
            const el = document.createElement('div');
            el.classList.add('betslip-item');
            el.innerHTML = `
                <div class="betslip-item-header">
                    <span class="betslip-item-match">${item.homeTeam} vs ${item.awayTeam}</span>
                    <button class="betslip-item-remove" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="betslip-item-pick">${item.pick}</div>
                <div class="betslip-item-odds">${item.odds.toFixed(2)}</div>
            `;
            itemsContainer.appendChild(el);
        });

        document.querySelectorAll('.betslip-item-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.getAttribute('data-index'));
                betslipItems.splice(idx, 1);
                saveBetslip();
                renderBetslip();
                if (typeof window.refreshActiveOddsButtons === 'function') {
                    window.refreshActiveOddsButtons();
                }
            });
        });

        document.getElementById('betslip-count').textContent = betslipItems.length;
        document.getElementById('betslip-total-odds').textContent = totalOdds.toFixed(2);
        updatePotentialWin(totalOdds);
    }

    function updatePotentialWin(totalOdds) {
        const stakeInput = document.getElementById('stake-input');
        const stake = parseFloat(stakeInput.value) || 0;
        const potentialWin = stake * totalOdds;
        document.getElementById('betslip-potential-win').textContent =
            Math.round(potentialWin).toLocaleString('hu-HU') + ' Ft';
    }

    function saveBetslip() {
        localStorage.setItem('betslip', JSON.stringify(betslipItems));
    }

    const stakeInput = document.getElementById('stake-input');
    if (stakeInput) {
        stakeInput.addEventListener('input', () => {
            let totalOdds = 1;
            betslipItems.forEach(item => totalOdds *= item.odds);
            updatePotentialWin(totalOdds);
        });
    }

    const submitBtn = document.getElementById('betslip-submit');
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            if (betslipItems.length === 0) return;
            const stake = parseFloat(stakeInput.value) || 0;
            if (stake < 100) {
                alert('A minimum tét 100 Ft!');
                return;
            }
            let totalOdds = 1;
            betslipItems.forEach(item => totalOdds *= item.odds);
            const bet = {
                id: Date.now(),
                date: new Date().toLocaleString('hu-HU'),
                items: [...betslipItems],
                stake: stake,
                totalOdds: totalOdds,
                potentialWin: Math.round(stake * totalOdds),
                status: 'pending'
            };
            betHistory.unshift(bet);
            localStorage.setItem('betHistory', JSON.stringify(betHistory));
            betslipItems = [];
            saveBetslip();
            renderBetslip();
            renderNaplo();
            if (typeof window.refreshActiveOddsButtons === 'function') {
                window.refreshActiveOddsButtons();
            }
            alert('Fogadás sikeresen leadva!');
        });
    }

    function renderNaplo() {
        const naploItems = document.getElementById('naplo-items');
        const naploEmpty = document.getElementById('naplo-empty');
        if (betHistory.length === 0) {
            naploEmpty.style.display = 'flex';
            naploItems.style.display = 'none';
            return;
        }
        naploEmpty.style.display = 'none';
        naploItems.style.display = 'flex';
        naploItems.innerHTML = '';
        betHistory.forEach(bet => {
            const statusClass = bet.status;
            const statusText = bet.status === 'pending' ? 'Függőben' : bet.status === 'won' ? 'Nyert' : 'Vesztett';
            const matchNames = bet.items.map(i => `${i.homeTeam} vs ${i.awayTeam}`).join(', ');
            const el = document.createElement('div');
            el.classList.add('naplo-item', statusClass);
            el.innerHTML = `
                <div class="naplo-item-header">
                    <span class="naplo-item-date">${bet.date}</span>
                    <span class="naplo-item-status ${statusClass}">${statusText}</span>
                </div>
                <div class="naplo-item-match">${matchNames}</div>
                <div class="naplo-item-details">
                    <span>Tét: ${bet.stake.toLocaleString('hu-HU')} Ft</span>
                    <span>Odds: ${bet.totalOdds.toFixed(2)}</span>
                    <span>${bet.potentialWin.toLocaleString('hu-HU')} Ft</span>
                </div>
            `;
            naploItems.appendChild(el);
        });
    }

    // ===== GLOBÁLIS FÜGGVÉNYEK =====
    window.refreshBetslipUI = function() {
        betslipItems = JSON.parse(localStorage.getItem('betslip') || '[]');
        renderBetslip();
    };

    window.addToBetslip = function(homeTeam, awayTeam, pick, odds, market) {
        var exists = betslipItems.some(function(i) {
            return i.homeTeam === homeTeam && i.awayTeam === awayTeam && i.pick === pick && i.market === market;
        });
        if (exists) return;
        betslipItems.push({ homeTeam: homeTeam, awayTeam: awayTeam, pick: pick, odds: odds, market: market || '' });
        saveBetslip();
        renderBetslip();
    };

    window.removeFromBetslip = function(homeTeam, awayTeam, pick, market) {
        betslipItems = betslipItems.filter(function(item) {
            return !(item.homeTeam === homeTeam && item.awayTeam === awayTeam && item.pick === pick && item.market === market);
        });
        saveBetslip();
        renderBetslip();
    };

    renderBetslip();
    renderNaplo();
});
