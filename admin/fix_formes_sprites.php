<?php
declare(strict_types=1);
require_once __DIR__ . '/../users/admin_required.php';
set_time_limit(0);

header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');
ob_implicit_flush(true);
ob_end_flush();

function out(string $line): void { echo $line . "\n"; flush(); }

function pokeapi_get(string $url): ?array {
    $ctx = stream_context_create([
        'http' => ['timeout' => 15, 'header' => "User-Agent: MonPokedex/1.0\r\n", 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) return null;
    $data = json_decode($json, true);
    if (!is_array($data) || isset($data['detail'])) return null;
    return $data;
}

require_once __DIR__ . '/../config/pdo.php';
try {
    $pdo = DB::getPDO();
} catch (Exception $e) {
    out("❌ Connexion DB : " . $e->getMessage()); exit;
}

$targetCode = strtoupper(trim($_GET['code'] ?? 'FA'));

$stmt = $pdo->prepare("SELECT id, name FROM pokedex_list WHERE code = ?");
$stmt->execute([$targetCode]);
$dex = $stmt->fetch();
if (!$dex) { out("❌ Pokédex « {$targetCode} » introuvable."); exit; }
$dexId   = $dex['id'];
$dexName = $dex['name'];

out("🔧 Correction sprites — Pokédex « {$dexName} » (code={$targetCode}, id={$dexId})");

$rows = $pdo->prepare("
    SELECT pf.id AS form_id, pf.pokemon_id, pf.form_code, p.name_en
    FROM pokemon_forms pf
    INNER JOIN pokedex_entries pe ON pe.pokemon_id = pf.id AND pe.pokedex_id = ?
    INNER JOIN pokemon p ON p.id = pf.pokemon_id
    WHERE pf.sprite IS NULL OR pf.sprite = ''
    ORDER BY pe.position
");
$rows->execute([$dexId]);
$missing = $rows->fetchAll();

$total = count($missing);
out("🔍 {$total} formes sans sprite trouvées…\n");

if ($total === 0) { out("✅ Aucun sprite manquant !"); exit; }

// form_code → suffixe PokéAPI (cas généraux)
$suffixMap = [
    'alolan'         => 'alola',
    'galarian'       => 'galar',
    'hisuian'        => 'hisui',
    'hisuian_f'      => 'hisui',
    'paldean'        => 'paldea',
    'galarian_zen'   => 'galar-zen',
    'paldean_combat' => 'paldea-combat',
    'paldean_blaze'  => 'paldea-blaze',
    'paldean_aqua'   => 'paldea-aqua',
    // Oricorio : PokéAPI utilise "pom-pom" avec tiret
    'pompom'         => 'pom-pom',
    // Ogerpon : les masques ont le suffixe "-mask"
    'wellspring'     => 'wellspring-mask',
    'hearthflame'    => 'hearthflame-mask',
    'cornerstone'    => 'cornerstone-mask',
];

// Slugs directs forcés pour les cas qui échappent à la logique automatique
$slugOverrides = [
    '555_galarian'       => 'darmanitan-galar',
    '128_paldean_combat' => 'tauros-paldea-combat',
    '128_paldean_blaze'  => 'tauros-paldea-blaze',
    '128_paldean_aqua'   => 'tauros-paldea-aqua',
    // Alcremie : les variantes de crème n'ont pas de sprite distinct dans PokéAPI
    // → on utilise directement pokemon-form avec le bon tiret
    '869_ruby_cream'     => 'alcremie-ruby-cream',
    '869_matcha_cream'   => 'alcremie-matcha-cream',
    '869_mint_cream'     => 'alcremie-mint-cream',
    '869_lemon_cream'    => 'alcremie-lemon-cream',
    '869_salted_cream'   => 'alcremie-salted-cream',
    '869_ruby_swirl'     => 'alcremie-ruby-swirl',
    '869_caramel_swirl'  => 'alcremie-caramel-swirl',
    '869_rainbow_swirl'  => 'alcremie-rainbow-swirl',
    // Sinistea / Polteageist
    '854_antique'        => 'sinistea-antique',
    '855_antique'        => 'polteageist-antique',
    // Poltchageist / Sinistcha
    '1012_antique'       => 'poltchageist-antique',
    '1013_masterpiece'   => 'sinistcha-masterpiece',
    // Xerneas active
    '716_active'         => 'xerneas-active',
];

function name_to_slug(string $nameEn): string {
    $accents = ['é'=>'e','è'=>'e','ê'=>'e','à'=>'a','â'=>'a','ù'=>'u',
                'û'=>'u','î'=>'i','ô'=>'o','ç'=>'c','É'=>'e','È'=>'e'];
    $s = strtr(strtolower($nameEn), $accents);
    $s = str_replace(['. ', ' '], '-', $s);
    $s = preg_replace('/[^a-z0-9\-]/', '', $s);
    return trim($s, '-');
}

$updateStmt = $pdo->prepare(
    "UPDATE pokemon_forms SET sprite = ?, shiny_sprite = ? WHERE id = ?"
);
$fixed = $notFound = 0;
$speciesCache = [];

foreach ($missing as $row) {
    $formId   = $row['form_id'];
    $natId    = (int)$row['pokemon_id'];
    $formCode = $row['form_code'];
    $nameSlug = name_to_slug($row['name_en']);

    $fSprite = $fShiny = null;

    try { $pdo->query('SELECT 1'); }
    catch (PDOException $e) { $pdo = DB::getPDO(); }

    // ── Cas female : front_female depuis pokemon/{id} ─────────────────
    if ($formCode === 'female') {
        if (!isset($speciesCache["pk_{$natId}"])) {
            $speciesCache["pk_{$natId}"] = pokeapi_get(
                "https://pokeapi.co/api/v2/pokemon/{$natId}/"
            );
        }
        $pk = $speciesCache["pk_{$natId}"];
        if ($pk) {
            $fSprite = $pk['sprites']['front_female']       ?? null;
            $fShiny  = $pk['sprites']['front_shiny_female'] ?? null;
        }

    // ── Autres formes ─────────────────────────────────────────────────
    } else {
        // Slug à utiliser : override forcé ou construction automatique
        if (isset($slugOverrides[$formId])) {
            $expectedSlug = $slugOverrides[$formId];
        } else {
            $suffix       = $suffixMap[$formCode] ?? str_replace('_', '-', $formCode);
            $expectedSlug = $nameSlug . '-' . $suffix;
        }

        // 1. Via pokemon-species → varieties (fiable pour formes régionales)
        if (!isset($speciesCache["sp_{$natId}"])) {
            $speciesCache["sp_{$natId}"] = pokeapi_get(
                "https://pokeapi.co/api/v2/pokemon-species/{$natId}/"
            );
        }
        $sp = $speciesCache["sp_{$natId}"];
        if ($sp && !empty($sp['varieties'])) {
            foreach ($sp['varieties'] as $v) {
                if ($v['pokemon']['name'] === $expectedSlug) {
                    $pk = pokeapi_get($v['pokemon']['url']);
                    if ($pk) {
                        $fSprite = $pk['sprites']['front_default'] ?? null;
                        $fShiny  = $pk['sprites']['front_shiny']   ?? null;
                        if ($formCode === 'hisuian_f') {
                            $fSprite = $pk['sprites']['front_female']       ?? $fSprite;
                            $fShiny  = $pk['sprites']['front_shiny_female'] ?? $fShiny;
                        }
                    }
                    break;
                }
            }
        }

        // 2. Fallback : pokemon-form/{slug}
        if (!$fSprite) {
            $fd = pokeapi_get("https://pokeapi.co/api/v2/pokemon-form/{$expectedSlug}/");
            if ($fd) {
                $fSprite = $fd['sprites']['front_default'] ?? null;
                $fShiny  = $fd['sprites']['front_shiny']   ?? null;
                if ($formCode === 'hisuian_f') {
                    $fSprite = $fd['sprites']['front_female']       ?? $fSprite;
                    $fShiny  = $fd['sprites']['front_shiny_female'] ?? $fShiny;
                }
            }
        }

        // 3. Fallback : pokemon/{slug}
        if (!$fSprite) {
            $pk = pokeapi_get("https://pokeapi.co/api/v2/pokemon/{$expectedSlug}/");
            if ($pk) {
                $fSprite = $pk['sprites']['front_default'] ?? null;
                $fShiny  = $pk['sprites']['front_shiny']   ?? null;
            }
        }

        // 4. Dernier recours : sprite de base de l'espèce (pour formes sans sprite distinct)
        if (!$fSprite) {
            if (!isset($speciesCache["pk_{$natId}"])) {
                $speciesCache["pk_{$natId}"] = pokeapi_get(
                    "https://pokeapi.co/api/v2/pokemon/{$natId}/"
                );
            }
            $pk = $speciesCache["pk_{$natId}"];
            if ($pk) {
                $fSprite = $pk['sprites']['front_default'] ?? null;
                $fShiny  = $pk['sprites']['front_shiny']   ?? null;
            }
            if ($fSprite) {
                out("  ~ {$formId} → sprite de base utilisé (forme sans sprite distinct)");
                $updateStmt->execute([$fSprite, $fShiny, $formId]);
                $fixed++;
                continue;
            }
        }
    }

    if ($fSprite) {
        $updateStmt->execute([$fSprite, $fShiny, $formId]);
        $fixed++;
        out("  ✔ {$formId}");
    } else {
        $notFound++;
        $label = $expectedSlug ?? ($nameSlug . '-' . $formCode);
        out("  ⚠ {$formId} ({$label}) — introuvable");
    }
}

out("\n✅ Terminé : {$fixed} sprites corrigés, {$notFound} toujours manquants.");
