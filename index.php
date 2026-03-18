<?php
include_once 'include.php';
$isLoggedIn = isset($_SESSION['user_id']);

if ($isLoggedIn) {
    $pdo = DB::getPDO();
    $stmt = $pdo->query("SELECT code, name FROM pokedex_list ORDER BY id ASC");
    $pokedexList = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon Pokédex — Suivi de collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <script src="https://feedeko.timdidelot.fr/widget.js" data-api-key="fw_c5484d61c80c4047bada0949ab973c6a" defer></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="index.php"><img src="img/logo_pokedex.svg" alt="Mon Pokédex" style="height:72px;"></a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto gap-1">
        <?php if ($isLoggedIn): ?>
          <li class="nav-item"><a class="nav-link" href="/stats.php">Statistiques</a></li>
          <li class="nav-item"><a class="nav-link" href="/users/profile.php">Profil</a></li>
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="admin/admin_import.php">Administration</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link" href="users/logout.php">Déconnexion</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="users/login.php">Connexion</a></li>
          <li class="nav-item"><a class="nav-link active" href="users/register.php">Inscription</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<?php if (!$isLoggedIn): ?>
<!-- ══ LANDING PAGE (visiteurs non connectés) ══════════════════════════════ -->
<div class="landing-hero">
    <div class="container text-center py-5">
        <img src="img/logo_pokedex.svg" alt="Mon Pokédex" style="height:110px;" class="mb-4">
        <h1 class="landing-title">Suivez votre collection Pokémon</h1>
        <p class="landing-subtitle">
            Mon Pokédex est un tracker en ligne qui vous permet de cocher les Pokémon que vous avez capturés
            dans chacun de vos jeux, y compris leurs formes chromatiques (shiny).
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
            <a href="users/register.php" class="btn btn-danger btn-lg fw-semibold px-5">Créer un compte gratuit</a>
            <a href="users/login.php" class="btn btn-light btn-lg fw-semibold px-5 text-dark">Se connecter</a>
        </div>
    </div>
</div>

<!-- ══ FONCTIONNALITÉS ══════════════════════════════════════════════════════ -->
<div class="container py-5">
    <h2 class="text-center fw-800 mb-5">Comment ça fonctionne ?</h2>
    <div class="row g-4 text-center">
        <div class="col-md-4">
            <div class="landing-feature-card">
                <div class="landing-feature-icon">📖</div>
                <h5 class="fw-700 mt-3">Choisissez un jeu</h5>
                <p class="text-muted">Sélectionnez le Pokédex du jeu qui vous intéresse parmi tous les titres disponibles.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="landing-feature-card">
                <div class="landing-feature-icon">✅</div>
                <h5 class="fw-700 mt-3">Cochez vos captures</h5>
                <p class="text-muted">Marquez chaque Pokémon comme capturé ou shiny en un seul clic. La progression se sauvegarde automatiquement.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="landing-feature-card">
                <div class="landing-feature-icon">✨</div>
                <h5 class="fw-700 mt-3">Suivez votre progression</h5>
                <p class="text-muted">Visualisez en temps réel combien de Pokémon vous avez capturés, normaux et shiny, dans chaque Pokédex.</p>
            </div>
        </div>
    </div>
</div>

<!-- ══ QU'EST-CE QU'UN POKÉDEX ? ══════════════════════════════════════════ -->
<div class="landing-explain-band py-5">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-md-6">
                <h2 class="fw-800 mb-3">C'est quoi Pokémon ?</h2>
                <p>
                    Pokémon est une franchise de jeux vidéo dans laquelle vous incarnez un Dresseur qui explore le monde
                    et capture des créatures appelées <strong>Pokémon</strong>. L'objectif principal est de compléter le
                    <strong>Pokédex</strong> — l'encyclopédie recensant toutes les espèces — en les capturant toutes.
                </p>
                <p>
                    Chaque jeu possède son propre Pokédex régional, limité aux espèces accessibles dans ce titre.
                    En plus des formes normales, certains Pokémon peuvent apparaître en version <strong>shiny</strong> :
                    une coloration alternative très rare, très recherchée par les collectionneurs.
                </p>
                <p>
                    Ce site vous aide à tenir le compte de vos captures à travers tous vos jeux, sans rien oublier.
                </p>
            </div>
            <div class="col-md-6 text-center">
                <div class="landing-stat-grid">
                    <div class="landing-stat-box">
                        <div class="landing-stat-number">1 000+</div>
                        <div class="landing-stat-label">Pokémon disponibles</div>
                    </div>
                    <div class="landing-stat-box">
                        <div class="landing-stat-number">Shiny</div>
                        <div class="landing-stat-label">Suivi des formes chromatiques</div>
                    </div>
                    <div class="landing-stat-box">
                        <div class="landing-stat-number">Multi-jeux</div>
                        <div class="landing-stat-label">Tous les Pokédex régionaux</div>
                    </div>
                    <div class="landing-stat-box">
                        <div class="landing-stat-number">Gratuit</div>
                        <div class="landing-stat-label">Sans abonnement</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══ CTA FINAL ════════════════════════════════════════════════════════════ -->
<div class="container text-center py-5">
    <h2 class="fw-800 mb-3">Prêt à compléter votre Pokédex ?</h2>
    <p class="text-muted mb-4">Inscription gratuite, aucune application à télécharger.</p>
    <a href="users/register.php" class="btn btn-danger btn-lg fw-semibold px-5">Commencer maintenant</a>
</div>

<?php else: ?>
<!-- ══ PAGE POKÉDEX (utilisateurs connectés) ════════════════════════════════ -->
<div class="container px-4">
    <div class="dex-hero">
        <h2>Choisissez un Pokédex</h2>
        <p>Suivez votre progression sur tous vos jeux Pokémon</p>
        <div class="search-wrap mx-auto mt-3" style="max-width:500px">
            <input type="search" id="globalSearch" class="form-control form-control-lg"
                   placeholder="Chercher un Pokédex ou un Pokémon…"
                   autocomplete="off" aria-label="Recherche globale">
            <div id="searchDropdown" class="search-dropdown" role="listbox" hidden></div>
        </div>
    </div>

    <?php if (empty($pokedexList)): ?>
        <p class="text-center text-muted">Aucun Pokédex disponible.</p>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 pb-5">
        <?php foreach ($pokedexList as $dex): ?>
        <div class="col">
            <a href="pokedex.php?dex=<?= urlencode($dex['code']) ?>" class="dex-card h-100">
                <div class="dex-card-name"><?= htmlspecialchars($dex['name']) ?></div>
                <div class="dex-card-cta">Voir le Pokédex →</div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const input    = document.getElementById('globalSearch');
    const dropdown = document.getElementById('searchDropdown');
    let timer = null, controller = null;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { closeDropdown(); return; }
        timer = setTimeout(() => fetchResults(q), 300);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.search-wrap')) closeDropdown();
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeDropdown(); input.blur(); }
        if (e.key === 'ArrowDown') { focusItem(0); e.preventDefault(); }
    });

    async function fetchResults(q) {
        if (controller) controller.abort();
        controller = new AbortController();
        try {
            const res  = await fetch('/search.php?q=' + encodeURIComponent(q), { signal: controller.signal });
            if (!res.headers.get('content-type')?.includes('application/json')) return;
            const data = await res.json();
            render(data, q);
        } catch (err) {
            if (err.name !== 'AbortError') closeDropdown();
        }
    }

    function render(data, q) {
        const hasDex = data.pokedexes?.length > 0;
        const hasPok = data.pokemon?.length > 0;
        if (!hasDex && !hasPok) {
            dropdown.innerHTML = '<div class="search-empty">Aucun résultat pour « ' + esc(q) + ' »</div>';
            openDropdown(); return;
        }
        let html = '';
        if (hasDex) {
            html += '<div class="search-section-label">Pokédex</div>';
            for (const d of data.pokedexes) {
                html += `<a href="/pokedex.php?dex=${enc(d.code)}" class="search-item">
                    <span class="search-item-icon">📖</span>
                    <span class="search-item-text" style="font-weight:700">${hl(esc(d.name), q)}</span>
                </a>`;
            }
        }
        if (hasPok) {
            html += '<div class="search-section-label">Pokémon</div>';
            for (const p of data.pokemon) {
                const name = p.name_fr || p.name_en || '#' + p.species_id;
                const sprite = p.sprite
                    ? `<img src="${esc(p.sprite)}" class="search-sprite" alt="" loading="lazy">`
                    : `<span class="search-sprite-placeholder"></span>`;
                const tags = (p.dexes || []).map(d =>
                    `<a href="/pokedex.php?dex=${enc(d.code)}#card-${p.species_id}" class="search-dex-tag">${esc(d.name)}</a>`
                ).join('') || '<span style="font-size:.72rem;color:#94a3b8">Aucun pokédex</span>';
                html += `<div class="search-item search-item-poke" tabindex="-1">
                    ${sprite}
                    <div class="search-item-body">
                        <span class="search-item-name">${hl(esc(name), q)}</span>
                        <div class="search-dex-tags">${tags}</div>
                    </div>
                </div>`;
            }
        }
        dropdown.innerHTML = html;
        openDropdown();
        setupKeyNav();
    }

    function openDropdown()  { dropdown.hidden = false; }
    function closeDropdown() { dropdown.hidden = true; dropdown.innerHTML = ''; }
    function focusItem(i)    { const items = dropdown.querySelectorAll('.search-item'); items[i]?.focus(); }

    function setupKeyNav() {
        const items = Array.from(dropdown.querySelectorAll('.search-item'));
        items.forEach((el, i) => {
            el.addEventListener('keydown', e => {
                if (e.key === 'ArrowDown') { focusItem(i + 1); e.preventDefault(); }
                if (e.key === 'ArrowUp')   { i === 0 ? input.focus() : focusItem(i - 1); e.preventDefault(); }
                if (e.key === 'Escape')    { closeDropdown(); input.focus(); }
            });
        });
    }

    function esc(s) { const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
    function enc(s) { return encodeURIComponent(s); }
    function hl(html, q) {
        const safe = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return html.replace(new RegExp('(' + safe + ')', 'gi'), '<mark>$1</mark>');
    }
})();
</script>
<?php endif; ?>

