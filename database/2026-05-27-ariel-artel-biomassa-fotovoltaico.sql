-- ========================================
-- VERSIONE: 1.0.1
-- DATA: 2026-05-27
-- FILE: database/2026-05-27-ariel-artel-biomassa-fotovoltaico.sql
-- ========================================
--
-- Verifica presenza anagrafica BIOMASSA / FOTOVOLTAICO.
-- Il picker JS 1.6.2 filtra per nome: se mancano qui, crearle in Espo
-- (Amministrazione > Entità > ProductCategory).
--
-- ========================================

SELECT id, name, product_brand_id, product_brand_name, deleted
FROM product_category
WHERE UPPER(TRIM(name)) IN ('BIOMASSA', 'FOTOVOLTAICO', 'STUFE', 'STUFE A PELLET')
ORDER BY name, deleted;
