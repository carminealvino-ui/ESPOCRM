-- Regola Referenza Personale (appuntamento tipo Referenza Personale).
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale
) VALUES
(
    'referenzaPersonale',
    'Referenza Personale',
    'Appuntamento con tipo Referenza Personale — 6% su imponibile (modificabile da Regole provvigionali)',
    0, 1, 620,
    'ARIEL_2026',
    'PercentualeImponibile',
    'Referenza Personale',
    6
)
ON DUPLICATE KEY UPDATE
    deleted = 0,
    name = VALUES(name),
    description = VALUES(description),
    attiva = VALUES(attiva),
    priorita = VALUES(priorita),
    tipo_calcolo = VALUES(tipo_calcolo),
    tipo_provvigione_record = VALUES(tipo_provvigione_record),
    percentuale = VALUES(percentuale);
