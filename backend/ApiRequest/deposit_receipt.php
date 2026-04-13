<?php
/**
 * Befizetési igazolás PDF letöltés
 * GET ?id=TRANSACTION_ID
 * 
 * Csak a saját, befejezett (completed) befizetésekhez elérhető.
 */
session_start();
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Nem vagy bejelentkezve.');
}

$user_id = (int)$_SESSION['user_id'];
$tx_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tx_id <= 0) {
    http_response_code(400);
    die('Hiányzó tranzakció azonosító.');
}

// Tranzakció lekérése
$stmt = $conn->prepare("
    SELECT t.id, t.transaction_id, t.amount, t.payment_method, t.status, t.created_at,
           u.username, u.full_name, u.email
    FROM Transactions t
    INNER JOIN Users u ON u.id = t.user_id
    WHERE t.id = ? AND t.user_id = ? AND t.type = 'deposit' AND t.status = 'completed'
    LIMIT 1
");
$stmt->bind_param("ii", $tx_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$tx = $result->fetch_assoc();
$stmt->close();

if (!$tx) {
    http_response_code(404);
    die('A befizetési igazolás nem található.');
}

// UTF-8 → latin2 (ISO-8859-2) konverzió magyar karakterekhez
function hu($text) {
    $converted = @iconv('UTF-8', 'ISO-8859-2//TRANSLIT', $text);
    return $converted !== false ? $converted : $text;
}

// Fizetési mód megjelenítés
$methodLabels = [
    'visa'          => 'Visa',
    'mastercard'    => 'Mastercard',
    'paypal'        => 'PayPal',
    'paypal_demo'   => 'PayPal',
    'card_demo'     => hu('Bankkártya'),
    'bank_transfer' => hu('Banki átutalás'),
];
$methodDisplay = $methodLabels[$tx['payment_method']] ?? $tx['payment_method'];

// PDF generálás
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// --- Fejléc háttér ---
$pdf->SetFillColor(40, 167, 69);
$pdf->Rect(0, 0, 210, 45, 'F');

// --- Logó szöveg ---
$pdf->SetFont('Helvetica', 'B', 22);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(15, 10);
$pdf->Cell(180, 10, 'BetMatchBonus', 0, 1, 'L');

// --- Cím ---
$pdf->SetFont('Helvetica', '', 13);
$pdf->SetXY(15, 22);
$pdf->Cell(180, 10, hu('BEFIZETÉSI IGAZOLÁS'), 0, 1, 'L');

// --- Tranzakció ID jobb oldalon ---
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetXY(15, 34);
$pdf->Cell(180, 6, $tx['transaction_id'], 0, 1, 'R');

// --- Összeg blokk ---
$pdf->Ln(12);
$pdf->SetTextColor(50, 50, 50);
$pdf->SetFont('Helvetica', '', 10);
$pdf->Cell(180, 6, hu('Befizetett összeg:'), 0, 1, 'C');
$pdf->SetFont('Helvetica', 'B', 28);
$pdf->SetTextColor(40, 167, 69);
$pdf->Cell(180, 14, number_format((float)$tx['amount'], 0, ',', ' ') . ' FT', 0, 1, 'C');

// --- Elválasztó ---
$pdf->Ln(6);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

// --- Táblázat ---
$pdf->SetTextColor(50, 50, 50);
$labelX = 20;
$valueX = 80;
$lineH = 9;

$rows = [
    [hu('Tranzakció azonosító'), $tx['transaction_id']],
    [hu('Dátum'), date('Y.m.d H:i', strtotime($tx['created_at']))],
    [hu('Felhasználó'), $tx['username']],
    [hu('Teljes név'), hu($tx['full_name'] ?? '-')],
    ['Email', $tx['email']],
    [hu('Összeg'), number_format((float)$tx['amount'], 0, ',', ' ') . ' FT'],
    [hu('Fizetési mód'), hu($methodDisplay)],
    [hu('Státusz'), hu('Sikeres')],
];

foreach ($rows as $i => $row) {
    $y = $pdf->GetY();
    
    // Zebra háttér
    if ($i % 2 === 0) {
        $pdf->SetFillColor(245, 247, 250);
        $pdf->Rect($labelX, $y, 170, $lineH, 'F');
    }
    
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetXY($labelX + 2, $y);
    $pdf->Cell(58, $lineH, $row[0], 0, 0, 'L');
    
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY($valueX, $y);
    $pdf->Cell(110, $lineH, $row[1], 0, 1, 'L');
}

// --- Elválasztó ---
$pdf->Ln(10);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(8);

// --- Lábléc szöveg ---
$pdf->SetFont('Helvetica', 'I', 9);
$pdf->SetTextColor(150, 150, 150);
$pdf->MultiCell(170, 5, hu('Ez a dokumentum elektronikusan generált, aláírás nélkül érvényes.'), 0, 'C');
$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(170, 5, 'BetMatchBonus ' . chr(169) . ' ' . date('Y'), 0, 1, 'C');
$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(170, 5, hu('Generálva: ') . date('Y.m.d H:i:s'), 0, 1, 'C');

// --- Kimeneti PDF ---
$filename = 'befizetes_' . $tx['transaction_id'] . '.pdf';
$pdf->Output('D', $filename);
exit;
