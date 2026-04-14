<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('balances');
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Egyenlegek | Admin | BetMatchBonus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body { background: #1a1a2e; color: #eee; }
        p { color: #e6e6e6 !important; }
        .text-muted { color: #9aa6b2 !important; }
        .navbar-admin { background: #16213e; }
        .sidebar {
            background: #16213e; min-height: calc(100vh - 56px);
            padding: 20px 0; width: 220px; flex-shrink: 0;
        }
        .sidebar .nav-link { color: #ccc; padding: 10px 20px; display: block; }
        .sidebar .nav-link:hover { color: #fff; background: #0f3460; }
        .main-content { flex: 1; padding: 24px; min-width: 0; }

        .stat-card {
            background: #16213e; border-radius: 10px; padding: 24px; text-align: center;
        }
        .stat-card h3 { font-size: 2.2rem; margin-bottom: 4px; }
        .stat-card p { color: #aaa; margin: 0; }

        .search-box {
            background: #16213e; border-radius: 10px; padding: 16px; margin-bottom: 20px;
            display: flex; gap: 10px; align-items: center;
        }
        .search-box input {
            background: #0f1b30; border: 1px solid #2a3a5c; color: #fff; border-radius: 8px;
            padding: 8px 14px; flex: 1; font-size: 0.95rem;
        }
        .search-box input:focus { border-color: #5b9bd5; outline: none; }
        .search-box input::placeholder { color: #666; }

        .table-dark th { color: #e94560; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table-dark td { vertical-align: middle; font-size: 0.9rem; }

        .user-row { cursor: pointer; transition: background 0.2s; }
        .user-row:hover { background: #0f3460 !important; }
        .user-row.active-row { background: #0f3460 !important; }

        .balance-badge {
            display: inline-block; padding: 3px 10px; border-radius: 6px;
            font-size: 0.82rem; font-weight: 600;
        }
        .bal-main    { background: #1b4332; color: #52b788; }
        .bal-win     { background: #1a3a1a; color: #a3d977; }
        .bal-bonus   { background: #3a2a0a; color: #f5c518; }
        .bal-locked  { background: #3a1a1a; color: #e94560; }

        .detail-panel { display: none; background: #12192e; }
        .detail-panel.show { display: table-row; }
        .detail-panel td { padding: 0 !important; }
        .detail-inner { padding: 20px 24px; }

        /* History entries */
        .hist-entry {
            display: flex; align-items: center; gap: 14px; padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .hist-entry:last-child { border-bottom: none; }
        .hist-icon {
            width: 34px; height: 34px; border-radius: 8px; display: flex;
            align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.85rem;
        }
        .hist-icon.credit { background: #1b4332; color: #52b788; }
        .hist-icon.debit  { background: #3a1a1a; color: #e94560; }
        .hist-body { flex: 1; min-width: 0; }
        .hist-amount { font-weight: 700; font-size: 0.95rem; }
        .hist-reason { font-size: 0.82rem; color: #9aa6b2; }
        .hist-time   { font-size: 0.72rem; color: #666; }
        .hist-balances { font-size: 0.78rem; color: #888; }

        .hist-empty { text-align: center; padding: 30px; color: #666; }

        /* Adjust form */
        .adjust-box {
            background: #0a1628; border: 1px solid #1a3a5c; border-radius: 8px;
            padding: 16px; margin-top: 14px;
        }
        .adjust-box input, .adjust-box textarea, .adjust-box select {
            background: #111; border: 1px solid #444; color: #fff;
            border-radius: 6px; padding: 6px 10px; font-size: 0.9rem;
        }
        .adjust-box input:focus, .adjust-box textarea:focus, .adjust-box select:focus {
            border-color: #5b9bd5; outline: none; box-shadow: 0 0 0 0.2rem rgba(91,155,213,.2);
        }
        .adjust-box input::placeholder, .adjust-box textarea::placeholder { color: #fff; opacity: 0.4; }
        .adjust-box textarea { resize: vertical; min-height: 50px; width: 100%; }

        .btn-credit { background: #1b6b3a; color: #fff; border: none; }
        .btn-credit:hover { background: #28a745; color: #fff; }
        .btn-debit { background: #8b1a2b; color: #fff; border: none; }
        .btn-debit:hover { background: #dc3545; color: #fff; }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }

        .pagination .page-item .page-link {
            background: #16213e; border-color: #2a3a5c; color: #ccc;
        }
        .pagination .page-item.active .page-link {
            background: #e94560; border-color: #e94560; color: #fff;
        }
        .pagination .page-item.disabled .page-link {
            background: #0f1b30; color: #555;
        }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- Navbar -->
<nav class="navbar navbar-admin px-4 d-flex justify-content-between" style="height:56px;">
    <div class="d-flex align-items-center gap-3">
        <img src="../../img/logo.png" alt="logo" style="width:40px;">
        <span class="text-white fw-bold fs-5">Admin Dashboard</span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white fw-semibold d-inline-flex align-items-center gap-2">
            <?= htmlspecialchars($_SESSION['admin_username']) ?>
            <span class="badge rounded-pill bg-danger"><?= htmlspecialchars($role) ?></span>
        </span>
        <a href="/BetMatchBonus/backend/Auth/admin_logout.php" class="btn btn-outline-danger btn-sm">Kijelentkezés</a>
    </div>
</nav>

<div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar">
        <?php $activePage = 'balances'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <!-- Stat kártyák -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#52b788;" id="statBalance">–</h3>
                    <p>Összes egyenleg (Ft)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#a3d977;" id="statWinnings">–</h3>
                    <p>Nyeremény egyenleg (Ft)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#f5c518;" id="statBonus">–</h3>
                    <p>Bónusz egyenleg (Ft)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#5b9bd5;" id="statUsers">–</h3>
                    <p>Aktív felhasználók</p>
                </div>
            </div>
        </div>

        <!-- Keresés -->
        <div class="search-box">
            <i class="fas fa-search" style="color:#666;"></i>
            <input type="text" id="searchInput" placeholder="Keresés: felhasználónév, email, név vagy ID...">
        </div>

        <!-- Felhasználók tábla -->
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Felhasználó</th>
                        <th>Egyenleg</th>
                        <th>Nyeremény</th>
                        <th>Bónusz</th>
                        <th>Zárolt</th>
                        <th>Történet</th>
                    </tr>
                </thead>
                <tbody id="usersBody">
                    <tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Betöltés...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Lapozás -->
        <nav class="mt-3 d-flex justify-content-center" id="paginationNav" style="display:none!important;"></nav>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '../../backend/ApiRequest/admin_balance.php';
let currentPage = 1;
let currentSearch = '';
let openUserId = null;

function fmt(n) {
    return Number(n).toLocaleString('hu-HU', { maximumFractionDigits: 0 });
}

function showToast(msg, success = true) {
    const c = document.getElementById('toastContainer');
    const d = document.createElement('div');
    d.className = 'toast show align-items-center text-white border-0';
    d.style.cssText = 'background:' + (success ? '#1b6b3a' : '#8b1a2b') + ';min-width:300px;margin-bottom:8px;';
    d.innerHTML = '<div class="d-flex"><div class="toast-body">' + msg + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    c.appendChild(d);
    setTimeout(() => d.remove(), 4000);
}

async function loadUsers(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({ action: 'users', page, search: currentSearch });
    try {
        const res = await fetch(API + '?' + params);
        const data = await res.json();

        // Stat kártyák
        if (data.stats) {
            document.getElementById('statBalance').textContent = fmt(data.stats.total_balance);
            document.getElementById('statWinnings').textContent = fmt(data.stats.total_winnings);
            document.getElementById('statBonus').textContent = fmt(data.stats.total_bonus);
            document.getElementById('statUsers').textContent = fmt(data.stats.total_users);
        }

        const tbody = document.getElementById('usersBody');
        if (!data.users || data.users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4" style="color:#666;">Nincs találat.</td></tr>';
            document.getElementById('paginationNav').innerHTML = '';
            return;
        }

        let html = '';
        data.users.forEach(u => {
            html += `<tr class="user-row" data-uid="${u.id}" onclick="toggleUser(${u.id})">
                <td>#${u.id}</td>
                <td>
                    <strong>${esc(u.username)}</strong><br>
                    <small class="text-muted">${esc(u.email)}</small>
                </td>
                <td><span class="balance-badge bal-main">${fmt(u.balance)} Ft</span></td>
                <td><span class="balance-badge bal-win">${fmt(u.winnings_balance)} Ft</span></td>
                <td><span class="balance-badge bal-bonus">${fmt(u.bonus_balance)} Ft</span></td>
                <td><span class="balance-badge bal-locked">${fmt(u.locked_amount)} Ft</span></td>
                <td><span style="color:#888;">${u.history_count} db</span></td>
            </tr>
            <tr class="detail-panel" id="detail-${u.id}">
                <td colspan="7">
                    <div class="detail-inner">
                        <div class="row">
                            <div class="col-md-7">
                                <h6 style="color:#e94560;"><i class="fas fa-history"></i> Egyenleg történet</h6>
                                <div id="history-${u.id}"><div class="hist-empty"><i class="fas fa-spinner fa-spin"></i></div></div>
                                <nav id="histPag-${u.id}" class="mt-2"></nav>
                            </div>
                            <div class="col-md-5">
                                <h6 style="color:#5b9bd5;"><i class="fas fa-edit"></i> Egyenleg módosítás</h6>
                                <div class="adjust-box">
                                    <div class="mb-2">
                                        <label class="form-label" style="font-size:0.82rem;color:#aaa;">Összeg (Ft)</label>
                                        <input type="number" id="adjAmount-${u.id}" min="1" placeholder="pl. 5000" style="width:100%;">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label" style="font-size:0.82rem;color:#aaa;">Ok / megjegyzés</label>
                                        <textarea id="adjReason-${u.id}" placeholder="Miért módosítod az egyenleget..."></textarea>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-credit btn-sm flex-fill" onclick="adjustBalance(${u.id}, 'credit')">
                                            <i class="fas fa-plus-circle"></i> Jóváírás
                                        </button>
                                        <button class="btn btn-debit btn-sm flex-fill" onclick="adjustBalance(${u.id}, 'debit')">
                                            <i class="fas fa-minus-circle"></i> Levonás
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-3" style="font-size:0.82rem;color:#888;">
                                    <strong>Jelenlegi egyenlegek:</strong><br>
                                    <span style="color:#52b788;">● Fő:</span> <span id="curBal-${u.id}">${fmt(u.balance)}</span> Ft<br>
                                    <span style="color:#a3d977;">● Nyeremény:</span> ${fmt(u.winnings_balance)} Ft<br>
                                    <span style="color:#f5c518;">● Bónusz:</span> ${fmt(u.bonus_balance)} Ft<br>
                                    <span style="color:#e94560;">● Zárolt:</span> ${fmt(u.locked_amount)} Ft
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;

        // Lapozás
        renderPagination(data.page, data.pages, 'paginationNav', loadUsers);
    } catch (err) {
        console.error(err);
    }
}

async function toggleUser(uid) {
    const panel = document.getElementById('detail-' + uid);
    const row = document.querySelector(`.user-row[data-uid="${uid}"]`);

    if (openUserId === uid) {
        panel.classList.remove('show');
        row.classList.remove('active-row');
        openUserId = null;
        return;
    }

    // Előző bezárása
    if (openUserId !== null) {
        const oldPanel = document.getElementById('detail-' + openUserId);
        const oldRow = document.querySelector(`.user-row[data-uid="${openUserId}"]`);
        if (oldPanel) oldPanel.classList.remove('show');
        if (oldRow) oldRow.classList.remove('active-row');
    }

    openUserId = uid;
    panel.classList.add('show');
    row.classList.add('active-row');
    loadHistory(uid, 1);
}

async function loadHistory(uid, page = 1) {
    const container = document.getElementById('history-' + uid);
    container.innerHTML = '<div class="hist-empty"><i class="fas fa-spinner fa-spin"></i></div>';

    try {
        const params = new URLSearchParams({ action: 'history', user_id: uid, page });
        const res = await fetch(API + '?' + params);
        const data = await res.json();

        if (!data.items || data.items.length === 0) {
            container.innerHTML = '<div class="hist-empty"><i class="fas fa-clipboard-list" style="font-size:1.5rem;margin-bottom:8px;display:block;"></i>Még nincs egyenleg-történeti bejegyzés.</div>';
            document.getElementById('histPag-' + uid).innerHTML = '';
            return;
        }

        let html = '';
        data.items.forEach(h => {
            const isCredit = h.change_amount >= 0;
            const sign = isCredit ? '+' : '';
            html += `<div class="hist-entry">
                <div class="hist-icon ${isCredit ? 'credit' : 'debit'}">
                    <i class="fas fa-${isCredit ? 'arrow-up' : 'arrow-down'}"></i>
                </div>
                <div class="hist-body">
                    <div class="hist-amount" style="color:${isCredit ? '#52b788' : '#e94560'};">${sign}${fmt(h.change_amount)} Ft</div>
                    <div class="hist-reason">${esc(h.reason || '–')}</div>
                    <div class="hist-balances">${fmt(h.previous_balance)} → ${fmt(h.new_balance)} Ft</div>
                    <div class="hist-time"><i class="far fa-clock"></i> ${h.created_at}${h.tx_ref ? ' · <span style="color:#5b9bd5;">' + esc(h.tx_ref) + '</span>' : ''}</div>
                </div>
            </div>`;
        });
        container.innerHTML = html;

        renderPagination(data.page, data.pages, 'histPag-' + uid, (p) => loadHistory(uid, p));
    } catch (err) {
        container.innerHTML = '<div class="hist-empty" style="color:#e94560;">Hálózati hiba</div>';
    }
}

async function adjustBalance(uid, type) {
    const amount = parseFloat(document.getElementById('adjAmount-' + uid).value);
    const reason = document.getElementById('adjReason-' + uid).value.trim();

    if (!amount || amount <= 0) { showToast('Add meg az összeget!', false); return; }
    if (!reason) { showToast('Add meg az okot!', false); return; }

    const label = type === 'credit' ? 'jóváírás' : 'levonás';
    if (!confirm(`Biztosan végrehajtod: ${fmt(amount)} Ft ${label}?`)) return;

    try {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: uid, amount, type, reason })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, true);
            document.getElementById('adjAmount-' + uid).value = '';
            document.getElementById('adjReason-' + uid).value = '';
            document.getElementById('curBal-' + uid).textContent = fmt(data.new_balance);
            loadHistory(uid, 1);
            // Frissítjük a fő sort is
            loadUsers(currentPage);
        } else {
            showToast(data.error || 'Hiba', false);
        }
    } catch (err) {
        showToast('Hálózati hiba', false);
    }
}

function renderPagination(current, total, containerId, callback) {
    const nav = document.getElementById(containerId);
    if (total <= 1) { nav.innerHTML = ''; return; }

    let html = '<ul class="pagination pagination-sm mb-0">';
    html += `<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="event.preventDefault();${callback.name ? callback.name : ''}(${current - 1})">«</a></li>`;

    for (let i = 1; i <= total; i++) {
        if (total > 7 && i > 3 && i < total - 2 && Math.abs(i - current) > 1) {
            if (i === 4) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            continue;
        }
        html += `<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" onclick="event.preventDefault()">${i}</a></li>`;
    }

    html += `<li class="page-item ${current >= total ? 'disabled' : ''}"><a class="page-link" href="#" onclick="event.preventDefault();${callback.name ? callback.name : ''}(${current + 1})">»</a></li>`;
    html += '</ul>';
    nav.innerHTML = html;

    // Click events
    nav.querySelectorAll('.page-item:not(.disabled):not(.active) .page-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = parseInt(link.textContent);
            if (link.textContent === '«') callback(current - 1);
            else if (link.textContent === '»') callback(current + 1);
            else if (!isNaN(page)) callback(page);
        });
    });
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

// Keresés debounce
let searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        currentSearch = this.value.trim();
        loadUsers(1);
    }, 400);
});

// Betöltés
loadUsers(1);
</script>
</body>
</html>
