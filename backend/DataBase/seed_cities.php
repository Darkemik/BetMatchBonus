<?php
/**
 * SEED_CITIES.PHP — Magyar városok beszúrása a Cities táblába
 * 
 * Futtatás: php seed_cities.php
 * Csak egyszer kell futtatni. Meglévő városokat nem duplikálja (INSERT IGNORE).
 */
require_once dirname(__DIR__) . "/connect.php";

// Magyarország country_id lekérése vagy létrehozása
$stmt = $conn->prepare("SELECT id FROM Countries WHERE code = 'HUN' LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
$country = $res->fetch_assoc();
$stmt->close();

if (!$country) {
    $conn->query("INSERT INTO Countries (code, name) VALUES ('HUN', 'Magyarország')");
    $countryId = $conn->insert_id;
    echo "Magyarország hozzáadva (id={$countryId})\n";
} else {
    $countryId = $country['id'];
    echo "Magyarország megvan (id={$countryId})\n";
}

$cities = [
    'Abony','Ajka','Albertirsa','Aszód',
    'Baja','Baktalórántháza','Balassagyarmat','Balmazújváros','Barcs','Bátonyterenye',
    'Battonya','Békés','Békéscsaba','Berettyóújfalu','Biatorbágy','Bicske','Bogács',
    'Bonyhád','Budakalász','Budakeszi','Budaörs','Budapest','Budaörs',
    'Celldömölk','Cegléd','Csenger','Csongrád','Csorna','Csurgó',
    'Dabas','Debrecen','Derecske','Devecser','Diósd','Dorog','Dunakeszi','Dunaújváros',
    'Edelény','Eger','Érd','Encs','Esztergom',
    'Fehérgyarmat','Fertőd','Fertőszentmiklós','Fonyód','Füzesabony','Füzesgyarmat',
    'Gárdony','Gödöllő','Gyál','Gyomaendrőd','Gyöngyös','Győr','Gyula',
    'Hajdúböszörmény','Hajdúdorog','Hajdúhadház','Hajdúnánás','Hajdúszoboszló',
    'Halásztelek','Hatvan','Heves','Hévíz','Hódmezővásárhely',
    'Ibrány','Isaszeg',
    'Jánoshalma','Jászapáti','Jászárokszállás','Jászberény','Jászfényszaru','Jászladány',
    'Kalocsa','Kaposvár','Kapuvár','Kazincbarcika','Kecskemét','Keszthely','Kisbér',
    'Kiskunfélegyháza','Kiskunhalas','Kiskunlacháza','Kiskunmajsa','Kiskőrös','Kistelek',
    'Kisvárda','Komárom','Komló','Körmend','Kőszeg','Kunhegyes','Kunszentmárton',
    'Kunszentmiklós',
    'Lajosmizse','Lenti','Leányfalu','Létavértes',
    'Makó','Marcali','Martfű','Mátészalka','Mezőberény','Mezőcsát','Mezőkovácsháza',
    'Mezőkövesd','Mezőtúr','Mindszent','Miskolc','Mohács','Monor','Mosonmagyaróvár',
    'Mór','Munkács',
    'Nagykálló','Nagykáta','Nagykőrös','Nagykanizsa','Nagyatád','Nagyecsed',
    'Nyergesújfalu','Nyíradony','Nyírbátor','Nyíregyháza','Nyírmada',
    'Orosháza','Oroszlány','Ózd',
    'Paks','Pánnonhalma','Pápa','Pásztó','Pécel','Pécs','Pétfürdő','Piliscsaba',
    'Pilisvörösvár','Pomáz','Putnok','Püspökladány',
    'Rácalmás','Ráckeve','Recsk','Rétság',
    'Sajószentpéter','Salgótarján','Sándorfalva','Sárbogárd','Sárospatak','Sárvár',
    'Sátoraljaújhely','Sellye','Siófok','Soltvadkert','Sopron','Sümeg',
    'Szarvas','Százhalombatta','Szeged','Szegvár','Szécsény','Székesfehérvár',
    'Szekszárd','Szendrő','Szentendre','Szentes','Szentgotthárd','Szentlőrinc',
    'Szerencs','Szigetszentmiklós','Szigetvár','Szikszó','Szob','Szolnok','Szombathely',
    'Tab','Tamási','Tata','Tatabánya','Tapolca','Tiszaföldvár','Tiszafüred',
    'Tiszakécske','Tiszalúc','Tiszaújváros','Tiszavasvári','Tokaj','Tolna',
    'Törökbálint','Törökszentmiklós','Túrkeve',
    'Újfehértó','Újszász',
    'Vác','Vasvár','Vecsés','Veresegyház','Veszprém','Villány','Visegrád',
    'Záhony','Zalaegerszeg','Zalaszentgrót','Zirc','Zugló'
];

$inserted = 0;
$skipped = 0;
$stmt = $conn->prepare("INSERT IGNORE INTO Cities (country_id, name, is_active) VALUES (?, ?, 1)");

foreach ($cities as $city) {
    $stmt->bind_param("is", $countryId, $city);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $inserted++;
    } else {
        $skipped++;
    }
}
$stmt->close();

echo "Kész! Beszúrva: {$inserted}, kihagyva (már létezett): {$skipped}\n";
echo "Összesen: " . count($cities) . " város\n";
