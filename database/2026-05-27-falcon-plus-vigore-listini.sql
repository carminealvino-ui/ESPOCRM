-- ========================================
-- FALCON MONO / PLUS — regole vigore listini (ARIEL)
-- ========================================
--
-- LISTINI NON PIÙ ATTIVI (non sincronizzare, non usare su nuove opportunità):
--   Marzo 2026  69d4c2dce710dc14b  ARIEL - 26-03 (Marzo 2026)
--   Aprile 2026 69ce7c1fa73049580  ARIEL - 26-04 (Aprile 2026)
--
-- LISTINI ATTIVI (sync prezzi / opportunità):
--   Maggio 2026  6a043018dc22acf33  ARIEL - 26-05 (Maggio 2026)  — in transizione
--   07/05/2026   07ce1b326cd314ca2  ARIEL - 26-07-05 (Climatizzatori 07/05/2026)  — listino in vigore
--
-- Storico aprile (solo documentazione):
--   Fino al 30/04/2026: due modelli 9.000 (MONO + MONO PLUS)
--   Dal 01/05/2026: solo FALCON MONO PLUS (e gamma PLUS)
--
-- CSV sync SOLO su listini attivi:
--   database/data/listino-ariel-climatizzatori-07052026.csv → 07ce1b326cd314ca2
--
-- ========================================

SELECT id, name, status, deleted
FROM price_book
WHERE deleted = 0
  AND UPPER(name) LIKE '%ARIEL%'
ORDER BY name;

-- MONO 9000 senza PLUS non deve avere product_price attivi su listini maggio / 07-05
SELECT
    pb.name AS listino,
    pb.status AS listino_status,
    p.name AS prodotto,
    pp.price,
    pp.date_start,
    pp.date_end,
    pp.status AS prezzo_status
FROM product_price pp
JOIN product p ON p.id = pp.product_id AND p.deleted = 0
JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
WHERE pp.deleted = 0
  AND p.name = 'ARIEL - CLIMATIZZATORI - FALCON MONO 9000BTU'
  AND pp.price_book_id IN ('6a043018dc22acf33', '07ce1b326cd314ca2')
ORDER BY pb.name;
