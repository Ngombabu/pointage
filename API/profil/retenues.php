<?php
// API/profil/retenues.php
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
    $retenues = fetchAll($pdo, "
        SELECT r.*, rd.temps as date_retard
        FROM retenu r
        LEFT JOIN retard rd ON r.id_retard = rd.id
        WHERE r.id_agent = ? AND MONTH(r.moi) = ? AND YEAR(r.moi) = ?
        ORDER BY r.moi DESC
    ", [$userId, $month, $year]);
    
    $montantTotal = array_sum(array_column($retenues, 'montant'));
    
    sendJSON(true, 'Succès', [
        'total' => count($retenues),
        'montant_total' => $montantTotal,
        'retenues' => $retenues
    ]);
} catch (Exception $e) {
    sendJSON(false, $e->getMessage());
}
?>