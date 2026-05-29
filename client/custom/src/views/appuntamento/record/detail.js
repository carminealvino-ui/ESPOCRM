// =====================================================
// VERSIONE: 2.0.6
// DATA: 09-05-2026 12:04
// TIMEZONE: Europe/Rome
// AUTORE: CARMINE ALVINO + CHATGPT
// =====================================================
//
// FIX DEFINITIVO FULL VIEW
//
// Problema:
// - Full View rompeva Modifica
//
// Causa:
// - addButton / buttonList incompatibili
//
// Soluzione:
// - setupActionItems()
// - actionItems EspoCRM standard
//
// =====================================================

define(
    'custom:views/appuntamento/record/detail',
    ['views/record/detail'],
    function (Dep) {

        return Dep.extend({

            // =============================================
            // ACTION ITEMS
            // =============================================

            setupActionItems: function () {

                Dep.prototype.setupActionItems.call(this);

                // =========================================
                // SICUREZZA
                // =========================================

                if (
                    !this.model ||
                    this.model.entityType !== 'Appuntamento'
                ) {
                    return;
                }

                // =========================================
                // SOLO SVOLTO
                // =========================================

                if (
                    this.model.get('status') !== 'Held'
                ) {
                    return;
                }

                // =========================================
                // MENU ACTION
                // =========================================

                this.dropdownItemList.push({

                    label: 'Crea Opportunità',

                    name: 'createOpportunity'
                });
            },

            // =============================================
            // ACTION
            // =============================================

            actionCreateOpportunity: function () {

                this.notify(
                    'Apertura Opportunità...'
                );

                let dataOpportunita = null;

                const dateStart =
                    this.model.get('dateStart');

                if (dateStart) {

                    dataOpportunita =
                        dateStart.substring(0, 10);
                }

                this.createView(
                    'createOpportunityDialog',

                    'views/modals/edit',

                    {

                        scope: 'Opportunity',

                        attributes: {

                            appuntamentoId:
                                this.model.id,

                            appuntamentoName:
                                this.model.get('name'),

                            dataOpportunit:
                                dataOpportunita,

                            azienda:
                                this.model.get('azienda'),

                            lineaProdotto:
                                this.model.get('lineaProdotto'),

                            telefono:
                                this.model.get('telefono')
                        }

                    },

                    function (view) {

                        view.render();
                    }
                );
            }
        });
    }
);
