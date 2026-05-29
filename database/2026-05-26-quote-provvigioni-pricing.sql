-- Campi listino/margine su contratto per calcolo provvigioni ARQUATI PNC
ALTER TABLE `quote`
    ADD COLUMN IF NOT EXISTS `prezzo_listino_iva_esclusa` DOUBLE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `prezzo_codice_iva_esclusa` DOUBLE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `margine_su_listino` DOUBLE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `contatto_personale_arquati` TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `integrazione_pnc_percentuale` DOUBLE DEFAULT NULL;
