<?php
// ADMIN/api/super_login.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../API/connexion/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(false, 'Méthode non autorisée');
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($email) || empty($password)) {
    sendJSON(false, 'Email et mot de passe requis');
}

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur de connexion à la base de données');
}

try {
    // Récupérer le superviseur
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email, password FROM superviseur WHERE email = ?");
    $stmt->execute([$email]);
    $superviseur = $stmt->fetch();

    if (!$superviseur || !password_verify($password, $superviseur['password'])) {
        sendJSON(false, 'Email ou mot de passe incorrect');
    }

    // Vérifier si c'est le premier superviseur (plus petit ID)
    $stmt = $pdo->query("SELECT MIN(id) as min_id FROM superviseur");
    $minId = $stmt->fetch()['min_id'];
    $isFirst = ($superviseur['id'] == $minId);

    // Créer la session
    $_SESSION['admin_id'] = $superviseur['id'];
    $_SESSION['admin_nom'] = $superviseur['nom'] . ' ' . $superviseur['prenom'];
    $_SESSION['admin_email'] = $superviseur['email'];
    $_SESSION['admin_is_first'] = $isFirst;
    $_SESSION['admin_logged_in'] = true;

    sendJSON(true, 'Connexion réussie', [
        'superviseur' => [
            'id' => $superviseur['id'],
            'nom' => $superviseur['nom'],
            'prenom' => $superviseur['prenom'],
            'email' => $superviseur['email'],
            'is_first' => $isFirst
        ]
    ]);

} catch (Exception $e) {
    error_log("Erreur login superviseur: " . $e->getMessage());
    sendJSON(false, 'Erreur serveur');
}
?>