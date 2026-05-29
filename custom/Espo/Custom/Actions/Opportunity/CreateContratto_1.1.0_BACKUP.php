<?php

namespace Espo\Custom\Actions\Opportunity;

use Espo\ORM\Entity;

class CreateContratto
{
    public function run(Entity $entity, array $params = [])
    {
        $em = $GLOBALS['container']->get('entityManager');

        $quote = $em->getEntity('Quote');

        $quote->set('name', $entity->get('name'));
        $quote->set('assignedUserId', $entity->get('assignedUserId'));
        $quote->set('dateQuoted', date('Y-m-d'));
        $quote->set('amount', (float) $entity->get('amount'));

        $em->saveEntity($quote);

        return true;
    }
}