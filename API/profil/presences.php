<?php
// API/profil/presences.php
session_start();
header('Content-Type: application/json');
require_once '../connexion/db.php';  // ✅ Utilise db.php

if (!isLoggedIn()) {
    sendJSON(false, 'Non connecté');
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : getCurrentUserId();
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur DB');
}

try {
    $presences = fetchAll($pdo, "
        SELECT p.*, s.nom as shop_nom,
               CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as est_retard
        FROM presence p
        LEFT JOIN shop s ON p.id_shop = s.id
        LEFT JOIN retard r ON p.id_agent = r.id_agent AND DATE(p.date) = DATE(r.temps)
        WHERE p.id_agent = ? AND MONTH(p.date) = ? AND YEAR(p.date) = ?
        ORDER BY p.date DESC
    ", [$userId, $month, $year]);
    
    $jours = array_unique(array_map(function($p) {
        return date('Y-m-d', strtotime($p['date']));
    }, $presences));
    
    sendJSON(true, 'Succès', [
        'total' => count($presences),
        'jours' => count($jours),
        'presences' => $presences
    ]);
} catch (Exception $e) {
    sendJSON(false, $e->getMessage());
}
?>