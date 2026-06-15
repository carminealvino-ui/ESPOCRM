<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Seleziona il Price Book (listino) in vigore per data e brand opportunità.
 */
class OpportunityPriceBookResolver
{
    private const IT_MONTHS = [
        1 => 'Gennaio',
        2 => 'Febbraio',
        3 => 'Marzo',
        4 => 'Aprile',
        5 => 'Maggio',
        6 => 'Giugno',
        7 => 'Luglio',
        8 => 'Agosto',
        9 => 'Settembre',
        10 => 'Ottobre',
        11 => 'Novembre',
        12 => 'Dicembre',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function resolveForOpportunity(Entity $opportunity): ?Entity
    {
        if (!$opportunity->hasAttribute('priceBookId')) {
            return null;
        }

        if (!$this->entityManager->getMetadata()->has('PriceBook')) {
            return null;
        }

        $refDate = $this->resolveReferenceDate($opportunity);

        if (!$refDate) {
            return null;
        }

        $brandKey = $this->resolveBrandKey($opportunity);

        if (!$brandKey) {
            return null;
        }

        $brandId = $opportunity->get('productBrandId');

        return $this->findPriceBook($brandKey, $brandId, $refDate);
    }

    private function resolveReferenceDate(Entity $opportunity): ?string
    {
        foreach (['dataOpportunit', 'closeDate', 'createdAt'] as $field) {
            if (!$opportunity->hasAttribute($field)) {
                continue;
            }

            $value = $opportunity->get($field);

            if (!$value) {
                continue;
            }

            return substr((string) $value, 0, 10);
        }

        return date('Y-m-d');
    }

    private function resolveBrandKey(Entity $opportunity): ?string
    {
        $name = $opportunity->get('productBrandName') ?: $opportunity->get('azienda');

        if (!$name) {
            return null;
        }

        $key = strtoupper(trim((string) $name));

        return $key !== '' ? $key : null;
    }

    private function findPriceBook(string $brandKey, ?string $brandId, string $refDate): ?Entity
    {
        $where = $this->buildBrandWhere($brandKey, $brandId);
        $where['status'] = 'Active';

        $collection = $this->entityManager
            ->getRDBRepository('PriceBook')
            ->where($where)
            ->order('name', 'ASC')
            ->find();

        $best = null;
        $bestScore = -1;

        foreach ($collection as $priceBook) {
            if (!$this->isEffectiveOnDate($priceBook, $refDate)) {
                continue;
            }

            $score = $this->scoreCandidate($priceBook, $refDate, $brandKey);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $priceBook;
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBrandWhere(string $brandKey, ?string $brandId): array
    {
        $defs = $this->entityManager->getDefs()->getEntity('PriceBook');

        if ($brandId && $defs->hasAttribute('productBrandId')) {
            return [
                'productBrandId' => $brandId,
            ];
        }

        return [
            'name*' => $brandKey,
        ];
    }

    private function isEffectiveOnDate(Entity $priceBook, string $refDate): bool
    {
        $defs = $this->entityManager->getDefs()->getEntity('PriceBook');

        if ($defs->hasAttribute('dateStart') || $defs->hasAttribute('dateEnd')) {
            $start = $defs->hasAttribute('dateStart')
                ? $this->dateOnly($priceBook->get('dateStart'))
                : null;

            $end = $defs->hasAttribute('dateEnd')
                ? $this->dateOnly($priceBook->get('dateEnd'))
                : null;

            if ($start && $start > $refDate) {
                return false;
            }

            if ($end && $end < $refDate) {
                return false;
            }

            return true;
        }

        foreach (['dataInVigore', 'dataInizioVigore', 'validFrom'] as $field) {
            if (!$defs->hasAttribute($field)) {
                continue;
            }

            $start = $this->dateOnly($priceBook->get($field));

            if ($start && $start > $refDate) {
                return false;
            }
        }

        foreach (['dataFineVigore', 'validTo'] as $field) {
            if (!$defs->hasAttribute($field)) {
                continue;
            }

            $end = $this->dateOnly($priceBook->get($field));

            if ($end && $end < $refDate) {
                return false;
            }
        }

        return $this->nameMatchesReferenceMonth($priceBook->get('name'), $refDate);
    }

    private function scoreCandidate(Entity $priceBook, string $refDate, string $brandKey): int
    {
        $score = 0;
        $name = strtoupper((string) $priceBook->get('name'));

        if (str_starts_with($name, $brandKey)) {
            $score += 10;
        }

        if ($this->nameMatchesReferenceMonth($priceBook->get('name'), $refDate)) {
            $score += 100;
        }

        $defs = $this->entityManager->getDefs()->getEntity('PriceBook');

        if ($defs->hasAttribute('dateStart')) {
            $start = $this->dateOnly($priceBook->get('dateStart'));

            if ($start && $start <= $refDate) {
                $score += (int) str_replace('-', '', $start);
            }
        }

        return $score;
    }

    private function nameMatchesReferenceMonth(?string $name, string $refDate): bool
    {
        if (!$name) {
            return false;
        }

        $month = (int) date('n', strtotime($refDate));
        $year = date('Y', strtotime($refDate));
        $label = (self::IT_MONTHS[$month] ?? '') . ' ' . $year;

        if ($label === ' ' || $label === '') {
            return false;
        }

        return stripos($name, $label) !== false;
    }

    private function dateOnly(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
