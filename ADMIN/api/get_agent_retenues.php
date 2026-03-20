<?php
// ADMIN/api/get_agent_retenues.php
session_start();
header('Content-Type: application/json');
require_once '../../API/connexion/db.php';

if (!isset($_SESSION['admin_id'])) {
    sendJSON(false, 'Non autorisé');
}

$agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;

if ($agentId <= 0) {
    sendJSON(false, 'ID agent requis');
}

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur de connexion DB');
}

try {
    // Récupérer les infos de l'agent
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email FROM agent WHERE id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();

    if (!$agent) {
        sendJSON(false, 'Agent non trouvé');
    }

    // Récupérer les retenues par mois
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(moi, '%Y-%m') as mois,
            COUNT(*) as total,
            SUM(montant) as montant_total
        FROM retenu 
        WHERE id_agent = ?
        GROUP BY DATE_FORMAT(moi, '%Y-%m')
        ORDER BY mois DESC
    ");
    $stmt->execute([$agentId]);
    $mensuel = $stmt->fetchAll();

    // Récupérer le détail des retenues
    $stmt = $pdo->prepare("
        SELECT r.*, rd.temps as date_retard
        FROM retenu r
        LEFT JOIN retard rd ON r.id_retard = rd.id
        WHERE r.id_agent = ?
        ORDER BY r.moi DESC
    ");
    $stmt->execute([$agentId]);
    $details = $stmt->fetchAll();

    sendJSON(true, 'Succès', [
        'agent' => $agent,
        'mensuel' => $mensuel,
        'details' => $details,
        'total_global' => array_sum(array_column($details, 'montant'))
    ]);

} catch (Exception $e) {
    error_log("Erreur get_agent_retenues: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement');
}
?>