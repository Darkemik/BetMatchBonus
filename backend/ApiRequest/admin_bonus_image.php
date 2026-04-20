<?php
/**
 * ADMIN_BONUS_IMAGE.PHP — Bónusz kép feltöltés (admin)
 * 
 * POST: multipart/form-data
 *   - bonus_id: int
 *   - bonus_image: file (jpg, jpeg, png, gif, svg, webp)
 * 
 * Válasz: JSON { success, image_url, error }
 */
session_start();
require_once dirname(__DIR__) . '/Auth/admin_guard.php';
admin_guard('MOD');
require_once dirname(__DIR__) . '/connect.php';

header('Content-Type: application/json; charset=utf-8');

$ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
$MAX_SIZE = 2 * 1024 * 1024; // 2 MB
$UPLOAD_DIR = dirname(__DIR__) . '/uploads/bonuses/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Csak POST kérés engedélyezett.']);
    exit;
}

$bonusId = isset($_POST['bonus_id']) ? (int)$_POST['bonus_id'] : 0;
if ($bonusId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Érvénytelen bónusz ID.']);
    exit;
}

// Ellenőrzés: létezik-e a bónusz
$stmt = $conn->prepare("SELECT id, image_url FROM BonusCodes WHERE id = ?");
$stmt->bind_param("i", $bonusId);
$stmt->execute();
$bonus = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bonus) {
    echo json_encode(['success' => false, 'error' => 'Bónusz nem található.']);
    exit;
}

if (!isset($_FILES['bonus_image']) || $_FILES['bonus_image']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['bonus_image']['error'] ?? 'unknown';
    echo json_encode(['success' => false, 'error' => "Feltöltési hiba (kód: $errCode)."]);
    exit;
}

$file = $_FILES['bonus_image'];

// Típus ellenőrzés
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $ALLOWED_TYPES, true)) {
    echo json_encode(['success' => false, 'error' => "Nem engedélyezett fájltípus: $mimeType. Elfogadott: JPG, PNG, GIF, SVG, WebP."]);
    exit;
}

// Méret ellenőrzés
if ($file['size'] > $MAX_SIZE) {
    echo json_encode(['success' => false, 'error' => 'A fájl túl nagy (max 2 MB).']);
    exit;
}

// Biztonságos fájlnév generálás
$ext = match ($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg',
    'image/webp' => 'webp',
    default => 'png'
};
$filename = 'bonus_' . $bonusId . '_' . str_replace('.', '', (string)microtime(true)) . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetPath = $UPLOAD_DIR . $filename;

// Mappa biztosítása
if (!is_dir($UPLOAD_DIR)) {
    if (!mkdir($UPLOAD_DIR, 0755, true) && !is_dir($UPLOAD_DIR)) {
        echo json_encode(['success' => false, 'error' => 'A célmappa nem hozható létre: ' . $UPLOAD_DIR]);
        exit;
    }
}

if (!is_writable($UPLOAD_DIR)) {
    echo json_encode(['success' => false, 'error' => 'A célmappa nem írható: ' . $UPLOAD_DIR]);
    exit;
}

// Régi fájl törlése (ha nem az alapértelmezett SVG)
$oldUrl = $bonus['image_url'] ?? '';
if ($oldUrl !== '' && strpos($oldUrl, 'uploads/bonuses/') !== false) {
    $oldFile = dirname(__DIR__) . str_replace('../../backend', '', $oldUrl);
    // Normalizálás
    $oldFile = $UPLOAD_DIR . basename($oldUrl);
    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

// Feltöltés
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'error' => 'Fájl mentése sikertelen.']);
    exit;
}

// DB frissítés — relatív URL a frontend számára
$imageUrl = '../../backend/uploads/bonuses/' . $filename;
$stmt = $conn->prepare("UPDATE BonusCodes SET image_url = ? WHERE id = ?");
if (!$stmt) {
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    echo json_encode(['success' => false, 'error' => 'Adatbázis prepare hiba: ' . $conn->error]);
    exit;
}
$stmt->bind_param("si", $imageUrl, $bonusId);
if (!$stmt->execute()) {
    $stmt->close();
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    echo json_encode(['success' => false, 'error' => 'Adatbázis frissítési hiba: ' . $conn->error]);
    exit;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'image_url' => $imageUrl,
    'message' => 'Bónusz kép sikeresen frissítve.'
]);
