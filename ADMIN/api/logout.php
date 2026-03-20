<?php
// ADMIN/api/logout.php
session_start();
header('Content-Type: application/json');

// Détruire la session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

sendJSON(true, 'Déconnexion réussie');
?>