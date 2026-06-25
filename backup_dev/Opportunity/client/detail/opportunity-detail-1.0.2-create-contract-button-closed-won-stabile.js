// ========================================
// VERSIONE: 1.0.2
// DATA: 2026-05-22
// AUTORE: CARMINE ALVINO + IA
// FILE: custom/Espo/Custom/Resources/client/views/opportunity/record/detail.js
// ========================================
//
// STORICO FIX
// ----------------------------------------
// BASE: 1.0.1
// Mostra bottone Crea Contratto solo su Opportunity.
//
// FIX 1.0.2
// Mostra bottone Crea Contratto solo se Opportunity.stage
// e' Closed Won.
//
// ROLLBACK:
// crm/application/backup/hooks_cleanup/
// backup-opportunity-detail-1.0.1-create-contract-button-stabile.js
// ========================================

/* global define */

define('custom:views/opportunity/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            // ========================================
            // SOLO SU OPPORTUNITY (1.0.1) - STABILE
            // ========================================

            if (this.model.entityType !== 'Opportunity') {
                return;
            }

            // ========================================
            // SOLO OPPORTUNITY CONCLUSA POSITIVAMENTE (1.0.2)
            // ========================================

            if (this.model.get('stage') !== 'Closed Won') {
                return;
            }

            // ========================================
            // BUTTON (1.0.2)
            // ========================================

            this.addMenuItem('buttons', {
                name: 'createContract',
                label: 'Crea Contratto',
                style: 'primary',
                action: 'createContract'
            });
        },

        // ========================================
        // ACTION (STABILE)
        // ========================================

        actionCreateContract: function () {
            console.log('Create Contract clicked');
        }

    });
});
