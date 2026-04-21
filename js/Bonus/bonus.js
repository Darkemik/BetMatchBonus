document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById("bonusContainer");
    let isLoggedIn = false;
    const t = (key, fallback) => (typeof window.i18n === 'function' ? window.i18n(key, fallback) : (fallback || key));

    function getCurrentLang() {
        const stored = String(localStorage.getItem('lang') || '').toLowerCase();
        if (stored === 'en') return 'en';
        if (stored === 'hu') return 'hu';
        return (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en' : 'hu';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatBonusDescriptionHtml(description) {
        const raw = String(description || '').trim();
        if (!raw) {
            return `<p class="doboz-back-text" style="color:#f5f7ff;">Nincs további információ.</p>`;
        }

        const normalized = raw.replace(/\s+/g, ' ').trim();
        const stepRegex = /(\d+)\)\s*/g;
        const markers = [];
        let match;

        while ((match = stepRegex.exec(normalized)) !== null) {
            const markerIndex = match.index;
            if (markerIndex > 0 && !/\s/.test(normalized[markerIndex - 1])) {
                continue;
            }
            markers.push({
                index: markerIndex,
                contentStart: stepRegex.lastIndex
            });
        }

        if (markers.length >= 2) {
            const intro = normalized.slice(0, markers[0].index).trim();
            const items = [];

            for (let i = 0; i < markers.length; i += 1) {
                const start = markers[i].contentStart;
                const end = i + 1 < markers.length ? markers[i + 1].index : normalized.length;
                const itemText = normalized.slice(start, end).trim();
                if (itemText) items.push(itemText);
            }

            const introHtml = intro ? `<p class="doboz-back-text" style="color:#f5f7ff;">${escapeHtml(intro)}</p>` : '';
            const listHtml = items.length
                ? `<ol class="bonus-back-list" style="color:#f5f7ff;">${items.map(item => `<li style="color:#f5f7ff;">${escapeHtml(item)}</li>`).join('')}</ol>`
                : '';

            return `${introHtml}${listHtml}` || `<p class="doboz-back-text" style="color:#f5f7ff;">${escapeHtml(normalized)}</p>`;
        }

        const importantMatch = normalized.match(/\b(Fontos:|Important:)\s*/i);
        if (importantMatch) {
            const importantIndex = importantMatch.index;
            const mainPart = normalized.slice(0, importantIndex).trim();
            const importantPart = normalized.slice(importantIndex).trim();
            const mainHtml = mainPart ? `<p class="doboz-back-text" style="color:#f5f7ff;">${escapeHtml(mainPart)}</p>` : '';
            const importantHtml = `<p class="doboz-back-text bonus-back-important" style="color:#f5f7ff;">${escapeHtml(importantPart)}</p>`;
            return `${mainHtml}${importantHtml}`;
        }

        return `<p class="doboz-back-text" style="color:#f5f7ff;">${escapeHtml(normalized)}</p>`;
    }

    function localizeBonusTitle(title, lang) {
        const src = String(title || '').trim();
        let localized = src;
        const bonusToken = '(?:B[ÓO]NUSZ|BONUS)';

        if (lang === 'en' && src === 'Vesztes fogadás cashback (30% Free Bet)') {
            localized = 'Losing Bet Cashback (30% Free Bet)';
        }

        if (lang === 'en') {
            const dailyTopPattern = /^Napi\s+Top\s+Jutalom/i;
            if (dailyTopPattern.test(src)) {
                localized = src
                    .replace(/^Napi\s+Top\s+Jutalom/i, 'Daily Top Reward')
                    .replace(/Ft/gi, 'FT');
            }
        }

        if (lang === 'en') {
            const weekdayPattern = new RegExp('^' + bonusToken + '\\s+H[ÉE]TK[ÖO]ZNAP', 'i');
            if (weekdayPattern.test(src)) {
                localized = src
                    .replace(weekdayPattern, 'Weekday Bonus')
                    .replace(/Ft/gi, 'FT');
            }
        }

        if (lang === 'en') {
            const dartsPattern = new RegExp('^DARTS\\s+' + bonusToken + '\\s*\\(([^)]+)\\)$', 'i');
            const match = src.match(dartsPattern);
            if (match) {
                const details = match[1]
                    .replace(/fogadás/gi, 'bet')
                    .replace(/bónusz/gi, 'bonus')
                    .replace(/Ft/gi, 'FT');
                localized = `Darts Bonus (${details})`;
            }
        }

        if (lang === 'en') {
            const esportPattern = new RegExp('^ESPORT\\s+' + bonusToken + '\\s*\\(([^)]+)\\)$', 'i');
            const match = src.match(esportPattern);
            if (match) {
                const details = match[1]
                    .replace(/fogadás/gi, 'bet')
                    .replace(/bónusz/gi, 'bonus')
                    .replace(/Ft/gi, 'FT');
                localized = `Esport Bonus (${details})`;
            }
        }

        if (lang === 'en') {
            const nb1Pattern = /^NB1\s+DERBY\s+B[ÓO]NUSZ/i;
            if (nb1Pattern.test(src)) {
                localized = src.replace(nb1Pattern, 'NB1 Derby Bonus');
            }
        }

        const titleCaseRules = [
            {
                source: new RegExp('^' + bonusToken + '\\s+H[ÉE]TK[ÖO]ZNAP', 'i'),
                hu: 'Bónusz Hétköznap',
                en: 'Weekday Bonus'
            },
            {
                source: new RegExp('^DARTS\\s+' + bonusToken, 'i'),
                hu: 'Darts Bónusz',
                en: 'Darts Bonus'
            },
            {
                source: new RegExp('^NB1\\s+DERBY\\s+' + bonusToken, 'i'),
                hu: 'NB1 Derby Bónusz',
                en: 'NB1 Derby Bonus'
            },
            {
                source: new RegExp('^ESPORT\\s+' + bonusToken, 'i'),
                hu: 'Esport Bónusz',
                en: 'Esport Bonus'
            },
            {
                source: new RegExp('^SZ[ÜU]LET[ÉE]SNAPI\\s+' + bonusToken, 'i'),
                hu: 'Születésnapi Bónusz',
                en: 'Birthday Bonus'
            },
            {
                source: new RegExp('^BETMATCH\\s+SZ[ÜU]LET[ÉE]SNAPI\\s+' + bonusToken, 'i'),
                hu: 'BetMatchBonus Születésnapi Bónusz',
                en: 'BetMatchBonus Birthday Bonus'
            },
            {
                source: new RegExp('^H[ÉE]TV[ÉE]GI\\s+' + bonusToken, 'i'),
                hu: 'Hétvégi Bónusz',
                en: 'Weekend Bonus'
            },
            {
                source: /^ADMIN\s+FREE\s+BET/i,
                hu: 'Admin Ingyenes Fogadás',
                en: 'Admin Free Bet'
            },
            {
                source: new RegExp('^ADMIN\\s+' + bonusToken, 'i'),
                hu: 'Admin Bónusz',
                en: 'Admin Bonus'
            }
        ];

        for (const rule of titleCaseRules) {
            if (rule.source.test(src)) {
                const replacement = (lang === 'en' ? rule.en : rule.hu);
                return localized.replace(rule.source, replacement);
            }
        }

        return localized;
    }

    function localizeBonusDescription(description, lang, title) {
        const src = String(description || '').trim();
        const titleSrc = String(title || '').trim();
        const huText = 'Ha egy legalább 5.000 Ft-os fogadásod veszít (min. odds: 1.80), visszakapsz 30%-ot Free Bet formájában. Naponta egyszer aktiválódik automatikusan a vesztes szelvény lezárásakor. A kapott Free Bet-et bármilyen fogadásra felhasználhatod.';
        const enText = 'If a bet of at least 5,000 Ft loses (min. odds: 1.80), you get 30% back as a Free Bet. It is automatically activated once per day when the losing ticket is settled. You can use the received Free Bet on any bet.';
        const weekdayEnText = 'Weekday deposit bonus available every day from Monday to Friday. How can you activate it? 1) Deposit at least 3,000 FT to your account. 2) You receive 100% of your deposit as bonus, up to 5,000 FT. Example: 3,000 FT deposit = 3,000 FT bonus, 5,000 FT deposit = 5,000 FT bonus, 10,000 FT deposit = 5,000 FT bonus (max). 3) The received bonus must be wagered 3 times before it becomes withdrawable. So if you received a 5,000 FT bonus, you need to place bets worth 15,000 FT. 4) The maximum winnings are 5x the bonus amount (25,000 FT). Important: This bonus can only be activated on weekdays (Monday to Friday), from 08:00 in the morning; it is not available on weekends.';
        const dartsHuText = 'Darts rajongóknak szóló exkluzív bónusz! Hogyan szerezheted meg? 1) Tégy meg egy legalább 10.000 Ft értékű fogadást kizárólag darts mérkőzésekre. 2) A fogadásnak legalább 2 eseményt (2-es kötést) kell tartalmaznia, minimum 2.00-es össz odds-szal. 3) A kvalifikáló fogadás leadása után azonnal 5.000 Ft bónusz pénzt kapsz a bónusz egyenlegedre. 4) A kapott 5.000 Ft bónuszt 2-szeresen kell megforgatnod (10.000 Ft értékű fogadás), mielőtt kifizethetővé válik. 5) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Fontos: Az aktiválás után 48 órád van a bónusz felhasználására!';
        const dartsEnText = 'Exclusive bonus for darts fans! How can you claim it? 1) Place a bet of at least 10,000 FT exclusively on darts matches. 2) The bet must contain at least 2 events (2-leg combo) with a minimum total odds of 2.00. 3) Right after placing the qualifying bet, you receive 5,000 FT bonus money to your bonus balance. 4) The received 5,000 FT bonus must be wagered 2 times (10,000 FT total stake) before it becomes withdrawable. 5) The maximum winnable amount is 5x the bonus (25,000 FT). Important: After activation, you have 48 hours to use the bonus.';
        const nb1DerbyEnText = 'Exclusive live betting bonus for the biggest NB1 derby! How can you claim it? 1) Wait until the Ujpest FC vs Ferencvarosi TC match starts. 2) During the match (as a live bet), place a bet of at least 5,000 FT with minimum 2.00 odds. 3) After your bet is settled, you receive a 5,000 FT Free Bet as a reward. 4) With a Free Bet, the stake is not returned, only the net winnings are paid out. 5) The maximum winnable amount is 5x the bonus (25,000 FT). Important: Valid only for live betting; pre-match bets do not count.';
        const esportEnText = 'Bonus created for esports fans - CS2, League of Legends, Dota 2, and more esports matches! How does it work? 1) Place a bet of at least 5,000 FT on any esports match. 2) The bet must include at least 3 events (3-leg combo). 3) Each event must have minimum 1.30 odds, and the total odds must reach at least 3.00. 4) After your bet is settled, you receive 5,000 FT bonus money. 5) The received bonus must be wagered 3 times (15,000 FT total stake). 6) The maximum winnable amount is 5x the bonus (25,000 FT). Try esports betting and claim the extra bonus!';
        const birthdayEnText = 'Happy Birthday! Our gift to you: a 5,000 FT bonus on your special day. How can you claim it? 1) On your birthday (based on the date provided at registration), claim the bonus in your profile or through customer support. 2) After approval, 5,000 FT bonus money is credited to your bonus balance. 3) You can bet with this bonus on any sport and any match - no sport restriction. 4) There is no wagering requirement, so your winnings become withdrawable immediately. 5) The maximum winnable amount is 5x the bonus (25,000 FT). You can claim it once every year on your birthday!';
        const betmatchBirthdayEnText = 'BetMatchBonus birthday special promotion - available in limited quantity! How does it work? 1) The promotion activates every year on May 26. 2) On that day, the first 500 eligible claims receive a 5,000 FT bonus. 3) Claim the bonus in your profile or via customer support - first come, first served. 4) You can bet with this bonus on any sport and any match - no sport restriction. 5) There is no wagering requirement, so your winnings become withdrawable immediately. 6) The maximum winnable amount is 5x the bonus (25,000 FT). Important: Only 500 bonuses are available in total, so do not miss it!';
        const weekendEnText = 'Weekend extra Free Bet available on Saturday and Sunday! How can you activate it? 1) On Saturday or Sunday, deposit at least 5,000 FT. 2) In return, you get a 5,000 FT Free Bet. 3) The Free Bet must be used in a 2-leg combo (at least 2 events). 4) Total odds must be at least 2.00, and each event must have minimum 1.40 odds. 5) No wagering requirement - winnings are withdrawable immediately (the stake is not returned, only net winnings are paid). 6) The maximum winnable amount is 5x the bonus (25,000 FT). Tip: Use it on the biggest weekend matches!';
        const adminFreeBetEnText = 'Free Bet granted manually by an admin.';
        const adminBonusEnText = 'Bonus money manually granted by an admin to the user\'s bonus balance.';
        const dailyTopEnText = 'Automatic daily reward for the top depositor, top bettor, and top winner.';
        const isDartsBonusTitle = /^DARTS\s+(?:B[ÓO]NUSZ|BONUS)/i.test(titleSrc);
        const isNb1DerbyBonusTitle = /^NB1\s+DERBY\s+(?:B[ÓO]NUSZ|BONUS)/i.test(titleSrc);
        const isEsportBonusTitle = /^ESPORT\s+(?:B[ÓO]NUSZ|BONUS)/i.test(titleSrc);
        const isBirthdayBonusTitle = /^SZ[ÜU]LET[ÉE]SNAPI\s+(?:B[ÓO]NUSZ|BONUS)/i.test(titleSrc);
        const isBetmatchBirthdayBonusTitle = /^BETMATCH\s+SZ[ÜU]LET[ÉE]SNAPI\s+(?:B[ÓO]NUSZ|BONUS)/i.test(titleSrc);
        const isWeekendBonusTitle = /^H[ÉE]TV[ÉE]GI\s+(?:B[ÓO]NUSZ|BONUS)/i.test(titleSrc);
        const isAdminFreeBetTitle = /^ADMIN\s+FREE\s+BET/i.test(titleSrc);
        const isAdminBonusTitle = /^ADMIN\s+(?:B[ÓO]NUSZ|BONUS)/i.test(titleSrc);

        if (lang === 'en' && isDartsBonusTitle) {
            return dartsEnText;
        }

        if (lang !== 'en' && isDartsBonusTitle) {
            return dartsHuText;
        }

        if (lang === 'en' && isNb1DerbyBonusTitle) {
            return nb1DerbyEnText;
        }

        if (lang === 'en' && isEsportBonusTitle) {
            return esportEnText;
        }

        if (lang === 'en' && isBirthdayBonusTitle) {
            return birthdayEnText;
        }

        if (lang === 'en' && isBetmatchBirthdayBonusTitle) {
            return betmatchBirthdayEnText;
        }

        if (lang === 'en' && isWeekendBonusTitle) {
            return weekendEnText;
        }

        if (lang === 'en' && (isAdminFreeBetTitle || src.includes('Admin által adott free bet'))) {
            return adminFreeBetEnText;
        }

        if (lang === 'en' && (isAdminBonusTitle || src.includes('Admin által manuálisan adott bónusz pénz'))) {
            return adminBonusEnText;
        }

        if (lang === 'en' && (src === huText || src.includes('Ha egy legalább 5.000 Ft-os fogadásod veszít'))) {
            return enText;
        }
        if (lang === 'en' && (src.includes('Hétfőtől péntekig minden nap elérhető feltöltési bónusz') || src.includes('kizárólag hétköznapokon'))) {
            return weekdayEnText;
        }
        if (lang === 'en' && (src === 'Automatikus napi jutalom a top befizető, top fogadó és top nyertes számára.' || src.includes('top befizető, top fogadó és top nyertes'))) {
            return dailyTopEnText;
        }
        return src;
    }

    function normalizeBonusText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-zA-Z0-9 ]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toUpperCase();
    }

    function isBirthdayBonusTitle(title) {
        const normalized = normalizeBonusText(title);
        if (!normalized) return false;

        const isStandardBirthday = normalized.includes('SZULETESNAPI BONUSZ') || normalized.includes('BIRTHDAY BONUS');
        const isBetmatchBirthday = normalized.includes('BETMATCHBONUS') && (normalized.includes('SZULETESNAPI BONUSZ') || normalized.includes('BIRTHDAY BONUS'));
        return isStandardBirthday || isBetmatchBirthday;
    }

    function isBirthdayBonusFlag(value) {
        return value === true || value === 1 || value === '1';
    }

    function isCodeLessBirthdayBonus(bonus) {
        const hasNoCode = !String(bonus?.code || '').trim();
        if (!hasNoCode) return false;
        return isBirthdayBonusTitle(String(bonus?.title || ''));
    }

    async function claimBonusById(bonusId) {
        const formData = new FormData();
        formData.append('bonus_id', String(bonusId));

        const response = await fetch('../../backend/ApiRequest/claim_bonus.php', {
            method: 'POST',
            body: formData
        });

        return response.json();
    }

    // Ellenőrizzük, hogy be van-e jelentkezve
    try {
        const res = await fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' });
        const data = await res.json();
        isLoggedIn = data.loggedIn;
    } catch (err) {
        console.warn('Auth check failed, continuing as guest:', err);
    }

    // Betöltjük a bónuszokat a DB-ből a PHP végponton keresztül!
    try {
        const currentLang = getCurrentLang();
        const bonusUrl = "../../backend/ApiRequest/get_active_bonuses.php?lang=" + encodeURIComponent(currentLang) + "&v=" + Date.now();
        const bonusRes = await fetch(bonusUrl, { cache: 'no-store' });
        const bonuses = await bonusRes.json();

        if (bonuses.length === 0) {
            container.innerHTML = '<p style="color: white; text-align: center; width: 100%;">Jelenleg nincs elérhető bónusz.</p>';
            return;
        }

        bonuses.forEach((bonus) => {
            const box = document.createElement("div");
            box.classList.add("doboz");
            const isBirthdayBonusCard = isBirthdayBonusFlag(bonus?.isBirthdayBonus) || isBirthdayBonusTitle(bonus.title);
            const localizedTitle = localizeBonusTitle(bonus.title, currentLang);
            const localizedLongDescription = localizeBonusDescription(bonus.longDescription, currentLang, bonus.title);
            const formattedLongDescriptionHtml = formatBonusDescriptionHtml(localizedLongDescription);

            let buttonHTML = '';
            if (!isLoggedIn) {
                buttonHTML = `
                    <button class="doboz-gomb" data-bs-toggle="modal" data-bs-target="#loginModal">
                        🔐 ${t('bonusPage.loginRegister', 'BEJELENTKEZÉS / REGISZTRÁCIÓ')}
                    </button>
                    <button class="tobb-info-gomb">
                        ℹ️ ${t('bonusPage.moreInfo', 'Több információ')}
                    </button>
                `;
            } else {
                const copyBtn = bonus.code 
                    ? `<button class="doboz-gomb copy-code-btn" data-code="${bonus.code}" style="background: #1a1a2e; border: 1px solid #7c4dff;">
                        📋 ${t('bonusPage.copyCode', 'Kód másolása')}
                      </button>` 
                    : '';
                const claimIconHtml = isBirthdayBonusCard ? '<i class="fas fa-birthday-cake" aria-hidden="true"></i> ' : '➡️ ';
                buttonHTML = `
                    ${copyBtn}
                    <button class="doboz-gomb claim-btn">
                        ${claimIconHtml}${t('bonusPage.claim', 'Igénylés')}
                    </button>
                    <button class="tobb-info-gomb">
                        ℹ️ ${t('bonusPage.moreInfo', 'Több információ')}
                    </button>
                `;
            }

                        const sportBadge = '';

            box.innerHTML = `
                <div class="doboz-inner">
                    <div class="doboz-front">
                        <div class="doboz-kep-wrap">
                            <img src="${bonus.image}" class="doboz-kep" alt="${bonus.title}" style="object-fit: cover; border-radius: 12px 12px 0 0;">
                            ${bonus.amount && bonus.amount !== 'Több lépcsős' ? `<span class="bonus-amount-badge">${bonus.amount}</span>` : ""}
                        </div>
                        <div class="doboz-tartalom">
                            ${sportBadge}
                            <p class="doboz-cim">${localizedTitle}</p>
                            ${(!isLoggedIn && bonus.status) ? `<div class="bonus-meta-line bonus-meta-active">● ${bonus.status}</div>` : ''}
                            <div class="bonus-feltetel">${bonus.condition}</div>
                            <div class="doboz-gombok">
                                ${buttonHTML}
                            </div>
                        </div>
                    </div>
                    <div class="doboz-back">
                        <div class="doboz-back-header">
                            <p class="doboz-back-title">${localizedTitle}</p>
                        </div>
                        <div class="doboz-back-body">
                            ${formattedLongDescriptionHtml}
                        </div>
                        <div class="doboz-back-footer">
                            <button class="doboz-back-close">← ${t('bonusPage.back', 'Vissza')}</button>
                        </div>
                    </div>
                </div>
            `;

            // Event listenerek hozzáadása
            if (isLoggedIn) {
                // "Kód másolása" gomb
                const copyBtn = box.querySelector(".copy-code-btn");
                if (copyBtn) {
                    copyBtn.addEventListener("click", () => {
                        const code = copyBtn.getAttribute("data-code");
                        navigator.clipboard.writeText(code).then(() => {
                            const origText = copyBtn.innerHTML;
                            copyBtn.innerHTML = "✅ Kimásolva!";
                            setTimeout(() => { copyBtn.innerHTML = origText; }, 2000);
                        });
                    });
                }

                // "Igénylés" gomb: kód nélküli születésnapi bónuszoknál azonnali API aktiválás,
                // egyébként marad a Bónuszaim oldalra navigálás.
                const claimBtn = box.querySelector(".claim-btn");
                if (claimBtn) {
                    claimBtn.addEventListener("click", async () => {
                        if (!isCodeLessBirthdayBonus(bonus)) {
                            window.location.href = "../../frontend/UserProfile/my_bonuses.php";
                            return;
                        }

                        const originalHtml = claimBtn.innerHTML;
                        claimBtn.disabled = true;
                        claimBtn.innerHTML = `⏳ ${t('common.loading', 'Betöltés...')}`;

                        try {
                            const data = await claimBonusById(bonus.id);
                            if (data && data.success) {
                                claimBtn.innerHTML = `✅ ${t('userProfile.myBonuses.active', 'Aktív')}`;
                                setTimeout(() => window.location.reload(), 900);
                                return;
                            }

                            const errMsg = (data && data.message) ? data.message : t('bonusPage.claimError', 'A bónusz aktiválása sikertelen.');
                            alert(errMsg);
                        } catch (error) {
                            alert(t('userProfile.myBonuses.networkError', 'Hálózati hiba történt.'));
                        }

                        claimBtn.disabled = false;
                        claimBtn.innerHTML = originalHtml;
                    });
                }
            }

            // Mindkét esetben működik a kártya forgatása
            box.querySelector(".tobb-info-gomb").addEventListener("click", () => {
                box.classList.add("flipped");
            });

            box.querySelector(".doboz-back-close").addEventListener("click", () => {
                box.classList.remove("flipped");
            });

            container.appendChild(box);
        });
    } catch (err) {
        console.error("Hiba az adatbázis bónuszainak betöltésekor:", err);
        container.innerHTML = '<p style="color: #ff6b6b; text-align: center; width: 100%;">A bónuszok betöltése sikertelen. Frissítsd az oldalt, vagy próbáld újra később.</p>';
    }

    window.addEventListener('languageChanged', function () {
        window.location.reload();
    });
});
