<?php
require_once 'config/pdo.php';
require_once 'config/constantesPDO.php';
session_start();
$pdo = DB::getPDO();

// Récupérer tous les Pokédex
$stmt = $pdo->query("SELECT code, name FROM pokedex_list ORDER BY id ASC");
$pokedexList = $stmt->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Accueil - Pokedex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Mon Pokedex</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="profile.php"><?= htmlspecialchars($_SESSION['username'] ?: $_SESSION['email']) ?></a></li>
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="admin/users.php">Admin</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link" href="logout.php">Déconnexion</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="login.php">Connexion</a></li>
          <li class="nav-item"><a class="nav-link" href="register.php">Inscription</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
    <h1 class="mb-4 text-center">Choisissez un Pokédex</h1>
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($pokedexList as $dex): ?>
            <div class="col">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($dex['name']) ?></h5>
                        <a href="pokedex.php?dex=<?= urlencode($dex['code']) ?>" class="btn btn-primary mt-3">Voir ce Pokédex</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($pokedexList)): ?>
            <p class="text-center">Aucun Pokédex disponible.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
