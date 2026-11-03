<?php
namespace Espo\Custom\Services;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Refresh active calls through explicit relationships, without scheduling effects. */
class CallLinkedIdentity
{
    public function __construct(private EntityManager $em) {}

    public function syncCall(Entity $call, bool $dryRun=false): array
    {
        if ($call->getEntityType()!=='Call' || $call->get('deleted')) return [];
        $patch=[];$parent=null;
        if (in_array($call->get('parentType'),['Lead','Prospect'],true) && $call->get('parentId')) {
            $parent=$this->em->getEntityById($call->get('parentType'),$call->get('parentId'));
        }
        $prospect=$call->get('prospectId')?$this->em->getEntityById('Prospect',$call->get('prospectId')):null;
        if (!$prospect && $parent?->getEntityType()==='Prospect') $prospect=$parent;
        if (!$prospect && $parent?->getEntityType()==='Lead') $prospect=(new LeadProspectSync($this->em))->findProspectForLead($parent);
        $person=$parent ?: $prospect;
        $name=trim((string)$person?->get('name'));
        $phone=trim((string)($prospect?->get('phoneNumber') ?: $prospect?->get('telefono') ?: $person?->get('phoneNumber')));
        if ($call->get('status')==='Planned' && $person) {
            if ($parent && $name!=='') $patch['parentName']=$name;
            if ($prospect) {$patch['prospectId']=$prospect->getId();$patch['prospectName']=$prospect->get('name');}
            if ($phone!=='') {
                $patch['telefono']=$phone;$digits=preg_replace('/\D+/','',$phone);
                $patch['whatsAppNumero']=$digits?'https://wa.me/'.(str_starts_with($digits,'39')&&strlen($digits)>10?'+'.$digits:'+39'.$digits):null;
            }
            // Keep the existing date/type prefix: it identifies the source appointment.
            // Never replace a manually written title or reschedule the call.
            $parts=explode(' - ',(string)$call->get('name'));
            if ($name!=='' && count($parts)>=4 && preg_match('/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}$/',$parts[0])) {
                $patch['name']=mb_strtoupper(implode(' - ',[$parts[0],$parts[1],$name,$phone!==''?$phone:$call->get('telefono')]),'UTF-8');
            }
        }
        if (!$call->get('opportunityId')) {
            $linker=new CallOpportunityLinker($this->em);
            $appointmentId=$linker->extractAppuntamentoId((string)$call->get('nota')) ?: $linker->extractAppuntamentoId((string)$call->get('description'));
            if ($appointmentId) {
                $matches=$this->em->getRDBRepository('Opportunity')->where(['appuntamentoId'=>$appointmentId])->find();
                if (count($matches)===1) foreach ($matches as $opportunity) {
                    $patch['opportunityId']=$opportunity->getId();$patch['opportunityName']=$opportunity->get('name');
                }
            }
        }
        $actual=[];
        foreach ($patch as $field=>$value) if ($call->get($field)!==$value) $actual[$field]=$value;
        if ($actual && !$dryRun) {
            $columns=['name'=>'name','telefono'=>'telefono','whatsAppNumero'=>'whats_app_numero','prospectId'=>'prospect_id','opportunityId'=>'opportunity_id'];
            $sets=[];$values=[];
            foreach($actual as $field=>$value)if(isset($columns[$field])){$sets[]='`'.$columns[$field].'`=?';$values[]=$value;}
            // Update only intended columns: legacy Call repositories may otherwise
            // normalize dateEnd/duration even when hooks and formulas are skipped.
            if($sets){$sets[]='modified_at=?';$values[]=gmdate('Y-m-d H:i:s');$values[]=$call->getId();$q=$this->em->getPDO()->prepare('UPDATE `call` SET '.implode(',',$sets).' WHERE id=? AND deleted=0');$q->execute($values);}
            $call->set($actual);
        }
        return $actual;
    }

    public function syncProspect(Entity $prospect): void
    {
        $or=[['prospectId'=>$prospect->getId()],['parentType'=>'Prospect','parentId'=>$prospect->getId()]];
        $ids=[];foreach($this->em->getRDBRepository('Lead')->where(['prospectId'=>$prospect->getId()])->find() as $l)$ids[]=$l->getId();
        if ($prospect->get('leadId')) $ids[]=$prospect->get('leadId');
        if($ids)$or[]=['parentType'=>'Lead','parentId'=>array_values(array_unique($ids))];
        foreach($this->em->getRDBRepository('Call')->where(['status'=>'Planned','OR'=>$or])->find() as $call)$this->syncCall($call);
    }

    public function syncOpportunity(Entity $opportunity): void
    {
        $id=$opportunity->get('appuntamentoId');if(!$id)return;
        $linker=new CallOpportunityLinker($this->em);
        foreach($this->em->getRDBRepository('Call')->where(['OR'=>[['nota*'=>'%'.$id.'%'],['description*'=>'%'.$id.'%']]])->find() as $call) {
            $source=$linker->extractAppuntamentoId((string)$call->get('nota')) ?: $linker->extractAppuntamentoId((string)$call->get('description'));
            if($source===$id)$this->syncCall($call);
        }
    }
}
