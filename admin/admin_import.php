<?php require_once '../users/admin_required.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin – Panneau d'administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --pk-red: #dc2626; }

        body { background: #f0f4f8; }

        .admin-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 2rem 0 1.75rem;
            margin-bottom: 2.5rem;
        }
        .admin-hero h1 { font-size: 1.9rem; font-weight: 800; margin-bottom: .2rem; }
        .admin-hero p  { opacity: .55; font-size: .9rem; margin: 0; }

        .section-title {
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 1rem;
        }

        /* Carte d'action */
        .action-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            overflow: hidden;
            transition: box-shadow .2s, transform .2s;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .action-card:hover {
            box-shadow: 0 6px 24px rgba(0,0,0,.13);
            transform: translateY(-3px);
            color: inherit;
        }
        .action-card-icon {
            font-size: 2rem;
            line-height: 1;
            margin-bottom: .75rem;
        }
        .action-card-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .action-card-title {
            font-size: 1rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: .4rem;
        }
        .action-card-desc {
            font-size: .82rem;
            color: #64748b;
            line-height: 1.5;
            flex: 1;
        }
        .action-card-footer {
            padding: .85rem 1.5rem;
            border-top: 1px solid #f1f5f9;
            font-size: .8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        /* Couleurs accent par type */
        .ac-red    .action-card-footer { color: var(--pk-red); }
        .ac-blue   .action-card-footer { color: #2563eb; }
        .ac-slate  .action-card-footer { color: #475569; }
        .ac-violet .action-card-footer { color: #7c3aed; }
        .ac-amber  .action-card-footer { color: #d97706; }

        .ac-red    { border-top: 3px solid var(--pk-red); }
        .ac-blue   { border-top: 3px solid #2563eb; }
        .ac-slate  { border-top: 3px solid #94a3b8; }
        .ac-violet { border-top: 3px solid #7c3aed; }
        .ac-amber  { border-top: 3px solid #d97706; }

        /* Carte import fichier (collapse) */
        .import-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            overflow: hidden;
            border-top: 3px solid #94a3b8;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="../index.php">
            <img src="../img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;">
        </a>
        <span class="navbar-text text-white fw-semibold">Administration</span>
    </div>
</nav>

<div class="admin-hero">
    <div class="container px-4">
        <h1>Panneau d'administration</h1>
        <p>Gestion des données, des utilisateurs et de la base de données.</p>
    </div>
</div>

<div class="container px-4 pb-5" style="max-width:900px">

    <!-- ── Gestion ─────────────────────────────────────────────────── -->
    <p class="section-title">Gestion</p>
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-lg-4">
            <a href="users.php" class="action-card ac-blue">
                <div class="action-card-body">
                    <div class="action-card-icon">👥</div>
                    <div class="action-card-title">Utilisateurs</div>
                    <div class="action-card-desc">Consulter la liste des comptes, voir la progression et supprimer des utilisateurs.</div>
                </div>
                <div class="action-card-footer">Gérer les utilisateurs →</div>
            </a>
        </div>

        <div class="col-sm-6 col-lg-4">
            <a href="pokedex_names.php" class="action-card ac-violet">
                <div class="action-card-body">
                    <div class="action-card-icon">🌐</div>
                    <div class="action-card-title">Noms des Pokédex</div>
                    <div class="action-card-desc">Modifier les noms en français, anglais, allemand et espagnol pour chaque Pokédex.</div>
                </div>
                <div class="action-card-footer">Modifier les noms →</div>
            </a>
        </div>

        <div class="col-sm-6 col-lg-4">
            <a href="../migrate.php" target="_blank" class="action-card ac-amber">
                <div class="action-card-body">
                    <div class="action-card-icon">🔧</div>
                    <div class="action-card-title">Migration DB</div>
                    <div class="action-card-desc">Ajouter les colonnes manquantes à la base de données (multilingue, ENUM langue…).</div>
                </div>
                <div class="action-card-footer">Lancer la migration →</div>
            </a>
        </div>

    </div>

    <!-- ── Import ──────────────────────────────────────────────────── -->
    <p class="section-title">Import de données</p>
    <div class="row g-3 mb-4">

        <div class="col-sm-6">
            <a href="import_pokeapi.php" class="action-card ac-red">
                <div class="action-card-body">
                    <div class="action-card-icon">📡</div>
                    <div class="action-card-title">Import depuis PokéAPI</div>
                    <div class="action-card-desc">Importe n'importe quel Pokédex officiel directement depuis PokéAPI — noms FR/EN/DE/ES, sprites et ordre régional inclus. Aucun fichier requis.</div>
                </div>
                <div class="action-card-footer">Utiliser PokéAPI →</div>
            </a>
        </div>

        <div class="col-sm-6">
            <div class="action-card ac-slate" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#fileForm">
                <div class="action-card-body">
                    <div class="action-card-icon">📁</div>
                    <div class="action-card-title">Import depuis un fichier JSON</div>
                    <div class="action-card-desc">Import manuel via un fichier JSON ou CSV généré par le script Python, pour les Pokédex non disponibles sur PokéAPI.</div>
                </div>
                <div class="action-card-footer">Importer un fichier →</div>
            </div>
        </div>

    </div>

    <!-- ── Formulaire import fichier (collapse) ────────────────────── -->
    <div class="collapse" id="fileForm">
        <div class="import-card mb-4">
            <div class="p-3 border-bottom fw-semibold" style="background:#f8fafc;font-size:.9rem;">
                📁 Import fichier JSON / CSV
            </div>
            <div class="p-4">
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
                    <button type="submit" class="btn btn-danger fw-semibold px-4">Importer</button>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
