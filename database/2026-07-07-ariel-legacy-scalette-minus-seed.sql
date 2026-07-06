-- ========================================
-- ARIEL LEGACY — Nuove Scalette Minus (vigore fino al 23/01/2026)
-- Promozione settembre–dicembre 2025; regime CRM attivo su contratti con data < 01/02/2026
-- Sconto % = (imponibile - prezzo codice IVA escl.) / prezzo codice * 100
-- Calcolo base: PercentualeMargine = % su imponibile contratto
-- Plus oltre listino: PercentualePlusvalenza 50% su (imponibile - listino) se imponibile > listino
-- ========================================

-- CLIMATIZZATORI
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arlCliM029', 'Ariel Clima 0% / -2,99%', 'Scaletta minus climatizzatori', 0, 1, 410, 'ARIEL_LEGACY', 'Ariel Climatizzatori', 'PercentualeMargine', 'Provvigione Base', 15, -2.99, 0),
('arlCliM499', 'Ariel Clima -3% / -4,99%', NULL, 0, 1, 400, 'ARIEL_LEGACY', 'Ariel Climatizzatori', 'PercentualeMargine', 'Provvigione Base', 13, -4.99, -3),
('arlCliM699', 'Ariel Clima -5% / -6,99%', NULL, 0, 1, 390, 'ARIEL_LEGACY', 'Ariel Climatizzatori', 'PercentualeMargine', 'Provvigione Base', 12, -6.99, -5),
('arlCliM999', 'Ariel Clima -7% / -9,99%', NULL, 0, 1, 380, 'ARIEL_LEGACY', 'Ariel Climatizzatori', 'PercentualeMargine', 'Provvigione Base', 10, -9.99, -7),
('arlCliM1299', 'Ariel Clima -10% / -12,99%', NULL, 0, 1, 370, 'ARIEL_LEGACY', 'Ariel Climatizzatori', 'PercentualeMargine', 'Provvigione Base', 6, -12.99, -10),
('arlCliM1499', 'Ariel Clima -13% / -14,99%', NULL, 0, 1, 360, 'ARIEL_LEGACY', 'Ariel Climatizzatori', 'PercentualeMargine', 'Provvigione Base', 3, -14.99, -13)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- CALDAIE
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arlCalM290', 'Ariel Caldaie 0% / -2,90%', 'Scaletta minus caldaie', 0, 1, 410, 'ARIEL_LEGACY', 'Ariel Caldaie', 'PercentualeMargine', 'Provvigione Base', 13, -2.9, 0),
('arlCalM1000', 'Ariel Caldaie -3% / -10%', NULL, 0, 1, 400, 'ARIEL_LEGACY', 'Ariel Caldaie', 'PercentualeMargine', 'Provvigione Base', 7, -10, -3),
('arlCalMDeep', 'Ariel Caldaie oltre -10,10%', NULL, 0, 1, 390, 'ARIEL_LEGACY', 'Ariel Caldaie', 'PercentualeMargine', 'Provvigione Base', 5, -99999, -10.1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- ECO WIND EASY (prezzo fisso, 5% a codice)
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arlEcoWind5', 'Ariel Eco Wind Easy 5%', 'Pacchetto installato a prezzo fisso', 0, 1, 420, 'ARIEL_LEGACY', 'Ariel Eco Wind Easy', 'PercentualeMargine', 'Provvigione Base', 5, 0, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- STUFE (stessa scaletta caldaie)
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, gruppo_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale, margine_min, margine_max
) VALUES
('arlStuM290', 'Ariel Stufe 0% / -2,90%', 'Scaletta minus stufe', 0, 1, 410, 'ARIEL_LEGACY', 'Ariel Stufe', 'PercentualeMargine', 'Provvigione Base', 13, -2.9, 0),
('arlStuM1000', 'Ariel Stufe -3% / -10%', NULL, 0, 1, 400, 'ARIEL_LEGACY', 'Ariel Stufe', 'PercentualeMargine', 'Provvigione Base', 7, -10, -3),
('arlStuMDeep', 'Ariel Stufe oltre -10,10%', NULL, 0, 1, 390, 'ARIEL_LEGACY', 'Ariel Stufe', 'PercentualeMargine', 'Provvigione Base', 5, -99999, -10.1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita),
    percentuale = VALUES(percentuale), margine_min = VALUES(margine_min), margine_max = VALUES(margine_max);

-- PLUS oltre listino (50% sul surplus vs listino IVA escl.)
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale
) VALUES
('arlLegacyPlus50', 'Ariel legacy — Plus 50% oltre listino', 'Clima/Caldaie/Stufe (escluso Eco Wind Easy)', 0, 1, 510, 'ARIEL_LEGACY', 'PercentualePlusvalenza', 'Plus Provvigionale', 50)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), attiva = VALUES(attiva), priorita = VALUES(priorita), percentuale = VALUES(percentuale);
