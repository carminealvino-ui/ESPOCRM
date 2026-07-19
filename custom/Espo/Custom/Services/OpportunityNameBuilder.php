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

        $dateLabel = $opportunity->get('dataOpportunit')
            ?: $opportunity->get('closeDate');

        $appuntamento = null;
        $appuntamentoId = trim((string) ($opportunity->get('appuntamentoId') ?? ''));

        if ($appuntamentoId !== '') {
            $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);
        }

        if ((!$dateLabel || $dateLabel === '') && $appuntamento && $appuntamento->get('dateStart')) {
            $dateLabel = substr((string) $appuntamento->get('dateStart'), 0, 10);
        }

        if ($dateLabel) {
            $dateLabel = substr((string) $dateLabel, 0, 10);
        }

        if ($displayName === '' && $appuntamento) {
            $displayName = trim((string) ($appuntamento->get('prospectName') ?? ''));
        }

        if ($displayName === '' && $before !== '') {
            // Estrae "LOMMI MAURIZIO" da "- LOMMI MAURIZIO - ARTEL - ..."
            $displayName = $this->extractClientFromLegacyName($before);
        }

        $brandLabel = trim((string) (
            $opportunity->get('productBrandName')
            ?: $opportunity->get('azienda')
            ?: ($appuntamento ? $appuntamento->get('productBrandName') : '')
            ?: ''
        ));

        $importo = $opportunity->get('amount')
            ?? $opportunity->get('importoOpportunit');

        $importoLabel = ($importo !== null && $importo !== '')
            ? number_format((float) $importo, 0, ',', '.')
            : '';

        $description = strtoupper(trim((string) ($opportunity->get('description') ?: '')));

        $parts = array_filter([
            $dateLabel ?: null,
            $displayName !== '' ? $displayName : null,
            $brandLabel !== '' ? $brandLabel : null,
            $description !== '' ? $description : null,
            $importoLabel !== '' ? '€. ' . $importoLabel : null,
        ], static fn ($part) => $part !== null && trim((string) $part) !== '');

        if ($parts === []) {
            return [
                'changed' => false,
                'name' => $before !== '' ? $before : null,
                'dataOpportunit' => $dateLabel ?: null,
                'before' => $before !== '' ? $before : null,
            ];
        }

        $newName = implode(' - ', $parts);
        $dataChanged = $dateLabel
            && (string) ($opportunity->get('dataOpportunit') ?? '') !== $dateLabel;
        $nameChanged = $newName !== $before;

        if (!$nameChanged && !$dataChanged) {
            return [
                'changed' => false,
                'name' => $newName,
                'dataOpportunit' => $dateLabel ?: null,
                'before' => $before,
            ];
        }

        if (!$dryRun) {
            if ($dataChanged) {
                $opportunity->set('dataOpportunit', $dateLabel);
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
            'dataOpportunit' => $dateLabel ?: null,
            'before' => $before,
        ];
    }

    public function needsRebuild(Entity $opportunity): bool
    {
        $name = trim((string) ($opportunity->get('name') ?? ''));

        if ($name === '' || str_starts_with($name, '-')) {
            return true;
        }

        // Manca prefisso data YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}\b/', $name)) {
            return true;
        }

        return false;
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

            // Skip brand-like short tokens only if next parts look like description
            return $part;
        }

        return '';
    }
}
