<?php
declare(strict_types=1);
require_once __DIR__ . '/../users/admin_required.php';
require_once __DIR__ . '/../config/constantesPDO.php';

$config = ConstantesPDO::getInstance()->getConfig();
$dbCfg  = $config['db'] ?? null;
$pdo    = new PDO(
    "mysql:host={$dbCfg['host']};dbname={$dbCfg['dbname']};charset=utf8mb4",
    $dbCfg['username'], $dbCfg['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$currentUserID = $_SESSION['user_id'];
$msg   = '';
$error = '';

// ── Suppression ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $deleteID = (int)$_POST['delete_user_id'];
    if ($deleteID === $currentUserID) {
        $error = 'Vous ne pouvez pas supprimer votre propre compte.';
    } else {
        // Supprimer la progression et le compte
        $pdo->prepare("DELETE FROM user_progress WHERE user_id = ?")->execute([$deleteID]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$deleteID]);
        $msg = "Utilisateur #$deleteID supprimé avec succès.";
    }
}

// ── Liste des utilisateurs ────────────────────────────────────────────────────
$users = $pdo->query("
    SELECT u.id, u.email, u.username, u.role, u.preferred_language, u.created_at,
           COUNT(DISTINCT up.pokemon_id) AS nb_catches
    FROM users u
    LEFT JOIN user_progress up ON up.user_id = u.id AND up.caught = 1
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin – Utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .role-admin { background:#fee2e2; color:#991b1b; font-weight:700; }
        .role-user  { background:#f1f5f9; color:#475569; }
        .lang-badge { font-size:.7rem; font-weight:700; padding:2px 7px; border-radius:4px; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php">
            <img src="../img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;">
        </a>
        <span class="navbar-text text-white">Administration</span>
        <a class="btn btn-outline-light btn-sm" href="admin_import.php">← Accueil admin</a>
    </div>
</nav>

<div class="container" style="max-width:1000px">
    <h1 class="mb-1">Gestion des utilisateurs</h1>
    <p class="text-muted mb-4"><?= count($users) ?> compte<?= count($users) > 1 ? 's' : '' ?> enregistré<?= count($users) > 1 ? 's' : '' ?>.</p>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Email</th>
                        <th>Pseudo</th>
                        <th>Rôle</th>
                        <th>Langue</th>
                        <th>Captures</th>
                        <th>Inscrit le</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="<?= $u['id'] === $currentUserID ? 'table-warning' : '' ?>">
                        <td class="text-secondary fw-bold"><?= $u['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($u['email']) ?>
                            <?php if ($u['id'] === $currentUserID): ?>
                                <span class="badge bg-warning text-dark ms-1">vous</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['username'] ? htmlspecialchars($u['username']) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                                <?= htmlspecialchars($u['role']) ?>
                            </span>
                        </td>
                        <td><span class="lang-badge" style="background:#e2e8f0;color:#334155"><?= strtoupper(htmlspecialchars($u['preferred_language'] ?? 'fr')) ?></span></td>
                        <td><?= (int)$u['nb_catches'] ?></td>
                        <td class="text-muted small"><?= $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?></td>
                        <td>
                            <?php if ($u['id'] !== $currentUserID): ?>
                                <button class="btn btn-outline-danger btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#confirmModal"
                                        data-id="<?= $u['id'] ?>"
                                        data-email="<?= htmlspecialchars($u['email']) ?>">
                                    Supprimer
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de confirmation -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-danger">Supprimer un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Vous êtes sur le point de supprimer le compte :</p>
                <p class="fw-bold fs-5" id="modalEmail"></p>
                <p class="text-muted small">Cette action supprimera également toute sa progression Pokédex. Elle est <strong>irréversible</strong>.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="delete_user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger fw-semibold">Supprimer définitivement</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('confirmModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modalEmail').textContent  = btn.dataset.email;
    document.getElementById('deleteUserId').value      = btn.dataset.id;
});
</script>
</body>
</html>
