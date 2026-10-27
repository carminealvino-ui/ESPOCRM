define('custom:views/quote/record/panels/provvigioni-archiviate',['views/record/panels/side'],function(Dep){
 return Dep.extend({
  template:'custom:quote/provvigioni-archiviate',
  events:{'click [data-archive-index]':function(e){
   const entry=this.entries[Number(e.currentTarget.getAttribute('data-archive-index'))];
   if(entry)this.createView('archivedCommission','custom:views/quote/modals/provvigione-archiviata',{entry}).then(view=>view.render());
  }},
  setup:function(){Dep.prototype.setup.call(this);this.once('remove',()=>{this._requestsDisposed=true;});this.entries=[];this.listenTo(this.model,'sync update-related:provvigioni',this.loadRows);this.wait(this.loadRows());},
  loadRows:function(){if(this._requestsDisposed||!this.model.id)return Promise.resolve();
   if(this._loadRequest)return this._loadRequest;
   const model=this.model,id=model.id;
   if(!model._commissionRowsRequest)model._commissionRowsRequest=Espo.Ajax.getRequest('Quote/action/provvigioniVisibili',{id:this.model.id}).finally(()=>{delete model._commissionRowsRequest;});
   this._loadRequest=model._commissionRowsRequest.then(data=>{if(this._requestsDisposed||this.model.id!==id)return;
   this.error=null;const money=n=>n===null?'—':Number(n).toLocaleString('it-IT',{style:'currency',currency:'EUR'});
   this.entries=data.list.filter(r=>r.archived).sort((a,b)=>(b.modifiedAt||'').localeCompare(a.modifiedAt||'')).map((r,index)=>({...r,index,
    base:money(r.importoBaseCalcolo),amount:money(r.importo),rate:r.tassoProvvigioni===null?'—':Number(r.tassoProvvigioni).toLocaleString('it-IT')+'%',
    state:r.statoProvvigione?this.getLanguage().translateOption(r.statoProvvigione,'statoProvvigione','Provvigione'):'—',
    baseLabel:r.baseCalcolo?this.getLanguage().translateOption(r.baseCalcolo,'baseCalcolo','Provvigione'):'—',modified:r.modifiedAt?this.getDateTime().toDisplay(r.modifiedAt):'—'}));
   if(this.isRendered())this.reRender();
  }).catch(()=>{if(this._requestsDisposed||this.model.id!==id)return;this.error='Impossibile caricare le provvigioni archiviate.';if(this.isRendered())this.reRender();}).finally(()=>{this._loadRequest=null;});return this._loadRequest;},
  actionRefresh:function(){return this.loadRows();},
  data:function(){return {entries:this.entries,count:this.entries.length,empty:!this.entries.length,error:this.error};}
 });
});
