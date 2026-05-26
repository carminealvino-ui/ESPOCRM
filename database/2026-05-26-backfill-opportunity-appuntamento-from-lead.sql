-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-26
-- FILE: database/2026-05-26-backfill-opportunity-appuntamento-from-lead.sql
-- ========================================
--
-- Collega opportunity.appuntamento_id quando manca ma lead_id e' valorizzato.
-- Usa l'appuntamento piu' recente (date_start) per quel lead.
--
-- Backup prima: export tabella opportunity da phpMyAdmin.
--
-- Anteprima (quante righe verrebbero aggiornate):
-- SELECT COUNT(*) FROM opportunity o
-- WHERE o.deleted = 0
--   AND o.lead_id IS NOT NULL AND o.lead_id != ''
--   AND (o.appuntamento_id IS NULL OR o.appuntamento_id = '');

-- ========================================

UPDATE opportunity o
INNER JOIN (
    SELECT
        o2.id AS opportunity_id,
        (
            SELECT a.id
            FROM appuntamento a
            WHERE a.deleted = 0
              AND (
                  a.lead_id = o2.lead_id
                  OR (a.parent_type = 'Lead' AND a.parent_id = o2.lead_id)
              )
            ORDER BY a.date_start DESC
            LIMIT 1
        ) AS appuntamento_id
    FROM opportunity o2
    WHERE o2.deleted = 0
      AND o2.lead_id IS NOT NULL
      AND o2.lead_id != ''
      AND (o2.appuntamento_id IS NULL OR o2.appuntamento_id = '')
) src ON src.opportunity_id = o.id
    AND src.appuntamento_id IS NOT NULL
    AND src.appuntamento_id != ''
SET o.appuntamento_id = src.appuntamento_id;

-- Verifica: opportunita con lead ma senza appuntamento (dovrebbe essere 0 o pochissime)
SELECT COUNT(*) AS senza_appuntamento_con_lead
FROM opportunity
WHERE deleted = 0
  AND lead_id IS NOT NULL
  AND lead_id != ''
  AND (appuntamento_id IS NULL OR appuntamento_id = '');
