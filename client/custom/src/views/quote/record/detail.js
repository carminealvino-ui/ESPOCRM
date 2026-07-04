/* global define, Espo */

define('custom:views/quote/record/detail', ['sales:views/quote/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.addMenuItem('buttons', {
                name: 'ricalcolaProvvigioni',
                label: this.translate('Ricalcola provvigioni', 'labels', 'Quote'),
                style: 'default',
                action: 'ricalcolaProvvigioni',
                acl: 'edit'
            });
        },

        actionRicalcolaProvvigioni: function () {
            var self = this;

            if (!this.model.get('opportunityId')) {
                Espo.Ui.error('Collegare un\'opportunità al contratto prima del ricalcolo.');

                return;
            }

            Espo.Ui.confirm({
                message: 'Ricalcolare tutte le provvigioni consolidate da regole provvigionali?'
            }).then(function () {
                return Espo.Ajax.postRequest('Quote/action/ricalcolaProvvigioni', {
                    id: self.model.id
                });
            }).then(function (result) {
                Espo.Ui.success(
                    'Provvigioni aggiornate: ' + (result.count || 0)
                );
                self.model.fetch();
                self.reRender();
            });
        }
    });
});
