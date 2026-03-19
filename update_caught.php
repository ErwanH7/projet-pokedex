<?php
include_once 'include.php';
header('Content-Type: application/json');

// Vérification CSRF
csrf_verify();

$userID    = $_SESSION['user_id'] ?? null;
$pokemonID = trim($_POST['pokemon_id'] ?? '');
$pokedexID = (int)($_POST['pokedex_id'] ?? 0);
$caught    = isset($_POST['caught']) ? (int)$_POST['caught'] : null;
$shiny     = isset($_POST['shiny'])  ? (int)$_POST['shiny']  : null;
$alpha     = isset($_POST['alpha'])  ? (int)$_POST['alpha']  : null;

if (!$userID || !$pokemonID || !$pokedexID || ($caught === null && $shiny === null && $alpha === null)) {
    echo json_encode(['ok' => false, 'error' => "Paramètres manquants"]);
    exit;
}

// Valider le format de pokemon_id : digits optionnellement suivis de _alphanum
if (!preg_match('/^\d+(_[a-z0-9_\-]+)?$/i', $pokemonID)) {
    echo json_encode(['ok' => false, 'error' => "Identifiant de forme invalide"]);
    exit;
}

// Limiter caught, shiny et alpha à 0 ou 1
if ($caught !== null) $caught = $caught ? 1 : 0;
if ($shiny  !== null) $shiny  = $shiny  ? 1 : 0;
if ($alpha  !== null) $alpha  = $alpha  ? 1 : 0;

try {
    $pdo = DB::getPDO();

    // Vérifier que l'utilisateur existe vraiment en BDD (session périmée possible)
    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmtUser->execute([$userID]);
    if (!$stmtUser->fetchColumn()) {
        session_destroy();
        echo json_encode(['ok' => false, 'error' => 'SESSION_EXPIRED']);
        exit;
    }

    // S'assurer que la forme existe dans pokemon_forms (au cas où FK active)
    $stmtFormCheck = $pdo->prepare("SELECT id FROM pokemon_forms WHERE id = ?");
    $stmtFormCheck->execute([$pokemonID]);
    if (!$stmtFormCheck->fetchColumn()) {
        // Tenter de créer la forme de base manquante si l'espèce existe
        $speciesID = (int)explode('_', $pokemonID)[0];
        $stmtSpecies = $pdo->prepare("SELECT id FROM pokemon WHERE id = ?");
        $stmtSpecies->execute([$speciesID]);
        if ($stmtSpecies->fetchColumn()) {
            $formCode = strpos($pokemonID, '_') !== false ? explode('_', $pokemonID, 2)[1] : 'base';
            $pdo->prepare("INSERT IGNORE INTO pokemon_forms (id, pokemon_id, form_code) VALUES (?, ?, ?)")
                ->execute([$pokemonID, $speciesID, $formCode]);
        } else {
            echo json_encode(['ok' => false, 'error' => "Pokémon introuvable : $pokemonID"]);
            exit;
        }
    }

    // Upsert en une seule requête selon le champ modifié
    if ($caught !== null) {
        $pdo->prepare("
            INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny)
            VALUES (?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE caught = VALUES(caught)
        ")->execute([$userID, $pokedexID, $pokemonID, $caught]);
    }

    if ($shiny !== null) {
        $pdo->prepare("
            INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny)
            VALUES (?, ?, ?, 0, ?)
            ON DUPLICATE KEY UPDATE shiny = VALUES(shiny)
        ")->execute([$userID, $pokedexID, $pokemonID, $shiny]);
    }

    if ($alpha !== null) {
        $pdo->prepare("
            INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny, alpha)
            VALUES (?, ?, ?, 0, 0, ?)
            ON DUPLICATE KEY UPDATE alpha = VALUES(alpha)
        ")->execute([$userID, $pokedexID, $pokemonID, $alpha]);
    }

    // Sync vers le Pokédex National (sens unique : régional → national seulement)
    $stmtCode = $pdo->prepare("SELECT code FROM pokedex_list WHERE id = ?");
    $stmtCode->execute([$pokedexID]);
    $currentCode = $stmtCode->fetchColumn();

    $nationalCodes = ['NATIONAL', 'NATIONALDEX', 'NATIONAL_DEX', 'NAT', 'NATDEX'];
    if ($currentCode && !in_array(strtoupper($currentCode), $nationalCodes)) {
        $placeholders = implode(',', array_fill(0, count($nationalCodes), '?'));
        $stmtNat = $pdo->prepare("SELECT id FROM pokedex_list WHERE UPPER(code) IN ($placeholders)");
        $stmtNat->execute($nationalCodes);
        $nationalID = $stmtNat->fetchColumn();

        if ($nationalID) {
            // Sync National : si on coche → toujours cocher.
            // Si on décoche → décocher seulement si le pokémon n'est coché dans aucun autre dex.
            if ($caught !== null) {
                if ($caught === 1) {
                    $pdo->prepare("
                        INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny)
                        VALUES (?, ?, ?, 1, 0)
                        ON DUPLICATE KEY UPDATE caught = 1
                    ")->execute([$userID, $nationalID, $pokemonID]);
                } else {
                    $stmtCheck = $pdo->prepare("
                        SELECT COUNT(*) FROM user_progress
                        WHERE user_id = ? AND pokemon_id = ?
                          AND pokedex_id NOT IN (?, ?) AND caught = 1
                    ");
                    $stmtCheck->execute([$userID, $pokemonID, $pokedexID, $nationalID]);
                    if ((int)$stmtCheck->fetchColumn() === 0) {
                        $pdo->prepare("
                            INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny)
                            VALUES (?, ?, ?, 0, 0)
                            ON DUPLICATE KEY UPDATE caught = 0
                        ")->execute([$userID, $nationalID, $pokemonID]);
                    }
                }
            }

            if ($shiny !== null) {
                if ($shiny === 1) {
                    $pdo->prepare("
                        INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny)
                        VALUES (?, ?, ?, 0, 1)
                        ON DUPLICATE KEY UPDATE shiny = 1
                    ")->execute([$userID, $nationalID, $pokemonID]);
                } else {
                    $stmtCheck = $pdo->prepare("
                        SELECT COUNT(*) FROM user_progress
                        WHERE user_id = ? AND pokemon_id = ?
                          AND pokedex_id NOT IN (?, ?) AND shiny = 1
                    ");
                    $stmtCheck->execute([$userID, $pokemonID, $pokedexID, $nationalID]);
                    if ((int)$stmtCheck->fetchColumn() === 0) {
                        $pdo->prepare("
                            INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny)
                            VALUES (?, ?, ?, 0, 0)
                            ON DUPLICATE KEY UPDATE shiny = 0
                        ")->execute([$userID, $nationalID, $pokemonID]);
                    }
                }
            }
        }
    }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    error_log("Erreur update_caught: " . $e->getMessage());
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
    echo json_encode(['ok' => false, 'error' => $isLocal ? $e->getMessage() : 'Erreur serveur.']);
}
