<?php
require_once __DIR__ . '/config.php';

/**
 * reCAPTCHA v3 token ellenőrzés.
 *
 * @param string $token  A frontendtől kapott g-recaptcha-response token
 * @param string $action Elvárt action név (pl. 'login', 'register')
 * @return array ['success' => bool, 'score' => float, 'error' => string|null]
 */
function verifyRecaptcha(string $token, string $action = ''): array
{
    if ($token === '') {
        return ['success' => false, 'score' => 0, 'error' => 'Hiányzó reCAPTCHA token.'];
    }

    $postData = [
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
    ];

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $postData['remoteip'] = $_SERVER['REMOTE_ADDR'];
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'score' => 0, 'error' => 'reCAPTCHA szerver nem elérhető: ' . $curlError];
    }

    $data = json_decode($response, true);

    if (empty($data['success'])) {
        return ['success' => false, 'score' => 0, 'error' => 'reCAPTCHA ellenőrzés sikertelen.'];
    }

    // Action ellenőrzés (opcionális, de ajánlott)
    if ($action !== '' && isset($data['action']) && $data['action'] !== $action) {
        return ['success' => false, 'score' => 0, 'error' => 'reCAPTCHA action nem egyezik.'];
    }

    $score = $data['score'] ?? 0;

    if ($score < RECAPTCHA_THRESHOLD) {
        return ['success' => false, 'score' => $score, 'error' => 'A rendszer botnak érzékelte a kérést.'];
    }

    return ['success' => true, 'score' => $score, 'error' => null];
}
