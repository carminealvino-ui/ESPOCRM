-- ========================================
-- VERSIONE: 1.1.0
-- DATA: 2026-05-26
-- FILE: database/2026-05-26-migrate-linea-prodotto-opportunity-only.sql
-- ========================================
--
-- Solo opportunity (e altre entità SE hanno linea_prodotto).
-- NON usa product_category.linea_prodotto (colonna assente in produzione).
--
-- Backup prima: export tabella opportunity da phpMyAdmin.
--
-- ========================================

-- Opportunity — mappa linea_prodotto → categoria (nome in anagrafica)
UPDATE opportunity o
INNER JOIN product_category pc ON pc.deleted = 0 AND pc.name = CASE TRIM(o.linea_prodotto)
    WHEN 'Climatizzazione' THEN 'CLIMATIZZATORI'
    WHEN 'Caldaie' THEN 'CALDAIE A GAS'
    WHEN 'Stufe' THEN 'BIOMASSA'
    WHEN 'Biomassa' THEN 'BIOMASSA'
    WHEN 'Fotovoltaico' THEN 'FOTOVOLTAICO'
    WHEN 'Pergole' THEN 'PERGOLA'
    WHEN 'Tende da Sole' THEN 'TENDA A BRACCI'
    WHEN 'Chiusure Verticali' THEN 'TENDA VERTICALE'
    WHEN 'Vetrate' THEN 'VETROTENDA'
    WHEN 'TLC' THEN 'VODAFONE VOCE'
    WHEN 'Energia' THEN 'ENEL BUSINESS'
    ELSE NULL
END
SET
  o.product_category_id = pc.id
WHERE o.deleted = 0
  AND (o.product_category_id IS NULL OR o.product_category_id = '')
  AND o.linea_prodotto IS NOT NULL
  AND TRIM(o.linea_prodotto) != '';

-- Verifica (solo product_category_id — in produzione non c'è product_category_name)
SELECT linea_prodotto, COUNT(*) AS tot,
  SUM(CASE WHEN product_category_id IS NOT NULL AND product_category_id != '' THEN 1 ELSE 0 END) AS con_categoria
FROM opportunity
WHERE deleted = 0 AND linea_prodotto IS NOT NULL AND linea_prodotto != ''
GROUP BY linea_prodotto
ORDER BY linea_prodotto;

SELECT id, name, linea_prodotto, product_category_id
FROM opportunity
WHERE deleted = 0
  AND (product_category_id IS NULL OR product_category_id = '')
  AND linea_prodotto IS NOT NULL AND linea_prodotto != ''
ORDER BY modified_at DESC
LIMIT 30;
