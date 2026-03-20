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
            INSERT INTO pokemon (id, name_fr, name_en, name_de, name_es, sprite, shiny_sprite)
            VALUES (?, ?, ?, ?, ?, ?, ?)
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
$slug         = trim($_POST['pokedex_slug']    ?? '');
$code         = strtoupper(trim($_POST['pokedex_code']   ?? ''));
$name         = trim($_POST['pokedex_name']    ?? '');
$name_en      = trim($_POST['pokedex_name_en'] ?? '') ?: null;
$name_de      = trim($_POST['pokedex_name_de'] ?? '') ?: null;
$name_es      = trim($_POST['pokedex_name_es'] ?? '') ?: null;
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
        $pdo->prepare("INSERT INTO pokedex_list (code, name, name_en, name_de, name_es) VALUES (?, ?, ?, ?, ?)")->execute([$code, $name, $name_en, $name_de, $name_es]);
        $pokedexID = $pdo->lastInsertId();
        out("✔ Pokédex « {$code} » créé (id={$pokedexID}).");
    } else {
        $pdo->prepare("UPDATE pokedex_list SET name = ?, name_en = ?, name_de = ?, name_es = ? WHERE id = ?")->execute([$name, $name_en, $name_de, $name_es, $pokedexID]);
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

        $name_fr = $name_en = $name_de = $name_es = null;
        $baseSprite = $baseShiny = null;

        if ($importData) {
            $speciesData = pokeapi_get("https://pokeapi.co/api/v2/pokemon-species/{$natId}/");
            if ($speciesData) {
                foreach ($speciesData['names'] as $n) {
                    if ($n['language']['name'] === 'fr') $name_fr = $n['name'];
                    if ($n['language']['name'] === 'en') $name_en = $n['name'];
                    if ($n['language']['name'] === 'de') $name_de = $n['name'];
                    if ($n['language']['name'] === 'es') $name_es = $n['name'];
                }
            }
            $pokData = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$natId}/");
            if ($pokData) {
                $baseSprite = $pokData['sprites']['front_default'] ?? null;
                $baseShiny  = $pokData['sprites']['front_shiny']  ?? null;
            }
        }

        $displayName = $name_en ?? $speciesSlug;
        $insertPokemon->execute([$natId, $name_fr, $name_en ?? $speciesSlug, $name_de, $name_es, $baseSprite, $baseShiny]);
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

