<?php
// superviseur/login.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration DB
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

function getShops($pdo, $superviseurId) {
    try {
        $stmt = $pdo->prepare("SELECT id, nom, adresse FROM shop WHERE id_superviseur = ?");
        $stmt->execute([$superviseurId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;

    if (empty($email) || empty($password) || $shop_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
        exit;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion DB']);
        exit;
    }

    // Vérifier superviseur
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email, password FROM superviseur WHERE email = ?");
    $stmt->execute([$email]);
    $superviseur = $stmt->fetch();

    if (!$superviseur || !password_verify($password, $superviseur['password'])) {
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
        exit;
    }

    // Vérifier que le shop appartient bien au superviseur
    $stmt = $pdo->prepare("SELECT id FROM shop WHERE id = ? AND id_superviseur = ?");
    $stmt->execute([$shop_id, $superviseur['id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Shop non autorisé']);
        exit;
    }

    // Créer session
    $_SESSION['superviseur_id'] = $superviseur['id'];
    $_SESSION['superviseur_nom'] = $superviseur['nom'] . ' ' . $superviseur['prenom'];
    $_SESSION['shop_id'] = $shop_id;
    $_SESSION['logged_in'] = true;

    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'redirect' => '/POINTAGE/SCAN/scan.html'
    ]);
    exit;
}

// GET pour récupérer les shops
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_shops') {
    $email = isset($_GET['email']) ? trim($_GET['email']) : '';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email requis']);
        exit;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Erreur DB']);
        exit;
    }

    // Récupérer l'ID du superviseur
    $stmt = $pdo->prepare("SELECT id FROM superviseur WHERE email = ?");
    $stmt->execute([$email]);
    $superviseur = $stmt->fetch();

    if (!$superviseur) {
        echo json_encode(['success' => false, 'message' => 'Superviseur non trouvé']);
        exit;
    }

    $shops = getShops($pdo, $superviseur['id']);
    echo json_encode(['success' => true, 'shops' => $shops]);
    exit;
}
?>