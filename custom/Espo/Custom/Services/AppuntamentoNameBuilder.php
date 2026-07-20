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

        if (str_contains($name, '(APPUNTAMENTO SENZA PROSPECT)') && $entity->get('prospectName')) {
            return true;
        }

        $expected = $this->build($entity);

        return $expected !== '' && $expected !== $name;
    }

    public function build(Entity $entity): string
    {
        $capBlock = $this->buildCapBlock($entity);

        $prospectBlock = trim((string) (
            $entity->get('prospectName')
            ?: $entity->get('parentName')
            ?: ''
        ));

        if ($prospectBlock === '') {
            $prospectBlock = '(APPUNTAMENTO SENZA PROSPECT)';
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

        if ($name === '' || $name === $before) {
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
