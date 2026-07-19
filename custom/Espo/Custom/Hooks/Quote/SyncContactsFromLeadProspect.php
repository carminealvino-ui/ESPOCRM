<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\ReferenteContactService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Contraente = Lead; Referente Prospect = Contatto installazione / 2° referente.
 */
class SyncContactsFromLeadProspect implements BeforeSave
{
    public static int $order = 20;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        $accountId = $entity->get('accountId');
        $opportunityId = $entity->get('opportunityId');

        if (!$accountId || !$opportunityId) {
            return;
        }

        $opportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        if (!$opportunity) {
            return;
        }

        $lead = null;
        $prospect = null;

        if ($opportunity->get('leadId')) {
            $lead = $this->entityManager->getEntityById('Lead', $opportunity->get('leadId'));
        }

        if ($opportunity->get('prospectId')) {
            $prospect = $this->entityManager->getEntityById(
                'Prospect',
                $opportunity->get('prospectId')
            );
        }

        if (!$lead && !$prospect) {
            return;
        }

        $service = new ReferenteContactService($this->entityManager);
        $pair = $service->ensureLeadAndProspectForAccount($accountId, [
            'lead' => $lead,
            'prospect' => $prospect,
            'assignedUserId' => $entity->get('assignedUserId')
                ?: $opportunity->get('assignedUserId'),
        ]);

        if (!empty($pair['billingContactId'])) {
            $entity->set([
                'billingContactId' => $pair['billingContactId'],
                'billingContactName' => $pair['billingContactName'],
            ]);
        }

        if (!empty($pair['shippingContactId'])) {
            $entity->set([
                'shippingContactId' => $pair['shippingContactId'],
                'shippingContactName' => $pair['shippingContactName'],
            ]);
        }
    }
}
