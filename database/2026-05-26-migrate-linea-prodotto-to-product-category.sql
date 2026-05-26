-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-26
-- FILE: database/2026-05-26-migrate-linea-prodotto-to-product-category.sql
-- ========================================
--
-- Migra il vecchio campo linea_prodotto → product_category_id / product_category_name
-- su opportunity, appuntamento, lead, prospect (solo righe senza categoria).
--
-- PRIMA: backup in backup/hooks_cleanup/
--   mysqldump -u USER -p telcalli_espo opportunity appuntamento lead prospect product_category \
--     > backup/hooks_cleanup/backup-linea-prodotto-migrate-YYYY-MM-DD-HHMM.sql
--
-- ========================================

-- 1) Valorizza linea_prodotto sulle categorie (se vuoto) per join future
UPDATE product_category SET linea_prodotto = 'Climatizzazione'
WHERE deleted = 0 AND UPPER(TRIM(name)) IN ('CLIMATIZZATORI', 'CLIMATIZZATORI - ACCESSORI')
  AND (linea_prodotto IS NULL OR linea_prodotto = '');

UPDATE product_category SET linea_prodotto = 'Caldaie'
WHERE deleted = 0 AND UPPER(TRIM(name)) = 'CALDAIE A GAS'
  AND (linea_prodotto IS NULL OR linea_prodotto = '');

UPDATE product_category SET linea_prodotto = 'Stufe'
WHERE deleted = 0 AND UPPER(TRIM(name)) IN ('STUFE', 'STUFE A PELLET', 'BIOMASSA')
  AND (linea_prodotto IS NULL OR linea_prodotto = '');

UPDATE product_category SET linea_prodotto = 'Fotovoltaico'
WHERE deleted = 0 AND UPPER(TRIM(name)) = 'FOTOVOLTAICO'
  AND (linea_prodotto IS NULL OR linea_prodotto = '');

UPDATE product_category SET linea_prodotto = 'Pergole'
WHERE deleted = 0 AND UPPER(TRIM(name)) IN ('PERGOLA', 'BIOCLIMATICA')
  AND (linea_prodotto IS NULL OR linea_prodotto = '');

UPDATE product_category SET linea_prodotto = 'Tende da Sole'
WHERE deleted = 0 AND UPPER(TRIM(name)) IN ('TENDA A BRACCI', 'TENDA A CUPOLA')
  AND (linea_prodotto IS NULL OR linea_prodotto = '');

UPDATE product_category SET linea_prodotto = 'Chiusure Verticali'
WHERE deleted = 0 AND UPPER(TRIM(name)) = 'TENDA VERTICALE'
  AND (linea_prodotto IS NULL OR linea_prodotto = '');

UPDATE product_category SET linea_prodotto = 'Vetrate'
WHERE deleted = 0 AND UPPER(TRIM(name)) IN ('VETROTENDA', 'VETRATA IMPACCHETTABILE', 'VETRATA SCORREVOLE')
  AND (linea_prodotto IS NULL OR linea_prodotto = '');

-- 2) Anteprima opportunity (eseguire da sola prima dell'UPDATE)
-- SELECT o.id, o.name, o.linea_prodotto, o.product_brand_id, pc.name AS categoria
-- FROM opportunity o
-- LEFT JOIN product_category pc ON pc.deleted = 0
--   AND pc.linea_prodotto = o.linea_prodotto
--   AND (
--     o.product_brand_id IS NULL OR o.product_brand_id = ''
--     OR pc.product_brand_id IS NULL OR pc.product_brand_id = ''
--     OR o.product_brand_id = pc.product_brand_id
--   )
-- WHERE o.deleted = 0
--   AND (o.product_category_id IS NULL OR o.product_category_id = '')
--   AND o.linea_prodotto IS NOT NULL AND o.linea_prodotto != ''
-- ORDER BY o.modified_at DESC
-- LIMIT 50;

-- 3) Opportunity — join per linea_prodotto (+ brand se presente)
UPDATE opportunity o
INNER JOIN product_category pc ON pc.deleted = 0
  AND pc.linea_prodotto = o.linea_prodotto
  AND (
    o.product_brand_id IS NULL OR o.product_brand_id = ''
    OR pc.product_brand_id IS NULL OR pc.product_brand_id = ''
    OR o.product_brand_id = pc.product_brand_id
  )
INNER JOIN (
    SELECT linea_prodotto, MIN(`order`) AS min_ord
    FROM product_category
    WHERE deleted = 0 AND linea_prodotto IS NOT NULL AND linea_prodotto != ''
    GROUP BY linea_prodotto
) pick ON pick.linea_prodotto = pc.linea_prodotto AND pc.`order` = pick.min_ord
SET
  o.product_category_id = pc.id,
  o.product_category_name = pc.name
WHERE o.deleted = 0
  AND (o.product_category_id IS NULL OR o.product_category_id = '')
  AND o.linea_prodotto IS NOT NULL
  AND o.linea_prodotto != '';

-- 4) Fallback esplicito (linee con più categorie o join fallita)
UPDATE opportunity o
INNER JOIN product_category pc ON pc.deleted = 0 AND pc.name = CASE TRIM(o.linea_prodotto)
    WHEN 'Climatizzazione' THEN 'CLIMATIZZATORI'
    WHEN 'Caldaie' THEN 'CALDAIE A GAS'
    WHEN 'Stufe' THEN 'BIOMASSA'
    WHEN 'Fotovoltaico' THEN 'FOTOVOLTAICO'
    WHEN 'Pergole' THEN 'PERGOLA'
    WHEN 'Tende da Sole' THEN 'TENDA A BRACCI'
    WHEN 'Chiusure Verticali' THEN 'TENDA VERTICALE'
    WHEN 'Vetrate' THEN 'VETROTENDA'
    ELSE NULL
