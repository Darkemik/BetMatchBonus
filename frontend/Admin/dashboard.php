<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('dashboard');
$perms = get_role_permissions();

// Statistics
$userCount  = $conn->query("SELECT COUNT(*) AS c FROM Users WHERE is_active = 1 AND is_verified = 1")->fetch_assoc()['c'];
$matchCount = $conn->query("SELECT COUNT(*) AS c FROM Events")->fetch_assoc()['c'];
$champCount = $conn->query("SELECT COUNT(*) AS c FROM Competitions")->fetch_assoc()['c'];

// Keresés
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchWhere = ' WHERE is_active = 1 AND is_verified = 1';
$searchParam = null;
if ($searchTerm !== '') {
    $searchWhere .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ? OR CAST(id AS CHAR) = ?)";
    $searchParam = '%' . $searchTerm . '%';
}

// All users
$usersQuery = "SELECT id, username, email, full_name, mobile_number, city, postal_code, address, 
               balance, winnings_balance, bonus_balance,
               is_active, is_verified, data_verified, birth_date, created_at, updated_at
               FROM Users" . $searchWhere . " ORDER BY id DESC";

if ($searchParam) {
    $usersStmt = $conn->prepare($usersQuery);
    $usersStmt->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchTerm);
    $usersStmt->execute();
    $allUsers = $usersStmt->get_result();
    $usersStmt->close();
} else {
    $allUsers = $conn->query($usersQuery);
}

$role = $_SESSION['admin_role'];

