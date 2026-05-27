-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-27
-- FILE: database/2026-05-27-analisi-prodotti-listino-070526.sql
-- ========================================
--
-- Audit prodotti vs listino Ariel climatizzatori in vigore dal 07/05/2026.
-- Eseguire su DB CRM (solo SELECT) dopo backup.
--
-- Mappatura listino PDF → CRM:
--   Codice (es. 00.02.95.0)     → product.part_number
--   Descrizione                 → product.name
--   Prezzo listino (IVA escl.)  → product.list_price, product_price.price
--   Prezzo a codice (provvig.)  → product.prezzo_codice (se assente: = listino)
--
-- ========================================

SHOW COLUMNS FROM product LIKE '%part%';
SHOW COLUMNS FROM product LIKE '%prezzo%';
SHOW COLUMNS FROM product LIKE '%list%';
SHOW COLUMNS FROM product LIKE '%unit%';

-- Prodotti ARIEL / Falcon per codice listino (esempio riga listino 07.05.26)
SELECT
    id,
    name,
    part_number,
    list_price,
    unit_price,
    prezzo_codice,
    deleted
FROM product
WHERE deleted = 0
  AND (
      part_number LIKE '00.02.%'
      OR UPPER(name) LIKE '%FALCON%'
  )
ORDER BY part_number, name
LIMIT 50;

-- Listini ARIEL (cercare quello 07/05/2026 o maggio 2026)
SELECT
    id,
    name,
    date_start,
    date_end,
    deleted
FROM price_book
WHERE deleted = 0
  AND UPPER(name) LIKE 'ARIEL%'
ORDER BY name;

-- Prezzi nel listino per codici noti (sostituire :price_book_id)
-- SET @pb := 'ID_LISTINO_ARIEL_070526';
--
-- SELECT
--     p.part_number,
--     p.name AS product_name,
--     pp.price,
--     pp.date_start,
--     pp.date_end,
--     pp.status,
--     pb.name AS price_book_name
-- FROM product_price pp
-- INNER JOIN product p ON p.id = pp.product_id AND p.deleted = 0
-- INNER JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
-- WHERE pp.deleted = 0
--   AND pp.price_book_id = @pb
--   AND p.part_number IN ('00.02.95.0')
-- ORDER BY p.part_number;

-- Disallineamenti: prodotto senza part_number ma nome da listino
SELECT id, name, part_number, list_price, prezzo_codice
FROM product
WHERE deleted = 0
  AND (part_number IS NULL OR TRIM(part_number) = '')
  AND UPPER(name) LIKE '%FALCON%';

-- Duplicati per stesso codice
SELECT part_number, COUNT(*) AS n
FROM product
WHERE deleted = 0
  AND part_number IS NOT NULL
  AND TRIM(part_number) <> ''
GROUP BY part_number
HAVING n > 1
ORDER BY n DESC
LIMIT 30;
