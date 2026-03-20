<?php
// ADMIN/api/retenues.php
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
    $mois = isset($_GET['mois']) ? intval($_GET['mois']) : date('m');
    $annee = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');
    $agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;

    $sql = "
        SELECT r.*, a.nom as agent_nom, a.prenom as agent_prenom, rd.temps as date_retard
        FROM retenu r
        JOIN agent a ON r.id_agent = a.id
        LEFT JOIN retard rd ON r.id_retard = rd.id
        WHERE MONTH(r.moi) = ? AND YEAR(r.moi) = ?
    ";
    $params = [$mois, $annee];

    if ($agentId) {
        $sql .= " AND r.id_agent = ?";
        $params[] = $agentId;
    }

    $sql .= " ORDER BY r.moi DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $retenues = $stmt->fetchAll();

    // Calculer les totaux
    $total = count($retenues);
    $montantTotal = array_sum(array_column($retenues, 'montant'));

    // Statistiques par agent
    $statsParAgent = [];
    foreach ($retenues as $r) {
        $agentKey = $r['id_agent'];
        if (!isset($statsParAgent[$agentKey])) {
            $statsParAgent[$agentKey] = [
                'agent_id' => $r['id_agent'],
                'nom' => $r['agent_nom'] . ' ' . $r['agent_prenom'],
                'total' => 0,
                'montant' => 0
            ];
        }
        $statsParAgent[$agentKey]['total']++;
        $statsParAgent[$agentKey]['montant'] += floatval($r['montant']);
    }

    sendJSON(true, 'Succès', [
        'total' => $total,
        'montant_total' => number_format($montantTotal, 2),
        'stats_par_agent' => array_values($statsParAgent),
        'retenues' => $retenues
    ]);

} catch (Exception $e) {
    error_log("Erreur retenues: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement des retenues');
}
?>