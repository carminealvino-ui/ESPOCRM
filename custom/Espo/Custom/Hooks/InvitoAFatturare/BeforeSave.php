<?php

namespace Espo\Custom\Hooks\InvitoAFatturare;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Totali amministrativi invito a fatturare. Lo stato provvigione è guidato dallo stato contratto.
 */
class BeforeSave implements BeforeSave
{
    public static int $order = 9;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->isNew() && $entity->get('meseCompetenza') && !$entity->get('dataScadenzaFatturazione')) {
            $mese = date('Y-m-01', strtotime((string) $entity->get('meseCompetenza')));
            $entity->set('dataScadenzaFatturazione', date('Y-m-15', strtotime($mese)));
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('stato')) {
            $this->recalculateTotals($entity);

            return;
        }

        $this->recalculateTotals($entity);

        $stato = $entity->get('stato');

        if ($stato === 'Emesso' && !$entity->get('dataInvito')) {
            $entity->set('dataInvito', date('Y-m-d'));
        }

        if ($stato === 'Annullato') {
            $this->unlinkProvvigioni($entity);
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

        $totale = 0.0;

        foreach ($collection as $provvigione) {
            $totale += (float) ($provvigione->get('importoConsolidato')
                ?? $provvigione->get('importo')
                ?? 0);
        }

        $entity->set('importoTotaleConsolidato', $totale);
        $entity->set('importoTotalePrevisto', $totale);
        $entity->set('scostamentoTotale', 0.0);
    }

    private function unlinkProvvigioni(Entity $entity): void
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
            $provvigione->set([
                'invitoAFatturareId' => null,
                'invitoAFatturareName' => null,
            ]);

            $this->entityManager->saveEntity($provvigione, ['silent' => true]);
        }
    }
}
