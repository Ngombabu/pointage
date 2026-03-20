<?php
// API/profil/presences.php
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
    // Récupérer les présences du mois
    $stmt = $pdo->prepare("
        SELECT p.*, s.nom as shop_nom,
               CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as est_retard
        FROM presence p
        LEFT JOIN shop s ON p.id_shop = s.id
        LEFT JOIN retard r ON p.id_agent = r.id_agent AND DATE(p.date) = DATE(r.temps)
        WHERE p.id_agent = ? 
        AND MONTH(p.date) = ? 
        AND YEAR(p.date) = ?
        ORDER BY p.date DESC
    ");
    $stmt->execute([$userId, $month, $year]);
    $presences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les jours uniques
    $jours = array_unique(array_map(function($p) {
        return date('Y-m-d', strtotime($p['date']));
    }, $presences));
    
    echo json_encode([
        'success' => true,
        'total' => count($presences),
        'jours' => count($jours),
        'presences' => $presences
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>