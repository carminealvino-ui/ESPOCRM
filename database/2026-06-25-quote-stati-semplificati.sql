-- Migrazione valori stati Contratto (Quote) — schema semplificato 2026-06.
-- Eseguire su DB produzione PRIMA di clear_cache/rebuild dopo deploy metadata.
--
--   mysql -u ... -p ... < database/2026-06-25-quote-stati-semplificati.sql

-- Stato (status)
UPDATE quote SET status = 'Bozza' WHERE status = 'Draft';
UPDATE quote SET status = 'In lavorazione' WHERE status IN ('Presented', 'Approved');
UPDATE quote SET status = 'Installato' WHERE status = 'Installato';
UPDATE quote SET status = 'Invalido' WHERE status IN ('Recesso', 'Finanziamento Rifiutato', 'Canceled');

-- Stato Contratto (stato_contratto)
UPDATE quote SET stato_contratto = 'Inserito' WHERE stato_contratto IS NULL OR stato_contratto = '';
UPDATE quote SET stato_contratto = 'Approvato' WHERE stato_contratto IN ('Installato', 'Appuntamento Fissato');

-- Stato Finanziamento (stato_finanziamento)
UPDATE quote SET stato_finanziamento = 'In valutazione'
    WHERE stato_finanziamento IN ('In rivalutazione', 'In lavorazione');
UPDATE quote SET stato_finanziamento = 'In attesa documentazione'
    WHERE stato_finanziamento IN ('In Attesa Documentazione', 'In attesa di documentazione');
UPDATE quote SET stato_finanziamento = 'Respinto'
    WHERE stato_finanziamento = 'Annullato';
