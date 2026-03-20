<?php
// ADMIN/api/superviseurs.php
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
    // Récupérer tous les superviseurs
    $stmt = $pdo->query("
        SELECT id, nom, postnom, prenom, email, telephone 
        FROM superviseur 
        ORDER BY id ASC
    ");
    $superviseurs = $stmt->fetchAll();

    // Déterminer le premier superviseur
    $firstId = !empty($superviseurs) ? $superviseurs[0]['id'] : null;

    $result = array_map(function($s) use ($firstId) {
        return [
            'id' => $s['id'],
            'nom' => $s['nom'],
            'postnom' => $s['postnom'] ?? '',
            'prenom' => $s['prenom'] ?? '',
            'email' => $s['email'],
            'telephone' => $s['telephone'] ?? '',
            'is_first' => ($s['id'] == $firstId)
        ];
    }, $superviseurs);

    sendJSON(true, 'Succès', ['superviseurs' => $result]);

} catch (Exception $e) {
    error_log("Erreur superviseurs: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement des superviseurs');
}
?>