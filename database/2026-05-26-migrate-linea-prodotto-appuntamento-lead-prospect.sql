-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-26
-- FILE: database/2026-05-26-migrate-linea-prodotto-appuntamento-lead-prospect.sql
-- ========================================
--
-- Allinea product_category_id da linea_prodotto (enum legacy).
-- Stessa mappa di opportunity-only (senza product_category_name in produzione).
--
-- Backup prima: appuntamento, lead, prospect (+ opportunity se non già fatto).
--
-- ========================================

-- Appuntamento
UPDATE appuntamento a
INNER JOIN product_category pc ON pc.deleted = 0 AND pc.name = CASE TRIM(a.linea_prodotto)
    WHEN 'Climatizzazione' THEN 'CLIMATIZZATORI'
    WHEN 'Caldaie' THEN 'CALDAIE A GAS'
    WHEN 'Stufe' THEN 'BIOMASSA'
    WHEN 'Biomassa' THEN 'BIOMASSA'
    WHEN 'Bioetanolo' THEN 'BIOMASSA'
    WHEN 'Fotovoltaico' THEN 'FOTOVOLTAICO'
    WHEN 'Pergole' THEN 'PERGOLA'
    WHEN 'Tende da Sole' THEN 'TENDA A BRACCI'
    WHEN 'Chiusure Verticali' THEN 'TENDA VERTICALE'
    WHEN 'Vetrate' THEN 'VETROTENDA'
    WHEN 'TLC' THEN 'VODAFONE VOCE'
    WHEN 'TLC - Vodafone' THEN 'VODAFONE VOCE'
    WHEN 'Energia' THEN 'ENEL BUSINESS'
    ELSE NULL
END
SET a.product_category_id = pc.id
WHERE a.deleted = 0
  AND (a.product_category_id IS NULL OR a.product_category_id = '')
  AND a.linea_prodotto IS NOT NULL
  AND TRIM(a.linea_prodotto) != '';

-- Lead
UPDATE `lead` l
INNER JOIN product_category pc ON pc.deleted = 0 AND pc.name = CASE TRIM(l.linea_prodotto)
    WHEN 'Climatizzazione' THEN 'CLIMATIZZATORI'
    WHEN 'Caldaie' THEN 'CALDAIE A GAS'
    WHEN 'Stufe' THEN 'BIOMASSA'
    WHEN 'Biomassa' THEN 'BIOMASSA'
    WHEN 'Bioetanolo' THEN 'BIOMASSA'
    WHEN 'Fotovoltaico' THEN 'FOTOVOLTAICO'
    WHEN 'Pergole' THEN 'PERGOLA'
    WHEN 'Tende da Sole' THEN 'TENDA A BRACCI'
    WHEN 'Chiusure Verticali' THEN 'TENDA VERTICALE'
    WHEN 'Vetrate' THEN 'VETROTENDA'
    WHEN 'TLC' THEN 'VODAFONE VOCE'
    WHEN 'TLC - Vodafone' THEN 'VODAFONE VOCE'
    WHEN 'Energia' THEN 'ENEL BUSINESS'
    ELSE NULL
END
SET l.product_category_id = pc.id
WHERE l.deleted = 0
  AND (l.product_category_id IS NULL OR l.product_category_id = '')
  AND l.linea_prodotto IS NOT NULL
  AND TRIM(l.linea_prodotto) != '';

-- Prospect
UPDATE prospect p
INNER JOIN product_category pc ON pc.deleted = 0 AND pc.name = CASE TRIM(p.linea_prodotto)
    WHEN 'Climatizzazione' THEN 'CLIMATIZZATORI'
    WHEN 'Caldaie' THEN 'CALDAIE A GAS'
    WHEN 'Stufe' THEN 'BIOMASSA'
    WHEN 'Biomassa' THEN 'BIOMASSA'
    WHEN 'Bioetanolo' THEN 'BIOMASSA'
    WHEN 'Fotovoltaico' THEN 'FOTOVOLTAICO'
    WHEN 'Pergole' THEN 'PERGOLA'
    WHEN 'Tende da Sole' THEN 'TENDA A BRACCI'
    WHEN 'Chiusure Verticali' THEN 'TENDA VERTICALE'
    WHEN 'Vetrate' THEN 'VETROTENDA'
    WHEN 'TLC' THEN 'VODAFONE VOCE'
    WHEN 'TLC - Vodafone' THEN 'VODAFONE VOCE'
    WHEN 'Energia' THEN 'ENEL BUSINESS'
    ELSE NULL
END
SET p.product_category_id = pc.id
WHERE p.deleted = 0
  AND (p.product_category_id IS NULL OR p.product_category_id = '')
  AND p.linea_prodotto IS NOT NULL
  AND TRIM(p.linea_prodotto) != '';

-- Verifica appuntamento
SELECT linea_prodotto, COUNT(*) AS tot,
  SUM(CASE WHEN product_category_id IS NOT NULL AND product_category_id != '' THEN 1 ELSE 0 END) AS con_categoria
FROM appuntamento
WHERE deleted = 0 AND linea_prodotto IS NOT NULL AND linea_prodotto != ''
GROUP BY linea_prodotto
ORDER BY linea_prodotto;

SELECT COUNT(*) AS appuntamenti_senza_categoria_con_linea
FROM appuntamento
WHERE deleted = 0
  AND linea_prodotto IS NOT NULL AND linea_prodotto != ''
  AND (product_category_id IS NULL OR product_category_id = '');
