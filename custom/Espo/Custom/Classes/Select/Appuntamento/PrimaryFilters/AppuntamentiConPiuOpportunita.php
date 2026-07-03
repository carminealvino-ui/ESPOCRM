<?php

namespace Espo\Custom\Classes\Select\Appuntamento\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\SelectBuilder;

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
            ->where([
                'status' => 'Held',
                'sottostato!=' => 'Annullato',
            ])
            ->find();

        foreach ($collection as $appuntamento) {
            $esito = $appuntamento->get('esito');

            if ($esito && in_array($esito, self::ESITI_ANNULLATI, true)) {
                continue;
            }

            $heldIds[] = $appuntamento->getId();
        }

        if ($heldIds === []) {
            return [];
        }

        $counts = [];

        $opportunities = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['appuntamentoId'])
            ->where(['appuntamentoId' => $heldIds])
            ->find();

        foreach ($opportunities as $opportunity) {
            $appuntamentoId = $opportunity->get('appuntamentoId');

            if (!$appuntamentoId) {
                continue;
            }

            $counts[$appuntamentoId] = ($counts[$appuntamentoId] ?? 0) + 1;
        }

        $ids = [];

        foreach ($counts as $appuntamentoId => $count) {
            if ($count > 1) {
                $ids[] = $appuntamentoId;
            }
        }

        return $ids;
    }
}
