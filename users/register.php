<?php
require_once 'config/pdo.php';
require_once 'config/constantesPDO.php';
session_start();

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
        // Hash
        $hash = password_hash($password . $userSalt, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, username, preferred_language, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $username ?: null, $lang, $hash]);

            // Connexion auto
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['email'] = $email;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';

            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            // Duplicate email
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
<head><meta charset="utf-8"><title>Inscription</title></head>
<body>
<h1>Créer un compte</h1>
<?php foreach ($errors as $err): ?>
  <p style="color:red"><?= htmlspecialchars($err) ?></p>
<?php endforeach; ?>

<form method="post">
  <label>Email:<br><input type="email" name="email" required></label><br><br>
  <label>Nom (optionnel):<br><input type="text" name="username"></label><br><br>
  <label>Langue préférée:
    <select name="preferred_language">
      <option value="fr">Français</option>
      <option value="en">English</option>
      <option value="de">Deutsch</option>
    </select>
  </label><br><br>
  <label>Mot de passe:<br><input type="password" name="password" required></label><br><br>
  <button type="submit">S'inscrire</button>
</form>
<p><a href="login.php">J'ai déjà un compte</a></p>
</body>
</html>
