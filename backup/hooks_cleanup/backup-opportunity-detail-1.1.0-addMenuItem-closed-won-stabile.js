// ========================================
// VERSIONE: 1.1.0
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// RIPRISTINO LOGICA STABILE 1.0.2
// addMenuItem solo su opportunita concluse positivamente.
// Rimosso checkVisibilityFunction e hide DOM (non affidabili).
// ========================================

/* global define */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            if (Dep.prototype.setup) {
                Dep.prototype.setup.apply(this, arguments);
            }

            this.manageCreateContrattoButton();

            this.listenTo(this.model, 'change:stage change:probability sync', function () {
                this.manageCreateContrattoButton();
            });
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

        manageCreateContrattoButton: function () {
            if (this.isClosedWon()) {
                this.addCreateContrattoButtonIfNeeded();

                return;
            }

            this.removeCreateContrattoButtonIfNeeded();
        },

        addCreateContrattoButtonIfNeeded: function () {
            if (this._createContrattoMenuAdded) {
                return;
            }

            this.addMenuItem('buttons', {
                name: 'createContratto',
                label: 'Crea Contratto',
                style: 'primary',
                handler: 'custom:action-handlers/opportunity/create-contratto',
                actionFunction: 'createContratto'
            });

            this._createContrattoMenuAdded = true;
        },

        removeCreateContrattoButtonIfNeeded: function () {
            if (!this.removeMenuItem) {
                return;
            }

            this.removeMenuItem('createContratto', false);
            this.removeMenuItem('createContract', false);
            this._createContrattoMenuAdded = false;
        }
    });
});
