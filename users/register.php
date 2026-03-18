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

// ── Validation pseudo ─────────────────────────────────────────────────────────
$FORBIDDEN_WORDS = [
    'admin','moderateur','staff','support','root','system','superuser',
    'hitler','nazi','nigger','nigga','pute','salope','connard','batard','fdp',
    'enculé','encule','merde','chier','bite','couille','con','cul',
];

function validate_username(string $username, array $forbiddenWords): ?string {
    if ($username === '') return null; // pseudo optionnel
    if (strlen($username) < 3)  return "Le pseudo doit faire au moins 3 caractères.";
    if (strlen($username) > 20) return "Le pseudo ne peut pas dépasser 20 caractères.";
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username))
        return "Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores (pas d'espaces ni de caractères spéciaux).";
    $lower = strtolower($username);
    foreach ($forbiddenWords as $word) {
        if (str_contains($lower, $word))
            return "Ce pseudo n'est pas autorisé.";
    }
    return null;
}

// ── Validation mot de passe ──────────────────────────────────────────────────
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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $lang     = in_array($_POST['preferred_language'] ?? 'fr', ['fr','en','de']) ? $_POST['preferred_language'] : 'fr';

    if ($email === '' || $password === '') {
        $errors[] = "Email et mot de passe requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    } else {
        // Valider le pseudo
        $usernameError = validate_username($username, $FORBIDDEN_WORDS);
        if ($usernameError !== null) $errors[] = $usernameError;

        // Valider le mot de passe
        $passwordError = validate_password($password);
        if ($passwordError !== null) $errors[] = $passwordError;

        if (empty($errors)) {
            $hash = password_hash($password . $userSalt, PASSWORD_BCRYPT);

            try {
                $stmt = $pdo->prepare("INSERT INTO users (email, username, preferred_language, password_hash) VALUES (?, ?, ?, ?)");
                $stmt->execute([$email, $username ?: null, $lang, $hash]);

                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['email']   = $email;
                $_SESSION['username'] = $username;
                $_SESSION['role']    = 'user';

                header('Location: ../index.php');
                exit;
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $errors[] = "Cet email est déjà utilisé.";
                } else {
                    error_log("Erreur inscription: " . $e->getMessage());
                    $errors[] = "Erreur serveur, veuillez réessayer.";
                }
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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
                           placeholder="Votre pseudo"
                           maxlength="20">
                    <div class="form-text">Lettres, chiffres, tirets et underscores uniquement. Pas d'espaces.</div>
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
                           placeholder="8 car. min, 1 maj, 1 min, 1 chiffre, 1 spécial" required>
                    <div class="form-text">8 caractères minimum · 1 majuscule · 1 minuscule · 1 chiffre · 1 caractère spécial (@, !, #, $…)</div>
                </div>
                <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
                    Créer mon compte
                </button>
            </form>

            <hr class="my-4">
            <p class="text-center mb-1">
                Déjà un compte ?
                <a href="login.php" class="text-danger fw-semibold">Se connecter</a>
            </p>
            <p class="text-center mb-0" style="font-size:.8rem">
                En créant un compte, vous acceptez notre
                <a href="../index.php#rgpdModal" data-bs-toggle="modal" data-bs-target="#rgpdModalReg" class="text-muted">politique de confidentialité (RGPD)</a>.
            </p>
        </div>
    </div>
</div>

<!-- Modal RGPD (copie légère pour la page d'inscription) -->
<div class="modal fade" id="rgpdModalReg" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Politique de confidentialité — RGPD</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Nous collectons votre email, pseudo (optionnel) et mot de passe (chiffré) pour faire fonctionner le service.
        Ces données ne sont ni revendues ni partagées. Vous pouvez demander leur suppression à tout moment via le support.</p>
        <p>Un cookie de session (HttpOnly, SameSite=Strict) est utilisé pour maintenir votre connexion. Aucun cookie publicitaire n'est déposé.</p>
        <p>Pour exercer vos droits RGPD (accès, rectification, suppression, portabilité), contactez-nous via le widget de support sur le site.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/utils.js"></script>
</body>
</html>
