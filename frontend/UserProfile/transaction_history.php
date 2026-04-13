<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /BetMatchBonus/frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Tranzakciók lekérése
$query = "SELECT id, type, amount, payment_method, status, transaction_id, created_at, rejection_reason FROM Transactions WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Összesítő számítás
$totalDeposit = 0;
$totalWithdrawal = 0;
$pendingCount = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'deposit' && $t['status'] === 'completed') {
        $totalDeposit += (float)$t['amount'];
    }
    if ($t['type'] === 'withdrawal' && $t['status'] === 'completed') {
        $totalWithdrawal += (float)$t['amount'];
    }
    if ($t['status'] === 'pending') {
        $pendingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="userProfile.transactionHistory.pageTitle">Tranzakciótörténet | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
    <?php include '../../frontend/Components/disclaimer.php'; ?>
    <?php require_once "../Components/header.php"; ?>
    <div class="container profile-container">
        <div class="row">
            <div class="col-md-3">
                <nav class="profile-sidebar">
                    <a href="personal_data.php" class="profile-nav-item"><i class="fas fa-user"></i> <span data-i18n="auth.personalData">Személyes Adatok</span></a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> <span data-i18n="auth.changePassword">Jelszó Módosítás</span></a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> <span data-i18n="auth.deposit">Befizetés</span></a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span></a>
                    <a href="transaction_history.php" class="profile-nav-item active"><i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span></a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span></a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> <span data-i18n="auth.activityLog">Napló</span></a>
                    <a href="#" class="profile-nav-item logout profile-logout-btn" onclick="event.preventDefault();fetch('/BetMatchBonus/backend/Auth/logout.php',{method:'POST'}).then(function(){window.location.href='/BetMatchBonus/frontend/MainMenu/MainMenu.php';});"><i class="fas fa-sign-out-alt"></i> <span data-i18n="auth.logout">Kijelentkezés</span></a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span></h1>

                    <!-- Összesítő kártyák -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card text-center transaction-stat-card" style="background:#1a1a2e;">
                                <div class="card-body py-3">
                                    <div style="font-size:13px;color:#f5c518;text-transform:uppercase;letter-spacing:1px;" data-i18n="userProfile.transactionHistory.totalDeposit">Összes befizetés</div>
                                    <div style="font-size:1.5rem;font-weight:700;color:#f5c518;"><?php echo number_format($totalDeposit, 0, ',', ' '); ?> Ft</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center transaction-stat-card" style="background:#1a1a2e;">
                                <div class="card-body py-3">
                                    <div style="font-size:13px;color:#f5c518;text-transform:uppercase;letter-spacing:1px;" data-i18n="userProfile.transactionHistory.totalWithdrawal">Összes kifizetés</div>
                                    <div style="font-size:1.5rem;font-weight:700;color:#f5c518;"><?php echo number_format($totalWithdrawal, 0, ',', ' '); ?> Ft</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center transaction-stat-card" style="background:#1a1a2e;">
                                <div class="card-body py-3">
                                    <div style="font-size:13px;color:#f5c518;text-transform:uppercase;letter-spacing:1px;" data-i18n="userProfile.transactionHistory.pendingCount">Függőben lévő</div>
                                    <div style="font-size:1.5rem;font-weight:700;color:#f5c518;"><?php echo $pendingCount; ?> db</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Keresés + Szűrők egy sorban -->
                    <div class="d-flex flex-wrap align-items-end gap-3 mb-3 transaction-filters">
                        <div class="flex-grow-1">
                            <label class="form-label mb-1" data-i18n="userProfile.transactionHistory.searchLabel">Keresés</label>
                            <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="Azonosító, összeg, dátum..." data-i18n-placeholder="userProfile.transactionHistory.searchPlaceholder">
                        </div>
                        <div>
                            <label class="form-label mb-1" data-i18n="userProfile.transactionHistory.typeLabel">Típus</label>
                            <select id="filterType" class="form-select form-select-sm" style="width:auto;">
                                <option value="" data-i18n="userProfile.transactionHistory.all">Mind</option>
                                <option value="deposit" data-i18n="userProfile.transactionHistory.typeDeposit">Befizetés</option>
                                <option value="withdrawal" data-i18n="userProfile.transactionHistory.typeWithdrawal">Kifizetés</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1" data-i18n="userProfile.transactionHistory.statusLabel">Státusz</label>
                            <select id="filterStatus" class="form-select form-select-sm" style="width:auto;">
                                <option value="" data-i18n="userProfile.transactionHistory.all">Mind</option>
                                <option value="pending" data-i18n="userProfile.transactionHistory.statusPending">Függőben</option>
                                <option value="completed" data-i18n="userProfile.transactionHistory.statusCompleted">Befejezve</option>
                                <option value="failed" data-i18n="userProfile.transactionHistory.statusFailed">Sikertelen</option>
                                <option value="cancelled" data-i18n="userProfile.transactionHistory.statusCancelled">Visszavont</option>
                                <option value="rejected" data-i18n="userProfile.transactionHistory.statusRejected">Elutasítva</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($transactions)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <span data-i18n="userProfile.transactionHistory.noTransactions">Még nincs tranzakció a fiókodban.</span>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table transaction-table" id="transactionTable">
                                <thead>
                                    <tr>
                                        <th data-i18n="userProfile.transactionHistory.colId">Azonosító</th>
                                        <th data-i18n="userProfile.transactionHistory.colType">Típus</th>
                                        <th data-i18n="userProfile.transactionHistory.colAmount">Összeg</th>
                                        <th data-i18n="userProfile.transactionHistory.colPaymentMethod">Fizetési Mód</th>
                                        <th data-i18n="userProfile.transactionHistory.colStatus">Státusz</th>
                                        <th data-i18n="userProfile.transactionHistory.colDate">Dátum</th>
                                        <th data-i18n="userProfile.transactionHistory.colProof">Igazolás</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <code><?php echo htmlspecialchars($transaction['transaction_id']); ?></code>
                                            </td>
                                            <td data-type="<?php echo htmlspecialchars($transaction['type']); ?>">
                                                <?php 
                                                    $icon = $transaction['type'] === 'deposit' ? '<i class="fas fa-plus-circle text-success"></i>' : '<i class="fas fa-minus-circle text-danger"></i>';
                                                    $type_key = $transaction['type'] === 'deposit'
                                                        ? 'userProfile.transactionHistory.typeDeposit'
                                                        : 'userProfile.transactionHistory.typeWithdrawal';
                                                    $type_label = $transaction['type'] === 'deposit' ? 'Befizetés' : 'Kifizetés';
                                                    echo '<span style="display:inline-flex;align-items:center;gap:6px;">' . $icon . ' <span data-i18n="' . $type_key . '">' . $type_label . '</span></span>';
                                                ?>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($transaction['amount'], 0, ',', ' '); ?> Ft</strong>
                                            </td>
                                            <td>
                                                <?php 
                                                    $methods = [
                                                        'visa'          => 'Visa',
                                                        'mastercard'    => 'Mastercard',
                                                        'paypal'        => 'PayPal',
                                                        'paypal_demo'   => 'PayPal',
                                                        'card_demo'     => 'Bankkártya',
                                                        'bank_transfer' => 'Banki átutalás',
                                                    ];
                                                    echo $methods[$transaction['payment_method']] ?? htmlspecialchars($transaction['payment_method']);
                                                ?>
                                            </td>
                                            <td data-status="<?php echo htmlspecialchars($transaction['status']); ?>">
                                                <?php 
                                                    $status_badges = [
                                                        'pending' => '<span class="badge bg-warning" data-i18n="userProfile.transactionHistory.statusPending">Függőben</span>',
                                                        'completed' => '<span class="badge bg-success" data-i18n="userProfile.transactionHistory.statusCompleted">Befejezve</span>',
                                                        'failed' => '<span class="badge bg-danger" data-i18n="userProfile.transactionHistory.statusFailed">Sikertelen</span>',
                                                        'cancelled' => '<span class="badge bg-secondary" data-i18n="userProfile.transactionHistory.statusCancelled">Visszavont</span>',
                                                        'rejected' => '<span class="badge bg-danger" data-i18n="userProfile.transactionHistory.statusRejected">Elutasítva</span>'
                                                    ];
                                                    echo $status_badges[$transaction['status']] ?? '<span class="badge bg-secondary">' . htmlspecialchars($transaction['status']) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?>
                                            </td>
                                            <td>
                                                <?php if ($transaction['type'] === 'deposit' && $transaction['status'] === 'completed'): ?>
                                                    <a href="../../backend/ApiRequest/deposit_receipt.php?id=<?php echo $transaction['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Befizetési igazolás letöltése" data-i18n-title="userProfile.transactionHistory.downloadDepositProofTitle">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                <?php elseif ($transaction['type'] === 'withdrawal' && $transaction['status'] === 'completed'): ?>
                                                    <a href="../../backend/ApiRequest/withdrawal_receipt.php?id=<?php echo $transaction['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Kifizetési igazolás letöltése" data-i18n-title="userProfile.transactionHistory.downloadProofTitle">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                <?php elseif ($transaction['type'] === 'withdrawal' && $transaction['status'] === 'rejected' && !empty($transaction['rejection_reason'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $transaction['id']; ?>"
                                                            title="Elutasítás oka" data-i18n-title="userProfile.transactionHistory.rejectionReasonTitle">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Elutasítás ok modálok -->
                            <?php foreach ($transactions as $transaction): ?>
                                <?php if ($transaction['type'] === 'withdrawal' && $transaction['status'] === 'rejected' && !empty($transaction['rejection_reason'])): ?>
                                    <div class="modal fade" id="rejectModal<?php echo $transaction['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content" style="background:#1a1a2e;color:#f5c518;border:1px solid #f5c518;">
                                                <div class="modal-header border-bottom" style="border-color:#f5c518 !important;">
                                                    <h5 class="modal-title"><i class="fas fa-times-circle text-danger"></i> <span data-i18n="userProfile.transactionHistory.rejectionReasonHeader">Elutasítás oka</span></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Bezár" data-i18n-aria="common.close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-1" style="color:#ccc;font-size:0.9rem;"><span data-i18n="userProfile.transactionHistory.transactionLabel">Tranzakció</span>: <code><?php echo htmlspecialchars($transaction['transaction_id']); ?></code></p>
                                                    <p class="mb-1" style="color:#ccc;font-size:0.9rem;"><span data-i18n="userProfile.transactionHistory.amountLabel">Összeg</span>: <strong><?php echo number_format($transaction['amount'], 0, ',', ' '); ?> Ft</strong></p>
                                                    <hr style="border-color:#f5c518;">
                                                    <div style="background:#16213e;padding:12px 16px;border-radius:8px;border-left:4px solid #dc3545;color:#fff;">
                                                        <?php echo nl2br(htmlspecialchars($transaction['rejection_reason'])); ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top" style="border-color:#f5c518 !important;">
                                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-dismiss="modal" data-i18n="common.close">Bezár</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> <span data-i18n="common.back">Vissza</span></a>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <script>
    $(document).ready(function() {
        var dtCustomFilterInstalled = false;

        function installCustomFilters() {
            if (dtCustomFilterInstalled) return;
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'transactionTable') return true;
                var typeFilter = $('#filterType').val() || '';
                var statusFilter = $('#filterStatus').val() || '';
                var rowNode = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;
                if (!rowNode) return true;

                var rowType = rowNode.querySelector('td[data-type]') ? rowNode.querySelector('td[data-type]').getAttribute('data-type') : '';
                var rowStatus = rowNode.querySelector('td[data-status]') ? rowNode.querySelector('td[data-status]').getAttribute('data-status') : '';

                if (typeFilter && rowType !== typeFilter) return false;
                if (statusFilter && rowStatus !== statusFilter) return false;
                return true;
            });
            dtCustomFilterInstalled = true;
        }

        function dtLang() {
            return {
                info: window.i18n ? window.i18n('userProfile.transactionHistory.dtInfo', '_START_ - _END_ / _TOTAL_ tranzakció') : '_START_ - _END_ / _TOTAL_ tranzakció',
                infoEmpty: window.i18n ? window.i18n('userProfile.transactionHistory.dtInfoEmpty', 'Nincs tranzakció') : 'Nincs tranzakció',
                infoFiltered: window.i18n ? window.i18n('userProfile.transactionHistory.dtInfoFiltered', '(szűrve _MAX_ összesből)') : '(szűrve _MAX_ összesből)',
                paginate: {
                    first: window.i18n ? window.i18n('userProfile.transactionHistory.dtFirst', 'Első') : 'Első',
                    last: window.i18n ? window.i18n('userProfile.transactionHistory.dtLast', 'Utolsó') : 'Utolsó',
                    next: window.i18n ? window.i18n('userProfile.transactionHistory.dtNext', 'Következő') : 'Következő',
                    previous: window.i18n ? window.i18n('userProfile.transactionHistory.dtPrevious', 'Előző') : 'Előző'
                },
                zeroRecords: window.i18n ? window.i18n('userProfile.transactionHistory.dtZeroRecords', 'Nincs találat') : 'Nincs találat',
                emptyTable: window.i18n ? window.i18n('userProfile.transactionHistory.dtEmptyTable', 'Nincs adat') : 'Nincs adat'
            };
        }

        function buildTable() {
            installCustomFilters();
            return $('#transactionTable').DataTable({
                language: dtLang(),
                order: [[5, 'desc']],
                pageLength: 25,
                lengthChange: false,
                searching: true,
                dom: 'rtip',
                columnDefs: [
                    { orderable: false, targets: 6 }
                ]
            });
        }

        if ($('#transactionTable').length) {
            var table = buildTable();

            // Saját keresőmező
            $('#filterSearch').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Típus szűrő
            $('#filterType').on('change', function() {
                table.draw();
            });

            // Státusz szűrő
            $('#filterStatus').on('change', function() {
                table.draw();
            });

            // Nyelvváltás után DataTable UI szövegek újraépítése
            window.addEventListener('languageChanged', function() {
                var currentSearch = $('#filterSearch').val() || '';
                var currentPage = table.page();
                table.destroy();
                table = buildTable();
                table.search(currentSearch).draw();
                table.page(currentPage).draw('page');
            });
        }
    });
    </script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
