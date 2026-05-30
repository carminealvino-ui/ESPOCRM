// ========================================
// VERSIONE: 1.0.6
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// STORICO FIX
// ----------------------------------------
// 1.0.5: visibilita tramite checkVisibilityFunction nel clientDefs.
// 1.0.6: filtro menu in getMenu + re-render header su change:stage.
//         Funziona anche se l'handler JS non e' caricato (deploy incompleto).
//
// OBIETTIVO:
// Mostrare Crea Contratto solo su Opportunity.stage = Closed Won.
// ========================================

/* global define */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:stage sync', function () {
                this.controlCreateContrattoHeader();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.controlCreateContrattoHeader();
        },

        isClosedWon: function () {
            return this.model.get('stage') === 'Closed Won';
        },

        getMenu: function () {
            var menu = Dep.prototype.getMenu.call(this);

            return this.filterCreateContrattoMenu(menu);
        },

        filterCreateContrattoMenu: function (menu) {
            if (this.isClosedWon()) {
                return menu;
            }

            var hiddenNames = ['createContratto', 'createContract'];

            (this.headerActionItemTypeList || ['buttons', 'dropdown']).forEach(function (type) {
                if (!menu[type]) {
                    return;
                }

                menu[type] = menu[type].filter(function (item) {
                    var name = item.name || item.action;

                    return hiddenNames.indexOf(name) === -1;
                });
            });

            return menu;
        },

        controlCreateContrattoHeader: function () {
            if (typeof this.getHeaderView === 'function') {
                var header = this.getHeaderView();

                if (header) {
                    header.reRender();
                }
            }

            if (!this.isClosedWon()) {
                this.hideLegacyCreateContractButtons();
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
