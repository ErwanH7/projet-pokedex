<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin – Import Pokédex CSV</title>
</head>
<body>

<h1>Admin – Importation CSV Pokédex</h1>

<form action="import_handler.php" method="POST" enctype="multipart/form-data">

    <label>Type de CSV :</label><br>
    <select name="csv_type" required>
        <option value="database">Database (noms FR/EN/DE)</option>
        <option value="pokedex">Pokedex (ZA, SV, XY, etc.)</option>
    </select>
    <br><br>

    <label>Fichier CSV :</label><br>
    <input type="file" name="csv" accept=".csv" required>
    <br><br>

    <label>Code du Pokédex (ZA, SV, XY, ULT, etc.)</label><br>
    <input type="text" name="pokedex_code" placeholder="Ex: ZA" maxlength="10">
    <br><br>

    <button type="submit">Importer</button>

</form>

</body>
</html>
