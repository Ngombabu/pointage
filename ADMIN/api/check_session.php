<?php
// ADMIN/api/check_session.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../API/connexion/db.php';

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendJSON(false, 'Non connecté');
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        sendJSON(false, 'Erreur de connexion DB');
    }

    // Récupérer les infos du superviseur
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email FROM superviseur WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $superviseur = $stmt->fetch();

    if (!$superviseur) {
        sendJSON(false, 'Superviseur non trouvé');
    }

    // Vérifier si c'est le premier superviseur
    $stmt = $pdo->query("SELECT MIN(id) as min_id FROM superviseur");
    $minId = $stmt->fetch()['min_id'];
    $isFirst = ($superviseur['id'] == $minId);

    sendJSON(true, 'Session valide', [
        'user' => [
            'id' => $superviseur['id'],
            'nom' => $superviseur['nom'] . ' ' . $superviseur['prenom'],
            'email' => $superviseur['email'],
            'is_first' => $isFirst
        ]
    ]);

} catch (Exception $e) {
    error_log("Erreur check_session: " . $e->getMessage());
    sendJSON(false, 'Erreur serveur');
}
?>