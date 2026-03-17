<?php
include_once '../include.php';

$pdo = DB::getPDO();
$cfg = ConstantesPDO::getInstance()->getConfig();
$userSalt = $cfg['login']['user']['salt'] ?? '';
$adminSalt = $cfg['login']['admin']['salt'] ?? '';

$userID = $_SESSION['user_id'];
$msg = '';
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        $username = trim($_POST['username'] ?? '');
        $lang = in_array($_POST['preferred_language'] ?? 'fr', ['fr','en','de']) ? $_POST['preferred_language'] : 'fr';

        $stmt = $pdo->prepare("UPDATE users SET username = ?, preferred_language = ? WHERE id = ?");
        $stmt->execute([$username ?: null, $lang, $userID]);

        $_SESSION['username'] = $username;
        $msg = "Profil mis à jour.";
    }

    if (isset($_POST['change_password'])) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';

        $salt = ($user['role'] === 'admin') ? $adminSalt : $userSalt;
        if (!password_verify($old . $salt, $user['password_hash'])) {
            $errors[] = "Mot de passe actuel incorrect.";
        } elseif (strlen($new) < 6) {
            $errors[] = "Nouveau mot de passe trop court (minimum 6 caractères).";
        } else {
            $newHash = password_hash($new . $salt, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $userID]);
            $msg = "Mot de passe changé avec succès.";
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userID]);
    $user = $stmt->fetch();
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon profil - Mon Pokédex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="../index.php"><img src="../img/logo_pokedex.svg" alt="Mon Pokédex" style="height:72px;"></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto gap-1">
                <li class="nav-item"><a class="nav-link active" href="profile.php">Profil</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="../admin/admin_import.php">Admin</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="logout.php">Déconnexion</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="profile-wrap">
    <div class="profile-header">
        <div class="profile-avatar">👤</div>
        <div class="fw-800" style="font-size:1.2rem;font-weight:800"><?= htmlspecialchars($user['username'] ?? $user['email']) ?></div>
        <div style="font-size:.8rem;opacity:.7;margin-top:.2rem"><?= htmlspecialchars($user['email']) ?></div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Informations personnelles</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email (identifiant)</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold">Pseudo</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                           placeholder="Votre pseudo de Dresseur">
                </div>
                <div class="mb-3">
                    <label for="preferred_language" class="form-label fw-semibold">Langue préférée</label>
                    <select class="form-select" id="preferred_language" name="preferred_language">
                        <option value="fr" <?= $user['preferred_language'] === 'fr' ? 'selected' : '' ?>>Français</option>
                        <option value="en" <?= $user['preferred_language'] === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="de" <?= $user['preferred_language'] === 'de' ? 'selected' : '' ?>>Deutsch</option>
                    </select>
                </div>
                <button name="update_info" type="submit" class="btn btn-primary">Mettre à jour</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Changer le mot de passe</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label for="old_password" class="form-label fw-semibold">Mot de passe actuel</label>
                    <input type="password" class="form-control" id="old_password" name="old_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label fw-semibold">Nouveau mot de passe</label>
                    <input type="password" class="form-control" id="new_password" name="new_password"
                           placeholder="Minimum 6 caractères" required>
                </div>
                <button name="change_password" type="submit" class="btn btn-warning">Changer le mot de passe</button>
            </form>
        </div>
    </div>

    <a href="logout.php" class="btn btn-outline-danger">Se déconnecter</a>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
