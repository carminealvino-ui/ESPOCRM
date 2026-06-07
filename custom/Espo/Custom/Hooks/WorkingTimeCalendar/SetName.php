<?php

namespace Espo\Custom\Hooks\WorkingTimeCalendar;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class SetName implements BeforeSave
{
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        $name = $this->buildName($entity);

        if ($name !== null) {
            $entity->set('name', $name);
        }
    }

    private function buildName(Entity $entity): ?string
    {
        $parts = [];

        $brandName = trim((string) ($entity->get('generazioneProductBrandName') ?: ''));

        if ($brandName === '') {
            $brandName = trim((string) ($entity->get('generazioneAzienda') ?: ''));
        }

        if ($brandName !== '') {
            $parts[] = $brandName;
        }

        $area = $entity->get('generazioneArea') ?? [];

        if (!is_array($area)) {
            $area = $area !== null && $area !== '' ? [$area] : [];
        }

        if ($area !== []) {
            $parts[] = implode(', ', $area);
        }

        $dateFrom = $this->normalizeDate($entity->get('dataInizioGenerazione'));
        $dateTo = $this->normalizeDate($entity->get('dataFineGenerazione'));

        if ($dateFrom && $dateTo) {
            $parts[] = $dateFrom . ' - ' . $dateTo;
        } elseif ($dateFrom) {
            $parts[] = $dateFrom;
        }

        if ($parts === []) {
            $existing = trim((string) ($entity->get('name') ?: ''));

            return $existing !== '' ? $existing : null;
        }

        return implode(' | ', $parts);
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
}
