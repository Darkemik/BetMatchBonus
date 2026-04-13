<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('SUPERADMIN');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Rendszerbeállítások | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body { background: #1a1a2e; color: #eee; }
        .navbar-admin { background: #16213e; }
        .sidebar {
            background: #16213e; min-height: calc(100vh - 56px);
            padding: 20px 0; width: 220px; flex-shrink: 0;
        }
        .sidebar .nav-link { color: #ccc; padding: 10px 20px; display: block; }
        .sidebar .nav-link:hover { color: #fff; background: #0f3460; }
        .sidebar .nav-section {
            font-size: 0.7rem; text-transform: uppercase; color: #666;
            padding: 14px 20px 4px; letter-spacing: 1px;
        }
        .main-content { flex: 1; padding: 24px; min-width: 0; }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }

        .settings-category {
            background: #16213e; border-radius: 10px; padding: 20px 24px;
            margin-bottom: 20px; border: 1px solid #2a2a4a;
        }
        .settings-category h5 {
            margin: 0 0 16px; padding-bottom: 10px;
            border-bottom: 1px solid #2a2a4a; display: flex; align-items: center; gap: 10px;
        }
        .settings-category h5 .cat-icon {
            width: 34px; height: 34px; border-radius: 8px; display: flex;
            align-items: center; justify-content: center; font-size: 0.95rem;
        }

        .setting-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid #1a1a2e; gap: 12px;
        }
        .setting-row:last-child { border-bottom: none; }
        .setting-info { flex: 1; }
        .setting-info .setting-label { font-weight: 600; font-size: 0.9rem; color: #eee; }
        .setting-info .setting-desc { font-size: 0.75rem; color: #888; margin-top: 2px; }
        .setting-input {
            width: 140px; background: #0f3460; border: 1px solid #333;
            color: #fff; font-size: 0.9rem; padding: 6px 10px; border-radius: 6px;
            text-align: right;
        }
        .setting-input:focus {
            border-color: #e94560; outline: none;
            box-shadow: 0 0 0 0.2rem rgba(233,69,96,.25);
        }
        .setting-input.changed {
            border-color: #f5c518; background: #2a2a10;
        }

        .save-bar {
            position: sticky; bottom: 0; background: #16213e;
            padding: 16px 24px; border-top: 2px solid #e94560;
            display: none; align-items: center; justify-content: space-between;
            border-radius: 8px 8px 0 0; margin: 0 -24px -24px;
        }
        .save-bar.visible { display: flex; }
        .save-bar .changes-count { color: #f5c518; font-weight: 600; }

        .loader { text-align: center; padding: 60px; }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- Navbar -->
<nav class="navbar navbar-admin px-4 d-flex justify-content-between" style="height:56px;">
    <div class="d-flex align-items-center gap-3">
        <img src="../../img/logo.png" alt="logo" style="width:40px;">
        <span class="text-white fw-bold fs-5">Admin – Rendszerbeállítások</span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted">
            <?= htmlspecialchars($_SESSION['admin_username']) ?>
            <span class="badge bg-danger"><?= htmlspecialchars($role) ?></span>
        </span>
        <a href="/BetMatchBonus/backend/Auth/admin_logout.php" class="btn btn-outline-danger btn-sm">Kijelentkezés</a>
    </div>
</nav>

<div class="d-flex">
    <aside class="sidebar">
        <?php $activePage = 'settings'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <main class="main-content">
        <div class="loader" id="loader">
            <div class="spinner-border text-danger" role="status"><span class="visually-hidden">Betöltés...</span></div>
        </div>
        <div id="settingsContainer"></div>

        <div class="save-bar" id="saveBar">
            <div>
                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                <span class="changes-count" id="changesCount">0</span> módosítás mentésre vár
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" onclick="resetAll()">
                    <i class="fas fa-undo me-1"></i>Visszaállítás
                </button>
                <button class="btn btn-success" onclick="saveSettings()">
                    <i class="fas fa-save me-1"></i>Mentés
                </button>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '../../backend/ApiRequest/admin_settings.php';

const CATEGORY_META = {
    deposit:      { label: 'Befizetés',          icon: '💰', color: '#52b788' },
    withdrawal:   { label: 'Kifizetés',           icon: '💸', color: '#f5c518' },
    betting:      { label: 'Fogadás',             icon: '🎫', color: '#4cc9f0' },
    security:     { label: 'Biztonság',           icon: '🔐', color: '#e94560' },
    registration: { label: 'Regisztráció',        icon: '📋', color: '#b794f6' }
};

let originalValues = {};
let currentSettings = [];

async function loadSettings() {
    try {
        const res = await fetch(API);
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        currentSettings = data.settings;
        renderSettings(data.settings);
    } catch (e) {
        document.getElementById('loader').innerHTML =
            '<p class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>Hiba a beállítások betöltésekor.</p>';
    }
}

function renderSettings(settings) {
    const container = document.getElementById('settingsContainer');
    const grouped = {};

    settings.forEach(s => {
        if (!grouped[s.category]) grouped[s.category] = [];
        grouped[s.category].push(s);
        originalValues[s.setting_key] = s.setting_value;
    });

    let html = '';

    for (const [cat, items] of Object.entries(grouped)) {
        const meta = CATEGORY_META[cat] || { label: cat, icon: '⚙️', color: '#888' };
        html += `
        <div class="settings-category">
            <h5>
                <span class="cat-icon" style="background: ${meta.color}22; color: ${meta.color};">${meta.icon}</span>
                <span style="color: ${meta.color};">${meta.label}</span>
            </h5>`;

        items.forEach(s => {
            const step = s.setting_value.includes('.') ? '0.01' : '1';
            html += `
            <div class="setting-row">
                <div class="setting-info">
                    <div class="setting-label">${escHtml(s.label)}</div>
                    <div class="setting-desc">${escHtml(s.description || '')}</div>
                </div>
                <input type="number" class="setting-input" id="input-${s.setting_key}"
                       value="${escHtml(s.setting_value)}" step="${step}" min="0"
                       data-key="${s.setting_key}" data-original="${escHtml(s.setting_value)}"
                       oninput="onSettingChange(this)">
            </div>`;
        });

        html += '</div>';
    }

    container.innerHTML = html;
    document.getElementById('loader').style.display = 'none';
}

function onSettingChange(input) {
    const original = input.dataset.original;
    const current = input.value;

    if (current !== original) {
        input.classList.add('changed');
    } else {
        input.classList.remove('changed');
    }

    updateSaveBar();
}

function updateSaveBar() {
    const changed = document.querySelectorAll('.setting-input.changed');
    const bar = document.getElementById('saveBar');
    const count = document.getElementById('changesCount');

    count.textContent = changed.length;
    bar.classList.toggle('visible', changed.length > 0);
}

function resetAll() {
    document.querySelectorAll('.setting-input').forEach(input => {
        input.value = input.dataset.original;
        input.classList.remove('changed');
    });
    updateSaveBar();
}

async function saveSettings() {
    const changed = document.querySelectorAll('.setting-input.changed');
    if (changed.length === 0) return;

    const updates = {};
    changed.forEach(input => {
        updates[input.dataset.key] = input.value;
    });

    try {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ settings: updates })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            // Update originals
            changed.forEach(input => {
                input.dataset.original = input.value;
                input.classList.remove('changed');
            });
            updateSaveBar();
        } else {
            showToast(data.message, 'danger');
        }
    } catch (e) {
        showToast('Hálózati hiba.', 'danger');
    }
}

function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const id = 'toast-' + Date.now();
    container.innerHTML += `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;
    setTimeout(() => { const el = document.getElementById(id); if (el) el.remove(); }, 4000);
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

loadSettings();
</script>
</body>
</html>
