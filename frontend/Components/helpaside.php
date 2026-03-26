<!-- Bal oldali menü sáv -->
<aside class="left-sidebar">
    <div class="sidebar-section">
        <h3>INFORMÁCIÓK</h3>
        <ul>
            <?php
                // Azonosítjuk az aktuális oldal filename-jét
                $current_page = basename($_SERVER['PHP_SELF']);
                
                $help_links = [
                    '../Help/GYIK.php' => 'GYIK',
                    '../Help/uj_funkcio.php' => 'Új funkciók',
                    '../Help/sportszabalyok.php' => 'Sportszabályok',
                    '../Help/szotar.php' => 'Szótár',
                    '../Help/fizetesi_lehetosegek.php' => 'Fizetési lehetőségek',
                    '../Help/jatekosvedelem.php' => 'Játékosvédelem',
                    '../Help/informaciobiztonsag.php' => 'Információbiztonság',
                    '../Help/panaszkezeles.php' => 'Panaszkezelés',
                    '../Help/kapcsolat.php' => 'Kapcsolat',
                    '../Help/adatkezelesi_tajekoztatok.php' => 'Adatkezelési tájékoztatók',
                    '../Help/reszveteli-szabalyzat.php' => 'Részvételi szabályzat'
                ];
                
                foreach ($help_links as $href => $label) {
                    $link_page = basename($href);
                    $is_active = ($current_page === $link_page) ? 'active' : '';
                    echo "<li><a href=\"$href\" class=\"$is_active\">$label</a></li>";
                }
            ?>
        </ul>
    </div>
</aside>