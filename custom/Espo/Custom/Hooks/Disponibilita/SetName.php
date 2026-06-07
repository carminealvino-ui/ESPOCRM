<?php

namespace Espo\Custom\Hooks\Disponibilita;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Disponibilita: datadisponibilita = data di inizio (solo data).
 *
 * v1.3.2: orarioInizio e' la data operativa reale sui record esistenti;
 * se l'utente modifica Data inizio (dateStart/dateStartDate) prevale quella.
 */
class SetName implements BeforeSave
{
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

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
            $oraInizio = $this->extractTime($inizio);
        }

        if (!empty($fine)) {
            $oraFine = $this->extractTime($fine);
        }

        $nome = '';

        if (!empty($aziendaNome)) {
            $nome .= $aziendaNome . ' | ';
        }

        if ($oraInizio && $oraFine) {
            $nome .= $oraInizio . ' - ' . $oraFine;
        }

        $entity->set('name', $nome);

        if (empty($fornitoreId)) {
            return;
        }

        $fornitore = $this->entityManager->getEntityById('FornitorePartner', $fornitoreId);

        if (!$fornitore) {
            return;
        }

        $colore = $fornitore->get('color');

        if (empty($colore)) {
            return;
        }

        $entity->set('color', $colore);
        $entity->set('calendarColor', $colore);
    }

    private function resolveSourceDate(Entity $entity): ?string
    {
        if ($entity->isAttributeChanged('dateStart') || $entity->isAttributeChanged('dateStartDate')) {
            $fromDataInizio = $this->dateFromDataInizioFields($entity);

            if ($fromDataInizio !== null) {
                return $fromDataInizio;
            }
        }

        $orarioInizio = $entity->get('orarioInizio');

        if (!empty($orarioInizio)) {
            return $this->extractDate($orarioInizio);
        }

        return $this->dateFromDataInizioFields($entity);
    }

    private function dateFromDataInizioFields(Entity $entity): ?string
    {
        $dateStartDate = $this->normalizeDateValue($entity->get('dateStartDate'));

        if ($dateStartDate !== null) {
            return $dateStartDate;
        }

        $dateStart = $entity->get('dateStart');

        if (!empty($dateStart)) {
            return $this->extractDate($dateStart);
        }

        return null;
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

    private function extractTime(string $dateTime): string
    {
        $dt = new \DateTime($dateTime, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return $dt->format('H:i');
    }

    private function syncOrarioDate(Entity $entity, string $field, string $sourceDate): void
    {
        $value = $entity->get($field);

        if (!is_string($value) || $value === '') {
            return;
        }

        $dt = new \DateTime($value, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone(self::TIMEZONE));

        $entity->set($field, $sourceDate . ' ' . $dt->format('H:i:s'));
    }
}
