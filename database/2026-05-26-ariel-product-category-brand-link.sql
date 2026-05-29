-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-26
-- FILE: database/2026-05-26-ariel-product-category-brand-link.sql
-- ========================================
--
-- Collega le categorie climatiche al brand ARIEL (picker Opportunità/Appuntamento).
-- Eseguire dopo backup; poi Clear cache + Rebuild.
--
-- ========================================

UPDATE `product_category` pc
INNER JOIN `product_brand` pb
    ON pb.deleted = 0
    AND UPPER(TRIM(pb.name)) = 'ARIEL'
SET
    pc.product_brand_id = pb.id,
    pc.product_brand_name = pb.name,
    pc.regime_provigione = COALESCE(NULLIF(TRIM(pc.regime_provigione), ''), 'ARIEL_2026')
WHERE pc.deleted = 0
  AND (
      UPPER(TRIM(pc.product_brand_name)) = 'ARIEL'
      OR pc.linea_prodotto IN ('Climatizzazione', 'Caldaie', 'Stufe', 'Fotovoltaico')
      OR pc.gruppo_provvigione = 'Clima e altro'
  )
  AND (
      pc.product_brand_id IS NULL
      OR pc.product_brand_id = ''
      OR pc.product_brand_id <> pb.id
  )
  AND UPPER(TRIM(COALESCE(pc.product_brand_name, ''))) IN ('', 'ARIEL');

SELECT id, name, linea_prodotto, gruppo_provvigione, product_brand_id, product_brand_name
FROM product_category
WHERE deleted = 0
  AND UPPER(TRIM(COALESCE(product_brand_name, ''))) = 'ARIEL'
ORDER BY name;
