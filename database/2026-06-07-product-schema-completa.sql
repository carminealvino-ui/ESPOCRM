-- Schema completa Product (catalogo + prezzi + IVA).
-- Eseguire prima del rebuild se la scheda prodotto non si apre (colonne mancanti).

ALTER TABLE product
    ADD COLUMN IF NOT EXISTS elenco_catalogo INT NULL,
    ADD COLUMN IF NOT EXISTS data_inizio_validita DATE NULL,
    ADD COLUMN IF NOT EXISTS aliquota_iva DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_esclusa DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_esclusa_currency VARCHAR(3) NULL DEFAULT 'EUR',
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_inclusa DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_listino_iva_inclusa_currency VARCHAR(3) NULL DEFAULT 'EUR',
    ADD COLUMN IF NOT EXISTS prezzo_codice_iva_inclusa DOUBLE NULL,
    ADD COLUMN IF NOT EXISTS prezzo_codice_iva_inclusa_currency VARCHAR(3) NULL DEFAULT 'EUR';

ALTER TABLE product_category
    ADD COLUMN IF NOT EXISTS elenco_catalogo INT NULL;

ALTER TABLE product_brand
    ADD COLUMN IF NOT EXISTS price_book_id VARCHAR(24) NULL;
