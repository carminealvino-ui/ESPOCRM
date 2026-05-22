-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-22
-- AUTORE: CARMINE ALVINO + IA
-- FILE:
-- database/2026-05-22-structure-fornitore-partner-product-brand.sql
-- ========================================
--
-- STORICO FIX
-- ----------------------------------------
-- 1.0.0
-- Struttura DB per sostituire progressivamente il campo azienda con:
-- - fornitorePartner
-- - productBrand
--
-- OBIETTIVO:
-- Aggiungere le colonne tecniche necessarie per i nuovi campi link
-- senza migrare ancora i dati storici.
--
-- IMPORTANTE:
-- 1) Eseguire prima un backup completo del database.
-- 2) Eseguire questo script dopo aver caricato i metadata aggiornati.
-- 3) Dopo l'esecuzione: php command.php clear-cache && php command.php rebuild
-- 4) La migrazione dati azienda -> productBrand/fornitorePartner si fa dopo,
--    quando la mappatura brand/partner e' confermata.
--
-- ROLLBACK:
-- usare backup completo DB.
--
-- ========================================

-- ========================================
-- PRODUCT BRAND -> FORNITORE PARTNER
-- ========================================

ALTER TABLE `product_brand`
    ADD COLUMN IF NOT EXISTS `fornitore_partner_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fornitore_partner_name` VARCHAR(255) DEFAULT NULL;

-- ========================================
-- PROSPECT
-- ========================================

ALTER TABLE `prospect`
    ADD COLUMN IF NOT EXISTS `fornitore_partner_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fornitore_partner_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_name` VARCHAR(255) DEFAULT NULL;

-- ========================================
-- APPUNTAMENTO
-- ========================================

ALTER TABLE `appuntamento`
    ADD COLUMN IF NOT EXISTS `fornitore_partner_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fornitore_partner_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_name` VARCHAR(255) DEFAULT NULL;

-- ========================================
-- LEAD
-- ========================================

ALTER TABLE `lead`
    ADD COLUMN IF NOT EXISTS `fornitore_partner_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fornitore_partner_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_name` VARCHAR(255) DEFAULT NULL;

-- ========================================
-- OPPORTUNITY
-- ========================================

ALTER TABLE `opportunity`
    ADD COLUMN IF NOT EXISTS `fornitore_partner_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fornitore_partner_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_name` VARCHAR(255) DEFAULT NULL;

-- ========================================
-- VERIFICA COLONNE
-- ========================================

SELECT
    TABLE_NAME,
    COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'product_brand',
      'prospect',
      'appuntamento',
      'lead',
      'opportunity'
  )
  AND COLUMN_NAME IN (
      'fornitore_partner_id',
      'fornitore_partner_name',
      'product_brand_id',
      'product_brand_name'
  )
ORDER BY
    TABLE_NAME,
    COLUMN_NAME;
