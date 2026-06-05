<?php

// ========================================
// VERSIONE: 1.0.1
// DATA: 2026-05-06
// AUTORE: CARMINE ALVINO + CHATGPT
// ----------------------------------------
// BASE: 1.0.0 STABILE
//
// ----------------------------------------
// ✅ CODICE STABILE:
//
// ✔ Blocco duplicati per dataStart + dateEnd
// ✔ Compatibile create/update
// ✔ Protezione loop import/sync
//
// ----------------------------------------
// 🔧 FIX 1.0.1:
//
// ✔ Aggiunto sistema LOG base
// ✔ Tracciamento CREATE / UPDATE
// ✔ Scrittura su data/logs/custom.log
//
// ----------------------------------------
// 🎯 OBIETTIVO:
//
// Monitorare comportamento reale del sistema
// per debug sync (Google Calendar)
//
// ========================================

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\ORM\Entity;
use Espo\Core\ORM\EntityManager;

class PreventDuplicate
{
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    // ========================================
    // ===== CODICE STABILE (NON TOCCARE) =====
    // ========================================
    public function beforeSave(Entity $entity, array $options)
    {
        // evita loop sync/import
        if (!empty($options['isImport'])) {
            return;
        }

        $start = $entity->get('dateStart');
        $end   = $entity->get('dateEnd');

        if (!$start || !$end) {
            return;
        }

        $repo = $this->entityManager->getRepository('Appuntamento');

        $qb = $repo->where([
            'dateStart' => $start,
            'dateEnd'   => $end,
        ]);

        if (!$entity->isNew()) {
            $qb->where(['id!=' => $entity->getId()]);
        }

        $existing = $qb->findOne();

        if ($existing) {
            throw new \Espo\Core\Exceptions\Error('Appuntamento duplicato bloccato');
        }
    }

    // ========================================
    // ===== FIX 1.0.1 - LOG BASE ============
    // ========================================
    public function afterSave(Entity $entity, array $options)
    {
        // Determina tipo operazione
        $operation = $entity->isNew() ? 'CREATE' : 'UPDATE';

        // Dati principali
        $id    = $entity->getId();
        $start = $entity->get('dateStart');
        $end   = $entity->get('dateEnd');

        // Log formattato
        $log = date('Y-m-d H:i:s') .
            " | Appuntamento | " . $operation .
            " | ID: " . $id .
            " | START: " . $start .
            " | END: " . $end . PHP_EOL;

        // Scrittura log
        file_put_contents('data/logs/custom.log', $log, FILE_APPEND);
    }
}

