<?php
namespace Espo\Custom\Actions\Quote;
use Espo\Core\Api\Request;
use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;
class InvitiCollegati
{
 public function __construct(private EntityManager $em,private Acl $acl) {}
 public function run(Request $r): object {return $this->get($r->getQueryParam('id'));}
 public function get($id): object {
  if(!is_string($id)||!$id)throw new BadRequest();
  $q=$this->em->getEntityById('Quote',$id);if(!$q)throw new NotFound();
  if(!$this->acl->checkEntity($q,'read'))throw new Forbidden();
  foreach(['InvitoAFatturare'=>['name','dataInvito','meseCompetenza','stato','numeroFattura'], 'InvitoAFatturareItem'=>['contratto','provvigione','invito','importo','importoConsolidato','tipo','movimento']] as $scope=>$fields){
   if(!$this->acl->checkScope($scope,'read'))throw new Forbidden();
   foreach($fields as $field)if(!$this->acl->checkField($scope,$field,'read'))throw new Forbidden();
  }
  $ids=[];
  $expected=$this->acl->checkScope('Provvigione','read') && $this->acl->checkField('Provvigione','importo','read') ? 0 : null;
  $cancelled=in_array($q->get('statoContratto'),['Annullato','Recesso'],true);
  foreach($this->em->getRDBRepository('Provvigione')->where(['contrattoId'=>$id])->find() as $p){
   if(!$this->acl->checkEntity($p,'read'))continue;
   $ids[]=$p->getId();
   if($expected!==null && !$cancelled)$expected+=(int)round(100*(float)$p->get('importo'));
  }
  $conditions=[['contrattoId'=>$id]];if($ids)$conditions[]=['provvigioneId'=>$ids];
  $out=[];
  foreach($this->em->getRDBRepository('InvitoAFatturareItem')->where(['OR'=>$conditions])->find() as $line){
   if(!$this->acl->checkEntity($line,'read'))continue;
   $invoiceId=$line->get('invitoId');if(!$invoiceId)continue;
   $i=$this->em->getEntityById('InvitoAFatturare',$invoiceId);if(!$i||!$this->acl->checkEntity($i,'read'))continue;
   if(!isset($out[$invoiceId]))$out[$invoiceId]=['id'=>$invoiceId,'name'=>$i->get('name'),'date'=>$i->get('dataInvito'),'month'=>$i->get('meseCompetenza'),'state'=>$i->get('stato'),'invoice'=>$i->get('numeroFattura'),'count'=>0,'cents'=>0,'items'=>[]];
   $out[$invoiceId]['count']++;
   $tipo=(string)$line->get('tipo');
   $tipo=preg_replace('/bonus ordine completo/i','Provvigione ordine completo',$tipo);
   $out[$invoiceId]['items'][]=['id'=>$line->getId(),'label'=>$tipo ?: 'Provvigione',
       'operation'=>$line->get('movimento')==='Storno' ? 'Rettifica' : 'Provvigione',
       'cents'=>(int)round(100*(float)($line->get('importoConsolidato')??$line->get('importo')))];
   $out[$invoiceId]['cents']+=(int)round(100*(float)($line->get('importoConsolidato')??$line->get('importo')));
  }
  $out=array_values($out);usort($out,fn($a,$b)=>strcmp($b['date']??'',$a['date']??'')?:strcmp($a['name'],$b['name']));
  $billed=0;$paid=0;
  foreach($out as $invoice){
   if($invoice['state']==='Pagato')$paid+=$invoice['cents'];
   if($invoice['state']==='Pagato' || ($invoice['state']==='Definitivo' && trim((string)$invoice['invoice'])!=='')) {
    $billed+=$invoice['cents'];
   }
  }
  return (object)['list'=>$out,'totals'=>['expected'=>$expected,'billed'=>$billed,'paid'=>$paid]];
 }
}
