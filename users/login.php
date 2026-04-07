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
$userSalt  = $cfg['login']['user']['salt']  ?? '';
$adminSalt = $cfg['login']['admin']['salt'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = t('error_email_password_required');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = t('error_account_not_found');
        } else {
            $salt = ($user['role'] === 'admin') ? $adminSalt : $userSalt;
            if (password_verify($password . $salt, $user['password_hash'])) {
                $_SESSION['user_id']            = $user['id'];
                $_SESSION['email']              = $user['email'];
                $_SESSION['username']           = $user['username'];
                $_SESSION['role']               = $user['role'];
                $_SESSION['preferred_language'] = $user['preferred_language'] ?? 'fr';

                $redirect = ($user['role'] === 'admin') ? '../admin/admin_import.php' : '../index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = t('error_wrong_password');
            }
        }
    }
}
?>
<!doctype html>
<html lang="<?= $appLang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('login_title') ?> - Mon Pokédex</title>
    <link rel="canonical" href="https://projet-pokedex.erwanhoarau.com/users/login.php">
    <link rel="icon" href="/img/favicon/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/img/favicon/favicon.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="/img/favicon/favicon-96x96.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/img/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/img/favicon/site.webmanifest">
    <meta name="description" content="Connecte-toi à Mon Pokédex pour suivre ta progression.">
    <meta property="og:title" content="Connexion - Mon Pokédex">
    <meta property="og:description" content="Connecte-toi à Mon Pokédex pour suivre ta progression.">
    <meta property="og:image" content="<?= htmlspecialchars($baseUrl) ?>/img/logo_pokedex.png">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="<?= htmlspecialchars($baseUrl) ?>/img/logo_pokedex.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="../index.php"><img src="../img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu" aria-controls="navbarMenu" aria-expanded="false" aria-label="<?= t('nav_menu_label') ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav ms-auto gap-1">
                <li class="nav-item"><a class="nav-link active" href="login.php"><?= t('nav_login') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="register.php"><?= t('nav_register') ?></a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-icon">⬤</div>
            <h2><?= t('login_title') ?></h2>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold"><?= t('login_email_label') ?></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="<?= t('login_email_placeholder') ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold"><?= t('login_password_label') ?></label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="<?= t('login_password_placeholder') ?>" required>
                </div>
                <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
                    <?= t('login_submit_btn') ?>
                </button>
            </form>

            <hr class="my-4">
            <p class="text-center mb-0">
                <?= t('login_no_account') ?>
                <a href="register.php" class="text-danger fw-semibold"><?= t('login_create_link') ?></a>
            </p>
        </div>
    </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/utils.js"></script>
</body>
</html>
