<?php
require_once 'config/pdo.php';
$pdo = DB::getPDO();

session_start();
$userID = $_SESSION['user_id'] ?? 1; // à adapter

$dexCode = $_GET['dex'] ?? null;
if (!$dexCode) die("Aucun Pokédex spécifié.");


// On récupère le pokédex demandé
$stmt = $pdo->prepare("SELECT id, name FROM pokedex_list WHERE code = ?");
$stmt->execute([$dexCode]);
$pokedex = $stmt->fetch();
if (!$pokedex) die("Pokédex introuvable.");

$pokedexID = $pokedex['id'];


// On récupère TOUTES les entrées du pokédex
$stmt = $pdo->prepare("
    SELECT pe.pokemon_id, p.name_fr, p.id AS species_id, p.sprite
    FROM pokedex_entries pe
    JOIN pokemon p ON SUBSTRING_INDEX(pe.pokemon_id, '_', 1) = p.id
    WHERE pe.pokedex_id = ?
    ORDER BY p.id ASC
");
$stmt->execute([$pokedexID]);
$entries = $stmt->fetchAll();


// On regroupe par espèce
$species = [];

foreach ($entries as $row) {
    $pid = $row['pokemon_id'];   // ex: "123_m"
    $speciesID = $row['species_id']; // ex: 123
    $name = $row['name_fr'];

    // Forme (m, f, mega…)
    $form = (strpos($pid, '_') !== false) ? explode("_", $pid)[1] : "normal";

    // Ajouter dans le tableau groupé :
    $species[$speciesID]['name'] = $name;
    $species[$speciesID]['forms'][$pid] = [
        "code" => $form,
        "image" => $row['sprite'] // lien URL
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($pokedex['name']) ?></title>
<style>
body { font-family: Arial; background: #f5f5f5; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; padding: 20px; }
.card { background: white; border-radius: 12px; padding: 10px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
.card img { width: 110px; height: 110px; object-fit: contain; image-rendering: pixelated; }
.form-label { font-size: 12px; color: #555; margin-bottom: 4px; }
</style>

<script>
// AJAX pour mettre à jour les captures
function toggleCaught(checkbox, pokemonID, pokedexID) {
    const caught = checkbox.checked ? 1 : 0;

    fetch("update_caught.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `pokemon_id=${pokemonID}&pokedex_id=${pokedexID}&caught=${caught}`
    });
}
</script>

</head>
<body>

<h1 style="text-align:center"><?= htmlspecialchars($pokedex['name']) ?></h1>

<div class="grid">

<?php
// Précharger les captures de l’utilisateur
$stmt = $pdo->prepare("
    SELECT pokemon_id, caught FROM user_progress
    WHERE user_id = ? AND pokedex_id = ?
");
$stmt->execute([$userID, $pokedexID]);
$caughtData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);


foreach ($species as $speciesID => $data):
?>
    <div class="card">

        <strong><?= htmlspecialchars($data['name']) ?></strong><br>

        <!-- Case à cocher globale -->
        <label>
            <input type="checkbox"
                   onclick="toggleCaught(this,'<?= $speciesID ?>','<?= $pokedexID ?>')"
                   <?= isset($caughtData[$speciesID]) && $caughtData[$speciesID] == 1 ? "checked" : "" ?>>
            Capturé
        </label>

        <hr>

        <?php foreach ($data['forms'] as $pid => $form): ?>
            <div>
                <div class="form-label"><?= htmlspecialchars($form['code']) ?></div>
                <img src="<?= htmlspecialchars($form['image']) ?>" alt="">
            </div>
        <?php endforeach; ?>

    </div>
<?php endforeach; ?>

</div>

</body>
</html>
