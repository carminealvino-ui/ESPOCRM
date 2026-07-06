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
        private ProvvigioneAccrual $accrual,
        private QuotePricingCalculator $quotePricingCalculator
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

        $referenceDate = $this->resolveCommercialReferenceDate($source, $opportunity);

        $regime = $this->accrual->resolveRegimeFromCommercial(
            $category,
            $source?->get('fornitorePartnerName') ?? $opportunity?->get('fornitorePartnerName'),
            $source?->get('productBrandName') ?? $opportunity?->get('productBrandName'),
            $referenceDate
        );

        $ecoWindEasy = $this->isArielEcoWindEasy($category, $source, $opportunity);

        $gruppoProvvigione = $category?->get('gruppoProvvigione');

        if ($regime === 'ARIEL_LEGACY') {
            $gruppoProvvigione = $this->resolveArielLegacyGruppo($category, $ecoWindEasy);
        }

        $plusvalenza = null;

        if ($regime === 'ARIEL_LEGACY') {
            if (
                !$ecoWindEasy
                && $imponibile !== null
                && $prezzoListino !== null
                && $imponibile > $prezzoListino
            ) {
                $plusvalenza = round($imponibile - $prezzoListino, 2);
            }
        } elseif ($imponibile !== null && $prezzoCodice !== null && $imponibile > $prezzoCodice) {
            $plusvalenza = $imponibile - $prezzoCodice;
        }

        $margine = $this->resolveMarginePercentuale(
            $source,
            $opportunity,
            $imponibile,
            $prezzoListino,
            $prezzoCodice,
            $regime
        );

        return [
            'regime' => $regime,
            'fornitorePartnerId' => $source?->get('fornitorePartnerId') ?? $opportunity?->get('fornitorePartnerId'),
            'productBrandId' => $source?->get('productBrandId') ?? $opportunity?->get('productBrandId'),
            'productCategoryId' => $category?->getId() ?? $source?->get('productCategoryId') ?? $opportunity?->get('productCategoryId'),
            'gruppoProvvigione' => $gruppoProvvigione,
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
            'arielEcoWindEasy' => $ecoWindEasy,
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

        $this->saveProvvigione($provvigione);

        return $provvigione;
    }

    /**
     * Applica le regole provvigionali a un record creato/modificato dal subpanel contratto.
     */
    public function syncProvvigioneFromContratto(Entity $provvigione): bool
    {
        if (!$provvigione->get('contrattoId')) {
            return false;
        }

        if ($provvigione->get('statoProvvigione') === 'Prevista') {
            return false;
        }

        $quote = $this->entityManager->getEntityById('Quote', $provvigione->get('contrattoId'));

        if (!$quote) {
            return false;
        }

        $opportunity = $this->resolveOpportunityForQuote($quote, $provvigione);
        $category = $this->resolveProductCategory($quote, $opportunity);
        $parent = $opportunity ?? $quote;

        $imponibile = $this->resolveQuoteImponibile($quote, $opportunity);

        $context = $this->buildContextFromEntities($category, $quote, $imponibile, $opportunity);
        $context['imponibile'] = $imponibile;
        $context['plusvalenza'] = $this->floatField($quote, 'minusPlus') ?? $context['plusvalenza'];

        $tipo = $provvigione->get('tipo') ?: 'Provvigione Base';
        $result = $this->resolveRuleResultForTipo($context, $tipo, $quote, $opportunity);

        $this->enrichProvvigioneFromContratto($provvigione, $quote, $opportunity, $category, $parent);

        if (!$provvigione->get('statoProvvigione')) {
            $provvigione->set('statoProvvigione', 'Consolidata');
        }

        $this->applyProvvigioneFromCalculation(
            $provvigione,
            $parent,
            $category,
            $result,
            (string) ($provvigione->get('statoProvvigione') ?: 'Consolidata'),
            $context,
            $quote
        );

        $provvigione->set([
            'tipo' => $tipo,
            'name' => $this->buildProvvigioneName($quote, $tipo, $result['regola'] ?? null),
        ]);

        if ($tipo === 'Provvigione Base' && $opportunity) {
            $prevista = $this->findProvvigione('Prevista', opportunitaId: $opportunity->getId())
                ?? $this->findProvvigione('Prevista', appuntamentoId: $opportunity->get('appuntamentoId'));

            if ($prevista) {
                $provvigione->set(
                    'importoPrevisto',
                    $prevista->get('importoPrevisto') ?? $prevista->get('importo')
                );
            }
        }

        if ($tipo === 'Provvigione Base' && $opportunity) {
            if (($context['regime'] ?? '') === 'ARIEL_2026') {
                $this->ensureArielPlusProvvigione($opportunity, $quote, $category, $context);
                $this->ensureArielMinusProvvigione($opportunity, $quote, $category, $context);
            }

            if (($context['regime'] ?? '') === 'ARIEL_LEGACY') {
                $this->ensureArielLegacyPlusProvvigione($opportunity, $quote, $category, $context);
            }
        }

        return $result !== null;
    }

    /**
     * Ricalcola tutte le provvigioni consolidate del contratto tramite regole.
     *
     * @return array{created: int, updated: int}
     */
    public function recalculateAllForQuote(Entity $quote): array
    {
        $opportunity = $this->resolveOpportunityForQuote($quote, null);

        if (!$opportunity) {
            return ['created' => 0, 'updated' => 0, 'purged' => 0];
        }

        $purged = $this->purgeProvvigioniForQuote($quote->getId());
        $this->syncQuotePricingFields($quote, $opportunity);

        $this->createConsolidataForQuote($opportunity, $quote);
        $this->refreshQuoteTotaleProvvigioni($quote);

        $count = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quote->getId()])
            ->count();

        return [
            'created' => $count,
            'updated' => 0,
            'purged' => $purged,
        ];
    }

    public function resolveTotaleProvvigioniForQuoteId(string $quoteId): ?float
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $totale = 0.0;

        foreach ($collection as $provvigione) {
            if ($provvigione->get('statoProvvigione') === 'Stornata') {
                continue;
            }

            if ($provvigione->get('statoProvvigione') === 'Prevista') {
                continue;
            }

            $importo = $provvigione->get('importoConsolidato');

            if ($importo === null || $importo === '') {
                continue;
            }

            $totale += (float) $importo;
        }

        return $totale > 0 ? round($totale, 2) : null;
    }

    public function refreshQuoteTotaleProvvigioni(Entity $quote): void
    {
        if (!$quote->getId()) {
            return;
        }

        $totale = $this->resolveTotaleProvvigioniForQuoteId($quote->getId());

        $quote->set('totaleProvvigioni', $totale);

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
    }

    private function purgeProvvigioniForQuote(string $quoteId): int
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $count = 0;

        foreach ($collection as $provvigione) {
            $this->entityManager->removeEntity($provvigione);
            $count++;
        }

        return $count;
    }

    private function syncQuotePricingFields(Entity $quote, Entity $opportunity): void
    {
        $this->quotePricingCalculator->syncOnBeforeSave($quote);

        $imponibile = $this->resolveQuoteImponibile($quote, $opportunity);

        if ($imponibile !== null && !$quote->get('importoContratto')) {
            $quote->set('importoContratto', $imponibile);
        }

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function saveProvvigione(Entity $provvigione, array $options = []): void
    {
        $this->entityManager->saveEntity($provvigione, array_merge([
            'silent' => true,
            'skipFormula' => true,
        ], $options));
    }

    public function createConsolidataForQuote(Entity $opportunity, Entity $quote): ?Entity
    {
        $this->syncQuotePricingFields($quote, $opportunity);

        $category = $this->resolveProductCategory($quote, $opportunity);

        $imponibile = $this->resolveQuoteImponibile($quote, $opportunity);

        $context = $this->buildContextFromEntities($category, $quote, $imponibile, $opportunity);
        $context['imponibile'] = $imponibile;

        if ($context['regime'] === 'ARIEL_2026') {
            return $this->createConsolidataAriel2026($opportunity, $quote, $category, $context, $imponibile);
        }

        if ($context['regime'] === 'ARIEL_LEGACY') {
            return $this->createConsolidataArielLegacy($opportunity, $quote, $category, $context, $imponibile);
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
        $this->ensureWeekendBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);

        $this->refreshQuoteTotaleProvvigioni($quote);

        return $provvigione;
    }

    /**
     * Ariel legacy (scalette minus fino al 23/01/2026): % su imponibile per fascia sconto su codice + plus 50% oltre listino.
     *
     * @param array<string, mixed> $context
     */
    private function createConsolidataArielLegacy(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context,
        ?float $imponibile
    ): ?Entity {
        if ($imponibile === null || $imponibile <= 0) {
            return null;
        }

        if ($context['marginePercentuale'] !== null && !$quote->get('margineSuListino')) {
            $quote->set('margineSuListino', $context['marginePercentuale']);
            $this->entityManager->saveEntity($quote, [
                'skipHooks' => true,
                'silent' => true,
            ]);
        }

        $result = $this->calculator->calculateBest($context);

        $base = $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Provvigione Base',
            null
        );

        $this->ensureArielLegacyPlusProvvigione($opportunity, $quote, $category, $context);
        $this->refreshQuoteTotaleProvvigioni($quote);

        return $base;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function ensureArielLegacyPlusProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context
    ): void {
        if (!empty($context['arielEcoWindEasy'])) {
            return;
        }

        if ($context['plusvalenza'] === null || $context['plusvalenza'] <= 0) {
            return;
        }

        $plusResult = $this->calculator->calculateForTipoRecord($context, 'Plus Provvigionale');

        if ($plusResult === null) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $plusResult,
            $context,
            'Plus Provvigionale',
            $this->buildProvvigioneName($quote, 'Plus Provvigionale', $plusResult['regola'] ?? null)
        );
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
            $this->buildProvvigioneName(
                $quote,
                'Provvigione Base',
                $baseResult['regola'] ?? $baseRule
            )
        );

        if ($context['plusvalenza'] !== null && $context['plusvalenza'] > 0) {
            $this->ensureArielPlusProvvigione($opportunity, $quote, $category, $context);
        } elseif ($minusPlus !== null && $minusPlus < 0) {
            $context['plusvalenza'] = $minusPlus;
            $this->ensureArielMinusProvvigione($opportunity, $quote, $category, $context);
        }

        $this->ensureWeekendBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);

        $this->refreshQuoteTotaleProvvigioni($quote);

        return $base;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function ensureArielPlusProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context
    ): void {
        if ($context['plusvalenza'] === null || $context['plusvalenza'] <= 0) {
            return;
        }

        $plusRule = $this->entityManager->getEntityById('RegolaProvvigionale', 'arielPlus35');

        if (!$plusRule || !$plusRule->get('attiva')) {
            return;
        }

        $plusImporto = $this->calculator->calculateRule($plusRule, $context);

        if ($plusImporto === null || $plusImporto <= 0) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            ['importo' => $plusImporto, 'regola' => $plusRule],
            $context,
            'Plus Provvigionale',
            $this->buildProvvigioneName($quote, 'Plus Provvigionale', $plusRule)
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function ensureArielMinusProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context
    ): void {
        $minusvalenza = $context['plusvalenza'] ?? null;

        if ($minusvalenza === null || $minusvalenza >= 0) {
            return;
        }

        $minusRule = $this->entityManager->getEntityById('RegolaProvvigionale', 'arielMinus35');

        if (!$minusRule || !$minusRule->get('attiva')) {
            return;
        }

        $minusImporto = $this->calculator->calculateRule($minusRule, $context);

        if ($minusImporto === null || $minusImporto >= 0) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            ['importo' => $minusImporto, 'regola' => $minusRule],
            $context,
            'Minus Provvigionale',
            $this->buildProvvigioneName($quote, 'Minus Provvigionale', $minusRule)
        );
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

        $this->saveProvvigione($provvigione);

        return $provvigione;
    }

    private function resolveProductCategory(Entity $quote, ?Entity $opportunity): ?Entity
    {
        $categoryId = $quote->get('productCategoryId') ?: $opportunity?->get('productCategoryId');

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

        $tipoRecord = $rule?->get('tipoProvvigioneRecord') ?? 'Provvigione Base';

        $provvigione->set([
            'name' => $this->buildProvvigioneName($dateSource, $tipoRecord, $rule),
            'statoProvvigione' => $stato,
            'regimeProvvigione' => $regime,
            'tipo' => $tipoRecord,
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
            $baseCalcolo = $this->resolveBaseCalcolo($rule, $context);

            $provvigione->set([
                'regolaProvvigionaleId' => $rule->getId(),
                'regolaProvvigionaleName' => $rule->get('name'),
                'tassoProvvigioni' => $this->resolveDisplayTasso($rule),
                'baseCalcolo' => $baseCalcolo['baseCalcolo'],
                'importoBaseCalcolo' => $baseCalcolo['importoBaseCalcolo'],
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

        $this->saveProvvigione($plus);
    }

    /**
     * Bonus sabato/domenica: riga aggiuntiva se data contratto o appuntamento in weekend.
     *
     * @param array<string, mixed> $context
     */
    private function ensureWeekendBonusProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context,
        ?float $imponibile
    ): void {
        if ($imponibile === null || $imponibile <= 0) {
            return;
        }

        if (!$this->isWeekendContractDate($quote, $opportunity)) {
            return;
        }

        $result = $this->resultFromRuleId('bonusWeekendSd', $context)
            ?? $this->calculator->calculateForTipoRecord($context, 'Bonus (Sabato-Domenica)');

        if ($result === null) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Bonus (Sabato-Domenica)',
            $this->buildProvvigioneName($quote, 'Bonus (Sabato-Domenica)', $result['regola'] ?? null)
        );
    }

    private function isWeekendContractDate(Entity $quote, ?Entity $opportunity): bool
    {
        $date = $this->resolveWeekendReferenceDate($quote, $opportunity);

        if ($date === null) {
            return false;
        }

        $day = (int) (new \DateTimeImmutable($date))->format('N');

        return $day >= 6;
    }

    private function resolveWeekendReferenceDate(Entity $quote, ?Entity $opportunity): ?string
    {
        $candidates = [
            $quote->get('dateQuoted'),
            $opportunity?->get('dataOpportunit'),
        ];

        if ($opportunity?->get('appuntamentoId')) {
            $appuntamento = $this->entityManager->getEntityById(
                'Appuntamento',
                $opportunity->get('appuntamentoId')
            );

            if ($appuntamento) {
                $candidates[] = $appuntamento->get('dateStart');
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeDateValue($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        if (strlen($string) >= 10) {
            $string = substr($string, 0, 10);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $string);

        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function resolveOpportunityForQuote(Entity $quote, ?Entity $provvigione): ?Entity
    {
        $opportunityId = $quote->get('opportunityId') ?: $provvigione?->get('opportunitaId');

        if (!$opportunityId) {
            return null;
        }

        return $this->entityManager->getEntityById('Opportunity', $opportunityId);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{importo: float, regola: Entity}|null
     */
    private function resolveRuleResultForTipo(
        array $context,
        string $tipo,
        Entity $quote,
        ?Entity $opportunity
    ): ?array {
        $regime = $context['regime'] ?? '';

        if ($regime === 'ARIEL_2026') {
            if ($tipo === 'Provvigione Base') {
                $ruleId = !empty($context['ordineIncompletoAriel']) ? 'arielBase10' : 'arielBase105';

                return $this->resultFromRuleId($ruleId, $context);
            }

            if ($tipo === 'Plus Provvigionale') {
                return $this->resultFromRuleId('arielPlus35', $context);
            }

            if ($tipo === 'Minus Provvigionale') {
                return $this->resultFromRuleId('arielMinus35', $context, true);
            }
        }

        if ($regime === 'ARIEL_LEGACY') {
            if ($tipo === 'Plus Provvigionale') {
                if (!empty($context['arielEcoWindEasy'])) {
                    return null;
                }

                return $this->resultFromRuleId('arlLegacyPlus50', $context);
            }

            return $this->calculator->calculateForTipoRecord($context, $tipo);
        }

        if ($regime === 'ARQUATI_PNC' && $tipo === 'Plus Provvigionale' && !empty($context['contattoPersonaleArquati'])) {
            $cpResult = $this->resultFromRuleId('arqCpP5', $context);

            if ($cpResult !== null) {
                return $cpResult;
            }
        }

        if ($tipo === 'Bonus (Sabato-Domenica)') {
            return $this->resultFromRuleId('bonusWeekendSd', $context)
                ?? $this->calculator->calculateForTipoRecord($context, $tipo);
        }

        return $this->calculator->calculateForTipoRecord($context, $tipo);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{importo: float, regola: Entity}|null
     */
    private function resultFromRuleId(string $ruleId, array $context, bool $allowNegative = false): ?array
    {
        $rule = $this->entityManager->getEntityById('RegolaProvvigionale', $ruleId);

        if (!$rule || !$rule->get('attiva')) {
            return null;
        }

        $importo = $this->calculator->calculateRule($rule, $context);

        if ($importo === null) {
            return null;
        }

        if ($allowNegative) {
            if ($importo >= 0) {
                return null;
            }
        } elseif ($importo <= 0) {
            return null;
        }

        return [
            'importo' => $importo,
            'regola' => $rule,
        ];
    }

    private function enrichProvvigioneFromContratto(
        Entity $provvigione,
        Entity $quote,
        ?Entity $opportunity,
        ?Entity $category,
        Entity $parent
    ): void {
        $provvigione->set([
            'contrattoId' => $quote->getId(),
            'contrattoName' => $quote->get('name'),
            'clienteId' => $quote->get('accountId'),
            'clienteName' => $quote->get('accountName'),
            'assignedUserId' => $provvigione->get('assignedUserId')
                ?: $quote->get('assignedUserId')
                ?: $opportunity?->get('assignedUserId'),
            'assignedUserName' => $provvigione->get('assignedUserName')
                ?: $quote->get('assignedUserName')
                ?: $opportunity?->get('assignedUserName'),
        ]);

        if ($opportunity) {
            $provvigione->set([
                'opportunitaId' => $opportunity->getId(),
                'opportunitaName' => $opportunity->get('name'),
            ]);
        }

        if ($category) {
            $provvigione->set([
                'productCategoryId' => $category->getId(),
                'productCategoryName' => $category->get('name'),
            ]);
        } elseif ($quote->get('productCategoryId')) {
            $provvigione->set([
                'productCategoryId' => $quote->get('productCategoryId'),
                'productCategoryName' => $quote->get('productCategoryName'),
            ]);
        }

        $provvigione->set([
            'fornitorePartnerId' => $parent->get('fornitorePartnerId'),
            'fornitorePartnerName' => $parent->get('fornitorePartnerName'),
            'productBrandId' => $parent->get('productBrandId'),
            'productBrandName' => $parent->get('productBrandName'),
        ]);
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
        ?float $prezzoListino,
        ?float $prezzoCodice = null,
        ?string $regime = null
    ): ?float {
        if ($regime === 'ARIEL_LEGACY') {
            if ($imponibile !== null && $prezzoCodice !== null && $prezzoCodice > 0) {
                return round((($imponibile - $prezzoCodice) / $prezzoCodice) * 100, 2);
            }

            return null;
        }

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

    private function resolveCommercialReferenceDate(?Entity $source, ?Entity $opportunity): ?string
    {
        $candidates = [
            $source?->get('dateQuoted'),
            $source?->get('dataInstallazione'),
            $source?->get('dataAttivazione'),
            $opportunity?->get('closeDate'),
            $opportunity?->get('dateStart'),
            $opportunity?->get('dataInstallazione'),
        ];

        foreach ($candidates as $date) {
            if ($date) {
                return (string) $date;
            }
        }

        return null;
    }

    private function isArielEcoWindEasy(?Entity $category, ?Entity $source, ?Entity $opportunity): bool
    {
        $labels = [
            $category?->get('name'),
            $source?->get('name'),
            $opportunity?->get('name'),
        ];

        foreach ($labels as $label) {
            if ($label && stripos((string) $label, 'ECO WIND EASY') !== false) {
                return true;
            }
        }

        return false;
    }

    private function resolveArielLegacyGruppo(?Entity $category, bool $ecoWindEasy): string
    {
        if ($ecoWindEasy) {
            return 'Ariel Eco Wind Easy';
        }

        $name = strtoupper(trim((string) $category?->get('name')));

        return match (true) {
            str_contains($name, 'CLIMAT') => 'Ariel Climatizzatori',
            str_contains($name, 'CALDA') => 'Ariel Caldaie',
            str_contains($name, 'STUF') => 'Ariel Stufe',
            default => 'Ariel Climatizzatori',
        };
    }

    private function resolveMinusPlusValue(
        Entity $quote,
        Entity $opportunity,
        ?float $imponibile
    ): ?float {
        $fromCalculator = $this->quotePricingCalculator->resolveMinusPlusForQuote($quote);

        if ($fromCalculator !== null) {
            return $fromCalculator;
        }

        $stored = $this->floatField($quote, 'minusPlus') ?? $this->floatField($opportunity, 'minusPlus');

        if ($stored !== null) {
            return round($stored, 2);
        }

        $prezzoCodice = $this->resolvePrezzoCodice($quote, $opportunity);
        $imponibileNet = $this->quotePricingCalculator->resolveImponibileNetto($quote) ?? $imponibile;

        if ($imponibileNet === null || $prezzoCodice === null) {
            return null;
        }

        return round($imponibileNet - $prezzoCodice, 2);
    }

    private function resolveQuoteImponibile(Entity $quote, ?Entity $opportunity = null): ?float
    {
        $importoContratto = $this->floatField($quote, 'importoContratto');

        if ($importoContratto !== null && $importoContratto > 0) {
            return round($importoContratto, 2);
        }

        $amount = $this->floatField($quote, 'amount');
        $tax = $this->floatField($quote, 'taxAmount');

        if ($amount !== null && $tax !== null && $tax > 0) {
            return round($amount - $tax, 2);
        }

        if ($amount !== null) {
            $aliquota = $this->floatField($quote, 'aliquotaIVA')
                ?? $this->floatField($quote, 'taxRate');

            if ($aliquota !== null && $aliquota > 0) {
                return round($amount / (1 + $aliquota / 100), 2);
            }

            return round($amount, 2);
        }

        return $opportunity ? $this->resolveImponibile($opportunity) : null;
    }

    /**
     * @param array<string, mixed>|null $context
     * @return array{baseCalcolo: string, importoBaseCalcolo: float|null}
     */
    private function resolveBaseCalcolo(?Entity $rule, ?array $context): array
    {
        $tipo = $rule?->get('tipoCalcolo') ?? 'PercentualeImponibile';

        return match ($tipo) {
            'PercentualePlusvalenza' => [
                'baseCalcolo' => isset($context['plusvalenza']) && (float) $context['plusvalenza'] < 0
                    ? 'Minusvalenza'
                    : 'Plusvalenza',
                'importoBaseCalcolo' => isset($context['plusvalenza'])
                    ? round(abs((float) $context['plusvalenza']), 2)
                    : null,
            ],
            'PercentualeMargine' => [
                'baseCalcolo' => 'MargineSuListino',
                'importoBaseCalcolo' => isset($context['imponibile'])
                    ? round((float) $context['imponibile'], 2)
                    : null,
            ],
            'CoefficienteCanone' => [
                'baseCalcolo' => 'CanoneMensile',
                'importoBaseCalcolo' => isset($context['canoneMensile'])
                    ? round((float) $context['canoneMensile'], 2)
                    : null,
            ],
            'GettoneFisso' => [
                'baseCalcolo' => 'GettoneFisso',
                'importoBaseCalcolo' => $this->floatField($rule, 'gettoneImporto'),
            ],
            'ImportoFissoPod' => [
                'baseCalcolo' => 'NumeroPod',
                'importoBaseCalcolo' => isset($context['numeroPod'])
                    ? (float) (int) $context['numeroPod']
                    : null,
            ],
            default => [
                'baseCalcolo' => 'ImponibileContratto',
                'importoBaseCalcolo' => isset($context['imponibile'])
                    ? round((float) $context['imponibile'], 2)
                    : null,
            ],
        };
    }

    private function buildProvvigioneName(Entity $quote, string $tipo, ?Entity $rule): string
    {
        $agent = (string) ($quote->get('assignedUserName') ?: '');

        if ($agent !== '') {
            return $agent . ' — ' . $tipo;
        }

        $contractRef = (string) ($quote->get('number') ?: $quote->getId());

        if ($rule && $rule->get('name')) {
            return $contractRef . ' — ' . $tipo;
        }

        return $contractRef . ' — ' . $tipo;
    }

    private function resolveDisplayTasso(?Entity $rule): ?float
    {
        if (!$rule) {
            return null;
        }

        $percent = $this->floatField($rule, 'percentuale');
        $add = $this->floatField($rule, 'percentualeAddizionale');

        if ($rule->get('tipoCalcolo') === 'PercentualeImponibileAddizionale' && $percent !== null && $add !== null) {
            return round($percent + $add, 2);
        }

        return $percent ?? $this->floatField($rule, 'coefficiente');
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
