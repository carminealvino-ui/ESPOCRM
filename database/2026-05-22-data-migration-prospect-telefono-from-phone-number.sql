-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-22
-- AUTORE: CARMINE ALVINO + IA
-- FILE:
-- database/2026-05-22-data-migration-prospect-telefono-from-phone-number.sql
-- ========================================
--
-- OBIETTIVO:
-- valorizzare prospect.telefono per i record storici usando
-- il numero standard EspoCRM salvato nelle tabelle phone_number
-- e entity_phone_number.
--
-- IMPORTANTE:
-- 1) Eseguire prima backup completo DB.
-- 2) Eseguire prima le query di verifica.
-- 3) Eseguire UPDATE solo se la verifica mostra risultati corretti.
--
-- ROLLBACK:
-- usare backup completo DB.
--
-- ========================================

-- ========================================
-- VERIFICA TABELLE TELEFONO
-- ========================================

SHOW TABLES LIKE 'phone_number';
SHOW TABLES LIKE 'entity_phone_number';

-- ========================================
-- VERIFICA STRUTTURA TABELLE TELEFONO
-- ========================================

SHOW COLUMNS FROM phone_number;
SHOW COLUMNS FROM entity_phone_number;

-- ========================================
-- ANTEPRIMA PROSPECT MIGRABILI
-- ========================================

SELECT
    p.id,
    p.name,
    p.telefono,
    pn.name AS phone_number
FROM prospect p
INNER JOIN entity_phone_number epn
    ON epn.entity_id = p.id
   AND epn.entity_type = 'Prospect'
   AND epn.deleted = 0
   AND epn.`primary` = 1
INNER JOIN phone_number pn
    ON pn.id = epn.phone_number_id
   AND pn.deleted = 0
WHERE p.deleted = 0
  AND (
      p.telefono IS NULL
      OR p.telefono = ''
  )
ORDER BY
    p.name
LIMIT 50;

-- ========================================
-- MIGRAZIONE PROSPECT.telefono
-- ========================================

UPDATE prospect p
INNER JOIN entity_phone_number epn
    ON epn.entity_id = p.id
   AND epn.entity_type = 'Prospect'
   AND epn.deleted = 0
   AND epn.`primary` = 1
INNER JOIN phone_number pn
    ON pn.id = epn.phone_number_id
   AND pn.deleted = 0
SET
    p.telefono = pn.name,
    p.whats_app = CONCAT('https://wa.me/', pn.name),
    p.whats_app39 = CONCAT('https://wa.me/+39', pn.name)
WHERE p.deleted = 0
  AND (
      p.telefono IS NULL
      OR p.telefono = ''
  );

-- ========================================
-- VERIFICA DOPO MIGRAZIONE
-- ========================================

SELECT
    COUNT(*) AS prospect_senza_telefono
FROM prospect
WHERE deleted = 0
  AND (
      telefono IS NULL
      OR telefono = ''
  );
