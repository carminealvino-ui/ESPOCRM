define('custom:views/dashlets/sales-kpi',['custom:views/dashlets/crm-kpi-20260924'],function(Dep){
    const labels={totali:'Vendite totali',lordi:'Esclusi recessi',valide:'Esclusi recessi e finanziamenti KO',netti:'Vendite nette',recessi:'Recessi',finanziamentiRifiutati:'Finanziamenti KO',sospesi:'Sospesi',senzaImporto:'Contratti senza importo',senzaData:'Contratti senza data'};
    return Dep.extend({
        name:'SalesKpi',template:'custom:dashlets/sales-kpi',
        events:{
            'click [data-action="refresh"]':function(){this.loadSummary();},
            'click [data-action="definitions"]':function(){this.createView('salesDetail','custom:views/modals/sales-kpi-detail',{detail:{title:'Criteri e legenda · vendite',definitions:true}}).then(v=>v.render());},
            'click [data-sales-key]':function(e){this.openBucket($(e.currentTarget).attr('data-sales-key'),$(e.currentTarget).attr('data-sales-group'),$(e.currentTarget).attr('data-sales-subkey'));},
            'keydown [data-sales-key]':function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();$(e.currentTarget).trigger('click');}}
        },
        loadSummary:function(silent){
            const token=this.loadToken=(this.loadToken||0)+1; if(!silent)this.wait(true);
            return Espo.Ajax.getRequest('SalesKpi/action/getSummary',{productBrandId:this.getOption('productBrandId')||this.getOption('productBrand'),period:this.getOption('period')||'totals',dateFrom:this.getOption('dateFrom'),dateTo:this.getOption('dateTo')})
                .then(s=>{if(token!==this.loadToken)return;this.summary=s;this.loadError=null;this.wait(false);this.reRender();})
                .catch(xhr=>{if(token!==this.loadToken)return;this.summary=null;this.loadError=xhr.status===403?'Permessi insufficienti per i dati delle vendite.':'Impossibile caricare le vendite.';this.wait(false);this.reRender();});
        },
        money:function(cents){return(Number(cents||0)/100).toLocaleString('it-IT',{style:'currency',currency:'EUR'});},
        openBucket:function(key,group,subkey){
            const b=group==='months'?this.summary?.months?.[key]?.[subkey]:group==='states'?this.periodStates()[key]:group==='cards'?this.summary?.cardMetrics?.[key]:this.summary?.metrics?.[key]; if(!b)return;
            const title=(group==='months'?key+' · '+labels[subkey]:group==='states'?key:labels[key])+(['cards','states'].includes(group)?' · '+this.cardPeriodLabel():'');
            const detail={title,total:this.money(b.cents),count:b.count,rows:b.records.map(r=>({...r,value:this.money(Math.round(r.amount*100)),url:'#Quote/view/'+encodeURIComponent(r.id)}))};
            this.createView('salesDetail','custom:views/modals/sales-kpi-detail',{detail}).then(v=>v.render());
        },
        cardPeriodLabel:function(){const p=this.summary?.cardPeriod;return p?.from?p.from+' → '+p.to:'Tutto lo storico';},
        periodStates:function(){
            const states=Object.create(null);
            ['Totali','Annullati','Recessi','Sospesi','In lavorazione','Chiusi'].forEach(key=>{
                states[key]={cents:0,count:0,records:[]};
            });
            const names={Annullato:'Annullati',Recesso:'Recessi',Sospeso:'Sospesi',Chiuso:'Chiusi'};
            (this.summary?.cardMetrics?.totali?.records||[]).forEach(row=>{
                const key=names[row.status]||row.status||'Senza stato';
                const b=states[key]||(states[key]={cents:0,count:0,records:[]});
                const cents=Math.round(row.amount*100);
                b.cents+=cents;b.count++;b.records.push(row);
                states.Totali.cents+=cents;states.Totali.count++;states.Totali.records.push(row);
            });
            return states;
        },
        data:function(){
            const s=this.summary||{},m=s.cardMetrics||{},item=key=>({key,label:labels[key],value:this.money(m[key]?.cents),count:m[key]?.count||0});
            const keys=['totali','lordi','valide','netti'],base=m.totali?.cents||0,values=keys.map(k=>m[k]?.cents||0),scale=Math.max(...values.map(Math.abs),1);
            const monotonic=values.every((v,i)=>v>=0&&(!i||v<=values[i-1]));
            const funnel=keys.map((key,i)=>{const width=Math.abs(values[i])/scale*1000,lower=monotonic?(i===keys.length-1?0:Math.abs(values[i+1])/scale*1000):width;
                return{...item(key),color:['#63a7c2','#ccc058','#89b875','#5cb85c'][i],points:`${(1000-width)/2},0 ${(1000+width)/2},0 ${(1000+lower)/2},85 ${(1000-lower)/2},85`,percent:base?(values[i]/base*100).toLocaleString('it-IT',{maximumFractionDigits:1})+'%':'—'};});
            const recentMonths=Object.entries(s.months||{}).filter(([key])=>/^\d{4}-\d{2}$/.test(key)).sort(([a],[b])=>a.localeCompare(b)).slice(-(this.monthCapacity||28));
            const monthScale=Math.max(...recentMonths.map(([,b])=>Math.abs(b.totali.cents)),1);
            return{ready:!!this.summary,loadError:this.loadError,brand:s.brandName||'Tutti i brand',today:s.today,cardPeriodLabel:this.cardPeriodLabel(),cards:['totali','netti','sospesi','recessi','finanziamentiRifiutati'].map(key=>({key,label:labels[key],value:this.money(s.cardMetrics?.[key]?.cents),count:s.cardMetrics?.[key]?.count||0})),funnel,signed:!monotonic,
                average:this.money(s.cardMetrics?.netti?.count?Math.round(s.cardMetrics.netti.cents/s.cardMetrics.netti.count):0),conversion:s.cardMetrics?.totali?.count?((s.cardMetrics.netti?.count||0)/s.cardMetrics.totali.count*100).toLocaleString('it-IT',{maximumFractionDigits:1})+'%':'—',
                states:Object.entries(this.periodStates()).map(([key,b])=>({key,value:this.money(b.cents),count:b.count})),
                monthRange:recentMonths.length?recentMonths[0][0]+' → '+recentMonths[recentMonths.length-1][0]:'',
                months:recentMonths.map(([key,b])=>({key,value:this.money(b.totali.cents),count:b.totali.count,countLabel:b.totali.count===1?'contratto':'contratti',width:Math.abs(b.totali.cents)/monthScale*100,negative:b.totali.cents<0,segments:b.totali.cents>0 && ['netti','sospesi','recessi','finanziamentiRifiutati'].every(k=>b[k].cents>=0)?['netti','sospesi','recessi','finanziamentiRifiutati'].filter(k=>b[k].cents>0).map(k=>({key:k,label:labels[k],height:b[k].cents/b.totali.cents*100,value:this.money(b[k].cents),count:b[k].count})):[]})),
                alerts:['senzaImporto','senzaData'].map(key=>({key,label:labels[key],value:this.money(s.cardMetrics?.[key]?.cents),count:s.cardMetrics?.[key]?.count||0}))};
        },
        afterRender:function(){
            this.fitDashletHeightToContent();
            if(this.monthObserver)this.monthObserver.disconnect();
            const chart=this.el.querySelector('.sk-month-chart');if(!chart)return;
            this.monthObserver=new ResizeObserver(entries=>{
                const width=entries[0].contentRect.width;if(width<=0)return;
                const capacity=Math.max(1,Math.floor((width-4)/30));
                if(capacity!==this.monthCapacity){this.monthCapacity=capacity;this.reRender();}
            });
            this.monthObserver.observe(chart);
            if(!this.monthObserverCleanup){this.monthObserverCleanup=true;this.listenToOnce(this,'remove',()=>this.monthObserver?.disconnect());}
        }
    });
});
