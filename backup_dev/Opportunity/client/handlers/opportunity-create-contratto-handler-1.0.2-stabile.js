// ========================================
// VERSIONE: 1.0.2
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/action-handlers/opportunity/create-contratto.js
// ========================================
//
// FIX 1.0.2
// Sintassi Dep.extend (compatibile Espo 7/8), senza optional chaining.
// ========================================

/* global define, Espo */

define('custom:action-handlers/opportunity/create-contratto', ['action-handler'], function (Dep) {

    return Dep.extend({

        initCreateContratto: function () {
            this.listenTo(this.view.model, 'change:stage', function () {
                var header = null;

                if (this.view.getHeaderView) {
                    header = this.view.getHeaderView();
                }

                if (header && header.reRender) {
                    header.reRender();
                }
            });
        },

        isCreateContrattoVisible: function () {
            return this.view.model.get('stage') === 'Closed Won';
        },

        createContratto: function () {
            var self = this;

            if (!this.isCreateContrattoVisible()) {
                return;
            }

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
