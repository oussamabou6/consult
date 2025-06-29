<?php
session_start();
require '../config/config.php'; // contient $pdo
// include également les settings si nécessaire

function getUserBalance($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

$userId = $_SESSION['user_id'];
$userBalance = getUserBalance($userId, $pdo);
$currency = isset($settings['currency']) ? $settings['currency'] : 'DA';

echo number_format($userBalance) . ' ' . $currency;
?>