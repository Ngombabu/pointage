<?php
// SCAN/api/check_session.php
session_start();
header('Content-Type: application/json');

$response = [
    'success' => true,
    'logged_in' => isset($_SESSION['superviseur_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
];

echo json_encode($response);
?>