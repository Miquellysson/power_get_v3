-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 05/11/2025 às 21:16
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image_path`, `active`, `sort_order`, `created_at`) VALUES
(10, 'TIRZ', 'tirz', NULL, NULL, 1, 1, '2025-11-02 08:49:32'),
(11, 'RETA', 'reta', NULL, NULL, 1, 0, '2025-11-02 08:49:37'),
(12, 'VITAMINA', 'vitamina', NULL, NULL, 1, 0, '2025-11-02 08:49:43'),
(13, 'SUPLEMENTO', 'suplemento', NULL, NULL, 1, 0, '2025-11-02 08:49:51'),
(14, 'Analgésicos', 'analgesicos', 'Medicamentos para alívio de dores e febres', NULL, 1, 0, '2025-11-05 15:29:02'),
(15, 'Antibióticos', 'antibioticos', 'Medicamentos para combate a infecções', NULL, 1, 0, '2025-11-05 15:29:02'),
(16, 'Anti-inflamatórios', 'anti-inflamatorios', 'Medicamentos para redução de inflamações', NULL, 1, 0, '2025-11-05 15:29:02'),
(17, 'Suplementos', 'suplementos', 'Vitaminas e suplementos alimentares', NULL, 1, 0, '2025-11-05 15:29:02'),
(18, 'Digestivos', 'digestivos', 'Medicamentos para problemas digestivos', NULL, 1, 0, '2025-11-05 15:29:02'),
(19, 'Cardiovasculares', 'cardiovasculares', 'Medicamentos para coração e pressão', NULL, 1, 0, '2025-11-05 15:29:02'),
(20, 'Respiratórios', 'respiratorios', 'Medicamentos para problemas respiratórios', NULL, 1, 0, '2025-11-05 15:29:02'),
(21, 'Dermatológicos', 'dermatologicos', 'Medicamentos para pele', NULL, 1, 0, '2025-11-05 15:29:02'),
(22, 'Anticoncepcionais', 'anticoncepcionais', 'Medicamentos anticoncepcionais', NULL, 1, 0, '2025-11-05 15:29:02');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
(10, 'Peter Parker', 'john.greeeeeen@gmail.com', '559551583801', 'R. Brg. Tobias, 527. SP Brazil', '', '', '01032-001', 'BR', '2025-11-04 02:26:06'),
(11, 'teste', 'mike@mmlins.com.br', '82996669740', '03 Travessa intendente Julio Calheiros', '', '', '57100-000', 'BR', '2025-11-04 13:07:58'),
(12, 'teste2mike', 'mike@mmlins.com.br', '82996669740', '03 Travessa intendente Julio Calheiros', '', '', '57100-000', 'BR', '2025-11-04 13:12:34'),
(13, 'miketeste23', 'mike@mmlins.com.br', '82996669740', '03 Travessa intendente Julio Calheiros', '', '', '57100-000', 'BR', '2025-11-04 13:15:19'),
(14, 'Ronielly O Silva', 'roniellyosilva@gmail.com', '561 6744746', '147 N Hampton Dr', '', '', '33897', 'BR', '2025-11-04 14:23:04'),
(15, 'Ronielly O Silva', 'roniellyosilva@gmail.com', '561 674 4746', '147 N Hampton Dr', '', '', '33897', 'BR', '2025-11-04 14:25:02'),
(16, 'Welisson Dougras de Melo', 'welissondougras78@gmail.com', '7743600357', '969 W Main Rd Apt: 4402', '', '', '02842', 'BR', '2025-11-04 17:17:10'),
(17, 'Nuliandra', 'nulialves19@gmail.com', '9785695781', '42 union st lowell', '', '', '01852', 'BR', '2025-11-04 23:41:27'),
(18, 'Fabiana', 'fahmartins31@gmail.com', '9788968994', '9 Emerson st Peabody', '', '', '01960', 'BR', '2025-11-05 00:35:30'),
(19, 'Bruno Siqueira Lage', 'brunoslage13@gmail.com', '7743861304', '20 florence st Milford Massachusetts', '', '', '01757', 'BR', '2025-11-05 01:25:57'),
(20, 'Rodrigo F Silva', 'xrodrigof@hotmail.com', '7743223638', '8 Maple st  apt 5', '', '', '01756', 'BR', '2025-11-05 02:36:52'),
(21, 'Renato', 'renatocesarazevedo@hotmail.com', '8575045859', '3 valley view way', '', '', '01464', 'BR', '2025-11-05 07:38:32'),
(22, 'Michele Araújo', 'micheledearaujo29@yahoo.com.br', '8135851024', '12309 Hawthorne view court', '', '', '33626', 'BR', '2025-11-05 15:55:55'),
(23, 'Thiago', 'galvaobthiago@gmail.com', '3132963164', '19756 Haggerty rd, apt 137, Livonia, MI', '', '', '48152', 'BR', '2025-11-05 16:50:50'),
(24, 'SIMY TOBELEM SILVA', 'simytobelem@yahoo.com', '5619299618', '652 Pisa pass, bldg 45', '', '', '33897', 'BR', '2025-11-05 19:11:33'),
(25, 'SIMY TOBELEM SILVA', 'SIMYTOBELEM@YAHOO.COM', '5619299618', '652 PISA PASS\r\nbldg 45', '', '', '33897', 'BR', '2025-11-05 19:14:25'),
(26, 'Evelyn Camargo', 'evelyn.camargo95@hotmail.com', '9098198684', '9079 whirlaway ct rancho cucamonga, ca', '', '', '91737', 'BR', '2025-11-05 19:32:53'),
(27, 'Ana Maria  Borges Coelho', 'anamaria.gomesborges@gmail.com', '+1(978)3936700', '7 Esquire Circle #3\r\nPeabody  MA 01960\r\nEstados Unidos', '', '', '01960', 'BR', '2025-11-05 19:34:00'),
(28, 'Evelyn Camargo', 'evelyn.camargo95@hotmail.com', '9098198684', '9079 whirlaway ct rancho cucamonga, ca 91737', '', '', '91737', 'BR', '2025-11-05 19:35:41');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
(102, 'new_order', 'Novo Pedido', 'Pedido #10 de Peter Parker', '{\"order_id\":10,\"total\":275,\"payment_method\":\"square\"}', 0, '2025-11-04 02:26:06'),
(103, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 03:04:56'),
(104, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 03:12:20'),
(105, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 03:12:28'),
(106, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-04 04:10:20'),
(107, 'cart_add', 'Produto ao carrinho', 'TIRZ 50 – Tirzepatide 50mg', '{\"product_id\":21}', 0, '2025-11-04 06:54:43'),
(108, 'cart_add', 'Produto ao carrinho', 'TIRZ 50 – Tirzepatide 50mg', '{\"product_id\":21}', 0, '2025-11-04 06:54:49'),
(109, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-04 11:24:30'),
(110, 'cart_add', 'Produto ao carrinho', 'TIRZ 50 – Tirzepatide 50mg', '{\"product_id\":21}', 0, '2025-11-04 12:06:15'),
(111, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 13:07:06'),
(112, 'new_order', 'Novo Pedido', 'Pedido #11 de teste', '{\"order_id\":11,\"total\":250,\"payment_method\":\"zelle\"}', 0, '2025-11-04 13:07:58'),
(113, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 13:12:21'),
(114, 'new_order', 'Novo Pedido', 'Pedido #12 de teste2mike', '{\"order_id\":12,\"total\":250,\"payment_method\":\"zelle\"}', 0, '2025-11-04 13:12:34'),
(115, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 13:15:08'),
(116, 'new_order', 'Novo Pedido', 'Pedido #13 de miketeste23', '{\"order_id\":13,\"total\":250,\"payment_method\":\"zelle\"}', 0, '2025-11-04 13:15:19'),
(117, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 14:21:49'),
(118, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 14:21:50'),
(119, 'new_order', 'Novo Pedido', 'Pedido #14 de Ronielly O Silva', '{\"order_id\":14,\"total\":500,\"payment_method\":\"square\"}', 0, '2025-11-04 14:23:04'),
(120, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 14:23:53'),
(121, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 14:23:56'),
(122, 'new_order', 'Novo Pedido', 'Pedido #15 de Ronielly O Silva', '{\"order_id\":15,\"total\":500,\"payment_method\":\"square\"}', 0, '2025-11-04 14:25:02'),
(123, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 16:04:45'),
(124, 'cart_add', 'Produto ao carrinho', 'Sterile Water', '{\"product_id\":27}', 0, '2025-11-04 16:31:09'),
(125, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 17:11:13'),
(126, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 17:11:17'),
(127, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 17:12:17'),
(128, 'new_order', 'Novo Pedido', 'Pedido #16 de Welisson Dougras de Melo', '{\"order_id\":16,\"total\":250,\"payment_method\":\"zelle\"}', 0, '2025-11-04 17:17:10'),
(129, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-04 17:35:35'),
(130, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 18:33:47'),
(131, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 22:45:21'),
(132, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-04 23:22:05'),
(133, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:36:03'),
(134, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:36:10'),
(135, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:36:12'),
(136, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:36:13'),
(137, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:36:37'),
(138, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:39:59'),
(139, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-04 23:40:48'),
(140, 'new_order', 'Novo Pedido', 'Pedido #17 de Nuliandra', '{\"order_id\":17,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-04 23:41:27'),
(141, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:47:56'),
(142, 'cart_add', 'Produto ao carrinho', 'Vitamina B12 0.5MG / 2ML', '{\"product_id\":18}', 0, '2025-11-04 23:49:30'),
(143, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:54:52'),
(144, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:54:58'),
(145, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-04 23:54:59'),
(146, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 00:18:19'),
(147, 'cart_add', 'Produto ao carrinho', 'TIRZ 50 – Tirzepatide 50mg', '{\"product_id\":21}', 0, '2025-11-05 00:32:21'),
(148, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-05 00:32:32'),
(149, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-05 00:33:20'),
(150, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-05 00:33:30'),
(151, 'new_order', 'Novo Pedido', 'Pedido #18 de Fabiana', '{\"order_id\":18,\"total\":415,\"payment_method\":\"square\"}', 0, '2025-11-05 00:35:30'),
(152, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 00:40:59'),
(153, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 00:41:01'),
(154, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-05 00:48:24'),
(155, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 01:01:39'),
(156, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 01:01:43'),
(157, 'cart_add', 'Produto ao carrinho', 'Vitamina B12 0.5MG / 2ML', '{\"product_id\":18}', 0, '2025-11-05 01:04:18'),
(158, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-05 01:18:53'),
(159, 'new_order', 'Novo Pedido', 'Pedido #19 de Bruno Siqueira Lage', '{\"order_id\":19,\"total\":515,\"payment_method\":\"square\"}', 0, '2025-11-05 01:25:57'),
(160, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 01:53:23'),
(161, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 02:33:03'),
(162, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 02:33:07'),
(163, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 02:33:12'),
(164, 'new_order', 'Novo Pedido', 'Pedido #20 de Rodrigo F Silva', '{\"order_id\":20,\"total\":250,\"payment_method\":\"zelle\"}', 0, '2025-11-05 02:36:52'),
(165, 'cart_add', 'Produto ao carrinho', 'TIRZ 60 – Tirzepatide 60mg', '{\"product_id\":17}', 0, '2025-11-05 03:28:51'),
(166, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 03:32:09'),
(167, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 03:32:16'),
(168, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 03:32:17'),
(169, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 03:32:18'),
(170, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 03:32:19'),
(171, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 03:43:38'),
(172, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 03:43:45'),
(173, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 03:43:50'),
(174, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-05 04:29:59'),
(175, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 04:31:19'),
(176, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-05 04:37:02'),
(177, 'cart_add', 'Produto ao carrinho', 'Mix Shot Burner -10ml', '{\"product_id\":19}', 0, '2025-11-05 04:38:21'),
(178, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 07:36:37'),
(179, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 07:36:40'),
(180, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 07:36:42'),
(181, 'new_order', 'Novo Pedido', 'Pedido #21 de Renato', '{\"order_id\":21,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-05 07:38:32'),
(182, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 10:03:11'),
(183, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-05 12:22:45'),
(184, 'cart_add', 'Produto ao carrinho', 'TIRZ 40 – Tirzepatide 40mg', '{\"product_id\":16}', 0, '2025-11-05 12:22:49'),
(185, 'cart_add', 'Produto ao carrinho', 'RETA 50 – Retatrutide 50mg', '{\"product_id\":26}', 0, '2025-11-05 12:23:10'),
(186, 'cart_add', 'Produto ao carrinho', 'Vitamina B12 0.5MG / 2ML', '{\"product_id\":18}', 0, '2025-11-05 12:24:22'),
(187, 'cart_add', 'Produto ao carrinho', 'Vitamina B12 0.5MG / 2ML', '{\"product_id\":18}', 0, '2025-11-05 12:24:25'),
(188, 'cart_add', 'Produto ao carrinho', 'TIRZ 50 – Tirzepatide 50mg', '{\"product_id\":21}', 0, '2025-11-05 12:51:00'),
(189, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:34:38'),
(190, 'cart_add', 'Produto ao carrinho', 'Vitamina B12 0.5MG / 2ML', '{\"product_id\":18}', 0, '2025-11-05 13:35:07'),
(191, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:39:48'),
(192, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:39:50'),
(193, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:39:54'),
(194, 'cart_add', 'Produto ao carrinho', 'Vitamina B12 0.5MG / 2ML', '{\"product_id\":18}', 0, '2025-11-05 13:40:00'),
(195, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:42:29'),
(196, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:42:31'),
(197, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:42:32'),
(198, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:42:33'),
(199, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:44:19'),
(200, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-05 13:44:52'),
(201, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:44:55'),
(202, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:44:56'),
(203, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 13:45:31'),
(204, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-05 13:53:32'),
(205, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":14}', 0, '2025-11-05 14:29:51'),
(206, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 15:47:36'),
(207, 'new_order', 'Novo Pedido', 'Pedido #22 de Michele Araújo', '{\"order_id\":22,\"total\":365,\"payment_method\":\"square\"}', 0, '2025-11-05 15:55:55'),
(208, 'cart_add', 'Produto ao carrinho', 'TIRZ 30 – Tirzepatide 30mg', '{\"product_id\":78}', 0, '2025-11-05 16:48:23'),
(209, 'new_order', 'Novo Pedido', 'Pedido #23 de Thiago', '{\"order_id\":23,\"total\":377,\"payment_method\":\"square\"}', 0, '2025-11-05 16:50:50'),
(210, 'cart_add', 'Produto ao carrinho', 'Mix Shot Burner -10ml', '{\"product_id\":83}', 0, '2025-11-05 16:55:13'),
(211, 'cart_add', 'Produto ao carrinho', 'Mix Shot Burner -10ml', '{\"product_id\":83}', 0, '2025-11-05 16:55:14'),
(212, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 17:07:42'),
(213, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 17:58:24'),
(214, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 18:51:00'),
(215, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 18:52:23'),
(216, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":79}', 0, '2025-11-05 19:09:22'),
(217, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":79}', 0, '2025-11-05 19:09:23'),
(218, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":79}', 0, '2025-11-05 19:09:30'),
(219, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":79}', 0, '2025-11-05 19:09:32'),
(220, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":79}', 0, '2025-11-05 19:09:33'),
(221, 'new_order', 'Novo Pedido', 'Pedido #24 de SIMY TOBELEM SILVA', '{\"order_id\":24,\"total\":287,\"payment_method\":\"zelle\"}', 0, '2025-11-05 19:11:33'),
(222, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 19:13:20'),
(223, 'new_order', 'Novo Pedido', 'Pedido #25 de SIMY TOBELEM SILVA', '{\"order_id\":25,\"total\":327,\"payment_method\":\"zelle\"}', 0, '2025-11-05 19:14:25'),
(224, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 19:19:33'),
(225, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 19:31:06'),
(226, 'new_order', 'Novo Pedido', 'Pedido #26 de Evelyn Camargo', '{\"order_id\":26,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-05 19:32:53'),
(227, 'new_order', 'Novo Pedido', 'Pedido #27 de Ana Maria  Borges Coelho', '{\"order_id\":27,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-05 19:34:00'),
(228, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 19:35:15'),
(229, 'new_order', 'Novo Pedido', 'Pedido #28 de Evelyn Camargo', '{\"order_id\":28,\"total\":250,\"payment_method\":\"square\"}', 0, '2025-11-05 19:35:41'),
(230, 'cart_add', 'Produto ao carrinho', 'TIRZ 20 – Tirzepatide 20mg', '{\"product_id\":13}', 0, '2025-11-05 20:06:02'),
(231, 'cart_add', 'Produto ao carrinho', 'TIRZ 15 – Tirzepatide 15mg', '{\"product_id\":79}', 0, '2025-11-05 20:38:29');

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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `shipping_name` varchar(120) DEFAULT NULL,
  `shipping_company` varchar(120) DEFAULT NULL,
  `shipping_email` varchar(160) DEFAULT NULL,
  `shipping_phone` varchar(40) DEFAULT NULL,
  `shipping_address1` varchar(160) DEFAULT NULL,
  `shipping_address2` varchar(160) DEFAULT NULL,
  `shipping_city` varchar(120) DEFAULT NULL,
  `shipping_state` varchar(80) DEFAULT NULL,
  `shipping_zipcode` varchar(20) DEFAULT NULL,
  `shipping_country` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `items_json`, `subtotal`, `shipping_cost`, `total`, `currency`, `payment_method`, `payment_ref`, `payment_status`, `status`, `track_token`, `zelle_receipt`, `notes`, `admin_viewed`, `created_at`, `updated_at`, `shipping_name`, `shipping_company`, `shipping_email`, `shipping_phone`, `shipping_address1`, `shipping_address2`, `shipping_city`, `shipping_state`, `shipping_zipcode`, `shipping_country`) VALUES
(1, 1, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/eWMKPWit\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 15.00, 265.00, 'USD', 'square', 'https://square.link/u/eWMKPWit', 'pending', 'pending', '9db37ae5b6ca3c2e228d71de0b91c427', NULL, NULL, 0, '2025-11-02 09:07:26', '2025-11-02 09:07:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/eWMKPWit\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/eWMKPWit', 'pending', 'pending', 'fed27261c159c724067bebdcb0f06a72', NULL, NULL, 0, '2025-11-02 13:57:33', '2025-11-02 13:57:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/eWMKPWit\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/eWMKPWit', 'pending', 'pending', '734ca4beefbcfd6debb6ed8fe3176f49', NULL, NULL, 0, '2025-11-02 14:46:29', '2025-11-02 14:46:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 4, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'paid', 'a656b769dae180f9a0aedc305061d700', NULL, NULL, 0, '2025-11-02 18:14:03', '2025-11-03 16:25:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 5, '[{\"id\":15,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":280,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 280.00, 15.00, 295.00, 'USD', 'square', 'https://square.link/u/ItAGrpHg', 'pending', 'paid', 'a947926aede43dc94c326d04ac0bb482', NULL, NULL, 0, '2025-11-02 21:30:41', '2025-11-03 16:25:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 6, '[{\"id\":15,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":280,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 280.00, 15.00, 295.00, 'USD', 'square', 'https://square.link/u/ItAGrpHg', 'pending', 'pending', 'aab8d2c4ea3ac666681204dafb7f9e82', NULL, NULL, 0, '2025-11-03 12:31:57', '2025-11-03 12:31:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 7, '[{\"id\":15,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":280,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 280.00, 15.00, 295.00, 'USD', 'square', 'https://square.link/u/ItAGrpHg', 'pending', 'paid', '830c52e178d3b07ecf27c46bb7699a0d', NULL, NULL, 0, '2025-11-03 12:35:23', '2025-11-03 16:25:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 8, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'pending', 'ab930f671e179b6371fbe495cc490640', NULL, NULL, 0, '2025-11-04 00:38:33', '2025-11-04 00:38:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 9, '[{\"id\":17,\"name\":\"TIRZ 60 – Tirzepatide 60mg\",\"price\":500,\"qty\":1,\"sku\":\"TIRZ-005\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/A69ku30z\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 500.00, 15.00, 515.00, 'USD', 'zelle', '', 'pending', 'pending', 'b853b38dba53aefa1e1b94132da5b639', 'storage/zelle_receipts/receipt_20251104_004140_8600c337.png', NULL, 0, '2025-11-04 00:41:40', '2025-11-04 00:41:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 10, '[{\"id\":15,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":260,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 260.00, 15.00, 275.00, 'USD', 'square', 'https://square.link/u/ItAGrpHg', 'pending', 'pending', '7491f04791a21249d38b96247af0f84a', NULL, NULL, 0, '2025-11-04 02:26:06', '2025-11-04 02:26:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 11, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'zelle', '', 'pending', 'pending', '63209cec7b5c3e8986b8ea22fde1bcb9', NULL, NULL, 0, '2025-11-04 13:07:58', '2025-11-04 13:07:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 12, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'zelle', '', 'pending', 'canceled', 'cd1fd698a3fe9c35215d6b707d1b3c6e', NULL, NULL, 0, '2025-11-04 13:12:34', '2025-11-05 01:37:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 13, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'zelle', '', 'pending', 'pending', '0062ff0f141a36334f308c8194296408', NULL, NULL, 0, '2025-11-04 13:15:19', '2025-11-04 13:15:19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 14, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":2,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 500.00, 0.00, 500.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'canceled', 'd979e3432e8e301829eac5a306c25684', NULL, NULL, 0, '2025-11-04 14:23:04', '2025-11-05 01:37:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 15, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":2,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 500.00, 0.00, 500.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'paid', '49ec907b018c2fd8039a21263af130cb', NULL, NULL, 0, '2025-11-04 14:25:02', '2025-11-05 01:32:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 16, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'zelle', '', 'pending', 'paid', '046f57b71891a56ca7775ebf06ac8eaf', NULL, NULL, 0, '2025-11-04 17:17:10', '2025-11-05 01:34:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 17, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'pending', '398682cc76797cb6d4fa9fbba143ff00', NULL, NULL, 0, '2025-11-04 23:41:27', '2025-11-04 23:41:27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 18, '[{\"id\":16,\"name\":\"TIRZ 40 – Tirzepatide 40mg\",\"price\":400,\"qty\":1,\"sku\":\"TIRZ-004\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/cilVU3dw\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 400.00, 15.00, 415.00, 'USD', 'square', 'https://square.link/u/cilVU3dw', 'pending', 'pending', '4516db606fd9f98b93b8814b430a9a28', NULL, NULL, 0, '2025-11-05 00:35:30', '2025-11-05 00:35:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 19, '[{\"id\":17,\"name\":\"TIRZ 60 – Tirzepatide 60mg\",\"price\":500,\"qty\":1,\"sku\":\"TIRZ-005\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/A69ku30z\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 500.00, 15.00, 515.00, 'USD', 'square', 'https://square.link/u/A69ku30z', 'pending', 'paid', 'e19efce6433d551937d896ee2aa607ec', NULL, NULL, 0, '2025-11-05 01:25:57', '2025-11-05 01:28:49', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 20, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'zelle', '', 'pending', 'pending', 'a95eae864dadf81ae0b79b87ad008d8f', NULL, NULL, 0, '2025-11-05 02:36:52', '2025-11-05 02:36:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 21, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'pending', 'c698ca85ee9938679841e40b24f46eff', NULL, NULL, 0, '2025-11-05 07:38:32', '2025-11-05 07:38:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 22, '[{\"id\":14,\"name\":\"TIRZ 30 – Tirzepatide 30mg\",\"price\":350,\"qty\":1,\"sku\":\"TIRZ-002\",\"shipping_cost\":15,\"square_link\":\"https:\\/\\/square.link\\/u\\/l9KQMPhT\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 350.00, 15.00, 365.00, 'USD', 'square', 'https://square.link/u/l9KQMPhT', 'pending', 'pending', '7481ffad852ac30dfa4358edbe23d886', NULL, NULL, 0, '2025-11-05 15:55:55', '2025-11-05 15:55:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 23, '[{\"id\":78,\"name\":\"TIRZ 30 – Tirzepatide 30mg\",\"price\":370,\"qty\":1,\"sku\":\"TIRZ-002\",\"shipping_cost\":7,\"square_link\":\"https:\\/\\/square.link\\/u\\/l9KQMPhT\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 370.00, 7.00, 377.00, 'USD', 'square', 'https://square.link/u/l9KQMPhT', 'pending', 'pending', '634dd5a55d6a2efa282c8e6b8cd756e3', NULL, NULL, 0, '2025-11-05 16:50:50', '2025-11-05 16:50:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 24, '[{\"id\":79,\"name\":\"TIRZ 15 – Tirzepatide 15mg\",\"price\":280,\"qty\":1,\"sku\":\"TIRZ-003\",\"shipping_cost\":7,\"square_link\":\"https:\\/\\/square.link\\/u\\/ItAGrpHg\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 280.00, 7.00, 287.00, 'USD', 'zelle', '', 'pending', 'pending', 'a42b9eff0a4674b60cf7d6efe0b39f42', NULL, NULL, 0, '2025-11-05 19:11:33', '2025-11-05 19:11:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 25, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":320,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":7,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 320.00, 7.00, 327.00, 'USD', 'zelle', '', 'pending', 'pending', '0ed65d3aed7c0c808f83d112eaf1f9be', NULL, NULL, 0, '2025-11-05 19:14:25', '2025-11-05 19:14:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 26, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'pending', 'ba7341e8cfade7ff994f26da835fc05c', NULL, NULL, 0, '2025-11-05 19:32:53', '2025-11-05 19:32:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 27, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'pending', '0f9a0ebd464f76c901fa15780e7a729c', NULL, NULL, 0, '2025-11-05 19:34:00', '2025-11-05 19:34:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 28, '[{\"id\":13,\"name\":\"TIRZ 20 – Tirzepatide 20mg\",\"price\":250,\"qty\":1,\"sku\":\"TIRZ-001\",\"shipping_cost\":0,\"square_link\":\"https:\\/\\/square.link\\/u\\/KvS3pgmm\",\"stripe_link\":\"\",\"currency\":\"USD\"}]', 250.00, 0.00, 250.00, 'USD', 'square', 'https://square.link/u/KvS3pgmm', 'pending', 'pending', '4a306bdf663d60a1e5cffcd87a5cb19e', NULL, NULL, 0, '2025-11-05 19:35:41', '2025-11-05 19:35:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `code`, `name`, `description`, `instructions`, `settings`, `icon_path`, `is_active`, `require_receipt`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'pix', 'Pix', NULL, 'Use o Pix para pagar seu pedido. Valor: {valor_pedido}.\\nChave: {pix_key}', '{\"type\":\"pix\",\"account_label\":\"Chave Pix\",\"account_value\":\"\",\"pix_key\":\"\",\"merchant_name\":\"\",\"merchant_city\":\"\",\"currency\":\"BRL\"}', NULL, 0, 0, 10, '2025-11-02 08:37:31', '2025-11-02 09:00:46'),
(2, 'zelle', 'Zelle', '', '💰 Zelle\r\nZelle: (978) 901-4603 ou 978 901 4603\r\nNome: Get Power Research Business\r\n\r\n📌\r\nObs: Coloque APENAS O NÚMERO DO PEDIDO\r\nna mensagem do Zelle.\r\n\r\nApós o pagamento, você pode enviar um e-mail com o comprovante de pagamento juntamente APENAS O NÚMERO DO PEDIDO\r\n\r\n📧 para o e-mail: getpowerresearch9@gmail.com\r\n\r\n⚠️ ATENÇÃO SOBRE SUA MENSAGEM DE PAGAMENTO\r\n\r\nOlá! Segue as regras abaixo para que seu pedido não seja cancelado:\r\n\r\nSempre escreva apenas o número do seu pedido no campo de mensagem.\r\n\r\nEXEMPLO:\r\nAPENAS O NÚMERO DO PEDIDO\r\n\r\nLAST FOUR DIGITS: 4603\r\n\r\n📧 para o e-mail: getpowerresearch9@gmail.com\r\n\r\n📌⚠️ Mensagens contendo nomes de medicamentos, nomes de pessoas ou qualquer outro texto fora o número do pedido causarão cancelamento automático da próxima compra, por motivos de segurança bancária e contábil.\r\n\r\n📌⚠️ Mensagens em branco ou com emojis também não são aceitas e podem atrasar o envio da sua compra.\r\n\r\nEsse procedimento é essencial para mantermos nosso sistema de envios seguro, organizado e funcionando corretamente.\r\n\r\nAgradecemos a sua atenção e compreensão.\r\n\r\n📌 ATENÇÃO!\r\n\r\n⏳💰 Você tem até 3 dias para efetuar o pagamento.\r\nApós os 3 dias, se o pagamento não for realizado, seu pedido será cancelado.\r\n\r\nEnvie o valor de {valor_pedido} via Zelle para {account_value}. Anexe o comprovante se solicitado.', '{\"type\":\"zelle\",\"account_label\":\"9789014603\",\"account_value\":\"\",\"button_bg\":\"#dc2626\",\"button_text\":\"#ffffff\",\"button_hover_bg\":\"#b91c1c\",\"recipient_name\":\"Get Power ( Renato Azevedo)\"}', NULL, 1, 0, 20, '2025-11-02 08:37:31', '2025-11-04 18:06:06'),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `price_compare`, `currency`, `shipping_cost`, `stock`, `image_path`, `square_payment_link`, `square_credit_link`, `square_debit_link`, `square_afterpay_link`, `stripe_payment_link`, `active`, `featured`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(13, 11, 'TIRZ-001', 'TIRZ 20 – Tirzepatide 20mg', 'Tirzepatide 20mg para tratamento', 250.00, 320.00, 'USD', 0.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/20.png', 'https://square.link/u/KvS3pgmm', 'https://square.link/u/KvS3pgmm', 'https://square.link/u/KvS3pgmm', 'https://square.link/u/KvS3pgmm', '', 1, 1, NULL, NULL, '2025-11-02 08:48:03', '2025-11-05 19:16:50'),
(78, NULL, 'TIRZ-002', 'TIRZ 30 – Tirzepatide 30mg', 'Tirzepatide 30mg para tratamento', 370.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/30.png', 'https://square.link/u/l9KQMPhT', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(79, NULL, 'TIRZ-003', 'TIRZ 15 – Tirzepatide 15mg', 'Tirzepatide 15mg para tratamento', 280.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/15.png', 'https://square.link/u/ItAGrpHg', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(80, NULL, 'TIRZ-004', 'TIRZ 40 – Tirzepatide 40mg', 'Tirzepatide 40mg para tratamento', 420.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/40.png', 'https://square.link/u/cilVU3dw', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(81, NULL, 'TIRZ-005', 'TIRZ 60 – Tirzepatide 60mg', 'Tirzepatide 60mg para tratamento', 520.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/08/ChatGPT-Image-16-de-set.-de-2025-18_21_31.png', 'https://square.link/u/A69ku30z', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(82, NULL, 'VIT-001', 'Vitamina B12 0.5MG / 2ML', 'Vitamina B12 injetável', 15.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/b12.png', '', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:08:42'),
(83, NULL, 'MIX-001', 'Mix Shot Burner -10ml', 'Mix Shot Burner queimador de gordura', 160.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/08/ChatGPT-Image-17-de-set.-de-2025-15_59_05.png', '', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:08:42'),
(84, NULL, 'VIT-003', 'Vitamina D - 10mg - 2000 ui', 'Vitamina D 2000 UI', 30.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/vitamina-d.png', '', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:08:42'),
(85, NULL, 'TIRZ-006', 'TIRZ 50 – Tirzepatide 50mg', 'Tirzepatide 50mg para tratamento', 470.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/ChatGPT-Image-16-de-set.-de-2025-18_13_33.png', 'https://square.link/u/DJIjLR9j', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(86, NULL, 'RETA-001', 'RETA 15 – Retatrutide 15mg', 'Retatrutide 15mg para tratamento', 320.00, NULL, 'USD', 7.00, 10, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/15-1.png', 'https://square.link/u/6ljvNcEz', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(87, 14, 'RETA-002', 'RETA 20 – Retatrutide 20mg', 'Retatrutide 20mg para tratamento', 250.00, 370.00, 'USD', 0.00, 9, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/20-1.png', 'https://square.link/u/pLTeZbVf', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 19:09:51'),
(88, NULL, 'RETA-003', 'RETA 30 – Retatrutide 30mg', 'Retatrutide 30mg para tratamento', 420.00, NULL, 'USD', 7.00, 10, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/30-1.png', 'https://square.link/u/b7X2o6jP', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(89, NULL, 'RETA-004', 'RETA 40 – Retatrutide 40mg', 'Retatrutide 40mg para tratamento', 470.00, NULL, 'USD', 7.00, 10, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/40-1.png', 'https://square.link/u/kFzMe83r', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(90, NULL, 'RETA-005', 'RETA 50 – Retatrutide 50mg', 'Retatrutide 50mg para tratamento', 520.00, NULL, 'USD', 7.00, 10, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/50.png', 'https://square.link/u/iu5LpABj', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21'),
(91, NULL, 'STER-001', 'Sterile Water', 'Água estéril para diluição', 10.00, NULL, 'USD', 7.00, 100, 'https://store.nestgeneralservices.company/wp-content/uploads/2025/09/ChatGPT-Image-17-de-set.-de-2025-15_49_15.png', '', '', '', '', '', 1, 0, NULL, NULL, '2025-11-05 16:08:42', '2025-11-05 16:29:21');

-- --------------------------------------------------------

--
-- Estrutura para tabela `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `skey` varchar(191) NOT NULL,
  `svalue` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `settings`
