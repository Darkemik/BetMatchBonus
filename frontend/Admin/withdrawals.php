<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('withdrawals');
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];

// Összesítő statisztikák
$stats = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) AS pending_sum,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS completed_sum
    FROM Transactions WHERE type = 'withdrawal'
")->fetch_assoc();

// Összes felhasználó kifizetési adattal
$withdrawalUsers = $conn->query("
    SELECT u.id, u.username, u.full_name, u.email, u.balance, u.winnings_balance,
        (SELECT COUNT(*) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'withdrawal' AND t.status = 'pending') AS pending_count,
        (SELECT COUNT(*) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'withdrawal' AND t.status = 'completed') AS completed_count,
        (SELECT COUNT(*) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'withdrawal' AND t.status = 'rejected') AS rejected_count,
        (SELECT COUNT(*) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'withdrawal') AS total_count,
        (SELECT SUM(t.amount) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'withdrawal' AND t.status = 'completed') AS total_paid
    FROM Users u
    WHERE u.is_active = 1 AND u.is_verified = 1
    ORDER BY pending_count DESC, total_count DESC, u.id DESC
");

// Kifizetések betöltése felhasználónként
$userWithdrawals = [];
if ($withdrawalUsers && $withdrawalUsers->num_rows > 0) {
    $withdrawalUsers->data_seek(0);
    while ($wu = $withdrawalUsers->fetch_assoc()) {
        $uid = (int)$wu['id'];
        if ((int)$wu['total_count'] > 0) {
            $wStmt = $conn->prepare("
                SELECT id, transaction_id, amount, status, account_holder, account_number,
                       rejection_reason, created_at, updated_at
                FROM Transactions
                WHERE user_id = ? AND type = 'withdrawal'
                ORDER BY FIELD(status, 'pending', 'completed', 'rejected', 'cancelled', 'failed'), created_at DESC
            ");
            $wStmt->bind_param("i", $uid);
            $wStmt->execute();
            $userWithdrawals[$uid] = $wStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $wStmt->close();
        }
    }
    $withdrawalUsers->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Kifizetések | Admin | BetMatchBonus</title>
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
        .user-row { cursor: pointer; transition: background 0.2s; }
        .user-row:hover { background: #0f3460 !important; }
        .user-row.active-row { background: #0f3460 !important; }
        .user-detail-panel { display: none; background: #16213e; }
        .user-detail-panel.show { display: table-row; }
        .user-detail-panel td { padding: 0 !important; }
        .detail-inner { padding: 20px 24px; }

        /* Withdrawal cards */
        .wd-card {
            background: #0f1b30; border-radius: 8px; padding: 14px 18px;
            margin-bottom: 10px; border-left: 4px solid #555;
        }
        .wd-card.status-pending  { border-left-color: #f5c518; }
        .wd-card.status-completed { border-left-color: #28a745; }
        .wd-card.status-rejected { border-left-color: #e94560; }
        .wd-card.status-cancelled { border-left-color: #888; }
        .wd-card.status-failed { border-left-color: #ff6b6b; }

        .wd-amount { font-size: 1.3rem; font-weight: 700; }
        .wd-amount.pending { color: #f5c518; }
        .wd-amount.completed { color: #28a745; }
        .wd-amount.rejected { color: #e94560; }
        .wd-amount.cancelled { color: #888; }

        .wd-status {
            display: inline-block; padding: 2px 10px; border-radius: 12px;
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
        }
        .ws-pending   { background: #3a2e0a; color: #f5c518; }
        .ws-completed { background: #1b4332; color: #52b788; }
        .ws-rejected  { background: #3a1a1a; color: #e94560; }
        .ws-cancelled { background: #2a2a2a; color: #999; }
        .ws-failed    { background: #3a1a1a; color: #ff6b6b; }

        .wd-meta { color: #aaa; font-size: 0.82rem; }
        .wd-bank { color: #9ab; font-family: monospace; font-size: 0.85rem; }
        .wd-reason { background: #2a1a1a; border: 1px solid #4a2a2a; border-radius: 6px; padding: 8px 12px; margin-top: 6px; color: #e94560; font-size: 0.85rem; }
        .wd-revoke-reason { background: #2a2400; border: 1px solid #5a4a00; border-radius: 6px; padding: 8px 12px; margin-top: 6px; color: #f5c518; font-size: 0.85rem; }

        .admin-actions { margin-top: 10px; }
        .admin-actions .btn { font-size: 0.82rem; padding: 4px 14px; }

        .revoke-reason-box { display: none; margin-top: 8px; }
        .revoke-reason-box textarea, .manual-wd-box textarea {
            background: #111; border: 1px solid #444; color: #fff; font-size: 0.9rem;
            resize: vertical; min-height: 50px; width: 100%; border-radius: 6px; padding: 8px;
        }
        .revoke-reason-box textarea::placeholder, .manual-wd-box textarea::placeholder,
        .manual-wd-box input::placeholder { color: #fff; opacity: 0.5; }
        .revoke-reason-box textarea:focus, .manual-wd-box textarea:focus, .manual-wd-box input:focus {
            border-color: #e94560; outline: none; box-shadow: 0 0 0 0.2rem rgba(233,69,96,.25);
        }

        .manual-wd-box {
            background: #0a1628; border: 1px solid #1a3a5c; border-radius: 8px;
            padding: 14px; margin-top: 12px;
        }
        .manual-wd-box input[type="number"] {
            background: #111; border: 1px solid #444; color: #fff; font-size: 1rem;
            border-radius: 6px; padding: 6px 10px; width: 200px;
        }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }

        .balance-badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .balance-main { background: #1b4332; color: #52b788; }
        .balance-win { background: #3a2e0a; color: #f5c518; }

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
        <?php $activePage = 'withdrawals'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <!-- Statisztikák -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#f5c518;"><?= (int)$stats['pending_count'] ?></h3>
                    <p>Függőben lévő</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#28a745;"><?= (int)$stats['completed_count'] ?></h3>
                    <p>Jóváhagyott</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#e94560;"><?= (int)$stats['rejected_count'] ?></h3>
                    <p>Elutasított</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 style="color:#52b788;"><?= number_format((float)($stats['completed_sum'] ?? 0), 0, ',', ' ') ?></h3>
                    <p>Összesen kifizetve (Ft)</p>
                </div>
            </div>
        </div>

        <?php if ((int)$stats['pending_count'] > 0): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2" style="background:#3a2e0a;border-color:#f5c518;color:#f5c518;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?= (int)$stats['pending_count'] ?> kifizetés</strong> vár jóváhagyásra – összesen
            <strong><?= number_format((float)($stats['pending_sum'] ?? 0), 0, ',', ' ') ?> Ft</strong>
        </div>
        <?php endif; ?>

        <h4 class="mb-3">💸 Kifizetések felhasználónként</h4>

        <?php if (!$withdrawalUsers || $withdrawalUsers->num_rows === 0): ?>
            <p class="text-muted">Nincsenek felhasználók.</p>
        <?php else: ?>

        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" style="background:#16213e;">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Felhasználó</th>
                        <th>Email</th>
                        <th>Egyenleg</th>
                        <th>Nyeremény egyenleg</th>
                        <th>Függőben</th>
                        <th>Jóváhagyott</th>
                        <th>Elutasított</th>
                        <th>Összesen kifizetve</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($wu = $withdrawalUsers->fetch_assoc()):
                    $uid = (int)$wu['id'];
                    $hasPending = (int)$wu['pending_count'] > 0;
                ?>
                    <tr class="user-row" 
                        data-uid="<?= $uid ?>" onclick="toggleWdPanel(<?= $uid ?>)">
                        <td><i class="fas fa-chevron-right" id="chevron-<?= $uid ?>" style="transition:transform 0.2s;"></i></td>
                        <td>
                            <strong><?= htmlspecialchars($wu['username']) ?></strong>
                            <span class="text-muted">(#<?= $uid ?>)</span>
                            <?php if ($wu['full_name']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($wu['full_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small><?= htmlspecialchars($wu['email']) ?></small></td>
                        <td><span class="balance-badge balance-main"><?= number_format((float)$wu['balance'], 0, ',', ' ') ?> Ft</span></td>
                        <td><span class="balance-badge balance-win"><?= number_format((float)$wu['winnings_balance'], 0, ',', ' ') ?> Ft</span></td>
                        <td>
                            <?php if ($hasPending): ?>
                                <span class="badge bg-warning text-dark"><?= $wu['pending_count'] ?> db</span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$wu['completed_count'] > 0): ?>
                                <span class="badge" style="background:#1b4332;color:#52b788;"><?= $wu['completed_count'] ?> db</span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$wu['rejected_count'] > 0): ?>
                                <span class="badge" style="background:#3a1a1a;color:#e94560;"><?= $wu['rejected_count'] ?> db</span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($wu['total_paid']): ?>
                                <strong style="color:#52b788;"><?= number_format((float)$wu['total_paid'], 0, ',', ' ') ?> Ft</strong>
                            <?php else: ?>
                                <span class="text-muted">0 Ft</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Expandable detail panel -->
                    <tr class="user-detail-panel" id="panel-<?= $uid ?>">
                        <td colspan="9">
                            <div class="detail-inner">
                                <?php
                                $wds = $userWithdrawals[$uid] ?? [];
                                $pendingWds = array_filter($wds, fn($w) => $w['status'] === 'pending');
                                $closedWds  = array_filter($wds, fn($w) => $w['status'] !== 'pending');
                                ?>

                                <?php if (count($pendingWds) > 0): ?>
                                <h6 style="color:#f5c518;" class="mb-3">
                                    <i class="fas fa-clock"></i> Függőben lévő kifizetések (<?= count($pendingWds) ?>)
                                </h6>
                                <?php foreach ($pendingWds as $wd): ?>
                                <div class="wd-card status-pending" id="wd-card-<?= $wd['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="wd-amount pending"><?= number_format((float)$wd['amount'], 0, ',', ' ') ?> Ft</span>
                                            <span class="wd-status ws-pending ms-2">Függőben</span>
                                        </div>
                                        <div class="text-end">
                                            <div class="wd-meta"><?= date('Y.m.d H:i', strtotime($wd['created_at'])) ?></div>
                                            <div class="wd-meta"><?= htmlspecialchars($wd['transaction_id']) ?></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <span class="wd-meta">Számlatulajdonos:</span>
                                        <strong style="color:#ccc;"><?= htmlspecialchars($wd['account_holder'] ?? '-') ?></strong>
                                        <span class="ms-3 wd-meta">Számlaszám:</span>
                                        <span class="wd-bank"><?= htmlspecialchars($wd['account_number'] ?? '-') ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (count($closedWds) > 0): ?>
                                <h6 style="color:#aaa;" class="mb-3 <?= count($pendingWds) > 0 ? 'mt-4' : '' ?>">
                                    <i class="fas fa-history"></i> Korábbi kifizetések (<?= count($closedWds) ?>)
                                </h6>
                                <?php foreach ($closedWds as $wd):
                                    $statusClass = $wd['status'];
                                    $statusLabels = [
                                        'completed' => 'Jóváhagyva',
                                        'rejected'  => 'Elutasítva',
                                        'cancelled' => 'Visszavonva',
                                        'failed'    => 'Visszavont',
                                    ];
                                    $statusLabel = $statusLabels[$wd['status']] ?? $wd['status'];
                                ?>
                                <div class="wd-card status-<?= $statusClass ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="wd-amount <?= $statusClass ?>"><?= number_format((float)$wd['amount'], 0, ',', ' ') ?> Ft</span>
                                            <span class="wd-status ws-<?= $statusClass ?> ms-2"><?= $statusLabel ?></span>
                                        </div>
                                        <div class="text-end">
                                            <div class="wd-meta"><?= date('Y.m.d H:i', strtotime($wd['created_at'])) ?></div>
                                            <div class="wd-meta"><?= htmlspecialchars($wd['transaction_id']) ?></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <span class="wd-meta">Számlatulajdonos:</span>
                                        <strong style="color:#ccc;"><?= htmlspecialchars($wd['account_holder'] ?? '-') ?></strong>
                                        <span class="ms-3 wd-meta">Számlaszám:</span>
                                        <span class="wd-bank"><?= htmlspecialchars($wd['account_number'] ?? '-') ?></span>
                                        <?php if ($wd['status'] === 'completed' && $wd['updated_at']): ?>
                                            <span class="ms-3 wd-meta">Jóváhagyva: <?= date('Y.m.d H:i', strtotime($wd['updated_at'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($wd['status'] === 'rejected' && $wd['rejection_reason']): ?>
                                    <div class="wd-reason">
                                        <i class="fas fa-info-circle"></i> <?= htmlspecialchars($wd['rejection_reason']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($wd['status'] === 'failed' && $wd['rejection_reason']): ?>
                                    <div class="wd-revoke-reason">
                                        <i class="fas fa-undo"></i> Visszavonás oka: <?= htmlspecialchars($wd['rejection_reason']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($wd['status'] === 'completed'): ?>
                                    <div class="admin-actions">
                                        <button class="btn btn-outline-warning btn-sm btn-revoke-toggle" data-tid="<?= $wd['id'] ?>">
                                            <i class="fas fa-undo"></i> Kifizetés visszavonása
                                        </button>
                                        <div class="revoke-reason-box" id="revoke-box-<?= $wd['id'] ?>">
                                            <textarea id="revoke-reason-<?= $wd['id'] ?>" placeholder="Visszavonás oka (kötelező)..."></textarea>
                                            <div class="mt-2">
                                                <button class="btn btn-warning btn-sm btn-revoke-confirm" data-tid="<?= $wd['id'] ?>">
                                                    <i class="fas fa-undo"></i> Visszavonás megerősítése
                                                </button>
                                                <button class="btn btn-outline-secondary btn-sm ms-1 btn-revoke-cancel" data-tid="<?= $wd['id'] ?>">
                                                    Mégse
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (count($wds) === 0): ?>
                                    <p class="text-muted">Nincsenek kifizetések.</p>
                                <?php endif; ?>

                                <!-- Manuális kifizetés -->
                                <div class="manual-wd-box">
                                    <h6 style="color:#52b788;margin-bottom:12px;">
                                        <i class="fas fa-plus-circle"></i> Manuális kifizetés indítása
                                    </h6>
                                    <div class="d-flex align-items-end gap-3 flex-wrap">
                                        <div>
                                            <label class="text-muted" style="font-size:0.78rem;">Összeg (Ft)</label>
                                            <input type="number" id="manual-amount-<?= $uid ?>" min="1" placeholder="Összeg...">
                                        </div>
                                        <div style="flex:1;min-width:200px;">
                                            <label class="text-muted" style="font-size:0.78rem;">Megjegyzés (opcionális)</label>
                                            <textarea id="manual-note-<?= $uid ?>" rows="1" placeholder="Pl. készpénzes kifizetés, korrekció..." style="min-height:38px;"></textarea>
                                        </div>
                                        <button class="btn btn-success btn-sm btn-manual-wd" data-uid="<?= $uid ?>" style="height:38px;">
                                            <i class="fas fa-paper-plane"></i> Kifizetés
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        Elérhető egyenleg: <strong style="color:#f5c518;"><?= number_format((float)$wu['balance'], 0, ',', ' ') ?> Ft</strong>
                                    </small>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </main>
</div>

<script>
/* ── Toggle panel ────────────────────────────────────────────── */
function toggleWdPanel(uid) {
    const panel = document.getElementById('panel-' + uid);
    const chevron = document.getElementById('chevron-' + uid);
    const row = document.querySelector('[data-uid="' + uid + '"]');

    if (panel.classList.contains('show')) {
        panel.classList.remove('show');
        row.classList.remove('active-row');
        chevron.style.transform = 'rotate(0deg)';
    } else {
        panel.classList.add('show');
        row.classList.add('active-row');
        chevron.style.transform = 'rotate(90deg)';
    }
}

/* ── Toast ────────────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' alert-dismissible fade show';
    toast.style.cssText = 'min-width:320px;box-shadow:0 4px 16px rgba(0,0,0,0.5);';
    toast.innerHTML = msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    container.appendChild(toast);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 200); }, 5000);
}

/* ── Manuális kifizetés ───────────────────────────────────────── */
document.querySelectorAll('.btn-manual-wd').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const uid = this.dataset.uid;
        const amount = parseFloat(document.getElementById('manual-amount-' + uid).value);
        const note = document.getElementById('manual-note-' + uid).value.trim();

        if (!amount || amount <= 0) { BmbPopup.warning('Add meg az összeget!'); return; }
        BmbPopup.confirm('Biztosan létrehozol egy manuális kifizetést: ' + amount.toLocaleString('hu') + ' Ft?', function() {
            fetch('/BetMatchBonus/backend/ApiRequest/admin_withdrawal_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'manual_withdraw', user_id: parseInt(uid), amount: amount, note: note })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(() => showToast('Hálózati hiba!', 'danger'));
        });
    });
});

/* ── Revoke toggle ────────────────────────────────────────────── */
document.querySelectorAll('.btn-revoke-toggle').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const tid = this.dataset.tid;
        const box = document.getElementById('revoke-box-' + tid);
        box.style.display = box.style.display === 'block' ? 'none' : 'block';
    });
});

document.querySelectorAll('.btn-revoke-cancel').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        document.getElementById('revoke-box-' + this.dataset.tid).style.display = 'none';
    });
});

/* ── Revoke confirm ───────────────────────────────────────────── */
document.querySelectorAll('.btn-revoke-confirm').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const tid = this.dataset.tid;
        const reason = document.getElementById('revoke-reason-' + tid).value.trim();
        if (!reason) { BmbPopup.warning('Add meg a visszavonás okát!'); return; }
        BmbPopup.confirm('Biztosan visszavonod? Az összeg visszakerül a felhasználó egyenlegére.', function() {
            fetch('/BetMatchBonus/backend/ApiRequest/admin_withdrawal_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'revoke', transaction_id: parseInt(tid), reason: reason })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(() => showToast('Hálózati hiba!', 'danger'));
        });
    });
});

/* prevent row toggle on action clicks */
document.querySelectorAll('.admin-actions, .revoke-reason-box, .manual-wd-box').forEach(el => {
    el.addEventListener('click', e => e.stopPropagation());
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
