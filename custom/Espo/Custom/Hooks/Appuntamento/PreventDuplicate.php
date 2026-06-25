<?php

// ========================================
// VERSIONE: 1.0.2
// DATA: 2026-06-05
// AUTORE: CARMINE ALVINO + CHATGPT
// ----------------------------------------
// FIX 1.0.2:
// ✔ Duplicato = stesso slot (dateStart + dateEnd) E stesso cliente
//   (prospect, oppure parent, oppure indirizzo)
// ✔ Prospect/indirizzo diversi → stesso orario consentito
//
// ROLLBACK:
// backup_dev/Appuntamento/hooks/preventduplicate-1.0.1-data-only-stabile.php
// ========================================

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Exceptions\Error;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<Entity>
 * @implements AfterSave<Entity>
 */
class PreventDuplicate implements BeforeSave, AfterSave
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('isImport')) {
            return;
        }

        $start = $entity->get('dateStart');
        $end = $entity->get('dateEnd');

        if (!$start || !$end) {
            return;
        }

        $identityKey = $this->buildIdentityKey($entity);

        if ($identityKey === null) {
            return;
        }

        $query = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where([
                'dateStart' => $start,
                'dateEnd' => $end,
            ]);

        if (!$entity->isNew()) {
            $query->where(['id!=' => $entity->getId()]);
        }

        foreach ($query->find() as $existing) {
            if ($this->buildIdentityKey($existing) === $identityKey) {
                throw new Error('Appuntamento duplicato bloccato');
            }
        }
    }

    /**
     * Chiave cliente per il controllo duplicati (prospect > parent > indirizzo).
     */
    private function buildIdentityKey(Entity $entity): ?string
    {
        $prospectId = $entity->get('prospectId');

        if ($prospectId) {
            return 'prospect:' . $prospectId;
        }

        $parentType = $entity->get('parentType');
        $parentId = $entity->get('parentId');

        if ($parentType && $parentId) {
            return 'parent:' . $parentType . ':' . $parentId;
        }

        $indirizzo = mb_strtolower(trim((string) $entity->get('indirizzo')));

        if ($indirizzo !== '') {
            return 'indirizzo:' . $indirizzo;
        }

        return null;
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $operation = $entity->isNew() ? 'CREATE' : 'UPDATE';
        $log = date('Y-m-d H:i:s') .
            ' | Appuntamento | ' . $operation .
            ' | ID: ' . $entity->getId() .
            ' | START: ' . $entity->get('dateStart') .
            ' | END: ' . $entity->get('dateEnd') . PHP_EOL;

        $logPath = 'data/logs/custom.log';

        if (is_writable(dirname($logPath)) || is_file($logPath)) {
            file_put_contents($logPath, $log, FILE_APPEND);
        }
    }
}
