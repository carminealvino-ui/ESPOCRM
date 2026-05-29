// ========================================
// VERSIONE: 1.0.3
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/action-handlers/opportunity/create-contratto.js
// ========================================
//
// FIX 1.0.3
// Handler minimale, senza initFunction.
// La vista detail custom e' stata rimossa per evitare crash loader.
// ========================================

/* global define, Espo */

define('custom:action-handlers/opportunity/create-contratto', ['action-handler'], function (Dep) {

    return Dep.extend({

        isCreateContrattoVisible: function () {
            if (!this.view || !this.view.model) {
                return false;
            }

            return this.view.model.get('stage') === 'Closed Won';
        },

        createContratto: function () {
            if (!this.isCreateContrattoVisible()) {
                return;
            }

            var self = this;

            this.view.disableMenuItem('createContratto');

            Espo.Ajax.postRequest(
                'Opportunity/action/createContratto',
                {
                    id: this.view.model.id
                }
            ).then(function (result) {
                self.view.enableMenuItem('createContratto');

                if (result && result.quoteId) {
                    window.location.hash = '#Quote/view/' + result.quoteId;
                }
            }).catch(function (e) {
                self.view.enableMenuItem('createContratto');
                throw e;
            });
        }
    });
});
