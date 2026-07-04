/* global define, Espo */

define('custom:views/invito-a-fatturare/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.get('stato') === 'Bozza') {
                this.addMenuItem('buttons', {
                    name: 'selezionaProvvigioni',
                    label: 'Seleziona provvigioni',
                    style: 'success',
                    action: 'selezionaProvvigioni',
                    acl: 'edit',
                });

                this.addMenuItem('buttons', {
                    name: 'generaDaProvvigioni',
                    label: 'Genera da provvigioni',
                    style: 'default',
                    action: 'generaDaProvvigioni',
                    acl: 'edit',
                });
            }

            this.addMenuItem('buttons', {
                name: 'emettiInvito',
                label: 'Emetti invito',
                style: 'primary',
                action: 'emettiInvito',
                acl: 'edit',
                hidden: this.model.get('stato') !== 'Bozza',
            });
        },

        actionSelezionaProvvigioni: function () {
            var self = this;
            var consulenteId = this.model.get('consulenteId') || this.model.get('assignedUserId');

            if (!consulenteId) {
                Espo.Ui.error('Selezionare il consulente sull\'invito.');

                return;
            }

            if (!this.model.get('meseCompetenza')) {
                Espo.Ui.error('Impostare il mese competenza sull\'invito.');

                return;
            }

            if (this.model.isNew()) {
                Espo.Ui.error('Salvare l\'invito prima di selezionare le provvigioni.');

                return;
            }

            this.createView('selectProvvigioniModal', 'custom:views/invito-a-fatturare/modals/select-provvigioni', {
                invitoModel: this.model,
            }, function (view) {
                view.render();

                self.listenToOnce(view, 'saved', function () {
                    self.model.fetch();
                    self.reRender();
                });
            });
        },

        actionGeneraDaProvvigioni: function () {
            var self = this;
            var consulenteId = this.model.get('consulenteId') || this.model.get('assignedUserId');
            var mese = this.model.get('meseCompetenza') || new Date().toISOString().slice(0, 10);

            if (!consulenteId) {
                Espo.Ui.error('Selezionare il consulente sull\'invito.');

                return;
            }

            Espo.Ui.confirm({
                message: 'Collegare automaticamente tutte le provvigioni consolidate del mese ' + mese + '?'
            }).then(function () {
                return Espo.Ajax.postRequest('InvitoAFatturare/action/generaDaProvvigioni', {
                    consulenteId: consulenteId,
                    meseCompetenza: mese,
                    fornitorePartnerId: self.model.get('fornitorePartnerId'),
                    productBrandId: self.model.get('productBrandId'),
                    invitoId: self.model.id,
                });
            }).then(function (result) {
                Espo.Ui.success('Provvigioni collegate: ' + (result.count || 0));
                self.model.fetch();
                self.reRender();
            });
        },

        actionEmettiInvito: function () {
            var self = this;

            Espo.Ui.confirm({
                message: 'Confermi l\'emissione dell\'invito a fatturare?'
            }).then(function () {
                return Espo.Ajax.postRequest('InvitoAFatturare/action/emetti', {
                    id: self.model.id,
                });
            }).then(function () {
                Espo.Ui.success('Invito emesso.');
                self.model.fetch();
                self.reRender();
            });
        },
    });
});
