<?php
namespace Espo\Custom\Hooks\InvitoAFatturareItem;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Custom\Services\InvitoWorkflow;

class Ledger implements \Espo\Core\Hook\Hook\BeforeSave, \Espo\Core\Hook\Hook\AfterSave, \Espo\Core\Hook\Hook\BeforeRemove, \Espo\Core\Hook\Hook\AfterRemove
{
    public static int $order = 5;
    public function __construct(private EntityManager $em, private InvitoWorkflow $flow, private Acl $acl) {}

    private function draft(Entity $line): Entity
    {
        $id=$line->get('invitoId');
        if (!$id) throw new BadRequest('Specificare l invito a fatturare.');
        $i=$this->em->getEntityById('InvitoAFatturare',$id);
        if (!$i) throw new BadRequest('Invito non trovato.');
        if (!$this->acl->checkEntity($i,'edit')) throw new Forbidden();
        $this->flow->lock($i);
        $i=$this->em->getEntityById('InvitoAFatturare',$id);
        if (!$this->flow->isDraft($i)) throw new BadRequest('Per modificare le voci, salvare prima l invito in Bozza o In verifica.');
        return $i;
    }

    public function beforeSave(Entity $line, SaveOptions $options): void
    {
        if ($line->get('voceAutomatica') || (!$line->isNew() && $line->getFetched('voceAutomatica'))) throw new BadRequest('La voce mensile si aggiorna dal riepilogo dell invito e non Ã¨ modificabile manualmente.');
        if (!$line->isNew()) {
            foreach (['invitoId','provvigioneId','movimento','rigaOrigineId'] as $field) {
                if ($line->isAttributeChanged($field)) throw new BadRequest('Per cambiare il movimento, rimuovere la riga dalla bozza e aggiungerla nuovamente.');
            }
        }
        $i=$this->draft($line);
        $movement=$line->get('movimento') ?: 'Addebito';
        if (!in_array($movement,['Addebito','Storno'],true)) throw new BadRequest('Operazione non valida.');
        $origin=null;
        if ($movement==='Storno' && $line->get('rigaOrigineId')) {
            if (!$line->get('rigaOrigineId')) throw new BadRequest('Selezionare l addebito originario da stornare.');
            $s=$this->em->getPDO()->prepare('SELECT id FROM invito_a_fatturare_item WHERE id=? AND deleted=0 FOR UPDATE');
            $s->execute([$line->get('rigaOrigineId')]);
            $origin=$this->em->getEntityById('InvitoAFatturareItem',$line->get('rigaOrigineId'));
            if (!$origin || $origin->get('voceAutomatica') || $origin->get('movimento')!=='Addebito') throw new BadRequest('Selezionare una riga provvigionale originaria, non uno storno.');
            if (!$this->acl->checkEntity($origin,'read')) throw new Forbidden();
            $originalInvoice=$this->em->getEntityById('InvitoAFatturare',$origin->get('invitoId'));
            if (!$originalInvoice || !$this->flow->isFinal($originalInvoice)) throw new BadRequest('L addebito da stornare deve appartenere a un invito Definitivo o Pagato.');
            if (!$this->acl->checkEntity($originalInvoice,'read')) throw new Forbidden();
            $active=$this->em->getPDO()->prepare("SELECT l.id FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 WHERE l.riga_origine_id=? AND l.deleted=0 AND i.stato<>'Annullato' FOR UPDATE");
            $active->execute([$origin->getId()]);
            foreach ($active->fetchAll(\PDO::FETCH_COLUMN) as $otherId) {
                if ($otherId!==$line->getId()) throw new BadRequest('Questo addebito ha gia uno storno in un altro invito attivo.');
            }
            $line->set('provvigioneId',$origin->get('provvigioneId'));
        } elseif ($line->get('rigaOrigineId')) {
            throw new BadRequest('L addebito originario va selezionato solo per uno storno.');
        }
        $p=$line->get('provvigioneId') ? $this->flow->invoiceCommission($line->get('provvigioneId')) : null;
        if (!$p) throw new BadRequest('Selezionare una provvigione.');
        if (!$this->acl->checkEntity($p,'read')) throw new Forbidden();
        $s=$this->em->getPDO()->prepare('SELECT id FROM provvigione WHERE id=? FOR UPDATE');$s->execute([$p->getId()]);
        if ($line->isNew() && $movement==='Addebito' && in_array($p->getId(),$this->flow->reservedCommissionIds((string)$i->get('fornitorePartnerId')),true)) throw new BadRequest('Provvigione gia inserita in un invito attivo. Sono disponibili solo i residui degli acconti.');
        if ($movement==='Addebito') $this->flow->validateMember($i,$p);
        elseif ($p->get('fornitorePartnerId')!==$i->get('fornitorePartnerId')) throw new BadRequest('Fornitore non corrispondente.');
        if ($movement==='Storno' && !$origin) {
            $dupe=$this->em->getPDO()->prepare("SELECT l.id FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 WHERE l.provvigione_id=? AND l.movimento='Storno' AND l.deleted=0 AND i.stato<>'Annullato' FOR UPDATE");
            $dupe->execute([$p->getId()]);
            foreach ($dupe->fetchAll(\PDO::FETCH_COLUMN) as $other) {
                if ($line->isNew() || $other!==$line->getId()) throw new BadRequest('Questa provvigione ha gia uno storno in un invito attivo.');
            }
        }
        if ($movement==='Addebito') {
            foreach ($this->em->getRDBRepository('InvitoAFatturareItem')->where(['invitoId'=>$i->getId(),'provvigioneId'=>$p->getId(),'movimento'=>'Addebito'])->find() as $same) {
                if ($same->getId()!==$line->getId()) throw new BadRequest('La provvigione Ã¨ gia presente in questo invito. PuÃ² essere aggiunta a un altro invito.');
            }
        }
        $choice=$line->get('tipoLiquidazione') ?: 'Saldo';
        if (!in_array($choice,['Acconto','Saldo','Chiusura con differenza','Da verificare'],true)) throw new BadRequest('Tipo di liquidazione non valido.');
        if ($line->isNew() && $choice === 'Saldo' && in_array($p->get('tipo'), ['Plus Provvigionale','Minus Provvigionale'], true)) {
            $choice = 'Da verificare';
        }
        $line->set('tipoLiquidazione',$choice);
        if ($line->isNew()) {
            $source=$origin ?: $p;
            $amount=$source->get('importoConsolidato') ?? $source->get('importo');
            if ($movement==='Storno' && !$origin) $amount=$line->get('importoConsolidato')!==null ? -(float)$line->get('importoConsolidato') : abs((float)$amount);
            if ($amount===null) throw new BadRequest('La provvigione non ha un importo.');
            if ($movement==='Addebito') {
                $used=$this->em->getPDO()->prepare("SELECT COALESCE(SUM(l.importo_consolidato),0) FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id WHERE l.provvigione_id=? AND l.movimento='Addebito' AND l.deleted=0 AND i.deleted=0 AND i.stato<>'Annullato'");
                $used->execute([$p->getId()]);
                $amount=$line->get('importoConsolidato') ?? round((float)$amount-(float)$used->fetchColumn(),2);
            }
            $line->set([
                'movimento'=>$movement,
                'name'=>($movement==='Storno'?'RETTIFICA - ':'').$p->get('name'),
                'tipo'=>$source->get('tipo'), 'baseCalcolo'=>$source->get('baseCalcolo'),
                'importoBaseCalcolo'=>$source->get('importoBaseCalcolo'), 'importoBaseCalcoloCurrency'=>'EUR',
                'tassoProvvigioni'=>$source->get('tassoProvvigioni'),
                'importoConsolidato'=>round((float)$amount*($movement==='Storno'?-1:1),2),
                'importoConsolidatoCurrency'=>'EUR',
                'assignedUserId'=>$i->get('assignedUserId'),
            ]);
        } else {
            foreach (['name','tipo','baseCalcolo','importoBaseCalcolo','importoBaseCalcoloCurrency','tassoProvvigioni','importoConsolidatoCurrency'] as $field) {
                if ($line->isAttributeChanged($field)) throw new BadRequest('L importo e i dati della riga derivano dalla provvigione originaria.');
            }
        }
        if (!$line->isNew()) {
            $original=$line->getFetched('importoOriginaleInvito');
            if ($original===null) $original=$line->getFetched('importoConsolidato');
            $line->set(['importoOriginaleInvito'=>$original,'importoOriginaleInvitoCurrency'=>'EUR','differenzaFornitore'=>round((float)$line->get('importoConsolidato')-(float)$original,2),'differenzaFornitoreCurrency'=>'EUR']);
        }
        if ($movement==='Storno' && !$origin) {
            $amount=$line->get('importoConsolidato');
            if (!is_numeric($amount) || !is_finite((float)$amount) || abs(round((float)$amount,2)-(float)$amount)>0.000001) throw new BadRequest('Inserire un importo di rettifica positivo o negativo con massimo due decimali.');
            $line->set('tipoLiquidazione','Da verificare');
            $expected=$line->get('importoOriginaleInvito') ?? -abs((float)($p->get('importoConsolidato') ?? $p->get('importo')));
            $line->set(['importoPrevistoRiga'=>$expected,'importoPrevistoRigaCurrency'=>'EUR','differenzaImporto'=>round((float)$amount-(float)$expected,2),'differenzaImportoCurrency'=>'EUR']);
            return;
        }
        if ($movement==='Storno') {
            if ((int)round(100*(float)$line->get('importoConsolidato')) !== -(int)round(100*(float)$origin->get('importoConsolidato'))) throw new BadRequest('Lo storno deve corrispondere alla riga originaria.');
            $line->set('tipoLiquidazione',$origin->get('tipoLiquidazione') ?: 'Saldo');
            return;
        }
        $this->flow->validateAllocation($line,$p);
    }

    public function afterSave(Entity $line, SaveOptions $options): void { $this->refresh($line); }
    public function beforeRemove(Entity $line, RemoveOptions $options): void { if ($line->get('voceAutomatica')) throw new BadRequest('La voce mensile si gestisce dal riepilogo dell invito.'); $this->draft($line); }
    public function afterRemove(Entity $line, RemoveOptions $options): void { $this->refresh($line); }
    private function refresh(Entity $line): void
    {
        $i=$this->em->getEntityById('InvitoAFatturare',$line->get('invitoId'));
        if ($i) $this->em->saveEntity($i);
        $p=$this->flow->invoiceCommission($line->get('provvigioneId'));
        if ($p) $this->flow->refreshCommission($p);
    }
}
