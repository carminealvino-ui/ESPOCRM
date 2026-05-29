-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-24
-- FILE: database/2026-05-24-product-category-gruppo-provvigione.sql
-- ========================================
--
-- Sostituisce linea_prodotto su product_category con gruppo_provvigione
-- (allineato alle regole provvigionali per macro-linea: Tende, Pergole,
-- Vetrate, Clima).
--
-- I campi linea_prodotto su opportunity/appuntamento restano in DB per
-- storico ma non sono più usati dall'interfaccia.
--
-- Dopo metadata + rebuild, valorizzare gruppo_provvigione su ogni categoria.
--
-- ========================================

ALTER TABLE `product_category`
    ADD COLUMN IF NOT EXISTS `gruppo_provvigione` VARCHAR(100) DEFAULT NULL;

-- Migrazione opzionale da linea_prodotto (se colonna già presente)
UPDATE `product_category`
SET `gruppo_provvigione` = CASE
    WHEN `linea_prodotto` IN ('Tende da Sole', 'Chiusure Verticali') THEN 'Tende da Sole'
    WHEN `linea_prodotto` = 'Pergole' THEN 'Pergole'
    WHEN `linea_prodotto` = 'Vetrate' THEN 'Vetrate'
    WHEN `linea_prodotto` IN ('Climatizzazione', 'Caldaie', 'Stufe', 'Fotovoltaico') THEN 'Clima e altro'
    WHEN `linea_prodotto` IN ('TLC', 'Energia', 'Rental') THEN NULL
    ELSE NULL
END
WHERE (`gruppo_provvigione` IS NULL OR `gruppo_provvigione` = '')
  AND `linea_prodotto` IS NOT NULL
  AND `linea_prodotto` <> ''
  AND `deleted` = 0;

SELECT id, name, linea_prodotto, gruppo_provvigione, product_brand_name
FROM product_category
WHERE deleted = 0
ORDER BY name
LIMIT 50;
