<?php
/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 77350457a8d35522431c4daeee1dd4ad
 ************************************************************************************/

namespace Espo\Modules\Outlook\Core\Outlook;

use DateTime;
use DateTimeZone;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\Integration;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Call;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\ORM\Entity;
use Espo\ORM\Entity as OrmEntity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Exception;
use RuntimeException;

class ItemPreparator
{
    private ?Integration $integration = null;

    public function __construct(
        private Config $config,
        private EntityManager $entityManager,
    ) {}

    /**
     * @param array{
     *     labelMap?: array<string, string>,
     *     defaultEntityType?: string|null,
     *     syncAttendees?: bool,
     * } $params
     * @return array<string, mixed>
     */
    public function prepare(Entity $entity, bool $isEspoEvent, array $params = []): array
    {
        $isAllDay = $entity->get('isAllDay') ?? false;
        $name = $entity->get('name');
        $dateStart = $entity->get('dateStart');
        $dateEnd = $entity->get('dateEnd');
        $description = $entity->get('description');

        $labelMap = $params['labelMap'] ?? [];
        $defaultEntityType = $params['defaultEntityType'] ?? Meeting::ENTITY_TYPE;
        $syncAttendees = $params['syncAttendees'] ?? false;

        if ($entity->getEntityType() !== $defaultEntityType) {
            foreach ($labelMap as $kEntityType => $label) {
                if (
                    $kEntityType === $entity->getEntityType() &&
                    !str_starts_with($name, $label . ':')
                ) {
                    $name = $label . ':' . $name;

                    break;
                }
            }
        }

        $timeZone = $this->config->get('timeZone', 'UTC');

        if ($isAllDay) {
            $timeZone = 'UTC';
            $dateStart = $entity->get('dateStartDate') . ' 00:00:00';

            try {
                $dateEnd = (new DateTime($entity->get('dateEndDate')))
                    ->modify('+1 day')
                    ->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage());
            }
        } else if ($timeZone !== 'UTC') {
            try {
                $tz = new DateTimeZone($timeZone);

                $dateStart = (new DateTime($dateStart))
                    ->setTimezone($tz)
                    ->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

                $dateEnd = (new DateTime($dateEnd))
                    ->setTimezone($tz)
                    ->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage());
            }
        }

        $item = [
            'Subject' => $name,
            'Start' => [
                'DateTime' => $dateStart,
                'TimeZone' => $timeZone,
            ],
            'End' => [
                'DateTime' => $dateEnd,
                'TimeZone' => $timeZone,
            ],
            'IsAllDay' => $isAllDay,
        ];

        if (!$isEspoEvent) {
            return $item;
        }

        if ($entity instanceof Meeting && $entity->getJoinUrl()) {
            $description ??= '';

            if ($description) {
                $description .= "\n---\n";
            }

            $description .= $entity->getJoinUrl();
        }

        if ($description) {
            $item['Body'] = [
                'ContentType' => 'Text',
                'Content' => $description,
            ];
        }

        if (
            $entity->hasAttribute('cLocation') &&
            $entity->getAttributeType('cLocation') === OrmEntity::VARCHAR
        ) {
            $location = $entity->get('cLocation');

            if ($location) {
                $item['location'] = [
                    'displayName' => $location,
                ];
            }
        }

        if (!$entity instanceof Meeting && !$entity instanceof Call) {
            return $item;
        }

        $attendees = [];

        $userIds = $entity->getLinkMultipleIdList('users');

        if ($userIds !== []) {
            $users = $this->entityManager
                ->getRDBRepositoryByClass(User::class)
                ->where([Attribute::ID => $userIds])
                ->find();

            foreach ($users as $user) {
                if (!$user->getEmailAddress()) {
                    continue;
                }

                $attendees[] = [
                    'emailAddress' => [
                        'address' => $this->getUserEmailAddress($user),
                        'name' => $user->getName(),
                    ],
                ];
            }
        }

        if ($syncAttendees) {
            $contactIds = $entity->getLinkMultipleIdList('contacts');
            $leadIds = $entity->getLinkMultipleIdList('leads');

            if ($contactIds !== []) {
                $contacts = $this->entityManager
                    ->getRDBRepositoryByClass(Contact::class)
                    ->where([Attribute::ID => $contactIds])
                    ->find();

                foreach ($contacts as $contact) {
                    if (!$contact->getEmailAddress()) {
                        continue;
                    }

                    $attendees[] = [
                        'emailAddress' => [
                            'address' => $contact->getEmailAddress(),
                            'name' => $contact->getName(),
                        ],
                    ];
                }
            }

            if ($leadIds !== []) {
                $leads = $this->entityManager
                    ->getRDBRepositoryByClass(Lead::class)
                    ->where([Attribute::ID => $leadIds])
                    ->find();

                foreach ($leads as $lead) {
                    if (!$lead->getEmailAddress()) {
                        continue;
                    }

                    $attendees[] = [
                        'emailAddress' => [
                            'address' => $lead->getEmailAddress(),
                            'name' => $lead->getName(),
                        ],
                    ];
                }
            }
        }

        $item['attendees'] = $attendees;

        return $item;
    }

    private function getDomainName(): ?string
    {
        if (!$this->integration) {
            $this->integration = $this->entityManager
                ->getRDBRepositoryByClass(Integration::class)
                ->getById('Outlook');
        }

        return $this->integration?->get('domainName');
    }


    private function getUserEmailAddress(User $user): ?string
    {
        $domainName = $this->getDomainName();

        if (!$domainName) {
            return $user->getEmailAddress();
        }

        foreach ($user->getEmailAddressGroup()->getList() as $item) {
            if (str_ends_with($item->getAddress(), $domainName)) {
                return $item->getAddress();
            }
        }

        return $user->getEmailAddress();
    }
}
