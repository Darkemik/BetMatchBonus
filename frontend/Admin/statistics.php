<?php
require_once __DIR__ . '/../../backend/Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../../backend/connect.php';
require_once __DIR__ . '/../../backend/Auth/permission_helper.php';
page_permission_guard('statistics');
$perms = get_role_permissions();

$role = $_SESSION['admin_role'];
$activePage = 'statistics';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Statisztikák | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
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
        .stat-card .stat-icon { font-size: 1.4rem; margin-bottom: 6px; }
        .chart-card {
            background: #16213e; border-radius: 12px; padding: 24px;
            border: 1px solid rgba(255,255,255,0.06); margin-bottom: 24px;
        }
        .chart-card h5 { color: #e94560; font-weight: 700; margin-bottom: 16px; font-size: 1rem; }
        .range-btn { background: #0f3460; border: none; color: #aaa; padding: 6px 14px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; }
        .range-btn.active { background: #e94560; color: #fff; }
        .range-btn:hover { color: #fff; }
        .top-table { width: 100%; font-size: 0.85rem; }
        .top-table th { color: #e94560; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); padding: 8px; }
        .top-table td { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .top-table tr:hover td { background: rgba(255,255,255,0.03); }
        .badge-rank { background: #e94560; color: #fff; border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; }
        .loading-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(26,26,46,0.85); display: flex; align-items: center;
            justify-content: center; z-index: 9999; font-size: 1.2rem;
        }
        .loading-overlay .spinner-border { width: 2.5rem; height: 2.5rem; }
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
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h4 class="mb-0"><i class="fas fa-chart-line"></i> Statisztikák & Riportok</h4>
            <div class="d-flex gap-2" id="rangeButtons">
                <button class="range-btn" data-range="7">7 nap</button>
                <button class="range-btn active" data-range="30">30 nap</button>
                <button class="range-btn" data-range="90">90 nap</button>
                <button class="range-btn" data-range="365">1 év</button>
                <button class="range-btn" data-range="all">Összes</button>
            </div>
        </div>

        <!-- Összesített mutatók -->
        <div class="row g-3 mb-4" id="overviewCards">
            <!-- JS tölti -->
        </div>

        <!-- Grafikonok -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5><i class="fas fa-money-bill-wave"></i> Napi bevétel (befizetés vs kifizetés)</h5>
                    <div style="position:relative;height:300px;"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5><i class="fas fa-ticket-alt"></i> Szelvény státuszok</h5>
                    <div style="position:relative;height:300px;"><canvas id="ticketPieChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="chart-card">
                    <h5><i class="fas fa-user-plus"></i> Napi regisztrációk</h5>
                    <div style="position:relative;height:260px;"><canvas id="regChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-card">
                    <h5><i class="fas fa-futbol"></i> Napi fogadási forgalom</h5>
                    <div style="position:relative;height:260px;"><canvas id="betsChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Top listák -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5><i class="fas fa-trophy" style="color:#f5c518;"></i> Top befizetők</h5>
                    <div id="topDepositorsTable"></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5><i class="fas fa-fire" style="color:#e94560;"></i> Top fogadók</h5>
                    <div id="topBettorsTable"></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5><i class="fas fa-medal" style="color:#52b788;"></i> Top nyertesek</h5>
                    <div id="topWinnersTable"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <div class="spinner-border text-danger mb-3" role="status"></div>
        <div>Adatok betöltése...</div>
    </div>
</div>

<script>
const fmt = n => Number(n).toLocaleString('hu-HU');
let revenueChart, regChart, betsChart, ticketPieChart;
let currentRange = '30';

// ─── Összesített kártyák ───
function renderOverview(o) {
    const cards = [
        { icon: 'fa-users',         color: '#4cc9f0', val: fmt(o.total_users),         label: 'Összes felhasználó' },
        { icon: 'fa-user-plus',     color: '#52b788', val: fmt(o.new_users_30d),       label: 'Új (30 nap)' },
        { icon: 'fa-arrow-down',    color: '#28a745', val: fmt(o.total_deposits)+' Ft', label: 'Összes befizetés' },
        { icon: 'fa-arrow-up',      color: '#dc3545', val: fmt(o.total_withdrawals)+' Ft', label: 'Összes kifizetés' },
        { icon: 'fa-chart-line',    color: o.net_revenue >= 0 ? '#52b788':'#dc3545', val: fmt(o.net_revenue)+' Ft', label: 'Nettó bevétel' },
        { icon: 'fa-ticket-alt',    color: '#5b9bd5', val: fmt(o.total_tickets),       label: 'Összes szelvény' },
        { icon: 'fa-coins',         color: '#f5c518', val: fmt(o.total_stake)+' Ft',   label: 'Összes tét' },
        { icon: 'fa-building-columns', color: o.house_edge >= 0 ? '#52b788':'#dc3545', val: fmt(o.house_edge)+' Ft', label: 'Házrés (profit)' },
    ];
    document.getElementById('overviewCards').innerHTML = cards.map(c => `
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="color:${c.color};"><i class="fas ${c.icon}"></i></div>
                <h3 style="color:${c.color}; font-size:1.35rem;">${c.val}</h3>
                <p>${c.label}</p>
            </div>
        </div>
    `).join('');
}

// ─── Grafikonok ───
function makeChart(ctx, type, labels, datasets, opts) {
    return new Chart(ctx, {
        type,
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#aaa', font: { size: 11 } } } },
            scales: type === 'doughnut' ? {} : {
                x: { ticks: { color: '#888', font: { size: 10 }, maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { ticks: { color: '#888', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.06)' } }
            },
            ...opts
        }
    });
}

function renderCharts(data) {
    // Revenue chart
    const allDates = [...new Set([
        ...data.daily_deposits.map(d=>d.date),
        ...data.daily_withdrawals.map(d=>d.date)
    ])].sort();

    const depMap = {}; data.daily_deposits.forEach(d => depMap[d.date] = d.total);
    const withMap = {}; data.daily_withdrawals.forEach(d => withMap[d.date] = d.total);

    if (revenueChart) revenueChart.destroy();
    revenueChart = makeChart(
        document.getElementById('revenueChart'), 'bar', allDates,
        [
            { label: 'Befizetések', data: allDates.map(d => depMap[d]||0), backgroundColor: 'rgba(40,167,69,0.7)', borderRadius: 4 },
            { label: 'Kifizetések', data: allDates.map(d => withMap[d]||0), backgroundColor: 'rgba(220,53,69,0.7)', borderRadius: 4 }
        ]
    );

    // Ticket pie
    const ov = data.overview;
    if (ticketPieChart) ticketPieChart.destroy();
    ticketPieChart = makeChart(
        document.getElementById('ticketPieChart'), 'doughnut',
        ['Nyert', 'Vesztett', 'Nyitott', 'Cashout'],
        [{
            data: [ov.won_tickets, ov.lost_tickets, ov.open_tickets, ov.total_cashouts],
            backgroundColor: ['#52b788','#dc3545','#5b9bd5','#f5c518'],
            borderWidth: 0
        }],
        { cutout: '60%' }
    );

    // Registration chart
    if (regChart) regChart.destroy();
    regChart = makeChart(
        document.getElementById('regChart'), 'line',
        data.daily_registrations.map(d=>d.date),
        [{
            label: 'Új felhasználók', data: data.daily_registrations.map(d=>d.count),
            borderColor: '#4cc9f0', backgroundColor: 'rgba(76,201,240,0.1)',
            fill: true, tension: 0.3, pointRadius: 2
        }]
    );

    // Bets chart
    if (betsChart) betsChart.destroy();
    betsChart = makeChart(
        document.getElementById('betsChart'), 'bar',
        data.daily_bets.map(d=>d.date),
        [{
            label: 'Napi tét (Ft)', data: data.daily_bets.map(d=>d.total),
            backgroundColor: 'rgba(245,197,24,0.7)', borderRadius: 4
        }]
    );
}

// ─── Top listák ───
function renderTopTable(containerId, items, unit) {
    if (!items.length) {
        document.getElementById(containerId).innerHTML = '<p class="text-muted text-center" style="font-size:0.85rem;">Nincs adat</p>';
        return;
    }
    let html = '<table class="top-table"><thead><tr><th>#</th><th>Felhasználó</th><th>Összeg</th><th>Db</th></tr></thead><tbody>';
    items.forEach((it, i) => {
        html += `<tr>
            <td><span class="badge-rank">${i+1}</span></td>
            <td>${it.username}</td>
            <td style="font-weight:700;">${fmt(it.total)} ${unit}</td>
            <td style="color:#aaa;">${it.count}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    document.getElementById(containerId).innerHTML = html;
}

// ─── Adatok betöltése ───
async function loadData(range) {
    currentRange = range;
    document.getElementById('loadingOverlay').style.display = 'flex';
    try {
        const res = await fetch('../../backend/ApiRequest/admin_statistics.php?range=' + range);
        const data = await res.json();
        renderOverview(data.overview);
        renderCharts(data);
        renderTopTable('topDepositorsTable', data.top_depositors, 'Ft');
        renderTopTable('topBettorsTable', data.top_bettors, 'Ft');
        renderTopTable('topWinnersTable', data.top_winners, 'Ft');
    } catch (e) {
        console.error('Stats load error:', e);
    }
    document.getElementById('loadingOverlay').style.display = 'none';
}

// ─── Range gombok ───
document.querySelectorAll('.range-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.range-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        loadData(this.dataset.range);
    });
});

loadData('30');
</script>
</body>
</html>
