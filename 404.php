<?php
http_response_code(404);

// Charger la session et les traductions si possible
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$appLang = $_SESSION['preferred_language'] ?? 'fr';

$labels = [
    'fr' => [
        'title'    => 'Page introuvable',
        'code'     => '404',
        'heading'  => 'Oups, cette page n\'existe pas.',
        'body'     => 'La page que vous cherchez a peut-être été déplacée, supprimée ou n\'a jamais existé.',
        'btn_home' => 'Retour à l\'accueil',
        'btn_dex'  => 'Voir mes Pokédex',
    ],
    'en' => [
        'title'    => 'Page not found',
        'code'     => '404',
        'heading'  => 'Oops, this page doesn\'t exist.',
        'body'     => 'The page you\'re looking for may have been moved, deleted, or never existed.',
        'btn_home' => 'Back to home',
        'btn_dex'  => 'View my Pokédex',
    ],
    'de' => [
        'title'    => 'Seite nicht gefunden',
        'code'     => '404',
        'heading'  => 'Hoppla, diese Seite existiert nicht.',
        'body'     => 'Die gesuchte Seite wurde möglicherweise verschoben, gelöscht oder hat nie existiert.',
        'btn_home' => 'Zurück zur Startseite',
        'btn_dex'  => 'Meine Pokédex ansehen',
    ],
    'es' => [
        'title'    => 'Página no encontrada',
        'code'     => '404',
        'heading'  => 'Vaya, esta página no existe.',
        'body'     => 'La página que buscas puede haber sido movida, eliminada o nunca existió.',
        'btn_home' => 'Volver al inicio',
        'btn_dex'  => 'Ver mis Pokédex',
    ],
];

$l = $labels[$appLang] ?? $labels['fr'];
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!doctype html>
<html lang="<?= $appLang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $l['title'] ?> — Mon Pokédex</title>
    <link rel="icon" href="/img/favicon/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/img/favicon/favicon.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="/img/favicon/favicon-96x96.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/img/favicon/apple-touch-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <style>
        body {
            background: #f0f4f8;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .error-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }
        .error-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 32px rgba(0,0,0,.09);
            padding: 3rem 2.5rem;
            text-align: center;
            max-width: 480px;
            width: 100%;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #dc2626 0%, #f97316 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: .5rem;
        }
        .error-pokeball {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            animation: wobble 2s ease-in-out infinite;
        }
        @keyframes wobble {
            0%, 100% { transform: rotate(-8deg); }
            50%       { transform: rotate(8deg); }
        }
        .error-heading {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: .75rem;
        }
        .error-body {
            color: #64748b;
            font-size: .95rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .error-actions {
            display: flex;
            gap: .75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="/index.php">
            <img src="/img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;">
        </a>
    </div>
</nav>

<div class="error-wrap">
    <div class="error-card">
        <div class="error-pokeball">⬤</div>
        <div class="error-code"><?= $l['code'] ?></div>
        <div class="error-heading"><?= htmlspecialchars($l['heading']) ?></div>
        <p class="error-body"><?= htmlspecialchars($l['body']) ?></p>
        <div class="error-actions">
            <a href="/index.php" class="btn btn-danger fw-semibold px-4">
                <?= htmlspecialchars($l['btn_home']) ?>
            </a>
            <?php if ($isLoggedIn): ?>
            <a href="/index.php" class="btn btn-outline-secondary fw-semibold px-4">
                <?= htmlspecialchars($l['btn_dex']) ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
