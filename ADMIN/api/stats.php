<?php
// ADMIN/api/stats.php
session_start();
header('Content-Type: application/json');
require_once '../../API/connexion/db.php';

if (!isset($_SESSION['admin_id'])) {
    sendJSON(false, 'Non autorisé');
}

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur de connexion DB');
}

try {
    // Total superviseurs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM superviseur");
    $totalSuperviseurs = $stmt->fetch()['total'];

    // Total agents
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM agent");
    $totalAgents = $stmt->fetch()['total'];

    // Total shops
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM shop");
    $totalShops = $stmt->fetch()['total'];

    // Présences aujourd'hui
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presence WHERE DATE(date) = ?");
    $stmt->execute([$today]);
    $presencesToday = $stmt->fetch()['total'];

    sendJSON(true, 'Succès', [
        'total_superviseurs' => $totalSuperviseurs,
        'total_agents' => $totalAgents,
        'total_shops' => $totalShops,
        'presences_today' => $presencesToday
    ]);

} catch (Exception $e) {
    error_log("Erreur stats: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement des statistiques');
}
?>