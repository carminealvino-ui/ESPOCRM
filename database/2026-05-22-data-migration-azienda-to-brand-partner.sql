-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-22
-- AUTORE: CARMINE ALVINO + IA
-- FILE:
-- database/2026-05-22-data-migration-azienda-to-brand-partner.sql
-- ========================================
--
-- STORICO FIX
-- ----------------------------------------
-- 1.0.0
-- Migrazione dati esistenti:
-- azienda -> productBrand / fornitorePartner
--
-- OBIETTIVO:
-- valorizzare i nuovi campi link gia' creati nel DB:
-- - product_brand_id
-- - product_brand_name
-- - fornitore_partner_id
-- - fornitore_partner_name
--
-- LOGICA:
-- 1) legge il vecchio campo azienda;
-- 2) cerca il brand in product_brand.name;
-- 3) copia product_brand.id/name;
-- 4) copia fornitore_partner_id/name gia' configurati sul brand.
--
-- IMPORTANTE:
-- 1) Eseguire prima backup completo DB.
-- 2) Eseguire prima le query di ANTEPRIMA.
-- 3) Lo script NON cancella azienda.
-- 4) Lo script aggiorna solo record con campi nuovi vuoti.
-- 5) Se un brand non ha fornitorePartner configurato, il partner resta vuoto.
--
-- ROLLBACK:
-- usare backup completo DB.
--
-- ========================================

-- ========================================
-- ANTEPRIMA BRAND CONFIGURATI
-- ========================================

SELECT
    id,
    name,
    fornitore_partner_id,
    fornitore_partner_name
FROM product_brand
WHERE deleted = 0
ORDER BY name;

-- ========================================
-- ANTEPRIMA RECORD MIGRABILI PER TABELLA
-- ========================================

SELECT
    'prospect' AS tabella,
    p.azienda,
    COUNT(*) AS record_migrabili
FROM prospect p
INNER JOIN product_brand pb
    ON pb.deleted = 0
   AND pb.name = p.azienda
WHERE p.deleted = 0
  AND p.azienda IS NOT NULL
  AND p.azienda <> ''
  AND (
      p.product_brand_id IS NULL
      OR p.product_brand_id = ''
  )
GROUP BY p.azienda

UNION ALL

SELECT
    'appuntamento' AS tabella,
    a.azienda,
    COUNT(*) AS record_migrabili
FROM appuntamento a
INNER JOIN product_brand pb
    ON pb.deleted = 0
   AND pb.name = a.azienda
WHERE a.deleted = 0
  AND a.azienda IS NOT NULL
  AND a.azienda <> ''
  AND (
      a.product_brand_id IS NULL
      OR a.product_brand_id = ''
  )
GROUP BY a.azienda

UNION ALL

SELECT
    'lead' AS tabella,
    l.azienda,
    COUNT(*) AS record_migrabili
FROM lead l
INNER JOIN product_brand pb
    ON pb.deleted = 0
   AND pb.name = l.azienda
WHERE l.deleted = 0
  AND l.azienda IS NOT NULL
  AND l.azienda <> ''
  AND (
      l.product_brand_id IS NULL
      OR l.product_brand_id = ''
  )
GROUP BY l.azienda

UNION ALL

SELECT
    'opportunity' AS tabella,
    o.azienda,
    COUNT(*) AS record_migrabili
FROM opportunity o
INNER JOIN product_brand pb
    ON pb.deleted = 0
   AND pb.name = o.azienda
WHERE o.deleted = 0
  AND o.azienda IS NOT NULL
  AND o.azienda <> ''
  AND (
      o.product_brand_id IS NULL
      OR o.product_brand_id = ''
  )
GROUP BY o.azienda
ORDER BY tabella, azienda;

-- ========================================
-- MIGRAZIONE PROSPECT
-- ========================================

UPDATE prospect p
INNER JOIN product_brand pb
    ON pb.deleted = 0
   AND pb.name = p.azienda
