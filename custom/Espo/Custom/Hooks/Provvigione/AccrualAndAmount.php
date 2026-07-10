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
        $this->ensureDisplayName($entity);

        if ($options->get('skipHooks') || $options->get('skipRules')) {
            $this->applyAccrualDates($entity);
            $this->applyScostamento($entity);

            return;
        }

        $this->applyAccrualDates($entity);
        $this->applyScostamento($entity);
    }

    private function ensureDisplayName(Entity $entity): void
    {
        $name = trim((string) ($entity->get('name') ?? ''));
        $needsRebuild = $name === '' || str_starts_with($name, 'PROVV-');

        if (!$needsRebuild) {
            return;
        }

        $quote = $this->resolveQuoteForProvvigione($entity);

        if (!$quote) {
            if ($name === '') {
                $entity->set('name', 'PROVV-' . ($entity->get('tipo') ?: 'Provvigione Base'));
            }

            return;
        }

        $this->backfillQuoteLinks($entity, $quote);

        $tipo = (string) ($entity->get('tipo') ?: 'Provvigione Base');
        $importo = $entity->get('importoConsolidato') ?? $entity->get('importo');
        $codice = $this->resolveQuoteCodice($quote);
        $cliente = strtoupper(trim((string) (
            $quote->get('accountName')
            ?? $entity->get('clienteName')
            ?? 'Cliente'
        )));

        if ($importo !== null && $importo !== '' && (float) $importo !== 0.0) {
            $entity->set(
                'name',
                sprintf(
                    '%s - %s - %s - €. %s',
                    $codice,
                    $cliente,
                    strtoupper($tipo),
                    number_format((float) $importo, 2, '.', '')
                )
            );

            return;
        }

        $entity->set('name', $codice . ' - ' . strtoupper($tipo));
    }

    private function resolveQuoteForProvvigione(Entity $entity): ?Entity
    {
        if ($entity->get('contrattoId')) {
            return $this->entityManager->getEntityById('Quote', $entity->get('contrattoId'));
        }

        if (!$entity->get('opportunitaId')) {
            return null;
        }

        return $this->entityManager
            ->getRDBRepository('Quote')
            ->where(['opportunityId' => $entity->get('opportunitaId')])
            ->order('createdAt', 'DESC')
            ->findOne();
    }

    private function backfillQuoteLinks(Entity $entity, Entity $quote): void
    {
        if (!$entity->get('contrattoId')) {
            $entity->set([
                'contrattoId' => $quote->getId(),
                'contrattoName' => $quote->get('name'),
            ]);
        }

        if (!$entity->get('clienteId') && $quote->get('accountId')) {
            $entity->set([
                'clienteId' => $quote->get('accountId'),
                'clienteName' => $quote->get('accountName'),
            ]);
        }
    }

    private function resolveQuoteCodice(Entity $quote): string
    {
        foreach (['numberA', 'number', 'numeroContratto'] as $field) {
            $value = trim((string) ($quote->get($field) ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return (string) $quote->getId();
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