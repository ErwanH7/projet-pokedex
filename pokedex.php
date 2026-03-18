<?php
include_once 'include.php';
$pdo = DB::getPDO();

$userID  = $_SESSION['user_id'];
$dexCode = $_GET['dex'] ?? null;
if (!$dexCode) die("Aucun Pokédex spécifié.");

$stmt = $pdo->prepare("SELECT id, name FROM pokedex_list WHERE code = ?");
$stmt->execute([$dexCode]);
$pokedex = $stmt->fetch();
if (!$pokedex) die("Pokédex introuvable.");

$pokedexID = $pokedex['id'];

// Pokédexes avec exclusivités de version
$VERSION_DEXES = ['EV', 'LA', 'ZA'];
$showVersion   = in_array(strtoupper($dexCode), $VERSION_DEXES);

// Pokédexes avec suivi Alpha/Baron
$ALPHA_DEXES = ['ZAL', 'EV', 'LA', 'EV1', 'EV2', 'ZAH'];
$showAlpha   = in_array(strtoupper($dexCode), $ALPHA_DEXES);

// Récupérer toutes les entrées avec sprites de la forme
$stmt = $pdo->prepare("
    SELECT
        pe.pokemon_id,
        pe.position,
        pe.version,
        p.id         AS species_id,
        p.name_fr,
        p.name_en,
        p.name_de,
        pf.sprite,
        pf.shiny_sprite,
        pf.form_code
    FROM pokedex_entries pe
    JOIN pokemon_forms pf ON pe.pokemon_id = pf.id
    JOIN pokemon p        ON pf.pokemon_id = p.id
    WHERE pe.pokedex_id = ?
    ORDER BY pe.position IS NULL ASC, pe.position ASC, p.id ASC
");
$stmt->execute([$pokedexID]);
$entries = $stmt->fetchAll();

// Regrouper par espèce
$species = [];
foreach ($entries as $row) {
    $sid = $row['species_id'];
    if (!isset($species[$sid])) {
        $species[$sid] = [
            'name'     => $row['name_fr'] ?: $row['name_en'] ?: '#' . $sid,
            'name_fr'  => $row['name_fr'] ?? '',
            'name_en'  => $row['name_en'] ?? '',
            'name_de'  => $row['name_de'] ?? '',
            'position' => $row['position'],
            'version'  => $row['version'],
            'forms'    => [],
        ];
    }
    $species[$sid]['forms'][$row['pokemon_id']] = [
        'code'   => $row['form_code'],
        'sprite' => $row['sprite'],
        'shiny'  => $row['shiny_sprite'],
    ];
}

// Progression : clé = pokemon_id (ID de forme, ex: "154" ou "154_m")
$stmt = $pdo->prepare("
    SELECT pokemon_id, caught, shiny, alpha FROM user_progress
    WHERE user_id = ? AND pokedex_id = ?
");
$stmt->execute([$userID, $pokedexID]);
$progressData = [];
foreach ($stmt->fetchAll() as $r) {
    $progressData[$r['pokemon_id']] = ['caught' => (int)$r['caught'], 'shiny' => (int)$r['shiny'], 'alpha' => (int)$r['alpha']];
}

// Compteurs : 1 espèce = cochée si au moins 1 de ses formes est cochée
$totalSpecies = count($species);
$caughtNormal = 0;
$caughtShiny  = 0;
$caughtAlpha  = 0;
foreach ($species as $sid => $data) {
    $speciesNormal = false;
    $speciesShiny  = false;
    $speciesAlpha  = false;
    foreach ($data['forms'] as $pid => $_) {
        $fp = $progressData[$pid] ?? ['caught' => 0, 'shiny' => 0, 'alpha' => 0];
        if ($fp['caught']) $speciesNormal = true;
        if ($fp['shiny'])  $speciesShiny  = true;
        if ($fp['alpha'])  $speciesAlpha  = true;
    }
    if ($speciesNormal) $caughtNormal++;
    if ($speciesShiny)  $caughtShiny++;
    if ($speciesAlpha)  $caughtAlpha++;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pokedex['name']) ?> — Mon Pokédex</title>
    <link rel="icon" type="image/png" href="/img/logo_pokedex.png">
    <meta name="description" content="Suis ta progression dans le Pokédex <?= htmlspecialchars($pokedex['name']) ?>.">
    <meta property="og:title" content="<?= htmlspecialchars($pokedex['name']) ?> — Mon Pokédex">
    <meta property="og:description" content="Suis ta progression dans le Pokédex <?= htmlspecialchars($pokedex['name']) ?>.">
    <meta property="og:image" content="<?= htmlspecialchars($baseUrl) ?>/img/logo_pokedex.png">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="<?= htmlspecialchars($baseUrl) ?>/img/logo_pokedex.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <style>
        .poke-card {
            background: white; border-radius: 12px; padding: 10px 8px;
            text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: box-shadow .2s; border: 2px solid transparent;
        }
        .poke-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.18); }
        .poke-card.both   { border-color: #f59e0b; background: #fffdf0; }
        .poke-card.normal { border-color: #198754; background: #f0fff4; }
        .poke-card.shiny  { border-color: #a855f7; background: #fdf4ff; }

        .sprites-row {
            display: flex; justify-content: center; align-items: flex-end;
            gap: 2px;
        }
        .sprite-col {
            display: flex; flex-direction: column; align-items: center; gap: 1px;
            flex: 1;
        }
        .poke-img {
            width: 126px; height: 126px; object-fit: contain;
            image-rendering: pixelated;
        }
        .poke-img.shiny-sprite { filter: drop-shadow(0 0 4px #f59e0b); }

        .form-badge { font-size: 0.68rem; color: #888; margin-bottom: 2px; }
        .poke-num   { font-size: 0.72rem; color: #bbb; }

        .check-col {
            display: flex; flex-direction: column; align-items: center;
            font-size: 0.68rem; gap: 2px; margin-top: 4px;
        }
        .check-col input[type=checkbox] { width: 20px; height: 20px; cursor: pointer; }
        .sprite-label { cursor: pointer; display: block; line-height: 0; }
        .sprite-label:hover .poke-img { opacity: .8; transform: scale(1.05); transition: opacity .15s, transform .15s; }
        .check-col .lbl-normal { color: #198754; font-weight: 600; }
        .check-col .lbl-shiny  { color: #a855f7; font-weight: 600; }
        .check-col .lbl-alpha  { color: #f97316; font-weight: 600; }
        .alpha-row { margin-top: 4px; padding-top: 4px; border-top: 1px dashed #e5e7eb; width: 100%; }
        .alpha-help {
            display: inline-flex; align-items: center; justify-content: center;
            width: 13px; height: 13px; border-radius: 50%;
            background: #f97316; color: white; font-size: .6rem; font-weight: 700;
            cursor: help; vertical-align: middle; margin-left: 2px;
        }

        .version-badge {
            display: inline-block; font-size: 0.6rem; font-weight: 700;
            padding: 1px 5px; border-radius: 4px; margin-bottom: 3px; letter-spacing: .03em;
        }
        .version-badge.scarlet { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .version-badge.violet  { background: #ede9fe; color: #7c3aed; border: 1px solid #c4b5fd; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="/index.php"><img src="/img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;"></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="/stats.php">Statistiques</a></li>
                <li class="nav-item"><a class="nav-link" href="/users/profile.php">Profil</a></li>
                <li class="nav-item"><a class="nav-link" href="/users/logout.php">Déconnexion</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="sub-bar sticky-top px-3 py-2" style="top:100px;z-index:999">
    <div class="d-flex align-items-center gap-2">
        <a href="/index.php" class="btn btn-sm btn-outline-secondary flex-shrink-0">&larr; Retour</a>
        <span class="dex-title d-none d-sm-block text-truncate flex-shrink-0" style="font-size:.95rem;max-width:140px"><?= htmlspecialchars($pokedex['name']) ?></span>
        <div class="position-relative flex-grow-1">
            <input type="search" id="pokeFilter" class="form-control form-control-sm pe-5"
                   placeholder="Filtrer un Pokémon…" autocomplete="off">
            <span id="filterCount" class="filter-count-badge" hidden></span>
        </div>
        <div class="d-flex gap-3 text-center flex-shrink-0 ms-1">
            <div class="count-block">
                <div class="count-val text-success" id="count-normal"><?= $caughtNormal ?></div>
                <div class="count-lbl">Normal / <?= $totalSpecies ?></div>
            </div>
            <div class="count-block">
                <div class="count-val" style="color:var(--pk-purple)" id="count-shiny"><?= $caughtShiny ?></div>
                <div class="count-lbl">Shiny / <?= $totalSpecies ?></div>
            </div>
            <?php if ($showAlpha): ?>
            <div class="count-block">
                <div class="count-val" style="color:#f97316" id="count-alpha"><?= $caughtAlpha ?></div>
                <div class="count-lbl">Alpha / <?= $totalSpecies ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container-fluid px-3 mt-3">
    <div class="row row-cols-3 g-1">
    <?php foreach ($species as $speciesID => $data):
        // Couleur de la carte = vérifie toutes les formes de l'espèce
        $cardCaught = false;
        $cardShiny  = false;
        foreach ($data['forms'] as $pid => $_) {
            $fp = $progressData[$pid] ?? ['caught' => 0, 'shiny' => 0];
            if ($fp['caught']) $cardCaught = true;
            if ($fp['shiny'])  $cardShiny  = true;
        }
        $cardClass = ($cardCaught && $cardShiny) ? 'both' : ($cardCaught ? 'normal' : ($cardShiny ? 'shiny' : ''));
    ?>
        <div class="col">
            <div class="poke-card <?= $cardClass ?>" id="card-<?= $speciesID ?>"
                 data-name-fr="<?= htmlspecialchars(mb_strtolower($data['name_fr'])) ?>"
                 data-name-en="<?= htmlspecialchars(mb_strtolower($data['name_en'])) ?>"
                 data-name-de="<?= htmlspecialchars(mb_strtolower($data['name_de'])) ?>">
                <div class="poke-num"><?= $data['position'] ? '#' . str_pad((string)$data['position'], 3, '0', STR_PAD_LEFT) : '—' ?></div>
                <?php if ($showVersion && $data['version']): ?>
                    <?php
                        $vLabel = match($data['version']) {
                            'scarlet' => 'Écarlate',
                            'violet'  => 'Violet',
                            'la'      => 'LA',
                            default   => htmlspecialchars($data['version']),
                        };
                        $vClass = match($data['version']) {
                            'scarlet' => 'scarlet',
                            'violet'  => 'violet',
                            default   => 'scarlet',
                        };
                    ?>
                    <span class="version-badge <?= $vClass ?>"><?= $vLabel ?></span>
                <?php endif; ?>
                <strong class="d-block mb-1" style="font-size:.88rem"><?= htmlspecialchars($data['name']) ?></strong>

                <?php foreach ($data['forms'] as $pid => $form):
                    // Progression propre à cette forme
                    $formProg   = $progressData[$pid] ?? ['caught' => 0, 'shiny' => 0, 'alpha' => 0];
                    $formCaught = $formProg['caught'];
                    $formShiny  = $formProg['shiny'];
                    $formAlpha  = $formProg['alpha'];
                    $safeId  = htmlspecialchars($pid);
                    $altName = htmlspecialchars($data['name']);
                    $noSprite = '<div style="width:72px;height:72px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:1.2rem">?</div>';
                ?>
                <div class="mb-2">
                    <?php if (!empty($form['code']) && $form['code'] !== 'base'): ?>
                        <div class="form-badge"><?= htmlspecialchars($form['code']) ?></div>
                    <?php endif; ?>

                    <div class="sprites-row">
                        <!-- Sprite normal -->
                        <div class="sprite-col">
                            <label class="sprite-label" for="chk-normal-<?= $safeId ?>">
                                <?php if ($form['sprite']): ?>
                                    <img src="<?= htmlspecialchars($form['sprite']) ?>"
                                         class="poke-img" alt="<?= $altName ?>">
                                <?php else: ?><?= $noSprite ?><?php endif; ?>
                            </label>
                            <div class="check-col">
                                <input type="checkbox"
                                       id="chk-normal-<?= $safeId ?>"
                                       onchange="saveCaught(this,'<?= $safeId ?>',<?= $speciesID ?>,'<?= $pokedexID ?>','caught')"
                                       <?= $formCaught ? 'checked' : '' ?>>
                                <span class="lbl-normal">Normal</span>
                            </div>
                        </div>

                        <!-- Sprite shiny -->
                        <div class="sprite-col">
                            <label class="sprite-label" for="chk-shiny-<?= $safeId ?>">
                                <?php if ($form['shiny']): ?>
                                    <img src="<?= htmlspecialchars($form['shiny']) ?>"
                                         class="poke-img shiny-sprite" alt="<?= $altName ?> shiny">
                                <?php else: ?>
                                    <div class="text-muted text-center" style="font-size:.65rem;line-height:1.2;padding:4px 0;cursor:pointer">sprite shiny<br>non disponible</div>
                                <?php endif; ?>
                            </label>
                            <div class="check-col">
                                <input type="checkbox"
                                       id="chk-shiny-<?= $safeId ?>"
                                       onchange="saveCaught(this,'<?= $safeId ?>',<?= $speciesID ?>,'<?= $pokedexID ?>','shiny')"
                                       <?= $formShiny ? 'checked' : '' ?>>
                                <span class="lbl-shiny">✨ Shiny</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($showAlpha): ?>
                    <div class="alpha-row check-col">
                        <input type="checkbox"
                               id="chk-alpha-<?= $safeId ?>"
                               onchange="saveCaught(this,'<?= $safeId ?>',<?= $speciesID ?>,'<?= $pokedexID ?>','alpha')"
                               <?= $formAlpha ? 'checked' : '' ?>>
                        <span class="lbl-alpha">⬆ Alpha
                            <span class="alpha-help"
                                  data-bs-toggle="tooltip"
                                  data-bs-placement="top"
                                  title="« Alpha » est le terme générique. Inclut aussi les Pokémon Baron (Légendes Arceus) et les Pokémon puissants de taille XXL dans EV.">?</span>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<button id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})"
    title="Remonter en haut"
    style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;
           width:44px;height:44px;border-radius:50%;border:none;
           background:#cc0000;color:white;font-size:1.1rem;
           box-shadow:0 4px 12px rgba(0,0,0,0.25);cursor:pointer;">&#8679;</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// formPid  = ID de la forme (ex: "154_m")  → envoyé à la BDD et utilisé pour les IDs HTML
// speciesId = ID de l'espèce (ex: 154)     → utilisé pour trouver la carte et recalculer les compteurs
function saveCaught(checkbox, formPid, speciesId, pokedexID, type) {
    const val = checkbox.checked ? 1 : 0;

    updateCardStyle(speciesId);
    recalcCounters();

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    fetch("update_caught.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-CSRF-Token": csrfToken
        },
        body: `pokemon_id=${encodeURIComponent(formPid)}&pokedex_id=${encodeURIComponent(pokedexID)}&${type}=${val}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok) {
            if (data.error === 'SESSION_EXPIRED') {
                alert("Votre session a expiré. Vous allez être redirigé vers la page de connexion.");
                window.location.href = 'users/login.php';
            } else {
                console.error("Erreur sauvegarde :", data.error);
                alert("Erreur lors de la sauvegarde :\n" + data.error);
                checkbox.checked = !checkbox.checked;
                updateCardStyle(speciesId);
                recalcCounters();
            }
        }
    })
    .catch(err => console.error("Erreur réseau :", err));
}

function updateCardStyle(speciesId) {
    const card = document.getElementById('card-' + speciesId);
    if (!card) return;
    const normalBoxes = card.querySelectorAll('[id^="chk-normal-"]');
    const shinyBoxes  = card.querySelectorAll('[id^="chk-shiny-"]');
    const hasCaught   = [...normalBoxes].some(cb => cb.checked);
    const hasShiny    = [...shinyBoxes].some(cb => cb.checked);

    card.classList.remove('normal', 'shiny', 'both');
    if (hasCaught && hasShiny) card.classList.add('both');
    else if (hasCaught)        card.classList.add('normal');
    else if (hasShiny)         card.classList.add('shiny');
}

function recalcCounters() {
    let normalCount = 0, shinyCount = 0, alphaCount = 0;
    const alphaEl = document.getElementById('count-alpha');
    document.querySelectorAll('.poke-card').forEach(card => {
        if ([...card.querySelectorAll('[id^="chk-normal-"]')].some(cb => cb.checked)) normalCount++;
        if ([...card.querySelectorAll('[id^="chk-shiny-"]')].some(cb => cb.checked))  shinyCount++;
        if ([...card.querySelectorAll('[id^="chk-alpha-"]')].some(cb => cb.checked))  alphaCount++;
    });
    document.getElementById('count-normal').textContent = normalCount;
    document.getElementById('count-shiny').textContent  = shinyCount;
    if (alphaEl) alphaEl.textContent = alphaCount;
}

// Init tooltips Bootstrap
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { trigger: 'hover focus' });
    });
});

const scrollBtn = document.getElementById('scrollTopBtn');
window.addEventListener('scroll', () => {
    scrollBtn.style.display = window.scrollY > 300 ? 'block' : 'none';
});
</script>
<script>
(function () {
    const filterInput = document.getElementById('pokeFilter');
    const countBadge  = document.getElementById('filterCount');
    if (!filterInput) return;

    // Construire l'index une seule fois au chargement
    const entries = Array.from(document.querySelectorAll('.poke-card')).map(card => ({
        card,
        col:  card.closest('.col'),
        fr:   card.dataset.nameFr  || '',
        en:   card.dataset.nameEn  || '',
        de:   card.dataset.nameDe  || '',
    }));

    let rafId = null;
    let matches = [];   // cartes correspondant au filtre actif
    let cursor  = -1;   // index dans matches de la carte mise en avant

    filterInput.addEventListener('input', () => {
        cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(() => applyFilter(filterInput.value.trim()));
    });

    filterInput.addEventListener('keydown', e => {
        if (e.key === 'Escape') { filterInput.value = ''; applyFilter(''); return; }
        if (!matches.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); moveCursor(1); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); moveCursor(-1); }
    });

    function moveCursor(dir) {
        if (!matches.length) return;
        highlightCard(cursor, false);
        cursor = (cursor + dir + matches.length) % matches.length;
        highlightCard(cursor, true);
        matches[cursor].scrollIntoView({ behavior: 'smooth', block: 'center' });
        countBadge.textContent = (cursor + 1) + ' / ' + matches.length;
    }

    function highlightCard(idx, on) {
        if (idx < 0 || idx >= matches.length) return;
        const card = matches[idx];
        card.style.outline      = on ? '3px solid var(--pk-red)' : '';
        card.style.outlineOffset = on ? '2px' : '';
        card.style.opacity      = on ? '1' : '';
    }

    function applyFilter(q) {
        // Retirer l'ancienne mise en avant
        highlightCard(cursor, false);
        cursor = -1;
        matches = [];

        const term = q.toLowerCase();
        entries.forEach(({ card, fr, en, de }) => {
            const match = !term || fr.includes(term) || en.includes(term) || de.includes(term);
            card.style.opacity       = match ? '' : '0.2';
            card.style.pointerEvents = match ? '' : 'none';
            card.style.outline       = '';
            if (match && term) matches.push(card);
        });

        if (!term) {
            countBadge.hidden = true;
        } else {
            countBadge.textContent = matches.length + ' / ' + entries.length;
            countBadge.hidden = false;
            if (matches.length) {
                cursor = 0;
                highlightCard(0, true);
                matches[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
})();
</script>
</body>
</html>
