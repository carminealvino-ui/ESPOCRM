-- ========================================
-- VERSIONE: 1.0.0
-- DATA: 2026-05-25
-- Seed regole provvigionali (esempi) - eseguire DOPO rebuild
-- ========================================
-- ATTENZIONE: adattare fornitore_partner_id / product_brand_id agli ID reali
-- oppure creare le regole da Amministrazione > Regole provvigionali.
-- ========================================

-- Esempio Ariel 2026: 10% + 5% su imponibile
-- INSERT INTO regola_provvigionale (id, name, attiva, priorita, regime_provvigione, tipo_calcolo,
--   percentuale, percentuale_addizionale, tipo_provvigione_record, deleted)
-- VALUES ('ARIEL10P5', 'Ariel 10+5 imponibile', 1, 200, 'ARIEL_2026',
--   'PercentualeImponibileAddizionale', 10, 5, 'Provvigione Base', 0);

-- Esempio Vodafone coeff 2 (inflow < 500) - creare 3 righe per fasce inflow e categorie

-- Esempio Enel gettone 30 - tipo GettoneFisso, gettone_importo 30, giorni_liquidazione 40

-- Esempio Fastweb POD 70 (0-4) / 100 (5+)

SELECT 'Configurare regole da UI dopo rebuild' AS nota;
