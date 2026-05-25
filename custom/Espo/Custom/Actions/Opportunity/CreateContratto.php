<?php

namespace Espo\Custom\Actions\Opportunity;

use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Crea contratto (Quote) da Opportunity chiusa positivamente.
 */
class CreateContratto
{
    private const CLOSED_WON_STAGES = [
        'Closed Won',
        'Chiuso Positivamente',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function run(Entity $opportunity): object
    {
        if (!$opportunity) {
            throw new \RuntimeException('Opportunita non trovata.');
        }

        $this->assertClosedWon($opportunity);

        $existing = $this->entityManager
            ->getRDBRepository('Quote')
            ->where(['opportunityId' => $opportunity->getId()])
            ->findOne();

        if ($existing) {
            return (object) [
                'quoteId' => $existing->getId(),
                'quoteName' => $existing->get('name'),
                'existing' => true,
            ];
        }

        $teamsIds = $opportunity->getLinkMultipleIdList('teams');
        $accountId = $opportunity->get('accountId');
        $lead = $this->resolveLead($opportunity);

        if (!$accountId && $lead) {
            $accountId = $this->createAccountFromLead($lead, $opportunity, $teamsIds);
        }

        $quote = $this->buildQuote($opportunity, $accountId, $teamsIds, $lead);

        $this->entityManager->saveEntity($quote);

        $provvigione = $this->provvigioneManager->createConsolidataForQuote($opportunity, $quote);

        return (object) [
            'quoteId' => $quote->getId(),
            'quoteName' => $quote->get('name'),
            'existing' => false,
            'provvigioneId' => $provvigione?->getId(),
        ];
    }

    private function assertClosedWon(Entity $opportunity): void
    {
        $stage = $opportunity->get('stage');

        if (in_array($stage, self::CLOSED_WON_STAGES, true)) {
            return;
        }

        $probability = $opportunity->get('probability');

        if ($probability === 100 || $probability === '100') {
            return;
        }

        throw new \RuntimeException(
            'Il contratto si crea solo su opportunita concluse positivamente.'
        );
    }

    private function resolveLead(Entity $opportunity): ?Entity
    {
        $leadId = $opportunity->get('leadId');

        if (!$leadId) {
            return null;
        }

        return $this->entityManager->getEntityById('Lead', $leadId);
    }

    private function createAccountFromLead(Entity $lead, Entity $opportunity, array $teamsIds): string
    {
        $cliente = $this->entityManager->createEntity('Account');

        $cliente->set([
            'name' => $lead->get('name'),
            'billingAddressStreet' => $lead->get('addressStreet'),
            'billingAddressCity' => $lead->get('addressCity'),
            'billingAddressPostalCode' => $lead->get('addressPostalCode'),
            'billingAddressState' => $lead->get('addressState'),
            'phoneNumber' => $lead->get('phoneNumber'),
            'teamsIds' => $teamsIds,
            'assignedUserId' => $opportunity->get('assignedUserId'),
            'type' => 'B2C',
            'segmento' => 'B2C',
        ]);

        $this->entityManager->saveEntity($cliente);

        $lead->set([
            'status' => 'Converted',
            'createdAccountId' => $cliente->getId(),
            'convertedAt' => date('Y-m-d H:i:s'),
        ]);

        $this->entityManager->saveEntity($lead);

        return $cliente->getId();
    }

    private function buildQuote(
        Entity $opportunity,
        ?string $accountId,
        array $teamsIds,
        ?Entity $lead
    ): Entity {
        $amount = $opportunity->get('amount') ?: $opportunity->get('importoOpportunita');
        $dateQuoted = $opportunity->get('closeDate') ?: $opportunity->get('dateClosed') ?: date('Y-m-d');

        $dataInstallazione = $opportunity->get('installazione');
        $dataAttivazione = $opportunity->get('dataAttivazione');

        if (!$dataAttivazione && $opportunity->get('appuntamentoId')) {
            $appuntamento = $this->entityManager->getEntityById(
                'Appuntamento',
                $opportunity->get('appuntamentoId')
            );

            if ($appuntamento) {
                $dataAttivazione = $appuntamento->get('dataAttivazione');
                $dataInstallazione = $dataInstallazione ?: $appuntamento->get('dataInstallazione');
            }
        }

        $prezzoCodice = $opportunity->get('prezzoCodiceIvaEsclusa');
        $minusPlus = null;

        if ($amount && $prezzoCodice && (float) $amount > (float) $prezzoCodice) {
            $minusPlus = (float) $amount - (float) $prezzoCodice;
        }

        $quote = $this->entityManager->createEntity('Quote');

        $quote->set([
            'name' => 'CONTRATTO_' . date('Ymd_His'),
            'opportunityId' => $opportunity->getId(),
            'accountId' => $accountId,
            'amount' => $amount,
            'importoContratto' => $amount,
            'minusPlus' => $minusPlus,
            'totalPrezzoCodice' => $prezzoCodice,
            'prezzoCodice' => $prezzoCodice,
            'dateQuoted' => $dateQuoted,
            'description' => $opportunity->get('description'),
            'taxRate' => $opportunity->get('iVA') ?? $opportunity->get('taxRate'),
            'taxCodeId' => $opportunity->get('taxCodeId'),
            'isTaxInclusive' => true,
            'priceBookId' => $opportunity->get('priceBookId'),
            'itemList' => [],
            'billingAddressStreet' => $opportunity->get('billingAddressStreet'),
            'billingAddressCity' => $opportunity->get('billingAddressCity'),
            'billingAddressPostalCode' => $opportunity->get('billingAddressPostalCode'),
            'billingAddressState' => $opportunity->get('billingAddressState'),
            'shippingAddressStreet' => $opportunity->get('billingAddressStreet'),
            'shippingAddressCity' => $opportunity->get('billingAddressCity'),
            'shippingAddressPostalCode' => $opportunity->get('billingAddressPostalCode'),
            'shippingAddressState' => $opportunity->get('billingAddressState'),
            'assignedUserId' => $opportunity->get('assignedUserId'),
            'teamsIds' => $teamsIds,
            'fornitorePartnerId' => $opportunity->get('fornitorePartnerId'),
            'fornitorePartnerName' => $opportunity->get('fornitorePartnerName'),
            'productBrandId' => $opportunity->get('productBrandId'),
            'productBrandName' => $opportunity->get('productBrandName'),
            'productCategoryId' => $opportunity->get('productCategoryId'),
            'productCategoryName' => $opportunity->get('productCategoryName'),
            'dataInstallazione' => $dataInstallazione,
            'dataAttivazione' => $dataAttivazione,
            'dataCompetenza' => $dataAttivazione
                ? date('Y-m-01', strtotime($dataAttivazione))
                : date('Y-m-01', strtotime($dateQuoted)),
        ]);

        return $quote;
    }
}
