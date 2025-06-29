<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");
// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email']) || $_SESSION['user_role'] != 'client') {
    header("Location: ../config/logout.php");
    exit;
}
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;


// Get user profile if logged in
$userProfile = null;
if ($isLoggedIn) {
    $userProfileQuery = "SELECT * FROM user_profiles WHERE user_id = $userId";
    $userProfileResult = $conn->query($userProfileQuery);
    
    if ($userProfileResult && $userProfileResult->num_rows > 0) {
        $userProfile = $userProfileResult->fetch_assoc();
    }
}
// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email']) || $_SESSION['user_role'] != 'client') {
    header("Location: ../config/logout.php");
    exit;
}

// Fetch site settings
$settingsQuery = "SELECT * FROM settings";
$settingsResult = $conn->query($settingsQuery);
$settings = [];

if ($settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch navigation menu items
$menuQuery = "SELECT * FROM categories ORDER BY name ASC LIMIT 6";
$menuResult = $conn->query($menuQuery);
$menuItems = [];

if ($menuResult && $menuResult->num_rows > 0) {
    while ($row = $menuResult->fetch_assoc()) {
        $menuItems[] = $row;
    }
}

// Fetch all chat sessions for the user
$chatSessionsQuery = "SELECT cs.id, cs.expert_id, cs.client_id, cs.started_at, cs.status as chat_status,
                    c.status as consultation_status,
                    CASE 
                        WHEN cs.client_id = $userId THEN u1.full_name
                        ELSE u2.full_name
                    END as contact_name,
                    CASE 
                        WHEN cs.client_id = $userId THEN up1.profile_image
                        ELSE up2.profile_image
                    END as contact_image,
                    CASE 
                        WHEN cs.client_id = $userId THEN u1.status
                        ELSE u2.status
                    END as contact_status,
                    CASE 
                        WHEN cs.client_id = $userId THEN u1.id
                        ELSE u2.id
                    END as contact_id,
                    (SELECT message FROM chat_messages WHERE chat_session_id = cs.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM chat_messages WHERE chat_session_id = cs.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                    (SELECT COUNT(*) FROM chat_messages WHERE chat_session_id = cs.id AND receiver_id = $userId AND is_read = 0) as unread_count
                    FROM chat_sessions cs
                    JOIN users u1 ON cs.expert_id = u1.id
                    JOIN users u2 ON cs.client_id = u2.id
                    JOIN consultations c ON cs.consultation_id = c.id
                    LEFT JOIN user_profiles up1 ON u1.id = up1.user_id
                    LEFT JOIN user_profiles up2 ON u2.id = up2.user_id
                    WHERE cs.expert_id = $userId OR cs.client_id = $userId
                    ORDER BY last_message_time DESC";

$chatSessionsResult = $conn->query($chatSessionsQuery);
$conversations = [];
$activeConversations = [];
$endedConversations = [];

if ($chatSessionsResult && $chatSessionsResult->num_rows > 0) {
    while ($row = $chatSessionsResult->fetch_assoc()) {
        // Separate active and ended conversations
        if ($row['chat_status'] === 'active' && $row['consultation_status'] !== 'completed' && $row['consultation_status'] !== 'cancelled') {
            $activeConversations[] = $row;
        } else {
            $endedConversations[] = $row;
        }
    }
    // Combine active conversations first, then ended ones
    $conversations = array_merge($activeConversations, $endedConversations);
}

// Get the selected chat session
$selectedChatSessionId = isset($_GET['chat_session_id']) ? intval($_GET['chat_session_id']) : (count($endedConversations) > 0 ? $endedConversations[0]['id'] : null);
$selectedChatSession = null;
$contactId = null;
$contactName = null;
$contactImage = null;
$contactStatus = null;
$isChatActive = false;

// Fetch messages for the selected chat session
$messages = [];
if ($selectedChatSessionId) {
    // Verify the user is part of this chat session
    $verifyQuery = "SELECT cs.*, c.status as consultation_status 
                   FROM chat_sessions cs
                   JOIN consultations c ON cs.consultation_id = c.id
                   WHERE cs.id = $selectedChatSessionId 
                   AND (cs.expert_id = $userId OR cs.client_id = $userId)";
    $verifyResult = $conn->query($verifyQuery);
    
    if ($verifyResult && $verifyResult->num_rows > 0) {
        $selectedChatSession = $verifyResult->fetch_assoc();
        $isChatActive = ($selectedChatSession['status'] === 'active' && $selectedChatSession['consultation_status'] !== 'completed' && $selectedChatSession['consultation_status'] !== 'cancelled');
        
        // Determine the contact (the other person in the chat session)
        if ($selectedChatSession['expert_id'] == $userId) {
            $contactId = $selectedChatSession['client_id'];
        } else {
            $contactId = $selectedChatSession['expert_id'];
        }
        
        // Get contact details
        $contactQuery = "SELECT u.full_name, u.status, up.profile_image 
                        FROM users u
                        LEFT JOIN user_profiles up ON u.id = up.user_id
                        WHERE u.id = $contactId";
        $contactResult = $conn->query($contactQuery);
        
        if ($contactResult && $contactResult->num_rows > 0) {
            $contact = $contactResult->fetch_assoc();
            $contactName = $contact['full_name'];
            $contactImage = $contact['profile_image'];
            $contactStatus = $contact['status'];
        }
        
        // Fetch messages
        $messagesQuery = "SELECT m.*, u.full_name as sender_name, up.profile_image as sender_image
                         FROM chat_messages m
                         JOIN users u ON m.sender_id = u.id
                         LEFT JOIN user_profiles up ON u.id = up.user_id
                         WHERE m.chat_session_id = $selectedChatSessionId
                         ORDER BY m.created_at ASC";
        $messagesResult = $conn->query($messagesQuery);
        
        if ($messagesResult && $messagesResult->num_rows > 0) {
            while ($row = $messagesResult->fetch_assoc()) {
                $messages[] = $row;
            }
            
            // Mark messages as read
            $markReadQuery = "UPDATE chat_messages 
                             SET is_read = 1 
                             WHERE chat_session_id = $selectedChatSessionId 
                             AND receiver_id = $userId 
                             AND is_read = 0";
            $conn->query($markReadQuery);
        }
    } else {
        // Invalid chat session ID
        $selectedChatSessionId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $chatSessionId = $conn->real_escape_string($_POST['chat_session_id']);
    $messageContent = $conn->real_escape_string($_POST['message_content']);
    $receiverId = $conn->real_escape_string($_POST['receiver_id']);
    
    // Validate chat session exists and user is part of it
    $validateQuery = "SELECT cs.*, c.status as consultation_status 
                     FROM chat_sessions cs
                     JOIN consultations c ON cs.consultation_id = c.id
                     WHERE cs.id = $chatSessionId 
                     AND (cs.expert_id = $userId OR cs.client_id = $userId)";
    $validateResult = $conn->query($validateQuery);
    
    if ($validateResult && $validateResult->num_rows > 0) {
        $chatSession = $validateResult->fetch_assoc();
        
        // Check if chat is active and consultation is not completed or cancelled
        if ($chatSession['status'] === 'active' && $chatSession['consultation_status'] !== 'completed' && $chatSession['consultation_status'] !== 'cancelled') {
            // Determine sender type
            $senderType = 'client';
            if ($chatSession['expert_id'] == $userId) {
                $senderType = 'expert';
            }
            
            // Insert the message
            $insertMessageQuery = "INSERT INTO chat_messages (sender_id, receiver_id, message, is_read, sender_type, created_at, chat_session_id) 
                                  VALUES ($userId, $receiverId, '$messageContent', 0, '$senderType', NOW(), $chatSessionId)";
            
            if ($conn->query($insertMessageQuery)) {
                // Send notification to receiver
                $notificationQuery = "INSERT INTO expert_notifications (user_id, message, is_read, created_at) 
                                     VALUES ($receiverId, 'You have received a new message.', 0, NOW())";
                $conn->query($notificationQuery);
                
                // Redirect to refresh the page
                header("Location: messages.php?chat_session_id=$chatSessionId");
                exit();
            } else {
                $messageError = "Failed to send message. Please try again.";
            }
        } else {
            $messageError = "This conversation has ended. You cannot send new messages.";
        }
    } else {
        $messageError = "Invalid chat session. Please try again.";
    }
}

// Fetch notifications for logged-in user
$notifications = [];
$notificationCount = 0;
if ($isLoggedIn) {
    $notificationsQuery = "SELECT * FROM client_notifications 
                          WHERE user_id = $userId AND is_read = 0
                          ORDER BY created_at DESC
                          LIMIT 5";
    $notificationsResult = $conn->query($notificationsQuery);
    
    if ($notificationsResult && $notificationsResult->num_rows > 0) {
        $notificationCount = $notificationsResult->num_rows;
        while ($row = $notificationsResult->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
}

// Fetch user balance
$userBalance = 0;
$balanceQuery = "SELECT balance FROM users WHERE id = $userId";
$balanceResult = $conn->query($balanceQuery);

if ($balanceResult && $balanceResult->num_rows > 0) {
    $userBalance = $balanceResult->fetch_assoc()['balance'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
    <!-- Favicon -->
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- Custom CSS -->
    <style>
        :root {
            /* Primary Colors */
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-200: #bfdbfe;
            --primary-300: #93c5fd;
            --primary-400: #60a5fa;
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-700: #1d4ed8;
            --primary-800: #1e40af;
            --primary-900: #1e3a8a;
            --primary-950: #172554;
            
            /* Secondary Colors */
            --secondary-50: #f5f3ff;
            --secondary-100: #ede9fe;
            --secondary-200: #ddd6fe;
            --secondary-300: #c4b5fd;
            --secondary-400: #a78bfa;
            --secondary-500: #8b5cf6;
            --secondary-600: #7c3aed;
            --secondary-700: #6d28d9;
            --secondary-800: #5b21b6;
            --secondary-900: #4c1d95;
            --secondary-950: #2e1065;
            
            /* Accent Colors */
            --accent-50: #fdf2f8;
            --accent-100: #fce7f3;
            --accent-200: #fbcfe8;
            --accent-300: #f9a8d4;
            --accent-400: #f472b6;
            --accent-500: #ec4899;
            --accent-600: #db2777;
            --accent-700: #be185d;
            --accent-800: #9d174d;
            --accent-900: #831843;
            --accent-950: #500724;
            
            /* Success Colors */
            --success-50: #ecfdf5;
            --success-100: #d1fae5;
            --success-200: #a7f3d0;
            --success-300: #6ee7b7;
            --success-400: #34d399;
            --success-500: #10b981;
            --success-600: #059669;
            --success-700: #047857;
            --success-800: #065f46;
            --success-900: #064e3b;
            --success-950: #022c22;
            
            /* Warning Colors */
            --warning-50: #fffbeb;
            --warning-100: #fef3c7;
            --warning-200: #fde68a;
            --warning-300: #fcd34d;
            --warning-400: #fbbf24;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            --warning-700: #b45309;
            --warning-800: #92400e;
            --warning-900: #78350f;
            --warning-950: #451a03;
            
            /* Danger Colors */
            --danger-50: #fef2f2;
            --danger-100: #fee2e2;
            --danger-200: #fecaca;
            --danger-300: #fca5a5;
            --danger-400: #f87171;
            --danger-500: #ef4444;
            --danger-600: #dc2626;
            --danger-700: #b91c1c;
            --danger-800: #991b1b;
            --danger-900: #7f1d1d;
            --danger-950: #450a0a;
            
            /* Gray Colors */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --gray-950: #030712;
            
            /* UI Variables */
            --border-radius-sm: 0.375rem;
            --border-radius: 0.5rem;
            --border-radius-md: 0.75rem;
            --border-radius-lg: 1rem;
            --border-radius-xl: 1.5rem;
            --border-radius-2xl: 2rem;
            --border-radius-3xl: 3rem;
            --border-radius-full: 9999px;
            
            --box-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --box-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --box-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --box-shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --box-shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Font Sizes */
            --text-xs: 0.75rem;
            --text-sm: 0.875rem;
            --text-base: 1rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
            --text-4xl: 2.25rem;
            --text-5xl: 3rem;
            --text-6xl: 3.75rem;
            --text-7xl: 4.5rem;
            --text-8xl: 6rem;
            --text-9xl: 8rem;
        }
        
        /* Base Styles */
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            line-height: 1.3;
            color: var(--gray-900);
        }
        
        a {
            text-decoration: none;
            color: var(--primary-600);
            transition: var(--transition);
        }
        
        a:hover {
            color: var(--primary-700);
        }
        
        /* Navbar */
        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--box-shadow);
            transition: all 0.4s ease;
            padding: 20px 0;
            z-index: 1000;
        }
        
        .navbar.scrolled {
            padding: 12px 0;
            box-shadow: var(--box-shadow-md);
            background-color: rgba(255, 255, 255, 0.98);
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--primary-600);
            transition: transform 0.3s ease;
            font-family: 'Manrope', sans-serif;
        }
        
        .navbar-brand:hover {
            transform: scale(1.05);
            color: var(--primary-700);
        }
        
        .navbar-brand img {
            height: 40px;
            transition: var(--transition);
        }
        
        .navbar.scrolled .navbar-brand img {
            height: 35px;
        }
        
        .nav-link {
            position: relative;
            margin: 0 12px;
            padding: 8px 0;
            font-weight: 600;
            color: var(--gray-700);
            transition: color 0.3s ease;
            font-size: 0.95rem;
        }
        
        .nav-link:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-600);
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .nav-link:hover {
            color: var(--primary-600);
        }
        
        .nav-link:hover:after, .nav-link.active:after {
            width: 100%;
        }
        
        .nav-link.active {
            color: var(--primary-600);
            font-weight: 700;
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(124, 58, 237, 0.9));
            color: white;
            padding: 80px 0 50px;
            border-radius: 0 0 var(--border-radius-3xl) var(--border-radius-3xl);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 0;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            z-index: 0;
        }
        
        .page-header-content {
            position: relative;
            z-index: 1;
        }
        
        .page-title {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Messages Container */
        .messages-container {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-lg);
            overflow: hidden;
            margin-bottom: 40px;
            height: calc(100vh - 300px);
            min-height: 500px;
        }
        
        .conversation-list {
            height: 100%;
            border-right: 1px solid var(--gray-200);
            overflow-y: auto;
        }
        
        .conversation-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            background-color: var(--gray-50);
        }
        
        .conversation-tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            font-weight: 600;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
        }
        
        .conversation-tab.active {
            color: var(--primary-600);
            border-bottom-color: var(--primary-600);
            background-color: white;
        }
        
        .conversation-tab:hover:not(.active) {
            background-color: var(--gray-100);
            color: var(--gray-800);
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid var(--gray-200);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .conversation-item:hover {
            background-color: var(--gray-100);
        }
        
        .conversation-item.active {
            background-color: var(--primary-50);
            border-left: 3px solid var(--primary-600);
        }
        
        .conversation-item.ended {
            opacity: 0.7;
        }
        
        .conversation-item.ended::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.3);
            pointer-events: none;
        }
        
        .conversation-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
            z-index: 1;
        }
        
        .status-active {
            background-color: var(--success-100);
            color: var(--success-800);
            border: 1px solid var(--success-200);
        }
        
        .status-ended {
            background-color: var(--gray-100);
            color: var(--gray-800);
            border: 1px solid var(--gray-200);
        }
        
        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            background-color: var(--gray-200);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border: 2px solid white;
        }
        
        .conversation-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--gray-900);
        }
        
        .conversation-last-message {
            font-size: var(--text-sm);
            color: var(--gray-600);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        
        .conversation-time {
            font-size: var(--text-xs);
            color: var(--gray-500);
        }
        
        .unread-badge {
            background-color: var(--primary-600);
            color: white;
            border-radius: var(--border-radius-full);
            padding: 2px 8px;
            font-size: var(--text-xs);
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
        }
        
        .chat-area {
            display: flex;
            flex-direction: column;
            height: 100%;
            background-color: var(--gray-50);
            position: relative;
        }
        
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-200);
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            z-index: 10;
        }
        
        .chat-header-status {
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 20px;
            margin-left: 10px;
            font-weight: 600;
        }
        
        .contact-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            box-shadow: 0 0 0 2px white;
        }
        
        .status-online {
            background-color: var(--success-500);
        }
        
        .status-offline {
            background-color: var(--gray-500);
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: var(--gray-50);
            background-image: 
                radial-gradient(circle at 25px 25px, rgba(0, 0, 0, 0.01) 2%, transparent 0%), 
                radial-gradient(circle at 75px 75px, rgba(0, 0, 0, 0.01) 2%, transparent 0%);
            background-size: 100px 100px;
        }
        
        .message {
            margin-bottom: 20px;
            max-width: 70%;
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-sent {
            margin-left: auto;
            background-color: var(--primary-100);
            color: var(--primary-900);
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 var(--border-radius-lg);
            padding: 12px 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .message-received {
            margin-right: auto;
            background-color: white;
            color: var(--gray-800);
            border-radius: 0 var(--border-radius-lg) var(--border-radius-lg) var(--border-radius-lg);
            padding: 12px 15px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .message-time {
            font-size: var(--text-xs);
            color: var(--gray-500);
            margin-top: 5px;
            text-align: right;
        }
        
        .message-sender {
            font-size: var(--text-xs);
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--gray-700);
        }
        
        .message-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            position: absolute;
            bottom: -5px;
        }
        
        .message-sent .message-avatar {
            right: -10px;
        }
        
        .message-received .message-avatar {
            left: -10px;
        }
        
        .chat-date-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .chat-date-divider::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background-color: var(--gray-200);
            z-index: 1;
        }
        
        .chat-date {
            background-color: var(--gray-50);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: var(--text-xs);
            color: var(--gray-600);
            position: relative;
            z-index: 2;
            display: inline-block;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }
        
        .chat-input {
            padding: 15px 20px;
            border-top: 1px solid var(--gray-200);
            background-color: white;
            position: relative;
            z-index: 10;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .chat-input-form {
            display: flex;
            gap: 10px;
        }
        
        .chat-input-field {
            flex: 1;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-full);
            padding: 12px 20px;
            font-size: var(--text-base);
            transition: var(--transition);
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .chat-input-field:focus {
            border-color: var(--primary-600);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
            outline: none;
        }
        
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 20px;
            text-align: center;
            color: var(--gray-500);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--gray-300);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            font-weight: 600;
            border-radius: var(--border-radius-full);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
            font-size: var(--text-base);
            letter-spacing: 0.5px;
            box-shadow: var(--box-shadow);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-700), var(--primary-800));
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .btn-icon {
            width: 48px;
            height: 48px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--accent-600);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: var(--text-xs);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--box-shadow);
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .conversation-list {
                height: 300px;
                border-right: none;
                border-bottom: 1px solid var(--gray-200);
            }
            
            .messages-container {
                height: auto;
            }
            
            .chat-area {
                height: 500px;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 40px;
            }
            
            .page-title {
                font-size: var(--text-2xl);
            }
        }
        
        @media (max-width: 576px) {
            .page-header {
                padding: 50px 0 30px;
            }
            
            .page-title {
                font-size: var(--text-xl);
            }
            
            .message {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <?php if(isset($settings['site_image']) && !empty($settings['site_image'])): ?>
                    <img src="../uploads/<?php echo $settings['site_image']; ?>" alt="<?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>" height="40">
                <?php else: ?>
                    <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="find-experts.php">Find Experts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact-support.php">Contact Support</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="messages.php">Messages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-consultations.php">My Consultations</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if($isLoggedIn): ?>
                        <div class="dropdown me-3">
                            <a class="position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" onclick="markNotifications  data-bs-toggle="dropdown" aria-expanded="false" onclick="markNotificationsAsRead()">
                                <i class="fas fa-bell fs-5 text-gray-700"></i>
                                <?php if($notificationCount > 0): ?>
                                    <span class="notification-badge" id="notification-badge"><?php echo $notificationCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" style="border-radius: 12px;">
                            <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
                            <div id="notifications-container" style="font-size:12px;">
                                <?php if(count($notifications) > 0): ?>
                                    <?php foreach($notifications as $notification): ?>
                                        <li>
                                            <a class="dropdown-item py-3 border-bottom" href="#">
                                                <small class="text-muted d-block"><?php echo date('M d, H:i', strtotime($notification['created_at'])); ?></small>
                                                <p class="mb-0 mt-1"><?php echo $notification['message']; ?></p>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>
                                <?php endif; ?>
                            </div>
                            <li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="notifications.php">View All</a></li>
                        </ul>
                        </div>
                        <div class="dropdown">
                            <a  id="btnclick" class= "btn btn-outline-primary dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" >
                                <?php if($userProfile && !empty($userProfile['profile_image'])): ?>
                                    <img src="<?php echo $userProfile['profile_image']; ?>" alt="Profile" class="rounded-circle" width="30" height="30">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                                <span class="d-none d-md-inline">
                                    <?php 
                                    // Get user's full name from database
                                    $userNameQuery = "SELECT full_name FROM users WHERE id = $userId";
                                    $userNameResult = $conn->query($userNameQuery);
                                    if ($userNameResult && $userNameResult->num_rows > 0) {
                                        echo $userNameResult->fetch_assoc()['full_name'];
                                    } else {
                                        echo "My Profile";
                                    }
                                    ?>
                                </span>
                                <?php if(isset($userBalance)): ?>
                                    <span class="ms-2 badge bg-success"><?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="userDropdown" style="border-radius: 12px;">
                                <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
                                <li><a class="dropdown-item py-2" href="add-fund.php"><i class="fas fa-wallet me-2 text-primary"></i> Add Fund: <?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></a></li>
                                <li><a class="dropdown-item py-2" href="my-consultations.php"><i class="fas fa-calendar-check me-2 text-primary"></i> My Consultations</a></li>
                                <li><a class="dropdown-item py-2" href="messages.php"><i class="fas fa-envelope me-2 text-primary"></i> Messages</a></li>
                                <li><a class="dropdown-item py-2" href="my-reports.php"><i class="fas fa-flag me-2 text-primary"></i> My Reports</a></li>
                            <li><a class="dropdown-item py-2" href="history-ratings.php"><i class="fas fa-solid fa-star text-primary"></i> Ratings</a></li>

                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2" href="../Config/logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="../pages/login.php" class="btn btn-outline-primary me-2">Login</a>
                        <a href="../pages/profile.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="page-header-content">
                <h1 class="page-title">Messages</h1>
                <p class="text-white opacity-75">Communicate with experts and manage your conversations</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <?php if(isset($messageError) && !empty($messageError)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $messageError; ?>
            </div>
        <?php endif; ?>

        <div class="messages-container">
            <div class="row g-0 h-100">
                <!-- Conversation List -->
                <div class="col-lg-4 conversation-list">
                    <div class="conversation-header py-3 px-3 bg-gray-50 border-bottom">
                        <h5 class="mb-0"><i class="fas fa-archive me-2"></i> Archived Conversations</h5>
                    </div>
                    
                    
                    
                    <div id="archivedConversations">
                        <?php if(count($endedConversations) > 0): ?>
                            <?php foreach($endedConversations as $conversation): ?>
                                <a href="messages.php?chat_session_id=<?php echo $conversation['id']; ?>" class="text-decoration-none">
                                    <div class="conversation-item ended <?php echo ($selectedChatSessionId == $conversation['id']) ? 'active' : ''; ?>">
                                        <span class="conversation-status-badge status-ended">Ended</span>
                                        <div class="d-flex align-items-center">
                                            <div class="position-relative me-3">
                                                <?php if(!empty($conversation['contact_image'])): ?>
                                                    <img src="<?php echo $conversation['contact_image']; ?>" alt="<?php echo $conversation['contact_name']; ?>" class="conversation-avatar">
                                                <?php else: ?>
                                                    <div class="conversation-avatar d-flex align-items-center justify-content-center bg-gray-400">
                                                        <i class="fas fa-user-circle fa-2x text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="conversation-name mb-0"><?php echo $conversation['contact_name']; ?></h6>
                                                    <small class="conversation-time"><?php echo date('d M', strtotime($conversation['last_message_time'])); ?></small>
                                                </div>
                                                <p class="conversation-last-message mb-0"><?php echo $conversation['last_message']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-archive"></i>
                                </div>
                                <h5>No archived conversations</h5>
                                <p>Completed conversations will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chat Area -->
                <div class="col-lg-8 chat-area">
                    <?php if($selectedChatSessionId && $contactId): ?>
                        <div class="chat-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="position-relative me-3">
                                        <?php if(!empty($contactImage)): ?>
                                            <img src="<?php echo $contactImage; ?>" alt="<?php echo $contactName; ?>" class="conversation-avatar">
                                        <?php else: ?>
                                            <div class="conversation-avatar d-flex align-items-center justify-content-center bg-primary">
                                                <i class="fas fa-user-circle fa-2x text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 d-flex align-items-center">
                                            <?php echo $contactName; ?>
                                            <span class="chat-header-status <?php echo $isChatActive ? 'status-active' : 'status-ended'; ?>">
                                                <?php echo $isChatActive ? 'Active' : 'Ended'; ?>
                                            </span>
                                        </h5>
                                        <p class="mb-0 small">
                                            <span class="contact-status <?php echo ($contactStatus == 'Online') ? 'status-online' : 'status-offline'; ?>"></span>
                                            <?php echo $contactStatus; ?>
                                        </p>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <?php if(count($messages) > 0): ?>
                                <?php 
                                $currentDate = null;
                                foreach($messages as $message): 
                                    $messageDate = date('Y-m-d', strtotime($message['created_at']));
                                    if($currentDate !== $messageDate): 
                                        $currentDate = $messageDate;
                                ?>
                                    <div class="chat-date-divider">
                                        <span class="chat-date"><?php echo date('F d, Y', strtotime($message['created_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                    <div class="message <?php echo ($message['sender_id'] == $userId) ? 'message-sent' : 'message-received'; ?>">
                                        <div class="message-sender"><?php echo $message['sender_name']; ?></div>
                                        <?php echo $message['message']; ?>
                                        <div class="message-time"><?php echo date('H:i', strtotime($message['created_at'])); ?></div>
                                        <?php if(!empty($message['sender_image'])): ?>
                                            <img src="<?php echo $message['sender_image']; ?>" alt="<?php echo $message['sender_name']; ?>" class="message-avatar">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted my-5">
                                    <i class="fas fa-comments fa-3x mb-3"></i>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                      <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h5>Select a conversation</h5>
                            <p>Choose a conversation from the list or start a new one.</p>
                            <a href="find-experts.php" class="btn btn-primary mt-3">Find Experts</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <!-- Custom JS -->
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            mirror: false,
            offset: 50
        });

        // Scroll chat to bottom
        function scrollChatToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Call on page load
        document.addEventListener('DOMContentLoaded', function() {
            scrollChatToBottom();
        });

        // Function to mark notifications as read
    function markNotificationsAsRead() {
        // Hide the notification badge immediately for better UX
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.style.display = 'none';
        }
        
        // Send AJAX request to mark notifications as read
        fetch('mark-notifications-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'mark_read' }),
        })
        .then(response => response.json())
        .then(data => {
            console.log('Notifications marked as read:', data);
        })
        .catch(error => {
            console.error('Error marking notifications as read:', error);
        });
    }
    
        // Auto-refresh messages every 10 seconds
        setInterval(function() {
            const chatSessionId = <?php echo $selectedChatSessionId ? $selectedChatSessionId : 'null'; ?>;
            
            if (chatSessionId) {
                // Create a new XMLHttpRequest
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'messages.php?chat_session_id=' + chatSessionId + '&_=' + new Date().getTime(), true);
                
                // Set response type
                xhr.responseType = 'document';
                
                // Handle response
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        // Get the chat messages from the response
                        const newChatMessages = xhr.response.querySelector('#chatMessages');
                        
                        // Replace the current chat messages with the new ones
                        if (newChatMessages) {
                            const currentChatMessages = document.querySelector('#chatMessages');
                            if (currentChatMessages) {
                                const wasAtBottom = currentChatMessages.scrollHeight - currentChatMessages.scrollTop <= currentChatMessages.clientHeight + 100;
                                
                                currentChatMessages.innerHTML = newChatMessages.innerHTML;
                                
                                // If the user was at the bottom, scroll to bottom again
                                if (wasAtBottom) {
                                    scrollChatToBottom();
                                }
                            }
                        }
                    }
                };
                
                // Send request
                xhr.send();
            }
        }, 10000);

        // Add this JavaScript code at the end of the file, just before the closing </body> tag

