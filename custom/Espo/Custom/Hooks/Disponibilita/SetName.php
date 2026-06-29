<?php

namespace Espo\Custom\Hooks\Disponibilita;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Hook Disponibilita: nome, date calendario (dateStartDate + isAllDay), colore da ProductBrand.
 *
 * Il calendario Espo per eventi all-day usa dateStartDate; senza allineamento il record
 * può sparire dalla vista settimana dopo una modifica manuale degli orari.
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

        $data = $this->resolveDataDisponibilita($entity);

        if ($data === null) {
            return;
        }

        $inizio = $this->realignOrario($entity->get('orarioInizio'), $data);
        $fine = $this->realignOrario($entity->get('orarioFine'), $data);

        if ($inizio !== null) {
            $entity->set('orarioInizio', $inizio);
        }

        if ($fine !== null) {
            $entity->set('orarioFine', $fine);
        }

        $entity->set([
            'datadisponibilita' => $data,
            'dateStartDate' => $data,
            'dateEndDate' => $data,
            'isAllDay' => true,
            'dateStart' => $data . ' 00:00:00',
            'dateEnd' => $data . ' 23:59:59',
        ]);

        $oraInizio = $this->formatLocalTime($inizio);
        $oraFine = $this->formatLocalTime($fine);

        $brand = $this->resolveProductBrand($entity);
        $brandName = $brand ? (string) $brand->get('name') : '';

        if ($brandName !== '' && $entity->hasAttribute('azienda')) {
            $entity->set('azienda', $brandName);
        }

        $nome = '';

        if ($brandName !== '') {
            $nome .= $brandName . ' | ';
        } elseif ($entity->get('azienda')) {
            $nome .= $entity->get('azienda') . ' | ';
        }

        if ($oraInizio && $oraFine) {
            $nome .= $oraInizio . ' - ' . $oraFine;
        }

        $entity->set('name', $nome);
    }

    private function resolveDataDisponibilita(Entity $entity): ?string
    {
        $data = $this->normalizeDate($entity->get('datadisponibilita'));

        if ($data !== null) {
            return $data;
        }

        $data = $this->normalizeDate($entity->get('dateStartDate'));

        if ($data !== null) {
            return $data;
        }

        if ($entity->get('orarioInizio')) {
            return $this->extractDateFromOrario((string) $entity->get('orarioInizio'));
        }

        return $this->normalizeDate($entity->get('dateStart'));
    }

    private function realignOrario(mixed $value, string $data): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $local = (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $time = $local->format('H:i:s');
            $rebuilt = new \DateTimeImmutable($data . ' ' . $time, new \DateTimeZone(self::TIMEZONE));

            return $rebuilt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractDateFromOrario(string $value): ?string
    {
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone(self::TIMEZONE))
                ->format('Y-m-d');
        } catch (\Throwable) {
            return $this->normalizeDate($value);
        }
    }

    private function formatLocalTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone(self::TIMEZONE))
                ->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function resolveProductBrand(Entity $entity): ?Entity
    {
        $brandId = $entity->get('productBrandId');

        if ($brandId) {
            return $this->entityManager->getEntityById('ProductBrand', $brandId);
        }

        $azienda = trim((string) ($entity->get('azienda') ?: ''));

        if ($azienda === '') {
            return null;
        }

        $brand = $this->entityManager
            ->getRDBRepository('ProductBrand')
            ->where(['name' => $azienda])
            ->findOne();

        if (!$brand) {
            return null;
        }

        $entity->set('productBrandId', $brand->getId());
        $entity->set('productBrandName', $brand->get('name'));

        return $brand;
    }
}
