<?php

namespace Espo\Custom\Tools\CrmKpi;

class KpiContext
{
    public function __construct(
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?string $productBrandId = null,
    ) {}

    /**
     * Filtro periodo appuntamenti: dataAppuntamento se valorizzata, altrimenti dateStart
     * (allineato a calendario e report che usano dateStart).
     *
     * @return array<string, mixed>
     */
    public function appuntamentoWhere(): array
    {
        return array_merge(
            $this->appuntamentoDateWhere(),
            $this->brandWhere('productBrandId')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function appuntamentoDateWhere(): array
    {
        if ($this->from === null && $this->to === null) {
            return [];
        }

        $dataAppuntamentoClause = $this->dateWhere('dataAppuntamento');

        $dateStartClause = [];

        if ($this->from !== null) {
            $dateStartClause['dateStart>='] = $this->from . ' 00:00:00';
        }

        if ($this->to !== null) {
            $dateStartClause['dateStart<='] = $this->to . ' 23:59:59';
        }

        return [
            'OR' => [
                $dataAppuntamentoClause,
                [
                    'AND' => [
                        [
                            'OR' => [
                                ['dataAppuntamento' => null],
                                ['dataAppuntamento' => ''],
                            ],
                        ],
                        $dateStartClause,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteWhere(string $dateField = 'dateQuoted'): array
    {
        return array_merge(
            $this->dateWhere($dateField),
            $this->brandWhere('productBrandId')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function dateWhere(string $field): array
    {
        $where = [];

        if ($this->from !== null) {
            $where[$field . '>='] = $this->from;
        }

        if ($this->to !== null) {
            $where[$field . '<='] = $this->to;
        }

        return $where;
    }

    /**
     * @return array<string, mixed>
     */
    private function brandWhere(string $field): array
    {
        if (!$this->productBrandId) {
            return [];
        }

        return [$field => $this->productBrandId];
    }
}
