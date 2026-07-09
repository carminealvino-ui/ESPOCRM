<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\ProvvigioneAccrual;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Date attivazione/installazione, liquidazione prevista e scostamenti.
 * Il calcolo importi è delegato a ProvvigioneManager (regole provvigionali).
 */
class AccrualAndAmount implements BeforeSave
{
    public static int $order = 1;

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneAccrual $accrual
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->ensurePlaceholderName($entity);

        if ($options->get('skipHooks') || $options->get('skipRules')) {
            $this->applyAccrualDates($entity);
            $this->applyScostamento($entity);

            return;
        }

        $this->applyAccrualDates($entity);
        $this->applyScostamento($entity);
    }

    private function ensurePlaceholderName(Entity $entity): void
    {
        if ($entity->get('name')) {
            return;
        }

        $tipo = $entity->get('tipo') ?: 'Provvigione Base';

        if ($entity->get('contrattoId')) {
            $quote = $this->entityManager->getEntityById('Quote', $entity->get('contrattoId'));

            if ($quote) {
                $ref = $quote->get('number') ?: $quote->getId();
                $entity->set('name', $ref . ' — ' . $tipo);

                return;
            }
        }

        $entity->set('name', 'PROVV-' . $tipo);
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
}
