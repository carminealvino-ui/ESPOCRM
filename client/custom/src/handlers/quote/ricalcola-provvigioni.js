/* global define, Espo */

define('custom:handlers/quote/ricalcola-provvigioni', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionRicalcolaProvvigioni: function () {
            var view = this.view;
            var model = view.model;

            if (!model.get('opportunityId')) {
                Espo.Ui.error('Collegare un\'opportunità al contratto prima del ricalcolo.');

                return;
            }

            Espo.Ui.confirm({
                message: 'Ricalcolare tutte le provvigioni consolidate da regole provvigionali?'
            }).then(function () {
                return Espo.Ajax.postRequest('Quote/action/ricalcolaProvvigioni', {
                    id: model.id
                });
            }).then(function (result) {
                Espo.Ui.success(
                    'Provvigioni aggiornate: ' + (result.count || 0)
                );
                model.fetch();
                view.reRender();
            });
        }
    });
});
