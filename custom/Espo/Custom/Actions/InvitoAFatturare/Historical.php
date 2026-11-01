<?php
namespace Espo\Custom\Actions\InvitoAFatturare;
use Espo\Core\Api\Request;
use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\EntityManager;
use Espo\Custom\Services\InvitoWorkflow;
class Historical {
 public function __construct(private EntityManager $em,private Acl $acl,private InvitoWorkflow $flow) {}
 private function invoice(string $id) {
  $i=$this->em->getEntityById('InvitoAFatturare',$id);
  if(!$i || !$this->acl->checkEntity($i,'edit') || !$this->acl->checkScope('Provvigione','read') || !$this->acl->checkScope('InvitoAFatturareItem','create'))throw new Forbidden();
  if(!$this->flow->isDraft($i))throw new BadRequest('Selezione disponibile solo in Bozza o In verifica.');
  return $i;
 }
 public function search(Request $r): object {
  $i=$this->invoice((string)$r->getQueryParam('id'));
  foreach(['name','tipo','importo','importoConsolidato','statoProvvigione'] as $field)if(!$this->acl->checkField('Provvigione',$field,'read'))throw new Forbidden();
  $text=trim((string)$r->getQueryParam('text'));
  $query=$this->em->getQueryBuilder()->select()->from('Provvigione')->withDeleted()->where(['fornitorePartnerId'=>$i->get('fornitorePartnerId')])->where(['name*'=>'%'.$text.'%'])->order('name','ASC')->build();
  $list=[];
  foreach($this->em->getRDBRepository('Provvigione')->clone($query)->find() as $p){
   if(!$this->flow->isHistoricalCommission($p)||!$this->acl->checkEntity($p,'read'))continue;
   $q=$this->em->getEntityById('Quote',$p->get('contrattoId'));if(!$q||!$this->acl->checkEntity($q,'read'))continue;
   try{$this->flow->validateMember($i,$p);}catch(BadRequest $e){continue;}
   $s=$this->em->getPDO()->prepare("SELECT COUNT(*) FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 WHERE l.provvigione_id=? AND l.deleted=0 AND i.stato<>'Annullato' AND l.movimento='Addebito'");$s->execute([$p->getId()]);if($s->fetchColumn())continue;
   $list[]=['id'=>$p->getId(),'name'=>$p->get('name'),'state'=>$q->get('status').' / '.$q->get('statoContratto'),'amount'=>round((float)($p->get('importoConsolidato')??$p->get('importo')),2)];
   if(count($list)>=100)break;
  }
  return (object)['list'=>$list];
 }
 public function add(Request $r): object {
  $b=$r->getParsedBody();$i=$this->invoice((string)($b->id??''));$entries=$b->entries??null;
  if(!is_array($entries)||!count($entries)||count($entries)>100)throw new BadRequest('Selezionare da 1 a 100 voci.');
  $tm=$this->em->getTransactionManager();$tm->start();
  try{
   $this->flow->lock($i);$i=$this->invoice($i->getId());$count=0;
   foreach($entries as $entry){
    $id=$entry->id??'';$amount=$entry->amount??null;
    if(!is_string($id)||!preg_match('/^[a-zA-Z0-9]+$/',$id)||!is_numeric($amount)||!is_finite((float)$amount)||abs((float)$amount)>100000000||abs((float)$amount-round((float)$amount,2))>0.000001)throw new BadRequest('Importo o selezione non validi.');
    $s=$this->em->getPDO()->prepare('SELECT id FROM provvigione WHERE id=? FOR UPDATE');$s->execute([$id]);
    $p=$this->flow->invoiceCommission($id);
    if(!$p||!$this->flow->isHistoricalCommission($p)||!$this->acl->checkEntity($p,'read'))throw new Forbidden();
    $q=$this->em->getEntityById('Quote',$p->get('contrattoId'));if(!$q||!$this->acl->checkEntity($q,'read'))throw new Forbidden();
    $this->flow->validateMember($i,$p);
    $s=$this->em->getPDO()->prepare("SELECT COUNT(*) FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 WHERE l.provvigione_id=? AND l.deleted=0 AND i.stato<>'Annullato' AND l.movimento='Addebito'");$s->execute([$id]);if($s->fetchColumn())throw new BadRequest('Provvigione gia presente in un invito attivo.');
    $line=$this->em->getNewEntity('InvitoAFatturareItem');$line->set(['invitoId'=>$i->getId(),'contrattoId'=>$q->getId(),'provvigioneId'=>$id,'movimento'=>'Addebito','tipoLiquidazione'=>'Da verificare','importoConsolidato'=>round((float)$amount,2),'description'=>'Voce storica da contratto invalido o annullato.']);$this->em->saveEntity($line);$count++;
   }
   $tm->commit();return (object)['count'=>$count];
  }catch(\Throwable $e){$tm->rollback();throw $e;}
 }
}
