<?php
// API/profil/update.php
session_start();
header('Content-Type: application/json');
require_once '../connexion/db.php';

if (!isLoggedIn()) {
    sendJSON(false, 'Non connecté');
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = getCurrentUserId();

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur DB');
}

try {
    $nom = isset($input['nom']) ? trim($input['nom']) : null;
    $postnom = isset($input['postnom']) ? trim($input['postnom']) : null;
    $prenom = isset($input['prenom']) ? trim($input['prenom']) : null;
    $email = isset($input['email']) ? trim($input['email']) : null;
    $telephone = isset($input['telephone']) ? trim($input['telephone']) : null;
    $currentPassword = isset($input['current_password']) ? $input['current_password'] : null;
    $newPassword = isset($input['new_password']) ? $input['new_password'] : null;
    
    if ($email && emailExists($pdo, 'agent', $email, $userId)) {
        sendJSON(false, 'Cet email est déjà utilisé');
    }
    
    $updates = [];
    $params = [];
    
    if ($nom !== null) { $updates[] = "nom = ?"; $params[] = $nom; }
    if ($postnom !== null) { $updates[] = "postnom = ?"; $params[] = $postnom; }
    if ($prenom !== null) { $updates[] = "prenom = ?"; $params[] = $prenom; }
    if ($email !== null) { $updates[] = "email = ?"; $params[] = $email; }
    if ($telephone !== null) { $updates[] = "telephone = ?"; $params[] = $telephone; }
    
    if ($newPassword && $currentPassword) {
        $user = fetchOne($pdo, "SELECT password FROM agent WHERE id = ?", [$userId]);
        if (!password_verify($currentPassword, $user['password'])) {
            sendJSON(false, 'Mot de passe actuel incorrect');
        }
        $updates[] = "password = ?";
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    
    if (empty($updates)) {
        sendJSON(true, 'Aucune modification');
    }
    
    $params[] = $userId;
    updateRow($pdo, 'agent', array_combine($updates, $params), 'id = ?', [$userId]);
    
    $user = fetchOne($pdo, "SELECT id, nom, postnom, prenom, email, telephone FROM agent WHERE id = ?", [$userId]);
    
    sendJSON(true, 'Profil mis à jour', ['user' => $user]);
    
} catch (Exception $e) {
    sendJSON(false, $e->getMessage());
}
?>