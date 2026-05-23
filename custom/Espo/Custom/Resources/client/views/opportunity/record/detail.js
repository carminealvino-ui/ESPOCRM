// ========================================
// VERSIONE: 1.1.1
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// FIX 1.1.1
// Rimosso listenTo su change:stage/sync: re-render header bloccava
// la selezione di "Chiuso Positivamente" nel menu stato.
// Gestione pulsante solo in afterRender (dopo salvataggio/reload).
// ========================================

/* global define */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            if (Dep.prototype.setup) {
                Dep.prototype.setup.apply(this, arguments);
            }

            this._createContrattoMenuAdded = false;
        },

        afterRender: function () {
            if (Dep.prototype.afterRender) {
                Dep.prototype.afterRender.apply(this, arguments);
            }

            this.manageCreateContrattoButton();
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
            }, false, true);

            this._createContrattoMenuAdded = true;

            if (typeof this.getHeaderView === 'function') {
                var header = this.getHeaderView();

                if (header && header.reRender) {
                    header.reRender();
                }
            }
        },

        removeCreateContrattoButtonIfNeeded: function () {
            if (!this.removeMenuItem) {
                return;
            }

            this.removeMenuItem('createContratto', true);
            this.removeMenuItem('createContract', true);
            this._createContrattoMenuAdded = false;
        }
    });
});
