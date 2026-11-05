<?php
namespace Espo\Custom\Hooks\Appuntamento;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
class CancelAutomaticCalls implements AfterSave
{
 public static int $order=10030;
 public function __construct(private EntityManager $em,private AppuntamentoPendingCallCreator $creator){}
 public function afterSave(Entity $entity,SaveOptions $options):void
 {
  if($options->get('skipHooks'))return;
  if($entity->get('sottostato')!=='Annullato'&&!str_starts_with((string)$entity->get('esito'),'Annullato'))return;
  $id=$entity->getId();if(!$id)return;
  foreach($this->em->getRDBRepository('Call')->where(['status'=>'Planned'])->find() as $call){
   if($this->creator->extractAppuntamentoIdFromNota((string)$call->get('nota'))!==$id)continue;
   $call->set('status','Not Held');
   $update=$this->em->getPDO()->prepare('UPDATE `call` SET status=? WHERE id=? AND status=?');
   $update->execute(['Not Held',$call->getId(),'Planned']);
   $this->creator->clearPopupReminders($call);
  }
 }
}