SET
    p.product_brand_id = pb.id,
    p.product_brand_name = pb.name,
    p.fornitore_partner_id = pb.fornitore_partner_id,
    p.fornitore_partner_name = pb.fornitore_partner_name
WHERE p.deleted = 0
  AND p.azienda IS NOT NULL
  AND p.azienda <> ''
  AND (
      p.product_brand_id IS NULL
      OR p.product_brand_id = ''
  );

-- ========================================
-- MIGRAZIONE APPUNTAMENTO
-- ========================================

UPDATE appuntamento a
INNER JOIN product_brand pb
    ON pb.deleted = 0
   AND pb.name = a.azienda
SET
    a.product_brand_id = pb.id,
    a.product_brand_name = pb.name,
    a.fornitore_partner_id = pb.fornitore_partner_id,
    a.fornitore_partner_name = pb.fornitore_partner_name
WHERE a.deleted = 0
  AND a.azienda IS NOT NULL
  AND a.azienda <> ''
  AND (
      a.product_brand_id IS NULL
      OR a.product_brand_id = ''
  );

-- ========================================
-- MIGRAZIONE LEAD
-- ========================================

UPDATE lead l
INNER JOIN product_brand pb
    ON pb.deleted = 0
   AND pb.name = l.azienda
SET
    l.product_brand_id = pb.id,
    l.product_brand_name = pb.name,
    l.fornitore_partner_id = pb.fornitore_partner_id,
    l.fornitore_partner_name = pb.fornitore_partner_name
WHERE l.deleted = 0
  AND l.azienda IS NOT NULL
  AND l.azienda <> ''
  AND (
      l.product_brand_id IS NULL
      OR l.product_brand_id = ''
  );

-- ========================================
-- MIGRAZIONE OPPORTUNITY
-- ========================================

UPDATE opportunity o
INNER JOIN product_brand pb
    ON pb.deleted = 0
   AND pb.name = o.azienda
SET
    o.product_brand_id = pb.id,
    o.product_brand_name = pb.name,
    o.fornitore_partner_id = pb.fornitore_partner_id,
    o.fornitore_partner_name = pb.fornitore_partner_name
WHERE o.deleted = 0
  AND o.azienda IS NOT NULL
  AND o.azienda <> ''
  AND (
      o.product_brand_id IS NULL
      OR o.product_brand_id = ''
  );

-- ========================================
-- VERIFICA DOPO MIGRAZIONE
-- ========================================

SELECT
    'prospect' AS tabella,
    product_brand_name,
    fornitore_partner_name,
    COUNT(*) AS totale
FROM prospect
WHERE deleted = 0
  AND product_brand_id IS NOT NULL
  AND product_brand_id <> ''
GROUP BY product_brand_name, fornitore_partner_name

UNION ALL

SELECT
    'appuntamento' AS tabella,
    product_brand_name,
    fornitore_partner_name,
    COUNT(*) AS totale
FROM appuntamento
WHERE deleted = 0
  AND product_brand_id IS NOT NULL
  AND product_brand_id <> ''
GROUP BY product_brand_name, fornitore_partner_name

UNION ALL

SELECT
    'lead' AS tabella,
    product_brand_name,
    fornitore_partner_name,
    COUNT(*) AS totale
FROM lead
WHERE deleted = 0
  AND product_brand_id IS NOT NULL
  AND product_brand_id <> ''
GROUP BY product_brand_name, fornitore_partner_name

UNION ALL

SELECT
    'opportunity' AS tabella,
    product_brand_name,
    fornitore_partner_name,
    COUNT(*) AS totale
FROM opportunity
WHERE deleted = 0
  AND product_brand_id IS NOT NULL
  AND product_brand_id <> ''
GROUP BY product_brand_name, fornitore_partner_name
ORDER BY tabella, product_brand_name, fornitore_partner_name;
