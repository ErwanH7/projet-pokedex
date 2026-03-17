<?php
if (!isset($_SESSION['user_id'])) {
    $current_script = basename($_SERVER['SCRIPT_FILENAME']);
    if ($current_script !== 'login.php' && $current_script !== 'register.php') {
        header('Location: /users/login.php');
        exit;
    }
}
