define('custom:views/quote/record/panels/provvigioni-complete',['views/record/panels/bottom'],function(Dep){
 return Dep.extend({
  template:'custom:quote/provvigioni-complete',
  setup:function(){Dep.prototype.setup.call(this);this.once('remove',()=>{this._requestsDisposed=true;});this.entries=[];this.listenTo(this.model,'sync update-related:provvigioni',this.loadRows);this.wait(this.loadRows());},
  loadRows:function(){if(this._requestsDisposed||!this.model.id)return Promise.resolve();
   if(this._loadRequest)return this._loadRequest;
   const model=this.model,id=model.id;
   if(!model._commissionRowsRequest)model._commissionRowsRequest=Espo.Ajax.getRequest('Quote/action/provvigioniVisibili',{id:this.model.id}).finally(()=>{delete model._commissionRowsRequest;});
   this._loadRequest=model._commissionRowsRequest.then(data=>{if(this._requestsDisposed||this.model.id!==id)return;
   this.error=null;const money=n=>n===null?'â€”':Number(n).toLocaleString('it-IT',{style:'currency',currency:'EUR'});
   this.entries=data.list.filter(r=>!r.archived).map(r=>({...r,url:r.archived?null:'#Provvigione/view/'+encodeURIComponent(r.id),base:money(r.importoBaseCalcolo),amount:money(r.importo),corrected:r.importoConsolidato!==null&&r.importoConsolidato!==undefined&&Math.abs(Number(r.importoConsolidato)-Number(r.importo))>=0.005?money(r.importoConsolidato):'—',rate:r.tassoProvvigioni===null?'â€”':Number(r.tassoProvvigioni).toLocaleString('it-IT')+'%',state:r.statoProvvigione?this.getLanguage().translateOption(r.statoProvvigione,'statoProvvigione','Provvigione'):'â€”',baseLabel:r.baseCalcolo?this.getLanguage().translateOption(r.baseCalcolo,'baseCalcolo','Provvigione'):'â€”',modified:r.modifiedAt?this.getDateTime().toDisplay(r.modifiedAt):'â€”'}));this.hasArchived=this.entries.some(r=>r.archived);
   const cents=n=>Math.round(Number(n||0)*100);
   const sums=this.entries.reduce((t,r)=>{
    const amount=cents(r.importo);
    const corrected=r.importoConsolidato!==null&&r.importoConsolidato!==undefined&&Math.abs(Number(r.importoConsolidato)-Number(r.importo))>=0.005;
    t.amount+=amount;
    t.corrected+=corrected?cents(r.importoConsolidato):0;
    t.effective+=corrected?cents(r.importoConsolidato):amount;
    t.hasCorrections=t.hasCorrections||corrected;
    return t;
   },{amount:0,corrected:0,effective:0,hasCorrections:false});
   this.totals={amount:money(sums.amount/100),corrected:money(sums.corrected/100),effective:money(sums.effective/100),hasCorrections:sums.hasCorrections};
   this.hasEntries=this.entries.length>0;if(this.isRendered())this.reRender();
  }).catch(()=>{if(this._requestsDisposed||this.model.id!==id)return;this.error='Impossibile caricare le provvigioni.';if(this.isRendered())this.reRender();}).finally(()=>{this._loadRequest=null;});return this._loadRequest;},
  actionRefresh:function(){return this.loadRows();},
  data:function(){return {entries:this.entries,error:this.error,hasArchived:this.hasArchived,totals:this.totals,hasEntries:this.hasEntries};}
 });
});
