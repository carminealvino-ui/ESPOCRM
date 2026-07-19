<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Solo Numero Contratto (numeroContratto), non il campo Espo `number`.
 *
 * - assente → status Bozza
 * - presente → Bozza/Draft diventa In Gestione / Presented
 */
class SetPresentedWhenNumeroContratto implements BeforeSave
{
    public static int $order = 12;

    /** @var string[] */
    private const TERMINAL_STATUSES = [
        'Installato',
        'Recesso',
        'Invalido',
        'Canceled',
        'Finanziamento Rifiutato',
    ];

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if ($this->hasNumeroContratto($entity)) {
            $this->promoteFromBozza($entity);

            return;
        }

        $this->forceBozzaWhenMissingNumero($entity);
    }

    private function promoteFromBozza(Entity $entity): void
    {
        $status = $entity->get('status');
        $nextStatus = match ($status) {
            'Bozza' => 'In Gestione',
            'Draft' => 'Presented',
            default => null,
        };

        if ($nextStatus === null) {
            return;
        }

        $entity->set('status', $nextStatus);
    }

    private function forceBozzaWhenMissingNumero(Entity $entity): void
    {
        $status = (string) $entity->get('status');

        if ($status === 'Bozza' || $status === 'Draft') {
            return;
        }

        if (in_array($status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $entity->set('status', 'Bozza');
    }

    private function hasNumeroContratto(Entity $entity): bool
    {
        $value = $entity->get('numeroContratto');

        return $value !== null && trim((string) $value) !== '';
    }
}
