<?php
// API/profil/retards.php
session_start();
header('Content-Type: application/json');
require_once '../connexion/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Erreur DB']);
    exit;
}

try {
    // Récupérer l'heure de pointage
    $stmt = $pdo->prepare("
        SELECT h.heure 
        FROM heure h
        JOIN agent a ON a.id_superviseur = h.id_superviseur
        WHERE a.id = ?
    ");
    $stmt->execute([$userId]);
    $heure = $stmt->fetch();
    $heureLimite = $heure ? $heure['heure'] : '07:00:00';
    
    // Récupérer les retards
    $stmt = $pdo->prepare("
        SELECT r.*, 
               TIMESTAMPDIFF(MINUTE, ?, r.temps) as minutes_retard
        FROM retard r
        WHERE r.id_agent = ? 
        AND MONTH(r.temps) = ? 
        AND YEAR(r.temps) = ?
        ORDER BY r.temps DESC
    ");
    $stmt->execute([$heureLimite, $userId, $month, $year]);
    $retards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalMinutes = array_sum(array_column($retards, 'minutes_retard'));
    
    echo json_encode([
        'success' => true,
        'total' => count($retards),
        'minutes' => $totalMinutes,
        'heure_limite' => $heureLimite,
        'retards' => $retards
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>