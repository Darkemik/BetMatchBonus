<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('deposits');
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];

// Összesítő statisztikák
$stats = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(amount) AS total_sum,
        SUM(CASE WHEN payment_method = 'visa' THEN 1 ELSE 0 END) AS visa_count,
        SUM(CASE WHEN payment_method = 'visa' THEN amount ELSE 0 END) AS visa_sum,
        SUM(CASE WHEN payment_method = 'mastercard' THEN 1 ELSE 0 END) AS mc_count,
        SUM(CASE WHEN payment_method = 'mastercard' THEN amount ELSE 0 END) AS mc_sum,
        SUM(CASE WHEN payment_method = 'paypal' THEN 1 ELSE 0 END) AS pp_count,
        SUM(CASE WHEN payment_method = 'paypal' THEN amount ELSE 0 END) AS pp_sum
    FROM Transactions WHERE type = 'deposit' AND status = 'completed'
")->fetch_assoc();

// Mai befizetések
$today = date('Y-m-d');
$todayStats = $conn->prepare("
    SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
    FROM Transactions WHERE type = 'deposit' AND status = 'completed' AND DATE(created_at) = ?
");
$todayStats->bind_param("s", $today);
$todayStats->execute();
$todayData = $todayStats->get_result()->fetch_assoc();
$todayStats->close();

// Összes felhasználó befizetési adattal
$depositUsers = $conn->query("
    SELECT u.id, u.username, u.full_name, u.email, u.balance,
        (SELECT COUNT(*) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'deposit' AND t.status = 'completed') AS deposit_count,
        (SELECT COUNT(*) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'deposit' AND t.status = 'failed') AS refunded_count,
        (SELECT SUM(t.amount) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'deposit' AND t.status = 'completed') AS total_deposited
    FROM Users u
    WHERE u.is_active = 1 AND u.is_verified = 1
    ORDER BY total_deposited DESC, deposit_count DESC, u.id DESC
");

// Befizetések betöltése felhasználónként
$userDeposits = [];
if ($depositUsers && $depositUsers->num_rows > 0) {
    $depositUsers->data_seek(0);
    while ($du = $depositUsers->fetch_assoc()) {
        $uid = (int)$du['id'];
        $totalTx = (int)$du['deposit_count'] + (int)$du['refunded_count'];
        if ($totalTx > 0) {
            $dStmt = $conn->prepare("
                SELECT id, transaction_id, amount, payment_method, status, rejection_reason, description, created_at
                FROM Transactions
                WHERE user_id = ? AND type = 'deposit'
                ORDER BY created_at DESC
            ");
            $dStmt->bind_param("i", $uid);
            $dStmt->execute();
            $userDeposits[$uid] = $dStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $dStmt->close();
        }
    }
    $depositUsers->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Befizetések | Admin | BetMatchBonus</title>
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
        .stat-card {
            background: #16213e; border-radius: 10px; padding: 24px; text-align: center;
        }
        .stat-card h3 { font-size: 2.2rem; }
        .stat-card p { color: #aaa; margin: 0; }
        .main-content { flex: 1; padding: 24px; min-width: 0; }

        .table-dark th { color: #e94560; }
        .user-row { cursor: pointer; transition: background 0.2s; }
        .user-row:hover { background: #0f3460 !important; }
        .user-row.active-row { background: #0f3460 !important; }
        .user-detail-panel { display: none; background: #16213e; }
        .user-detail-panel.show { display: table-row; }
        .user-detail-panel td { padding: 0 !important; }
        .detail-inner { padding: 20px 24px; }

        /* Deposit cards */
        .dep-card {
            background: #0f1b30; border-radius: 8px; padding: 14px 18px;
            margin-bottom: 10px; border-left: 4px solid #28a745;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px;
        }
        .dep-amount { font-size: 1.3rem; font-weight: 700; color: #52b788; }
        .dep-method {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 0.78rem; font-weight: 600; text-transform: uppercase;
        }
        .method-visa       { background: #1a2a4a; color: #5b9bd5; }
        .method-mastercard  { background: #3a2a0a; color: #f5a623; }
        .method-paypal      { background: #1a2a3a; color: #00b4d8; }
        .dep-meta { color: #aaa; font-size: 0.82rem; }
        .dep-txid { color: #888; font-family: monospace; font-size: 0.78rem; }

        /* Refunded card */
        .dep-card.refunded { border-left-color: #e94560; opacity: 0.7; }
        .dep-card.refunded .dep-amount { color: #e94560; text-decoration: line-through; }
        .dep-refund-reason { background: #2a1a1a; border: 1px solid #4a2a2a; border-radius: 6px; padding: 6px 10px; margin-top: 6px; color: #e94560; font-size: 0.82rem; width: 100%; }
        .dep-status-refunded { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 600; background: #3a1a1a; color: #e94560; }
        .method-admin { background: #2a1a3a; color: #b388ff; }

        /* Manual deposit form */
        .manual-dep-box {
            background: #0a1628; border: 1px solid #1a3a5c; border-radius: 8px;
            padding: 14px; margin-top: 12px;
        }
        .manual-dep-box input[type="number"] {
            background: #111; border: 1px solid #444; color: #fff; font-size: 1rem;
            border-radius: 6px; padding: 6px 10px; width: 200px;
        }
        .manual-dep-box input:focus, .manual-dep-box textarea:focus {
            border-color: #28a745; outline: none; box-shadow: 0 0 0 0.2rem rgba(40,167,69,.25);
        }
        .manual-dep-box textarea {
            background: #111; border: 1px solid #444; color: #fff; font-size: 0.9rem;
            resize: vertical; min-height: 50px; width: 100%; border-radius: 6px; padding: 8px;
        }
        .manual-dep-box input::placeholder, .manual-dep-box textarea::placeholder { color: #fff; opacity: 0.5; }

        /* Refund reason box */
        .refund-reason-box { display: none; margin-top: 8px; }
        .refund-reason-box textarea {
            background: #111; border: 1px solid #444; color: #fff; font-size: 0.9rem;
            resize: vertical; min-height: 50px; width: 100%; border-radius: 6px; padding: 8px;
        }
        .refund-reason-box textarea::placeholder { color: #fff; opacity: 0.5; }
        .refund-reason-box textarea:focus {
            border-color: #e94560; outline: none; box-shadow: 0 0 0 0.2rem rgba(233,69,96,.25);
        }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }

        .balance-badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .balance-main { background: #1b4332; color: #52b788; }

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
        <span class="text-muted">
            <?= htmlspecialchars($_SESSION['admin_username']) ?>
            <span class="badge bg-danger"><?= htmlspecialchars($role) ?></span>
        </span>
        <a href="/BetMatchBonus/backend/Auth/admin_logout.php" class="btn btn-outline-danger btn-sm">Kijelentkezés</a>
    </div>
</nav>

<div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar">
        <?php $activePage = 'deposits'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <!-- Statisztikák -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#52b788;"><?= number_format((float)($stats['total_sum'] ?? 0), 0, ',', ' ') ?></h3>
                    <p>Összes befizetés (Ft)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#28a745;"><?= (int)($stats['total'] ?? 0) ?></h3>
                    <p>Tranzakciók száma</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#f5c518;"><?= number_format((float)($todayData['total'] ?? 0), 0, ',', ' ') ?></h3>
                    <p>Mai befizetés (Ft)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#5b9bd5;"><?= (int)($todayData['cnt'] ?? 0) ?></h3>
                    <p>Mai tranzakciók</p>
                </div>
            </div>
        </div>

        <!-- Módszer szerinti bontás -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card" style="border-left: 4px solid #5b9bd5;">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                        <i class="fab fa-cc-visa" style="font-size:1.5rem; color:#5b9bd5;"></i>
                        <span style="color:#5b9bd5; font-weight:600;">VISA</span>
                    </div>
                    <h3 style="color:#5b9bd5; font-size:1.6rem;"><?= number_format((float)($stats['visa_sum'] ?? 0), 0, ',', ' ') ?> Ft</h3>
                    <p><?= (int)($stats['visa_count'] ?? 0) ?> tranzakció</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-left: 4px solid #f5a623;">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                        <i class="fab fa-cc-mastercard" style="font-size:1.5rem; color:#f5a623;"></i>
                        <span style="color:#f5a623; font-weight:600;">MASTERCARD</span>
                    </div>
                    <h3 style="color:#f5a623; font-size:1.6rem;"><?= number_format((float)($stats['mc_sum'] ?? 0), 0, ',', ' ') ?> Ft</h3>
                    <p><?= (int)($stats['mc_count'] ?? 0) ?> tranzakció</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-left: 4px solid #00b4d8;">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                        <i class="fab fa-cc-paypal" style="font-size:1.5rem; color:#00b4d8;"></i>
                        <span style="color:#00b4d8; font-weight:600;">PAYPAL</span>
                    </div>
                    <h3 style="color:#00b4d8; font-size:1.6rem;"><?= number_format((float)($stats['pp_sum'] ?? 0), 0, ',', ' ') ?> Ft</h3>
                    <p><?= (int)($stats['pp_count'] ?? 0) ?> tranzakció</p>
                </div>
            </div>
        </div>

        <!-- Felhasználók táblázat -->
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th></th>
                        <th>Felhasználó</th>
                        <th>Email</th>
                        <th class="text-center">Befizetések</th>
                        <th class="text-end">Összes befizetés</th>
                        <th class="text-end">Egyenleg</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $depositUsers->data_seek(0);
                    while ($u = $depositUsers->fetch_assoc()):
                        $uid = (int)$u['id'];
                        $depCount = (int)$u['deposit_count'];
                        $totalDep = (float)($u['total_deposited'] ?? 0);
                    ?>
                    <tr class="user-row" onclick="togglePanel(<?= $uid ?>)">
                        <td style="width:30px;">
                            <i class="fas fa-chevron-right" id="arrow-<?= $uid ?>" style="transition:transform 0.2s; font-size:0.7rem; color:#888;"></i>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($u['username']) ?></strong>
                            <?php if ($u['full_name']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($u['full_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="text-center">
                            <?php if ($depCount > 0): ?>
                                <span class="badge bg-success"><?= $depCount ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($totalDep > 0): ?>
                                <strong style="color:#52b788;"><?= number_format($totalDep, 0, ',', ' ') ?> Ft</strong>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="balance-badge balance-main"><?= number_format((float)$u['balance'], 0, ',', ' ') ?> Ft</span>
                        </td>
                    </tr>
                    <!-- Detail panel -->
                    <tr class="user-detail-panel" id="panel-<?= $uid ?>">
                        <td colspan="6">
                            <div class="detail-inner">
                                <?php if (isset($userDeposits[$uid]) && count($userDeposits[$uid]) > 0): ?>
                                    <h6 class="mb-3" style="color:#e94560;">
                                        <i class="fas fa-receipt me-1"></i>Befizetések (<?= count($userDeposits[$uid]) ?>)
                                    </h6>
                                    <?php foreach ($userDeposits[$uid] as $dep):
                                        $isRefunded = ($dep['status'] === 'failed');
                                        $methodCls = 'method-visa';
                                        $methodLabel = strtoupper($dep['payment_method'] ?? '');
                                        if ($dep['payment_method'] === 'mastercard') $methodCls = 'method-mastercard';
                                        if ($dep['payment_method'] === 'paypal') $methodCls = 'method-paypal';
                                        if ($dep['payment_method'] === 'admin') { $methodCls = 'method-admin'; $methodLabel = 'ADMIN'; }
                                    ?>
                                    <div class="dep-card <?= $isRefunded ? 'refunded' : '' ?>">
                                        <div style="flex:1;">
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="dep-amount"><?= number_format((float)$dep['amount'], 0, ',', ' ') ?> Ft</span>
                                                <span class="dep-method <?= $methodCls ?>"><?= $methodLabel ?></span>
                                                <?php if ($isRefunded): ?>
                                                    <span class="dep-status-refunded">VISSZATÉRÍTVE</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($isRefunded && !empty($dep['rejection_reason'])): ?>
                                                <div class="dep-refund-reason">
                                                    <i class="fas fa-undo me-1"></i><?= htmlspecialchars($dep['rejection_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($dep['description']) && $dep['payment_method'] === 'admin'): ?>
                                                <div class="dep-meta mt-1"><i class="fas fa-comment me-1"></i><?= htmlspecialchars($dep['description']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!$isRefunded && $dep['status'] === 'completed'): ?>
                                                <!-- Refund gomb -->
                                                <div class="admin-actions">
                                                    <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); toggleRefundBox(<?= $dep['id'] ?>)">
                                                        <i class="fas fa-undo me-1"></i>Visszatérítés
                                                    </button>
                                                    <div class="refund-reason-box" id="refundBox-<?= $dep['id'] ?>">
                                                        <textarea id="refundReason-<?= $dep['id'] ?>" placeholder="Visszatérítés indoklása (opcionális)..."></textarea>
                                                        <div class="mt-2">
                                                            <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); doRefund(<?= $dep['id'] ?>)">
                                                                <i class="fas fa-check me-1"></i>Visszatérítés megerősítése
                                                            </button>
                                                            <button class="btn btn-sm btn-secondary ms-1" onclick="event.stopPropagation(); toggleRefundBox(<?= $dep['id'] ?>)">Mégse</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <div class="dep-meta"><?= date('Y.m.d H:i', strtotime($dep['created_at'])) ?></div>
                                            <div class="dep-txid"><?= htmlspecialchars($dep['transaction_id']) ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Nincs befizetés.</p>
                                <?php endif; ?>

                                <!-- Manuális jóváírás -->
                                <div class="manual-dep-box">
                                    <h6 style="color:#28a745; margin-bottom:10px;">
                                        <i class="fas fa-plus-circle me-1"></i>Manuális jóváírás
                                    </h6>
                                    <div class="d-flex align-items-end gap-3 flex-wrap">
                                        <div>
                                            <label class="form-label text-muted" style="font-size:0.8rem;">Összeg (Ft)</label>
                                            <input type="number" id="manualAmount-<?= $uid ?>" placeholder="Összeg" min="1">
                                        </div>
                                        <div style="flex:1; min-width:200px;">
                                            <label class="form-label text-muted" style="font-size:0.8rem;">Megjegyzés (opcionális)</label>
                                            <textarea id="manualNote-<?= $uid ?>" placeholder="Pl. ügyfélszolgálati jóváírás..." rows="1"></textarea>
                                        </div>
                                        <div>
                                            <button class="btn btn-success btn-sm" onclick="event.stopPropagation(); doManualDeposit(<?= $uid ?>)">
                                                <i class="fas fa-plus me-1"></i>Jóváírás
                                            </button>
                                        </div>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:0.75rem;">
                                        Elérhető egyenleg: <?= number_format((float)$u['balance'], 0, ',', ' ') ?> Ft
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API_URL = '../../backend/ApiRequest/admin_deposit_action.php';

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

function togglePanel(uid) {
    const panel = document.getElementById('panel-' + uid);
    const arrow = document.getElementById('arrow-' + uid);
    const row = panel.previousElementSibling;

    if (panel.classList.contains('show')) {
        panel.classList.remove('show');
        arrow.style.transform = 'rotate(0deg)';
        row.classList.remove('active-row');
    } else {
        document.querySelectorAll('.user-detail-panel.show').forEach(p => {
            p.classList.remove('show');
            const a = p.previousElementSibling.querySelector('.fa-chevron-right');
            if (a) a.style.transform = 'rotate(0deg)';
            p.previousElementSibling.classList.remove('active-row');
        });
        panel.classList.add('show');
        arrow.style.transform = 'rotate(90deg)';
        row.classList.add('active-row');
    }
}

/* ━━━ Visszatérítés ━━━ */
function toggleRefundBox(txId) {
    const box = document.getElementById('refundBox-' + txId);
    box.style.display = box.style.display === 'block' ? 'none' : 'block';
}

async function doRefund(txId) {
    if (!confirm('Biztosan visszatéríted ezt a befizetést? Az összeg levonásra kerül a felhasználó egyenlegéből.')) return;

    const reason = document.getElementById('refundReason-' + txId).value.trim();
    const data = await apiCall({ action: 'refund', transaction_id: txId, reason: reason });

    if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(data.message, 'danger');
    }
}

/* ━━━ Manuális jóváírás ━━━ */
async function doManualDeposit(uid) {
    const amount = parseFloat(document.getElementById('manualAmount-' + uid).value);
    const note = document.getElementById('manualNote-' + uid).value.trim();

    if (!amount || amount <= 0) {
        showToast('Adj meg érvényes összeget!', 'danger');
        return;
    }
    if (!confirm(`Biztosan jóváírsz ${amount.toLocaleString('hu')} Ft-ot?`)) return;

    const data = await apiCall({ action: 'manual_deposit', user_id: uid, amount: amount, note: note });

    if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(data.message, 'danger');
    }
}
</script>
</body>
</html>
