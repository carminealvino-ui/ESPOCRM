-- Aliquota IVA sul pannello Prezzo Product.
-- Dopo import: php command.php rebuild && rm -rf data/cache/*

ALTER TABLE product
    ADD COLUMN IF NOT EXISTS aliquota_iva DOUBLE PRECISION NULL;
