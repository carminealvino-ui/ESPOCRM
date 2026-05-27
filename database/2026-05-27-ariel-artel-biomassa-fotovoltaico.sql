-- ========================================
-- VERSIONE: 1.0.1
-- DATA: 2026-05-27
-- FILE: database/2026-05-27-ariel-artel-biomassa-fotovoltaico.sql
-- ========================================
--
-- Verifica / collega categorie BIOMASSA e FOTOVOLTAICO a brand ARIEL e ARTEL.
-- Eseguire dopo backup DB.
--
-- ========================================

SELECT id, name, product_brand_name
FROM product_category
WHERE deleted = 0
  AND UPPER(TRIM(name)) IN ('BIOMASSA', 'FOTOVOLTAICO')
ORDER BY name;

UPDATE `product_category` pc
INNER JOIN `product_brand` pb
    ON pb.deleted = 0 AND UPPER(TRIM(pb.name)) = 'ARIEL'
SET
    pc.product_brand_id = pb.id,
    pc.product_brand_name = pb.name
WHERE pc.deleted = 0
  AND UPPER(TRIM(pc.name)) IN ('BIOMASSA', 'FOTOVOLTAICO');

UPDATE `product_category` pc
INNER JOIN `product_brand` pb
    ON pb.deleted = 0 AND UPPER(TRIM(pb.name)) = 'ARTEL'
SET
    pc.product_brand_id = pb.id,
    pc.product_brand_name = pb.name
WHERE pc.deleted = 0
  AND UPPER(TRIM(pc.name)) IN ('BIOMASSA', 'FOTOVOLTAICO');

SELECT name, product_brand_name
FROM product_category
WHERE deleted = 0
  AND UPPER(TRIM(name)) IN ('BIOMASSA', 'FOTOVOLTAICO')
ORDER BY name, product_brand_name;
