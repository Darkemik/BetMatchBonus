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

    // Régi, matchId nélküli fogadások törlése (egyszeri tisztítás)
    if (!localStorage.getItem('betHistoryCleared_v2')) {
        localStorage.removeItem('betHistory');
        localStorage.setItem('betHistoryCleared_v2', '1');
    }

    let betHistory = JSON.parse(localStorage.getItem('betHistory') || '[]');
    let checkBetsTimer = null;

    // =========================================================================
    // ===== KÖTÉSTILTÁS (CONFLICT CHECKER) ====================================
    // =========================================================================

    /**
     * Piac és pick típus felismerés
     * Visszaad egy struktúrált objektumot ami alapján könnyű összehasonlítani
     */
    function parseBetType(market, pick) {
        var m = (market || '').toLowerCase().trim();
        var p = (pick || '').toLowerCase().trim();

        var result = {
            type: 'unknown',
            subtype: null,
            side: null,   // 'home', 'away', 'draw'
            line: null,    // szám (pl. 2.5 over/under-nél)
            scope: 'ft',   // 'ft' = full time, '1h' = 1. félidő, '2h' = 2. félidő
            raw: { market: market, pick: pick }
        };

        // --- Scope (félidő) felismerés ---
        if (m.indexOf('1st half') !== -1 || m.indexOf('first half') !== -1 ||
            m.indexOf('1. félidő') !== -1 || m.indexOf('1st period') !== -1 ||
            m.indexOf('1. félid') !== -1 || m.indexOf('half 1') !== -1 ||
            m.indexOf('1h ') !== -1 || m.match(/\b1st\b/) || m.indexOf('első félidő') !== -1) {
            result.scope = '1h';
        } else if (m.indexOf('2nd half') !== -1 || m.indexOf('second half') !== -1 ||
            m.indexOf('2. félidő') !== -1 || m.indexOf('2nd period') !== -1 ||
            m.indexOf('2. félid') !== -1 || m.indexOf('half 2') !== -1 ||
            m.indexOf('2h ') !== -1 || m.indexOf('második félidő') !== -1) {
            result.scope = '2h';
        }

        // --- 1X2 / Match Winner / Moneyline ---
        if (m.indexOf('1x2') !== -1 || m.indexOf('winner') !== -1 ||
            m.indexOf('győztes') !== -1 || m.indexOf('match result') !== -1 ||
            m.indexOf('full time result') !== -1 || m.indexOf('moneyline') !== -1 ||
            m.indexOf('match winner') !== -1 || m.indexOf('to win') !== -1 ||
            m.indexOf('result') !== -1) {
            result.type = '1x2';
            if (p === '1' || p === 'home') result.side = 'home';
            else if (p === '2' || p === 'away') result.side = 'away';
            else if (p === 'x' || p === 'draw' || p === 'döntetlen') result.side = 'draw';
            else result.side = p;
            return result;
        }

        // --- Double Chance ---
        if (m.indexOf('double chance') !== -1 || m.indexOf('dupla esély') !== -1 || m.indexOf('dupla') !== -1) {
            result.type = 'double_chance';
            if (p === '1x' || p === 'home or draw') result.side = '1x';
            else if (p === 'x2' || p === 'draw or away') result.side = 'x2';
            else if (p === '12' || p === 'home or away') result.side = '12';
            return result;
        }

        // --- Over/Under ---
        if (m.indexOf('over') !== -1 || m.indexOf('under') !== -1 ||
            m.indexOf('total') !== -1 || m.indexOf('össz') !== -1 ||
            m.indexOf('több') !== -1 || m.indexOf('kevesebb') !== -1 ||
            m.indexOf('goals') !== -1 || m.indexOf('gól') !== -1) {
            result.type = 'over_under';
            var lineMatch = m.match(/\((\d+\.?\d*)\)/);
            if (lineMatch) result.line = parseFloat(lineMatch[1]);
            // Ha nincs zárójelben, keressük a pickben is
            if (result.line === null) {
                var lineMatch2 = p.match(/(\d+\.?\d*)/);
                if (lineMatch2) result.line = parseFloat(lineMatch2[1]);
            }
            if (p === 'over' || p.indexOf('over') !== -1 || p.indexOf('több') !== -1 || p.indexOf('+') !== -1) result.side = 'over';
            else if (p === 'under' || p.indexOf('under') !== -1 || p.indexOf('kevesebb') !== -1 || p.indexOf('-') !== -1) result.side = 'under';
            return result;
        }

        // --- Both Teams To Score (BTTS) ---
        if (m.indexOf('both teams') !== -1 || m.indexOf('btts') !== -1 ||
            m.indexOf('mindkét csapat') !== -1 || m.indexOf('mindkét') !== -1 ||
            m.indexOf('gg') !== -1) {
            result.type = 'btts';
            if (p === 'yes' || p === 'igen' || p === 'gg') result.side = 'yes';
            else if (p === 'no' || p === 'nem' || p === 'ng') result.side = 'no';
            return result;
        }

        // --- Correct Score / Pontos eredmény ---
        if (m.indexOf('correct score') !== -1 || m.indexOf('pontos eredmény') !== -1 ||
            m.indexOf('exact score') !== -1 || m.indexOf('pontos') !== -1) {
            result.type = 'correct_score';
            var scoreMatch = p.match(/(\d+)\s*[:\-]\s*(\d+)/);
            if (scoreMatch) {
                result.homeGoals = parseInt(scoreMatch[1]);
                result.awayGoals = parseInt(scoreMatch[2]);
            }
            return result;
        }

        // --- Handicap ---
        if (m.indexOf('handicap') !== -1 || m.indexOf('spread') !== -1) {
            result.type = 'handicap';
            var hcMatch = m.match(/\(([+-]?\d+\.?\d*)\)/);
            if (hcMatch) result.line = parseFloat(hcMatch[1]);
            if (p === '1' || p.indexOf('home') !== -1) result.side = 'home';
            else if (p === '2' || p.indexOf('away') !== -1) result.side = 'away';
            else result.side = p;
            return result;
        }

        // --- Odd/Even ---
        if (m.indexOf('odd') !== -1 || m.indexOf('even') !== -1 ||
            m.indexOf('páros') !== -1 || m.indexOf('páratlan') !== -1) {
            result.type = 'odd_even';
            if (p === 'odd' || p === 'páratlan') result.side = 'odd';
            else if (p === 'even' || p === 'páros') result.side = 'even';
            return result;
        }

        return result;
    }

    /**
     * Pontos eredményből kiderítjük melyik oldal nyer
     */
    function getCorrectScoreWinner(homeGoals, awayGoals) {
        if (homeGoals > awayGoals) return 'home';
        if (awayGoals > homeGoals) return 'away';
        return 'draw';
    }

    /**
     * Scope-ok kompatibilisek-e (félidő vs teljes meccs)
     * ft vs 1h / 2h → cross-scope (speciális szabályok)
     * 1h vs 2h → teljesen független
     * ugyanaz a scope → normál szabályok
     */
    function isSameScope(a, b) {
        return a === b;
    }

    function isCrossScope(a, b) {
        // ft vs 1h, ft vs 2h, 1h vs ft, 2h vs ft
        return (a === 'ft' && (b === '1h' || b === '2h')) ||
               (b === 'ft' && (a === '1h' || a === '2h'));
    }

    function isDifferentHalf(a, b) {
        return (a === '1h' && b === '2h') || (a === '2h' && b === '1h');
    }

    /**
     * Ellenőrzi hogy két fogadás ütközik-e (UGYANAZON a meccsen belül!)
     */
    function checkConflict(existingBet, newBet, existingOdds, newOdds) {
        var a = parseBetType(existingBet.market, existingBet.pick);
        var b = parseBetType(newBet.market, newBet.pick);

        // Két különböző félidő (1h vs 2h) → teljesen független, nincs ütközés
        if (isDifferentHalf(a.scope, b.scope)) {
            return { conflict: false };
        }

        var sameScope = isSameScope(a.scope, b.scope);
        var crossScope = isCrossScope(a.scope, b.scope);

        // =====================================================================
        // AZONOS SCOPE ÜTKÖZÉSEK (pl. ft vs ft, 1h vs 1h)
        // =====================================================================

        // ---------- 1X2 vs 1X2 ----------
        if (a.type === '1x2' && b.type === '1x2' && sameScope) {
            if (a.side && b.side && a.side !== b.side) {
                return { conflict: true, type: 'block', message: 'Nem teheted egyszerre a hazai és vendég győzelmet / döntetlent ugyanarra a meccsre' + (a.scope !== 'ft' ? ' (azonos félidő)' : '') + '!' };
            }
        }

        // ---------- 1X2 vs Double Chance (azonos scope) ----------
        if (a.type === '1x2' && b.type === 'double_chance' && sameScope) {
            if (a.side === 'home' && b.side === 'x2') return { conflict: true, type: 'block', message: 'Hazai győzelem és "Döntetlen vagy Vendég" kizárja egymást!' };
            if (a.side === 'away' && b.side === '1x') return { conflict: true, type: 'block', message: 'Vendég győzelem és "Hazai vagy Döntetlen" kizárja egymást!' };
            if (a.side === 'draw' && b.side === '12') return { conflict: true, type: 'block', message: 'Döntetlen és "Hazai vagy Vendég" kizárja egymást!' };
            if (a.side === 'home' && b.side === '1x') return { conflict: true, type: 'redundant', message: 'Hazai győzelem már benne van a "Hazai vagy Döntetlen"-ben. Csak a nagyobb odds marad.' };
            if (a.side === 'away' && b.side === 'x2') return { conflict: true, type: 'redundant', message: 'Vendég győzelem már benne van a "Döntetlen vagy Vendég"-ben. Csak a nagyobb odds marad.' };
            if (a.side === 'draw' && (b.side === '1x' || b.side === 'x2')) return { conflict: true, type: 'redundant', message: 'Döntetlen már benne van ebben a dupla esélyben. Csak a nagyobb odds marad.' };
        }
        if (a.type === 'double_chance' && b.type === '1x2' && sameScope) {
            var rev = checkConflict(newBet, existingBet, newOdds, existingOdds);
            if (rev.conflict) return rev;
        }

        // ---------- 1X2 vs Pontos eredmény (azonos scope) ----------
        if (a.type === '1x2' && b.type === 'correct_score' && sameScope) {
            if (b.homeGoals !== undefined && b.awayGoals !== undefined) {
                var scoreWinner = getCorrectScoreWinner(b.homeGoals, b.awayGoals);
                if (a.side === 'home' && scoreWinner !== 'home') return { conflict: true, type: 'block', message: 'Hazai győzelem nem kompatibilis ezzel a pontos eredménnyel (' + b.raw.pick + ')!' };
                if (a.side === 'away' && scoreWinner !== 'away') return { conflict: true, type: 'block', message: 'Vendég győzelem nem kompatibilis ezzel a pontos eredménnyel (' + b.raw.pick + ')!' };
                if (a.side === 'draw' && scoreWinner !== 'draw') return { conflict: true, type: 'block', message: 'Döntetlen nem kompatibilis ezzel a pontos eredménnyel (' + b.raw.pick + ')!' };
            }
        }
        if (a.type === 'correct_score' && b.type === '1x2' && sameScope) {
            if (a.homeGoals !== undefined && a.awayGoals !== undefined) {
                var scoreWinner2 = getCorrectScoreWinner(a.homeGoals, a.awayGoals);
                if (b.side === 'home' && scoreWinner2 !== 'home') return { conflict: true, type: 'block', message: 'Ez a pontos eredmény (' + a.raw.pick + ') nem kompatibilis a hazai győzelemmel!' };
                if (b.side === 'away' && scoreWinner2 !== 'away') return { conflict: true, type: 'block', message: 'Ez a pontos eredmény (' + a.raw.pick + ') nem kompatibilis a vendég győzelemmel!' };
                if (b.side === 'draw' && scoreWinner2 !== 'draw') return { conflict: true, type: 'block', message: 'Ez a pontos eredmény (' + a.raw.pick + ') nem kompatibilis a döntetlennel!' };
            }
        }

        // ---------- Over vs Under (azonos scope) ----------
        if (a.type === 'over_under' && b.type === 'over_under' && sameScope) {
            if (a.line !== null && b.line !== null && a.line === b.line) {
                if ((a.side === 'over' && b.side === 'under') || (a.side === 'under' && b.side === 'over')) {
                    return { conflict: true, type: 'block', message: 'Over és Under ' + a.line + ' kizárja egymást!' };
                }
            }
            if (a.side === 'over' && b.side === 'over' && a.line !== null && b.line !== null && a.line !== b.line) {
                return { conflict: true, type: 'redundant', message: 'Over ' + a.line + ' és Over ' + b.line + ' redundáns. Csak a nagyobb odds marad.' };
            }
            if (a.side === 'under' && b.side === 'under' && a.line !== null && b.line !== null && a.line !== b.line) {
                return { conflict: true, type: 'redundant', message: 'Under ' + a.line + ' és Under ' + b.line + ' redundáns. Csak a nagyobb odds marad.' };
            }
            if (a.side === 'over' && b.side === 'under' && a.line !== null && b.line !== null && a.line >= b.line) {
                return { conflict: true, type: 'block', message: 'Over ' + a.line + ' és Under ' + b.line + ' kizárja egymást!' };
            }
            if (a.side === 'under' && b.side === 'over' && a.line !== null && b.line !== null && b.line >= a.line) {
                return { conflict: true, type: 'block', message: 'Under ' + a.line + ' és Over ' + b.line + ' kizárja egymást!' };
            }
        }

        // =====================================================================
        // CROSS-SCOPE ÜTKÖZÉSEK (félidő vs teljes meccs)
        // =====================================================================

        if (a.type === 'over_under' && b.type === 'over_under' && crossScope) {
            var halfBet = (a.scope === '1h' || a.scope === '2h') ? a : b;
            var ftBet = (a.scope === 'ft') ? a : b;

            // Félidő Under X + Teljes Over Y ahol Y > X*2 → nagyon valószínűtlen / kizáró
            // Pl: 1H Under 0.5 + FT Over 2.5 → 0 gól az 1. félidőben, de 3+ összesen? Lehetetlen nem, de nagyon szűk
            // Félidő Under X + Teljes Over Y ahol Y >= X → kizáró ha félidő under vonal túl alacsony
            if (halfBet.side === 'under' && ftBet.side === 'over') {
                if (halfBet.line !== null && ftBet.line !== null) {
                    // Ha a félidős under vonal >= teljes meccs over vonal → kizáró
                    // Pl: 1H Under 2.5 + FT Over 2.5 → lehetséges (2. félidőben rúgnak)
                    // De: 1H Under 0.5 + FT Over 2.5 → extrém
                    // Szabály: ha félidős under < (teljes over - félidős under) nem igaz → ellentmondás
                    // Egyszerűbb: ha a félidős under vonal * 2 <= teljes over vonal → kizáró
                    if (halfBet.line * 2 <= ftBet.line) {
                        var scopeName = halfBet.scope === '1h' ? '1. félidő' : '2. félidő';
                        return { conflict: true, type: 'block', message: scopeName + ' Under ' + halfBet.line + ' és Összesen Over ' + ftBet.line + ' kizárja egymást! Ha a félidőben max ' + Math.floor(halfBet.line) + ' gól esik, nem érheti el az összgólszám a ' + ftBet.line + '-t.' };
                    }
                }
            }

            // Félidő Over X + Teljes Under Y ahol X >= Y → kizáró
            // Pl: 1H Over 2.5 + FT Under 2.5 → ha 1. félidőben 3+ gól, összesen nem lehet Under 2.5
            if (halfBet.side === 'over' && ftBet.side === 'under') {
                if (halfBet.line !== null && ftBet.line !== null && halfBet.line >= ftBet.line) {
                    var scopeName2 = halfBet.scope === '1h' ? '1. félidő' : '2. félidő';
                    return { conflict: true, type: 'block', message: scopeName2 + ' Over ' + halfBet.line + ' és Összesen Under ' + ftBet.line + ' kizárja egymást!' };
                }
            }

            // Félidő Over X + Teljes Over Y ahol X >= Y → redundáns (félidő magában foglalja)
            if (halfBet.side === 'over' && ftBet.side === 'over') {
                if (halfBet.line !== null && ftBet.line !== null && halfBet.line >= ftBet.line) {
                    var scopeName3 = halfBet.scope === '1h' ? '1. félidő' : '2. félidő';
                    return { conflict: true, type: 'redundant', message: scopeName3 + ' Over ' + halfBet.line + ' magában foglalja az Összesen Over ' + ftBet.line + '-t. Csak a nagyobb odds marad.' };
                }
            }

            // Félidő Under X + Teljes Under Y ahol Y <= X → redundáns
            if (halfBet.side === 'under' && ftBet.side === 'under') {
                if (halfBet.line !== null && ftBet.line !== null && ftBet.line <= halfBet.line) {
                    var scopeName4 = halfBet.scope === '1h' ? '1. félidő' : '2. félidő';
                    return { conflict: true, type: 'redundant', message: 'Összesen Under ' + ftBet.line + ' magában foglalja a ' + scopeName4 + ' Under ' + halfBet.line + '-t. Csak a nagyobb odds marad.' };
                }
            }
        }

        // ---------- Cross-scope: félidő 1X2 vs teljes meccs 1X2 ----------
        // Pl: 1H döntetlen + FT hazai nyer → ez OK (félidőben döntetlen, végül hazai nyer)
        // De: 1H vendég nyer + FT hazai nyer → redundáns fogadás (szűkít, de nem lehetetlen)
        // Nem blokkoljuk, mert meccs közben fordulhat

        // =====================================================================
        // AZONOS SCOPE ÜTKÖZÉSEK (FOLYTATÁS)
        // =====================================================================

        // ---------- BTTS vs BTTS (azonos scope) ----------
        if (a.type === 'btts' && b.type === 'btts' && sameScope) {
            if (a.side !== b.side) {
                return { conflict: true, type: 'block', message: '"Mindkét csapat szerez gólt" Igen és Nem kizárja egymást!' };
            }
        }

        // ---------- BTTS Igen + Over/Under redundancia (azonos scope) ----------
        if (a.type === 'btts' && a.side === 'yes' && b.type === 'over_under' && b.side === 'over' && sameScope) {
            if (b.line !== null && b.line <= 1.5) {
                return { conflict: true, type: 'redundant', message: 'BTTS Igen magában foglalja az Over ' + b.line + '-öt. Csak a nagyobb odds marad.' };
            }
        }
        if (b.type === 'btts' && b.side === 'yes' && a.type === 'over_under' && a.side === 'over' && sameScope) {
            if (a.line !== null && a.line <= 1.5) {
                return { conflict: true, type: 'redundant', message: 'BTTS Igen magában foglalja az Over ' + a.line + '-öt. Csak a nagyobb odds marad.' };
            }
        }

        // ---------- BTTS + Under ütközés (azonos scope) ----------
        if (a.type === 'btts' && a.side === 'yes' && b.type === 'over_under' && b.side === 'under' && sameScope) {
            if (b.line !== null && b.line <= 1.5) {
                return { conflict: true, type: 'block', message: 'BTTS Igen és Under ' + b.line + ' kizárja egymást! Ha mindkét csapat rúg, legalább 2 gól esik.' };
            }
        }
        if (b.type === 'btts' && b.side === 'yes' && a.type === 'over_under' && a.side === 'under' && sameScope) {
            if (a.line !== null && a.line <= 1.5) {
                return { conflict: true, type: 'block', message: 'BTTS Igen és Under ' + a.line + ' kizárja egymást! Ha mindkét csapat rúg, legalább 2 gól esik.' };
            }
        }

        // ---------- BTTS Nem + Pontos eredmény (azonos scope) ----------
        if (a.type === 'btts' && a.side === 'no' && b.type === 'correct_score' && sameScope) {
            if (b.homeGoals > 0 && b.awayGoals > 0) {
                return { conflict: true, type: 'block', message: 'BTTS Nem nem kompatibilis ezzel a pontos eredménnyel (' + b.raw.pick + ') ahol mindkét csapat szerez gólt!' };
            }
        }
        if (b.type === 'btts' && b.side === 'no' && a.type === 'correct_score' && sameScope) {
            if (a.homeGoals > 0 && a.awayGoals > 0) {
                return { conflict: true, type: 'block', message: 'Ez a pontos eredmény (' + a.raw.pick + ') nem kompatibilis a BTTS Nem-mel!' };
            }
        }

        // ---------- BTTS Igen + Pontos eredmény ahol valaki nem rúg (azonos scope) ----------
        if (a.type === 'btts' && a.side === 'yes' && b.type === 'correct_score' && sameScope) {
            if (b.homeGoals === 0 || b.awayGoals === 0) {
                return { conflict: true, type: 'block', message: 'BTTS Igen nem kompatibilis ezzel a pontos eredménnyel (' + b.raw.pick + ')!' };
            }
        }
        if (b.type === 'btts' && b.side === 'yes' && a.type === 'correct_score' && sameScope) {
            if (a.homeGoals === 0 || a.awayGoals === 0) {
                return { conflict: true, type: 'block', message: 'Ez a pontos eredmény (' + a.raw.pick + ') nem kompatibilis a BTTS Igen-nel!' };
            }
        }

        // ---------- Pontos eredmény vs Over/Under (azonos scope) ----------
        if (a.type === 'correct_score' && b.type === 'over_under' && sameScope) {
            if (a.homeGoals !== undefined && a.awayGoals !== undefined && b.line !== null) {
                var totalGoals = a.homeGoals + a.awayGoals;
                if (b.side === 'over' && totalGoals <= b.line) {
                    return { conflict: true, type: 'block', message: 'Pontos eredmény ' + a.raw.pick + ' (' + totalGoals + ' gól) nem kompatibilis Over ' + b.line + '-tel!' };
                }
                if (b.side === 'under' && totalGoals >= b.line) {
                    return { conflict: true, type: 'block', message: 'Pontos eredmény ' + a.raw.pick + ' (' + totalGoals + ' gól) nem kompatibilis Under ' + b.line + '-tel!' };
                }
            }
        }
        if (b.type === 'correct_score' && a.type === 'over_under' && sameScope) {
            if (b.homeGoals !== undefined && b.awayGoals !== undefined && a.line !== null) {
                var totalGoals2 = b.homeGoals + b.awayGoals;
                if (a.side === 'over' && totalGoals2 <= a.line) {
                    return { conflict: true, type: 'block', message: 'Over ' + a.line + ' nem kompatibilis a pontos eredménnyel ' + b.raw.pick + ' (' + totalGoals2 + ' gól)!' };
                }
                if (a.side === 'under' && totalGoals2 >= a.line) {
                    return { conflict: true, type: 'block', message: 'Under ' + a.line + ' nem kompatibilis a pontos eredménnyel ' + b.raw.pick + ' (' + totalGoals2 + ' gól)!' };
                }
            }
        }

        // ---------- Odd/Even vs Odd/Even (azonos scope) ----------
        if (a.type === 'odd_even' && b.type === 'odd_even' && sameScope) {
            if (a.side !== b.side) {
                return { conflict: true, type: 'block', message: 'Páros és Páratlan kizárja egymást!' };
            }
        }

        // ---------- Double Chance vs Double Chance (azonos scope) ----------
        if (a.type === 'double_chance' && b.type === 'double_chance' && sameScope) {
            if (a.side !== b.side) {
                return { conflict: true, type: 'redundant', message: 'Két különböző dupla esély redundáns. Csak a nagyobb odds marad.' };
            }
        }

        // ---------- Pontos eredmény vs Pontos eredmény (azonos scope) ----------
        if (a.type === 'correct_score' && b.type === 'correct_score' && sameScope) {
            if (a.homeGoals !== b.homeGoals || a.awayGoals !== b.awayGoals) {
                return { conflict: true, type: 'block', message: 'Két különböző pontos eredmény kizárja egymást!' };
            }
        }

        // =====================================================================
        // CROSS-SCOPE: BTTS félidő vs teljes meccs
        // =====================================================================
        if (a.type === 'btts' && b.type === 'btts' && crossScope) {
            // FT BTTS Nem + 1H BTTS Igen → lehetséges (1. félidőben mindkettő rúg, 2.-ban nem)
            // FT BTTS Igen + 1H BTTS Nem → lehetséges (2. félidőben rúg az is aki az 1.-ben nem)
            // Nem blokkoljuk ezeket
        }

        // Cross-scope: félidő BTTS Igen + teljes Under
        if (crossScope) {
            var halfBtts = null, ftOU = null;
            if (a.type === 'btts' && (a.scope === '1h' || a.scope === '2h') && b.type === 'over_under' && b.scope === 'ft') {
                halfBtts = a; ftOU = b;
            } else if (b.type === 'btts' && (b.scope === '1h' || b.scope === '2h') && a.type === 'over_under' && a.scope === 'ft') {
                halfBtts = b; ftOU = a;
            }
            if (halfBtts && ftOU) {
                if (halfBtts.side === 'yes' && ftOU.side === 'under' && ftOU.line !== null && ftOU.line <= 1.5) {
                    var scopeName5 = halfBtts.scope === '1h' ? '1. félidő' : '2. félidő';
                    return { conflict: true, type: 'block', message: scopeName5 + ' BTTS Igen és Összesen Under ' + ftOU.line + ' kizárja egymást!' };
                }
            }
        }

        return { conflict: false };
    }

    /**
     * Ellenőrzi az új fogadást az összes meglévővel (UGYANAZON a meccsen!)
     * Visszatér: { allowed: true/false, message: '...', removeIndices: [...] }
     */
    function validateNewBet(newItem) {
        var issues = [];
        var removeIndices = [];

        for (var i = 0; i < betslipItems.length; i++) {
            var existing = betslipItems[i];

            // Csak UGYANAZON a meccsen belül vizsgáljuk
            if (existing.homeTeam !== newItem.homeTeam || existing.awayTeam !== newItem.awayTeam) {
                continue;
            }

            // Ugyanaz a market + pick → már rajta van (ezt külön kezeljük)
            if (existing.market === newItem.market && existing.pick === newItem.pick) {
                continue;
            }

            var result = checkConflict(existing, newItem, existing.odds, newItem.odds);

            if (result.conflict) {
                if (result.type === 'block') {
                    return { allowed: false, message: result.message, removeIndices: [] };
                }
                if (result.type === 'redundant') {
                    // A kisebb odds-ú kikerül, a nagyobb marad
                    if (existing.odds >= newItem.odds) {
                        // A meglévő marad, az újat nem adjuk hozzá
                        return {
                            allowed: false,
                            message: result.message + '\n\nA meglévő fogadás (' + existing.pick + ' @ ' + existing.odds.toFixed(2) + ') magasabb oddsú, ezért az marad.',
                            removeIndices: []
                        };
                    } else {
                        // Az újat adjuk hozzá, a régit töröljük
                        removeIndices.push(i);
                        issues.push(result.message);
                    }
                }
            }
        }

        return {
            allowed: true,
            message: issues.length > 0 ? issues.join('\n') : '',
            removeIndices: removeIndices
        };
    }

    // =========================================================================
    // ===== BETSLIP RENDERELÉS ================================================
    // =========================================================================

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
                <div class="betslip-item-market">${item.market || ''}</div>
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
                showBetConfirmModal(null, null, null, null, 'A minimum tét 100 Ft!');
                return;
            }
            let totalOdds = 1;
            betslipItems.forEach(item => totalOdds *= item.odds);

            var betItems = betslipItems.map(function(item) {
                return {
                    homeTeam: item.homeTeam,
                    awayTeam: item.awayTeam,
                    pick: item.pick,
                    odds: item.odds,
                    market: item.market || '',
                    matchId: item.matchId || 0,
                    status: 'pending'
                };
            });

            const bet = {
                id: Date.now(),
                date: new Date().toLocaleString('hu-HU'),
                items: betItems,
                stake: stake,
                totalOdds: totalOdds,
                potentialWin: Math.round(stake * totalOdds),
                status: 'pending'
            };

            showBetConfirmModal(betItems, stake, totalOdds, Math.round(stake * totalOdds));

            betHistory.unshift(bet);
            localStorage.setItem('betHistory', JSON.stringify(betHistory));
            betslipItems = [];
            saveBetslip();
            renderBetslip();
            renderNaplo();
            if (typeof window.refreshActiveOddsButtons === 'function') {
                window.refreshActiveOddsButtons();
            }
        });
    }

    // ===== FOGADÁS VISSZAIGAZOLÁS MODAL =====
    function showBetConfirmModal(items, stake, totalOdds, potentialWin, errorMsg) {
        var overlay = document.getElementById('bet-confirm-overlay');
        if (!overlay) return;

        var headerEl = overlay.querySelector('.bet-confirm-header h3');
        var iconEl = overlay.querySelector('.bet-confirm-icon');
        var itemsEl = document.getElementById('bet-confirm-items');
        var stakeEl = document.getElementById('bet-confirm-stake');
        var oddsEl = document.getElementById('bet-confirm-odds');
        var winEl = document.getElementById('bet-confirm-win');
        var summaryEl = overlay.querySelector('.bet-confirm-summary');

        if (errorMsg) {
            iconEl.textContent = '⚠️';
            headerEl.textContent = errorMsg;
            itemsEl.innerHTML = '';
            summaryEl.style.display = 'none';
        } else {
            iconEl.textContent = '✅';
            headerEl.textContent = 'Fogadás sikeresen leadva!';
            summaryEl.style.display = 'block';

            var html = '';
            items.forEach(function(item) {
                html += '<div class="bet-confirm-item">' +
                    '<div class="bet-confirm-match">' + item.homeTeam + ' vs ' + item.awayTeam + '</div>' +
                    '<div class="bet-confirm-pick">' + (item.market || '') + ' → <strong>' + item.pick + '</strong> @ ' + item.odds.toFixed(2) + '</div>' +
                '</div>';
            });
            itemsEl.innerHTML = html;

            stakeEl.textContent = stake.toLocaleString('hu-HU') + ' Ft';
            oddsEl.textContent = totalOdds.toFixed(2);
            winEl.textContent = potentialWin.toLocaleString('hu-HU') + ' Ft';
        }

        overlay.classList.add('active');
    }

    // ===== KÖTÉSTILTÁS FIGYELMEZTETÉS MODAL =====
    function showConflictModal(message, isRedundant) {
        var overlay = document.getElementById('bet-confirm-overlay');
        if (!overlay) return;

        var headerEl = overlay.querySelector('.bet-confirm-header h3');
        var iconEl = overlay.querySelector('.bet-confirm-icon');
        var itemsEl = document.getElementById('bet-confirm-items');
        var summaryEl = overlay.querySelector('.bet-confirm-summary');

        iconEl.textContent = isRedundant ? '🔄' : '🚫';
        headerEl.textContent = isRedundant ? 'Redundáns fogadás' : 'Kötéstiltás!';
        summaryEl.style.display = 'none';

        var lines = message.split('\n').filter(function(l) { return l.trim(); });
        var html = '<div class="bet-conflict-message">';
        lines.forEach(function(line) {
            html += '<p>' + line + '</p>';
        });
        html += '</div>';
        itemsEl.innerHTML = html;

        overlay.classList.add('active');
    }

    function closeBetConfirmModal() {
        var overlay = document.getElementById('bet-confirm-overlay');
        if (overlay) overlay.classList.remove('active');
    }

    var confirmClose = document.getElementById('bet-confirm-close');
    var confirmOk = document.getElementById('bet-confirm-ok');
    var confirmOverlay = document.getElementById('bet-confirm-overlay');

    if (confirmClose) confirmClose.addEventListener('click', closeBetConfirmModal);
    if (confirmOk) confirmOk.addEventListener('click', closeBetConfirmModal);
    if (confirmOverlay) {
        confirmOverlay.addEventListener('click', function(e) {
            if (e.target === confirmOverlay) closeBetConfirmModal();
        });
    }

    // ===== NAPLÓ RENDERELÉS =====
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
        betHistory.forEach(function(bet) {
            var statusClass = bet.status;
            var statusText = 'Függőben';
            var statusIcon = '⏳';
            if (bet.status === 'won') {
                statusText = 'Nyertes';
                statusIcon = '✅';
            } else if (bet.status === 'lost') {
                statusText = 'Vesztes';
                statusIcon = '❌';
            }

            var itemsHtml = '';
            if (bet.items && bet.items.length > 0) {
                bet.items.forEach(function(item) {
                    var itemStatusClass = item.status || 'pending';
                    var itemStatusIcon = '⏳';
                    if (item.status === 'won') itemStatusIcon = '✅';
                    else if (item.status === 'lost') itemStatusIcon = '❌';

                    var scoreHtml = item.finalScore ? ' <span class="naplo-item-score">(' + item.finalScore + ')</span>' : '';

                    itemsHtml += '<div class="naplo-bet-item ' + itemStatusClass + '">' +
                        '<span class="naplo-bet-icon">' + itemStatusIcon + '</span>' +
                        '<div class="naplo-bet-details">' +
                            '<span class="naplo-bet-match">' + item.homeTeam + ' vs ' + item.awayTeam + scoreHtml + '</span>' +
                            '<span class="naplo-bet-pick">' + (item.market || '') + ' → <strong>' + item.pick + '</strong> @ ' + item.odds.toFixed(2) + '</span>' +
                        '</div>' +
                    '</div>';
                });
            }

            var winHtml = '';
            if (bet.status === 'won') {
                winHtml = '<div class="naplo-item-win"><i class="fas fa-coins"></i> Nyeremény: ' + bet.potentialWin.toLocaleString('hu-HU') + ' Ft</div>';
            }

            var el = document.createElement('div');
            el.classList.add('naplo-item', statusClass);
            el.innerHTML =
                '<div class="naplo-item-header">' +
                    '<span class="naplo-item-date">' + bet.date + '</span>' +
                    '<span class="naplo-item-status ' + statusClass + '">' + statusIcon + ' ' + statusText + '</span>' +
                '</div>' +
                '<div class="naplo-bet-items">' + itemsHtml + '</div>' +
                '<div class="naplo-item-details">' +
                    '<span>Tét: ' + bet.stake.toLocaleString('hu-HU') + ' Ft</span>' +
                    '<span>Odds: ' + bet.totalOdds.toFixed(2) + '</span>' +
                    '<span>Lehetséges: ' + bet.potentialWin.toLocaleString('hu-HU') + ' Ft</span>' +
                '</div>' +
                winHtml;
            naploItems.appendChild(el);
        });
    }

    // ===== FOGADÁSOK ELLENŐRZÉSE (BACKEND) =====
    function getCheckBetsUrl() {
        var scripts = document.querySelectorAll('script[src*="Betslip/betslip.js"]');
        if (scripts.length > 0) {
            var src = scripts[0].getAttribute('src');
            var base = src.replace('js/Betslip/betslip.js', '');
            return base + 'backend/ApiRequest/check_bets.php';
        }
        return '../../backend/ApiRequest/check_bets.php';
    }

    function checkBetResults() {
        var pendingBets = betHistory.filter(function(b) { return b.status === 'pending'; });
        if (pendingBets.length === 0) return;

        var checkableBets = pendingBets.filter(function(b) {
            return b.items && b.items.some(function(item) {
                return item.matchId && item.matchId > 0;
            });
        });
        if (checkableBets.length === 0) return;

        var url = getCheckBetsUrl();

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(checkableBets)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.status !== 'ok' || !data.bets) return;

            var changed = false;
            data.bets.forEach(function(updatedBet) {
                for (var i = 0; i < betHistory.length; i++) {
                    if (betHistory[i].id === updatedBet.id) {
                        if (betHistory[i].status !== updatedBet.status ||
                            JSON.stringify(betHistory[i].items) !== JSON.stringify(updatedBet.items)) {
                            betHistory[i].status = updatedBet.status;
                            betHistory[i].items = updatedBet.items;
                            changed = true;
                        }
                        break;
                    }
                }
            });

            if (changed) {
                localStorage.setItem('betHistory', JSON.stringify(betHistory));
                renderNaplo();
            }
        })
        .catch(function(err) {
            console.error('Fogadás ellenőrzés hiba:', err);
        });
    }

    function startBetCheck() {
        checkBetResults();
        checkBetsTimer = setInterval(checkBetResults, 30000);
    }

    // ===== GLOBÁLIS FÜGGVÉNYEK =====
    window.refreshBetslipUI = function() {
        betslipItems = JSON.parse(localStorage.getItem('betslip') || '[]');
        renderBetslip();
    };

    window.addToBetslip = function(homeTeam, awayTeam, pick, odds, market, matchId) {
        // Duplikátum ellenőrzés
        var exists = betslipItems.some(function(i) {
            return i.homeTeam === homeTeam && i.awayTeam === awayTeam && i.pick === pick && i.market === market;
        });
        if (exists) return;

        var newItem = {
            homeTeam: homeTeam,
            awayTeam: awayTeam,
            pick: pick,
            odds: odds,
            market: market || '',
            matchId: matchId || 0
        };

        // === KÖTÉSTILTÁS ELLENŐRZÉS ===
        var validation = validateNewBet(newItem);

        if (!validation.allowed) {
            // Tiltott fogadás → modal figyelmeztetés
            showConflictModal(validation.message, false);
            return;
        }

        // Ha van redundáns tétel, azt eltávolítjuk (nagyobb odds marad)
        if (validation.removeIndices.length > 0) {
            // Rendezés csökkenő sorrendbe, hogy a splice ne keveredjen
            validation.removeIndices.sort(function(a, b) { return b - a; });
            validation.removeIndices.forEach(function(idx) {
                betslipItems.splice(idx, 1);
            });
            // Redundancia üzenet megjelenítése
            showConflictModal(validation.message + '\n\nA kisebb oddsú fogadás eltávolítva, a nagyobb marad.', true);
        }

        betslipItems.push(newItem);
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
    startBetCheck();
});
