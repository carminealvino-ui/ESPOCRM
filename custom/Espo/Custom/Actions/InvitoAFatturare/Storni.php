<?php
namespace Espo\Custom\Actions\InvitoAFatturare;
use Espo\Core\Api\Request;
use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Services\InvitoWorkflow;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
class Storni
{
 public function __construct(private EntityManager $em, private InvitoWorkflow $workflow, private Acl $acl) {}
 private function invoice($id,string $action): Entity {
  if (!is_string($id)||!$id) throw new BadRequest('Salvare prima invito.');
  $i=$this->em->getEntityById('InvitoAFatturare',$id);
  if (!$i) throw new NotFound();
  if (!$this->acl->checkEntity($i,$action)) throw new Forbidden();
  return $i;
 }
 public function available(Request $r): object {
  $i=$this->invoice($r->getQueryParam('id'),'edit');
  if (!$this->acl->checkScope('InvitoAFatturareItem','create')) throw new Forbidden();
  $s=$this->em->getPDO()->prepare("SELECT l.id FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 WHERE l.deleted=0 AND l.movimento='Addebito' AND COALESCE(l.voce_automatica,0)=0 AND l.importo_consolidato<>0 AND i.stato='Pagato' AND i.fornitore_partner_id=? AND NOT EXISTS (SELECT 1 FROM invito_a_fatturare_item r JOIN invito_a_fatturare ri ON ri.id=r.invito_id AND ri.deleted=0 WHERE r.deleted=0 AND r.riga_origine_id=l.id AND ri.stato<>'Annullato')");
  $s->execute([$i->get('fornitorePartnerId')]);$ids=[];
  foreach ($s->fetchAll(\PDO::FETCH_COLUMN) as $id) {
   $l=$this->em->getEntityById('InvitoAFatturareItem',$id);
   $parent=$this->em->getEntityById('InvitoAFatturare',$l->get('invitoId'));
   if ($this->acl->checkEntity($l,'read')&&$this->acl->checkEntity($parent,'read')) $ids[]=$id;
  }
  return (object)['ids'=>$ids];
 }
 public function run(Request $r): object {
  $b=$r->getParsedBody();$i=$this->invoice($b->id??null,'edit');
  if (!is_array($b->ids??null)) throw new BadRequest('Selezionare le voci pagate da stornare.');
  return (object)['count'=>$this->add($i,$b->ids)];
 }
 public function add(Entity $i,array $ids): int {
  if (!$this->acl->checkEntity($i,'edit')||!$this->acl->checkScope('InvitoAFatturareItem','create')) throw new Forbidden();
  if (!$ids||count($ids)>200) throw new BadRequest('Selezionare da 1 a 200 voci.');
  foreach ($ids as $id) if (!is_string($id)||!preg_match('/^[a-zA-Z0-9]+$/',$id)) throw new BadRequest('Selezione non valida.');
  $ids=array_values(array_unique($ids));sort($ids);$tm=$this->em->getTransactionManager();$tm->start();
  try {
   $this->workflow->lock($i);$i=$this->invoice($i->getId(),'edit');
   if (!$this->workflow->isDraft($i)) throw new BadRequest('Lo storno richiede un invito in Bozza o In verifica.');
   foreach ($ids as $id) {
    $commission=$this->em->getEntityById('Provvigione',$id);
    if (!$commission||!$this->acl->checkEntity($commission,'read')) throw new Forbidden();
    if ($commission->get('fornitorePartnerId')!==$i->get('fornitorePartnerId')) throw new BadRequest('Fornitore non corrispondente.');
    $line=$this->em->getNewEntity('InvitoAFatturareItem');
    $line->set(['invitoId'=>$i->getId(),'movimento'=>'Storno','provvigioneId'=>$id,'tipoLiquidazione'=>'Da verificare']);
    $this->em->saveEntity($line);
   }
   $tm->commit();return count($ids);
  }catch(\Throwable $e){$tm->rollback();throw $e;}
 }
 public function summary(Request $r): object {return $this->summarize($this->invoice($r->getQueryParam('id'),'read'));}
 public function isPlusMinus(Entity $line): bool {
  return $line->get('movimento')!=='Storno' && preg_match('/Plus|Minus/i',(string)$line->get('tipo'))===1;
 }
 public function bucket(Entity $line): string {
  if($this->isPlusMinus($line))return 'plusMinus';
  if($line->get('movimento')!=='Storno' && preg_match('/^(?!.*ordine\s*completo)(Bonus|Premio|Gara)/i',(string)$line->get('tipo')))return 'premi';
  return 'other';
 }
 public function summarize(Entity $i): object {
  if (!$this->acl->checkEntity($i,'read')||!$this->acl->checkScope('InvitoAFatturareItem','read')) throw new Forbidden();
  $t=['plus'=>0,'minus'=>0,'saldoPlusMinus'=>0,'premi'=>0,'storni'=>0,'altre'=>0,'totale'=>0];
  $clients=[];$quotes=[];$commissions=[];
  foreach($this->workflow->rows($i) as $l){
   if (!$this->acl->checkEntity($l,'read')) throw new Forbidden();
   $amount=(int)round(100*(float)($l->get('importoConsolidato')??$l->get('importo')??0));$type=(string)$l->get('tipo');
   if($l->get('movimento')==='Storno')$key='storni';
   elseif(stripos($type,'Plus')!==false||stripos($type,'Minus')!==false)$key=$amount<0?'minus':'plus';
   elseif(preg_match('/^(?!.*ordine\s*completo)(Bonus|Premio|Gara)/i',$type))$key='premi';else $key='altre';
   $t[$key]+=$amount;$t['totale']+=$amount;
   $client=$this->client($l);$clientKey=$this->groupKey($l);$clientName=$client['name'];
   if (!isset($clients[$clientKey])) $clients[$clientKey]=['id'=>$clientKey,'name'=>$clientName,'cents'=>0,'original'=>0,'count'=>0,'editable'=>true,'versions'=>[],'contracts'=>[],'premiCents'=>0,'premiCount'=>0,'premiEditable'=>true,'plusMinusOriginalCents'=>0,'plusMinusCents'=>0,'plusMinusCount'=>0,'otherCount'=>0,'plusMinusEditable'=>true,'otherEditable'=>true];
   if (isset($client['contract'])) $clients[$clientKey]['contracts'][$client['contract']['id']]=$client['contract'];
   $clients[$clientKey]['cents']+=$amount;$clients[$clientKey]['count']++;
   $clients[$clientKey]['original']+=(int)round(100*(float)($l->get('importoOriginaleInvito') ?? $l->get('importoConsolidato') ?? 0));
   $clients[$clientKey]['editable']=$clients[$clientKey]['editable'] && $this->workflow->isDraft($i) && $this->acl->checkEntity($i,'edit') && $this->acl->checkEntity($l,'edit') && !$l->get('voceAutomatica') && !$l->get('rigaOrigineId') && isset($client['contract']);
   $bucket=$this->bucket($l);
   if($bucket==='plusMinus'){
    $base=$l->get('importoBaseCalcolo');
    if($base===null && $l->get('provvigioneId'))$base=$this->em->getEntityById('Provvigione',$l->get('provvigioneId'))?->get('importoBaseCalcolo');
    $gross=(int)round(100*(float)($base??0));
    if(stripos((string)$l->get('tipo'),'Minus')!==false)$gross=-abs($gross);
    $clients[$clientKey]['plusMinusOriginalCents']+=$gross;
   }
   $clients[$clientKey][$bucket.'Count']++;
   if($bucket!=='other')$clients[$clientKey][$bucket.'Cents']+=$amount;
   $clients[$clientKey][$bucket.'Editable']=$clients[$clientKey][$bucket.'Editable'] && $this->workflow->isDraft($i) && $this->acl->checkEntity($i,'edit') && $this->acl->checkEntity($l,'edit') && !$l->get('voceAutomatica') && !$l->get('rigaOrigineId') && isset($client['contract']);
   $clients[$clientKey]['versions'][$l->getId()]=[$amount,$l->get('importoOriginaleInvito'),$l->get('modifiedAt')];

  }
  $t['saldoPlusMinus']=$t['plus']+$t['minus'];
  $result=array_map(fn($v)=>$v/100,$t);
  uasort($clients,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
  $result['clienti']=array_values(array_map(function($c){ksort($c['versions']);return ['id'=>$c['id'],'name'=>$c['name'],'total'=>$c['cents']/100,'plusMinusOriginal'=>$c['plusMinusOriginalCents']/100,'plusMinus'=>$c['plusMinusCents']/100,'premi'=>$c['premiCents']/100,'premiEditable'=>$c['premiEditable'] && $c['premiCount']>0,'other'=>($c['cents']-$c['plusMinusCents']-$c['premiCents'])/100,'plusMinusEditable'=>$c['plusMinusEditable'] && $c['plusMinusCount']>0,'otherEditable'=>$c['otherEditable'] && $c['otherCount']>0,'original'=>$c['original']/100,'difference'=>($c['cents']-$c['original'])/100,'count'=>$c['count'],'contracts'=>array_values($c['contracts']),'editable'=>$c['editable'],'version'=>hash('sha256',json_encode($c['versions']))];},$clients));
  return (object)$result;
 }
 public function groupKey(Entity $line): string {
  $client=$this->client($line);
  return isset($client['contract']) ? $client['id'].':'.$client['contract']['id'] : 'missing:'.$line->getId();
 }
 public function client(Entity $line): array {
  if ($line->get('voceAutomatica')==='saldoDocumentale') return ['id'=>'documento:'.$line->get('invitoId'),'name'=>'Saldo Plus/Minus da prospetto GDL'];
  $qid=$line->get('contrattoId');
  if (!$qid && $line->get('provvigioneId')) $qid=$this->em->getEntityById('Provvigione',$line->get('provvigioneId'))?->get('contrattoId');
  $q=$qid?$this->em->getEntityById('Quote',$qid):null;
  if ($q && $this->acl->checkEntity($q,'read') && $q->get('accountId')) {
   $name=$q->get('accountName') ?: 'Cliente senza nome';
   // Historical invoice identity follows the original contract title, not a renamed customer account.
   if (($q->get('status')==='Invalido' || $q->get('statoContratto')==='Annullato')
       && preg_match('/^\\d{4}-\\d{2}-\\d{2}\\s+-\\s+(.+?)\\s+-\\s+/u',(string)$q->get('name'),$match)) {
    $name=trim($match[1]);
   }
   return ['id'=>$q->get('accountId'),'name'=>$name,'contract'=>['id'=>$q->getId(),'name'=>$q->get('name') ?: 'Apri contratto']];
  }
  return ['id'=>'missing','name'=>'Cliente non collegato'];
 }

}
