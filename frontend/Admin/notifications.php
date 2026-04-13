<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('notifications');
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];
$activePage = 'notifications';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Értesítések | Admin</title>
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
        .stat-card {
            background: #16213e; border-radius: 12px; padding: 20px;
            text-align: center; border: 1px solid rgba(255,255,255,0.06);
        }
        .stat-card h3 { font-weight: 800; font-size: 1.8rem; margin: 0; }
        .stat-card p { color: #aaa; font-size: 0.82rem; margin: 6px 0 0; }
        .compose-card {
            background: #16213e; border-radius: 12px; padding: 28px;
            border: 1px solid rgba(255,255,255,0.06); margin-bottom: 24px;
        }
        .compose-card h5 { color: #e94560; font-weight: 700; margin-bottom: 20px; }
        .compose-card label { font-weight: 600; font-size: 0.85rem; color: #ccc; margin-bottom: 6px; }
        .compose-card .form-control, .compose-card .form-select {
            background: #1a1a2e; border: 1px solid rgba(255,255,255,0.12);
            color: #eee; border-radius: 8px;
        }
        .compose-card .form-control:focus, .compose-card .form-select:focus {
            border-color: #e94560; box-shadow: 0 0 0 2px rgba(233,69,96,0.2);
        }
        .compose-card textarea { min-height: 120px; resize: vertical; }
        .btn-send {
            background: #e94560; border: none; color: #fff; padding: 12px 32px;
            border-radius: 8px; font-weight: 700; font-size: 0.95rem; cursor: pointer;
        }
        .btn-send:hover { background: #d63853; }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; }
        .history-card {
            background: #16213e; border-radius: 12px; padding: 24px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .history-card h5 { color: #e94560; font-weight: 700; margin-bottom: 16px; }
        .notif-item {
            background: rgba(255,255,255,0.03); border-radius: 10px; padding: 16px;
            margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }
        .notif-item:hover { border-color: rgba(233,69,96,0.3); }
        .notif-title { font-weight: 700; font-size: 1rem; }
        .notif-meta { font-size: 0.8rem; color: #888; margin-top: 4px; }
        .notif-msg { font-size: 0.88rem; color: #bbb; margin-top: 8px; white-space: pre-wrap; word-break: break-word; }
        .read-bar { height: 4px; border-radius: 2px; background: #333; margin-top: 8px; overflow: hidden; }
        .read-bar-fill { height: 100%; border-radius: 2px; background: #52b788; transition: width 0.3s; }
        .pagination-wrap { display: flex; justify-content: center; gap: 6px; margin-top: 20px; }
        .pagination-wrap button {
            background: #0f3460; border: none; color: #aaa; padding: 6px 14px;
            border-radius: 6px; font-size: 0.8rem; cursor: pointer;
        }
        .pagination-wrap button.active { background: #e94560; color: #fff; }
        .pagination-wrap button:hover { color: #fff; }
        .char-count { font-size: 0.75rem; color: #888; text-align: right; margin-top: 4px; }
        .target-info { font-size: 0.78rem; color: #888; margin-top: 6px; }
        .btn-delete-notif {
            background: none; border: none; color: #dc3545; font-size: 0.85rem;
            cursor: pointer; opacity: 0.6; padding: 4px 8px;
        }
        .btn-delete-notif:hover { opacity: 1; }
        .preview-box {
            background: rgba(233,69,96,0.08); border: 1px dashed rgba(233,69,96,0.3);
            border-radius: 10px; padding: 16px; margin-top: 16px; display: none;
        }
        .preview-box h6 { color: #e94560; font-weight: 700; font-size: 0.85rem; margin-bottom: 8px; }
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
        <h4 class="mb-4"><i class="fas fa-bell"></i> Értesítések & Hirdetmények</h4>

        <!-- Stat kártyák -->
        <div class="row g-3 mb-4" id="notifStats">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <h3 style="color:#4cc9f0;" id="statActiveUsers">-</h3>
                    <p>Aktív felhasználó</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <h3 style="color:#e94560;" id="statBroadcasts">-</h3>
                    <p>Küldött hirdetmény</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <h3 style="color:#52b788;" id="statTotalSent">-</h3>
                    <p>Összes üzenet</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <h3 style="color:#f5c518;" id="statUnread">-</h3>
                    <p>Olvasatlan</p>
                </div>
            </div>
        </div>

        <!-- Üzenet küldése -->
        <div class="compose-card">
            <h5><i class="fas fa-paper-plane"></i> Új hirdetmény küldése</h5>
            <form id="composeForm">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="notifTitle">Cím *</label>
                        <input type="text" class="form-control" id="notifTitle" maxlength="100" placeholder="Értesítés címe..." required>
                        <div class="char-count"><span id="titleCount">0</span>/100</div>
                    </div>
                    <div class="col-md-4">
                        <label for="notifTarget">Célcsoport</label>
                        <select class="form-select" id="notifTarget">
                            <option value="all">Összes felhasználó</option>
                            <option value="active" selected>Aktív felhasználók</option>
                            <option value="verified">Hitelesített felhasználók</option>
                        </select>
                        <div class="target-info" id="targetInfo">
                            <i class="fas fa-users"></i> <span id="targetCount">-</span> címzett
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="notifMessage">Üzenet *</label>
                        <textarea class="form-control" id="notifMessage" maxlength="255" placeholder="Üzenet tartalma..." required></textarea>
                        <div class="char-count"><span id="msgCount">0</span>/255</div>
                    </div>
                </div>

                <!-- Előnézet -->
                <div class="preview-box" id="previewBox">
                    <h6><i class="fas fa-eye"></i> Előnézet</h6>
                    <div style="background:#1a1a2e;border-radius:8px;padding:12px;border:1px solid rgba(255,255,255,0.08);">
                        <div style="font-weight:700;font-size:0.95rem;" id="previewTitle"></div>
                        <div style="color:#bbb;font-size:0.85rem;margin-top:6px;white-space:pre-wrap;" id="previewMsg"></div>
                        <div style="color:#9aa6b2;font-size:0.75rem;margin-top:8px;"><i class="fas fa-clock"></i> Most</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 mt-3">
                    <button type="submit" class="btn-send" id="sendBtn">
                        <i class="fas fa-paper-plane"></i> Küldés
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="previewBtn">
                        <i class="fas fa-eye"></i> Előnézet
                    </button>
                    <div id="sendResult" style="font-size:0.85rem;"></div>
                </div>
            </form>
        </div>

        <!-- Korábbi hirdetmények -->
        <div class="history-card">
            <h5><i class="fas fa-history"></i> Korábbi hirdetmények</h5>
            <div id="historyList"></div>
            <div class="pagination-wrap" id="historyPagination"></div>
        </div>
    </div>
</div>

<script>
const fmt = n => Number(n).toLocaleString('hu-HU');
let currentPage = 1;

// ─── Stat kártyák ───
async function loadStats() {
    try {
        const res = await fetch('../../backend/ApiRequest/admin_notifications.php?action=stats');
        const data = await res.json();
        document.getElementById('statActiveUsers').textContent = fmt(data.active_users);
        document.getElementById('statBroadcasts').textContent = fmt(data.broadcasts);
        document.getElementById('statTotalSent').textContent = fmt(data.total_sent);
        document.getElementById('statUnread').textContent = fmt(data.unread);
        document.getElementById('targetCount').textContent = fmt(data.active_users);
    } catch (e) { console.error(e); }
}

// ─── História ───
async function loadHistory(page) {
    currentPage = page || 1;
    try {
        const res = await fetch('../../backend/ApiRequest/admin_notifications.php?action=list&page=' + currentPage);
        const data = await res.json();

        if (!data.items.length) {
            document.getElementById('historyList').innerHTML = '<p class="text-muted text-center">Még nem küldtél hirdetményt.</p>';
            document.getElementById('historyPagination').innerHTML = '';
            return;
        }

        let html = '';
        data.items.forEach(item => {
            const readPct = item.recipient_count > 0 ? Math.round(item.read_count / item.recipient_count * 100) : 0;
            const date = new Date(item.sent_at).toLocaleString('hu-HU');
            html += `
                <div class="notif-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="notif-title"><i class="fas fa-bullhorn" style="color:#e94560;"></i> ${escHtml(item.title)}</div>
                            <div class="notif-meta">
                                <i class="fas fa-clock"></i> ${date}
                                &nbsp;&bull;&nbsp;
                                <i class="fas fa-users"></i> ${item.recipient_count} címzett
                                &nbsp;&bull;&nbsp;
                                <i class="fas fa-eye"></i> ${item.read_count} olvasva (${readPct}%)
                            </div>
                        </div>
                        <button class="btn-delete-notif" onclick="deleteNotif('${escAttr(item.title)}','${item.sent_at}')" title="Törlés">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    <div class="notif-msg">${escHtml(item.message)}</div>
                    <div class="read-bar"><div class="read-bar-fill" style="width:${readPct}%"></div></div>
                </div>
            `;
        });
        document.getElementById('historyList').innerHTML = html;

        // Pagination
        let pgHtml = '';
        for (let i = 1; i <= data.pages; i++) {
            pgHtml += `<button class="${i === data.page ? 'active' : ''}" onclick="loadHistory(${i})">${i}</button>`;
        }
        document.getElementById('historyPagination').innerHTML = pgHtml;
    } catch (e) { console.error(e); }
}

// ─── Küldés ───
document.getElementById('composeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const title = document.getElementById('notifTitle').value.trim();
    const message = document.getElementById('notifMessage').value.trim();
    const target = document.getElementById('notifTarget').value;

    if (!title || !message) return;

    const targetLabels = { all: 'összes felhasználónak', active: 'aktív felhasználóknak', verified: 'hitelesített felhasználóknak' };
    if (!confirm('Biztosan elküldöd a hirdetményt ' + targetLabels[target] + '?')) return;

    const btn = document.getElementById('sendBtn');
    const result = document.getElementById('sendResult');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Küldés...';
    result.innerHTML = '';

    try {
        const res = await fetch('../../backend/ApiRequest/admin_notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send', title, message, target })
        });
        const data = await res.json();

        if (data.success) {
            result.innerHTML = '<span style="color:#52b788;"><i class="fas fa-check-circle"></i> ' + data.message + '</span>';
            document.getElementById('notifTitle').value = '';
            document.getElementById('notifMessage').value = '';
            document.getElementById('titleCount').textContent = '0';
            document.getElementById('msgCount').textContent = '0';
            document.getElementById('previewBox').style.display = 'none';
            loadStats();
            loadHistory(1);
        } else {
            result.innerHTML = '<span style="color:#dc3545;"><i class="fas fa-times-circle"></i> ' + (data.error || 'Hiba') + '</span>';
        }
    } catch (err) {
        result.innerHTML = '<span style="color:#dc3545;">Hálózati hiba</span>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Küldés';
});

// ─── Törlés ───
async function deleteNotif(title, sentAt) {
    if (!confirm('Biztosan törlöd ezt a hirdetményt minden felhasználónál?')) return;
    try {
        const res = await fetch('../../backend/ApiRequest/admin_notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', title, sent_at: sentAt })
        });
        const data = await res.json();
        if (data.success) {
            loadStats();
            loadHistory(currentPage);
        } else {
            alert(data.error || 'Törlési hiba');
        }
    } catch (e) { alert('Hálózati hiba'); }
}

// ─── Karakter számlálók ───
document.getElementById('notifTitle').addEventListener('input', function() {
    document.getElementById('titleCount').textContent = this.value.length;
    updatePreview();
});
document.getElementById('notifMessage').addEventListener('input', function() {
    document.getElementById('msgCount').textContent = this.value.length;
    updatePreview();
});

// ─── Előnézet ───
document.getElementById('previewBtn').addEventListener('click', function() {
    const box = document.getElementById('previewBox');
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
    updatePreview();
});

function updatePreview() {
    document.getElementById('previewTitle').textContent = document.getElementById('notifTitle').value || '(nincs cím)';
    document.getElementById('previewMsg').textContent = document.getElementById('notifMessage').value || '(nincs üzenet)';
}

// ─── Helpers ───
function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
function escAttr(s) {
    return s.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Init
loadStats();
loadHistory(1);
</script>
</body>
</html>
