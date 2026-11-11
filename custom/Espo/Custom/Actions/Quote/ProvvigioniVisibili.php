<?php
namespace Espo\Custom\Actions\Quote;
use Espo\Core\Api\Request;
use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;
class ProvvigioniVisibili
{
 public function __construct(private EntityManager $em,private Acl $acl) {}
 public function run(Request $r): object{return $this->get($r->getQueryParam('id'));}
 public function get($id): object{
  if(!is_string($id)||!$id)throw new BadRequest();
  $q=$this->em->getEntityById('Quote',$id);if(!$q)throw new NotFound();
  if(!$this->acl->checkEntity($q,'read')||!$this->acl->checkScope('Provvigione','read'))throw new Forbidden();
  $fields=['name','baseCalcolo','importoBaseCalcolo','tassoProvvigioni','importo','importoConsolidato','statoProvvigione','modifiedAt'];
  foreach($fields as $field)if(!$this->acl->checkField('Provvigione',$field,'read'))throw new Forbidden();
  $query=$this->em->getQueryBuilder()->select()->from('Provvigione')->withDeleted()->where(['contrattoId'=>$id])->order('deleted','ASC')->order('createdAt','DESC')->build();
  $out=[];
  $activeTypes=[];
  foreach($this->em->getRDBRepository('Provvigione')->clone($query)->find() as $p){
   if(!$p->get('deleted'))$activeTypes[(string)$p->get('tipo')]=true;
   if(!$this->acl->checkEntity($p,'read'))continue;
   $row=['id'=>$p->getId(),'archived'=>(bool)$p->get('deleted')];
   foreach($fields as $field)$row[$field]=$p->get($field);
   $row['name']=preg_replace('/bonus ordine completo/i','PROVVIGIONE ORDINE COMPLETO',(string)$row['name']);
   $out[]=$row;
  }
  return (object)['list'=>array_merge($out,$this->invoicedPlusMinus($id,$activeTypes))];
 }

 private function invoicedPlusMinus(string $contractId,array $activeTypes): array
 {
  $fields=[
   'InvitoAFatturareItem'=>['name','contratto','provvigione','invito','tipo','movimento','voceAutomatica','baseCalcolo','importoBaseCalcolo','tassoProvvigioni','importoConsolidato'],
   'InvitoAFatturare'=>['name','stato'],
  ];
  foreach($fields as $scope=>$names){
   if(!$this->acl->checkScope($scope,'read'))return [];
   foreach($names as $name)if(!$this->acl->checkField($scope,$name,'read'))return [];
  }
  $lines=$this->em->getRDBRepository('InvitoAFatturareItem')->where([
   'contrattoId'=>$contractId,
   'tipo'=>['Plus Provvigionale','Minus Provvigionale'],
   'movimento'=>'Addebito',
   'provvigioneId'=>null,
  ])->find();
  $result=[];
  foreach($lines as $line){
   // A matching active commission already incorporates this line in its consolidated amount.
   if(isset($activeTypes[$line->get('tipo')]) ||
      !str_starts_with((string)$line->get('voceAutomatica'),'saldoDocumentale:') ||
      !$this->acl->checkEntity($line,'read'))continue;
   $invoice=$line->get('invitoId') ? $this->em->getEntityById('InvitoAFatturare',$line->get('invitoId')) : null;
   if(!$invoice || !in_array($invoice->get('stato'),['Definitivo','Pagato'],true) ||
      !$this->acl->checkEntity($invoice,'read'))continue;
   $result[]=[
    'id'=>$line->getId(),'entityType'=>'InvitoAFatturareItem','archived'=>false,
    'name'=>$line->get('name'),'baseCalcolo'=>$line->get('baseCalcolo'),
    'importoBaseCalcolo'=>$line->get('importoBaseCalcolo'),'tassoProvvigioni'=>$line->get('tassoProvvigioni'),
    'importo'=>null,'importoConsolidato'=>$line->get('importoConsolidato'),
    'invoiceStatus'=>($invoice->get('stato')==='Pagato'?'Pagato':'Fatturato').' · '.$invoice->get('name'),
   ];
  }
  return $result;
 }
}
