<?php
namespace Espo\Custom\Hooks\Prospect;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncLinkedIdentity implements AfterSave
{
    public static int $order = 30;
    public function __construct(private EntityManager $entityManager) {}
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if($options->get('skipHooks')) return;
        foreach(['name','salutationName','firstName','lastName','b2B','ragioneSociale','referenteAziendale'] as $field) {
            if($entity->isAttributeChanged($field)) { $this->sync($entity); return; }
        }
    }
    public function sync(Entity $prospect, bool $dryRun=false): array
    {
        $em=$this->entityManager;$name=trim((string)$prospect->get('name'));
        if(!$name || !$prospect->getId()) return [];
        $changes=[];$oldNames=[];$accounts=[];
        $recordChange=function(Entity $record, array $patch) use (&$changes,$dryRun,$em) {
            $actual=[];$before=[];
            foreach($patch as $field=>$value) if($record->get($field)!==$value) { $before[$field]=$record->get($field);$actual[$field]=$value; }
            if(!$actual)return;
            $changes[]=['entityType'=>$record->getEntityType(),'id'=>$record->getId(),'before'=>$before,'after'=>$actual];
            if(!$dryRun){$record->set($actual);$em->saveEntity($record,['skipHooks'=>true,'silent'=>true]);if($record->getEntityType()==='Quote')(new \Espo\Custom\Services\CommissionIdentitySync($em))->sync($record);}
        };
        $leads=[];
        foreach($em->getRDBRepository('Lead')->where(['prospectId'=>$prospect->getId()])->find() as $lead)$leads[$lead->getId()]=$lead;
        if($prospect->get('leadId')) {
            $lead=$em->getEntityById('Lead',$prospect->get('leadId'));
            if($lead && (!$lead->get('prospectId') || $lead->get('prospectId')===$prospect->getId()))$leads[$lead->getId()]=$lead;
        }
        foreach($leads as $lead){
            $oldNames[]=(string)$lead->get('name');
            $recordChange($lead,['salutationName'=>$prospect->get('salutationName'),'firstName'=>$prospect->get('firstName'),'lastName'=>$prospect->get('lastName'),'name'=>$name,'b2B'=>(bool)$prospect->get('b2B'),'referenteAziendale'=>$prospect->get('referenteAziendale')]);
        }
        if($prospect->get('clienteId'))$accounts[$prospect->get('clienteId')]=true;
        $opportunities=$em->getRDBRepository('Opportunity')->where(['prospectId'=>$prospect->getId()])->find();
        foreach($opportunities as $opportunity)if($opportunity->get('accountId'))$accounts[$opportunity->get('accountId')]=true;
        foreach(array_keys($accounts) as $id){
            $account=$em->getEntityById('Account',$id);if(!$account)continue;
            // Do not rename an account explicitly shared with a different prospect.
            if($em->getRDBRepository('Prospect')->where(['clienteId'=>$id,'id!='=>$prospect->getId()])->findOne())continue;
            $oldNames[]=(string)$account->get('name');
            $legalName=trim((string)$prospect->get('ragioneSociale'));
            $business=(bool)$prospect->get('b2B');
            $accountPatch=['b2B'=>$business,'type'=>$business?'B2B':'B2C','segmento'=>$business?'B2B':'B2C'];
            if(!$prospect->get('b2B') || $legalName!=='') $accountPatch['name']=$prospect->get('b2B')?$legalName:$name;
            $recordChange($account,$accountPatch);
            if ($business && $legalName !== '') {
                $contact = null;
                if (!$dryRun && trim((string)$prospect->get('referenteAziendale')) !== '') {
                    $contact = (new \Espo\Custom\Services\ReferenteContactService($em))->ensureFromPerson($id,$prospect,$prospect->get('assignedUserId'),false);
                }
                foreach ($opportunities as $opportunity) {
                    if ($opportunity->get('accountId') !== $id) continue;
                    foreach ($em->getRDBRepository('Quote')->where(['opportunityId'=>$opportunity->getId(),'accountId'=>$id])->find() as $quote) {
                        $amount=$quote->get('importoContratto')??$quote->get('amount');
                        $parts=[$quote->get('dateQuoted'),$legalName,trim((string)$quote->get('description'))];
                        if($amount!==null && $amount!=='')$parts[]='€. '.number_format((float)$amount,0,',','.');
                        $patch=['name'=>implode(' - ',array_filter($parts,static fn($v)=>$v!==null && $v!==''))];
                        $patch['contractingPartyType']='Account';
                        $patch['contractingPartyId']=$id;
                        $patch['contractingPartyNeedsReview']=false;
                        if($contact)$patch['shippingContactId']=$contact['id'];
                        $recordChange($quote,$patch);
                    }
                }
            }
        }
        foreach($opportunities as $opportunity){
            $copy=clone $opportunity;$copy->set('prospectName',$name);
            $result=(new \Espo\Custom\Services\OpportunityNameBuilder($em))->rebuild($copy,true);
            $recordChange($opportunity,['name'=>$result['name']]);
        }
        foreach($em->getRDBRepository('Appuntamento')->where(['prospectId'=>$prospect->getId()])->find() as $appointment){
            $description=(string)($appointment->get('description')??'');
            $updated=preg_replace_callback('/^Cliente: ([^\r\n]*)(?=\r?$)/m',static function($m)use($oldNames,$name){return in_array(trim($m[1]),$oldNames,true)?'Cliente: '.$name:$m[0];},$description);
            if($updated!==$description)$recordChange($appointment,['description'=>$updated]);
        }
        return $changes;
    }
}
