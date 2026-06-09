-- ========================================
-- Seed completo Regole Provvigionali — Fase A (inserimento manuale su contratto)
-- Eseguire DOPO rebuild EspoCRM (entità RegolaProvvigionale attiva).
-- Include: ARQUATI PNC, Ariel 2026, GFB Vodafone, Fastweb POD, Enel gettone.
-- ========================================

-- ---------- Ariel 2026 (GDL) ----------
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
    'Plus maturata sopra prezzo a codice (minus/plus)',
    0, 1, 550,
    'ARIEL_2026',
    'PercentualePlusvalenza',
    'Plus Provvigionale',
    35, NULL
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), description = VALUES(description),
    attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), percentuale_addizionale = VALUES(percentuale_addizionale);

-- ---------- ARQUATI PNC — Tende da sole ----------
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arqTdsGe25', 'ARQUATI Tende ≥25%', 'Allegato C — Tende da sole', 0, 1, 410, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 18, 25, NULL),
('arqTds1524', 'ARQUATI Tende 15-24,9%', NULL, 0, 1, 400, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 15, 15, 24.9),
('arqTds514', 'ARQUATI Tende 5-14,9%', NULL, 0, 1, 390, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 13, 5, 14.9),
('arqTdsCod', 'ARQUATI Tende a codice-4,9%', NULL, 0, 1, 380, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 11, 0, 4.9),
('arqTdsM05', 'ARQUATI Tende -0,1% / -5%', NULL, 0, 1, 370, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 9, -5, -0.1),
('arqTdsM10', 'ARQUATI Tende -5,1% / -10%', NULL, 0, 1, 360, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 7, -10, -5.1),
('arqTdsM20', 'ARQUATI Tende -10,1% / -20%', NULL, 0, 1, 350, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 5, -20, -10.1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- ---------- ARQUATI PNC — Pergole ----------
INSERT INTO regola_provvigionale (
    id, name, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arqPerGe25', 'ARQUATI Pergole ≥25%', 0, 1, 410, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 15, 25, NULL),
('arqPer1524', 'ARQUATI Pergole 15-24,9%', 0, 1, 400, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 13, 15, 24.9),
('arqPer514', 'ARQUATI Pergole 5-14,9%', 0, 1, 390, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 11, 5, 14.9),
('arqPerCod', 'ARQUATI Pergole a codice-4,9%', 0, 1, 380, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 9, 0, 4.9),
('arqPerM05', 'ARQUATI Pergole -0,1% / -5%', 0, 1, 370, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 7, -5, -0.1),
('arqPerM10', 'ARQUATI Pergole -5,1% / -10%', 0, 1, 360, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 6, -10, -5.1),
('arqPerM20', 'ARQUATI Pergole -10,1% / -20%', 0, 1, 350, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 4, -20, -10.1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- ---------- ARQUATI PNC — Vetrate ----------
INSERT INTO regola_provvigionale (
    id, name, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arqVetGe25', 'ARQUATI Vetrate ≥25%', 0, 1, 410, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 13, 25, NULL),
('arqVet1524', 'ARQUATI Vetrate 15-24,9%', 0, 1, 400, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 10, 15, 24.9),
('arqVet514', 'ARQUATI Vetrate 5-14,9%', 0, 1, 390, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 9, 5, 14.9),
('arqVetCod', 'ARQUATI Vetrate a codice-4,9%', 0, 1, 380, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 8, 0, 4.9),
('arqVetM05', 'ARQUATI Vetrate -0,1% / -5%', 0, 1, 370, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 6, -5, -0.1),
('arqVetM10', 'ARQUATI Vetrate -5,1% / -10%', 0, 1, 360, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 4, -10, -5.1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- ---------- ARQUATI — Clima/accessori + integrazione CP ----------
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record, percentuale
) VALUES
(
    'arqClima9',
    'ARQUATI Clima/accessori 9% listino',
    'Accessori a prezzo di listino codice',
    0, 1, 320, 'ARQUATI_PNC', 'Clima e altro', 'PercentualeImponibile', 'Provvigione Base', 9
),
(
    'arqCpP5',
    'ARQUATI integrazione contatti personali +5%',
    'Somma al calcolo base se flag contatto personale su contratto',
    0, 1, 520, 'ARQUATI_PNC', NULL, 'PercentualeImponibile', 'Plus Provvigionale', 5
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita), percentuale = VALUES(percentuale);

-- ---------- GFB Vodafone — coefficiente su canone per fascia inflow ----------
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    coefficiente, inflow_min, inflow_max, giorni_liquidazione
) VALUES
(
    'gfbVfCoeff2',
    'GFB Vodafone — coeff. 2 (inflow < 500)',
    'Canone mensile × 2 se inflow totale sotto 500 €',
    0, 1, 500,
    'GFB_VODAFONE_COEFF',
    'CoefficienteCanone',
    'Provvigione Base',
    2, NULL, 499.99, 0
),
(
    'gfbVfCoeff15',
    'GFB Vodafone — coeff. 1,5 (inflow 500-999)',
    NULL,
    0, 1, 490,
    'GFB_VODAFONE_COEFF',
    'CoefficienteCanone',
    'Provvigione Base',
    1.5, 500, 999.99, 0
),
(
    'gfbVfCoeff1',
    'GFB Vodafone — coeff. 1 (inflow ≥ 1000)',
    NULL,
    0, 1, 480,
    'GFB_VODAFONE_COEFF',
    'CoefficienteCanone',
    'Provvigione Base',
    1, 1000, NULL, 0
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    coefficiente = VALUES(coefficiente), inflow_min = VALUES(inflow_min), inflow_max = VALUES(inflow_max);

-- ---------- GFB Fastweb — importo per POD ----------
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    importo_fisso_pod, pod_min, pod_max, giorni_liquidazione
) VALUES
(
    'gfbFwPod04',
    'GFB Fastweb — 70 €/POD (1-4 POD)',
    NULL,
    0, 1, 470,
    'GFB_FASTWEB_POD',
    'ImportoFissoPod',
    'Provvigione Base',
    70, 1, 4, 60
),
(
    'gfbFwPod5',
    'GFB Fastweb — 100 €/POD (da 5 POD)',
    NULL,
    0, 1, 460,
    'GFB_FASTWEB_POD',
    'ImportoFissoPod',
    'Provvigione Base',
    100, 5, NULL, 60
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    importo_fisso_pod = VALUES(importo_fisso_pod), pod_min = VALUES(pod_min), pod_max = VALUES(pod_max),
    giorni_liquidazione = VALUES(giorni_liquidazione);

-- ---------- Solution / Enel — gettone fisso ----------
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    gettone_importo, giorni_liquidazione
) VALUES
(
    'solEnelGt30',
    'Solution Enel — gettone 30 €',
    'Liquidazione a 40 giorni da attivazione',
    0, 1, 450,
    'SOLUTION_ENEL_GETTONE',
    'GettoneFisso',
    'Provvigione Base',
    30, 40
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    gettone_importo = VALUES(gettone_importo), giorni_liquidazione = VALUES(giorni_liquidazione);

-- ---------- GFB RS — placeholder percentuale generica (da affinare) ----------
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record, percentuale
) VALUES
(
    'gfbRsBase5',
    'GFB RS — 5% imponibile (generico)',
    'Regola placeholder regime GFB_RS_BIMESTRE — verificare contratto commerciale',
    0, 1, 440,
    'GFB_RS_BIMESTRE',
    'PercentualeImponibile',
    'Provvigione Base',
    5
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita), percentuale = VALUES(percentuale);

SELECT COUNT(*) AS regole_attive FROM regola_provvigionale WHERE deleted = 0 AND attiva = 1;
