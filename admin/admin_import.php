<?php require_once '../users/admin_required.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin – Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php"><img src="../img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;"></a>
        <span class="navbar-text text-white">Administration</span>
    </div>
</nav>

<div class="container" style="max-width:720px">
    <h1 class="mb-4">Importation</h1>

    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Import depuis PokéAPI</h5>
                    <p class="card-text text-muted flex-grow-1">
                        Importe n'importe quel Pokédex officiel directement depuis PokéAPI —
                        noms FR/EN/DE, sprites et ordre régional inclus. Aucun fichier requis.
                    </p>
                    <a href="import_pokeapi.php" class="btn btn-danger mt-2">Utiliser PokéAPI</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Import depuis un fichier JSON</h5>
                    <p class="card-text text-muted flex-grow-1">
                        Import manuel via un fichier JSON généré par le script Python
                        (pour les Pokédex non disponibles sur PokéAPI comme ZA).
                    </p>
                    <button class="btn btn-outline-secondary mt-2" data-bs-toggle="collapse" data-bs-target="#fileForm">
                        Import fichier
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="collapse" id="fileForm">
        <div class="card">
            <div class="card-header fw-semibold">Import fichier JSON / CSV</div>
            <div class="card-body">
                <form action="import_handler.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type de fichier</label>
                        <select class="form-select" name="csv_type" required>
                            <option value="database">Database (noms FR/EN/DE + sprites)</option>
                            <option value="pokedex">Pokédex (ZA, SV, XY, etc.)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fichier JSON</label>
                        <input type="file" class="form-control" name="file" accept=".json,.csv" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Code du Pokédex</label>
                        <input type="text" class="form-control" name="pokedex_code" placeholder="Ex: ZA" maxlength="10">
                    </div>
                    <button type="submit" class="btn btn-primary">Importer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
