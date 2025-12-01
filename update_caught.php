<?php
require_once 'config/pdo.php';
require_once 'config/constantesPDO.php';
$pdo = DB::getPDO();
session_start();

$userID = $_SESSION['user_id'] ?? 1;

$pokemonID = $_POST['pokemon_id'];
$pokedexID = $_POST['pokedex_id'];
$caught = $_POST['caught'];

// Vérifier si existe déjà
$stmt = $pdo->prepare("
    SELECT * FROM user_progress 
    WHERE user_id = ? AND pokedex_id = ? AND pokemon_id = ?
");
$stmt->execute([$userID, $pokedexID, $pokemonID]);

if ($stmt->fetch()) {

    // Update
    $upd = $pdo->prepare("
        UPDATE user_progress 
        SET caught = ?
        WHERE user_id = ? AND pokedex_id = ? AND pokemon_id = ?
    ");
    $upd->execute([$caught, $userID, $pokedexID, $pokemonID]);

} else {

    // Insert
    $ins = $pdo->prepare("
        INSERT INTO user_progress (user_id, pokedex_id, pokemon_id, caught)
        VALUES (?, ?, ?, ?)
    ");
    $ins->execute([$userID, $pokedexID, $pokemonID, $caught]);
}

echo "OK";
