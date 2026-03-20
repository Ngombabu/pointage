<?php
// ADMIN/api/retards.php
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

    // Récupérer l'heure de pointage par défaut
    $stmt = $pdo->query("SELECT heure FROM heure WHERE id = 1");
    $heureLimite = $stmt->fetch();
    $heureLimiteValue = $heureLimite ? $heureLimite['heure'] : '07:00:00';

    // Récupérer tous les retards du mois avec les infos agents
    $sql = "
        SELECT 
            r.*,
            a.nom as agent_nom,
            a.prenom as agent_prenom,
            a.email as agent_email,
            s.nom as shop_nom,
            p.date as date_presence,
            TIMESTAMPDIFF(MINUTE, ?, r.temps) as minutes_retard
        FROM retard r
        JOIN agent a ON r.id_agent = a.id
        LEFT JOIN presence p ON r.id_agent = p.id_agent AND DATE(r.temps) = DATE(p.date)
        LEFT JOIN shop s ON p.id_shop = s.id
        WHERE MONTH(r.temps) = ? AND YEAR(r.temps) = ?
        ORDER BY r.temps DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$heureLimiteValue, $mois, $annee]);
    $retards = $stmt->fetchAll();

    // Statistiques
    $totalRetards = count($retards);
    $totalMinutes = array_sum(array_column($retards, 'minutes_retard'));
    
    // Récupérer le montant de la pénalité
    $stmt = $pdo->query("SELECT retard FROM penalite WHERE id = 2");
    $penalite = $stmt->fetch();
    $montantPenalite = $penalite ? floatval($penalite['retard']) : 2.50;
    
    $totalPenalites = $totalRetards * $montantPenalite;

    sendJSON(true, 'Succès', [
        'periode' => [
            'mois' => $mois,
            'annee' => $annee,
            'mois_nom' => getFrenchMonthName($mois)
        ],
        'stats' => [
            'total_retards' => $totalRetards,
            'total_minutes' => $totalMinutes,
            'total_penalites' => number_format($totalPenalites, 2),
            'montant_penalite' => $montantPenalite
        ],
        'retards' => array_map(function($r) use ($montantPenalite) {
            return [
                'id' => $r['id'],
                'id_agent' => $r['id_agent'],
                'agent_nom' => $r['agent_nom'] . ' ' . ($r['agent_prenom'] ?? ''),
                'agent_email' => $r['agent_email'],
                'date' => $r['temps'],
                'date_formatee' => date('d/m/Y H:i', strtotime($r['temps'])),
                'minutes_retard' => intval($r['minutes_retard']),
                'penalite' => number_format($montantPenalite, 2),
                'shop' => $r['shop_nom'] ?? '-'
            ];
        }, $retards)
    ]);

} catch (Exception $e) {
    error_log("Erreur retards API: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement des retards');
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