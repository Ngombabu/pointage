<?php
// API/profil/update.php
session_start();
header('Content-Type: application/json');
require_once '../connexion/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Erreur DB']);
    exit;
}

try {
    // Récupérer les données
    $nom = isset($input['nom']) ? trim($input['nom']) : null;
    $postnom = isset($input['postnom']) ? trim($input['postnom']) : null;
    $prenom = isset($input['prenom']) ? trim($input['prenom']) : null;
    $email = isset($input['email']) ? trim($input['email']) : null;
    $telephone = isset($input['telephone']) ? trim($input['telephone']) : null;
    $currentPassword = isset($input['current_password']) ? $input['current_password'] : null;
    $newPassword = isset($input['new_password']) ? $input['new_password'] : null;
    
    // Vérifier si l'email existe déjà
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM agent WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
            exit;
        }
    }
    
    // Construire la requête de mise à jour
    $updates = [];
    $params = [];
    
    if ($nom !== null) {
        $updates[] = "nom = ?";
        $params[] = $nom;
    }
    if ($postnom !== null) {
        $updates[] = "postnom = ?";
        $params[] = $postnom;
    }
    if ($prenom !== null) {
        $updates[] = "prenom = ?";
        $params[] = $prenom;
    }
    if ($email !== null) {
        $updates[] = "email = ?";
        $params[] = $email;
    }
    if ($telephone !== null) {
        $updates[] = "telephone = ?";
        $params[] = $telephone;
    }
    
    // Gérer le changement de mot de passe
    if ($newPassword && $currentPassword) {
        // Vérifier l'ancien mot de passe
        $stmt = $pdo->prepare("SELECT password FROM agent WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPassword, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
            exit;
        }
        
        $updates[] = "password = ?";
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'Aucune modification']);
        exit;
    }
    
    $params[] = $userId;
    $sql = "UPDATE agent SET " . implode(", ", $updates) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Récupérer les nouvelles données
    $stmt = $pdo->prepare("SELECT id, nom, postnom, prenom, email, telephone FROM agent WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profil mis à jour avec succès',
        'user' => $user
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>