<?php
// SCAN/api.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration DB
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_NAME', 'if0_41083645_pointage_db');
define('DB_USER', 'if0_41083645');
define('DB_PASS', 'TwbNlC3rhQTFY');

// ==================== FONCTIONS DE BASE ====================

function getDBConnection() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur DB: " . $e->getMessage());
        return null;
    }
}

function sendJSON($success, $message = '', $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ==================== FONCTIONS MÉTIER ====================

function getHeurePointage($pdo, $superviseurId) {
    try {
        $stmt = $pdo->prepare("SELECT heure FROM heure WHERE id_superviseur = ?");
        $stmt->execute([$superviseurId]);
        $result = $stmt->fetch();
        return $result ? $result['heure'] : '07:30:00';
    } catch (Exception $e) {
        return '07:30:00';
    }
}

function getShopInfo($pdo, $shopId) {
    try {
        $stmt = $pdo->prepare("SELECT id, nom, adresse FROM shop WHERE id = ?");
        $stmt->execute([$shopId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

function getPenalite($pdo, $superviseurId) {
    try {
        $stmt = $pdo->prepare("SELECT retard FROM penalite WHERE id_superviseur = ?");
        $stmt->execute([$superviseurId]);
        $result = $stmt->fetch();
        return $result ? floatval($result['retard']) : 2.50;
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
            $retardId = $pdo->lastInsertId();
            return ['success' => true, 'presence_id' => $presenceId, 'retard_id' => $retardId];
        }

        return ['success' => true, 'presence_id' => $presenceId];
    } catch (Exception $e) {
        error_log("Erreur enregistrement: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function appliquerPenalite($pdo, $agentId, $montant, $retardId) {
    try {
        // Vérifier si une pénalité a déjà été appliquée aujourd'hui
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT r.id FROM retenu r 
                               JOIN retard rd ON r.id_retard = rd.id 
                               WHERE r.id_agent = ? AND DATE(rd.temps) = ?");
        $stmt->execute([$agentId, $today]);
        
        if ($stmt->fetch()) {
            return false; // Pénalité déjà appliquée
        }
        
        // Appliquer la pénalité
        $stmt = $pdo->prepare("INSERT INTO retenu (id_agent, id_retard, montant, moi) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$agentId, $retardId, $montant]);
        return true;
    } catch (Exception $e) {
        error_log("Erreur pénalité: " . $e->getMessage());
        return false;
    }
}

// ==================== VÉRIFICATION SESSION ====================

// Vérifier session superviseur
if (!isset($_SESSION['superviseur_id']) || !isset($_SESSION['shop_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    sendJSON(false, 'Non autorisé', ['redirect' => 'index.html']);
}

$superviseurId = $_SESSION['superviseur_id'];
$shopId = $_SESSION['shop_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur de connexion DB');
}

// ==================== ACTIONS API ====================

// Action: Vérifier statut et récupérer infos
if ($action === 'status') {
    $shopInfo = getShopInfo($pdo, $shopId);
    $heurePointage = getHeurePointage($pdo, $superviseurId);
    
    // Récupérer le nom du superviseur
    $stmt = $pdo->prepare("SELECT nom, prenom FROM superviseur WHERE id = ?");
    $stmt->execute([$superviseurId]);
    $superviseur = $stmt->fetch();
    
    sendJSON(true, 'Succès', [
        'superviseur_id' => $superviseurId,
        'superviseur_nom' => $superviseur ? $superviseur['nom'] . ' ' . $superviseur['prenom'] : 'Superviseur',
        'shop_id' => $shopId,
        'shop_nom' => $shopInfo ? $shopInfo['nom'] : 'Shop inconnu',
        'shop_adresse' => $shopInfo ? $shopInfo['adresse'] : '',
        'heure_pointage' => $heurePointage
    ]);
}

// Action: Scanner QR code
if ($action === 'scan') {
    $input = json_decode(file_get_contents('php://input'), true);
    $qrData = isset($input['qr_data']) ? $input['qr_data'] : '';

    if (empty($qrData)) {
        sendJSON(false, 'Données QR manquantes');
    }

    // Décoder les données du QR
    $data = json_decode($qrData, true);
    if (!$data || !isset($data['user_id']) || !isset($data['token']) || !isset($data['timestamp'])) {
        sendJSON(false, 'QR code invalide');
    }

    $agentId = $data['user_id'];
    $token = $data['token'];
    $timestamp = $data['timestamp'];

    // Vérifier si le token n'est pas trop vieux (15 secondes max)
    if (time() - $timestamp > 15) {
        sendJSON(false, 'QR code expiré (plus de 15 secondes)');
    }

    // Vérifier le token dans la base
    $stmt = $pdo->prepare("SELECT id, nom, prenom, token FROM agent WHERE id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();

    if (!$agent || $agent['token'] !== $token) {
        sendJSON(false, 'Token invalide');
    }

    // Vérifier si déjà pointé aujourd'hui
    if (aDejaPointeAujourdhui($pdo, $agentId)) {
        sendJSON(false, 'Cet agent a déjà pointé aujourd\'hui', [
            'agent' => [
                'nom' => $agent['nom'],
                'prenom' => $agent['prenom']
            ]
        ]);
    }

    // Récupérer l'heure de pointage
    $heurePointage = getHeurePointage($pdo, $superviseurId);
    $heureActuelle = date('H:i:s');
    
    // Comparer les heures
    $estEnRetard = $heureActuelle > $heurePointage;
    
    // Enregistrer la présence
    $result = enregistrerPresence($pdo, $agentId, $shopId, $estEnRetard);
    
    if (!$result['success']) {
        sendJSON(false, 'Erreur lors de l\'enregistrement');
    }

    // Si en retard, appliquer pénalité
    $penaliteMessage = '';
    $montantPenalite = 0;
    
    if ($estEnRetard) {
        // Vérifier si déjà en retard aujourd'hui
        if (!aDejaRetardAujourdhui($pdo, $agentId)) {
            $montantPenalite = getPenalite($pdo, $superviseurId);
            $penaliteAppliquee = appliquerPenalite($pdo, $agentId, $montantPenalite, $result['retard_id']);
            
            if ($penaliteAppliquee) {
                $penaliteMessage = " Pénalité de {$montantPenalite}$ appliquée.";
            }
        }
    }

    sendJSON(true, 'Pointage enregistré avec succès' . $penaliteMessage, [
        'agent' => [
            'nom' => $agent['nom'],
            'prenom' => $agent['prenom']
        ],
        'statut' => $estEnRetard ? 'RETARD' : 'À L\'HEURE',
        'heure_pointage' => $heureActuelle,
        'heure_requise' => $heurePointage,
        'penalite_appliquee' => $estEnRetard,
        'montant_penalite' => $montantPenalite
    ]);
}

// Action: Récupérer l'historique des scans de la journée
if ($action === 'history') {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT p.*, a.nom as agent_nom, a.prenom as agent_prenom, 
               s.nom as shop_nom,
               CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as est_retard
        FROM presence p
        JOIN agent a ON p.id_agent = a.id
        JOIN shop s ON p.id_shop = s.id
        LEFT JOIN retard r ON p.id_agent = r.id_agent AND DATE(p.date) = DATE(r.temps)
        WHERE p.id_shop = ? AND DATE(p.date) = ?
        ORDER BY p.date DESC
        LIMIT 50
    ");
    $stmt->execute([$shopId, $today]);
    $history = $stmt->fetchAll();
    
    sendJSON(true, 'Succès', ['history' => $history]);
}

// Action: Récupérer les statistiques du jour
if ($action === 'stats') {
    $today = date('Y-m-d');
    
    // Total présences aujourd'hui
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presence WHERE id_shop = ? AND DATE(date) = ?");
    $stmt->execute([$shopId, $today]);
    $totalPresences = $stmt->fetch()['total'];
    
    // Total retards aujourd'hui
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM presence p
        JOIN retard r ON p.id_agent = r.id_agent AND DATE(p.date) = DATE(r.temps)
        WHERE p.id_shop = ? AND DATE(p.date) = ?
    ");
    $stmt->execute([$shopId, $today]);
    $totalRetards = $stmt->fetch()['total'];
    
    sendJSON(true, 'Succès', [
        'total_presences' => $totalPresences,
        'total_retards' => $totalRetards,
        'taux_retard' => $totalPresences > 0 ? round(($totalRetards / $totalPresences) * 100, 1) : 0
    ]);
}

// Si aucune action correspondante
sendJSON(false, 'Action non valide');
?>