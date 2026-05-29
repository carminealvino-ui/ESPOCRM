-- ========================================
-- ARQUATI PNC — regole provvigionali (maggio 2023)
-- Eseguire DOPO rebuild + entity RegolaProvvigionale
-- Margine % = (imponibile - prezzo listino IVA escl.) / prezzo listino * 100
-- Calcolo: PercentualeMargine = % su imponibile contratto
-- ========================================

-- TENDE DA SOLE
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arqTdsGe25', 'ARQUATI Tende ≥25%', 'Allegato C 0.1 — Tende da sole', 0, 1, 410, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 18, 25, NULL),
('arqTds1524', 'ARQUATI Tende 15-24,9%', NULL, 0, 1, 400, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 15, 15, 24.9),
('arqTds514', 'ARQUATI Tende 5-14,9%', NULL, 0, 1, 390, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 13, 5, 14.9),
('arqTdsCod', 'ARQUATI Tende a codice-4,9%', NULL, 0, 1, 380, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 11, 0, 4.9),
('arqTdsM05', 'ARQUATI Tende -0,1% / -5%', NULL, 0, 1, 370, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 9, -5, -0.1),
('arqTdsM10', 'ARQUATI Tende -5,1% / -10%', NULL, 0, 1, 360, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 7, -10, -5.1),
('arqTdsM20', 'ARQUATI Tende -10,1% / -20%', NULL, 0, 1, 350, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 5, -20, -10.1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- PERGOLE
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arqPerGe25', 'ARQUATI Pergole ≥25%', NULL, 0, 1, 410, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 15, 25, NULL),
('arqPer1524', 'ARQUATI Pergole 15-24,9%', NULL, 0, 1, 400, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 13, 15, 24.9),
('arqPer514', 'ARQUATI Pergole 5-14,9%', NULL, 0, 1, 390, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 11, 5, 14.9),
('arqPerCod', 'ARQUATI Pergole a codice-4,9%', NULL, 0, 1, 380, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 9, 0, 4.9),
('arqPerM05', 'ARQUATI Pergole -0,1% / -5%', NULL, 0, 1, 370, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 7, -5, -0.1),
('arqPerM10', 'ARQUATI Pergole -5,1% / -10%', NULL, 0, 1, 360, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 6, -10, -5.1),
('arqPerM20', 'ARQUATI Pergole -10,1% / -20%', NULL, 0, 1, 350, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 4, -20, -10.1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- VETRATE
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arqVetGe25', 'ARQUATI Vetrate ≥25%', NULL, 0, 1, 410, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 13, 25, NULL),
('arqVet1524', 'ARQUATI Vetrate 15-24,9%', NULL, 0, 1, 400, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 10, 15, 24.9),
('arqVet514', 'ARQUATI Vetrate 5-14,9%', NULL, 0, 1, 390, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 9, 5, 14.9),
('arqVetCod', 'ARQUATI Vetrate a codice-4,9%', NULL, 0, 1, 380, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 8, 0, 4.9),
('arqVetM05', 'ARQUATI Vetrate -0,1% / -5%', NULL, 0, 1, 370, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 6, -5, -0.1),
('arqVetM10', 'ARQUATI Vetrate -5,1% / -10%', NULL, 0, 1, 360, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 4, -10, -5.1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- ACCESSORI / CLIMA (prezzo a codice di listino)
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale
) VALUES
('arqClima9', 'ARQUATI Clima/accessori 9% listino', 'Ciclamino, timpani, sensori, GHIBLI, SCIROCCO, BORA…', 0, 1, 320, 'ARQUATI_PNC', 'Clima e altro', 'PercentualeImponibile', 'Provvigione Base', 9)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita), percentuale = VALUES(percentuale);

-- INTEGRAZIONE CONTATTI PERSONALI +5% (si applica in PHP se flag su contratto)
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale
) VALUES
('arqCpP5', 'ARQUATI integrazione contatti personali +5%', 'Somma al calcolo base se contattoPersonaleArquati', 0, 1, 520, 'ARQUATI_PNC', 'PercentualeImponibile', 'Plus Provvigionale', 5)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita), percentuale = VALUES(percentuale);
