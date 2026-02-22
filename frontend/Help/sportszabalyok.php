<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segítség | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/Help/help.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
</head>

<body>
    <?php include '../../frontend/Components/helpheader.php'; ?>

    <div class="help-container">
        <?php include '../../frontend/Components/helpaside.php'; ?>

        <!-- Fő tartalom (középső rész)-->
        <main class="main-content">
            <h2>DARTS</h2>
            <h3>Verseny célja:</h3>
            <p>Minél nagyobb összegű pontot elérni (maximálisan 180). A játékosok felváltva dobnak körönként 3-3 nyilat.
                A dobás értékét a nyíl által eltalált szektor száma adja meg.</p>
        </main>

        <?php include '../../frontend/Components/promokartya.php'; ?>
    </div>

    <?php include '../../frontend/Components/footer.php'; ?>
    <?php include '../../frontend/Components/modal.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Main/layout.js"></script>
</body>

</html>