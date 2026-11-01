define('custom:views/invito/record/panels/movimenti-rettifiche-v2', ['views/record/panels/relationship'], function (Dep) {
    return Dep.extend({
        setup: function () {
            this.defs = Object.assign({}, this.defs, {unlinkDisabled: true, selectDisabled: true});
            Dep.prototype.setup.call(this);
            if (this.getAcl().check('InvitoAFatturareItem', 'create') && this.getAcl().checkModel(this.model, 'edit')) {
                this.buttonList.push({action: 'selectStorni', text: 'Rettifica provvigioni', acl: 'edit'});
                this.buttonList.push({action: 'selectHistoricalCommissions', text: 'Seleziona provvigioni storiche', acl: 'edit'});
                this.buttonList.push({action: 'selectProvvigioni', text: 'Seleziona provvigioni', title: 'Seleziona provvigioni', acl: 'edit'});
            }
            this.listenTo(this.model, 'after:relate:righeProvvigionali after:unrelate:righeProvvigionali', function () {
                this.model.fetch();
            });
        },
        actionSelectHistoricalCommissions: function () {
            if (!['Bozza','In verifica'].includes(this.model.get('stato'))) { Espo.Ui.error('Salvare prima in Bozza o In verifica.'); return; }
            this.createView('historicalDialog','custom:views/invito/modals/historical-commissions',{invoiceId:this.model.id},view=>{
                this.listenToOnce(view,'added',()=>{this.model.trigger('update-related:righeProvvigionali');this.model.fetch();});
                view.render();
            });
        },
        actionSelectStorni: function () {
            if (!['Bozza', 'In verifica'].includes(this.model.get('stato'))) {
                Espo.Ui.error('La rettifica si inserisce in un invito in Bozza o In verifica.'); return;
            }
            if (this.selecting) return;
            this.selecting = true;
            Espo.Ajax.getRequest('InvitoAFatturare/action/provvigioniIncluse', {id: this.model.id}).then(data => {
                return this.createView('selectStorniDialog', 'custom:views/invito/modals/select-provvigioni-disponibili', {
                    scope: 'Provvigione', multiple: true, createButton: false, massRelateEnabled: false,
                    filters: {fornitorePartner: {type: 'equals', attribute: 'fornitorePartnerId', value: data.fornitorePartnerId, data: {type: 'is', nameValue: data.fornitorePartnerName}}},
                    supplierId: data.fornitorePartnerId, boolFilterList: [], orderBy: 'dataContrattoInvito', orderDirection: 'desc'
                }, view => {
                    view.render();
                    this.listenToOnce(view, 'select', selected => {
                        const ids = (Array.isArray(selected) ? selected : [selected]).map(model => model.id);
                        Espo.Ajax.postRequest('InvitoAFatturare/action/aggiungiStorni', {id: this.model.id, ids: ids}).then(result => {
                            Espo.Ui.success('Rettifiche aggiunte: ' + result.count);
                            this.model.trigger('update-related:righeProvvigionali'); this.model.fetch();
                        });
                    });
                });
            }).finally(() => { this.selecting = false; });
        },
        actionSelectProvvigioni: function () {
            if (!['Bozza', 'In verifica'].includes(this.model.get('stato'))) {
                Espo.Ui.error('Salvare prima lÃ¢â‚¬â„¢invito in Bozza o In verifica per aggiungere provvigioni.');
                return;
            }
            if (this.selecting) return;
            this.selecting = true;
            Espo.Ajax.getRequest('InvitoAFatturare/action/provvigioniIncluse', {id: this.model.id}).then(data => {
                return this.createView('selectProvvigioniDialog', 'custom:views/invito/modals/select-provvigioni-disponibili', {
                    scope: 'Provvigione', multiple: true, createButton: false, massRelateEnabled: false,
                    filters: {fornitorePartner: {type: 'equals', attribute: 'fornitorePartnerId', value: data.fornitorePartnerId, data: {type: 'is', nameValue: data.fornitorePartnerName || this.model.get('fornitorePartnerName')}}},
                    availableForInvoice: true, excludedIds: data.currentIds || [], supplierId: data.fornitorePartnerId, boolFilterList: [], orderBy: 'dataContrattoInvito', orderDirection: 'desc'
                }, view => {
                    view.render();
                    this.listenToOnce(view, 'select', selected => {
                        const ids = (Array.isArray(selected) ? selected : [selected]).map(model => model.id);
                        Espo.Ajax.postRequest('InvitoAFatturare/action/aggiungiProvvigioni', {id: this.model.id, ids: ids}).then(result => {
                            Espo.Ui.success('Provvigioni aggiunte: ' + result.count);
                            this.model.trigger('update-related:righeProvvigionali');
                            this.model.fetch();
                        });
                    });
                });
            }).finally(() => { this.selecting = false; });
        }
    });
});
