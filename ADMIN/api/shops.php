<?php
// ADMIN/api/shops.php
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
    $stmt = $pdo->query("
        SELECT s.*, sup.nom as superviseur_nom 
        FROM shop s
        LEFT JOIN superviseur sup ON s.id_superviseur = sup.id
        ORDER BY s.id DESC
    ");
    $shops = $stmt->fetchAll();

    sendJSON(true, 'Succès', ['shops' => $shops]);

} catch (Exception $e) {
    error_log("Erreur shops: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement des shops');
}
?>