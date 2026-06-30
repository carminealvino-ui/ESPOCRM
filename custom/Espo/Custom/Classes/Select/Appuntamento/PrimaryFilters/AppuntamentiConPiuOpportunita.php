<?php

namespace Espo\Custom\Classes\Select\Appuntamento\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Custom\Tools\CrmKpi\MonthRange;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\SelectBuilder;

class AppuntamentiConPiuOpportunita implements Filter
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function apply(SelectBuilder $queryBuilder): void
    {
        [$from, $to] = MonthRange::bounds('currentMonth');
        $ids = $this->resolveAppuntamentoIds($from, $to);

        if ($ids === []) {
            $queryBuilder->where(['id' => null]);

            return;
        }

        $queryBuilder->where(['id' => $ids]);
    }

    /**
     * @return string[]
     */
    private function resolveAppuntamentoIds(?string $from, ?string $to): array
    {
        $where = ['status' => 'Held'];

        if ($from !== null) {
            $where['dataAppuntamento>='] = $from;
        }

        if ($to !== null) {
            $where['dataAppuntamento<='] = $to;
        }

        $heldIds = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where($where)
            ->find();

        foreach ($collection as $appuntamento) {
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
