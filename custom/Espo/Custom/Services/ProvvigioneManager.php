<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Creazione e aggiornamento provvigioni tramite regole.
 * Stato: Forecast / In pagamento / Pagato / Inesigibile (da stato contratto).
 */
class ProvvigioneManager
{
    public function __construct(
        private EntityManager $entityManager,
        private RegolaProvvigionaleCalculator $calculator,
        private ProvvigioneAccrual $accrual,
        private QuotePricingCalculator $quotePricingCalculator,
        private ProvvigioneStatusSync $statusSync
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

        $provvigione = $this->findProvvigioneByAppuntamento($appuntamento->getId())
            ?? $this->entityManager->createEntity('Provvigione');

        $this->applyProvvigioneFromCalculation(
            $provvigione,
            $appuntamento,
            $category,
            $result,
            ProvvigioneStatusSync::FORECAST,
            $context,
            $appuntamento
        );

        $this->saveProvvigioneEntity($provvigione);

        return $provvigione;
    }

    public function createConsolidataForQuote(Entity $opportunity, Entity $quote): ?Entity
    {
        $this->quotePricingCalculator->syncOnBeforeSave($quote);

        $category = $this->resolveProductCategory($quote, $opportunity);

        $imponibile = $this->resolveQuoteImponibile($quote, $opportunity);
        $minusPlus = $this->quotePricingCalculator->resolveMinusPlusForQuote($quote);

        if ($minusPlus !== null) {
            $quote->set('minusPlus', $minusPlus);
        }

        $context = $this->buildContextFromEntities($category, $quote, $imponibile, $opportunity);
        $context['imponibile'] = $imponibile;

        if ($context['regime'] === 'ARIEL_2026') {
            return $this->createConsolidataAriel2026($opportunity, $quote, $category, $context, $imponibile);
        }

        if (!$category) {
            return null;
        }

        $context['plusvalenza'] = ($minusPlus !== null && $minusPlus > 0)
            ? $minusPlus
            : (($minusPlus !== null && $minusPlus < 0) ? null : ($context['plusvalenza'] ?? null));

        if ($minusPlus !== null && $minusPlus < 0) {
            $context['minusvalenza'] = $minusPlus;
        }

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

        $this->statusSync->syncProvvigioniForQuote($quote);
        $this->refreshQuoteTotaleProvvigioni($quote);

        return $provvigione;
    }

    public function resolveTotaleProvvigioniForQuoteId(string $quoteId): ?float
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $totale = 0.0;
        $counted = 0;

        foreach ($collection as $provvigione) {
            if (!$this->statusSync->shouldCountInTotale((string) $provvigione->get('statoProvvigione'))) {
                continue;
            }

            $importo = $provvigione->get('importoConsolidato') ?? $provvigione->get('importo');

            if ($importo === null || $importo === '') {
                continue;
            }

            $totale += (float) $importo;
            $counted++;
        }

        return $counted > 0 ? round($totale, 2) : null;
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

    /**
     * Ricalcola tutte le provvigioni consolidate del contratto tramite regole.
     *
     * @return array{created: int, updated: int, purged: int}
     */
    public function recalculateAllForQuote(Entity $quote): array
    {
        $opportunity = $this->resolveOpportunityForQuote($quote, null);

        if (!$opportunity) {
            return ['created' => 0, 'updated' => 0, 'purged' => 0];
        }

        $purged = $this->purgeRecalculableProvvigioniForQuote($quote->getId());
        $this->syncQuotePricingFields($quote, $opportunity);

        $quoteId = $quote->getId();
        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return ['created' => 0, 'updated' => 0, 'purged' => $purged];
        }

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

    private function saveProvvigioneEntity(Entity $provvigione): void
    {
        $this->entityManager->saveEntity($provvigione, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
    }

    private function purgeRecalculableProvvigioniForQuote(string $quoteId): int
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $count = 0;

        foreach ($collection as $provvigione) {
            if (!$this->statusSync->canRecalculate($provvigione)) {
                continue;
            }

            $this->entityManager->removeEntity($provvigione);
            $count++;
        }

        return $count;
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

        $imponibile = $this->quotePricingCalculator->resolveImponibileNetto($quote)
            ?? $this->resolveQuoteImponibile($quote, $opportunity);
        $minusPlus = $this->quotePricingCalculator->resolveMinusPlusForQuote($quote);
        $prezzoListino = $this->floatField($quote, 'prezzoListinoIvaEsclusa')
            ?? $this->floatField($opportunity, 'prezzoListinoIvaEsclusa');

        if ($minusPlus !== null) {
            $quote->set('minusPlus', $minusPlus);
        }

        if ($imponibile !== null && $prezzoListino !== null && $prezzoListino > 0) {
            $quote->set(
                'margineSuListino',
                round((($imponibile - $prezzoListino) / $prezzoListino) * 100, 2)
            );
        }

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
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
     * GDL / Ariel 2026: provvigione base + referenza personale (additiva) + plus/minus + bonus weekend.
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

        $imponibile = $this->resolveQuoteImponibile($quote, $opportunity) ?? $imponibile;
        $context['imponibile'] = $imponibile;

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
        $baseResult = $this->resultFromRuleId($ruleId, $context);

        $base = $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $baseResult,
            $context,
            'Provvigione Base',
            null
        );

