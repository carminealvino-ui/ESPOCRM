define('custom:views/quote/record/panels/inviti-collegati-v2',['views/record/panels/bottom'],function(Dep){
 return Dep.extend({
  template:'custom:quote/inviti-collegati-v2',
  setup:function(){Dep.prototype.setup.call(this);this.entries=[];this.listenTo(this.model,'sync',this.loadInviti);this.wait(this.loadInviti());},
  loadInviti:function(){if(!this.model.id)return Promise.resolve();return Espo.Ajax.getRequest('Quote/action/invitiCollegati',{id:this.model.id}).then(data=>{
   this.error=null;
   const money=cents=>cents===null?'—':(cents/100).toLocaleString('it-IT',{style:'currency',currency:'EUR'});
   this.totals=data.totals?{expected:money(data.totals.expected),billed:money(data.totals.billed),paid:money(data.totals.paid)}:null;
   this.entries=data.list.map(row=>({...row,items:(row.items||[]).map(item=>({...item,url:'#InvitoAFatturareItem/view/'+encodeURIComponent(item.id),isCorrection:item.operation==='Rettifica',amount:(item.cents/100).toLocaleString('it-IT',{style:'currency',currency:'EUR'})})),url:'#InvitoAFatturare/view/'+encodeURIComponent(row.id),date:row.date?this.getDateTime().toDisplayDate(row.date):'—',month:row.month?row.month.slice(0,7):'—',amount:(row.cents/100).toLocaleString('it-IT',{style:'currency',currency:'EUR'})}));if(this.isRendered())this.reRender();
  }).catch(()=>{this.error='Impossibile caricare gli inviti collegati.';if(this.isRendered())this.reRender();});},
  actionRefresh:function(){return this.loadInviti();},
  data:function(){return {entries:this.entries,error:this.error,totals:this.totals};}
 });
});
