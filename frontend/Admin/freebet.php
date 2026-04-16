<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('ADMIN');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('freebet');
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];
$activePage = 'freebet';

// Felhasználók betöltése PHP-ból (megbízható, session garantált)
$usersResult = $conn->query("SELECT id, username, email FROM Users WHERE is_active = 1 ORDER BY username ASC");
$allUsers = [];
while ($u = $usersResult->fetch_assoc()) {
    $allUsers[] = $u;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Free Bet | Admin</title>
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
        .sidebar .nav-link { color: #aaa; padding: 10px 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .sidebar .nav-section { color: #e94560; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px 5px; }
        .main-area { flex: 1; padding: 30px; overflow-y: auto; }

        .freebet-card {
            background: #16213e; border-radius: 14px; padding: 28px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .freebet-card h5 { color: #e94560; font-weight: 700; margin-bottom: 20px; }

        .form-control, .form-select {
            background: #0f3460; border: 1px solid rgba(255,255,255,0.1);
            color: #eee; border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            background: #0f3460; color: #eee;
            border-color: #e94560; box-shadow: 0 0 0 0.2rem rgba(233,69,96,0.25);
        }
        .form-control::placeholder { color: #666; }
        .form-label { color: #aaa; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; }

        .btn-freebet {
            background: linear-gradient(135deg, #e94560, #c23152);
            border: none; color: #fff; font-weight: 700; border-radius: 8px;
            padding: 10px 28px; font-size: 0.95rem;
            transition: all 0.2s;
        }
        .btn-freebet:hover { background: linear-gradient(135deg, #c23152, #a8203a); color: #fff; transform: translateY(-1px); }
        .btn-freebet:disabled { opacity: 0.5; transform: none; }

        .form-check-input:checked { background-color: #e94560; border-color: #e94560; }

        /* Searchable user select */
        .user-picker { position: relative; }
        .user-picker input {
            background: #0f3460; border: 1px solid rgba(255,255,255,0.1);
            color: #eee; border-radius: 8px; padding: 8px 12px; width: 100%; font-size: 0.88rem;
        }
        .user-picker input:focus {
            outline: none; border-color: #e94560; box-shadow: 0 0 0 0.2rem rgba(233,69,96,0.25);
        }
        .user-picker input::placeholder { color: #666; }
        .user-picker-list {
            position: absolute; top: 100%; left: 0; right: 0; z-index: 200;
            background: #0f3460; border: 1px solid rgba(255,255,255,0.15);
            border-top: none; border-radius: 0 0 8px 8px;
            max-height: 220px; overflow-y: auto; display: none;
        }
        .user-picker-list.open { display: block; }
        .user-picker-item {
            padding: 8px 12px; cursor: pointer; font-size: 0.85rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .user-picker-item:hover, .user-picker-item.highlighted { background: rgba(233,69,96,0.15); }
        .user-picker-item .uname { font-weight: 700; color: #e94560; }
        .user-picker-item .uemail { color: #777; font-size: 0.78rem; margin-left: 6px; }
        .user-picker-selected {
            display: none; align-items: center; justify-content: space-between;
            background: rgba(233,69,96,0.1); border: 1px solid #e94560;
            border-radius: 8px; padding: 8px 12px; font-size: 0.88rem;
        }
        .user-picker-selected.show { display: flex; }
        .user-picker-selected .remove-pick {
            background: none; border: none; color: #e94560; font-size: 1.2rem; cursor: pointer; line-height: 1;
        }

        /* Quick amount buttons */
        .quick-amounts { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
        .quick-amount {
            background: #0f3460; border: 1px solid rgba(255,255,255,0.1);
            color: #aaa; padding: 4px 12px; border-radius: 6px;
            font-size: 0.78rem; cursor: pointer; transition: all 0.15s;
        }
        .quick-amount:hover { border-color: #e94560; color: #e94560; }

        /* History table */
        .history-table { width: 100%; font-size: 0.85rem; }
        .history-table th { color: #e94560; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); padding: 10px 8px; }
        .history-table td { padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
        .history-table tr:hover td { background: rgba(255,255,255,0.03); }

        .badge-status { padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
        .badge-active { background: rgba(82,183,136,0.2); color: #52b788; }
        .badge-used { background: rgba(91,155,213,0.2); color: #5b9bd5; }
        .badge-expired { background: rgba(154,166,178,0.2); color: #9aa6b2; }

        .pagination-wrap { display: flex; justify-content: center; gap: 6px; margin-top: 16px; }
        .page-btn {
            background: #0f3460; border: 1px solid rgba(255,255,255,0.1);
            color: #aaa; padding: 6px 12px; border-radius: 6px;
            font-size: 0.8rem; cursor: pointer;
        }
        .page-btn.active { background: #e94560; color: #fff; border-color: #e94560; }
        .page-btn:hover { color: #fff; border-color: #e94560; }

        .btn-revoke {
            background: rgba(233,69,96,0.15); border: 1px solid #e94560;
            color: #e94560; padding: 4px 10px; border-radius: 6px;
            font-size: 0.75rem; cursor: pointer; transition: all 0.15s;
            white-space: nowrap;
        }
        .btn-revoke:hover { background: #e94560; color: #fff; }

        /* Toast notification */
        .toast-container { position: fixed; top: 70px; right: 20px; z-index: 9999; }
        .admin-toast {
            background: #16213e; border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 14px 20px; min-width: 300px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4); display: none;
            animation: slideIn 0.3s ease;
        }
        .admin-toast.show { display: block; }
        .admin-toast.success { border-left: 4px solid #52b788; }
        .admin-toast.error { border-left: 4px solid #e94560; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>

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
    <div class="sidebar">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>
    <div class="main-area">
        <h4 class="mb-4"><i class="fas fa-gift" style="color:#e94560;"></i> Free Bet kezelés</h4>

        <div class="row g-4">
            <!-- Free Bet adása form -->
            <div class="col-lg-5">
                <div class="freebet-card">
                    <h5><i class="fas fa-paper-plane"></i> Free Bet küldése</h5>

                    <div class="mb-3">
                        <label class="form-label">Címzett</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="sendToAll" style="background-color:#0f3460;border-color:rgba(255,255,255,0.2);">
                            <label class="form-check-label" for="sendToAll" style="color:#f5c518;font-weight:700;font-size:0.85rem;">
                                <i class="fas fa-users"></i> Összes aktív felhasználónak küldés (<?= count($allUsers) ?> fő)
                            </label>
                        </div>
                        <div class="user-picker" id="userPicker">
                            <input type="text" id="userSearchInput" placeholder="Kezdj el gépelni... (felhasználónév vagy email)" autocomplete="off">
                            <div class="user-picker-list" id="userPickerList"></div>
                            <div class="user-picker-selected" id="userPickerSelected">
                                <span id="userPickerLabel"></span>
                                <button type="button" class="remove-pick" id="userPickerRemove">&times;</button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Összeg (Ft)</label>
                        <input type="number" class="form-control" id="freebetAmount" min="100" max="1000000" placeholder="pl. 1000">
                        <div class="quick-amounts">
                            <span class="quick-amount" data-val="500">500 Ft</span>
                            <span class="quick-amount" data-val="1000">1.000 Ft</span>
                            <span class="quick-amount" data-val="2000">2.000 Ft</span>
                            <span class="quick-amount" data-val="5000">5.000 Ft</span>
                            <span class="quick-amount" data-val="10000">10.000 Ft</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lejárat</label>
                        <select class="form-select" id="freebetExpire">
                            <option value="24">24 óra</option>
                            <option value="48">48 óra</option>
                            <option value="72" selected>72 óra (3 nap)</option>
                            <option value="168">1 hét</option>
                            <option value="336">2 hét</option>
                            <option value="720">30 nap</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Indoklás</label>
                        <textarea class="form-control" id="freebetReason" rows="2" placeholder="Miért kapja a free betet?" style="resize:none;"></textarea>
                    </div>

                    <button class="btn btn-freebet w-100" id="btnGiveFreebet">
                        <i class="fas fa-gift"></i> Free Bet jóváírás
                    </button>
                </div>
            </div>

            <!-- Előzmények -->
            <div class="col-lg-7">
                <div class="freebet-card">
                    <h5><i class="fas fa-history"></i> Free Bet előzmények</h5>
                    <div id="historyContent">
                        <p class="text-muted text-center">Betöltés...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast-container">
    <div class="admin-toast" id="adminToast">
        <div id="toastMessage"></div>
    </div>
</div>

<script>
const fmt = n => Number(n).toLocaleString('hu-HU');

// ─── Toast ───
function showToast(msg, type = 'success') {
    const t = document.getElementById('adminToast');
    t.className = 'admin-toast show ' + type;
    document.getElementById('toastMessage').innerHTML =
        `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}" style="color:${type === 'success' ? '#52b788':'#e94560'};margin-right:8px;"></i>${msg}`;
    setTimeout(() => t.classList.remove('show'), 4000);
}

// ─── API hívás ───
async function apiCall(action, data = {}) {
    try {
        const res = await fetch('../../backend/ApiRequest/admin_freebet_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action, ...data })
        });
        const text = await res.text();
        try { return JSON.parse(text); }
        catch(e) { console.error('Nem JSON:', text.substring(0, 300)); return { success: false, message: 'Szerver hiba (nem JSON válasz)' }; }
    } catch(e) {
        console.error('Hálózati hiba:', e);
        return { success: false, message: 'Hálózati hiba' };
    }
}

// ─── Felhasználó adatok PHP-ból ───
const ALL_USERS = [
    <?php foreach ($allUsers as $u): ?>
    { id: <?= (int)$u['id'] ?>, username: <?= json_encode($u['username'], JSON_UNESCAPED_UNICODE) ?>, email: <?= json_encode($u['email'], JSON_UNESCAPED_UNICODE) ?> },
    <?php endforeach; ?>
];

// ─── Searchable user picker ───
const pickerInput = document.getElementById('userSearchInput');
const pickerList = document.getElementById('userPickerList');
const pickerSelected = document.getElementById('userPickerSelected');
const pickerLabel = document.getElementById('userPickerLabel');
let selectedUserId = null;
let highlightedIdx = -1;

function renderPickerList(filter) {
    const term = filter.toLowerCase();
    const filtered = term.length === 0 ? ALL_USERS : ALL_USERS.filter(u =>
        u.username.toLowerCase().includes(term) || u.email.toLowerCase().includes(term)
    );
    if (!filtered.length) {
        pickerList.innerHTML = '<div style="padding:10px 12px;color:#666;font-size:0.84rem;">Nincs találat</div>';
    } else {
        pickerList.innerHTML = filtered.map((u, i) =>
            `<div class="user-picker-item" data-id="${u.id}" data-username="${u.username.replace(/"/g,'&quot;')}" data-idx="${i}"><span class="uname">${u.username}</span><span class="uemail">(${u.email})</span></div>`
        ).join('');
    }
    highlightedIdx = -1;
    pickerList.classList.add('open');
}

pickerInput.addEventListener('input', function() {
    renderPickerList(this.value.trim());
});

pickerInput.addEventListener('focus', function() {
    if (!pickerSelected.classList.contains('show')) {
        renderPickerList(this.value.trim());
    }
});

pickerInput.addEventListener('keydown', function(e) {
    const items = pickerList.querySelectorAll('.user-picker-item[data-id]');
    if (!items.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); highlightedIdx = Math.min(highlightedIdx + 1, items.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlightedIdx = Math.max(highlightedIdx - 1, 0); }
    else if (e.key === 'Enter' && highlightedIdx >= 0) { e.preventDefault(); items[highlightedIdx].click(); return; }
    else return;
    items.forEach((el, i) => el.classList.toggle('highlighted', i === highlightedIdx));
    items[highlightedIdx]?.scrollIntoView({ block: 'nearest' });
});

pickerList.addEventListener('mousedown', function(e) {
    e.preventDefault(); // prevent blur
    const item = e.target.closest('.user-picker-item[data-id]');
    if (!item) return;
    pickUser(parseInt(item.dataset.id), item.dataset.username);
});

function pickUser(id, username) {
    selectedUserId = id;
    pickerLabel.innerHTML = `<i class="fas fa-user" style="color:#e94560;margin-right:6px;"></i><strong>${username}</strong> <small style="color:#888;">(#${id})</small>`;
    pickerSelected.classList.add('show');
    pickerInput.style.display = 'none';
    pickerList.classList.remove('open');
}

document.getElementById('userPickerRemove').addEventListener('click', function() {
    selectedUserId = null;
    pickerSelected.classList.remove('show');
    pickerInput.style.display = '';
    pickerInput.value = '';
    pickerInput.focus();
});

document.addEventListener('mousedown', function(e) {
    if (!e.target.closest('#userPicker')) pickerList.classList.remove('open');
});

// ─── Összes felhasználó checkbox ───
const sendToAllCb = document.getElementById('sendToAll');
const pickerWrap = document.getElementById('userPicker');

sendToAllCb.addEventListener('change', function() {
    if (this.checked) {
        pickerWrap.style.opacity = '0.4';
        pickerWrap.style.pointerEvents = 'none';
    } else {
        pickerWrap.style.opacity = '';
        pickerWrap.style.pointerEvents = '';
    }
});

// ─── Gyors összeg gombok ───
document.querySelectorAll('.quick-amount').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('freebetAmount').value = this.dataset.val;
    });
});

// ─── Free Bet küldése ───
document.getElementById('btnGiveFreebet').addEventListener('click', async function() {
    const sendAll = sendToAllCb.checked;
    const userId = sendAll ? 0 : selectedUserId;
    const amount = parseInt(document.getElementById('freebetAmount').value);
    const reason = document.getElementById('freebetReason').value.trim();

    if (!sendAll && !userId) { showToast('Válassz felhasználót vagy jelöld be az "Összes"-t!', 'error'); return; }
    if (!amount || amount < 100) { showToast('Minimum összeg: 100 Ft', 'error'); return; }
    if (!reason) { showToast('Adj meg indoklást!', 'error'); return; }

    const doSend = async () => {
        const btn = document.getElementById('btnGiveFreebet');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Küldés...';

        const expireHours = parseInt(document.getElementById('freebetExpire').value);
        const action = sendAll ? 'give_freebet_all' : 'give_freebet';
        const payload = sendAll
            ? { amount, expire_hours: expireHours, reason }
            : { user_id: userId, amount, expire_hours: expireHours, reason };

        const data = await apiCall(action, payload);

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-gift"></i> Free Bet jóváírás';

    if (data.success) {
        showToast(data.message, 'success');
        document.getElementById('userPickerRemove').click();
        sendToAllCb.checked = false;
        pickerWrap.style.opacity = '';
        pickerWrap.style.pointerEvents = '';
        document.getElementById('freebetAmount').value = '';
        document.getElementById('freebetReason').value = '';
        loadHistory(1);
    } else {
        showToast(data.message, 'error');
    }
    };

    if (sendAll) {
        BmbPopup.confirm(`Biztosan küldöd ${fmt(amount)} Ft-ot MINDEN aktív felhasználónak?`, doSend);
    } else {
        doSend();
    }
});

// ─── Előzmények ───
async function loadHistory(page = 1) {
    const data = await apiCall('get_history', { page });
    if (!data.success) {
        document.getElementById('historyContent').innerHTML = '<p class="text-muted text-center">Hiba történt</p>';
        return;
    }
    if (!data.history.length) {
        document.getElementById('historyContent').innerHTML = '<p class="text-muted text-center" style="font-size:0.88rem;">Még nem adtál free betet.</p>';
        return;
    }

    let html = `<table class="history-table">
        <thead><tr><th>Felhasználó</th><th>Összeg</th><th>Státusz</th><th>Lejárat</th><th>Dátum</th><th></th></tr></thead><tbody>`;

    data.history.forEach(h => {
        const created = new Date(h.created_at);
        const createdStr = created.toLocaleDateString('hu-HU') + ' ' + created.toLocaleTimeString('hu-HU', {hour:'2-digit',minute:'2-digit'});

        let statusBadge = '';
        switch (h.status) {
            case 'ACTIVE':    statusBadge = '<span class="badge-status badge-active">Aktív</span>'; break;
            case 'COMPLETED': statusBadge = '<span class="badge-status badge-used">Felhasználva</span>'; break;
            case 'EXPIRED':   statusBadge = '<span class="badge-status badge-expired">Lejárt</span>'; break;
            case 'FAILED':    statusBadge = '<span class="badge-status badge-expired">Sikertelen</span>'; break;
            default:          statusBadge = '<span class="badge-status badge-expired">' + h.status + '</span>';
        }

        let expiresStr = '-';
        if (h.expires_at) {
            const exp = new Date(h.expires_at);
            expiresStr = exp.toLocaleDateString('hu-HU') + ' ' + exp.toLocaleTimeString('hu-HU', {hour:'2-digit',minute:'2-digit'});
        }

        const canRevoke = (h.status === 'ACTIVE');
        const isBatch = h.batch_count > 1;
        const revokeBtn = canRevoke
            ? `<button class="btn-revoke" onclick="revokeFreebet(${h.id}, ${isBatch}, ${h.batch_count})" title="${isBatch ? 'Csoportos elvétel (' + h.batch_count + ' fő)' : 'Elvétel'}">
                <i class="fas fa-ban"></i>${isBatch ? ' <small>' + h.batch_count + ' fő</small>' : ''}
               </button>`
            : '';

        html += `<tr>
            <td><strong>${h.username}</strong> <small style="color:#666;">#${h.user_id}</small></td>
            <td style="font-weight:700;color:#f5c518;">${fmt(h.amount)} Ft</td>
            <td>${statusBadge}</td>
            <td style="font-size:0.78rem;color:#888;">${expiresStr}</td>
            <td style="font-size:0.78rem;color:#888;">${createdStr}</td>
            <td>${revokeBtn}</td>
        </tr>`;
    });

    html += '</tbody></table>';

    if (data.pages > 1) {
        html += '<div class="pagination-wrap">';
        for (let p = 1; p <= data.pages; p++) {
            html += `<button class="page-btn${p === data.page ? ' active' : ''}" onclick="loadHistory(${p})">${p}</button>`;
        }
        html += '</div>';
    }

    document.getElementById('historyContent').innerHTML = html;
}

loadHistory(1);

async function revokeFreebet(id, isBatch, batchCount) {
    const msg = isBatch
        ? `Biztosan visszavonod ezt a Free Bet-et MINDEN címzettől (${batchCount} fő)?`
        : 'Biztosan visszavonod ezt a Free Bet-et?';
    BmbPopup.confirm(msg, async function() {
        const data = await apiCall('revoke_freebet', { id });
        if (data.success) {
            showToast(data.message, 'success');
            loadHistory(1);
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>
</body>
</html>
