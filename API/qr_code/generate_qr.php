<?php
// API/qr_code/generate_qr.php
session_start();
ob_clean();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once('../../phpqrcode-master/qrlib.php');
    define('DB_HOST', 'sql302.infinityfree.com');
    define('DB_NAME', 'if0_41083645_pointage_db');
    define('DB_USER', 'if0_41083645');
    define('DB_PASS', 'TwbNlC3rhQTFY');
    define('BASE_PATH', realpath(__DIR__ . '/../../') . '/');
    define('QR_PATH', BASE_PATH . 'SRC/QR/');

    if (!file_exists(QR_PATH)) {
        mkdir(QR_PATH, 0777, true);
    }

    function getDBConnection() {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            return $pdo;
        } catch (Exception $e) {
            return null;
        }
    }

    function sendJSON($data) {
        echo json_encode($data);
        exit;
    }

    // Vérifier session
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendJSON(['success' => false, 'message' => 'Non connecté']);
    }

    $userId = $_SESSION['user_id'];
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'generate') {
        $pdo = getDBConnection();
        if (!$pdo) {
            sendJSON(['success' => false, 'message' => 'Erreur DB']);
        }

        // Récupérer user
        $stmt = $pdo->prepare("SELECT id, nom, postnom, prenom FROM agent WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $pdo->prepare("SELECT id, nom, postnom, prenom FROM superviseur WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }

        if (!$user) {
            sendJSON(['success' => false, 'message' => 'Utilisateur non trouvé']);
        }

        // Générer token
        $timestamp = time();
        $token = hash('sha256', $userId . '|' . $timestamp . '|' . uniqid());
        
        $qrData = json_encode([
            'user_id' => $userId,
            'nom' => $user['nom'],
            'prenom' => $user['prenom'] ?? '',
            'token' => $token,
            'timestamp' => $timestamp
        ]);

        // 🔥 UN SEUL FICHIER PAR AGENT - toujours le même nom
        $fileName = 'user_' . $userId . '_qr.png';
        $filePath = QR_PATH . $fileName;
        $relativePath = 'SRC/QR/' . $fileName;

        // Supprimer l'ancien fichier s'il existe (optionnel, sera écrasé de toute façon)
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Générer QR
        QRcode::png($qrData, $filePath, QR_ECLEVEL_L, 10);

        if (!file_exists($filePath)) {
            sendJSON(['success' => false, 'message' => 'Échec création fichier']);
        }

        // Mettre à jour DB
        $updateStmt = $pdo->prepare("UPDATE agent SET token = ?, image_agent = ? WHERE id = ?");
        $updateStmt->execute([$token, $relativePath, $userId]);

        sendJSON([
            'success' => true,
            'qr_path' => $relativePath,
            'qr_url' => '/pointage-presence.rf.gd/' . $relativePath,
            'token' => $token,
            'timestamp' => $timestamp,
            'expires_in' => 15
        ]);
    }

    if ($action === 'cleanup') {
        // Optionnel: nettoyer mais avec le nouveau système, pas nécessaire
        // On garde juste pour compatibilité
        sendJSON(['success' => true, 'message' => 'Cleanup non nécessaire']);
    }

    if ($action === 'verify') {
        $token = $_GET['token'] ?? '';
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT token FROM agent WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            sendJSON([
                'success' => true,
                'valid' => ($user && $user['token'] === $token)
            ]);
        }
    }

    sendJSON(['success' => false, 'message' => 'Action non valide']);

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    exit;
}
?>