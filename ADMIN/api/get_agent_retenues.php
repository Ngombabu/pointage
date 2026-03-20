<?php
// ADMIN/api/get_agent_retenues.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../API/connexion/db.php';

if (!isset($_SESSION['admin_id'])) {
    sendJSON(false, 'Non autorisé');
}

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur de connexion DB');
}

try {
    $agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
    
    // Si pas d'agent_id, retourner une erreur claire
    if ($agentId <= 0) {
        sendJSON(false, 'ID agent requis');
    }

    // Vérifier que l'agent existe
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email FROM agent WHERE id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();

    if (!$agent) {
        sendJSON(false, 'Agent non trouvé');
    }

    // Récupérer toutes les retenues de l'agent
    $stmt = $pdo->prepare("
        SELECT r.*, 
               rd.temps as date_retard,
               DATE_FORMAT(r.moi, '%Y-%m') as mois
        FROM retenu r
        LEFT JOIN retard rd ON r.id_retard = rd.id
        WHERE r.id_agent = ?
        ORDER BY r.moi DESC
    ");
    $stmt->execute([$agentId]);
    $details = $stmt->fetchAll();

    // Statistiques mensuelles
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(moi, '%Y-%m') as mois,
            COUNT(*) as nombre,
            SUM(montant) as total
        FROM retenu 
        WHERE id_agent = ?
        GROUP BY DATE_FORMAT(moi, '%Y-%m')
        ORDER BY mois DESC
    ");
    $stmt->execute([$agentId]);
    $mensuel = $stmt->fetchAll();

    // Calculer le total global
    $totalGlobal = array_sum(array_column($details, 'montant'));

    // Compter le nombre total de retenues
    $nombreTotal = count($details);

    sendJSON(true, 'Succès', [
        'agent' => [
            'id' => $agent['id'],
            'nom' => $agent['nom'],
            'prenom' => $agent['prenom'] ?? '',
            'email' => $agent['email']
        ],
        'stats' => [
            'total_global' => number_format($totalGlobal, 2),
            'nombre_total' => $nombreTotal,
            'moyenne' => $nombreTotal > 0 ? number_format($totalGlobal / $nombreTotal, 2) : '0.00'
        ],
        'mensuel' => array_map(function($m) {
            return [
                'mois' => $m['mois'],
                'nombre' => intval($m['nombre']),
                'total' => number_format(floatval($m['total']), 2)
            ];
        }, $mensuel),
        'details' => array_map(function($r) {
            return [
                'id' => $r['id'],
                'date' => $r['moi'],
                'date_formatee' => date('d/m/Y', strtotime($r['moi'])),
                'montant' => number_format(floatval($r['montant']), 2),
                'motif' => $r['motif'] ?? ($r['id_retard'] ? 'Retard automatique' : 'Retenue manuelle'),
                'date_retard' => $r['date_retard'] ? date('d/m/Y', strtotime($r['date_retard'])) : null
            ];
        }, $details)
    ]);

} catch (Exception $e) {
    error_log("Erreur get_agent_retenues: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement: ' . $e->getMessage());
}
?>