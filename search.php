<?php
declare(strict_types=1);
include_once 'include.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode(['pokedexes' => [], 'pokemon' => []]); exit; }
$q = mb_substr($q, 0, 60);

try {
    $pdo  = DB::getPDO();
    // Échapper les caractères spéciaux LIKE pour éviter les wildcard non désirés
    $qSafe = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $like  = '%' . $qSafe . '%';

    // --- Pokédex matching ---
    $stmt = $pdo->prepare("
        SELECT code, name
        FROM pokedex_list
        WHERE name LIKE ? OR code LIKE ?
        ORDER BY name ASC
        LIMIT 8
    ");
    $stmt->execute([$like, $like]);
    $pokedexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Pokémon matching ---
    // GROUP_CONCAT safe length
    $pdo->exec("SET SESSION group_concat_max_len = 4096");

    $stmt = $pdo->prepare("
        SELECT
            p.id          AS species_id,
            p.name_fr,
            p.name_en,
            p.name_de,
            COALESCE(
                (SELECT pf2.sprite FROM pokemon_forms pf2
                 WHERE pf2.pokemon_id = p.id AND pf2.form_code = 'base' LIMIT 1),
                (SELECT pf3.sprite FROM pokemon_forms pf3
                 WHERE pf3.pokemon_id = p.id LIMIT 1),
                p.sprite
            ) AS sprite,
            GROUP_CONCAT(DISTINCT pl.code ORDER BY pl.id SEPARATOR ',')   AS dex_codes,
            GROUP_CONCAT(DISTINCT pl.name ORDER BY pl.id SEPARATOR '||')  AS dex_names
        FROM pokemon p
        LEFT JOIN pokemon_forms pf ON pf.pokemon_id = p.id
        LEFT JOIN pokedex_entries pe ON pe.pokemon_id = pf.id
        LEFT JOIN pokedex_list pl ON pl.id = pe.pokedex_id
        WHERE p.name_fr LIKE ?
           OR p.name_en LIKE ?
           OR p.name_de LIKE ?
        GROUP BY p.id
        ORDER BY p.id ASC
        LIMIT 15
    ");
    $stmt->execute([$like, $like, $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pokemon = [];
    foreach ($rows as $row) {
        $codes = $row['dex_codes'] ? explode(',', $row['dex_codes']) : [];
        $names = $row['dex_names'] ? explode('||', $row['dex_names']) : [];
        $dexes = [];
        foreach ($codes as $i => $code) {
            $dexes[] = ['code' => $code, 'name' => $names[$i] ?? $code];
        }
        $pokemon[] = [
            'species_id' => (int)$row['species_id'],
            'name_fr'    => $row['name_fr'],
            'name_en'    => $row['name_en'],
            'name_de'    => $row['name_de'],
            'sprite'     => $row['sprite'],
            'dexes'      => $dexes,
        ];
    }

    echo json_encode(['pokedexes' => $pokedexes, 'pokemon' => $pokemon]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
