<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];
    
    // Vérifier si l'utilisateur est un expert
    $check_role_sql = "SELECT role FROM users WHERE id = ?";
    $check_role_stmt = $conn->prepare($check_role_sql);
    $check_role_stmt->bind_param("i", $user_id);
    $check_role_stmt->execute();
    $role_result = $check_role_stmt->get_result();
    $user_data = $role_result->fetch_assoc();
    $check_role_stmt->close();
    
    // Si l'utilisateur est un expert, vérifier les consultations en attente
    if ($user_data && $user_data['role'] == 'expert') {
        // Trouver toutes les consultations en attente pour cet expert
        $pending_consultations_sql = "SELECT id, client_id FROM consultations WHERE expert_id = ? AND status = 'pending'";
        $pending_stmt = $conn->prepare($pending_consultations_sql);
        $pending_stmt->bind_param("i", $user_id);
        $pending_stmt->execute();
        $pending_result = $pending_stmt->get_result();
        
        // Mettre à jour le statut des consultations en attente à "Canceled"
        $update_consultation_sql = "UPDATE consultations SET status = 'Canceled', rejection_reason = 'Expert went offline' WHERE id = ?";
        $update_consultation_stmt = $conn->prepare($update_consultation_sql);
        
        // Préparer l'insertion de notifications
        $insert_notification_sql = "INSERT INTO client_notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())";
        $insert_notification_stmt = $conn->prepare($insert_notification_sql);
        
        // Pour chaque consultation en attente
        while ($consultation = $pending_result->fetch_assoc()) {
            // Mettre à jour le statut de la consultation
            $update_consultation_stmt->bind_param("i", $consultation['id']);
            $update_consultation_stmt->execute();
            
            // Envoyer une notification au client
            $notification_message = "Your consultation request with " . $_SESSION['full_name'] . " was automatically cancelled because the expert went offline.";
            $insert_notification_stmt->bind_param("is", $consultation['client_id'], $notification_message);
            $insert_notification_stmt->execute();
        }
        
        // Fermer les déclarations
        $pending_stmt->close();
        $update_consultation_stmt->close();
        $insert_notification_stmt->close();
    }
    // Si l'utilisateur est un client, vérifier les consultations en attente
    else if ($user_data && $user_data['role'] == 'client') {
        // Trouver toutes les consultations en attente pour ce client
        $pending_consultations_sql = "SELECT id, expert_id FROM consultations WHERE client_id = ? AND status = 'pending'";
        $pending_stmt = $conn->prepare($pending_consultations_sql);
        $pending_stmt->bind_param("i", $user_id);
        $pending_stmt->execute();
        $pending_result = $pending_stmt->get_result();
        
        // Mettre à jour le statut des consultations en attente à "Canceled"
        $update_consultation_sql = "UPDATE consultations SET status = 'Canceled', rejection_reason = 'Client went offline' WHERE id = ?";
        $update_consultation_stmt = $conn->prepare($update_consultation_sql);
        
        // Préparer l'insertion de notifications pour les experts
        $insert_notification_sql = "INSERT INTO expert_notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())";
        $insert_notification_stmt = $conn->prepare($insert_notification_sql);
        
        // Pour chaque consultation en attente
        while ($consultation = $pending_result->fetch_assoc()) {
            // Mettre à jour le statut de la consultation
            $update_consultation_stmt->bind_param("i", $consultation['id']);
            $update_consultation_stmt->execute();
            
            // Envoyer une notification à l'expert
            $notification_message = "A consultation request from " . $_SESSION['full_name'] . " was automatically cancelled because the client went offline.";
            $insert_notification_stmt->bind_param("is", $consultation['expert_id'], $notification_message);
            $insert_notification_stmt->execute();
        }
        
        // Fermer les déclarations
        $pending_stmt->close();
        $update_consultation_stmt->close();
        $insert_notification_stmt->close();
    }
    
    // Mettre à jour le statut de l'utilisateur à "Offline"
    $update_status_sql = "UPDATE users SET status = 'Offline', last_login = NOW() WHERE id = ?";
    $update_status_stmt = $conn->prepare($update_status_sql);
    $update_status_stmt->bind_param("i", $user_id);
    $update_status_stmt->execute();
    $update_status_stmt->close();
    
    // Fermer la connexion à la base de données
    $conn->close();
}

// Supprimer toutes les variables de session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
// header("Location: ../index.php?logout=success");
header("Location: ../pages/login.php");
exit();
?>
