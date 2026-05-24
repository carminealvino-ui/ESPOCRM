-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-24
-- FILE:
-- database/2026-05-24-structure-product-category-cascade.sql
-- ========================================
--
-- OBIETTIVO:
-- Collegare Categoria Prodotto (ProductCategory) al Brand e alla
-- linea prodotto; abilitare il campo productCategory su Prospect,
-- Appuntamento, Lead e Opportunity.
--
-- IMPORTANTE:
-- 1) Backup completo DB prima dell'esecuzione.
-- 2) Caricare i metadata custom aggiornati.
-- 3) Dopo l'esecuzione: php command.php clear-cache && php command.php rebuild
-- 4) In Amministrazione > Categorie prodotto: assegnare Brand e Linea
--    a ogni categoria (es. VODAFONE VOCE -> Brand Vodafone, Linea TLC).
--
-- ========================================

ALTER TABLE `product_category`
    ADD COLUMN IF NOT EXISTS `product_brand_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `linea_prodotto` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `prospect`
    ADD COLUMN IF NOT EXISTS `product_category_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_category_name` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `appuntamento`
    ADD COLUMN IF NOT EXISTS `product_category_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_category_name` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `lead`
    ADD COLUMN IF NOT EXISTS `product_category_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_category_name` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `opportunity`
    ADD COLUMN IF NOT EXISTS `product_category_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_category_name` VARCHAR(255) DEFAULT NULL;

SELECT
    TABLE_NAME,
    COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'product_category',
      'prospect',
      'appuntamento',
      'lead',
      'opportunity'
  )
  AND COLUMN_NAME IN (
      'product_brand_id',
      'product_brand_name',
      'linea_prodotto',
      'product_category_id',
      'product_category_name'
  )
ORDER BY
    TABLE_NAME,
    COLUMN_NAME;
