<?php
/**
 * @brief Démarrage de la session PHP
 * 
 * Initialise la session si elle n'est pas déjà démarrée.
 * Permet de stocker les informations de l'utilisateur connecté.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
