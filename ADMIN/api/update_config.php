<?php
// ADMIN/api/update_config.php
session_start();
header('Content-Type: application/json');
require_once '../../API/connexion/db.php';

if (!isset($_SESSION['admin_id'])) {
    sendJSON(false, 'Non autorisé');
}

// Vérifier que c'est bien le premier superviseur
$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur de connexion DB');
}

$stmt = $pdo->query("SELECT MIN(id) as min_id FROM superviseur");
$minId = $stmt->fetch()['min_id'];

if ($_SESSION['admin_id'] != $minId) {
    sendJSON(false, 'Seul le premier superviseur peut modifier la configuration');
}

$type = isset($_POST['type']) ? $_POST['type'] : '';
$value = isset($_POST['value']) ? trim($_POST['value']) : '';

if (empty($type) || empty($value)) {
    sendJSON(false, 'Paramètres manquants');
}

try {
    if ($type === 'heure') {
        // Valider format heure
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
            sendJSON(false, 'Format d\'heure invalide (HH:MM)');
        }
        $value .= ':00'; // Ajouter les secondes
        $stmt = $pdo->prepare("UPDATE heure SET heure = ? WHERE id = 1");
        $stmt->execute([$value]);
    } 
    elseif ($type === 'penalite') {
        // Valider nombre
        if (!is_numeric($value) || floatval($value) <= 0) {
            sendJSON(false, 'Montant invalide');
        }
        $stmt = $pdo->prepare("UPDATE penalite SET retard = ? WHERE id = 2");
        $stmt->execute([floatval($value)]);
    }
    else {
        sendJSON(false, 'Type de configuration inconnu');
    }

    sendJSON(true, 'Configuration mise à jour');

} catch (Exception $e) {
    error_log("Erreur update_config: " . $e->getMessage());
    sendJSON(false, 'Erreur lors de la mise à jour');
}
?>