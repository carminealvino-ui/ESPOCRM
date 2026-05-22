// ========================================
// VERSIONE: 1.0.3
// DATA: 2026-05-22
// AUTORE: CARMINE ALVINO + IA
// FILE: custom/Espo/Custom/Resources/client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// STORICO FIX
// ----------------------------------------
// BASE: 1.0.1
// Mostra bottone Crea Contratto solo su Opportunity.
//
// 1.0.2
// Mostra bottone Crea Contratto solo se Opportunity.stage
// e' Closed Won.
//
// 1.0.3
// Fix percorso client corretto per vista:
// custom:views/opportunity/record/detail
//
// OBIETTIVO:
// Il pulsante Crea Contratto deve comparire solo quando
// Opportunity.stage = Closed Won.
// La mappatura Lead resta gestita da Appuntamento.sottostato.
//
// ROLLBACK:
// crm/application/backup/hooks_cleanup/
// backup-opportunity-detail-1.0.2-create-contract-button-closed-won-stabile.js
// ========================================

/* global define */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

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
            // SOLO CONCLUSA POSITIVAMENTE (1.0.3)
            // ========================================

            if (!this.isCreateContractAllowed()) {
                return;
            }

            // ========================================
            // BUTTON (1.0.3)
            // ========================================

            this.addMenuItem('buttons', {
                name: 'createContract',
                label: 'Crea Contratto',
                style: 'primary',
                action: 'createContract'
            });
        },

        // ========================================
        // CONTROLLO VISIBILITA (1.0.3)
        // ========================================

        isCreateContractAllowed: function () {
            return this.model.get('stage') === 'Closed Won';
        },

        // ========================================
        // ACTION (STABILE)
        // ========================================

        actionCreateContract: function () {
            console.log('Create Contract clicked');
        }

    });
});
