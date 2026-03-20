<?php
// API/profil/retards.php
session_start();
header('Content-Type: application/json');
require_once '../connexion/db.php';

if (!isLoggedIn()) {
    sendJSON(false, 'Non connecté');
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : getCurrentUserId();
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur DB');
}

try {
    $heureLimite = fetchValue($pdo, "
        SELECT h.heure 
        FROM heure h
        JOIN agent a ON a.id_superviseur = h.id_superviseur
        WHERE a.id = ?
    ", [$userId]) ?: '07:00:00';
    
    $retards = fetchAll($pdo, "
        SELECT r.*, TIMESTAMPDIFF(MINUTE, ?, r.temps) as minutes_retard
        FROM retard r
        WHERE r.id_agent = ? AND MONTH(r.temps) = ? AND YEAR(r.temps) = ?
        ORDER BY r.temps DESC
    ", [$heureLimite, $userId, $month, $year]);
    
    $totalMinutes = array_sum(array_column($retards, 'minutes_retard'));
    
    sendJSON(true, 'Succès', [
        'total' => count($retards),
        'minutes' => $totalMinutes,
        'heure_limite' => $heureLimite,
        'retards' => $retards
    ]);
} catch (Exception $e) {
    sendJSON(false, $e->getMessage());
}
?>