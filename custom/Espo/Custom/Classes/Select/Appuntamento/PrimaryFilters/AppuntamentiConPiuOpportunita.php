<?php

namespace Espo\Custom\Classes\Select\Appuntamento\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\SelectBuilder;

/**
 * Stessa logica di CrmKpi\Alerts::countAppuntamentiConPiuOpportunita.
 */
class AppuntamentiConPiuOpportunita implements Filter
{
    /** @var string[] */
    private const ESITI_ANNULLATI = [
        'Annullato dal Potenziale',
        'Annullato dal Consulente',
        'Annullato Azienda',
        'Annullato Call Center',
        'Appuntamento non in agenda',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function apply(SelectBuilder $queryBuilder): void
    {
        $ids = $this->resolveAppuntamentoIds();

        if ($ids === []) {
            $queryBuilder->where(['id' => null]);

            return;
        }

        $queryBuilder->where(['id' => $ids]);
    }

    /**
     * @return string[]
     */
    private function resolveAppuntamentoIds(): array
    {
        $heldIds = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id', 'esito', 'sottostato'])
            ->where(['status' => 'Held'])
            ->find();

        foreach ($collection as $appuntamento) {
            if ($this->isAppuntamentoNotAnnullato($appuntamento)) {
                $heldIds[] = $appuntamento->getId();
            }
        }

        if ($heldIds === []) {
            return [];
        }

        $counts = [];

        foreach (array_chunk($heldIds, 500) as $chunk) {
            $opportunities = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->select(['appuntamentoId'])
                ->where(['appuntamentoId' => $chunk])
                ->find();

            foreach ($opportunities as $opportunity) {
                $appuntamentoId = $opportunity->get('appuntamentoId');

                if (!$appuntamentoId) {
                    continue;
                }

                $counts[$appuntamentoId] = ($counts[$appuntamentoId] ?? 0) + 1;
            }
        }

        $ids = [];

        foreach ($counts as $appuntamentoId => $count) {
            if ($count > 1) {
                $ids[] = $appuntamentoId;
            }
        }

        return $ids;
    }

    private function isAppuntamentoNotAnnullato(Entity $appuntamento): bool
    {
        if ($appuntamento->get('sottostato') === 'Annullato') {
            return false;
        }

        $esito = $appuntamento->get('esito');

        return !($esito && in_array($esito, self::ESITI_ANNULLATI, true));
    }
}
