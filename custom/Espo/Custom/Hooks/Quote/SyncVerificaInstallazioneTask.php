<?php
// ========================================
// FIX ID: CRM-HOOKS-2026-001
// VERSIONE: 1.0.0
// DATA: 2026-08-13
// ENTITÀ: Quote
//
// DESCRIZIONE:
// Hook afterSave auto-contenuto (crea/aggiorna To-Do verifica installazione).
// Registrato in metadata/hooks/Quote.json (afterSave, order 25).
// FIX: corretto guard da '&&' a '||' (prima il guard non funzionava).
// ========================================

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuoteInstallazioneVerificaTaskSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Crea/aggiorna To-Do promemoria quando cambia dataInstallazione.
 *
 * @implements AfterSave<Entity>
 */
class SyncVerificaInstallazioneTask implements AfterSave
{
    public static int $order = 25;

    public function __construct(
        private QuoteInstallazioneVerificaTaskSync $sync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->get('skipHooks')) {
            return;
        }

        $installChanged = $entity->isAttributeChanged('dataInstallazione');
        $needsTask = $this->normalizeHasInstallDate($entity)
            && !$entity->get('verificaInstallazioneTaskId');

        $stateChanged = $entity->isAttributeChanged('status') || $entity->isAttributeChanged('statoContratto');
        if (!$installChanged && !$needsTask && !$stateChanged) {
            return;
        }

        $this->sync->syncFromQuote($entity);
    }

    private function normalizeHasInstallDate(Entity $entity): bool
    {
        $value = $entity->get('dataInstallazione');

        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        return is_string($value) && trim($value) !== '';
    }
}
