-- ========================================
-- FALCON MONO / PLUS — regole vigore listini (ARIEL)
-- ========================================
--
-- APRILE 2026 (price_book 69ce7c1fa73049580 — ARIEL - 26-04):
--   Due modelli 9.000 BTU:
--     - FALCON MONO 9000BTU          → valido FINO AL 30/04/2026 (non dal 1° maggio)
--     - FALCON MONO PLUS 9000BTU
--
-- Dal 1 MAGGIO 2026:
--   Il MONO 9000BTU (senza PLUS) non è più in listino.
--
-- MAGGIO 2026 e successivi (26-05, 07/05/2026 …):
--   Solo FALCON MONO PLUS … (niente MONO 9.000 senza PLUS)
--   Stessa logica estesa a tutta la gamma Falcon (MONO, DUAL, TRIAL, combo)
--   Nel PDF 07/05 la voce può essere "Falcon 9.000 btu" = prodotto CRM PLUS.
--
-- Listino 07/05/2026: 07ce1b326cd314ca2
-- Listino Maggio 2026: 6a043018dc22acf33
-- Listino Aprile 2026: 69ce7c1fa73049580
--
-- CSV sync:
--   Aprile 9.000: database/data/listino-ariel-climatizzatori-2604-aprile-9000.csv
--   Maggio+/07-05 MONO: database/data/listino-ariel-climatizzatori-07052026.csv
--
-- ========================================

-- Verifica: prezzi MONO 9000 senza PLUS su listini da maggio in poi
SELECT
    pb.name AS listino,
    p.name AS prodotto,
    pp.price,
    pp.date_start,
    pp.date_end,
    pp.status
FROM product_price pp
JOIN product p ON p.id = pp.product_id AND p.deleted = 0
JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
WHERE pp.deleted = 0
  AND p.name = 'ARIEL - CLIMATIZZATORI - FALCON MONO 9000BTU'
  AND pb.id IN ('6a043018dc22acf33', '07ce1b326cd314ca2')
ORDER BY pb.name;

-- Dopo verifica: chiudere eventuali righe errate (es. MONO non-PLUS su listino maggio+)
-- UPDATE product_price pp
-- INNER JOIN product p ON p.id = pp.product_id
-- SET pp.date_end = '2026-04-30', pp.status = 'Inactive'
-- WHERE pp.deleted = 0
--   AND p.name = 'ARIEL - CLIMATIZZATORI - FALCON MONO 9000BTU'
--   AND pp.price_book_id IN ('6a043018dc22acf33', '07ce1b326cd314ca2')
--   AND (pp.date_end IS NULL OR pp.date_end >= '2026-05-01');
