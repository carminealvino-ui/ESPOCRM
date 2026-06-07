-- Campi dual IVA su product_price (subpanel Prezzi / Crea prezzo).
-- Dopo import: php command.php rebuild && php command.php clearCache

ALTER TABLE product_price
    ADD COLUMN IF NOT EXISTS aliquota_iva DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_esclusa DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_esclusa_currency VARCHAR(3) NULL DEFAULT 'EUR',
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_inclusa DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_inclusa_currency VARCHAR(3) NULL DEFAULT 'EUR',
    ADD COLUMN IF NOT EXISTS prezzo_codice DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_codice_currency VARCHAR(3) NULL DEFAULT 'EUR',
    ADD COLUMN IF NOT EXISTS prezzo_codice_iva_inclusa DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_codice_iva_inclusa_currency VARCHAR(3) NULL DEFAULT 'EUR';
