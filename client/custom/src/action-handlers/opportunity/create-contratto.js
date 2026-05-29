// ========================================
// VERSIONE: 1.0.4
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/action-handlers/opportunity/create-contratto.js
// ========================================

/* global define, Espo */

define('custom:action-handlers/opportunity/create-contratto', ['action-handler'], function (Dep) {

    return Dep.extend({

        initCreateContratto: function () {
            if (this.view && this.view.updateCreateContrattoButton) {
                this.view.updateCreateContrattoButton();
            }
        },

        isCreateContrattoVisible: function () {
            if (!this.view || !this.view.model) {
                return false;
            }

            if (this.view.isClosedWon) {
                return this.view.isClosedWon();
            }

            var stage = this.view.model.get('stage');

            if (stage === 'Closed Won' || stage === 'Chiuso Positivamente') {
                return true;
            }

            if (stage === 'Closed Lost' || stage === 'Chiusa persa' || stage === 'Chiuso Negativamente') {
                return false;
            }

            var probability = this.view.model.get('probability');

            return probability === 100 || probability === '100';
        },

        createContratto: function () {
            if (!this.isCreateContrattoVisible()) {
                Espo.Ui.warning('Disponibile solo su opportunita concluse positivamente.');

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