<!-- ══ FOOTER RGPD ══════════════════════════════════════════════════════════ -->
<footer class="site-footer">
    <div class="container text-center">
        <span>Mon Pokédex &copy; <?= date('Y') ?> &mdash;
        <button class="btn btn-link btn-sm p-0 footer-link" data-bs-toggle="modal" data-bs-target="#rgpdModal">
            Politique de confidentialité (RGPD)
        </button>
        </span>
    </div>
</footer>

<!-- Modal RGPD -->
<div class="modal fade" id="rgpdModal" tabindex="-1" aria-labelledby="rgpdModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="rgpdModalLabel">Politique de confidentialité — RGPD</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <h6 class="fw-bold">1. Responsable du traitement</h6>
        <p>Le site Mon Pokédex est un projet personnel à vocation non commerciale. Pour toute question relative à vos données, vous pouvez nous contacter via le widget de support disponible sur le site.</p>

        <h6 class="fw-bold">2. Données collectées</h6>
        <p>Lors de votre inscription, nous collectons les informations suivantes :</p>
        <ul>
            <li><strong>Adresse email</strong> — utilisée comme identifiant de connexion</li>
            <li><strong>Pseudo</strong> — optionnel, affiché sur votre profil</li>
            <li><strong>Mot de passe</strong> — stocké sous forme chiffrée (bcrypt), jamais en clair</li>
            <li><strong>Langue préférée</strong> — pour l'affichage des noms de Pokémon</li>
            <li><strong>Progression Pokédex</strong> — Pokémon marqués comme capturés ou shiny</li>
        </ul>

        <h6 class="fw-bold">3. Finalité du traitement</h6>
        <p>Ces données sont utilisées uniquement pour faire fonctionner le service : authentification, sauvegarde de votre progression et personnalisation de l'affichage. Elles ne sont ni revendues, ni partagées avec des tiers.</p>

        <h6 class="fw-bold">4. Durée de conservation</h6>
        <p>Vos données sont conservées tant que votre compte est actif. Vous pouvez demander la suppression de votre compte à tout moment via le support.</p>

        <h6 class="fw-bold">5. Vos droits (RGPD)</h6>
        <p>Conformément au Règlement Général sur la Protection des Données (RGPD), vous disposez des droits suivants :</p>
        <ul>
            <li>Droit d'accès à vos données</li>
            <li>Droit de rectification</li>
            <li>Droit à l'effacement (« droit à l'oubli »)</li>
            <li>Droit à la portabilité de vos données</li>
        </ul>
        <p>Pour exercer ces droits, contactez-nous via le widget de support.</p>

        <h6 class="fw-bold">6. Cookies</h6>
        <p>Ce site utilise uniquement un cookie de session (HttpOnly, SameSite=Strict) pour maintenir votre connexion. Aucun cookie publicitaire ou de traçage n'est utilisé.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
