<?php
include_once 'include.php';
$pdo = DB::getPDO();

header('Content-Type: application/json');

// Vérification CSRF
csrf_verify();

$userID    = $_SESSION['user_id'] ?? null;
$pokemonID = trim($_POST['pokemon_id'] ?? '');
$pokedexID = (int)($_POST['pokedex_id'] ?? 0);
$caught    = isset($_POST['caught']) ? (int)$_POST['caught'] : null;
$shiny     = isset($_POST['shiny'])  ? (int)$_POST['shiny']  : null;

if (!$userID || !$pokemonID || !$pokedexID) {
    echo json_encode(['ok' => false, 'error' => "Paramètres manquants"]);
    exit;
}

// Valider le format de pokemon_id : digits optionnellement suivis de _alphanum
if (!preg_match('/^\d+(_[a-z0-9\-]+)?$/i', $pokemonID)) {
    echo json_encode(['ok' => false, 'error' => "Identifiant de forme invalide"]);
    exit;
}

// Limiter caught et shiny à 0 ou 1
if ($caught !== null) $caught = $caught ? 1 : 0;
if ($shiny  !== null) $shiny  = $shiny  ? 1 : 0;

// Vérifier que l'utilisateur existe vraiment en BDD (session périmée possible)
$stmtUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmtUser->execute([$userID]);
if (!$stmtUser->fetchColumn()) {
    // Session invalide : forcer la déconnexion
    session_destroy();
    echo json_encode(['ok' => false, 'error' => 'SESSION_EXPIRED']);
    exit;
}

try {
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
            // Sync direct, pas besoin que le pokemon soit dans pokedex_entries du national
            if ($caught !== null) {
                $pdo->prepare("
                    INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny)
                    VALUES (?, ?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE caught = VALUES(caught)
                ")->execute([$userID, $nationalID, $pokemonID, $caught]);
            }
            if ($shiny !== null) {
                $pdo->prepare("
                    INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught, shiny)
                    VALUES (?, ?, ?, 0, ?)
                    ON DUPLICATE KEY UPDATE shiny = VALUES(shiny)
                ")->execute([$userID, $nationalID, $pokemonID, $shiny]);
            }
        }
    }

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {
    error_log("Erreur update_caught: " . $e->getMessage());
    // En dev local on peut voir l'erreur réelle, en prod on masque
    $detail = (($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1') ? $e->getMessage() : 'Erreur serveur.';
    echo json_encode(['ok' => false, 'error' => $detail]);
}
