-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-24
-- Contesto commerciale su Quote (contratto) da Opportunity
-- ========================================

ALTER TABLE `quote`
    ADD COLUMN IF NOT EXISTS `fornitore_partner_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fornitore_partner_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_brand_name` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_category_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_category_name` VARCHAR(255) DEFAULT NULL;
