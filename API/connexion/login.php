<?php
// API/connexion/login.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'pointeur_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDBConnection() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

function checkLoginStatus($pdo) {
    try {
        $stmt = $pdo->query("SELECT login FROM connexion WHERE id = 1");
        $result = $stmt->fetch();
        return ['success' => true, 'login_status' => $result ? $result['login'] : 'OFF'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur'];
    }
}

function authenticateUser($pdo, $email, $password) {
    try {
        // Vérifier dans agent
        $stmt = $pdo->prepare("SELECT id, nom, postnom, prenom, telephone, email, password FROM agent WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'nom' => $user['nom'],
                    'postnom' => $user['postnom'] ?? '',
                    'prenom' => $user['prenom'] ?? '',
                    'email' => $user['email'],
                    'telephone' => $user['telephone'] ?? '',
                    'type' => 'agent'
                ]
            ];
        }
        
        // Vérifier dans superviseur
        $stmt = $pdo->prepare("SELECT id, nom, postnom, prenom, telephone, email, password FROM superviseur WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'nom' => $user['nom'],
                    'postnom' => $user['postnom'] ?? '',
                    'prenom' => $user['prenom'] ?? '',
                    'email' => $user['email'],
                    'telephone' => $user['telephone'] ?? '',
                    'type' => 'superviseur'
                ]
            ];
        }
        
        return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur de base de données'];
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$pdo = getDBConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion DB']);
    exit;
}

if ($action === 'check_status') {
    echo json_encode(checkLoginStatus($pdo));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Champs requis']);
        exit;
    }
    
    $auth = authenticateUser($pdo, $email, $password);
    
    if ($auth['success']) {
        $_SESSION['user_id'] = $auth['user']['id'];
        $_SESSION['user_email'] = $auth['user']['email'];
        $_SESSION['user_nom_complet'] = $auth['user']['nom'] . ' ' . $auth['user']['prenom'];
        $_SESSION['user_type'] = $auth['user']['type'];
        $_SESSION['logged_in'] = true;
        
        echo json_encode([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => $auth['user']
        ]);
    } else {
        echo json_encode($auth);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Requête invalide']);
?>