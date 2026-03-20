<?php
// ADMIN/api/presences.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../API/connexion/db.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion DB']);
    exit;
}

try {
    $mois = isset($_GET['mois']) ? intval($_GET['mois']) : intval(date('m'));
    $annee = isset($_GET['annee']) ? intval($_GET['annee']) : intval(date('Y'));
    $shopId = isset($_GET['shop_id']) && $_GET['shop_id'] !== 'all' ? intval($_GET['shop_id']) : null;
    $agentId = isset($_GET['agent_id']) && $_GET['agent_id'] !== 'all' ? intval($_GET['agent_id']) : null;

    // Construire la requête de base
    $sql = "
        SELECT 
            p.*,
            a.nom as agent_nom,
            a.prenom as agent_prenom,
            a.email as agent_email,
            s.nom as shop_nom,
            s.id as shop_id,
            CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as est_retard
        FROM presence p
        JOIN agent a ON p.id_agent = a.id
        JOIN shop s ON p.id_shop = s.id
        LEFT JOIN retard r ON p.id_agent = r.id_agent AND DATE(p.date) = DATE(r.temps)
        WHERE MONTH(p.date) = ? AND YEAR(p.date) = ?
    ";
    
    $params = [$mois, $annee];
    
    if ($shopId) {
        $sql .= " AND p.id_shop = ?";
        $params[] = $shopId;
    }
    
    if ($agentId) {
        $sql .= " AND p.id_agent = ?";
        $params[] = $agentId;
    }
    
    $sql .= " ORDER BY p.date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $presences = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques globales
    $totalPresences = count($presences);
    $agentsUniques = [];
    $joursUniques = [];
    
    foreach ($presences as $p) {
        $agentsUniques[$p['id_agent']] = true;
        $joursUniques[date('Y-m-d', strtotime($p['date']))] = true;
    }
    
    $totalAgents = count($agentsUniques);
    $totalJours = count($joursUniques);
    $moyenneParJour = $totalJours > 0 ? round($totalPresences / $totalJours, 1) : 0;

    // Statistiques par agent
    $statsParAgent = [];
    foreach ($presences as $p) {
        $agentId = $p['id_agent'];
        if (!isset($statsParAgent[$agentId])) {
            $statsParAgent[$agentId] = [
                'id' => $agentId,
                'nom' => $p['agent_nom'] . ' ' . $p['agent_prenom'],
                'email' => $p['agent_email'],
                'total' => 0,
                'jours' => [],
                'retards' => 0
            ];
        }
        $statsParAgent[$agentId]['total']++;
        $statsParAgent[$agentId]['jours'][date('Y-m-d', strtotime($p['date']))] = true;
        if ($p['est_retard']) {
            $statsParAgent[$agentId]['retards']++;
        }
    }

    // Statistiques par jour
    $statsParJour = [];
    foreach ($presences as $p) {
        $jour = date('Y-m-d', strtotime($p['date']));
        if (!isset($statsParJour[$jour])) {
            $statsParJour[$jour] = [
                'date' => $jour,
                'total' => 0,
                'agents' => []
            ];
        }
        $statsParJour[$jour]['total']++;
        $statsParJour[$jour]['agents'][$p['id_agent']] = true;
    }

    // Récupérer tous les shops pour le filtre
    $stmtShops = $pdo->query("SELECT id, nom FROM shop ORDER BY nom");
    $shops = $stmtShops->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer tous les agents pour le filtre
    $stmtAgents = $pdo->query("SELECT id, nom, prenom FROM agent ORDER BY nom");
    $agents = $stmtAgents->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => $totalPresences,
            'total_agents' => $totalAgents,
            'total_jours' => $totalJours,
            'moyenne' => $moyenneParJour
        ],
        'par_agent' => array_map(function($a) {
            return [
                'id' => $a['id'],
                'nom' => $a['nom'],
                'email' => $a['email'],
                'total' => $a['total'],
                'jours' => count($a['jours']),
                'retards' => $a['retards']
            ];
        }, array_values($statsParAgent)),
        'par_jour' => array_map(function($j) {
            return [
                'date' => $j['date'],
                'date_formatee' => date('d/m/Y', strtotime($j['date'])),
                'total' => $j['total'],
                'agents' => count($j['agents'])
            ];
        }, array_values($statsParJour)),
        'details' => array_map(function($p) {
            return [
                'id' => $p['id'],
                'date' => $p['date'],
                'date_formatee' => date('d/m/Y H:i', strtotime($p['date'])),
                'agent' => $p['agent_nom'] . ' ' . $p['agent_prenom'],
                'agent_id' => $p['id_agent'],
                'shop' => $p['shop_nom'],
                'shop_id' => $p['shop_id'],
                'est_retard' => $p['est_retard']
            ];
        }, $presences),
        'filters' => [
            'shops' => $shops,
            'agents' => $agents
        ]
    ]);

} catch (Exception $e) {
    error_log("Erreur presences API: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>