<?php
include_once 'include.php';
$pdo = DB::getPDO();

$stmt = $pdo->query("SELECT code, name FROM pokedex_list ORDER BY id ASC");
$pokedexList = $stmt->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accueil — Mon Pokédex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="index.php"><img src="img/logo_pokedex.svg" alt="Mon Pokédex" style="height:72px;"></a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto gap-1">
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="/users/profile.php">Profil</a></li>
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="admin/admin_import.php">Administration</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link" href="users/logout.php">Déconnexion</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="users/login.php">Connexion</a></li>
          <li class="nav-item"><a class="nav-link" href="users/register.php">Inscription</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
</body>
</html>
