<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Naming Appuntamento (ex formula beforeSaveCustomScript / beforeSaveApiScript).
 *
 * Formato: {CAP} - {Prospect/Lead} ({Brand} - {Categoria})
 * Es.: 00169 - LZ/A3 (Torre Maura) - PANFILI PAOLO (ARIEL - CLIMATIZZATORI)
 */
class AppuntamentoNameBuilder
{
    private const PLACEHOLDER = '(APPUNTAMENTO SENZA PROSPECT)';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function needsRebuild(Entity $entity): bool
    {
        $name = trim((string) $entity->get('name'));

        if ($name === '') {
            return true;
        }

        // Solo orario o placeholder rotto
        if (preg_match('/^\d{2}:\d{2}/', $name)) {
            return true;
        }

        $expected = $this->build($entity);

        if ($expected === '' || $expected === $name) {
            return false;
        }

        if ($this->isDowngrade($name, $expected)) {
            return false;
        }

        return true;
    }

    public function build(Entity $entity): string
    {
        $capBlock = $this->buildCapBlock($entity);

        $prospectBlock = $this->resolveProspectBlock($entity);

        if ($prospectBlock === '') {
            $prospectBlock = self::PLACEHOLDER;
        }

        $brandLabel = trim((string) (
            $entity->get('azienda')
            ?: $entity->get('productBrandName')
            ?: ''
        ));

        $categoriaLabel = trim((string) (
            $entity->get('productCategoryName')
            ?: $entity->get('lineaProdotto')
            ?: ''
        ));

        $extraBlock = '';

        if ($brandLabel !== '' && $categoriaLabel !== '') {
            $extraBlock = ' (' . $brandLabel . ' - ' . $categoriaLabel . ')';
        } elseif ($brandLabel !== '' || $categoriaLabel !== '') {
            $extraBlock = ' (' . ($brandLabel !== '' ? $brandLabel : $categoriaLabel) . ')';
        }

        $prefix = $capBlock !== '' ? $capBlock . ' - ' : '';

        return trim($prefix . $prospectBlock . $extraBlock);
    }

    /**
     * @return array{changed: bool, before: string, name: string}
     */
    public function rebuild(Entity $entity, bool $dryRun = false): array
    {
        $before = trim((string) $entity->get('name'));
        $name = $this->build($entity);

        if ($name === '' || $name === $before || $this->isDowngrade($before, $name)) {
            return [
                'changed' => false,
                'before' => $before,
                'name' => $before,
            ];
        }

        $entity->set('name', $name);

        if (!$dryRun) {
            $this->entityManager->saveEntity($entity, [
                'skipHooks' => true,
                'silent' => true,
            ]);
        }

        return [
            'changed' => true,
            'before' => $before,
            'name' => $name,
        ];
    }

    private function resolveProspectBlock(Entity $entity): string
    {
        $fromFields = trim((string) (
            $entity->get('prospectName')
            ?: $entity->get('parentName')
            ?: $entity->get('leadName')
            ?: ''
        ));

        if ($fromFields !== '') {
            return $fromFields;
        }

        $prospectId = $entity->get('prospectId');

        if ($prospectId) {
            $name = $this->resolveEntityName('Prospect', (string) $prospectId);

            if ($name !== '') {
                return $name;
            }
        }

        if ($entity->get('parentType') === 'Prospect' && $entity->get('parentId')) {
            $name = $this->resolveEntityName('Prospect', (string) $entity->get('parentId'));

            if ($name !== '') {
                return $name;
            }
        }

        $parentType = $entity->get('parentType');
        $parentId = $entity->get('parentId');

        if ($parentType && $parentId) {
            $name = $this->resolveEntityName((string) $parentType, (string) $parentId);

            if ($name !== '') {
                return $name;
            }
        }

        if ($entity->get('leadId')) {
            $name = $this->resolveEntityName('Lead', (string) $entity->get('leadId'));

            if ($name !== '') {
                return $name;
            }
        }

        $parsed = $this->parseProspectFromName($entity);

        if ($parsed !== '') {
            return $parsed;
        }

        return $this->parseProspectFromDescription($entity);
    }

    private function resolveEntityName(string $entityType, string $id): string
    {
        $linked = $this->entityManager->getEntityById($entityType, $id);

        if (!$linked) {
            return '';
        }

        return (new LeadProspectSync($this->entityManager))
            ->resolveDisplayName($linked) ?: '';
    }

    private function parseProspectFromName(Entity $entity): string
    {
        $name = trim((string) $entity->get('name'));

        if ($name === '' || $this->containsPlaceholder($name) || preg_match('/^\d{2}:\d{2}/', $name)) {
            return '';
        }

        $remainder = $name;
        $capBlock = $this->buildCapBlock($entity);

        if ($capBlock !== '' && str_starts_with($remainder, $capBlock)) {
            $remainder = trim(substr($remainder, strlen($capBlock)));

            if (str_starts_with($remainder, '-')) {
                $remainder = trim(substr($remainder, 1));
            }
        }

        $remainder = trim((string) preg_replace('/\s*\([^)]+\)\s*$/', '', $remainder));

        if ($remainder !== '' && !$this->containsPlaceholder($remainder)) {
            return $remainder;
        }

        return '';
    }

    private function parseProspectFromDescription(Entity $entity): string
    {
        $description = (string) $entity->get('description');

        if (preg_match('/Cliente:\s*(.+)/iu', $description, $matches)) {
            $name = trim($matches[1]);

            if ($name !== '') {
                return $name;
            }
        }

        $appuntamentoId = $entity->getId();

        if (!$appuntamentoId) {
            return '';
        }

        $opportunity = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where(['appuntamentoId' => $appuntamentoId])
            ->order('createdAt', 'DESC')
            ->findOne();

        if (!$opportunity) {
            return '';
        }

        return trim((string) $opportunity->get('prospectName'));
    }

    private function isDowngrade(string $before, string $after): bool
    {
        if (!$this->containsPlaceholder($after)) {
            return false;
        }

        if ($this->containsPlaceholder($before)) {
            return false;
        }

        if (preg_match('/^\d{2}:\d{2}/', $before)) {
            return false;
        }

        return trim($before) !== '';
    }

    private function containsPlaceholder(string $name): bool
    {
        return (bool) preg_match('/\(APPUNTAMENTO\s*SENZA\s*PROSPECT\)/i', $name);
    }

    private function buildCapBlock(Entity $entity): string
    {
        $capName = '';
        $capCodice = trim((string) ($entity->get('cAPCodice') ?: ''));
        $capDesc = trim((string) ($entity->get('cAPDescrizione') ?: ''));
        $postal = trim((string) ($entity->get('indirizzoPostalCode') ?: ''));

        if ($entity->get('cAPId')) {
            $cap = $this->entityManager->getEntityById('CAP', (string) $entity->get('cAPId'));

            if ($cap) {
                $capName = trim((string) ($cap->get('name') ?: ''));
                $capCodice = $capCodice ?: trim((string) ($cap->get('codiceCAP') ?: ''));
                $capDesc = $capDesc ?: trim((string) ($cap->get('description') ?: ''));
            }
        }

        if ($capName === '' && $postal !== '') {
            $capName = $postal;
        }

        if ($capName === '' && $capCodice === '' && $capDesc === '') {
            return '';
        }

        $block = $capName !== '' ? $capName : $postal;

        if ($capCodice !== '') {
            $block .= ' - ' . $capCodice;
        }

        if ($capDesc !== '') {
            $block .= ' (' . $capDesc . ')';
        }

        return trim($block);
    }
}
