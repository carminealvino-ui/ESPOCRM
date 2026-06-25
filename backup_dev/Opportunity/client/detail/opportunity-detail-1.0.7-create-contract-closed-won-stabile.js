// ========================================
// VERSIONE: 1.0.7
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// FIX 1.0.7
// Rimosso override getMenu (non esiste su views/record/detail in Espo 8+).
// Evitato reRender header in afterRender (loop / crash loader).
//
// OBIETTIVO:
// Mostrare Crea Contratto solo su Opportunity.stage = Closed Won.
// ========================================

/* global define */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.filterCreateContrattoMenuDefinition();

            this.listenTo(this.model, 'change:stage', function () {
                this.filterCreateContrattoMenuDefinition();
                this.onStageChanged();
            });

            this.listenTo(this.model, 'sync', function () {
                this.filterCreateContrattoMenuDefinition();
                this.onStageChanged();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.isClosedWon()) {
                this.hideLegacyCreateContractButtons();
            }
        },

        isClosedWon: function () {
            return this.model.get('stage') === 'Closed Won';
        },

        filterCreateContrattoMenuDefinition: function () {
            if (!this.menu || this.isClosedWon()) {
                return;
            }

            var hiddenNames = ['createContratto', 'createContract'];
            var types = ['buttons', 'dropdown'];

            types.forEach(function (type) {
                if (!this.menu[type] || !this.menu[type].length) {
                    return;
                }

                this.menu[type] = this.menu[type].filter(function (item) {
                    var name = item.name || item.action;

                    return hiddenNames.indexOf(name) === -1;
                });
            }, this);
        },

        onStageChanged: function () {
            if (!this.isClosedWon()) {
                this.hideLegacyCreateContractButtons();
            }

            if (typeof this.getHeaderView !== 'function') {
                return;
            }

            var header = this.getHeaderView();

            if (header && typeof header.reRender === 'function') {
                header.reRender();
            }
        },

        hideLegacyCreateContractButtons: function () {
            this.hideLegacyButtonByAction('createContract');
            this.hideLegacyButtonByAction('createContratto');
        },

        hideLegacyButtonByAction: function (action) {
            var selector = '[data-action="' + action + '"], [data-name="' + action + '"]';
            var $scope = null;

            if (this.$headerActionsContainer && this.$headerActionsContainer.length) {
                $scope = this.$headerActionsContainer;
            } else if (this.$el) {
                $scope = this.$el;
            }

            if ($scope) {
                $scope.find(selector).closest('a, button, .btn').hide();
            }
        }
    });
});
