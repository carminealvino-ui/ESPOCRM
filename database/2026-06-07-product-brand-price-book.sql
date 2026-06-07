-- Listino prezzi collegato al Brand (es. ARIEL → ARIEL Energia).
-- Dopo import: php command.php rebuild && rm -rf data/cache/*

ALTER TABLE product_brand
    ADD COLUMN IF NOT EXISTS price_book_id VARCHAR(24) NULL;

CREATE INDEX IF NOT EXISTS idx_product_brand_price_book_id ON product_brand (price_book_id);
