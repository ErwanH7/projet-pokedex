<?php
require_once 'auth_required.php'; // démarre session
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Interdit: accès administrateur seulement.";
    exit;
}