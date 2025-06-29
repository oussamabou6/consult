-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 09 mai 2025 à 01:36
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `consult_pro`
--

-- --------------------------------------------------------

--
-- Structure de la table `admin_bank_accounts`
--

CREATE TABLE `admin_bank_accounts` (
  `id` int(11) NOT NULL,
  `account_type` enum('ccp','rip') NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `key_number` varchar(50) DEFAULT NULL,
  `rip_number` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `admin_bank_accounts`
--

INSERT INTO `admin_bank_accounts` (`id`, `account_type`, `account_number`, `bank_name`, `key_number`, `rip_number`, `created_at`, `updated_at`) VALUES
(1, 'ccp', '123456789012', 'Algérie Poste', '45', NULL, '2025-04-13 15:16:51', NULL),
(2, 'rip', '987654321098', 'Banque Nationale d\'Algérie', NULL, 'RIP00123456789', '2025-04-13 15:16:51', NULL),
(3, 'ccp', '34576890', 'ccp', '45', '', '2025-04-13 15:57:27', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL COMMENT 'Type of notification: new_profile, profile_updated, etc.',
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `user_id`, `profile_id`, `notification_type`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(1, 0, 0, '', 'New support request from user #52 regarding consultation #16', NULL, 1, '2025-04-22 19:23:24'),
(2, 52, 0, 'fund_request', 'Nouvelle demande d\'ajout de fonds de 2000 DA', 1, 1, '2025-04-24 18:38:41'),
(3, 51, 27, 'withdrawal_request', 'New withdrawal request of 2444 DA from expert #51', NULL, 1, '2025-04-25 10:38:52'),
(4, 51, 27, 'withdrawal_request', 'New withdrawal request of 2800 DA from expert #51', NULL, 1, '2025-04-25 11:03:03'),
(5, 51, 27, 'support_request', 'Nouvelle demande de support de l\'expert #51 concernant: rthtyh', 4, 1, '2025-04-25 14:37:05'),
(6, 51, 27, 'support_reply', 'Nouvelle réponse de l\'expert #51 à la demande de support #4', 4, 1, '2025-04-25 14:39:42'),
(7, 51, 27, 'support_reply', 'Nouvelle réponse de l\'expert #51 à la demande de support #4', 4, 1, '2025-04-25 14:54:47'),
(8, 51, 27, 'support_reply', 'Nouvelle réponse de l\'expert #51 à la demande de support #4', 4, 1, '2025-04-25 14:54:58'),
(9, 51, 27, 'withdrawal_request', 'New withdrawal request of 6100 DA from expert #51', NULL, 1, '2025-04-25 18:41:16'),
(10, 54, 28, 'new_profile', 'New expert profile submitted for review', NULL, 1, '2025-04-25 19:54:56'),
(11, 54, 28, 'banking_submitted', 'Expert banking information submitted for review', NULL, 1, '2025-04-25 19:56:10'),
(12, 51, 27, 'withdrawal_request', 'New withdrawal request of 7200 DA from expert #51', NULL, 1, '2025-04-26 12:39:27'),
(13, 54, 28, 'withdrawal_request', 'New withdrawal request of 200 DA from expert #54', NULL, 1, '2025-04-26 14:35:45'),
(14, 54, 28, 'support_request', 'Nouvelle demande de support de l\'expert #54 concernant: Oussama', 5, 1, '2025-04-26 20:10:22'),
(15, 54, 28, 'support_reply', 'Nouvelle réponse de l\'expert #54 à la demande de support #5', 5, 1, '2025-04-26 20:14:24'),
(16, 52, 0, 'support_request', 'New support request from user #52 regarding: problem', NULL, 1, '2025-04-27 11:19:27'),
(17, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 11:42:57'),
(18, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 11:56:29'),
(19, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 11:56:53'),
(20, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:20'),
(21, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:31'),
(22, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:32'),
(23, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:32'),
(24, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:33'),
(25, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:33'),
(26, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:34'),
(27, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:35'),
(28, 52, 0, 'support_request', 'New support request from user #52 regarding: Aya', NULL, 1, '2025-04-27 12:15:35'),
(29, 51, 27, 'report', 'New report from expert #51 regarding client #52', 62, 1, '2025-04-27 18:17:41'),
(30, 0, 0, '', 'New report from user #52 regarding consultation #75', NULL, 1, '2025-04-27 23:36:31'),
(31, 0, 0, '', 'New report from user #52 regarding consultation #75', NULL, 1, '2025-04-27 23:37:46'),
(32, 0, 0, '', 'New report from user #52 regarding consultation #75', NULL, 1, '2025-04-27 23:38:31'),
(33, 54, 28, 'report', 'New report from expert #54 regarding client #52', 79, 1, '2025-04-28 12:04:56'),
(34, 67, 29, 'new_profile', 'New expert profile submitted for review', NULL, 1, '2025-04-28 13:19:37'),
(35, 67, 29, 'banking_submitted', 'Expert banking information submitted for review', NULL, 1, '2025-04-28 13:27:14'),
(36, 68, 0, 'fund_request', 'New fund request of 10000 DA', 2, 1, '2025-04-28 14:24:35'),
(37, 68, 0, 'fund_request', 'New fund request of 10000 DA', 3, 1, '2025-04-28 14:29:13'),
(38, 0, 0, '', 'New report from user #68 regarding consultation #83', NULL, 1, '2025-04-28 14:49:30'),
(39, 54, 28, 'withdrawal_request', 'New withdrawal request of 9000 DA from expert #54', NULL, 1, '2025-04-28 15:22:24'),
(40, 54, 28, 'withdrawal_request', 'New withdrawal request of 2255 DA from expert #54', NULL, 1, '2025-04-30 19:26:40'),
(41, 54, 28, 'report', 'New report from expert #54 regarding client #52', 107, 1, '2025-04-30 20:40:12'),
(42, 0, 0, '', 'New report from user #68 regarding consultation #118', NULL, 1, '2025-05-01 20:14:12'),
(43, 0, 0, '', 'New report from user #68 regarding consultation #120', NULL, 1, '2025-05-01 20:18:30'),
(44, 0, 0, '', 'New report from user #68 regarding consultation #119', NULL, 1, '2025-05-01 20:18:51'),
(45, 0, 0, '', 'New report from user #68 regarding consultation #134', NULL, 1, '2025-05-01 20:21:47'),
(46, 0, 0, '', 'New report from user #68 regarding consultation #133', NULL, 1, '2025-05-01 20:22:20'),
(47, 0, 0, '', 'New report from user #68 regarding consultation #132', NULL, 1, '2025-05-01 20:22:43'),
(48, 0, 0, '', 'New report from user #68 regarding consultation #131', NULL, 1, '2025-05-01 20:23:18'),
(49, 0, 0, '', 'New report from user #68 regarding consultation #129', NULL, 1, '2025-05-01 20:23:41'),
(50, 0, 0, '', 'New report from user #68 regarding consultation #130', NULL, 1, '2025-05-01 20:24:05'),
(51, 0, 0, '', 'New report from user #68 regarding consultation #127', NULL, 1, '2025-05-01 20:24:28'),
(52, 0, 0, '', 'New report from user #68 regarding consultation #128', NULL, 1, '2025-05-01 20:24:56'),
(53, 0, 0, '', 'New report from user #68 regarding consultation #125', NULL, 1, '2025-05-01 20:25:17'),
(54, 0, 0, '', 'New report from user #68 regarding consultation #126', NULL, 1, '2025-05-01 20:25:41'),
(55, 0, 0, '', 'New report from user #68 regarding consultation #121', NULL, 1, '2025-05-01 20:25:59'),
(56, 0, 0, '', 'New report from user #68 regarding consultation #123', NULL, 1, '2025-05-01 20:26:30'),
(57, 0, 0, '', 'New report from user #68 regarding consultation #124', NULL, 1, '2025-05-01 20:26:57'),
(58, 0, 0, '', 'New report from user #68 regarding consultation #122', NULL, 1, '2025-05-01 20:27:26'),
(59, 68, 0, 'support_request', 'New support request from user #68 regarding: rzegdf', NULL, 1, '2025-05-01 21:13:55'),
(60, 68, 0, 'support_request', 'New support request from user #68 regarding: yfyuigikyk', NULL, 1, '2025-05-01 21:14:33'),
(61, 68, 0, 'fund_request', 'New fund request of 1 DA', 4, 1, '2025-05-01 21:15:22'),
(62, 119, 39, 'new_profile', 'New expert profile submitted for review', NULL, 1, '2025-05-06 23:32:50'),
(63, 119, 39, 'new_profile', 'A new expert profile has been submitted for review', NULL, 1, '2025-05-07 00:35:53'),
(64, 0, 0, '', 'New report from user #52 regarding consultation #227', NULL, 1, '2025-05-07 10:59:05');

-- --------------------------------------------------------

--
-- Structure de la table `banking_information`
--

CREATE TABLE `banking_information` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `ccp` varchar(50) NOT NULL,
  `ccp_key` varchar(20) NOT NULL,
  `check_file_path` varchar(255) NOT NULL,
  `consultation_minutes` int(11) NOT NULL,
  `consultation_price` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `banking_information`
--

INSERT INTO `banking_information` (`id`, `user_id`, `profile_id`, `ccp`, `ccp_key`, `check_file_path`, `consultation_minutes`, `consultation_price`, `created_at`, `updated_at`) VALUES
(19, 51, 27, '0040079688', '22', '../uploads/checks/check_51_1744905133.png', 5, 500, '2025-04-17 15:52:13', '2025-04-17 15:52:13'),
(20, 54, 28, '2423546', '345', '../uploads/checks/check_54_1745610970.png', 5, 1000, '2025-04-25 19:56:10', '2025-04-25 19:56:10'),
(21, 67, 29, '37348235', '74', '../uploads/checks/check_67_1745846834.png', 6, 789, '2025-04-28 13:27:14', '2025-04-28 13:27:14'),
(22, 110, 110, '1234567890', '12', '', 60, 1500, '2025-04-29 23:35:33', '2025-04-29 23:35:33'),
(23, 111, 111, '2345678901', '23', '', 45, 2500, '2025-04-29 23:35:47', '2025-04-29 23:35:47'),
(24, 112, 112, '3456789012', '34', '', 50, 3000, '2025-04-29 23:35:56', '2025-04-29 23:35:56'),
(25, 113, 113, '4567890123', '45', '', 30, 4000, '2025-04-29 23:36:06', '2025-04-29 23:36:06'),
(26, 114, 114, '5678901234', '56', '', 60, 2000, '2025-04-29 23:36:14', '2025-04-29 23:36:14'),
(27, 115, 115, '6789012345', '67', '', 45, 5000, '2025-04-29 23:36:22', '2025-04-29 23:36:22'),
(28, 116, 116, '7890123456', '78', '', 60, 3500, '2025-04-29 23:36:32', '2025-04-29 23:36:32'),
(29, 117, 117, '8901234567', '89', '', 30, 1800, '2025-04-29 23:36:45', '2025-04-29 23:36:45'),
(30, 118, 118, '9012345678', '90', '', 45, 1500, '2025-04-29 23:36:51', '2025-04-29 23:36:51'),
(33, 119, 39, '1234567', '89', 'uploads/checks/check_119_1746577556.jpg', 10, 1000, '2025-05-07 00:25:56', '2025-05-07 00:25:56');

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
(1, 'Education', '2025-04-29 13:47:36'),
(2, 'Finance', '2025-04-29 13:47:36'),
(3, 'Healthcare', '2025-04-29 13:47:36'),
(4, 'Technology', '2025-04-29 13:47:36'),
(5, 'Business & Consulting', '2025-04-29 13:47:36'),
(6, 'Legal Services', '2025-04-29 13:47:36'),
(7, 'Engineering & Architecture', '2025-04-29 13:47:36'),
(8, 'Creative & Media', '2025-04-29 13:47:36'),
(9, 'Real Estate', '2025-04-29 13:47:36'),
(10, 'Hospitality & Tourism', '2025-04-29 13:47:36');

-- --------------------------------------------------------

--
-- Structure de la table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `certificate_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `institution` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `certificates`
--

INSERT INTO `certificates` (`id`, `profile_id`, `section_id`, `certificate_id`, `start_date`, `end_date`, `institution`, `file_path`, `description`, `created_at`, `status`, `rejection_reason`) VALUES
(34, 27, 1, 1, '2025-04-01', '2025-04-06', 'lisence', '../uploads/certificates/cert_51_1_1744905119.png', 'stamboli', '2025-04-17 15:51:59', 'approved', NULL),
(35, 28, 1, 1, '2025-04-01', '2025-04-12', 'lisence', '../uploads/certificates/cert_54_1_1745610896.jpg', 'scqsd', '2025-04-25 19:54:56', 'approved', NULL),
(36, 29, 1, 1, '2025-04-16', '2025-04-26', 'lisence', '../uploads/certificates/cert_67_1_1745846377.jpg', 'zscqsdc', '2025-04-28 13:19:37', 'pending', NULL),
(37, 39, 1, 1, '2010-06-04', '2025-05-01', 'lisence', '../uploads/certificates/cert_119_1_1746574370.jpg', 'MEMOIRE', '2025-05-06 23:32:50', 'pending', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('active','closed','archived') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_message_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sender_type` enum('client','expert','admin') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `chat_session_id` int(11) NOT NULL,
  `message_type` enum('text','image','file','voice') NOT NULL DEFAULT 'text'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `sender_id`, `receiver_id`, `consultation_id`, `message`, `file_path`, `is_read`, `sender_type`, `created_at`, `updated_at`, `chat_session_id`, `message_type`) VALUES
(1, 52, 51, 12, 'oijk^po', NULL, 0, 'client', '2025-04-22 14:29:19', '2025-04-22 14:29:19', 13, 'text'),
(2, 52, 51, 12, 'jnjn$', NULL, 0, 'client', '2025-04-22 14:29:23', '2025-04-22 14:29:23', 13, 'text'),
(3, 52, 51, 12, 'hnuinhuio', NULL, 0, 'client', '2025-04-22 14:29:29', '2025-04-22 14:29:29', 13, 'text'),
(4, 52, 51, 12, 'iujh', NULL, 0, 'client', '2025-04-22 14:31:53', '2025-04-22 14:31:53', 13, 'text'),
(5, 52, 51, 12, 'uinuinhuinhoi', NULL, 0, 'client', '2025-04-22 14:31:57', '2025-04-22 14:31:57', 13, 'text'),
(6, 52, 51, 12, 'uinuinhuinhoi', NULL, 0, 'client', '2025-04-22 14:32:02', '2025-04-22 14:32:02', 13, 'text'),
(7, 52, 51, 12, 'Aya', NULL, 0, 'client', '2025-04-22 14:32:11', '2025-04-22 14:32:11', 13, 'text'),
(8, 52, 51, 12, 'Oussama', NULL, 0, 'client', '2025-04-22 14:32:18', '2025-04-22 14:32:18', 13, 'text'),
(9, 52, 51, 12, 'Oussama', NULL, 0, 'client', '2025-04-22 14:32:36', '2025-04-22 14:32:36', 13, 'text'),
(10, 52, 51, 12, 'Oussama', NULL, 0, 'client', '2025-04-22 14:32:44', '2025-04-22 14:32:44', 13, 'text'),
(11, 52, 51, 12, 'aya', NULL, 0, 'client', '2025-04-22 14:32:51', '2025-04-22 14:32:51', 13, 'text'),
(12, 52, 51, 12, 'xsuihjndpci', NULL, 0, 'client', '2025-04-22 14:33:06', '2025-04-22 14:33:06', 13, 'text'),
(13, 52, 51, 12, 'poek,;cpoze;', NULL, 0, 'client', '2025-04-22 14:33:11', '2025-04-22 14:33:11', 13, 'text'),
(14, 52, 51, 13, 'TYFGUI', NULL, 0, 'client', '2025-04-22 18:08:52', '2025-04-22 18:08:52', 14, 'text'),
(15, 51, 52, 13, 'UYGUH', NULL, 1, 'expert', '2025-04-22 18:08:58', '2025-04-24 14:48:13', 14, 'text'),
(16, 52, 51, 13, 'OLJ', NULL, 0, 'client', '2025-04-22 18:09:01', '2025-04-22 18:09:01', 14, 'text'),
(17, 51, 52, 13, 'UHIL', NULL, 1, 'expert', '2025-04-22 18:09:05', '2025-04-24 14:48:13', 14, 'text'),
(18, 51, 52, 13, 'UYGUI', NULL, 1, 'expert', '2025-04-22 18:09:10', '2025-04-24 14:48:13', 14, 'text'),
(19, 52, 51, 13, 'JHBIOLK', NULL, 0, 'client', '2025-04-22 18:09:13', '2025-04-22 18:09:13', 14, 'text'),
(20, 52, 51, 13, 'UIHIOJ', NULL, 0, 'client', '2025-04-22 18:09:16', '2025-04-22 18:09:16', 14, 'text'),
(21, 51, 52, 13, 'TGUIH', NULL, 1, 'expert', '2025-04-22 18:09:23', '2025-04-24 14:48:13', 14, 'text'),
(22, 51, 52, 13, 'FGCGH?VJK/', NULL, 1, 'expert', '2025-04-22 18:09:27', '2025-04-24 14:48:13', 14, 'text'),
(23, 51, 52, 13, 'TGUIHPO', NULL, 1, 'expert', '2025-04-22 18:09:31', '2025-04-24 14:48:13', 14, 'text'),
(24, 52, 51, 13, 'ILUHMOILK', NULL, 0, 'client', '2025-04-22 18:09:35', '2025-04-22 18:09:35', 14, 'text'),
(25, 52, 51, 13, 'UIJHPOM', NULL, 0, 'client', '2025-04-22 18:09:55', '2025-04-22 18:09:55', 14, 'text'),
(26, 52, 51, 13, 'MOIJM', NULL, 0, 'client', '2025-04-22 18:10:03', '2025-04-22 18:10:03', 14, 'text'),
(27, 51, 52, 13, 'HGCVHGB', NULL, 1, 'expert', '2025-04-22 18:10:11', '2025-04-24 14:48:13', 14, 'text'),
(28, 52, 51, 13, 'MOIJM', NULL, 0, 'client', '2025-04-22 18:10:48', '2025-04-22 18:10:48', 14, 'text'),
(29, 52, 51, 13, 'MOIJM', NULL, 0, 'client', '2025-04-22 18:11:12', '2025-04-22 18:11:12', 14, 'text'),
(30, 52, 51, 13, 'MOIJM', NULL, 0, 'client', '2025-04-22 18:11:16', '2025-04-22 18:11:16', 14, 'text'),
(31, 52, 51, 13, 'IUHPOK', NULL, 0, 'client', '2025-04-22 18:11:59', '2025-04-22 18:11:59', 14, 'text'),
(32, 52, 51, 13, 'IOJHIOJ', NULL, 0, 'client', '2025-04-22 18:12:03', '2025-04-22 18:12:03', 14, 'text'),
(33, 52, 51, 13, 'JHPOML', NULL, 0, 'client', '2025-04-22 18:12:07', '2025-04-22 18:12:07', 14, 'text'),
(34, 51, 52, 13, 'JKLNKL?', NULL, 1, 'expert', '2025-04-22 18:12:11', '2025-04-24 14:48:13', 14, 'text'),
(35, 51, 52, 13, 'JHKJNKL', NULL, 1, 'expert', '2025-04-22 18:12:27', '2025-04-24 14:48:13', 14, 'text'),
(36, 51, 52, 13, 'JHNL', NULL, 1, 'expert', '2025-04-22 18:13:26', '2025-04-24 14:48:13', 14, 'text'),
(37, 52, 51, 13, 'KJHL', NULL, 0, 'client', '2025-04-22 18:13:31', '2025-04-22 18:13:31', 14, 'text'),
(38, 52, 51, 13, 'JKHL', NULL, 0, 'client', '2025-04-22 18:13:35', '2025-04-22 18:13:35', 14, 'text'),
(39, 52, 51, 13, 'UKJBLK', NULL, 0, 'client', '2025-04-22 18:13:41', '2025-04-22 18:13:41', 14, 'text'),
(40, 52, 51, 13, 'UIHMO', NULL, 0, 'client', '2025-04-22 18:13:45', '2025-04-22 18:13:45', 14, 'text'),
(41, 51, 52, 13, 'ILJMKL', NULL, 1, 'expert', '2025-04-22 18:13:48', '2025-04-24 14:48:13', 14, 'text'),
(42, 51, 52, 13, 'LIJHMOLK', NULL, 1, 'expert', '2025-04-22 18:13:51', '2025-04-24 14:48:13', 14, 'text'),
(43, 52, 51, 14, 'o__ijm', NULL, 0, 'client', '2025-04-22 18:28:07', '2025-04-22 18:28:07', 15, 'text'),
(44, 51, 52, 14, 'uhiokpù', NULL, 1, 'expert', '2025-04-22 18:28:11', '2025-04-24 14:47:21', 15, 'text'),
(45, 51, 52, 14, 'reqhrhtfg', NULL, 1, 'expert', '2025-04-22 18:28:16', '2025-04-24 14:47:21', 15, 'text'),
(46, 52, 51, 14, 'yukvjh', NULL, 0, 'client', '2025-04-22 18:28:19', '2025-04-22 18:28:19', 15, 'text'),
(47, 52, 51, 14, 'bhil', NULL, 0, 'client', '2025-04-22 18:28:23', '2025-04-22 18:28:23', 15, 'text'),
(48, 52, 51, 14, 'bilkl', NULL, 0, 'client', '2025-04-22 18:28:27', '2025-04-22 18:28:27', 15, 'text'),
(49, 52, 51, 14, ',huhkui', NULL, 0, 'client', '2025-04-22 18:28:29', '2025-04-22 18:28:29', 15, 'text'),
(50, 51, 52, 14, 'uçol', NULL, 1, 'expert', '2025-04-22 18:28:32', '2025-04-24 14:47:21', 15, 'text'),
(51, 51, 52, 14, ';', NULL, 1, 'expert', '2025-04-22 18:28:34', '2025-04-24 14:47:21', 15, 'text'),
(52, 52, 51, 14, 'rfcvrcg', NULL, 0, 'client', '2025-04-22 18:30:14', '2025-04-22 18:30:14', 15, 'text'),
(53, 52, 51, 14, 'zefsehyt', NULL, 0, 'client', '2025-04-22 18:30:18', '2025-04-22 18:30:18', 15, 'text'),
(54, 52, 51, 14, 'tybhytxsgy', NULL, 0, 'client', '2025-04-22 18:30:23', '2025-04-22 18:30:23', 15, 'text'),
(55, 52, 51, 14, 'ouu', NULL, 0, 'client', '2025-04-22 18:30:27', '2025-04-22 18:30:27', 15, 'text'),
(56, 52, 51, 14, 'qxsqc', NULL, 0, 'client', '2025-04-22 18:31:49', '2025-04-22 18:31:49', 15, 'text'),
(57, 52, 51, 14, 'x w', NULL, 0, 'client', '2025-04-22 18:33:25', '2025-04-22 18:33:25', 15, 'text'),
(58, 51, 52, 14, 'xwx', NULL, 1, 'expert', '2025-04-22 18:33:38', '2025-04-24 14:47:21', 15, 'text'),
(59, 52, 51, 14, 'x w', NULL, 0, 'client', '2025-04-22 18:33:48', '2025-04-22 18:33:48', 15, 'text'),
(60, 52, 51, 14, 'x w', NULL, 0, 'client', '2025-04-22 18:44:48', '2025-04-22 18:44:48', 15, 'text'),
(61, 52, 51, 15, 'sdvgfrb', NULL, 0, 'client', '2025-04-22 19:06:54', '2025-04-22 19:06:54', 16, 'text'),
(62, 51, 52, 15, 'sdvvv', NULL, 1, 'expert', '2025-04-22 19:06:59', '2025-04-24 14:47:15', 16, 'text'),
(63, 52, 51, 15, 'dsgbv', NULL, 0, 'client', '2025-04-22 19:07:04', '2025-04-22 19:07:04', 16, 'text'),
(64, 51, 52, 15, 'dvxgsdb', NULL, 1, 'expert', '2025-04-22 19:07:08', '2025-04-24 14:47:15', 16, 'text'),
(65, 51, 52, 15, 'dvxgsdb', NULL, 1, 'expert', '2025-04-22 19:07:08', '2025-04-24 14:47:15', 16, 'text'),
(66, 51, 52, 15, 'f bfv', NULL, 1, 'expert', '2025-04-22 19:07:57', '2025-04-24 14:47:15', 16, 'text'),
(67, 52, 51, 18, 'hjklhkl', NULL, 0, 'client', '2025-04-23 11:39:44', '2025-04-23 11:39:44', 18, 'text'),
(68, 51, 52, 18, 'grjhy', NULL, 1, 'expert', '2025-04-23 11:40:17', '2025-04-24 14:47:27', 18, 'text'),
(69, 52, 51, 18, 'drjlr', NULL, 0, 'client', '2025-04-23 11:40:50', '2025-04-23 11:40:50', 18, 'text'),
(70, 52, 51, 18, 'drjlr', NULL, 0, 'client', '2025-04-23 11:42:34', '2025-04-23 11:42:34', 18, 'text'),
(71, 52, 51, 19, 'ئيقا', NULL, 0, 'client', '2025-04-24 09:59:52', '2025-04-24 09:59:52', 19, 'text'),
(72, 52, 51, NULL, 'erifjerolf', NULL, 0, 'client', '2025-04-24 14:47:45', '2025-04-24 14:47:45', 12, 'text'),
(73, 52, 51, NULL, 'erifjerolfOussama\r\n\r\n', NULL, 0, 'client', '2025-04-24 14:48:04', '2025-04-24 14:48:04', 12, 'text'),
(74, 51, 52, 40, 'ouussama', NULL, 1, 'expert', '2025-04-24 15:05:45', '2025-04-24 15:05:50', 37, 'text'),
(75, 52, 51, 40, 'ezfijze,', NULL, 1, 'client', '2025-04-24 15:06:10', '2025-04-24 19:37:36', 37, 'text'),
(76, 52, 51, 40, 'iefnkzfe$', NULL, 1, 'client', '2025-04-24 15:06:18', '2025-04-24 19:37:36', 37, 'text'),
(77, 52, 51, 40, 'oekfpoe,', NULL, 1, 'client', '2025-04-24 15:06:22', '2025-04-24 19:37:36', 37, 'text'),
(78, 52, 51, 40, 'azpojf,p^ze', NULL, 1, 'client', '2025-04-24 15:06:25', '2025-04-24 19:37:36', 37, 'text'),
(79, 52, 51, 41, 'dsvdvvfxc', NULL, 0, 'client', '2025-04-24 20:13:51', '2025-04-24 20:13:51', 38, 'text'),
(80, 52, 51, 41, 'tyyyyyyjy', NULL, 0, 'client', '2025-04-24 20:14:04', '2025-04-24 20:14:04', 38, 'text'),
(81, 52, 51, 41, 'sdvdv', NULL, 0, 'client', '2025-04-24 20:14:13', '2025-04-24 20:14:13', 38, 'text'),
(82, 51, 52, 42, 'sdvsv', NULL, 1, 'expert', '2025-04-24 21:15:16', '2025-04-24 21:46:52', 39, 'text'),
(83, 52, 51, 42, 'sdvfv', NULL, 0, 'client', '2025-04-24 21:15:20', '2025-04-24 21:15:20', 39, 'text'),
(84, 51, 52, 42, 'ergv', NULL, 1, 'expert', '2025-04-24 21:15:26', '2025-04-24 21:46:52', 39, 'text'),
(85, 52, 51, 42, 'ergt', NULL, 0, 'client', '2025-04-24 21:15:30', '2025-04-24 21:15:30', 39, 'text'),
(86, 52, 51, 42, 'nty', NULL, 0, 'client', '2025-04-24 21:15:34', '2025-04-24 21:15:34', 39, 'text'),
(87, 51, 52, 42, 'serg', NULL, 1, 'expert', '2025-04-24 21:15:42', '2025-04-24 21:46:52', 39, 'text'),
(88, 51, 52, 42, 'gfufyik', NULL, 1, 'expert', '2025-04-24 21:19:29', '2025-04-24 21:46:52', 39, 'text'),
(89, 52, 51, 42, 'gvjhkh;h', NULL, 0, 'client', '2025-04-24 21:19:37', '2025-04-24 21:19:37', 39, 'text'),
(90, 52, 51, 42, 'ghjoj', NULL, 0, 'client', '2025-04-24 21:28:26', '2025-04-24 21:28:26', 39, 'text'),
(91, 52, 51, 42, 'sdvfv', NULL, 0, 'client', '2025-04-24 21:49:35', '2025-04-24 21:49:35', 39, 'text'),
(92, 52, 51, 45, 'TGUIH', NULL, 0, 'client', '2025-04-24 22:42:23', '2025-04-24 22:42:23', 42, 'text'),
(93, 52, 51, 47, 'UYGUI', NULL, 0, 'client', '2025-04-24 22:54:03', '2025-04-24 22:54:03', 43, 'text'),
(94, 51, 52, 48, 'v', NULL, 0, 'expert', '2025-04-25 18:51:20', '2025-04-25 18:51:20', 44, 'text'),
(95, 52, 51, 48, 'xdc', NULL, 0, 'client', '2025-04-25 19:00:49', '2025-04-25 19:00:49', 44, 'text'),
(96, 51, 52, 51, 'vfxdf', NULL, 0, 'expert', '2025-04-25 19:41:31', '2025-04-25 19:41:31', 47, 'text'),
(97, 52, 51, 52, 'dsc xd', NULL, 0, 'client', '2025-04-25 19:42:15', '2025-04-25 19:42:15', 48, 'text'),
(98, 51, 52, 52, 'dsv sd', NULL, 0, 'expert', '2025-04-25 19:42:17', '2025-04-25 19:42:17', 48, 'text'),
(99, 51, 52, 52, 'vwdfx', NULL, 0, 'expert', '2025-04-25 19:42:19', '2025-04-25 19:42:19', 48, 'text'),
(100, 51, 52, 52, 'sdv sdv', NULL, 0, 'expert', '2025-04-25 19:42:21', '2025-04-25 19:42:21', 48, 'text'),
(101, 51, 52, 52, 'dsvdv', NULL, 0, 'expert', '2025-04-25 19:42:24', '2025-04-25 19:42:24', 48, 'text'),
(102, 52, 51, 52, 'dsv ds', NULL, 0, 'client', '2025-04-25 19:42:26', '2025-04-25 19:42:26', 48, 'text'),
(103, 52, 51, 52, 'dsv d', NULL, 0, 'client', '2025-04-25 19:42:28', '2025-04-25 19:42:28', 48, 'text'),
(104, 52, 51, 52, 'fdbvf', NULL, 0, 'client', '2025-04-25 19:42:31', '2025-04-25 19:42:31', 48, 'text'),
(105, 52, 51, 52, 'fdbvf', NULL, 0, 'client', '2025-04-25 19:42:34', '2025-04-25 19:42:34', 48, 'text'),
(106, 51, 52, 52, 'FGCGH?VJK/', NULL, 0, 'expert', '2025-04-25 19:43:11', '2025-04-25 19:43:11', 48, 'text'),
(107, 52, 51, 52, 'سير', NULL, 0, 'client', '2025-04-25 19:44:06', '2025-04-25 19:44:06', 48, 'text'),
(108, 52, 51, 52, 'سير', NULL, 0, 'client', '2025-04-25 19:44:29', '2025-04-25 19:44:29', 48, 'text'),
(109, 51, 52, 52, 'dvcdf', NULL, 0, 'expert', '2025-04-25 19:44:48', '2025-04-25 19:44:48', 48, 'text'),
(110, 51, 52, 52, 'Ouss', NULL, 0, 'expert', '2025-04-25 19:44:54', '2025-04-25 19:44:54', 48, 'text'),
(111, 52, 51, 52, 'yes', NULL, 0, 'client', '2025-04-25 19:44:58', '2025-04-25 19:44:58', 48, 'text'),
(112, 52, 51, 52, 'dffcvsd', NULL, 0, 'client', '2025-04-25 19:45:50', '2025-04-25 19:45:50', 48, 'text'),
(113, 51, 52, 52, 'sqcsc', NULL, 0, 'expert', '2025-04-25 19:45:53', '2025-04-25 19:45:53', 48, 'text'),
(114, 52, 51, 52, 'cdscdx', NULL, 0, 'client', '2025-04-25 19:47:23', '2025-04-25 19:47:23', 48, 'text'),
(117, 54, 51, NULL, 'ouusama', NULL, 1, '', '2025-04-25 20:15:57', '2025-04-25 20:16:08', 0, 'text'),
(118, 51, 54, NULL, 'ghbfg', '', 1, '', '2025-04-25 20:17:02', '2025-04-25 20:18:59', 0, 'text'),
(119, 51, 54, NULL, 'v cv', '', 1, '', '2025-04-25 20:17:06', '2025-04-25 20:18:59', 0, 'text'),
(120, 51, 54, NULL, 'fs f fvd fg', '', 1, 'client', '2025-04-25 20:18:39', '2025-04-25 20:18:59', 0, 'text'),
(121, 51, 54, NULL, 'this is my love', '../uploads/chat_files/680bee44471f6_1745612356.jpg', 1, 'client', '2025-04-25 20:19:16', '2025-04-25 20:19:31', 0, 'image'),
(122, 54, 51, NULL, 'efsd', '', 1, 'client', '2025-04-25 20:19:31', '2025-04-30 19:34:32', 0, 'text'),
(123, 52, 54, 53, 'dscd', NULL, 0, 'client', '2025-04-25 20:22:27', '2025-04-25 20:22:27', 49, 'text'),
(124, 52, 54, 53, 'tyht', NULL, 0, 'client', '2025-04-25 20:24:33', '2025-04-25 20:24:33', 49, 'text'),
(125, 54, 52, 53, 'fbf', NULL, 1, 'expert', '2025-04-25 20:24:41', '2025-04-25 20:36:36', 49, 'text'),
(126, 54, 1, NULL, 'ouss', NULL, 1, 'expert', '2025-04-26 21:15:43', '2025-04-26 21:17:10', 0, 'text'),
(127, 1, 54, NULL, 'jkgjhkl*', NULL, 1, '', '2025-04-26 21:17:17', '2025-04-26 21:17:36', 0, 'text'),
(128, 54, 1, NULL, 'rtyjkl', NULL, 1, 'expert', '2025-04-26 21:17:36', '2025-04-26 21:17:38', 0, 'text'),
(129, 1, 54, NULL, 'mkm;', NULL, 1, '', '2025-04-26 21:18:00', '2025-04-26 21:24:50', 0, 'text'),
(130, 1, 54, NULL, 'llmljh', NULL, 1, '', '2025-04-26 21:24:44', '2025-04-26 21:24:50', 0, 'text'),
(131, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 21:24:50', '2025-04-26 21:24:54', 0, 'text'),
(132, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 22:14:35', '2025-04-26 22:14:36', 0, 'text'),
(133, 1, 54, NULL, 'scqs', NULL, 1, '', '2025-04-26 22:14:42', '2025-04-26 22:14:45', 0, 'text'),
(134, 1, 54, NULL, 'scsqc', NULL, 1, '', '2025-04-26 22:14:57', '2025-04-26 22:15:00', 0, 'text'),
(135, 1, 54, NULL, 'zscdefcedcqs', NULL, 1, '', '2025-04-26 22:15:10', '2025-04-26 22:15:10', 0, 'text'),
(136, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 22:20:25', '2025-04-26 22:20:26', 0, 'text'),
(137, 1, 54, NULL, ';nk;,n;,;kgvghsdrgseq', NULL, 1, '', '2025-04-26 22:20:48', '2025-04-26 22:20:50', 0, 'text'),
(138, 54, 1, NULL, 'jkhkjbjb;k', NULL, 1, 'expert', '2025-04-26 22:20:57', '2025-04-26 22:20:57', 0, 'text'),
(139, 54, 1, NULL, 'kgkugvkjbhl:', NULL, 1, 'expert', '2025-04-26 22:21:04', '2025-04-26 22:21:05', 0, 'text'),
(140, 54, 1, NULL, 'pn,lkhlbl', NULL, 1, 'expert', '2025-04-26 22:21:08', '2025-04-26 22:21:09', 0, 'text'),
(141, 54, 1, NULL, 'ln,;$$', NULL, 1, 'expert', '2025-04-26 22:21:11', '2025-04-26 22:21:11', 0, 'text'),
(142, 54, 1, NULL, 'bn,jo', NULL, 1, 'expert', '2025-04-26 22:21:13', '2025-04-26 22:21:13', 0, 'text'),
(143, 1, 54, NULL, 'yuruè_iuhl', NULL, 1, '', '2025-04-26 22:21:22', '2025-04-26 22:21:25', 0, 'text'),
(144, 54, 1, NULL, 'ytgjug', NULL, 1, 'expert', '2025-04-26 22:21:55', '2025-04-26 22:21:56', 0, 'text'),
(145, 54, 1, NULL, 'ytujygk', NULL, 1, 'expert', '2025-04-26 22:21:59', '2025-04-26 22:21:59', 0, 'text'),
(146, 1, 54, NULL, 'tryhfhy', NULL, 1, '', '2025-04-26 22:22:04', '2025-04-26 22:22:06', 0, 'text'),
(147, 54, 1, NULL, 'moko', NULL, 1, 'expert', '2025-04-26 22:29:50', '2025-04-26 22:30:21', 0, 'text'),
(148, 54, 1, NULL, 'ih', NULL, 1, 'expert', '2025-04-26 22:29:52', '2025-04-26 22:30:21', 0, 'text'),
(149, 54, 1, NULL, 'iohoj^', NULL, 1, 'expert', '2025-04-26 22:29:56', '2025-04-26 22:30:21', 0, 'text'),
(150, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 22:30:07', '2025-04-26 22:30:21', 0, 'text'),
(151, 54, 1, NULL, 'oijop', NULL, 1, 'expert', '2025-04-26 22:30:13', '2025-04-26 22:30:21', 0, 'text'),
(152, 54, 1, NULL, 'hihk', NULL, 1, 'expert', '2025-04-26 22:30:14', '2025-04-26 22:30:21', 0, 'text'),
(153, 54, 1, NULL, 'jnik*', NULL, 1, 'expert', '2025-04-26 22:30:15', '2025-04-26 22:30:21', 0, 'text'),
(154, 54, 1, NULL, 'pjik', NULL, 1, 'expert', '2025-04-26 22:30:17', '2025-04-26 22:30:21', 0, 'text'),
(155, 1, 54, NULL, 'iobhù\r\nnh', NULL, 1, '', '2025-04-26 22:30:29', '2025-04-26 22:30:32', 0, 'text'),
(156, 1, 54, NULL, '_hiohn', NULL, 1, '', '2025-04-26 22:30:40', '2025-04-26 22:30:42', 0, 'text'),
(157, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 22:32:51', '2025-04-26 22:32:56', 0, 'text'),
(158, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 22:34:26', '2025-04-26 23:12:36', 0, 'text'),
(159, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 22:47:57', '2025-04-26 23:12:36', 0, 'text'),
(160, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:02:16', '2025-04-26 23:12:36', 0, 'text'),
(161, 54, 1, NULL, 'zegfrg', NULL, 1, 'expert', '2025-04-26 23:02:25', '2025-04-26 23:12:36', 0, 'text'),
(162, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:03:37', '2025-04-26 23:12:36', 0, 'text'),
(163, 54, 1, NULL, 'gfvergvr', NULL, 1, 'expert', '2025-04-26 23:03:40', '2025-04-26 23:12:36', 0, 'text'),
(164, 54, 1, NULL, 'gqegvdwtbgr', NULL, 1, 'expert', '2025-04-26 23:03:43', '2025-04-26 23:12:36', 0, 'text'),
(165, 54, 1, NULL, 'eqrgvvvvvvvvdr', NULL, 1, 'expert', '2025-04-26 23:03:46', '2025-04-26 23:12:36', 0, 'text'),
(166, 54, 1, NULL, 'eqrgvvvvvvvvdr', NULL, 1, 'expert', '2025-04-26 23:03:46', '2025-04-26 23:12:36', 0, 'text'),
(167, 54, 1, NULL, 'zeqfvrgverr', NULL, 1, 'expert', '2025-04-26 23:03:48', '2025-04-26 23:12:36', 0, 'text'),
(168, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:04:37', '2025-04-26 23:12:36', 0, 'text'),
(169, 54, 1, NULL, 'sdvdf', NULL, 1, 'expert', '2025-04-26 23:04:40', '2025-04-26 23:12:36', 0, 'text'),
(170, 54, 1, NULL, 'sdvdfc v', NULL, 1, 'expert', '2025-04-26 23:04:41', '2025-04-26 23:12:36', 0, 'text'),
(171, 54, 1, NULL, 'dsvdv', NULL, 1, 'expert', '2025-04-26 23:04:43', '2025-04-26 23:12:36', 0, 'text'),
(172, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:05:31', '2025-04-26 23:12:36', 0, 'text'),
(173, 54, 1, NULL, 'iohjnl', NULL, 1, 'expert', '2025-04-26 23:05:34', '2025-04-26 23:12:36', 0, 'text'),
(174, 54, 1, NULL, 'nknn', NULL, 1, 'expert', '2025-04-26 23:05:36', '2025-04-26 23:12:36', 0, 'text'),
(175, 54, 1, NULL, 'nkn k', NULL, 1, 'expert', '2025-04-26 23:05:37', '2025-04-26 23:12:36', 0, 'text'),
(176, 54, 1, NULL, 'nkn', NULL, 1, 'expert', '2025-04-26 23:05:37', '2025-04-26 23:12:36', 0, 'text'),
(177, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:06:33', '2025-04-26 23:12:36', 0, 'text'),
(178, 54, 1, NULL, 'dv fc vf', NULL, 1, 'expert', '2025-04-26 23:06:35', '2025-04-26 23:12:36', 0, 'text'),
(179, 54, 1, NULL, 'dsvdv sd', NULL, 1, 'expert', '2025-04-26 23:06:36', '2025-04-26 23:12:36', 0, 'text'),
(180, 54, 1, NULL, 'sdv&lt;sdv', NULL, 1, 'expert', '2025-04-26 23:06:37', '2025-04-26 23:12:36', 0, 'text'),
(181, 54, 1, NULL, 'sdv&lt;sdv', NULL, 1, 'expert', '2025-04-26 23:06:37', '2025-04-26 23:12:36', 0, 'text'),
(182, 54, 1, NULL, 'ergaerr', NULL, 1, 'expert', '2025-04-26 23:07:04', '2025-04-26 23:12:36', 0, 'text'),
(183, 54, 1, NULL, 'rfaef', NULL, 1, 'expert', '2025-04-26 23:07:06', '2025-04-26 23:12:36', 0, 'text'),
(184, 54, 1, NULL, 're', NULL, 1, 'expert', '2025-04-26 23:07:07', '2025-04-26 23:12:36', 0, 'text'),
(185, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:09:19', '2025-04-26 23:12:36', 0, 'text'),
(186, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:10:37', '2025-04-26 23:12:36', 0, 'text'),
(187, 54, 1, NULL, 'oj,', NULL, 1, 'expert', '2025-04-26 23:10:40', '2025-04-26 23:12:36', 0, 'text'),
(188, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:12:02', '2025-04-26 23:12:36', 0, 'text'),
(189, 54, 1, NULL, 'hn', NULL, 1, 'expert', '2025-04-26 23:12:20', '2025-04-26 23:12:36', 0, 'text'),
(190, 54, 1, NULL, 'lmkm,!;', NULL, 1, 'expert', '2025-04-26 23:12:27', '2025-04-26 23:12:36', 0, 'text'),
(191, 1, 54, NULL, 'mkmùk*ùl*', NULL, 1, '', '2025-04-26 23:13:03', '2025-04-26 23:13:03', 0, 'text'),
(192, 1, 54, NULL, 'mkpojmoj', NULL, 1, '', '2025-04-26 23:13:10', '2025-04-26 23:13:11', 0, 'text'),
(193, 54, 1, NULL, 'khmok', NULL, 1, 'expert', '2025-04-26 23:13:18', '2025-04-26 23:13:18', 0, 'text'),
(194, 1, 54, NULL, 'vefb ù', NULL, 1, '', '2025-04-26 23:13:29', '2025-04-26 23:13:30', 0, 'text'),
(195, 1, 54, NULL, 'erfgverpkgv', NULL, 1, '', '2025-04-26 23:13:55', '2025-04-30 17:41:19', 0, 'text'),
(196, 52, 51, 56, 'dvd', NULL, 0, 'client', '2025-04-27 17:03:21', '2025-04-27 17:03:21', 50, 'text'),
(197, 51, 52, 56, 'dvdvd', NULL, 0, 'expert', '2025-04-27 17:03:24', '2025-04-27 17:03:24', 50, 'text'),
(198, 52, 51, 56, 'dvd', NULL, 0, 'client', '2025-04-27 17:16:54', '2025-04-27 17:16:54', 50, 'text'),
(199, 52, 51, 57, 'rgved', NULL, 0, 'client', '2025-04-27 17:19:02', '2025-04-27 17:19:02', 51, 'text'),
(200, 52, 51, 57, 'gdfbf', NULL, 0, 'client', '2025-04-27 17:19:05', '2025-04-27 17:19:05', 51, 'text'),
(201, 51, 52, 57, 'fdgf', NULL, 0, 'expert', '2025-04-27 17:19:08', '2025-04-27 17:19:08', 51, 'text'),
(202, 51, 52, 57, 'UYGUH', NULL, 0, 'expert', '2025-04-27 17:19:22', '2025-04-27 17:19:22', 51, 'text'),
(203, 51, 52, 58, 'cv cv', NULL, 0, 'expert', '2025-04-27 17:26:06', '2025-04-27 17:26:06', 52, 'text'),
(204, 52, 51, 58, 'x x', NULL, 0, 'client', '2025-04-27 17:26:16', '2025-04-27 17:26:16', 52, 'text'),
(205, 51, 52, 58, 'qxq', NULL, 0, 'expert', '2025-04-27 17:26:20', '2025-04-27 17:26:20', 52, 'text'),
(206, 52, 51, 58, 'SSQC ', NULL, 0, 'client', '2025-04-27 17:26:27', '2025-04-27 17:26:27', 52, 'text'),
(207, 51, 52, 58, 'SCX', NULL, 0, 'expert', '2025-04-27 17:26:31', '2025-04-27 17:26:31', 52, 'text'),
(208, 51, 52, 59, 'vsd', NULL, 0, 'expert', '2025-04-27 17:51:35', '2025-04-27 17:51:35', 53, 'text'),
(209, 52, 51, 59, 'cqdv', NULL, 0, 'client', '2025-04-27 17:51:38', '2025-04-27 17:51:38', 53, 'text'),
(210, 52, 51, 59, 'Boucetta', NULL, 0, 'client', '2025-04-27 17:52:21', '2025-04-27 17:52:21', 53, 'text'),
(211, 51, 52, 59, 'x', NULL, 0, 'expert', '2025-04-27 17:52:28', '2025-04-27 17:52:28', 53, 'text'),
(212, 51, 52, 61, 'gvdfgvf', NULL, 0, 'expert', '2025-04-27 18:08:02', '2025-04-27 18:08:02', 55, 'text'),
(213, 52, 51, 61, 'dfbsfgv', NULL, 0, 'client', '2025-04-27 18:08:05', '2025-04-27 18:08:05', 55, 'text'),
(214, 52, 51, 61, 'dfsgwv', NULL, 0, 'client', '2025-04-27 18:08:07', '2025-04-27 18:08:07', 55, 'text'),
(215, 51, 52, 61, 'dfsbgvf', NULL, 0, 'expert', '2025-04-27 18:08:11', '2025-04-27 18:08:11', 55, 'text'),
(216, 51, 52, 61, 'fdvv', NULL, 0, 'expert', '2025-04-27 18:08:13', '2025-04-27 18:08:13', 55, 'text'),
(217, 52, 51, 61, '[ؤشي', NULL, 0, 'client', '2025-04-27 18:16:10', '2025-04-27 18:16:10', 55, 'text'),
(218, 52, 51, 62, 'سثبؤيسر', NULL, 0, 'client', '2025-04-27 18:16:44', '2025-04-27 18:16:44', 56, 'text'),
(219, 52, 51, 62, 'sdvfedv', NULL, 0, 'client', '2025-04-27 18:16:47', '2025-04-27 18:16:47', 56, 'text'),
(220, 52, 51, 62, 'sdvfedv', NULL, 0, 'client', '2025-04-27 18:16:47', '2025-04-27 18:16:47', 56, 'text'),
(221, 51, 52, 62, 'sfedfds', NULL, 0, 'expert', '2025-04-27 18:16:51', '2025-04-27 18:16:51', 56, 'text'),
(222, 51, 52, 62, 'qsfds', NULL, 0, 'expert', '2025-04-27 18:16:54', '2025-04-27 18:16:54', 56, 'text'),
(223, 52, 51, 62, 'dvds', NULL, 0, 'client', '2025-04-27 18:17:50', '2025-04-27 18:17:50', 56, 'text'),
(224, 52, 51, 66, 'rgdf', NULL, 0, 'client', '2025-04-27 19:10:20', '2025-04-27 19:10:20', 60, 'text'),
(225, 52, 51, 68, 'يبلا', NULL, 0, 'client', '2025-04-27 19:20:45', '2025-04-27 19:20:45', 62, 'text'),
(226, 52, 51, 68, 'sqfesd', NULL, 0, 'client', '2025-04-27 19:24:00', '2025-04-27 19:24:00', 62, 'text'),
(227, 52, 51, 70, 'sqcqsc', NULL, 0, 'client', '2025-04-27 20:11:54', '2025-04-27 20:11:54', 64, 'text'),
(228, 51, 52, 70, 'ccsscqs', NULL, 0, 'expert', '2025-04-27 20:12:02', '2025-04-27 20:12:02', 64, 'text'),
(229, 52, 51, 70, 'scsqx', NULL, 0, 'client', '2025-04-27 20:12:07', '2025-04-27 20:12:07', 64, 'text'),
(230, 52, 51, 70, 'scsqx', NULL, 0, 'client', '2025-04-27 20:12:55', '2025-04-27 20:12:55', 64, 'text'),
(231, 52, 51, 70, 'efcefv', NULL, 0, 'client', '2025-04-27 20:12:57', '2025-04-27 20:12:57', 64, 'text'),
(232, 51, 52, 70, 'fedfvev', NULL, 0, 'expert', '2025-04-27 20:13:01', '2025-04-27 20:13:01', 64, 'text'),
(233, 52, 51, 70, 'Oussama', NULL, 0, 'client', '2025-04-27 20:16:03', '2025-04-27 20:16:03', 64, 'text'),
(234, 51, 52, 70, 'mlk', NULL, 0, 'expert', '2025-04-27 20:16:08', '2025-04-27 20:16:08', 64, 'text'),
(235, 51, 52, 70, 'chkhsk', NULL, 0, 'expert', '2025-04-27 20:16:15', '2025-04-27 20:16:15', 64, 'text'),
(236, 52, 51, 70, 'c', NULL, 0, 'client', '2025-04-27 20:16:34', '2025-04-27 20:16:34', 64, 'text'),
(237, 52, 51, 70, 'c', NULL, 0, 'client', '2025-04-27 20:29:11', '2025-04-27 20:29:11', 64, 'text'),
(238, 52, 51, 71, 'Tech', NULL, 0, 'client', '2025-04-27 20:30:02', '2025-04-27 20:30:02', 65, 'text'),
(239, 52, 51, 71, 'hi', '../uploads/chat_files/680e93e713a94_1745785831.jpg', 0, 'client', '2025-04-27 20:30:31', '2025-04-27 20:30:31', 65, 'image'),
(240, 52, 51, 71, 'ezfgvr', NULL, 0, 'client', '2025-04-27 20:31:34', '2025-04-27 20:31:34', 65, 'text'),
(241, 52, 51, 71, 'ezfgvr', NULL, 0, 'client', '2025-04-27 20:32:38', '2025-04-27 20:32:38', 65, 'text'),
(242, 52, 51, 71, 'ezfgvr', NULL, 0, 'client', '2025-04-27 20:34:19', '2025-04-27 20:34:19', 65, 'text'),
(243, 52, 51, 71, 'sc', NULL, 0, 'client', '2025-04-27 20:34:33', '2025-04-27 20:34:33', 65, 'text'),
(244, 52, 51, 72, 'lkjnpm,l', NULL, 0, 'client', '2025-04-27 20:42:55', '2025-04-27 20:42:55', 66, 'text'),
(245, 52, 51, 72, 'knmnl,', NULL, 0, 'client', '2025-04-27 20:42:59', '2025-04-27 20:42:59', 66, 'text'),
(246, 52, 51, 72, 'knmnl,', NULL, 0, 'client', '2025-04-27 21:58:28', '2025-04-27 21:58:28', 66, 'text'),
(247, 52, 54, 73, 'Tech', NULL, 0, 'client', '2025-04-27 21:59:48', '2025-04-27 21:59:48', 67, 'text'),
(248, 52, 54, 73, 'jkn', NULL, 0, 'client', '2025-04-27 22:03:56', '2025-04-27 22:03:56', 67, 'text'),
(249, 52, 54, 73, 'FCB X', NULL, 0, 'client', '2025-04-27 22:40:18', '2025-04-27 22:40:18', 67, 'text'),
(250, 52, 54, 73, 'FCB X', NULL, 0, 'client', '2025-04-27 22:40:28', '2025-04-27 22:40:28', 67, 'text'),
(251, 52, 54, 74, 'YUKU', NULL, 0, 'client', '2025-04-27 22:41:23', '2025-04-27 22:41:23', 68, 'text'),
(252, 52, 54, 74, 'FYUKFF', NULL, 0, 'client', '2025-04-27 22:41:25', '2025-04-27 22:41:25', 68, 'text'),
(253, 54, 52, 74, 'FUKYK', NULL, 0, 'expert', '2025-04-27 22:41:28', '2025-04-27 22:41:28', 68, 'text'),
(254, 54, 52, 74, 'XDCD', NULL, 0, 'expert', '2025-04-27 22:42:08', '2025-04-27 22:42:08', 68, 'text'),
(255, 52, 54, 75, 'dv', NULL, 0, 'client', '2025-04-27 23:03:26', '2025-04-27 23:03:26', 69, 'text'),
(256, 52, 54, 75, 'dv', NULL, 0, 'client', '2025-04-27 23:03:31', '2025-04-27 23:03:31', 69, 'text'),
(257, 52, 54, 75, 'dmd:*ùv:*ùd', NULL, 0, 'client', '2025-04-27 23:03:34', '2025-04-27 23:03:34', 69, 'text'),
(258, 54, 52, 75, 'fcsergv', NULL, 0, 'expert', '2025-04-27 23:03:46', '2025-04-27 23:03:46', 69, 'text'),
(259, 52, 54, 75, 'yes', '../uploads/chat_files/680eb7e1085ef_1745795041.jpg', 0, 'client', '2025-04-27 23:04:01', '2025-04-27 23:04:01', 69, 'image'),
(260, 54, 52, 75, 'egfvrsdvf', '../uploads/chat_files/680eb9ac62207_1745795500.jpg', 0, 'expert', '2025-04-27 23:11:40', '2025-04-27 23:11:40', 69, 'image'),
(261, 52, 54, 75, 'Boucetta', NULL, 0, 'client', '2025-04-27 23:11:48', '2025-04-27 23:11:48', 69, 'text'),
(262, 52, 54, 75, 'Boucetta', NULL, 0, 'client', '2025-04-27 23:11:48', '2025-04-27 23:11:48', 69, 'text'),
(263, 52, 54, 75, 'Boucetta', NULL, 0, 'client', '2025-04-27 23:11:48', '2025-04-27 23:11:48', 69, 'text'),
(264, 54, 52, 75, 'tgyuhj', NULL, 0, 'expert', '2025-04-27 23:13:14', '2025-04-27 23:13:14', 69, 'text'),
(265, 52, 54, 75, 'x', NULL, 0, 'client', '2025-04-27 23:13:38', '2025-04-27 23:13:38', 69, 'text'),
(266, 52, 54, 76, 'dvdfbv', NULL, 0, 'client', '2025-04-27 23:52:41', '2025-04-27 23:52:41', 70, 'text'),
(267, 54, 52, 76, 'xc', NULL, 1, 'expert', '2025-04-27 23:53:04', '2025-04-28 21:46:39', 70, 'text'),
(268, 52, 54, 77, 'pokp', NULL, 0, 'client', '2025-04-28 09:31:12', '2025-04-28 09:31:12', 71, 'text'),
(269, 52, 54, 77, 'ok,kl', NULL, 0, 'client', '2025-04-28 09:31:17', '2025-04-28 09:31:17', 71, 'text'),
(270, 52, 54, 77, 'hadahowa', '../uploads/chat_files/680f4b018c8e3_1745832705.txt', 0, 'client', '2025-04-28 09:31:45', '2025-04-28 09:31:45', 71, 'file'),
(271, 52, 54, 77, 'هختة', NULL, 0, 'client', '2025-04-28 09:33:55', '2025-04-28 09:33:55', 71, 'text'),
(272, 52, 54, 77, 'UHIL', NULL, 0, 'client', '2025-04-28 09:35:25', '2025-04-28 09:35:25', 71, 'text'),
(273, 52, 54, 77, 'ي', NULL, 0, 'client', '2025-04-28 09:36:15', '2025-04-28 09:36:15', 71, 'text'),
(274, 52, 54, 77, 'ريصسي', NULL, 0, 'client', '2025-04-28 09:36:21', '2025-04-28 09:36:21', 71, 'text'),
(275, 54, 52, 77, 'ثيصبكوصث', NULL, 1, 'expert', '2025-04-28 09:36:32', '2025-04-28 21:46:14', 71, 'text'),
(276, 52, 54, 77, 'صثنمةبلصك', NULL, 0, 'client', '2025-04-28 09:36:45', '2025-04-28 09:36:45', 71, 'text'),
(277, 52, 54, 77, 'صثنمةبلصك', NULL, 0, 'client', '2025-04-28 09:42:41', '2025-04-28 09:42:41', 71, 'text'),
(278, 54, 68, 83, 'slm', NULL, 1, 'expert', '2025-04-28 14:44:55', '2025-05-01 17:32:43', 74, 'text'),
(279, 68, 54, 83, 'kirak', NULL, 0, 'client', '2025-04-28 14:45:07', '2025-04-28 14:45:07', 74, 'text'),
(280, 68, 54, 83, 'rani mrid', NULL, 0, 'client', '2025-04-28 14:45:18', '2025-04-28 14:45:18', 74, 'text'),
(281, 54, 68, 83, 'za3tar', NULL, 1, 'expert', '2025-04-28 14:45:29', '2025-05-01 17:32:43', 74, 'text'),
(282, 54, 68, 83, 'hade hiya', '../uploads/chat_files/680f94e00f06a_1745851616.png', 1, 'expert', '2025-04-28 14:46:56', '2025-05-01 17:32:43', 74, 'image'),
(283, 54, 52, 104, 'EFZE', NULL, 0, 'expert', '2025-04-30 17:59:56', '2025-04-30 17:59:56', 81, 'text'),
(284, 54, 52, 104, 'EFZ', NULL, 0, 'expert', '2025-04-30 17:59:58', '2025-04-30 17:59:58', 81, 'text'),
(285, 54, 52, 104, 'SDFDE', NULL, 0, 'expert', '2025-04-30 18:00:00', '2025-04-30 18:00:00', 81, 'text'),
(286, 54, 52, 104, 'ZEFDFZS', NULL, 0, 'expert', '2025-04-30 18:00:02', '2025-04-30 18:00:02', 81, 'text'),
(287, 54, 52, 104, 'SFE', NULL, 0, 'expert', '2025-04-30 18:00:05', '2025-04-30 18:00:05', 81, 'text'),
(288, 54, 52, 106, 'dvsqdv', NULL, 0, 'expert', '2025-04-30 18:03:26', '2025-04-30 18:03:26', 82, 'text'),
(289, 51, 54, NULL, 'utgui', '', 1, 'client', '2025-04-30 19:34:41', '2025-05-02 19:09:23', 0, 'text'),
(290, 1, 54, NULL, 'ouss', NULL, 0, '', '2025-04-30 20:21:09', '2025-04-30 20:21:09', 0, 'text'),
(291, 1, 54, NULL, 'fhdgnv', NULL, 0, '', '2025-04-30 20:24:03', '2025-04-30 20:24:03', 0, 'text'),
(292, 1, 54, NULL, 'yes sondos', NULL, 0, '', '2025-04-30 20:24:10', '2025-04-30 20:24:10', 0, 'text'),
(293, 1, 54, NULL, 'Oussama', NULL, 0, '', '2025-04-30 20:26:59', '2025-04-30 20:26:59', 0, 'text'),
(294, 54, 1, NULL, 'sdcsdv', NULL, 1, '', '2025-04-30 20:30:00', '2025-05-02 18:26:32', 0, 'text'),
(295, 1, 54, NULL, 'ffd', NULL, 0, '', '2025-04-30 20:31:15', '2025-04-30 20:31:15', 0, 'text'),
(296, 54, 1, NULL, 'dfgf', NULL, 1, '', '2025-04-30 20:31:20', '2025-05-02 18:26:32', 0, 'text'),
(297, 54, 1, NULL, 'dfgfggggggggggg', NULL, 1, '', '2025-04-30 20:31:30', '2025-05-02 18:26:32', 0, 'text'),
(298, 1, 54, NULL, 'dfgfgdqwf', NULL, 0, '', '2025-04-30 20:31:39', '2025-04-30 20:31:39', 0, 'text'),
(299, 54, 52, 110, 'dv', NULL, 0, 'expert', '2025-04-30 21:05:09', '2025-04-30 21:05:09', 84, 'text'),
(300, 51, 54, NULL, 'hello', '', 1, 'client', '2025-05-01 18:07:52', '2025-05-02 19:09:23', 0, 'text'),
(301, 68, 51, 118, 'efed', NULL, 0, 'client', '2025-05-01 20:13:25', '2025-05-01 20:13:25', 88, 'text'),
(302, 51, 68, 118, 'dcsd', NULL, 0, 'expert', '2025-05-01 20:13:28', '2025-05-01 20:13:28', 88, 'text'),
(303, 68, 51, 118, 'dcs', NULL, 0, 'client', '2025-05-01 20:13:42', '2025-05-01 20:13:42', 88, 'text'),
(304, 51, 68, 118, 'dcs', NULL, 0, 'expert', '2025-05-01 20:13:44', '2025-05-01 20:13:44', 88, 'text'),
(305, 51, 68, 118, 'sc', NULL, 0, 'expert', '2025-05-01 20:14:18', '2025-05-01 20:14:18', 88, 'text'),
(306, 51, 68, 118, 'sc', NULL, 0, 'expert', '2025-05-01 20:14:18', '2025-05-01 20:14:18', 88, 'text'),
(307, 68, 51, 119, 'sdvdvc', NULL, 0, 'client', '2025-05-01 20:18:45', '2025-05-01 20:18:45', 90, 'text'),
(308, 54, 1, NULL, 'سيبي', NULL, 1, '', '2025-05-02 18:21:06', '2025-05-02 18:26:32', 0, 'text'),
(309, 54, 1, NULL, 'mljpmk', NULL, 1, 'admin', '2025-05-02 18:26:01', '2025-05-02 18:26:32', 0, 'text'),
(310, 1, 54, NULL, 'wh', NULL, 1, 'admin', '2025-05-02 18:26:39', '2025-05-02 18:26:40', 0, 'text'),
(311, 1, 54, NULL, 'pexels-cottonbro-4098215.jpg', '../uploads/chat_files/68150e8a90c11_1746210442.jpg', 1, 'admin', '2025-05-02 18:27:22', '2025-05-02 18:27:22', 0, 'image'),
(312, 54, 1, NULL, 'kelf,sd', NULL, 1, 'expert', '2025-05-02 18:43:48', '2025-05-02 18:43:48', 0, 'text'),
(313, 54, 1, NULL, 'yes', NULL, 1, 'expert', '2025-05-02 18:43:57', '2025-05-02 18:43:57', 0, 'text'),
(314, 54, 1, NULL, 'photo-1557804506-669a67965ba0.jpg', '../uploads/chat_files/68151291978b7_1746211473.jpg', 1, 'expert', '2025-05-02 18:44:33', '2025-05-02 18:44:33', 0, 'image'),
(315, 1, 54, NULL, 'yes', NULL, 1, 'admin', '2025-05-02 18:44:41', '2025-05-02 18:44:42', 0, 'text'),
(316, 1, 54, NULL, 'yes', NULL, 1, 'admin', '2025-05-02 18:44:47', '2025-05-02 18:44:47', 0, 'text'),
(317, 1, 54, NULL, 'yesm', NULL, 1, 'admin', '2025-05-02 18:44:56', '2025-05-02 18:44:56', 0, 'text'),
(318, 54, 1, NULL, 'iojkio', NULL, 1, 'expert', '2025-05-02 18:45:21', '2025-05-02 18:45:22', 0, 'text'),
(319, 1, 4, NULL, 'okpok', NULL, 1, 'admin', '2025-05-02 19:04:13', '2025-05-02 19:07:41', 0, 'text'),
(320, 54, 1, NULL, 'k,kl,', NULL, 1, 'admin', '2025-05-02 19:04:22', '2025-05-02 19:04:28', 0, 'text'),
(321, 1, 54, NULL, 'zlef,em', NULL, 1, 'admin', '2025-05-02 19:04:52', '2025-05-02 19:04:53', 0, 'text'),
(322, 54, 1, NULL, 'of', NULL, 1, 'admin', '2025-05-02 19:05:09', '2025-05-02 19:05:09', 0, 'text'),
(323, 1, 54, NULL, 'yes', NULL, 1, 'admin', '2025-05-02 19:05:20', '2025-05-02 19:05:21', 0, 'text'),
(324, 54, 1, NULL, 'eojerg', NULL, 1, 'admin', '2025-05-02 19:06:42', '2025-05-02 19:06:42', 0, 'text'),
(325, 54, 1, NULL, 'yes', NULL, 1, 'expert', '2025-05-02 19:07:08', '2025-05-02 19:07:08', 0, 'text'),
(326, 1, 54, NULL, 'ok', NULL, 1, 'admin', '2025-05-02 19:07:14', '2025-05-02 19:07:14', 0, 'text'),
(327, 1, 54, NULL, 'mlk', NULL, 1, 'admin', '2025-05-02 19:07:52', '2025-05-02 19:07:55', 0, 'text'),
(328, 54, 1, NULL, 'yes', NULL, 1, 'expert', '2025-05-02 19:08:06', '2025-05-02 19:09:51', 0, 'text'),
(329, 54, 51, NULL, 'yes', '', 1, 'expert', '2025-05-02 19:09:30', '2025-05-02 19:11:03', 0, 'text'),
(330, 54, 51, NULL, 'ok', '', 1, 'expert', '2025-05-02 19:09:46', '2025-05-02 19:11:03', 0, 'text'),
(331, 51, 54, NULL, 'wh', '', 1, 'expert', '2025-05-02 19:11:14', '2025-05-02 19:13:08', 0, 'text'),
(332, 54, 51, NULL, 'k,ml', '', 0, 'expert', '2025-05-02 21:16:31', '2025-05-02 21:16:31', 0, 'text'),
(333, 54, 51, NULL, 'ouss', '', 0, 'expert', '2025-05-02 21:47:04', '2025-05-02 21:47:04', 0, 'text'),
(334, 52, 51, NULL, 'pokpok', NULL, 0, 'client', '2025-05-03 12:04:40', '2025-05-03 12:04:40', 12, 'text'),
(335, 54, 1, NULL, 'qsdswx', NULL, 0, 'expert', '2025-05-06 19:27:42', '2025-05-06 19:27:42', 0, 'text');

-- --------------------------------------------------------

--
-- Structure de la table `chat_sessions`
--

CREATE TABLE `chat_sessions` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `status` enum('active','ended') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `chat_sessions`
--

INSERT INTO `chat_sessions` (`id`, `consultation_id`, `expert_id`, `client_id`, `started_at`, `ended_at`, `status`) VALUES
(12, 11, 51, 52, '2025-04-22 15:15:45', NULL, 'active'),
(13, 12, 51, 52, '2025-04-22 15:28:53', NULL, 'active'),
(14, 13, 51, 52, '2025-04-22 19:08:36', NULL, 'active'),
(15, 14, 51, 52, '2025-04-22 19:27:57', NULL, 'active'),
(16, 15, 51, 52, '2025-04-22 20:06:12', NULL, 'active'),
(17, 16, 51, 52, '2025-04-22 20:15:43', NULL, 'active'),
(18, 18, 51, 52, '2025-04-23 12:37:25', NULL, 'active'),
(19, 19, 51, 52, '2025-04-24 10:54:31', NULL, 'active'),
(20, 20, 51, 52, '2025-04-24 11:02:21', NULL, 'active'),
(21, 21, 51, 52, '2025-04-24 11:05:05', NULL, 'active'),
(22, 22, 51, 52, '2025-04-24 11:22:26', NULL, 'active'),
(23, 23, 51, 52, '2025-04-24 11:23:55', NULL, 'active'),
(24, 25, 51, 52, '2025-04-24 11:30:31', NULL, 'active'),
(25, 26, 51, 52, '2025-04-24 11:33:35', NULL, 'active'),
(26, 27, 51, 52, '2025-04-24 11:35:32', NULL, 'active'),
(27, 28, 51, 52, '2025-04-24 11:38:25', NULL, 'active'),
(28, 29, 51, 52, '2025-04-24 11:39:45', NULL, 'active'),
(29, 30, 51, 52, '2025-04-24 11:42:12', NULL, 'active'),
(30, 31, 51, 52, '2025-04-24 11:43:58', NULL, 'active'),
(31, 32, 51, 52, '2025-04-24 11:51:31', NULL, 'active'),
(32, 33, 51, 52, '2025-04-24 12:13:52', NULL, 'active'),
(33, 34, 51, 52, '2025-04-24 12:15:07', NULL, 'active'),
(34, 35, 51, 52, '2025-04-24 12:16:45', NULL, 'active'),
(35, 36, 51, 52, '2025-04-24 12:19:32', NULL, 'active'),
(36, 38, 51, 52, '2025-04-24 12:30:22', NULL, 'active'),
(37, 40, 51, 52, '2025-04-24 16:05:13', NULL, 'active'),
(38, 41, 51, 52, '2025-04-24 21:13:29', NULL, 'active'),
(39, 42, 51, 52, '2025-04-24 22:14:52', NULL, 'active'),
(40, 43, 51, 52, '2025-04-24 22:48:28', NULL, 'active'),
(41, 44, 51, 52, '2025-04-24 23:39:39', NULL, 'active'),
(42, 45, 51, 52, '2025-04-24 23:41:19', NULL, 'active'),
(43, 47, 51, 52, '2025-04-24 23:53:13', NULL, 'active'),
(44, 48, 51, 52, '2025-04-25 19:44:05', NULL, 'active'),
(45, 49, 51, 52, '2025-04-25 20:01:58', '2025-04-25 20:19:06', ''),
(46, 50, 51, 52, '2025-04-25 20:20:46', '2025-04-25 20:20:54', ''),
(47, 51, 51, 52, '2025-04-25 20:41:05', NULL, 'active'),
(48, 52, 51, 52, '2025-04-25 20:42:08', NULL, 'active'),
(49, 53, 54, 52, '2025-04-25 21:22:10', NULL, 'active'),
(50, 56, 51, 52, '2025-04-27 18:02:42', NULL, 'active'),
(51, 57, 51, 52, '2025-04-27 18:18:37', NULL, 'active'),
(52, 58, 51, 52, '2025-04-27 18:25:01', NULL, 'active'),
(53, 59, 51, 52, '2025-04-27 18:51:21', NULL, 'active'),
(54, 60, 51, 52, '2025-04-27 19:06:54', '2025-04-27 19:07:06', 'ended'),
(55, 61, 51, 52, '2025-04-27 19:07:54', '2025-04-27 19:16:02', 'ended'),
(56, 62, 51, 52, '2025-04-27 19:16:36', '2025-04-27 19:17:41', 'ended'),
(57, 63, 51, 52, '2025-04-27 19:18:28', '2025-04-27 19:29:57', 'ended'),
(58, 64, 51, 52, '2025-04-27 19:49:51', '2025-04-27 19:50:13', 'ended'),
(59, 65, 51, 52, '2025-04-27 19:50:41', '2025-04-27 20:03:26', 'ended'),
(60, 66, 51, 52, '2025-04-27 20:04:01', NULL, 'active'),
(61, 67, 51, 52, '2025-04-27 20:13:06', NULL, 'active'),
(62, 68, 51, 52, '2025-04-27 20:19:25', NULL, 'active'),
(63, 69, 51, 52, '2025-04-27 20:47:50', '2025-04-27 20:48:09', 'ended'),
(64, 70, 51, 52, '2025-04-27 21:11:27', '2025-04-27 21:16:27', 'ended'),
(65, 71, 51, 52, '2025-04-27 21:29:45', '2025-04-27 21:34:47', 'ended'),
(66, 72, 51, 52, '2025-04-27 21:41:06', '2025-04-27 21:46:06', 'ended'),
(67, 73, 54, 52, '2025-04-27 22:59:29', NULL, 'active'),
(68, 74, 54, 52, '2025-04-27 23:40:56', NULL, 'active'),
(69, 75, 54, 52, '2025-04-27 23:58:23', '2025-04-28 00:13:24', 'ended'),
(70, 76, 54, 52, '2025-04-28 00:52:29', NULL, 'active'),
(71, 77, 54, 52, '2025-04-28 10:30:56', '2025-04-28 10:36:33', 'ended'),
(72, 79, 54, 52, '2025-04-28 13:03:45', '2025-04-28 13:04:56', 'ended'),
(73, 82, 54, 68, '2025-04-28 15:41:32', NULL, 'active'),
(74, 83, 54, 68, '2025-04-28 15:43:13', '2025-04-28 15:48:44', 'ended'),
(75, 84, 54, 68, '2025-04-29 19:11:55', NULL, 'active'),
(76, 93, 51, 52, '2025-04-29 19:53:58', '2025-04-29 19:56:30', 'ended'),
(77, 94, 51, 52, '2025-04-29 20:28:26', '2025-04-29 20:31:05', 'ended'),
(78, 95, 51, 52, '2025-04-29 20:39:45', NULL, 'active'),
(79, 96, 51, 52, '2025-04-29 20:39:50', NULL, 'active'),
(80, 103, 54, 52, '2025-04-30 18:50:20', NULL, 'active'),
(81, 104, 54, 52, '2025-04-30 18:58:52', NULL, 'active'),
(82, 106, 54, 52, '2025-04-30 19:02:59', '2025-04-30 21:56:11', 'ended'),
(83, 107, 54, 52, '2025-04-30 21:39:37', '2025-04-30 21:40:12', 'ended'),
(84, 110, 54, 52, '2025-04-30 22:04:31', NULL, 'active'),
(85, 111, 54, 52, '2025-05-01 00:15:26', NULL, 'active'),
(86, 115, 54, 52, '2025-05-01 00:54:30', '2025-05-01 00:54:39', 'ended'),
(87, 114, 54, 52, '2025-05-01 00:54:45', '2025-05-01 00:55:02', 'ended'),
(88, 118, 51, 68, '2025-05-01 21:13:18', NULL, 'active'),
(89, 120, 51, 68, '2025-05-01 21:18:15', NULL, 'active'),
(90, 119, 51, 68, '2025-05-01 21:18:38', NULL, 'active'),
(91, 134, 51, 68, '2025-05-01 21:20:36', NULL, 'active'),
(92, 133, 51, 68, '2025-05-01 21:20:41', NULL, 'active'),
(93, 132, 51, 68, '2025-05-01 21:20:45', NULL, 'active'),
(94, 131, 51, 68, '2025-05-01 21:20:49', NULL, 'active'),
(95, 129, 51, 68, '2025-05-01 21:20:52', NULL, 'active'),
(96, 130, 51, 68, '2025-05-01 21:20:57', NULL, 'active'),
(97, 128, 51, 68, '2025-05-01 21:21:01', NULL, 'active'),
(98, 127, 51, 68, '2025-05-01 21:21:05', NULL, 'active'),
(99, 126, 51, 68, '2025-05-01 21:21:10', NULL, 'active'),
(100, 125, 51, 68, '2025-05-01 21:21:14', NULL, 'active'),
(101, 124, 51, 68, '2025-05-01 21:21:18', NULL, 'active'),
(102, 123, 51, 68, '2025-05-01 21:21:24', NULL, 'active'),
(103, 122, 51, 68, '2025-05-01 21:21:27', NULL, 'active'),
(104, 121, 51, 68, '2025-05-01 21:21:31', NULL, 'active'),
(105, 145, 54, 52, '2025-05-03 13:04:15', '2025-05-03 13:04:17', 'ended'),
(106, 155, 54, 52, '2025-05-03 14:19:17', '2025-05-03 14:19:19', 'ended'),
(107, 170, 54, 52, '2025-05-04 10:52:20', '2025-05-04 10:52:28', 'ended'),
(108, 199, 51, 68, '2025-05-04 23:35:48', '2025-05-04 23:38:19', 'ended'),
(109, 227, 51, 52, '2025-05-07 11:52:49', NULL, 'active'),
(110, 228, 51, 68, '2025-05-07 16:11:47', '2025-05-07 16:11:52', 'ended'),
(111, 135, 54, 52, '2025-05-07 20:28:04', NULL, 'active');

-- --------------------------------------------------------

--
-- Structure de la table `chat_timers`
--

CREATE TABLE `chat_timers` (
  `id` int(11) NOT NULL,
  `chat_session_id` int(11) NOT NULL,
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `status` enum('running','stopped') NOT NULL DEFAULT 'running'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `chat_timers`
--

INSERT INTO `chat_timers` (`id`, `chat_session_id`, `started_at`, `ended_at`, `duration`, `status`) VALUES
(1, 12, '2025-04-22 15:15:45', '2025-04-22 15:15:53', 8, 'stopped'),
(2, 13, '2025-04-22 15:28:53', '2025-04-22 19:06:18', 13045, 'stopped'),
(3, 14, '2025-04-22 19:08:36', '2025-04-22 19:09:48', 72, 'stopped'),
(13, 15, '2025-04-22 19:27:57', '2025-04-22 19:32:58', 301, 'stopped'),
(14, 16, '2025-04-22 20:06:12', '2025-04-22 20:07:16', 64, 'stopped'),
(15, 17, '2025-04-22 20:15:43', '2025-04-22 20:15:58', 15, 'stopped'),
(16, 18, '2025-04-23 12:37:25', '2025-04-23 12:42:25', 300, 'stopped'),
(17, 19, '2025-04-24 10:54:31', '2025-04-24 10:59:35', 304, 'stopped'),
(18, 20, '2025-04-24 11:02:21', '2025-04-24 11:03:17', 56, 'stopped'),
(19, 21, '2025-04-24 11:05:05', '2025-04-24 11:07:32', 147, 'stopped'),
(20, 22, '2025-04-24 11:22:26', '2025-04-24 11:28:59', 393, 'stopped'),
(21, 23, '2025-04-24 11:23:55', '2025-04-24 11:29:15', 320, 'stopped'),
(22, 24, '2025-04-24 11:30:31', '2025-04-24 11:32:40', 129, 'stopped'),
(23, 25, '2025-04-24 11:33:35', '2025-04-24 11:33:53', 18, 'stopped'),
(24, 26, '2025-04-24 11:35:32', '2025-04-24 11:35:49', 17, 'stopped'),
(25, 27, '2025-04-24 11:38:25', '2025-04-24 11:38:48', 23, 'stopped'),
(26, 28, '2025-04-24 11:39:45', '2025-04-24 11:40:34', 49, 'stopped'),
(27, 29, '2025-04-24 11:42:12', '2025-04-24 11:43:04', 52, 'stopped'),
(28, 30, '2025-04-24 11:43:58', '2025-04-24 11:50:29', 391, 'stopped'),
(29, 31, '2025-04-24 11:51:31', '2025-04-24 11:53:21', 110, 'stopped'),
(30, 32, '2025-04-24 12:13:52', '2025-04-24 12:14:27', 35, 'stopped'),
(31, 33, '2025-04-24 12:15:07', '2025-04-24 12:15:48', 41, 'stopped'),
(32, 34, '2025-04-24 12:16:45', '2025-04-24 12:18:30', 105, 'stopped'),
(33, 35, '2025-04-24 12:19:32', '2025-04-24 12:19:46', 14, 'stopped'),
(34, 36, '2025-04-24 12:30:22', '2025-04-24 12:30:52', 30, 'stopped'),
(35, 37, '2025-04-24 16:05:13', '2025-04-24 16:06:42', 89, 'stopped'),
(36, 38, '2025-04-24 21:13:29', '2025-04-24 21:14:29', 60, 'stopped'),
(37, 39, '2025-04-24 22:14:52', '2025-04-24 22:19:52', 300, 'stopped'),
(39, 41, '2025-04-24 23:39:39', '2025-04-24 23:39:56', 17, 'stopped'),
(40, 42, '2025-04-24 23:41:19', '2025-04-24 23:42:19', 60, 'stopped'),
(41, 43, '2025-04-24 23:53:13', '2025-04-24 23:53:56', 43, 'stopped'),
(42, 44, '2025-04-25 19:44:05', '2025-04-25 19:49:05', 300, 'stopped'),
(43, 45, '2025-04-25 20:01:58', '2025-04-25 20:19:05', 1027, 'stopped'),
(44, 46, '2025-04-25 20:20:46', '2025-04-25 20:20:54', 8, 'stopped'),
(45, 47, '2025-04-25 20:41:05', '2025-04-25 20:41:11', 6, 'stopped'),
(46, 48, '2025-04-25 20:42:08', '2025-04-25 20:47:08', 300, 'stopped'),
(47, 49, '2025-04-25 21:22:10', '2025-04-25 21:26:33', 23, 'stopped'),
(48, 50, '2025-04-27 18:02:42', '2025-04-27 18:03:17', 35, 'stopped'),
(49, 51, '2025-04-27 18:18:37', '2025-04-27 18:24:38', 361, 'stopped'),
(50, 52, '2025-04-27 18:25:01', '2025-04-27 18:26:13', 72, 'stopped'),
(51, 53, '2025-04-27 18:51:21', '2025-04-27 18:52:18', 57, 'stopped'),
(52, 54, '2025-04-27 19:06:54', '2025-04-27 19:07:06', 12, 'stopped'),
(53, 55, '2025-04-27 19:07:54', '2025-04-27 19:16:01', 487, 'stopped'),
(54, 56, '2025-04-27 19:16:36', '2025-04-27 19:17:41', 65, 'stopped'),
(55, 57, '2025-04-27 19:18:28', '2025-04-27 19:21:13', 165, 'stopped'),
(56, 58, '2025-04-27 19:49:51', '2025-04-27 19:50:13', 22, 'stopped'),
(57, 59, '2025-04-27 19:50:41', '2025-04-27 20:03:25', 764, 'stopped'),
(58, 60, '2025-04-27 20:04:01', '2025-04-27 20:10:13', 372, 'stopped'),
(59, 61, '2025-04-27 20:13:06', '2025-04-27 20:25:00', 714, 'stopped'),
(60, 62, '2025-04-27 20:19:25', '2025-04-27 20:20:48', 83, 'stopped'),
(61, 63, '2025-04-27 20:47:50', '2025-04-27 20:48:09', 19, 'stopped'),
(62, 64, '2025-04-27 21:11:27', '2025-04-27 21:16:27', 300, 'stopped'),
(63, 65, '2025-04-27 21:29:45', '2025-04-27 21:34:45', 300, 'stopped'),
(64, 66, '2025-04-27 21:41:06', '2025-04-27 21:46:06', 300, 'stopped'),
(65, 67, '2025-04-27 22:59:29', '2025-04-27 23:40:23', 2454, 'stopped'),
(66, 68, '2025-04-27 23:40:56', '2025-04-27 23:41:51', 55, 'stopped'),
(67, 69, '2025-04-27 23:58:24', '2025-04-28 00:13:24', 900, 'stopped'),
(68, 70, '2025-04-28 00:52:29', '2025-04-28 00:52:47', 18, 'stopped'),
(69, 71, '2025-04-28 10:30:56', '2025-04-28 10:36:32', 336, 'stopped'),
(70, 72, '2025-04-28 13:03:45', '2025-04-28 13:04:56', 71, 'stopped'),
(71, 73, '2025-04-28 15:41:32', '2025-04-28 15:42:21', 49, 'stopped'),
(72, 74, '2025-04-28 15:43:13', '2025-04-28 15:48:07', 294, 'stopped'),
(73, 75, '2025-04-29 19:11:55', '2025-04-30 19:00:32', 85717, 'stopped'),
(74, 76, '2025-04-29 19:53:58', '2025-04-29 19:56:30', 152, 'stopped'),
(75, 77, '2025-04-29 20:28:26', '2025-04-29 20:31:05', 159, 'stopped'),
(76, 78, '2025-04-29 20:39:45', '2025-04-29 20:40:15', 30, 'stopped'),
(77, 79, '2025-04-29 20:39:50', '2025-04-29 20:40:04', 14, 'stopped'),
(78, 80, '2025-04-30 18:50:20', '2025-04-30 19:00:20', 600, 'stopped'),
(79, 81, '2025-04-30 18:58:52', '2025-04-30 19:00:09', 77, 'stopped'),
(80, 81, '2025-04-30 19:00:09', '2025-04-30 19:00:11', 2, 'stopped'),
(81, 81, '2025-04-30 19:00:11', '2025-04-30 19:00:15', 4, 'stopped'),
(82, 82, '2025-04-30 19:02:59', '2025-04-30 21:56:10', 10391, 'stopped'),
(83, 83, '2025-04-30 21:39:37', '2025-04-30 21:40:12', 35, 'stopped'),
(84, 84, '2025-04-30 22:04:31', '2025-04-30 22:04:55', 24, 'stopped'),
(85, 85, '2025-05-01 00:15:26', '2025-05-01 00:15:48', 22, 'stopped'),
(86, 86, '2025-05-01 00:54:30', '2025-05-01 00:54:39', 9, 'stopped'),
(87, 87, '2025-05-01 00:54:45', '2025-05-01 00:55:02', 17, 'stopped'),
(88, 88, '2025-05-01 21:13:18', '2025-05-01 21:14:01', 43, 'stopped'),
(89, 89, '2025-05-01 21:18:15', '2025-05-01 21:18:26', 11, 'stopped'),
(90, 90, '2025-05-01 21:18:38', '2025-05-01 21:18:47', 9, 'stopped'),
(91, 91, '2025-05-01 21:20:36', '2025-05-01 21:21:40', 64, 'stopped'),
(92, 92, '2025-05-01 21:20:41', '2025-05-01 21:22:12', 91, 'stopped'),
(93, 93, '2025-05-01 21:20:45', '2025-05-01 21:22:34', 109, 'stopped'),
(94, 94, '2025-05-01 21:20:49', '2025-05-01 21:23:10', 141, 'stopped'),
(95, 95, '2025-05-01 21:20:52', '2025-05-01 21:23:33', 161, 'stopped'),
(96, 96, '2025-05-01 21:20:57', '2025-05-01 21:23:55', 178, 'stopped'),
(97, 97, '2025-05-01 21:21:01', '2025-05-01 21:24:48', 227, 'stopped'),
(98, 98, '2025-05-01 21:21:05', '2025-05-01 21:24:21', 196, 'stopped'),
(99, 99, '2025-05-01 21:21:10', '2025-05-01 21:25:34', 264, 'stopped'),
(100, 100, '2025-05-01 21:21:14', '2025-05-01 21:25:10', 236, 'stopped'),
(101, 101, '2025-05-01 21:21:18', '2025-05-01 21:26:50', 332, 'stopped'),
(102, 102, '2025-05-01 21:21:24', '2025-05-01 21:26:20', 296, 'stopped'),
(103, 103, '2025-05-01 21:21:28', '2025-05-01 21:27:19', 351, 'stopped'),
(104, 104, '2025-05-01 21:21:31', '2025-05-01 21:25:52', 261, 'stopped'),
(105, 105, '2025-05-03 13:04:15', '2025-05-03 13:04:16', 1, 'stopped'),
(106, 106, '2025-05-03 14:19:17', '2025-05-03 14:19:18', 1, 'stopped'),
(107, 107, '2025-05-04 10:52:20', '2025-05-04 10:52:28', 8, 'stopped'),
(108, 108, '2025-05-04 23:35:48', '2025-05-04 23:38:19', 151, 'stopped'),
(109, 109, '2025-05-07 11:52:49', '2025-05-07 11:58:48', 359, 'stopped'),
(110, 110, '2025-05-07 16:11:47', '2025-05-07 16:11:52', 5, 'stopped'),
(111, 111, '2025-05-07 20:28:04', '2025-05-07 20:28:12', 8, 'stopped');

-- --------------------------------------------------------

--
-- Structure de la table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `cities`
--

INSERT INTO `cities` (`id`, `name`, `created_at`) VALUES
(1, 'Adrar', '2025-05-06 22:15:45'),
(2, 'Chlef', '2025-05-06 22:15:45'),
(3, 'Laghouat', '2025-05-06 22:15:45'),
(4, 'Oum El Bouaghi', '2025-05-06 22:15:45'),
(5, 'Batna', '2025-05-06 22:15:45'),
(6, 'Bejaia', '2025-05-06 22:15:45'),
(7, 'Biskra', '2025-05-06 22:15:45'),
(8, 'Bechar', '2025-05-06 22:15:45'),
(9, 'Blida', '2025-05-06 22:15:45'),
(10, 'Bouira', '2025-05-06 22:15:45'),
(11, 'Tamanrasset', '2025-05-06 22:15:45'),
(12, 'Tebessa', '2025-05-06 22:15:45'),
(13, 'Tlemcen', '2025-05-06 22:15:45'),
(14, 'Tiaret', '2025-05-06 22:15:45'),
(15, 'Tizi Ouzou', '2025-05-06 22:15:45'),
(16, 'Algiers', '2025-05-06 22:15:45'),
(17, 'Djelfa', '2025-05-06 22:15:45'),
(18, 'Jijel', '2025-05-06 22:15:45'),
(19, 'Setif', '2025-05-06 22:15:45'),
(20, 'Saida', '2025-05-06 22:15:45'),
(21, 'Skikda', '2025-05-06 22:15:45'),
(22, 'Sidi Bel Abbes', '2025-05-06 22:15:45'),
(23, 'Annaba', '2025-05-06 22:15:45'),
(24, 'Guelma', '2025-05-06 22:15:45'),
(25, 'Constantine', '2025-05-06 22:15:45'),
(26, 'Medea', '2025-05-06 22:15:45'),
(27, 'Mostaganem', '2025-05-06 22:15:45'),
(28, 'M\'Sila', '2025-05-06 22:15:45'),
(29, 'Mascara', '2025-05-06 22:15:45'),
(30, 'Ouargla', '2025-05-06 22:15:45'),
(31, 'Oran', '2025-05-06 22:15:45'),
(32, 'El Bayadh', '2025-05-06 22:15:45'),
(33, 'Illizi', '2025-05-06 22:15:45'),
(34, 'Bordj Bou Arreridj', '2025-05-06 22:15:45'),
(35, 'Boumerdes', '2025-05-06 22:15:45'),
(36, 'El Tarf', '2025-05-06 22:15:45'),
(37, 'Tindouf', '2025-05-06 22:15:45'),
(38, 'Tissemsilt', '2025-05-06 22:15:45'),
(39, 'El Oued', '2025-05-06 22:15:45'),
(40, 'Khenchela', '2025-05-06 22:15:45'),
(41, 'Souk Ahras', '2025-05-06 22:15:45'),
(42, 'Tipaza', '2025-05-06 22:15:45'),
(43, 'Mila', '2025-05-06 22:15:45'),
(44, 'Ain Defla', '2025-05-06 22:15:45'),
(45, 'Naama', '2025-05-06 22:15:45'),
(46, 'Ain Temouchent', '2025-05-06 22:15:45'),
(47, 'Ghardaia', '2025-05-06 22:15:45'),
(48, 'Relizane', '2025-05-06 22:15:45'),
(49, 'El Mghair', '2025-05-06 22:15:45'),
(50, 'El Meniaa', '2025-05-06 22:15:45'),
(51, 'Ouled Djellal', '2025-05-06 22:15:45'),
(52, 'Bordj Badji Mokhtar', '2025-05-06 22:15:45'),
(53, 'Beni Abbes', '2025-05-06 22:15:45'),
(54, 'Timimoun', '2025-05-06 22:15:45'),
(55, 'Touggourt', '2025-05-06 22:15:45'),
(56, 'Djanet', '2025-05-06 22:15:45'),
(57, 'In Salah', '2025-05-06 22:15:45'),
(58, 'In Guezzam', '2025-05-06 22:15:45');

-- --------------------------------------------------------

--
-- Structure de la table `client_notifications`
--

CREATE TABLE `client_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `client_notifications`
--

INSERT INTO `client_notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 52, 'Your consultation on 22 Apr 2025 at 16:15 has been confirmed by the expert.', 1, '2025-04-22 14:15:45'),
(2, 52, 'Your consultation on 22 Apr 2025 at 16:28 has been confirmed by the expert.', 1, '2025-04-22 14:28:53'),
(3, 52, 'Your consultation on 22 Apr 2025 at 16:28 has been confirmed by the expert.', 1, '2025-04-22 18:06:03'),
(4, 52, 'Your consultation on 22 Apr 2025 at 20:08 has been confirmed by the expert.', 1, '2025-04-22 18:08:36'),
(5, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:08:58'),
(6, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:09:05'),
(7, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:09:10'),
(8, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:09:23'),
(9, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:09:27'),
(10, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:09:31'),
(11, 52, 'Your consultation has been paused by the expert.', 1, '2025-04-22 18:09:48'),
(12, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:10:00'),
(13, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:10:11'),
(14, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:10:19'),
(15, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:10:20'),
(16, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:10:22'),
(17, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:10:28'),
(18, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:10:30'),
(19, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:11:19'),
(20, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:11:23'),
(21, 52, 'Your consultation has been resumed by the expert.', 1, '2025-04-22 18:11:49'),
(22, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:12:11'),
(23, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:12:27'),
(24, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:13:26'),
(25, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:13:48'),
(26, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:13:51'),
(27, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-22 18:21:42'),
(28, 52, 'Your consultation on 22 Apr 2025 at 20:27 has been confirmed by the expert.', 1, '2025-04-22 18:27:57'),
(29, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:28:11'),
(30, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:28:16'),
(31, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:28:32'),
(32, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:28:34'),
(33, 52, 'You have a new message from your expert.', 1, '2025-04-22 18:33:38'),
(34, 52, 'Your consultation on 22 Apr 2025 at 21:06 has been confirmed by the expert.', 1, '2025-04-22 19:06:12'),
(35, 52, 'You have a new message from your expert.', 1, '2025-04-22 19:06:59'),
(36, 52, 'You have a new message from your expert.', 1, '2025-04-22 19:07:08'),
(37, 52, 'You have a new message from your expert.', 1, '2025-04-22 19:07:08'),
(38, 52, 'You have a new message from your expert.', 1, '2025-04-22 19:07:57'),
(39, 52, 'Your consultation on 22 Apr 2025 at 21:08 has been confirmed by the expert.', 1, '2025-04-22 19:15:43'),
(40, 52, 'Your consultation on 23 Apr 2025 at 13:34 has been confirmed by the expert.', 1, '2025-04-23 11:37:25'),
(41, 52, 'You have a new message from your expert.', 1, '2025-04-23 11:40:17'),
(42, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-23 11:42:27'),
(43, 52, 'Your consultation on 24 Apr 2025 at 11:54 has been confirmed by the expert.', 1, '2025-04-24 09:54:31'),
(44, 52, 'Your consultation on 24 Apr 2025 at 11:54 has been confirmed by the expert.', 1, '2025-04-24 09:54:31'),
(45, 52, 'Your consultation on 24 Apr 2025 at 12:02 has been confirmed by the expert.', 1, '2025-04-24 10:02:21'),
(46, 52, 'Your consultation on 24 Apr 2025 at 12:03 has been confirmed by the expert.', 1, '2025-04-24 10:05:05'),
(47, 52, 'Your consultation on 24 Apr 2025 at 12:22 has been confirmed by the expert.', 1, '2025-04-24 10:22:26'),
(48, 52, 'Your consultation on 24 Apr 2025 at 12:22 has been confirmed by the expert.', 1, '2025-04-24 10:23:49'),
(49, 52, 'Your consultation on 24 Apr 2025 at 12:23 has been confirmed by the expert.', 1, '2025-04-24 10:23:55'),
(50, 52, 'Your consultation on 24 Apr 2025 at 12:30 has been confirmed by the expert.', 1, '2025-04-24 10:30:31'),
(51, 52, 'Your consultation on 24 Apr 2025 at 12:32 has been confirmed by the expert.', 1, '2025-04-24 10:33:35'),
(52, 52, 'Your consultation on 24 Apr 2025 at 12:34 has been confirmed by the expert.', 1, '2025-04-24 10:35:32'),
(53, 52, 'Your consultation on 24 Apr 2025 at 12:36 has been confirmed by the expert.', 1, '2025-04-24 10:38:25'),
(54, 52, 'Your consultation on 24 Apr 2025 at 12:39 has been confirmed by the expert.', 1, '2025-04-24 10:39:45'),
(55, 52, 'Your consultation on 24 Apr 2025 at 12:42 has been confirmed by the expert.', 1, '2025-04-24 10:42:12'),
(56, 52, 'Your consultation on 24 Apr 2025 at 12:43 has been confirmed by the expert.', 1, '2025-04-24 10:43:58'),
(57, 52, 'Your consultation on 24 Apr 2025 at 12:50 has been confirmed by the expert.', 1, '2025-04-24 10:51:31'),
(58, 52, 'Your consultation on 24 Apr 2025 at 13:13 has been confirmed by the expert.', 1, '2025-04-24 11:13:52'),
(59, 52, 'Your consultation on 24 Apr 2025 at 13:14 has been confirmed by the expert.', 1, '2025-04-24 11:15:07'),
(60, 52, 'Your consultation on 24 Apr 2025 at 13:16 has been confirmed by the expert.', 1, '2025-04-24 11:16:45'),
(61, 52, 'Your consultation on 24 Apr 2025 at 13:18 has been confirmed by the expert.', 1, '2025-04-24 11:19:32'),
(62, 52, 'Your consultation on 24 Apr 2025 at 13:29 has been confirmed by the expert.', 1, '2025-04-24 11:30:22'),
(63, 52, 'Your consultation request with Oussam has been automatically cancelled because the expert is offline.', 1, '2025-04-24 11:31:27'),
(64, 52, 'Your consultation on 24 Apr 2025 at 17:04 has been confirmed by the expert.', 1, '2025-04-24 15:05:13'),
(65, 52, 'You have a new message from your expert.', 1, '2025-04-24 15:05:45'),
(66, 52, 'Your consultation on 24 Apr 2025 at 22:13 has been confirmed by the expert.', 1, '2025-04-24 20:13:29'),
(67, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-24 20:18:30'),
(68, 52, 'Your consultation on 24 Apr 2025 at 23:14 has been confirmed by the expert.', 1, '2025-04-24 21:14:52'),
(69, 52, 'You have a new message from your expert.', 1, '2025-04-24 21:15:16'),
(70, 52, 'You have a new message from your expert.', 1, '2025-04-24 21:15:26'),
(71, 52, 'You have a new message from your expert.', 1, '2025-04-24 21:15:42'),
(72, 52, 'You have a new message from your expert.', 1, '2025-04-24 21:19:29'),
(73, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-24 21:19:54'),
(74, 52, 'Your consultation on 24 Apr 2025 at 23:47 has been confirmed by the expert.', 1, '2025-04-24 21:48:28'),
(75, 52, 'Your consultation on 25 Apr 2025 at 00:26 has been confirmed by the expert.', 1, '2025-04-24 22:39:39'),
(76, 52, 'Your consultation on 25 Apr 2025 at 00:40 has been confirmed by the expert.', 1, '2025-04-24 22:41:19'),
(77, 52, 'Your consultation on 25 Apr 2025 at 00:52 has been confirmed by the expert.', 1, '2025-04-24 22:53:13'),
(78, 52, 'Your consultation on 25 Apr 2025 at 20:43 has been confirmed by the expert.', 1, '2025-04-25 18:44:05'),
(79, 52, 'You have a new message from your expert.', 1, '2025-04-25 18:51:20'),
(80, 52, 'Your consultation on 25 Apr 2025 at 21:01 has been confirmed by the expert.', 1, '2025-04-25 19:01:58'),
(81, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-25 19:19:05'),
(82, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-25 19:19:06'),
(83, 52, 'Your consultation on 25 Apr 2025 at 21:20 has been confirmed by the expert.', 1, '2025-04-25 19:20:46'),
(84, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-25 19:20:54'),
(85, 52, 'Your consultation on 25 Apr 2025 at 21:40 has been confirmed by the expert.', 1, '2025-04-25 19:41:05'),
(86, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:41:31'),
(87, 52, 'Your consultation on 25 Apr 2025 at 21:42 has been confirmed by the expert.', 1, '2025-04-25 19:42:08'),
(88, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:42:17'),
(89, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:42:19'),
(90, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:42:21'),
(91, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:42:24'),
(92, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:43:11'),
(93, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:44:48'),
(94, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:44:54'),
(95, 52, 'You have a new message from your expert.', 1, '2025-04-25 19:45:53'),
(96, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-25 19:47:08'),
(97, 52, 'Your consultation on 25 Apr 2025 at 22:22 has been confirmed by the expert.', 1, '2025-04-25 20:22:10'),
(98, 52, 'You have a new message from your expert.', 1, '2025-04-25 20:24:41'),
(99, 52, 'Your consultation request has been declined by the expert. Reason: manich ga3d', 1, '2025-04-26 11:22:21'),
(100, 52, 'Your fund request of 2,000.00 has been approved and added to your balance.', 1, '2025-04-26 19:36:21'),
(101, 52, 'Your fund request of 2,000.00 DA has been approved and added to your balance.', 1, '2025-04-26 19:52:25'),
(102, 52, 'Your support request has been resolved. Thank you for your patience.', 1, '2025-04-27 11:48:22'),
(103, 52, 'Your support request has been resolved. Thank you for your patience.', 1, '2025-04-27 12:14:54'),
(104, 52, 'Your support request has been resolved. Thank you for your patience.', 1, '2025-04-27 12:15:04'),
(105, 52, 'Your support request has been resolved. Thank you for your patience.', 1, '2025-04-27 12:15:54'),
(106, 52, 'Your support request has been resolved. Thank you for your patience.', 1, '2025-04-27 12:15:58'),
(107, 52, 'Your support request has been resolved. Thank you for your patience.', 1, '2025-04-27 12:16:02'),
(108, 52, 'Your consultation on 27 Apr 2025 at 19:00 has been confirmed by the expert.', 1, '2025-04-27 17:02:42'),
(109, 52, 'You have a new message from your expert.', 1, '2025-04-27 17:03:24'),
(110, 52, 'Your consultation on 27 Apr 2025 at 19:17 has been confirmed by the expert.', 1, '2025-04-27 17:18:37'),
(111, 52, 'You have a new message from your expert.', 1, '2025-04-27 17:19:08'),
(112, 52, 'You have a new message from your expert.', 1, '2025-04-27 17:19:22'),
(113, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 17:24:38'),
(114, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 17:24:39'),
(115, 52, 'Your consultation on 27 Apr 2025 at 19:24 has been confirmed by the expert.', 1, '2025-04-27 17:25:01'),
(116, 52, 'You have a new message from your expert.', 1, '2025-04-27 17:26:06'),
(117, 52, 'You have a new message from your expert.', 1, '2025-04-27 17:26:20'),
(118, 52, 'You have a new message from your expert.', 1, '2025-04-27 17:26:31'),
(119, 52, 'Your consultation on 27 Apr 2025 at 19:51 has been confirmed by the expert.', 1, '2025-04-27 17:51:21'),
(120, 52, 'You have a new message from your expert.', 1, '2025-04-27 17:51:35'),
(121, 52, 'You have a new message from your expert.', 1, '2025-04-27 17:52:28'),
(122, 52, 'Your consultation on 27 Apr 2025 at 20:06 has been confirmed by the expert.', 1, '2025-04-27 18:06:54'),
(123, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 18:07:06'),
(124, 52, 'Your consultation on 27 Apr 2025 at 20:07 has been confirmed by the expert.', 1, '2025-04-27 18:07:54'),
(125, 52, 'You have a new message from your expert.', 1, '2025-04-27 18:08:02'),
(126, 52, 'You have a new message from your expert.', 1, '2025-04-27 18:08:11'),
(127, 52, 'You have a new message from your expert.', 1, '2025-04-27 18:08:13'),
(128, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 18:16:01'),
(129, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 18:16:02'),
(130, 52, 'Your consultation on 27 Apr 2025 at 20:16 has been confirmed by the expert.', 1, '2025-04-27 18:16:36'),
(131, 52, 'You have a new message from your expert.', 1, '2025-04-27 18:16:51'),
(132, 52, 'You have a new message from your expert.', 1, '2025-04-27 18:16:54'),
(133, 52, 'Your consultation has been ended by the expert.', 1, '2025-04-27 18:17:41'),
(134, 52, 'Your consultation on 27 Apr 2025 at 20:18 has been confirmed by the expert.', 1, '2025-04-27 18:18:28'),
(135, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 18:21:13'),
(136, 52, 'Your consultation on 27 Apr 2025 at 20:18 has been confirmed by the expert.', 1, '2025-04-27 18:29:26'),
(137, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 18:29:57'),
(138, 52, 'Your consultation on 27 Apr 2025 at 20:49 has been confirmed by the expert.', 1, '2025-04-27 18:49:51'),
(139, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 18:50:13'),
(140, 52, 'Your consultation on 27 Apr 2025 at 20:50 has been confirmed by the expert.', 1, '2025-04-27 18:50:41'),
(141, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 19:03:25'),
(142, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 19:03:26'),
(143, 52, 'Your consultation on 27 Apr 2025 at 21:03 has been confirmed by the expert.', 1, '2025-04-27 19:04:01'),
(144, 52, 'Your consultation on 27 Apr 2025 at 21:12 has been confirmed by the expert.', 1, '2025-04-27 19:13:06'),
(145, 52, 'Your consultation on 27 Apr 2025 at 21:19 has been confirmed by the expert.', 1, '2025-04-27 19:19:25'),
(146, 52, 'Your consultation on 27 Apr 2025 at 21:47 has been confirmed by the expert.', 1, '2025-04-27 19:47:50'),
(147, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 19:48:09'),
(148, 52, 'Your consultation on 27 Apr 2025 at 22:11 has been confirmed by the expert.', 1, '2025-04-27 20:11:27'),
(149, 52, 'You have a new message from your expert.', 1, '2025-04-27 20:12:02'),
(150, 52, 'You have a new message from your expert.', 1, '2025-04-27 20:13:01'),
(151, 52, 'You have a new message from your expert.', 1, '2025-04-27 20:16:08'),
(152, 52, 'You have a new message from your expert.', 1, '2025-04-27 20:16:15'),
(153, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 20:16:27'),
(154, 52, 'Your consultation on 27 Apr 2025 at 22:29 has been confirmed by the expert.', 1, '2025-04-27 20:29:45'),
(155, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 20:34:47'),
(156, 52, 'Your consultation on 27 Apr 2025 at 22:40 has been confirmed by the expert.', 1, '2025-04-27 20:41:06'),
(157, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 20:46:07'),
(158, 52, 'Your consultation on 27 Apr 2025 at 23:59 has been confirmed by the expert.', 1, '2025-04-27 21:59:29'),
(159, 52, 'Your consultation on 28 Apr 2025 at 00:40 has been confirmed by the expert.', 1, '2025-04-27 22:40:56'),
(160, 52, 'You have a new message from your expert.', 1, '2025-04-27 22:41:28'),
(161, 52, 'You have a new message from your expert.', 1, '2025-04-27 22:42:08'),
(162, 52, 'Your consultation on 28 Apr 2025 at 00:58 has been confirmed by the expert.', 1, '2025-04-27 22:58:24'),
(163, 52, 'Your consultation on 28 Apr 2025 at 00:58 has been confirmed by the expert.', 1, '2025-04-27 22:58:24'),
(164, 52, 'You have a new message from your expert.', 1, '2025-04-27 23:03:46'),
(165, 52, 'You have a new message from your expert.', 1, '2025-04-27 23:11:40'),
(166, 52, 'You have a new message from your expert.', 1, '2025-04-27 23:13:14'),
(167, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-27 23:13:24'),
(168, 52, 'Your consultation on 28 Apr 2025 at 01:37 has been confirmed by the expert.', 1, '2025-04-27 23:52:29'),
(169, 52, 'You have a new message from your expert.', 1, '2025-04-27 23:53:04'),
(170, 52, 'Your report has been updated to: Resolved.', 1, '2025-04-28 07:33:22'),
(171, 51, 'Your report has been updated to: Dismissed.', 0, '2025-04-28 08:11:12'),
(172, 52, 'Your consultation on 28 Apr 2025 at 11:30 has been confirmed by the expert.', 1, '2025-04-28 09:30:56'),
(173, 52, 'You have a new message from your expert.', 1, '2025-04-28 09:36:32'),
(174, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-28 09:36:32'),
(175, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-28 09:36:33'),
(176, 52, 'Your consultation on 28 Apr 2025 at 14:03 has been confirmed by the expert.', 1, '2025-04-28 12:03:45'),
(177, 52, 'Your consultation has been ended by the expert.', 1, '2025-04-28 12:04:56'),
(186, 52, 'Your consultation on 29 Apr 2025 at 20:53 has been confirmed by the expert.', 1, '2025-04-29 18:53:58'),
(187, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-29 18:56:30'),
(188, 52, 'Your consultation on 29 Apr 2025 at 20:58 has been confirmed by the expert.', 1, '2025-04-29 19:28:26'),
(189, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-29 19:31:05'),
(190, 52, 'Your consultation on 29 Apr 2025 at 21:39 has been confirmed by the expert.', 1, '2025-04-29 19:39:45'),
(191, 52, 'Your consultation on 29 Apr 2025 at 21:39 has been confirmed by the expert.', 1, '2025-04-29 19:39:50'),
(192, 52, 'Your consultation request with Oussam was automatically cancelled because the expert went offline.', 1, '2025-04-29 19:57:48'),
(193, 52, 'Your consultation request with Oussam was automatically cancelled because the expert went offline.', 1, '2025-04-29 20:07:34'),
(194, 52, 'Your consultation request has been declined by the expert.', 1, '2025-04-29 21:03:26'),
(195, 52, 'Your consultation request has been declined by the expert.', 1, '2025-04-29 21:03:31'),
(196, 52, 'Your consultation request with Oussam was automatically cancelled because the expert went offline.', 1, '2025-04-29 21:23:07'),
(197, 52, 'Your consultation request has been declined by the expert. Reason: wdfbftg,n', 1, '2025-04-29 22:33:46'),
(198, 52, 'Your consultation on 30 Apr 2025 at 19:44 has been confirmed by the expert.', 1, '2025-04-30 17:50:20'),
(199, 52, 'Your consultation on 30 Apr 2025 at 19:54 has been confirmed by the expert.', 1, '2025-04-30 17:58:52'),
(200, 52, 'You have a new message from your expert.', 1, '2025-04-30 17:59:56'),
(201, 52, 'You have a new message from your expert.', 1, '2025-04-30 17:59:58'),
(202, 52, 'You have a new message from your expert.', 1, '2025-04-30 18:00:00'),
(203, 52, 'You have a new message from your expert.', 1, '2025-04-30 18:00:02'),
(204, 52, 'You have a new message from your expert.', 1, '2025-04-30 18:00:05'),
(205, 52, 'Your consultation has been paused by the expert.', 1, '2025-04-30 18:00:09'),
(206, 52, 'Your consultation has been paused by the expert.', 1, '2025-04-30 18:00:11'),
(207, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-30 18:00:15'),
(208, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-30 18:00:20'),
(211, 52, 'Your consultation on 30 Apr 2025 at 20:02 has been confirmed by the expert.', 1, '2025-04-30 18:02:59'),
(213, 52, 'You have a new message from your expert.', 1, '2025-04-30 18:03:26'),
(214, 52, 'Your consultation on 30 Apr 2025 at 21:21 has been confirmed by the expert.', 1, '2025-04-30 20:39:37'),
(215, 52, 'Your consultation has been ended by the expert.', 1, '2025-04-30 20:40:12'),
(216, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-30 20:56:10'),
(217, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-30 20:56:11'),
(218, 52, 'Your consultation request with Ouss was automatically cancelled because the expert went offline.', 1, '2025-04-30 20:56:25'),
(219, 52, 'Your consultation request with Ouss was automatically cancelled because the expert went offline.', 1, '2025-04-30 20:56:25'),
(220, 52, 'Your consultation on 30 Apr 2025 at 23:03 has been confirmed by the expert.', 1, '2025-04-30 21:04:31'),
(221, 52, 'You have a new message from your expert.', 1, '2025-04-30 21:05:09'),
(222, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:07:57'),
(223, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:08:07'),
(224, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:08:18'),
(225, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:08:38'),
(226, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:08:48'),
(227, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:08:58'),
(228, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:09:08'),
(229, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:09:19'),
(230, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:09:29'),
(231, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:09:40'),
(232, 52, 'rah endi consultation ki nkaml nrslk', 1, '2025-04-30 21:09:51'),
(233, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-04-30 22:17:11'),
(234, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-04-30 22:18:28'),
(235, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-04-30 22:18:41'),
(236, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-04-30 23:14:21'),
(237, 52, 'Your consultation on 30 Apr 2025 at 23:05 has been confirmed by the expert.', 1, '2025-04-30 23:15:26'),
(238, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-04-30 23:36:26'),
(239, 52, 'Your consultation on 01 May 2025 at 01:53 has been confirmed by the expert.', 1, '2025-04-30 23:54:30'),
(240, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-30 23:54:39'),
(241, 52, 'Your consultation on 01 May 2025 at 01:53 has been confirmed by the expert.', 1, '2025-04-30 23:54:45'),
(242, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-04-30 23:55:02'),
(243, 54, 'Your report has been updated to: Pending.', 1, '2025-05-01 08:55:34'),
(244, 54, 'Your report has been updated to: In_progress.', 1, '2025-05-01 08:55:43'),
(245, 54, 'Your report has been updated to: Resolved.', 1, '2025-05-01 08:55:51'),
(248, 54, 'Your report has been updated to: Dismissed.', 1, '2025-05-01 10:32:21'),
(249, 52, 'A report against you has been dismissed. Please check your account status.', 1, '2025-05-01 10:32:21'),
(251, 54, 'A report against you has been dismissed. Please check your account status.', 1, '2025-05-01 10:38:42'),
(253, 54, 'Your report has been updated to: .', 1, '2025-05-01 10:52:19'),
(255, 54, 'Your report has been updated to: Resolved.', 1, '2025-05-01 10:57:29'),
(256, 54, 'Your report has been updated to: .', 1, '2025-05-01 11:25:08'),
(258, 54, 'Your report has been updated to: Dismissed.', 1, '2025-05-01 11:25:42'),
(260, 54, 'Your report has been updated to: Dismissed.', 1, '2025-05-01 11:48:34'),
(261, 52, 'A report against you has been dismissed. Please check your account status.', 1, '2025-05-01 11:48:34'),
(263, 54, 'Your report has been updated to: Resolved.', 1, '2025-05-01 12:01:07'),
(266, 54, 'Your report has been updated to: Resolved.', 1, '2025-05-01 16:59:46'),
(270, 4, 'Your account suspension has ended. You can now use the platform again.', 0, '2025-05-01 19:22:41'),
(307, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-01 20:46:40'),
(310, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-01 20:47:04'),
(313, 52, 'Your consultation request has been rejected by the expert. Reason: mklj,ml', 1, '2025-05-03 11:20:36'),
(314, 52, 'Your consultation request has been rejected by the expert. Reason: sdfdg', 1, '2025-05-03 11:27:08'),
(315, 52, 'Your consultation request has been rejected by the expert. Reason: kjoi,lk', 1, '2025-05-03 11:48:53'),
(316, 52, 'Your consultation on 03 May 2025 at 14:04 has been confirmed by the expert.', 1, '2025-05-03 12:04:15'),
(317, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-05-03 12:04:16'),
(318, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-05-03 12:04:17'),
(319, 52, 'Your consultation request has been rejected by the expert. Reason: jkno', 1, '2025-05-03 12:05:10'),
(320, 52, 'Your consultation request has been rejected by the expert. Reason: iosjkoc', 1, '2025-05-03 12:12:19'),
(321, 52, 'Your consultation request has been rejected by the expert. Reason: kljpioj,', 1, '2025-05-03 12:16:30'),
(322, 52, 'Your consultation request has been rejected by the expert. Reason: jkn', 1, '2025-05-03 12:20:07'),
(323, 52, 'Your consultation request has been rejected by the expert. Reason: kjioj', 1, '2025-05-03 12:22:09'),
(324, 52, 'Your consultation request has been rejected by the expert. Reason: oijoi', 1, '2025-05-03 12:31:19'),
(325, 52, 'Your consultation request has been rejected by the expert. Reason: jknk', 1, '2025-05-03 13:00:57'),
(326, 52, 'Your consultation request has been rejected by the expert. Reason: jkhuiojhio', 1, '2025-05-03 13:13:57'),
(327, 52, 'Your consultation request has been rejected by the expert. Reason: dmld^v', 1, '2025-05-03 13:16:00'),
(328, 52, 'Your consultation on 03 May 2025 at 15:19 has been confirmed by the expert.', 1, '2025-05-03 13:19:17'),
(329, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-05-03 13:19:18'),
(330, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-05-03 13:19:19'),
(331, 52, 'Your consultation request has been rejected by the expert. Reason: SDGVFDRHBFG', 1, '2025-05-03 13:19:50'),
(332, 52, 'Your consultation request has been rejected by the expert. Reason: iljoi', 1, '2025-05-03 13:30:06'),
(333, 52, 'Your consultation request has been rejected by the expert. Reason: dvdcv', 1, '2025-05-03 13:46:16'),
(334, 52, 'Your consultation request has been rejected by the expert. Reason: poe)gàr', 1, '2025-05-03 13:51:08'),
(335, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-03 14:05:36'),
(336, 52, 'Your consultation request has been rejected by the expert. Reason: zzvsd', 1, '2025-05-03 14:05:47'),
(337, 52, 'Your consultation request has been rejected by the expert. Reason: uiyhoijkl', 1, '2025-05-04 08:44:37'),
(338, 52, 'Your consultation request has been rejected by the expert. Reason: iojpjkpojk', 1, '2025-05-04 08:46:15'),
(339, 52, 'Your consultation request has been rejected by the expert. Reason: segfvd', 1, '2025-05-04 08:50:44'),
(340, 52, 'Your consultation request has been rejected by the expert. Reason: dvsd', 1, '2025-05-04 08:55:47'),
(341, 52, 'Your consultation request has been rejected by the expert. Reason: sdcwd', 1, '2025-05-04 08:56:24'),
(342, 52, 'Your consultation request has been rejected by the expert. Reason: ijpokp', 1, '2025-05-04 09:05:37'),
(343, 52, 'Your consultation request has been rejected by the expert. Reason: pok^l^p', 1, '2025-05-04 09:22:32'),
(344, 52, 'Your consultation request has been rejected by the expert. Reason: wcwx', 1, '2025-05-04 09:30:40'),
(345, 52, 'Your consultation on 04 May 2025 at 11:51 has been confirmed by the expert.', 1, '2025-05-04 09:52:20'),
(346, 52, 'Your consultation has been marked as completed by the expert.', 1, '2025-05-04 09:52:28'),
(347, 52, 'Your consultation request has been rejected by the expert. Reason: zefmùlsef^d', 1, '2025-05-04 09:52:38'),
(348, 52, 'Your consultation request has been rejected by the expert. Reason: yàçupo', 1, '2025-05-04 10:29:11'),
(349, 52, 'Your consultation request has been rejected by the expert. Reason: kljpokml', 1, '2025-05-04 17:59:17'),
(350, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-04 18:14:45'),
(351, 52, 'Your consultation request has been rejected by the expert. Reason: mlqksdoc', 1, '2025-05-04 18:14:58'),
(352, 52, 'Your consultation request has been rejected by the expert. Reason: mùqclq', 1, '2025-05-04 18:15:11'),
(353, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-04 18:39:27'),
(354, 52, 'Your consultation request has been rejected by the expert. Reason: jedpoze', 1, '2025-05-04 18:40:03'),
(355, 52, 'Your consultation request has been rejected by the expert. Reason: mlkp', 1, '2025-05-04 19:15:36'),
(356, 52, 'Your consultation request has been rejected by the expert. Reason: ^poikp^mù', 1, '2025-05-04 19:46:28'),
(357, 52, 'Your consultation request has been rejected by the expert. Reason: zdcqs', 1, '2025-05-04 20:48:05'),
(358, 52, 'Your consultation request has been rejected by the expert. Reason: mlkpom', 1, '2025-05-04 20:49:19'),
(359, 52, 'Your consultation request with Oussam was automatically cancelled because the expert went offline.', 1, '2025-05-04 21:19:19'),
(360, 52, 'Your consultation request has been rejected by the expert. Reason: ml;m;', 1, '2025-05-04 21:26:00'),
(361, 52, 'Your consultation request has been rejected by the expert. Reason: KLJKLK', 1, '2025-05-04 21:27:33'),
(362, 52, 'Your consultation request has been rejected by the expert. Reason: iojpomkl', 1, '2025-05-04 21:30:38'),
(363, 52, 'Your consultation request with Oussam was automatically cancelled because the expert went offline.', 1, '2025-05-04 21:30:45'),
(364, 52, 'Your consultation request has been rejected by the expert. Reason: mlkùm', 1, '2025-05-04 21:51:55'),
(365, 52, 'Your consultation request with Oussam was automatically cancelled because the expert went offline.', 1, '2025-05-04 22:16:37'),
(366, 52, 'Your consultation request has been rejected by the expert. Reason: erfsdcx', 1, '2025-05-04 22:18:00'),
(367, 68, 'Your consultation request has been rejected by the expert. Reason: kjpkom', 1, '2025-05-04 22:20:06'),
(368, 68, 'Your consultation request has been rejected by the expert. Reason: ioupl', 1, '2025-05-04 22:22:23'),
(369, 68, 'Your consultation request has been rejected by the expert. Reason: kljmol', 1, '2025-05-04 22:28:50'),
(370, 68, 'Your consultation request with Oussam was automatically cancelled because the expert went offline.', 1, '2025-05-04 22:33:40'),
(371, 68, 'Your consultation on 05 May 2025 at 00:35 has been confirmed by the expert.', 1, '2025-05-04 22:35:48'),
(372, 68, 'Your consultation has been marked as completed by the expert.', 1, '2025-05-04 22:38:19'),
(373, 68, 'Your consultation request has been rejected by the expert. Reason: sdvcx', 1, '2025-05-04 22:39:30'),
(374, 68, 'aya', 1, '2025-05-04 22:40:22'),
(375, 68, 'ouss', 1, '2025-05-04 22:48:27'),
(376, 52, 'Your consultation request has been rejected by the expert. Reason: ioujol', 1, '2025-05-05 09:43:43'),
(377, 52, 'Your consultation request has been rejected by the expert. Reason: poià)o', 1, '2025-05-05 09:45:52'),
(378, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-05 09:50:44'),
(379, 52, 'Your consultation request has been rejected by the expert. Reason: ouss', 1, '2025-05-05 09:53:17'),
(380, 52, 'Your consultation request has been rejected by the expert. Reason: poà)', 1, '2025-05-05 09:56:23'),
(381, 52, 'Your consultation request has been rejected by the expert. Reason: ^pokpôp', 1, '2025-05-05 09:56:50'),
(382, 52, 'Your consultation request has been rejected by the expert. Reason: po)=p', 1, '2025-05-05 09:57:29'),
(383, 52, 'Your consultation request has been rejected by the expert. Reason: Ouussama', 1, '2025-05-05 09:59:26'),
(384, 52, 'Your consultation request has been rejected by the expert. Reason: klmkà^pl', 1, '2025-05-05 10:00:45'),
(385, 52, 'Your consultation request has been rejected by the expert. Reason: djkpkjouigyftr', 1, '2025-05-05 10:08:10'),
(386, 52, 'Your consultation request has been rejected by the expert. Reason: mokp^ùlm', 1, '2025-05-05 10:16:55'),
(387, 52, 'Your consultation request has been rejected by the expert. Reason: okp^ùo', 1, '2025-05-05 10:17:26'),
(388, 52, 'Your consultation request has been rejected by the expert. Reason: opi^pl', 1, '2025-05-05 10:18:25'),
(389, 52, 'Your consultation request has been rejected by the expert. Reason: dfvfc', 1, '2025-05-05 10:19:17'),
(390, 52, 'Your consultation request was automatically cancelled because the expert went offline.', 1, '2025-05-05 10:21:22'),
(391, 52, 'Your consultation request with Ouss was automatically cancelled because the expert went offline.', 1, '2025-05-05 10:23:28'),
(392, 52, 'Your consultation request has been rejected by the expert. Reason: gresdf', 1, '2025-05-05 16:50:52'),
(393, 52, 'Your consultation request has been rejected by the expert. Reason: dvdsf', 1, '2025-05-05 16:52:33'),
(394, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-05 16:53:08'),
(395, 52, 'Your consultation request has been rejected by the expert. Reason: dsfd', 1, '2025-05-05 16:55:21'),
(396, 52, 'Your consultation request has been rejected by the expert. Reason: regvfds', 1, '2025-05-05 16:56:02'),
(397, 52, 'Your consultation request has been rejected by the expert. Reason: dvfdev', 1, '2025-05-05 17:04:39'),
(398, 52, 'Your consultation request has been rejected by the expert. Reason: ghjk;', 1, '2025-05-05 17:57:12'),
(399, 52, 'Your consultation request with Ouss was automatically cancelled because the expert went offline.', 1, '2025-05-05 18:00:07'),
(400, 52, 'Your consultation request has been rejected by the expert. Reason: edfvc', 1, '2025-05-05 18:28:43'),
(401, 52, 'Your consultation request has been rejected by the expert. Reason: ocu', 1, '2025-05-07 10:51:05'),
(402, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-07 10:52:36'),
(403, 52, 'Your consultation on 07 May 2025 at 12:52 has been confirmed by the expert.', 1, '2025-05-07 10:52:49'),
(404, 52, 'Your report has been accepted and a refund of 500.00 DA has been processed. Payment has been reimbursed.', 1, '2025-05-07 11:02:57'),
(405, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-07 11:09:10'),
(406, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-07 11:15:06'),
(407, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-07 11:17:22'),
(408, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-07 11:18:09'),
(409, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-07 11:18:28'),
(410, 52, 'You have a new message from the expert regarding your consultation.', 1, '2025-05-07 15:11:06'),
(411, 52, 'Your consultation request has been rejected by the expert. Reason: lkn,;mù:', 1, '2025-05-07 15:11:26'),
(412, 68, 'Your consultation on 07 May 2025 at 12:56 has been confirmed by the expert.', 1, '2025-05-07 15:11:47'),
(413, 68, 'Your consultation has been marked as completed by the expert.', 1, '2025-05-07 15:11:52'),
(414, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-07 15:25:04'),
(415, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-07 15:25:56'),
(416, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-07 15:26:33'),
(417, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-07 15:49:04'),
(418, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-07 15:49:15'),
(419, 52, 'Your report has been updated to: Dismissed.', 1, '2025-05-07 16:21:52'),
(420, 52, 'Your report has been updated to: Resolved.', 1, '2025-05-07 18:25:04'),
(421, 52, 'Your consultation request has been rejected by the expert. Reason: gverdf', 1, '2025-05-07 19:30:39'),
(422, 52, 'Your consultation request with OussAya was automatically cancelled because the expert went offline.', 1, '2025-05-07 21:01:12'),
(423, 51, 'Your account has been suspended for 30 days due to multiple reports.', 0, '2025-05-07 21:22:19'),
(424, 68, 'Your support request has been accepted. Thank you for your patience.', 1, '2025-05-08 21:16:21');

-- --------------------------------------------------------

--
-- Structure de la table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `consultation_date` date NOT NULL,
  `consultation_time` time NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `status` enum('pending','confirmed','completed','rejected','canceled') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `expert_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `canceled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `consultations`
--

INSERT INTO `consultations` (`id`, `client_id`, `expert_id`, `consultation_date`, `consultation_time`, `duration`, `status`, `rejection_reason`, `notes`, `expert_message`, `created_at`, `updated_at`, `canceled_at`) VALUES
(11, 52, 51, '2025-04-22', '16:15:28', 0, 'completed', NULL, 'yuuinl\r\n', NULL, '2025-04-22 14:15:28', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(12, 52, 51, '2025-04-22', '16:28:43', 15, 'completed', NULL, 'kujiukljio', NULL, '2025-04-22 14:28:43', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(13, 52, 51, '2025-04-22', '20:08:30', 5, 'completed', NULL, 'oUSS', NULL, '2025-04-22 18:08:30', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(14, 52, 51, '2025-04-22', '20:27:47', 5, 'completed', NULL, '\"\'t\'tqerqgerf', NULL, '2025-04-22 18:27:47', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(15, 52, 51, '2025-04-22', '21:06:04', 5, 'completed', NULL, 'sdfvsf', NULL, '2025-04-22 19:06:04', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(16, 52, 51, '2025-04-22', '21:08:16', 5, 'completed', NULL, 'fdhdtfngv', NULL, '2025-04-22 19:08:16', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(18, 52, 51, '2025-04-23', '13:34:06', 5, 'completed', NULL, 'consult', NULL, '2025-04-23 11:34:06', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(19, 52, 51, '2025-04-24', '11:54:15', 5, 'completed', NULL, 'hiulj', NULL, '2025-04-24 09:54:15', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(20, 52, 51, '2025-04-24', '12:02:05', 5, 'completed', NULL, 'ergr', NULL, '2025-04-24 10:02:05', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(21, 52, 51, '2025-04-24', '12:03:38', 5, 'completed', NULL, 'trht', NULL, '2025-04-24 10:03:38', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(22, 52, 51, '2025-04-24', '12:22:07', 5, 'completed', NULL, 'ygjhnkl,:', NULL, '2025-04-24 10:22:07', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(23, 52, 51, '2025-04-24', '12:23:46', 5, 'completed', NULL, 'ygjhnkl,:', NULL, '2025-04-24 10:23:46', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(25, 52, 51, '2025-04-24', '12:30:12', 5, 'completed', NULL, 'ergth', NULL, '2025-04-24 10:30:12', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(26, 52, 51, '2025-04-24', '12:32:51', 5, 'completed', NULL, 'ergth', NULL, '2025-04-24 10:32:51', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(27, 52, 51, '2025-04-24', '12:34:51', 5, 'completed', NULL, 'fze', NULL, '2025-04-24 10:34:51', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(28, 52, 51, '2025-04-24', '12:36:58', 5, 'completed', NULL, 'ergver', NULL, '2025-04-24 10:36:58', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(29, 52, 51, '2025-04-24', '12:39:35', 5, 'completed', NULL, 'rjnty', NULL, '2025-04-24 10:39:35', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(30, 52, 51, '2025-04-24', '12:42:02', 5, 'completed', NULL, 'rvfrd', NULL, '2025-04-24 10:42:02', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(31, 52, 51, '2025-04-24', '12:43:21', 5, 'completed', NULL, 'ezfer', NULL, '2025-04-24 10:43:21', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(32, 52, 51, '2025-04-24', '12:50:52', 5, 'completed', NULL, 'jhgukhl', NULL, '2025-04-24 10:50:52', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(33, 52, 51, '2025-04-24', '13:13:23', 5, 'completed', NULL, 'yjuhklm', NULL, '2025-04-24 11:13:23', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(34, 52, 51, '2025-04-24', '13:14:07', 5, 'completed', NULL, 'trdfgyh\r\n', NULL, '2025-04-24 11:14:07', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(35, 52, 51, '2025-04-24', '13:16:19', 5, 'completed', NULL, 'yuhjk', NULL, '2025-04-24 11:16:19', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(36, 52, 51, '2025-04-24', '13:18:49', 5, 'completed', NULL, 'dsgfb', NULL, '2025-04-24 11:18:49', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(38, 52, 51, '2025-04-24', '13:29:55', 5, 'completed', NULL, 'rfvfb ', NULL, '2025-04-24 11:29:55', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(40, 52, 51, '2025-04-24', '17:04:30', 5, 'completed', NULL, 'feqpogjr,epozepozuyskq,mfocjqsdoiufvnicdbuilqshncmosdihv', NULL, '2025-04-24 15:04:30', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(41, 52, 51, '2025-04-24', '22:13:18', 5, 'completed', NULL, 'qvqdv ', NULL, '2025-04-24 20:13:18', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(42, 52, 51, '2025-04-24', '23:14:33', 5, 'completed', NULL, 'zefecfze', NULL, '2025-04-24 21:14:33', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(43, 52, 51, '2025-04-24', '23:47:36', 5, 'completed', NULL, 'dfghkjlm', NULL, '2025-04-24 21:47:36', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(44, 52, 51, '2025-04-25', '00:26:40', 5, 'completed', NULL, 'vsdv f', NULL, '2025-04-24 22:26:40', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(45, 52, 51, '2025-04-25', '00:40:45', 5, 'completed', NULL, 'dfghvjbknk', NULL, '2025-04-24 22:40:45', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(47, 52, 51, '2025-04-25', '00:52:53', 5, 'completed', NULL, 'DFVDF', NULL, '2025-04-24 22:52:53', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(48, 52, 51, '2025-04-25', '20:43:56', 5, 'completed', NULL, 'ertfgd', NULL, '2025-04-25 18:43:56', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(49, 52, 51, '2025-04-25', '21:01:43', 20, 'completed', NULL, 'sc', NULL, '2025-04-25 19:01:43', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(50, 52, 51, '2025-04-25', '21:20:36', 5, 'completed', NULL, 'tyjy', NULL, '2025-04-25 19:20:36', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(51, 52, 51, '2025-04-25', '21:40:59', 5, 'completed', NULL, 'trhbrt', NULL, '2025-04-25 19:40:59', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(52, 52, 51, '2025-04-25', '21:42:03', 5, 'completed', NULL, 'svsdfv', NULL, '2025-04-25 19:42:03', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(53, 52, 54, '2025-04-25', '22:22:01', 5, 'completed', NULL, 'efczesd', NULL, '2025-04-25 20:22:01', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(54, 52, 54, '2025-04-26', '13:21:59', 5, 'rejected', 'manich ga3d', 'qzcsdef', NULL, '2025-04-26 11:21:59', '2025-04-27 17:02:34', '2025-05-04 20:32:58'),
(56, 52, 51, '2025-04-27', '19:00:02', 5, 'completed', NULL, 'wxc xwc', NULL, '2025-04-27 17:00:02', '2025-04-27 17:02:42', '2025-05-04 20:32:58'),
(57, 52, 51, '2025-04-27', '19:17:17', 5, 'completed', NULL, 'efzerf', NULL, '2025-04-27 17:17:17', '2025-04-27 17:18:37', '2025-05-04 20:32:58'),
(58, 52, 51, '2025-04-27', '19:24:55', 5, 'completed', NULL, 'egqvergv', NULL, '2025-04-27 17:24:55', '2025-04-27 17:25:01', '2025-05-04 20:32:58'),
(59, 52, 51, '2025-04-27', '19:51:12', 5, 'completed', NULL, 'erfvd', NULL, '2025-04-27 17:51:12', '2025-04-27 17:51:21', '2025-05-04 20:32:58'),
(60, 52, 51, '2025-04-27', '20:06:44', 5, 'completed', NULL, 'efzed', NULL, '2025-04-27 18:06:44', '2025-04-27 18:07:06', '2025-05-04 20:32:58'),
(61, 52, 51, '2025-04-27', '20:07:48', 5, 'completed', NULL, 'qcdxs', NULL, '2025-04-27 18:07:48', '2025-04-27 18:16:02', '2025-05-04 20:32:58'),
(62, 52, 51, '2025-04-27', '20:16:28', 60, 'completed', NULL, 'ثسبيررؤ', NULL, '2025-04-27 18:16:28', '2025-04-27 18:17:41', '2025-05-04 20:32:58'),
(63, 52, 51, '2025-04-27', '20:18:23', 5, 'completed', NULL, 'fvsdrv', NULL, '2025-04-27 18:18:23', '2025-04-27 18:29:57', '2025-05-04 20:32:58'),
(64, 52, 51, '2025-04-27', '20:49:39', 5, 'completed', NULL, 'fvsdrv', NULL, '2025-04-27 18:49:39', '2025-04-27 18:50:13', '2025-05-04 20:32:58'),
(65, 52, 51, '2025-04-27', '20:50:29', 5, 'completed', NULL, 'efds', NULL, '2025-04-27 18:50:29', '2025-04-27 19:03:26', '2025-05-04 20:32:58'),
(66, 52, 51, '2025-04-27', '21:03:41', 5, 'completed', NULL, 'sdvfx', NULL, '2025-04-27 19:03:41', '2025-04-27 19:04:01', '2025-05-04 20:32:58'),
(67, 52, 51, '2025-04-27', '21:12:34', 5, 'completed', NULL, 'zefefv', NULL, '2025-04-27 19:12:34', '2025-04-27 19:13:06', '2025-05-04 20:32:58'),
(68, 52, 51, '2025-04-27', '21:19:11', 5, 'completed', NULL, 'zefefv', NULL, '2025-04-27 19:19:11', '2025-04-27 19:19:25', '2025-05-04 20:32:58'),
(69, 52, 51, '2025-04-27', '21:47:35', 5, 'completed', NULL, 'ergverdgfv', NULL, '2025-04-27 19:47:35', '2025-04-27 19:48:09', '2025-05-04 20:32:58'),
(70, 52, 51, '2025-04-27', '22:11:19', 5, 'completed', NULL, 'ergvrd', NULL, '2025-04-27 20:11:19', '2025-04-27 20:16:27', '2025-05-04 20:32:58'),
(71, 52, 51, '2025-04-27', '22:29:30', 5, 'completed', NULL, 'ergvrdrevef', NULL, '2025-04-27 20:29:30', '2025-04-27 20:34:47', '2025-05-04 20:32:58'),
(72, 52, 51, '2025-04-27', '22:40:54', 5, 'completed', NULL, 'ثقلثقفلب', NULL, '2025-04-27 20:40:54', '2025-04-27 20:46:06', '2025-05-04 20:32:58'),
(73, 52, 54, '2025-04-27', '23:59:17', 5, 'completed', NULL, 'qefrvf', NULL, '2025-04-27 21:59:17', '2025-04-27 21:59:29', '2025-05-04 20:32:58'),
(74, 52, 54, '2025-04-28', '00:40:49', 20, 'completed', NULL, 'YKUK.', NULL, '2025-04-27 22:40:49', '2025-04-27 22:40:56', '2025-05-04 20:32:58'),
(75, 52, 54, '2025-04-28', '00:58:10', 15, 'completed', NULL, 'fbdfb dcv', NULL, '2025-04-27 22:58:10', '2025-04-27 23:13:24', '2025-05-04 20:32:58'),
(76, 52, 54, '2025-04-28', '01:37:39', 20, 'completed', NULL, '', NULL, '2025-04-27 23:37:39', '2025-04-27 23:52:29', '2025-05-04 20:32:58'),
(77, 52, 54, '2025-04-28', '11:30:46', 5, 'completed', NULL, 'iujh', NULL, '2025-04-28 09:30:46', '2025-04-28 09:36:33', '2025-05-04 20:32:58'),
(79, 52, 54, '2025-04-28', '14:03:29', 5, 'completed', NULL, 'hnhn', NULL, '2025-04-28 12:03:29', '2025-04-28 12:04:56', '2025-05-04 20:32:58'),
(80, 68, 54, '2025-04-28', '16:31:05', 5, 'rejected', 'i&#039;m very bussy', 'fvf', NULL, '2025-04-28 14:31:05', '2025-04-28 14:38:53', '2025-05-04 20:32:58'),
(82, 68, 54, '2025-04-28', '16:40:12', 5, 'completed', NULL, 'slm', NULL, '2025-04-28 14:40:12', '2025-04-28 14:41:32', '2025-05-04 20:32:58'),
(83, 68, 54, '2025-04-28', '16:42:55', 5, 'completed', NULL, 'qlm', NULL, '2025-04-28 14:42:55', '2025-04-28 14:48:44', '2025-05-04 20:32:58'),
(84, 68, 54, '2025-04-28', '16:48:53', 15, 'completed', NULL, '', NULL, '2025-04-28 14:48:53', '2025-04-29 18:11:55', '2025-05-04 20:32:58'),
(93, 52, 51, '2025-04-29', '20:53:24', 5, 'completed', NULL, 'oouussama', NULL, '2025-04-29 18:53:24', '2025-04-29 18:56:30', '2025-05-04 20:32:58'),
(94, 52, 51, '2025-04-29', '20:58:59', 5, 'completed', NULL, 'qsqd', NULL, '2025-04-29 18:58:59', '2025-04-29 19:31:05', '2025-05-04 20:32:58'),
(95, 52, 51, '2025-04-29', '21:39:09', 5, 'completed', NULL, 'zecr', NULL, '2025-04-29 19:39:09', '2025-04-29 19:39:45', '2025-05-04 20:32:58'),
(96, 52, 51, '2025-04-29', '21:39:09', 5, 'completed', NULL, 'zecr', NULL, '2025-04-29 19:39:09', '2025-04-29 19:39:50', '2025-05-04 20:32:58'),
(97, 52, 51, '2025-04-29', '21:57:22', 5, 'completed', 'Expert went offline', 'unkl,;', NULL, '2025-04-29 19:57:22', '2025-04-29 19:57:48', '2025-05-04 20:32:58'),
(98, 52, 51, '2025-04-29', '22:07:09', 5, 'completed', 'Expert went offline', 'erdfgbx', NULL, '2025-04-29 20:07:09', '2025-04-29 20:07:34', '2025-05-04 20:32:58'),
(99, 52, 51, '2025-04-29', '22:16:15', 5, 'rejected', '', 'سيرسير', NULL, '2025-04-29 20:16:15', '2025-04-29 21:03:26', '2025-05-04 20:32:58'),
(100, 52, 51, '2025-04-29', '22:19:53', 5, 'rejected', '', 'wdbfb', NULL, '2025-04-29 20:19:53', '2025-04-29 21:03:31', '2025-05-04 20:32:58'),
(101, 52, 51, '2025-04-29', '23:06:09', 5, 'completed', 'Expert went offline', 'g,fg,b', NULL, '2025-04-29 21:06:09', '2025-04-29 21:23:07', '2025-05-04 20:32:58'),
(102, 52, 51, '2025-04-30', '00:31:21', 5, 'rejected', 'wdfbftg,n', 'pioj,;ù', NULL, '2025-04-29 22:31:21', '2025-04-29 22:33:46', '2025-05-04 20:32:58'),
(103, 52, 54, '2025-04-30', '19:44:47', 5, 'completed', NULL, 'yufgjhnml', NULL, '2025-04-30 17:44:47', '2025-04-30 17:44:47', '2025-05-04 20:32:58'),
(104, 52, 54, '2025-04-30', '19:54:23', 5, 'completed', NULL, 'dscdv', NULL, '2025-04-30 17:54:23', '2025-04-30 17:54:23', '2025-05-04 20:32:58'),
(105, 52, 54, '2025-04-30', '20:02:28', 7, 'completed', 'Expert went offline', 'dsvf', NULL, '2025-04-30 18:02:28', '2025-04-30 20:56:25', '2025-05-04 20:32:58'),
(106, 52, 54, '2025-04-30', '20:02:38', 8, 'completed', NULL, 'dsvfsdcvd', NULL, '2025-04-30 18:02:38', '2025-04-30 20:56:11', '2025-05-04 20:32:58'),
(107, 52, 54, '2025-04-30', '21:21:45', 5, 'completed', NULL, 'TRHTG', NULL, '2025-04-30 19:21:45', '2025-04-30 20:40:12', '2025-05-04 20:32:58'),
(108, 52, 54, '2025-04-30', '21:21:45', 5, 'completed', 'Expert went offline', 'TRHTG', NULL, '2025-04-30 19:21:45', '2025-04-30 20:56:25', '2025-05-04 20:32:58'),
(109, 52, 54, '2025-04-30', '23:03:34', 5, 'completed', NULL, 'dfvsrdfgdvc', NULL, '2025-04-30 21:03:34', '2025-04-30 21:03:34', '2025-05-04 20:32:58'),
(110, 52, 54, '2025-04-30', '23:03:34', 5, 'completed', NULL, 'dfvsrdfgdvc', NULL, '2025-04-30 21:03:34', '2025-04-30 21:03:34', '2025-05-04 20:32:58'),
(111, 52, 54, '2025-04-30', '23:05:20', 5, 'completed', NULL, 'erhtbg', 'sdvfws&lt;efyuazghcpfnçuqzehv_oywsdiunvkjxchn_voqzierhjn^çsd_hfpv_zuqemhojgvidfhv)_erhjfgodjibvputrhgçe^rsjo,fibohb)gçzepq^rj)$fa*3JKFO¨ZEIFNH¨ZEUI9OIGNVFJ9', '2025-04-30 21:05:20', '2025-04-30 21:05:20', '2025-05-04 20:32:58'),
(112, 52, 54, '2025-05-01', '01:16:17', 5, 'completed', NULL, '¨RGVDSRFVC Automatically cancelled because expert went offline.', NULL, '2025-04-30 23:16:17', '2025-04-30 23:16:17', '2025-05-04 20:32:58'),
(113, 52, 54, '2025-05-01', '01:28:16', 5, 'completed', NULL, 'trhbtgbf Automatically cancelled because expert went offline.', 'yess very good', '2025-04-30 23:28:16', '2025-04-30 23:28:16', '2025-05-04 20:32:58'),
(114, 52, 54, '2025-05-01', '01:53:03', 7, 'completed', NULL, 'dcsd', NULL, '2025-04-30 23:53:03', '2025-04-30 23:55:02', '2025-05-04 20:32:58'),
(115, 52, 54, '2025-05-01', '01:53:11', 7, 'completed', NULL, 'dcsd', NULL, '2025-04-30 23:53:11', '2025-04-30 23:54:39', '2025-05-04 20:32:58'),
(117, 68, 54, '2025-05-01', '19:41:39', 5, 'rejected', 'mojpojopm', 'iljil', NULL, '2025-05-01 17:41:39', '2025-05-01 17:41:39', '2025-05-04 20:32:58'),
(118, 68, 51, '2025-05-01', '22:12:57', 5, 'completed', NULL, 'dvsfv c', NULL, '2025-05-01 20:12:57', '2025-05-01 20:12:57', '2025-05-04 20:32:58'),
(119, 68, 51, '2025-05-01', '22:14:29', 5, 'completed', NULL, 'dfbfgb', NULL, '2025-05-01 20:14:29', '2025-05-01 20:14:29', '2025-05-04 20:32:58'),
(120, 68, 51, '2025-05-01', '22:16:40', 6, 'completed', NULL, 'v f', NULL, '2025-05-01 20:16:40', '2025-05-01 20:16:40', '2025-05-04 20:32:58'),
(121, 68, 51, '2025-05-01', '22:19:03', 5, 'completed', NULL, 'ervdfv', NULL, '2025-05-01 20:19:03', '2025-05-01 20:19:03', '2025-05-04 20:32:58'),
(122, 68, 51, '2025-05-01', '22:19:10', 5, 'completed', NULL, 'ervdfvbdfgbv', NULL, '2025-05-01 20:19:10', '2025-05-01 20:19:10', '2025-05-04 20:32:58'),
(123, 68, 51, '2025-05-01', '22:19:16', 5, 'completed', NULL, 'ervdfvbdfgbvdbfb g vc', NULL, '2025-05-01 20:19:16', '2025-05-01 20:19:16', '2025-05-04 20:32:58'),
(124, 68, 51, '2025-05-01', '22:19:24', 5, 'completed', NULL, 'ervdfvbdfgbvdbfbregvsfxc  g vc', NULL, '2025-05-01 20:19:24', '2025-05-01 20:19:24', '2025-05-04 20:32:58'),
(125, 68, 51, '2025-05-01', '22:19:30', 5, 'completed', NULL, 'ervdfvbdfgbvdbfbrezevsdxcgvsfxc  g vc', NULL, '2025-05-01 20:19:30', '2025-05-01 20:19:30', '2025-05-04 20:32:58'),
(126, 68, 51, '2025-05-01', '22:19:35', 5, 'completed', NULL, 'ervdfvbdfgbvdbfbrezevsdxcgvsfxc  g vc', NULL, '2025-05-01 20:19:35', '2025-05-01 20:19:35', '2025-05-04 20:32:58'),
(127, 68, 51, '2025-05-01', '22:19:39', 5, 'completed', NULL, 'ervdfvbdfgbvdbfbrezevsdxcgvsfxc  g vc', NULL, '2025-05-01 20:19:39', '2025-05-01 20:19:39', '2025-05-04 20:32:58'),
(128, 68, 51, '2025-05-01', '22:19:42', 5, 'completed', NULL, 'ervdfvbdfgbvdbfbrezevsdxcgvsfxc  g vc', NULL, '2025-05-01 20:19:42', '2025-05-01 20:19:42', '2025-05-04 20:32:58'),
(129, 68, 51, '2025-05-01', '22:19:46', 5, 'completed', NULL, 'ervdfvbdfgbvdbfbrezevsdxcgvsfxc  g vc', NULL, '2025-05-01 20:19:46', '2025-05-01 20:19:46', '2025-05-04 20:32:58'),
(130, 68, 51, '2025-05-01', '22:19:46', 5, 'completed', NULL, 'ervdfvbdfgbvdbfbrezevsdxcgvsfxc  g vc', NULL, '2025-05-01 20:19:46', '2025-05-01 20:19:46', '2025-05-04 20:32:58'),
(131, 68, 51, '2025-05-01', '22:20:13', 5, 'completed', NULL, 'v x', NULL, '2025-05-01 20:20:13', '2025-05-01 20:20:13', '2025-05-04 20:32:58'),
(132, 68, 51, '2025-05-01', '22:20:16', 5, 'completed', NULL, 'v x', NULL, '2025-05-01 20:20:16', '2025-05-01 20:20:16', '2025-05-04 20:32:58'),
(133, 68, 51, '2025-05-01', '22:20:19', 5, 'completed', NULL, 'v x', NULL, '2025-05-01 20:20:19', '2025-05-01 20:20:19', '2025-05-04 20:32:58'),
(134, 68, 51, '2025-05-01', '22:20:22', 5, 'completed', NULL, 'v x', NULL, '2025-05-01 20:20:22', '2025-05-01 20:20:22', '2025-05-04 20:32:58'),
(135, 52, 54, '2025-05-02', '19:38:19', 6, 'completed', NULL, 'ouss', NULL, '2025-05-02 17:38:19', '2025-05-02 17:38:19', '2025-05-04 20:32:58'),
(136, 52, 54, '2025-05-02', '19:43:07', 5, 'completed', NULL, 'cd', NULL, '2025-05-02 17:43:07', '2025-05-02 17:43:07', '2025-05-04 20:32:58'),
(137, 52, 54, '2025-05-02', '20:04:00', 5, 'completed', NULL, 'uhiokj', NULL, '2025-05-02 18:04:00', '2025-05-02 18:04:00', '2025-05-04 20:32:58'),
(138, 52, 54, '2025-05-02', '20:08:16', 5, 'completed', NULL, 'zfzef', NULL, '2025-05-02 18:08:16', '2025-05-02 18:08:16', '2025-05-04 20:32:58'),
(139, 52, 54, '2025-05-02', '20:08:24', 5, 'completed', NULL, 'zfzef', NULL, '2025-05-02 18:08:24', '2025-05-02 18:08:24', '2025-05-04 20:32:58'),
(140, 52, 54, '2025-05-02', '20:17:39', 5, 'completed', NULL, 'sgvgdfb', NULL, '2025-05-02 18:17:39', '2025-05-02 18:17:39', '2025-05-04 20:32:58'),
(142, 52, 54, '2025-05-03', '13:13:08', 0, 'rejected', 'mklj,ml', 'UIHIOJ?', NULL, '2025-05-03 11:13:08', '2025-05-03 11:13:08', '2025-05-04 20:32:58'),
(143, 52, 54, '2025-05-03', '13:26:48', 0, 'rejected', 'sdfdg', 'zee', NULL, '2025-05-03 11:26:48', '2025-05-03 11:26:48', '2025-05-04 20:32:58'),
(144, 52, 54, '2025-05-03', '13:40:45', 0, 'rejected', 'kjoi,lk', 'mk;po', NULL, '2025-05-03 11:40:45', '2025-05-03 11:40:45', '2025-05-04 20:32:58'),
(145, 52, 54, '2025-05-03', '14:04:04', 0, 'completed', NULL, 'dc', NULL, '2025-05-03 12:04:04', '2025-05-03 12:04:17', '2025-05-04 20:32:58'),
(146, 52, 54, '2025-05-03', '14:05:00', 0, 'rejected', 'jkno', 'lkpo', NULL, '2025-05-03 12:05:00', '2025-05-03 12:05:00', '2025-05-04 20:32:58'),
(147, 52, 54, '2025-05-03', '14:12:09', 0, 'rejected', 'iosjkoc', 'dckdpo', NULL, '2025-05-03 12:12:09', '2025-05-03 12:12:09', '2025-05-04 20:32:58'),
(148, 52, 54, '2025-05-03', '14:16:21', 0, 'rejected', 'kljpioj,', 'dckdpo', NULL, '2025-05-03 12:16:21', '2025-05-03 12:16:21', '2025-05-04 20:32:58'),
(149, 52, 54, '2025-05-03', '14:19:57', 0, 'rejected', 'jkn', 'pijohi', NULL, '2025-05-03 12:19:57', '2025-05-03 12:19:57', '2025-05-04 20:32:58'),
(150, 52, 54, '2025-05-03', '14:22:01', 0, 'rejected', 'kjioj', 'pijohi', NULL, '2025-05-03 12:22:01', '2025-05-03 12:22:01', '2025-05-04 20:32:58'),
(151, 52, 54, '2025-05-03', '14:29:59', 0, 'rejected', 'oijoi', 'pkpok', NULL, '2025-05-03 12:29:59', '2025-05-03 12:29:59', '2025-05-04 20:32:58'),
(152, 52, 54, '2025-05-03', '15:00:39', 0, 'rejected', 'jknk', 'ml,cmldqs', NULL, '2025-05-03 13:00:39', '2025-05-03 13:00:39', '2025-05-04 20:32:58'),
(153, 52, 54, '2025-05-03', '15:13:46', 0, 'rejected', 'jkhuiojhio', 'kuhioj', NULL, '2025-05-03 13:13:46', '2025-05-03 13:13:46', '2025-05-04 20:32:58'),
(154, 52, 54, '2025-05-03', '15:15:53', 0, 'rejected', 'dmld^v', 'kuhioj', NULL, '2025-05-03 13:15:53', '2025-05-03 13:15:53', '2025-05-04 20:32:58'),
(155, 52, 54, '2025-05-03', '15:19:13', 0, 'completed', NULL, 'okpo', NULL, '2025-05-03 13:19:13', '2025-05-03 13:19:19', '2025-05-04 20:32:58'),
(156, 52, 54, '2025-05-03', '15:19:34', 0, 'rejected', 'SDGVFDRHBFG', 'FCDEF', NULL, '2025-05-03 13:19:34', '2025-05-03 13:19:34', '2025-05-04 20:32:58'),
(157, 52, 54, '2025-05-03', '15:29:49', 5, 'rejected', 'iljoi', 'mokpo', NULL, '2025-05-03 13:29:49', '2025-05-03 13:29:49', '2025-05-04 20:32:58'),
(158, 52, 54, '2025-05-03', '15:46:04', 5, 'rejected', 'dvdcv', 'ddc', NULL, '2025-05-03 13:46:04', '2025-05-03 13:46:04', '2025-05-04 20:32:58'),
(159, 52, 54, '2025-05-03', '15:50:16', 5, 'rejected', 'poe)gàr', 'sdvd', NULL, '2025-05-03 13:50:16', '2025-05-03 13:50:16', '2025-05-04 20:32:58'),
(160, 52, 54, '2025-05-03', '16:05:07', 5, 'rejected', 'zzvsd', 'قثبري', 'yes', '2025-05-03 14:05:07', '2025-05-03 14:05:07', '2025-05-04 20:32:58'),
(161, 52, 54, '2025-05-04', '10:44:11', 5, 'rejected', 'uiyhoijkl', 'guihiu', NULL, '2025-05-04 08:44:11', '2025-05-04 08:44:11', '2025-05-04 20:32:58'),
(162, 52, 54, '2025-05-04', '10:46:01', 5, 'rejected', 'iojpjkpojk', 'kljio', NULL, '2025-05-04 08:46:01', '2025-05-04 08:46:01', '2025-05-04 20:32:58'),
(163, 52, 54, '2025-05-04', '10:50:34', 5, 'rejected', 'segfvd', 'rgbvdfv', NULL, '2025-05-04 08:50:34', '2025-05-04 08:50:34', '2025-05-04 20:32:58'),
(164, 52, 54, '2025-05-04', '10:55:29', 5, 'rejected', 'dvsd', 'dpslv^pdv', NULL, '2025-05-04 08:55:29', '2025-05-04 08:55:29', '2025-05-04 20:32:58'),
(165, 52, 54, '2025-05-04', '10:56:12', 5, 'rejected', 'sdcwd', 'dbvfb', NULL, '2025-05-04 08:56:12', '2025-05-04 08:56:12', '2025-05-04 20:32:58'),
(166, 52, 54, '2025-05-04', '11:05:22', 5, 'rejected', 'ijpokp', 'sefpôlse^dc', NULL, '2025-05-04 09:05:22', '2025-05-04 09:05:22', '2025-05-04 20:32:58'),
(167, 52, 54, '2025-05-04', '11:22:13', 5, 'rejected', 'pok^l^p', 'iojpok', NULL, '2025-05-04 09:22:13', '2025-05-04 09:22:13', '2025-05-04 20:32:58'),
(168, 52, 54, '2025-05-04', '11:30:19', 6, 'rejected', 'wcwx', 'wxc wx', NULL, '2025-05-04 09:30:19', '2025-05-04 09:30:19', '2025-05-04 20:32:58'),
(169, 52, 54, '2025-05-04', '11:51:17', 5, 'rejected', 'zefmùlsef^d', 'mlk^pol', NULL, '2025-05-04 09:51:17', '2025-05-04 09:51:17', '2025-05-04 20:32:58'),
(170, 52, 54, '2025-05-04', '11:51:50', 5, 'completed', NULL, 'p^l^pùl', NULL, '2025-05-04 09:51:50', '2025-05-04 09:52:28', '2025-05-04 20:32:58'),
(171, 52, 54, '2025-05-04', '11:57:12', 5, 'rejected', 'yàçupo', 'kl,po,', NULL, '2025-05-04 09:57:12', '2025-05-04 09:57:12', '2025-05-04 20:32:58'),
(172, 52, 54, '2025-05-04', '19:58:50', 6, 'rejected', 'kljpokml', 'adza', NULL, '2025-05-04 17:58:50', '2025-05-04 17:58:50', '2025-05-04 20:32:58'),
(173, 52, 51, '2025-05-04', '20:13:56', 5, 'rejected', 'mùqclq', 'ouus', NULL, '2025-05-04 18:13:56', '2025-05-04 18:13:56', '2025-05-04 20:32:58'),
(174, 52, 51, '2025-05-04', '20:14:22', 5, 'rejected', 'mlqksdoc', 'ejfioze', 'hello', '2025-05-04 18:14:22', '2025-05-04 18:14:22', '2025-05-04 20:32:58'),
(175, 52, 51, '2025-05-04', '20:38:23', 5, 'rejected', 'mlkp', 'qrfprfo', '^jpkp', '2025-05-04 18:38:23', '2025-05-04 18:38:23', '2025-05-04 20:32:58'),
(176, 52, 51, '2025-05-04', '20:39:40', 5, 'rejected', 'jedpoze', 'sevdf', NULL, '2025-05-04 18:39:40', '2025-05-04 18:39:40', '2025-05-04 20:32:58'),
(177, 52, 51, '2025-05-04', '21:45:51', 5, 'rejected', '^poikp^mù', 'mkpô$p', NULL, '2025-05-04 19:45:51', '2025-05-04 19:45:51', '2025-05-04 20:32:58'),
(178, 52, 51, '2025-05-04', '21:59:25', 6, 'canceled', NULL, 'oUSS', NULL, '2025-05-04 19:59:25', '2025-05-04 19:59:25', '2025-05-04 20:43:34'),
(179, 52, 51, '2025-05-04', '22:21:29', 5, 'canceled', NULL, 'jhiojo', NULL, '2025-05-04 20:21:29', '2025-05-04 20:21:29', '2025-05-04 20:44:19'),
(180, 52, 51, '2025-05-04', '22:45:07', 5, 'rejected', 'zdcqs', 'iou_çuoi', NULL, '2025-05-04 20:45:07', '2025-05-04 20:45:07', '2025-05-04 20:45:07'),
(181, 52, 51, '2025-05-04', '22:49:02', 5, 'rejected', 'mlkpom', 'dsvdw', NULL, '2025-05-04 20:49:02', '2025-05-04 20:49:02', '2025-05-04 20:49:02'),
(182, 52, 51, '2025-05-04', '22:49:29', 5, 'canceled', NULL, 'kljol', NULL, '2025-05-04 20:49:29', '2025-05-04 20:49:29', '2025-05-04 20:51:12'),
(183, 52, 51, '2025-05-04', '22:51:35', 5, 'canceled', NULL, 'dcvds', NULL, '2025-05-04 20:51:35', '2025-05-04 20:51:35', '2025-05-04 20:51:57'),
(184, 52, 51, '2025-05-04', '23:00:26', 5, 'canceled', NULL, 'yjtuiyio', NULL, '2025-05-04 21:00:26', '2025-05-04 21:00:26', '2025-05-04 21:00:44'),
(185, 52, 51, '2025-05-04', '23:07:00', 5, 'canceled', NULL, 'pp)^m', NULL, '2025-05-04 21:07:00', '2025-05-04 21:07:00', '2025-05-04 21:07:10'),
(186, 52, 51, '2025-05-04', '23:19:07', 5, 'canceled', 'Expert went offline', 'iezfpok', NULL, '2025-05-04 21:19:07', '2025-05-04 21:19:07', '2025-05-04 21:19:07'),
(187, 52, 51, '2025-05-04', '23:25:43', 5, 'rejected', 'ml;m;', 'okpo', NULL, '2025-05-04 21:25:43', '2025-05-04 21:25:43', '2025-05-04 21:25:43'),
(188, 52, 51, '2025-05-04', '23:26:45', 5, 'rejected', 'KLJKLK', 'lp^ùl:', NULL, '2025-05-04 21:26:45', '2025-05-04 21:26:45', '2025-05-04 21:26:45'),
(189, 52, 51, '2025-05-04', '23:30:17', 5, 'canceled', 'Expert went offline', 'jiiokl', NULL, '2025-05-04 21:30:17', '2025-05-04 21:30:17', '2025-05-04 21:30:17'),
(190, 52, 51, '2025-05-04', '23:30:26', 5, 'rejected', 'iojpomkl', 'kljiol', NULL, '2025-05-04 21:30:26', '2025-05-04 21:30:26', '2025-05-04 21:30:26'),
(191, 52, 51, '2025-05-04', '23:51:44', 5, 'rejected', 'mlkùm', 'hjiokl;', NULL, '2025-05-04 21:51:44', '2025-05-04 21:51:44', '2025-05-04 21:51:44'),
(192, 52, 51, '2025-05-04', '23:53:59', 5, 'canceled', NULL, 'mlkpl^ùl', NULL, '2025-05-04 21:53:59', '2025-05-04 21:53:59', '2025-05-04 22:12:19'),
(193, 52, 51, '2025-05-05', '00:16:25', 5, 'canceled', 'Expert went offline', 'zefvsdc', NULL, '2025-05-04 22:16:25', '2025-05-04 22:16:25', '2025-05-04 22:16:25'),
(194, 52, 51, '2025-05-05', '00:17:48', 5, 'rejected', 'erfsdcx', 'azfsd', NULL, '2025-05-04 22:17:48', '2025-05-04 22:17:48', '2025-05-04 22:17:48'),
(195, 68, 51, '2025-05-05', '00:19:53', 5, 'rejected', 'kjpkom', 'sdfvdxc', NULL, '2025-05-04 22:19:53', '2025-05-04 22:19:53', '2025-05-04 22:19:53'),
(196, 68, 51, '2025-05-05', '00:22:08', 5, 'rejected', 'ioupl', 'qzrgdf', NULL, '2025-05-04 22:22:08', '2025-05-04 22:22:08', '2025-05-04 22:22:08'),
(197, 68, 51, '2025-05-05', '00:28:38', 5, 'rejected', 'kljmol', 'lkm!;:', NULL, '2025-05-04 22:28:38', '2025-05-04 22:28:38', '2025-05-04 22:28:38'),
(198, 68, 51, '2025-05-05', '00:33:22', 5, 'canceled', 'Expert went offline', 'kl', NULL, '2025-05-04 22:33:22', '2025-05-04 22:33:22', '2025-05-04 22:33:22'),
(199, 68, 51, '2025-05-05', '00:35:33', 5, 'completed', NULL, 'sdxc', NULL, '2025-05-04 22:35:33', '2025-05-04 22:38:19', '2025-05-04 22:35:33'),
(200, 68, 51, '2025-05-05', '00:39:12', 5, 'rejected', 'sdvcx', 'srvfdc', NULL, '2025-05-04 22:39:12', '2025-05-04 22:39:12', '2025-05-04 22:39:12'),
(201, 68, 51, '2025-05-05', '00:39:49', 5, 'rejected', 'ikm', 'iljl', NULL, '2025-05-04 22:39:49', '2025-05-04 22:39:49', '2025-05-04 22:39:49'),
(202, 68, 51, '2025-05-05', '00:43:07', 5, 'rejected', 'ouss', 'sdvxc', NULL, '2025-05-04 22:43:07', '2025-05-04 22:43:07', '2025-05-04 22:43:07'),
(203, 52, 54, '2025-05-05', '11:42:40', 5, 'rejected', 'ioujol', 'zefcvsdx', NULL, '2025-05-05 09:42:40', '2025-05-05 09:42:40', '2025-05-05 09:42:40'),
(204, 52, 54, '2025-05-05', '11:45:14', 5, 'rejected', 'poià)o', 'zefscf', NULL, '2025-05-05 09:45:14', '2025-05-05 09:45:14', '2025-05-05 09:45:14'),
(205, 52, 54, '2025-05-05', '11:50:19', 5, 'rejected', 'ouss', 'dsfv', 'iojoik', '2025-05-05 09:50:19', '2025-05-05 09:50:19', '2025-05-05 09:50:19'),
(206, 52, 54, '2025-05-05', '11:54:21', 5, 'rejected', '^pokpôp', 'kpom', NULL, '2025-05-05 09:54:21', '2025-05-05 09:54:21', '2025-05-05 09:54:21'),
(207, 52, 54, '2025-05-05', '11:54:34', 5, 'rejected', 'poà)', 'klpol', NULL, '2025-05-05 09:54:34', '2025-05-05 09:54:34', '2025-05-05 09:54:34'),
(208, 52, 54, '2025-05-05', '11:57:15', 5, 'rejected', 'po)=p', 'pioikpl^ù', NULL, '2025-05-05 09:57:15', '2025-05-05 09:57:15', '2025-05-05 09:57:15'),
(209, 52, 54, '2025-05-05', '11:59:10', 5, 'rejected', 'Ouussama', 'uiohi', NULL, '2025-05-05 09:59:10', '2025-05-05 09:59:10', '2025-05-05 09:59:10'),
(210, 52, 54, '2025-05-05', '12:00:32', 5, 'rejected', 'klmkà^pl', 'oij', NULL, '2025-05-05 10:00:32', '2025-05-05 10:00:32', '2025-05-05 10:00:32'),
(211, 52, 54, '2025-05-05', '12:03:41', 5, 'rejected', 'djkpkjouigyftr', 'خهحكم', NULL, '2025-05-05 10:03:41', '2025-05-05 10:03:41', '2025-05-05 10:03:41'),
(212, 52, 54, '2025-05-05', '12:16:40', 5, 'rejected', 'mokp^ùlm', 'iljpko', NULL, '2025-05-05 10:16:40', '2025-05-05 10:16:40', '2025-05-05 10:16:40'),
(213, 52, 54, '2025-05-05', '12:17:14', 5, 'rejected', 'okp^ùo', 'ijpkl:', NULL, '2025-05-05 10:17:14', '2025-05-05 10:17:14', '2025-05-05 10:17:14'),
(214, 52, 54, '2025-05-05', '12:17:38', 5, 'rejected', 'opi^pl', 'ljkmol;', NULL, '2025-05-05 10:17:38', '2025-05-05 10:17:38', '2025-05-05 10:17:38'),
(215, 52, 54, '2025-05-05', '12:19:02', 5, 'rejected', 'dfvfc', 'zefcsx', NULL, '2025-05-05 10:19:02', '2025-05-05 10:19:02', '2025-05-05 10:19:02'),
(216, 52, 54, '2025-05-05', '12:21:01', 5, 'canceled', 'Expert went offline', 'zegfvsdcx', NULL, '2025-05-05 10:21:01', '2025-05-05 10:21:01', '2025-05-05 10:21:01'),
(217, 52, 54, '2025-05-05', '12:23:09', 5, 'canceled', 'Expert went offline', 'zefcdxs', NULL, '2025-05-05 10:23:09', '2025-05-05 10:23:09', '2025-05-05 10:23:09'),
(218, 52, 54, '2025-05-05', '18:50:17', 5, 'rejected', 'dvdsf', 'dfg', NULL, '2025-05-05 16:50:17', '2025-05-05 16:50:17', '2025-05-05 16:50:17'),
(219, 52, 54, '2025-05-05', '18:50:17', 5, 'rejected', 'dsfd', 'dfg', 'dsqc', '2025-05-05 16:50:17', '2025-05-05 16:50:17', '2025-05-05 16:50:17'),
(220, 52, 54, '2025-05-05', '18:50:18', 5, 'rejected', 'gresdf', 'dfg', NULL, '2025-05-05 16:50:18', '2025-05-05 16:50:18', '2025-05-05 16:50:18'),
(221, 52, 54, '2025-05-05', '18:55:39', 5, 'rejected', 'regvfds', 'egbr', NULL, '2025-05-05 16:55:39', '2025-05-05 16:55:39', '2025-05-05 16:55:39'),
(222, 52, 54, '2025-05-05', '19:04:23', 5, 'rejected', 'dvfdev', 'trg f', NULL, '2025-05-05 17:04:23', '2025-05-05 17:04:23', '2025-05-05 17:04:23'),
(223, 52, 54, '2025-05-05', '19:56:59', 5, 'rejected', 'ghjk;', 'ghbjnk,l;', NULL, '2025-05-05 17:56:59', '2025-05-05 17:56:59', '2025-05-05 17:56:59'),
(224, 52, 54, '2025-05-05', '19:59:55', 5, 'canceled', 'Expert went offline', 'jkl', NULL, '2025-05-05 17:59:55', '2025-05-05 17:59:55', '2025-05-05 17:59:55'),
(225, 52, 54, '2025-05-05', '20:28:25', 5, 'rejected', 'edfvc', 'drgvsfc', NULL, '2025-05-05 18:28:25', '2025-05-05 18:28:25', '2025-05-05 18:28:25'),
(226, 52, 51, '2025-05-07', '12:50:24', 9, 'rejected', 'ocu', 'slm', NULL, '2025-05-07 10:50:24', '2025-05-07 10:50:24', '2025-05-07 10:50:24'),
(227, 52, 51, '2025-05-07', '12:52:15', 5, 'completed', NULL, 'slm', '5min', '2025-05-07 10:52:15', '2025-05-07 10:52:15', '2025-05-07 10:52:15'),
(228, 68, 51, '2025-05-07', '12:56:38', 5, 'completed', NULL, 'slm', NULL, '2025-05-07 10:56:38', '2025-05-07 15:11:52', '2025-05-07 10:56:38'),
(229, 52, 51, '2025-05-07', '13:14:40', 5, 'rejected', 'lkn,;mù:', 'afced', 'ùm;:*ù', '2025-05-07 11:14:40', '2025-05-07 11:14:40', '2025-05-07 11:14:40'),
(230, 52, 54, '2025-05-07', '21:29:33', 5, 'rejected', 'gverdf', 'oussama', NULL, '2025-05-07 19:29:33', '2025-05-07 19:29:33', '2025-05-07 19:29:33'),
(231, 52, 54, '2025-05-07', '21:31:04', 5, 'canceled', NULL, 'sdvdv', NULL, '2025-05-07 19:31:04', '2025-05-07 19:31:04', '2025-05-07 19:32:28'),
(232, 52, 54, '2025-05-07', '21:38:21', 5, 'canceled', 'Expert went offline', 'svdf', NULL, '2025-05-07 19:38:21', '2025-05-07 19:38:21', '2025-05-07 19:38:21'),
(233, 68, 54, '2025-05-09', '01:31:39', 6, 'pending', NULL, 'oussama', NULL, '2025-05-08 23:31:39', '2025-05-08 23:31:39', '2025-05-08 23:31:39'),
(234, 68, 54, '2025-05-09', '01:32:29', 7, 'canceled', NULL, 'boucetta', NULL, '2025-05-08 23:32:29', '2025-05-08 23:32:29', '2025-05-08 23:33:20');

-- --------------------------------------------------------

--
-- Structure de la table `consultation_confirmation_listeners`
--

CREATE TABLE `consultation_confirmation_listeners` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `consultation_confirmation_listeners`
--

INSERT INTO `consultation_confirmation_listeners` (`id`, `consultation_id`, `client_id`, `processed`, `created_at`, `processed_at`) VALUES
(1, 106, 52, 0, '2025-04-30 19:02:38', NULL),
(2, 107, 52, 0, '2025-04-30 20:21:45', NULL),
(3, 108, 52, 0, '2025-04-30 20:21:45', NULL),
(4, 110, 52, 0, '2025-04-30 22:03:34', NULL),
(5, 113, 52, 0, '2025-05-01 00:28:16', NULL),
(6, 115, 52, 0, '2025-05-01 00:53:11', NULL),
(7, 120, 68, 0, '2025-05-01 21:16:40', NULL),
(8, 122, 68, 0, '2025-05-01 21:19:10', NULL),
(9, 123, 68, 0, '2025-05-01 21:19:16', NULL),
(10, 124, 68, 0, '2025-05-01 21:19:24', NULL),
(11, 125, 68, 0, '2025-05-01 21:19:30', NULL),
(12, 126, 68, 0, '2025-05-01 21:19:35', NULL),
(13, 127, 68, 0, '2025-05-01 21:19:39', NULL),
(14, 128, 68, 0, '2025-05-01 21:19:42', NULL),
(15, 129, 68, 0, '2025-05-01 21:19:46', NULL),
(16, 130, 68, 0, '2025-05-01 21:19:46', NULL),
(17, 131, 68, 0, '2025-05-01 21:20:13', NULL),
(18, 132, 68, 0, '2025-05-01 21:20:16', NULL),
(19, 133, 68, 0, '2025-05-01 21:20:19', NULL),
(20, 134, 68, 0, '2025-05-01 21:20:22', NULL),
(21, 136, 52, 0, '2025-05-02 18:43:07', NULL),
(22, 137, 52, 0, '2025-05-02 19:04:00', NULL),
(23, 139, 52, 0, '2025-05-02 19:08:24', NULL),
(24, 170, 52, 0, '2025-05-04 10:51:50', NULL),
(25, 174, 52, 0, '2025-05-04 19:14:22', NULL),
(26, 176, 52, 0, '2025-05-04 19:39:40', NULL),
(27, 179, 52, 0, '2025-05-04 21:21:29', NULL),
(28, 190, 52, 0, '2025-05-04 22:30:26', NULL),
(29, 207, 52, 0, '2025-05-05 10:54:34', NULL),
(30, 219, 52, 0, '2025-05-05 17:50:18', NULL),
(31, 220, 52, 0, '2025-05-05 17:50:18', NULL),
(32, 234, 68, 0, '2025-05-09 00:32:29', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `experiences`
--

CREATE TABLE `experiences` (
  `id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `workplace` varchar(255) NOT NULL,
  `duration_years` int(11) NOT NULL,
  `duration_months` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `experiences`
--

INSERT INTO `experiences` (`id`, `profile_id`, `start_date`, `end_date`, `workplace`, `duration_years`, `duration_months`, `description`, `file_path`, `created_at`, `status`, `rejection_reason`) VALUES
(15, 27, '2025-04-01', '2025-04-09', 'pharmacie', 0, 0, 'en banyamina', '../uploads/experiences/exp_51_0_1744905119.png', '2025-04-17 15:51:59', 'approved', NULL),
(16, 28, '2025-04-03', '2025-04-13', 'pharmacie', 0, 0, 'sq', '../uploads/experiences/exp_54_0_1745610896.jpg', '2025-04-25 19:54:56', 'approved', NULL),
(17, 39, '2021-05-08', '2025-05-02', 'pharmacie', 4, 0, 'ZDAZD', '../uploads/experiences/exp_119_0_1746574370.jpg', '2025-05-06 23:32:50', 'pending', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `expert_approval_comments`
--

CREATE TABLE `expert_approval_comments` (
  `id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `expert_notifications`
--

CREATE TABLE `expert_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `expert_notifications`
--

INSERT INTO `expert_notifications` (`id`, `user_id`, `profile_id`, `notification_type`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(20, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 12:25:49'),
(21, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 12:26:15'),
(22, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 13:02:10'),
(23, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 13:11:53'),
(24, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 13:12:06'),
(25, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 14:15:28'),
(26, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 14:28:43'),
(27, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:29:19'),
(28, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:29:23'),
(29, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:29:29'),
(30, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:31:53'),
(31, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:31:57'),
(32, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:32:02'),
(33, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:32:11'),
(34, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:32:18'),
(35, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:32:36'),
(36, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:32:44'),
(37, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:32:51'),
(38, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:33:06'),
(39, 51, 27, 'new_message', 'You have a new message from a client.', 12, 1, '2025-04-22 14:33:11'),
(40, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-22 18:06:18'),
(41, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 18:08:30'),
(42, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:08:52'),
(43, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:09:01'),
(44, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:09:13'),
(45, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:09:16'),
(46, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:09:35'),
(47, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:09:55'),
(48, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:10:03'),
(49, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:10:48'),
(50, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:11:12'),
(51, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:11:16'),
(52, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:11:59'),
(53, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:12:03'),
(54, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:12:07'),
(55, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:13:31'),
(56, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:13:35'),
(57, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:13:41'),
(58, 51, 27, 'new_message', 'You have a new message from a client.', 13, 1, '2025-04-22 18:13:45'),
(59, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 18:27:47'),
(60, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:28:07'),
(61, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:28:19'),
(62, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:28:23'),
(63, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:28:27'),
(64, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:28:29'),
(65, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:30:14'),
(66, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:30:18'),
(67, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:30:23'),
(68, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:30:27'),
(69, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:31:49'),
(70, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-22 18:32:58'),
(71, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:33:25'),
(72, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:33:48'),
(73, 51, 27, 'new_message', 'You have a new message from a client.', 14, 1, '2025-04-22 18:44:48'),
(74, 51, 27, 'new_rating', 'You have received a new rating.', NULL, 1, '2025-04-22 18:45:09'),
(75, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 19:06:04'),
(76, 51, 27, 'new_message', 'You have a new message from a client.', 15, 1, '2025-04-22 19:06:54'),
(77, 51, 27, 'new_message', 'You have a new message from a client.', 15, 1, '2025-04-22 19:07:04'),
(78, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-22 19:07:16'),
(79, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 19:08:16'),
(80, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-22 19:15:58'),
(81, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-22 21:38:23'),
(82, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-23 11:34:06'),
(83, 51, 27, 'new_message', 'You have a new message from a client.', 18, 1, '2025-04-23 11:39:44'),
(84, 51, 27, 'new_message', 'You have a new message from a client.', 18, 1, '2025-04-23 11:40:50'),
(85, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-23 11:42:25'),
(86, 51, 27, 'new_message', 'You have a new message from a client.', 18, 1, '2025-04-23 11:42:34'),
(87, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 09:54:15'),
(88, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 09:59:35'),
(89, 51, 27, 'new_message', 'You have a new message from a client.', 19, 1, '2025-04-24 09:59:52'),
(90, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:02:05'),
(91, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:03:17'),
(92, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:03:38'),
(93, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:07:32'),
(94, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:22:07'),
(95, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:23:46'),
(96, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:25:33'),
(97, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:28:59'),
(98, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:29:15'),
(99, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:30:12'),
(100, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:32:40'),
(101, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:32:51'),
(102, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:33:53'),
(103, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:34:51'),
(104, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:35:50'),
(105, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:36:58'),
(106, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:38:48'),
(107, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:39:35'),
(108, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:40:34'),
(109, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:42:02'),
(110, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:43:04'),
(111, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:43:22'),
(112, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:50:29'),
(113, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 10:50:52'),
(114, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 10:53:21'),
(115, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 11:13:23'),
(116, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 11:14:07'),
(117, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 11:14:27'),
(118, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 11:15:48'),
(119, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 11:16:19'),
(120, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 11:18:30'),
(121, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 11:18:49'),
(122, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 11:19:46'),
(123, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 11:20:09'),
(124, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 11:29:55'),
(125, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 11:30:52'),
(126, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 11:31:11'),
(127, 51, NULL, NULL, 'You have received a new message.', NULL, 1, '2025-04-24 14:47:45'),
(128, 51, NULL, NULL, 'You have received a new message.', NULL, 1, '2025-04-24 14:48:04'),
(129, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 15:04:30'),
(130, 51, 27, 'new_message', 'You have a new message from a client.', 40, 1, '2025-04-24 15:06:10'),
(131, 51, 27, 'new_message', 'You have a new message from a client.', 40, 1, '2025-04-24 15:06:18'),
(132, 51, 27, 'new_message', 'You have a new message from a client.', 40, 1, '2025-04-24 15:06:22'),
(133, 51, 27, 'new_message', 'You have a new message from a client.', 40, 1, '2025-04-24 15:06:25'),
(134, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 15:06:42'),
(135, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 20:13:18'),
(136, 51, 27, 'new_message', 'You have a new message from a client.', 41, 1, '2025-04-24 20:13:51'),
(137, 51, 27, 'new_message', 'You have a new message from a client.', 41, 1, '2025-04-24 20:14:04'),
(138, 51, 27, 'new_message', 'You have a new message from a client.', 41, 1, '2025-04-24 20:14:13'),
(139, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 20:14:29'),
(140, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 21:14:33'),
(141, 51, 27, 'new_message', 'You have a new message from a client.', 42, 1, '2025-04-24 21:15:20'),
(142, 51, 27, 'new_message', 'You have a new message from a client.', 42, 1, '2025-04-24 21:15:30'),
(143, 51, 27, 'new_message', 'You have a new message from a client.', 42, 1, '2025-04-24 21:15:34'),
(144, 51, 27, 'new_message', 'You have a new message from a client.', 42, 1, '2025-04-24 21:19:37'),
(145, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 21:19:52'),
(146, 51, 27, 'new_message', 'You have a new message from a client.', 42, 1, '2025-04-24 21:28:26'),
(147, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 21:47:36'),
(148, 51, 27, 'new_message', 'You have a new message from a client.', 42, 1, '2025-04-24 21:49:35'),
(149, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 22:26:40'),
(150, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 22:39:56'),
(151, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 22:40:45'),
(152, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 22:42:19'),
(153, 51, 27, 'new_message', 'You have a new message from a client.', 45, 1, '2025-04-24 22:42:23'),
(154, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 22:51:23'),
(155, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-24 22:52:53'),
(156, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-24 22:53:56'),
(157, 51, 27, 'new_message', 'You have a new message from a client.', 47, 1, '2025-04-24 22:54:03'),
(158, 51, NULL, NULL, 'Your withdrawal request of 2800.00 has been approved.', NULL, 1, '2025-04-25 13:38:45'),
(159, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-25 18:43:56'),
(160, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-25 18:49:05'),
(161, 51, 27, 'new_message', 'You have a new message from a client.', 48, 1, '2025-04-25 19:00:49'),
(162, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-25 19:01:43'),
(163, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-25 19:20:36'),
(164, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-25 19:40:59'),
(165, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-25 19:41:11'),
(166, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-25 19:42:03'),
(167, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:42:15'),
(168, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:42:26'),
(169, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:42:28'),
(170, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:42:31'),
(171, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:42:34'),
(172, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:44:06'),
(173, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:44:29'),
(174, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:44:58'),
(175, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:45:50'),
(176, 51, NULL, NULL, 'A consultation has been completed and payment has been processed.', NULL, 1, '2025-04-25 19:47:08'),
(177, 51, 27, 'new_message', 'You have a new message from a client.', 52, 1, '2025-04-25 19:47:23'),
(188, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-26 13:29:02'),
(189, 51, NULL, NULL, 'Your withdrawal request of 7,200.00 has been approved.', NULL, 1, '2025-04-26 14:07:18'),
(190, 51, NULL, NULL, 'Your withdrawal request of 7,200.00 has been approved.', NULL, 1, '2025-04-26 14:08:21'),
(193, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 17:00:02'),
(194, 51, 27, 'new_message', 'You have a new message from a client.', 56, 1, '2025-04-27 17:03:21'),
(195, 51, 27, 'new_message', 'You have a new message from a client.', 56, 1, '2025-04-27 17:16:54'),
(196, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 17:17:17'),
(197, 51, 27, 'new_message', 'You have a new message from a client.', 57, 1, '2025-04-27 17:19:02'),
(198, 51, 27, 'new_message', 'You have a new message from a client.', 57, 1, '2025-04-27 17:19:05'),
(199, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 17:24:55'),
(200, 51, 27, 'new_message', 'You have a new message from a client.', 58, 1, '2025-04-27 17:26:16'),
(201, 51, 27, 'new_message', 'You have a new message from a client.', 58, 1, '2025-04-27 17:26:27'),
(202, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 17:51:12'),
(203, 51, 27, 'new_message', 'You have a new message from a client.', 59, 1, '2025-04-27 17:51:38'),
(204, 51, 27, 'new_message', 'You have a new message from a client.', 59, 1, '2025-04-27 17:52:21'),
(205, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 18:06:44'),
(206, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 18:07:48'),
(207, 51, 27, 'new_message', 'You have a new message from a client.', 61, 1, '2025-04-27 18:08:05'),
(208, 51, 27, 'new_message', 'You have a new message from a client.', 61, 1, '2025-04-27 18:08:07'),
(209, 51, 27, 'new_message', 'You have a new message from a client.', 61, 1, '2025-04-27 18:16:10'),
(210, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 18:16:28'),
(211, 51, 27, 'new_message', 'You have a new message from a client.', 62, 1, '2025-04-27 18:16:44'),
(212, 51, 27, 'new_message', 'You have a new message from a client.', 62, 1, '2025-04-27 18:16:47'),
(213, 51, 27, 'new_message', 'You have a new message from a client.', 62, 1, '2025-04-27 18:16:47'),
(214, 51, 27, 'new_message', 'You have a new message from a client.', 62, 1, '2025-04-27 18:17:50'),
(215, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 18:18:23'),
(216, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 18:49:39'),
(217, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 18:50:29'),
(218, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 19:03:41'),
(219, 51, 27, 'new_message', 'You have a new message from a client.', 66, 1, '2025-04-27 19:10:20'),
(220, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 19:12:34'),
(221, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 19:19:11'),
(222, 51, 27, 'new_message', 'You have a new message from a client.', 68, 1, '2025-04-27 19:20:45'),
(223, 51, 27, 'new_message', 'You have a new message from a client.', 68, 1, '2025-04-27 19:24:00'),
(224, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 19:47:35'),
(225, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 20:11:19'),
(226, 51, 27, 'new_message', 'You have a new message from a client.', 70, 1, '2025-04-27 20:11:54'),
(227, 51, 27, 'new_message', 'You have a new message from a client.', 70, 1, '2025-04-27 20:12:07'),
(228, 51, 27, 'new_message', 'You have a new message from a client.', 70, 1, '2025-04-27 20:12:55'),
(229, 51, 27, 'new_message', 'You have a new message from a client.', 70, 1, '2025-04-27 20:12:57'),
(230, 51, 27, 'new_message', 'You have a new message from a client.', 70, 1, '2025-04-27 20:16:03'),
(231, 51, 27, 'new_message', 'You have a new message from a client.', 70, 1, '2025-04-27 20:16:34'),
(232, 51, 27, 'new_message', 'You have a new message from a client.', 70, 1, '2025-04-27 20:29:11'),
(233, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 20:29:30'),
(234, 51, 27, 'new_message', 'You have a new message from a client.', 71, 1, '2025-04-27 20:30:02'),
(235, 51, 27, 'new_message', 'You have a new message from a client.', 71, 1, '2025-04-27 20:30:31'),
(236, 51, 27, 'new_message', 'You have a new message from a client.', 71, 1, '2025-04-27 20:31:34'),
(237, 51, 27, 'new_message', 'You have a new message from a client.', 71, 1, '2025-04-27 20:32:38'),
(238, 51, 27, 'new_message', 'You have a new message from a client.', 71, 1, '2025-04-27 20:34:19'),
(239, 51, 27, 'new_message', 'You have a new message from a client.', 71, 1, '2025-04-27 20:34:33'),
(240, 51, NULL, NULL, 'You have a new consultation request.', NULL, 1, '2025-04-27 20:40:54'),
(241, 51, 27, 'new_message', 'You have a new message from a client.', 72, 1, '2025-04-27 20:42:55'),
(242, 51, 27, 'new_message', 'You have a new message from a client.', 72, 1, '2025-04-27 20:42:59'),
(243, 51, 27, 'new_message', 'You have a new message from a client.', 72, 1, '2025-04-27 21:58:28'),
(279, 68, NULL, NULL, 'Your fund request of 10,000.00 has been rejected.', NULL, 0, '2025-04-28 14:26:15'),
(280, 68, NULL, NULL, 'Your fund request of 10,000.00 has been approved and added to your balance.', NULL, 0, '2025-04-28 14:30:10'),
(290, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 18:53:24'),
(291, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 18:58:59'),
(292, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 19:39:09'),
(293, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 19:39:09'),
(294, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 19:57:22'),
(295, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 20:07:09'),
(296, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 20:16:15'),
(297, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 20:19:53'),
(298, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 21:06:09'),
(299, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-04-29 22:31:21'),
(300, 115, NULL, 'section_approved', 'Your formations have been approved.', NULL, 0, '2025-04-29 23:44:42'),
(328, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:12:57'),
(329, 51, 27, 'new_message', 'You have a new message from a client.', 118, 1, '2025-05-01 20:13:25'),
(330, 51, 27, 'new_message', 'You have a new message from a client.', 118, 1, '2025-05-01 20:13:42'),
(331, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:14:29'),
(332, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:16:40'),
(333, 51, 27, 'new_message', 'You have a new message from a client.', 119, 1, '2025-05-01 20:18:45'),
(334, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:03'),
(335, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:10'),
(336, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:16'),
(337, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:24'),
(338, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:30'),
(339, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:35'),
(340, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:39'),
(341, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:42'),
(342, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:46'),
(343, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:19:46'),
(344, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:20:13'),
(345, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:20:16'),
(346, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:20:19'),
(347, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-01 20:20:22'),
(348, 51, 27, 'new_rating', 'You have received a new rating.', NULL, 1, '2025-05-01 20:21:55'),
(349, 51, 27, 'new_rating', 'You have received a new rating.', NULL, 1, '2025-05-01 20:22:53'),
(350, 51, NULL, NULL, 'A report against you has been accepted and a refund of 500.00 DA has been processed. Payment has been reimbursed. This is a warning. Please contact support if you have any questions.', NULL, 1, '2025-05-01 20:44:17'),
(351, 51, NULL, NULL, 'A report against you has been accepted and a refund of 500.00 DA has been processed. Payment has been reimbursed. This is a warning. Please contact support if you have any questions.', NULL, 1, '2025-05-01 20:45:31'),
(352, 51, NULL, NULL, 'A report against you has been accepted and a refund of 500.00 DA has been processed. Payment has been reimbursed. This is a warning. Please contact support if you have any questions.', NULL, 1, '2025-05-01 20:45:42'),
(353, 51, NULL, NULL, 'A report against you has been accepted and a refund of 500.00 DA has been processed. Payment has been reimbursed. This is a warning. Please contact support if you have any questions.', NULL, 1, '2025-05-01 20:46:05'),
(354, 51, NULL, NULL, 'A report against you has been accepted and a refund of 500.00 DA has been processed. Payment has been reimbursed. This is a warning. Please contact support if you have any questions.', NULL, 1, '2025-05-01 20:46:13'),
(355, 51, NULL, NULL, 'A report against you has been accepted and a refund of 600.00 DA has been processed. Payment has been reimbursed. This is a warning. Please contact support if you have any questions.', NULL, 1, '2025-05-01 20:46:52'),
(373, 51, NULL, NULL, 'You have received a new message.', NULL, 1, '2025-05-03 12:04:40'),
(401, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 18:13:56'),
(402, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 18:14:22'),
(403, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 18:38:23'),
(404, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 18:39:40'),
(405, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 19:45:51'),
(406, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 19:59:25'),
(407, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 20:21:29'),
(408, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 20:45:07'),
(409, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 20:49:02'),
(410, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 20:49:29'),
(411, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 20:51:35'),
(412, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:00:26'),
(413, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:07:00'),
(414, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:19:07'),
(415, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:25:43'),
(416, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:26:45'),
(417, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:30:17'),
(418, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:30:26'),
(419, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:51:44'),
(420, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 21:53:59'),
(421, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:16:25'),
(422, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:17:48'),
(423, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:19:53'),
(424, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:22:08'),
(425, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:28:38'),
(426, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:33:22'),
(427, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:35:33'),
(428, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:39:12'),
(429, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:39:49'),
(430, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-04 22:43:07'),
(454, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-07 10:50:24'),
(455, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-07 10:52:15'),
(456, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-07 10:56:38'),
(457, 51, NULL, NULL, 'A report against you has been accepted and a refund of 500.00 DA has been processed. Payment has been reimbursed. This is a warning. Please contact support if you have any questions.', NULL, 1, '2025-05-07 11:02:57'),
(458, 51, 27, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-07 11:14:40'),
(459, 54, 28, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-07 19:29:33'),
(460, 54, 28, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-07 19:31:04'),
(461, 54, 28, 'consultation_canceled', 'A client has canceled their consultation request.', NULL, 1, '2025-05-07 19:32:28'),
(462, 54, 28, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-07 19:38:21'),
(463, 54, 28, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-08 23:31:39'),
(464, 54, 28, 'new_consultation', 'You have a new consultation request.', NULL, 1, '2025-05-08 23:32:29'),
(465, 54, 28, 'consultation_canceled', 'A client has canceled their consultation request.', NULL, 1, '2025-05-08 23:33:20');

-- --------------------------------------------------------

--
-- Structure de la table `expert_profiledetails`
--

CREATE TABLE `expert_profiledetails` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `subcategory` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `workplace_map_url` varchar(255) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'pending_review' COMMENT 'Status can be: pending_review, approved, rejected',
  `profile_status` varchar(20) DEFAULT 'pending_review',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `certificates_status` varchar(20) DEFAULT 'pending_review',
  `experiences_status` varchar(20) DEFAULT 'pending_review',
  `formations_status` varchar(20) DEFAULT 'pending_review',
  `banking_status` varchar(20) DEFAULT 'pending_review',
  `profile_feedback` text DEFAULT NULL,
  `certificates_feedback` text DEFAULT NULL,
  `experiences_feedback` text DEFAULT NULL,
  `formations_feedback` text DEFAULT NULL,
  `banking_feedback` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `expert_profiledetails`
--

INSERT INTO `expert_profiledetails` (`id`, `user_id`, `category`, `subcategory`, `city`, `workplace_map_url`, `skills`, `created_at`, `status`, `profile_status`, `submitted_at`, `reviewed_at`, `reviewed_by`, `certificates_status`, `experiences_status`, `formations_status`, `banking_status`, `profile_feedback`, `certificates_feedback`, `experiences_feedback`, `formations_feedback`, `banking_feedback`, `approved_at`, `rejected_at`, `rejection_reason`) VALUES
(2, 4, '1', '1', '1', 'https://maps.app.goo.gl/D1wd4LLiHcTE6oxv8', NULL, '2025-04-17 14:51:59', 'approved', 'approved', '2025-04-17 14:52:13', '2025-04-17 17:10:48', 1, 'approved', 'approved', 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 5, '2', '2', '29', NULL, NULL, '2025-04-25 18:54:56', 'approved', 'approved', '2025-04-25 18:56:10', '2025-04-25 20:57:03', 1, 'approved', 'approved', 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 6, '1', '1', '1', 'https://maps.app.goo.gl/D1wd4LLiHcTE6oxv8', NULL, '2025-04-17 14:51:59', 'approved', 'approved', '2025-04-17 14:52:13', '2025-04-17 17:10:48', 1, 'approved', 'approved', 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 7, '2', '2', '29', NULL, NULL, '2025-04-25 18:54:56', 'approved', 'approved', '2025-04-25 18:56:10', '2025-04-25 20:57:03', 1, 'approved', 'approved', 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 8, '1', '1', '1', 'https://maps.app.goo.gl/D1wd4LLiHcTE6oxv8', NULL, '2025-04-17 14:51:59', 'approved', 'approved', '2025-04-17 14:52:13', '2025-04-17 17:10:48', 1, 'approved', 'approved', 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 9, '2', '2', '29', NULL, NULL, '2025-04-25 18:54:56', 'approved', 'approved', '2025-04-25 18:56:10', '2025-04-25 20:57:03', 1, 'approved', 'approved', 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 51, '1', '1', '1', 'https://maps.app.goo.gl/D1wd4LLiHcTE6oxv8', NULL, '2025-04-17 15:51:59', 'approved', 'approved', '2025-04-17 15:52:13', '2025-04-17 17:10:48', 1, 'approved', 'approved', 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 54, '2', '2', '1', NULL, NULL, '2025-04-25 19:54:56', 'approved', 'approved', '2025-04-25 19:56:10', '2025-04-25 20:57:03', 1, 'approved', 'approved', 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, '2025-04-26 11:34:22', NULL, NULL),
(29, 67, '1', '1', '10', NULL, NULL, '2025-04-28 13:19:37', 'approved', 'approved', '2025-04-28 13:27:14', '2025-04-25 20:57:03', 1, 'approved', 'approved', 'approved', 'pending_review', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 119, '8', '29', '29', NULL, NULL, '2025-05-06 23:32:50', 'pending_review', 'pending_review', '2025-05-07 00:35:53', NULL, NULL, 'pending_review', 'pending_review', 'pending_review', 'pending_review', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `expert_ratings`
--

CREATE TABLE `expert_ratings` (
  `id` int(11) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `likes` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `expert_ratings`
--

INSERT INTO `expert_ratings` (`id`, `expert_id`, `client_id`, `consultation_id`, `rating`, `comment`, `likes`, `created_at`, `updated_at`, `is_read`) VALUES
(1, 54, 52, 49, 2, 'tghgukilouss', 0, '2025-04-22 18:45:09', '2025-05-01 18:12:53', 1),
(6, 27, 52, 14, 5, 'YEDDVIOSDJV?FVDSVFFS', 0, '2025-04-22 19:52:26', '2025-04-22 19:52:26', 0),
(11, 51, 68, 134, 5, 'sdvsdv<sx', 0, '2025-05-01 20:21:55', '2025-05-02 19:11:28', 1),
(12, 51, 68, 132, 5, 'Ouus', 0, '2025-05-01 20:22:53', '2025-05-02 19:11:28', 1);

-- --------------------------------------------------------

--
-- Structure de la table `expert_rating_likes`
--

CREATE TABLE `expert_rating_likes` (
  `id` int(11) NOT NULL,
  `rating_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `expert_rating_responses`
--

CREATE TABLE `expert_rating_responses` (
  `id` int(11) NOT NULL,
  `rating_id` int(11) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `response_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `expert_rating_responses`
--

INSERT INTO `expert_rating_responses` (`id`, `rating_id`, `expert_id`, `response_text`, `created_at`) VALUES
(1, 6, 27, 'wh sahbi', '2025-04-29 12:30:04');

-- --------------------------------------------------------

--
-- Structure de la table `expert_social_links`
--

CREATE TABLE `expert_social_links` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `expert_social_links`
--

INSERT INTO `expert_social_links` (`id`, `user_id`, `profile_id`, `facebook_url`, `instagram_url`, `linkedin_url`, `github_url`, `twitter_url`, `website_url`, `created_at`, `updated_at`) VALUES
(7, 51, 27, 'https://facebook/oussama.boucetta.29/', '', '', '', '', '', '2025-04-17 16:16:41', '2025-04-17 16:16:41');

-- --------------------------------------------------------

--
-- Structure de la table `formations`
--

CREATE TABLE `formations` (
  `id` int(11) NOT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `formation_name` varchar(255) NOT NULL,
  `formation_type` varchar(100) NOT NULL,
  `formation_year` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `formations`
--

INSERT INTO `formations` (`id`, `profile_id`, `formation_name`, `formation_type`, `formation_year`, `description`, `file_path`, `created_at`, `status`, `rejection_reason`) VALUES
(14, 27, 'pharmacy', 'certificate', 2022, '', '', '2025-04-17 15:51:59', 'approved', NULL),
(15, 28, 'pharmacie', 'certificate', 2012, 'azdcqs', '../uploads/formations/form_54_0_1745610896.jpg', '2025-04-25 19:54:56', 'approved', NULL),
(16, 39, 'pharmacie', 'workshop', 2013, 'qxdqsc', '../uploads/formations/form_119_0_1746574370.jpg', '2025-05-06 23:32:50', 'pending', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `fund_requests`
--

CREATE TABLE `fund_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `previous_balance` decimal(10,2) NOT NULL,
  `new_balance` decimal(10,2) NOT NULL,
  `proof_image` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `fund_requests`
--

INSERT INTO `fund_requests` (`id`, `user_id`, `amount`, `previous_balance`, `new_balance`, `proof_image`, `status`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 52, 2000.00, 117147.00, 119147.00, '../uploads/fund_proofs/proof_52_1745519921_logo.png', 'approved', 'hjknjk', '2025-04-24 18:38:41', '2025-04-26 19:52:25'),
(2, 68, 10000.00, 0.00, 10000.00, '../uploads/fund_proofs/proof_68_1745850275_logo.png', 'rejected', '', '2025-04-28 14:24:35', '2025-04-28 14:26:15'),
(3, 68, 10000.00, 0.00, 10000.00, '../uploads/fund_proofs/proof_68_1745850553_logo.png', 'approved', '', '2025-04-28 14:29:13', '2025-05-08 20:47:58'),
(4, 68, 1.00, 4600.00, 4601.00, '../uploads/fund_proofs/proof_68_1746134122_pexels-yankrukov-ed.jpg', 'pending', NULL, '2025-05-01 21:15:22', '2025-05-01 21:15:22');

-- --------------------------------------------------------

--
-- Structure de la table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','completed','rejected','canceled') NOT NULL DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `payments`
--

INSERT INTO `payments` (`id`, `consultation_id`, `client_id`, `expert_id`, `amount`, `status`, `transaction_id`, `created_at`) VALUES
(1, 12, 52, 51, 21800.00, 'completed', NULL, '2025-04-22 18:06:18'),
(2, 14, 52, 51, 600.00, 'completed', NULL, '2025-04-22 18:32:58'),
(3, 15, 52, 51, 200.00, 'completed', NULL, '2025-04-22 19:07:16'),
(4, 16, 52, 51, 100.00, 'completed', NULL, '2025-04-22 19:15:58'),
(5, 18, 52, 51, 500.00, 'completed', NULL, '2025-04-23 11:42:25'),
(6, 19, 52, 51, 600.00, 'completed', NULL, '2025-04-24 09:59:35'),
(7, 20, 52, 51, 100.00, 'completed', NULL, '2025-04-24 10:03:17'),
(8, 21, 52, 51, 300.00, 'completed', NULL, '2025-04-24 10:07:32'),
(9, 22, 52, 51, 700.00, 'completed', NULL, '2025-04-24 10:28:59'),
(10, 23, 52, 51, 600.00, 'completed', NULL, '2025-04-24 10:29:15'),
(11, 25, 52, 51, 300.00, 'completed', NULL, '2025-04-24 10:32:40'),
(12, 26, 52, 51, 100.00, 'completed', NULL, '2025-04-24 10:33:53'),
(13, 27, 52, 51, 100.00, 'completed', NULL, '2025-04-24 10:35:50'),
(14, 28, 52, 51, 100.00, 'completed', NULL, '2025-04-24 10:38:48'),
(15, 29, 52, 51, 100.00, 'completed', NULL, '2025-04-24 10:40:34'),
(16, 30, 52, 51, 100.00, 'completed', NULL, '2025-04-24 10:43:04'),
(17, 31, 52, 51, 700.00, 'completed', NULL, '2025-04-24 10:50:29'),
(18, 32, 52, 51, 200.00, 'completed', NULL, '2025-04-24 10:53:21'),
(19, 33, 52, 51, 100.00, 'completed', NULL, '2025-04-24 11:14:27'),
(20, 34, 52, 51, 100.00, 'completed', NULL, '2025-04-24 11:15:48'),
(21, 35, 52, 51, 200.00, 'completed', NULL, '2025-04-24 11:18:30'),
(22, 36, 52, 51, 100.00, 'completed', NULL, '2025-04-24 11:19:46'),
(23, 38, 52, 51, 100.00, 'completed', NULL, '2025-04-24 11:30:52'),
(24, 40, 52, 51, 200.00, 'completed', NULL, '2025-04-24 15:06:42'),
(25, 41, 52, 51, 100.00, 'completed', NULL, '2025-04-24 20:14:29'),
(26, 42, 52, 51, 500.00, 'completed', NULL, '2025-04-24 21:19:52'),
(27, 44, 52, 51, 100.00, 'completed', NULL, '2025-04-24 22:39:56'),
(28, 45, 52, 51, 100.00, 'completed', NULL, '2025-04-24 22:42:19'),
(29, 47, 52, 51, 100.00, 'completed', NULL, '2025-04-24 22:53:56'),
(30, 48, 52, 51, 500.00, 'completed', NULL, '2025-04-25 18:49:05'),
(31, 51, 52, 51, 100.00, 'completed', NULL, '2025-04-25 19:41:11'),
(32, 52, 52, 51, 500.00, 'completed', NULL, '2025-04-25 19:47:08'),
(33, 53, 52, 54, 200.00, 'completed', NULL, '2025-04-25 20:22:33'),
(34, 73, 52, 54, 8200.00, 'completed', NULL, '2025-04-27 22:40:23'),
(35, 74, 52, 54, 200.00, 'completed', NULL, '2025-04-27 22:41:51'),
(36, 76, 52, 54, 200.00, 'completed', NULL, '2025-04-27 23:52:47'),
(37, 82, 68, 54, 200.00, 'completed', NULL, '2025-04-28 14:42:21'),
(38, 83, 68, 54, 1000.00, 'completed', NULL, '2025-04-28 14:48:07'),
(39, 93, 52, 51, 500.00, 'pending', NULL, '2025-04-29 18:53:24'),
(40, 94, 52, 51, 500.00, 'pending', NULL, '2025-04-29 18:58:59'),
(41, 95, 52, 51, 500.00, 'pending', NULL, '2025-04-29 19:39:09'),
(42, 96, 52, 51, 500.00, 'pending', NULL, '2025-04-29 19:39:09'),
(43, 96, 52, 51, 100.00, 'completed', NULL, '2025-04-29 19:40:04'),
(44, 95, 52, 51, 100.00, 'completed', NULL, '2025-04-29 19:40:15'),
(45, 97, 52, 51, 500.00, 'pending', NULL, '2025-04-29 19:57:22'),
(46, 98, 52, 51, 500.00, 'pending', NULL, '2025-04-29 20:07:09'),
(47, 99, 52, 51, 500.00, 'pending', NULL, '2025-04-29 20:16:15'),
(48, 100, 52, 51, 500.00, 'pending', NULL, '2025-04-29 20:19:53'),
(49, 101, 52, 51, 500.00, 'pending', NULL, '2025-04-29 21:06:09'),
(50, 102, 52, 51, 500.00, 'pending', NULL, '2025-04-29 22:31:21'),
(51, 103, 52, 54, 1000.00, 'completed', NULL, '2025-04-30 17:44:47'),
(52, 104, 52, 54, 1000.00, 'completed', NULL, '2025-04-30 17:54:23'),
(53, 105, 52, 54, 1400.00, 'pending', NULL, '2025-04-30 18:02:28'),
(54, 106, 52, 54, 1600.00, 'pending', NULL, '2025-04-30 18:02:38'),
(55, 107, 52, 54, 1000.00, 'pending', NULL, '2025-04-30 19:21:45'),
(56, 108, 52, 54, 1000.00, 'pending', NULL, '2025-04-30 19:21:45'),
(57, 109, 52, 54, 1000.00, '', NULL, '2025-04-30 21:03:34'),
(58, 110, 52, 54, 1000.00, 'pending', NULL, '2025-04-30 21:03:34'),
(59, 110, 52, 54, 200.00, 'completed', NULL, '2025-04-30 21:04:55'),
(60, 111, 52, 54, 1000.00, 'pending', NULL, '2025-04-30 21:05:20'),
(61, 111, 52, 54, 200.00, 'completed', NULL, '2025-04-30 23:15:48'),
(62, 112, 52, 54, 1000.00, 'pending', NULL, '2025-04-30 23:16:17'),
(63, 113, 52, 54, 1000.00, 'pending', NULL, '2025-04-30 23:28:16'),
(64, 114, 52, 54, 1400.00, 'pending', NULL, '2025-04-30 23:53:03'),
(65, 115, 52, 54, 1400.00, 'pending', NULL, '2025-04-30 23:53:11'),
(66, 117, 68, 54, 1000.00, '', NULL, '2025-05-01 17:41:39'),
(67, 118, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:12:57'),
(68, 118, 68, 51, 100.00, 'completed', NULL, '2025-05-01 20:14:01'),
(69, 119, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:14:29'),
(70, 120, 68, 51, 600.00, 'pending', NULL, '2025-05-01 20:16:40'),
(71, 120, 68, 51, 100.00, 'completed', NULL, '2025-05-01 20:18:26'),
(72, 119, 68, 51, 100.00, 'completed', NULL, '2025-05-01 20:18:47'),
(73, 121, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:03'),
(74, 122, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:10'),
(75, 123, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:16'),
(76, 124, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:24'),
(77, 125, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:30'),
(78, 126, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:35'),
(79, 127, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:39'),
(80, 128, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:42'),
(81, 129, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:46'),
(82, 130, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:19:46'),
(83, 131, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:20:13'),
(84, 132, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:20:16'),
(85, 133, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:20:19'),
(86, 134, 68, 51, 500.00, 'pending', NULL, '2025-05-01 20:20:22'),
(87, 134, 68, 51, 200.00, 'completed', NULL, '2025-05-01 20:21:40'),
(88, 133, 68, 51, 200.00, 'completed', NULL, '2025-05-01 20:22:12'),
(89, 132, 68, 51, 200.00, 'completed', NULL, '2025-05-01 20:22:34'),
(90, 131, 68, 51, 300.00, 'completed', NULL, '2025-05-01 20:23:10'),
(91, 129, 68, 51, 300.00, 'completed', NULL, '2025-05-01 20:23:33'),
(92, 130, 68, 51, 300.00, 'completed', NULL, '2025-05-01 20:23:55'),
(93, 127, 68, 51, 400.00, 'completed', NULL, '2025-05-01 20:24:21'),
(94, 128, 68, 51, 400.00, 'completed', NULL, '2025-05-01 20:24:48'),
(95, 125, 68, 51, 400.00, 'completed', NULL, '2025-05-01 20:25:10'),
(96, 126, 68, 51, 500.00, 'completed', NULL, '2025-05-01 20:25:34'),
(97, 121, 68, 51, 500.00, 'completed', NULL, '2025-05-01 20:25:52'),
(98, 123, 68, 51, 500.00, 'completed', NULL, '2025-05-01 20:26:20'),
(99, 124, 68, 51, 600.00, 'completed', NULL, '2025-05-01 20:26:50'),
(100, 122, 68, 51, 600.00, 'completed', NULL, '2025-05-01 20:27:19'),
(101, 135, 52, 54, 1200.00, '', NULL, '2025-05-02 17:38:19'),
(102, 136, 52, 54, 1000.00, '', NULL, '2025-05-02 17:43:07'),
(103, 137, 52, 54, 1000.00, '', NULL, '2025-05-02 18:04:00'),
(104, 138, 52, 54, 1000.00, '', NULL, '2025-05-02 18:08:16'),
(105, 139, 52, 54, 1000.00, '', NULL, '2025-05-02 18:08:24'),
(106, 140, 52, 54, 1000.00, '', NULL, '2025-05-02 18:17:39'),
(107, 157, 52, 54, 1000.00, '', NULL, '2025-05-03 13:29:49'),
(108, 158, 52, 54, 1000.00, '', NULL, '2025-05-03 13:46:04'),
(109, 159, 52, 54, 1000.00, '', NULL, '2025-05-03 13:50:16'),
(110, 160, 52, 54, 1000.00, '', NULL, '2025-05-03 14:05:07'),
(111, 161, 52, 54, 1000.00, '', NULL, '2025-05-04 08:44:11'),
(112, 162, 52, 54, 1000.00, '', NULL, '2025-05-04 08:46:01'),
(113, 163, 52, 54, 1000.00, '', NULL, '2025-05-04 08:50:34'),
(114, 164, 52, 54, 1000.00, '', NULL, '2025-05-04 08:55:29'),
(115, 165, 52, 54, 1000.00, '', NULL, '2025-05-04 08:56:12'),
(116, 166, 52, 54, 1000.00, '', NULL, '2025-05-04 09:05:22'),
(117, 167, 52, 54, 1000.00, '', NULL, '2025-05-04 09:22:13'),
(118, 168, 52, 54, 1200.00, '', NULL, '2025-05-04 09:30:19'),
(119, 169, 52, 54, 1000.00, '', NULL, '2025-05-04 09:51:17'),
(120, 170, 52, 54, 1000.00, 'pending', NULL, '2025-05-04 09:51:50'),
(121, 171, 52, 54, 1000.00, '', NULL, '2025-05-04 09:57:12'),
(122, 172, 52, 54, 1200.00, '', NULL, '2025-05-04 17:58:50'),
(123, 173, 52, 51, 500.00, '', NULL, '2025-05-04 18:13:56'),
(124, 174, 52, 51, 500.00, '', NULL, '2025-05-04 18:14:22'),
(125, 175, 52, 51, 500.00, '', NULL, '2025-05-04 18:38:23'),
(126, 176, 52, 51, 500.00, '', NULL, '2025-05-04 18:39:40'),
(127, 177, 52, 51, 500.00, '', NULL, '2025-05-04 19:45:51'),
(128, 178, 52, 51, 600.00, 'pending', NULL, '2025-05-04 19:59:25'),
(129, 179, 52, 51, 500.00, 'pending', NULL, '2025-05-04 20:21:29'),
(130, 180, 52, 51, 500.00, '', NULL, '2025-05-04 20:45:07'),
(131, 181, 52, 51, 500.00, '', NULL, '2025-05-04 20:49:02'),
(132, 182, 52, 51, 500.00, 'pending', NULL, '2025-05-04 20:49:29'),
(133, 183, 52, 51, 500.00, 'pending', NULL, '2025-05-04 20:51:35'),
(134, 184, 52, 51, 500.00, 'pending', NULL, '2025-05-04 21:00:26'),
(135, 185, 52, 51, 500.00, 'pending', NULL, '2025-05-04 21:07:00'),
(136, 186, 52, 51, 500.00, 'pending', NULL, '2025-05-04 21:19:07'),
(137, 187, 52, 51, 500.00, '', NULL, '2025-05-04 21:25:43'),
(138, 188, 52, 51, 500.00, '', NULL, '2025-05-04 21:26:45'),
(139, 189, 52, 51, 500.00, 'pending', NULL, '2025-05-04 21:30:17'),
(140, 190, 52, 51, 500.00, '', NULL, '2025-05-04 21:30:26'),
(141, 191, 52, 51, 500.00, '', NULL, '2025-05-04 21:51:44'),
(142, 192, 52, 51, 500.00, 'pending', NULL, '2025-05-04 21:53:59'),
(143, 193, 52, 51, 500.00, 'pending', NULL, '2025-05-04 22:16:25'),
(144, 194, 52, 51, 500.00, '', NULL, '2025-05-04 22:17:48'),
(145, 195, 68, 51, 500.00, '', NULL, '2025-05-04 22:19:53'),
(146, 196, 68, 51, 500.00, '', NULL, '2025-05-04 22:22:08'),
(147, 197, 68, 51, 500.00, '', NULL, '2025-05-04 22:28:38'),
(148, 198, 68, 51, 500.00, 'pending', NULL, '2025-05-04 22:33:22'),
(149, 199, 68, 51, 500.00, 'pending', NULL, '2025-05-04 22:35:33'),
(150, 200, 68, 51, 500.00, '', NULL, '2025-05-04 22:39:12'),
(151, 201, 68, 51, 500.00, '', NULL, '2025-05-04 22:39:49'),
(152, 202, 68, 51, 500.00, '', NULL, '2025-05-04 22:43:07'),
(153, 203, 52, 54, 1000.00, '', NULL, '2025-05-05 09:42:40'),
(154, 204, 52, 54, 1000.00, '', NULL, '2025-05-05 09:45:14'),
(155, 205, 52, 54, 1000.00, '', NULL, '2025-05-05 09:50:19'),
(156, 206, 52, 54, 1000.00, '', NULL, '2025-05-05 09:54:21'),
(157, 207, 52, 54, 1000.00, '', NULL, '2025-05-05 09:54:34'),
(158, 208, 52, 54, 1000.00, '', NULL, '2025-05-05 09:57:15'),
(159, 209, 52, 54, 1000.00, '', NULL, '2025-05-05 09:59:10'),
(160, 210, 52, 54, 1000.00, '', NULL, '2025-05-05 10:00:32'),
(161, 211, 52, 54, 1000.00, '', NULL, '2025-05-05 10:03:41'),
(162, 212, 52, 54, 1000.00, '', NULL, '2025-05-05 10:16:40'),
(163, 213, 52, 54, 1000.00, '', NULL, '2025-05-05 10:17:14'),
(164, 214, 52, 54, 1000.00, '', NULL, '2025-05-05 10:17:38'),
(165, 215, 52, 54, 1000.00, '', NULL, '2025-05-05 10:19:02'),
(166, 216, 52, 54, 1000.00, 'pending', NULL, '2025-05-05 10:21:01'),
(167, 217, 52, 54, 1000.00, 'pending', NULL, '2025-05-05 10:23:09'),
(168, 218, 52, 54, 1000.00, '', NULL, '2025-05-05 16:50:17'),
(169, 219, 52, 54, 1000.00, '', NULL, '2025-05-05 16:50:18'),
(170, 220, 52, 54, 1000.00, '', NULL, '2025-05-05 16:50:18'),
(171, 221, 52, 54, 1000.00, '', NULL, '2025-05-05 16:55:39'),
(172, 222, 52, 54, 1000.00, '', NULL, '2025-05-05 17:04:23'),
(173, 223, 52, 54, 1000.00, '', NULL, '2025-05-05 17:56:59'),
(174, 224, 52, 54, 1000.00, 'pending', NULL, '2025-05-05 17:59:55'),
(175, 225, 52, 54, 1000.00, '', NULL, '2025-05-05 18:28:25'),
(176, 226, 52, 51, 900.00, '', NULL, '2025-05-07 10:50:24'),
(177, 227, 52, 51, 500.00, 'pending', NULL, '2025-05-07 10:52:15'),
(178, 228, 68, 51, 500.00, 'pending', NULL, '2025-05-07 10:56:38'),
(179, 227, 52, 51, 600.00, 'completed', NULL, '2025-05-07 10:58:48'),
(180, 229, 52, 51, 500.00, '', NULL, '2025-05-07 11:14:40'),
(181, 135, 52, 54, 200.00, 'completed', NULL, '2025-05-07 19:28:12'),
(182, 230, 52, 54, 1000.00, '', NULL, '2025-05-07 19:29:33'),
(183, 231, 52, 54, 1000.00, 'pending', NULL, '2025-05-07 19:31:04'),
(184, 232, 52, 54, 1000.00, 'pending', NULL, '2025-05-07 19:38:21'),
(185, 233, 68, 54, 1200.00, 'pending', NULL, '2025-05-08 23:31:39'),
(186, 234, 68, 54, 1400.00, 'pending', NULL, '2025-05-08 23:32:29');

-- --------------------------------------------------------

--
-- Structure de la table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL COMMENT 'ID of the user making the report',
  `reported_id` int(11) NOT NULL COMMENT 'ID of the user being reported',
  `report_type` varchar(50) NOT NULL COMMENT 'Type of report (spam, inappropriate, etc)',
  `message` text NOT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','accepted','rejected','remborser','handled') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reports`
--

INSERT INTO `reports` (`id`, `consultation_id`, `reporter_id`, `reported_id`, `report_type`, `message`, `admin_notes`, `created_at`, `updated_at`, `status`) VALUES
(1, 83, 68, 54, 'payment', 'maradch 3liya', 'Payment has been reimbursed', '2025-04-28 14:49:30', '2025-05-01 17:00:20', 'remborser'),
(2, 107, 54, 52, 'habalni', 'MLK MLK MAA', '', '2025-04-30 20:40:12', '2025-05-01 16:59:46', ''),
(3, 118, 68, 51, 'technical', 'yes', '', '2025-05-01 20:14:12', '2025-05-01 20:47:04', ''),
(4, 120, 68, 51, 'inappropriate', 'zergvrf', 'Payment has been reimbursed', '2025-05-01 20:18:30', '2025-05-01 20:46:52', 'remborser'),
(5, 119, 68, 51, 'technical', 'sdvcedc', '', '2025-05-01 20:18:51', '2025-05-01 20:46:40', ''),
(6, 134, 68, 51, 'inappropriate', 'qdcvsdc', '', '2025-05-01 20:21:47', '2025-05-01 20:46:28', ''),
(7, 133, 68, 51, 'technical', 'dvc <sdxw', 'Payment has been reimbursed', '2025-05-01 20:22:20', '2025-05-01 20:46:13', 'remborser'),
(8, 132, 68, 51, 'technical', 'oussa\r\n', 'Payment has been reimbursed', '2025-05-01 20:22:43', '2025-05-01 20:46:05', 'remborser'),
(9, 131, 68, 51, 'payment', 'sqcqsdc dwx', '', '2025-05-01 20:23:18', '2025-05-01 20:45:54', ''),
(10, 129, 68, 51, 'payment', 'sqc!;:dc\r\n', 'Payment has been reimbursed', '2025-05-01 20:23:41', '2025-05-01 20:45:42', 'remborser'),
(11, 130, 68, 51, 'payment', 'scs', 'Payment has been reimbursed', '2025-05-01 20:24:05', '2025-05-01 20:45:31', 'remborser'),
(12, 127, 68, 51, 'technical', 'wxcx ', '', '2025-05-01 20:24:28', '2025-05-01 20:45:13', ''),
(13, 128, 68, 51, 'quality', 'w xw x c', '', '2025-05-01 20:24:56', '2025-05-01 20:44:58', ''),
(14, 125, 68, 51, 'quality', 'wx x xc', '', '2025-05-01 20:25:17', '2025-05-01 20:44:36', ''),
(15, 126, 68, 51, 'other', 'w cxxdc', 'Payment has been reimbursed', '2025-05-01 20:25:41', '2025-05-01 20:44:17', 'remborser'),
(16, 121, 68, 51, 'technical', 'cswcx', '', '2025-05-01 20:25:59', '2025-05-01 20:44:10', ''),
(17, 123, 68, 51, 'inappropriate', 'wxc wx ', '', '2025-05-01 20:26:30', '2025-05-01 20:43:56', ''),
(18, 124, 68, 51, 'other', '<xsc xscxw', '', '2025-05-01 20:26:57', '2025-05-01 20:43:42', ''),
(19, 122, 68, 51, 'other', 'wx x ', '', '2025-05-01 20:27:26', '2025-05-01 20:43:02', ''),
(20, 227, 52, 51, 'payment', 'ma3amalnich ghaya', '', '2025-05-07 10:59:05', '2025-05-07 18:25:04', '');

-- --------------------------------------------------------

--
-- Structure de la table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Ouss Aya', '2025-03-16 21:17:51', '2025-04-15 20:31:00'),
(2, 'site_email', 'admin@consultpro.com', '2025-03-16 21:17:51', '2025-03-16 21:17:51'),
(3, 'site_description', 'Consult Pro - Expert Consultation Platform', '2025-03-16 21:17:51', '2025-03-16 21:17:51'),
(9, 'currency', 'DA', '2025-03-16 21:17:51', '2025-04-15 20:31:25'),
(10, 'payment_methods', 'ccp', '2025-03-16 21:17:51', '2025-04-08 23:37:49'),
(11, 'commission_rate', '10', '2025-03-16 21:17:51', '2025-04-26 12:37:19'),
(13, 'phone_number1', '456789', '2025-04-13 16:48:53', '2025-04-14 14:27:14'),
(14, 'phone_number2', '4567890', '2025-04-13 16:48:54', '2025-04-14 14:27:14'),
(15, 'facebook_url', 'https://www.reverso.net/text-translation', '2025-04-13 16:48:54', '2025-04-14 14:27:14'),
(16, 'instagram_url', 'https://www.reverso.net/text-translation', '2025-04-13 16:48:54', '2025-04-14 14:27:14'),
(19, 'site_image', 'site_logo_1744624511.png', '2025-04-14 09:55:11', '2025-04-14 09:55:11');

-- --------------------------------------------------------

--
-- Structure de la table `skills`
--

CREATE TABLE `skills` (
  `id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `skills`
--

INSERT INTO `skills` (`id`, `profile_id`, `skill_name`, `created_at`) VALUES
(1, 27, 'web', '2025-04-17 15:51:59'),
(2, 40, 'Web Developement', '2025-04-17 15:51:59'),
(3, 28, 'dev', '2025-04-25 19:54:56'),
(4, 39, 'web $', '2025-05-06 23:32:50'),
(5, 39, 'deqign', '2025-05-06 23:32:50'),
(6, 39, 'developement', '2025-05-06 23:32:50');

-- --------------------------------------------------------

--
-- Structure de la table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `subcategories`
--

INSERT INTO `subcategories` (`id`, `name`, `category_id`, `created_at`) VALUES
(1, 'Online Tutoring', 1, '2025-04-29 13:47:36'),
(2, 'Curriculum Development', 1, '2025-04-29 13:47:36'),
(3, 'Special Education', 1, '2025-04-29 13:47:36'),
(4, 'Language Training', 1, '2025-04-29 13:47:36'),
(5, 'Investment Banking', 2, '2025-04-29 13:47:36'),
(6, 'Accounting & Auditing', 2, '2025-04-29 13:47:36'),
(7, 'Financial Planning', 2, '2025-04-29 13:47:36'),
(8, 'Cryptocurrency Consulting', 2, '2025-04-29 13:47:36'),
(9, 'General Practitioners', 3, '2025-04-29 13:47:36'),
(10, 'Mental Health Therapists', 3, '2025-04-29 13:47:36'),
(11, 'Nutritionists', 3, '2025-04-29 13:47:36'),
(12, 'Physical Therapists', 3, '2025-04-29 13:47:36'),
(13, 'Software Development', 4, '2025-04-29 13:47:36'),
(14, 'Cybersecurity', 4, '2025-04-29 13:47:36'),
(15, 'Data Analytics', 4, '2025-04-29 13:47:36'),
(16, 'IT Support', 4, '2025-04-29 13:47:36'),
(17, 'Management Consulting', 5, '2025-04-29 13:47:36'),
(18, 'Human Resources', 5, '2025-04-29 13:47:36'),
(19, 'Marketing Strategy', 5, '2025-04-29 13:47:36'),
(20, 'Startup Advisory', 5, '2025-04-29 13:47:36'),
(21, 'Corporate Law', 6, '2025-04-29 13:47:36'),
(22, 'Immigration Law', 6, '2025-04-29 13:47:36'),
(23, 'Criminal Defense', 6, '2025-04-29 13:47:36'),
(24, 'Intellectual Property', 6, '2025-04-29 13:47:36'),
(25, 'Civil Engineering', 7, '2025-04-29 13:47:36'),
(26, 'Mechanical Design', 7, '2025-04-29 13:47:36'),
(27, 'Sustainable Architecture', 7, '2025-04-29 13:47:36'),
(28, 'Electrical Engineering', 7, '2025-04-29 13:47:36'),
(29, 'Graphic Design', 8, '2025-04-29 13:47:36'),
(30, 'Video Editing', 8, '2025-04-29 13:47:36'),
(31, 'Content Writing', 8, '2025-04-29 13:47:36'),
(32, 'Social Media Management', 8, '2025-04-29 13:47:36'),
(33, 'Property Valuation', 9, '2025-04-29 13:47:36'),
(34, 'Commercial Leasing', 9, '2025-04-29 13:47:36'),
(35, 'Mortgage Advisory', 9, '2025-04-29 13:47:36'),
(36, 'Urban Planning', 9, '2025-04-29 13:47:36'),
(37, 'Hotel Management', 10, '2025-04-29 13:47:36'),
(38, 'Event Planning', 10, '2025-04-29 13:47:36'),
(39, 'Travel Consulting', 10, '2025-04-29 13:47:36'),
(40, 'Culinary Arts', 10, '2025-04-29 13:47:36');

-- --------------------------------------------------------

--
-- Structure de la table `support_attachments`
--

CREATE TABLE `support_attachments` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `contact_type` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `status` enum('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `support_messages`
--

INSERT INTO `support_messages` (`id`, `user_id`, `consultation_id`, `contact_type`, `subject`, `message`, `contact_email`, `status`, `created_at`, `updated_at`) VALUES
(20, 68, NULL, 'technical', 'rzegdf', 'Moaaaah', 'boucettaoussama29@gmail.com', 'pending', '2025-05-01 21:13:55', '2025-05-08 21:13:25'),
(21, 68, NULL, 'general', 'yfyuigikyk', 'lem,zemc,e', 'boucettaoussama123@gmail.com', 'accepted', '2025-05-01 21:14:33', '2025-05-08 21:16:21');

-- --------------------------------------------------------

--
-- Structure de la table `support_message_replies`
--

CREATE TABLE `support_message_replies` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reply_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `support_message_replies`
--

INSERT INTO `support_message_replies` (`id`, `message_id`, `user_id`, `reply_text`, `created_at`) VALUES
(1, 4, 51, 'jhhkom', '2025-04-25 14:39:42'),
(2, 4, 51, 'uyoussa', '2025-04-25 14:54:47'),
(3, 4, 51, 'yassine', '2025-04-25 14:54:58'),
(4, 5, 54, 'vfdf', '2025-04-26 20:14:24');

-- --------------------------------------------------------

--
-- Structure de la table `support_responses`
--

CREATE TABLE `support_responses` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `response` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `support_responses`
--

INSERT INTO `support_responses` (`id`, `message_id`, `admin_id`, `response`, `created_at`, `is_read`) VALUES
(4, 4, 1, 'FEQFEQS', '2025-04-26 10:07:22', 0),
(5, 4, 1, 'dsvc ds', '2025-04-26 13:21:11', 0),
(6, 5, 1, 'mlk', '2025-04-26 20:12:08', 1);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` varchar(20) DEFAULT NULL,
  `status` enum('suspended','Online','Offline','Deleted') NOT NULL DEFAULT 'Offline',
  `balance` decimal(10,0) DEFAULT NULL,
  `verification_code` varchar(6) DEFAULT NULL,
  `code_expires_at` datetime DEFAULT NULL,
  `suspension_end_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `created_at`, `updated_at`, `role`, `status`, `balance`, `verification_code`, `code_expires_at`, `suspension_end_date`, `status_updated_at`, `deleted_at`, `last_login`) VALUES
(1, 'Ousamaboucetta', 'boucetta@gmail.com', '$2y$10$UaZXWS/E41w5tEHssqnHEeQgf2XbJ9d4OmD7m8rdK6Kdpv4HBKK7W', '2025-04-15 09:55:01', '2025-05-08 23:31:08', 'admin', 'Offline', 0, NULL, NULL, '2025-05-01 19:19:35', '2025-05-08 23:31:08', NULL, '2025-05-08 23:31:08'),
(4, 'Oussam', 'boucettaoussaZEma029@gmail.com', '$2y$10$xpJNj1D/rsuuwzHaCMIpGuHllupyvA9B0yKPEmx6nuIR1c0Q3AY5C', '2025-04-17 14:48:46', '2025-05-01 19:22:41', 'expert', 'Offline', 27200, NULL, NULL, '2025-05-01 19:22:41', '2025-05-04 10:39:13', NULL, NULL),
(5, 'Oussama Boucetta', 'BoucettaoussFVama050@gmail.com', '$2y$10$ehKkC3B9FoNpx1SNNZKjNe9Xx/N.sSAGVJJ7txUqDsXCiEIhaOC6C', '2025-04-17 14:53:03', '2025-05-07 23:30:51', 'expert', 'Deleted', 114947, NULL, NULL, '2025-05-01 19:19:35', '2025-05-07 23:30:51', '2025-05-07 23:33:59', NULL),
(6, 'Ouss', 'boucettaoussamaRE29@gmail.com', '$2y$10$8dGq7wQPdH05T3g/2Kn/SeWdDLBXvNcbmd7U75jlticaudm4q4/WO', '2025-04-25 18:50:51', '2025-04-25 20:05:01', 'expert', 'Offline', 200, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(7, 'Oussam', 'boucettaoussaFZEma029@gmail.com', '$2y$10$xpJNj1D/rsuuwzHaCMIpGuHllupyvA9B0yKPEmx6nuIR1c0Q3AY5C', '2025-04-17 14:48:46', '2025-04-25 19:21:12', 'expert', 'Offline', 27200, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(8, 'Oussama Boucetta', 'BoucetEZtaoussama050@gmail.com', '$2y$10$ehKkC3B9FoNpx1SNNZKjNe9Xx/N.sSAGVJJ7txUqDsXCiEIhaOC6C', '2025-04-17 14:53:03', '2025-04-25 20:05:42', 'expert', 'Offline', 114947, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(9, 'Ouss', 'boucettaoussamAAFa29@gmail.com', '$2y$10$8dGq7wQPdH05T3g/2Kn/SeWdDLBXvNcbmd7U75jlticaudm4q4/WO', '2025-04-25 18:50:51', '2025-04-25 20:05:01', 'expert', 'Offline', 200, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(51, 'Oussam', 'boucettaoussama029@gmail.com', '$2y$10$xpJNj1D/rsuuwzHaCMIpGuHllupyvA9B0yKPEmx6nuIR1c0Q3AY5C', '2025-04-17 15:48:46', '2025-05-07 21:22:19', 'expert', 'suspended', 33600, '726167', '2025-05-03 18:13:09', '2025-06-06 22:22:19', '2025-05-07 21:22:19', NULL, NULL),
(52, 'Oussama Boucetta', 'Boucettaoussama090@gmail.com', '$2y$10$ehKkC3B9FoNpx1SNNZKjNe9Xx/N.sSAGVJJ7txUqDsXCiEIhaOC6C', '2025-04-17 15:53:03', '2025-05-07 23:12:06', 'client', 'Offline', 75547, '315097', '2025-05-07 11:16:26', '2025-05-01 19:19:35', '2025-05-07 23:12:06', NULL, '2025-05-07 23:12:06'),
(54, 'OussAya', 'boucettaoussama29@gmail.com', '$2y$10$8dGq7wQPdH05T3g/2Kn/SeWdDLBXvNcbmd7U75jlticaudm4q4/WO', '2025-04-25 19:50:51', '2025-05-08 23:31:26', 'expert', 'Online', 4945, NULL, NULL, '2025-05-01 19:19:35', '2025-05-08 23:31:26', NULL, NULL),
(55, 'Ouss', 'boucettaoussama0500@gmail.com', '$2y$10$OTAysWoPbhGTEMGVZFbUYua/gPds7sSaoF/6O3y6nTS.SU5sIK5Eu', '2025-04-26 15:28:13', '2025-04-26 15:28:13', 'client', 'Offline', NULL, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(57, 'Ouss', 'Bou6@gmail.com', '$2y$10$7wN3vMcAUrV2TYuO.xGs9eY/w8iyuSieNASu/Y7QTTD0OrqY2f2bG', '2025-04-28 10:25:24', '2025-04-28 10:54:25', 'client', 'Offline', NULL, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(58, 'Oussama', 'boucettaoussama@gmail.com', '$2y$10$Fwv3t.2ryRsF1OV6aCDww.wS2xI64m7nHPjxgvoZuTaID8u37MZI2', '2025-04-28 10:26:38', '2025-04-28 10:54:51', 'client', 'Offline', NULL, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(59, 'ouss', 'boucettaoussama2004@gmail.com', '$2y$10$DDrDWnuEjdRUNg3pWBoM9.hHgVF1IShoKIebl436nN4kry41W0Hq2', '2025-04-28 10:34:58', '2025-04-28 10:54:55', 'client', 'Offline', NULL, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(60, 'Ouss', 'oussamaboucetta04@gmail.com', '$2y$10$0Ea5O9yiJ9ABz0yeLrlRUevvsLhLPpkvEOFnlzQOWTkxTej6U/kNm', '2025-04-28 10:40:15', '2025-04-28 10:43:46', 'client', 'Offline', 0, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(61, 'tfgyhmiool', 'ayaBou6@gmail.com', '$2y$10$AczeHbwir1t84jDG9zlFIexlrYInrZh35mcpgdYj1NV0Swpu732nG', '2025-04-28 10:44:21', '2025-04-28 10:47:17', 'client', 'Offline', 0, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(63, 'OussAya', 'boucettaoussama05000@gmail.com', '$2y$10$XmPOXdzjaNs2DqN2KeZcJufBfiNVPLTMYwWhi6AQI/n4epmJx2GSi', '2025-04-28 10:49:39', '2025-04-28 10:49:39', 'expert', 'Offline', 0, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(64, 'Ouss', 'boucettaoussama0050@gmail.com', '$2y$10$50stgAjNK.DwgziHICs03OZD5NmioQDgo9bX9H1hgjogCsAGeVPBG', '2025-04-28 11:05:37', '2025-04-28 11:05:37', 'expert', 'Offline', 0, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(65, 'Ouss', 'boucettaoussama00050@gmail.com', '$2y$10$DNmQQcj5krrJAUPvbE.Af.sJKak/HXr7iy.8/1Rjn32bJKKFIVBtq', '2025-04-28 11:06:36', '2025-04-28 11:06:36', 'client', 'Offline', 0, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(66, 'Ouss', 'boucettaousisama00050@gmail.com', '$2y$10$88f5VmgnbYXa6XFyLG5myu9waZb.sVHL25lX/2LvvXRjodO5mpSjG', '2025-04-28 11:07:58', '2025-04-28 11:07:58', 'client', 'Offline', 0, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(67, 'Oussama', 'Bou66@gmail.com', '$2y$10$5GZ/RR/NxLg/DkQhsjndE.pbJUGcw2Q1jw6jhz12..H5ZP4h/2k3O', '2025-04-28 12:41:16', '2025-04-28 12:41:16', 'expert', 'Offline', 0, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(68, 'mohamed', 'MOHAMED@GMAIL.COM', '$2y$10$PGWkE0GMuH.e3OJzCW13ROjJzNbmx5f7cZrw2VTpM4r1U6nXVZbFq', '2025-04-28 14:15:39', '2025-05-08 23:32:29', 'client', 'Online', 500, NULL, NULL, '2025-05-01 19:19:35', '2025-05-08 23:32:29', NULL, NULL),
(110, 'Emily Johnson', 'emily.tutor@example.com', '$2y$10$hashedpassword', '2025-04-29 23:35:33', '2025-05-01 19:24:00', 'expert', 'Offline', 1200, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(111, 'Mohamed El Amine', 'mohamed.finance@example.com', '$2y$10$hashedpassword', '2025-04-29 23:35:47', '2025-05-01 19:24:03', 'expert', 'Offline', 4500, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(112, 'Dr. Sarah Benali', 'sarah.therapist@example.com', '$2y$10$hashedpassword', '2025-04-29 23:35:56', '2025-05-01 19:24:08', 'expert', 'Offline', 3800, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(113, 'Karim Boudiaf', 'karim.cyber@example.com', '$2y$10$hashedpassword', '2025-04-29 23:36:06', '2025-04-29 23:36:06', 'expert', 'Online', 5200, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(114, 'Leila Messaoud', 'leila.marketing@example.com', '$2y$10$hashedpassword', '2025-04-29 23:36:14', '2025-04-29 23:36:14', 'expert', 'Online', 2900, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(115, 'Youssef Khemissi', 'youssef.lawyer@example.com', '$2y$10$hashedpassword', '2025-04-29 23:36:22', '2025-04-29 23:36:22', 'expert', 'Offline', 6100, NULL, NULL, '2025-05-01 19:19:35', '2025-05-04 10:39:13', NULL, NULL),
(119, 'Nasro', 'Nasro@gmail.com', '$2y$10$LW0GGERrTYtcRukZfefaMelsl5U9kcqHxg6ThGNETUqWDjRMyuuEC', '2025-05-06 20:58:53', '2025-05-07 23:30:39', 'expert', 'Offline', 0, NULL, NULL, '2025-05-06 20:58:53', '2025-05-07 23:30:39', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `phone`, `address`, `dob`, `gender`, `bio`, `profile_image`, `created_at`, `updated_at`) VALUES
(1, 51, '+213771512322', 'la zone 12', NULL, 'male', 'I&#039;m student licence in informatique', '../uploads/profiles/profile_52_1745403540.jpg', '2025-04-17 15:48:59', '2025-04-25 15:09:07'),
(2, 52, '+213562601765', 'Rue zarouki', NULL, 'male', 'uijola\"\'fzeuiqjemroi', '../uploads/profiles/profile_52_1745403540.jpg', '2025-04-17 15:53:16', '2025-04-23 12:27:54'),
(4, 54, '+213562601769', 'mascara', '2004-04-02', 'male', NULL, '../uploads/profile_images/54_1745610793_pexels-yankrukov-ed.jpg', '2025-04-25 19:53:12', '2025-04-26 11:58:03'),
(5, 60, '+213562601764', 'mascara', '2004-04-09', 'male', NULL, '../uploads/profile_images/60_1745836983_pexels-fauxels-3182831.jpg', '2025-04-28 10:40:57', '2025-04-28 10:43:03'),
(6, 61, '+213562601766', 'mascara', '2004-04-10', 'male', NULL, '../uploads/profile_images/61_1745837106_pexels-cottonbro-4098215.jpg', '2025-04-28 10:45:06', '2025-04-28 10:45:06'),
(7, 62, '+213562601788', 'mascara', '2004-07-14', 'male', NULL, '', '2025-04-28 10:48:34', '2025-04-28 10:48:34'),
(8, 63, '+213562666765', 'mascara', '2004-07-14', 'male', NULL, '../uploads/profile_images/63_1745837584_pexels-yankrukov-ed.jpg', '2025-04-28 10:50:02', '2025-04-28 10:53:04'),
(9, 67, '+213562601799', 'mascara', '2004-09-22', 'male', NULL, '', '2025-04-28 12:45:47', '2025-04-28 12:45:47'),
(10, 68, '+213562601745', 'mascara', '2004-01-01', 'male', NULL, '../uploads/profile_images/68_1745849893_logo.png', '2025-04-28 14:18:13', '2025-04-28 14:18:13'),
(11, 119, '+213549342576', 'mascara', '2004-01-01', 'male', NULL, '../uploads/profile_images/119_1746568079_pexels-fauxels-3182831.jpg', '2025-05-06 21:47:59', '2025-05-06 21:47:59');

-- --------------------------------------------------------

--
-- Structure de la table `user_suspensions`
--

CREATE TABLE `user_suspensions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `reason` text NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `user_suspensions`
--

INSERT INTO `user_suspensions` (`id`, `user_id`, `start_date`, `end_date`, `reason`, `active`, `created_at`) VALUES
(1, 116, '2025-05-01 19:25:28', '2025-05-31 19:25:28', 'Excessive reports', 1, '2025-05-01 17:25:28');

-- --------------------------------------------------------

--
-- Structure de la table `withdrawal_dates`
--

CREATE TABLE `withdrawal_dates` (
  `id` int(11) NOT NULL,
  `day_of_month` int(2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `withdrawal_requests`
--

CREATE TABLE `withdrawal_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `amount_avec_commission` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','processing','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `withdrawal_requests`
--

INSERT INTO `withdrawal_requests` (`id`, `user_id`, `amount`, `amount_avec_commission`, `status`, `notes`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 51, 2444.00, 2199.60, 'completed', 'CSQCD', NULL, '2025-04-25 10:38:52', '2025-04-25 12:01:01'),
(2, 51, 2800.00, 2520.00, 'completed', 'DZDXS', '', '2025-04-25 11:03:03', '2025-04-25 13:38:45'),
(3, 51, 6100.00, 5490.00, 'completed', 'scs', NULL, '2025-04-25 18:41:16', '2025-04-25 18:41:37'),
(4, 51, 7200.00, 6480.00, 'completed', 'YES', 'egfrg', '2025-04-26 12:39:27', '2025-04-26 14:08:21'),
(5, 54, 200.00, 180.00, 'completed', '', '', '2025-04-26 14:35:45', '2025-04-26 14:57:21'),
(6, 54, 9000.00, 8100.00, 'completed', '', '', '2025-04-28 15:22:24', '2025-04-28 15:22:58'),
(7, 54, 2255.00, 2029.50, 'completed', '', '', '2025-04-30 19:26:40', '2025-05-01 18:08:33');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admin_bank_accounts`
--
ALTER TABLE `admin_bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `profile_id` (`profile_id`),
  ADD KEY `idx_notifications_unread` (`is_read`);

--
-- Index pour la table `banking_information`
--
ALTER TABLE `banking_information`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `profile_id` (`profile_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `certificates_ibfk_1` (`profile_id`);

--
-- Index pour la table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expert_id` (`expert_id`),
  ADD KEY `status` (`status`),
  ADD KEY `last_message_at` (`last_message_at`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `chat_session_id` (`chat_session_id`);

--
-- Index pour la table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `expert_id` (`expert_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Index pour la table `chat_timers`
--
ALTER TABLE `chat_timers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_session_id` (`chat_session_id`);

--
-- Index pour la table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `client_notifications`
--
ALTER TABLE `client_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `expert_id` (`expert_id`);

--
-- Index pour la table `consultation_confirmation_listeners`
--
ALTER TABLE `consultation_confirmation_listeners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Index pour la table `experiences`
--
ALTER TABLE `experiences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profile_id` (`profile_id`);

--
-- Index pour la table `expert_approval_comments`
--
ALTER TABLE `expert_approval_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profile_id` (`profile_id`);

--
-- Index pour la table `expert_notifications`
--
ALTER TABLE `expert_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `profile_id` (`profile_id`);

--
-- Index pour la table `expert_profiledetails`
--
ALTER TABLE `expert_profiledetails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_profile_status` (`status`),
  ADD KEY `expert_profiledetails_ibfk_1` (`user_id`);

--
-- Index pour la table `expert_ratings`
--
ALTER TABLE `expert_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expert_id` (`expert_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `consultation_id` (`consultation_id`);

--
-- Index pour la table `expert_rating_likes`
--
ALTER TABLE `expert_rating_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rating_id` (`rating_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `expert_rating_responses`
--
ALTER TABLE `expert_rating_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rating_id` (`rating_id`),
  ADD KEY `expert_id` (`expert_id`);

--
-- Index pour la table `expert_social_links`
--
ALTER TABLE `expert_social_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expert_social_links_user_fk` (`user_id`),
  ADD KEY `expert_social_links_profile_fk` (`profile_id`);

--
-- Index pour la table `formations`
--
ALTER TABLE `formations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profile_id` (`profile_id`);

--
-- Index pour la table `fund_requests`
--
ALTER TABLE `fund_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `idx_payments_expert` (`expert_id`),
  ADD KEY `idx_payments_status` (`status`);

--
-- Index pour la table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reported_id` (`reported_id`);

--
-- Index pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `expert_id` (`expert_id`);

--
-- Index pour la table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Index pour la table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profile_id` (`profile_id`);

--
-- Index pour la table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Index pour la table `support_attachments`
--
ALTER TABLE `support_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Index pour la table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_support_consultation` (`consultation_id`);

--
-- Index pour la table `support_message_replies`
--
ALTER TABLE `support_message_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `support_responses`
--
ALTER TABLE `support_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `user_suspensions`
--
ALTER TABLE `user_suspensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `withdrawal_dates`
--
ALTER TABLE `withdrawal_dates`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_withdrawal_requests_user` (`user_id`),
  ADD KEY `idx_withdrawal_requests_status` (`status`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admin_bank_accounts`
--
ALTER TABLE `admin_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT pour la table `banking_information`
--
ALTER TABLE `banking_information`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT pour la table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT pour la table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=336;

--
-- AUTO_INCREMENT pour la table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT pour la table `chat_timers`
--
ALTER TABLE `chat_timers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT pour la table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT pour la table `client_notifications`
--
ALTER TABLE `client_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=425;

--
-- AUTO_INCREMENT pour la table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=235;

--
-- AUTO_INCREMENT pour la table `consultation_confirmation_listeners`
--
ALTER TABLE `consultation_confirmation_listeners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT pour la table `experiences`
--
ALTER TABLE `experiences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `expert_approval_comments`
--
ALTER TABLE `expert_approval_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `expert_notifications`
--
ALTER TABLE `expert_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=466;

--
-- AUTO_INCREMENT pour la table `expert_profiledetails`
--
ALTER TABLE `expert_profiledetails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT pour la table `expert_ratings`
--
ALTER TABLE `expert_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `expert_rating_likes`
--
ALTER TABLE `expert_rating_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `expert_rating_responses`
--
ALTER TABLE `expert_rating_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `expert_social_links`
--
ALTER TABLE `expert_social_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `fund_requests`
--
ALTER TABLE `fund_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=187;

--
-- AUTO_INCREMENT pour la table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `skills`
--
ALTER TABLE `skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT pour la table `support_attachments`
--
ALTER TABLE `support_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `support_message_replies`
--
ALTER TABLE `support_message_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `support_responses`
--
ALTER TABLE `support_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT pour la table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `user_suspensions`
--
ALTER TABLE `user_suspensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `withdrawal_dates`
--
ALTER TABLE `withdrawal_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD CONSTRAINT `chat_conversations_expert_fk` FOREIGN KEY (`expert_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_conversations_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_3` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `chat_timers`
--
ALTER TABLE `chat_timers`
  ADD CONSTRAINT `chat_timers_ibfk_1` FOREIGN KEY (`chat_session_id`) REFERENCES `chat_sessions` (`id`);

--
-- Contraintes pour la table `client_notifications`
--
ALTER TABLE `client_notifications`
  ADD CONSTRAINT `client_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`expert_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `consultation_confirmation_listeners`
--
ALTER TABLE `consultation_confirmation_listeners`
  ADD CONSTRAINT `consultation_confirmation_listeners_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultation_confirmation_listeners_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `expert_notifications`
--
ALTER TABLE `expert_notifications`
  ADD CONSTRAINT `expert_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expert_notifications_ibfk_2` FOREIGN KEY (`profile_id`) REFERENCES `expert_profiledetails` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `fund_requests`
--
ALTER TABLE `fund_requests`
  ADD CONSTRAINT `fund_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `support_attachments`
--
ALTER TABLE `support_attachments`
  ADD CONSTRAINT `support_attachments_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `support_messages` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `fk_support_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
