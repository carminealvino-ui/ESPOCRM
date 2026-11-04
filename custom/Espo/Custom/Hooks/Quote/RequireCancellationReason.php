<?php
namespace Espo\Custom\Hooks\Quote;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
class RequireCancellationReason
{
    public static int $order = 10001;
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->get('statoContratto') !== 'Annullato') return;
        if (!in_array($entity->get('causaleAnnullamento'), ['Finanziamento KO','Infattibilità tecnica','Altro'], true)) {
            throw new BadRequest('Per un contratto Annullato è obbligatorio indicare la Causale annullamento.');
        }
    }
}
