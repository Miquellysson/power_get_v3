-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 04/11/2025 às 02:31
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u100060033_get_power_app`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image_path`, `active`, `sort_order`, `created_at`) VALUES
(10, 'TIRZ', 'tirz', NULL, NULL, 1, 1, '2025-11-02 08:49:32'),
(11, 'RETA', 'reta', NULL, NULL, 1, 0, '2025-11-02 08:49:37'),
(12, 'VITAMINA', 'vitamina', NULL, NULL, 1, 0, '2025-11-02 08:49:43'),
(13, 'SUPLEMENTO', 'suplemento', NULL, NULL, 1, 0, '2025-11-02 08:49:51');

-- --------------------------------------------------------

--
-- Estrutura para tabela `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(190) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'BR',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `city`, `state`, `zipcode`, `country`, `created_at`) VALUES
(1, 'MIke lins', 'mike@Mmlins.com', '38383838383', 'massashuts', '', '', '57100', 'BR', '2025-11-02 09:07:26'),
(2, 'Larissa', 'larissa_carvalho_20@hotmail.com', '8572870826', '350 highland Avenue apt32', '', '', '02148', 'BR', '2025-11-02 13:57:33'),
(3, 'Edson Jeronimo filho', 'edson.montec@gmail.com', '011990121998', 'Rua Maria de Jesus Medeiros Quitéria n⁰15', '', '', '09371-078', 'BR', '2025-11-02 14:46:29'),
(4, 'Mario Lana', 'mariolana78@hotmail.com', '9783323492', '13 Prospect hts, Milford MA', '', '', '01757', 'BR', '2025-11-02 18:14:03'),
(5, 'Cristiano pereira', 'juliaribeiroamorim@icloud.com', '9785967625', '136 chapel st', '', '', '01852', 'BR', '2025-11-02 21:30:41'),
(6, 'Galba carvalho', 'galbacarvalho1972@icloud.com', '5083958623', '49 pond st Natick Ma', '', '', '01760', 'BR', '2025-11-03 12:31:57'),
(7, 'Galba carvalho', 'galbacarvalho1972@icloud.com', '5083958623', '49 pond st Natick Ma', '', '', '01760', 'BR', '2025-11-03 12:35:23'),
(8, 'marcos', 'markim2025@gmail.com', '5615066204', '2273 deer creek alba way', '', '', '33442', 'BR', '2025-11-04 00:38:33'),
(9, 'Ana bianchi', 'anabianchi222@gmail.com', '7744056129', '165 ames st , Marlborough MA 01752 , unit 3009', '', '', '01752', 'BR', '2025-11-04 00:41:40'),
(10, 'Peter Parker', 'john.greeeeeen@gmail.com', '559551583801', 'R. Brg. Tobias, 527. SP Brazil', '', '', '01032-001', 'BR', '2025-11-04 02:26:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `data`, `is_read`, `created_at`) VALUES
(1, 'cart_add', 'Produto ao carrinho', 'Mix Shot Burner -10ml', '{\"product_id\":19}', 0, '2025-11-02 09:02:43'),
(2, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 09:07:05'),
(3, 'new_order', 'Novo Pedido', 'Pedido #1 de MIke lins', '{\"order_id\":1,\"total\":265,\"payment_method\":\"square\"}', 0, '2025-11-02 09:07:26'),
(4, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 10:18:57'),
(5, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 10:42:09'),
(6, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 12:35:13'),
(7, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 13:16:50'),
(8, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 13:16:51'),
(9, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 13:55:18'),
(10, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 13:55:23'),
(11, 'new_order', 'Novo Pedido', 'Pedido #2 de Larissa', '{\"order_id\":2,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-02 13:57:33'),
(12, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-02 13:59:41'),
(13, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 14:00:47'),
(14, 'cart_add', 'Produto ao carrinho', 'RETA 15 – Retatrutide 15mg', '{\"product_id\":22}', 0, '2025-11-02 14:41:55'),
(15, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 14:42:04'),
(16, 'new_order', 'Novo Pedido', 'Pedido #3 de Edson Jeronimo filho', '{\"order_id\":3,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-02 14:46:29'),
(17, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-02 14:51:23'),
(18, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-02 14:51:31'),
(19, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-02 14:52:38'),
(20, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-02 14:52:54'),
(21, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 14:55:12'),
(22, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 15:26:01'),
(23, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 15:27:04'),
(24, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-02 15:30:11'),
(25, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-02 15:30:18'),
(26, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 15:45:31'),
(27, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 17:06:48'),
(28, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 17:16:40'),
(29, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 17:51:29'),
(30, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 17:51:32'),
(31, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 18:08:24'),
(32, 'new_order', 'Novo Pedido', 'Pedido #4 de Mario Lana', '{\"order_id\":4,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-02 18:14:03'),
(33, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 20:45:13'),
(34, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-02 20:47:28'),
(35, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:16'),
(36, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:18'),
(37, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:19'),
(38, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:20'),
(39, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:21'),
(40, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:21'),
(41, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:22'),
(42, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:22'),
(43, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:28:23'),
(44, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-02 21:28:37'),
(45, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-02 21:30:12'),
(46, 'new_order', 'Novo Pedido', 'Pedido #5 de Cristiano pereira', '{\"order_id\":5,\"total\":295,\"payment_method\":\"square\"}', 0, '2025-11-02 21:30:41'),
(47, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-02 21:33:02'),
(48, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-02 22:50:23'),
(49, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-02 23:17:06'),
(50, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 00:30:50'),
(51, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 01:28:57'),
(52, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 10:07:38'),
(53, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 11:59:09'),
(54, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 11:59:18'),
(55, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 11:59:19'),
(56, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 11:59:20'),
(57, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-03 12:30:07'),
(58, 'new_order', 'Novo Pedido', 'Pedido #6 de Galba carvalho', '{\"order_id\":6,\"total\":295,\"payment_method\":\"square\"}', 0, '2025-11-03 12:31:57'),
(59, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-03 12:34:05'),
(60, 'new_order', 'Novo Pedido', 'Pedido #7 de Galba carvalho', '{\"order_id\":7,\"total\":295,\"payment_method\":\"square\"}', 0, '2025-11-03 12:35:23'),
(61, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 15:46:06'),
(62, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-03 16:42:59'),
(63, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:00:44'),
(64, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 17:13:36'),
(65, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 17:13:37'),
(66, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 17:13:38'),
(67, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 17:13:39'),
(68, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 17:13:40'),
(69, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 17:13:41'),
(70, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-03 17:14:31'),
(71, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 17:52:58'),
(72, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:53:23'),
(73, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:53:25'),
(74, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:53:26'),
(75, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:53:26'),
(76, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:53:27'),
(77, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:53:28'),
(78, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:53:29'),
(79, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 17:53:29'),
(80, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 19:00:21'),
(81, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-03 19:14:50'),
(82, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 19:38:45'),
(83, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 20:14:20'),
(84, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-03 20:14:22'),
(85, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-03 20:19:11'),
(86, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 20:39:52'),
(87, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-03 20:40:19'),
(88, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-03 20:40:20'),
(89, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-03 20:40:34'),
(90, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-03 21:00:35'),
(91, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-03 21:00:37'),
(92, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-03 21:04:38'),
(93, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-03 21:10:37'),
(94, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 00:36:44'),
(95, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-04 00:37:13'),
(96, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-04 00:37:14'),
(97, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 00:37:24'),
(98, 'new_order', 'Novo Pedido', 'Pedido #8 de marcos', '{\"order_id\":8,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-04 00:38:33'),
(99, 'new_order', 'Novo Pedido', 'Pedido #9 de Ana bianchi', '{\"order_id\":9,\"total\":515,\"payment_method\":\"zelle\"}', 0, '2025-11-04 00:41:40'),
(100, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-04 01:05:52'),
(101, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":15}', 0, '2025-11-04 02:06:02'),
(102, 'new_order', 'Novo Pedido', 'Pedido #10 de Peter Parker', '{\"order_id\":10,\"total\":275,\"payment_method\":\"square\"}', 0, '2025-11-04 02:26:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `items_json` longtext NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `payment_method` varchar(40) NOT NULL,
  `payment_ref` text DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT 'pending',
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `track_token` varchar(64) DEFAULT NULL,
  `zelle_receipt` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_viewed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `items_json`, `subtotal`, `shipping_cost`, `total`, `currency`, `payment_method`, `payment_ref`, `payment_status`, `status`, `track_token`, `zelle_receipt`, `notes`, `admin_viewed`, `created_at`, `updated_at`) VALUES
(1, 1, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/eWMKPWit\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 15.00, 265.00, 'USD', 'square', 'https://square.link/u/eWMKPWit', 'pending', 'pending', '9db37ae5b6ca3c2e228d71de0b91c427', NULL, NULL, 0, '2025-11-02 09:07:26', '2025-11-02 09:07:26'),
(2, 2, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/eWMKPWit\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/eWMKPWit', 'pending', 'pending', 'fed27261c159c724067bebdcb0f06a72', NULL, NULL, 0, '2025-11-02 13:57:33', '2025-11-02 13:57:33'),
(3, 3, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/eWMKPWit\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/eWMKPWit', 'pending', 'pending', '734ca4beefbcfd6debb6ed8fe3176f49', NULL, NULL, 0, '2025-11-02 14:46:29', '2025-11-02 14:46:29'),
(4, 4, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'paid', 'a656b769dae180f9a0aedc305061d700', NULL, NULL, 0, '2025-11-02 18:14:03', '2025-11-03 16:25:45'),
(5, 5, '[{\"id\":15,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":280,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 280.00, 15.00, 295.00, 'USD', 'square', 'https://square.link/u/ItAGrpHg', 'pending', 'paid', 'a947926aede43dc94c326d04ac0bb482', NULL, NULL, 0, '2025-11-02 21:30:41', '2025-11-03 16:25:30'),
(6, 6, '[{\"id\":15,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":280,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 280.00, 15.00, 295.00, 'USD', 'square', 'https://square.link/u/ItAGrpHg', 'pending', 'pending', 'aab8d2c4ea3ac666681204dafb7f9e82', NULL, NULL, 0, '2025-11-03 12:31:57', '2025-11-03 12:31:57'),
(7, 7, '[{\"id\":15,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":280,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 280.00, 15.00, 295.00, 'USD', 'square', 'https://square.link/u/ItAGrpHg', 'pending', 'paid', '830c52e178d3b07ecf27c46bb7699a0d', NULL, NULL, 0, '2025-11-03 12:35:23', '2025-11-03 16:25:24'),
(8, 8, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'pending', 'ab930f671e179b6371fbe495cc490640', NULL, NULL, 0, '2025-11-04 00:38:33', '2025-11-04 00:38:33'),
(9, 9, '[{\"id\":17,\"name\":\"TIRZ 60 – Tirzepatide 60mg\",\"price\":500,\"qty\":1,\"sku\":\"TIRZ-005\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/A69ku30z\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 500.00, 15.00, 515.00, 'USD', 'zelle', '', 'pending', 'pending', 'b853b38dba53aefa1e1b94132da5b639', 'storage/zelle_receipts/receipt_20251104_004140_8600c337.png', NULL, 0, '2025-11-04 00:41:40', '2025-11-04 00:41:40'),
(10, 10, '[{\"id\":15,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":260,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 260.00, 15.00, 275.00, 'USD', 'square', 'https://square.link/u/ItAGrpHg', 'pending', 'pending', '7491f04791a21249d38b96247af0f84a', NULL, NULL, 0, '2025-11-04 02:26:06', '2025-11-04 02:26:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `page_layouts`
--

CREATE TABLE `page_layouts` (
  `id` int(10) UNSIGNED NOT NULL,
  `page_slug` varchar(100) NOT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `content` longtext DEFAULT NULL,
  `styles` longtext DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_request` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `instructions` longtext DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `icon_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `require_receipt` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `code`, `name`, `description`, `instructions`, `settings`, `icon_path`, `is_active`, `require_receipt`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'pix', 'Pix', NULL, 'Use o Pix para pagar seu pedido. Valor: {valor_pedido}.\\nChave: {pix_key}', '{\"type\":\"pix\",\"account_label\":\"Chave Pix\",\"account_value\":\"\",\"pix_key\":\"\",\"merchant_name\":\"\",\"merchant_city\":\"\",\"currency\":\"BRL\"}', NULL, 0, 0, 10, '2025-11-02 08:37:31', '2025-11-02 09:00:46'),
(2, 'zelle', 'Zelle', '', '💰 Zelle\r\nZelle: (978) 901-4603 ou 978 901 4603\r\nNome: Get Power ( Renato Azevedo)\r\n\r\n📌\r\nObs: Coloque APENAS O NÚMERO DO PEDIDO\r\nna mensagem do Zelle.\r\n\r\nApós o pagamento, você pode enviar um e-mail com o comprovante de pagamento juntamente APENAS O NÚMERO DO PEDIDO\r\n\r\n📧 para o e-mail: getpowerresearch9@gmail.com\r\n\r\n⚠️ ATENÇÃO SOBRE SUA MENSAGEM DE PAGAMENTO\r\n\r\nOlá! Segue as regras abaixo para que seu pedido não seja cancelado:\r\n\r\nSempre escreva apenas o número do seu pedido no campo de mensagem.\r\n\r\nEXEMPLO:\r\nAPENAS O NÚMERO DO PEDIDO\r\n\r\nLAST FOUR DIGITS: 4603\r\n\r\n📧 para o e-mail: getpowerresearch9@gmail.com\r\n\r\n📌⚠️ Mensagens contendo nomes de medicamentos, nomes de pessoas ou qualquer outro texto fora o número do pedido causarão cancelamento automático da próxima compra, por motivos de segurança bancária e contábil.\r\n\r\n📌⚠️ Mensagens em branco ou com emojis também não são aceitas e podem atrasar o envio da sua compra.\r\n\r\nEsse procedimento é essencial para mantermos nosso sistema de envios seguro, organizado e funcionando corretamente.\r\n\r\nAgradecemos a sua atenção e compreensão.\r\n\r\n📌 ATENÇÃO!\r\n\r\n⏳💰 Você tem até 3 dias para efetuar o pagamento.\r\nApós os 3 dias, se o pagamento não for realizado, seu pedido será cancelado.\r\n\r\nEnvie o valor de {valor_pedido} via Zelle para {account_value}. Anexe o comprovante se solicitado.', '{\"type\":\"zelle\",\"account_label\":\"9789014603\",\"account_value\":\"\",\"button_bg\":\"#dc2626\",\"button_text\":\"#ffffff\",\"button_hover_bg\":\"#b91c1c\",\"recipient_name\":\"Get Power ( Renato Azevedo)\"}', NULL, 1, 1, 20, '2025-11-02 08:37:31', '2025-11-02 09:02:29'),
(3, 'venmo', 'Venmo', NULL, 'Pague {valor_pedido} via Venmo. Link: {venmo_link}.', '{\"type\":\"venmo\",\"account_label\":\"Link Venmo\",\"venmo_link\":\"\"}', NULL, 0, 1, 30, '2025-11-02 08:37:31', '2025-11-02 09:01:27'),
(4, 'paypal', 'PayPal', NULL, 'Após finalizar, você será direcionado ao PayPal com o valor {valor_pedido}.', '{\"type\":\"paypal\",\"business\":\"\",\"account_value\":\"\",\"currency\":\"USD\",\"return_url\":\"\",\"cancel_url\":\"\"}', NULL, 0, 0, 40, '2025-11-02 08:37:31', '2025-11-02 09:01:30'),
(5, 'square', 'Cartão de crédito', NULL, 'Abriremos o checkout de cartão de crédito em uma nova aba para concluir o pagamento.', '{\"type\":\"square\",\"mode\":\"square_product_link\",\"open_new_tab\":true}', NULL, 1, 0, 50, '2025-11-02 08:37:31', '2025-11-02 08:37:31'),
(6, 'whatsapp', 'WhatsApp', NULL, 'Finalize seu pedido conversando com nossa equipe pelo WhatsApp: {whatsapp_link}.', '{\"type\":\"whatsapp\",\"number\":\"\",\"message\":\"Olá! Gostaria de finalizar meu pedido.\",\"link\":\"\"}', NULL, 0, 0, 60, '2025-11-02 08:37:31', '2025-11-02 09:01:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `price_compare` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 7.00,
  `stock` int(11) NOT NULL DEFAULT 100,
  `image_path` varchar(255) DEFAULT NULL,
  `square_payment_link` varchar(255) DEFAULT NULL,
  `square_credit_link` varchar(255) DEFAULT NULL,
  `square_debit_link` varchar(255) DEFAULT NULL,
  `square_afterpay_link` varchar(255) DEFAULT NULL,
  `stripe_payment_link` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `price_compare`, `currency`, `shipping_cost`, `stock`, `image_path`, `square_payment_link`, `square_credit_link`, `square_debit_link`, `square_afterpay_link`, `stripe_payment_link`, `active`, `featured`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(13, 11, 'TIRZ-001', 'TIRZ 20 – Tirzepatide 20mg', 'Tirzepatide 20mg para tratamento', 250.00, 320.00, 'USD', 0.00, 100, 'storage/products/p_1762073921_3f61c2.png', 'https://square.link/u/KvS3pgmm', 'https://square.link/u/KvS3pgmm', 'https://square.link/u/KvS3pgmm', 'https://square.link/u/KvS3pgmm', '', 1, 1, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 15:45:09'),
(14, 10, 'TIRZ-002', 'TIRZ 30 – Tirzepatide 30mg', 'Tirzepatide 30mg para tratamento', 350.00, 370.00, 'USD', 15.00, 100, 'storage/products/p_1762073870_936732.png', 'https://square.link/u/l9KQMPhT', 'https://square.link/u/l9KQMPhT', 'https://square.link/u/l9KQMPhT', 'https://square.link/u/l9KQMPhT', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-03 16:35:43'),
(15, 11, 'TIRZ-003', 'TIRZ 15 – Tirzepatide 15mg', 'Tirzepatide 15mg para tratamento', 260.00, 280.00, 'USD', 15.00, 100, 'storage/products/p_1762073834_64ae8c.png', 'https://square.link/u/ItAGrpHg', 'https://square.link/u/ItAGrpHg', 'https://square.link/u/ItAGrpHg', 'https://square.link/u/ItAGrpHg', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-03 16:36:41'),
(16, 11, 'TIRZ-004', 'TIRZ 40 – Tirzepatide 40mg', 'Tirzepatide 40mg para tratamento', 400.00, 420.00, 'USD', 15.00, 100, 'storage/products/p_1762073806_8abbb6.png', 'https://square.link/u/cilVU3dw', 'https://square.link/u/cilVU3dw', 'https://square.link/u/cilVU3dw', 'https://square.link/u/cilVU3dw', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-03 16:35:27'),
(17, 11, 'TIRZ-005', 'TIRZ 60 – Tirzepatide 60mg', 'Tirzepatide 60mg para tratamento', 500.00, 520.00, 'USD', 15.00, 100, 'storage/products/p_1762073774_8d23ea.png', 'https://square.link/u/A69ku30z', 'https://square.link/u/A69ku30z', 'https://square.link/u/A69ku30z', 'https://square.link/u/A69ku30z', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-03 16:34:52'),
(18, 11, 'VIT-001', 'Vitamina B12 0.5MG / 2ML', 'Vitamina B12 injetável', 15.00, 15.00, 'USD', 15.00, 100, 'storage/products/p_1762073745_80da59.png', '', '', '', '', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 09:04:55'),
(19, 13, 'MIX-001', 'Mix Shot Burner -10ml', 'Mix Shot Burner queimador de gordura', 160.00, 160.00, 'USD', 15.00, 100, 'storage/products/p_1762073730_32d3fe.png', '', '', '', '', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 09:05:04'),
(20, 12, 'VIT-003', 'Vitamina D - 10mg - 2000 ui', 'Vitamina D 2000 UI', 30.00, 30.00, 'USD', 15.00, 100, 'storage/products/p_1762073714_e7d76c.png', '', '', '', '', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 09:04:01'),
(21, 10, 'TIRZ-006', 'TIRZ 50 – Tirzepatide 50mg', 'Tirzepatide 50mg para tratamento', 450.00, 470.00, 'USD', 15.00, 100, 'storage/products/p_1761944996_aaa247.png', 'https://square.link/u/DJIjLR9j', 'https://square.link/u/DJIjLR9j', 'https://square.link/u/DJIjLR9j', 'https://square.link/u/DJIjLR9j', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-03 16:34:32'),
(22, 11, 'RETA-001', 'RETA 15 – Retatrutide 15mg', 'Retatrutide 15mg para tratamento', 320.00, 320.00, 'USD', 15.00, 100, 'storage/products/p_1762073658_3e0418.png', 'https://square.link/u/6ljvNcEz', 'https://square.link/u/6ljvNcEz', 'https://square.link/u/6ljvNcEz', 'https://square.link/u/6ljvNcEz', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 08:54:18'),
(23, 11, 'RETA-002', 'RETA 20 – Retatrutide 20mg', 'Retatrutide 20mg para tratamento', 370.00, 370.00, 'USD', 15.00, 100, 'storage/products/p_1762073636_be6f44.png', 'https://square.link/u/pLTeZbVf', 'https://square.link/u/pLTeZbVf', 'https://square.link/u/pLTeZbVf', 'https://square.link/u/pLTeZbVf', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 08:53:56'),
(24, 11, 'RETA-003', 'RETA 30 – Retatrutide 30mg', 'Retatrutide 30mg para tratamento', 420.00, 420.00, 'USD', 15.00, 100, 'storage/products/p_1762073605_2dfd20.png', 'https://square.link/u/b7X2o6jP', 'https://square.link/u/b7X2o6jP', 'https://square.link/u/b7X2o6jP', 'https://square.link/u/b7X2o6jP', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 08:53:25'),
(25, 11, 'RETA-004', 'RETA 40 – Retatrutide 40mg', 'Retatrutide 40mg para tratamento', 470.00, 470.00, 'USD', 15.00, 100, 'storage/products/p_1762073567_530fc1.png', 'https://square.link/u/kFzMe83r', 'https://square.link/u/kFzMe83r', 'https://square.link/u/kFzMe83r', 'https://square.link/u/kFzMe83r', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 08:52:47'),
(26, 11, 'RETA-005', 'RETA 50 – Retatrutide 50mg', 'Retatrutide 50mg para tratamento', 520.00, 520.00, 'USD', 15.00, 100, 'storage/products/p_1762073536_a988af.png', 'https://square.link/u/iu5LpABj', 'https://square.link/u/iu5LpABj', 'https://square.link/u/iu5LpABj', 'https://square.link/u/iu5LpABj', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 08:52:16'),
(27, 12, 'STER-001', 'Sterile Water', 'Água estéril para diluição', 10.00, 10.00, 'USD', 15.00, 100, 'storage/products/p_1762073484_70790d.png', '', 'https://square.link/u/eWMKPWit', '', '', '', 1, 0, NULL, NULL, '2025-11-02 08:48:03', '2025-11-02 09:03:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `skey` varchar(191) NOT NULL,
  `svalue` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `settings`
--

INSERT INTO `settings` (`id`, `skey`, `svalue`, `updated_at`) VALUES
(1, 'store_name', 'Get Power Research', '2025-11-02 13:32:16'),
(2, 'store_email', 'getpowerresearch9@gmail.com', '2025-11-02 13:32:16'),
(3, 'store_phone', '+1 (978) 901 4603', '2025-11-02 13:32:16'),
(4, 'store_address', 'Massachusetts – USA', '2025-11-02 13:32:16'),
(5, 'store_meta_title', 'Get Power Research', '2025-11-02 13:32:16'),
(6, 'home_hero_title', 'Tudo para sua saúde', '2025-11-02 13:32:16'),
(7, 'home_hero_subtitle', 'Experiência de app, rápida e segura.', '2025-11-02 13:32:16'),
(8, 'header_subline', 'Tudo para sua saúde', '2025-11-02 13:32:16'),
(9, 'footer_title', 'Get Power Research', '2025-11-02 13:32:16'),
(10, 'footer_description', 'Tudo para sua saúde online com experiência de app.', '2025-11-02 13:32:16'),
(11, 'footer_copy', '© {{year}} Get Power Research. Todos os direitos reservados.', '2025-11-02 13:32:16'),
(12, 'theme_color', '#2060C8', '2025-11-02 13:32:16'),
(13, 'home_featured_label', 'Oferta destaque', '2025-11-02 13:32:16'),
(14, 'whatsapp_button_text', 'Fale com a gente', '2025-11-02 13:32:16'),
(15, 'whatsapp_message', 'Olá! Gostaria de tirar uma dúvida sobre os produtos.', '2025-11-02 13:32:16'),
(16, 'store_currency', 'USD', '2025-11-02 08:37:31'),
(17, 'pwa_name', 'Get Power Research', '2025-11-02 13:32:16'),
(18, 'pwa_short_name', 'Get Power Research', '2025-11-02 13:32:16'),
(19, 'home_featured_enabled', '1', '2025-11-02 13:32:16'),
(20, 'home_featured_title', 'Ofertas em destaque', '2025-11-02 13:32:16'),
(21, 'home_featured_subtitle', 'Seleção especial com preços imperdíveis. + FRETE GRÁTIS', '2025-11-02 13:32:16'),
(22, 'home_featured_badge_title', 'Seleção especial', '2025-11-02 13:32:16'),
(23, 'home_featured_badge_text', 'Selecionados com carinho para você', '2025-11-02 13:32:16'),
(24, 'email_customer_subject', 'Seu pedido {{order_id}} foi recebido - Get Power Research', '2025-11-02 13:32:16'),
(25, 'email_customer_body', '<p>Olá {{customer_name}},</p>\r\n<p>Recebemos seu pedido <strong>#{{order_id}}</strong> na {{store_name}}.</p>\r\n<p><strong>Resumo do pedido:</strong></p>\r\n{{order_items}}\r\n<p><strong>Subtotal:</strong> {{order_subtotal}}<br>\r\n<strong>Frete:</strong> {{order_shipping}}<br>\r\n<strong>Total:</strong> {{order_total}}</p>\r\n<p>Forma de pagamento: {{payment_method}}</p>\r\n<p>Status e atualização: {{track_link}}</p>\r\n<p>Qualquer dúvida, responda este e-mail ou fale com a gente em {{support_email}}.</p>\r\n<p>Equipe {{store_name}}</p>', '2025-11-02 13:32:16'),
(26, 'email_admin_subject', 'Novo pedido #{{order_id}} - Get Power Research', '2025-11-02 13:32:16'),
(27, 'email_admin_body', '<h2>Novo pedido recebido</h2>\r\n<p><strong>Loja:</strong> {{store_name}}</p>\r\n<p><strong>Pedido:</strong> #{{order_id}}</p>\r\n<p><strong>Cliente:</strong> {{customer_name}} &lt;{{customer_email}}&gt; — {{customer_phone}}</p>\r\n<p><strong>Total:</strong> {{order_total}} &nbsp;|&nbsp; <strong>Pagamento:</strong> {{payment_method}}</p>\r\n{{order_items}}\r\n<p><strong>Endereço:</strong><br>{{shipping_address}}</p>\r\n<p><strong>Observações:</strong> {{order_notes}}</p>\r\n<p>Painel: <a href=\"{{admin_order_url}}\">{{admin_order_url}}</a></p>', '2025-11-02 13:32:16'),
(48, 'whatsapp_enabled', '1', '2025-11-02 13:32:16'),
(49, 'whatsapp_number', '19789014603', '2025-11-02 13:32:16'),
(63, 'store_logo', 'storage/logo/logo.png', '2025-11-02 13:32:16'),
(64, 'store_logo_url', 'storage/logo/logo.png', '2025-11-02 13:32:16'),
(83, 'pwa_icon_last_update', '1762090336', '2025-11-02 13:32:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `pass`, `role`, `active`, `created_at`) VALUES
(1, 'Mike Lins', 'mike@mmlins.com.br', '$2y$10$5wzwoXp9F7VZpWkXZia1Me3qXdBpf4abgcKEzp6pytAjtkTmsFTM2', 'super_admin', 1, '2025-11-02 08:37:31'),
(2, 'Mike teste', 'ml@mmlins.com.br', '$2y$10$/42zdnBM8YBZ.uu9P3qILO7iQCZUxTjyQx/xgBcI3ix/9m15xsTfC', 'viewer', 1, '2025-11-02 11:04:48');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Índices de tabela `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Índices de tabela `page_layouts`
--
ALTER TABLE `page_layouts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_layout_slug_status` (`page_slug`,`status`);

--
-- Índices de tabela `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token_hash`),
  ADD KEY `idx_user` (`user_id`);

--
-- Índices de tabela `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_payment_code` (`code`);

--
-- Índices de tabela `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Índices de tabela `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_settings_skey` (`skey`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT de tabela `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `page_layouts`
--
ALTER TABLE `page_layouts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
