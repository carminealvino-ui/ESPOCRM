-- Prodotti Falcon creati dal sync errato (nome vuoto o senza denominazione) — solo SELECT
SELECT id, name, denominazione, brand_name, list_price, created_at
FROM product
WHERE deleted = 0
  AND (
      (name IS NULL OR TRIM(name) = '')
      OR (
          UPPER(IFNULL(name, '')) LIKE '%FALCON%'
          AND (denominazione IS NULL OR TRIM(denominazione) = '')
      )
  )
  AND created_at >= '2026-05-27'
ORDER BY created_at DESC;
