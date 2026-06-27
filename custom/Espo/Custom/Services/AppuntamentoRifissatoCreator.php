<?php

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AppuntamentoRifissatoCreator
{
    private const DURATION_SECONDS = 5400;

    /** @var string[] */
    private const COPY_FIELDS = [
        'name',
        'prospectId',
        'prospectName',
        'parentType',
        'parentId',
        'parentName',
        'leadId',
        'leadName',
        'azienda',
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
        'cAPId',
        'cAPName',
        'assignedUserId',
        'assignedUserName',
        'tipo',
        'callCenter',
        'indirizzoStreet',
        'indirizzoCity',
        'indirizzoPostalCode',
        'indirizzoState',
        'indirizzoCountry',
        'location',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @param string[] $assignedUsersIds
     */
    public function create(
        string $sourceId,
        string $dateStart,
        array $assignedUsersIds = [],
    ): string {
        $sourceId = trim($sourceId);
        $dateStart = trim($dateStart);

        if ($sourceId === '' || $dateStart === '') {
            throw new BadRequest('sourceId e dateStart sono obbligatori.');
        }

        $source = $this->entityManager->getEntityById('Appuntamento', $sourceId);

        if (!$source) {
            throw new NotFound('Appuntamento origine non trovato.');
        }

        if ($source->get('sottostato') !== 'Rifissato') {
            throw new BadRequest('L\'appuntamento origine deve avere sottostato Rifissato.');
        }

        $new = $this->entityManager->getNewEntity('Appuntamento');

        foreach (self::COPY_FIELDS as $field) {
            $value = $source->get($field);

            if ($value !== null && $value !== '') {
                $new->set($field, $value);
            }
        }

        $this->ensureName($source, $new);
        $this->copyTeams($source, $new);
        $this->copyAssignedUsers($source, $new, $assignedUsersIds);

        if (!$new->get('parentType') && $new->get('prospectId')) {
            $new->set('parentType', 'Prospect');
            $new->set('parentId', $new->get('prospectId'));
            $new->set('parentName', $new->get('prospectName'));
        }

        $new->set([
            'status' => 'Planned',
            'sottostato' => null,
            'esito' => null,
            'noteEsito' => null,
            'dateStart' => $dateStart,
            'dateEnd' => $this->resolveDateEnd($dateStart),
            'description' => $this->buildDescription($source),
        ]);

        $this->entityManager->saveEntity($new);

        return (string) $new->getId();
    }

    private function ensureName(Entity $source, Entity $new): void
    {
        if ($new->get('name')) {
            return;
        }

        $prospectName = $source->get('prospectName') ?: $new->get('prospectName');

        if ($prospectName) {
            $new->set('name', $prospectName);
        }
    }

    private function copyTeams(Entity $source, Entity $new): void
    {
        $teamsIds = $source->getLinkMultipleIdList('teams');

        if (empty($teamsIds)) {
            $teamsIds = $source->get('teamsIds') ?: [];
        }

        if (empty($teamsIds)) {
            return;
        }

        $teamsNames = $source->getLinkMultipleNameMap('teams');

        $new->set([
            'teamsIds' => $teamsIds,
            'teamsNames' => $teamsNames,
        ]);
    }

    /**
     * @param string[] $preservedAssignedUsersIds
     */
    private function copyAssignedUsers(
        Entity $source,
        Entity $new,
        array $preservedAssignedUsersIds = [],
    ): void {
        $assignedUsersIds = $this->normalizeIdList($preservedAssignedUsersIds);

        if (empty($assignedUsersIds)) {
            $assignedUsersIds = $this->loadAssignedUsersIds($source);
        }

        if (empty($assignedUsersIds)) {
            return;
        }

        $assignedUsersNames = $source->getLinkMultipleNameMap('assignedUsers');

        foreach ($assignedUsersIds as $assignedUserId) {
            if (!isset($assignedUsersNames[$assignedUserId])) {
                $user = $this->entityManager->getEntityById('User', $assignedUserId);

                if ($user) {
                    $assignedUsersNames[$assignedUserId] = $user->get('name');
                }
            }
        }

        $new->set([
            'assignedUsersIds' => $assignedUsersIds,
            'assignedUsersNames' => $assignedUsersNames,
        ]);

        if (!$new->get('assignedUserId')) {
            $new->set([
                'assignedUserId' => $assignedUsersIds[0],
                'assignedUserName' => $assignedUsersNames[$assignedUsersIds[0]] ?? null,
            ]);
        }
    }

    /**
     * @return string[]
     */
    private function loadAssignedUsersIds(Entity $source): array
    {
        $assignedUsersIds = $this->normalizeIdList($source->getLinkMultipleIdList('assignedUsers'));

        if (!empty($assignedUsersIds)) {
            return $assignedUsersIds;
        }

        $assignedUsersIds = $this->normalizeIdList(
            $this->entityManager
                ->getRDBRepository('Appuntamento')
                ->getRelation($source, 'assignedUsers')
                ->find()
                ->getIdList()
        );

        if (!empty($assignedUsersIds)) {
            return $assignedUsersIds;
        }

        $assignedUserId = $source->get('assignedUserId');

        if ($assignedUserId) {
            return [(string) $assignedUserId];
        }

        return [];
    }

    /**
     * @param mixed $ids
     * @return string[]
     */
    private function normalizeIdList($ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        $normalized = [];

        foreach ($ids as $id) {
            $id = trim((string) $id);

            if ($id !== '') {
                $normalized[] = $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function buildDescription(Entity $source): string
    {
        $dateStart = (string) $source->get('dateStart');

        if ($dateStart === '') {
            return 'appuntamento rifissato';
        }

        try {
            $dateTime = new \DateTime($dateStart);
        } catch (\Throwable) {
            return 'appuntamento rifissato';
        }

        return sprintf(
            'appuntamento rifissato del %s ore %s',
            $dateTime->format('d/m/Y'),
            $dateTime->format('H:i')
        );
    }

    private function resolveDateEnd(string $dateStart): string
    {
        try {
            $dateTime = new \DateTime($dateStart);
        } catch (\Throwable) {
            return $dateStart;
        }

        $dateTime->modify('+' . self::DURATION_SECONDS . ' seconds');

        return $dateTime->format('Y-m-d H:i:s');
    }
}
