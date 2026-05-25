/* global define, Espo */

define('custom:views/invito-a-fatturare/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.addMenuItem('buttons', {
                name: 'generaDaProvvigioni',
                label: 'Genera da provvigioni',
                style: 'default',
                action: 'generaDaProvvigioni',
                acl: 'edit'
            });

            this.addMenuItem('buttons', {
                name: 'emettiInvito',
                label: 'Emetti invito',
                style: 'primary',
                action: 'emettiInvito',
                acl: 'edit'
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
                message: 'Collegare le provvigioni consolidate del mese ' + mese + ' a questo invito?'
            }).then(function () {
                return Espo.Ajax.postRequest('InvitoAFatturare/action/generaDaProvvigioni', {
                    consulenteId: consulenteId,
                    meseCompetenza: mese,
                    fornitorePartnerId: self.model.get('fornitorePartnerId'),
                    productBrandId: self.model.get('productBrandId')
                });
            }).then(function (result) {
                Espo.Ui.success(
                    'Provvigioni collegate: ' + (result.count || 0)
                );
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
                    id: self.model.id
                });
            }).then(function () {
                Espo.Ui.success('Invito emesso.');
                self.model.fetch();
                self.reRender();
            });
        }
    });
});