        if ($this->isReferenzaPersonaleOpportunity($opportunity)) {
            $refResult = $this->resultFromRuleId('referenzaPersonale', $context);

            if ($refResult !== null) {
                $this->saveConsolidataProvvigione(
                    $opportunity,
                    $quote,
                    $category,
                    $refResult,
                    $context,
                    'Referenza Personale',
                    null
                );
            }
        }

        if ($context['plusvalenza'] !== null && $context['plusvalenza'] > 0) {
            $this->ensureArielPlusProvvigione($opportunity, $quote, $category, $context);
        } elseif ($minusPlus !== null && $minusPlus < 0) {
            $context['plusvalenza'] = $minusPlus;
            $this->ensureArielMinusProvvigione($opportunity, $quote, $category, $context);
        }

        $this->ensureWeekendBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);

        $this->statusSync->syncProvvigioniForQuote($quote);
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

        $result = $this->resultFromRuleId('arielPlus35', $context);

        if ($result === null) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Plus Provvigionale',
            null
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

        $result = $this->resultFromRuleId('arielMinus35', $context, true);

        if ($result === null) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Minus Provvigionale',
            null
        );
    }

    /**
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
            null
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
            $this->parseDateFromContractLabel($quote->get('name')),
            $opportunity?->get('closeDate'),
            $opportunity?->get('dataOpportunit'),
            $quote->get('createdAt'),
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

    private function parseDateFromContractLabel(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $name, $m)) {
            return $m[1];
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $name, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
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

    private function isReferenzaPersonaleOpportunity(Entity $opportunity): bool
    {
        $appuntamentoId = $opportunity->get('appuntamentoId');

        if (!$appuntamentoId) {
            return false;
        }

        $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        if (!$appuntamento) {
            return false;
        }

        $tipo = $appuntamento->get('tipo');

        if (is_array($tipo)) {
            return in_array('Referenza Personale', $tipo, true);
        }

        if (is_string($tipo) && $tipo !== '') {
            return str_contains($tipo, 'Referenza Personale');
        }

        return false;
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
        ?string $nameSuffix = null
    ): ?Entity {
        $provvigione = $this->findProvvigioneByContrattoAndTipo($quote->getId(), $tipo)
            ?? $this->entityManager->createEntity('Provvigione');

        $stato = $this->statusSync->resolveStatoFromQuote($quote);

        $this->applyProvvigioneFromCalculation(
            $provvigione,
            $opportunity,
            $category,
            $result,
            $stato,
            $context,
            $quote
        );

        $clienteId = $quote->get('accountId') ?: $opportunity->get('accountId');
        $clienteName = $quote->get('accountName') ?: $opportunity->get('accountName');

        $provvigione->set([
            'tipo' => $tipo,
            'contrattoId' => $quote->getId(),
            'contrattoName' => $quote->get('name'),
            'opportunitaId' => $opportunity->getId(),
            'opportunitaName' => $opportunity->get('name'),
            'clienteId' => $clienteId,
            'clienteName' => $clienteName,
        ]);

        $importo = $provvigione->get('importoConsolidato') ?? $provvigione->get('importo');

        if ($importo !== null && $importo !== '') {
            $importoRounded = round((float) $importo, 2);
            $provvigione->set([
                'importo' => $importoRounded,
                'importoConsolidato' => $importoRounded,
                'name' => $this->buildProvvigioneDisplayName($quote, $tipo, $importoRounded),
            ]);
        }

        $this->saveProvvigioneEntity($provvigione);

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
        $tipoRecord = $rule?->get('tipoProvvigioneRecord') ?? 'Provvigione Base';

        $provvigione->set([
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
        } elseif ($context !== null) {
            $fallbackBase = $this->resolveFallbackBaseCalcolo($tipoRecord, $context);
            $provvigione->set([
                'baseCalcolo' => $fallbackBase['baseCalcolo'],
                'importoBaseCalcolo' => $fallbackBase['importoBaseCalcolo'],
            ]);
        }

        if ($giorni > 0 && $eventDate && $stato !== ProvvigioneStatusSync::FORECAST) {
            $quote = $dateSource->getEntityType() === 'Quote' ? $dateSource : null;
            $dataPagamento = $quote ? $this->statusSync->resolveDataPagamento($quote) : null;

            if ($dataPagamento !== null) {
                $provvigione->set('dataLiquidazionePrevista', $dataPagamento);
            } else {
                $provvigione->set(
                    'dataLiquidazionePrevista',
                    $this->accrual->calculateLiquidationDate($regime, $dataAttivazione, $dataInstallazione)
                        ?? (new \DateTimeImmutable($eventDate))->modify('last day of this month')
                            ->modify('+' . $giorni . ' days')->format('Y-m-d')
                );
            }
        }

        if ($stato === ProvvigioneStatusSync::FORECAST) {
            $provvigione->set([
                'importoPrevisto' => $importo,
                'importo' => $importo,
                'importoConsolidato' => null,
            ]);
        } else {
            $importoRounded = $importo !== null ? round((float) $importo, 2) : null;

            $provvigione->set([
                'importoConsolidato' => $importoRounded,
                'importo' => $importoRounded,
            ]);
        }

        if (
            $dateSource->getEntityType() === 'Quote'
            && $importo !== null
            && (float) $importo !== 0.0
        ) {
            $provvigione->set(
                'name',
                $this->buildProvvigioneDisplayName($dateSource, $tipoRecord, (float) $importo)
            );
        }
    }

    private function findProvvigioneByAppuntamento(string $appuntamentoId): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['appuntamentoId' => $appuntamentoId])
            ->findOne();
    }

    private function findProvvigioneByContrattoAndTipo(string $contrattoId, string $tipo): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where([
                'contrattoId' => $contrattoId,
                'tipo' => $tipo,
            ])
            ->findOne();
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

        $plus = $this->findProvvigioneByContrattoAndTipo($quote->getId(), 'Plus Provvigionale')
            ?? $this->entityManager->createEntity('Provvigione');

        $stato = $this->statusSync->resolveStatoFromQuote($quote);

        $this->applyProvvigioneFromCalculation(
            $plus,
            $opportunity,
            $category,
            ['importo' => $importo, 'regola' => $rule],
            $stato,
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

        $this->saveProvvigioneEntity($plus);
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
        $fromCalculator = $this->quotePricingCalculator->resolveMinusPlusForQuote($quote);

        if ($fromCalculator !== null) {
            return $fromCalculator;
        }

        $stored = $this->floatField($quote, 'minusPlus') ?? $this->floatField($opportunity, 'minusPlus');

        if ($stored !== null) {
            return round($stored, 2);
        }

        $prezzoCodice = $this->quotePricingCalculator->resolvePrezzoCodiceNetForMinusPlus($quote, $opportunity)
            ?? $this->resolvePrezzoCodice($quote, $opportunity);
        $imponibileNet = $this->quotePricingCalculator->resolveImponibileNetto($quote) ?? $imponibile;

        if ($imponibileNet === null || $prezzoCodice === null) {
            return null;
        }

        return round($imponibileNet - $prezzoCodice, 2);
    }

    private function resolveQuoteImponibile(Entity $quote, ?Entity $opportunity = null): ?float
    {
        $net = $this->quotePricingCalculator->resolveImponibileNetto($quote);

        if ($net !== null && $net > 0) {
            return $net;
        }

        if ($opportunity) {
            return $this->resolveImponibile($opportunity);
        }

        return null;
    }

    private function resolveImponibile(Entity $entity): ?float
    {
        if ($entity->getEntityType() === 'Quote') {
            return $this->quotePricingCalculator->resolveImponibileNetto($entity);
        }

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

    /**
     * @param array<string, mixed> $context
     * @return array{baseCalcolo: string, importoBaseCalcolo: float|null}
     */
    private function resolveFallbackBaseCalcolo(string $tipoRecord, array $context): array
    {
        if (in_array($tipoRecord, ['Minus Provvigionale', 'Plus Provvigionale'], true)) {
            return [
                'baseCalcolo' => $tipoRecord === 'Minus Provvigionale' ? 'Minusvalenza' : 'Plusvalenza',
                'importoBaseCalcolo' => isset($context['plusvalenza'])
                    ? round((float) $context['plusvalenza'], 2)
                    : null,
            ];
        }

        return [
            'baseCalcolo' => 'ImponibileContratto',
            'importoBaseCalcolo' => isset($context['imponibile'])
                ? round((float) $context['imponibile'], 2)
                : null,
        ];
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
                    ? round((float) $context['plusvalenza'], 2)
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

    private function buildProvvigioneDisplayName(Entity $quote, string $tipo, float $importo): string
    {
        // $importo ignorato di proposito: se finisce nel nome, cambia a ogni ricalcolo.
        $codice = $this->resolveQuoteCodice($quote);
        $cliente = strtoupper(trim((string) ($quote->get('accountName') ?? 'Cliente')));

        return sprintf(
            '%s - %s - %s',
            $codice,
            $cliente,
            strtoupper($tipo)
        );
    }

    private function resolveQuoteCodice(Entity $quote): string
    {
        $quoteName = trim((string) ($quote->get('name') ?? ''));

        if ($quoteName !== '' && preg_match('/^Contratto[_ ]/i', $quoteName)) {
            return $quoteName;
        }

        foreach (['numberA', 'number', 'numeroContratto'] as $field) {
            $value = trim((string) ($quote->get($field) ?? ''));

            if ($value === '') {
                continue;
            }

            if (preg_match('/^Contratto[_ ]/i', $value)) {
                return $value;
            }

            return 'Contratto_' . ltrim($value, '_');
        }

        if ($quoteName !== '') {
            return $quoteName;
        }

        return 'Contratto_' . $quote->getId();
    }
}
