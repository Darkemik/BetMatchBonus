<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('tickets');
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];

// Összes felhasználó szelvényadatokkal
$ticketUsers = $conn->query("
    SELECT u.id, u.username, u.full_name, u.email,
        (SELECT COUNT(*) FROM Tickets t WHERE t.user_id = u.id AND t.status = 'OPEN') AS open_count,
        (SELECT COUNT(*) FROM Tickets t WHERE t.user_id = u.id AND t.status != 'OPEN' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)) AS recent_count
    FROM Users u
    WHERE u.is_active = 1 AND u.is_verified = 1
    ORDER BY u.id DESC
");

// Szelvények betöltése felhasználónként
$userTickets = [];
if ($ticketUsers && $ticketUsers->num_rows > 0) {
    $ticketUsers->data_seek(0);
    while ($tu = $ticketUsers->fetch_assoc()) {
        $uid = (int)$tu['id'];
        $tStmt = $conn->prepare("
            SELECT t.id, t.stake, t.bonus_stake, t.total_odds, t.potential_win, t.status, 
                   t.cashout_amount, t.cashout_at, t.created_at
            FROM Tickets t
            WHERE t.user_id = ? AND (t.status = 'OPEN' OR t.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY))
            ORDER BY t.created_at DESC
        ");
        $tStmt->bind_param("i", $uid);
        $tStmt->execute();
        $tickets = $tStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $tStmt->close();

        foreach ($tickets as &$ticket) {
            $sStmt = $conn->prepare("
                SELECT ts.home_team, ts.away_team, ts.pick_label, ts.market_name, ts.odds_at_pick, ts.status
                FROM TicketSelections ts
                WHERE ts.ticket_id = ?
            ");
            $sStmt->bind_param("i", $ticket['id']);
            $sStmt->execute();
            $ticket['selections'] = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $sStmt->close();
        }
        unset($ticket);

        $userTickets[$uid] = $tickets;
    }
    $ticketUsers->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Szelvények | Admin | BetMatchBonus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body { background: #1a1a2e; color: #eee; }
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
        .main-content { flex: 1; padding: 24px; min-width: 0; }
        .table-dark th { color: #e94560; }

        .ticket-user-row { cursor: pointer; transition: background 0.2s; }
        .ticket-user-row:hover { background: #0f3460 !important; }
        .ticket-detail { display: none; background: #16213e; }
        .ticket-detail.show { display: table-row; }
        .ticket-card {
            background: #0f3460; border-radius: 8px; padding: 14px; margin-bottom: 10px;
            border-left: 4px solid #555;
        }
        .ticket-card.status-OPEN { border-left-color: #4fc3f7; }
        .ticket-card.status-WON { border-left-color: #52b788; }
        .ticket-card.status-LOST { border-left-color: #e94560; }
        .ticket-card.status-CASHOUT { border-left-color: #f5c518; }
        .ticket-status { font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
        .ts-OPEN { background: #1a3a5c; color: #4fc3f7; }
        .ts-WON { background: #1b4332; color: #52b788; }
        .ts-LOST { background: #4a1a1a; color: #e94560; }
        .ts-CASHOUT { background: #3a2e0a; color: #f5c518; }
        .sel-row { font-size: 0.8rem; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sel-row:last-child { border-bottom: none; }
        .sel-status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
        .sel-dot-OPEN { background: #4fc3f7; }
        .sel-dot-WON { background: #52b788; }
        .sel-dot-LOST { background: #e94560; }

        /* text-muted felülírás a ticket kártyákon belül */
        .ticket-card .text-muted,
        .ticket-detail .text-muted,
        .ticket-user-row .text-muted { color: #bbb !important; }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .ticket-card.status-VOID { border-left-color: #888; }
        .ts-VOID { background: #333; color: #aaa; }
        .admin-actions { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); }
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
        <?php $activePage = 'tickets'; include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <h4 class="mb-2">🎫 Szelvények</h4>
        <p class="mb-3" style="font-size:0.85rem;color:#ccc;">Aktív (OPEN) szelvények és az elmúlt 3 nap lezárt szelvényei</p>

        <div class="table-responsive shadow-sm" style="border-radius: 8px; overflow: hidden;">
            <table class="table table-dark table-striped mb-0">
                <thead>
                    <tr>
                        <th>Felhasználó</th>
                        <th>Email</th>
                        <th class="text-center">Aktív</th>
                        <th class="text-center">Múltbeli (3 nap)</th>
                        <th class="text-center" style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($ticketUsers && $ticketUsers->num_rows > 0): ?>
                    <?php while ($tu = $ticketUsers->fetch_assoc()): ?>
                    <tr class="ticket-user-row" data-tuid="<?= (int)$tu['id'] ?>">
                        <td>
                            <strong><?= htmlspecialchars($tu['full_name'] ?? $tu['username']) ?></strong>
                            <small class="text-muted d-block"><?= htmlspecialchars($tu['username']) ?></small>
                        </td>
                        <td><small><?= htmlspecialchars($tu['email']) ?></small></td>
                        <td class="text-center">
                            <?php if ((int)$tu['open_count'] > 0): ?>
                                <span class="badge bg-info"><?= (int)$tu['open_count'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$tu['recent_count'] > 0): ?>
                                <span class="badge bg-secondary"><?= (int)$tu['recent_count'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><i class="fas fa-chevron-down text-muted ticket-toggle-icon"></i></td>
                    </tr>
                    <tr class="ticket-detail" id="tdetail-<?= (int)$tu['id'] ?>">
                        <td colspan="5" style="padding:0 !important;">
                            <div style="padding:16px 24px;">
                                <?php
                                $uid = (int)$tu['id'];
                                $tickets = $userTickets[$uid] ?? [];
                                if (empty($tickets)): ?>
                                    <p class="mb-0" style="color:#ccc;">Nincs szelvény.</p>
                                <?php else:
                                    $openTickets = array_filter($tickets, fn($t) => $t['status'] === 'OPEN');
                                    $closedTickets = array_filter($tickets, fn($t) => $t['status'] !== 'OPEN');
                                ?>
                                    <?php if (!empty($openTickets)): ?>
                                    <h6 style="color:#4fc3f7;" class="mb-2"><i class="fas fa-clock"></i> Aktív szelvények</h6>
                                    <?php foreach ($openTickets as $t): ?>
                                    <div class="ticket-card status-<?= $t['status'] ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="ticket-status ts-<?= $t['status'] ?>"><?= $t['status'] ?></span>
                                                <small class="text-muted ms-2">#<?= $t['id'] ?> | <?= date('m.d H:i', strtotime($t['created_at'])) ?></small>
                                            </div>
                                            <div style="font-size:0.85rem;">
                                                Tét: <strong><?= number_format($t['stake'], 0, ',', ' ') ?> Ft</strong>
                                                <?php if ((float)$t['bonus_stake'] > 0): ?>
                                                    <span class="text-warning ms-1">(+<?= number_format($t['bonus_stake'], 0, ',', ' ') ?> bónusz)</span>
                                                <?php endif; ?>
                                                | Odds: <strong><?= number_format($t['total_odds'], 2, ',', '') ?></strong>
                                                | Nyeremény: <strong style="color:#52b788;"><?= number_format($t['potential_win'], 0, ',', ' ') ?> Ft</strong>
                                            </div>
                                        </div>
                                        <?php foreach ($t['selections'] as $sel): ?>
                                        <div class="sel-row d-flex justify-content-between">
                                            <div>
                                                <span class="sel-status-dot sel-dot-<?= $sel['status'] ?>"></span>
                                                <?= htmlspecialchars($sel['home_team'] . ' - ' . $sel['away_team']) ?>
                                            </div>
                                            <div>
                                                <span class="text-muted"><?= htmlspecialchars($sel['market_name'] ?? '') ?></span>
                                                <strong class="ms-2"><?= htmlspecialchars($sel['pick_label'] ?? '') ?></strong>
                                                <span class="ms-2" style="color:#4fc3f7;"><?= number_format($sel['odds_at_pick'], 2, ',', '') ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <div class="admin-actions d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-danger btn-void" data-tid="<?= $t['id'] ?>">
                                                <i class="fas fa-ban"></i> Érvénytelenítés (tét visszaadás)
                                            </button>
                                            <button class="btn btn-sm btn-outline-success btn-manual-won" data-tid="<?= $t['id'] ?>">
                                                <i class="fas fa-check"></i> WON
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning btn-manual-lost" data-tid="<?= $t['id'] ?>">
                                                <i class="fas fa-times"></i> LOST
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if (!empty($closedTickets)): ?>
                                    <h6 style="color:#aaa;" class="mb-2 mt-3"><i class="fas fa-history"></i> Lezárt szelvények (3 nap)</h6>
                                    <?php foreach ($closedTickets as $t): ?>
                                    <div class="ticket-card status-<?= $t['status'] ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="ticket-status ts-<?= $t['status'] ?>"><?= $t['status'] ?></span>
                                                <?php if ($t['status'] === 'CASHOUT'): ?>
                                                    <small class="text-warning ms-1"><?= number_format($t['cashout_amount'], 0, ',', ' ') ?> Ft</small>
                                                <?php endif; ?>
                                                <small class="text-muted ms-2">#<?= $t['id'] ?> | <?= date('m.d H:i', strtotime($t['created_at'])) ?></small>
                                            </div>
                                            <div style="font-size:0.85rem;">
                                                Tét: <strong><?= number_format($t['stake'], 0, ',', ' ') ?> Ft</strong>
                                                <?php if ((float)$t['bonus_stake'] > 0): ?>
                                                    <span class="text-warning ms-1">(+<?= number_format($t['bonus_stake'], 0, ',', ' ') ?> bónusz)</span>
                                                <?php endif; ?>
                                                | Odds: <strong><?= number_format($t['total_odds'], 2, ',', '') ?></strong>
                                                | <?= $t['status'] === 'WON' ? '<span style="color:#52b788;">Nyeremény: <strong>' . number_format($t['potential_win'], 0, ',', ' ') . ' Ft</strong></span>' : '<span class="text-muted">Pot.: ' . number_format($t['potential_win'], 0, ',', ' ') . ' Ft</span>' ?>
                                            </div>
                                        </div>
                                        <?php foreach ($t['selections'] as $sel): ?>
                                        <div class="sel-row d-flex justify-content-between">
                                            <div>
                                                <span class="sel-status-dot sel-dot-<?= $sel['status'] ?>"></span>
                                                <?= htmlspecialchars($sel['home_team'] . ' - ' . $sel['away_team']) ?>
                                            </div>
                                            <div>
                                                <span class="text-muted"><?= htmlspecialchars($sel['market_name'] ?? '') ?></span>
                                                <strong class="ms-2"><?= htmlspecialchars($sel['pick_label'] ?? '') ?></strong>
                                                <span class="ms-2" style="color:#4fc3f7;"><?= number_format($sel['odds_at_pick'], 2, ',', '') ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Nincs szelvény az elmúlt 3 napban.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ticket-user-row').forEach(row => {
        row.addEventListener('click', function() {
            const uid = this.dataset.tuid;
            const panel = document.getElementById('tdetail-' + uid);
            const icon = this.querySelector('.ticket-toggle-icon');

            document.querySelectorAll('.ticket-detail.show').forEach(p => {
                if (p.id !== 'tdetail-' + uid) {
                    p.classList.remove('show');
                    const otherRow = document.querySelector('.ticket-user-row[data-tuid="' + p.id.replace('tdetail-','') + '"]');
                    if (otherRow) {
                        otherRow.querySelector('.ticket-toggle-icon').classList.replace('fa-chevron-up', 'fa-chevron-down');
                    }
                }
            });

            panel.classList.toggle('show');
            if (panel.classList.contains('show')) {
                icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
            } else {
                icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
            }
        });
    });

    const API_URL = '../../backend/ApiRequest/admin_ticket_action.php';

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

    function ticketAction(action, ticketId, newStatus) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('ticket_id', ticketId);
        if (newStatus) formData.append('new_status', newStatus);

        fetch(API_URL, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'danger');
                if (data.success) setTimeout(() => location.reload(), 1500);
            })
            .catch(() => showToast('Hálózati hiba!', 'danger'));
    }

    // Érvénytelenítés
    document.querySelectorAll('.btn-void').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!confirm('Biztosan érvényteleníted ezt a szelvényt? A tét visszakerül a felhasználó egyenlegére.')) return;
            ticketAction('void', this.dataset.tid);
        });
    });

    // Manuális WON
    document.querySelectorAll('.btn-manual-won').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!confirm('Biztosan NYERTESRE zárod ezt a szelvényt? A nyeremény jóváírásra kerül.')) return;
            ticketAction('manual_close', this.dataset.tid, 'WON');
        });
    });

    // Manuális LOST
    document.querySelectorAll('.btn-manual-lost').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!confirm('Biztosan VESZTESRE zárod ezt a szelvényt?')) return;
            ticketAction('manual_close', this.dataset.tid, 'LOST');
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
