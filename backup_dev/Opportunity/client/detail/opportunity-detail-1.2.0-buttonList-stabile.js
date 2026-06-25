// ========================================
// VERSIONE: 1.2.0
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// FIX 1.2.0
// I pulsanti header Opportunity sono su buttonList (addButton /
// showActionItem), NON su addMenuItem del MainView.
// clientDefs.buttonList registra il bottone; la vista lo mostra
// solo su opportunita concluse positivamente.
// ========================================

/* global define, Espo */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            if (Dep.prototype.setup) {
                Dep.prototype.setup.apply(this, arguments);
            }

            this.updateCreateContrattoButton();

            this.listenTo(this.model, 'change:stage change:probability sync', function () {
                this.updateCreateContrattoButton();
            });
        },

        afterRender: function () {
            if (Dep.prototype.afterRender) {
                Dep.prototype.afterRender.apply(this, arguments);
            }

            this.updateCreateContrattoButton();
        },

        isClosedWon: function () {
            var stage = this.model.get('stage');

            if (stage === 'Closed Won' || stage === 'Chiuso Positivamente') {
                return true;
            }

            if (stage === 'Closed Lost' || stage === 'Chiusa persa' || stage === 'Chiuso Negativamente') {
                return false;
            }

            var probability = this.model.get('probability');

            return probability === 100 || probability === '100';
        },

        updateCreateContrattoButton: function () {
            if (!this.showActionItem || !this.hideActionItem) {
                return;
            }

            if (this.isClosedWon()) {
                this.showActionItem('createContratto');

                return;
            }

            this.hideActionItem('createContratto');
        },

        actionCreateContratto: function () {
            if (!this.isClosedWon()) {
                Espo.Ui.warning('Disponibile solo su opportunita concluse positivamente.');

                return;
            }

            var self = this;

            this.disableActionItem('createContratto');

            Espo.Ajax.postRequest(
                'Opportunity/action/createContratto',
                {
                    id: this.model.id
                }
            ).then(function (result) {
                self.enableActionItem('createContratto');

                if (result && result.quoteId) {
                    window.location.hash = '#Quote/view/' + result.quoteId;
                }
            }).catch(function (e) {
                self.enableActionItem('createContratto');
                throw e;
            });
        }
    });
});
