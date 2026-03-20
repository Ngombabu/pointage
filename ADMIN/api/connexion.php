<?php
// ADMIN/api/connexion.php
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

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    if ($action === 'get_status') {
        // Récupérer le statut actuel
        $stmt = $pdo->query("SELECT login, id_superviseur FROM connexion WHERE id = 1");
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$status) {
            // Créer l'entrée si elle n'existe pas
            $stmt = $pdo->prepare("INSERT INTO connexion (id, id_superviseur, login) VALUES (1, ?, 'OFF')");
            $stmt->execute([$_SESSION['admin_id']]);
            $status = ['login' => 'OFF', 'id_superviseur' => $_SESSION['admin_id']];
        }
        
        // Récupérer le nom du superviseur qui a fait la dernière modification
        $stmt = $pdo->prepare("SELECT nom, prenom FROM superviseur WHERE id = ?");
        $stmt->execute([$status['id_superviseur']]);
        $superviseur = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'status' => $status['login'],
            'last_modified_by' => $superviseur ? $superviseur['nom'] . ' ' . $superviseur['prenom'] : 'Inconnu',
            'last_modified_id' => $status['id_superviseur']
        ]);
        exit;
    }
    
    elseif ($action === 'toggle') {
        // Vérifier que c'est bien le premier superviseur (ID le plus petit)
        $stmt = $pdo->query("SELECT MIN(id) as min_id FROM superviseur");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $minId = $result['min_id'];
        
        if ($_SESSION['admin_id'] != $minId) {
            echo json_encode(['success' => false, 'message' => 'Seul le premier superviseur peut modifier le statut de connexion']);
            exit;
        }
        
        $newStatus = isset($_POST['status']) ? $_POST['status'] : '';
        
        if (!in_array($newStatus, ['ON', 'OFF'])) {
            echo json_encode(['success' => false, 'message' => 'Statut invalide']);
            exit;
        }
        
        // Vérifier si l'enregistrement existe
        $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM connexion WHERE id = 1");
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checkResult['count'] > 0) {
            // Mise à jour
            $stmt = $pdo->prepare("UPDATE connexion SET login = ?, id_superviseur = ? WHERE id = 1");
            $stmt->execute([$newStatus, $_SESSION['admin_id']]);
        } else {
            // Insertion
            $stmt = $pdo->prepare("INSERT INTO connexion (id, id_superviseur, login) VALUES (1, ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $newStatus]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Statut mis à jour', 
            'status' => $newStatus
        ]);
        exit;
    }
    
    else {
        echo json_encode(['success' => false, 'message' => 'Action non valide']);
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Erreur connexion API PDO: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    error_log("Erreur connexion API: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
    exit;
}
?>