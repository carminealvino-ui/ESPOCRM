-- ========================================
-- GDL / Ariel Energia — provvigioni da 01/02/2026 (mail rete vendita)
-- Partner GDL + brand ARIEL → regime ARIEL_2026 nel CRM
-- ========================================

INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, percentuale_addizionale
) VALUES
(
    'arielBase105',
    'Ariel 2026 — 10% + 5% imponibile',
    'Provvigione standard GDL/Ariel (mandato 10% + addizionale 5%)',
    0, 1, 600,
    'ARIEL_2026',
    'PercentualeImponibileAddizionale',
    'Provvigione Base',
    10, 5
),
(
    'arielBase10',
    'Ariel 2026 — solo 10% (ordine incompleto)',
    'Decurtazione 5 punti se ordine incompleto / sopralluogo tecnico',
    0, 1, 610,
    'ARIEL_2026',
    'PercentualeImponibile',
    'Provvigione Base',
    10, NULL
),
(
    'arielPlus35',
    'Ariel 2026 — 35% su plusvalenza',
    'Plus maturata sopra listino codice (contatore minus/plus)',
    0, 1, 550,
    'ARIEL_2026',
    'PercentualePlusvalenza',
    'Plus Provvigionale',
    35, NULL
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    attiva = VALUES(attiva),
    priorita = VALUES(priorita),
    percentuale = VALUES(percentuale),
    percentuale_addizionale = VALUES(percentuale_addizionale);
