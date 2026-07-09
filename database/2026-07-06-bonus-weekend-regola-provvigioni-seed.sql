-- Bonus provvigionale contratti chiusi sabato o domenica (% su imponibile).
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale
) VALUES
(
    'bonusWeekendSd',
    'Bonus Sabato-Domenica',
    'Extra provvigione se data contratto/appuntamento cade sabato o domenica',
    0, 1, 530,
    '',
    'PercentualeImponibile',
    'Bonus (Sabato-Domenica)',
    2
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
