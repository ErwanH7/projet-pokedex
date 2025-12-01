<?php
// import_handler.php
declare(strict_types=1);

require_once __DIR__ . '/../config/constantesPDO.php';

$config = ConstantesPDO::getInstance()->getConfig();
$dbCfg = $config['db'] ?? null;
if (!$dbCfg) die("Configuration DB introuvable dans config/config.json");

// crée le PDO
try {
    $pdo = new PDO(
        "mysql:host={$dbCfg['host']};dbname={$dbCfg['dbname']};charset=utf8mb4",
        $dbCfg['username'],
        $dbCfg['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Connexion à la base échouée : " . $e->getMessage());
}

// ---------- CONFIG POKEDEX / FORMES AUTORISÉES ----------
$allowedForms = [
    "ZA"  => ["base", "m", "f", "mega"],
    "SV"  => ["base", "m", "f", "alolan", "galarian", "hisuian", "paldean", "10", "50"],
    "PLA" => ["base", "m", "f", "hisuian"],
    "XY"  => ["base", "m", "f", "mega", "10", "50"],
];

// ---------- UTILITAIRES ----------
function detect_form_code_from_raw(string $rawName): ?string {
    $lower = mb_strtolower($rawName);

    // AZ's Floette
    if (strpos($lower, "az") !== false && strpos($lower, "floette") !== false) {
        return "azett";
    }

    // Zygarde 10% / 50%
    if (preg_match('/zygarde\s*(10%|50%)/i', $rawName, $m)) {
        return ($m[1] === '10%') ? '10' : '50';
    }

    // Mega
    if (stripos($rawName, 'mega') !== false) return 'mega';

    // Gender symbols
    if (strpos($rawName, '♂') !== false) return 'm';
    if (strpos($rawName, '♀') !== false) return 'f';

    // Regionals
    $map = ['alolan' => 'alolan', 'galarian' => 'galarian', 'hisuian' => 'hisuian', 'paldean' => 'paldean'];
    foreach ($map as $k => $v) {
        if (stripos($rawName, $k) !== false || stripos($rawName, ucfirst($k)) !== false) return $v;
    }

    // nothing detected -> null means base/species
    return null;
}

function normalize_name_for_lookup(string $rawName): string {
    // remove gender symbols and parenthesis content and extra "pattern/flower" words
    $name = str_replace(['♂','♀'], '', $rawName);
    $name = preg_replace('/\((.*?)\)/u', '', $name);

    $removeWords = [
        'Red Flower','White Flower','Orange Flower','Yellow Flower','Blue Flower',
        'Meadow Pattern','Marine Pattern','Garden Pattern','Elegant Pattern',
        'High Plains Pattern','Modern Pattern','Monsoon Pattern','Ocean Pattern',
        'Sandstorm Pattern','Savanna Pattern','Sun Pattern','Tundra Pattern',
        'Poké Ball Pattern','Fancy Pattern',
        'Small','Average','Large','Super',
        'Natural','Heart Trim','Star Trim','Diamond Trim','La Reine Trim',
        'Kabuki Trim','Pharaoh Trim','Debutante Trim','Matron Trim','Dandy Trim'
    ];
    $name = str_ireplace($removeWords, '', $name);
    $name = trim($name);
    return $name;
}

function getOrCreateForm(PDO $pdo, int $speciesID, string $formCode, string $sprite = null, string $shiny = null): string {
    // form id convention: species (int) for base, species_formCode for others
    $formID = ($formCode === 'base') ? (string)$speciesID : "{$speciesID}_{$formCode}";

    // exists ?
    $stmt = $pdo->prepare("SELECT id FROM pokemon_forms WHERE id = ?");
    $stmt->execute([$formID]);
    if ($stmt->fetchColumn()) return $formID;

    // insert (créé si absent)
    $stmt = $pdo->prepare("
        INSERT INTO pokemon_forms (id, pokemon_id, form_code, sprite, shiny_sprite)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$formID, $speciesID, $formCode, $sprite, $shiny]);
    return $formID;
}

// ---------- START HANDLER ----------
if (!isset($_FILES['csv'])) {
    die("Aucun fichier n'a été envoyé.");
}
$csvType = $_POST['csv_type'] ?? null;
$pokedexCode = $_POST['pokedex_code'] ?? null;
$csvFile = $_FILES['csv']['tmp_name'] ?? null;

if (!$csvFile || !file_exists($csvFile)) {
    die("Le fichier CSV n'existe pas.");
}

$handle = fopen($csvFile, "r");
if (!$handle) die("Impossible d'ouvrir le fichier CSV.");
$headers = fgetcsv($handle, 10000, ",");

// -------------------- IMPORT DATABASE.CSV --------------------
if ($csvType === "database") {
    echo "<h2>Import du fichier Database.csv…</h2>";

    while (($row = fgetcsv($handle, 10000, ",")) !== false) {
        // protéger contre lignes vides
        if (!isset($row[0])) continue;

        $idRaw        = trim($row[0]);   // ex: 3, 3_m, 3_f, 3_mega
        $name_de      = trim($row[1] ?? '');
        $name_en      = trim($row[2] ?? '');
        $sprite       = trim($row[3] ?? '');
        $shiny_sprite = trim($row[4] ?? '');
        $name_fr      = trim($row[5] ?? '');

        if ($idRaw === '') continue;

        // si c'est une espèce (pas de underscore) => insert/maj dans pokemon
        if (strpos($idRaw, "_") === false) {
            $stmt = $pdo->prepare("
                INSERT INTO pokemon (id, name_fr, name_en, name_de, sprite, shiny_sprite)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name_fr = VALUES(name_fr),
                    name_en = VALUES(name_en),
                    name_de = VALUES(name_de),
                    sprite = VALUES(sprite),
                    shiny_sprite = VALUES(shiny_sprite)
            ");
            // cast id en int si possible
            $idInt = is_numeric($idRaw) ? (int)$idRaw : $idRaw;
            $stmt->execute([$idInt, $name_fr, $name_en, $name_de, $sprite, $shiny_sprite]);
        } else {
            // forme
            list($nationalID, $form_code) = explode("_", $idRaw, 2);

            // vérifier existence de l'espèce
            $stmt = $pdo->prepare("SELECT id FROM pokemon WHERE id = ?");
            $stmt->execute([ (int)$nationalID ]);
            $speciesID = $stmt->fetchColumn();
            if (!$speciesID) {
                echo "⚠ Espèce non trouvée pour la forme : $idRaw<br>";
                continue;
            }

            // insérer / maj la forme
            $formID = "{$nationalID}_{$form_code}";
            $stmt = $pdo->prepare("
                INSERT INTO pokemon_forms (id, pokemon_id, form_code, sprite, shiny_sprite)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    sprite = VALUES(sprite),
                    shiny_sprite = VALUES(shiny_sprite)
            ");
            $stmt->execute([$formID, (int)$nationalID, $form_code, $sprite, $shiny_sprite]);
        }
    }

    echo "✔ Import terminé (Database)<br>";
    fclose($handle);
    exit;
}

// -------------------- IMPORT POKEDEX (JSON preferred, CSV fallback) --------------------
if ($csvType === "pokedex") {

    if (!$pokedexCode) die("Le code Pokédex est obligatoire pour ce type d’import.");
    $codeUpper = strtoupper($pokedexCode);
    echo "<h2>Import du Pokédex {$codeUpper}…</h2>";

    // forms autorisées pour ce pokedex
    $currentAllowed = $allowedForms[$codeUpper] ?? ["base"];

    // vérifie / crée pokedex_list
    $stmt = $pdo->prepare("SELECT id FROM pokedex_list WHERE code = ?");
    $stmt->execute([$codeUpper]);
    $pokedexID = $stmt->fetchColumn();
    if (!$pokedexID) {
        $pdo->prepare("INSERT INTO pokedex_list (code, name) VALUES (?, ?)")->execute([$codeUpper, "Pokédex " . $codeUpper]);
        $pokedexID = $pdo->lastInsertId();
    }

    // Read file content (we accept JSON or CSV)
    $content = file_get_contents($csvFile);
    $jsonData = json_decode($content, true);
    $isJSON = (json_last_error() === JSON_ERROR_NONE) && is_array($jsonData);

    // If JSON not valid, attempt CSV fallback: build $jsonData as array of ['id' => <first column>]
    if (!$isJSON) {
        // Try parse as CSV (comma or semicolon separated)
        $jsonData = [];
        // Re-open handle to ensure we start at beginning
        if ($handle) {
            fclose($handle);
        }
        $handle = fopen($csvFile, 'r');
        if ($handle) {
            // Try autodetect delimiter by reading first line
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = ',';
            if ($firstLine !== false) {
                // if semicolon appears more than comma, use semicolon
                $commaCount = substr_count($firstLine, ',');
                $semiCount  = substr_count($firstLine, ';');
                if ($semiCount > $commaCount) $delimiter = ';';
            }
            // read rows
            while (($row = fgetcsv($handle, 10000, $delimiter)) !== false) {
                // skip entirely empty rows
                $allEmpty = true;
                foreach ($row as $c) { if (trim((string)$c) !== '') { $allEmpty = false; break; } }
                if ($allEmpty) continue;

                // prefer first non-empty cell as the form id
                $formID = "";
                foreach ($row as $c) {
                    if (trim((string)$c) !== '') { $formID = trim((string)$c); break; }
                }
                // If the CSV layout is like your JSON sample (id in column "id"), handle header
                // If first row looks like header with non-numeric "id" label, skip it:
                if (strtolower($formID) === 'id' || strtolower($formID) === 'my id system' || preg_match('/[a-zA-Z]/', $formID) && !preg_match('/\d/', $formID)) {
                    // probably header -> read next row for actual data
                    continue;
                }
                if ($formID !== '') {
                    $jsonData[] = ['id' => $formID];
                }
            }
            fclose($handle);
        } else {
            die("Impossible d'ouvrir le fichier pour lecture (CSV fallback).");
        }
    }

    // maintenant $jsonData est un tableau de lignes; chaque ligne doit contenir 'id' ou la valeur en index 0
    foreach ($jsonData as $entry) {
        // support both formats: entry may be string (id) or object with ["id"] or ["columns"]["Lumiose Dex"]
        $formID = "";

        if (is_string($entry)) {
            $formID = trim($entry);
        } elseif (is_array($entry)) {
            if (isset($entry['id'])) {
                $formID = trim((string)$entry['id']);
            } elseif (isset($entry[0])) {
                $formID = trim((string)$entry[0]);
            } elseif (isset($entry['columns']) && is_array($entry['columns'])) {
                // try to find a typical id column name inside columns (e.g. "Lumiose Dex" or "id")
                if (isset($entry['columns']['id'])) {
                    $formID = trim((string)$entry['columns']['id']);
                } else {
                    // fallback to first non-empty column value
                    foreach ($entry['columns'] as $val) {
                        if (trim((string)$val) !== "") { $formID = trim((string)$val); break; }
                    }
                }
            }
        }

        if ($formID === '') continue;

        // Vérifie que la forme existe dans pokemon_forms
        $stmt = $pdo->prepare("SELECT pokemon_id, form_code FROM pokemon_forms WHERE id = ?");
        $stmt->execute([$formID]);
        $form = $stmt->fetch();

        if (!$form) {
            echo "⚠ Forme introuvable dans pokemon_forms : {$formID}<br>";
            continue;
        }

        $speciesID = (int)$form["pokemon_id"];
        $formCode  = $form["form_code"];

        // Vérifie si cette forme est autorisée dans ce Pokédex
        if (!in_array($formCode, $currentAllowed, true) && $formCode !== "base") {
            // La forme existe mais le Pokédex ne la veut pas → on ignore
            continue;
        }

        // Insère dans pokedex_entries
        $pdo->prepare("INSERT IGNORE INTO pokedex_entries (pokedex_id, pokemon_id) VALUES (?, ?)")->execute([$pokedexID, $formID]);
    }

    echo "✔ Import terminé (Pokedex {$codeUpper})<br>";
    exit;
}



// si type inconnu
fclose($handle);
die("Type d'import inconnu.");

