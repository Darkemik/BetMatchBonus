<!-- Bal oldali menü sáv -->
<aside class="left-sidebar">
    <div class="sidebar-section">
        <h3 data-i18n="helpMenu.info">INFORMÁCIÓK</h3>
        <ul>
            <?php
                // Azonosítjuk az aktuális oldal filename-jét
                $current_page = basename($_SERVER['PHP_SELF']);
                
                $help_links = [
                    ['href' => '../Help/GYIK.php', 'key' => 'helpMenu.faq', 'label' => 'GYIK'],
                    ['href' => '../Help/uj_funkcio.php', 'key' => 'helpMenu.newFeatures', 'label' => 'Oldalbemutató'],
                    ['href' => '../Help/sportszabalyok.php', 'key' => 'helpMenu.sportRules', 'label' => 'Sportszabályok'],
                    ['href' => '../Help/szotar.php', 'key' => 'helpMenu.dictionary', 'label' => 'Szótár'],
                    ['href' => '../Help/fizetesi_lehetosegek.php', 'key' => 'helpMenu.paymentOptions', 'label' => 'Fizetési lehetőségek'],
                    ['href' => '../Help/jatekosvedelem.php', 'key' => 'helpMenu.playerProtection', 'label' => 'Játékosvédelem'],
                    ['href' => '../Help/informaciobiztonsag.php', 'key' => 'helpMenu.informationSecurity', 'label' => 'Információbiztonság'],
                    ['href' => '../Help/panaszkezeles.php', 'key' => 'helpMenu.complaintHandling', 'label' => 'Panaszkezelés'],
                    ['href' => '../Help/kapcsolat.php', 'key' => 'helpMenu.contact', 'label' => 'Kapcsolat'],
                    ['href' => '../Help/adatkezelesi_tajekoztatok.php', 'key' => 'helpMenu.privacyNotices', 'label' => 'Adatkezelési tájékoztatók'],
                    ['href' => '../Help/reszveteli-szabalyzat.php', 'key' => 'helpMenu.participationRules', 'label' => 'Részvételi szabályzat']
                ];
                
                foreach ($help_links as $item) {
                    $href = $item['href'];
                    $label = $item['label'];
                    $key = $item['key'];
                    $link_page = basename($href);
                    $is_active = ($current_page === $link_page) ? 'active' : '';
                    echo "<li><a href=\"$href\" class=\"$is_active\" data-i18n=\"$key\">$label</a></li>";
                }
            ?>
        </ul>
    </div>
</aside>