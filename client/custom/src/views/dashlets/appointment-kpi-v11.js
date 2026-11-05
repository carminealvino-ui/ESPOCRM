define('custom:views/dashlets/appointment-kpi-v11',['custom:views/dashlets/crm-kpi-20260924'],function(Dep){
    const labels={conclusi:'Conclusi positivamente',agenda:'In agenda',totali:'Appuntamenti totali',lordi:'Esclusi annullati',netti:'Appuntamenti netti',annullati:'Annullati',ingestibili:'Ingestibili',pianificati:'Pianificati',rifissati:'Rifissati',svolti:'Svolti',senzaData:'Senza data',senzaArea:'Area non disponibile'};
    const statuses={'Planned':'Pianificato','Held':'Svolto','Not Held':'Non svolto','Ingestibile':'Ingestibile'};
    return Dep.extend({
        name:'AppointmentKpi',template:'custom:dashlets/appointment-kpi-v11',
        events:{
            'click [data-action="sortGrouping"]':function(e){e.preventDefault();this.groupingSort=this.groupingSort==='closure'?'total':'closure';this.reRender();},
            'click [data-action="toggleGrouping"]':function(e){e.preventDefault();const mode=e.currentTarget.getAttribute('data-grouping');if(!['areas','products','caps'].includes(mode))return;this.groupingMode=mode;this.reRender();},
            'click [data-action="refresh"]':function(){this.loadSummary();},
            'click [data-action="definitions"]':function(){this.createView('appointmentDetail','custom:views/modals/appointment-kpi-detail-v5',{detail:{title:'Criteri · appuntamenti',definitions:true}}).then(v=>v.render());},
            'click [data-appointment-key]':function(e){e.stopPropagation();const el=$(e.currentTarget);this.openBucket(el.attr('data-appointment-key'),el.attr('data-group'),el.attr('data-subkey'));},
            'keydown [data-appointment-key]':function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();$(e.currentTarget).trigger('click');}}
        },
        loadSummary:function(silent){
            const token=this.loadToken=(this.loadToken||0)+1;if(!silent)this.wait(true);
            return Espo.Ajax.getRequest('AppointmentKpi/action/getSummary',{productBrandId:this.getOption('productBrandId')||this.getOption('productBrand'),areaId:this.getOption('areaId')||this.getOption('area'),period:this.getOption('period')||'totals',dateFrom:this.getOption('dateFrom'),dateTo:this.getOption('dateTo')})
                .then(s=>{if(token!==this.loadToken)return;this.summary=s;this.loadError=null;this.wait(false);this.reRender();})
                .catch(xhr=>{if(token!==this.loadToken)return;this.summary=null;this.loadError=xhr.status===403?'Permessi insufficienti per gli appuntamenti.':xhr.status===400?'Controllare le date nelle opzioni.':'Impossibile caricare gli appuntamenti.';this.wait(false);this.reRender();});
        },
        cardPeriodLabel:function(){const p=this.summary?.cardPeriod,date=v=>v.split('-').reverse().join('/');return p?.from?date(p.from)+' → '+date(p.to):'Tutto lo storico';},
        openBucket:function(key,group,subkey){
            const b=group==='months'?this.summary?.months?.[key]?.[subkey]:['areas','products','caps'].includes(group)?this.summary?.[group]?.[key]?.metrics?.[subkey]:this.summary?.cardMetrics?.[key];
            if(!b)return;
            const title=group==='months'?key+' · '+labels[subkey]:['areas','products','caps'].includes(group)?this.summary[group][key].name+' · '+labels[subkey]:labels[key];
            const detail={title:title+' · '+(group==='months'?'Storico':this.cardPeriodLabel()),count:b.count,rows:b.records.map(r=>({...r,status:statuses[r.status]||r.status,url:'#Appuntamento/view/'+encodeURIComponent(r.id)}))};
            this.createView('appointmentDetail','custom:views/modals/appointment-kpi-detail-v5',{detail}).then(v=>v.render());
        },
        data:function(){
            const s=this.summary||{},m=s.cardMetrics||{},item=key=>({key,label:labels[key],count:m[key]?.count||0}),base=m.totali?.count||0;
            const keys=['totali','lordi','netti','conclusi'],scale=Math.max(base,1);
            const funnel=keys.map((key,i)=>{const width=(m[key]?.count||0)/scale*1000,lower=i===keys.length-1?0:(m[keys[i+1]]?.count||0)/scale*1000;return {...item(key),color:['#63a7c2','#ccc058','#5cb85c','#18794e'][i],points:`${(1000-width)/2},0 ${(1000+width)/2},0 ${(1000+lower)/2},85 ${(1000-lower)/2},85`,percent:base?((m[key]?.count||0)/base*100).toLocaleString('it-IT',{maximumFractionDigits:1})+'%':'—'};});
            const months=Object.entries(s.months||{}).filter(([k])=>/^\d{4}-\d{2}$/.test(k)).sort(([a],[b])=>a.localeCompare(b)).slice(-(this.monthCapacity||24));
            const monthMax=Math.max(...months.map(([,b])=>b.totali.count),1);
            const positive=m.conclusi?.count||0,percent=(n,d)=>d?(n/d*100).toLocaleString('it-IT',{maximumFractionDigits:1})+'%':'—';
            const grouping=['products','caps'].includes(this.groupingMode)?this.groupingMode:'areas',areas=Object.entries(s[grouping]||{}).sort(([,a],[,b])=>{
                if(this.groupingSort==='closure'){
                    const denominator=g=>Math.max(0,g.metrics.totali.count-g.metrics.annullati.count);
                    const rate=g=>denominator(g)?(g.metrics.conclusi?.count||0)/denominator(g):-1;
                    const delta=rate(b)-rate(a);if(delta)return delta;
                }
                return b.metrics.totali.count-a.metrics.totali.count || a.name.localeCompare(b.name,'it');
            }).slice(0,grouping==='caps'?15:Infinity),areaMax=Math.max(...areas.map(([,b])=>b.metrics.totali.count),1);
            return {grouping,sortGroupingLabel:this.groupingSort==='closure'?'Ordine: % chiusura':'Ordine: appuntamenti',isAreas:grouping==='areas',isProducts:grouping==='products',isCaps:grouping==='caps',zoneHeading:grouping==='caps'?(this.groupingSort==='closure'?'CAP · primi 15 per percentuale di chiusura':'Appuntamenti per CAP · primi 15'):grouping==='products'?'Appuntamenti per prodotto':'Appuntamenti per zona',groupCaption:grouping==='caps'?(this.groupingSort==='closure'?'Primi 15 CAP per percentuale di chiusura':'Primi 15 CAP per appuntamenti totali'):grouping==='products'?'Per prodotto':'Per zona',groupColumn:grouping==='caps'?'CAP':grouping==='products'?'Prodotto':'Zona',toggleGroupingLabel:grouping==='products'?'Per CAP':grouping==='caps'?'Per zona':'Per prodotto',ready:!!this.summary,loadError:this.loadError,brand:s.brandName||'Tutti i brand',areaFilter:this.getOption('areaName')||'Tutte le aree',cardPeriodLabel:this.cardPeriodLabel(),
                positive,conclusionRates:['totali','lordi','netti'].map(key=>({key,label:key==='lordi'?'Sui lordi':key==='netti'?'Sui netti':'Sui totali',base:m[key]?.count||0,positive,rate:percent(positive,m[key]?.count||0)})),zoneRates:areas.map(([key,b])=>({key,grouping,name:b.name,positive:b.metrics.conclusi?.count||0,base:b.metrics.netti.count,rate:percent(b.metrics.conclusi?.count||0,b.metrics.netti.count),rates:['totali','lordi','netti'].map(metric=>({key,grouping,metric,label:metric,positive:b.metrics.conclusi?.count||0,base:b.metrics[metric].count,rate:percent(b.metrics.conclusi?.count||0,b.metrics[metric].count)}))})),
                cards:['totali','annullati','ingestibili','netti','conclusi'].map(key=>({...item(key),percent:percent(m[key]?.count||0,key==='conclusi'?Math.max(0,base-(m.annullati?.count||0)):base),percentBaseLabel:key==='conclusi'?'sul totale meno annullati':'sul totale'})),funnel,
                conversion:base?((m.netti?.count||0)/base*100).toLocaleString('it-IT',{maximumFractionDigits:1})+'%':'—',
                months:months.map(([key,b])=>({key,count:b.totali.count,netti:b.netti.count,positive:b.conclusi?.count||0,positiveRate:percent(b.conclusi?.count||0,b.totali.count),positiveWidth:b.netti.count?(b.conclusi?.count||0)/b.netti.count*100:0,width:b.totali.count/monthMax*100,netWidth:b.totali.count?b.netti.count/b.totali.count*100:0})),
                monthRange:months.length?months[0][0]+' → '+months[months.length-1][0]:'',
                extras:['rifissati','svolti','senzaData','senzaArea'].map(item),
                areas:areas.map(([key,b])=>{const total=b.metrics.totali.count;return {key,grouping,name:b.name,description:grouping==='caps'?b.description:null,total,positive:b.metrics.conclusi?.count||0,positiveHeight:b.metrics.netti.count?(b.metrics.conclusi?.count||0)/b.metrics.netti.count*100:0,net:b.metrics.netti.count,cancelled:b.metrics.annullati.count,unmanageable:b.metrics.ingestibili.count,planned:b.metrics.pianificati.count,width:total/areaMax*100,netHeight:total?b.metrics.netti.count/total*100:0,cancelledHeight:total?b.metrics.annullati.count/total*100:0,unmanageableHeight:total?b.metrics.ingestibili.count/total*100:0,rate:this.groupingSort==='closure'?percent(b.metrics.conclusi?.count||0,Math.max(0,total-b.metrics.annullati.count)):percent(b.metrics.netti.count,total),rateLabel:this.groupingSort==='closure'?'Chiusura: conclusi / (totali meno annullati)':'Netti / totali'};})};
        },
        fitDashletHeightToContent:function(){
            Dep.prototype.fitDashletHeightToContent.call(this);
            const root=this.el.querySelector('.appointment-kpi');if(!root||!this.summary)return;
            const panel=this.el.closest('.dashlet'),heading=panel?.querySelector('.panel-heading h4, .panel-heading .panel-title');
            if(heading){let caption=heading.querySelector('.ak-heading-caption');if(!caption){caption=document.createElement('span');caption.className='ak-heading-caption';heading.appendChild(caption);}caption.textContent=' - '+(this.summary.brandName||'Tutti i brand')+' - Periodo '+this.cardPeriodLabel()+(this.getOption('areaName')?' - '+this.getOption('areaName'):'');}
            root.style.zoom='1';root.style.width='100%';
            const available=Math.max(280,window.innerHeight-root.getBoundingClientRect().top-window.scrollY-35),width=root.clientWidth;
            let scale=1;for(let i=0;i<3;i++){root.style.zoom=String(scale);root.style.width=(width/scale)+'px';const next=Math.min(1,available/Math.max(root.scrollHeight,1));if(Math.abs(next-scale)<.005)break;scale=next;}
            root.style.zoom=String(scale);root.style.width=(width/scale)+'px';
            const content=document.getElementById('content');if(content){const top=content.getBoundingClientRect().top+window.scrollY,bottom=this.el.getBoundingClientRect().bottom+window.scrollY;content.style.setProperty('min-height',Math.max(window.innerHeight-top-20,bottom-top+16)+'px','important');}
        },
        afterRender:function(){
            this.fitDashletHeightToContent();this.monthObserver?.disconnect();const chart=this.el.querySelector('.ak-month-chart');if(!chart)return;
            this.monthObserver=new ResizeObserver(entries=>{const width=entries[0].contentRect.width;if(width<=0)return;const capacity=Math.max(1,Math.floor(width/30));if(capacity!==this.monthCapacity){this.monthCapacity=capacity;this.reRender();}});this.monthObserver.observe(chart);
            if(!this.observerCleanup){this.observerCleanup=true;this.listenToOnce(this,'remove',()=>this.monthObserver?.disconnect());}
            if(!this.viewportCleanup){this.viewportCleanup=true;this.viewportResize=()=>this.fitDashletHeightToContent();window.addEventListener('resize',this.viewportResize);this.listenToOnce(this,'remove',()=>window.removeEventListener('resize',this.viewportResize));}
        }
    });
});