END
SET
  o.product_category_id = pc.id,
  o.product_category_name = pc.name
WHERE o.deleted = 0
  AND (o.product_category_id IS NULL OR o.product_category_id = '')
  AND o.linea_prodotto IS NOT NULL
  AND TRIM(o.linea_prodotto) != '';

-- 5) Stesso per appuntamento (se colonna linea_prodotto presente)
UPDATE appuntamento a
INNER JOIN product_category pc ON pc.deleted = 0
  AND pc.linea_prodotto = a.linea_prodotto
  AND (
    a.product_brand_id IS NULL OR a.product_brand_id = ''
    OR pc.product_brand_id IS NULL OR pc.product_brand_id = ''
    OR a.product_brand_id = pc.product_brand_id
  )
INNER JOIN (
    SELECT linea_prodotto, MIN(`order`) AS min_ord
    FROM product_category
    WHERE deleted = 0 AND linea_prodotto IS NOT NULL AND linea_prodotto != ''
    GROUP BY linea_prodotto
) pick ON pick.linea_prodotto = pc.linea_prodotto AND pc.`order` = pick.min_ord
SET
  a.product_category_id = pc.id,
  a.product_category_name = pc.name
WHERE a.deleted = 0
  AND (a.product_category_id IS NULL OR a.product_category_id = '')
  AND a.linea_prodotto IS NOT NULL
  AND a.linea_prodotto != '';

UPDATE appuntamento a
INNER JOIN product_category pc ON pc.deleted = 0 AND pc.name = CASE TRIM(a.linea_prodotto)
    WHEN 'Climatizzazione' THEN 'CLIMATIZZATORI'
    WHEN 'Caldaie' THEN 'CALDAIE A GAS'
    WHEN 'Stufe' THEN 'BIOMASSA'
    WHEN 'Fotovoltaico' THEN 'FOTOVOLTAICO'
    WHEN 'Pergole' THEN 'PERGOLA'
    WHEN 'Tende da Sole' THEN 'TENDA A BRACCI'
    WHEN 'Chiusure Verticali' THEN 'TENDA VERTICALE'
    WHEN 'Vetrate' THEN 'VETROTENDA'
    ELSE NULL
END
SET
  a.product_category_id = pc.id,
  a.product_category_name = pc.name
WHERE a.deleted = 0
  AND (a.product_category_id IS NULL OR a.product_category_id = '')
  AND a.linea_prodotto IS NOT NULL
  AND TRIM(a.linea_prodotto) != '';

-- 6) Lead e Prospect (se hanno linea_prodotto)
UPDATE `lead` l
INNER JOIN product_category pc ON pc.deleted = 0 AND pc.name = CASE TRIM(l.linea_prodotto)
    WHEN 'Climatizzazione' THEN 'CLIMATIZZATORI'
    WHEN 'Caldaie' THEN 'CALDAIE A GAS'
    WHEN 'Stufe' THEN 'BIOMASSA'
    WHEN 'Fotovoltaico' THEN 'FOTOVOLTAICO'
    WHEN 'Pergole' THEN 'PERGOLA'
    WHEN 'Tende da Sole' THEN 'TENDA A BRACCI'
    WHEN 'Chiusure Verticali' THEN 'TENDA VERTICALE'
    WHEN 'Vetrate' THEN 'VETROTENDA'
    ELSE NULL
END
SET
  l.product_category_id = pc.id,
  l.product_category_name = pc.name
WHERE l.deleted = 0
  AND (l.product_category_id IS NULL OR l.product_category_id = '')
  AND l.linea_prodotto IS NOT NULL
  AND TRIM(l.linea_prodotto) != '';

UPDATE prospect p
INNER JOIN product_category pc ON pc.deleted = 0 AND pc.name = CASE TRIM(p.linea_prodotto)
    WHEN 'Climatizzazione' THEN 'CLIMATIZZATORI'
    WHEN 'Caldaie' THEN 'CALDAIE A GAS'
    WHEN 'Stufe' THEN 'BIOMASSA'
    WHEN 'Fotovoltaico' THEN 'FOTOVOLTAICO'
    WHEN 'Pergole' THEN 'PERGOLA'
    WHEN 'Tende da Sole' THEN 'TENDA A BRACCI'
    WHEN 'Chiusure Verticali' THEN 'TENDA VERTICALE'
    WHEN 'Vetrate' THEN 'VETROTENDA'
    ELSE NULL
END
SET
  p.product_category_id = pc.id,
  p.product_category_name = pc.name
WHERE p.deleted = 0
  AND (p.product_category_id IS NULL OR p.product_category_id = '')
  AND p.linea_prodotto IS NOT NULL
  AND TRIM(p.linea_prodotto) != '';

-- 7) Verifica
SELECT
  linea_prodotto,
  COUNT(*) AS tot,
  SUM(CASE WHEN product_category_id IS NOT NULL AND product_category_id != '' THEN 1 ELSE 0 END) AS con_categoria,
  SUM(CASE WHEN product_category_id IS NULL OR product_category_id = '' THEN 1 ELSE 0 END) AS senza_categoria
FROM opportunity
WHERE deleted = 0 AND linea_prodotto IS NOT NULL AND linea_prodotto != ''
GROUP BY linea_prodotto
ORDER BY linea_prodotto;

SELECT id, name, linea_prodotto, product_category_name
FROM opportunity
WHERE deleted = 0
  AND (product_category_id IS NULL OR product_category_id = '')
  AND linea_prodotto IS NOT NULL AND linea_prodotto != ''
ORDER BY modified_at DESC
LIMIT 30;
