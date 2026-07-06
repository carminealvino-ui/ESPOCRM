-- Colonne base calcolo su provvigione
-- php clear_cache.php && php rebuild.php

ALTER TABLE `provvigione`
    ADD COLUMN IF NOT EXISTS `base_calcolo` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `importo_base_calcolo` DOUBLE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `importo_base_calcolo_currency` VARCHAR(3) DEFAULT 'EUR';
