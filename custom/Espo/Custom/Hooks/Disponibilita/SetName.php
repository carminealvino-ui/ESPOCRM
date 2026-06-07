<?php

namespace Espo\Custom\Hooks\Disponibilita;

use Espo\ORM\Entity;

/**
 * ============================================================
 * ENTITÀ: Disponibilita
 * FILE: SetName.php
 * VERSIONE: 1.3.1
 * DATA: 2026-06-06
 *
 * FIX 1.3.1
 * ✔ datadisponibilita da dateStartDate (all-day) / orarioInizio / dateStart
 * ✔ Allinea dateStartDate e dateStart datetime al salvataggio
 * ============================================================
 */
class SetName
{
    private const TIMEZONE = 'Europe/Rome';

    public function beforeSave(Entity $entity, array $options): void
    {
        $entityManager = $GLOBALS['entityManager'];

        $sourceDate = $this->resolveSourceDate($entity);

        if ($sourceDate !== null) {
            $entity->set([
                'datadisponibilita' => $sourceDate,
                'dateStartDate' => $sourceDate,
                'dateEndDate' => $sourceDate,
                'dateStart' => $sourceDate . ' 00:00:00',
                'dateEnd' => $sourceDate . ' 23:59:59',
            ]);

            $this->syncOrarioDate($entity, 'orarioInizio', $sourceDate);
            $this->syncOrarioDate($entity, 'orarioFine', $sourceDate);
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
        }

        if (!empty($fine)) {
            $dtEnd = new \DateTime($fine, new \DateTimeZone('UTC'));
            $dtEnd->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $oraFine = $dtEnd->format('H:i');
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

    /**
     * Data di inizio effettiva mostrata in elenco (all-day = dateStartDate).
     */
    private function resolveSourceDate(Entity $entity): ?string
    {
        $dateStartDate = $this->normalizeDateValue($entity->get('dateStartDate'));

        if ($dateStartDate !== null) {
            return $dateStartDate;
        }

        $orarioInizio = $entity->get('orarioInizio');

        if (!empty($orarioInizio)) {
            return $this->extractDate($orarioInizio);
        }

        $dateStart = $entity->get('dateStart');

        if (!empty($dateStart)) {
            return $this->extractDate($dateStart);
        }

        return $this->normalizeDateValue($entity->get('datadisponibilita'));
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractDate(string $dateTime): string
    {
        $dt = new \DateTime($dateTime, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return $dt->format('Y-m-d');
    }
}
