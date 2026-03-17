<?php
include_once '../include.php';

// Si déjà connecté, rediriger selon le rôle
if (isset($_SESSION['user_id'])) {
    $redirect = (($_SESSION['role'] ?? '') === 'admin') ? '../admin/import_pokeapi.php' : '../index.php';
    header('Location: ' . $redirect);
    exit;
}

$pdo = DB::getPDO();
$cfg = ConstantesPDO::getInstance()->getConfig();
$userSalt = $cfg['login']['user']['salt'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Email et mot de passe requis.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Compte introuvable.";
        } else {
            if (password_verify($password . $userSalt, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                $redirect = ($user['role'] === 'admin') ? '../admin/admin_import.php' : '../index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = "Mot de passe incorrect.";
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
    <title>Connexion - Mon Pokédex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="../index.php"><img src="../img/logo_pokedex.svg" alt="Mon Pokédex" style="height:72px;"></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto gap-1">
                <li class="nav-item"><a class="nav-link active" href="login.php">Connexion</a></li>
                <li class="nav-item"><a class="nav-link" href="register.php">Inscription</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-icon">⬤</div>
            <h2>Connexion</h2>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Adresse email</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="exemple@email.com" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Votre mot de passe" required>
                </div>
                <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
                    Se connecter
                </button>
            </form>

            <hr class="my-4">
            <p class="text-center mb-0">
                Pas encore de compte ?
                <a href="register.php" class="text-danger fw-semibold">Créer un compte</a>
            </p>
        </div>
    </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
