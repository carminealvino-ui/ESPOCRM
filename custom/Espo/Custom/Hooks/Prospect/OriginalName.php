<?php
namespace Espo\Custom\Hooks\Prospect;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/** Keep the same history when identity is edited from appointment review. */
class OriginalName implements BeforeSave
{
    public static int $order = 35;
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $entity->isNew()) return;
        if (!$entity->isAttributeChanged('name') && !$entity->isAttributeChanged('firstName') && !$entity->isAttributeChanged('lastName')) return;
        $description=(string)$entity->get('description');$marker='[NOMINATIVO ORIGINARIO]';
        if (str_contains($description,$marker)) return;
        $old=trim((string)$entity->getFetched('name'));
        if ($old==='') $old=trim((string)$entity->getFetched('lastName').' '.(string)$entity->getFetched('firstName'));
        $new=trim((string)$entity->get('name'));
        $normalize=static fn(string $name):string=>mb_strtoupper(preg_replace('/\s+/u',' ',trim($name)));
        if ($old==='' || $new==='' || $normalize($old)===$normalize($new)) return;
        $date=(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('d/m/Y H:i');
        $entity->set('description',rtrim($description).($description!==''?"\n\n":'').$marker.' Registrato inizialmente come: '.$old.'. Conservato al cambio nome del '.$date.'.');
    }
}
