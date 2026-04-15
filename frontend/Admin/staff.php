<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('SUPERADMIN');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];
$currentAdminId = (int)$_SESSION['admin_id'];

// Admin felhasználók lekérdezése
$admins = $conn->query("
    SELECT a.id, a.username, a.email, a.role_id, a.is_active, a.created_at, a.last_login,
           r.name AS role_name
    FROM AdminUsers a
    JOIN Roles r ON a.role_id = r.id
    ORDER BY a.role_id DESC, a.username ASC
");

// Szerepkörök lekérdezése
$roles = $conn->query("SELECT id, name AS role_name FROM Roles ORDER BY id ASC");
$roleList = [];
while ($r2 = $roles->fetch_assoc()) {
    $roleList[] = $r2;
}

// Statisztikák
$totalAdmins = 0;
$activeAdmins = 0;
$adminList = [];
if ($admins) {
    while ($a = $admins->fetch_assoc()) {
        $adminList[] = $a;
        $totalAdmins++;
        if ($a['is_active']) $activeAdmins++;
    }
}
$inactiveAdmins = $totalAdmins - $activeAdmins;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Staff (Adminok) | Admin | BetMatchBonus</title>
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
        .sidebar .nav-section {
            font-size: 0.7rem; text-transform: uppercase; color: #e94560;
            padding: 14px 20px 4px; letter-spacing: 1px;
        }
        .stat-card {
            background: #16213e; border-radius: 10px; padding: 24px; text-align: center;
        }
        .stat-card h3 { font-size: 2.2rem; }
        .stat-card p { color: #aaa; margin: 0; }
        .main-content { flex: 1; padding: 24px; min-width: 0; }

        .table-dark th { color: #e94560; }

        /* Role badges */
        .role-badge {
            display: inline-block; padding: 3px 12px; border-radius: 12px;
            font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .role-mod        { background: #1a3a2a; color: #52b788; }
        .role-admin      { background: #2a2e0a; color: #f5c518; }
        .role-superadmin  { background: #3a1a2a; color: #e94560; }

        .role-quick-select {
            appearance: none; -webkit-appearance: none;
            border-radius: 12px !important; font-size: 0.78rem !important;
            font-weight: 700 !important; text-transform: uppercase; letter-spacing: 0.5px;
            padding: 4px 30px 4px 12px !important; border: none !important;
            cursor: pointer; transition: all 0.2s;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23ffffff80'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 10px center !important;
            background-size: 10px 6px !important;
        }
        .role-quick-select:focus {
            box-shadow: 0 0 0 2px rgba(255,255,255,0.15) !important;
            outline: none;
        }
        .role-quick-select:hover { filter: brightness(1.2); }
        .role-quick-select.role-select-mod {
            background-color: #1a3a2a !important; color: #52b788 !important;
        }
        .role-quick-select.role-select-admin {
            background-color: #2a2e0a !important; color: #f5c518 !important;
        }

        /* Status badges */
        .status-active   { color: #52b788; }
        .status-inactive { color: #e94560; }

        /* Permissions */
        .perm-role-card {
            background: #0f1b30; border-radius: 10px; padding: 20px;
            border: 1px solid #1a3a5c;
        }
        .perm-role-header { font-size: 1.1rem; font-weight: 700; margin-bottom: 14px; }
        .perm-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; border-radius: 6px; margin-bottom: 6px;
            background: #16213e; transition: background 0.15s;
        }
        .perm-item:hover { background: #1a2a4a; }
        .perm-item label { cursor: pointer; margin: 0; font-size: 0.92rem; }
        .perm-item .form-check-input {
            width: 2.2em; height: 1.1em; cursor: pointer;
        }
        .perm-item .form-check-input:checked { background-color: #28a745; border-color: #28a745; }
        .perm-page-icon { width: 24px; text-align: center; margin-right: 10px; }

        /* Form styles */
        .form-section {
            background: #16213e; border-radius: 10px; padding: 24px;
            margin-bottom: 24px; border: 1px solid #1a3a5c;
        }
        .form-section h5 { color: #e94560; margin-bottom: 16px; }
        .form-control, .form-select {
            background: #111; border: 1px solid #444; color: #fff;
        }
        .form-control:focus, .form-select:focus {
            border-color: #e94560; box-shadow: 0 0 0 0.2rem rgba(233,69,96,.25); background: #111; color: #fff;
        }
        .form-control::placeholder { color: #fff; opacity: 0.5; }

        /* Edit modal */
        .modal-content { background: #16213e; border: 1px solid #1a3a5c; color: #eee; }
        .modal-header { border-bottom: 1px solid #1a3a5c; }
        .modal-footer { border-top: 1px solid #1a3a5c; }
        .btn-close { filter: invert(1); }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }

        .text-muted { color: #bbb !important; }
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
        <?php $activePage = 'staff'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <!-- Statisztikák -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#52b788;"><?= $totalAdmins ?></h3>
                    <p>Összes admin</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#28a745;"><?= $activeAdmins ?></h3>
                    <p>Aktív</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#e94560;"><?= $inactiveAdmins ?></h3>
                    <p>Letiltott</p>
                </div>
            </div>
        </div>

        <!-- Új admin hozzáadása -->
        <div class="form-section">
            <h5><i class="fas fa-user-plus me-2"></i>Új admin hozzáadása</h5>
            <form id="createAdminForm" onsubmit="return createAdmin(event)">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="newUsername" placeholder="Felhasználónév" required>
                    </div>
                    <div class="col-md-3">
                        <input type="email" class="form-control" id="newEmail" placeholder="Email cím" required>
                    </div>
                    <div class="col-md-2">
                        <input type="password" class="form-control" id="newPassword" placeholder="Jelszó" required minlength="6">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="newRole">
                            <?php foreach ($roleList as $rl): ?>
                            <option value="<?= $rl['id'] ?>"><?= htmlspecialchars($rl['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100" id="createBtn">
                            <i class="fas fa-plus me-1"></i>Létrehozás
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Admin lista -->
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Felhasználónév</th>
                        <th>Email</th>
                        <th>Szerepkör</th>
                        <th>Státusz</th>
                        <th>Utolsó belépés</th>
                        <th>Létrehozva</th>
                        <th class="text-center">Műveletek</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adminList as $admin): ?>
                    <?php
                        $isSelf = ((int)$admin['id'] === $currentAdminId);
                        $roleCls = 'role-mod';
                        if ($admin['role_name'] === 'ADMIN') $roleCls = 'role-admin';
                        if ($admin['role_name'] === 'SUPERADMIN') $roleCls = 'role-superadmin';
                    ?>
                    <tr>
                        <td class="text-muted">#<?= $admin['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($admin['username']) ?></strong>
                            <?php if ($isSelf): ?>
                                <span class="badge bg-info ms-1" style="font-size:0.65rem;">TE</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($admin['email']) ?></td>
                        <td>
                            <?php if (!$isSelf && $admin['role_name'] !== 'SUPERADMIN'): ?>
                                <?php $selectCls = (int)$admin['role_id'] === 2 ? 'role-select-admin' : 'role-select-mod'; ?>
                                <select class="role-quick-select <?= $selectCls ?>"
                                        data-admin-id="<?= $admin['id'] ?>">
                                    <?php foreach ($roleList as $rl): ?>
                                        <?php if ($rl['role_name'] !== 'SUPERADMIN'): ?>
                                        <option value="<?= $rl['id'] ?>" <?= (int)$rl['id'] === (int)$admin['role_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rl['role_name']) ?>
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span class="role-badge <?= $roleCls ?>"><?= htmlspecialchars($admin['role_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($admin['is_active']): ?>
                                <i class="fas fa-circle status-active me-1" style="font-size:0.6rem;"></i> Aktív
                            <?php else: ?>
                                <i class="fas fa-circle status-inactive me-1" style="font-size:0.6rem;"></i> Letiltva
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= $admin['last_login'] ? date('Y.m.d H:i', strtotime($admin['last_login'])) : '—' ?></td>
                        <td class="text-muted"><?= date('Y.m.d', strtotime($admin['created_at'])) ?></td>
                        <td class="text-center">
                            <?php if (!$isSelf): ?>
                                <!-- Szerkesztés -->
                                <button class="btn btn-sm btn-outline-warning me-1" title="Szerkesztés"
                                    onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($admin['email'], ENT_QUOTES) ?>', <?= $admin['role_id'] ?>)">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <!-- Jelszó reset -->
                                <button class="btn btn-sm btn-outline-info me-1" title="Jelszó visszaállítás"
                                    onclick="openPasswordModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-key"></i>
                                </button>
                                <!-- Aktív/Inaktív toggle -->
                                <?php if ($admin['is_active']): ?>
                                    <button class="btn btn-sm btn-outline-danger me-1" title="Letiltás"
                                        onclick="openBanModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success me-1" title="Aktiválás"
                                        onclick="toggleActive(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <!-- Törlés -->
                                <button class="btn btn-sm btn-outline-danger" title="Törlés"
                                    onclick="openDeleteModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:0.8rem;">— saját fiók —</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Szerepkör jogosultságok -->
        <div class="form-section mt-4" id="permissionsSection">
            <h5><i class="fas fa-shield-alt me-2"></i>Szerepkör jogosultságok</h5>
            <p class="text-muted" style="font-size:0.85rem;">Állítsd be, hogy az egyes szerepkörök mely admin oldalakat láthatják. A SUPERADMIN mindig mindenhez hozzáfér.</p>
            <div id="permissionsLoader" class="text-center py-4">
                <div class="spinner-border text-danger" role="status"><span class="visually-hidden">Betöltés...</span></div>
            </div>
            <div id="permissionsContent" style="display:none;"></div>
        </div>

    </main>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Admin szerkesztése</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editAdminId">
                <div class="mb-3">
                    <label class="form-label">Felhasználónév</label>
                    <input type="text" class="form-control" id="editUsername" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="editEmail" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Szerepkör</label>
                    <select class="form-select" id="editRole">
                        <?php foreach ($roleList as $rl): ?>
                        <option value="<?= $rl['id'] ?>"><?= htmlspecialchars($rl['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-warning" onclick="saveEdit()">
                    <i class="fas fa-save me-1"></i>Mentés
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Password Reset Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Jelszó visszaállítás</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pwAdminId">
                <p class="text-muted mb-3">Admin: <strong id="pwAdminName"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Új jelszó</label>
                    <input type="password" class="form-control" id="newAdminPassword" required minlength="6" placeholder="Min. 6 karakter">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-info" onclick="resetPassword()">
                    <i class="fas fa-key me-1"></i>Beállítás
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Ban Modal -->
<div class="modal fade" id="banModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom-color: #e94560;">
                <h5 class="modal-title"><i class="fas fa-ban me-2 text-danger"></i>Admin letiltása</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="banAdminId">
                <p>Biztosan letiltod <strong id="banAdminName" class="text-danger"></strong> admin fiókját?</p>
                <div class="mb-3">
                    <label class="form-label">Indoklás (megjelenik az emailben)</label>
                    <textarea class="form-control" id="banReason" rows="3" placeholder="Írd le a letiltás okát..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-danger" onclick="confirmBan()">
                    <i class="fas fa-ban me-1"></i>Letiltás
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom-color: #e94560;">
                <h5 class="modal-title"><i class="fas fa-trash me-2 text-danger"></i>Admin törlése</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="delAdminId">
                <div class="alert alert-danger py-2" style="background:#3a1a1a; border-color:#e94560; color:#ff8a8a;">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>FIGYELEM!</strong> Ez a művelet nem vonható vissza!
                </div>
                <p>Biztosan törlöd <strong id="delAdminName" class="text-danger"></strong> admin fiókját?</p>
                <div class="mb-3">
                    <label class="form-label">Indoklás (megjelenik az emailben)</label>
                    <textarea class="form-control" id="delReason" rows="3" placeholder="Írd le a törlés okát..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash me-1"></i>Végleges törlés
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API_URL = '../../backend/ApiRequest/admin_staff_action.php';

/* ━━━ Szerepkör gyorsváltás (PATCH) ━━━ */
document.querySelectorAll('.role-quick-select').forEach(select => {
    function updateSelectColor(sel) {
        sel.classList.remove('role-select-mod', 'role-select-admin');
        sel.classList.add(parseInt(sel.value) === 2 ? 'role-select-admin' : 'role-select-mod');
    }
    select.addEventListener('change', async function() {
        const adminId = parseInt(this.dataset.adminId);
        const roleId = parseInt(this.value);
        const roleName = this.options[this.selectedIndex].text;
        updateSelectColor(this);

        if (!confirm(`Biztosan módosítod a szerepkört: ${roleName}?`)) {
            // Visszaállítás az eredeti értékre
            this.value = this.dataset.originalValue || this.querySelector('[selected]')?.value;
            return;
        }

        try {
            const res = await fetch(API_URL, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ admin_id: adminId, role_id: roleId })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message, 'danger');
                this.value = this.dataset.originalValue || this.querySelector('[selected]')?.value;
            }
        } catch (e) {
            showToast('Hálózati hiba', 'danger');
        }
    });
    // Eredeti érték mentése
    select.dataset.originalValue = select.value;
});

function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 show`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

async function apiCall(body) {
    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    return await res.json();
}

/* ━━━ Létrehozás ━━━ */
async function createAdmin(e) {
    e.preventDefault();
    const btn = document.getElementById('createBtn');
    btn.disabled = true;

    const data = await apiCall({
        action: 'create',
        username: document.getElementById('newUsername').value.trim(),
        email: document.getElementById('newEmail').value.trim(),
        password: document.getElementById('newPassword').value,
        role_id: parseInt(document.getElementById('newRole').value)
    });

    if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(data.message, 'danger');
        btn.disabled = false;
    }
}

/* ━━━ Szerkesztés modal ━━━ */
function openEditModal(id, username, email, roleId) {
    document.getElementById('editAdminId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRole').value = roleId;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

async function saveEdit() {
    const data = await apiCall({
        action: 'update',
        admin_id: parseInt(document.getElementById('editAdminId').value),
        username: document.getElementById('editUsername').value.trim(),
        email: document.getElementById('editEmail').value.trim(),
        role_id: parseInt(document.getElementById('editRole').value)
    });

    if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(data.message, 'danger');
    }
}

/* ━━━ Jelszó reset modal ━━━ */
function openPasswordModal(id, username) {
    document.getElementById('pwAdminId').value = id;
    document.getElementById('pwAdminName').textContent = username;
    document.getElementById('newAdminPassword').value = '';
    new bootstrap.Modal(document.getElementById('passwordModal')).show();
}

async function resetPassword() {
    const pw = document.getElementById('newAdminPassword').value;
    if (pw.length < 6) {
        showToast('A jelszó legalább 6 karakter legyen!', 'danger');
        return;
    }

    const data = await apiCall({
        action: 'reset_password',
        admin_id: parseInt(document.getElementById('pwAdminId').value),
        new_password: pw
    });

    if (data.success) {
        showToast(data.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
    } else {
        showToast(data.message, 'danger');
    }
}

/* ━━━ Aktív/Inaktív toggle ━━━ */
// Aktiválás (nincs modal, egyszerű confirm)
async function toggleActive(id, username) {
    if (!confirm(`Biztosan aktiválod ${username} fiókját?`)) return;

    const data = await apiCall({ action: 'toggle_active', admin_id: id });

    if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(data.message, 'danger');
    }
}

// Letiltás (modal indoklással)
function openBanModal(id, username) {
    document.getElementById('banAdminId').value = id;
    document.getElementById('banAdminName').textContent = username;
    document.getElementById('banReason').value = '';
    new bootstrap.Modal(document.getElementById('banModal')).show();
}

async function confirmBan() {
    const data = await apiCall({
        action: 'toggle_active',
        admin_id: parseInt(document.getElementById('banAdminId').value),
        reason: document.getElementById('banReason').value.trim()
    });

    if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(data.message, 'danger');
    }
}

/* ━━━ Törlés (modal indoklással) ━━━ */
function openDeleteModal(id, username) {
    document.getElementById('delAdminId').value = id;
    document.getElementById('delAdminName').textContent = username;
    document.getElementById('delReason').value = '';
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

async function confirmDelete() {
    const data = await apiCall({
        action: 'delete',
        admin_id: parseInt(document.getElementById('delAdminId').value),
        reason: document.getElementById('delReason').value.trim()
    });

    if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(data.message, 'danger');
    }
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   Szerepkör jogosultságok
   ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
const PERM_API = '../../backend/ApiRequest/admin_permissions.php';

const PAGE_META = {
    dashboard:          { icon: '👥', label: 'Felhasználók' },
    registrations:      { icon: '📋', label: 'Regisztrációk' },
    data_verification:  { icon: '🔍', label: 'Adatellenőrzés' },
    tickets:            { icon: '🎫', label: 'Szelvények' },
    bonuses:            { icon: '🎁', label: 'Bónuszok' },
    deposits:           { icon: '💰', label: 'Befizetések' },
    withdrawals:        { icon: '💸', label: 'Kifizetések' },
    statistics:         { icon: '📊', label: 'Statisztikák' },
    notifications:      { icon: '🔔', label: 'Értesítések' }
};

const ROLE_COLORS = { 1: '#52b788', 2: '#f5c518' };
const ROLE_ICONS  = { 1: 'fa-user-shield', 2: 'fa-user-cog' };

async function loadPermissions() {
    try {
        const res = await fetch(PERM_API);
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        renderPermissions(data.roles);
    } catch (e) {
        document.getElementById('permissionsLoader').innerHTML =
            '<p class="text-danger">Hiba a jogosultságok betöltésekor.</p>';
    }
}

function renderPermissions(roles) {
    const container = document.getElementById('permissionsContent');
    let html = '<div class="row g-4">';

    roles.forEach(role => {
        const color = ROLE_COLORS[role.role_id] || '#ccc';
        const icon = ROLE_ICONS[role.role_id] || 'fa-user';
        html += `
        <div class="col-md-6">
            <div class="perm-role-card">
                <div class="perm-role-header d-flex align-items-center gap-2">
                    <i class="fas ${icon}" style="color:${color};"></i>
                    <span style="color:${color};">${role.role_name}</span>
                    <small class="text-muted ms-2" style="font-weight:400; font-size:0.78rem;">${role.description || ''}</small>
                </div>
                <div id="permItems-${role.role_id}">`;

        Object.keys(PAGE_META).forEach(pageKey => {
            const meta = PAGE_META[pageKey];
            const checked = role.permissions[pageKey] ? 'checked' : '';
            html += `
                <div class="perm-item">
                    <label for="perm-${role.role_id}-${pageKey}" class="d-flex align-items-center">
                        <span class="perm-page-icon">${meta.icon}</span>
                        ${meta.label}
                    </label>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                            id="perm-${role.role_id}-${pageKey}"
                            data-role="${role.role_id}" data-page="${pageKey}" ${checked}>
                    </div>
                </div>`;
        });

        html += `
                </div>
                <button class="btn btn-sm btn-outline-success mt-3 w-100" onclick="savePermissions(${role.role_id})">
                    <i class="fas fa-save me-1"></i>Mentés (${role.role_name})
                </button>
            </div>
        </div>`;
    });

    html += '</div>';
    container.innerHTML = html;
    document.getElementById('permissionsLoader').style.display = 'none';
    container.style.display = 'block';
}

async function savePermissions(roleId) {
    const perms = {};
    Object.keys(PAGE_META).forEach(pageKey => {
        const cb = document.getElementById(`perm-${roleId}-${pageKey}`);
        perms[pageKey] = cb ? cb.checked : false;
    });

    const res = await fetch(PERM_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ role_id: roleId, permissions: perms })
    });
    const data = await res.json();

    if (data.success) {
        showToast(data.message, 'success');
    } else {
        showToast(data.message, 'danger');
    }
}

// Betöltés induláskor
loadPermissions();
</script>
</body>
</html>
