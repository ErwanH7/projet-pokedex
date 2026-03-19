<?php
declare(strict_types=1);
require_once __DIR__ . '/../users/admin_required.php';
set_time_limit(0);

// Streaming : envoie les lignes au fur et à mesure
header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');
ob_implicit_flush(true);
ob_end_flush();

function out(string $line): void {
    echo $line . "\n";
    flush();
}

function pokeapi_get(string $url): ?array {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header'  => "User-Agent: MonPokedex/1.0\r\n",
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) return null;
    $data = json_decode($json, true);
    if (!is_array($data)) return null;
    // Détecter les réponses d'erreur HTTP (ex: 404 de PokéAPI)
    if (isset($data['detail'])) return null;
    return $data;
}

/**
 * Récupère l'URL d'un sprite Méga depuis Bulbagarden Archives (fallback).
 * Utilise l'API MediaWiki pour obtenir l'URL directe du fichier HOME.
 * formCode : 'mega' | 'mega_x' | 'mega_y'
 */
function bulbagarden_mega_sprite(int $nationalId, string $formCode): ?string {
    $suffix = match($formCode) {
        'mega'   => 'M',
        'mega_x' => 'MX',
        'mega_y' => 'MY',
        default  => null,
    };
    if ($suffix === null) return null;

    $filename = 'HOME' . str_pad((string)$nationalId, 4, '0', STR_PAD_LEFT) . $suffix . '.png';
    $apiUrl   = 'https://archives.bulbagarden.net/w/api.php?action=query&titles=File:'
               . urlencode($filename)
               . '&prop=imageinfo&iiprop=url&format=json';

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header'  => "User-Agent: MonPokedex/1.0\r\n",
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $json = @file_get_contents($apiUrl, false, $ctx);
    if (!$json) return null;
    $data = json_decode($json, true);
    $pages = $data['query']['pages'] ?? [];
    $page  = reset($pages);
    return $page['imageinfo'][0]['url'] ?? null;
}

function form_slug_to_code(string $formSlug, string $baseName): ?string {
    $suffix = ltrim(str_replace($baseName, '', $formSlug), '-');
    if ($suffix === '' || $suffix === $formSlug) return null; // forme de base
    return match($suffix) {
        'mega'     => 'mega',
        'mega-x'   => 'mega_x',
        'mega-y'   => 'mega_y',
        'mega-z'      => 'mega_z',
        'droopy-mega' => 'droopy_mega',
        'stretchy-mega' => 'stretchy_mega',
        'gmax'        => 'gmax',
        'alola'    => 'alolan',
        'galar'    => 'galarian',
        'hisui'    => 'hisuian',
        'paldea'   => 'paldean',
        default    => null,
    };
}

// ---------- CONFIG DB ----------
require_once __DIR__ . '/../config/constantesPDO.php';
$config = ConstantesPDO::getInstance()->getConfig();
$dbCfg  = $config['db'] ?? null;
if (!$dbCfg) { out("❌ Config DB introuvable."); exit; }

