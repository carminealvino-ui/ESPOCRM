-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-26
-- FILE: database/2026-05-26-product-category-brand-gruppo-data.sql
-- ========================================
--
-- Valorizza Brand + gruppo provvigione sulle categorie (fix definitivo filtro UI).
-- Eseguire dopo backup DB in backup_dev/
--
-- ========================================

UPDATE `product_category` pc
INNER JOIN `product_brand` pb
    ON pb.deleted = 0 AND UPPER(TRIM(pb.name)) = 'ARIEL'
SET
    pc.product_brand_id = pb.id,
    pc.product_brand_name = pb.name,
    pc.gruppo_provvigione = 'Clima e altro',
    pc.regime_provigione = COALESCE(NULLIF(TRIM(pc.regime_provigione), ''), 'ARIEL_2026')
WHERE pc.deleted = 0
  AND UPPER(TRIM(pc.name)) IN (
      'CLIMATIZZATORI',
      'CLIMATIZZAZIONE',
      'CALDAIE A GAS',
      'CALDAIE',
      'BIOMASSA',
      'STUFE',
      'STUFE A PELLET',
      'FOTOVOLTAICO'
  );

UPDATE `product_category` pc
INNER JOIN `product_brand` pb
    ON pb.deleted = 0 AND UPPER(TRIM(pb.name)) = 'ARTEL'
SET
    pc.product_brand_id = pb.id,
    pc.product_brand_name = pb.name,
    pc.gruppo_provvigione = 'Clima e altro',
    pc.regime_provigione = COALESCE(NULLIF(TRIM(pc.regime_provigione), ''), 'GENERICO')
WHERE pc.deleted = 0
  AND UPPER(TRIM(pc.name)) IN (
      'CLIMATIZZATORI',
      'CLIMATIZZAZIONE',
      'CALDAIE A GAS',
      'CALDAIE',
      'BIOMASSA',
      'STUFE',
      'STUFE A PELLET',
      'FOTOVOLTAICO'
  );

UPDATE `product_category` pc
INNER JOIN `product_brand` pb
    ON pb.deleted = 0 AND UPPER(TRIM(pb.name)) = 'ARQUATI'
SET
    pc.product_brand_id = pb.id,
    pc.product_brand_name = pb.name,
    pc.gruppo_provvigione = CASE
        WHEN UPPER(TRIM(pc.name)) LIKE '%PERGOLA%' OR UPPER(TRIM(pc.name)) = 'BIOCLIMATICA' THEN 'Pergole'
        WHEN UPPER(TRIM(pc.name)) LIKE 'VETR%' THEN 'Vetrate'
        ELSE 'Tende da Sole'
    END,
    pc.regime_provigione = COALESCE(NULLIF(TRIM(pc.regime_provigione), ''), 'ARQUATI_PNC')
WHERE pc.deleted = 0
  AND UPPER(TRIM(pc.name)) IN (
      'TENDA VERTICALE',
      'TENDA A BRACCI',
      'TENDA A CUPOLA',
      'PERGOLA',
      'BIOCLIMATICA',
      'VETROTENDA',
      'VETRATA IMPACCHETTABILE',
      'VETRATA SCORREVOLE',
      'CHIUSURE VERTICALI'
  );

SELECT name, product_brand_name, gruppo_provvigione, regime_provigione
FROM product_category
WHERE deleted = 0
ORDER BY `order`;
