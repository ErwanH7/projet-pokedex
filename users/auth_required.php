<?php
if (!isset($_SESSION['user_id'])) {
    $current_script = basename($_SERVER['SCRIPT_FILENAME']);
    $public_pages   = ['login.php', 'register.php', 'index.php'];
    if (!in_array($current_script, $public_pages, true)) {
        header('Location: /users/login.php');
        exit;
    }
}
