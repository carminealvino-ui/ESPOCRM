define('custom:views/dashlets/commission-kpi-composition-v13', ['custom:views/dashlets/crm-kpi-20260924'], function (Dep) {
    const labels={aperteValide:'In pagamento + stima + sospesi',aperteAttive:'Sospesi',pagamentoEStima:'In pagamento + stima futura',inPagamento:'Prossimo invito · in pagamento',stimaFutura:'Stima futura',provvigioniMaturate:'Provvigioni maturate',bonusMaturati:'Bonus maturati',plusMinusMaturati:'Plus/minus maturati',sospese:'Provvigioni sospese',maturateContratti:'Provvigioni maturate',aperte:'Aperte',bloccate:'Bloccate',totali:'Totali',recessi:'Recessi',lordi:'Lorde',finanziamentiRifiutati:'Finanziamenti KO',sospesi:'Sospese',netti:'Nette',maturate:'In pagamento',daMaturare:'Da andare in pagamento',pagate:'Pagate',residuo:'Residuo da incassare',negliInviti:'Negli inviti aperti',disponibili:'Maturate disponibili',differenze:'Differenze di chiusura',daVerificare:'Differenze da verificare',arretrati:'Arretrati non inseriti negli inviti',senzaData:'Contratti senza data prevista',inesigibili:'Inesigibili',mensili:'A saldo mensile'};
    const lifetimeLabels={totali:'Provvigioni lorde',finanziamentiOk:'Provvigioni · finanziamenti OK',recessi:'Provvigioni · recessi',finanziamentiKo:'Provvigioni · finanziamenti KO',netti:'Provvigioni nette',pagate:'Provvigioni pagate'};
    const categories={provvigioni:'Provvigioni / PNC',plus:'Plus',minus:'Minus',bonus:'Bonus e premi',altre:'Altre voci',nonAttribuite:'Da riconciliare'};
    return Dep.extend({
        name:'CommissionKpi',template:'custom:dashlets/commission-kpi-composition-v13',
        events:{
            'click [data-report-category]':function(e){const el=$(e.currentTarget);this.showReportCategory(el.attr('data-report-category'),el.attr('data-key'));},
            'click [data-column-total]':function(e){this.showDetail('column',$(e.currentTarget).attr('data-column-total'));},
            'click [data-action="refresh"]':function(){this.loadSummary();},
            'click [data-liquidation]':function(e){this.showDetail('liquidation',$(e.currentTarget).attr('data-liquidation'));},
            'click [data-snapshot]':function(e){this.showDetail('snapshot',$(e.currentTarget).attr('data-snapshot'));},
            'click [data-metric]':function(e){this.showDetail('metric',$(e.currentTarget).attr('data-metric'),$(e.currentTarget).attr('data-category'));},
            'click [data-lifetime-metric]':function(e){this.showDetail('lifetime',$(e.currentTarget).attr('data-lifetime-metric'),$(e.currentTarget).attr('data-category'));},
            'click [data-open-metric]':function(e){this.showDetail('open',$(e.currentTarget).attr('data-open-metric'),$(e.currentTarget).attr('data-category'));},
            'click [data-forecast]':function(e){this.showDetail('forecast',Number($(e.currentTarget).attr('data-forecast')),$(e.currentTarget).attr('data-category'));},
            'click [data-funnel-category]':function(e){this.funnelCategory=$(e.currentTarget).attr('data-funnel-category');this.reRender();},
            'click [data-action="definitions"]':function(){this.showDetail('definitions');},
            'click [data-action="allForecast"]':function(){this.showDetail('allForecast');},
            'click [data-action="nextForecast"]':function(){this.showDetail('nextForecast');}
        },
        loadSummary:function(silent){
            const token=this.loadToken=(this.loadToken||0)+1;if(!silent)this.wait(true);
            return Espo.Ajax.getRequest('CommissionKpi/action/getSummary',{period:'totals',productBrandId:this.getOption('productBrandId')||this.getOption('productBrand'),dateFrom:null,dateTo:null})
                .then(data=>{if(token!==this.loadToken)return;this.summary=data;this.loadError=null;this.wait(false);this.reRender();})
                .catch(xhr=>{if(token!==this.loadToken)return;this.summary=null;this.loadError=xhr.status===403?'Permessi insufficienti per i dati provvigionali.':(xhr.responseJSON?.message||'Impossibile caricare le provvigioni.');this.wait(false);this.reRender();});
        },
        money:function(cents){return(Number(cents||0)/100).toLocaleString('it-IT',{style:'currency',currency:'EUR',useGrouping:true});},
        nextForecast:function(){const map=new Map();(this.summary?.forecast||[]).forEach((f,index)=>{if(!f.overdue&&(!map.has(f.supplierId)||f.date<map.get(f.supplierId).date))map.set(f.supplierId,{...f,index});});return[...map.values()];},
        detailRows:function(bucket){return(bucket.records||[]).map(r=>({...r,value:this.money(Math.round(r.amount*100)),categoryLabel:categories[r.category]||'',url:r.id&&r.entityType?'#'+encodeURIComponent(r.entityType)+'/view/'+encodeURIComponent(r.id):null}));},
        contractRows:function(bucket){return this.detailRows({records:Object.values(bucket.contracts||{})}).map(r=>({...r,parts:Object.entries(r.components||{}).map(([k,v])=>(categories[k]||k)+': '+this.money(v)).join(' · ')}));},
        showDetail:function(type,key,category){
            if(type==='nextForecast' && this.nextForecast().length===1)return this.showDetail('forecast',this.nextForecast()[0].index);
            const s=this.summary||{},m=s.metrics||{};let detail;
            if(type==='definitions')detail={title:'Come leggere i KPI provvigionali',definitions:true};
            else if(type==='allForecast'||type==='nextForecast'){
                const rows=type==='allForecast'?(s.forecast||[]):this.nextForecast();
                detail={title:type==='allForecast'?'Calendario completo degli inviti':'Prossimi inviti · quote disponibili',forecast:true,
                    rows:type==='nextForecast'?rows.flatMap(f=>this.contractRows(f).map(r=>({...r,status:f.supplierName+' · '+f.date}))):rows.map(f=>({name:f.supplierName,date:f.date,status:f.overdue?'Arretrato da verificare':'Stima futura',value:this.money(f.cents),
                    parts:Object.entries(f.parts||{}).map(([k,b])=>categories[k]+': '+this.money(b.cents)).join(' · ')}))};
            }else{
                let bucket,title;
                if(type==='lifetime'){bucket=category?s.lifetimeFunnel?.groups?.[category]?.[key]:s.lifetimeFunnel?.metrics?.[key];title=lifetimeLabels[key]+' · tutto lo storico'+(category?' · '+categories[category]:'');}
                else if(type==='liquidation'){bucket=s.liquidation?.[key];title=({stornate:'Provvigioni stornate',daStornare:'Provvigioni da stornare',pagate:'Provvigioni pagate',recessi:'Provvigioni recessi',finanziamentiKo:'Provvigioni finanziamenti KO',maturate:'Provvigioni maturate',daMaturare:'Provvigioni da maturare'})[key]+' · pagamenti · tutto lo storico';}
                else if(type==='snapshot'){bucket=s.openFunnel?.snapshot?.[key];title=labels[key]+' · tutto lo storico';}
                else if(type==='open'){bucket=category?s.openFunnel?.groups?.[category]?.[key]:s.openFunnel?.metrics?.[key];title='Aperte · tutto lo storico · '+(category?categories[category]+' · ':'')+labels[key];}
                else if(type==='column'){
                    bucket={cents:0,count:0,records:[],contracts:{}};title=labels[key];
                    Object.entries(s.reportGroups||{}).filter(([category,g])=>['provvigioni','plus','minus','bonus'].includes(category)||g.aperteValide?.count).forEach(([category,g])=>{
                        const part=g[key]||{};bucket.cents+=part.cents||0;
                        Object.entries(part.contracts||{}).forEach(([id,contract])=>{
                            if(!bucket.contracts[id])bucket.contracts[id]={...contract,amount:0,components:{}};
                            const target=bucket.contracts[id];
                            target.amount=Math.round((target.amount+contract.amount)*100)/100;
                            target.components[category]=(target.components[category]||0)+Math.round(contract.amount*100);
                        });
                    });
                    bucket.contractCount=Object.keys(bucket.contracts).length;bucket.count=bucket.contractCount;bucket.monthlyRows=this.detailRows({records:(s.report?.[key]?.records||[]).filter(r=>!r.id&&!r.contractId)});
                }
                else if(type==='metric'){bucket=category?s.groups?.[category]?.[key]:m[key];title=(category?categories[category]+' · ':'')+labels[key];}
                else{const f=s.forecast?.[key];bucket=category?f?.parts?.[category]:f;title=(f?.supplierName||'')+' · '+(f?.label||'')+(category?' · '+categories[category]:'');}
                if(!bucket)return;
                detail={title,total:this.money(bucket.cents),count:bucket.count,limited:bucket.count>bucket.records.length,rows:this.detailRows(bucket)};
                if(type==='lifetime'||type==='snapshot'||type==='column'||type==='liquidation'||(type==='metric'&&key==='senzaData')){detail.rows=this.contractRows(bucket);detail.count=bucket.contractCount||0;detail.contracts=true;detail.limited=false;if(bucket.monthlyRows)detail.rows.push(...bucket.monthlyRows);}
                if(type==='metric'&&key==='daVerificare'){
                    detail.differences=true;detail.contracts=true;detail.count=bucket.contractCount||bucket.count;
                    detail.rows=(bucket.records||[]).map(r=>({...r,url:'#Quote/view/'+encodeURIComponent(r.id),expected:this.money(r.expectedCents),invited:this.money(r.invitedCents),difference:this.money(r.differenceCents),absolute:this.money(r.absoluteCents),
                        lines:(r.lines||[]).filter(l=>l.differenceCents!==0).map(l=>({...l,name:(l.name||'').replace(/Bonus Ordine Completo/i,'Provvigione ordine completo'),isReversal:l.movement==='Storno',invoiceUrl:'#InvitoAFatturare/view/'+encodeURIComponent(l.invoiceId),expected:this.money(l.expectedCents),invited:this.money(l.invitedCents),difference:this.money(l.differenceCents)}))}));
                }
                if(type==='liquidation'&&key==='stornate'){
                    const grouped=new Map();
                    (bucket.reversalLines||[]).forEach(l=>{
                        if(!grouped.has(l.contractId))grouped.set(l.contractId,{name:l.contractName,url:'#Quote/view/'+encodeURIComponent(l.contractId),lines:[]});
                        grouped.get(l.contractId).lines.push({...l,name:(l.commissionType||'').replace(/Bonus Ordine Completo/i,'Provvigione ordine completo'),
                            reversalUrl:'#InvitoAFatturare/view/'+encodeURIComponent(l.reversalId),reversalValue:this.money(l.reversalCents),
                            origins:l.origins.map(o=>({...o,url:'#InvitoAFatturare/view/'+encodeURIComponent(o.id),value:this.money(o.cents)})),missingOrigin:!l.origins.length,multipleOrigins:l.origins.length>1&&!l.explicitOrigin});
                    });
                    detail.reversals=true;detail.rows=[...grouped.values()];
                }
                if(type==='forecast' && !category){
                    const f=s.forecast[key], individual={records:(f.records||[]).filter(r=>r.id)};
                    detail.rows=this.contractRows(f);detail.count=f.contractCount||0;detail.contracts=true;
                    detail.commissionTotal=this.money(f.cents-(f.records||[]).filter(r=>!r.id).reduce((sum,r)=>sum+Math.round(r.amount*100),0));
                    detail.generalTotal=this.money(f.cents);
                }
                if(type==='forecast'){
                    const f=s.forecast[key];
                    const available=!!f.settlement;
                    const st=f.settlement||{month:(f.label||'').match(/\d{4}-\d{2}/)?.[0]||'',records:[]};
                    detail.settlement={month:st.month,unavailable:!available,rows:this.detailRows(st),
                        totals:[['Plus lordi','arielPlusLordo'],['Minus lordi','arielMinusLordo'],['Compensazione provvigionale (35%)','arielCompensato'],['Saldo commerciale','arielSaldoMinusPlus'],['Quota plus provvigionale (35%)','arielProvvigionePlus'],['Minus residuo provvigionale (35%)','arielAddebitoMinus']].map(([label,k])=>({label,value:available?this.money(k==='arielCompensato'?Math.round(st[k]*0.35):st[k]):'Da calcolare'}))};
                }
            }
            this.createView('commissionDetail','custom:views/modals/commission-kpi-detail-v2',{detail}).then(view=>{this.listenTo(view,'saved',()=>this.loadSummary());view.render();});
        },
        showReportCategory:function(category,key){
            const bucket=this.summary?.reportGroups?.[category]?.[key];if(!bucket)return;
            const rows=this.contractRows(bucket);rows.push(...this.detailRows({records:(bucket.records||[]).filter(r=>!r.id&&!r.contractId)}));
            this.createView('commissionDetail','custom:views/modals/commission-kpi-detail-v2',{detail:{title:categories[category]+' · '+labels[key],total:this.money(bucket.cents),count:bucket.contractCount||0,contracts:true,rows}}).then(v=>v.render());
        },
        data:function(){
            const s=this.summary||{},m=s.metrics||{},groups=s.groups||{},reportGroups=s.reportGroups||{};
            const metric=(key,category)=>{const b=category?groups[category]?.[key]:m[key];return{key,category,label:labels[key],value:this.money(b?.cents),count:b?.count||0};};
            const selected=this.funnelCategory||'',open=s.lifetimeFunnel||{},fm=selected?open.groups?.[selected]||{}:open.metrics||{};
            const keys=['totali','netti','pagate'];
            const values=keys.map(k=>fm[k]?.cents||0),base=values[0];
            // These historical subsets overlap: each width is independent, with its signed amount intact.
            const monotonic=values.every(v=>v>=0)&&values[0]>=values[1]&&values[1]>=values[2],scale=Math.max(...values.map(Math.abs),1);
            const funnel=keys.map((key,index)=>{
                const width=Math.abs(values[index])/scale*1000,lower=monotonic?Math.abs(values[Math.min(index+1,2)])/scale*1000:width;
                return{key,category:selected,label:lifetimeLabels[key],value:this.money(values[index]),color:['#63a7c2','#ccc058','#5cb85c'][index],
                    points:`${(1000-width)/2},0 ${(1000+width)/2},0 ${(1000+lower)/2},36 ${(1000-lower)/2},36`,
                    percent:base!==0?(values[index]/base*100).toLocaleString('it-IT',{maximumFractionDigits:1})+'%':'—'};
            });
            const next=this.nextForecast(),nextTotal=next.reduce((a,f)=>a+f.cents,0);
            const forecast=(s.forecast||[]).map((f,index)=>({...f,index,value:this.money(f.cents),next:next.some(n=>n.index===index),parts:['provvigioni','plus','minus','bonus'].map(category=>({category,index,value:this.money(f.parts?.[category]?.cents),hasValue:!!f.parts?.[category]}))}));
            // Show upcoming rows first; keep the complete calendar in a modal.
            const ordered=[...forecast].sort((a,b)=>b.date.localeCompare(a.date) || a.supplierName.localeCompare(b.supplierName));
            const composition=Object.entries(categories).filter(([k])=>['provvigioni','plus','minus','bonus'].includes(k)||reportGroups[k]?.aperteValide?.count).map(([category,label])=>({category,label,
                cells:['aperteValide','aperteAttive','pagamentoEStima','inPagamento','stimaFutura'].map(key=>({key,category,value:this.money(reportGroups[category]?.[key]?.cents)}))}));
            return{ready:!!this.summary,loadError:this.loadError,brand:s.brandName||'Tutti i brand',today:s.today,period:'Report totale · tutto lo storico',
                cards:['aperteValide','aperteAttive','pagamentoEStima','inPagamento','stimaFutura'].map(key=>({key,label:labels[key],value:this.money(s.report?.[key]?.cents),count:s.report?.[key]?.contractCount||0})),nextTotal:this.money(nextTotal),hasNext:next.length>0,
                funnel,funnelSigned:!monotonic,funnelChoices:[{key:'',label:'Tutte'},...['provvigioni','plus','minus','bonus'].map(k=>({key:k,label:k==='provvigioni'?'Provvigioni':categories[k]}))].map(x=>({...x,active:x.key===selected})),
                exclusions:['finanziamentiKo','recessi'].map(key=>({key,category:selected,label:key==='recessi'?'Recessi':'Finanziamenti KO',count:fm[key]?.contractCount||0,value:this.money(fm[key]?.cents)})),composition,
                settlement:[['pagate','Provvigioni pagate'],['recessi','Provvigioni recessi'],['finanziamentiKo','Provvigioni finanziamenti KO'],['maturate','Provvigioni maturate'],['daMaturare','Provvigioni da maturare'],['stornate','Provvigioni stornate'],['daStornare','Provvigioni da stornare']].map(([key,label])=>({key,label,value:this.money(s.liquidation?.[key]?.cents)})),alerts:['arretrati','senzaData','daVerificare','inesigibili'].map(key=>({...metric(key),count:key==='senzaData'?(m[key]?.contractCount||0):(m[key]?.count||0)})),
                forecast:ordered,forecastCount:forecast.length,moreForecast:false,
                reconciliation:s.reconciliationCents?this.money(s.reconciliationCents):null,
                forecastWarning:s.monthlyManualReview?'Plus/minus manuali: saldo mensile escluso dalla stima.':(s.forecastMissingDates?s.forecastMissingDates+' voci senza data escluse dalla previsione.':(!s.monthlyPreviewAvailable?'Saldo mensile riservato agli amministratori.':null))};
        },
        afterRender:function(){this.fitDashletHeightToContent();}
    });
});
