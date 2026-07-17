<?php

namespace Espo\Custom\Hooks\Prospect;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Aggiorna gli Appuntamenti pianificati collegati al Prospect quando
 * cambiano dati anagrafici (es. indirizzo, telefono, nome).
 */
class SyncPlannedAppointments implements AfterSave
{
    /**
     * Solo questi cambi devono propagarsi agli appuntamenti.
     *
     * @var array<int, string>
     */
    private array $watchedAttributes = [
        'name',
        'firstName',
        'lastName',
        'phoneNumber',
        'mobileNumber',
        'addressStreet',
        'addressCity',
        'addressPostalCode',
        'addressState',
        'addressCountry',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        if (!$entity->getId()) {
            return;
        }

        if (!$entity->isNew()) {
            $changed = false;

            foreach ($this->watchedAttributes as $attr) {
                if ($entity->isAttributeChanged($attr)) {
                    $changed = true;
                    break;
                }
            }

            if (!$changed) {
                return;
            }
        }

        $appointments = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where([
                'status' => 'Planned',
                'deleted' => false,
                'prospectId' => $entity->getId(),
            ])
            ->find();

        if (count($appointments) === 0) {
            $appointments = $this->entityManager
                ->getRDBRepository('Appuntamento')
                ->where([
                    'status' => 'Planned',
                    'deleted' => false,
                    'parentType' => 'Prospect',
                    'parentId' => $entity->getId(),
                ])
                ->find();
        }

        if (count($appointments) === 0) {
            return;
        }

        $prospectName = $this->resolveProspectName($entity);
        $location = $this->buildLocation($entity);

        foreach ($appointments as $appointment) {
            $appointment->set('prospectId', $entity->getId());
            $appointment->set('prospectName', $prospectName);

            if ($appointment->get('parentType') === 'Prospect') {
                $appointment->set('parentName', $prospectName);
            }

            if ($appointment->hasAttribute('indirizzoStreet')) {
                $appointment->set('indirizzoStreet', $entity->get('addressStreet'));
            }
            if ($appointment->hasAttribute('indirizzoCity')) {
                $appointment->set('indirizzoCity', $entity->get('addressCity'));
            }
            if ($appointment->hasAttribute('indirizzoPostalCode')) {
                $appointment->set('indirizzoPostalCode', $entity->get('addressPostalCode'));
            }
            if ($appointment->hasAttribute('indirizzoState')) {
                $appointment->set('indirizzoState', $entity->get('addressState'));
            }
            if ($appointment->hasAttribute('indirizzoCountry')) {
                $appointment->set('indirizzoCountry', $entity->get('addressCountry'));
            }
            if ($appointment->hasAttribute('location')) {
                $appointment->set('location', $location);
            }

            $this->entityManager->saveEntity($appointment, ['skipHooks' => true, 'silent' => true]);
        }
    }

    private function resolveProspectName(Entity $prospect): string
    {
        $name = trim((string) ($prospect->get('name') ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim(
            (string) ($prospect->get('firstName') ?? '') . ' ' .
            (string) ($prospect->get('lastName') ?? '')
        );
    }

    private function buildLocation(Entity $prospect): string
    {
        $parts = [];

        foreach (['addressStreet', 'addressPostalCode', 'addressCity', 'addressState', 'addressCountry'] as $field) {
            $value = trim((string) ($prospect->get($field) ?? ''));

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(', ', $parts);
    }
}
