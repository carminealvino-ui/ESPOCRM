<?php

namespace Espo\Custom\Hooks\InvitoAFatturare;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Totali amministrativi e allineamento stato provvigioni incluse.
 */
class BeforeSave implements BeforeSave
{
    public static int $order = 9;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew() && !$entity->isAttributeChanged('stato')) {
            $this->recalculateTotals($entity);

            return;
        }

        $this->recalculateTotals($entity);

        $stato = $entity->get('stato');

        if ($stato === 'Emesso' && !$entity->get('dataInvito')) {
            $entity->set('dataInvito', date('Y-m-d'));
        }

        if ($stato === 'Emesso') {
            $this->markLinkedProvvigioniInInvito($entity);
        }

        if ($stato === 'Fatturato') {
            $this->markLinkedProvvigioniFatturata($entity);
        }
    }

    private function recalculateTotals(Entity $entity): void
    {
        if (!$entity->getId()) {
            return;
        }

        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where([
                'invitoAFatturareId' => $entity->getId(),
            ])
            ->find();

        $consolidato = 0.0;
        $previsto = 0.0;

        foreach ($collection as $provvigione) {
            $consolidato += (float) ($provvigione->get('importoConsolidato')
                ?? $provvigione->get('importo')
                ?? 0);
            $previsto += (float) ($provvigione->get('importoPrevisto') ?? 0);
        }

        $entity->set('importoTotaleConsolidato', $consolidato);
        $entity->set('importoTotalePrevisto', $previsto);
        $entity->set('scostamentoTotale', $consolidato - $previsto);
    }

    private function markLinkedProvvigioniInInvito(Entity $entity): void
    {
        if (!$entity->getId()) {
            return;
        }

        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where([
                'invitoAFatturareId' => $entity->getId(),
                'statoProvvigione' => 'Consolidata',
            ])
            ->find();

        foreach ($collection as $provvigione) {
            $provvigione->set('statoProvvigione', 'InInvito');
            $this->entityManager->saveEntity($provvigione, ['silent' => true]);
        }
    }

    private function markLinkedProvvigioniFatturata(Entity $entity): void
    {
        if (!$entity->getId()) {
            return;
        }

        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where([
                'invitoAFatturareId' => $entity->getId(),
            ])
            ->find();

        foreach ($collection as $provvigione) {
            $provvigione->set('statoProvvigione', 'Fatturata');
            $provvigione->set(
                'dataLiquidazioneEffettiva',
                $provvigione->get('dataLiquidazioneEffettiva') ?: date('Y-m-d')
            );
            $this->entityManager->saveEntity($provvigione, ['silent' => true]);
        }
    }
}
