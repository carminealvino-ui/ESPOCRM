<?php

namespace Espo\Custom\Hooks\WorkingTimeCalendar;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\WorkingTimeCalendarDisponibilitaGenerator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Alla creazione di una Disponibilità Ricorrente, genera le disponibilità giornaliere
 * se il pannello «Generazione Disponibilità» è compilato.
 */
class AutoGeneraDisponibilita implements BeforeSave, AfterSave
{
    /** @var array<int, true> */
    private array $pending = [];

    public function __construct(
        private EntityManager $entityManager,
        private Log $log
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('skipAutoGeneraDisponibilita')) {
            return;
        }

        if (!$entity->isNew() || !$this->canAutoGenerate($entity)) {
            return;
        }

        $this->pending[spl_object_id($entity)] = true;
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('skipAutoGeneraDisponibilita')) {
            return;
        }

        $key = spl_object_id($entity);

        if (!($this->pending[$key] ?? false)) {
            return;
        }

        unset($this->pending[$key]);

        try {
            $generator = new WorkingTimeCalendarDisponibilitaGenerator($this->entityManager);
            $generator->generateFromCalendar($entity);
        } catch (\Throwable $e) {
            $this->log->warning(
                'Auto-genera disponibilità da calendario {id} fallita: {message}',
                [
                    'id' => $entity->getId(),
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    private function canAutoGenerate(Entity $entity): bool
    {
        if (!$entity->get('dataInizioGenerazione') || !$entity->get('dataFineGenerazione')) {
            return false;
        }

        $area = $entity->get('generazioneArea') ?? [];

        if (!is_array($area)) {
            $area = $area !== null && $area !== '' ? [$area] : [];
        }

        if ($area === []) {
            return false;
        }

        $generator = new WorkingTimeCalendarDisponibilitaGenerator($this->entityManager);

        return $generator->resolveAssignedUserIds($entity) !== [];
    }
}
