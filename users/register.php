<?php
include_once '../include.php';

// Si déjà connecté, rediriger vers l'accueil
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$pdo = DB::getPDO();
$cfg = ConstantesPDO::getInstance()->getConfig();
$userSalt = $cfg['login']['user']['salt'] ?? '';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $lang = in_array($_POST['preferred_language'] ?? 'fr', ['fr','en','de']) ? $_POST['preferred_language'] : 'fr';

    if ($email === '' || $password === '') {
        $errors[] = "Email et mot de passe requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    } else {
        $hash = password_hash($password . $userSalt, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, username, preferred_language, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $username ?: null, $lang, $hash]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['email'] = $email;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';

            header('Location: ../index.php');
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $errors[] = "Cet email est déjà utilisé.";
            } else {
                $errors[] = "Erreur serveur: " . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscription - Mon Pokédex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="../index.php"><img src="../img/logo_pokedex.svg" alt="Mon Pokédex" style="height:72px;"></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto gap-1">
                <li class="nav-item"><a class="nav-link" href="login.php">Connexion</a></li>
                <li class="nav-item"><a class="nav-link active" href="register.php">Inscription</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-icon">★</div>
            <h2>Créer un compte</h2>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Adresse email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="exemple@email.com" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold">Pseudo <span class="text-muted fw-normal">(optionnel)</span></label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="Votre pseudo de Dresseur">
                </div>
                <div class="mb-3">
                    <label for="preferred_language" class="form-label fw-semibold">Langue préférée</label>
                    <select class="form-select" id="preferred_language" name="preferred_language">
                        <option value="fr" <?= ($_POST['preferred_language'] ?? 'fr') === 'fr' ? 'selected' : '' ?>>Français</option>
                        <option value="en" <?= ($_POST['preferred_language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="de" <?= ($_POST['preferred_language'] ?? '') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Mot de passe <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Minimum 6 caractères" required>
                </div>
                <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
                    Créer mon compte
                </button>
            </form>

            <hr class="my-4">
            <p class="text-center mb-0">
                Déjà un compte ?
                <a href="login.php" class="text-danger fw-semibold">Se connecter</a>
            </p>
        </div>
    </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
