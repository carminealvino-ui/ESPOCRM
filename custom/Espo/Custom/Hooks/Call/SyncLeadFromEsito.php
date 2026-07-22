<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\CallEsitoOpportunitySync;
use Espo\Custom\Services\LeadProspectSync;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Propaga l'esito del Contatto Telefonico sul Lead collegato.
 * Per "Non interessato" chiude anche l'opportunità collegata (Closed Lost).
 */
class SyncLeadFromEsito implements AfterSave
{
    private const ESITO_NON_INTERESSATO = 'Non interessato';
    private const ESITO_IN_TRATTATIVA = 'In Trattativa';
    private const ESITO_OPPORTUNITA_ACCETTATA = 'Opportunità Accettata';

    public static int $order = 10;

    public function __construct(
        private EntityManager $entityManager,
        private LeadProspectSync $leadProspectSync,
        private CallEsitoOpportunitySync $callEsitoOpportunitySync,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Call') {
            return;
        }

        if ($entity->get('status') === 'Planned') {
            return;
        }

        $esito = $entity->get('esito');

        if (!$esito) {
            return;
        }

        // Opportunità: usa l'hook Call già attivo in cache (non solo SyncOpportunityFromEsito).
        if ((string) $esito === self::ESITO_NON_INTERESSATO) {
            $this->callEsitoOpportunitySync->syncFromCall($entity);
        }

        $leadId = $this->resolveLeadId($entity);

        if (!$leadId) {
            return;
        }

        $lead = $this->entityManager->getEntityById('Lead', $leadId);

        if (!$lead) {
            return;
        }

        $attributes = $this->mapEsitoToLeadAttributes((string) $esito);

        if ($attributes === null) {
            return;
        }

        $alreadyAligned = true;

        foreach ($attributes as $field => $value) {
            if ($lead->get($field) !== $value) {
                $alreadyAligned = false;
                break;
            }
        }

        if ($alreadyAligned) {
            return;
        }

        $lead->set($attributes);

        $this->entityManager->saveEntity($lead, [
            'skipHooks' => false,
            'silent' => true,
            'skipAcl' => true,
        ]);
    }

    /**
     * @return array<string, string>|null
     */
    private function mapEsitoToLeadAttributes(string $esito): ?array
    {
        return match ($esito) {
            self::ESITO_NON_INTERESSATO => [
                'status' => 'Dead',
                'statoGestione' => 'Trattativa Chiusa',
            ],
            self::ESITO_IN_TRATTATIVA => [
                'status' => 'In Process',
                'statoGestione' => 'Sospeso',
            ],
            self::ESITO_OPPORTUNITA_ACCETTATA => [
                'status' => 'In Process',
                'statoGestione' => 'Trattativa Aperta',
            ],
            default => null,
        };
    }

    private function resolveLeadId(Entity $call): ?string
    {
        if ($call->get('parentType') === 'Lead' && $call->get('parentId')) {
            return (string) $call->get('parentId');
        }

        $prospectId = $call->get('prospectId');

        if (!$prospectId && $call->get('parentType') === 'Prospect' && $call->get('parentId')) {
            $prospectId = $call->get('parentId');
        }

        if (!$prospectId) {
            return null;
        }

        $prospect = $this->entityManager->getEntityById('Prospect', (string) $prospectId);

        if (!$prospect) {
            return null;
        }

        $lead = $this->leadProspectSync->findExistingLeadByProspect($prospect);

        return $lead ? $lead->getId() : null;
    }
}
