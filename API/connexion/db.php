<?php
// API/connexion/db.php
// Fichier de configuration centralisé pour la base de données

// Configuration de la base de données
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_NAME', 'if0_41083645_pointage_db ');
define('DB_USER', 'if0_41083645');
define('DB_PASS', 'TwbNlC3rhQTFY');

/**
 * Établit une connexion à la base de données
 * @return PDO|null Retourne l'objet PDO ou null en cas d'erreur
 */
function getDBConnection() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Activer les exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Mode de fetch par défaut
                PDO::ATTR_EMULATE_PREPARES => false, // Désactiver l'émulation des requêtes préparées
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4" // Forcer l'UTF-8
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Journaliser l'erreur (mais ne pas l'afficher en production)
        error_log("Erreur de connexion DB: " . $e->getMessage());
        return null;
    }
}

/**
 * Vérifie si un utilisateur est connecté
 * @return bool True si connecté, false sinon
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Récupère l'ID de l'utilisateur connecté
 * @return int|null L'ID ou null si non connecté
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Vérifie si un superviseur est connecté
 * @return bool True si connecté, false sinon
 */
function isSuperviseurLoggedIn() {
    return isset($_SESSION['superviseur_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Récupère l'ID du superviseur connecté
 * @return int|null L'ID ou null si non connecté
 */
function getCurrentSuperviseurId() {
    return isset($_SESSION['superviseur_id']) ? $_SESSION['superviseur_id'] : null;
}

/**
 * Récupère l'ID du shop sélectionné
 * @return int|null L'ID du shop ou null si non défini
 */
function getCurrentShopId() {
    return isset($_SESSION['shop_id']) ? $_SESSION['shop_id'] : null;
}

/**
 * Exécute une requête préparée de manière sécurisée
 * @param PDO $pdo Objet PDO
 * @param string $sql Requête SQL
 * @param array $params Paramètres
 * @return PDOStatement|false Le statement ou false en cas d'erreur
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erreur de requête: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère une seule ligne de résultat
 * @param PDO $pdo Objet PDO
 * @param string $sql Requête SQL
 * @param array $params Paramètres
 * @return array|false La ligne ou false
 */
function fetchOne($pdo, $sql, $params = []) {
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

/**
 * Récupère toutes les lignes de résultat
 * @param PDO $pdo Objet PDO
 * @param string $sql Requête SQL
 * @param array $params Paramètres
 * @return array Les lignes
 */
function fetchAll($pdo, $sql, $params = []) {
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Échappe les caractères spéciaux pour une utilisation dans MySQL
 * @param string $str Chaîne à échapper
 * @return string Chaîne échappée
 */
function escapeString($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Formate une date pour l'affichage
 * @param string $date Date au format MySQL
 * @param string $format Format de sortie
 * @return string Date formatée
 */
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '';
    $d = new DateTime($date);
    return $d->format($format);
}

/**
 * Formate une heure pour l'affichage
 * @param string $time Heure au format MySQL
 * @return string Heure formatée
 */
function formatTime($time) {
    if (!$time) return '';
    return date('H:i', strtotime($time));
}

/**
 * Vérifie si une table existe dans la base de données
 * @param PDO $pdo Objet PDO
 * @param string $table Nom de la table
 * @return bool True si existe
 */
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Récupère la configuration du système
 * @param PDO $pdo Objet PDO
 * @return array Configuration
 */
function getSystemConfig($pdo) {
    $config = [];
    
    // Récupérer le statut de connexion
    try {
        $stmt = $pdo->query("SELECT login FROM connexion WHERE id = 1");
        $result = $stmt->fetch();
        $config['login_status'] = $result ? $result['login'] : 'OFF';
    } catch (Exception $e) {
        $config['login_status'] = 'OFF';
    }
    
    // Récupérer l'heure de pointage par défaut
    try {
        $stmt = $pdo->query("SELECT heure FROM heure LIMIT 1");
        $result = $stmt->fetch();
        $config['default_heure'] = $result ? $result['heure'] : '07:00:00';
    } catch (Exception $e) {
        $config['default_heure'] = '07:00:00';
    }
    
    // Récupérer la pénalité par défaut
    try {
        $stmt = $pdo->query("SELECT retard FROM penalite LIMIT 1");
        $result = $stmt->fetch();
        $config['default_penalite'] = $result ? floatval($result['retard']) : 2.50;
    } catch (Exception $e) {
        $config['default_penalite'] = 2.50;
    }
    
    return $config;
}

/**
 * Nettoie une chaîne pour éviter les injections
 * @param string $data Chaîne à nettoyer
 * @return string Chaîne nettoyée
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Génère un token unique
 * @return string Token
 */
function generateToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Vérifie si un token est valide (pas expiré)
 * @param int $timestamp Timestamp de création
 * @param int $expireIn Durée de validité en secondes
 * @return bool True si valide
 */
function isTokenValid($timestamp, $expireIn = 15) {
    return (time() - $timestamp) <= $expireIn;
}

/**
 * Récupère les informations d'un agent
 * @param PDO $pdo Objet PDO
 * @param int $agentId ID de l'agent
 * @return array|false Informations ou false
 */
function getAgentInfo($pdo, $agentId) {
    return fetchOne($pdo, "SELECT id, nom, postnom, prenom, email, telephone FROM agent WHERE id = ?", [$agentId]);
}

/**
 * Récupère les informations d'un superviseur
 * @param PDO $pdo Objet PDO
 * @param int $superviseurId ID du superviseur
 * @return array|false Informations ou false
 */
function getSuperviseurInfo($pdo, $superviseurId) {
    return fetchOne($pdo, "SELECT id, nom, postnom, prenom, email, telephone FROM superviseur WHERE id = ?", [$superviseurId]);
}

/**
 * Journalise une action dans les logs
 * @param string $action Action effectuée
 * @param string $details Détails
 */
function logAction($action, $details = '') {
    $logFile = __DIR__ . '/../../logs/actions.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'anonymous';
    $logEntry = "[$timestamp] User: $userId - Action: $action - Details: $details" . PHP_EOL;
    
    // Créer le dossier logs s'il n'existe pas
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Envoie une réponse JSON
 * @param bool $success Succès ou échec
 * @param string $message Message
 * @param array $data Données supplémentaires
 */
function sendJSON($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

/**
 * Vérifie si la requête est en AJAX
 * @return bool True si AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Récupère l'adresse IP du client
 * @return string Adresse IP
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Démarre la session de manière sécurisée
 */
function secureSessionStart() {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

// Initialisation sécurisée de la session si appelé directement
if (basename($_SERVER['PHP_SELF']) == 'db.php') {
    header('Content-Type: application/json');
    sendJSON(false, 'Accès direct interdit');
}
?>