-- ========================================
-- FASE 1 — Audit listino Ariel 07/05/2026 (SOLO LETTURA)
-- FILE: database/2026-05-27-fase-1-audit-solo-lettura.sql
-- ========================================
--
-- Nessun UPDATE. Eseguire su DB CRM dopo backup.
-- Valori attesi Falcon (PDF IVA 10% inclusa → CRM IVA esclusa):
--   Listino TOTALE IVI 3950  → 3590,91 escl.
--   Prezzo codice IVI 2950   → 2681,82 escl.
--   part_number 00.02.95.0
--
-- In alternativa sul server: php tools/fase-1-audit-listino.php
--
-- ========================================

-- ---------- A) Struttura tabelle ----------
SHOW COLUMNS FROM price_book;
SHOW COLUMNS FROM product_price;
SHOW COLUMNS FROM product LIKE '%part%';
SHOW COLUMNS FROM product LIKE '%prezzo%';
SHOW COLUMNS FROM product LIKE '%list%';

-- ---------- B) Listini ARIEL attivi ----------
SELECT id, name, date_start, date_end, status, deleted
FROM price_book
WHERE deleted = 0
  AND UPPER(name) LIKE '%ARIEL%'
ORDER BY name;

-- Listini il cui nome richiama maggio 2026 o 07/05 (verificare vigore comunicato)
SELECT id, name, date_start, date_end
FROM price_book
WHERE deleted = 0
  AND UPPER(name) LIKE '%ARIEL%'
  AND (
      UPPER(name) LIKE '%05%'
      OR UPPER(name) LIKE '%MAGG%'
      OR UPPER(name) LIKE '%2026%'
  )
ORDER BY name;

-- ---------- C) Prodotto test Falcon ----------
SELECT
    id,
    name,
    part_number,
    list_price,
    unit_price,
    prezzo_codice,
    ROUND(list_price * 1.10, 2) AS listino_ivi_ricalcolato,
    ROUND(prezzo_codice * 1.10, 2) AS codice_ivi_ricalcolato
FROM product
WHERE deleted = 0
  AND (
      part_number = '00.02.95.0'
      OR UPPER(name) LIKE '%FALCON%9%'
  )
ORDER BY part_number, name;

-- Attesi IVA escl.: list_price ≈ 3590.91, prezzo_codice ≈ 2681.82
SELECT
    id,
    name,
    part_number,
    list_price,
    prezzo_codice,
    CASE WHEN ABS(list_price - 3590.91) < 0.02 THEN 'OK' ELSE 'DA ALLINEARE' END AS check_listino,
    CASE WHEN ABS(prezzo_codice - 2681.82) < 0.02 THEN 'OK' ELSE 'DA ALLINEARE' END AS check_codice
FROM product
WHERE deleted = 0
  AND part_number = '00.02.95.0';

-- ---------- D) Prezzi prodotto nel listino (sostituire ID) ----------
-- SET @pb_id = 'INCollARE_ID_LISTINO_ARIEL_070526';
--
-- SELECT
--     pb.name AS listino,
--     p.part_number,
--     p.name,
--     pp.price,
--     ROUND(pp.price * 1.10, 2) AS price_ivi,
--     pp.date_start,
--     pp.date_end,
--     pp.status
-- FROM product_price pp
-- JOIN product p ON p.id = pp.product_id AND p.deleted = 0
-- JOIN price_book pb ON pb.id = pp.price_book_id
-- WHERE pp.deleted = 0
--   AND pp.price_book_id = @pb_id
--   AND p.part_number = '00.02.95.0';

-- ---------- E) Anomalie ----------
SELECT part_number, COUNT(*) AS n
FROM product
WHERE deleted = 0
  AND part_number IS NOT NULL
  AND TRIM(part_number) <> ''
GROUP BY part_number
HAVING n > 1
ORDER BY n DESC
LIMIT 20;

SELECT id, name, part_number, list_price, prezzo_codice
FROM product
WHERE deleted = 0
  AND prezzo_codice IS NOT NULL
  AND list_price IS NOT NULL
  AND ABS(prezzo_codice - list_price) < 0.01
  AND UPPER(name) LIKE '%FALCON%';

-- ---------- F) Opportunità recenti ARIEL (campione) ----------
SELECT
    o.id,
    o.name,
    o.price_book_id,
    o.price_book_name,
    o.prezzo_listino_iva_esclusa,
    o.prezzo_codice_iva_esclusa,
    o.data_opportunit,
    o.created_at
FROM opportunity o
WHERE o.deleted = 0
  AND (
      UPPER(o.price_book_name) LIKE '%ARIEL%'
      OR UPPER(o.azienda) LIKE '%ARIEL%'
      OR UPPER(o.product_brand_name) LIKE '%ARIEL%'
  )
ORDER BY o.created_at DESC
LIMIT 15;
