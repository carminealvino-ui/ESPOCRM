// ========================================
// VERSIONE: 1.0.9
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// FIX 1.0.9
// - Nessun getMenu (evita crash Espo 8+)
// - Pulsante sempre nel menu, visibilita via show/hide DOM
// - Riconosce Closed Won e label IT "Chiuso Positivamente"
// ========================================

/* global define, $ */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            if (Dep.prototype.setup) {
                Dep.prototype.setup.apply(this, arguments);
            }

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
            var show = this.isClosedWon();
            var names = ['createContratto', 'createContract'];

            names.forEach(function (name) {
                var $btn = this.findMenuButton(name);

                if ($btn && $btn.length) {
                    if (show) {
                        $btn.show();
                    } else {
                        $btn.hide();
                    }
                }
            }, this);
        },

        findMenuButton: function (name) {
            var selector = '[data-name="' + name + '"], [data-action="' + name + '"]';

            if (this.$headerActionsContainer && this.$headerActionsContainer.length) {
                return this.$headerActionsContainer.find(selector).closest('a, button, .btn');
            }

            if (this.$el) {
                return this.$el.find(selector).closest('a, button, .btn');
            }

            return $(selector).closest('a, button, .btn');
        }
    });
});
