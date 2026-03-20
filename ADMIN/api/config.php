<?php
// ADMIN/api/config.php
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
    // Récupérer l'heure de pointage
    $stmt = $pdo->query("SELECT heure FROM heure WHERE id = 1");
    $heure = $stmt->fetch();
    $heureValue = $heure ? $heure['heure'] : '07:00:00';

    // Récupérer la pénalité
    $stmt = $pdo->query("SELECT retard FROM penalite WHERE id = 2");
    $penalite = $stmt->fetch();
    $penaliteValue = $penalite ? floatval($penalite['retard']) : 2.50;

    sendJSON(true, 'Succès', [
        'heure' => substr($heureValue, 0, 5),
        'penalite' => number_format($penaliteValue, 2)
    ]);

} catch (Exception $e) {
    error_log("Erreur config: " . $e->getMessage());
    sendJSON(false, 'Erreur lors du chargement de la configuration');
}
?>