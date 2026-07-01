<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

class RinvioRichiamo implements AfterSave
{
    private const NOTA_PREFIX = 'Auto-Rinvio-Call:';

    public static int $order = 7;

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private AppuntamentoPendingCallCreator $callCreator,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Call') {
            return;
        }

        $status = (string) $entity->get('status');
        $callId = (string) $entity->getId();

        if ($callId === '') {
            return;
        }

        if (
            !in_array($status, ['Held', 'Not Held'], true)
            || !$entity->get('daRichiamare')
            || !$entity->get('dataRichiamo')
        ) {
            return;
        }

        try {
            $dataRichiamo = (string) $entity->get('dataRichiamo');
            $existing = $this->findExistingFollowUp($callId, $dataRichiamo);

            if ($existing) {
                return;
            }

            $tipologia = trim((string) $entity->get('richiamo')) ?: (string) $entity->get('tipologia');
            $name = (string) $entity->get('name');

            if ($name !== '' && $tipologia !== '') {
                $parts = explode(' - ', $name);

                if (count($parts) >= 4) {
                    $parts[1] = $tipologia;
                    $name = strtoupper(implode(' - ', $parts));
                }
            }

            $followUp = $this->entityManager->createEntity('Call');
            $followUp->set([
                'name' => $name ?: ('RICHIAMO - ' . $callId),
                'status' => 'Planned',
                'direction' => $entity->get('direction') ?: 'Outbound',
                'tipologia' => $tipologia,
                'richiamo' => '',
                'daRichiamare' => false,
                'dataRichiamo' => null,
                'parentType' => $entity->get('parentType'),
                'parentId' => $entity->get('parentId'),
                'parentName' => $entity->get('parentName'),
                'prospectId' => $entity->get('prospectId'),
                'prospectName' => $entity->get('prospectName'),
                'telefono' => $entity->get('telefono'),
                'whatsAppNumero' => $entity->get('whatsAppNumero'),
                'whatsApp' => (bool) $entity->get('whatsApp'),
                'vocale' => (bool) $entity->get('vocale'),
                'testo' => $entity->get('testo'),
                'assignedUserId' => $entity->get('assignedUserId'),
                'assignedUserName' => $entity->get('assignedUserName'),
                'usersIds' => $entity->get('usersIds') ?: [],
                'usersNames' => $entity->get('usersNames') ?: (object) [],
                'dateStart' => $dataRichiamo,
                'dateEnd' => null,
                'nota' => self::NOTA_PREFIX . ' ' . $callId,
            ]);

            $this->entityManager->saveEntity($followUp, [
                'skipAcl' => true,
                'silent' => true,
                'skipHooks' => true,
            ]);

            $this->callCreator->syncPopupReminders($followUp);

            $entity->set([
                'daRichiamare' => false,
                'dataRichiamo' => null,
                'richiamo' => '',
            ]);

            $this->entityManager->saveEntity($entity, [
                'skipAcl' => true,
                'silent' => true,
                'skipHooks' => true,
            ]);
        } catch (\Throwable $e) {
            $this->log->error(
                'Rinvio richiamo Call {id} fallito: {message}',
                [
                    'id' => $callId,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
        }
    }

    private function findExistingFollowUp(string $sourceCallId, string $dataRichiamo): ?Entity
    {
        $collection = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'nota*' => self::NOTA_PREFIX . ' ' . $sourceCallId,
                'status' => 'Planned',
            ])
            ->find();

        foreach ($collection as $call) {
            if ((string) $call->get('dateStart') === $dataRichiamo) {
                return $call;
            }
        }

        return null;
    }
}
