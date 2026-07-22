<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Nome contratto: data - Lead/Cliente - descrizione - importo
 * (non Prospect / Contraente referente).
 */
class SyncNameFromLead implements BeforeSave
{
    public static int $order = 25;

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

        $clienteLabel = $this->resolveClienteLabel($entity);

        if ($clienteLabel === null || $clienteLabel === '') {
            return;
        }

        $dateQuoted = $entity->get('dateQuoted') ?: '';
        $description = trim((string) ($entity->get('description') ?? ''));
        $amount = $entity->get('importoContratto') ?? $entity->get('amount');
        $importoStr = '';

        if ($amount !== null && $amount !== '') {
            $importoStr = number_format((float) $amount, 0, ',', '.');
        }

        $parts = array_filter([
            $dateQuoted,
            $clienteLabel,
            $description !== '' ? $description : null,
            $importoStr !== '' ? '€. ' . $importoStr : null,
        ], static fn ($p) => $p !== null && $p !== '');

        if ($parts === []) {
            return;
        }

        $entity->set('name', implode(' - ', $parts));
    }

    private function resolveClienteLabel(Entity $quote): ?string
    {
        if ($quote->get('opportunityId')) {
            $opportunity = $this->entityManager->getEntityById(
                'Opportunity',
                $quote->get('opportunityId')
            );

            if ($opportunity) {
                $leadName = trim((string) ($opportunity->get('leadName') ?? ''));

                if ($leadName !== '') {
                    return $leadName;
                }

                if ($opportunity->get('leadId')) {
                    $lead = $this->entityManager->getEntityById(
                        'Lead',
                        $opportunity->get('leadId')
                    );

                    if ($lead) {
                        $fromLead = trim((string) ($lead->get('name') ?? ''));

                        if ($fromLead === '') {
                            $fromLead = trim(
                                ($lead->get('firstName') ?? '') . ' ' . ($lead->get('lastName') ?? '')
                            );
                        }

                        if ($fromLead !== '') {
                            return $fromLead;
                        }
                    }
                }
            }
        }

        $accountName = trim((string) ($quote->get('accountName') ?? ''));

        if ($accountName !== '') {
            return $accountName;
        }

        if ($quote->get('accountId')) {
            $account = $this->entityManager->getEntityById(
                'Account',
                $quote->get('accountId')
            );

            if ($account) {
                $name = trim((string) ($account->get('name') ?? ''));

                if ($name !== '') {
                    return $name;
                }
            }
        }

        return null;
    }
}
