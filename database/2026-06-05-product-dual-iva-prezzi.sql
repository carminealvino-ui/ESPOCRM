-- Campi dual IVA sul pannello Prezzo Product.
-- Dopo import: php command.php rebuild && rm -rf data/cache/*

ALTER TABLE product
    ADD COLUMN IF NOT EXISTS data_inizio_validita DATE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_esclusa DOUBLE PRECISION NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_esclusa_currency VARCHAR(3) NULL DEFAULT 'EUR',
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_inclusa DOUBLE PRECISION NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_inclusa_currency VARCHAR(3) NULL DEFAULT 'EUR',
    ADD COLUMN IF NOT EXISTS prezzo_codice_iva_inclusa DOUBLE PRECISION NULL,
    ADD COLUMN IF NOT EXISTS prezzo_codice_iva_inclusa_currency VARCHAR(3) NULL DEFAULT 'EUR';
