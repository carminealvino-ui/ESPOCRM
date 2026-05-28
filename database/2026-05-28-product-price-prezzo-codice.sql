-- Campi prezzo codice su product_price (Listino prezzo - Aggiornamenti).
-- Dopo import: php command.php rebuild && rm -rf data/cache/*

ALTER TABLE product_price
    ADD COLUMN IF NOT EXISTS prezzo_codice DOUBLE PRECISION NULL,
    ADD COLUMN IF NOT EXISTS prezzo_codice_currency VARCHAR(3) NULL DEFAULT 'EUR',
    ADD COLUMN IF NOT EXISTS prezzo_codice_iva_inclusa DOUBLE PRECISION NULL,
    ADD COLUMN IF NOT EXISTS prezzo_codice_iva_inclusa_currency VARCHAR(3) NULL DEFAULT 'EUR';
