<?php
/**
 * ENV_LOADER.PHP — .env fájl betöltése
 * 
 * Betölti a projekt gyökérben lévő .env fájlt és beállítja
 * a változókat getenv() / $_ENV számára.
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Automatikus betöltés a projekt gyökérből
loadEnv(dirname(__DIR__) . '/.env');
