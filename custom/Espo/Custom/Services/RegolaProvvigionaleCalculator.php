<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Selezione regole provvigionali e calcolo importi.
 */
class RegolaProvvigionaleCalculator
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @param array<string, mixed> $context
     * @return Entity[]
     */
    public function findMatchingRules(array $context): array
    {
        $builder = $this->entityManager
            ->getRDBRepository('RegolaProvvigionale')
            ->where([
                'attiva' => true,
            ])
            ->order('priorita', 'DESC');

        $collection = $builder->find();

        $matched = [];

        foreach ($collection as $rule) {
            if ($this->ruleMatches($rule, $context)) {
                $matched[] = $rule;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function calculateRule(Entity $rule, array $context): ?float
    {
        $tipo = $rule->get('tipoCalcolo');

        $imponibile = $this->floatOrNull($context['imponibile'] ?? null);
        $canone = $this->floatOrNull($context['canoneMensile'] ?? null);
        $plusvalenza = $this->floatOrNull($context['plusvalenza'] ?? null);
        $pods = isset($context['numeroPod']) ? (int) $context['numeroPod'] : null;
        $margine = $this->floatOrNull($context['marginePercentuale'] ?? null);

        return match ($tipo) {
            'PercentualeImponibile' => $this->percentOf(
                $imponibile,
                (float) $rule->get('percentuale')
            ),
            'PercentualeImponibileAddizionale' => $this->sum(
                $this->percentOf($imponibile, (float) $rule->get('percentuale')),
                $this->percentOf($imponibile, (float) $rule->get('percentualeAddizionale'))
            ),
            'CoefficienteCanone' => $canone !== null
                ? round($canone * (float) $rule->get('coefficiente'), 2)
                : null,
            'GettoneFisso' => $this->floatOrNull($rule->get('gettoneImporto')),
            'ImportoFissoPod' => $pods !== null && $pods > 0
                ? round((float) $rule->get('importoFissoPod') * $pods, 2)
                : null,
            'PercentualePlusvalenza' => $this->percentOf(
                $plusvalenza,
                (float) $rule->get('percentuale')
            ),
            'PercentualeMargine' => $this->percentOf(
                $imponibile,
                (float) $rule->get('percentuale')
            ),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array{importo: float, regola: Entity}|null
     */
    public function calculateBest(array $context): ?array
    {
        $rules = $this->findMatchingRules($context);

        foreach ($rules as $rule) {
            $importo = $this->calculateRule($rule, $context);

            if ($importo !== null && $importo > 0) {
                return [
                    'importo' => $importo,
                    'regola' => $rule,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function ruleMatches(Entity $rule, array $context): bool
    {
        if ($rule->get('regimeProvvigione') && $rule->get('regimeProvvigione') !== ($context['regime'] ?? '')) {
            return false;
        }

        if ($rule->get('fornitorePartnerId') && $rule->get('fornitorePartnerId') !== ($context['fornitorePartnerId'] ?? null)) {
            return false;
        }

        if ($rule->get('productBrandId') && $rule->get('productBrandId') !== ($context['productBrandId'] ?? null)) {
            return false;
        }

        if ($rule->get('productCategoryId') && $rule->get('productCategoryId') !== ($context['productCategoryId'] ?? null)) {
            return false;
        }

        if ($rule->get('gruppoProvvigione') && $rule->get('gruppoProvvigione') !== ($context['gruppoProvvigione'] ?? '')) {
            return false;
        }

        $inflow = $this->floatOrNull($context['inflowTotale'] ?? $context['imponibile'] ?? null);

        if ($rule->get('inflowMin') !== null && $rule->get('inflowMin') !== '' && $inflow !== null) {
            if ($inflow < (float) $rule->get('inflowMin')) {
                return false;
            }
        }

        if ($rule->get('inflowMax') !== null && $rule->get('inflowMax') !== '' && $inflow !== null) {
            if ($inflow > (float) $rule->get('inflowMax')) {
                return false;
            }
        }

        $pods = isset($context['numeroPod']) ? (int) $context['numeroPod'] : null;

        if ($rule->get('podMin') !== null && $rule->get('podMin') !== '' && $pods !== null) {
            if ($pods < (int) $rule->get('podMin')) {
                return false;
            }
        }

        if ($rule->get('podMax') !== null && $rule->get('podMax') !== '' && $pods !== null) {
            if ($pods > (int) $rule->get('podMax')) {
                return false;
            }
        }

        $margine = $this->floatOrNull($context['marginePercentuale'] ?? null);

        if ($margine !== null && $rule->get('margineMin') !== null && $rule->get('margineMin') !== '') {
            if ($margine < (float) $rule->get('margineMin')) {
                return false;
            }
        }

        if ($margine !== null && $rule->get('margineMax') !== null && $rule->get('margineMax') !== '') {
            if ($margine > (float) $rule->get('margineMax')) {
                return false;
            }
        }

        return true;
    }

    private function percentOf(?float $base, float $percent): ?float
    {
        if ($base === null || $percent <= 0) {
            return null;
        }

        return round($base * $percent / 100, 2);
    }

    private function sum(?float $a, ?float $b): ?float
    {
        if ($a === null && $b === null) {
            return null;
        }

        return round((float) $a + (float) $b, 2);
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
