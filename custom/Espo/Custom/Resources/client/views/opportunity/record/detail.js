// ========================================
// VERSIONE: 1.0.5
// DATA: 2026-05-22
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// STORICO FIX
// ----------------------------------------
// BASE: 1.0.4
// Tentativo controllo pulsante tramite addMenuItem condizionale.
//
// FIX 1.0.5
// Rimosso addMenuItem manuale.
// La visibilita del pulsante e' demandata al clientDefs ufficiale
// con checkVisibilityFunction.
//
// OBIETTIVO:
// evitare che il bottone Crea Contratto venga aggiunto su
// Opportunity non concluse positivamente.
//
// NOTA:
// La mappatura Lead resta gestita da Appuntamento.sottostato.
// ========================================

/* global define */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'sync change:stage', function () {
                this.hideLegacyCreateContractButtonIfNeeded();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.hideLegacyCreateContractButtonIfNeeded();
        },

        hideLegacyCreateContractButtonIfNeeded: function () {
            if (this.model.get('stage') === 'Closed Won') {
                return;
            }

            this.hideLegacyButtonByAction('createContract');
            this.hideLegacyButtonByAction('createContratto');
        },

        hideLegacyButtonByAction: function (action) {
            var selector = '[data-action="' + action + '"], [data-name="' + action + '"]';

            if (this.$el) {
                this.$el.find(selector).closest('a, button, .btn').hide();
            }
        }
    });
});
