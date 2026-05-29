-- ========================================
-- Colonne regola_provvigionale e link provvigione (se rebuild non le crea)
-- ========================================

ALTER TABLE `provvigione`
    ADD COLUMN IF NOT EXISTS `regola_provvigionale_id` VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `regola_provvigionale_name` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `opportunity`
    ADD COLUMN IF NOT EXISTS `data_attivazione` DATE DEFAULT NULL;

ALTER TABLE `quote`
    ADD COLUMN IF NOT EXISTS `numero_pod` INT DEFAULT NULL;
