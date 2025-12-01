<?php
require_once 'config/pdo.php';
require_once 'config/constantesPDO.php';
require_once 'auth_required.php';

$pdo = DB::getPDO();
$cfg = ConstantesPDO::getInstance()->getConfig();
$userSalt = $cfg['login']['user']['salt'] ?? '';
$adminSalt = $cfg['login']['admin']['salt'] ?? '';

$userID = $_SESSION['user_id'];
$msg = '';
$errors = [];

// Récupérer user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable.");
}

// Mise à jour du profil
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

        // vérifier ancien mot de passe
        $salt = ($user['role'] === 'admin') ? $adminSalt : $userSalt;
        if (!password_verify($old . $salt, $user['password_hash'])) {
            $errors[] = "Mot de passe actuel incorrect.";
        } elseif (strlen($new) < 6) {
            $errors[] = "Nouveau mot de passe trop court.";
        } else {
            $newHash = password_hash($new . $salt, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $userID]);
            $msg = "Mot de passe changé.";
        }
    }
    // refetch user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userID]);
    $user = $stmt->fetch();
}
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Mon profil</title></head>
<body>
<h1>Profil</h1>
<?php if ($msg) echo "<p style='color:green'>$msg</p>"; ?>
<?php foreach ($errors as $e) echo "<p style='color:red'>$e</p>"; ?>

<form method="post">
  <h3>Informations</h3>
  <label>Email (login): <strong><?= htmlspecialchars($user['email']) ?></strong></label><br><br>
  <label>Nom (username) : <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>"></label><br><br>
  <label>Langue :
    <select name="preferred_language">
      <option value="fr" <?= $user['preferred_language'] === 'fr' ? 'selected' : '' ?>>Français</option>
      <option value="en" <?= $user['preferred_language'] === 'en' ? 'selected' : '' ?>>English</option>
      <option value="de" <?= $user['preferred_language'] === 'de' ? 'selected' : '' ?>>Deutsch</option>
    </select>
  </label><br><br>
  <button name="update_info" type="submit">Mettre à jour</button>
</form>

<hr>

<form method="post">
  <h3>Changer mot de passe</h3>
  <label>Mot de passe actuel: <input type="password" name="old_password" required></label><br><br>
  <label>Nouveau mot de passe: <input type="password" name="new_password" required></label><br><br>
  <button name="change_password" type="submit">Changer</button>
</form>

<p><a href="logout.php">Déconnexion</a></p>
</body>
</html>
