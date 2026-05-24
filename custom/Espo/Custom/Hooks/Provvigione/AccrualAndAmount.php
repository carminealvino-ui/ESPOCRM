<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\ProvvigioneAccrual;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Date attivazione/installazione, liquidazione prevista, scostamenti e calcolo legacy.
 */
class AccrualAndAmount implements BeforeSave
{
    public static int $order = 9;

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneAccrual $accrual
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->applyAccrualDates($entity);
        $this->applyScostamento($entity);
        $this->applyLegacyQuoteCalculation($entity);
    }

    private function applyAccrualDates(Entity $entity): void
    {
        $regime = $entity->get('regimeProvvigione') ?: 'GENERICO';

        if (
            !$regime
            && $entity->get('productCategoryId')
        ) {
            $category = $this->entityManager->getEntityById(
                'ProductCategory',
                $entity->get('productCategoryId')
            );

            if ($category) {
                $regime = $this->accrual->resolveRegimeFromCategory($category);
                $entity->set('regimeProvvigione', $regime);
            }
        }

        $eventDate = $this->accrual->resolveEventDate(
            $entity->get('dataAttivazione'),
            $entity->get('dataInstallazione')
        );

        if ($eventDate && !$entity->get('dataCompetenza')) {
            $entity->set(
                'dataCompetenza',
                $this->accrual->resolveCompetenceMonthStart($eventDate)
            );
        }

        if ($eventDate) {
            $entity->set(
                'dataLiquidazionePrevista',
                $this->accrual->calculateLiquidationDate(
                    $regime,
                    $entity->get('dataAttivazione'),
                    $entity->get('dataInstallazione')
                )
            );

            $entity->set(
                'giorniLiquidazioneDaAttivazione',
                $this->accrual->getLiquidationDays($regime)
            );
        }
    }

    private function applyScostamento(Entity $entity): void
    {
        $previsto = $entity->get('importoPrevisto');
        $consolidato = $entity->get('importoConsolidato') ?? $entity->get('importo');

        if ($previsto !== null && $previsto !== '' && $consolidato !== null && $consolidato !== '') {
            $entity->set(
                'scostamentoImporto',
                (float) $consolidato - (float) $previsto
            );
        }
    }

    private function applyLegacyQuoteCalculation(Entity $entity): void
    {
        if (!$entity->get('contrattoId')) {
            return;
        }

        if ($entity->get('statoProvvigione') === 'Prevista') {
            return;
        }

        $quote = $this->entityManager
            ->getRDBRepository('Quote')
            ->where(['id' => $entity->get('contrattoId')])
            ->findOne();

        if (!$quote) {
            return;
        }

        $tipo = $entity->get('tipo');
        $tasso = (float) $entity->get('tassoProvvigioni');

        $base = 0.0;

        if ($tipo === 'Provvigione Base') {
            $base = (float) $quote->get('amount');
        } elseif ($tipo === 'Plus Provvigionale' || $tipo === 'Minus Provvigionale') {
            $base = (float) $quote->get('minusPlus');
        } elseif ($tipo === 'Bonus (Sabato-Domenica)') {
            $date = $quote->get('dateQuoted');

            if ($date) {
                $day = date('N', strtotime($date));

                if ($day >= 6) {
                    $base = (float) $quote->get('amount');
                }
            }
        } elseif ($tipo && strpos($tipo, 'Gara') !== false) {
            $amount = (float) $quote->get('amount');

            if (strpos($tipo, '2.5') !== false && $amount > 2500) {
                $base = $amount;
            }

            if (strpos($tipo, '3.5') !== false && $amount > 3500) {
                $base = $amount;
            }

            if (strpos($tipo, '5') !== false && $amount > 5000) {
                $base = $amount;
            }
        }

        if ($base > 0 && $tasso > 0) {
            $importo = ($base * $tasso) / 100;
            $entity->set('importo', $importo);
            $entity->set('importoConsolidato', $importo);
        }
    }
}
