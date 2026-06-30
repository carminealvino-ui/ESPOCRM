<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class NormalizeAutoPendingFields implements BeforeSave
{
    private const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    private const TIPOLOGIA_RICHIAMO = 'Richiamo su Opportunità Generata';
    private const LEGACY_TIPOLOGIA = 'Contatto dopo Prima Visita';
    private const TESTO_STANDARD =
        'Salve, sono Carmine Alvino di ARIEL ENERGIA, mi fa sapere entro la giornata di oggi '
        . 'poi cosa ha deciso rispetto alla proposta che le ho fatto, Grazie';
    private const AUTO_PENDING_DESCRIPTION_PREFIX = 'Richiamo automatico per appuntamento Pending del';

    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        if (!$this->isAutoPendingCall($entity)) {
            return;
        }

        $this->normalizeMisplacedFields($entity);
        $this->applyDefaults($entity);
    }

    private function isAutoPendingCall(Entity $entity): bool
    {
        $nota = trim((string) $entity->get('nota'));
        $tipologia = trim((string) $entity->get('tipologia'));
        $name = strtoupper(trim((string) $entity->get('name')));

        return str_contains($nota, self::NOTA_PREFIX)
            || $tipologia === self::LEGACY_TIPOLOGIA
            || str_contains($name, 'CONTATTO DOPO PRIMA VISITA');
    }

    private function normalizeMisplacedFields(Entity $entity): void
    {
        $description = trim((string) $entity->get('description'));

        if ($description === '') {
            return;
        }

        if (str_starts_with($description, self::AUTO_PENDING_DESCRIPTION_PREFIX)) {
            $nota = trim((string) $entity->get('nota'));

            if ($nota === '' || !str_contains($nota, $description)) {
                $entity->set('nota', $nota === '' ? $description : $nota . "\n" . $description);
            }

            $entity->set('description', '');

            return;
        }

        if ($description === self::TESTO_STANDARD) {
            if (!trim((string) $entity->get('testo'))) {
                $entity->set('testo', self::TESTO_STANDARD);
            }

            $entity->set('description', '');
        }
    }

    private function applyDefaults(Entity $entity): void
    {
        $tipologia = trim((string) $entity->get('tipologia'));

        if ($tipologia === '' || $tipologia === self::LEGACY_TIPOLOGIA) {
            $entity->set('tipologia', self::TIPOLOGIA_RICHIAMO);
        }

        if (!trim((string) $entity->get('testo'))) {
            $entity->set([
                'testo' => self::TESTO_STANDARD,
                'whatsApp' => true,
                'vocale' => false,
            ]);
        }
    }
}
