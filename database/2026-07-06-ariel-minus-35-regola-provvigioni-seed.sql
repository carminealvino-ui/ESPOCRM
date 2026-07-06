-- Minus provvigionale Ariel 35% su minusvalenza (minus/plus negativo).

INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale
) VALUES
(
    'arielMinus35',
    'Ariel 2026 — 35% su minusvalenza',
    'Minus sotto listino codice (contatore minus/plus negativo)',
    0, 1, 545,
    'ARIEL_2026',
    'PercentualePlusvalenza',
    'Minus Provvigionale',
    35
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    attiva = VALUES(attiva),
    priorita = VALUES(priorita),
    tipo_calcolo = VALUES(tipo_calcolo),
    tipo_provvigione_record = VALUES(tipo_provvigione_record),
    percentuale = VALUES(percentuale);
