ALTER TABLE `quote`
    ADD COLUMN IF NOT EXISTS `ordine_incompleto_ariel` TINYINT(1) DEFAULT 0;

ALTER TABLE `opportunity`
    ADD COLUMN IF NOT EXISTS `ordine_incompleto_ariel` TINYINT(1) DEFAULT 0;
