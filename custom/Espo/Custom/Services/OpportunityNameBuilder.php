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

        $appuntamento = $this->resolveAppuntamento($opportunity);
        $displayName = $this->resolveClientLabel($opportunity, $appuntamento, $before);

        $dateForField = $this->normalizeDate($opportunity->get('dataOpportunit'))
            ?: $this->normalizeDate($opportunity->get('closeDate'));

        if ($dateForField === null && $appuntamento) {
            $dateForField = $this->normalizeDate($appuntamento->get('dateStart'))
                ?: $this->normalizeDate($appuntamento->get('dataAppuntamento'));
        }

        $dateForName = $dateForField
            ?: $this->normalizeDate($opportunity->get('createdAt'));

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

        if ($description === '' && $before !== '') {
            $description = $this->extractDescriptionFromLegacyName($before, $displayName, $brandLabel);
        }

        $parts = array_values(array_filter([
            $dateForName,
            $displayName !== '' ? $displayName : null,
            $brandLabel !== '' ? $brandLabel : null,
            $description !== '' ? $description : null,
            $importoLabel !== '' ? '€. ' . $importoLabel : null,
        ], static fn ($part) => $part !== null && trim((string) $part) !== ''));

        if ($parts === []) {
            return [
                'changed' => false,
                'name' => $before !== '' ? $before : null,
                'dataOpportunit' => $dateForField,
                'before' => $before !== '' ? $before : null,
            ];
        }

        $newName = implode(' - ', $parts);
        // Evita residui " - - "
        $newName = preg_replace('/\s*-\s*-\s*/', ' - ', $newName) ?? $newName;
        $newName = trim($newName, " -\t");

        $dataChanged = $dateForField !== null
            && (string) ($opportunity->get('dataOpportunit') ?? '') !== $dateForField;
        $nameChanged = $newName !== $before;

        $leadNameSync = false;
        if (
            $displayName !== ''
            && trim((string) ($opportunity->get('leadName') ?? '')) === ''
            && $opportunity->get('leadId')
        ) {
            $leadNameSync = true;
        }

        if (!$nameChanged && !$dataChanged && !$leadNameSync) {
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

            if ($leadNameSync) {
                $opportunity->set('leadName', $displayName);
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

        if (preg_match('/^\s*-/', $name)) {
            return true;
        }

        // Cliente mancante: "2025-08-09 - - PROGETTO - ..."
        if (preg_match('/\s-\s+-\s/', $name) || str_contains($name, ' - - ')) {
            return true;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}\b/', $name)) {
            return true;
        }

        // Ha lead/prospect ma il nome non contiene il cliente
        $client = trim((string) (
            $opportunity->get('leadName')
            ?: $opportunity->get('prospectName')
            ?: $opportunity->get('accountName')
            ?: ''
        ));

        if ($client === '' && $opportunity->get('leadId')) {
            $lead = $this->entityManager->getEntityById('Lead', (string) $opportunity->get('leadId'));
            $client = $lead ? trim((string) ($lead->get('name') ?? '')) : '';
        }

        if ($client !== '' && !str_contains(mb_strtoupper($name), mb_strtoupper($client))) {
            return true;
        }

        return false;
    }

    private function resolveClientLabel(
        Entity $opportunity,
        ?Entity $appuntamento,
        string $before
    ): string {
        $displayName = trim((string) (
            $opportunity->get('prospectName')
            ?: $opportunity->get('leadName')
            ?: $opportunity->get('accountName')
            ?: ''
        ));

        if ($displayName !== '') {
            return $displayName;
        }

        $leadId = trim((string) ($opportunity->get('leadId') ?? ''));
        if ($leadId !== '') {
            $lead = $this->entityManager->getEntityById('Lead', $leadId);
            if ($lead) {
                $n = trim((string) ($lead->get('name') ?? ''));
                if ($n !== '') {
                    return $n;
                }
            }
        }

        $prospectId = trim((string) ($opportunity->get('prospectId') ?? ''));
        if ($prospectId !== '') {
            $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);
            if ($prospect) {
                $n = trim((string) ($prospect->get('name') ?? ''));
                if ($n !== '') {
                    return $n;
                }
            }
        }

        $accountId = trim((string) ($opportunity->get('accountId') ?? ''));
        if ($accountId !== '') {
            $account = $this->entityManager->getEntityById('Account', $accountId);
            if ($account) {
                $n = trim((string) ($account->get('name') ?? ''));
                if ($n !== '') {
                    return $n;
                }
            }
        }

        if ($appuntamento) {
            $n = trim((string) (
                $appuntamento->get('prospectName')
                ?: $appuntamento->get('parentName')
                ?: ''
            ));
            if ($n !== '' && !str_contains($n, 'SENZA PROSPECT')) {
                return $n;
            }

            if ($appuntamento->get('parentType') === 'Lead' && $appuntamento->get('parentId')) {
                $lead = $this->entityManager->getEntityById('Lead', (string) $appuntamento->get('parentId'));
                if ($lead) {
                    $n = trim((string) ($lead->get('name') ?? ''));
                    if ($n !== '') {
                        return $n;
                    }
                }
            }
        }

        // Non usare extract da legacy se produrrebbe il brand (PROGETTO) come cliente
        return '';
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

        if (abs($value) > 0 && abs($value) < 1) {
            return number_format($value, 2, ',', '.');
        }

        return number_format($value, 0, ',', '.');
    }

    private function extractDescriptionFromLegacyName(
        string $name,
        string $displayName,
        string $brandLabel
    ): string {
        $trimmed = trim($name);
        $trimmed = ltrim($trimmed, "- \t");
        $trimmed = preg_replace('/\s*-\s*-\s*/', ' - ', $trimmed) ?? $trimmed;
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