function make_pdo(array $cfg): PDO {
    return new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4",
        $cfg['username'], $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

try {
    $pdo = make_pdo($dbCfg);
} catch (PDOException $e) {
    out("❌ Connexion DB : " . $e->getMessage()); exit;
}

function prepare_stmts(PDO $pdo): array {
    return [
        'pokemon' => $pdo->prepare("
            INSERT INTO pokemon (id, name_fr, name_en, name_de, sprite, shiny_sprite)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id = id
        "),
        'form' => $pdo->prepare("
            INSERT INTO pokemon_forms (id, pokemon_id, form_code, sprite, shiny_sprite)
            VALUES (?, ?, 'base', ?, ?)
            ON DUPLICATE KEY UPDATE pokemon_id = pokemon_id
        "),
        'entry' => $pdo->prepare("
            INSERT INTO pokedex_entries (pokedex_id, pokemon_id, position)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE position = VALUES(position)
        "),
        'formVariant' => $pdo->prepare("
            INSERT INTO pokemon_forms (id, pokemon_id, form_code, sprite, shiny_sprite)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE sprite = VALUES(sprite), shiny_sprite = VALUES(shiny_sprite)
        "),
    ];
}

// ---------- PARAMS ----------
$slug         = trim($_POST['pokedex_slug']   ?? '');
$code         = strtoupper(trim($_POST['pokedex_code']  ?? ''));
$name         = trim($_POST['pokedex_name']   ?? '');
$importData   = isset($_POST['import_pokemon_data']);

if (!$slug || !$code || !$name) { out("❌ Paramètres manquants."); exit; }

// ---------- CAS SPÉCIAL : Méga Évolutions ----------
if ($slug === 'mega-evolutions') {
    $megaList = [
        // [nationalId, speciesSlug, [formSlugs...]]
        // --- Ordre du Méga Pokédex ZA ---
        [154, 'meganium',    ['meganium-mega']],
        [500, 'emboar',      ['emboar-mega']],
        [160, 'feraligatr',  ['feraligatr-mega']],
        [15,  'beedrill',    ['beedrill-mega']],
        [18,  'pidgeot',     ['pidgeot-mega']],
        [181, 'ampharos',    ['ampharos-mega']],
        [130, 'gyarados',    ['gyarados-mega']],
        [689, 'barbaracle',  ['barbaracle-mega']],
        [121, 'starmie',     ['starmie-mega']],
        [670, 'floette',     ['floette-mega']],
        [668, 'pyroar',      ['pyroar-mega']],
        [36,  'clefable',    ['clefable-mega']],
        [65,  'alakazam',    ['alakazam-mega']],
        [94,  'gengar',      ['gengar-mega']],
        [545, 'scolipede',   ['scolipede-mega']],
        [71,  'victreebel',  ['victreebel-mega']],
        [308, 'medicham',    ['medicham-mega']],
        [310, 'manectric',   ['manectric-mega']],
        [282, 'gardevoir',   ['gardevoir-mega']],
        [475, 'gallade',     ['gallade-mega']],
        [229, 'houndoom',    ['houndoom-mega']],
        [334, 'altaria',     ['altaria-mega']],
        [531, 'audino',      ['audino-mega']],
        [428, 'lopunny',     ['lopunny-mega']],
        [354, 'banette',     ['banette-mega']],
        [323, 'camerupt',    ['camerupt-mega']],
        [530, 'excadrill',   ['excadrill-mega']],
        [445, 'garchomp',    ['garchomp-mega', 'garchomp-mega-z']],
        [302, 'sableye',     ['sableye-mega']],
        [303, 'mawile',      ['mawile-mega']],
        [359, 'absol',       ['absol-mega', 'absol-mega-z']],
        [448, 'lucario',     ['lucario-mega', 'lucario-mega-z']],
        [80,  'slowbro',     ['slowbro-mega']],
        [319, 'sharpedo',    ['sharpedo-mega']],
        [604, 'eelektross',  ['eelektross-mega']],
        [149, 'dragonite',   ['dragonite-mega']],
        [3,   'venusaur',    ['venusaur-mega']],
        [6,   'charizard',   ['charizard-mega-x', 'charizard-mega-y']],
        [9,   'blastoise',   ['blastoise-mega']],
        [687, 'malamar',     ['malamar-mega']],
        [691, 'dragalge',    ['dragalge-mega']],
        [362, 'glalie',      ['glalie-mega']],
        [478, 'froslass',    ['froslass-mega']],
        [460, 'abomasnow',   ['abomasnow-mega']],
        [212, 'scizor',      ['scizor-mega']],
        [127, 'pinsir',      ['pinsir-mega']],
        [214, 'heracross',   ['heracross-mega']],
        [701, 'hawlucha',    ['hawlucha-mega']],
        [560, 'scrafty',     ['scrafty-mega']],
        [609, 'chandelure',  ['chandelure-mega']],
        [142, 'aerodactyl',  ['aerodactyl-mega']],
        [208, 'steelix',     ['steelix-mega']],
        [306, 'aggron',      ['aggron-mega']],
        [248, 'tyranitar',   ['tyranitar-mega']],
        [658, 'greninja',    ['greninja-mega']],
        [870, 'falinks',     ['falinks-mega']],
        [652, 'chesnaught',  ['chesnaught-mega']],
        [227, 'skarmory',    ['skarmory-mega']],
        [655, 'delphox',     ['delphox-mega']],
        [373, 'salamence',   ['salamence-mega']],
        [115, 'kangaskhan',  ['kangaskhan-mega']],
        [780, 'drampa',      ['drampa-mega']],
        [376, 'metagross',   ['metagross-mega']],
        [718, 'zygarde',     ['zygarde-mega']],
        [719, 'diancie',     ['diancie-mega']],
        [150, 'mewtwo',      ['mewtwo-mega-x', 'mewtwo-mega-y']],
        // --- Exclusifs ZA supplémentaires ---
        [970, 'glimmora',    ['glimmora-mega']],
        [740, 'crabominable',['crabominable-mega']],
        [807, 'zeraora',     ['zeraora-mega']],
        [801, 'magearna',    ['magearna-mega']],
        [491, 'darkrai',     ['darkrai-mega']],
        [485, 'heatran',     ['heatran-mega']],
        [26,  'raichu',     ['raichu-mega-x', 'raichu-mega-y']],
        [358, 'chimecho',   ['chimecho-mega']],
        [398, 'staraptor',  ['staraptor-mega']],
        [623, 'golurk',     ['golurk-mega']],
        [678, 'meowstic',   ['meowstic-mega']],
        [768, 'golisopod',  ['golisopod-mega']],
        [952, 'scovillain', ['scovillain-mega']],
        [978, 'tatsugiri',  ['tatsugiri-mega', 'tatsugiri-droopy-mega', 'tatsugiri-stretchy-mega']],
        [998, 'baxcalibur', ['baxcalibur-mega']],
        // Note : Éoko, Golemastoc, Glaivodo, Étouraptor sont des nouveaux Pokémon ZA
        // sans ID national PokéAPI connu pour l'instant — à ajouter manuellement plus tard.
    ];

    // Créer ou mettre à jour le pokédex
    $stmt = $pdo->prepare("SELECT id FROM pokedex_list WHERE code = ?");
    $stmt->execute([$code]);
    $pokedexID = $stmt->fetchColumn();
    if (!$pokedexID) {
        $pdo->prepare("INSERT INTO pokedex_list (code, name) VALUES (?, ?)")->execute([$code, $name]);
        $pokedexID = $pdo->lastInsertId();
        out("✔ Pokédex « {$code} » créé (id={$pokedexID}).");
    } else {
        $pdo->prepare("UPDATE pokedex_list SET name = ? WHERE id = ?")->execute([$name, $pokedexID]);
        out("✔ Pokédex « {$code} » existant mis à jour.");
    }

    ['pokemon' => $insertPokemon, 'form' => $insertForm, 'entry' => $insertEntry, 'formVariant' => $insertFormVariant] = prepare_stmts($pdo);

    $total = count($megaList);
    $done  = 0;
    $pos   = 1;

    foreach ($megaList as [$natId, $speciesSlug, $formSlugs]) {
        // Maintenir la connexion MySQL active
        try { $pdo->query('SELECT 1'); }
        catch (PDOException $e) {
            $pdo = make_pdo($dbCfg);
            ['pokemon' => $insertPokemon, 'form' => $insertForm, 'entry' => $insertEntry, 'formVariant' => $insertFormVariant] = prepare_stmts($pdo);
        }

        $name_fr = $name_en = $name_de = null;
        $baseSprite = $baseShiny = null;

        if ($importData) {
            $speciesData = pokeapi_get("https://pokeapi.co/api/v2/pokemon-species/{$natId}/");
            if ($speciesData) {
                foreach ($speciesData['names'] as $n) {
                    if ($n['language']['name'] === 'fr') $name_fr = $n['name'];
                    if ($n['language']['name'] === 'en') $name_en = $n['name'];
                    if ($n['language']['name'] === 'de') $name_de = $n['name'];
                }
            }
            $pokData = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$natId}/");
            if ($pokData) {
                $baseSprite = $pokData['sprites']['front_default'] ?? null;
                $baseShiny  = $pokData['sprites']['front_shiny']  ?? null;
            }
        }

        $displayName = $name_en ?? $speciesSlug;
        $insertPokemon->execute([$natId, $name_fr, $name_en ?? $speciesSlug, $name_de, $baseSprite, $baseShiny]);
        $insertForm->execute([(string)$natId, $natId, $baseSprite, $baseShiny]);

        // Importer chaque forme méga et l'ajouter au pokédex
        foreach ($formSlugs as $formSlug) {
            $formCode = form_slug_to_code($formSlug, $speciesSlug);
            if (!$formCode) continue;

            $fSprite = $fShiny = null;
            if ($importData) {
                // Essayer d'abord l'endpoint pokemon-form
                $formData = pokeapi_get("https://pokeapi.co/api/v2/pokemon-form/{$formSlug}/");
                if ($formData) {
                    $fSprite = $formData['sprites']['front_default'] ?? null;
                    $fShiny  = $formData['sprites']['front_shiny']  ?? null;
                }
                // Fallback 1 : endpoint pokemon/{slug}
                if (!$fSprite) {
                    $megaPokData = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$formSlug}/");
                    if ($megaPokData) {
                        $fSprite = $megaPokData['sprites']['front_default'] ?? null;
                        $fShiny  = $megaPokData['sprites']['front_shiny']  ?? null;
                    }
                }
                // Fallback 2 : Bulbagarden Archives (sprite HOME)
                if (!$fSprite) {
                    $fSprite = bulbagarden_mega_sprite($natId, $formCode);
                }
            }

            $formId = "{$natId}_{$formCode}";
            $insertFormVariant->execute([$formId, $natId, $formCode, $fSprite, $fShiny]);
            // L'entrée pokédex pointe vers la FORME MÉGA
            $insertEntry->execute([$pokedexID, $formId, $pos]);
            out("  [{$pos}] {$displayName} ({$formCode})" . ($fSprite ? '' : ' ⚠ pas de sprite'));
        }

        $pos++;
        $done++;
        if ($done % 10 === 0 || $done === $total) {
            out("  [{$done}/{$total}] espèces traitées…");
        }
    }

    out("\n✅ Import terminé !");
    out("   • {$done} espèces, " . ($pos - 1) . " formes méga importées");
    out("   • Pokédex « {$code} » prêt dans l'application.");
    exit;
}

// ---------- 1. FETCH POKÉDEX (supporte plusieurs slugs séparés par +) ----------
$slugs = array_filter(array_map('trim', explode('+', $slug)));
$entries = [];
$seen    = []; // éviter les doublons lors de la fusion
$position = 1; // position régionale globale pour les dex fusionnés

foreach ($slugs as $singleSlug) {
    out("📡 Récupération du Pokédex « {$singleSlug} » depuis PokéAPI…");
    $dexUrl  = "https://pokeapi.co/api/v2/pokedex/{$singleSlug}/";
    $dexData = pokeapi_get($dexUrl);
    if (!$dexData || empty($dexData['pokemon_entries'])) {
        out("⚠ Pokédex introuvable ou vide (slug: {$singleSlug}), ignoré.");
        continue;
    }
    $subEntries = $dexData['pokemon_entries'];
    out("  ✔ " . count($subEntries) . " entrées trouvées.");

    foreach ($subEntries as $e) {
        preg_match('/\/(\d+)\/?$/', $e['pokemon_species']['url'], $m);
        $natId = isset($m[1]) ? (int)$m[1] : null;
        if (!$natId || isset($seen[$natId])) continue; // doublon inter-dex ignoré
        $seen[$natId] = true;
        // Remplacer entry_number par une position globale continue
        $e['entry_number'] = $position++;
        $entries[] = $e;
    }
}

if (empty($entries)) {
    out("❌ Aucune entrée récupérée."); exit;
}

out("✔ Total fusionné : " . count($entries) . " Pokémon uniques.");

// ---------- 2. CRÉER OU MAJ POKÉDEX DANS LA DB ----------
$stmt = $pdo->prepare("SELECT id FROM pokedex_list WHERE code = ?");
$stmt->execute([$code]);
$pokedexID = $stmt->fetchColumn();
if (!$pokedexID) {
    $pdo->prepare("INSERT INTO pokedex_list (code, name) VALUES (?, ?)")->execute([$code, $name]);
    $pokedexID = $pdo->lastInsertId();
    out("✔ Pokédex « {$code} » créé (id={$pokedexID}).");
} else {
    $pdo->prepare("UPDATE pokedex_list SET name = ? WHERE id = ?")->execute([$name, $pokedexID]);
    out("✔ Pokédex « {$code} » existant mis à jour.");
}

// ---------- 3. IMPORT ENTRÉES ----------
out("\n📋 Import des entrées…");

['pokemon' => $insertPokemon, 'form' => $insertForm, 'entry' => $insertEntry, 'formVariant' => $insertFormVariant] = prepare_stmts($pdo);

$total   = count($entries);
$done    = 0;
$skipped = 0;

foreach ($entries as $entry) {
    // Maintenir la connexion MySQL active (les appels API peuvent durer plusieurs minutes)
    try { $pdo->query('SELECT 1'); }
    catch (PDOException $e) {
        $pdo = make_pdo($dbCfg);
        ['pokemon' => $insertPokemon, 'form' => $insertForm, 'entry' => $insertEntry, 'formVariant' => $insertFormVariant] = prepare_stmts($pdo);
    }

    $regionalNum = (int)$entry['entry_number'];
    $speciesUrl  = $entry['pokemon_species']['url'];

    // Extraire l'ID national depuis l'URL (ex: .../pokemon-species/152/)
    preg_match('/\/(\d+)\/?$/', $speciesUrl, $m);
    $nationalId = isset($m[1]) ? (int)$m[1] : null;

    if (!$nationalId) {
        out("  ⚠ Impossible d'extraire l'ID national pour l'entrée régionale #{$regionalNum}");
        $skipped++;
        continue;
    }

    $name_fr = null;
    $name_en = $entry['pokemon_species']['name'];  // slug par défaut
    $name_de = null;
    $sprite  = null;
    $shiny   = null;

    // ---------- Fetch données Pokémon (optionnel) ----------
    if ($importData) {
        // Noms dans les langues
        $speciesData = pokeapi_get($speciesUrl);
        if ($speciesData) {
            foreach ($speciesData['names'] as $nameEntry) {
                $lang = $nameEntry['language']['name'];
                if ($lang === 'fr')   $name_fr = $nameEntry['name'];
                if ($lang === 'en')   $name_en = $nameEntry['name'];
                if ($lang === 'de')   $name_de = $nameEntry['name'];
            }
        }

        // Sprites (depuis l'endpoint pokemon/{id})
        $pokemonData = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$nationalId}/");
        if ($pokemonData) {
            $sprite = $pokemonData['sprites']['front_default']       ?? null;
            $shiny  = $pokemonData['sprites']['front_shiny']         ?? null;
        }
    }

    // Insert pokemon (base species)
    $insertPokemon->execute([$nationalId, $name_fr, $name_en, $name_de, $sprite, $shiny]);

    // Insert forme de base
    $insertForm->execute([(string)$nationalId, $nationalId, $sprite, $shiny]);

    // ---------- Formes alternatives (Méga, Gigantamax, formes régionales…) ----------
    if ($importData && $pokemonData && !empty($pokemonData['forms'])) {
        $baseName = strtolower($entry['pokemon_species']['name']); // slug PokéAPI de l'espèce
        foreach ($pokemonData['forms'] as $formRef) {
            $formSlug = $formRef['name'];
            $formCode = form_slug_to_code($formSlug, $baseName);
            if (!$formCode) continue; // forme de base ou inconnue → déjà traitée

            $formData = pokeapi_get("https://pokeapi.co/api/v2/pokemon-form/{$formSlug}/");
            if (!$formData) continue;

            $fSprite = $formData['sprites']['front_default'] ?? null;
            $fShiny  = $formData['sprites']['front_shiny']  ?? null;
            if (!$fSprite) continue; // pas de sprite disponible → ignorer

            $formID = "{$nationalId}_{$formCode}";
            $insertFormVariant->execute([$formID, $nationalId, $formCode, $fSprite, $fShiny]);
        }
    }

    // Insert entrée pokédex
    $insertEntry->execute([$pokedexID, (string)$nationalId, $regionalNum]);

    $done++;
    if ($done % 50 === 0 || $done === $total) {
        out("  [{$done}/{$total}] importés…");
    }
}

out("\n✅ Import terminé !");
out("   • {$done} Pokémon importés");
if ($skipped) out("   • {$skipped} ignorés (ID non résolu)");
out("   • Pokédex « {$code} » prêt dans l'application.");
