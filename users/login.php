<?php
require_once 'config/pdo.php';
require_once 'config/constantesPDO.php';
session_start();

$pdo = DB::getPDO();
$cfg = ConstantesPDO::getInstance()->getConfig();
$userSalt = $cfg['login']['user']['salt'] ?? '';
$adminSalt = $cfg['login']['admin']['salt'] ?? '';

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
            $salt = ($user['role'] === 'admin') ? $adminSalt : $userSalt;
            if (password_verify($password . $salt, $user['password_hash'])) {
                // succès
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                header('Location: index.php');
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
<head><meta charset="utf-8"><title>Connexion</title></head>
<body>
<h1>Connexion</h1>
<?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<form method="post">
  <label>Email:<br><input type="email" name="email" required></label><br><br>
  <label>Mot de passe:<br><input type="password" name="password" required></label><br><br>
  <button type="submit">Se connecter</button>
</form>
<p><a href="register.php">Créer un compte</a></p>
</body>
</html>
