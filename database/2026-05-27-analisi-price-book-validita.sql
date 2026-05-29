-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-27
-- FILE: database/2026-05-27-analisi-price-book-validita.sql
-- ========================================
--
-- Analisi produzione: dove sono le date di validità listini.
-- Eseguire su DB CRM (solo SELECT) dopo backup.
--
-- ========================================

-- Colonne tabella listino (Sales Pack: price_book)
SHOW COLUMNS FROM price_book;

-- Esempi listini ARIEL / ARTEL
SELECT
    id,
    name,
    date_start,
    date_end,
    deleted
FROM price_book
WHERE deleted = 0
  AND (
      UPPER(name) LIKE 'ARIEL%'
      OR UPPER(name) LIKE 'ARTEL%'
  )
ORDER BY name
LIMIT 30;

-- Prezzi prodotto nel listino (Sales Pack: product_price)
-- Se la tabella esiste:
-- SHOW COLUMNS FROM product_price;
--
-- SELECT price_book_id, name, date_start, date_end, deleted
-- FROM product_price
-- WHERE deleted = 0
-- ORDER BY price_book_id, date_start
-- LIMIT 50;
