<?php
require_once "../../backend/Auth/check_session.php";
require_once "../../backend/ApiRequest/connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /frontend/MainMenu/MainMenu.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Tranzakciók lekérése
$query = "SELECT id, type, amount, payment_method, status, transaction_id, created_at FROM Transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 100";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tranzakciótörténet | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/UserProfile/user_profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
    <?php require_once "../Components/header.php"; ?>
    <div class="container profile-container">
        <div class="row">
            <div class="col-md-3">
                <nav class="profile-sidebar">
                    <a href="personal_data.php" class="profile-nav-item"><i class="fas fa-user"></i> Személyes Adatok</a>
                    <a href="change_password.php" class="profile-nav-item"><i class="fas fa-key"></i> Jelszó Módosítás</a>
                    <a href="deposit.php" class="profile-nav-item"><i class="fas fa-plus-circle"></i> Befizetés</a>
                    <a href="withdrawal.php" class="profile-nav-item"><i class="fas fa-minus-circle"></i> Kifizetés</a>
                    <a href="transaction_history.php" class="profile-nav-item active"><i class="fas fa-history"></i> Tranzakciótörténet</a>
                    <a href="my_bonuses.php" class="profile-nav-item"><i class="fas fa-gift"></i> Bónuszaim</a>
                    <a href="activity_log.php" class="profile-nav-item"><i class="fas fa-list"></i> Napló</a>
                    <a href="../../backend/Auth/logout.php" class="profile-nav-item logout"><i class="fas fa-sign-out-alt"></i> Kijelentkezés</a>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="profile-content">
                    <h1><i class="fas fa-history"></i> Tranzakciótörténet</h1>
                    
                    <?php if (empty($transactions)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Még nincs tranzakció az Ön fiókjában.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover transaction-table">
                                <thead>
                                    <tr>
                                        <th>Azonosító</th>
                                        <th>Típus</th>
                                        <th>Összeg</th>
                                        <th>Fizetési Mód</th>
                                        <th>Státusz</th>
                                        <th>Dátum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <code><?php echo htmlspecialchars($transaction['transaction_id']); ?></code>
                                            </td>
                                            <td>
                                                <?php 
                                                    $icon = $transaction['type'] === 'deposit' ? '<i class="fas fa-plus-circle text-success"></i>' : '<i class="fas fa-minus-circle text-danger"></i>';
                                                    $type_label = $transaction['type'] === 'deposit' ? 'Befizetés' : 'Kifizetés';
                                                    echo $icon . ' ' . $type_label;
                                                ?>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($transaction['amount'], 0, ',', ' '); ?> FT</strong>
                                            </td>
                                            <td>
                                                <?php 
                                                    $methods = [
                                                        'credit_card' => 'Hitelkártya',
                                                        'debit_card' => 'Bankkártya',
                                                        'bank_transfer' => 'Banki átutalás',
                                                        'upi' => 'UPI',
                                                        'wallet' => 'E-pénztárca'
                                                    ];
                                                    echo $methods[$transaction['payment_method']] ?? htmlspecialchars($transaction['payment_method']);
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $status_badges = [
                                                        'pending' => '<span class="badge bg-warning">Függőben</span>',
                                                        'completed' => '<span class="badge bg-success">Befejezve</span>',
                                                        'failed' => '<span class="badge bg-danger">Sikertelen</span>',
                                                        'cancelled' => '<span class="badge bg-secondary">Visszavont</span>'
                                                    ];
                                                    echo $status_badges[$transaction['status']] ?? '<span class="badge bg-secondary">' . htmlspecialchars($transaction['status']) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <a href="personal_data.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Vissza</a>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "../Components/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../js/UserProfile/user_profile.js"></script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>
