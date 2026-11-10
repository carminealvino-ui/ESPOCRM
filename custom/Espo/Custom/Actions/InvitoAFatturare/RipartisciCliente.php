<?php
namespace Espo\Custom\Actions\InvitoAFatturare;
use Espo\Core\Api\Request;
use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Services\InvitoWorkflow;
use Espo\ORM\EntityManager;
class RipartisciCliente
{
 public function __construct(private EntityManager $em, private InvitoWorkflow $workflow, private Storni $summary, private Acl $acl) {}
 public function run(Request $r): object {
  $b=$r->getParsedBody();
  return $this->apply($b->id??'', $b->clientId??'', $b->amount??null, $b->version??'', $b->scope??'all');
 }
 /** Allocate integer cents by largest remainder; stable ordering resolves equal fractions. */
 public static function allocate(array $weights,int $target,bool $allowSignChange=false): array {
  $sum=array_sum($weights);
  if (!$sum) {
   if ($target===0) return $weights;
   throw new BadRequest('Totale iniziale nullo: modificare le singole voci.');
  }
  if (!$allowSignChange && $target && (($target<0)!==($sum<0))) throw new BadRequest('Il totale fornitore non puo invertire il segno del totale iniziale.');
  $out=[];$fractions=[];
  foreach($weights as $id=>$weight){$raw=$weight*$target/$sum;$out[$id]=(int)floor($raw);$fractions[$id]=$raw-$out[$id];}
  arsort($fractions,SORT_NUMERIC);$remaining=$target-array_sum($out);
  foreach($fractions as $id=>$unused){if($remaining--<=0)break;$out[$id]++;}
  return $out;
 }
 private function saveManualPlusMinus(\Espo\ORM\Entity $i,array $group,int $cents,array $lines): void {
  if(!$i->get('arielRegolamento') || $i->get('arielIncludiSaldoMensile') || count($group['contracts'])!==1)throw new BadRequest('Inserire il dettaglio Plus/Minus separatamente dal saldo automatico.');
  $q=$this->em->getEntityById('Quote',$group['contracts'][0]['id']);
  if(!$q || !$this->acl->checkEntity($q,'read') || $q->get('fornitorePartnerId')!==$i->get('fornitorePartnerId'))throw new Forbidden();
  $line=$lines?reset($lines):null;
  if(!$line && !$cents)return;
  if(!$line && !$this->acl->checkScope('InvitoAFatturareItem','create'))throw new Forbidden();
  $key='saldoDocumentale:'.$q->getId();
  if(!$line && $this->em->getRDBRepository('InvitoAFatturareItem')->where(['invitoId'=>$i->getId(),'voceAutomatica'=>$key])->findOne())throw new BadRequest('Voce Plus/Minus già presente. Aggiornare il riepilogo.');
  $line??=$this->em->getNewEntity('InvitoAFatturareItem');$new=$line->isNew();
  $gross=(float)$q->get('minusPlus');
  $matched=(int)round($gross*35)===$cents;
  if(!$matched)$gross=round($cents/35,2);
  $line->set(['invitoId'=>$i->getId(),'contrattoId'=>$q->getId(),'voceAutomatica'=>$key,'movimento'=>'Addebito',
   'tipo'=>$cents<0?'Minus Provvigionale':'Plus Provvigionale','baseCalcolo'=>$cents<0?'Minusvalenza':'Plusvalenza',
   'importoBaseCalcolo'=>$gross,'importoBaseCalcoloCurrency'=>'EUR','tassoProvvigioni'=>35,
   'importoConsolidato'=>$cents/100,'importoConsolidatoCurrency'=>'EUR','tipoLiquidazione'=>'Da verificare',
   'name'=>'Plus/Minus da invito - '.$q->get('name'),
   'description'=>'[PLUSMINUS-MANUALE-INVITO] Importo netto riconosciuto dal fornitore, inserito dal riepilogo. '.($matched?'Lordo coerente con il contratto.':'Lordo equivalente ricavato dal netto al 35%; da confrontare con il prospetto GDL.')]);
  if($new)$line->set(['importoOriginaleInvito'=>0,'importoOriginaleInvitoCurrency'=>'EUR','importoPrevistoRiga'=>0,'importoPrevistoRigaCurrency'=>'EUR','assignedUserId'=>$i->get('assignedUserId')]);
  $this->em->saveEntity($line,['skipHooks'=>true,'skipFormula'=>true,'silent'=>true]);
  $this->workflow->totals($i);$this->em->saveEntity($i);
 }
 public function apply(string $id,string $clientId,mixed $amount,string $version,string $scope='all'): object {
  if(!in_array($scope,['all','other','plusMinus','premi'],true))throw new BadRequest('Ripartizione non valida.');
  if (!is_numeric($amount)||!is_finite((float)$amount)||abs((float)$amount)>100000000||abs((float)$amount-round((float)$amount,2))>0.000001) throw new BadRequest('Inserire un importo con massimo due decimali.');
  $tm=$this->em->getTransactionManager();$tm->start();
  try {
   $i=$this->em->getEntityById('InvitoAFatturare',$id);if(!$i)throw new NotFound();
   if(!$this->acl->checkEntity($i,'edit'))throw new Forbidden();
   $this->workflow->lock($i);$i=$this->em->getEntityById('InvitoAFatturare',$id);
   if(!$this->workflow->isDraft($i))throw new BadRequest('Modifica disponibile solo in Bozza o In verifica.');
   $group=null;foreach($this->summary->summarize($i)->clienti as $c)if($c['id']===$clientId)$group=$c;
   if(!$group||!$group[$scope==='all'?'editable':$scope.'Editable'])throw new BadRequest('Le voci di questo contratto non sono modificabili dal riepilogo.');
   if(!hash_equals($group['version'],$version))throw new BadRequest('Le voci sono cambiate. Aggiornare il riepilogo prima di salvare.');
   $lines=[];$weights=[];$onlyAdjustments=true;
   foreach($this->workflow->rows($i) as $line){
    if($this->summary->groupKey($line)!==$clientId)continue;
    if($scope!=='all' && $this->summary->bucket($line)!==$scope)continue;
    if(!$this->acl->checkEntity($line,'edit'))throw new Forbidden();
    $lines[$line->getId()]=$line;
    $onlyAdjustments=$onlyAdjustments && $line->get('movimento')==='Storno' && !$line->get('rigaOrigineId');
    $weights[$line->getId()]=(int)round(100*(float)($line->get('importoOriginaleInvito')??$line->get('importoConsolidato')));
   }
   if($scope==='plusMinus' && (!$lines || (count($lines)===1 && $this->summary->isEditableManualPlusMinus(reset($lines))))) {
    $this->saveManualPlusMinus($i,$group,(int)round(100*(float)$amount),$lines);
    $result=$this->summary->summarize($i);$tm->commit();return $result;
   }
   ksort($weights);$allocated=self::allocate($weights,(int)round(100*(float)$amount),$onlyAdjustments);
   foreach($allocated as $lineId=>$cents){
    $line=$lines[$lineId];
    if((int)round(100*(float)$line->get('importoConsolidato'))===$cents)continue;
    $line->set('importoConsolidato',$cents/100);
    // Preserve advances; otherwise keep discrepancies open for reconciliation.
    if($line->get('tipoLiquidazione')!=='Acconto'||$cents===0)$line->set('tipoLiquidazione','Da verificare');
    $this->em->saveEntity($line);
   }
   $result=$this->summary->summarize($i);
   $tm->commit();return $result;
  }catch(\Throwable $e){$tm->rollback();throw $e;}
 }
}
