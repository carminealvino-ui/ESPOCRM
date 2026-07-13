<?php

namespace Espo\Custom\Actions\WorkingTimeCalendar;

use Espo\Custom\Actions\Disponibilita\GeneraDisponibilitaRicorrenti;
use Espo\ORM\EntityManager;

/**
 * Generazione da dettaglio calendario (pulsante «Genera Disponibilità»).
 * Delega a GeneraDisponibilitaRicorrenti con calendarId = id.
 */
class GeneraDisponibilita
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function run(object $data): object
    {
        $id = $data->id ?? null;

        if (!$id) {
            throw new \Exception('ID mancante');
        }

        $payload = (object) array_merge((array) $data, [
            'calendarId' => $id,
        ]);

        $action = new GeneraDisponibilitaRicorrenti($this->entityManager);

        return $action->run($payload);
    }
}
