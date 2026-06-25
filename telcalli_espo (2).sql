-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Creato il: Mag 22, 2026 alle 19:03
-- Versione del server: 11.4.11-MariaDB
-- Versione PHP: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `telcalli_espo`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `account`
--

CREATE TABLE `account` (
  `id` varchar(24) NOT NULL,
  `name` varchar(249) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `website` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `sic_code` varchar(40) DEFAULT NULL,
  `billing_address_street` varchar(255) DEFAULT NULL,
  `billing_address_city` varchar(100) DEFAULT NULL,
  `billing_address_state` varchar(100) DEFAULT NULL,
  `billing_address_country` varchar(100) DEFAULT NULL,
  `billing_address_postal_code` varchar(40) DEFAULT NULL,
  `shipping_address_street` varchar(255) DEFAULT NULL,
  `shipping_address_city` varchar(100) DEFAULT NULL,
  `shipping_address_state` varchar(100) DEFAULT NULL,
  `shipping_address_country` varchar(100) DEFAULT NULL,
  `shipping_address_postal_code` varchar(40) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `codice_cliente` varchar(255) DEFAULT NULL,
  `partita_i_v_a` varchar(255) DEFAULT NULL,
  `codice_p_o_s` varchar(255) DEFAULT NULL,
  `mese_importazione_c_b` varchar(255) DEFAULT NULL,
  `cluster` varchar(255) DEFAULT NULL,
  `b2_b` tinyint(1) NOT NULL DEFAULT 0,
  `consistenze` mediumtext DEFAULT '[]',
  `segmento` varchar(255) DEFAULT NULL,
  `whats_app` varchar(255) DEFAULT NULL,
  `stato` varchar(255) DEFAULT 'Nuovo',
  `azienda` mediumtext DEFAULT NULL,
  `stream_updated_at` datetime DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL,
  `tax_number` varchar(32) DEFAULT NULL,
  `electronic_address_scheme` varchar(4) DEFAULT NULL,
  `electronic_address_identifier` varchar(255) DEFAULT NULL,
  `price_book_id` varchar(24) DEFAULT NULL,
  `payment_terms_profile_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `account_contact`
--

CREATE TABLE `account_contact` (
  `id` bigint(20) NOT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `is_inactive` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `account_document`
--

CREATE TABLE `account_document` (
  `id` bigint(20) NOT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `document_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `account_payment_method`
--

CREATE TABLE `account_payment_method` (
  `id` bigint(20) NOT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `account_portal_user`
--

CREATE TABLE `account_portal_user` (
  `id` bigint(20) NOT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `account_target_list`
--

CREATE TABLE `account_target_list` (
  `id` bigint(20) NOT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `opted_out` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `action_history_record`
--

CREATE TABLE `action_history_record` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `data` mediumtext DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `ip_address` varchar(39) DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `auth_token_id` varchar(24) DEFAULT NULL,
  `auth_log_record_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `address_country`
--

CREATE TABLE `address_country` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_preferred` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `appuntamento`
--

CREATE TABLE `appuntamento` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Planned',
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `is_all_day` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `date_start_date` date DEFAULT NULL,
  `date_end_date` date DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `c_a_p_id` varchar(24) DEFAULT NULL,
  `prospect_id` varchar(24) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `data_appuntamento` date DEFAULT NULL,
  `esito` varchar(255) DEFAULT NULL,
  `indirizzo_street` varchar(255) DEFAULT NULL,
  `indirizzo_city` varchar(100) DEFAULT NULL,
  `indirizzo_state` varchar(100) DEFAULT NULL,
  `indirizzo_country` varchar(100) DEFAULT NULL,
  `indirizzo_postal_code` varchar(40) DEFAULT NULL,
  `note_consulente` mediumtext DEFAULT NULL,
  `note_esito` mediumtext DEFAULT NULL,
  `sottostato` varchar(255) DEFAULT NULL,
  `tipo` mediumtext DEFAULT NULL,
  `azienda` varchar(255) DEFAULT NULL,
  `video_call_telefonico` tinyint(1) NOT NULL DEFAULT 0,
  `call_center` varchar(255) DEFAULT NULL,
  `linea_prodotto` varchar(255) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `z_t_l` tinyint(1) NOT NULL DEFAULT 0,
  `sorgente` varchar(100) DEFAULT 'Outlook',
  `impegno_id` varchar(24) DEFAULT NULL,
  `meeting_ref` varchar(100) DEFAULT NULL,
  `sync_con_google` tinyint(1) NOT NULL DEFAULT 0,
  `note_call_center` mediumtext DEFAULT NULL,
  `hook_version` varchar(100) DEFAULT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `brand_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_id` varchar(24) DEFAULT NULL,
  `product_brand_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_name` varchar(255) DEFAULT NULL,
  `product_brand_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `app_log_record`
--

CREATE TABLE `app_log_record` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `message` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` int(11) DEFAULT NULL,
  `exception_class` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line` int(11) DEFAULT NULL,
  `request_method` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_resource_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `app_secret`
--

CREATE TABLE `app_secret` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `delete_id` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `area`
--

CREATE TABLE `area` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `macro_area_id` varchar(24) DEFAULT NULL,
  `codice_area` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `array_value`
--

CREATE TABLE `array_value` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `value` varchar(100) DEFAULT NULL,
  `attribute` varchar(100) DEFAULT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `attachment`
--

CREATE TABLE `attachment` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `type` varchar(100) DEFAULT NULL,
  `size` bigint(20) DEFAULT NULL,
  `source_id` varchar(24) DEFAULT NULL,
  `field` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `role` varchar(36) DEFAULT NULL,
  `storage` varchar(24) DEFAULT NULL,
  `storage_file_path` varchar(260) DEFAULT NULL,
  `global` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `related_id` varchar(24) DEFAULT NULL,
  `related_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `is_being_uploaded` tinyint(1) NOT NULL DEFAULT 0,
  `modified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `authentication_provider`
--

CREATE TABLE `authentication_provider` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oidc_client_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oidc_client_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oidc_authorization_endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oidc_token_endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oidc_jwks_endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oidc_jwt_signature_algorithm_list` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '["RS256"]',
  `oidc_scopes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '["profile","email","phone"]',
  `oidc_create_user` tinyint(1) NOT NULL DEFAULT 0,
  `oidc_username_claim` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'sub',
  `oidc_sync` tinyint(1) NOT NULL DEFAULT 0,
  `oidc_logout_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oidc_authorization_prompt` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oidc_user_info_endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `auth_log_record`
--

CREATE TABLE `auth_log_record` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `username` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `is_denied` tinyint(1) NOT NULL DEFAULT 0,
  `denial_reason` varchar(255) DEFAULT NULL,
  `request_time` double DEFAULT NULL,
  `request_url` varchar(255) DEFAULT NULL,
  `request_method` varchar(15) DEFAULT NULL,
  `authentication_method` varchar(255) DEFAULT NULL,
  `portal_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `auth_token_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `auth_token`
--

CREATE TABLE `auth_token` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `token` varchar(36) DEFAULT NULL,
  `hash` varchar(150) DEFAULT NULL,
  `secret` varchar(36) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_access` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `portal_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `autofollow`
--

CREATE TABLE `autofollow` (
  `id` int(11) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bpmn_flowchart`
--

CREATE TABLE `bpmn_flowchart` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `target_type` varchar(255) DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  `elements_data_hash` mediumtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `has_none_start_event` tinyint(1) NOT NULL DEFAULT 0,
  `event_start_id_list` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `event_start_all_id_list` mediumtext DEFAULT NULL,
  `category_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bpmn_flowchart_category`
--

CREATE TABLE `bpmn_flowchart_category` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bpmn_flowchart_category_path`
--

CREATE TABLE `bpmn_flowchart_category_path` (
  `id` int(11) NOT NULL,
  `ascendor_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descendor_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bpmn_flow_node`
--

CREATE TABLE `bpmn_flow_node` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(36) DEFAULT NULL,
  `element_id` varchar(36) DEFAULT NULL,
  `element_type` varchar(36) DEFAULT NULL,
  `element_data` mediumtext DEFAULT NULL,
  `previous_flow_node_element_type` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `proceed_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `divergent_flow_node_id` varchar(24) DEFAULT NULL,
  `previous_flow_node_id` varchar(24) DEFAULT NULL,
  `flowchart_id` varchar(24) DEFAULT NULL,
  `process_id` varchar(24) DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  `is_deferred` tinyint(1) NOT NULL DEFAULT 0,
  `deferred_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bpmn_process`
--

CREATE TABLE `bpmn_process` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Created',
  `target_type` varchar(100) DEFAULT NULL,
  `flowchart_data` mediumtext DEFAULT NULL,
  `start_element_id` varchar(24) DEFAULT NULL,
  `flowchart_elements_data_hash` mediumtext DEFAULT NULL,
  `created_entities_data` mediumtext DEFAULT NULL,
  `variables` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `flowchart_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `workflow_id` varchar(36) DEFAULT NULL,
  `parent_process_id` varchar(24) DEFAULT NULL,
  `parent_process_flow_node_id` varchar(24) DEFAULT NULL,
  `root_process_id` varchar(24) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `visit_timestamp` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bpmn_signal_listener`
--

CREATE TABLE `bpmn_signal_listener` (
  `id` varchar(24) NOT NULL,
  `name` varchar(200) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `is_triggered` tinyint(1) NOT NULL DEFAULT 0,
  `triggered_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `flow_node_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bpmn_user_task`
--

CREATE TABLE `bpmn_user_task` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `action_type` varchar(255) DEFAULT NULL,
  `resolution` varchar(255) DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `resolution_note` mediumtext DEFAULT NULL,
  `is_canceled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `process_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `flow_node_id` varchar(24) DEFAULT NULL,
  `instructions` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `brand`
--

CREATE TABLE `brand` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `codice` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `colore` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `partner_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stream_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `call`
--

CREATE TABLE `call` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Planned',
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `direction` varchar(255) DEFAULT 'Outbound',
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `nota` mediumtext DEFAULT NULL,
  `whats_app` tinyint(1) NOT NULL DEFAULT 0,
  `telefono` varchar(255) DEFAULT NULL,
  `testo` mediumtext DEFAULT NULL,
  `tipologia` varchar(255) DEFAULT NULL,
  `vocale` tinyint(1) NOT NULL DEFAULT 0,
  `whats_app_numero` varchar(255) DEFAULT NULL,
  `data` date DEFAULT NULL,
  `prospect_id` varchar(24) DEFAULT NULL,
  `data_richiamo` datetime DEFAULT NULL,
  `richiamo` varchar(255) DEFAULT NULL,
  `da_richiamare` tinyint(1) NOT NULL DEFAULT 0,
  `uid` varchar(255) DEFAULT NULL,
  `google_calendar_event_id` varchar(384) DEFAULT NULL,
  `google_calendar_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `call_contact`
--

CREATE TABLE `call_contact` (
  `id` bigint(20) NOT NULL,
  `call_id` varchar(24) DEFAULT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `status` varchar(36) DEFAULT 'None',
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `call_lead`
--

CREATE TABLE `call_lead` (
  `id` bigint(20) NOT NULL,
  `call_id` varchar(24) DEFAULT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `status` varchar(36) DEFAULT 'None',
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `call_user`
--

CREATE TABLE `call_user` (
  `id` bigint(20) NOT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `call_id` varchar(24) DEFAULT NULL,
  `status` varchar(36) DEFAULT 'None',
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `campaign`
--

CREATE TABLE `campaign` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Planning',
  `type` varchar(64) DEFAULT 'Email',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `budget` double DEFAULT NULL,
  `mail_merge_only_with_address` tinyint(1) NOT NULL DEFAULT 1,
  `budget_currency` varchar(3) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `contacts_template_id` varchar(24) DEFAULT NULL,
  `leads_template_id` varchar(24) DEFAULT NULL,
  `accounts_template_id` varchar(24) DEFAULT NULL,
  `users_template_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `campaign_log_record`
--

CREATE TABLE `campaign_log_record` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `action` varchar(50) DEFAULT NULL,
  `action_date` datetime DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  `string_data` varchar(100) DEFAULT NULL,
  `string_additional_data` varchar(100) DEFAULT NULL,
  `application` varchar(36) DEFAULT 'Espo',
  `created_at` datetime DEFAULT NULL,
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_id` varchar(24) DEFAULT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `object_id` varchar(24) DEFAULT NULL,
  `object_type` varchar(100) DEFAULT NULL,
  `queue_item_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `campaign_target_list`
--

CREATE TABLE `campaign_target_list` (
  `id` bigint(20) NOT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `campaign_target_list_excluding`
--

CREATE TABLE `campaign_target_list_excluding` (
  `id` bigint(20) NOT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `campaign_tracking_url`
--

CREATE TABLE `campaign_tracking_url` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `url` varchar(255) DEFAULT NULL,
  `action` varchar(12) DEFAULT 'Redirect',
  `message` mediumtext DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `case`
--

CREATE TABLE `case` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` int(10) UNSIGNED NOT NULL,
  `status` varchar(255) DEFAULT 'New',
  `priority` varchar(255) DEFAULT 'Normal',
  `type` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `inbound_email_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `stream_updated_at` datetime DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `case_contact`
--

CREATE TABLE `case_contact` (
  `id` bigint(20) NOT NULL,
  `case_id` varchar(24) DEFAULT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `case_knowledge_base_article`
--

CREATE TABLE `case_knowledge_base_article` (
  `id` bigint(20) NOT NULL,
  `case_id` varchar(24) DEFAULT NULL,
  `knowledge_base_article_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `contact`
--

CREATE TABLE `contact` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `salutation_name` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `do_not_call` tinyint(1) NOT NULL DEFAULT 0,
  `address_street` varchar(255) DEFAULT NULL,
  `address_city` varchar(100) DEFAULT NULL,
  `address_state` varchar(100) DEFAULT NULL,
  `address_country` varchar(100) DEFAULT NULL,
  `address_postal_code` varchar(40) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `stream_updated_at` datetime DEFAULT NULL,
  `fornitore_partner_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `contact_document`
--

CREATE TABLE `contact_document` (
  `id` bigint(20) NOT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `document_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `contact_meeting`
--

CREATE TABLE `contact_meeting` (
  `id` bigint(20) NOT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `meeting_id` varchar(24) DEFAULT NULL,
  `status` varchar(36) DEFAULT 'None',
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `contact_opportunity`
--

CREATE TABLE `contact_opportunity` (
  `id` bigint(20) NOT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `opportunity_id` varchar(24) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `contact_target_list`
--

CREATE TABLE `contact_target_list` (
  `id` bigint(20) NOT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `opted_out` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `contratto`
--

CREATE TABLE `contratto` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `stream_updated_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `importo_totale` double DEFAULT NULL,
  `importo_totale_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_v_a` double DEFAULT NULL,
  `modalit_di_pagamento` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caparra` double DEFAULT NULL,
  `caparra_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saldo` double DEFAULT NULL,
  `saldo_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `importo_i_v_a_esclusa` double DEFAULT NULL,
  `importo_i_v_a_esclusa_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `credit_note`
--

CREATE TABLE `credit_note` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `applied_to_invoice` tinyint(1) NOT NULL DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_draft_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `date_issued` date DEFAULT NULL,
  `date_due` date DEFAULT NULL,
  `posting_date` date DEFAULT NULL,
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `tax_amount` double DEFAULT NULL,
  `rounding_amount` decimal(13,4) DEFAULT NULL,
  `amount` decimal(13,4) DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `shipping_amount` decimal(13,4) DEFAULT NULL,
  `shipping_tax_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total_amount` decimal(13,4) DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `grand_total_amount_local` decimal(13,3) DEFAULT NULL,
  `shipping_amount_local` decimal(13,3) DEFAULT NULL,
  `tax_amount_local` decimal(13,3) DEFAULT NULL,
  `rounding_amount_local` decimal(13,3) DEFAULT NULL,
  `currency_rate` decimal(20,8) DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_issued` tinyint(1) NOT NULL DEFAULT 0,
  `was_issued` tinyint(1) NOT NULL DEFAULT 0,
  `issued_at` datetime DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `rounding_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rounding_profile_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `credit_note_item`
--

CREATE TABLE `credit_note_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `unit_price` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `unit_price_net` decimal(13,4) DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `tax_amount` decimal(13,4) DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `unit_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `period_start_date` date DEFAULT NULL,
  `period_end_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `credit_note_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `credit_note_subscription_update`
--

CREATE TABLE `credit_note_subscription_update` (
  `id` bigint(20) NOT NULL,
  `credit_note_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_update_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `currency`
--

CREATE TABLE `currency` (
  `id` varchar(3) NOT NULL,
  `rate` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `currency_record`
--

CREATE TABLE `currency_record` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `delete_id` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `currency_record_rate`
--

CREATE TABLE `currency_record_rate` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `base_code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `delete_id` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `record_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate` decimal(20,8) DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `c_a_p`
--

CREATE TABLE `c_a_p` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `macro_area_id` varchar(24) DEFAULT NULL,
  `area_id` varchar(24) DEFAULT NULL,
  `zona_id` varchar(24) DEFAULT NULL,
  `codice_c_a_p` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `dashboard_template`
--

CREATE TABLE `dashboard_template` (
  `id` varchar(24) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `layout` mediumtext DEFAULT NULL,
  `dashlets_options` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `delivery_order`
--

CREATE TABLE `delivery_order` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `date_ordered` date DEFAULT NULL,
  `shipping_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_hard_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `shipping_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `delivery_order_item`
--

CREATE TABLE `delivery_order_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `unit_weight` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `delivery_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_number_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `disponibilita`
--

CREATE TABLE `disponibilita` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Presente',
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `is_all_day` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `date_start_date` date DEFAULT NULL,
  `date_end_date` date DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `azienda` varchar(255) DEFAULT NULL,
  `area` mediumtext DEFAULT NULL,
  `datadisponibilita` date DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `orario_inizio` datetime DEFAULT NULL,
  `orario_fine` datetime DEFAULT NULL,
  `brand_id` varchar(24) DEFAULT NULL,
  `hook_version` varchar(100) DEFAULT NULL,
  `ricorrenza_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `disponibilt_ricorrente2`
--

CREATE TABLE `disponibilt_ricorrente2` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `document`
--

CREATE TABLE `document` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Active',
  `type` varchar(255) DEFAULT NULL,
  `publish_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `file_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `folder_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `document_folder`
--

CREATE TABLE `document_folder` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `document_folder_path`
--

CREATE TABLE `document_folder_path` (
  `id` int(11) NOT NULL,
  `ascendor_id` varchar(24) DEFAULT NULL,
  `descendor_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `document_lead`
--

CREATE TABLE `document_lead` (
  `id` bigint(20) NOT NULL,
  `document_id` varchar(24) DEFAULT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `document_opportunity`
--

CREATE TABLE `document_opportunity` (
  `id` bigint(20) NOT NULL,
  `document_id` varchar(24) DEFAULT NULL,
  `opportunity_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email`
--

CREATE TABLE `email` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `from_string` varchar(255) DEFAULT NULL,
  `reply_to_string` varchar(255) DEFAULT NULL,
  `address_name_map` mediumtext DEFAULT NULL,
  `is_replied` tinyint(1) NOT NULL DEFAULT 0,
  `message_id` varchar(255) DEFAULT NULL,
  `message_id_internal` varchar(300) DEFAULT NULL,
  `body_plain` mediumtext DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `is_html` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(255) DEFAULT 'Archived',
  `has_attachment` tinyint(1) NOT NULL DEFAULT 0,
  `date_sent` datetime DEFAULT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `from_email_address_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `sent_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `replied_id` varchar(24) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `ics_contents` mediumtext DEFAULT NULL,
  `ics_event_uid` varchar(255) DEFAULT NULL,
  `created_event_id` varchar(24) DEFAULT NULL,
  `created_event_type` varchar(100) DEFAULT NULL,
  `group_folder_id` varchar(24) DEFAULT NULL,
  `send_at` datetime DEFAULT NULL,
  `group_status_folder` varchar(7) DEFAULT NULL,
  `is_auto_reply` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_account`
--

CREATE TABLE `email_account` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `email_address` varchar(100) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Active',
  `host` varchar(255) DEFAULT NULL,
  `port` int(11) DEFAULT 993,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `monitored_folders` mediumtext DEFAULT '["INBOX"]',
  `sent_folder` varchar(255) DEFAULT NULL,
  `store_sent_emails` tinyint(1) NOT NULL DEFAULT 0,
  `keep_fetched_emails_unread` tinyint(1) NOT NULL DEFAULT 0,
  `fetch_since` date DEFAULT NULL,
  `fetch_data` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `use_imap` tinyint(1) NOT NULL DEFAULT 1,
  `use_smtp` tinyint(1) NOT NULL DEFAULT 0,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT 587,
  `smtp_auth` tinyint(1) NOT NULL DEFAULT 1,
  `smtp_security` varchar(255) DEFAULT 'TLS',
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_auth_mechanism` varchar(255) DEFAULT 'login',
  `imap_handler` varchar(255) DEFAULT NULL,
  `smtp_handler` varchar(255) DEFAULT NULL,
  `email_folder_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `security` varchar(255) DEFAULT 'SSL',
  `connected_at` datetime DEFAULT NULL,
  `folder_map` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_address`
--

CREATE TABLE `email_address` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `lower` varchar(255) DEFAULT NULL,
  `invalid` tinyint(1) NOT NULL DEFAULT 0,
  `opt_out` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_email_account`
--

CREATE TABLE `email_email_account` (
  `id` bigint(20) NOT NULL,
  `email_id` varchar(24) DEFAULT NULL,
  `email_account_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_email_address`
--

CREATE TABLE `email_email_address` (
  `id` bigint(20) NOT NULL,
  `email_id` varchar(24) DEFAULT NULL,
  `email_address_id` varchar(24) DEFAULT NULL,
  `address_type` varchar(4) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_filter`
--

CREATE TABLE `email_filter` (
  `id` varchar(24) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `from` varchar(255) DEFAULT NULL,
  `to` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body_contains` mediumtext DEFAULT NULL,
  `is_global` tinyint(1) NOT NULL DEFAULT 0,
  `action` varchar(255) DEFAULT 'Skip',
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `email_folder_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `mark_as_read` tinyint(1) NOT NULL DEFAULT 0,
  `group_email_folder_id` varchar(24) DEFAULT NULL,
  `body_contains_all` mediumtext DEFAULT NULL,
  `skip_notification` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_folder`
--

CREATE TABLE `email_folder` (
  `id` varchar(24) NOT NULL,
  `name` varchar(64) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `skip_notifications` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_inbound_email`
--

CREATE TABLE `email_inbound_email` (
  `id` bigint(20) NOT NULL,
  `email_id` varchar(24) DEFAULT NULL,
  `inbound_email_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_queue_item`
--

CREATE TABLE `email_queue_item` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT NULL,
  `attempt_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `email_address` varchar(255) DEFAULT NULL,
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `mass_email_id` varchar(24) DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_template`
--

CREATE TABLE `email_template` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `subject` varchar(255) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `is_html` tinyint(1) NOT NULL DEFAULT 1,
  `one_off` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `category_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL,
  `status` varchar(8) DEFAULT 'Active',
  `description` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_template_category`
--

CREATE TABLE `email_template_category` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_template_category_path`
--

CREATE TABLE `email_template_category_path` (
  `id` int(11) NOT NULL,
  `ascendor_id` varchar(24) DEFAULT NULL,
  `descendor_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `email_user`
--

CREATE TABLE `email_user` (
  `id` bigint(20) NOT NULL,
  `email_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_important` tinyint(1) DEFAULT 0,
  `in_trash` tinyint(1) DEFAULT 0,
  `folder_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `in_archive` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `entity_collaborator`
--

CREATE TABLE `entity_collaborator` (
  `id` bigint(20) NOT NULL,
  `entity_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `entity_email_address`
--

CREATE TABLE `entity_email_address` (
  `id` bigint(20) NOT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `email_address_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `primary` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `entity_phone_number`
--

CREATE TABLE `entity_phone_number` (
  `id` bigint(20) NOT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `phone_number_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `primary` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `entity_team`
--

CREATE TABLE `entity_team` (
  `id` bigint(20) NOT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `team_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `entity_user`
--

CREATE TABLE `entity_user` (
  `id` bigint(20) NOT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `eu_tax_mapping`
--

CREATE TABLE `eu_tax_mapping` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `category_code` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exemption_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exemption_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_class_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `evento`
--

CREATE TABLE `evento` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Planned',
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `is_all_day` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `date_start_date` date DEFAULT NULL,
  `date_end_date` date DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `export`
--

CREATE TABLE `export` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Pending',
  `params` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `notify_on_finish` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_id` varchar(24) DEFAULT NULL,
  `attachment_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `extension`
--

CREATE TABLE `extension` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `version` varchar(50) DEFAULT NULL,
  `file_list` mediumtext DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `is_installed` tinyint(1) NOT NULL DEFAULT 0,
  `check_version_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `license_status` varchar(36) DEFAULT NULL,
  `license_status_message` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `external_account`
--

CREATE TABLE `external_account` (
  `id` varchar(64) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `data` mediumtext DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fattura_attiva`
--

CREATE TABLE `fattura_attiva` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornitore_partner_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fornitore_partner`
--

CREATE TABLE `fornitore_partner` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disponibilita_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `google_calendar`
--

CREATE TABLE `google_calendar` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `calendar_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `google_calendar_event`
--

CREATE TABLE `google_calendar_event` (
  `id` varchar(24) NOT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `google_calendar_id` varchar(24) DEFAULT NULL,
  `google_calendar_event_id` varchar(384) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `google_calendar_recurrent_event`
--

CREATE TABLE `google_calendar_recurrent_event` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `page_token` mediumtext DEFAULT NULL,
  `last_loaded_event_time` datetime DEFAULT NULL,
  `event_id` varchar(255) DEFAULT NULL,
  `google_calendar_user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `google_calendar_user`
--

CREATE TABLE `google_calendar_user` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `role` varchar(255) DEFAULT 'owner',
  `type` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sync_token` mediumtext DEFAULT NULL,
  `page_token` mediumtext DEFAULT NULL,
  `last_looked` datetime DEFAULT NULL,
  `last_sync` varchar(255) DEFAULT NULL,
  `google_calendar_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `google_contacts_group`
--

CREATE TABLE `google_contacts_group` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `group_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `google_contacts_pair`
--

CREATE TABLE `google_contacts_pair` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `google_account_email` varchar(255) DEFAULT NULL,
  `google_contact_id` varchar(255) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `resource_name` varchar(255) DEFAULT NULL,
  `etag` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `google_contacts_user`
--

CREATE TABLE `google_contacts_user` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `type` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_looked` datetime DEFAULT NULL,
  `last_sync` varchar(255) DEFAULT NULL,
  `google_contacts_group_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `group_email_folder`
--

CREATE TABLE `group_email_folder` (
  `id` varchar(24) NOT NULL,
  `name` varchar(64) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `group_email_folder_team`
--

CREATE TABLE `group_email_folder_team` (
  `id` bigint(20) NOT NULL,
  `group_email_folder_id` varchar(24) DEFAULT NULL,
  `team_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `import`
--

CREATE TABLE `import` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `entity_type` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `file_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `last_index` int(11) DEFAULT NULL,
  `params` mediumtext DEFAULT NULL,
  `attribute_list` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `import_entity`
--

CREATE TABLE `import_entity` (
  `id` bigint(20) NOT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `import_id` varchar(24) DEFAULT NULL,
  `is_imported` tinyint(1) NOT NULL DEFAULT 0,
  `is_updated` tinyint(1) NOT NULL DEFAULT 0,
  `is_duplicate` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `import_error`
--

CREATE TABLE `import_error` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `row_index` int(11) DEFAULT NULL,
  `export_row_index` int(11) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `validation_failures` mediumtext DEFAULT NULL,
  `row` mediumtext DEFAULT NULL,
  `import_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `inbound_email`
--

CREATE TABLE `inbound_email` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `email_address` varchar(100) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Active',
  `host` varchar(255) DEFAULT NULL,
  `port` int(11) DEFAULT 993,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `monitored_folders` mediumtext DEFAULT '["INBOX"]',
  `fetch_since` date DEFAULT NULL,
  `fetch_data` mediumtext DEFAULT NULL,
  `add_all_team_users` tinyint(1) NOT NULL DEFAULT 1,
  `sent_folder` varchar(255) DEFAULT NULL,
  `store_sent_emails` tinyint(1) NOT NULL DEFAULT 0,
  `keep_fetched_emails_unread` tinyint(1) NOT NULL DEFAULT 0,
  `use_imap` tinyint(1) NOT NULL DEFAULT 1,
  `use_smtp` tinyint(1) NOT NULL DEFAULT 0,
  `smtp_is_shared` tinyint(1) NOT NULL DEFAULT 0,
  `smtp_is_for_mass_email` tinyint(1) NOT NULL DEFAULT 0,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT 587,
  `smtp_auth` tinyint(1) NOT NULL DEFAULT 1,
  `smtp_security` varchar(255) DEFAULT 'TLS',
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_auth_mechanism` varchar(255) DEFAULT 'login',
  `create_case` tinyint(1) NOT NULL DEFAULT 0,
  `case_distribution` varchar(255) DEFAULT 'Direct-Assignment',
  `target_user_position` varchar(255) DEFAULT NULL,
  `reply` tinyint(1) NOT NULL DEFAULT 0,
  `reply_from_address` varchar(255) DEFAULT NULL,
  `reply_to_address` varchar(255) DEFAULT NULL,
  `reply_from_name` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `imap_handler` varchar(255) DEFAULT NULL,
  `smtp_handler` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `assign_to_user_id` varchar(24) DEFAULT NULL,
  `team_id` varchar(24) DEFAULT NULL,
  `reply_email_template_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `security` varchar(255) DEFAULT 'SSL',
  `group_email_folder_id` varchar(24) DEFAULT NULL,
  `connected_at` datetime DEFAULT NULL,
  `exclude_from_reply` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `inbound_email_team`
--

CREATE TABLE `inbound_email_team` (
  `id` bigint(20) NOT NULL,
  `inbound_email_id` varchar(24) DEFAULT NULL,
  `team_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `integration`
--

CREATE TABLE `integration` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `data` mediumtext DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `inventory_adjustment`
--

CREATE TABLE `inventory_adjustment` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `warehouse_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `inventory_adjustment_item`
--

CREATE TABLE `inventory_adjustment_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `new_quantity_on_hand` double DEFAULT NULL,
  `quantity` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `inventory_adjustment_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_number_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `inventory_number`
--

CREATE TABLE `inventory_number` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Batch',
  `incoming_date` date DEFAULT NULL,
  `production_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `delete_id` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `inventory_transaction`
--

CREATE TABLE `inventory_transaction` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `type` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Transfer',
  `number` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(13,4) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `parent_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_number_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `inviti_a_fatturare`
--

CREATE TABLE `inviti_a_fatturare` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quote_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `invoice`
--

CREATE TABLE `invoice` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_draft_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_debit_note_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Invoice',
  `date_invoiced` date DEFAULT NULL,
  `date_due` date DEFAULT NULL,
  `posting_date` date DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_terms_note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `shipping_amount` decimal(13,4) DEFAULT NULL,
  `shipping_tax_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount` double DEFAULT NULL,
  `rounding_amount` decimal(13,4) DEFAULT NULL,
  `discount_amount` double DEFAULT NULL,
  `amount` decimal(13,4) DEFAULT NULL,
  `pre_discounted_amount` double DEFAULT NULL,
  `grand_total_amount` decimal(13,4) DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `grand_total_amount_local` decimal(13,3) DEFAULT NULL,
  `shipping_amount_local` decimal(13,3) DEFAULT NULL,
  `tax_amount_local` decimal(13,3) DEFAULT NULL,
  `rounding_amount_local` decimal(13,3) DEFAULT NULL,
  `currency_rate` decimal(20,8) DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pre_discounted_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `buyer_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_issued` tinyint(1) NOT NULL DEFAULT 0,
  `was_issued` tinyint(1) NOT NULL DEFAULT 0,
  `issued_at` datetime DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `billing_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rounding_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opportunity_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quote_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preceding_invoice_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rounding_profile_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_terms_profile_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_book_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `invoice_item`
--

CREATE TABLE `invoice_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `list_price` double DEFAULT NULL,
  `unit_price` double DEFAULT NULL,
  `discount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `unit_weight` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `unit_price_net` decimal(13,4) DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `tax_amount` decimal(13,4) DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `unit_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `list_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `period_start_date` date DEFAULT NULL,
  `period_end_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `invoice_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `invoice_payment_method`
--

CREATE TABLE `invoice_payment_method` (
  `id` bigint(20) NOT NULL,
  `invoice_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `invoice_payment_request`
--

CREATE TABLE `invoice_payment_request` (
  `id` bigint(20) NOT NULL,
  `invoice_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_request_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `invoice_subscription_period`
--

CREATE TABLE `invoice_subscription_period` (
  `id` bigint(20) NOT NULL,
  `invoice_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_period_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `invoice_subscription_update`
--

CREATE TABLE `invoice_subscription_update` (
  `id` bigint(20) NOT NULL,
  `invoice_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_update_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `job`
--

CREATE TABLE `job` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(16) DEFAULT 'Pending',
  `execute_time` datetime DEFAULT NULL,
  `number` bigint(20) UNSIGNED NOT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `method_name` varchar(100) DEFAULT NULL,
  `job` varchar(255) DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  `queue` varchar(36) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `pid` int(11) DEFAULT NULL,
  `attempts` int(11) DEFAULT NULL,
  `target_id` varchar(48) DEFAULT NULL,
  `target_type` varchar(64) DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `scheduled_job_id` varchar(24) DEFAULT NULL,
  `class_name` varchar(255) DEFAULT NULL,
  `group` varchar(128) DEFAULT NULL,
  `target_group` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `kanban_order`
--

CREATE TABLE `kanban_order` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` smallint(6) DEFAULT NULL,
  `group` varchar(100) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `knowledge_base_article`
--

CREATE TABLE `knowledge_base_article` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Draft',
  `language` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT 'Article',
  `publish_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL,
  `body_plain` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `knowledge_base_article_knowledge_base_category`
--

CREATE TABLE `knowledge_base_article_knowledge_base_category` (
  `id` bigint(20) NOT NULL,
  `knowledge_base_article_id` varchar(24) DEFAULT NULL,
  `knowledge_base_category_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `knowledge_base_article_portal`
--

CREATE TABLE `knowledge_base_article_portal` (
  `id` bigint(20) NOT NULL,
  `portal_id` varchar(24) DEFAULT NULL,
  `knowledge_base_article_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `knowledge_base_category`
--

CREATE TABLE `knowledge_base_category` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `knowledge_base_category_path`
--

CREATE TABLE `knowledge_base_category_path` (
  `id` int(11) NOT NULL,
  `ascendor_id` varchar(24) DEFAULT NULL,
  `descendor_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `layout_record`
--

CREATE TABLE `layout_record` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `data` mediumtext DEFAULT NULL,
  `layout_set_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `layout_set`
--

CREATE TABLE `layout_set` (
  `id` varchar(24) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `layout_list` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `lead`
--

CREATE TABLE `lead` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `salutation_name` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT 'Call Center',
  `industry` varchar(255) DEFAULT NULL,
  `opportunity_amount` double DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address_street` varchar(255) DEFAULT NULL,
  `address_city` varchar(100) DEFAULT NULL,
  `address_state` varchar(100) DEFAULT NULL,
  `address_country` varchar(100) DEFAULT NULL,
  `address_postal_code` varchar(40) DEFAULT NULL,
  `do_not_call` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `opportunity_amount_currency` varchar(3) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `created_account_id` varchar(24) DEFAULT NULL,
  `created_contact_id` varchar(24) DEFAULT NULL,
  `created_opportunity_id` varchar(24) DEFAULT NULL,
  `b2_b` tinyint(1) NOT NULL DEFAULT 0,
  `c_a_p_id` varchar(24) DEFAULT NULL,
  `appuntamento_id` varchar(24) DEFAULT NULL,
  `insegna` varchar(255) DEFAULT NULL,
  `partita_i_v_a` varchar(255) DEFAULT NULL,
  `referente_aziendale` varchar(255) DEFAULT NULL,
  `mese_importazione_c_b` varchar(255) DEFAULT NULL,
  `cluster` varchar(255) DEFAULT NULL,
  `codice_cliente` varchar(255) DEFAULT NULL,
  `codice_p_o_s` varchar(255) DEFAULT NULL,
  `consistenze` mediumtext DEFAULT '[]',
  `segmento` varchar(255) DEFAULT NULL,
  `azienda` varchar(100) DEFAULT NULL,
  `stream_updated_at` datetime DEFAULT NULL,
  `importo_fattura` double DEFAULT NULL,
  `importo_fattura_currency` varchar(3) DEFAULT NULL,
  `stato_gestione` varchar(100) DEFAULT NULL,
  `descrizione_opportunit_generata` mediumtext DEFAULT NULL,
  `fornitore_partner_id` varchar(24) DEFAULT NULL,
  `brand_id` varchar(24) DEFAULT NULL,
  `product_brand_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_name` varchar(255) DEFAULT NULL,
  `product_brand_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `lead_capture`
--

CREATE TABLE `lead_capture` (
  `id` varchar(24) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `subscribe_to_target_list` tinyint(1) NOT NULL DEFAULT 1,
  `subscribe_contact_to_target_list` tinyint(1) NOT NULL DEFAULT 1,
  `field_list` mediumtext DEFAULT '["firstName","lastName","emailAddress"]',
  `duplicate_check` tinyint(1) NOT NULL DEFAULT 1,
  `opt_in_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `opt_in_confirmation_lifetime` int(11) DEFAULT 48,
  `opt_in_confirmation_success_message` mediumtext DEFAULT NULL,
  `create_lead_before_opt_in_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `skip_opt_in_confirmation_if_subscribed` tinyint(1) NOT NULL DEFAULT 0,
  `lead_source` varchar(255) DEFAULT 'Web Site',
  `api_key` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `opt_in_confirmation_email_template_id` varchar(24) DEFAULT NULL,
  `target_team_id` varchar(24) DEFAULT NULL,
  `inbound_email_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `phone_number_country` varchar(2) DEFAULT NULL,
  `field_params` mediumtext DEFAULT NULL,
  `form_id` varchar(17) DEFAULT NULL,
  `form_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `form_text` mediumtext DEFAULT NULL,
  `form_success_text` mediumtext DEFAULT NULL,
  `form_success_redirect_url` varchar(255) DEFAULT NULL,
  `form_language` varchar(5) DEFAULT NULL,
  `form_frame_ancestors` mediumtext DEFAULT NULL,
  `form_captcha` tinyint(1) NOT NULL DEFAULT 0,
  `form_title` varchar(80) DEFAULT NULL,
  `form_theme` varchar(64) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `lead_capture_log_record`
--

CREATE TABLE `lead_capture_log_record` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` int(10) UNSIGNED NOT NULL,
  `data` mediumtext DEFAULT NULL,
  `is_created` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `lead_capture_id` varchar(24) DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `lead_meeting`
--

CREATE TABLE `lead_meeting` (
  `id` bigint(20) NOT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `meeting_id` varchar(24) DEFAULT NULL,
  `status` varchar(36) DEFAULT 'None',
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `lead_target_list`
--

CREATE TABLE `lead_target_list` (
  `id` bigint(20) NOT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `opted_out` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `macro_area`
--

CREATE TABLE `macro_area` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `codice_macro_area` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mail_chimp_batch`
--

CREATE TABLE `mail_chimp_batch` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `parent_type` varchar(255) DEFAULT NULL,
  `parent_id` varchar(255) DEFAULT NULL,
  `queue_id` varchar(255) DEFAULT NULL,
  `method` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mail_chimp_log_marker`
--

CREATE TABLE `mail_chimp_log_marker` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `mc_campaign_id` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `page` int(11) DEFAULT 0,
  `skip` int(11) DEFAULT 0,
  `offset` int(11) DEFAULT 0,
  `data` mediumtext DEFAULT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mail_chimp_manual_sync`
--

CREATE TABLE `mail_chimp_manual_sync` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `data` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `jobs` mediumtext DEFAULT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mail_chimp_queue`
--

CREATE TABLE `mail_chimp_queue` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT '',
  `deleted` tinyint(1) DEFAULT 0,
  `action_name` varchar(30) DEFAULT NULL,
  `order_number` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  `additional_data` mediumtext DEFAULT NULL,
  `parent_type` varchar(255) DEFAULT NULL,
  `parent_id` varchar(255) DEFAULT NULL,
  `recipient_entity_type` varchar(255) DEFAULT NULL,
  `recipient_entity_id` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT '',
  `action_status` varchar(20) DEFAULT NULL,
  `related_item_id` varchar(255) DEFAULT NULL,
  `attemps_left` int(11) DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mass_action`
--

CREATE TABLE `mass_action` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `entity_type` varchar(255) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Pending',
  `data` mediumtext DEFAULT NULL,
  `params` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `processed_count` int(11) DEFAULT NULL,
  `notify_on_finish` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mass_email`
--

CREATE TABLE `mass_email` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Pending',
  `store_sent_emails` tinyint(1) NOT NULL DEFAULT 0,
  `opt_out_entirely` tinyint(1) NOT NULL DEFAULT 0,
  `from_address` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `reply_to_address` varchar(255) DEFAULT NULL,
  `reply_to_name` varchar(255) DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `email_template_id` varchar(24) DEFAULT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `inbound_email_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mass_email_target_list`
--

CREATE TABLE `mass_email_target_list` (
  `id` bigint(20) NOT NULL,
  `mass_email_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mass_email_target_list_excluding`
--

CREATE TABLE `mass_email_target_list_excluding` (
  `id` bigint(20) NOT NULL,
  `mass_email_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `meeting`
--

CREATE TABLE `meeting` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Planned',
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `is_all_day` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `date_start_date` date DEFAULT NULL,
  `date_end_date` date DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `privato` tinyint(1) NOT NULL DEFAULT 0,
  `uid` varchar(255) DEFAULT NULL,
  `join_url` text DEFAULT NULL,
  `outlook_skip_push` tinyint(1) NOT NULL DEFAULT 0,
  `video_call` tinyint(1) NOT NULL DEFAULT 0,
  `sorgente` varchar(100) DEFAULT 'Outlook',
  `appuntamento_id` varchar(24) DEFAULT NULL,
  `appuntamento_ref` varchar(100) DEFAULT NULL,
  `google_calendar_event_id` varchar(384) DEFAULT NULL,
  `google_calendar_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `meeting_user`
--

CREATE TABLE `meeting_user` (
  `id` bigint(20) NOT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `meeting_id` varchar(24) DEFAULT NULL,
  `status` varchar(36) DEFAULT 'None',
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `next_number`
--

CREATE TABLE `next_number` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `entity_type` varchar(100) DEFAULT NULL,
  `field_name` varchar(100) DEFAULT NULL,
  `value` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `note`
--

CREATE TABLE `note` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `post` mediumtext DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  `type` varchar(24) DEFAULT 'Post',
  `target_type` varchar(7) DEFAULT NULL,
  `number` bigint(20) UNSIGNED NOT NULL,
  `is_global` tinyint(1) NOT NULL DEFAULT 0,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `related_id` varchar(24) DEFAULT NULL,
  `related_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `super_parent_id` varchar(24) DEFAULT NULL,
  `super_parent_type` varchar(100) DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `note_portal`
--

CREATE TABLE `note_portal` (
  `id` bigint(20) NOT NULL,
  `note_id` varchar(24) DEFAULT NULL,
  `portal_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `note_team`
--

CREATE TABLE `note_team` (
  `id` bigint(20) NOT NULL,
  `note_id` varchar(24) DEFAULT NULL,
  `team_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `note_user`
--

CREATE TABLE `note_user` (
  `id` bigint(20) NOT NULL,
  `note_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `notification`
--

CREATE TABLE `notification` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `data` mediumtext DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `read` tinyint(1) NOT NULL DEFAULT 0,
  `email_is_processed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `message` mediumtext DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `related_id` varchar(24) DEFAULT NULL,
  `related_type` varchar(100) DEFAULT NULL,
  `related_parent_id` varchar(24) DEFAULT NULL,
  `related_parent_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `action_id` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `opportunity`
--

CREATE TABLE `opportunity` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `amount` double DEFAULT NULL,
  `stage` varchar(255) DEFAULT 'Prospecting',
  `last_stage` varchar(255) DEFAULT NULL,
  `probability` int(11) DEFAULT NULL,
  `lead_source` varchar(255) DEFAULT NULL,
  `close_date` date DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `campaign_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `c_a_p_id` varchar(24) DEFAULT NULL,
  `appuntamento_id` varchar(24) DEFAULT NULL,
  `data_opportunit` date DEFAULT NULL,
  `i_v_a` double DEFAULT 10,
  `i_v_a_listino` double DEFAULT 10,
  `importo_offerta_iva_esclusa` double DEFAULT NULL,
  `importo_offerta_iva_esclusa_currency` varchar(3) DEFAULT NULL,
  `motivo_decisione` varchar(255) DEFAULT NULL,
  `note` mediumtext DEFAULT NULL,
  `whats_app` varchar(255) DEFAULT NULL,
  `prospect_id` varchar(24) DEFAULT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `su_prezzo_codice` double DEFAULT NULL,
  `prezzo_listino_i_v_a_inclusa` double DEFAULT NULL,
  `prezzo_listino_i_v_a_inclusa_currency` varchar(3) DEFAULT NULL,
  `prezzo_codice_iva_inclusa` double DEFAULT NULL,
  `prezzo_listino_iva_esclusa` double DEFAULT NULL,
  `prezzo_listino_iva_esclusa_currency` varchar(3) DEFAULT NULL,
  `prezzo_codice_iva_esclusa` double DEFAULT NULL,
  `prezzo_codice_iva_esclusa_currency` varchar(3) DEFAULT NULL,
  `prezzo_codice_iva_inclusa_currency` varchar(3) DEFAULT NULL,
  `azienda` varchar(100) DEFAULT NULL,
  `importo_opportunit` double DEFAULT NULL,
  `importo_opportunit_currency` varchar(3) DEFAULT NULL,
  `installazione` date DEFAULT NULL,
  `installatore` varchar(255) DEFAULT NULL,
  `importo_provvigione` double DEFAULT NULL,
  `importo_provvigione_currency` varchar(3) DEFAULT NULL,
  `provvigione` double DEFAULT NULL,
  `finanziamento` tinyint(1) NOT NULL DEFAULT 0,
  `stato_finanziamento` varchar(255) DEFAULT NULL,
  `stato_contratto` varchar(255) DEFAULT NULL,
  `plus_minus` double DEFAULT 0,
  `minus_plus` double DEFAULT NULL,
  `minus_plus_currency` varchar(3) DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL,
  `linea_prodotto` varchar(100) DEFAULT NULL,
  `su_prezzo_promo` double DEFAULT NULL,
  `descrizione_motivazione` varchar(100) DEFAULT NULL,
  `provvigione_totale` double DEFAULT NULL,
  `price_book_id` varchar(24) DEFAULT NULL,
  `hook_version` varchar(100) DEFAULT NULL,
  `brand_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_id` varchar(24) DEFAULT NULL,
  `product_brand_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_name` varchar(255) DEFAULT NULL,
  `product_brand_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `opportunity_item`
--

CREATE TABLE `opportunity_item` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `prezzo_codice` double DEFAULT NULL,
  `prezzo_codice_currency` varchar(3) DEFAULT NULL,
  `prezzo_listino` double DEFAULT NULL,
  `prezzo_listino_currency` varchar(3) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `quantity` double DEFAULT 1,
  `list_price` double DEFAULT NULL,
  `unit_price` double DEFAULT NULL,
  `discount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `list_price_currency` varchar(3) DEFAULT NULL,
  `unit_price_currency` varchar(3) DEFAULT NULL,
  `amount_currency` varchar(3) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `opportunity_id` varchar(24) DEFAULT NULL,
  `product_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `outlook_calendar`
--

CREATE TABLE `outlook_calendar` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `calendar_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `outlook_calendar_event`
--

CREATE TABLE `outlook_calendar_event` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `event_id` varchar(230) DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL,
  `is_updated` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `is_espo_event` tinyint(1) NOT NULL DEFAULT 0,
  `i_cal_u_id` varchar(230) DEFAULT NULL,
  `outlook_user_id` varchar(230) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `calendar_id` varchar(24) DEFAULT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `outlook_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `outlook_calendar_user`
--

CREATE TABLE `outlook_calendar_user` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `type` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `delta_token` mediumtext DEFAULT NULL,
  `skip_token` mediumtext DEFAULT NULL,
  `last_looked_at` datetime DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `calendar_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `outlook_contacts_entity`
--

CREATE TABLE `outlook_contacts_entity` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `contact_id` varchar(230) DEFAULT NULL,
  `outlook_user_id` varchar(230) DEFAULT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `o_auth_account`
--

CREATE TABLE `o_auth_account` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `access_token` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refresh_token` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `o_auth_provider`
--

CREATE TABLE `o_auth_provider` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `client_id` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authorization_endpoint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_endpoint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authorization_prompt` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'none',
  `scopes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authorization_params` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope_separator` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pagamenti_provvigionali`
--

CREATE TABLE `pagamenti_provvigionali` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `opportunita_id` varchar(24) DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `importo` double DEFAULT NULL,
  `importo_currency` varchar(3) DEFAULT NULL,
  `cliente_id` varchar(24) DEFAULT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `data_fattura` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `password_change_request`
--

CREATE TABLE `password_change_request` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `request_id` varchar(64) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_allocation`
--

CREATE TABLE `payment_allocation` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(13,4) DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `fx_gain_loss` decimal(13,3) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_note_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_entry_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `write_off_entry_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_credit_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_channel`
--

CREATE TABLE `payment_channel` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `provider` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `record_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_channel_sepa_credit_transfer`
--

CREATE TABLE `payment_channel_sepa_credit_transfer` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `account_holder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bic` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_channel_sepa_direct_debit`
--

CREATE TABLE `payment_channel_sepa_direct_debit` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `account_holder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creditor_identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_channel_wire_transfer`
--

CREATE TABLE `payment_channel_wire_transfer` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `account_holder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bic` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_entry`
--

CREATE TABLE `payment_entry` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_draft_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Inbound',
  `party_type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Customer',
  `date_paid` date DEFAULT NULL,
  `posting_date` date DEFAULT NULL,
  `amount` decimal(13,4) DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `currency_rate` decimal(20,8) DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_issued` tinyint(1) NOT NULL DEFAULT 0,
  `was_issued` tinyint(1) NOT NULL DEFAULT 0,
  `issued_at` datetime DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_installment`
--

CREATE TABLE `payment_installment` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `amount` decimal(13,3) DEFAULT NULL,
  `amount_local` decimal(13,3) DEFAULT NULL,
  `percentage` decimal(6,2) DEFAULT NULL,
  `status` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Unsettled',
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_mandate`
--

CREATE TABLE `payment_mandate` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `reference_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `account_holder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_signed` date DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_mandate_sepa`
--

CREATE TABLE `payment_mandate_sepa` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `scheme` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_method`
--

CREATE TABLE `payment_method` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `instructions` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_inbound` tinyint(1) NOT NULL DEFAULT 1,
  `is_outbound` tinyint(1) NOT NULL DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `channel_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_request`
--

CREATE TABLE `payment_request` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `expiration_date` date DEFAULT NULL,
  `amount` decimal(13,4) DEFAULT NULL,
  `reference_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_terms_profile`
--

CREATE TABLE `payment_terms_profile` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `payment_terms_profile_item`
--

CREATE TABLE `payment_terms_profile_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `percentage` decimal(6,2) DEFAULT NULL,
  `days` int(11) DEFAULT 0,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `profile_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `phone_number`
--

CREATE TABLE `phone_number` (
  `id` varchar(24) NOT NULL,
  `name` varchar(36) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `type` varchar(255) DEFAULT NULL,
  `numeric` varchar(36) DEFAULT NULL,
  `invalid` tinyint(1) NOT NULL DEFAULT 0,
  `opt_out` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `portal`
--

CREATE TABLE `portal` (
  `id` varchar(24) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `custom_id` varchar(36) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `tab_list` mediumtext DEFAULT NULL,
  `quick_create_list` mediumtext DEFAULT NULL,
  `theme` varchar(255) DEFAULT NULL,
  `language` varchar(255) DEFAULT NULL,
  `time_zone` varchar(255) DEFAULT NULL,
  `date_format` varchar(255) DEFAULT NULL,
  `time_format` varchar(255) DEFAULT NULL,
  `week_start` int(11) DEFAULT -1,
  `default_currency` varchar(255) DEFAULT NULL,
  `dashboard_layout` mediumtext DEFAULT NULL,
  `dashlets_options` mediumtext DEFAULT NULL,
  `custom_url` varchar(255) DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `logo_id` varchar(24) DEFAULT NULL,
  `company_logo_id` varchar(24) DEFAULT NULL,
  `layout_set_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `theme_params` mediumtext DEFAULT NULL,
  `authentication_provider_id` varchar(24) DEFAULT NULL,
  `auth_token_lifetime` double DEFAULT NULL,
  `auth_token_max_idle_time` double DEFAULT NULL,
  `application_name` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `portal_portal_role`
--

CREATE TABLE `portal_portal_role` (
  `id` bigint(20) NOT NULL,
  `portal_id` varchar(24) DEFAULT NULL,
  `portal_role_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `portal_report`
--

CREATE TABLE `portal_report` (
  `id` bigint(20) NOT NULL,
  `portal_id` varchar(24) DEFAULT NULL,
  `report_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `portal_role`
--

CREATE TABLE `portal_role` (
  `id` varchar(24) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `data` mediumtext DEFAULT NULL,
  `field_data` mediumtext DEFAULT NULL,
  `export_permission` varchar(255) DEFAULT 'not-set',
  `mass_update_permission` varchar(255) DEFAULT 'not-set',
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `portal_role_user`
--

CREATE TABLE `portal_role_user` (
  `id` bigint(20) NOT NULL,
  `portal_role_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `portal_user`
--

CREATE TABLE `portal_user` (
  `id` bigint(20) NOT NULL,
  `portal_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `preferences`
--

CREATE TABLE `preferences` (
  `id` varchar(24) NOT NULL,
  `data` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `price_book`
--

CREATE TABLE `price_book` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `parent_price_book_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `price_rule`
--

CREATE TABLE `price_rule` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Product Category',
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `min_quantity` double DEFAULT NULL,
  `based_on` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Unit',
  `discount` double DEFAULT NULL,
  `rounding_method` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Half Up',
  `rounding_factor` double DEFAULT 0.01,
  `surcharge` double DEFAULT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `price_book_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_category_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `price_rule_condition`
--

CREATE TABLE `price_rule_condition` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `prodotticontratti`
--

CREATE TABLE `prodotticontratti` (
  `id` bigint(20) NOT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quote_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product`
--

CREATE TABLE `product` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `denominazione` varchar(255) DEFAULT NULL,
  `opportunita_id` varchar(24) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'Available',
  `type` varchar(8) DEFAULT 'Regular',
  `item_type` varchar(7) DEFAULT 'Goods',
  `part_number` varchar(50) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `pricing_type` varchar(255) DEFAULT 'Fixed',
  `pricing_factor` double DEFAULT 0,
  `cost_price` double DEFAULT NULL,
  `list_price` double DEFAULT NULL,
  `unit_price` double DEFAULT NULL,
  `allow_fractional_quantity` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `is_tax_free` tinyint(1) NOT NULL DEFAULT 0,
  `is_sellable` tinyint(1) NOT NULL DEFAULT 1,
  `is_purchasable` tinyint(1) NOT NULL DEFAULT 1,
  `is_subscribable` tinyint(1) NOT NULL DEFAULT 0,
  `is_inventory` tinyint(1) NOT NULL DEFAULT 1,
  `inventory_number_type` varchar(6) DEFAULT NULL,
  `expiration_days` int(11) DEFAULT NULL,
  `removal_strategy` varchar(4) DEFAULT 'FIFO',
  `variant_order` int(11) DEFAULT NULL,
  `cost_price_currency` varchar(3) DEFAULT NULL,
  `list_price_currency` varchar(3) DEFAULT NULL,
  `unit_price_currency` varchar(3) DEFAULT NULL,
  `brand_id` varchar(24) DEFAULT NULL,
  `category_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `template_id` varchar(24) DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL,
  `prezzo_codice` double DEFAULT NULL,
  `prezzo_codice_currency` varchar(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_attribute`
--

CREATE TABLE `product_attribute` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_attribute_option`
--

CREATE TABLE `product_attribute_option` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_brand`
--

CREATE TABLE `product_brand` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `website` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_id` varchar(24) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL,
  `fornitore_partner_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_category`
--

CREATE TABLE `product_category` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_category_path`
--

CREATE TABLE `product_category_path` (
  `id` int(11) NOT NULL,
  `ascendor_id` varchar(24) DEFAULT NULL,
  `descendor_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_price`
--

CREATE TABLE `product_price` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `min_quantity` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `interval` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_book_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_product_attribute`
--

CREATE TABLE `product_product_attribute` (
  `id` bigint(20) NOT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_attribute_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_product_attribute_option`
--

CREATE TABLE `product_product_attribute_option` (
  `id` bigint(20) NOT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_attribute_option_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_tax_class`
--

CREATE TABLE `product_tax_class` (
  `id` bigint(20) NOT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_class_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `product_variant_product_attribute_option`
--

CREATE TABLE `product_variant_product_attribute_option` (
  `id` bigint(20) NOT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_attribute_option_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `prospect`
--

CREATE TABLE `prospect` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `salutation_name` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `address_street` varchar(255) DEFAULT NULL,
  `address_city` varchar(100) DEFAULT 'Roma',
  `address_state` varchar(100) DEFAULT 'RM',
  `address_country` varchar(100) DEFAULT 'Italia',
  `address_postal_code` varchar(40) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `c_a_p_id` varchar(24) DEFAULT NULL,
  `b2_b` tinyint(1) NOT NULL DEFAULT 0,
  `insegna` varchar(255) DEFAULT NULL,
  `partita_i_v_a` varchar(255) DEFAULT NULL,
  `ragione_sociale` varchar(255) DEFAULT NULL,
  `referente_aziendale` varchar(255) DEFAULT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `whats_app` varchar(255) DEFAULT NULL,
  `whats_app39` varchar(255) DEFAULT NULL,
  `lead_id` varchar(24) DEFAULT NULL,
  `origine` varchar(100) DEFAULT NULL,
  `azienda` varchar(100) DEFAULT NULL,
  `cliente_id` varchar(24) DEFAULT NULL,
  `brand_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_id` varchar(24) DEFAULT NULL,
  `product_brand_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_name` varchar(255) DEFAULT NULL,
  `product_brand_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `provvigione`
--

CREATE TABLE `provvigione` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contratto_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Provvigione Base',
  `cliente_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `importo` double DEFAULT NULL,
  `importo_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tasso_provvigioni` double DEFAULT NULL,
  `contratto_articoli_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hook_version` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_calcolo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Prezzo Codice',
  `valore_base` double DEFAULT NULL,
  `valore_base_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regole_provvigionali_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descrizione_calcolo` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `provvigioni_regole`
--

CREATE TABLE `provvigioni_regole` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Provvigione Base',
  `fornitore_partner_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_brand_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tasso_provvigionale` double DEFAULT NULL,
  `base_calcolo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Totale',
  `tipo_valore` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'percentuale',
  `valore_fisso` double DEFAULT NULL,
  `valore_fisso_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contratti_minimi` int(11) DEFAULT NULL,
  `contratti_massimi` int(11) DEFAULT NULL,
  `tipo_premio` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Normale',
  `soglia_contratti` int(11) DEFAULT NULL,
  `premio_totale` double DEFAULT NULL,
  `premio_totale_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `purchase_order`
--

CREATE TABLE `purchase_order` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `date_ordered` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `shipping_amount` decimal(13,4) DEFAULT NULL,
  `shipping_tax_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount` double DEFAULT NULL,
  `discount_amount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `pre_discounted_amount` double DEFAULT NULL,
  `grand_total_amount` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `tax_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pre_discounted_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_receipt_fully_created` tinyint(1) NOT NULL DEFAULT 0,
  `has_inventory_items` tinyint(1) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `supplier_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `purchase_order_item`
--

CREATE TABLE `purchase_order_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `list_price` double DEFAULT NULL,
  `unit_price` double DEFAULT NULL,
  `discount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `unit_weight` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `list_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `purchase_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `quote`
--

CREATE TABLE `quote` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `c_a_p_id` varchar(24) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `number` varchar(100) DEFAULT NULL,
  `number_a` varchar(36) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Draft',
  `date_quoted` date DEFAULT NULL,
  `date_ordered` date DEFAULT NULL,
  `date_invoiced` date DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `shipping_amount` decimal(13,4) DEFAULT NULL,
  `shipping_tax_mode` varchar(255) DEFAULT NULL,
  `tax_amount` double DEFAULT NULL,
  `discount_amount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `pre_discounted_amount` double DEFAULT NULL,
  `grand_total_amount` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `tax_amount_currency` varchar(3) DEFAULT NULL,
  `shipping_cost_currency` varchar(3) DEFAULT NULL,
  `shipping_amount_currency` varchar(3) DEFAULT NULL,
  `pre_discounted_amount_currency` varchar(3) DEFAULT NULL,
  `grand_total_amount_currency` varchar(3) DEFAULT NULL,
  `discount_amount_currency` varchar(3) DEFAULT NULL,
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `billing_address_street` varchar(255) DEFAULT NULL,
  `billing_address_city` varchar(100) DEFAULT NULL,
  `billing_address_state` varchar(100) DEFAULT NULL,
  `billing_address_country` varchar(100) DEFAULT NULL,
  `billing_address_postal_code` varchar(40) DEFAULT NULL,
  `shipping_address_street` varchar(255) DEFAULT NULL,
  `shipping_address_city` varchar(100) DEFAULT NULL,
  `shipping_address_state` varchar(100) DEFAULT NULL,
  `shipping_address_country` varchar(100) DEFAULT NULL,
  `shipping_address_postal_code` varchar(40) DEFAULT NULL,
  `amount_currency` varchar(3) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `opportunity_id` varchar(24) DEFAULT NULL,
  `billing_contact_id` varchar(24) DEFAULT NULL,
  `shipping_contact_id` varchar(24) DEFAULT NULL,
  `tax_id` varchar(24) DEFAULT NULL,
  `shipping_provider_id` varchar(24) DEFAULT NULL,
  `price_book_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL,
  `importo_contratto` double DEFAULT NULL,
  `importo_contratto_currency` varchar(3) DEFAULT NULL,
  `prezzo_codice` double DEFAULT NULL,
  `prezzo_codice_currency` varchar(3) DEFAULT NULL,
  `minus_plus` double DEFAULT NULL,
  `minus_plus_currency` varchar(3) DEFAULT NULL,
  `aliquota_i_v_a` double DEFAULT NULL,
  `total_prezzo_codice` double DEFAULT NULL,
  `total_prezzo_codice_currency` varchar(3) DEFAULT NULL,
  `totale_provvigioni` double DEFAULT NULL,
  `totale_provvigioni_currency` varchar(3) DEFAULT NULL,
  `hook_version` varchar(100) DEFAULT NULL,
  `numero_contratto` varchar(100) DEFAULT NULL,
  `brand_id` varchar(24) DEFAULT NULL,
  `fornitore_partner_id` varchar(24) DEFAULT NULL,
  `imponibile_contratto` double DEFAULT NULL,
  `imponibile_contratto_currency` varchar(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `quote_item`
--

CREATE TABLE `quote_item` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `list_price` double DEFAULT NULL,
  `unit_price` double DEFAULT NULL,
  `discount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `unit_weight` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `list_price_currency` varchar(3) DEFAULT NULL,
  `unit_price_currency` varchar(3) DEFAULT NULL,
  `amount_currency` varchar(3) DEFAULT NULL,
  `quote_id` varchar(24) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `product_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `tax_code_id` varchar(24) DEFAULT NULL,
  `prezzo_codice` double DEFAULT NULL,
  `prezzo_codice_currency` varchar(3) DEFAULT 'EUR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `receipt_order`
--

CREATE TABLE `receipt_order` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `date_ordered` date DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_hard_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `receipt_order_item`
--

CREATE TABLE `receipt_order_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `quantity_received` double DEFAULT NULL,
  `unit_weight` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `receipt_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `receipt_order_received_item`
--

CREATE TABLE `receipt_order_received_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 0,
  `order` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `receipt_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_number_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `regole_provvigionali`
--

CREATE TABLE `regole_provvigionali` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornitore_partner_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_categoria_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attiva` tinyint(1) NOT NULL DEFAULT 1,
  `priorit` int(11) DEFAULT 100,
  `base_calcolo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Prezzo Codice',
  `percentuale` double DEFAULT NULL,
  `importo_minimo` double DEFAULT NULL,
  `importo_minimo_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `importo_massimo` double DEFAULT NULL,
  `importo_massimo_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_provvigione` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Provvigione Base'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `reminder`
--

CREATE TABLE `reminder` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `remind_at` datetime DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `type` varchar(36) DEFAULT 'Popup',
  `seconds` int(11) DEFAULT 0,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `is_submitted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `report`
--

CREATE TABLE `report` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `entity_type` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT 'Grid',
  `data` mediumtext DEFAULT NULL,
  `columns` mediumtext DEFAULT NULL,
  `group_by` mediumtext DEFAULT NULL,
  `order_by` mediumtext DEFAULT NULL,
  `order_by_list` varchar(255) DEFAULT NULL,
  `filters` mediumtext DEFAULT NULL,
  `filters_data_list` mediumtext DEFAULT NULL,
  `runtime_filters` mediumtext DEFAULT NULL,
  `filters_data` mediumtext DEFAULT NULL,
  `columns_data` mediumtext DEFAULT NULL,
  `chart_color_list` mediumtext DEFAULT NULL,
  `chart_colors` mediumtext DEFAULT NULL,
  `chart_color` varchar(7) DEFAULT '#6FA8D6',
  `description` mediumtext DEFAULT NULL,
  `chart_type` varchar(255) DEFAULT NULL,
  `depth` int(11) DEFAULT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `internal_class_name` varchar(192) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `email_sending_interval` varchar(255) DEFAULT NULL,
  `email_sending_setting_month` int(11) DEFAULT NULL,
  `email_sending_setting_day` int(11) DEFAULT NULL,
  `email_sending_setting_weekdays` varchar(255) DEFAULT NULL,
  `email_sending_time` time DEFAULT NULL,
  `email_sending_last_date_sent` datetime DEFAULT NULL,
  `email_sending_do_not_send_empty_report` tinyint(1) NOT NULL DEFAULT 0,
  `apply_acl` tinyint(1) NOT NULL DEFAULT 0,
  `category_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `chart_data_list` mediumtext DEFAULT NULL,
  `joined_report_data_list` mediumtext DEFAULT NULL,
  `internal_params` mediumtext DEFAULT NULL,
  `table_mode` varchar(10) DEFAULT 'Regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `report_category`
--

CREATE TABLE `report_category` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `report_category_path`
--

CREATE TABLE `report_category_path` (
  `id` int(11) NOT NULL,
  `ascendor_id` varchar(24) DEFAULT NULL,
  `descendor_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `report_filter`
--

CREATE TABLE `report_filter` (
  `id` varchar(24) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `entity_type` varchar(255) DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `report_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `report_panel`
--

CREATE TABLE `report_panel` (
  `id` varchar(24) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `entity_type` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(255) DEFAULT 'side',
  `column` varchar(255) DEFAULT NULL,
  `order` int(11) DEFAULT 7,
  `display_total` tinyint(1) NOT NULL DEFAULT 0,
  `display_only_total` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `dynamic_logic_visible` mediumtext DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `report_id` varchar(24) DEFAULT NULL,
  `display_type` varchar(255) DEFAULT NULL,
  `use_si_multiplier` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `report_target_list`
--

CREATE TABLE `report_target_list` (
  `id` bigint(20) NOT NULL,
  `report_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `report_user`
--

CREATE TABLE `report_user` (
  `id` bigint(20) NOT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `report_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `return_order`
--

CREATE TABLE `return_order` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `date_ordered` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `shipping_amount` decimal(13,4) DEFAULT NULL,
  `shipping_tax_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount` double DEFAULT NULL,
  `discount_amount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `pre_discounted_amount` double DEFAULT NULL,
  `grand_total_amount` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `tax_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pre_discounted_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `is_receipt_fully_created` tinyint(1) NOT NULL DEFAULT 0,
  `has_inventory_items` tinyint(1) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `billing_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `return_order_item`
--

CREATE TABLE `return_order_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `tax_rate` double DEFAULT NULL,
  `list_price` double DEFAULT NULL,
  `unit_price` double DEFAULT NULL,
  `discount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `unit_weight` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `list_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `return_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_number_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `role`
--

CREATE TABLE `role` (
  `id` varchar(24) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `assignment_permission` varchar(255) DEFAULT 'not-set',
  `user_permission` varchar(255) DEFAULT 'not-set',
  `portal_permission` varchar(255) DEFAULT 'not-set',
  `group_email_account_permission` varchar(255) DEFAULT 'not-set',
  `export_permission` varchar(255) DEFAULT 'not-set',
  `mass_update_permission` varchar(255) DEFAULT 'not-set',
  `data_privacy_permission` varchar(255) DEFAULT 'not-set',
  `data` mediumtext DEFAULT NULL,
  `field_data` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `follower_management_permission` varchar(255) DEFAULT 'not-set',
  `message_permission` varchar(255) DEFAULT 'not-set',
  `audit_permission` varchar(255) DEFAULT 'not-set',
  `mention_permission` varchar(255) DEFAULT 'not-set',
  `user_calendar_permission` varchar(255) DEFAULT 'not-set'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `role_team`
--

CREATE TABLE `role_team` (
  `id` bigint(20) NOT NULL,
  `role_id` varchar(24) DEFAULT NULL,
  `team_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `role_user`
--

CREATE TABLE `role_user` (
  `id` bigint(20) NOT NULL,
  `role_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `rounding_profile`
--

CREATE TABLE `rounding_profile` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `rounding_factor` decimal(13,2) DEFAULT 1.00,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `sales_order`
--

CREATE TABLE `sales_order` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `date_ordered` date DEFAULT NULL,
  `date_invoiced` date DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `shipping_amount` decimal(13,4) DEFAULT NULL,
  `shipping_tax_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount` double DEFAULT NULL,
  `discount_amount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `pre_discounted_amount` double DEFAULT NULL,
  `grand_total_amount` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `tax_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pre_discounted_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_hard_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_delivery_created` tinyint(1) NOT NULL DEFAULT 0,
  `has_inventory_items` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `billing_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opportunity_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quote_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_book_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `sales_order_item`
--

CREATE TABLE `sales_order_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `list_price` double DEFAULT NULL,
  `unit_price` double DEFAULT NULL,
  `discount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `unit_weight` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `unit_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `list_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `sales_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `scheduled_job`
--

CREATE TABLE `scheduled_job` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `job` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Active',
  `scheduling` varchar(255) DEFAULT NULL,
  `last_run` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `scheduled_job_log_record`
--

CREATE TABLE `scheduled_job_log_record` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT NULL,
  `execution_time` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `scheduled_job_id` varchar(24) DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `shipping_provider`
--

CREATE TABLE `shipping_provider` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `website` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `status` varchar(8) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `sms`
--

CREATE TABLE `sms` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `from_name` varchar(255) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Archived',
  `date_sent` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `from_phone_number_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `replied_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `sms_phone_number`
--

CREATE TABLE `sms_phone_number` (
  `id` bigint(20) NOT NULL,
  `sms_id` varchar(24) DEFAULT NULL,
  `phone_number_id` varchar(24) DEFAULT NULL,
  `address_type` varchar(4) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `star_subscription`
--

CREATE TABLE `star_subscription` (
  `id` bigint(20) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `entity_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `stream_subscription`
--

CREATE TABLE `stream_subscription` (
  `id` bigint(20) NOT NULL,
  `entity_id` varchar(24) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription`
--

CREATE TABLE `subscription` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `billing_state` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Clear',
  `end_date` date DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anchor_day` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_book_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_plan_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL,
  `buyer_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription_billing_plan`
--

CREATE TABLE `subscription_billing_plan` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `grace_period_days` int(11) DEFAULT 15,
  `invoice_due_period_days` int(11) DEFAULT 15,
  `invoice_lead_time_days` int(11) DEFAULT 0,
  `interval` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_cycle_length` int(11) DEFAULT 1,
  `create_payment_requests` tinyint(1) NOT NULL DEFAULT 1,
  `send_payment_requests` tinyint(1) NOT NULL DEFAULT 1,
  `send_invoices` tinyint(1) NOT NULL DEFAULT 0,
  `alignment_type` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alignment_days` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alignment_weekdays` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alignment_proration_policy` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alignment_charge_min_days` int(11) DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription_billing_plan_subscription_template`
--

CREATE TABLE `subscription_billing_plan_subscription_template` (
  `id` bigint(20) NOT NULL,
  `subscription_billing_plan_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_template_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription_item`
--

CREATE TABLE `subscription_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `unit_price` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `unit_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_update_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription_period`
--

CREATE TABLE `subscription_period` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `type` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Regular',
  `status` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Scheduled',
  `billing_status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `invoice_automatically` tinyint(1) NOT NULL DEFAULT 0,
  `hold_until_billing_complete` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `subscription_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription_template`
--

CREATE TABLE `subscription_template` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `has_trial` tinyint(1) NOT NULL DEFAULT 0,
  `trial_period_days` int(11) DEFAULT 10,
  `has_term` tinyint(1) NOT NULL DEFAULT 0,
  `term_unit` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `term_length` int(11) DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_quantity` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `primary_product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription_template_item`
--

CREATE TABLE `subscription_template_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `fixed_quantity` tinyint(1) NOT NULL DEFAULT 0,
  `order` int(11) DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `subscription_template_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription_update`
--

CREATE TABLE `subscription_update` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `date` date DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Applied',
  `billing_status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `amount` double DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `supplier`
--

CREATE TABLE `supplier` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `supplier_bill`
--

CREATE TABLE `supplier_bill` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_draft_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_invoiced` date DEFAULT NULL,
  `date_due` date DEFAULT NULL,
  `posting_date` date DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `shipping_amount` decimal(13,4) DEFAULT NULL,
  `shipping_tax_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `grand_total_amount` double DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `grand_total_amount_local` decimal(13,3) DEFAULT NULL,
  `shipping_amount_local` decimal(13,3) DEFAULT NULL,
  `tax_amount_local` decimal(13,3) DEFAULT NULL,
  `currency_rate` decimal(20,8) DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_issued` tinyint(1) NOT NULL DEFAULT 0,
  `was_issued` tinyint(1) NOT NULL DEFAULT 0,
  `issued_at` datetime DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `supplier_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `supplier_bill_item`
--

CREATE TABLE `supplier_bill_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `unit_price` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `unit_price_net` decimal(13,4) DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `unit_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `supplier_bill_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `supplier_credit`
--

CREATE TABLE `supplier_credit` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_draft_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_issued` date DEFAULT NULL,
  `date_due` date DEFAULT NULL,
  `posting_date` date DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `shipping_amount` decimal(13,4) DEFAULT NULL,
  `shipping_tax_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `grand_total_amount` double DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `grand_total_amount_local` decimal(13,3) DEFAULT NULL,
  `shipping_amount_local` decimal(13,3) DEFAULT NULL,
  `tax_amount_local` decimal(13,3) DEFAULT NULL,
  `currency_rate` decimal(20,8) DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total_amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_issued` tinyint(1) NOT NULL DEFAULT 0,
  `was_issued` tinyint(1) NOT NULL DEFAULT 0,
  `issued_at` datetime DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `supplier_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_bill_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `supplier_credit_item`
--

CREATE TABLE `supplier_credit_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `unit_price` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `tax_rate` double DEFAULT NULL,
  `unit_price_net` decimal(13,4) DEFAULT NULL,
  `amount_local` decimal(13,4) DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `unit_price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `supplier_credit_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `supplier_product_price`
--

CREATE TABLE `supplier_product_price` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `min_quantity` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `price_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `system_data`
--

CREATE TABLE `system_data` (
  `id` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `last_password_recovery_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `target`
--

CREATE TABLE `target` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `salutation_name` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT '',
  `last_name` varchar(100) DEFAULT '',
  `title` varchar(100) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address_street` varchar(255) DEFAULT NULL,
  `address_city` varchar(100) DEFAULT NULL,
  `address_state` varchar(100) DEFAULT NULL,
  `address_country` varchar(100) DEFAULT NULL,
  `address_postal_code` varchar(40) DEFAULT NULL,
  `do_not_call` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `target_list`
--

CREATE TABLE `target_list` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `category_id` varchar(24) DEFAULT NULL,
  `sync_with_reports_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `sync_with_reports_unlink` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `target_list_category`
--

CREATE TABLE `target_list_category` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `target_list_category_path`
--

CREATE TABLE `target_list_category_path` (
  `id` int(11) NOT NULL,
  `ascendor_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descendor_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `target_list_user`
--

CREATE TABLE `target_list_user` (
  `id` bigint(20) NOT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `target_list_id` varchar(24) DEFAULT NULL,
  `opted_out` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `task`
--

CREATE TABLE `task` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `status` varchar(255) DEFAULT 'Not Started',
  `priority` varchar(255) DEFAULT 'Normal',
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `date_start_date` date DEFAULT NULL,
  `date_end_date` date DEFAULT NULL,
  `date_completed` datetime DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL,
  `parent_type` varchar(100) DEFAULT NULL,
  `account_id` varchar(24) DEFAULT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `opportunita_id` varchar(24) DEFAULT NULL,
  `privato` tinyint(1) NOT NULL DEFAULT 0,
  `tipologia` varchar(255) DEFAULT NULL,
  `email_id` varchar(24) DEFAULT NULL,
  `stream_updated_at` datetime DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax`
--

CREATE TABLE `tax` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `rate` double DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `status` varchar(8) DEFAULT 'Active',
  `basis` varchar(8) DEFAULT 'Tax Code',
  `shipping_mode` varchar(12) DEFAULT NULL,
  `tax_code_id` varchar(24) DEFAULT NULL,
  `shipping_tax_code_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_allocation_item`
--

CREATE TABLE `tax_allocation_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `component` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentage` decimal(8,4) DEFAULT NULL,
  `rate` decimal(8,3) DEFAULT NULL,
  `amount` decimal(13,3) DEFAULT NULL,
  `base_amount` decimal(13,3) DEFAULT NULL,
  `amount_local` decimal(13,3) DEFAULT NULL,
  `base_amount_local` decimal(13,3) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allocation_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_entry_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_class`
--

CREATE TABLE `tax_class` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_code`
--

CREATE TABLE `tax_code` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `is_selectable` tinyint(1) NOT NULL DEFAULT 1,
  `is_for_sales` tinyint(1) NOT NULL DEFAULT 1,
  `is_for_purchases` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Percentage',
  `rate` decimal(8,3) DEFAULT NULL,
  `amount` decimal(13,3) DEFAULT NULL,
  `base` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Net Amount',
  `included_in_price` tinyint(1) NOT NULL DEFAULT 0,
  `apply_to_proportional_shipping` tinyint(1) NOT NULL DEFAULT 1,
  `rounding_level` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Line',
  `rounding_factor` decimal(13,3) DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_code_tax_code`
--

CREATE TABLE `tax_code_tax_code` (
  `id` bigint(20) NOT NULL,
  `right_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `left_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_item_rule`
--

CREATE TABLE `tax_item_rule` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT 1,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `rate` double DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_line_item`
--

CREATE TABLE `tax_line_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `component` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate` decimal(8,3) DEFAULT NULL,
  `amount` decimal(13,3) DEFAULT NULL,
  `base_amount` decimal(13,3) DEFAULT NULL,
  `amount_local` decimal(13,3) DEFAULT NULL,
  `base_amount_local` decimal(10,0) DEFAULT NULL,
  `amount_precise` decimal(24,8) DEFAULT NULL,
  `amount_local_precise` decimal(24,8) DEFAULT NULL,
  `base_amount_local_precise` decimal(24,8) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_purchase_rule`
--

CREATE TABLE `tax_purchase_rule` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logic` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_rule`
--

CREATE TABLE `tax_rule` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logic` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `tax_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `tax_total_item`
--

CREATE TABLE `tax_total_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `rate` decimal(8,3) DEFAULT NULL,
  `amount` decimal(13,3) DEFAULT NULL,
  `base_amount` decimal(13,3) DEFAULT NULL,
  `amount_local` decimal(13,3) DEFAULT NULL,
  `base_amount_local` decimal(13,3) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_local_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `team`
--

CREATE TABLE `team` (
  `id` varchar(24) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `position_list` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `layout_set_id` varchar(24) DEFAULT NULL,
  `working_time_calendar_id` varchar(24) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `team_user`
--

CREATE TABLE `team_user` (
  `id` bigint(20) NOT NULL,
  `team_id` varchar(24) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `team_workflow`
--

CREATE TABLE `team_workflow` (
  `id` bigint(20) NOT NULL,
  `team_id` varchar(24) DEFAULT NULL,
  `workflow_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `template`
--

CREATE TABLE `template` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `body` mediumtext DEFAULT NULL,
  `header` mediumtext DEFAULT NULL,
  `footer` mediumtext DEFAULT NULL,
  `entity_type` varchar(255) DEFAULT NULL,
  `left_margin` double DEFAULT 10,
  `right_margin` double DEFAULT 10,
  `top_margin` double DEFAULT 10,
  `bottom_margin` double DEFAULT 20,
  `print_footer` tinyint(1) NOT NULL DEFAULT 0,
  `print_header` tinyint(1) NOT NULL DEFAULT 0,
  `footer_position` double DEFAULT 10,
  `header_position` double DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `page_orientation` varchar(255) DEFAULT 'Portrait',
  `page_format` varchar(255) DEFAULT 'A4',
  `page_width` double DEFAULT NULL,
  `page_height` double DEFAULT NULL,
  `font_face` varchar(255) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL,
  `style` mediumtext DEFAULT NULL,
  `status` varchar(8) DEFAULT 'Active',
  `description` mediumtext DEFAULT NULL,
  `filename` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `transfer_order`
--

CREATE TABLE `transfer_order` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `date_ordered` date DEFAULT NULL,
  `shipping_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `shipping_cost_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_hard_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `from_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_warehouse_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_warehouse_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_provider_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `transfer_order_item`
--

CREATE TABLE `transfer_order_item` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `quantity` double DEFAULT 1,
  `quantity_received` double DEFAULT 1,
  `unit_weight` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `order` int(11) DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `transfer_order_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_number_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `two_factor_code`
--

CREATE TABLE `two_factor_code` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `code` varchar(100) DEFAULT NULL,
  `method` varchar(100) DEFAULT NULL,
  `attempts_left` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `unique_id`
--

CREATE TABLE `unique_id` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `data` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `terminate_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `user`
--

CREATE TABLE `user` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `user_name` varchar(50) DEFAULT NULL,
  `type` varchar(24) DEFAULT 'regular',
  `password` varchar(150) DEFAULT NULL,
  `auth_method` varchar(24) DEFAULT NULL,
  `api_key` varchar(100) DEFAULT NULL,
  `salutation_name` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `title` varchar(100) DEFAULT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `default_team_id` varchar(24) DEFAULT NULL,
  `contact_id` varchar(24) DEFAULT NULL,
  `avatar_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `dashboard_template_id` varchar(24) DEFAULT NULL,
  `working_time_calendar_id` varchar(24) DEFAULT NULL,
  `delete_id` varchar(17) NOT NULL DEFAULT '0',
  `layout_set_id` varchar(24) DEFAULT NULL,
  `avatar_color` varchar(7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `user_data`
--

CREATE TABLE `user_data` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `auth2_f_a` tinyint(1) NOT NULL DEFAULT 0,
  `auth2_f_a_method` varchar(255) DEFAULT NULL,
  `auth2_f_a_totp_secret` varchar(32) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `auth2_f_a_email_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `user_reaction`
--

CREATE TABLE `user_reaction` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `user_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `user_working_time_range`
--

CREATE TABLE `user_working_time_range` (
  `id` bigint(20) NOT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `working_time_range_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `warehouse`
--

CREATE TABLE `warehouse` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT 1,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `is_available_for_stock` tinyint(1) NOT NULL DEFAULT 1,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_postal_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `webhook`
--

CREATE TABLE `webhook` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `event` varchar(100) DEFAULT NULL,
  `url` varchar(512) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `entity_type` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `field` varchar(255) DEFAULT NULL,
  `secret_key` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `skip_own` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `webhook_event_queue_item`
--

CREATE TABLE `webhook_event_queue_item` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `event` varchar(100) DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `is_processed` tinyint(1) NOT NULL DEFAULT 0,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `user_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `webhook_queue_item`
--

CREATE TABLE `webhook_queue_item` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` bigint(20) UNSIGNED NOT NULL,
  `event` varchar(100) DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `status` varchar(7) DEFAULT 'Pending',
  `processed_at` datetime DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  `process_at` datetime DEFAULT NULL,
  `webhook_id` varchar(24) DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `workflow`
--

CREATE TABLE `workflow` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `entity_type` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT 'afterRecordCreated',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `conditions_all` mediumtext DEFAULT NULL,
  `conditions_any` mediumtext DEFAULT NULL,
  `conditions_formula` mediumtext DEFAULT NULL,
  `actions` mediumtext DEFAULT NULL,
  `portal_only` tinyint(1) NOT NULL DEFAULT 0,
  `scheduling` varchar(48) DEFAULT '0 0 * * *',
  `last_run` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `portal_id` varchar(24) DEFAULT NULL,
  `target_report_id` varchar(24) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `flowchart_id` varchar(24) DEFAULT NULL,
  `signal_name` varchar(200) DEFAULT NULL,
  `scheduling_apply_timezone` tinyint(1) NOT NULL DEFAULT 1,
  `manual_dynamic_logic` mediumtext DEFAULT NULL,
  `manual_label` varchar(100) DEFAULT NULL,
  `manual_access_required` varchar(255) DEFAULT 'read',
  `manual_element_type` varchar(255) DEFAULT 'Button',
  `category_id` varchar(24) DEFAULT NULL,
  `manual_confirmation` tinyint(1) NOT NULL DEFAULT 1,
  `manual_confirmation_text` mediumtext DEFAULT NULL,
  `manual_style` varchar(255) DEFAULT NULL,
  `process_order` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `workflow_category`
--

CREATE TABLE `workflow_category` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `parent_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `workflow_category_path`
--

CREATE TABLE `workflow_category_path` (
  `id` int(11) NOT NULL,
  `ascendor_id` varchar(24) DEFAULT NULL,
  `descendor_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `workflow_log_record`
--

CREATE TABLE `workflow_log_record` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `workflow_id` varchar(24) DEFAULT NULL,
  `target_id` varchar(24) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `workflow_round_robin`
--

CREATE TABLE `workflow_round_robin` (
  `id` varchar(24) NOT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `action_id` varchar(100) DEFAULT NULL,
  `entity_type` varchar(255) DEFAULT NULL,
  `last_user_id` varchar(255) DEFAULT NULL,
  `flowchart_id` varchar(24) DEFAULT NULL,
  `workflow_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `working_time_calendar`
--

CREATE TABLE `working_time_calendar` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `time_zone` varchar(255) DEFAULT NULL,
  `time_ranges` mediumtext DEFAULT '[["9:00","17:00"]]',
  `weekday0` tinyint(1) NOT NULL DEFAULT 0,
  `weekday1` tinyint(1) NOT NULL DEFAULT 1,
  `weekday2` tinyint(1) NOT NULL DEFAULT 1,
  `weekday3` tinyint(1) NOT NULL DEFAULT 1,
  `weekday4` tinyint(1) NOT NULL DEFAULT 1,
  `weekday5` tinyint(1) NOT NULL DEFAULT 1,
  `weekday6` tinyint(1) NOT NULL DEFAULT 0,
  `weekday0_time_ranges` mediumtext DEFAULT NULL,
  `weekday1_time_ranges` mediumtext DEFAULT NULL,
  `weekday2_time_ranges` mediumtext DEFAULT NULL,
  `weekday3_time_ranges` mediumtext DEFAULT NULL,
  `weekday4_time_ranges` mediumtext DEFAULT NULL,
  `weekday5_time_ranges` mediumtext DEFAULT NULL,
  `weekday6_time_ranges` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `working_time_calendar_working_time_range`
--

CREATE TABLE `working_time_calendar_working_time_range` (
  `id` bigint(20) NOT NULL,
  `working_time_calendar_id` varchar(24) DEFAULT NULL,
  `working_time_range_id` varchar(24) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `working_time_range`
--

CREATE TABLE `working_time_range` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `time_ranges` mediumtext DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `type` varchar(11) DEFAULT 'Non-working',
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `write_off_entry`
--

CREATE TABLE `write_off_entry` (
  `id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_draft_a` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `date` date DEFAULT NULL,
  `posting_date` date DEFAULT NULL,
  `amount` decimal(13,4) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `is_not_actual` tinyint(1) NOT NULL DEFAULT 0,
  `is_issued` tinyint(1) NOT NULL DEFAULT 0,
  `was_issued` tinyint(1) NOT NULL DEFAULT 0,
  `issued_at` datetime DEFAULT NULL,
  `amount_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `account_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_by_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `zona`
--

CREATE TABLE `zona` (
  `id` varchar(24) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `description` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `created_by_id` varchar(24) DEFAULT NULL,
  `modified_by_id` varchar(24) DEFAULT NULL,
  `assigned_user_id` varchar(24) DEFAULT NULL,
  `macro_area_id` varchar(24) DEFAULT NULL,
  `area_id` varchar(24) DEFAULT NULL,
  `codice_zona` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `account`
--
ALTER TABLE `account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`,`deleted`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_PRICE_BOOK_ID` (`price_book_id`),
  ADD KEY `IDX_PAYMENT_TERMS_PROFILE_ID` (`payment_terms_profile_id`);

--
-- Indici per le tabelle `account_contact`
--
ALTER TABLE `account_contact`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ACCOUNT_ID_CONTACT_ID` (`account_id`,`contact_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`);

--
-- Indici per le tabelle `account_document`
--
ALTER TABLE `account_document`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ACCOUNT_ID_DOCUMENT_ID` (`account_id`,`document_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_DOCUMENT_ID` (`document_id`);

--
-- Indici per le tabelle `account_payment_method`
--
ALTER TABLE `account_payment_method`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ACCOUNT_ID_PAYMENT_METHOD_ID` (`account_id`,`payment_method_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PAYMENT_METHOD_ID` (`payment_method_id`);

--
-- Indici per le tabelle `account_portal_user`
--
ALTER TABLE `account_portal_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_USER_ID_ACCOUNT_ID` (`user_id`,`account_id`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`);

--
-- Indici per le tabelle `account_target_list`
--
ALTER TABLE `account_target_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ACCOUNT_ID_TARGET_LIST_ID` (`account_id`,`target_list_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `action_history_record`
--
ALTER TABLE `action_history_record`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_AUTH_TOKEN_ID` (`auth_token_id`),
  ADD KEY `IDX_AUTH_LOG_RECORD_ID` (`auth_log_record_id`),
  ADD KEY `IDX_TARGET` (`target_type`,`target_id`);

--
-- Indici per le tabelle `address_country`
--
ALTER TABLE `address_country`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NAME` (`name`);

--
-- Indici per le tabelle `appuntamento`
--
ALTER TABLE `appuntamento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_C_A_P_ID` (`c_a_p_id`),
  ADD KEY `IDX_PROSPECT_ID` (`prospect_id`),
  ADD KEY `IDX_IMPEGNO_ID` (`impegno_id`),
  ADD KEY `IDX_DATE_START_STATUS` (`date_start`,`status`),
  ADD KEY `IDX_DATE_START` (`date_start`,`deleted`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER_STATUS` (`assigned_user_id`,`status`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`),
  ADD KEY `IDX_PRODUCT_BRAND_ID` (`product_brand_id`);

--
-- Indici per le tabelle `app_log_record`
--
ALTER TABLE `app_log_record`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_LEVEL` (`level`);

--
-- Indici per le tabelle `app_secret`
--
ALTER TABLE `app_secret`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NAME_DELETE_ID` (`name`,`delete_id`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `area`
--
ALTER TABLE `area`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_MACRO_AREA_ID` (`macro_area_id`);

--
-- Indici per le tabelle `array_value`
--
ALTER TABLE `array_value`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ENTITY_TYPE_VALUE` (`entity_type`,`value`),
  ADD KEY `IDX_ENTITY_VALUE` (`entity_type`,`entity_id`,`value`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`);

--
-- Indici per le tabelle `attachment`
--
ALTER TABLE `attachment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PARENT` (`parent_type`,`parent_id`),
  ADD KEY `IDX_SOURCE` (`source_id`),
  ADD KEY `IDX_RELATED` (`related_id`,`related_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`);

--
-- Indici per le tabelle `authentication_provider`
--
ALTER TABLE `authentication_provider`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `auth_log_record`
--
ALTER TABLE `auth_log_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_IP_ADDRESS` (`ip_address`),
  ADD KEY `IDX_IP_ADDRESS_REQUEST_TIME` (`ip_address`,`request_time`),
  ADD KEY `IDX_REQUEST_TIME` (`request_time`),
  ADD KEY `IDX_PORTAL_ID` (`portal_id`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_AUTH_TOKEN_ID` (`auth_token_id`),
  ADD KEY `IDX_USERNAME_IP_ADDRESS` (`username`,`ip_address`);

--
-- Indici per le tabelle `auth_token`
--
ALTER TABLE `auth_token`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_TOKEN` (`token`,`deleted`),
  ADD KEY `IDX_HASH` (`hash`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_PORTAL_ID` (`portal_id`);

--
-- Indici per le tabelle `autofollow`
--
ALTER TABLE `autofollow`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ENTITY_TYPE` (`entity_type`),
  ADD KEY `IDX_USER` (`user_id`);

--
-- Indici per le tabelle `bpmn_flowchart`
--
ALTER TABLE `bpmn_flowchart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_CATEGORY_ID` (`category_id`);

--
-- Indici per le tabelle `bpmn_flowchart_category`
--
ALTER TABLE `bpmn_flowchart_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`);

--
-- Indici per le tabelle `bpmn_flowchart_category_path`
--
ALTER TABLE `bpmn_flowchart_category_path`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `bpmn_flow_node`
--
ALTER TABLE `bpmn_flow_node`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_STATUS_TARGET_TYPE_ELEMENT_TYPE` (`status`,`target_type`,`element_type`),
  ADD KEY `IDX_STATUS_ELEMENT_TYPE` (`status`,`element_type`),
  ADD KEY `IDX_STATUS_PROCESS_ID` (`status`,`process_id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`),
  ADD KEY `IDX_DIVERGENT_FLOW_NODE_ID` (`divergent_flow_node_id`),
  ADD KEY `IDX_PREVIOUS_FLOW_NODE_ID` (`previous_flow_node_id`),
  ADD KEY `IDX_FLOWCHART_ID` (`flowchart_id`),
  ADD KEY `IDX_PROCESS_ID` (`process_id`);

--
-- Indici per le tabelle `bpmn_process`
--
ALTER TABLE `bpmn_process`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_TARGET` (`target_type`,`target_id`),
  ADD KEY `IDX_FLOWCHART_ID` (`flowchart_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_PROCESS_ID` (`parent_process_id`),
  ADD KEY `IDX_PARENT_PROCESS_FLOW_NODE_ID` (`parent_process_flow_node_id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_STATUS_CREATED_AT` (`status`,`created_at`),
  ADD KEY `IDX_ROOT_PROCESS_ID` (`root_process_id`),
  ADD KEY `IDX_IS_LOCKED_VISIT_TIMESTAMP` (`status`,`is_locked`,`visit_timestamp`);

--
-- Indici per le tabelle `bpmn_signal_listener`
--
ALTER TABLE `bpmn_signal_listener`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_NAME_NUMBER` (`name`,`number`),
  ADD KEY `IDX_FLOW_NODE_ID` (`flow_node_id`);

--
-- Indici per le tabelle `bpmn_user_task`
--
ALTER TABLE `bpmn_user_task`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`),
  ADD KEY `IDX_PROCESS_ID` (`process_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_FLOW_NODE_ID` (`flow_node_id`);

--
-- Indici per le tabelle `brand`
--
ALTER TABLE `brand`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PARTNER_ID` (`partner_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `call`
--
ALTER TABLE `call`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DATE_START_STATUS` (`date_start`,`status`),
  ADD KEY `IDX_DATE_START` (`date_start`,`deleted`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER_STATUS` (`assigned_user_id`,`status`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_UID` (`uid`),
  ADD KEY `IDX_PROSPECT_ID` (`prospect_id`),
  ADD KEY `IDX_GOOGLE_CALENDAR_ID` (`google_calendar_id`);

--
-- Indici per le tabelle `call_contact`
--
ALTER TABLE `call_contact`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CALL_ID_CONTACT_ID` (`call_id`,`contact_id`),
  ADD KEY `IDX_CALL_ID` (`call_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`);

--
-- Indici per le tabelle `call_lead`
--
ALTER TABLE `call_lead`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CALL_ID_LEAD_ID` (`call_id`,`lead_id`),
  ADD KEY `IDX_CALL_ID` (`call_id`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`);

--
-- Indici per le tabelle `call_user`
--
ALTER TABLE `call_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_USER_ID_CALL_ID` (`user_id`,`call_id`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_CALL_ID` (`call_id`);

--
-- Indici per le tabelle `campaign`
--
ALTER TABLE `campaign`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CONTACTS_TEMPLATE_ID` (`contacts_template_id`),
  ADD KEY `IDX_LEADS_TEMPLATE_ID` (`leads_template_id`),
  ADD KEY `IDX_ACCOUNTS_TEMPLATE_ID` (`accounts_template_id`),
  ADD KEY `IDX_USERS_TEMPLATE_ID` (`users_template_id`);

--
-- Indici per le tabelle `campaign_log_record`
--
ALTER TABLE `campaign_log_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ACTION_DATE` (`action_date`,`deleted`),
  ADD KEY `IDX_ACTION` (`action`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_OBJECT` (`object_id`,`object_type`),
  ADD KEY `IDX_QUEUE_ITEM_ID` (`queue_item_id`);

--
-- Indici per le tabelle `campaign_target_list`
--
ALTER TABLE `campaign_target_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CAMPAIGN_ID_TARGET_LIST_ID` (`campaign_id`,`target_list_id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `campaign_target_list_excluding`
--
ALTER TABLE `campaign_target_list_excluding`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CAMPAIGN_ID_TARGET_LIST_ID` (`campaign_id`,`target_list_id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `campaign_tracking_url`
--
ALTER TABLE `campaign_tracking_url`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`);

--
-- Indici per le tabelle `case`
--
ALTER TABLE `case`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER_STATUS` (`assigned_user_id`,`status`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_INBOUND_EMAIL_ID` (`inbound_email_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);
ALTER TABLE `case` ADD FULLTEXT KEY `IDX_SYSTEM_FULL_TEXT_SEARCH` (`name`,`description`);

--
-- Indici per le tabelle `case_contact`
--
ALTER TABLE `case_contact`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CASE_ID_CONTACT_ID` (`case_id`,`contact_id`),
  ADD KEY `IDX_CASE_ID` (`case_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`);

--
-- Indici per le tabelle `case_knowledge_base_article`
--
ALTER TABLE `case_knowledge_base_article`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CASE_ID_KNOWLEDGE_BASE_ARTICLE_ID` (`case_id`,`knowledge_base_article_id`),
  ADD KEY `IDX_CASE_ID` (`case_id`),
  ADD KEY `IDX_KNOWLEDGE_BASE_ARTICLE_ID` (`knowledge_base_article_id`);

--
-- Indici per le tabelle `contact`
--
ALTER TABLE `contact`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`,`deleted`),
  ADD KEY `IDX_FIRST_NAME` (`first_name`,`deleted`),
  ADD KEY `IDX_NAME` (`first_name`,`last_name`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`);

--
-- Indici per le tabelle `contact_document`
--
ALTER TABLE `contact_document`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CONTACT_ID_DOCUMENT_ID` (`contact_id`,`document_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_DOCUMENT_ID` (`document_id`);

--
-- Indici per le tabelle `contact_meeting`
--
ALTER TABLE `contact_meeting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CONTACT_ID_MEETING_ID` (`contact_id`,`meeting_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_MEETING_ID` (`meeting_id`);

--
-- Indici per le tabelle `contact_opportunity`
--
ALTER TABLE `contact_opportunity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CONTACT_ID_OPPORTUNITY_ID` (`contact_id`,`opportunity_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_OPPORTUNITY_ID` (`opportunity_id`);

--
-- Indici per le tabelle `contact_target_list`
--
ALTER TABLE `contact_target_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CONTACT_ID_TARGET_LIST_ID` (`contact_id`,`target_list_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `contratto`
--
ALTER TABLE `contratto`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `credit_note`
--
ALTER TABLE `credit_note`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_INVOICE_ID` (`invoice_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_ROUNDING_PROFILE_ID` (`rounding_profile_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_ISSUED_BY_ID` (`issued_by_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `credit_note_item`
--
ALTER TABLE `credit_note_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREDIT_NOTE_ID` (`credit_note_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `credit_note_subscription_update`
--
ALTER TABLE `credit_note_subscription_update`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREDIT_NOTE_ID_SUBSCRIPTION_UPDATE_ID` (`credit_note_id`,`subscription_update_id`),
  ADD KEY `IDX_CREDIT_NOTE_ID` (`credit_note_id`),
  ADD KEY `IDX_SUBSCRIPTION_UPDATE_ID` (`subscription_update_id`);

--
-- Indici per le tabelle `currency`
--
ALTER TABLE `currency`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `currency_record`
--
ALTER TABLE `currency_record`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CODE_DELETE_ID` (`code`,`delete_id`),
  ADD KEY `IDX_CODE` (`code`);

--
-- Indici per le tabelle `currency_record_rate`
--
ALTER TABLE `currency_record_rate`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_RECORD_ID_BASE_CODE_DATE` (`record_id`,`base_code`,`date`,`delete_id`),
  ADD KEY `IDX_RECORD_ID` (`record_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `c_a_p`
--
ALTER TABLE `c_a_p`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_MACRO_AREA_ID` (`macro_area_id`),
  ADD KEY `IDX_AREA_ID` (`area_id`),
  ADD KEY `IDX_ZONA_ID` (`zona_id`);

--
-- Indici per le tabelle `dashboard_template`
--
ALTER TABLE `dashboard_template`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `delivery_order`
--
ALTER TABLE `delivery_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_SALES_ORDER_ID` (`sales_order_id`),
  ADD KEY `IDX_WAREHOUSE_ID` (`warehouse_id`),
  ADD KEY `IDX_SHIPPING_CONTACT_ID` (`shipping_contact_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `delivery_order_item`
--
ALTER TABLE `delivery_order_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DELIVERY_ORDER_ID` (`delivery_order_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_INVENTORY_NUMBER_ID` (`inventory_number_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `disponibilita`
--
ALTER TABLE `disponibilita`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DATE_START_STATUS` (`date_start`,`status`),
  ADD KEY `IDX_DATE_START` (`date_start`,`deleted`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER_STATUS` (`assigned_user_id`,`status`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `disponibilt_ricorrente2`
--
ALTER TABLE `disponibilt_ricorrente2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `document`
--
ALTER TABLE `document`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_FOLDER_ID` (`folder_id`);

--
-- Indici per le tabelle `document_folder`
--
ALTER TABLE `document_folder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`);

--
-- Indici per le tabelle `document_folder_path`
--
ALTER TABLE `document_folder_path`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASCENDOR_ID` (`ascendor_id`),
  ADD KEY `IDX_DESCENDOR_ID` (`descendor_id`);

--
-- Indici per le tabelle `document_lead`
--
ALTER TABLE `document_lead`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_DOCUMENT_ID_LEAD_ID` (`document_id`,`lead_id`),
  ADD KEY `IDX_DOCUMENT_ID` (`document_id`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`);

--
-- Indici per le tabelle `document_opportunity`
--
ALTER TABLE `document_opportunity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_DOCUMENT_ID_OPPORTUNITY_ID` (`document_id`,`opportunity_id`),
  ADD KEY `IDX_DOCUMENT_ID` (`document_id`),
  ADD KEY `IDX_OPPORTUNITY_ID` (`opportunity_id`);

--
-- Indici per le tabelle `email`
--
ALTER TABLE `email`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_DATE_SENT` (`date_sent`,`deleted`),
  ADD KEY `IDX_DATE_SENT_STATUS` (`date_sent`,`status`,`deleted`),
  ADD KEY `IDX_MESSAGE_ID` (`message_id`),
  ADD KEY `IDX_FROM_EMAIL_ADDRESS_ID` (`from_email_address_id`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_SENT_BY_ID` (`sent_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_REPLIED_ID` (`replied_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_ICS_EVENT_UID` (`ics_event_uid`),
  ADD KEY `IDX_CREATED_EVENT` (`created_event_id`,`created_event_type`),
  ADD KEY `IDX_GROUP_FOLDER_ID` (`group_folder_id`),
  ADD KEY `IDX_GROUP_STATUS_FOLDER` (`group_status_folder`);
ALTER TABLE `email` ADD FULLTEXT KEY `IDX_SYSTEM_FULL_TEXT_SEARCH` (`name`,`body_plain`,`body`);

--
-- Indici per le tabelle `email_account`
--
ALTER TABLE `email_account`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_EMAIL_FOLDER_ID` (`email_folder_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `email_address`
--
ALTER TABLE `email_address`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_LOWER` (`lower`);

--
-- Indici per le tabelle `email_email_account`
--
ALTER TABLE `email_email_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_EMAIL_ID_EMAIL_ACCOUNT_ID` (`email_id`,`email_account_id`),
  ADD KEY `IDX_EMAIL_ID` (`email_id`),
  ADD KEY `IDX_EMAIL_ACCOUNT_ID` (`email_account_id`);

--
-- Indici per le tabelle `email_email_address`
--
ALTER TABLE `email_email_address`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_EMAIL_ID_EMAIL_ADDRESS_ID_ADDRESS_TYPE` (`email_id`,`email_address_id`,`address_type`),
  ADD KEY `IDX_EMAIL_ID` (`email_id`),
  ADD KEY `IDX_EMAIL_ADDRESS_ID` (`email_address_id`);

--
-- Indici per le tabelle `email_filter`
--
ALTER TABLE `email_filter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_EMAIL_FOLDER_ID` (`email_folder_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_GROUP_EMAIL_FOLDER_ID` (`group_email_folder_id`);

--
-- Indici per le tabelle `email_folder`
--
ALTER TABLE `email_folder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `email_inbound_email`
--
ALTER TABLE `email_inbound_email`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_EMAIL_ID_INBOUND_EMAIL_ID` (`email_id`,`inbound_email_id`),
  ADD KEY `IDX_EMAIL_ID` (`email_id`),
  ADD KEY `IDX_INBOUND_EMAIL_ID` (`inbound_email_id`);

--
-- Indici per le tabelle `email_queue_item`
--
ALTER TABLE `email_queue_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_MASS_EMAIL_ID` (`mass_email_id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`),
  ADD KEY `IDX_SENT_AT` (`sent_at`);

--
-- Indici per le tabelle `email_template`
--
ALTER TABLE `email_template`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CATEGORY_ID` (`category_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `email_template_category`
--
ALTER TABLE `email_template_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`);

--
-- Indici per le tabelle `email_template_category_path`
--
ALTER TABLE `email_template_category_path`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASCENDOR_ID` (`ascendor_id`),
  ADD KEY `IDX_DESCENDOR_ID` (`descendor_id`);

--
-- Indici per le tabelle `email_user`
--
ALTER TABLE `email_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_EMAIL_ID_USER_ID` (`email_id`,`user_id`),
  ADD KEY `IDX_EMAIL_ID` (`email_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `entity_collaborator`
--
ALTER TABLE `entity_collaborator`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ENTITY_ID_USER_ID_ENTITY_TYPE` (`entity_id`,`user_id`,`entity_type`),
  ADD KEY `IDX_ENTITY_ID` (`entity_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `entity_email_address`
--
ALTER TABLE `entity_email_address`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ENTITY_ID_EMAIL_ADDRESS_ID_ENTITY_TYPE` (`entity_id`,`email_address_id`,`entity_type`),
  ADD KEY `IDX_ENTITY_ID` (`entity_id`),
  ADD KEY `IDX_EMAIL_ADDRESS_ID` (`email_address_id`);

--
-- Indici per le tabelle `entity_phone_number`
--
ALTER TABLE `entity_phone_number`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ENTITY_ID_PHONE_NUMBER_ID_ENTITY_TYPE` (`entity_id`,`phone_number_id`,`entity_type`),
  ADD KEY `IDX_ENTITY_ID` (`entity_id`),
  ADD KEY `IDX_PHONE_NUMBER_ID` (`phone_number_id`);

--
-- Indici per le tabelle `entity_team`
--
ALTER TABLE `entity_team`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ENTITY_ID_TEAM_ID_ENTITY_TYPE` (`entity_id`,`team_id`,`entity_type`),
  ADD KEY `IDX_ENTITY_ID` (`entity_id`),
  ADD KEY `IDX_TEAM_ID` (`team_id`);

--
-- Indici per le tabelle `entity_user`
--
ALTER TABLE `entity_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ENTITY_ID_USER_ID_ENTITY_TYPE` (`entity_id`,`user_id`,`entity_type`),
  ADD KEY `IDX_ENTITY_ID` (`entity_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `eu_tax_mapping`
--
ALTER TABLE `eu_tax_mapping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ORDER` (`order`),
  ADD KEY `IDX_TAX_CODE_ID_ORDER` (`tax_code_id`,`order`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_TAX_CLASS_ID` (`tax_class_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `evento`
--
ALTER TABLE `evento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DATE_START_STATUS` (`date_start`,`status`),
  ADD KEY `IDX_DATE_START` (`date_start`,`deleted`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER_STATUS` (`assigned_user_id`,`status`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `export`
--
ALTER TABLE `export`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_ATTACHMENT` (`attachment_id`);

--
-- Indici per le tabelle `extension`
--
ALTER TABLE `extension`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_LICENSE_STATUS` (`license_status`);

--
-- Indici per le tabelle `external_account`
--
ALTER TABLE `external_account`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `fattura_attiva`
--
ALTER TABLE `fattura_attiva`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`);

--
-- Indici per le tabelle `fornitore_partner`
--
ALTER TABLE `fornitore_partner`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_DISPONIBILITA_ID` (`disponibilita_id`);

--
-- Indici per le tabelle `google_calendar`
--
ALTER TABLE `google_calendar`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `google_calendar_event`
--
ALTER TABLE `google_calendar_event`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_GOOGLE_CALENDAR_EVENT_ID` (`google_calendar_event_id`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`),
  ADD KEY `IDX_GOOGLE_CALENDAR` (`google_calendar_id`);

--
-- Indici per le tabelle `google_calendar_recurrent_event`
--
ALTER TABLE `google_calendar_recurrent_event`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_GOOGLE_CALENDAR_USER_ID` (`google_calendar_user_id`);

--
-- Indici per le tabelle `google_calendar_user`
--
ALTER TABLE `google_calendar_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_GOOGLE_CALENDAR_ID` (`google_calendar_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `google_contacts_group`
--
ALTER TABLE `google_contacts_group`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `google_contacts_pair`
--
ALTER TABLE `google_contacts_pair`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_RESOURCE_NAME` (`resource_name`),
  ADD KEY `IDX_ETAG` (`etag`);

--
-- Indici per le tabelle `google_contacts_user`
--
ALTER TABLE `google_contacts_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_GOOGLE_CONTACTS_GROUP_ID` (`google_contacts_group_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `group_email_folder`
--
ALTER TABLE `group_email_folder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `group_email_folder_team`
--
ALTER TABLE `group_email_folder_team`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_GROUP_EMAIL_FOLDER_ID_TEAM_ID` (`group_email_folder_id`,`team_id`),
  ADD KEY `IDX_GROUP_EMAIL_FOLDER_ID` (`group_email_folder_id`),
  ADD KEY `IDX_TEAM_ID` (`team_id`);

--
-- Indici per le tabelle `import`
--
ALTER TABLE `import`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`);

--
-- Indici per le tabelle `import_entity`
--
ALTER TABLE `import_entity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ENTITY_IMPORT` (`import_id`,`entity_type`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`),
  ADD KEY `IDX_IMPORT` (`import_id`);

--
-- Indici per le tabelle `import_error`
--
ALTER TABLE `import_error`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ROW_INDEX` (`row_index`),
  ADD KEY `IDX_IMPORT_ROW_INDEX` (`import_id`,`row_index`),
  ADD KEY `IDX_IMPORT_ID` (`import_id`);

--
-- Indici per le tabelle `inbound_email`
--
ALTER TABLE `inbound_email`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASSIGN_TO_USER_ID` (`assign_to_user_id`),
  ADD KEY `IDX_TEAM_ID` (`team_id`),
  ADD KEY `IDX_REPLY_EMAIL_TEMPLATE_ID` (`reply_email_template_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_GROUP_EMAIL_FOLDER_ID` (`group_email_folder_id`);

--
-- Indici per le tabelle `inbound_email_team`
--
ALTER TABLE `inbound_email_team`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_INBOUND_EMAIL_ID_TEAM_ID` (`inbound_email_id`,`team_id`),
  ADD KEY `IDX_INBOUND_EMAIL_ID` (`inbound_email_id`),
  ADD KEY `IDX_TEAM_ID` (`team_id`);

--
-- Indici per le tabelle `integration`
--
ALTER TABLE `integration`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `inventory_adjustment`
--
ALTER TABLE `inventory_adjustment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_STATUS` (`status`),
  ADD KEY `IDX_WAREHOUSE_ID` (`warehouse_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `inventory_adjustment_item`
--
ALTER TABLE `inventory_adjustment_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_INVENTORY_ADJUSTMENT_ID` (`inventory_adjustment_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_INVENTORY_NUMBER_ID` (`inventory_number_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `inventory_number`
--
ALTER TABLE `inventory_number`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PRODUCT_ID_NAME` (`product_id`,`name`,`delete_id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_INCOMING_DATE` (`incoming_date`),
  ADD KEY `IDX_EXPIRATION_DATE` (`expiration_date`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `inventory_transaction`
--
ALTER TABLE `inventory_transaction`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_PRODUCT_ID_TYPE` (`product_id`,`type`),
  ADD KEY `IDX_PRODUCT_ID_PARENT_ID` (`product_id`,`parent_id`),
  ADD KEY `IDX_PRODUCT_ID_WAREHOUSE_ID` (`product_id`,`warehouse_id`),
  ADD KEY `IDX_PRODUCT_ID_INVENTORY_NUMBER_ID` (`product_id`,`inventory_number_id`),
  ADD KEY `IDX_INVENTORY_NUMBER_ID_TYPE` (`inventory_number_id`,`type`),
  ADD KEY `IDX_TYPE` (`type`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_WAREHOUSE_ID` (`warehouse_id`),
  ADD KEY `IDX_INVENTORY_NUMBER_ID` (`inventory_number_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `inviti_a_fatturare`
--
ALTER TABLE `inviti_a_fatturare`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_QUOTE_ID` (`quote_id`);

--
-- Indici per le tabelle `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_OPPORTUNITY_ID` (`opportunity_id`),
  ADD KEY `IDX_QUOTE_ID` (`quote_id`),
  ADD KEY `IDX_SALES_ORDER_ID` (`sales_order_id`),
  ADD KEY `IDX_PRECEDING_INVOICE_ID` (`preceding_invoice_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_SHIPPING_CONTACT_ID` (`shipping_contact_id`),
  ADD KEY `IDX_ROUNDING_PROFILE_ID` (`rounding_profile_id`),
  ADD KEY `IDX_PAYMENT_TERMS_PROFILE_ID` (`payment_terms_profile_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_PRICE_BOOK_ID` (`price_book_id`),
  ADD KEY `IDX_ISSUED_BY_ID` (`issued_by_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `invoice_item`
--
ALTER TABLE `invoice_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_INVOICE_ID` (`invoice_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `invoice_payment_method`
--
ALTER TABLE `invoice_payment_method`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_INVOICE_ID_PAYMENT_METHOD_ID` (`invoice_id`,`payment_method_id`),
  ADD KEY `IDX_INVOICE_ID` (`invoice_id`),
  ADD KEY `IDX_PAYMENT_METHOD_ID` (`payment_method_id`);

--
-- Indici per le tabelle `invoice_payment_request`
--
ALTER TABLE `invoice_payment_request`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_INVOICE_ID_PAYMENT_REQUEST_ID` (`invoice_id`,`payment_request_id`),
  ADD KEY `IDX_INVOICE_ID` (`invoice_id`),
  ADD KEY `IDX_PAYMENT_REQUEST_ID` (`payment_request_id`);

--
-- Indici per le tabelle `invoice_subscription_period`
--
ALTER TABLE `invoice_subscription_period`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_INVOICE_ID_SUBSCRIPTION_PERIOD_ID` (`invoice_id`,`subscription_period_id`),
  ADD KEY `IDX_INVOICE_ID` (`invoice_id`),
  ADD KEY `IDX_SUBSCRIPTION_PERIOD_ID` (`subscription_period_id`);

--
-- Indici per le tabelle `invoice_subscription_update`
--
ALTER TABLE `invoice_subscription_update`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_INVOICE_ID_SUBSCRIPTION_UPDATE_ID` (`invoice_id`,`subscription_update_id`),
  ADD KEY `IDX_INVOICE_ID` (`invoice_id`),
  ADD KEY `IDX_SUBSCRIPTION_UPDATE_ID` (`subscription_update_id`);

--
-- Indici per le tabelle `job`
--
ALTER TABLE `job`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_EXECUTE_TIME` (`status`,`execute_time`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_SCHEDULED_JOB_ID` (`scheduled_job_id`),
  ADD KEY `IDX_STATUS_SCHEDULED_JOB_ID` (`status`,`scheduled_job_id`);

--
-- Indici per le tabelle `kanban_order`
--
ALTER TABLE `kanban_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ENTITY_USER_ID` (`entity_type`,`entity_id`,`user_id`),
  ADD KEY `IDX_ENTITY_TYPE` (`entity_type`),
  ADD KEY `IDX_ENTITY_TYPE_USER_ID` (`entity_type`,`user_id`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`),
  ADD KEY `IDX_USER` (`user_id`);

--
-- Indici per le tabelle `knowledge_base_article`
--
ALTER TABLE `knowledge_base_article`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);
ALTER TABLE `knowledge_base_article` ADD FULLTEXT KEY `IDX_SYSTEM_FULL_TEXT_SEARCH` (`name`,`body_plain`);

--
-- Indici per le tabelle `knowledge_base_article_knowledge_base_category`
--
ALTER TABLE `knowledge_base_article_knowledge_base_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_KNOWLEDGE_BASE_ARTICLE_ID_KNOWLEDGE_BASE_CATEGORY_ID` (`knowledge_base_article_id`,`knowledge_base_category_id`),
  ADD KEY `IDX_KNOWLEDGE_BASE_ARTICLE_ID` (`knowledge_base_article_id`),
  ADD KEY `IDX_KNOWLEDGE_BASE_CATEGORY_ID` (`knowledge_base_category_id`);

--
-- Indici per le tabelle `knowledge_base_article_portal`
--
ALTER TABLE `knowledge_base_article_portal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PORTAL_ID_KNOWLEDGE_BASE_ARTICLE_ID` (`portal_id`,`knowledge_base_article_id`),
  ADD KEY `IDX_PORTAL_ID` (`portal_id`),
  ADD KEY `IDX_KNOWLEDGE_BASE_ARTICLE_ID` (`knowledge_base_article_id`);

--
-- Indici per le tabelle `knowledge_base_category`
--
ALTER TABLE `knowledge_base_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`);

--
-- Indici per le tabelle `knowledge_base_category_path`
--
ALTER TABLE `knowledge_base_category_path`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASCENDOR_ID` (`ascendor_id`),
  ADD KEY `IDX_DESCENDOR_ID` (`descendor_id`);

--
-- Indici per le tabelle `layout_record`
--
ALTER TABLE `layout_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME_LAYOUT_SET_ID` (`name`,`layout_set_id`),
  ADD KEY `IDX_LAYOUT_SET_ID` (`layout_set_id`);

--
-- Indici per le tabelle `layout_set`
--
ALTER TABLE `layout_set`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `lead`
--
ALTER TABLE `lead`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_FIRST_NAME` (`first_name`,`deleted`),
  ADD KEY `IDX_NAME` (`first_name`,`last_name`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`,`deleted`),
  ADD KEY `IDX_CREATED_AT_STATUS` (`created_at`,`status`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER_STATUS` (`assigned_user_id`,`status`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_CREATED_ACCOUNT_ID` (`created_account_id`),
  ADD KEY `IDX_CREATED_CONTACT_ID` (`created_contact_id`),
  ADD KEY `IDX_CREATED_OPPORTUNITY_ID` (`created_opportunity_id`),
  ADD KEY `IDX_C_A_P_ID` (`c_a_p_id`),
  ADD KEY `IDX_APPUNTAMENTO_ID` (`appuntamento_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`),
  ADD KEY `IDX_PRODUCT_BRAND_ID` (`product_brand_id`);

--
-- Indici per le tabelle `lead_capture`
--
ALTER TABLE `lead_capture`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`),
  ADD KEY `IDX_OPT_IN_CONFIRMATION_EMAIL_TEMPLATE_ID` (`opt_in_confirmation_email_template_id`),
  ADD KEY `IDX_TARGET_TEAM_ID` (`target_team_id`),
  ADD KEY `IDX_INBOUND_EMAIL_ID` (`inbound_email_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `lead_capture_log_record`
--
ALTER TABLE `lead_capture_log_record`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_LEAD_CAPTURE_ID` (`lead_capture_id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`);

--
-- Indici per le tabelle `lead_meeting`
--
ALTER TABLE `lead_meeting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_LEAD_ID_MEETING_ID` (`lead_id`,`meeting_id`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`),
  ADD KEY `IDX_MEETING_ID` (`meeting_id`);

--
-- Indici per le tabelle `lead_target_list`
--
ALTER TABLE `lead_target_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_LEAD_ID_TARGET_LIST_ID` (`lead_id`,`target_list_id`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `macro_area`
--
ALTER TABLE `macro_area`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `mail_chimp_batch`
--
ALTER TABLE `mail_chimp_batch`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `mail_chimp_log_marker`
--
ALTER TABLE `mail_chimp_log_marker`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `mail_chimp_manual_sync`
--
ALTER TABLE `mail_chimp_manual_sync`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `mail_chimp_queue`
--
ALTER TABLE `mail_chimp_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD UNIQUE KEY `UNIQ_ORDER_NUMBER` (`order_number`),
  ADD KEY `IDX_ACTION_NAME_STATUS` (`action_name`,`action_status`),
  ADD KEY `IDX_ACTION_STATUS` (`action_status`,`deleted`),
  ADD KEY `IDX_ACTION_NAME` (`action_name`,`deleted`);

--
-- Indici per le tabelle `mass_action`
--
ALTER TABLE `mass_action`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`);

--
-- Indici per le tabelle `mass_email`
--
ALTER TABLE `mass_email`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_EMAIL_TEMPLATE_ID` (`email_template_id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_INBOUND_EMAIL_ID` (`inbound_email_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `mass_email_target_list`
--
ALTER TABLE `mass_email_target_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_MASS_EMAIL_ID_TARGET_LIST_ID` (`mass_email_id`,`target_list_id`),
  ADD KEY `IDX_MASS_EMAIL_ID` (`mass_email_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `mass_email_target_list_excluding`
--
ALTER TABLE `mass_email_target_list_excluding`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_MASS_EMAIL_ID_TARGET_LIST_ID` (`mass_email_id`,`target_list_id`),
  ADD KEY `IDX_MASS_EMAIL_ID` (`mass_email_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `meeting`
--
ALTER TABLE `meeting`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DATE_START_STATUS` (`date_start`,`status`),
  ADD KEY `IDX_DATE_START` (`date_start`,`deleted`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER_STATUS` (`assigned_user_id`,`status`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_UID` (`uid`),
  ADD KEY `IDX_APPUNTAMENTO_ID` (`appuntamento_id`),
  ADD KEY `IDX_GOOGLE_CALENDAR_ID` (`google_calendar_id`);
ALTER TABLE `meeting` ADD FULLTEXT KEY `IDX_SYSTEM_FULL_TEXT_SEARCH` (`name`);

--
-- Indici per le tabelle `meeting_user`
--
ALTER TABLE `meeting_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_USER_ID_MEETING_ID` (`user_id`,`meeting_id`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_MEETING_ID` (`meeting_id`);

--
-- Indici per le tabelle `next_number`
--
ALTER TABLE `next_number`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ENTITY_TYPE` (`entity_type`),
  ADD KEY `IDX_ENTITY_TYPE_FIELD_NAME` (`entity_type`,`field_name`);

--
-- Indici per le tabelle `note`
--
ALTER TABLE `note`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_CREATED_BY_NUMBER` (`created_by_id`,`number`),
  ADD KEY `IDX_RELATED` (`related_id`,`related_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_SUPER_PARENT` (`super_parent_id`,`super_parent_type`),
  ADD KEY `IDX_TYPE` (`type`),
  ADD KEY `IDX_TARGET_TYPE` (`target_type`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`),
  ADD KEY `IDX_PARENT_TYPE` (`parent_type`),
  ADD KEY `IDX_RELATED_ID` (`related_id`),
  ADD KEY `IDX_RELATED_TYPE` (`related_type`),
  ADD KEY `IDX_SUPER_PARENT_TYPE` (`super_parent_type`),
  ADD KEY `IDX_SUPER_PARENT_ID` (`super_parent_id`);
ALTER TABLE `note` ADD FULLTEXT KEY `IDX_SYSTEM_FULL_TEXT_SEARCH` (`post`);

--
-- Indici per le tabelle `note_portal`
--
ALTER TABLE `note_portal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NOTE_ID_PORTAL_ID` (`note_id`,`portal_id`),
  ADD KEY `IDX_NOTE_ID` (`note_id`),
  ADD KEY `IDX_PORTAL_ID` (`portal_id`);

--
-- Indici per le tabelle `note_team`
--
ALTER TABLE `note_team`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NOTE_ID_TEAM_ID` (`note_id`,`team_id`),
  ADD KEY `IDX_NOTE_ID` (`note_id`),
  ADD KEY `IDX_TEAM_ID` (`team_id`);

--
-- Indici per le tabelle `note_user`
--
ALTER TABLE `note_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NOTE_ID_USER_ID` (`note_id`,`user_id`),
  ADD KEY `IDX_NOTE_ID` (`note_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_RELATED` (`related_id`,`related_type`),
  ADD KEY `IDX_RELATED_PARENT` (`related_parent_id`,`related_parent_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_USER_ID_READ_RELATED_PARENT_TYPE` (`user_id`,`deleted`,`read`,`related_parent_type`),
  ADD KEY `IDX_ACTION_ID` (`action_id`),
  ADD KEY `IDX_USER` (`user_id`,`number`);

--
-- Indici per le tabelle `opportunity`
--
ALTER TABLE `opportunity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_STAGE` (`stage`,`deleted`),
  ADD KEY `IDX_LAST_STAGE` (`last_stage`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`,`deleted`),
  ADD KEY `IDX_CREATED_AT_STAGE` (`created_at`,`stage`),
  ADD KEY `IDX_ASSIGNED_USER_STAGE` (`assigned_user_id`,`stage`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_CAMPAIGN_ID` (`campaign_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_C_A_P_ID` (`c_a_p_id`),
  ADD KEY `IDX_APPUNTAMENTO_ID` (`appuntamento_id`),
  ADD KEY `IDX_PROSPECT_ID` (`prospect_id`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`),
  ADD KEY `IDX_PRICE_BOOK_ID` (`price_book_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`),
  ADD KEY `IDX_PRODUCT_BRAND_ID` (`product_brand_id`);

--
-- Indici per le tabelle `opportunity_item`
--
ALTER TABLE `opportunity_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_OPPORTUNITY_ID` (`opportunity_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `outlook_calendar`
--
ALTER TABLE `outlook_calendar`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `outlook_calendar_event`
--
ALTER TABLE `outlook_calendar_event`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CALENDAR_EVENT` (`calendar_id`,`event_id`),
  ADD KEY `IDX_EVENT_ID` (`event_id`),
  ADD KEY `IDX_I_CAL_U_ID` (`i_cal_u_id`),
  ADD KEY `IDX_CALENDAR_ID` (`calendar_id`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `outlook_calendar_user`
--
ALTER TABLE `outlook_calendar_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CALENDAR_ID` (`calendar_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `outlook_contacts_entity`
--
ALTER TABLE `outlook_contacts_entity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_OUTLOOK_USER_ID` (`outlook_user_id`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `o_auth_account`
--
ALTER TABLE `o_auth_account`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PROVIDER_ID` (`provider_id`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `o_auth_provider`
--
ALTER TABLE `o_auth_provider`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `pagamenti_provvigionali`
--
ALTER TABLE `pagamenti_provvigionali`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_OPPORTUNITA_ID` (`opportunita_id`),
  ADD KEY `IDX_CLIENTE_ID` (`cliente_id`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`);

--
-- Indici per le tabelle `password_change_request`
--
ALTER TABLE `password_change_request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_REQUEST_ID` (`request_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `payment_allocation`
--
ALTER TABLE `payment_allocation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_CREDIT_NOTE_ID` (`credit_note_id`),
  ADD KEY `IDX_PAYMENT_ENTRY_ID` (`payment_entry_id`),
  ADD KEY `IDX_WRITE_OFF_ENTRY_ID` (`write_off_entry_id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_SUPPLIER_CREDIT_ID` (`supplier_credit_id`);

--
-- Indici per le tabelle `payment_channel`
--
ALTER TABLE `payment_channel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_RECORD` (`record_id`,`record_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `payment_channel_sepa_credit_transfer`
--
ALTER TABLE `payment_channel_sepa_credit_transfer`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `payment_channel_sepa_direct_debit`
--
ALTER TABLE `payment_channel_sepa_direct_debit`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `payment_channel_wire_transfer`
--
ALTER TABLE `payment_channel_wire_transfer`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `payment_entry`
--
ALTER TABLE `payment_entry`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_TRANSACTION_ID` (`transaction_id`),
  ADD KEY `IDX_STATUS` (`status`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_SUPPLIER_ID` (`supplier_id`),
  ADD KEY `IDX_METHOD_ID` (`method_id`),
  ADD KEY `IDX_ISSUED_BY_ID` (`issued_by_id`),
  ADD KEY `IDX_REQUEST_ID` (`request_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `payment_installment`
--
ALTER TABLE `payment_installment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_SOURCE` (`source_id`,`source_type`);

--
-- Indici per le tabelle `payment_mandate`
--
ALTER TABLE `payment_mandate`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_RECORD` (`record_id`,`record_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `payment_mandate_sepa`
--
ALTER TABLE `payment_mandate_sepa`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `payment_method`
--
ALTER TABLE `payment_method`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_CHANNEL_ID` (`channel_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `payment_request`
--
ALTER TABLE `payment_request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_EXPIRATION_DATE_STATUS` (`expiration_date`,`status`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_REFERENCE_ID` (`reference_id`),
  ADD KEY `IDX_METHOD_ID` (`method_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `payment_terms_profile`
--
ALTER TABLE `payment_terms_profile`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `payment_terms_profile_item`
--
ALTER TABLE `payment_terms_profile_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PROFILE_ID` (`profile_id`);

--
-- Indici per le tabelle `phone_number`
--
ALTER TABLE `phone_number`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_NUMERIC` (`numeric`);

--
-- Indici per le tabelle `portal`
--
ALTER TABLE `portal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CUSTOM_ID` (`custom_id`),
  ADD KEY `IDX_LAYOUT_SET_ID` (`layout_set_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_AUTHENTICATION_PROVIDER_ID` (`authentication_provider_id`);

--
-- Indici per le tabelle `portal_portal_role`
--
ALTER TABLE `portal_portal_role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PORTAL_ID_PORTAL_ROLE_ID` (`portal_id`,`portal_role_id`),
  ADD KEY `IDX_PORTAL_ID` (`portal_id`),
  ADD KEY `IDX_PORTAL_ROLE_ID` (`portal_role_id`);

--
-- Indici per le tabelle `portal_report`
--
ALTER TABLE `portal_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PORTAL_ID_REPORT_ID` (`portal_id`,`report_id`),
  ADD KEY `IDX_PORTAL_ID` (`portal_id`),
  ADD KEY `IDX_REPORT_ID` (`report_id`);

--
-- Indici per le tabelle `portal_role`
--
ALTER TABLE `portal_role`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `portal_role_user`
--
ALTER TABLE `portal_role_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PORTAL_ROLE_ID_USER_ID` (`portal_role_id`,`user_id`),
  ADD KEY `IDX_PORTAL_ROLE_ID` (`portal_role_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `portal_user`
--
ALTER TABLE `portal_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PORTAL_ID_USER_ID` (`portal_id`,`user_id`),
  ADD KEY `IDX_PORTAL_ID` (`portal_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `preferences`
--
ALTER TABLE `preferences`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `price_book`
--
ALTER TABLE `price_book`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PARENT_PRICE_BOOK_ID` (`parent_price_book_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `price_rule`
--
ALTER TABLE `price_rule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PRICE_BOOK_ID` (`price_book_id`),
  ADD KEY `IDX_PRODUCT_CATEGORY_ID` (`product_category_id`),
  ADD KEY `IDX_CONDITION_ID` (`condition_id`),
  ADD KEY `IDX_SUPPLIER_ID` (`supplier_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `price_rule_condition`
--
ALTER TABLE `price_rule_condition`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `prodotticontratti`
--
ALTER TABLE `prodotticontratti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PRODUCT_ID_QUOTE_ID` (`product_id`,`quote_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_QUOTE_ID` (`quote_id`);

--
-- Indici per le tabelle `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_OPPORTUNITA_ID` (`opportunita_id`),
  ADD KEY `IDX_TYPE_NAME` (`type`,`name`),
  ADD KEY `IDX_TYPE_STATUS` (`type`,`status`),
  ADD KEY `IDX_TYPE_CATEGORY_ID` (`type`,`category_id`),
  ADD KEY `IDX_BRAND_ID` (`brand_id`),
  ADD KEY `IDX_CATEGORY_ID` (`category_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_TEMPLATE_ID` (`template_id`);

--
-- Indici per le tabelle `product_attribute`
--
ALTER TABLE `product_attribute`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `product_attribute_option`
--
ALTER TABLE `product_attribute_option`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ATTRIBUTE_ID_ORDER` (`attribute_id`,`order`),
  ADD KEY `IDX_ATTRIBUTE_ID` (`attribute_id`);

--
-- Indici per le tabelle `product_brand`
--
ALTER TABLE `product_brand`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`);

--
-- Indici per le tabelle `product_category`
--
ALTER TABLE `product_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`);

--
-- Indici per le tabelle `product_category_path`
--
ALTER TABLE `product_category_path`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASCENDOR_ID` (`ascendor_id`),
  ADD KEY `IDX_DESCENDOR_ID` (`descendor_id`);

--
-- Indici per le tabelle `product_price`
--
ALTER TABLE `product_price`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PRICE_BOOK_GROUP` (`price_book_id`,`product_id`,`status`),
  ADD KEY `IDX_PRICE_BOOK_ID` (`price_book_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `product_product_attribute`
--
ALTER TABLE `product_product_attribute`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PRODUCT_ID_PRODUCT_ATTRIBUTE_ID` (`product_id`,`product_attribute_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_PRODUCT_ATTRIBUTE_ID` (`product_attribute_id`);

--
-- Indici per le tabelle `product_product_attribute_option`
--
ALTER TABLE `product_product_attribute_option`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PRODUCT_ID_PRODUCT_ATTRIBUTE_OPTION_ID` (`product_id`,`product_attribute_option_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_PRODUCT_ATTRIBUTE_OPTION_ID` (`product_attribute_option_id`);

--
-- Indici per le tabelle `product_tax_class`
--
ALTER TABLE `product_tax_class`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PRODUCT_ID_TAX_CLASS_ID` (`product_id`,`tax_class_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CLASS_ID` (`tax_class_id`);

--
-- Indici per le tabelle `product_variant_product_attribute_option`
--
ALTER TABLE `product_variant_product_attribute_option`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PRODUCT_ID_PRODUCT_ATTRIBUTE_OPTION_ID` (`product_id`,`product_attribute_option_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_PRODUCT_ATTRIBUTE_OPTION_ID` (`product_attribute_option_id`);

--
-- Indici per le tabelle `prospect`
--
ALTER TABLE `prospect`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_FIRST_NAME` (`first_name`,`deleted`),
  ADD KEY `IDX_NAME` (`first_name`,`last_name`),
  ADD KEY `IDX_C_A_P_ID` (`c_a_p_id`),
  ADD KEY `IDX_LEAD_ID` (`lead_id`),
  ADD KEY `IDX_CLIENTE_ID` (`cliente_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`),
  ADD KEY `IDX_PRODUCT_BRAND_ID` (`product_brand_id`);

--
-- Indici per le tabelle `provvigione`
--
ALTER TABLE `provvigione`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CLIENTE_ID` (`cliente_id`),
  ADD KEY `IDX_CONTRATTO_ID` (`contratto_id`),
  ADD KEY `IDX_CONTRATTO_ARTICOLI_ID` (`contratto_articoli_id`);

--
-- Indici per le tabelle `provvigioni_regole`
--
ALTER TABLE `provvigioni_regole`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`),
  ADD KEY `IDX_PRODUCT_BRAND_ID` (`product_brand_id`);

--
-- Indici per le tabelle `purchase_order`
--
ALTER TABLE `purchase_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_SUPPLIER_ID` (`supplier_id`),
  ADD KEY `IDX_WAREHOUSE_ID` (`warehouse_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_SHIPPING_CONTACT_ID` (`shipping_contact_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `purchase_order_item`
--
ALTER TABLE `purchase_order_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PURCHASE_ORDER_ID` (`purchase_order_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `quote`
--
ALTER TABLE `quote`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_C_A_P_ID` (`c_a_p_id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_OPPORTUNITY_ID` (`opportunity_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_SHIPPING_CONTACT_ID` (`shipping_contact_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_PRICE_BOOK_ID` (`price_book_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `quote_item`
--
ALTER TABLE `quote_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_QUOTE_ID` (`quote_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `receipt_order`
--
ALTER TABLE `receipt_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_STATUS` (`status`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_SUPPLIER_ID` (`supplier_id`),
  ADD KEY `IDX_PURCHASE_ORDER_ID` (`purchase_order_id`),
  ADD KEY `IDX_RETURN_ORDER_ID` (`return_order_id`),
  ADD KEY `IDX_WAREHOUSE_ID` (`warehouse_id`),
  ADD KEY `IDX_SHIPPING_CONTACT_ID` (`shipping_contact_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `receipt_order_item`
--
ALTER TABLE `receipt_order_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_RECEIPT_ORDER_ID` (`receipt_order_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `receipt_order_received_item`
--
ALTER TABLE `receipt_order_received_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_RECEIPT_ORDER_ID` (`receipt_order_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_INVENTORY_NUMBER_ID` (`inventory_number_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `regole_provvigionali`
--
ALTER TABLE `regole_provvigionali`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREATED_AT_ID` (`created_at`,`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_FORNITORE_PARTNER_ID` (`fornitore_partner_id`),
  ADD KEY `IDX_BRAND_ID` (`brand_id`),
  ADD KEY `IDX_PRODUCT_CATEGORIA_ID` (`product_categoria_id`);

--
-- Indici per le tabelle `reminder`
--
ALTER TABLE `reminder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_REMIND_AT` (`remind_at`),
  ADD KEY `IDX_START_AT` (`start_at`),
  ADD KEY `IDX_TYPE` (`type`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`);

--
-- Indici per le tabelle `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CATEGORY_ID` (`category_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `report_category`
--
ALTER TABLE `report_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`);

--
-- Indici per le tabelle `report_category_path`
--
ALTER TABLE `report_category_path`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `report_filter`
--
ALTER TABLE `report_filter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_REPORT_ID` (`report_id`);

--
-- Indici per le tabelle `report_panel`
--
ALTER TABLE `report_panel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_REPORT_ID` (`report_id`);

--
-- Indici per le tabelle `report_target_list`
--
ALTER TABLE `report_target_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_TARGET_LIST_ID_REPORT_ID` (`target_list_id`,`report_id`),
  ADD KEY `IDX_REPORT_ID` (`report_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `report_user`
--
ALTER TABLE `report_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_USER_ID_REPORT_ID` (`user_id`,`report_id`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_REPORT_ID` (`report_id`);

--
-- Indici per le tabelle `return_order`
--
ALTER TABLE `return_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_SALES_ORDER_ID` (`sales_order_id`),
  ADD KEY `IDX_WAREHOUSE_ID` (`warehouse_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_SHIPPING_CONTACT_ID` (`shipping_contact_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `return_order_item`
--
ALTER TABLE `return_order_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_RETURN_ORDER_ID` (`return_order_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_INVENTORY_NUMBER_ID` (`inventory_number_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `role_team`
--
ALTER TABLE `role_team`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ROLE_ID_TEAM_ID` (`role_id`,`team_id`),
  ADD KEY `IDX_ROLE_ID` (`role_id`),
  ADD KEY `IDX_TEAM_ID` (`team_id`);

--
-- Indici per le tabelle `role_user`
--
ALTER TABLE `role_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_ROLE_ID_USER_ID` (`role_id`,`user_id`),
  ADD KEY `IDX_ROLE_ID` (`role_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `rounding_profile`
--
ALTER TABLE `rounding_profile`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ORDER_STATUS` (`order`,`status`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `sales_order`
--
ALTER TABLE `sales_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_OPPORTUNITY_ID` (`opportunity_id`),
  ADD KEY `IDX_QUOTE_ID` (`quote_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_SHIPPING_CONTACT_ID` (`shipping_contact_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_PRICE_BOOK_ID` (`price_book_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `sales_order_item`
--
ALTER TABLE `sales_order_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_SALES_ORDER_ID` (`sales_order_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `scheduled_job`
--
ALTER TABLE `scheduled_job`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `scheduled_job_log_record`
--
ALTER TABLE `scheduled_job_log_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_SCHEDULED_JOB_ID_EXECUTION_TIME` (`scheduled_job_id`,`execution_time`),
  ADD KEY `IDX_SCHEDULED_JOB_ID` (`scheduled_job_id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`);

--
-- Indici per le tabelle `shipping_provider`
--
ALTER TABLE `shipping_provider`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `sms`
--
ALTER TABLE `sms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DATE_SENT` (`date_sent`,`deleted`),
  ADD KEY `IDX_DATE_SENT_STATUS` (`date_sent`,`status`,`deleted`),
  ADD KEY `IDX_FROM_PHONE_NUMBER_ID` (`from_phone_number_id`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_REPLIED_ID` (`replied_id`);

--
-- Indici per le tabelle `sms_phone_number`
--
ALTER TABLE `sms_phone_number`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_SMS_ID_PHONE_NUMBER_ID_ADDRESS_TYPE` (`sms_id`,`phone_number_id`,`address_type`),
  ADD KEY `IDX_SMS_ID` (`sms_id`),
  ADD KEY `IDX_PHONE_NUMBER_ID` (`phone_number_id`);

--
-- Indici per le tabelle `star_subscription`
--
ALTER TABLE `star_subscription`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_USER_ENTITY` (`user_id`,`entity_id`,`entity_type`),
  ADD KEY `IDX_USER_ENTITY_TYPE` (`user_id`,`entity_type`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`),
  ADD KEY `IDX_USER` (`user_id`);

--
-- Indici per le tabelle `stream_subscription`
--
ALTER TABLE `stream_subscription`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_USER_ENTITY` (`user_id`,`entity_id`,`entity_type`),
  ADD KEY `IDX_ENTITY` (`entity_id`,`entity_type`),
  ADD KEY `IDX_USER` (`user_id`);

--
-- Indici per le tabelle `subscription`
--
ALTER TABLE `subscription`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_PRICE_BOOK_ID` (`price_book_id`),
  ADD KEY `IDX_PAYMENT_METHOD_ID` (`payment_method_id`),
  ADD KEY `IDX_BILLING_PLAN_ID` (`billing_plan_id`),
  ADD KEY `IDX_PRIMARY_PRODUCT_ID` (`primary_product_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `subscription_billing_plan`
--
ALTER TABLE `subscription_billing_plan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `subscription_billing_plan_subscription_template`
--
ALTER TABLE `subscription_billing_plan_subscription_template`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_SUBSCRIPTION_BILLING_PLAN_ID_SUBSCRIPTION_TEMPLATE_ID` (`subscription_billing_plan_id`,`subscription_template_id`),
  ADD KEY `IDX_SUBSCRIPTION_BILLING_PLAN_ID` (`subscription_billing_plan_id`),
  ADD KEY `IDX_SUBSCRIPTION_TEMPLATE_ID` (`subscription_template_id`);

--
-- Indici per le tabelle `subscription_item`
--
ALTER TABLE `subscription_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_SUBSCRIPTION_ID` (`subscription_id`),
  ADD KEY `IDX_SUBSCRIPTION_UPDATE_ID` (`subscription_update_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `subscription_period`
--
ALTER TABLE `subscription_period`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_STATUS_END_DATE` (`status`,`end_date`),
  ADD KEY `IDX_STATUS_START_DATE` (`status`,`start_date`),
  ADD KEY `IDX_SUBSCRIPTION_ID_END_DATE` (`subscription_id`,`end_date`),
  ADD KEY `IDX_BILLING_STATUS_START_DATE` (`billing_status`,`start_date`),
  ADD KEY `IDX_START_DATE` (`start_date`),
  ADD KEY `IDX_SUBSCRIPTION_ID` (`subscription_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `subscription_template`
--
ALTER TABLE `subscription_template`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_PRIMARY_PRODUCT_ID` (`primary_product_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `subscription_template_item`
--
ALTER TABLE `subscription_template_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_SUBSCRIPTION_TEMPLATE_ID` (`subscription_template_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`);

--
-- Indici per le tabelle `subscription_update`
--
ALTER TABLE `subscription_update`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_SUBSCRIPTION_ID` (`subscription_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `supplier_bill`
--
ALTER TABLE `supplier_bill`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_SUPPLIER_ID` (`supplier_id`),
  ADD KEY `IDX_PURCHASE_ORDER_ID` (`purchase_order_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_ISSUED_BY_ID` (`issued_by_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `supplier_bill_item`
--
ALTER TABLE `supplier_bill_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_SUPPLIER_BILL_ID` (`supplier_bill_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `supplier_credit`
--
ALTER TABLE `supplier_credit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_SUPPLIER_ID` (`supplier_id`),
  ADD KEY `IDX_SUPPLIER_BILL_ID` (`supplier_bill_id`),
  ADD KEY `IDX_BILLING_CONTACT_ID` (`billing_contact_id`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_ISSUED_BY_ID` (`issued_by_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `supplier_credit_item`
--
ALTER TABLE `supplier_credit_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_SUPPLIER_CREDIT_ID` (`supplier_credit_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `supplier_product_price`
--
ALTER TABLE `supplier_product_price`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_SUPPLIER_GROUP` (`supplier_id`,`product_id`,`status`),
  ADD KEY `IDX_SUPPLIER_ID` (`supplier_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `system_data`
--
ALTER TABLE `system_data`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `target`
--
ALTER TABLE `target`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_FIRST_NAME` (`first_name`,`deleted`),
  ADD KEY `IDX_NAME` (`first_name`,`last_name`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `target_list`
--
ALTER TABLE `target_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_CATEGORY_ID` (`category_id`);

--
-- Indici per le tabelle `target_list_category`
--
ALTER TABLE `target_list_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`);

--
-- Indici per le tabelle `target_list_category_path`
--
ALTER TABLE `target_list_category_path`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ASCENDOR_ID` (`ascendor_id`),
  ADD KEY `IDX_DESCENDOR_ID` (`descendor_id`);

--
-- Indici per le tabelle `target_list_user`
--
ALTER TABLE `target_list_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_USER_ID_TARGET_LIST_ID` (`user_id`,`target_list_id`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_TARGET_LIST_ID` (`target_list_id`);

--
-- Indici per le tabelle `task`
--
ALTER TABLE `task`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DATE_START_STATUS` (`date_start`,`status`),
  ADD KEY `IDX_DATE_END_STATUS` (`date_end`,`status`),
  ADD KEY `IDX_DATE_START` (`date_start`,`deleted`),
  ADD KEY `IDX_STATUS` (`status`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER_STATUS` (`assigned_user_id`,`status`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_OPPORTUNITA_ID` (`opportunita_id`),
  ADD KEY `IDX_EMAIL_ID` (`email_id`);

--
-- Indici per le tabelle `tax`
--
ALTER TABLE `tax`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_SHIPPING_TAX_CODE_ID` (`shipping_tax_code_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `tax_allocation_item`
--
ALTER TABLE `tax_allocation_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_ITEM` (`item_id`,`item_type`),
  ADD KEY `IDX_SOURCE` (`source_id`,`source_type`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_ALLOCATION_ID` (`allocation_id`),
  ADD KEY `IDX_PAYMENT_ENTRY_ID` (`payment_entry_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`);

--
-- Indici per le tabelle `tax_class`
--
ALTER TABLE `tax_class`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `tax_code`
--
ALTER TABLE `tax_code`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ORDER_STATUS` (`order`,`status`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `tax_code_tax_code`
--
ALTER TABLE `tax_code_tax_code`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_RIGHT_ID_LEFT_ID` (`right_id`,`left_id`),
  ADD KEY `IDX_RIGHT_ID` (`right_id`),
  ADD KEY `IDX_LEFT_ID` (`left_id`);

--
-- Indici per le tabelle `tax_item_rule`
--
ALTER TABLE `tax_item_rule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ORDER` (`order`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_CLASS_ID` (`class_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`),
  ADD KEY `IDX_CREATED_BY` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY` (`modified_by_id`);

--
-- Indici per le tabelle `tax_line_item`
--
ALTER TABLE `tax_line_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_ITEM` (`item_id`,`item_type`),
  ADD KEY `IDX_SOURCE` (`source_id`,`source_type`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`);

--
-- Indici per le tabelle `tax_purchase_rule`
--
ALTER TABLE `tax_purchase_rule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_STATUS_ORDER` (`status`,`order`),
  ADD KEY `IDX_ORDER` (`order`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `tax_rule`
--
ALTER TABLE `tax_rule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_STATUS_ORDER` (`status`,`order`),
  ADD KEY `IDX_ORDER` (`order`),
  ADD KEY `IDX_TAX_ID` (`tax_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `tax_total_item`
--
ALTER TABLE `tax_total_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_SOURCE` (`source_id`,`source_type`),
  ADD KEY `IDX_TAX_CODE_ID` (`tax_code_id`);

--
-- Indici per le tabelle `team`
--
ALTER TABLE `team`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_LAYOUT_SET_ID` (`layout_set_id`),
  ADD KEY `IDX_WORKING_TIME_CALENDAR_ID` (`working_time_calendar_id`);

--
-- Indici per le tabelle `team_user`
--
ALTER TABLE `team_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_TEAM_ID_USER_ID` (`team_id`,`user_id`),
  ADD KEY `IDX_TEAM_ID` (`team_id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `team_workflow`
--
ALTER TABLE `team_workflow`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_TEAM_ID_WORKFLOW_ID` (`team_id`,`workflow_id`),
  ADD KEY `IDX_TEAM_ID` (`team_id`),
  ADD KEY `IDX_WORKFLOW_ID` (`workflow_id`);

--
-- Indici per le tabelle `template`
--
ALTER TABLE `template`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `transfer_order`
--
ALTER TABLE `transfer_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_STATUS` (`status`),
  ADD KEY `IDX_FROM_WAREHOUSE_ID` (`from_warehouse_id`),
  ADD KEY `IDX_TO_WAREHOUSE_ID` (`to_warehouse_id`),
  ADD KEY `IDX_SHIPPING_PROVIDER_ID` (`shipping_provider_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`);

--
-- Indici per le tabelle `transfer_order_item`
--
ALTER TABLE `transfer_order_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_TRANSFER_ORDER_ID` (`transfer_order_id`),
  ADD KEY `IDX_PRODUCT_ID` (`product_id`),
  ADD KEY `IDX_INVENTORY_NUMBER_ID` (`inventory_number_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `two_factor_code`
--
ALTER TABLE `two_factor_code`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_USER_ID_METHOD` (`user_id`,`method`),
  ADD KEY `IDX_USER_ID_METHOD_IS_ACTIVE` (`user_id`,`method`,`is_active`),
  ADD KEY `IDX_USER_ID_METHOD_CREATED_AT` (`user_id`,`method`,`created_at`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `unique_id`
--
ALTER TABLE `unique_id`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`);

--
-- Indici per le tabelle `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_USER_NAME_DELETE_ID` (`user_name`,`delete_id`),
  ADD KEY `IDX_TYPE` (`type`),
  ADD KEY `IDX_DEFAULT_TEAM_ID` (`default_team_id`),
  ADD KEY `IDX_CONTACT_ID` (`contact_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_DASHBOARD_TEMPLATE_ID` (`dashboard_template_id`),
  ADD KEY `IDX_USER_NAME` (`user_name`),
  ADD KEY `IDX_WORKING_TIME_CALENDAR_ID` (`working_time_calendar_id`),
  ADD KEY `IDX_LAYOUT_SET_ID` (`layout_set_id`);

--
-- Indici per le tabelle `user_data`
--
ALTER TABLE `user_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `user_reaction`
--
ALTER TABLE `user_reaction`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_PARENT_USER_TYPE` (`parent_id`,`parent_type`,`user_id`,`type`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_PARENT` (`parent_id`,`parent_type`);

--
-- Indici per le tabelle `user_working_time_range`
--
ALTER TABLE `user_working_time_range`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_USER_ID_WORKING_TIME_RANGE_ID` (`user_id`,`working_time_range_id`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_WORKING_TIME_RANGE_ID` (`working_time_range_id`);

--
-- Indici per le tabelle `warehouse`
--
ALTER TABLE `warehouse`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`),
  ADD KEY `IDX_ORDER` (`order`),
  ADD KEY `IDX_IS_AVAILABLE_FOR_STOCK` (`is_available_for_stock`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `webhook`
--
ALTER TABLE `webhook`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_EVENT` (`event`),
  ADD KEY `IDX_ENTITY_TYPE_TYPE` (`entity_type`,`type`),
  ADD KEY `IDX_ENTITY_TYPE_FIELD` (`entity_type`,`field`),
  ADD KEY `IDX_USER_ID` (`user_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `webhook_event_queue_item`
--
ALTER TABLE `webhook_event_queue_item`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`),
  ADD KEY `IDX_USER_ID` (`user_id`);

--
-- Indici per le tabelle `webhook_queue_item`
--
ALTER TABLE `webhook_queue_item`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_NUMBER` (`number`),
  ADD KEY `IDX_WEBHOOK_ID` (`webhook_id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`);

--
-- Indici per le tabelle `workflow`
--
ALTER TABLE `workflow`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_PORTAL_ID` (`portal_id`),
  ADD KEY `IDX_TARGET_REPORT_ID` (`target_report_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_FLOWCHART_ID` (`flowchart_id`),
  ADD KEY `IDX_TYPE` (`type`),
  ADD KEY `IDX_CATEGORY_ID` (`category_id`);

--
-- Indici per le tabelle `workflow_category`
--
ALTER TABLE `workflow_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_PARENT_ID` (`parent_id`);

--
-- Indici per le tabelle `workflow_category_path`
--
ALTER TABLE `workflow_category_path`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `workflow_log_record`
--
ALTER TABLE `workflow_log_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_WORKFLOW_ID` (`workflow_id`),
  ADD KEY `IDX_TARGET` (`target_id`,`target_type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`);

--
-- Indici per le tabelle `workflow_round_robin`
--
ALTER TABLE `workflow_round_robin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_ACTION_ID` (`action_id`),
  ADD KEY `IDX_FLOWCHART_ID` (`flowchart_id`),
  ADD KEY `IDX_WORKFLOW_ID` (`workflow_id`);

--
-- Indici per le tabelle `working_time_calendar`
--
ALTER TABLE `working_time_calendar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `working_time_calendar_working_time_range`
--
ALTER TABLE `working_time_calendar_working_time_range`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_WORKING_TIME_CALENDAR_ID_WORKING_TIME_RANGE_ID` (`working_time_calendar_id`,`working_time_range_id`),
  ADD KEY `IDX_WORKING_TIME_CALENDAR_ID` (`working_time_calendar_id`),
  ADD KEY `IDX_WORKING_TIME_RANGE_ID` (`working_time_range_id`);

--
-- Indici per le tabelle `working_time_range`
--
ALTER TABLE `working_time_range`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_TYPE_RANGE` (`type`,`date_start`,`date_end`),
  ADD KEY `IDX_TYPE` (`type`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `write_off_entry`
--
ALTER TABLE `write_off_entry`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_CREATED_AT` (`created_at`),
  ADD KEY `IDX_NUMBER` (`number`),
  ADD KEY `IDX_STATUS` (`status`),
  ADD KEY `IDX_ACCOUNT_ID` (`account_id`),
  ADD KEY `IDX_ISSUED_BY_ID` (`issued_by_id`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`);

--
-- Indici per le tabelle `zona`
--
ALTER TABLE `zona`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_NAME` (`name`,`deleted`),
  ADD KEY `IDX_ASSIGNED_USER` (`assigned_user_id`,`deleted`),
  ADD KEY `IDX_CREATED_BY_ID` (`created_by_id`),
  ADD KEY `IDX_MODIFIED_BY_ID` (`modified_by_id`),
  ADD KEY `IDX_ASSIGNED_USER_ID` (`assigned_user_id`),
  ADD KEY `IDX_MACRO_AREA_ID` (`macro_area_id`),
  ADD KEY `IDX_AREA_ID` (`area_id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `account_contact`
--
ALTER TABLE `account_contact`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `account_document`
--
ALTER TABLE `account_document`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `account_payment_method`
--
ALTER TABLE `account_payment_method`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `account_portal_user`
--
ALTER TABLE `account_portal_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `account_target_list`
--
ALTER TABLE `account_target_list`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `action_history_record`
--
ALTER TABLE `action_history_record`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `app_log_record`
--
ALTER TABLE `app_log_record`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `autofollow`
--
ALTER TABLE `autofollow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bpmn_flowchart_category_path`
--
ALTER TABLE `bpmn_flowchart_category_path`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bpmn_flow_node`
--
ALTER TABLE `bpmn_flow_node`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bpmn_signal_listener`
--
ALTER TABLE `bpmn_signal_listener`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `call_contact`
--
ALTER TABLE `call_contact`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `call_lead`
--
ALTER TABLE `call_lead`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `call_user`
--
ALTER TABLE `call_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `campaign_target_list`
--
ALTER TABLE `campaign_target_list`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `campaign_target_list_excluding`
--
ALTER TABLE `campaign_target_list_excluding`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `case`
--
ALTER TABLE `case`
  MODIFY `number` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `case_contact`
--
ALTER TABLE `case_contact`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `case_knowledge_base_article`
--
ALTER TABLE `case_knowledge_base_article`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `contact_document`
--
ALTER TABLE `contact_document`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `contact_meeting`
--
ALTER TABLE `contact_meeting`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `contact_opportunity`
--
ALTER TABLE `contact_opportunity`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `contact_target_list`
--
ALTER TABLE `contact_target_list`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `credit_note_subscription_update`
--
ALTER TABLE `credit_note_subscription_update`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `document_folder_path`
--
ALTER TABLE `document_folder_path`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `document_lead`
--
ALTER TABLE `document_lead`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `document_opportunity`
--
ALTER TABLE `document_opportunity`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `email_email_account`
--
ALTER TABLE `email_email_account`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `email_email_address`
--
ALTER TABLE `email_email_address`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `email_inbound_email`
--
ALTER TABLE `email_inbound_email`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `email_template_category_path`
--
ALTER TABLE `email_template_category_path`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `email_user`
--
ALTER TABLE `email_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `entity_collaborator`
--
ALTER TABLE `entity_collaborator`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `entity_email_address`
--
ALTER TABLE `entity_email_address`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `entity_phone_number`
--
ALTER TABLE `entity_phone_number`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `entity_team`
--
ALTER TABLE `entity_team`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `entity_user`
--
ALTER TABLE `entity_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `group_email_folder_team`
--
ALTER TABLE `group_email_folder_team`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `import_entity`
--
ALTER TABLE `import_entity`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `inbound_email_team`
--
ALTER TABLE `inbound_email_team`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `inventory_number`
--
ALTER TABLE `inventory_number`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `inventory_transaction`
--
ALTER TABLE `inventory_transaction`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `invoice_payment_method`
--
ALTER TABLE `invoice_payment_method`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `invoice_payment_request`
--
ALTER TABLE `invoice_payment_request`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `invoice_subscription_period`
--
ALTER TABLE `invoice_subscription_period`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `invoice_subscription_update`
--
ALTER TABLE `invoice_subscription_update`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `job`
--
ALTER TABLE `job`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `knowledge_base_article_knowledge_base_category`
--
ALTER TABLE `knowledge_base_article_knowledge_base_category`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `knowledge_base_article_portal`
--
ALTER TABLE `knowledge_base_article_portal`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `knowledge_base_category_path`
--
ALTER TABLE `knowledge_base_category_path`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `lead_capture_log_record`
--
ALTER TABLE `lead_capture_log_record`
  MODIFY `number` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `lead_meeting`
--
ALTER TABLE `lead_meeting`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `lead_target_list`
--
ALTER TABLE `lead_target_list`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mail_chimp_queue`
--
ALTER TABLE `mail_chimp_queue`
  MODIFY `order_number` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mass_email_target_list`
--
ALTER TABLE `mass_email_target_list`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mass_email_target_list_excluding`
--
ALTER TABLE `mass_email_target_list_excluding`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `meeting_user`
--
ALTER TABLE `meeting_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `note`
--
ALTER TABLE `note`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `note_portal`
--
ALTER TABLE `note_portal`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `note_team`
--
ALTER TABLE `note_team`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `note_user`
--
ALTER TABLE `note_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `notification`
--
ALTER TABLE `notification`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `payment_allocation`
--
ALTER TABLE `payment_allocation`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `portal_portal_role`
--
ALTER TABLE `portal_portal_role`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `portal_report`
--
ALTER TABLE `portal_report`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `portal_role_user`
--
ALTER TABLE `portal_role_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `portal_user`
--
ALTER TABLE `portal_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `prodotticontratti`
--
ALTER TABLE `prodotticontratti`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `product_category_path`
--
ALTER TABLE `product_category_path`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `product_product_attribute`
--
ALTER TABLE `product_product_attribute`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `product_product_attribute_option`
--
ALTER TABLE `product_product_attribute_option`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `product_tax_class`
--
ALTER TABLE `product_tax_class`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `product_variant_product_attribute_option`
--
ALTER TABLE `product_variant_product_attribute_option`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `report_category_path`
--
ALTER TABLE `report_category_path`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `report_target_list`
--
ALTER TABLE `report_target_list`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `report_user`
--
ALTER TABLE `report_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `role_team`
--
ALTER TABLE `role_team`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `role_user`
--
ALTER TABLE `role_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `sms_phone_number`
--
ALTER TABLE `sms_phone_number`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `star_subscription`
--
ALTER TABLE `star_subscription`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `stream_subscription`
--
ALTER TABLE `stream_subscription`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `subscription_billing_plan_subscription_template`
--
ALTER TABLE `subscription_billing_plan_subscription_template`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `subscription_update`
--
ALTER TABLE `subscription_update`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `target_list_category_path`
--
ALTER TABLE `target_list_category_path`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `target_list_user`
--
ALTER TABLE `target_list_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `tax_code_tax_code`
--
ALTER TABLE `tax_code_tax_code`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `team_user`
--
ALTER TABLE `team_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `team_workflow`
--
ALTER TABLE `team_workflow`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `user_working_time_range`
--
ALTER TABLE `user_working_time_range`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `webhook_event_queue_item`
--
ALTER TABLE `webhook_event_queue_item`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `webhook_queue_item`
--
ALTER TABLE `webhook_queue_item`
  MODIFY `number` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `workflow_category_path`
--
ALTER TABLE `workflow_category_path`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `working_time_calendar_working_time_range`
--
ALTER TABLE `working_time_calendar_working_time_range`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
