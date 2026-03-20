<?php
// ADMIN/api/agents.php
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
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    if (!empty($search)) {
        // Recherche avec filtre
        $stmt = $pdo->prepare("
            SELECT a.*, s.nom as superviseur_nom 
            FROM agent a
            LEFT JOIN superviseur s ON a.id_superviseur = s.id
            WHERE a.nom LIKE ? OR a.prenom LIKE ? OR a.email LIKE ? OR a.telephone LIKE ?
            ORDER BY a.id DESC
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        // Tous les agents
        $stmt = $pdo->query("
            SELECT a.*, s.nom as superviseur_nom 
            FROM agent a
            LEFT JOIN superviseur s ON a.id_superviseur = s.id
            ORDER BY a.id DESC
        ");
    }

    $agents = $stmt->fetchAll();

    sendJSON(true, 'Succès', ['agents' => $agents]);

} catch (Exception $e) {
    error_log("Erreur agents: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement des agents');
}
?>