$role = $_SESSION['admin_role'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | BetMatchBonus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body { background: #1a1a2e; color: #eee; }
        p { color: #e6e6e6 !important; }
        .text-muted { color: #9aa6b2 !important; }
        .navbar-admin { background: #16213e; }
        .sidebar {
            background: #16213e;
            min-height: calc(100vh - 56px);
            padding: 20px 0;
            width: 220px;
            flex-shrink: 0;
        }
        .sidebar .nav-link { color: #ccc; padding: 10px 20px; display: block; }
        .sidebar .nav-link:hover { color: #fff; background: #0f3460; }
        .sidebar .nav-section {
            font-size: 0.7rem; text-transform: uppercase; color: #e94560;
            padding: 14px 20px 4px; letter-spacing: 1px;
        }
        .stat-card {
            background: #16213e; border-radius: 10px; padding: 24px; text-align: center;
        }
        .stat-card h3 { color: #e94560; font-size: 2.5rem; }
        .stat-card p  { color: #aaa; margin: 0; }
        .table-dark th { color: #e94560; }
        .main-content { flex: 1; padding: 24px; min-width: 0; }

        /* User row */
        .user-row { cursor: pointer; transition: background 0.2s; }
        .user-row:hover { background: #0f3460 !important; }
        .user-row.active-row { background: #0f3460 !important; }

        /* User details panel */
        .user-detail-panel {
            display: none; background: #16213e; border-radius: 0 0 8px 8px;
        }
        .user-detail-panel.show { display: table-row; }
        .user-detail-panel td { padding: 0 !important; }
        .detail-inner { padding: 20px 24px; }
        .detail-inner label { color: #aaa; font-size: 0.78rem; margin-bottom: 2px; }
        .detail-inner .form-control {
            background: #0f3460; border: 1px solid #333; color: #fff; font-size: 0.85rem;
        }
        .detail-inner .form-control:focus {
            background: #0f3460; border-color: #e94560; color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(233,69,96,.25);
        }
        .detail-inner .form-check-input { background-color: #0f3460; border-color: #555; }
        .detail-inner .form-check-input:checked { background-color: #e94560; border-color: #e94560; }

        .balance-badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .balance-main { background: #1b4332; color: #52b788; }
        .balance-win { background: #3a2e0a; color: #f5c518; }
        .balance-bonus { background: #2d1b4e; color: #b794f6; }

        .msg-box { background: #2a1a1a; border: 1px solid #4a2a2a; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .msg-box textarea.msg-text {
            background: #111 !important; border: 1px solid #444; color: #fff; font-size: 1rem;
            resize: vertical; min-height: 100px; width: 100%;
        }
        .msg-box textarea.msg-text::placeholder { color: #fff; opacity: 0.7; }
        .msg-box textarea.msg-text:focus { background: #111 !important; border-color: #e94560; outline: none; box-shadow: 0 0 0 0.2rem rgba(233,69,96,.25); }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    </style>
</head>
<body>

<!-- Toast notifications -->
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
        <?php $activePage = 'dashboard'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <!-- Stat cards -->
        <div class="row g-4 mb-4" id="users">
            <div class="col-md-4">
                <div class="stat-card"><h3><?= $userCount ?></h3><p>Regisztrált felhasználó</p></div>
            </div>
            <div class="col-md-4">
                <div class="stat-card"><h3><?= $matchCount ?></h3><p>Meccs az adatbázisban</p></div>
            </div>
            <div class="col-md-4">
                <div class="stat-card"><h3><?= $champCount ?></h3><p>Bajnokság</p></div>
            </div>
        </div>

        <!-- Search -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">👥 Felhasználók</h4>
            <form method="GET" class="d-flex gap-2" style="max-width:450px;">
                <input type="text" name="search" class="form-control" placeholder="Keresés: név, email, ID..."
                    value="<?= htmlspecialchars($searchTerm) ?>" style="background:#111;border-color:#444;color:#fff;font-size:1rem;">
                <button type="submit" class="btn btn-outline-info"><i class="fas fa-search"></i></button>
                <?php if ($searchTerm): ?>
                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users table -->
        <div class="table-responsive shadow-sm" style="border-radius: 8px; overflow: hidden;">
            <table class="table table-dark table-striped mb-0" id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Teljes név</th>
                        <th>Egyenleg</th>
                        <th>Regisztráció</th>
                        <th class="text-center">Státusz</th>
                        <th class="text-center" style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($allUsers && $allUsers->num_rows > 0): ?>
                    <?php while ($u = $allUsers->fetch_assoc()): ?>
                    <!-- Alap sor -->
                    <tr class="user-row" data-uid="<?= (int)$u['id'] ?>">
                        <td class="fw-bold"><?= (int)$u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><small><?= htmlspecialchars($u['email']) ?></small></td>
                        <td><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
                        <td>
                            <span class="balance-badge balance-main"><?= number_format($u['balance'], 0, ',', ' ') ?></span>
                            <span class="balance-badge balance-win"><?= number_format($u['winnings_balance'], 0, ',', ' ') ?></span>
                            <span class="balance-badge balance-bonus"><?= number_format($u['bonus_balance'], 0, ',', ' ') ?></span>
                        </td>
                        <td><small><?= date('Y.m.d H:i', strtotime($u['created_at'])) ?></small></td>
                        <td class="text-center">
                            <?php if((int)$u['is_active']): ?>
                                <span class="badge bg-success">Aktív</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inaktív</span>
                            <?php endif; ?>
                            <?php if((int)$u['data_verified']): ?>
                                <span class="badge bg-info">Ellenőrizve</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><i class="fas fa-chevron-down text-muted toggle-icon"></i></td>
                    </tr>

                    <!-- Lenyitható részletek -->
                    <tr class="user-detail-panel" id="detail-<?= (int)$u['id'] ?>">
                        <td colspan="8">
                            <div class="detail-inner">
                                <div class="row g-3">
                                    <!-- Bal oldal: adatok -->
                                    <div class="col-md-7">
                                        <h6 style="color:#e94560;" class="mb-3"><i class="fas fa-user-edit"></i> Felhasználó szerkesztése <small class="text-muted">(#<?= $u['id'] ?>)</small></h6>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label>Username</label>
                                                <input type="text" class="form-control form-control-sm edit-field" data-field="username" value="<?= htmlspecialchars($u['username']) ?>">
                                            </div>
                                            <div class="col-6">
                                                <label>Email</label>
                                                <input type="email" class="form-control form-control-sm edit-field" data-field="email" value="<?= htmlspecialchars($u['email']) ?>">
                                            </div>
                                            <div class="col-6">
                                                <label>Teljes név</label>
                                                <input type="text" class="form-control form-control-sm edit-field" data-field="full_name" value="<?= htmlspecialchars($u['full_name'] ?? '') ?>">
                                            </div>
                                            <div class="col-6">
                                                <label>Születési dátum</label>
                                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($u['birth_date'] ?? '-') ?>" disabled title="Születési dátum nem módosítható">
                                            </div>
                                            <div class="col-6">
                                                <label>Telefonszám</label>
                                                <input type="text" class="form-control form-control-sm edit-field" data-field="mobile_number" value="<?= htmlspecialchars($u['mobile_number'] ?? '') ?>">
                                            </div>
                                            <div class="col-6">
                                                <label>Város</label>
                                                <input type="text" class="form-control form-control-sm edit-field" data-field="city" value="<?= htmlspecialchars($u['city'] ?? '') ?>">
                                            </div>
                                            <div class="col-4">
                                                <label>Irányítószám</label>
                                                <input type="text" class="form-control form-control-sm edit-field" data-field="postal_code" value="<?= htmlspecialchars($u['postal_code'] ?? '') ?>">
                                            </div>
                                            <div class="col-8">
                                                <label>Cím</label>
                                                <input type="text" class="form-control form-control-sm edit-field" data-field="address" value="<?= htmlspecialchars($u['address'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <!-- Státuszok (csak olvasható) -->
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            <?php if((int)$u['is_active']): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Fiók aktív</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-ban"></i> Fiók letiltva</span>
                                            <?php endif; ?>
                                            <?php if((int)$u['is_verified']): ?>
                                                <span class="badge bg-success"><i class="fas fa-user-check"></i> Regisztráció jóváhagyva</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="fas fa-clock"></i> Regisztráció várakozik</span>
                                            <?php endif; ?>
                                            <?php if((int)$u['data_verified']): ?>
                                                <span class="badge bg-info"><i class="fas fa-id-card"></i> Személyazonosság igazolva</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="fas fa-id-card"></i> Nincs igazolva</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="d-flex gap-2 mt-3">
                                            <button type="button" class="btn btn-warning btn-sm fw-bold btn-save-user" data-uid="<?= $u['id'] ?>">
                                                <i class="fas fa-database"></i> Felhasználó frissítése
                                            </button>
                                            <?php if((int)$u['is_active']): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm btn-toggle-active" data-uid="<?= $u['id'] ?>" data-active="1">
                                                    <i class="fas fa-user-slash"></i> Fiók letiltása
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success btn-sm btn-toggle-active" data-uid="<?= $u['id'] ?>" data-active="0">
                                                    <i class="fas fa-user-check"></i> Fiók aktiválása
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Jobb oldal: egyenlegek + üzenetküldés -->
                                    <div class="col-md-5">
                                        <h6 style="color:#4fc3f7;" class="mb-3"><i class="fas fa-wallet"></i> Egyenlegek</h6>
                                        <div class="d-flex flex-column gap-1 mb-3" style="font-size:0.85rem;">
                                            <div>💰 Egyenleg: <strong style="color:#52b788;"><?= number_format($u['balance'], 0, ',', ' ') ?> Ft</strong></div>
                                            <div>🏆 Nyeremény: <strong style="color:#f5c518;"><?= number_format($u['winnings_balance'], 0, ',', ' ') ?> Ft</strong></div>
                                            <div>🎁 Bónusz: <strong style="color:#b794f6;"><?= number_format($u['bonus_balance'], 0, ',', ' ') ?> Ft</strong></div>
                                        </div>

                                        <div class="msg-box">
                                            <h6 style="color:#e94560; font-size:0.85rem;"><i class="fas fa-envelope"></i> Üzenet küldése</h6>
                                            <textarea class="form-control form-control-sm msg-text" data-uid="<?= $u['id'] ?>" placeholder="Írd ide az üzeneted..." rows="3"></textarea>
                                            <button type="button" class="btn btn-sm btn-outline-warning mt-2 w-100 btn-send-msg" data-uid="<?= $u['id'] ?>">
                                                <i class="fas fa-paper-plane"></i> Üzenet küldése emailben
                                            </button>
                                        </div>

                                        <div class="mt-2" style="font-size:0.75rem;color:#666;">
                                            <div>Utolsó frissítés: <?= date('Y.m.d H:i', strtotime($u['updated_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Nincs találat.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const API_URL = '../../backend/ApiRequest/admin_update_user.php';

    function showToast(msg, type) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-' + type + ' border-0 show';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + msg + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        container.appendChild(toast);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 4000);
    }

    // Lenyitás/bezárás
    document.querySelectorAll('.user-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('input, button, a, select, textarea')) return;
            const uid = this.dataset.uid;
            const panel = document.getElementById('detail-' + uid);
            const icon = this.querySelector('.toggle-icon');

            // Bezárjuk a többi panelt
            document.querySelectorAll('.user-detail-panel.show').forEach(p => {
                if (p.id !== 'detail-' + uid) {
                    p.classList.remove('show');
                    const otherRow = document.querySelector('.user-row[data-uid="' + p.id.replace('detail-','') + '"]');
                    if (otherRow) {
                        otherRow.classList.remove('active-row');
                        otherRow.querySelector('.toggle-icon').classList.replace('fa-chevron-up', 'fa-chevron-down');
                    }
                }
            });

            panel.classList.toggle('show');
            this.classList.toggle('active-row');
            if (panel.classList.contains('show')) {
                icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
            } else {
                icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
            }
        });
    });

    // Felhasználó frissítése
    document.querySelectorAll('.btn-save-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const uid = this.dataset.uid;
            const panel = document.getElementById('detail-' + uid);
            const fields = panel.querySelectorAll('.edit-field');
            const formData = new FormData();
            formData.append('action', 'update_user');
            formData.append('user_id', uid);

            fields.forEach(f => {
                const field = f.dataset.field;
                if (f.type === 'checkbox') {
                    formData.append(field, f.checked ? '1' : '0');
                } else {
                    formData.append(field, f.value);
                }
            });

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mentés...';

            fetch(API_URL, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'danger');
                    if (data.success) setTimeout(() => location.reload(), 1500);
                })
                .catch(() => showToast('Hálózati hiba!', 'danger'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-database"></i> Felhasználó frissítése';
                });
        });
    });

    // Üzenet küldése
    document.querySelectorAll('.btn-send-msg').forEach(btn => {
        btn.addEventListener('click', function() {
            const uid = this.dataset.uid;
            const textarea = document.querySelector('.msg-text[data-uid="' + uid + '"]');
            const msg = textarea.value.trim();
            if (!msg) { showToast('Üres üzenet!', 'warning'); return; }

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('user_id', uid);
            formData.append('message', msg);

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Küldés...';

            fetch(API_URL, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'danger');
                    if (data.success) textarea.value = '';
                })
                .catch(() => showToast('Hálózati hiba!', 'danger'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Üzenet küldése emailben';
                });
        });
    });

    // Fiók letiltás / aktiválás
    document.querySelectorAll('.btn-toggle-active').forEach(btn => {
        btn.addEventListener('click', function() {
            const uid = this.dataset.uid;
            const currentlyActive = this.dataset.active === '1';
            const action = currentlyActive ? 'letiltani' : 'aktiválni';

            if (!confirm('Biztosan szeretnéd ' + action + ' ezt a felhasználót?')) return;

            const formData = new FormData();
            formData.append('action', 'toggle_active');
            formData.append('user_id', uid);
            formData.append('is_active', currentlyActive ? '0' : '1');

            btn.disabled = true;

            fetch(API_URL, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'danger');
                    if (data.success) setTimeout(() => location.reload(), 1000);
                })
                .catch(() => showToast('Hálózati hiba!', 'danger'))
                .finally(() => { btn.disabled = false; });
        });
    });

});
</script>
</body>
</html>