// ---------- CAS SPÉCIAL : Formes Alternatives ----------
if ($slug === 'formes-alternatives') {
    // [species_id, api_form_slug, form_code]
    // form_code 'female'    : sprite front_female depuis pokemon/{id}
    // form_code 'hisuian_f' : front_female ou front_default depuis pokemon-form/{slug}
    $formesList = [
        // ── FORMES ALOLA (18) ─────────────────────────────────────────
        [19,  'rattata-alola',    'alolan'],
        [20,  'raticate-alola',   'alolan'],
        [26,  'raichu-alola',     'alolan'],
        [27,  'sandshrew-alola',  'alolan'],
        [28,  'sandslash-alola',  'alolan'],
        [37,  'vulpix-alola',     'alolan'],
        [38,  'ninetales-alola',  'alolan'],
        [50,  'diglett-alola',    'alolan'],
        [51,  'dugtrio-alola',    'alolan'],
        [52,  'meowth-alola',     'alolan'],
        [53,  'persian-alola',    'alolan'],
        [74,  'geodude-alola',    'alolan'],
        [75,  'graveler-alola',   'alolan'],
        [76,  'golem-alola',      'alolan'],
        [88,  'grimer-alola',     'alolan'],
        [89,  'muk-alola',        'alolan'],
        [103, 'exeggutor-alola',  'alolan'],
        [105, 'marowak-alola',    'alolan'],
        // ── FORMES GALAR (20) ─────────────────────────────────────────
        [52,  'meowth-galar',          'galarian'],
        [77,  'ponyta-galar',          'galarian'],
        [78,  'rapidash-galar',        'galarian'],
        [79,  'slowpoke-galar',        'galarian'],
        [80,  'slowbro-galar',         'galarian'],
        [83,  'farfetchd-galar',       'galarian'],
        [110, 'weezing-galar',         'galarian'],
        [122, 'mr-mime-galar',         'galarian'],
        [144, 'articuno-galar',        'galarian'],
        [145, 'zapdos-galar',          'galarian'],
        [146, 'moltres-galar',         'galarian'],
        [199, 'slowking-galar',        'galarian'],
        [222, 'corsola-galar',         'galarian'],
        [263, 'zigzagoon-galar',       'galarian'],
        [264, 'linoone-galar',         'galarian'],
        [554, 'darumaka-galar',        'galarian'],
        [555, 'darmanitan-galar',      'galarian'],
        [555, 'darmanitan-galar-zen',  'galarian_zen'],
        [562, 'yamask-galar',          'galarian'],
        [618, 'stunfisk-galar',        'galarian'],
        // ── FORMES HISUI (17) ─────────────────────────────────────────
        [58,  'growlithe-hisui',  'hisuian'],
        [59,  'arcanine-hisui',   'hisuian'],
        [100, 'voltorb-hisui',    'hisuian'],
        [101, 'electrode-hisui',  'hisuian'],
        [157, 'typhlosion-hisui', 'hisuian'],
        [211, 'qwilfish-hisui',   'hisuian'],
        [215, 'sneasel-hisui',    'hisuian'],
        [215, 'sneasel-hisui',    'hisuian_f'],
        [503, 'samurott-hisui',   'hisuian'],
        [549, 'lilligant-hisui',  'hisuian'],
        [570, 'zorua-hisui',      'hisuian'],
        [571, 'zoroark-hisui',    'hisuian'],
        [628, 'braviary-hisui',   'hisuian'],
        [705, 'sliggoo-hisui',    'hisuian'],
        [706, 'goodra-hisui',     'hisuian'],
        [713, 'avalugg-hisui',    'hisuian'],
        [724, 'decidueye-hisui',  'hisuian'],
        // ── FORMES PALDEA (4) ─────────────────────────────────────────
        [194, 'wooper-paldea',          'paldean'],
        [128, 'tauros-paldea-combat',   'paldean_combat'],
        [128, 'tauros-paldea-blaze',    'paldean_blaze'],
        [128, 'tauros-paldea-aqua',     'paldean_aqua'],
        // ── UNOWN (28) ────────────────────────────────────────────────
        [201, 'unown-a', 'a'], [201, 'unown-b', 'b'], [201, 'unown-c', 'c'],
        [201, 'unown-d', 'd'], [201, 'unown-e', 'e'], [201, 'unown-f', 'f'],
        [201, 'unown-g', 'g'], [201, 'unown-h', 'h'], [201, 'unown-i', 'i'],
        [201, 'unown-j', 'j'], [201, 'unown-k', 'k'], [201, 'unown-l', 'l'],
        [201, 'unown-m', 'm'], [201, 'unown-n', 'n'], [201, 'unown-o', 'o'],
        [201, 'unown-p', 'p'], [201, 'unown-q', 'q'], [201, 'unown-r', 'r'],
        [201, 'unown-s', 's'], [201, 'unown-t', 't'], [201, 'unown-u', 'u'],
        [201, 'unown-v', 'v'], [201, 'unown-w', 'w'], [201, 'unown-x', 'x'],
        [201, 'unown-y', 'y'], [201, 'unown-z', 'z'],
        [201, 'unown-exclamation', 'exclamation'],
        [201, 'unown-question',    'question'],
        // ── CASTFORM (3) ──────────────────────────────────────────────
        [351, 'castform-sunny', 'sunny'],
        [351, 'castform-rainy', 'rainy'],
        [351, 'castform-snowy', 'snowy'],
        // ── DEOXYS (3) ────────────────────────────────────────────────
        [386, 'deoxys-attack',   'attack'],
        [386, 'deoxys-defense',  'defense'],
        [386, 'deoxys-speed',    'speed'],
        // ── BURMY / WORMADAM ──────────────────────────────────────────
        [412, 'burmy-sandy',     'sandy'],
        [412, 'burmy-trash',     'trash'],
        [413, 'wormadam-sandy',  'sandy'],
        [413, 'wormadam-trash',  'trash'],
        // ── CHERRIM ───────────────────────────────────────────────────
        [421, 'cherrim-sunshine', 'sunshine'],
        // ── SHELLOS / GASTRODON ───────────────────────────────────────
        [422, 'shellos-east',    'east'],
        [423, 'gastrodon-east',  'east'],
        // ── ROTOM (5) ─────────────────────────────────────────────────
        [479, 'rotom-heat',  'heat'],
        [479, 'rotom-wash',  'wash'],
        [479, 'rotom-frost', 'frost'],
        [479, 'rotom-fan',   'fan'],
        [479, 'rotom-mow',   'mow'],
        // ── GIRATINA ──────────────────────────────────────────────────
        [487, 'giratina-origin', 'origin'],
        // ── ARCEUS (17 types) ─────────────────────────────────────────
        [493, 'arceus-fighting', 'fighting'], [493, 'arceus-flying',   'flying'],
        [493, 'arceus-poison',   'poison'],   [493, 'arceus-ground',   'ground'],
        [493, 'arceus-rock',     'rock'],     [493, 'arceus-bug',      'bug'],
        [493, 'arceus-ghost',    'ghost'],    [493, 'arceus-steel',    'steel'],
        [493, 'arceus-fire',     'fire'],     [493, 'arceus-water',    'water'],
        [493, 'arceus-grass',    'grass'],    [493, 'arceus-electric', 'electric'],
        [493, 'arceus-psychic',  'psychic'],  [493, 'arceus-ice',      'ice'],
        [493, 'arceus-dragon',   'dragon'],   [493, 'arceus-dark',     'dark'],
        [493, 'arceus-fairy',    'fairy'],
        // ── BASCULIN ──────────────────────────────────────────────────
        [550, 'basculin-blue-striped', 'blue_striped'],
        // ── DEERLING / SAWSBUCK (3 saisons) ───────────────────────────
        [585, 'deerling-summer',  'summer'],
        [585, 'deerling-autumn',  'autumn'],
        [585, 'deerling-winter',  'winter'],
        [586, 'sawsbuck-summer',  'summer'],
        [586, 'sawsbuck-autumn',  'autumn'],
        [586, 'sawsbuck-winter',  'winter'],
        // ── FORCES DE LA NATURE therian ───────────────────────────────
        [641, 'tornadus-therian',  'therian'],
        [642, 'thundurus-therian', 'therian'],
        [645, 'landorus-therian',  'therian'],
        // ── KYUREM ────────────────────────────────────────────────────
        [646, 'kyurem-black', 'black'],
        [646, 'kyurem-white', 'white'],
        // ── KELDEO ────────────────────────────────────────────────────
        [647, 'keldeo-resolute', 'resolute'],
        // ── MELOETTA ──────────────────────────────────────────────────
        [648, 'meloetta-pirouette', 'pirouette'],
        // ── GENESECT (4 modules) ──────────────────────────────────────
        [649, 'genesect-douse', 'douse'],
        [649, 'genesect-shock', 'shock'],
        [649, 'genesect-burn',  'burn'],
        [649, 'genesect-chill', 'chill'],
        // ── VIVILLON (19 motifs) ──────────────────────────────────────
        [666, 'vivillon-icy-snow',    'icy_snow'],
        [666, 'vivillon-polar',       'polar'],
        [666, 'vivillon-tundra',      'tundra'],
        [666, 'vivillon-continental', 'continental'],
        [666, 'vivillon-garden',      'garden'],
        [666, 'vivillon-elegant',     'elegant'],
        [666, 'vivillon-modern',      'modern'],
        [666, 'vivillon-marine',      'marine'],
        [666, 'vivillon-archipelago', 'archipelago'],
        [666, 'vivillon-high-plains', 'high_plains'],
        [666, 'vivillon-sandstorm',   'sandstorm'],
        [666, 'vivillon-river',       'river'],
        [666, 'vivillon-monsoon',     'monsoon'],
        [666, 'vivillon-savanna',     'savanna'],
        [666, 'vivillon-sun',         'sun'],
        [666, 'vivillon-ocean',       'ocean'],
        [666, 'vivillon-jungle',      'jungle'],
        [666, 'vivillon-fancy',       'fancy'],
        [666, 'vivillon-poke-ball',   'poke_ball'],
        // ── FLABÉBÉ / FLOETTE / FLORGES (4 couleurs alt.) ────────────
        [669, 'flabebe-yellow-flower', 'yellow'],
        [669, 'flabebe-orange-flower', 'orange'],
        [669, 'flabebe-blue-flower',   'blue'],
        [669, 'flabebe-white-flower',  'white'],
        [670, 'floette-yellow-flower', 'yellow'],
        [670, 'floette-orange-flower', 'orange'],
        [670, 'floette-blue-flower',   'blue'],
        [670, 'floette-white-flower',  'white'],
        [671, 'florges-yellow-flower', 'yellow'],
        [671, 'florges-orange-flower', 'orange'],
        [671, 'florges-blue-flower',   'blue'],
        [671, 'florges-white-flower',  'white'],
        // ── FURFROU (9 tailles) ───────────────────────────────────────
        [676, 'furfrou-heart',      'heart'],
        [676, 'furfrou-star',       'star'],
        [676, 'furfrou-diamond',    'diamond'],
        [676, 'furfrou-debutante',  'debutante'],
        [676, 'furfrou-matron',     'matron'],
        [676, 'furfrou-dandy',      'dandy'],
        [676, 'furfrou-la-reine',   'la_reine'],
        [676, 'furfrou-kabuki',     'kabuki'],
        [676, 'furfrou-pharaoh',    'pharaoh'],
        // ── AEGISLASH ─────────────────────────────────────────────────
        [681, 'aegislash-blade', 'blade'],
        // ── PUMPKABOO / GOURGEIST (3 tailles alt.) ───────────────────
        [710, 'pumpkaboo-small', 'small'],
        [710, 'pumpkaboo-large', 'large'],
        [710, 'pumpkaboo-super', 'super'],
        [711, 'gourgeist-small', 'small'],
        [711, 'gourgeist-large', 'large'],
        [711, 'gourgeist-super', 'super'],
        // ── XERNEAS ───────────────────────────────────────────────────
        [716, 'xerneas-active', 'active'],
        // ── ZYGARDE (3 formes) ────────────────────────────────────────
        [718, 'zygarde-10',       'ten'],
        [718, 'zygarde-50',       'fifty'],
        [718, 'zygarde-complete', 'complete'],
        // ── HOOPA ─────────────────────────────────────────────────────
        [720, 'hoopa-unbound', 'unbound'],
        // ── ORICORIO (3 styles) ───────────────────────────────────────
        [741, 'oricorio-pau',    'pau'],
        [741, 'oricorio-pompom', 'pompom'],
        [741, 'oricorio-sensu',  'sensu'],
        // ── LYCANROC ──────────────────────────────────────────────────
        [745, 'lycanroc-midnight', 'midnight'],
        [745, 'lycanroc-dusk',     'dusk'],
        // ── WISHIWASHI ────────────────────────────────────────────────
        [746, 'wishiwashi-school', 'school'],
        // ── SILVALLY (17 types) ───────────────────────────────────────
        [773, 'silvally-fighting', 'fighting'], [773, 'silvally-flying',   'flying'],
        [773, 'silvally-poison',   'poison'],   [773, 'silvally-ground',   'ground'],
        [773, 'silvally-rock',     'rock'],     [773, 'silvally-bug',      'bug'],
        [773, 'silvally-ghost',    'ghost'],    [773, 'silvally-steel',    'steel'],
        [773, 'silvally-fire',     'fire'],     [773, 'silvally-water',    'water'],
        [773, 'silvally-grass',    'grass'],    [773, 'silvally-electric', 'electric'],
        [773, 'silvally-psychic',  'psychic'],  [773, 'silvally-ice',      'ice'],
        [773, 'silvally-dragon',   'dragon'],   [773, 'silvally-dark',     'dark'],
        [773, 'silvally-fairy',    'fairy'],
        // ── MINIOR (7 noyaux) ─────────────────────────────────────────
        [774, 'minior-red',    'red_core'],    [774, 'minior-orange', 'orange_core'],
        [774, 'minior-yellow', 'yellow_core'], [774, 'minior-green',  'green_core'],
        [774, 'minior-blue',   'blue_core'],   [774, 'minior-indigo', 'indigo_core'],
        [774, 'minior-violet', 'violet_core'],
        // ── NECROZMA (3 formes) ───────────────────────────────────────
        [800, 'necrozma-dusk',  'dusk_mane'],
        [800, 'necrozma-dawn',  'dawn_wings'],
        [800, 'necrozma-ultra', 'ultra'],
        // ── MAGEARNA ──────────────────────────────────────────────────
        [801, 'magearna-original', 'original'],
        // ── TOXTRICITY ────────────────────────────────────────────────
        [849, 'toxtricity-low-key', 'low_key'],
        // ── SINISTEA / POLTEAGEIST ────────────────────────────────────
        [854, 'sinistea-antique',    'antique'],
        [855, 'polteageist-antique', 'antique'],
        // ── ALCREMIE (8 formes) ───────────────────────────────────────
        [869, 'alcremie-ruby-cream',    'ruby_cream'],
        [869, 'alcremie-matcha-cream',  'matcha_cream'],
        [869, 'alcremie-mint-cream',    'mint_cream'],
        [869, 'alcremie-lemon-cream',   'lemon_cream'],
        [869, 'alcremie-salted-cream',  'salted_cream'],
        [869, 'alcremie-ruby-swirl',    'ruby_swirl'],
        [869, 'alcremie-caramel-swirl', 'caramel_swirl'],
        [869, 'alcremie-rainbow-swirl', 'rainbow_swirl'],
        // ── MORPEKO ───────────────────────────────────────────────────
        [877, 'morpeko-hangry', 'hangry'],
        // ── ZACIAN / ZAMAZENTA ────────────────────────────────────────
        [888, 'zacian-crowned',    'crowned'],
        [889, 'zamazenta-crowned', 'crowned'],
        // ── URSHIFU ───────────────────────────────────────────────────
        [892, 'urshifu-rapid-strike', 'rapid_strike'],
        // ── ZARUDE ────────────────────────────────────────────────────
        [893, 'zarude-dada', 'dada'],
        // ── CALYREX ───────────────────────────────────────────────────
        [898, 'calyrex-ice',    'ice'],
        [898, 'calyrex-shadow', 'shadow'],
        // ── ÉNAMORUS therian ──────────────────────────────────────────
        [905, 'enamorus-therian', 'therian'],
        // ── GEN 9 ─────────────────────────────────────────────────────
        [931, 'squawkabilly-yellow-plumage', 'yellow'],
        [931, 'squawkabilly-blue-plumage',   'blue'],
        [931, 'squawkabilly-white-plumage',  'white'],
        [964, 'palafin-hero', 'hero'],
        [978, 'tatsugiri-droopy',    'droopy'],
        [978, 'tatsugiri-stretchy',  'stretchy'],
        [982, 'dudunsparce-three-segment', 'three_segment'],
        [999, 'gimmighoul-roaming',  'roaming'],
        [1012,'poltchageist-antique','antique'],
        [1013,'sinistcha-masterpiece','masterpiece'],
        [1017,'ogerpon-wellspring',  'wellspring'],
        [1017,'ogerpon-hearthflame', 'hearthflame'],
        [1017,'ogerpon-cornerstone', 'cornerstone'],
        // ── DIFFÉRENCES SEXUELLES (♀) ─────────────────────────────────
        [3,   'venusaur',   'female'],
        [12,  'butterfree', 'female'],
        [25,  'pikachu',    'female'],
        [26,  'raichu',     'female'],
        [154, 'meganium',   'female'],
        [178, 'xatu',       'female'],
        [190, 'aipom',      'female'],
        [198, 'murkrow',    'female'],
        [202, 'wobbuffet',  'female'],
        [207, 'gligar',     'female'],
        [208, 'steelix',    'female'],
        [212, 'scizor',     'female'],
        [214, 'heracross',  'female'],
        [215, 'sneasel',    'female'],
        [229, 'houndoom',   'female'],
        [232, 'donphan',    'female'],
        [315, 'roselia',    'female'],
        [332, 'cacturne',   'female'],
        [350, 'milotic',    'female'],
        [396, 'starly',     'female'],
        [397, 'staravia',   'female'],
        [398, 'staraptor',  'female'],
        [399, 'bidoof',     'female'],
        [400, 'bibarel',    'female'],
        [401, 'kricketot',  'female'],
        [402, 'kricketune', 'female'],
        [403, 'shinx',      'female'],
        [404, 'luxio',      'female'],
        [405, 'luxray',     'female'],
        [415, 'combee',     'female'],
        [443, 'gible',      'female'],
        [444, 'gabite',     'female'],
        [445, 'garchomp',   'female'],
        [453, 'croagunk',   'female'],
        [454, 'toxicroak',  'female'],
        [456, 'finneon',    'female'],
        [457, 'lumineon',   'female'],
        [459, 'snover',     'female'],
        [460, 'abomasnow',  'female'],
        [592, 'frillish',   'female'],
        [593, 'jellicent',  'female'],
        [668, 'pyroar',     'female'],
        [678, 'meowstic',   'female'],
        // ── PIKACHU CASQUETTES (8) ────────────────────────────────────
        [25, 'pikachu-original-cap', 'original_cap'],
        [25, 'pikachu-hoenn-cap',    'hoenn_cap'],
        [25, 'pikachu-sinnoh-cap',   'sinnoh_cap'],
        [25, 'pikachu-unova-cap',    'unova_cap'],
        [25, 'pikachu-kalos-cap',    'kalos_cap'],
        [25, 'pikachu-alola-cap',    'alola_cap'],
        [25, 'pikachu-partner-cap',  'partner_cap'],
        [25, 'pikachu-world-cap',    'world_cap'],
    ];

    // Créer ou mettre à jour le pokédex
    $stmt = $pdo->prepare("SELECT id FROM pokedex_list WHERE code = ?");
    $stmt->execute([$code]);
    $pokedexID = $stmt->fetchColumn();
    if (!$pokedexID) {
        $pdo->prepare("INSERT INTO pokedex_list (code, name, name_en, name_de, name_es) VALUES (?, ?, ?, ?, ?)")->execute([$code, $name, $name_en, $name_de, $name_es]);
        $pokedexID = $pdo->lastInsertId();
        out("✔ Pokédex « {$code} » créé (id={$pokedexID}).");
    } else {
        $pdo->prepare("UPDATE pokedex_list SET name = ?, name_en = ?, name_de = ?, name_es = ? WHERE id = ?")->execute([$name, $name_en, $name_de, $name_es, $pokedexID]);
        out("✔ Pokédex « {$code} » existant mis à jour.");
    }

    ['pokemon' => $insertPokemon, 'form' => $insertForm, 'entry' => $insertEntry, 'formVariant' => $insertFormVariant] = prepare_stmts($pdo);

    $total        = count($formesList);
    $done         = 0;
    $pos          = 1;
    $speciesCache = []; // évite les appels API en double pour la même espèce

    foreach ($formesList as [$natId, $apiSlug, $formCode]) {
        // Maintenir la connexion MySQL active
        try { $pdo->query('SELECT 1'); }
        catch (PDOException $e) {
            $pdo = make_pdo($dbCfg);
            ['pokemon' => $insertPokemon, 'form' => $insertForm, 'entry' => $insertEntry, 'formVariant' => $insertFormVariant] = prepare_stmts($pdo);
        }

        $name_fr = $name_en = $name_de = $name_es = $baseSprite = $baseShiny = null;

        if ($importData) {
            // Charger données espèce une seule fois par species_id
            if (!isset($speciesCache[$natId])) {
                $cached = [];
                $sp = pokeapi_get("https://pokeapi.co/api/v2/pokemon-species/{$natId}/");
                if ($sp) {
                    foreach ($sp['names'] as $n) {
                        if ($n['language']['name'] === 'fr') $cached['fr'] = $n['name'];
                        if ($n['language']['name'] === 'en') $cached['en'] = $n['name'];
                        if ($n['language']['name'] === 'de') $cached['de'] = $n['name'];
                        if ($n['language']['name'] === 'es') $cached['es'] = $n['name'];
                    }
                }
                $pk = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$natId}/");
                if ($pk) {
                    $cached['sprite']       = $pk['sprites']['front_default']       ?? null;
                    $cached['shiny']        = $pk['sprites']['front_shiny']         ?? null;
                    $cached['female']       = $pk['sprites']['front_female']        ?? null;
                    $cached['shiny_female'] = $pk['sprites']['front_shiny_female']  ?? null;
                }
                $speciesCache[$natId] = $cached;
            }
            $c = $speciesCache[$natId];
            $name_fr    = $c['fr']     ?? null;
            $name_en    = $c['en']     ?? null;
            $name_de    = $c['de']     ?? null;
            $name_es    = $c['es']     ?? null;
            $baseSprite = $c['sprite'] ?? null;
            $baseShiny  = $c['shiny']  ?? null;
        }

        $displayName = $name_en ?? $apiSlug;

        // Insérer l'espèce de base et sa forme de base
        $insertPokemon->execute([$natId, $name_fr, $name_en ?? $apiSlug, $name_de, $name_es, $baseSprite, $baseShiny]);
        $insertForm->execute([(string)$natId, $natId, $baseSprite, $baseShiny]);

        // Récupérer le sprite de la forme alternative
        $fSprite = $fShiny = null;
        if ($importData) {
            if ($formCode === 'female') {
                // Différence sexuelle : sprite front_female du Pokémon de base
                $fSprite = $speciesCache[$natId]['female']       ?? null;
                $fShiny  = $speciesCache[$natId]['shiny_female'] ?? null;
            } elseif ($formCode === 'hisuian_f') {
                // Forme hisuienne femelle : front_female du pokemon-form
                $fd = pokeapi_get("https://pokeapi.co/api/v2/pokemon-form/{$apiSlug}/");
                if ($fd) {
                    $fSprite = $fd['sprites']['front_female']       ?? $fd['sprites']['front_default'] ?? null;
                    $fShiny  = $fd['sprites']['front_shiny_female'] ?? $fd['sprites']['front_shiny']   ?? null;
                }
            } else {
                // Forme standard : pokemon-form d'abord, puis pokemon/{slug}
                $fd = pokeapi_get("https://pokeapi.co/api/v2/pokemon-form/{$apiSlug}/");
                if ($fd) {
                    $fSprite = $fd['sprites']['front_default'] ?? null;
                    $fShiny  = $fd['sprites']['front_shiny']   ?? null;
                }
                if (!$fSprite) {
                    $pa = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$apiSlug}/");
                    if ($pa) {
                        $fSprite = $pa['sprites']['front_default'] ?? null;
                        $fShiny  = $pa['sprites']['front_shiny']   ?? null;
                    }
                }
            }
        }

        $formId = "{$natId}_{$formCode}";
        $insertFormVariant->execute([$formId, $natId, $formCode, $fSprite, $fShiny]);
        $insertEntry->execute([$pokedexID, $formId, $pos]);
        out("  [{$pos}] {$displayName} ({$formCode})" . ($fSprite ? '' : ' ⚠ pas de sprite'));

        $pos++;
        $done++;
        if ($done % 20 === 0 || $done === $total) {
            out("  [{$done}/{$total}] formes traitées…");
        }
    }

    out("\n✅ Import terminé !");
    out("   • {$done} formes importées dans le Pokédex « {$code} ».");
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
    $pdo->prepare("INSERT INTO pokedex_list (code, name, name_en, name_de, name_es) VALUES (?, ?, ?, ?, ?)")->execute([$code, $name, $name_en, $name_de, $name_es]);
    $pokedexID = $pdo->lastInsertId();
    out("✔ Pokédex « {$code} » créé (id={$pokedexID}).");
} else {
    $pdo->prepare("UPDATE pokedex_list SET name = ?, name_en = ?, name_de = ?, name_es = ? WHERE id = ?")->execute([$name, $name_en, $name_de, $name_es, $pokedexID]);
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
    $name_es = null;
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
                if ($lang === 'es')   $name_es = $nameEntry['name'];
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
    $insertPokemon->execute([$nationalId, $name_fr, $name_en, $name_de, $name_es, $sprite, $shiny]);

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
