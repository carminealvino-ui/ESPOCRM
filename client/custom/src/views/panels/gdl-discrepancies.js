define('custom:views/panels/gdl-discrepancies',['views/record/panels/side'],function(Dep){return Dep.extend({
template:'custom:panels/gdl-discrepancies',
events:{'click [data-action="openReviews"]':function(){this.createView('reviews','custom:views/modals/gdl-discrepancies',{scopeType:this.model.entityType,recordId:this.model.id}).then(v=>{this.listenTo(v,'saved',this.load);v.render();});},'click [data-action="reviewGdl"]':function(e){this.createView('review','custom:views/modals/gdl-review',{lineId:e.currentTarget.dataset.id}).then(v=>{this.listenTo(v,'saved',this.load);v.render();});}},
setup:function(){Dep.prototype.setup.call(this);this.once('remove',()=>{this._requestsDisposed=true;});this.lines=[];this.listenTo(this.model,'sync',this.load);this.wait(this.load());},
load:function(){if(this._requestsDisposed||!this.model.id)return Promise.resolve();
   if(this._loadRequest)return this._loadRequest;
   const model=this.model,id=model.id;
   if(!model._gdlReviewRequest)model._gdlReviewRequest=Espo.Ajax.getRequest('CommissionReview',{scopeType:this.model.entityType,id:this.model.id,includeResolved:'true'}).finally(()=>{delete model._gdlReviewRequest;});
   this._loadRequest=model._gdlReviewRequest.then(d=>{if(this._requestsDisposed||this.model.id!==id)return;this.lines=(d.bucket.records||[]).flatMap(r=>r.lines);this.error=null;if(this.isRendered())this.reRender();}).catch(e=>{if(this._requestsDisposed||this.model.id!==id)return;this.error=e.status===403?'Permessi insufficienti.':'Impossibile caricare le incongruenze.';if(this.isRendered())this.reRender();}).finally(()=>{this._loadRequest=null;});return this._loadRequest;},
data:function(){const open=this.lines.filter(l=>!l.reviewClosed);return{error:this.error,count:open.length,closed:this.lines.length-open.length,lines:open.slice(0,4).map(l=>({...l,difference:(l.differenceCents/100).toLocaleString('it-IT',{style:'currency',currency:'EUR'})}))};}
});});