--

INSERT INTO `settings` (`id`, `skey`, `svalue`, `updated_at`) VALUES
(1, 'store_name', 'Get Power Research', '2025-11-04 13:14:57'),
(2, 'store_email', 'getpowerresearch9@gmail.com', '2025-11-04 13:14:57'),
(3, 'store_phone', '+1 (978) 901 4603', '2025-11-04 13:14:57'),
(4, 'store_address', 'Massachusetts – USA', '2025-11-04 13:14:57'),
(5, 'store_meta_title', 'Get Power Research', '2025-11-04 13:14:57'),
(6, 'home_hero_title', 'Tudo para sua saúde', '2025-11-04 13:14:57'),
(7, 'home_hero_subtitle', 'Experiência de app, rápida e segura.', '2025-11-04 13:14:57'),
(8, 'header_subline', 'Tudo para sua saúde', '2025-11-04 13:14:57'),
(9, 'footer_title', 'Get Power Research', '2025-11-04 13:14:57'),
(10, 'footer_description', 'Tudo para sua saúde online com experiência de app.', '2025-11-04 13:14:57'),
(11, 'footer_copy', '© {{year}} Get Power Research. Todos os direitos reservados.', '2025-11-04 13:14:57'),
(12, 'theme_color', '#2060C8', '2025-11-04 13:14:57'),
(13, 'home_featured_label', 'Oferta destaque', '2025-11-04 13:14:57'),
(14, 'whatsapp_button_text', 'Fale com a gente', '2025-11-04 13:14:57'),
(15, 'whatsapp_message', 'Olá! Gostaria de tirar uma dúvida sobre os produtos.', '2025-11-04 13:14:57'),
(16, 'store_currency', 'USD', '2025-11-02 08:37:31'),
(17, 'pwa_name', 'Get Power Research', '2025-11-04 13:14:57'),
(18, 'pwa_short_name', 'Get Power Research', '2025-11-04 13:14:57'),
(19, 'home_featured_enabled', '1', '2025-11-04 13:14:57'),
(20, 'home_featured_title', 'Ofertas em destaque', '2025-11-04 13:14:57'),
(21, 'home_featured_subtitle', 'Seleção especial com preços imperdíveis. + FRETE GRÁTIS', '2025-11-04 13:14:57'),
(22, 'home_featured_badge_title', 'Seleção especial', '2025-11-04 13:14:57'),
(23, 'home_featured_badge_text', 'Selecionados com carinho para você', '2025-11-04 13:14:57'),
(24, 'email_customer_subject', 'Seu pedido {{order_id}} foi recebido - Get Power Research', '2025-11-04 13:14:57'),
(25, 'email_customer_body', '<!DOCTYPE html>\r\n<html lang=\"pt-BR\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\r\n    <title>{{store_name}}</title>\r\n</head>\r\n<body leftmargin=\"0\" marginwidth=\"0\" topmargin=\"0\" marginheight=\"0\" offset=\"0\" style=\"padding: 0;\">\r\n    <div id=\"wrapper\" dir=\"ltr\" style=\"background-color: #f6f6f6; margin: 0; padding: 70px 0; width: 100%; -webkit-text-size-adjust: none;\">\r\n        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" height=\"100%\" width=\"100%\">\r\n            <tr>\r\n                <td align=\"center\" valign=\"top\">\r\n                    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" id=\"template_container\" style=\"background-color: #ffffff; border: 1px solid #dddddd; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1); border-radius: 3px;\">\r\n                        <tr>\r\n                            <td align=\"center\" valign=\"top\">\r\n                                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" id=\"template_header\" style=\'background-color: #4d77b9; color: #ffffff; border-bottom: 0; font-weight: bold; line-height: 100%; vertical-align: middle; font-family: \"Helvetica Neue\", Helvetica, Roboto, Arial, sans-serif; border-radius: 3px 3px 0 0;\'>\r\n                                    <tr>\r\n                                        <td id=\"header_wrapper\" style=\"padding: 36px 48px; display: block;\">\r\n                                            \r\n                                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                                <tr>\r\n                                                    <td align=\"left\" style=\"padding-bottom: 20px;\">\r\n                                                        <img src=\"https://getpoweresearch.com/storage/logo/logo.png?v=1762090336\" alt=\"Get Power Research\" style=\"border: none; display: inline-block; font-size: 14px; font-weight: bold; height: auto; outline: none; text-decoration: none; text-transform: capitalize; max-width: 250px; color: #ffffff;\" />\r\n                                                    </td>\r\n                                                </tr>\r\n                                            </table>\r\n                                            <h1 style=\'font-family: \"Helvetica Neue\", Helvetica, Roboto, Arial, sans-serif; font-size: 30px; font-weight: 300; line-height: 150%; margin: 0; text-align: left; color: #ffffff; background-color: inherit;\'>Obrigado pelo seu pedido!</h1>\r\n                                        </td>\r\n                                    </tr>\r\n                                </table>\r\n                                </td>\r\n                        </tr>\r\n                        <tr>\r\n                            <td align=\"center\" valign=\"top\">\r\n                                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" id=\"template_body\">\r\n                                    <tr>\r\n                                        <td valign=\"top\" id=\"body_content\" style=\"background-color: #ffffff;\">\r\n                                            <table border=\"0\" cellpadding=\"20\" cellspacing=\"0\" width=\"100%\">\r\n                                                <tr>\r\n                                                    <td valign=\"top\" style=\"padding: 48px 48px 32px;\">\r\n                                                        <div id=\"body_content_inner\" style=\'color: #636363; font-family: \"Helvetica Neue\", Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left;\'>\r\n\r\n                                                            <p style=\"margin: 0 0 16px;\">Olá {{customer_name}},</p>\r\n                                                            <p style=\"margin: 0 0 16px;\">Recebemos seu pedido <strong>#{{order_id}}</strong> na {{store_name}}. Seguem os detalhes:</p>\r\n\r\n                                                            <h2 style=\'color: #4d77b9; display: block; font-family: \"Helvetica Neue\", Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;\'>\r\n                                                                [Pedido #{{order_id}}]\r\n                                                            </h2>\r\n\r\n                                                            <div style=\"margin-bottom: 40px;\">\r\n                                                                <table class=\"td\" cellspacing=\"0\" cellpadding=\"6\" border=\"1\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;\">\r\n                                                                    <thead>\r\n                                                                        <tr>\r\n                                                                            <th class=\"td\" scope=\"col\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;\">Produto</th>\r\n                                                                            <th class=\"td\" scope=\"col\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;\">Quantidade</th>\r\n                                                                            <th class=\"td\" scope=\"col\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;\">Preço</th>\r\n                                                                        </tr>\r\n                                                                    </thead>\r\n                                                                    <tbody>\r\n                                                                        {{order_items}}\r\n                                                                    </tbody>\r\n                                                                    <tfoot>\r\n                                                                        <tr>\r\n                                                                            <th class=\"td\" scope=\"row\" colspan=\"2\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left; border-top-width: 4px;\">Subtotal:</th>\r\n                                                                            <td class=\"td\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left; border-top-width: 4px;\">{{order_subtotal}}</td>\r\n                                                                        </tr>\r\n                                                                        <tr>\r\n                                                                            <th class=\"td\" scope=\"row\" colspan=\"2\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;\">Frete:</th>\r\n                                                                            <td class=\"td\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;\">{{order_shipping}}</td>\r\n                                                                        </tr>\r\n                                                                        <tr>\r\n                                                                            <th class=\"td\" scope=\"row\" colspan=\"2\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;\">Método de pagamento:</th>\r\n                                                                            <td class=\"td\" style=\"color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;\">{{payment_method}}</td>\r\n                                                                        </tr>\r\n                                                                        <tr>\r\n                           ', '2025-11-04 13:14:57'),
(26, 'email_admin_subject', 'Novo pedido #{{order_id}} - Get Power Research', '2025-11-04 13:14:57'),
(27, 'email_admin_body', '<h2>Novo pedido recebido</h2>\r\n<p><strong>Loja:</strong> {{store_name}}</p>\r\n<p><strong>Pedido:</strong> #{{order_id}}</p>\r\n<p><strong>Cliente:</strong> {{customer_name}} &lt;{{customer_email}}&gt; — {{customer_phone}}</p>\r\n<p><strong>Total:</strong> {{order_total}} &nbsp;|&nbsp; <strong>Pagamento:</strong> {{payment_method}}</p>\r\n{{order_items}}\r\n<p><strong>Endereço:</strong><br>{{shipping_address}}</p>\r\n<p><strong>Observações:</strong> {{order_notes}}</p>\r\n<p>Painel: <a href=\"{{admin_order_url}}\">{{admin_order_url}}</a></p>', '2025-11-04 13:14:57'),
(48, 'whatsapp_enabled', '1', '2025-11-04 13:14:57'),
(49, 'whatsapp_number', '19789014603', '2025-11-04 13:14:57'),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232;

--
-- AUTO_INCREMENT de tabela `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT de tabela `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
