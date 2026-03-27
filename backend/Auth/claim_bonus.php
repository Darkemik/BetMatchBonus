<?php
// Ez a fájl elavult – a bónusz beváltást a backend/ApiRequest/claim_bonus.php kezeli.
// Ide kerülő kérések átirányítódnak a helyes végpontra.
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'Érvénytelen végpont. Kérjük frissítse az oldalt.']);
exit();