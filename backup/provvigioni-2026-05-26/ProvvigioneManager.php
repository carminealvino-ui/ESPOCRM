<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Creazione e aggiornamento provvigioni (prevista / consolidata) tramite regole.
 */
class ProvvigioneManager
{
    public function __construct(
        private EntityManager $entityManager,
        private RegolaProvvigionaleCalculator $calculator,
        private ProvvigioneAccrual $accrual
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildContextFromEntities(
        ?Entity $category,
        ?Entity $source,
        ?float $imponibileOverride = null
    ): array {
        $regime = $this->accrual->resolveRegimeFromCategory($category);

        $imponibile = $imponibileOverride;

        if ($imponibile === null && $source) {
            $imponibile = $this->resolveImponibile($source);
        }

        $prezzoCodice = $source ? $this->floatField($source, 'prezzoCodiceIvaEsclusa')
            ?? $this->floatField($source, 'totalPrezzoCodice')
            ?? $this->floatField($source, 'prezzoCodice') : null;

        $prezzoListino = $source ? $this->floatField($source, 'prezzoListinoIvaEsclusa') : null;

        $plusvalenza = null;

        if ($imponibile !== null && $prezzoCodice !== null && $imponibile > $prezzoCodice) {
            $plusvalenza = $imponibile - $prezzoCodice;
        }

        $margine = null;

        if ($imponibile !== null && $prezzoListino !== null && $prezzoListino > 0) {
            $margine = (($imponibile - $prezzoListino) / $prezzoListino) * 100;
        }

        return [
            'regime' => $regime,
            'fornitorePartnerId' => $source?->get('fornitorePartnerId'),
            'productBrandId' => $source?->get('productBrandId'),
            'productCategoryId' => $category?->getId() ?? $source?->get('productCategoryId'),
            'gruppoProvvigione' => $category?->get('gruppoProvvigione'),
            'imponibile' => $imponibile,
            'canoneMensile' => $this->floatField($source, 'canoneMensile') ?? $imponibile,
            'inflowTotale' => $imponibile,
            'plusvalenza' => $plusvalenza,
            'marginePercentuale' => $margine,
            'numeroPod' => $source?->get('numeroPod'),
        ];
    }

    public function syncPrevistaFromAppuntamento(Entity $appuntamento): ?Entity
    {
        if (!$appuntamento->get('productCategoryId')) {
            return null;
        }

        $category = $this->entityManager->getEntityById(
            'ProductCategory',
            $appuntamento->get('productCategoryId')
        );

        if (!$category) {
            return null;
        }

        $imponibile = $this->floatField($appuntamento, 'importoImponibilePrevisto')
            ?? $this->floatField($appuntamento, 'importoTrattativa');

        $context = $this->buildContextFromEntities($category, $appuntamento, $imponibile);
        $result = $this->calculator->calculateBest($context);

        $provvigione = $this->findProvvigione(
            'Prevista',
            appuntamentoId: $appuntamento->getId()
        ) ?? $this->entityManager->createEntity('Provvigione');

        $this->applyProvvigioneFromCalculation(
            $provvigione,
            $appuntamento,
            $category,
            $result,
            'Prevista',
            $context,
            $appuntamento
        );

        $this->entityManager->saveEntity($provvigione, ['silent' => true]);

        return $provvigione;
    }

    public function createConsolidataForQuote(Entity $opportunity, Entity $quote): ?Entity
    {
        $category = null;

        if ($quote->get('productCategoryId')) {
            $category = $this->entityManager->getEntityById(
                'ProductCategory',
                $quote->get('productCategoryId')
            );
        } elseif ($opportunity->get('productCategoryId')) {
            $category = $this->entityManager->getEntityById(
                'ProductCategory',
                $opportunity->get('productCategoryId')
            );
        }

        if (!$category) {
            return null;
        }

        $imponibile = $this->floatField($quote, 'amount')
            ?? $this->floatField($quote, 'importoContratto')
            ?? $this->floatField($opportunity, 'amount')
            ?? $this->floatField($opportunity, 'importoOpportunita');

        $context = $this->buildContextFromEntities($category, $quote, $imponibile);
        $context['imponibile'] = $imponibile;
        $context['plusvalenza'] = $this->floatField($quote, 'minusPlus') ?? $context['plusvalenza'];

        $result = $this->calculator->calculateBest($context);

        $provvigione = $this->findProvvigione(
            'Consolidata',
            contrattoId: $quote->getId()
        ) ?? $this->entityManager->createEntity('Provvigione');

        $this->applyProvvigioneFromCalculation(
            $provvigione,
            $opportunity,
            $category,
            $result,
            'Consolidata',
            $context,
            $quote
        );

        $provvigione->set([
            'contrattoId' => $quote->getId(),
            'contrattoName' => $quote->get('name'),
            'opportunitaId' => $opportunity->getId(),
            'opportunitaName' => $opportunity->get('name'),
            'clienteId' => $quote->get('accountId'),
            'clienteName' => $quote->get('accountName'),
        ]);

        $prevista = $this->findProvvigione('Prevista', opportunitaId: $opportunity->getId())
            ?? $this->findProvvigione('Prevista', appuntamentoId: $opportunity->get('appuntamentoId'));

        if ($prevista) {
            $provvigione->set('importoPrevisto', $prevista->get('importoPrevisto') ?? $prevista->get('importo'));
        }

        $this->entityManager->saveEntity($provvigione, ['silent' => true]);

        return $provvigione;
    }

    /**
     * @param array<string, mixed>|null $context
     * @param array{importo: float, regola: Entity}|null $result
     */
    private function applyProvvigioneFromCalculation(
        Entity $provvigione,
        Entity $parent,
        Entity $category,
        ?array $result,
        string $stato,
        ?array $context,
        Entity $dateSource
    ): void {
        $regime = $context['regime'] ?? $this->accrual->resolveRegimeFromCategory($category);
        $importo = $result['importo'] ?? null;
        $rule = $result['regola'] ?? null;

        $dataAttivazione = $dateSource->get('dataAttivazione');
        $dataInstallazione = $dateSource->get('dataInstallazione') ?? $dateSource->get('installazione');
        $eventDate = $this->accrual->resolveEventDate($dataAttivazione, $dataInstallazione);

        $giorni = $rule?->get('giorniLiquidazione') ?? $this->accrual->getLiquidationDays($regime);

        $provvigione->set([
            'name' => ($stato === 'Prevista' ? 'PREV-' : 'CONS-') . ($parent->get('name') ?? $parent->getId()),
            'statoProvvigione' => $stato,
            'regimeProvvigione' => $regime,
            'tipo' => $rule?->get('tipoProvvigioneRecord') ?? 'Provvigione Base',
            'productCategoryId' => $category->getId(),
            'productCategoryName' => $category->get('name'),
            'fornitorePartnerId' => $parent->get('fornitorePartnerId'),
            'fornitorePartnerName' => $parent->get('fornitorePartnerName'),
            'productBrandId' => $parent->get('productBrandId'),
            'productBrandName' => $parent->get('productBrandName'),
            'dataInstallazione' => $dataInstallazione,
            'dataAttivazione' => $dataAttivazione,
            'dataCompetenza' => $this->accrual->resolveCompetenceMonthStart($eventDate),
            'giorniLiquidazioneDaAttivazione' => $giorni,
            'assignedUserId' => $parent->get('assignedUserId'),
            'assignedUserName' => $parent->get('assignedUserName'),
        ]);

        if ($rule) {
            $provvigione->set([
                'regolaProvvigionaleId' => $rule->getId(),
                'regolaProvvigionaleName' => $rule->get('name'),
                'tassoProvvigioni' => $rule->get('percentuale') ?? $rule->get('coefficiente'),
            ]);
        }

        if ($giorni > 0 && $eventDate) {
            $provvigione->set(
                'dataLiquidazionePrevista',
                $this->accrual->calculateLiquidationDate($regime, $dataAttivazione, $dataInstallazione)
                    ?? (new \DateTimeImmutable($eventDate))->modify('last day of this month')
                        ->modify('+' . $giorni . ' days')->format('Y-m-d')
            );
        }

        if ($stato === 'Prevista') {
            $provvigione->set([
                'importoPrevisto' => $importo,
                'importo' => $importo,
                'importoConsolidato' => null,
            ]);
        } else {
            $provvigione->set([
                'importoConsolidato' => $importo,
                'importo' => $importo,
            ]);
        }
    }

    private function findProvvigione(
        string $stato,
        ?string $appuntamentoId = null,
        ?string $opportunitaId = null,
        ?string $contrattoId = null
    ): ?Entity {
        $where = ['statoProvvigione' => $stato];

        if ($appuntamentoId) {
            $where['appuntamentoId'] = $appuntamentoId;
        }

        if ($opportunitaId) {
            $where['opportunitaId'] = $opportunitaId;
        }

        if ($contrattoId) {
            $where['contrattoId'] = $contrattoId;
        }

        return $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where($where)
            ->findOne();
    }

    private function resolveImponibile(Entity $entity): ?float
    {
        return $this->floatField($entity, 'amount')
            ?? $this->floatField($entity, 'importoContratto')
            ?? $this->floatField($entity, 'importoOpportunita')
            ?? $this->floatField($entity, 'importoImponibilePrevisto')
            ?? $this->floatField($entity, 'importoTrattativa');
    }

    private function floatField(Entity $entity, string $field): ?float
    {
        $value = $entity->get($field);

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
