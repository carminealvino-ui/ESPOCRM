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
     * @return array<string, mixed>
     */
    public function appuntamentoWhere(string $dateField = 'dataAppuntamento'): array
    {
        return array_merge(
            $this->dateWhere($dateField),
            $this->brandWhere('productBrandId')
        );
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
