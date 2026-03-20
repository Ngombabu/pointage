<?php
// ADMIN/api/crud.php
session_start();
header('Content-Type: application/json');
require_once '../../API/connexion/db.php';

if (!isset($_SESSION['admin_id'])) {
    sendJSON(false, 'Non autorisé');
}

$pdo = getDBConnection();
if (!$pdo) {
    sendJSON(false, 'Erreur de connexion DB');
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    switch ($action) {
        case 'superviseur':
            // Ajouter ou modifier superviseur
            $id = isset($_POST['id']) ? intval($_POST['id']) : null;
            $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
            $postnom = isset($_POST['postnom']) ? trim($_POST['postnom']) : '';
            $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            if (empty($nom) || empty($prenom) || empty($email)) {
                sendJSON(false, 'Champs requis manquants');
            }

            // Vérifier si l'email existe déjà
            $checkStmt = $pdo->prepare("SELECT id FROM superviseur WHERE email = ?" . ($id ? " AND id != ?" : ""));
            $checkParams = [$email];
            if ($id) $checkParams[] = $id;
            $checkStmt->execute($checkParams);
            if ($checkStmt->fetch()) {
                sendJSON(false, 'Cet email est déjà utilisé');
            }

            if ($id) {
                // Modification
                if (!empty($password)) {
                    $stmt = $pdo->prepare("
                        UPDATE superviseur 
                        SET nom = ?, postnom = ?, prenom = ?, email = ?, telephone = ?, password = ?
                        WHERE id = ?
                    ");
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->execute([$nom, $postnom, $prenom, $email, $telephone, $hashedPassword, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE superviseur 
                        SET nom = ?, postnom = ?, prenom = ?, email = ?, telephone = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nom, $postnom, $prenom, $email, $telephone, $id]);
                }
                $message = 'Superviseur modifié avec succès';
            } else {
                // Ajout
                if (empty($password)) {
                    sendJSON(false, 'Mot de passe requis pour un nouveau superviseur');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO superviseur (nom, postnom, prenom, email, telephone, password, image_supervieseur) 
                    VALUES (?, ?, ?, ?, ?, ?, '')
                ");
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$nom, $postnom, $prenom, $email, $telephone, $hashedPassword]);
                $message = 'Superviseur ajouté avec succès';
            }
            break;

        case 'agent':
            // Ajouter ou modifier agent
            $id = isset($_POST['id']) ? intval($_POST['id']) : null;
            $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
            $postnom = isset($_POST['postnom']) ? trim($_POST['postnom']) : '';
            $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
            $id_superviseur = isset($_POST['superviseur']) ? intval($_POST['superviseur']) : $_SESSION['admin_id'];
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            if (empty($nom) || empty($prenom) || empty($email)) {
                sendJSON(false, 'Champs requis manquants');
            }

            // Vérifier si l'email existe déjà
            $checkStmt = $pdo->prepare("SELECT id FROM agent WHERE email = ?" . ($id ? " AND id != ?" : ""));
            $checkParams = [$email];
            if ($id) $checkParams[] = $id;
            $checkStmt->execute($checkParams);
            if ($checkStmt->fetch()) {
                sendJSON(false, 'Cet email est déjà utilisé');
            }

            if ($id) {
                // Modification
                if (!empty($password)) {
                    $stmt = $pdo->prepare("
                        UPDATE agent 
                        SET nom = ?, postnom = ?, prenom = ?, email = ?, telephone = ?, id_superviseur = ?, password = ?
                        WHERE id = ?
                    ");
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->execute([$nom, $postnom, $prenom, $email, $telephone, $id_superviseur, $hashedPassword, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE agent 
                        SET nom = ?, postnom = ?, prenom = ?, email = ?, telephone = ?, id_superviseur = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nom, $postnom, $prenom, $email, $telephone, $id_superviseur, $id]);
                }
                $message = 'Agent modifié avec succès';
            } else {
                // Ajout
                if (empty($password)) {
                    sendJSON(false, 'Mot de passe requis pour un nouvel agent');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO agent (nom, postnom, prenom, email, telephone, id_superviseur, password, token, image_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, '', '')
                ");
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$nom, $postnom, $prenom, $email, $telephone, $id_superviseur, $hashedPassword]);
                $message = 'Agent ajouté avec succès';
            }
            break;

        case 'shop':
            // Ajouter ou modifier shop
            $id = isset($_POST['id']) ? intval($_POST['id']) : null;
            $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
            $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
            $id_superviseur = isset($_POST['superviseur']) ? intval($_POST['superviseur']) : $_SESSION['admin_id'];

            if (empty($nom) || empty($adresse)) {
                sendJSON(false, 'Champs requis manquants');
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE shop SET nom = ?, adresse = ?, id_superviseur = ? WHERE id = ?");
                $stmt->execute([$nom, $adresse, $id_superviseur, $id]);
                $message = 'Shop modifié avec succès';
            } else {
                $stmt = $pdo->prepare("INSERT INTO shop (nom, adresse, id_superviseur) VALUES (?, ?, ?)");
                $stmt->execute([$nom, $adresse, $id_superviseur]);
                $message = 'Shop ajouté avec succès';
            }
            break;

        case 'retenue':
            // Ajouter retenue manuelle
            $agent_id = isset($_POST['agentid']) ? intval($_POST['agentid']) : 0;
            $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0;
            $motif = isset($_POST['motif']) ? trim($_POST['motif']) : 'Retenue manuelle';

            if ($agent_id <= 0 || $montant <= 0) {
                sendJSON(false, 'Agent et montant requis');
            }

            // Vérifier que l'agent existe
            $checkStmt = $pdo->prepare("SELECT id FROM agent WHERE id = ?");
            $checkStmt->execute([$agent_id]);
            if (!$checkStmt->fetch()) {
                sendJSON(false, 'Agent non trouvé');
            }

            $stmt = $pdo->prepare("
                INSERT INTO retenu (id_agent, id_retard, montant, moi) 
                VALUES (?, NULL, ?, NOW())
            ");
            $stmt->execute([$agent_id, $montant]);
            $message = 'Retenue ajoutée avec succès';
            break;

        case 'delete_superviseur':
            // Supprimer superviseur (admin seulement)
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            // Vérifier que c'est bien le premier superviseur
            $stmt = $pdo->query("SELECT MIN(id) as min_id FROM superviseur");
            $minId = $stmt->fetch()['min_id'];
            if ($_SESSION['admin_id'] != $minId) {
                sendJSON(false, 'Seul le premier superviseur peut supprimer');
            }

            if ($id == $minId) {
                sendJSON(false, 'Impossible de supprimer le premier superviseur');
            }

            $stmt = $pdo->prepare("DELETE FROM superviseur WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Superviseur supprimé avec succès';
            break;

        case 'delete_agent':
            // Supprimer agent
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $stmt = $pdo->prepare("DELETE FROM agent WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Agent supprimé avec succès';
            break;

        case 'delete_shop':
            // Supprimer shop
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $stmt = $pdo->prepare("DELETE FROM shop WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Shop supprimé avec succès';
            break;

        case 'delete_retenue':
            // Supprimer retenue
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $stmt = $pdo->prepare("DELETE FROM retenu WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Retenue supprimée avec succès';
            break;

        default:
            sendJSON(false, 'Action non valide');
    }

    sendJSON(true, $message);

} catch (Exception $e) {
    error_log("Erreur CRUD: " . $e->getMessage());
    sendJSON(false, 'Erreur lors de l\'opération: ' . $e->getMessage());
}
?>