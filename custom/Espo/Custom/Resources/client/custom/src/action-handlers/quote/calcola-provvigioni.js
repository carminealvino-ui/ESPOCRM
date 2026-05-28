/* global define, Espo */

define('custom:action-handlers/quote/calcola-provvigioni', ['action-handler'], function (Dep) {

    return Dep.extend({

        calcolaProvvigioni: function () {
            var self = this;
            var model = this.view.model;

            if (!model.get('opportunityId')) {
                Espo.Ui.error('Collegare il contratto a un\'opportunità.');

                return;
            }

            if ((model.get('modalitaCalcoloProvvigioni') || 'Manuale') === 'Manuale') {
                var ids = model.get('regoleProvvigionaliIds') || [];

                if (!ids.length) {
                    Espo.Ui.error('Selezionare almeno una Regola provvigionale.');

                    return;
                }
            }

            this.view.disableMenuItem('calcolaProvvigioni');

            Espo.Ajax.postRequest('Quote/action/calcolaProvvigioni', {
                id: model.id
            }).then(function (result) {
                self.view.enableMenuItem('calcolaProvvigioni');

                var count = result.count || 0;
                var totale = result.totaleProvvigioni;

                Espo.Ui.success(
                    'Provvigioni calcolate: ' + count +
                    (totale != null ? ' — totale € ' + totale.toFixed(2) : '')
                );

                model.fetch();
                self.view.trigger('after:save');
            }).catch(function () {
                self.view.enableMenuItem('calcolaProvvigioni');
            });
        }
    });
});
