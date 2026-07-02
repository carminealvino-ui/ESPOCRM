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
        ?float $imponibileOverride = null,
        ?Entity $opportunity = null
    ): array {
        $imponibile = $imponibileOverride;

        if ($imponibile === null && $source) {
            $imponibile = $this->resolveImponibile($source);
        }

        if ($imponibile === null && $opportunity) {
            $imponibile = $this->resolveImponibile($opportunity);
        }

        $prezzoCodice = $this->resolvePrezzoCodice($source, $opportunity);
        $prezzoListino = $this->resolvePrezzoListino($source, $opportunity);

        $plusvalenza = null;

        if ($imponibile !== null && $prezzoCodice !== null && $imponibile > $prezzoCodice) {
            $plusvalenza = $imponibile - $prezzoCodice;
        }

        $margine = $this->resolveMarginePercentuale($source, $opportunity, $imponibile, $prezzoListino);

        $regime = $this->accrual->resolveRegimeFromCommercial(
            $category,
            $source?->get('fornitorePartnerName') ?? $opportunity?->get('fornitorePartnerName'),
            $source?->get('productBrandName') ?? $opportunity?->get('productBrandName')
        );

        return [
            'regime' => $regime,
            'fornitorePartnerId' => $source?->get('fornitorePartnerId') ?? $opportunity?->get('fornitorePartnerId'),
            'productBrandId' => $source?->get('productBrandId') ?? $opportunity?->get('productBrandId'),
            'productCategoryId' => $category?->getId() ?? $source?->get('productCategoryId') ?? $opportunity?->get('productCategoryId'),
            'gruppoProvvigione' => $category?->get('gruppoProvvigione'),
            'imponibile' => $imponibile,
            'canoneMensile' => $this->floatField($source, 'canoneMensile') ?? $imponibile,
            'inflowTotale' => $imponibile,
            'plusvalenza' => $plusvalenza,
            'marginePercentuale' => $margine,
            'numeroPod' => $source?->get('numeroPod') ?? $opportunity?->get('numeroPod'),
            'contattoPersonaleArquati' => (bool) ($source?->get('contattoPersonaleArquati')
                ?? $opportunity?->get('contattoPersonaleArquati')),
            'integrazionePncPercentuale' => $this->floatField($source, 'integrazionePncPercentuale')
                ?? $this->floatField($opportunity, 'integrazionePncPercentuale'),
            'ordineIncompletoAriel' => (bool) ($source?->get('ordineIncompletoAriel')
                ?? $opportunity?->get('ordineIncompletoAriel')),
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
        $category = $this->resolveProductCategory($quote, $opportunity);

        $imponibile = $this->floatField($quote, 'amount')
            ?? $this->floatField($quote, 'importoContratto')
            ?? $this->floatField($opportunity, 'amount')
            ?? $this->floatField($opportunity, 'importoOpportunit');

        $context = $this->buildContextFromEntities($category, $quote, $imponibile, $opportunity);
        $context['imponibile'] = $imponibile;

        if ($context['regime'] === 'ARIEL_2026') {
            return $this->createConsolidataAriel2026($opportunity, $quote, $category, $context, $imponibile);
        }

        if (!$category) {
            return null;
        }

        $context['plusvalenza'] = $this->floatField($quote, 'minusPlus') ?? $context['plusvalenza'];

        if ($context['marginePercentuale'] !== null && !$quote->get('margineSuListino')) {
            $quote->set('margineSuListino', $context['marginePercentuale']);
            $this->entityManager->saveEntity($quote, [
                'skipHooks' => true,
                'silent' => true,
            ]);
        }

        $result = $this->calculator->calculateBest($context);

        $provvigione = $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Provvigione Base',
            null
        );

        $this->syncIntegrazioneContattiPersonali($quote, $opportunity, $category, $context, $imponibile);

        return $provvigione;
    }

    /**
     * GDL / Ariel 2026: 10+5% su imponibile (o 10% se ordine incompleto) + 35% su plusvalenza.
     *
     * @param array<string, mixed> $context
     */
    private function createConsolidataAriel2026(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context,
        ?float $imponibile
    ): ?Entity {
        if ($imponibile === null || $imponibile <= 0) {
            return null;
        }

        $minusPlus = $this->resolveMinusPlusValue($quote, $opportunity, $imponibile);

        $quote->set([
            'minusPlus' => $minusPlus,
            'prezzoCodiceIvaEsclusa' => $quote->get('prezzoCodiceIvaEsclusa')
                ?: $opportunity->get('prezzoCodiceIvaEsclusa'),
            'prezzoListinoIvaEsclusa' => $quote->get('prezzoListinoIvaEsclusa')
                ?: $opportunity->get('prezzoListinoIvaEsclusa'),
        ]);

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
        ]);

        $context['plusvalenza'] = ($minusPlus !== null && $minusPlus > 0) ? $minusPlus : null;

        $ruleId = !empty($context['ordineIncompletoAriel']) ? 'arielBase10' : 'arielBase105';
        $baseRule = $this->entityManager->getEntityById('RegolaProvvigionale', $ruleId);
        $baseResult = null;

        if ($baseRule && $baseRule->get('attiva')) {
            $importo = $this->calculator->calculateRule($baseRule, $context);

            if ($importo !== null && $importo > 0) {
                $baseResult = ['importo' => $importo, 'regola' => $baseRule];
            }
        }

        $base = $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $baseResult,
            $context,
            'Provvigione Base',
            'ARIEL-BASE-' . ($quote->get('number') ?? $quote->getId())
        );

        if ($context['plusvalenza'] !== null && $context['plusvalenza'] > 0) {
            $plusRule = $this->entityManager->getEntityById('RegolaProvvigionale', 'arielPlus35');

            if ($plusRule && $plusRule->get('attiva')) {
                $plusImporto = $this->calculator->calculateRule($plusRule, $context);

                if ($plusImporto !== null && $plusImporto > 0) {
                    $this->saveConsolidataProvvigione(
                        $opportunity,
                        $quote,
                        $category,
                        ['importo' => $plusImporto, 'regola' => $plusRule],
                        $context,
                        'Plus Provvigionale',
                        'ARIEL-PLUS35-' . ($quote->get('number') ?? $quote->getId())
                    );
                }
            }
        }

        return $base;
    }

    /**
     * @param array{importo: float, regola: Entity}|null $result
     * @param array<string, mixed> $context
     */
    private function saveConsolidataProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        ?array $result,
        array $context,
        string $tipo,
        ?string $nameSuffix
    ): ?Entity {
        $provvigione = $this->findProvvigione(
            'Consolidata',
            contrattoId: $quote->getId(),
            tipo: $tipo
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
            'tipo' => $tipo,
            'contrattoId' => $quote->getId(),
            'contrattoName' => $quote->get('name'),
            'opportunitaId' => $opportunity->getId(),
            'opportunitaName' => $opportunity->get('name'),
            'clienteId' => $quote->get('accountId'),
            'clienteName' => $quote->get('accountName'),
        ]);

        if ($nameSuffix) {
            $provvigione->set('name', $nameSuffix);
        }

        if ($tipo === 'Provvigione Base') {
            $prevista = $this->findProvvigione('Prevista', opportunitaId: $opportunity->getId())
                ?? $this->findProvvigione('Prevista', appuntamentoId: $opportunity->get('appuntamentoId'));

            if ($prevista) {
                $provvigione->set('importoPrevisto', $prevista->get('importoPrevisto') ?? $prevista->get('importo'));
            }
        }

        $this->entityManager->saveEntity($provvigione, ['silent' => true]);

        return $provvigione;
    }

    private function resolveProductCategory(Entity $quote, Entity $opportunity): ?Entity
    {
        $categoryId = $quote->get('productCategoryId') ?: $opportunity->get('productCategoryId');

        if (!$categoryId) {
            return null;
        }

        return $this->entityManager->getEntityById('ProductCategory', $categoryId);
    }

    /**
     * @param array<string, mixed>|null $context
     * @param array{importo: float, regola: Entity}|null $result
     */
    private function applyProvvigioneFromCalculation(
        Entity $provvigione,
        Entity $parent,
        ?Entity $category,
        ?array $result,
        string $stato,
        ?array $context,
        Entity $dateSource
    ): void {
        $regime = $context['regime'] ?? $this->accrual->resolveRegimeFromCommercial(
            $category,
            $parent->get('fornitorePartnerName'),
            $parent->get('productBrandName')
        );
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
            'productCategoryId' => $category?->getId(),
            'productCategoryName' => $category?->get('name') ?? $parent->get('productCategoryName'),
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
        ?string $contrattoId = null,
        ?string $tipo = null
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

        if ($tipo) {
            $where['tipo'] = $tipo;
        }

        return $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where($where)
            ->findOne();
    }

    /**
     * Integrazione +5% contatti personali (ARQUATI PNC).
     *
     * @param array<string, mixed> $context
     */
    private function syncIntegrazioneContattiPersonali(
        Entity $quote,
        Entity $opportunity,
        Entity $category,
        array $context,
        ?float $imponibile
    ): void {
        if (($context['regime'] ?? '') !== 'ARQUATI_PNC') {
            return;
        }

        if (!$context['contattoPersonaleArquati'] || $imponibile === null || $imponibile <= 0) {
            return;
        }

        $rule = $this->entityManager->getEntityById('RegolaProvvigionale', 'arqCpP5');

        if (!$rule || !$rule->get('attiva')) {
            return;
        }

        $importo = round($imponibile * 5 / 100, 2);

        $plus = $this->findProvvigione(
            'Consolidata',
            contrattoId: $quote->getId(),
            tipo: 'Plus Provvigionale'
        ) ?? $this->entityManager->createEntity('Provvigione');

        $this->applyProvvigioneFromCalculation(
            $plus,
            $opportunity,
            $category,
            ['importo' => $importo, 'regola' => $rule],
            'Consolidata',
            $context,
            $quote
        );

        $plus->set([
            'tipo' => 'Plus Provvigionale',
            'contrattoId' => $quote->getId(),
            'contrattoName' => $quote->get('name'),
            'opportunitaId' => $opportunity->getId(),
            'opportunitaName' => $opportunity->get('name'),
            'clienteId' => $quote->get('accountId'),
            'clienteName' => $quote->get('accountName'),
            'name' => 'PLUS-CP5-' . ($quote->get('number') ?? $quote->getId()),
        ]);

        $this->entityManager->saveEntity($plus, ['silent' => true]);
    }

    private function resolvePrezzoListino(?Entity $source, ?Entity $opportunity): ?float
    {
        return $this->floatField($source, 'prezzoListinoIvaEsclusa')
            ?? $this->floatField($opportunity, 'prezzoListinoIvaEsclusa');
    }

    private function resolvePrezzoCodice(?Entity $source, ?Entity $opportunity): ?float
    {
        return $this->floatField($source, 'prezzoCodiceIvaEsclusa')
            ?? $this->floatField($source, 'totalPrezzoCodice')
            ?? $this->floatField($source, 'prezzoCodice')
            ?? $this->floatField($opportunity, 'prezzoCodiceIvaEsclusa');
    }

    private function resolveMarginePercentuale(
        ?Entity $source,
        ?Entity $opportunity,
        ?float $imponibile,
        ?float $prezzoListino
    ): ?float {
        $stored = $this->floatField($source, 'margineSuListino')
            ?? $this->floatField($opportunity, 'suPrezzoCodice');

        if ($stored !== null) {
            return $stored;
        }

        if ($imponibile === null || $prezzoListino === null || $prezzoListino <= 0) {
            return null;
        }

        return round((($imponibile - $prezzoListino) / $prezzoListino) * 100, 2);
    }

    private function resolveMinusPlusValue(
        Entity $quote,
        Entity $opportunity,
        ?float $imponibile
    ): ?float {
        $stored = $this->floatField($quote, 'minusPlus') ?? $this->floatField($opportunity, 'minusPlus');

        if ($stored !== null) {
            return round($stored, 2);
        }

        $prezzoCodice = $this->resolvePrezzoCodice($quote, $opportunity);

        if ($imponibile === null || $prezzoCodice === null) {
            return null;
        }

        return round($imponibile - $prezzoCodice, 2);
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
