<?php
// API/qr_code/generate_qr.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once '../../API/connexion/db.php';  // ✅ Utilise db.php

try {
    require_once('../../phpqrcode-master/qrlib.php');

    if (!isLoggedIn()) {
        sendJSON(false, 'Non connecté');
    }

    $userId = getCurrentUserId();
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'generate') {
        $pdo = getDBConnection();
        if (!$pdo) {
            sendJSON(false, 'Erreur DB');
        }

        $user = fetchOne($pdo, "SELECT id, nom, postnom, prenom FROM agent WHERE id = ?", [$userId]);
        if (!$user) {
            $user = fetchOne($pdo, "SELECT id, nom, postnom, prenom FROM superviseur WHERE id = ?", [$userId]);
        }

        if (!$user) {
            sendJSON(false, 'Utilisateur non trouvé');
        }

        $timestamp = time();
        $token = hash('sha256', $userId . '|' . $timestamp . '|' . uniqid());
        
        $qrData = json_encode([
            'user_id' => $userId,
            'nom' => $user['nom'],
            'prenom' => $user['prenom'] ?? '',
            'token' => $token,
            'timestamp' => $timestamp
        ]);

        $fileName = 'user_' . $userId . '_qr.png';
        $filePath = QR_PATH . $fileName;
        $relativePath = 'SRC/QR/' . $fileName;

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        QRcode::png($qrData, $filePath, QR_ECLEVEL_L, 10);

        if (!file_exists($filePath)) {
            sendJSON(false, 'Échec création fichier');
        }

        updateRow($pdo, 'agent', ['token' => $token, 'image_agent' => $relativePath], 'id = ?', [$userId]);

        sendJSON(true, 'QR généré', [
            'qr_path' => $relativePath,
            'qr_url' => '/POINTAGE/' . $relativePath,
            'token' => $token,
            'timestamp' => $timestamp,
            'expires_in' => 15
        ]);
    }

    if ($action === 'verify') {
        $token = $_GET['token'] ?? '';
        $pdo = getDBConnection();
        if ($pdo) {
            $user = fetchOne($pdo, "SELECT token FROM agent WHERE id = ?", [$userId]);
            sendJSON(true, 'Vérification', ['valid' => ($user && $user['token'] === $token)]);
        }
    }

    sendJSON(false, 'Action non valide');

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    sendJSON(false, 'Erreur serveur: ' . $e->getMessage());
}
?>