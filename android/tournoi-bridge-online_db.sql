-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql-tournoi-bridge-online.alwaysdata.net
-- Generation Time: Mar 23, 2026 at 10:39 AM
-- Server version: 10.11.15-MariaDB
-- PHP Version: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tournoi-bridge-online_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `associations`
--

CREATE TABLE `associations` (
  `ID` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mdp_hash` varchar(255) NOT NULL,
  `token_api` varchar(64) NOT NULL,
  `code_adherent` varchar(30) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `plan` varchar(20) DEFAULT 'gratuit',
  `nbre_tournois_max` int(11) DEFAULT 5,
  `nbre_tournois_joues` int(11) DEFAULT 0,
  `date_inscription` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `codes_invitation`
--

CREATE TABLE `codes_invitation` (
  `ID` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `nbre_tournois_max` int(11) DEFAULT 5,
  `actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `codes_invitation`
--

INSERT INTO `codes_invitation` (`ID`, `code`, `description`, `nbre_tournois_max`, `actif`, `date_creation`) VALUES
(1, 'BRIDGE2026', 'Code général 2026', 10, 1, '2026-03-23 22:35:22');

-- --------------------------------------------------------

--
-- Table structure for table `donnes`
--

CREATE TABLE `donnes` (
  `ID` smallint(6) NOT NULL,
  `id_association` int(11) NOT NULL DEFAULT 1,
  `id_tournoi` smallint(6) DEFAULT NULL,
  `numero_donne` tinyint(4) DEFAULT NULL,
  `donneur` varchar(1) DEFAULT NULL,
  `vulnerable` varchar(2) DEFAULT NULL,
  `main_N` varchar(4) DEFAULT NULL,
  `main_S` varchar(4) DEFAULT NULL,
  `main_E` varchar(4) DEFAULT NULL,
  `main_O` varchar(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `donnes_d_v`
--

CREATE TABLE `donnes_d_v` (
  `ID_donnes_d_v` tinyint(4) DEFAULT NULL,
  `donneur` varchar(1) DEFAULT NULL,
  `vulnerable` varchar(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `donnes_d_v`
--

INSERT INTO `donnes_d_v` (`ID_donnes_d_v`, `donneur`, `vulnerable`) VALUES
(1, 'N', 'P'),
(2, 'E', 'NS'),
(3, 'S', 'EO'),
(4, 'O', 'T'),
(5, 'N', 'NS'),
(6, 'E', 'EO'),
(7, 'S', 'T'),
(8, 'O', 'P'),
(9, 'N', 'EO'),
(10, 'E', 'T'),
(11, 'S', 'P'),
(12, 'O', 'NS'),
(13, 'N', 'T'),
(14, 'E', 'P'),
(15, 'S', 'NS'),
(16, 'O', 'EO'),
(17, 'N', 'P'),
(18, 'E', 'NS'),
(19, 'S', 'EO'),
(20, 'O', 'T'),
(21, 'N', 'NS');

-- --------------------------------------------------------

--
-- Table structure for table `encheres`
--

CREATE TABLE `encheres` (
  `ID` smallint(6) NOT NULL,
  `id_association` int(11) NOT NULL DEFAULT 1,
  `id_tournoi` smallint(6) DEFAULT NULL,
  `numero_donne` tinyint(4) DEFAULT NULL,
  `equipeNS` tinyint(4) DEFAULT NULL,
  `equipeEO` tinyint(4) DEFAULT NULL,
  `ordre` tinyint(4) DEFAULT NULL,
  `joueur` varchar(1) DEFAULT NULL,
  `annonce` varchar(6) DEFAULT NULL,
  `id_enchere_suivante` varchar(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Stand-in structure for view `encheres199`
-- (See below for the actual view)
--
CREATE TABLE `encheres199` (
`ID` smallint(6)
,`id_tournoi` smallint(6)
,`numero_donne` tinyint(4)
,`equipeNS` tinyint(4)
,`equipeEO` tinyint(4)
,`ordre` tinyint(4)
,`joueur` varchar(1)
,`annonce` varchar(6)
,`id_enchere_suivante` varchar(4)
);

-- --------------------------------------------------------

--
-- Table structure for table `equipes`
--

CREATE TABLE `equipes` (
  `ID` smallint(6) NOT NULL,
  `id_association` int(11) NOT NULL DEFAULT 1,
  `id_tournoi` smallint(6) DEFAULT NULL,
  `equipe_numero` tinyint(4) DEFAULT NULL,
  `id_joueur1` tinyint(4) DEFAULT NULL,
  `id_joueur2` tinyint(4) DEFAULT NULL,
  `mvnt_numero` tinyint(4) DEFAULT NULL,
  `numero_donne` tinyint(4) DEFAULT NULL,
  `index_donne_jouee` tinyint(4) DEFAULT NULL,
  `pts` smallint(6) DEFAULT NULL,
  `rang` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `Howell_3t10d`
--

CREATE TABLE `Howell_3t10d` (
  `ID` tinyint(4) DEFAULT NULL,
  `mvnt_numero` tinyint(4) DEFAULT NULL,
  `table_numero` tinyint(4) DEFAULT NULL,
  `equipe_NS` tinyint(4) DEFAULT NULL,
  `equipe_EO` tinyint(4) DEFAULT NULL,
  `numero_d1` tinyint(4) DEFAULT NULL,
  `numero_d2` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `Howell_3t10d`
--

INSERT INTO `Howell_3t10d` (`ID`, `mvnt_numero`, `table_numero`, `equipe_NS`, `equipe_EO`, `numero_d1`, `numero_d2`) VALUES
(1, 1, 1, 6, 1, 1, 2),
(2, 1, 2, 4, 3, 4, 10),
(3, 1, 3, 5, 2, 5, 7),
(4, 2, 1, 6, 2, 3, 4),
(5, 2, 2, 5, 4, 2, 6),
(6, 2, 3, 1, 3, 7, 9),
(7, 3, 1, 6, 3, 5, 6),
(8, 3, 2, 1, 5, 4, 8),
(9, 3, 3, 2, 4, 1, 9),
(10, 4, 1, 6, 4, 7, 8),
(11, 4, 2, 2, 1, 6, 10),
(12, 4, 3, 3, 5, 1, 3),
(13, 5, 1, 6, 5, 9, 10),
(14, 5, 2, 3, 2, 2, 8),
(15, 5, 3, 4, 1, 3, 5);

-- --------------------------------------------------------

--
-- Table structure for table `Howell_3t20d`
--

CREATE TABLE `Howell_3t20d` (
  `ID` tinyint(4) DEFAULT NULL,
  `mvnt_numero` tinyint(4) DEFAULT NULL,
  `table_numero` tinyint(4) DEFAULT NULL,
  `equipe_NS` tinyint(4) DEFAULT NULL,
  `equipe_EO` tinyint(4) DEFAULT NULL,
  `numero_d1` tinyint(4) DEFAULT NULL,
  `numero_d2` tinyint(4) DEFAULT NULL,
  `numero_d3` tinyint(4) DEFAULT NULL,
  `numero_d4` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `Howell_3t20d`
--

INSERT INTO `Howell_3t20d` (`ID`, `mvnt_numero`, `table_numero`, `equipe_NS`, `equipe_EO`, `numero_d1`, `numero_d2`, `numero_d3`, `numero_d4`) VALUES
(1, 1, 1, 6, 1, 1, 2, 3, 4),
(2, 4, 2, 2, 1, 11, 12, 19, 20),
(3, 2, 3, 1, 3, 13, 14, 17, 18),
(4, 2, 1, 6, 2, 5, 6, 7, 8),
(5, 2, 2, 5, 4, 3, 4, 11, 12),
(6, 1, 3, 5, 2, 9, 10, 13, 14),
(7, 3, 1, 6, 3, 9, 10, 11, 12),
(8, 5, 2, 3, 2, 3, 4, 15, 16),
(9, 5, 3, 4, 1, 5, 6, 9, 10),
(10, 4, 1, 6, 4, 13, 14, 15, 16),
(11, 3, 2, 1, 5, 7, 8, 15, 16),
(12, 4, 3, 3, 5, 1, 2, 5, 6),
(13, 5, 1, 6, 5, 17, 18, 19, 20),
(14, 1, 2, 4, 3, 7, 8, 19, 20),
(15, 3, 3, 2, 4, 1, 2, 17, 18);

-- --------------------------------------------------------

--
-- Table structure for table `Howell_4t21d`
--

CREATE TABLE `Howell_4t21d` (
  `ID` tinyint(4) DEFAULT NULL,
  `mvnt_numero` tinyint(4) DEFAULT NULL,
  `table_numero` tinyint(4) DEFAULT NULL,
  `equipe_NS` tinyint(4) DEFAULT NULL,
  `equipe_EO` tinyint(4) DEFAULT NULL,
  `numero_d1` tinyint(4) DEFAULT NULL,
  `numero_d2` tinyint(4) DEFAULT NULL,
  `numero_d3` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `Howell_4t21d`
--

INSERT INTO `Howell_4t21d` (`ID`, `mvnt_numero`, `table_numero`, `equipe_NS`, `equipe_EO`, `numero_d1`, `numero_d2`, `numero_d3`) VALUES
(1, 1, 1, 8, 1, 1, 2, 3),
(5, 5, 1, 8, 5, 13, 14, 15),
(9, 2, 2, 3, 7, 13, 14, 15),
(13, 6, 2, 7, 4, 4, 5, 6),
(17, 3, 3, 7, 2, 1, 2, 3),
(21, 7, 3, 4, 6, 13, 14, 15),
(25, 4, 4, 6, 7, 7, 8, 9),
(2, 2, 1, 8, 2, 4, 5, 6),
(6, 6, 1, 8, 6, 16, 17, 18),
(10, 3, 2, 4, 1, 16, 17, 18),
(14, 7, 2, 1, 5, 7, 8, 9),
(18, 4, 3, 1, 3, 4, 5, 6),
(22, 1, 4, 3, 4, 19, 20, 21),
(26, 5, 4, 7, 1, 10, 11, 12),
(3, 3, 1, 8, 3, 7, 8, 9),
(7, 7, 1, 8, 7, 19, 20, 21),
(11, 4, 2, 5, 2, 19, 20, 21),
(15, 1, 3, 5, 7, 16, 17, 18),
(19, 5, 3, 2, 4, 7, 8, 9),
(23, 2, 4, 4, 5, 1, 2, 3),
(27, 6, 4, 1, 2, 13, 14, 15),
(4, 4, 1, 8, 4, 10, 11, 12),
(8, 1, 2, 2, 6, 10, 11, 12),
(12, 5, 2, 6, 3, 1, 2, 3),
(16, 2, 3, 6, 1, 19, 20, 21),
(20, 6, 3, 3, 5, 10, 11, 12),
(24, 3, 4, 5, 6, 4, 5, 6),
(28, 7, 4, 2, 3, 16, 17, 18);

-- --------------------------------------------------------

--
-- Table structure for table `Howell_5t18d`
--

CREATE TABLE `Howell_5t18d` (
  `ID` tinyint(4) DEFAULT NULL,
  `mvnt_numero` tinyint(4) DEFAULT NULL,
  `table_numero` tinyint(4) DEFAULT NULL,
  `equipe_NS` tinyint(4) DEFAULT NULL,
  `equipe_EO` tinyint(4) DEFAULT NULL,
  `numero_d1` tinyint(4) DEFAULT NULL,
  `numero_d2` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `Howell_5t18d`
--

INSERT INTO `Howell_5t18d` (`ID`, `mvnt_numero`, `table_numero`, `equipe_NS`, `equipe_EO`, `numero_d1`, `numero_d2`) VALUES
(1, 1, 1, 10, 1, 1, 2),
(2, 2, 1, 10, 2, 3, 4),
(12, 3, 2, 1, 2, 7, 8),
(22, 4, 3, 9, 7, 11, 12),
(32, 5, 4, 7, 2, 5, 6),
(42, 6, 5, 7, 1, 9, 10),
(43, 7, 5, 8, 2, 11, 12),
(44, 8, 5, 9, 3, 13, 14),
(45, 9, 5, 1, 4, 15, 16),
(3, 3, 1, 10, 3, 5, 6),
(4, 4, 1, 10, 4, 7, 8),
(5, 5, 1, 10, 5, 9, 10),
(6, 6, 1, 10, 6, 11, 12),
(7, 7, 1, 10, 7, 13, 14),
(8, 8, 1, 10, 8, 15, 16),
(9, 9, 1, 10, 9, 17, 18),
(10, 1, 2, 8, 9, 3, 4),
(11, 2, 2, 9, 1, 5, 6),
(13, 4, 2, 2, 3, 9, 10),
(14, 5, 2, 3, 4, 11, 12),
(15, 6, 2, 4, 5, 13, 14),
(16, 7, 2, 5, 6, 15, 16),
(17, 8, 2, 6, 7, 17, 18),
(18, 9, 2, 7, 8, 1, 2),
(19, 1, 3, 6, 4, 5, 6),
(20, 2, 3, 7, 5, 7, 8),
(21, 3, 3, 8, 6, 9, 10),
(23, 5, 3, 1, 8, 13, 14),
(24, 6, 3, 2, 9, 15, 16),
(25, 7, 3, 3, 1, 17, 18),
(26, 8, 3, 4, 2, 1, 2),
(27, 9, 3, 5, 3, 3, 4),
(28, 1, 4, 3, 7, 15, 16),
(29, 2, 4, 4, 8, 17, 18),
(30, 3, 4, 5, 9, 1, 2),
(31, 4, 4, 6, 1, 3, 4),
(33, 6, 4, 8, 3, 7, 8),
(34, 7, 4, 9, 4, 9, 10),
(35, 8, 4, 1, 5, 11, 12),
(36, 9, 4, 2, 6, 13, 14),
(37, 1, 5, 2, 5, 17, 18),
(38, 2, 5, 3, 6, 1, 2),
(39, 3, 5, 4, 7, 3, 4),
(40, 4, 5, 5, 8, 5, 6),
(41, 5, 5, 6, 9, 7, 8);

-- --------------------------------------------------------

--
-- Table structure for table `Howell_6t22d`
--

CREATE TABLE `Howell_6t22d` (
  `ID` tinyint(4) DEFAULT NULL,
  `mvnt_numero` tinyint(4) DEFAULT NULL,
  `table_numero` tinyint(4) DEFAULT NULL,
  `equipe_NS` tinyint(4) DEFAULT NULL,
  `equipe_EO` tinyint(4) DEFAULT NULL,
  `numero_d1` tinyint(4) DEFAULT NULL,
  `numero_d2` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `Howell_6t22d`
--

INSERT INTO `Howell_6t22d` (`ID`, `mvnt_numero`, `table_numero`, `equipe_NS`, `equipe_EO`, `numero_d1`, `numero_d2`) VALUES
(1, 1, 1, 12, 11, 1, 2),
(2, 1, 2, 1, 10, 3, 4),
(3, 1, 3, 2, 9, 5, 6),
(4, 1, 4, 3, 8, 7, 8),
(5, 1, 5, 4, 7, 9, 10),
(6, 1, 6, 5, 6, 11, 12),
(7, 2, 1, 12, 10, 3, 4),
(8, 2, 2, 11, 9, 5, 6),
(9, 2, 3, 1, 8, 7, 8),
(10, 2, 4, 2, 7, 9, 10),
(11, 2, 5, 3, 6, 11, 12),
(12, 2, 6, 4, 5, 13, 14),
(13, 3, 1, 12, 9, 5, 6),
(14, 3, 2, 10, 8, 7, 8),
(15, 3, 3, 11, 7, 9, 10),
(16, 3, 4, 1, 6, 11, 12),
(17, 3, 5, 2, 5, 13, 14),
(18, 3, 6, 3, 4, 15, 16),
(19, 4, 1, 12, 8, 7, 8),
(20, 4, 2, 9, 7, 9, 10),
(21, 4, 3, 10, 6, 11, 12),
(22, 4, 4, 11, 5, 13, 14),
(23, 4, 5, 1, 4, 15, 16),
(24, 4, 6, 2, 3, 17, 18),
(25, 5, 1, 12, 7, 9, 10),
(26, 5, 2, 8, 6, 11, 12),
(27, 5, 3, 9, 5, 13, 14),
(28, 5, 4, 10, 4, 15, 16),
(29, 5, 5, 11, 3, 17, 18),
(30, 5, 6, 1, 2, 19, 20),
(31, 6, 1, 12, 6, 11, 12),
(32, 6, 2, 7, 5, 13, 14),
(33, 6, 3, 8, 4, 15, 16),
(34, 6, 4, 9, 3, 17, 18),
(35, 6, 5, 10, 2, 19, 20),
(36, 6, 6, 11, 1, 21, 22),
(37, 7, 1, 12, 5, 13, 14),
(38, 7, 2, 6, 4, 15, 16),
(39, 7, 3, 7, 3, 17, 18),
(40, 7, 4, 8, 2, 19, 20),
(41, 7, 5, 9, 1, 21, 22),
(42, 7, 6, 10, 11, 1, 2),
(43, 8, 1, 12, 4, 15, 16),
(44, 8, 2, 5, 3, 17, 18),
(45, 8, 3, 6, 2, 19, 20),
(46, 8, 4, 7, 1, 21, 22),
(47, 8, 5, 8, 11, 1, 2),
(48, 8, 6, 9, 10, 3, 4),
(49, 9, 1, 12, 3, 17, 18),
(50, 9, 2, 4, 2, 19, 20),
(51, 9, 3, 5, 1, 21, 22),
(52, 9, 4, 6, 11, 1, 2),
(53, 9, 5, 7, 10, 3, 4),
(54, 9, 6, 8, 9, 5, 6),
(55, 10, 1, 12, 2, 19, 20),
(56, 10, 2, 3, 1, 21, 22),
(57, 10, 3, 4, 11, 1, 2),
(58, 10, 4, 5, 10, 3, 4),
(59, 10, 5, 6, 9, 5, 6),
(60, 10, 6, 7, 8, 7, 8),
(61, 11, 1, 12, 1, 21, 22),
(62, 11, 2, 2, 11, 1, 2),
(63, 11, 3, 3, 10, 3, 4),
(64, 11, 4, 4, 9, 5, 6),
(65, 11, 5, 5, 8, 7, 8),
(66, 11, 6, 6, 7, 9, 10);

-- --------------------------------------------------------

--
-- Table structure for table `joueurs`
--

CREATE TABLE `joueurs` (
  `ID` tinyint(4) NOT NULL,
  `id_association` int(11) NOT NULL DEFAULT 1,
  `nom` varchar(10) DEFAULT NULL,
  `prenom` varchar(10) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `mdp_hash` varchar(255) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `joueurs`
--

INSERT INTO `joueurs` (`ID`, `id_association`, `nom`, `prenom`, `email`, `mdp_hash`, `actif`) VALUES
(1, 1, 'DUFRESNE', 'Marc', 'marco62118@gmail.com', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 1),
(2, 1, 'RUEL', 'Claude', NULL, NULL, 1),
(3, 1, 'BRENIERE', 'Christiane', NULL, NULL, 1),
(4, 1, 'NADET', 'nadet', NULL, NULL, 1),
(5, 1, 'BARBIER', 'François', NULL, NULL, 1),
(6, 1, 'BARBIER', 'Migueline', NULL, NULL, 1),
(7, 1, 'BERARD', 'Henri', NULL, NULL, 1),
(9, 1, 'BRENIERE', 'Yvon', NULL, NULL, 1),
(10, 1, 'BRUN', 'Monique', NULL, NULL, 1),
(12, 1, 'HERBAUT ', 'Christine', NULL, NULL, 1),
(13, 1, 'LAURAIN', 'Robert', NULL, NULL, 1),
(14, 1, 'LECLERC', 'André', NULL, NULL, 1),
(15, 1, 'LEFORT', 'M-Hélène', NULL, NULL, 1),
(16, 1, 'LONGCHAMP', 'Henri', NULL, NULL, 1),
(17, 1, 'LUBET', 'J.Pierre', NULL, NULL, 1),
(18, 1, 'MEGEVAND', 'Daniel', NULL, NULL, 1),
(19, 1, 'MICHEL', 'J.Marie', NULL, NULL, 1),
(26, 1, 'Walter', 'Roger', NULL, NULL, 1),
(27, 1, 'Vacanciers', 'Claude', NULL, NULL, 1),
(28, 1, 'Vacanciers', 'Jeanine', NULL, NULL, 1),
(29, 1, 'Relais', 'AA1', NULL, NULL, 1),
(30, 1, 'Relais', 'AA2', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `mains`
--

CREATE TABLE `mains` (
  `ID` smallint(6) NOT NULL,
  `id_association` int(11) NOT NULL DEFAULT 1,
  `carte1` varchar(3) DEFAULT NULL,
  `carte2` varchar(3) DEFAULT NULL,
  `carte3` varchar(3) DEFAULT NULL,
  `carte4` varchar(3) DEFAULT NULL,
  `carte5` varchar(3) DEFAULT NULL,
  `carte6` varchar(3) DEFAULT NULL,
  `carte7` varchar(3) DEFAULT NULL,
  `carte8` varchar(3) DEFAULT NULL,
  `carte9` varchar(3) DEFAULT NULL,
  `carte10` varchar(3) DEFAULT NULL,
  `carte11` varchar(3) DEFAULT NULL,
  `carte12` varchar(3) DEFAULT NULL,
  `carte13` varchar(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `resultats`
--

CREATE TABLE `resultats` (
  `ID` smallint(6) NOT NULL,
  `id_association` int(11) NOT NULL DEFAULT 1,
  `id_tournoi` smallint(6) DEFAULT NULL,
  `mvnt_numero` tinyint(4) DEFAULT NULL,
  `equipeNS` tinyint(4) DEFAULT NULL,
  `equipeEO` tinyint(4) DEFAULT NULL,
  `numero_table` tinyint(4) DEFAULT NULL,
  `numero_donne` tinyint(4) DEFAULT NULL,
  `id_donne` varchar(3) DEFAULT NULL,
  `id_enchere_premiere` varchar(4) DEFAULT NULL,
  `contrat` varchar(3) DEFAULT NULL,
  `declarant` varchar(5) DEFAULT NULL,
  `id_cartes_jouees` varchar(0) DEFAULT NULL,
  `resultat_contrat` varchar(1) DEFAULT NULL,
  `points` smallint(6) DEFAULT NULL,
  `pointsNS` varchar(3) DEFAULT NULL,
  `pointsEO` varchar(4) DEFAULT NULL,
  `nombre_pli` tinyint(4) DEFAULT NULL,
  `ptsNS` varchar(2) DEFAULT NULL,
  `ptsEO` varchar(1) DEFAULT NULL,
  `carteEntame` varchar(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `sessions_adherents`
--

CREATE TABLE `sessions_adherents` (
  `ID` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `id_joueur` int(11) NOT NULL,
  `id_association` int(11) NOT NULL,
  `expire` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions_adherents`
--

INSERT INTO `sessions_adherents` (`ID`, `token`, `id_joueur`, `id_association`, `expire`) VALUES
(1, '7388b7965dfc2fb8506a6c23a31660bfe892648b1db52673ed798b8f2262fe24', 1, 1, '2026-03-23 16:56:45'),
(2, '3b1657227b4fdd33f5fdff793fb1a840b6386863b98e42b0a21a92d8adb51321', 75, 1, '2026-03-23 17:22:03'),
(3, '0cbeb0fbdfe10e5a1af4fea19563dcb734dee2e2654f13b59f2e637593c6a1d4', 1, 1, '2026-03-23 22:16:50'),
(4, '87480b3313fa2ae6e40e449483010c2ba92f4361c5877b9f6000090f138ce99c', 76, 1, '2026-03-23 22:25:47');

-- --------------------------------------------------------

--
-- Table structure for table `sqlite_sequence`
--

CREATE TABLE `sqlite_sequence` (
  `name` varchar(12) DEFAULT NULL,
  `seq` smallint(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `sqlite_sequence`
--

INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES
('joueurs', 30),
('donnes_d_v', 21),
('mains', 563),
('donnes', 150),
('equipes', 622),
('tournois', 160),
('resultats', 1220),
('encheres', 2278),
('type', 5),
('Howell_3t20d', 15),
('Howell_3t10d', 15);

-- --------------------------------------------------------

--
-- Table structure for table `tournois`
--

CREATE TABLE `tournois` (
  `ID` smallint(6) NOT NULL,
  `id_association` int(11) NOT NULL DEFAULT 1,
  `date` varchar(10) DEFAULT NULL,
  `ouvert` tinyint(4) DEFAULT NULL,
  `mouvement` varchar(0) DEFAULT NULL,
  `nbre_enregistrement` tinyint(4) DEFAULT NULL,
  `nbre_donne` tinyint(4) DEFAULT NULL,
  `nbre_equipe` tinyint(4) DEFAULT NULL,
  `type` varchar(12) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `type`
--

CREATE TABLE `type` (
  `ID` tinyint(4) NOT NULL,
  `type` varchar(12) DEFAULT NULL,
  `nombre_table` tinyint(4) DEFAULT NULL,
  `nombre_donne` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `type`
--

INSERT INTO `type` (`ID`, `type`, `nombre_table`, `nombre_donne`) VALUES
(1, 'Howell_3t20d', 3, 20),
(2, 'Howell_4t21d', 4, 21),
(3, 'Howell_5t18d', 5, 18),
(4, 'Howell_6t22d', 6, 22),
(5, 'Howell_3t10d', 3, 10);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `associations`
--
ALTER TABLE `associations`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `token_api` (`token_api`);

--
-- Indexes for table `codes_invitation`
--
ALTER TABLE `codes_invitation`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `donnes`
--
ALTER TABLE `donnes`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `encheres`
--
ALTER TABLE `encheres`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `equipes`
--
ALTER TABLE `equipes`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `joueurs`
--
ALTER TABLE `joueurs`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `mains`
--
ALTER TABLE `mains`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `resultats`
--
ALTER TABLE `resultats`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `sessions_adherents`
--
ALTER TABLE `sessions_adherents`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Indexes for table `tournois`
--
ALTER TABLE `tournois`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `type`
--
ALTER TABLE `type`
  ADD PRIMARY KEY (`ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `associations`
--
ALTER TABLE `associations`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `codes_invitation`
--
ALTER TABLE `codes_invitation`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `donnes`
--
ALTER TABLE `donnes`
  MODIFY `ID` smallint(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encheres`
--
ALTER TABLE `encheres`
  MODIFY `ID` smallint(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipes`
--
ALTER TABLE `equipes`
  MODIFY `ID` smallint(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `joueurs`
--
ALTER TABLE `joueurs`
  MODIFY `ID` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `mains`
--
ALTER TABLE `mains`
  MODIFY `ID` smallint(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resultats`
--
ALTER TABLE `resultats`
  MODIFY `ID` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1430;

--
-- AUTO_INCREMENT for table `sessions_adherents`
--
ALTER TABLE `sessions_adherents`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tournois`
--
ALTER TABLE `tournois`
  MODIFY `ID` smallint(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `type`
--
ALTER TABLE `type`
  MODIFY `ID` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

-- --------------------------------------------------------

--
-- Structure for view `encheres199`
--
DROP TABLE IF EXISTS `encheres199`;

CREATE ALGORITHM=UNDEFINED DEFINER=`tournoi-bridge-online`@`%` SQL SECURITY DEFINER VIEW `encheres199`  AS SELECT `encheres`.`ID` AS `ID`, `encheres`.`id_tournoi` AS `id_tournoi`, `encheres`.`numero_donne` AS `numero_donne`, `encheres`.`equipeNS` AS `equipeNS`, `encheres`.`equipeEO` AS `equipeEO`, `encheres`.`ordre` AS `ordre`, `encheres`.`joueur` AS `joueur`, `encheres`.`annonce` AS `annonce`, `encheres`.`id_enchere_suivante` AS `id_enchere_suivante` FROM `encheres` WHERE `encheres`.`id_tournoi` = 199 ORDER BY `encheres`.`ID` ASC ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
