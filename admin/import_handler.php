<?php
// import_handler.php
declare(strict_types=1);
require_once __DIR__ . '/../users/admin_required.php';

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
    "ZA"  => ["base", "mega", "10", "50"],
    "ZAL" => ["base", "mega", "10", "50"],
    "ZAH" => ["base", "mega", "10", "50"],
    "SV"  => ["base", "alolan", "galarian", "hisuian", "paldean", "10", "50"],
    "PLA" => ["base", "hisuian"],
    "XY"  => ["base", "mega", "10", "50"],
    "EV"  => ["base", "alolan", "galarian", "hisuian", "paldean"],
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
        // Formes spéciales — à supprimer pour retrouver l'espèce de base
        'Mega', 'Gigantamax', 'G-Max',
        'Alolan', 'Galarian', 'Hisuian', 'Paldean',
        // Fleurs Flabébé/Florges
        'Red Flower','White Flower','Orange Flower','Yellow Flower','Blue Flower',
        // Patterns Vivillon
        'Meadow Pattern','Marine Pattern','Garden Pattern','Elegant Pattern',
        'High Plains Pattern','Modern Pattern','Monsoon Pattern','Ocean Pattern',
        'Sandstorm Pattern','Savanna Pattern','Sun Pattern','Tundra Pattern',
        'Poké Ball Pattern','Fancy Pattern',
        // Tailles
        'Small','Average','Large','Super',
        // Trims Furfrou
        'Natural','Heart Trim','Star Trim','Diamond Trim','La Reine Trim',
        'Kabuki Trim','Pharaoh Trim','Debutante Trim','Matron Trim','Dandy Trim'
    ];
    $name = str_ireplace($removeWords, '', $name);
    $name = trim($name);
    return $name;
}

function getOrCreateForm(PDO $pdo, int $speciesID, string $formCode, ?string $sprite = null, ?string $shiny = null): string {
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
if (!isset($_FILES['file'])) {
    die("Aucun fichier n'a été envoyé.");
}
$csvType = $_POST['csv_type'] ?? null;
$pokedexCode = $_POST['pokedex_code'] ?? null;
$filePath = $_FILES['file']['tmp_name'] ?? null;

if (!$filePath || !file_exists($filePath)) {
    die("Le fichier n'existe pas.");
}

$handle = fopen($filePath, "r");
if (!$handle) die("Impossible d'ouvrir le fichier.");
$headers = fgetcsv($handle, 10000, ",");

// -------------------- IMPORT DATABASE.CSV --------------------
if ($csvType === "database") {
    echo "<h2>Import du fichier Database…</h2>";

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
        $pdo->prepare("INSERT INTO pokedex_list (code, name, name_en, name_de) VALUES (?, ?, NULL, NULL)")->execute([$codeUpper, "Pokédex " . $codeUpper]);
        $pokedexID = $pdo->lastInsertId();
    }

    // Read file content (we accept JSON or CSV)
    $content = file_get_contents($filePath);
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
        $handle = fopen($filePath, 'r');
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
        $regionalNumber = null;

        if (is_string($entry)) {
            $formID = trim($entry);
        } elseif (is_array($entry)) {
            // Numéro régional (position d'affichage)
            if (isset($entry['regional_number']) && $entry['regional_number'] !== '') {
                $regionalNumber = (int)$entry['regional_number'];
            }

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

        // Extraire le code de forme depuis l'id (ex: "3_m" → formCode="m")
        if (strpos($formID, '_') !== false) {
            [, $formCode] = explode('_', $formID, 2);
        } else {
            $formCode = 'base';
        }
        // Les formes de genre sont traitées comme la forme de base
        if (in_array($formCode, ['m', 'f'], true)) {
            $formCode = 'base';
        }

        // ---------- Résolution de l'ID national ----------
        // Priorité 1 : champ "national_id" explicite (nouveau format JSON)
        $speciesID = null;
        if (is_array($entry) && isset($entry['national_id']) && is_numeric($entry['national_id'])) {
            $speciesID = (int)$entry['national_id'];
        }

        // Priorité 2 : recherche par nom anglais dans la table pokemon
        if (!$speciesID && is_array($entry) && isset($entry['columns']['Name'])) {
            $normalizedName = normalize_name_for_lookup($entry['columns']['Name']);
            if ($normalizedName !== '') {
                $stmt = $pdo->prepare("SELECT id FROM pokemon WHERE name_en = ?");
                $stmt->execute([$normalizedName]);
                $found = $stmt->fetchColumn();
                if ($found) $speciesID = (int)$found;
            }
        }

        // Priorité 3 : l'id du JSON est déjà un ID national (ancien format ou CSV simple)
        if (!$speciesID) {
            $speciesID = (int)explode('_', $formID, 2)[0];
        }

        // ---------- Résolution du numéro régional ----------
        // Priorité 1 : champ "regional_number" explicite
        // (déjà lu plus haut dans $regionalNumber)

        // Priorité 2 : colonne "Lumiose Dex" (ou autre colonne régionale connue)
        if ($regionalNumber === null && is_array($entry) && isset($entry['columns'])) {
            $regionalCols = ['Lumiose Dex', 'Regional Dex', 'Regional', 'Dex #'];
            foreach ($regionalCols as $col) {
                if (isset($entry['columns'][$col]) && is_numeric($entry['columns'][$col]) && $entry['columns'][$col] !== '') {
                    $regionalNumber = (int)$entry['columns'][$col];
                    break;
                }
            }
        }

        // Vérifie que l'espèce existe dans pokemon
        $stmt = $pdo->prepare("SELECT id FROM pokemon WHERE id = ?");
        $stmt->execute([$speciesID]);
        if (!$stmt->fetchColumn()) {
            $nameHint = $entry['columns']['Name'] ?? $formID;
            echo "⚠ Espèce introuvable : « {$nameHint} » (id national résolu : #{$speciesID})<br>";
            continue;
        }

        // Crée la forme si elle n'existe pas
        $actualFormID = getOrCreateForm($pdo, $speciesID, $formCode);

        // Vérifie si cette forme est autorisée dans ce Pokédex
        if (!in_array($formCode, $currentAllowed, true) && $formCode !== "base") {
            continue;
        }

        // ---------- Exclusivité de version ----------
        $version = null;
        if (is_array($entry)) {
            if (isset($entry['version']) && $entry['version'] !== '') {
                $version = strtolower(trim($entry['version']));
            } elseif (isset($entry['columns'])) {
                foreach (['Version', 'Exclusive', 'Exclusif', 'Jeu', 'Game'] as $col) {
                    if (!empty($entry['columns'][$col])) {
                        $version = strtolower(trim($entry['columns'][$col]));
                        break;
                    }
                }
            }
        }
        // Normalisation des valeurs acceptées
        if (!in_array($version, ['scarlet', 'violet', 'ecarlate', 'la', null], true)) {
            $version = null;
        }
        if ($version === 'ecarlate') $version = 'scarlet';

        // Insère dans pokedex_entries avec la position régionale et la version
        $pdo->prepare("
            INSERT INTO pokedex_entries (pokedex_id, pokemon_id, position, version)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE position = VALUES(position), version = VALUES(version)
        ")->execute([$pokedexID, $actualFormID, $regionalNumber, $version]);
    }

    echo "✔ Import terminé (Pokedex {$codeUpper})<br>";
    exit;
}



// si type inconnu
fclose($handle);
die("Type d'import inconnu.");

