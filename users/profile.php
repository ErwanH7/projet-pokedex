<?php
include_once '../include.php';

$pdo = DB::getPDO();
$cfg = ConstantesPDO::getInstance()->getConfig();
$userSalt  = $cfg['login']['user']['salt']  ?? '';
$adminSalt = $cfg['login']['admin']['salt'] ?? '';

$userID = $_SESSION['user_id'];
$msg    = '';
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();

if (!$user) {
    die(t('profile_error_not_found'));
}

// ── Validation pseudo ─────────────────────────────────────────────────────────
$FORBIDDEN_WORDS = [
    'admin','moderateur','staff','support','root','system','superuser',
    'hitler','nazi','nigger','nigga','pute','salope','connard','batard','fdp',
    'enculé','encule','merde','chier','bite','couille','con','cul',
];

function validate_username(string $username, array $forbiddenWords): ?string {
    if ($username === '') return null;
    if (strlen($username) < 3)  return "Le pseudo doit faire au moins 3 caractères.";
    if (strlen($username) > 20) return "Le pseudo ne peut pas dépasser 20 caractères.";
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username))
        return "Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores.";
    $lower = strtolower($username);
    foreach ($forbiddenWords as $word) {
        if (str_contains($lower, $word)) return "Ce pseudo n'est pas autorisé.";
    }
    return null;
}

function validate_password(string $password): ?string {
    if (strlen($password) < 8)
        return "Le mot de passe doit contenir au moins 8 caractères.";
    if (!preg_match('/[A-Z]/', $password))
        return "Le mot de passe doit contenir au moins une majuscule.";
    if (!preg_match('/[a-z]/', $password))
        return "Le mot de passe doit contenir au moins une minuscule.";
    if (!preg_match('/[0-9]/', $password))
        return "Le mot de passe doit contenir au moins un chiffre.";
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:\'",.<>?\/\\\\|`~]/', $password))
        return "Le mot de passe doit contenir au moins un caractère spécial (!@#\$%…).";
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['update_info'])) {
        $username = trim($_POST['username'] ?? '');
        $lang     = in_array($_POST['preferred_language'] ?? 'fr', ['fr','en','de','es']) ? $_POST['preferred_language'] : 'fr';

        $usernameError = validate_username($username, $FORBIDDEN_WORDS);
        if ($usernameError !== null) {
            $errors[] = $usernameError;
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, preferred_language = ? WHERE id = ?");
            $stmt->execute([$username ?: null, $lang, $userID]);
            $_SESSION['username']           = $username;
            $_SESSION['preferred_language'] = $lang;
            $appLang = $lang; // Appliquer immédiatement pour cette requête
            $msg = t('profile_success_info');
        }
    }

    if (isset($_POST['change_password'])) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';

        $salt = ($user['role'] === 'admin') ? $adminSalt : $userSalt;
        if (!password_verify($old . $salt, $user['password_hash'])) {
            $errors[] = "Mot de passe actuel incorrect.";
        } else {
            $pwError = validate_password($new);
            if ($pwError !== null) {
                $errors[] = $pwError;
            } else {
                $newHash = password_hash($new . $salt, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $userID]);
                $msg = t('profile_success_password');
            }
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userID]);
    $user = $stmt->fetch();
}
?>
<!doctype html>
<html lang="<?= $appLang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('nav_profile') ?> - Mon Pokédex</title>
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
                <li class="nav-item"><a class="nav-link active" href="profile.php"><?= t('nav_profile') ?></a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="../admin/admin_import.php"><?= t('nav_admin') ?></a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="logout.php"><?= t('nav_logout') ?></a></li>
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
        <div class="card-header fw-semibold"><?= t('profile_personal_info') ?></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= t('profile_email_label') ?></label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold"><?= t('profile_username_label') ?></label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                           placeholder="<?= t('profile_username_label') ?>"
                           maxlength="20">
                    <div class="form-text"><?= t('profile_username_hint') ?></div>
                </div>
                <div class="mb-3">
                    <label for="preferred_language" class="form-label fw-semibold"><?= t('profile_lang_label') ?></label>
                    <select class="form-select" id="preferred_language" name="preferred_language">
                        <option value="fr" <?= $user['preferred_language'] === 'fr' ? 'selected' : '' ?>>Français</option>
                        <option value="en" <?= $user['preferred_language'] === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="de" <?= $user['preferred_language'] === 'de' ? 'selected' : '' ?>>Deutsch</option>
                        <option value="es" <?= $user['preferred_language'] === 'es' ? 'selected' : '' ?>>Español</option>
                    </select>
                </div>
                <button name="update_info" type="submit" class="btn btn-primary"><?= t('profile_update_btn') ?></button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold"><?= t('profile_change_password_section') ?></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3">
                    <label for="old_password" class="form-label fw-semibold"><?= t('profile_old_password_label') ?></label>
                    <input type="password" class="form-control" id="old_password" name="old_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label fw-semibold"><?= t('profile_new_password_label') ?></label>
                    <input type="password" class="form-control" id="new_password" name="new_password"
                           placeholder="<?= t('profile_new_password_hint') ?>" required>
                    <div class="form-text"><?= t('profile_new_password_hint_long') ?></div>
                </div>
                <button name="change_password" type="submit" class="btn btn-warning"><?= t('profile_change_password_btn') ?></button>
            </form>
        </div>
    </div>

    <a href="logout.php" class="btn btn-outline-danger"><?= t('profile_logout_btn') ?></a>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/utils.js"></script>
</body>
</html>