// Function to mark notifications as read
function markNotificationsAsRead() {
    // Hide the notification badge immediately for better UX
    const badge = document.getElementById('notification-badge');
    if (badge) {
        badge.style.display = 'none';
    }
    
    // Send AJAX request to mark notifications as read
    fetch('mark-notifications-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'mark_read' }),
    })
    .then(response => response.json())
    .then(data => {
        console.log('Notifications marked as read:', data);
    })
    .catch(error => {
        console.error('Error marking notifications as read:', error);
    });
}

// Function to fetch notifications
function fetchNotifications() {
    fetch('get-notifications.php')
        .then(response => response.json())
        .then(data => {
            // Update notification badge
            const badge = document.getElementById('notification-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                    badge.style.display = 'flex';
                } else {
                    const newBadge = document.createElement('span');
                    newBadge.id = 'notification-badge';
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = data.count;
                    document.querySelector('#notificationDropdown').appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.style.display = 'none';
                }
            }
            
            // Update notification list
            const container = document.getElementById('notifications-container');
            if (container) {
                let html = '';
                if (data.notifications.length > 0) {
                    data.notifications.forEach(notification => {
                        html += `
                            <li>
                                <a class="dropdown-item py-3 border-bottom" href="#">
                                    <small class="text-muted d-block">${notification.date}</small>
                                    <p class="mb-0 mt-1">${notification.message}</p>
                                </a>
                            </li>
                        `;
                    });
                } else {
                    html = '<li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>';
                }
                container.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

// Fetch notifications every second
setInterval(fetchNotifications, 1000);

    </script>
</body>
</html>
