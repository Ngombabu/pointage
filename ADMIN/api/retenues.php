<?php
// ADMIN/api/retenues.php
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
    $mois = isset($_GET['mois']) ? intval($_GET['mois']) : intval(date('m'));
    $annee = isset($_GET['annee']) ? intval($_GET['annee']) : intval(date('Y'));
    $agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;

    // Construire la requête
    $sql = "
        SELECT 
            r.*,
            a.nom as agent_nom,
            a.prenom as agent_prenom,
            rd.temps as date_retard,
            s.nom as shop_nom
        FROM retenu r
        JOIN agent a ON r.id_agent = a.id
        LEFT JOIN retard rd ON r.id_retard = rd.id
        LEFT JOIN presence p ON rd.id_agent = p.id_agent AND DATE(rd.temps) = DATE(p.date)
        LEFT JOIN shop s ON p.id_shop = s.id
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

    // Statistiques globales du mois
    $totalMois = count($retenues);
    $montantTotalMois = array_sum(array_column($retenues, 'montant'));

    // Statistiques par agent
    $statsParAgent = [];
    foreach ($retenues as $r) {
        $agentKey = $r['id_agent'];
        if (!isset($statsParAgent[$agentKey])) {
            $statsParAgent[$agentKey] = [
                'agent_id' => $r['id_agent'],
                'nom' => ($r['agent_nom'] ?? '') . ' ' . ($r['agent_prenom'] ?? ''),
                'total' => 0,
                'montant' => 0
            ];
        }
        $statsParAgent[$agentKey]['total']++;
        $statsParAgent[$agentKey]['montant'] += floatval($r['montant']);
    }

    sendJSON(true, 'Succès', [
        'periode' => [
            'mois' => $mois,
            'annee' => $annee,
            'mois_nom' => getFrenchMonthName($mois)
        ],
        'total' => $totalMois,
        'montant_total' => number_format($montantTotalMois, 2),
        'stats_par_agent' => array_values($statsParAgent),
        'retenues' => array_map(function($r) {
            return [
                'id' => $r['id'],
                'id_agent' => $r['id_agent'],
                'agent_nom' => ($r['agent_nom'] ?? '') . ' ' . ($r['agent_prenom'] ?? ''),
                'montant' => number_format(floatval($r['montant']), 2),
                'moi' => $r['moi'],
                'date_formatee' => date('d/m/Y', strtotime($r['moi'])),
                'motif' => $r['motif'] ?? ($r['id_retard'] ? 'Retard' : 'Retenue manuelle'),
                'shop' => $r['shop_nom'] ?? '-'
            ];
        }, $retenues)
    ]);

} catch (Exception $e) {
    error_log("Erreur retenues: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement des retenues');
}

function getFrenchMonthName($monthNum) {
    $months = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];
    return $months[intval($monthNum)] ?? 'Inconnu';
}
?>