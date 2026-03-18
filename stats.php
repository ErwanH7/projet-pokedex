<?php
include_once 'include.php';
$pdo    = DB::getPDO();
$userID = $_SESSION['user_id'];

$ALPHA_DEXES = ['ZAL', 'EV', 'LA', 'EV1', 'EV2', 'ZAH'];

$stmt = $pdo->prepare("
    SELECT
        pl.id,
        pl.code,
        pl.name,
        COUNT(DISTINCT p.id)                                   AS total_species,
        COUNT(DISTINCT CASE WHEN up.caught = 1 THEN p.id END)  AS caught_normal,
        COUNT(DISTINCT CASE WHEN up.shiny  = 1 THEN p.id END)  AS caught_shiny,
        COUNT(DISTINCT CASE WHEN up.alpha  = 1 THEN p.id END)  AS caught_alpha
    FROM pokedex_list pl
    LEFT JOIN pokedex_entries pe ON pe.pokedex_id = pl.id
    LEFT JOIN pokemon_forms   pf ON pf.id         = pe.pokemon_id
    LEFT JOIN pokemon          p ON p.id           = pf.pokemon_id
    LEFT JOIN user_progress   up ON up.pokedex_id  = pl.id
                                AND up.pokemon_id  = pe.pokemon_id
                                AND up.user_id     = ?
    GROUP BY pl.id, pl.code, pl.name
    ORDER BY pl.id ASC
");
$stmt->execute([$userID]);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistiques — Mon Pokédex</title>
    <link rel="icon" type="image/png" href="/img/logo_pokedex.png">
    <meta name="description" content="Consulte tes statistiques de captures Pokémon par jeu.">
    <meta property="og:title" content="Statistiques — Mon Pokédex">
    <meta property="og:description" content="Consulte tes statistiques de captures Pokémon par jeu.">
    <meta property="og:image" content="<?= htmlspecialchars($baseUrl) ?>/img/logo_pokedex.png">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="<?= htmlspecialchars($baseUrl) ?>/img/logo_pokedex.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        /* ── Stats page ── */
        body { background: #f0f4f8; }

        .stats-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 2.5rem 0 2rem;
            margin-bottom: 2.5rem;
        }
        .stats-hero h1 { font-size: 2rem; font-weight: 800; margin-bottom: .25rem; }
        .stats-hero p  { opacity: .65; font-size: .95rem; margin: 0; }

        .dex-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            overflow: hidden;
            transition: box-shadow .2s, transform .2s;
        }
        .dex-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.12); transform: translateY(-2px); }

        .dex-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .9rem 1.25rem .6rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .dex-card-title {
            font-weight: 800;
            font-size: 1rem;
            color: #1e293b;
            text-decoration: none;
            transition: color .15s;
        }
        .dex-card-title:hover { color: var(--pk-red); }

        .medal-gold   { font-size: 1.3rem; line-height: 1; }
        .medal-row    { display: flex; align-items: center; gap: 4px; }

        .dex-card-body { padding: .85rem 1.25rem 1rem; }

        .stat-row {
            display: grid;
            grid-template-columns: 80px 1fr 56px 68px 28px;
            align-items: center;
            gap: 8px;
            margin-bottom: .55rem;
        }
        .stat-row:last-child { margin-bottom: 0; }

        .stat-label {
            font-size: .78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .stat-bar-wrap .progress {
            height: 11px;
            border-radius: 99px;
            background: #e2e8f0;
        }
        .stat-bar-wrap .progress-bar {
            border-radius: 99px;
            transition: width .8s cubic-bezier(.4,0,.2,1);
        }

        .stat-pct {
            font-size: .85rem;
            font-weight: 800;
            text-align: right;
            white-space: nowrap;
        }
        .stat-count {
            font-size: .72rem;
            color: #94a3b8;
            text-align: right;
            white-space: nowrap;
        }
        .stat-medal {
            font-size: 1rem;
            line-height: 1;
            text-align: center;
        }

        /* Couleurs */
        .c-normal { color: #16a34a; }
        .c-shiny  { color: #a855f7; }
        .c-alpha  { color: #f97316; }
        .bar-normal { background: #16a34a !important; }
        .bar-shiny  { background: #a855f7 !important; }
        .bar-alpha  { background: #f97316 !important; }

        /* Résumé global en haut */
        .global-summary {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .summary-box {
            background: white;
            border-radius: 14px;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            flex: 1;
            min-width: 140px;
            text-align: center;
        }
        .summary-val  { font-size: 1.8rem; font-weight: 800; }
        .summary-lbl  { font-size: .72rem; color: #94a3b8; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; margin-top: .2rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="index.php"><img src="img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;"></a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto gap-1">
        <li class="nav-item"><a class="nav-link active" href="/stats.php">Statistiques</a></li>
        <li class="nav-item"><a class="nav-link" href="/users/profile.php">Profil</a></li>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="admin/admin_import.php">Administration</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="users/logout.php">Déconnexion</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="stats-hero">
    <div class="container px-4">
        <h1>Mes statistiques</h1>
        <p>Progression globale sur tous vos Pokédex</p>
    </div>
</div>

<div class="container px-4 pb-5">

<?php if (empty($rows)): ?>
    <p class="text-muted text-center">Aucun Pokédex disponible.</p>
<?php else:
    // ── Calcul du résumé global ───────────────────────────────────────────
    $totalDex       = count($rows);
    $completedNormal = 0;
    $completedShiny  = 0;
    foreach ($rows as $r) {
        $t = max(1, (int)$r['total_species']);
        if ((int)$r['caught_normal'] >= $t) $completedNormal++;
        if ((int)$r['caught_shiny']  >= $t) $completedShiny++;
    }
?>

<!-- Résumé global -->
<div class="global-summary">
    <div class="summary-box">
        <div class="summary-val" style="color:var(--pk-red)"><?= $totalDex ?></div>
        <div class="summary-lbl">Pokédex</div>
    </div>
    <div class="summary-box">
        <div class="summary-val c-normal"><?= $completedNormal ?></div>
        <div class="summary-lbl">Complétés (Normal)</div>
    </div>
    <div class="summary-box">
        <div class="summary-val c-shiny"><?= $completedShiny ?></div>
        <div class="summary-lbl">Complétés (Shiny)</div>
    </div>
    <div class="summary-box">
        <div class="summary-val medal-gold"><?= $completedNormal > 0 && $completedShiny > 0 ? '🥇' : ($completedNormal > 0 ? '🥉' : ($completedShiny > 0 ? '🥈' : '—')) ?></div>
        <div class="summary-lbl">Meilleure médaille</div>
    </div>
</div>

<!-- Cartes par Pokédex -->
<div class="row row-cols-1 row-cols-md-2 g-4">
<?php foreach ($rows as $row):
    $total    = max(1, (int)$row['total_species']);
    $normal   = (int)$row['caught_normal'];
    $shiny    = (int)$row['caught_shiny'];
    $alpha    = (int)$row['caught_alpha'];
    $hasAlpha = in_array(strtoupper($row['code']), $ALPHA_DEXES);

    $pctNormal = round($normal / $total * 100, 1);
    $pctShiny  = round($shiny  / $total * 100, 1);
    $pctAlpha  = $hasAlpha ? round($alpha / $total * 100, 1) : null;

    $fullNormal = $normal >= $total;
    $fullShiny  = $shiny  >= $total;
    $goldMedal  = $fullNormal && $fullShiny;
?>
<div class="col">
    <div class="dex-card">
        <div class="dex-card-header">
            <a href="pokedex.php?dex=<?= urlencode($row['code']) ?>" class="dex-card-title">
                <?= htmlspecialchars($row['name']) ?>
            </a>
            <div class="medal-row">
                <?php if ($goldMedal): ?>
                    <span class="medal-gold" title="Pokédex complété Normal + Shiny !">🥇</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="dex-card-body">

            <!-- Normal -->
            <div class="stat-row">
                <span class="stat-label c-normal">Normal</span>
                <div class="stat-bar-wrap">
                    <div class="progress">
                        <div class="progress-bar bar-normal"
                             style="width:<?= $pctNormal ?>%"
                             aria-valuenow="<?= $pctNormal ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <span class="stat-pct c-normal"><?= $pctNormal ?>%</span>
                <span class="stat-count"><?= $normal ?> / <?= $total ?></span>
                <span class="stat-medal"><?= $fullNormal ? '🥉' : '' ?></span>
            </div>

            <!-- Shiny -->
            <div class="stat-row">
                <span class="stat-label c-shiny">✨ Shiny</span>
                <div class="stat-bar-wrap">
                    <div class="progress">
                        <div class="progress-bar bar-shiny"
                             style="width:<?= $pctShiny ?>%"
                             aria-valuenow="<?= $pctShiny ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <span class="stat-pct c-shiny"><?= $pctShiny ?>%</span>
                <span class="stat-count"><?= $shiny ?> / <?= $total ?></span>
                <span class="stat-medal"><?= $fullShiny ? '🥈' : '' ?></span>
            </div>

            <?php if ($hasAlpha): ?>
            <!-- Alpha -->
            <div class="stat-row">
                <span class="stat-label c-alpha">⬆ Alpha</span>
                <div class="stat-bar-wrap">
                    <div class="progress">
                        <div class="progress-bar bar-alpha"
                             style="width:<?= $pctAlpha ?>%"
                             aria-valuenow="<?= $pctAlpha ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <span class="stat-pct c-alpha"><?= $pctAlpha ?>%</span>
                <span class="stat-count"><?= $alpha ?> / <?= $total ?></span>
                <span class="stat-medal"></span>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
