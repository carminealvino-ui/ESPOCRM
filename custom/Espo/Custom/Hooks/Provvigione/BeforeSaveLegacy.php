<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Calcolo legacy importo da tasso × base (compatibile Espo 10 — non usa Hooks\Base).
 *
 * @implements BeforeSave<Entity>
 */
class BeforeSaveLegacy implements BeforeSave
{
    public static int $order = 5;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        if (!$entity->get('contrattoId')) {
            return;
        }

        $quote = $this->entityManager
            ->getRDBRepository('Quote')
            ->where(['id' => $entity->get('contrattoId')])
            ->findOne();

        if (!$quote) {
            return;
        }

        $tipo = (string) ($entity->get('tipo') ?? '');
        $tasso = (float) $entity->get('tassoProvvigioni');

        $base = 0.0;

        if ($tipo === 'Provvigione Base') {
            $base = (float) $quote->get('amount');
        } elseif ($tipo === 'Plus Provvigionale' || $tipo === 'Minus Provvigionale') {
            $base = (float) $quote->get('minusPlus');
        } elseif ($tipo === 'Bonus (Sabato-Domenica)') {
            $date = $quote->get('dateQuoted');

            if ($date) {
                $day = (int) date('N', strtotime((string) $date));

                if ($day >= 6) {
                    $base = (float) $quote->get('amount');
                }
            }
        } elseif ($tipo === 'Bonus Taxi') {
            $base = (float) $quote->get('amount');
        } elseif (str_contains($tipo, 'Gara')) {
            $amount = (float) $quote->get('amount');

            if (str_contains($tipo, '2.5') && $amount > 2500) {
                $base = $amount;
            }

            if (str_contains($tipo, '3.5') && $amount > 3500) {
                $base = $amount;
            }

            if (str_contains($tipo, '5') && $amount > 5000) {
                $base = $amount;
            }
        }

        $importo = 0.0;

        if ($base > 0 && $tasso > 0) {
            $importo = ($base * $tasso) / 100;
        }

        $entity->set('importo', $importo);
    }
}
