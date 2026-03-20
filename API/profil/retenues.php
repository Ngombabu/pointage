<?php
// API/profil/retenues.php
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
    // Récupérer les retenues du mois
    $stmt = $pdo->prepare("
        SELECT r.*, rd.temps as date_retard
        FROM retenu r
        LEFT JOIN retard rd ON r.id_retard = rd.id
        WHERE r.id_agent = ? 
        AND MONTH(r.moi) = ? 
        AND YEAR(r.moi) = ?
        ORDER BY r.moi DESC
    ");
    $stmt->execute([$userId, $month, $year]);
    $retenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $montantTotal = array_sum(array_column($retenues, 'montant'));
    
    echo json_encode([
        'success' => true,
        'total' => count($retenues),
        'montant_total' => $montantTotal,
        'retenues' => $retenues
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>