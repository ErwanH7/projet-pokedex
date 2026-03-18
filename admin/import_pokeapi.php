<?php require_once '../users/admin_required.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin – Import depuis PokéAPI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php"><img src="../img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;"></a>
        <span class="navbar-text text-white">Administration</span>
        <a class="btn btn-outline-light btn-sm" href="admin_import.php">Import fichier</a>
    </div>
</nav>

<div class="container" style="max-width:720px">
    <h1 class="mb-1">Import depuis PokéAPI</h1>
    <p class="text-muted mb-4">Importe un Pokédex complet (noms FR/EN/DE + sprites + ordre régional) directement depuis <a href="https://pokeapi.co" target="_blank">pokeapi.co</a>.</p>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Pokédex disponibles (sélection)</div>
        <div class="card-body">
            <form method="POST" id="importForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Pokédex à importer</label>
                    <select class="form-select" name="pokedex_slug" required>
                        <optgroup label="Génération 1">
                            <option value="kanto">Kanto (RB/RY/FR/LG)</option>
                        </optgroup>
                        <optgroup label="Génération 2">
                            <option value="original-johto">Johto (OR/AS)</option>
                            <option value="updated-johto">Johto mis à jour (HG/SS)</option>
                        </optgroup>
                        <optgroup label="Génération 3">
                            <option value="hoenn">Hoenn (RS/E)</option>
                            <option value="updated-hoenn">Hoenn mis à jour (ORAS)</option>
                        </optgroup>
                        <optgroup label="Génération 4">
                            <option value="original-sinnoh">Sinnoh (DP)</option>
                            <option value="updated-sinnoh">Sinnoh mis à jour (Pt)</option>
                        </optgroup>
                        <optgroup label="Génération 5">
                            <option value="original-unova">Unova (NB)</option>
                            <option value="updated-unova">Unova mis à jour (N2B2)</option>
                        </optgroup>
                        <optgroup label="Génération 6">
                            <option value="kalos-central+kalos-coastal+kalos-mountain">Kalos complet (XY)</option>
                        </optgroup>
                        <optgroup label="Génération 7">
                            <option value="original-melemele+original-akala+original-ulaula+original-poni">Alola complet (SM)</option>
                            <option value="updated-melemele+updated-akala+updated-ulaula+updated-poni">Alola complet mis à jour (USUM)</option>
                        </optgroup>
                        <optgroup label="Génération 8">
                            <option value="galar">Galar (EB)</option>
                            <option value="isle-of-armor">Île Armure (EB)</option>
                            <option value="crown-tundra">Terres Couronnes (EB)</option>
                            <option value="hisui">Hisui (LA)</option>
                        </optgroup>
                        <optgroup label="Génération 9">
                            <option value="paldea">Paldea (EV)</option>
                            <option value="kitakami">Kitakami (EV)</option>
                            <option value="blueberry">Blueberry (EV)</option>
                        </optgroup>
                        <optgroup label="Légendes Z-A">
                            <option value="lumiose-city">ZA — Lumiose City (code conseillé : ZAL)</option>
                            <option value="hyperspace">ZA — Hyperespace (code conseillé : ZAH)</option>
                        </optgroup>
                        <optgroup label="National">
                            <option value="national">Pokédex National complet</option>
                        </optgroup>
                        <optgroup label="Spéciaux">
                            <option value="mega-evolutions">Méga Évolutions (tous jeux)</option>
                        </optgroup>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Code interne du Pokédex</label>
                    <input type="text" class="form-control" name="pokedex_code" placeholder="Ex: XY, SV, LA, KANTO…" maxlength="20" required>
                    <div class="form-text">Code utilisé dans votre application (ex : ZA, SV, LA).</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nom affiché</label>
                    <input type="text" class="form-control" name="pokedex_name" placeholder="Ex: Pokédex de Kalos" maxlength="100" required>
                </div>

                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="import_pokemon_data" id="importData" checked>
                        <label class="form-check-label" for="importData">
                            Importer aussi les noms (FR/EN/DE) et sprites des Pokémon
                            <small class="text-muted">(plus lent — 1 requête par Pokémon)</small>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger" id="submitBtn">
                    Lancer l'import
                </button>
            </form>
        </div>
    </div>

    <div class="card mb-4 border-warning">
        <div class="card-header fw-semibold text-warning-emphasis bg-warning-subtle">Outils de maintenance</div>
        <div class="card-body">
            <p class="text-muted small mb-2">Répare les sprites manquants des formes Méga en interrogeant PokéAPI puis Bulbagarden Archives.</p>
            <button class="btn btn-warning btn-sm" id="fixSpritesBtn">🔧 Corriger les sprites Méga manquants</button>
        </div>
    </div>

    <div class="card d-none" id="progressCard">
        <div class="card-header fw-semibold">Progression</div>
        <div class="card-body">
            <div id="progressLog" style="font-family:monospace; font-size:0.85rem; max-height:400px; overflow-y:auto; white-space:pre-wrap;"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const card = document.getElementById('progressCard');
    const log  = document.getElementById('progressLog');

    btn.disabled = true;
    btn.textContent = 'Import en cours…';
    card.classList.remove('d-none');
    log.textContent = '';

    const data = new FormData(this);

    fetch('pokeapi_handler.php', { method: 'POST', body: data })
        .then(response => {
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            function read() {
                reader.read().then(({ done, value }) => {
                    if (done) {
                        btn.disabled = false;
                        btn.textContent = 'Lancer l\'import';
                        return;
                    }
                    log.textContent += decoder.decode(value);
                    log.scrollTop = log.scrollHeight;
                    read();
                });
            }
            read();
        })
        .catch(err => {
            log.textContent += '\n❌ Erreur réseau : ' + err.message;
            btn.disabled = false;
            btn.textContent = 'Lancer l\'import';
        });
});

document.getElementById('fixSpritesBtn').addEventListener('click', function() {
    const btn  = document.getElementById('fixSpritesBtn');
    const card = document.getElementById('progressCard');
    const log  = document.getElementById('progressLog');

    btn.disabled = true;
    btn.textContent = 'Correction en cours…';
    card.classList.remove('d-none');
    log.textContent = '';

    fetch('fix_mega_sprites.php')
        .then(response => {
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            function read() {
                reader.read().then(({ done, value }) => {
                    if (done) { btn.disabled = false; btn.textContent = '🔧 Corriger les sprites Méga manquants'; return; }
                    log.textContent += decoder.decode(value);
                    log.scrollTop = log.scrollHeight;
                    read();
                });
            }
            read();
        })
        .catch(err => {
            log.textContent += '\n❌ Erreur : ' + err.message;
            btn.disabled = false;
        });
});
</script>
</body>
</html>
