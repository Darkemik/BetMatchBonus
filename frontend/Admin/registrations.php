<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('registrations');
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];

// Függőben lévő regisztrációk (is_active=0, is_verified=0)
$pending = $conn->query("
    SELECT id, username, email, full_name, pre_name, family_name, sure_name,
           mother_full_name, birthplace, birth_date, phone, mobile_number,
           country, city, postal_code, address,
           id_image_first, id_image_second, address_image,
           created_at
    FROM Users
    WHERE is_active = 0 AND is_verified = 0
    ORDER BY created_at DESC
");
$pendingCount = $pending ? $pending->num_rows : 0;

// Mai regisztrációk
$todayCount = $conn->query("
    SELECT COUNT(*) AS c FROM Users
    WHERE is_active = 0 AND is_verified = 0
    AND DATE(created_at) = CURDATE()
")->fetch_assoc()['c'];

// Összesen jóváhagyott
$approvedCount = $conn->query("
    SELECT COUNT(*) AS c FROM Users
    WHERE is_active = 1 AND is_verified = 1
")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Regisztrációk | Admin</title>
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
        .stat-card h3 { font-size: 2.5rem; }
        .stat-card p { color: #aaa; margin: 0; }
        .main-content { flex: 1; padding: 24px; min-width: 0; }

        .reg-card {
            background: #16213e; border-radius: 10px; padding: 20px;
            margin-bottom: 16px; border: 1px solid #2a2a4a;
            transition: border-color 0.2s;
        }
        .reg-card:hover { border-color: #0f3460; }
        .reg-card .reg-header {
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; gap: 12px;
        }
        .reg-card .reg-header .username { font-weight: 600; font-size: 1.05rem; color: #fff; }
        .reg-card .reg-header .email { color: #888; font-size: 0.85rem; }
        .reg-card .reg-header .date { color: #666; font-size: 0.8rem; }
        .reg-card .reg-details { display: none; padding-top: 16px; border-top: 1px solid #2a2a4a; margin-top: 16px; }
        .reg-card.open .reg-details { display: block; }
        .reg-card .reg-header .toggle-icon { transition: transform 0.2s; color: #888; }
        .reg-card.open .reg-header .toggle-icon { transform: rotate(180deg); }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; }
        .detail-grid .detail-item label { color: #888; font-size: 0.75rem; margin-bottom: 2px; display: block; }
        .detail-grid .detail-item span { color: #eee; font-size: 0.9rem; }

        .doc-images { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
        .doc-images .doc-thumb {
            width: 160px; height: 110px; border-radius: 8px;
            object-fit: cover; border: 2px solid #2a2a4a; cursor: pointer;
            transition: border-color 0.2s;
        }
        .doc-images .doc-thumb:hover { border-color: #e94560; }
        .doc-images .doc-label {
            font-size: 0.7rem; color: #888; text-align: center; margin-top: 4px;
        }

        .action-btns { display: flex; gap: 10px; margin-top: 16px; }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }

        .empty-state {
            text-align: center; padding: 60px 20px; color: #9aa6b2;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }

        /* Lightbox */
        .lightbox-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9); z-index: 10000; justify-content: center; align-items: center;
            cursor: zoom-out;
        }
        .lightbox-overlay.show { display: flex; }
        .lightbox-overlay img { max-width: 90%; max-height: 90%; border-radius: 8px; }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="this.classList.remove('show')">
    <img src="" id="lightboxImg" alt="Dokumentum">
</div>

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
    <aside class="sidebar">
        <?php $activePage = 'registrations'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <main class="main-content">
        <!-- Stat cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#f5c518;"><?= $pendingCount ?></h3>
                    <p>Függőben lévő regisztráció</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#e94560;"><?= $todayCount ?></h3>
                    <p>Mai regisztráció</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3 style="color:#52b788;"><?= $approvedCount ?></h3>
                    <p>Összes jóváhagyott</p>
                </div>
            </div>
        </div>

        <h4 class="mb-3">📋 Függőben lévő regisztrációk</h4>

        <?php if ($pendingCount === 0): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color:#52b788;"></i>
                <h5>Nincs függőben lévő regisztráció</h5>
                <p class="text-muted">Minden regisztrációs kérelem feldolgozásra került.</p>
            </div>
        <?php else: ?>
            <?php while ($u = $pending->fetch_assoc()): ?>
            <div class="reg-card" id="regCard-<?= (int)$u['id'] ?>">
                <div class="reg-header" onclick="toggleCard(<?= (int)$u['id'] ?>)">
                    <div>
                        <span class="username"><?= htmlspecialchars($u['username']) ?></span>
                        <span class="email ms-2"><?= htmlspecialchars($u['email']) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="date"><?= date('Y.m.d H:i', strtotime($u['created_at'])) ?></span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                </div>
                <div class="reg-details">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Teljes név</label>
                            <span><?= htmlspecialchars($u['full_name'] ?? '-') ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Előtag / Családnév / Keresztnév</label>
                            <span><?= htmlspecialchars(($u['pre_name'] ?? '') . ' ' . ($u['family_name'] ?? '') . ' ' . ($u['sure_name'] ?? '')) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Anyja neve</label>
                            <span><?= htmlspecialchars($u['mother_full_name'] ?? '-') ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Születési hely / dátum</label>
                            <span><?= htmlspecialchars(($u['birthplace'] ?? '-') . ', ' . ($u['birth_date'] ?? '-')) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Telefon</label>
                            <span><?= htmlspecialchars($u['phone'] ?? $u['mobile_number'] ?? '-') ?></span>
                        </div>
                    </div>

                    <!-- Dokumentumok -->
                    <h6 class="mt-3 mb-2" style="color:#aaa;"><i class="fas fa-id-card me-1"></i>Dokumentumok</h6>
                    <div class="doc-images">
                        <?php
                        $imgBase = '../../backend/uploads/registrations/' . (int)$u['id'] . '/';
                        $docs = [
                            ['file' => $u['id_image_first'], 'label' => 'Személyi 1. oldal'],
                            ['file' => $u['id_image_second'], 'label' => 'Személyi 2. oldal'],
                            ['file' => $u['address_image'], 'label' => 'Lakcímkártya'],
                        ];
                        foreach ($docs as $doc):
                            if (!empty($doc['file'])):
                                $src = $imgBase . htmlspecialchars(basename($doc['file']));
                        ?>
                        <div style="text-align:center;">
                            <img src="<?= $src ?>" class="doc-thumb" alt="<?= htmlspecialchars($doc['label']) ?>"
                                 onclick="openLightbox(this.src)">
                            <div class="doc-label"><?= $doc['label'] ?></div>
                        </div>
                        <?php endif; endforeach; ?>
                    </div>

                    <!-- Akció gombok -->
                    <div class="action-btns">
                        <button class="btn btn-success" onclick="approveUser(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                            <i class="fas fa-check me-1"></i>Jóváhagyás
                        </button>
                        <button class="btn btn-danger" onclick="showRejectModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                            <i class="fas fa-times me-1"></i>Elutasítás
                        </button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </main>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#16213e;color:#eee;">
            <div class="modal-header" style="border-bottom-color:#e94560;">
                <h5 class="modal-title"><i class="fas fa-times-circle me-2 text-danger"></i>Regisztráció elutasítása</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectUserId">
                <p class="text-muted">Felhasználó: <strong id="rejectUsername" class="text-white"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Elutasítás oka <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejectReason" rows="4" placeholder="Írd le az elutasítás okát..."
                        style="background:#0f3460;border-color:#333;color:#fff;"></textarea>
                </div>
                <small class="text-muted">Az ok emailben kiküldésre kerül a felhasználónak. A fiók és az összes adat törlésre kerül.</small>
            </div>
            <div class="modal-footer" style="border-top-color:#333;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-danger" onclick="rejectUser()">
                    <i class="fas fa-trash me-1"></i>Elutasítás és törlés
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '../../backend/ApiRequest/admin_registration_action.php';

function toggleCard(id) {
    document.getElementById('regCard-' + id).classList.toggle('open');
}

function openLightbox(src) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lightboxImg').src = src;
    lb.classList.add('show');
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

async function approveUser(userId, username) {
    BmbPopup.confirm('Biztosan jóváhagyod ' + username + ' regisztrációját?', async function() {
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve', user_id: userId })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                const card = document.getElementById('regCard-' + userId);
                card.style.opacity = '0.4';
                card.style.pointerEvents = 'none';
                setTimeout(() => card.remove(), 1500);
                updatePendingCount(-1);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (e) {
            showToast('Hálózati hiba.', 'danger');
        }
    });
}

function showRejectModal(userId, username) {
    document.getElementById('rejectUserId').value = userId;
    document.getElementById('rejectUsername').textContent = username;
    document.getElementById('rejectReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

async function rejectUser() {
    const userId = parseInt(document.getElementById('rejectUserId').value);
    const reason = document.getElementById('rejectReason').value.trim();

    if (!reason) {
        showToast('Add meg az elutasítás okát!', 'warning');
        return;
    }

    try {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reject', user_id: userId, reason: reason })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
            const card = document.getElementById('regCard-' + userId);
            card.style.opacity = '0.4';
            card.style.pointerEvents = 'none';
            setTimeout(() => card.remove(), 1500);
            updatePendingCount(-1);
        } else {
            showToast(data.message, 'danger');
        }
    } catch (e) {
        showToast('Hálózati hiba.', 'danger');
    }
}

function updatePendingCount(delta) {
    const cards = document.querySelectorAll('.stat-card h3');
    if (cards.length > 0) {
        const cur = parseInt(cards[0].textContent) || 0;
        cards[0].textContent = Math.max(0, cur + delta);
    }
}
</script>
</body>
</html>
