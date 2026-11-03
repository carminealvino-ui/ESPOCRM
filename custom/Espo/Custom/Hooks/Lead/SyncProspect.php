<?php
namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/** Propagate explicit identity edits only through an established relationship. */
class SyncProspect implements AfterSave
{
    public static int $order = 40;
    public function __construct(private EntityManager $em) {}

    public function afterSave(Entity $lead, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('skipLeadProspectSync') || !$lead->getId()) return;
        $fields=['salutationName','firstName','lastName','name','b2B','referenteAziendale',
            'phoneNumber','emailAddress','addressStreet','addressCity','addressPostalCode',
            'addressState','addressCountry','cAPId','cAPName','partitaIVA','insegna','website'];
        $patch=[];
        foreach ($fields as $field) if ($lead->isAttributeChanged($field)) $patch[$field]=$lead->get($field);
        foreach (['phoneNumber','emailAddress'] as $field) {
            $data=$field.'Data';
            if ($lead->has($data) && $lead->isAttributeChanged($data)) {
                $patch[$data]=$lead->get($data);
                $patch[$field]=$lead->get($field);
            }
        }
        if (!$patch) return;
        $targets=[];
        $prospectId=$lead->get('prospectId');
        if ($prospectId) {
            $p=$this->em->getEntityById('Prospect',$prospectId);
            if ($p && !$p->get('deleted') && (!$p->get('leadId') || $p->get('leadId')===$lead->getId())) $targets[$p->getId()]=$p;
        }
        foreach ($this->em->getRDBRepository('Prospect')->where(['leadId'=>$lead->getId()])->find() as $p) {
            if (!$prospectId || $prospectId===$p->getId()) $targets[$p->getId()]=$p;
        }
        foreach ($targets as $prospect) {
            $actual=[];
            foreach ($patch as $field=>$value) if ($prospect->get($field)!==$value) $actual[$field]=$value;
            if (!$actual) continue;
            $prospect->set($actual);
            // Run normal Prospect normalization and appointment hooks; the reverse
            // identity hook saves its Lead with skipHooks, preventing recursion.
            $this->em->saveEntity($prospect,['skipLeadProspectSync'=>true,'silent'=>true]);
        }
    }
}
