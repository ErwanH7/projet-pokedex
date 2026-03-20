<?php
/**
 * @brief Démarrage de la session PHP
 *
 * Initialise la session si elle n'est pas déjà démarrée.
 * Permet de stocker les informations de l'utilisateur connecté.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',   // Strict bloque les cookies lors des redirections HTTP→HTTPS (Hostinger)
    ]);
    session_start();
}

// ── En-têtes de sécurité HTTP ────────────────────────────────────────────────
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── Protection CSRF ──────────────────────────────────────────────────────────

/**
 * Retourne le token CSRF de la session, le crée si absent.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF (POST body ou header X-CSRF-Token).
 * Termine la requête avec 403 si invalide.
 */
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Token CSRF invalide.']));
    }
}

/**
 * @brief Inclusion des fichiers de configuration
 * 
 * Charge les configurations nécessaires pour la connexion à la base de données
 * et d'autres constantes globales utilisées dans l'application.
 */

/**
 * @brief Inclusion du fichier de connexion à la base de données
 */
require_once 'config/pdo.php';

/**
 * @brief Inclusion du fichier des constantes de configuration
 */
require_once 'config/constantesPDO.php';

/**
 * @brief Inclusion du fichier d'authentification
 * 
 * Vérifie si l'utilisateur est authentifié pour accéder aux pages protégées.
 */
require_once 'users/auth_required.php';

// ── Langue de l'application ───────────────────────────────────────────────────
$appLang = $_SESSION['preferred_language'] ?? 'fr';
require_once __DIR__ . '/lang/translations.php';

/**
 * Retourne le nom d'un Pokédex dans la langue de l'utilisateur.
 * Attend un tableau avec au moins 'name' (fr), optionnellement 'name_en', 'name_de'.
 */
function dex_name(array $dex): string
{
    global $appLang;
    if ($appLang !== 'fr') {
        $col = "name_{$appLang}";
        if (!empty($dex[$col])) return $dex[$col];
    }
    return $dex['name'] ?? '';
}

// ── URL de base (utilisée pour les meta og:image) ────────────────────────────
$_proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $_proto . '://' . $_SERVER['HTTP_HOST'];
