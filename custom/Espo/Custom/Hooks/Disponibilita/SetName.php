<?php

namespace Espo\Custom\Hooks\Disponibilita;

use Espo\ORM\Entity;

/**
 * ============================================================
 * ENTITÀ: Disponibilita
 * FILE: SetName.php
 * VERSIONE: 1.3.0
 * DATA: 2026-06-06
 * STATO: STABILE PRODUZIONE
 *
 * ============================================================
 * FIX 1.3.0
 * ============================================================
 * ✔ datadisponibilita = sola data di dateStart (Europe/Rome)
 * ✔ Non sovrascrive più dateStart/dateEnd da datadisponibilita
 *
 * ============================================================
 * CONTESTO
 * ============================================================
 * Hook gestisce:
 *  - datadisponibilita (da dateStart)
 *  - name (azienda + orari)
 *  - isAllDay / color calendario
 * ============================================================
 */
class SetName
{
    private const TIMEZONE = 'Europe/Rome';

    public function beforeSave(Entity $entity, array $options): void
    {
        $entityManager = $GLOBALS['entityManager'];

        $dateStart = $entity->get('dateStart');

        if (!empty($dateStart)) {
            $entity->set('datadisponibilita', $this->extractDate($dateStart));
        }

        $fornitoreId = $entity->get('fornitorePartnerId');
        $aziendaNome = $entity->get('azienda');
        $inizio = $entity->get('orarioInizio');
        $fine = $entity->get('orarioFine');

        $entity->set('isAllDay', true);

        $oraInizio = '';
        $oraFine = '';

        if (!empty($inizio)) {
            $dtStart = new \DateTime($inizio, new \DateTimeZone('UTC'));
            $dtStart->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $oraInizio = $dtStart->format('H:i');
        } elseif (!empty($dateStart)) {
            $dtStart = new \DateTime($dateStart, new \DateTimeZone('UTC'));
            $dtStart->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $oraInizio = $dtStart->format('H:i');
        }

        if (!empty($fine)) {
            $dtEnd = new \DateTime($fine, new \DateTimeZone('UTC'));
            $dtEnd->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $oraFine = $dtEnd->format('H:i');
        } else {
            $dateEnd = $entity->get('dateEnd');

            if (!empty($dateEnd)) {
                $dtEnd = new \DateTime($dateEnd, new \DateTimeZone('UTC'));
                $dtEnd->setTimezone(new \DateTimeZone(self::TIMEZONE));
                $oraFine = $dtEnd->format('H:i');
            }
        }

        $nome = '';

        if (!empty($aziendaNome)) {
            $nome .= $aziendaNome . ' | ';
        }

        if ($oraInizio && $oraFine) {
            $nome .= $oraInizio . ' - ' . $oraFine;
        }

        $entity->set('name', $nome);

        if (!empty($fornitoreId)) {
            $fornitore = $entityManager->getEntityById('FornitorePartner', $fornitoreId);

            if ($fornitore) {
                $colore = $fornitore->get('color');

                if (!empty($colore)) {
                    $entity->set('color', $colore);
                    $entity->set('calendarColor', $colore);
                }
            }
        }
    }

    private function extractDate(string $dateTime): string
    {
        $dt = new \DateTime($dateTime, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return $dt->format('Y-m-d');
    }
}
