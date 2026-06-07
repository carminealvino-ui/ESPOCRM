<?php

namespace Espo\Custom\Hooks\Disponibilita;

use Espo\ORM\Entity;

/**
 * ============================================================
 * ENTITÀ: Disponibilita
 * FILE: SetName.php
 * VERSIONE: 1.2.0
 * DATA: 2026-05-07
 * STATO: STABILE PRODUZIONE
 *
 * ============================================================
 * CONTESTO
 * ============================================================
 * Questo hook gestisce:
 *
 * ✔ Nome evento (azienda + orari)
 * ✔ Impostazione calendario (all-day)
 * ✔ Conversione timezone UTC → Europe/Rome
 * ✔ Colore evento da Fornitore/Partner
 *
 * ============================================================
 * ARCHITETTURA
 * ============================================================
 * HOOK = fonte unica della verità per:
 *  - dateStart / dateEnd
 *  - name
 *  - color
 *
 * JS = SOLO UX (default valori)
 *
 * ============================================================
 * FIX IMPLEMENTATI
 * ============================================================
 * ✔ Fix timezone corretto
 * ✔ Eliminata dipendenza da campi UI
 * ✔ Aggiunto supporto calendarColor (fix calendario)
 *
 * ============================================================
 */

class SetName
{
    public function beforeSave(Entity $entity, array $options)
    {
        $entityManager = $GLOBALS['entityManager'];

        /**
         * ====================================================
         * LETTURA DATI BASE (STABILE)
         * ====================================================
         */
        $fornitoreId = $entity->get('fornitorePartnerId');
        $aziendaNome = $entity->get('azienda');
        $data = $entity->get('datadisponibilita');
        $inizio = $entity->get('orarioInizio');
        $fine = $entity->get('orarioFine');

        if (empty($data)) {
            $data = $entity->get('dateStartDate');

            if (empty($data) && !empty($entity->get('dateStart'))) {
                $data = substr((string) $entity->get('dateStart'), 0, 10);
            }

            if (!empty($data)) {
                $entity->set('datadisponibilita', $data);
            }
        }

        if (empty($data)) {
            return;
        }

        /**
         * ====================================================
         * CALENDARIO (STABILE)
         * ====================================================
         * Evento sempre all-day per visualizzazione barra alta
         */
        $entity->set('isAllDay', true);
        $entity->set('dateStart', $data . ' 00:00:00');
        $entity->set('dateEnd', $data . ' 23:59:59');

        /**
         * ====================================================
         * CONVERSIONE ORARI (FIX TIMEZONE)
         * ====================================================
         * DB → UTC
         * UI → Europe/Rome
         */
        $oraInizio = '';
        $oraFine = '';

        if (!empty($inizio)) {
            $dtStart = new \DateTime($inizio, new \DateTimeZone('UTC'));
            $dtStart->setTimezone(new \DateTimeZone('Europe/Rome'));
            $oraInizio = $dtStart->format('H:i');
        }

        if (!empty($fine)) {
            $dtEnd = new \DateTime($fine, new \DateTimeZone('UTC'));
            $dtEnd->setTimezone(new \DateTimeZone('Europe/Rome'));
            $oraFine = $dtEnd->format('H:i');
        }

        /**
         * ====================================================
         * COSTRUZIONE NOME (STABILE)
         * ====================================================
         * Formato:
         * AZIENDA | HH:mm - HH:mm
         */
        $nome = '';

        if (!empty($aziendaNome)) {
            $nome .= $aziendaNome . ' | ';
        }

        if ($oraInizio && $oraFine) {
            $nome .= $oraInizio . ' - ' . $oraFine;
        }

        $entity->set('name', $nome);

        /**
         * ====================================================
         * COLORE EVENTO (FIX COMPLETO)
         * ====================================================
         * Origine: Fornitore/Partner.color
         *
         * Problema Espo:
         * - 'color' non sempre usato dal calendario
         *
         * Soluzione:
         * ✔ set color
         * ✔ set calendarColor (fondamentale)
         */
        if (!empty($fornitoreId)) {

            $fornitore = $entityManager->getEntityById('FornitorePartner', $fornitoreId);

            if ($fornitore) {
                $colore = $fornitore->get('color');

                if (!empty($colore)) {

                    // campo standard
                    $entity->set('color', $colore);

                    // FIX calendario Espo
                    $entity->set('calendarColor', $colore);
                }
            }
        }
    }
}
