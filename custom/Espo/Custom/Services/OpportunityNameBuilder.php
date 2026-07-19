<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Nome opportunità: YYYY-MM-DD - Cliente - Brand - DESCRIZIONE - €. importo
 */
class OpportunityNameBuilder
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return array{changed: bool, name: ?string, dataOpportunit: ?string, before: ?string}
     */
    public function rebuild(Entity $opportunity, bool $dryRun = false): array
    {
        $before = (string) ($opportunity->get('name') ?? '');

        $displayName = trim((string) (
            $opportunity->get('prospectName')
            ?: $opportunity->get('leadName')
            ?: $opportunity->get('accountName')
            ?: ''
        ));

        $appuntamento = $this->resolveAppuntamento($opportunity);

        $dateForField = $this->normalizeDate($opportunity->get('dataOpportunit'))
            ?: $this->normalizeDate($opportunity->get('closeDate'));

        if ($dateForField === null && $appuntamento) {
            $dateForField = $this->normalizeDate($appuntamento->get('dateStart'))
                ?: $this->normalizeDate($appuntamento->get('dataAppuntamento'));
        }

        // Ultimo fallback solo per il nome (non scrive dataOpportunit).
        $dateForName = $dateForField
            ?: $this->normalizeDate($opportunity->get('createdAt'));

        if ($displayName === '' && $appuntamento) {
            $displayName = trim((string) ($appuntamento->get('prospectName') ?? ''));
        }

        if ($displayName === '' && $before !== '') {
            $displayName = $this->extractClientFromLegacyName($before);
        }

        $brandLabel = trim((string) (
            $opportunity->get('productBrandName')
            ?: $opportunity->get('azienda')
            ?: ($appuntamento ? ($appuntamento->get('productBrandName') ?: $appuntamento->get('azienda')) : '')
            ?: ''
        ));

        $importo = $opportunity->get('amount');
        if ($importo === null || $importo === '') {
            $importo = $opportunity->get('importoOpportunit');
        }

        $importoLabel = $this->formatImporto($importo);

        $description = strtoupper(trim((string) ($opportunity->get('description') ?: '')));

        // Se descrizione vuota, prova a ricavarla dal nome legacy.
        if ($description === '' && $before !== '') {
            $description = $this->extractDescriptionFromLegacyName($before, $displayName, $brandLabel);
        }

        $parts = array_filter([
            $dateForName,
            $displayName !== '' ? $displayName : null,
            $brandLabel !== '' ? $brandLabel : null,
            $description !== '' ? $description : null,
            $importoLabel !== '' ? '€. ' . $importoLabel : null,
        ], static fn ($part) => $part !== null && trim((string) $part) !== '');

        if ($parts === []) {
            return [
                'changed' => false,
                'name' => $before !== '' ? $before : null,
                'dataOpportunit' => $dateForField,
                'before' => $before !== '' ? $before : null,
            ];
        }

        $newName = implode(' - ', $parts);
        $dataChanged = $dateForField !== null
            && (string) ($opportunity->get('dataOpportunit') ?? '') !== $dateForField;
        $nameChanged = $newName !== $before;

        if (!$nameChanged && !$dataChanged) {
            return [
                'changed' => false,
                'name' => $newName,
                'dataOpportunit' => $dateForField,
                'before' => $before,
            ];
        }

        if (!$dryRun) {
            if ($dataChanged) {
                $opportunity->set('dataOpportunit', $dateForField);
            }

            if ($nameChanged) {
                $opportunity->set('name', $newName);
            }

            $this->entityManager->saveEntity($opportunity, [
                'silent' => true,
                'skipHooks' => true,
            ]);
        }

        return [
            'changed' => true,
            'name' => $newName,
            'dataOpportunit' => $dateForField,
            'before' => $before,
        ];
    }

    public function needsRebuild(Entity $opportunity): bool
    {
        $name = trim((string) ($opportunity->get('name') ?? ''));

        if ($name === '') {
            return true;
        }

        // Trattino iniziale / spazio + trattino
        if (preg_match('/^\s*-/', $name)) {
            return true;
        }

        // Manca prefisso data YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}\b/', $name)) {
            return true;
        }

        return false;
    }

    private function resolveAppuntamento(Entity $opportunity): ?Entity
    {
        $appuntamentoId = trim((string) ($opportunity->get('appuntamentoId') ?? ''));

        if ($appuntamentoId !== '') {
            $linked = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

            if ($linked) {
                return $linked;
            }
        }

        $prospectId = trim((string) ($opportunity->get('prospectId') ?? ''));

        if ($prospectId === '') {
            return null;
        }

        $candidates = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where(['prospectId' => $prospectId])
            ->order('dateStart', 'DESC')
            ->limit(0, 5)
            ->find();

        foreach ($candidates as $app) {
            return $app;
        }

        return null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        // 23.04.2025 → 2025-04-23
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        $ts = strtotime($raw);

        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function formatImporto(mixed $importo): string
    {
        if ($importo === null || $importo === '') {
            return '';
        }

        $value = (float) $importo;

        // Evita 0.01 → 0
        if (abs($value) > 0 && abs($value) < 1) {
            return number_format($value, 2, ',', '.');
        }

        return number_format($value, 0, ',', '.');
    }

    private function extractClientFromLegacyName(string $name): string
    {
        $trimmed = trim($name);
        $trimmed = ltrim($trimmed, "- \t");
        $parts = array_map('trim', explode(' - ', $trimmed));

        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $part)) {
                continue;
            }

            if (preg_match('/^€/', $part)) {
                continue;
            }

            return $part;
        }

        return '';
    }

    private function extractDescriptionFromLegacyName(
        string $name,
        string $displayName,
        string $brandLabel
    ): string {
        $trimmed = trim($name);
        $trimmed = ltrim($trimmed, "- \t");
        $parts = array_map('trim', explode(' - ', $trimmed));
        $descParts = [];

        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $part)) {
                continue;
            }

            if (preg_match('/^€/', $part)) {
                continue;
            }

            if ($displayName !== '' && strcasecmp($part, $displayName) === 0) {
                continue;
            }

            if ($brandLabel !== '' && strcasecmp($part, $brandLabel) === 0) {
                continue;
            }

            $descParts[] = $part;
        }

        return strtoupper(trim(implode(' - ', $descParts)));
    }
}
