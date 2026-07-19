-- ========================================
-- Crea tabella regola_provvigionale (Regole provvigioni)
-- Eseguire se php rebuild.php non l'ha creata.
-- ========================================

CREATE TABLE IF NOT EXISTS `regola_provvigionale` (
    `id` VARCHAR(24) NOT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `deleted` TINYINT(1) DEFAULT 0,
    `description` MEDIUMTEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `modified_at` DATETIME DEFAULT NULL,
    `created_by_id` VARCHAR(24) DEFAULT NULL,
    `modified_by_id` VARCHAR(24) DEFAULT NULL,
    `assigned_user_id` VARCHAR(24) DEFAULT NULL,
    `attiva` TINYINT(1) DEFAULT 1,
    `priorita` INT DEFAULT 100,
    `regime_provvigione` VARCHAR(100) DEFAULT NULL,
    `tipo_calcolo` VARCHAR(100) DEFAULT 'PercentualeImponibile',
    `tipo_provvigione_record` VARCHAR(100) DEFAULT 'Provvigione Base',
    `percentuale` DOUBLE DEFAULT NULL,
    `percentuale_addizionale` DOUBLE DEFAULT NULL,
    `coefficiente` DOUBLE DEFAULT NULL,
    `gettone_importo` DOUBLE DEFAULT NULL,
    `gettone_importo_currency` VARCHAR(3) DEFAULT 'EUR',
    `importo_fisso_pod` DOUBLE DEFAULT NULL,
    `importo_fisso_pod_currency` VARCHAR(3) DEFAULT 'EUR',
    `margine_min` DOUBLE DEFAULT NULL,
    `margine_max` DOUBLE DEFAULT NULL,
    `inflow_min` DOUBLE DEFAULT NULL,
    `inflow_min_currency` VARCHAR(3) DEFAULT 'EUR',
    `inflow_max` DOUBLE DEFAULT NULL,
    `inflow_max_currency` VARCHAR(3) DEFAULT 'EUR',
    `pod_min` INT DEFAULT NULL,
    `pod_max` INT DEFAULT NULL,
    `giorni_liquidazione` INT DEFAULT NULL,
    `gruppo_provvigione` VARCHAR(100) DEFAULT NULL,
    `fornitore_partner_id` VARCHAR(24) DEFAULT NULL,
    `fornitore_partner_name` VARCHAR(255) DEFAULT NULL,
    `product_brand_id` VARCHAR(24) DEFAULT NULL,
    `product_brand_name` VARCHAR(255) DEFAULT NULL,
    `product_category_id` VARCHAR(24) DEFAULT NULL,
    `product_category_name` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `IDX_REGOLA_PROVVIGIONALE_NAME` (`name`, `deleted`),
    KEY `IDX_REGOLA_PROVVIGIONALE_ASSIGNED_USER` (`assigned_user_id`, `deleted`),
    KEY `IDX_REGOLA_PROVVIGIONALE_CREATED_AT` (`created_at`),
    KEY `IDX_REGOLA_PROVVIGIONALE_PRIORITA` (`priorita`, `deleted`),
    KEY `IDX_REGOLA_PROVVIGIONALE_REGIME` (`regime_provvigione`, `deleted`),
    KEY `IDX_REGOLA_PROVVIGIONALE_FORNITORE` (`fornitore_partner_id`, `deleted`),
    KEY `IDX_REGOLA_PROVVIGIONALE_BRAND` (`product_brand_id`, `deleted`),
    KEY `IDX_REGOLA_PROVVIGIONALE_CATEGORY` (`product_category_id`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link provvigione → regola (se mancante)
ALTER TABLE `provvigione`
    ADD COLUMN IF NOT EXISTS `regola_provvigionale_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `regola_provvigionale_name` VARCHAR(255) DEFAULT NULL;
