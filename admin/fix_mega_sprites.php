<?php
declare(strict_types=1);
require_once __DIR__ . '/../users/admin_required.php';
set_time_limit(0);

header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');
ob_implicit_flush(true);
ob_end_flush();

function out(string $line): void { echo "{$line}\n"; flush(); }

function http_get(string $url): ?string {
    $ctx = stream_context_create([
        'http' => ['timeout' => 15, 'header' => "User-Agent: MonPokedex/1.0\r\n", 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $r = @file_get_contents($url, false, $ctx);
    return ($r === false || $r === '') ? null : $r;
}

function pokeapi_get(string $url): ?array {
    $json = http_get($url);
    if (!$json) return null;
    $d = json_decode($json, true);
    return (is_array($d) && !isset($d['detail'])) ? $d : null;
}

function bulbagarden_url(string $filename): ?string {
    $api = 'https://archives.bulbagarden.net/w/api.php?action=query&titles=File:'
          . urlencode($filename) . '&prop=imageinfo&iiprop=url&format=json';
    $json = http_get($api);
    if (!$json) return null;
    $data  = json_decode($json, true);
    $pages = $data['query']['pages'] ?? [];
    $page  = reset($pages);
    if (!isset($page['imageinfo'][0]['url'])) return null;
    if ((int)($page['pageid'] ?? -1) < 0) return null;
    return $page['imageinfo'][0]['url'];
}

// ---------- DB ----------
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../config/constantesPDO.php';
$config = ConstantesPDO::getInstance()->getConfig();
$cfg    = $config['db'] ?? null;
if (!$cfg) { out("❌ Config DB introuvable."); exit; }

try {
    $pdo = DB::getPDO();
} catch (Exception $e) {
    out("❌ Connexion DB : " . $e->getMessage());
    exit;
}

// Corrections d'IDs connus incorrects dans pokemon_forms
// (pokemon_id enregistré avec le mauvais numéro national)
$idFixes = [
    '739_mega' => ['correct_id' => 740, 'name_en' => 'Crabominable'],
    '863_mega' => ['correct_id' => 870, 'name_en' => 'Falinks'],
];
foreach ($idFixes as $oldFormId => $fix) {
    $check = $pdo->prepare("SELECT id FROM pokemon_forms WHERE id = ?");
    $check->execute([$oldFormId]);
    if (!$check->fetchColumn()) continue;

    $newFormId = $fix['correct_id'] . '_mega';
    // S'assurer que la nouvelle espèce existe
    $speciesExists = $pdo->prepare("SELECT id FROM pokemon WHERE id = ?");
    $speciesExists->execute([$fix['correct_id']]);
    if (!$speciesExists->fetchColumn()) {
        out("  ⚠ Espèce #{$fix['correct_id']} absente de la table pokemon — correction ignorée pour {$oldFormId}");
        continue;
    }

    // 1. Créer le nouvel enregistrement dans pokemon_forms (si absent)
    $pdo->prepare("
        INSERT IGNORE INTO pokemon_forms (id, pokemon_id, form_code, sprite, shiny_sprite)
        SELECT ?, ?, form_code, sprite, shiny_sprite FROM pokemon_forms WHERE id = ?
    ")->execute([$newFormId, $fix['correct_id'], $oldFormId]);

    // 2. Mettre à jour pokedex_entries pour pointer vers le nouvel ID
    $pdo->prepare("UPDATE pokedex_entries SET pokemon_id = ? WHERE pokemon_id = ?")->execute([$newFormId, $oldFormId]);

    // 3. Supprimer l'ancien enregistrement
    $pdo->prepare("DELETE FROM pokemon_forms WHERE id = ?")->execute([$oldFormId]);

    out("  🔧 Correction ID : {$oldFormId} → {$newFormId}");
}

// Récupérer toutes les formes méga sans sprite (LEFT JOIN pour détecter les pokemon sans name_en)
$rows = $pdo->query("
    SELECT pf.id, pf.pokemon_id, pf.form_code, p.name_en
    FROM pokemon_forms pf
    LEFT JOIN pokemon p ON p.id = pf.pokemon_id
    WHERE pf.form_code REGEXP 'mega'
      AND (pf.sprite IS NULL OR pf.sprite = '')
    ORDER BY pf.pokemon_id
")->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
out("🔍 {$total} formes méga sans sprite trouvées.\n");

if ($total === 0) { out("✅ Rien à corriger."); exit; }

$updateStmt = $pdo->prepare(
    "UPDATE pokemon_forms SET sprite = ?, shiny_sprite = ? WHERE id = ?"
);

$fixed    = 0;
$notFound = 0;

foreach ($rows as $row) {
    $natId    = (int)$row['pokemon_id'];
    $formCode = $row['form_code'];
    $formId   = $row['id'];
    $nameEn   = $row['name_en'];

    // Reconnexion si besoin
    try { $pdo->query('SELECT 1'); } catch (PDOException $e) {
        $pdo = DB::getPDO();
        $updateStmt = $pdo->prepare("UPDATE pokemon_forms SET sprite = ?, shiny_sprite = ? WHERE id = ?");
    }

    if (!$nameEn) {
        out("  ⚠ #{$natId} ({$formCode}) — name_en manquant, tentative via national ID uniquement");
    }

    // Construire le slug PokéAPI
    $speciesSlug = strtolower(str_replace([' ', "'", '.', '\''], ['-', '', '', ''], $nameEn ?? ''));
    $formSuffix  = match($formCode) {
        'mega'   => '-mega',
        'mega_x' => '-mega-x',
        'mega_y' => '-mega-y',
        'mega_z'       => '-mega-z',
        'droopy_mega'  => '-droopy-mega',
        'stretchy_mega'=> '-stretchy-mega',
        default        => null,
    };

    $fSprite = null;
    $fShiny  = null;
    $src     = null;

    if ($formSuffix !== null && $speciesSlug !== '') {
        $formSlug = $speciesSlug . $formSuffix;

        // Tentative 1 : PokéAPI pokemon-form
        $d = pokeapi_get("https://pokeapi.co/api/v2/pokemon-form/{$formSlug}/");
        if ($d && !empty($d['sprites']['front_default'])) {
            $fSprite = $d['sprites']['front_default'];
            $fShiny  = $d['sprites']['front_shiny'] ?? null;
            $src     = "PokéAPI form ({$formSlug})";
        }

        // Tentative 2 : PokéAPI pokemon/{slug}
        if (!$fSprite) {
            $d = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$formSlug}/");
            if ($d && !empty($d['sprites']['front_default'])) {
                $fSprite = $d['sprites']['front_default'];
                $fShiny  = $d['sprites']['front_shiny'] ?? null;
                $src     = "PokéAPI pokemon ({$formSlug})";
            }
        }

        // Tentative 3 : PokéAPI par ID numérique (cherche la forme méga dans les formes de l'espèce)
        if (!$fSprite) {
            $pokData = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$natId}/");
            if ($pokData) {
                foreach ($pokData['forms'] ?? [] as $formRef) {
                    $slug = $formRef['name'] ?? '';
                    // Vérifier que le slug se termine bien par -mega, -mega-x ou -mega-y
                    if (!preg_match('/-mega(-[xyz])?$/', $slug)) continue;
                    $fd = pokeapi_get("https://pokeapi.co/api/v2/pokemon-form/{$slug}/");
                    if ($fd && !empty($fd['sprites']['front_default'])) {
                        $isX = str_ends_with($slug, '-x');
                        $isY = str_ends_with($slug, '-y');
                        $isZ = str_ends_with($slug, '-z');
                        $match = match($formCode) {
                            'mega'   => !$isX && !$isY && !$isZ,
                            'mega_x' => $isX,
                            'mega_y' => $isY,
                            'mega_z' => $isZ,
                            default  => false,
                        };
                        if ($match) {
                            $fSprite = $fd['sprites']['front_default'];
                            $fShiny  = $fd['sprites']['front_shiny'] ?? null;
                            $src     = "PokéAPI form by species ({$slug})";
                            break;
                        }
                    }
                }
            }
        }
    }

    // Tentative 4 : Bulbagarden — format {id4}{Name}-Mega.png
    if (!$fSprite && $nameEn) {
        $id4 = str_pad((string)$natId, 4, '0', STR_PAD_LEFT);
        // Certains noms ont des apostrophes ou points à retirer du nom de fichier
        $nameClean = str_replace(["'", ".", "'"], '', $nameEn);
        $candidates = match($formCode) {
            'mega'   => [
                "{$id4}{$nameClean}-Mega.png",
                "{$id4}{$nameClean}-Curly_Mega.png",
            ],
            'mega_x' => [
                "{$id4}{$nameClean}-Mega X.png",
                "{$id4}{$nameClean}-Mega-X.png",
            ],
            'mega_y' => [
                "{$id4}{$nameClean}-Mega Y.png",
                "{$id4}{$nameClean}-Mega-Y.png",
            ],
            'mega_z' => [
                "{$id4}{$nameClean}-Mega_Z.png",
                "{$id4}{$nameClean}-Mega Z.png",
                "{$id4}{$nameClean}-Mega-Z.png",
            ],
            'droopy_mega' => [
                "{$id4}{$nameClean}-Droopy_Mega.png",
                "{$id4}{$nameClean}-Droopy Mega.png",
            ],
            'stretchy_mega' => [
                "{$id4}{$nameClean}-Stretchy_Mega.png",
                "{$id4}{$nameClean}-Stretchy Mega.png",
            ],
            default  => [],
        };
        foreach ($candidates as $fname) {
            $url = bulbagarden_url($fname);
            if ($url) { $fSprite = $url; $src = "Bulbagarden ({$fname})"; break; }
        }
    }

    if ($fSprite) {
        $updateStmt->execute([$fSprite, $fShiny, $formId]);
        out("  ✔ #{$natId} {$nameEn} ({$formCode}) — {$src}");
        $fixed++;
    } else {
        $label = $nameEn ?? "id:{$natId}";
        out("  ✗ #{$natId} {$label} ({$formCode}) — introuvable (slug essayé: " . ($speciesSlug . ($formSuffix ?? '')) . ")");
        $notFound++;
    }
}

out("\n✅ Terminé : {$fixed} sprites ajoutés, {$notFound} introuvables.");
