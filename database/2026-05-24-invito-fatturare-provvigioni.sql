-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-24
-- Invito a fatturare, date attivazione, forecast provvigioni
-- ========================================
-- Dopo deploy metadata: clear-cache + rebuild
-- ========================================

-- Tabella InvitoAFatturare (creata da rebuild; colonne custom se tabella esiste già)
-- Espo crea la tabella invito_a_fatturare al rebuild.

ALTER TABLE `provvigione`
    ADD COLUMN IF NOT EXISTS `stato_provvigione` VARCHAR(100) DEFAULT 'Consolidata',
    ADD COLUMN IF NOT EXISTS `regime_provvigione` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `data_installazione` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `data_attivazione` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `data_competenza` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `data_liquidazione_prevista` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `data_liquidazione_effettiva` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `importo_previsto` DOUBLE PRECISION DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `importo_consolidato` DOUBLE PRECISION DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `scostamento_importo` DOUBLE PRECISION DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `giorni_liquidazione_da_attivazione` INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `appuntamento_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `appuntamento_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `opportunita_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `opportunita_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_category_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_category_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `invito_a_fatturare_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `invito_a_fatturare_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fornitore_partner_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fornitore_partner_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_name` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `appuntamento`
    ADD COLUMN IF NOT EXISTS `data_installazione` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `data_attivazione` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `importo_imponibile_previsto` DOUBLE PRECISION DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `importo_trattativa` DOUBLE PRECISION DEFAULT NULL;

ALTER TABLE `quote`
    ADD COLUMN IF NOT EXISTS `data_installazione` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `data_attivazione` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `data_competenza` DATE DEFAULT NULL;

ALTER TABLE `product_category`
    ADD COLUMN IF NOT EXISTS `regime_provvigione` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `pagamenti_provvigionali`
    ADD COLUMN IF NOT EXISTS `invito_a_fatturare_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `invito_a_fatturare_name` VARCHAR(255) DEFAULT NULL;
