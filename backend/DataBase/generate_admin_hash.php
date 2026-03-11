<?php
echo "<pre>";

echo "GBence (ADMIN): " . password_hash("ADMIN_JELSZO_ITT", PASSWORD_DEFAULT) . PHP_EOL;
echo "VBence (MOD): " . password_hash("MOD1_JELSZO_ITT", PASSWORD_DEFAULT) . PHP_EOL;
echo "UMarcell (MOD): " . password_hash("MOD2_JELSZO_ITT", PASSWORD_DEFAULT) . PHP_EOL;

echo "</pre>";

echo "<p style='color:red'><b>Ha kimásoltad a hash-eket, TÖRÖLD ezt a fájlt!</b></p>";