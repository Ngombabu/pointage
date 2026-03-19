<?php
// SCAN/api.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
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

function getHeurePointage($pdo, $superviseurId) {
    try {
        $stmt = $pdo->prepare("SELECT heure FROM heure WHERE id_superviseur = ?");
        $stmt->execute([$superviseurId]);
        $result = $stmt->fetch();
        return $result ? $result['heure'] : '07:30:00'; // Heure par défaut
    } catch (Exception $e) {
        return '07:30:00';
    }
}

function getPenalite($pdo, $superviseurId) {
    try {
        $stmt = $pdo->prepare("SELECT retard FROM penalite WHERE id_superviseur = ?");
        $stmt->execute([$superviseurId]);
        $result = $stmt->fetch();
        return $result ? floatval($result['retard']) : 2.50; // Pénalité par défaut
    } catch (Exception $e) {
        return 2.50;
    }
}

function aDejaPointeAujourdhui($pdo, $agentId) {
    try {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT id FROM presence WHERE id_agent = ? AND DATE(date) = ?");
        $stmt->execute([$agentId, $today]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function aDejaRetardAujourdhui($pdo, $agentId) {
    try {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT id FROM retard WHERE id_agent = ? AND DATE(temps) = ?");
        $stmt->execute([$agentId, $today]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function enregistrerPresence($pdo, $agentId, $shopId, $estEnRetard) {
    try {
        // Enregistrer présence
        $stmt = $pdo->prepare("INSERT INTO presence (id_agent, id_shop, date) VALUES (?, ?, NOW())");
        $stmt->execute([$agentId, $shopId]);
        $presenceId = $pdo->lastInsertId();

        // Si en retard, enregistrer aussi dans retard
        if ($estEnRetard) {
            $stmt = $pdo->prepare("INSERT INTO retard (id_agent, temps) VALUES (?, NOW())");
            $stmt->execute([$agentId]);
        }

        return ['success' => true, 'presence_id' => $presenceId];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function appliquerPenalite($pdo, $agentId, $montant) {
    try {
        $stmt = $pdo->prepare("INSERT INTO retenu (id_agent, montant, moi) VALUES (?, ?, NOW())");
        $stmt->execute([$agentId, $montant]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Vérifier session superviseur
if (!isset($_SESSION['superviseur_id']) || !isset($_SESSION['shop_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé', 'redirect' => '/POINTAGE/superviseur/']);
    exit;
}

$superviseurId = $_SESSION['superviseur_id'];
$shopId = $_SESSION['shop_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion DB']);
    exit;
}

// Action: Scanner QR code
if ($action === 'scan') {
    $input = json_decode(file_get_contents('php://input'), true);
    $qrData = isset($input['qr_data']) ? $input['qr_data'] : '';

    if (empty($qrData)) {
        echo json_encode(['success' => false, 'message' => 'Données QR manquantes']);
        exit;
    }

    // Décoder les données du QR
    $data = json_decode($qrData, true);
    if (!$data || !isset($data['user_id']) || !isset($data['token']) || !isset($data['timestamp'])) {
        echo json_encode(['success' => false, 'message' => 'QR code invalide']);
        exit;
    }

    $agentId = $data['user_id'];
    $token = $data['token'];
    $timestamp = $data['timestamp'];

    // Vérifier si le token n'est pas trop vieux (15 secondes max)
    if (time() - $timestamp > 15) {
        echo json_encode(['success' => false, 'message' => 'QR code expiré (plus de 15 secondes)']);
        exit;
    }

    // Vérifier le token dans la base
    $stmt = $pdo->prepare("SELECT id, nom, prenom, token FROM agent WHERE id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();

    if (!$agent || $agent['token'] !== $token) {
        echo json_encode(['success' => false, 'message' => 'Token invalide']);
        exit;
    }

    // Vérifier si déjà pointé aujourd'hui
    if (aDejaPointeAujourdhui($pdo, $agentId)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cet agent a déjà pointé aujourd\'hui',
            'agent' => [
                'nom' => $agent['nom'],
                'prenom' => $agent['prenom']
            ]
        ]);
        exit;
    }

    // Récupérer l'heure de pointage
    $heurePointage = getHeurePointage($pdo, $superviseurId);
    $heureActuelle = date('H:i:s');
    
    // Comparer les heures
    $estEnRetard = $heureActuelle > $heurePointage;
    
    // Enregistrer la présence
    $result = enregistrerPresence($pdo, $agentId, $shopId, $estEnRetard);
    
    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
        exit;
    }

    // Si en retard, appliquer pénalité
    $penaliteMessage = '';
    if ($estEnRetard) {
        // Vérifier si déjà en retard aujourd'hui (normalement pas car pas de présence)
        if (!aDejaRetardAujourdhui($pdo, $agentId)) {
            $montantPenalite = getPenalite($pdo, $superviseurId);
            appliquerPenalite($pdo, $agentId, $montantPenalite);
            $penaliteMessage = " Pénalité de {$montantPenalite}€ appliquée.";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pointage enregistré avec succès' . $penaliteMessage,
        'agent' => [
            'nom' => $agent['nom'],
            'prenom' => $agent['prenom']
        ],
        'statut' => $estEnRetard ? 'RETARD' : 'À L\'HEURE',
        'heure_pointage' => $heureActuelle,
        'heure_requise' => $heurePointage,
        'penalite_appliquee' => $estEnRetard
    ]);
    exit;
}

// Action: Vérifier statut
if ($action === 'status') {
    echo json_encode([
        'success' => true,
        'superviseur_id' => $superviseurId,
        'shop_id' => $shopId,
        'heure_pointage' => getHeurePointage($pdo, $superviseurId)
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non valide']);
?>