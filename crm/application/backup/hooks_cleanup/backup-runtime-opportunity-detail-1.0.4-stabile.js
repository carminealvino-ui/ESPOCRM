// ========================================
// VERSIONE: 1.0.4
// DATA: 2026-05-22
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// STORICO FIX
// ----------------------------------------
// BASE: 1.0.3
// Il pulsante Crea Contratto deve comparire solo quando
// Opportunity.stage = Closed Won.
//
// FIX 1.0.4
// Aggiunto file nel percorso runtime standard EspoCRM:
// client/custom/src/views/opportunity/record/detail.js
//
// OBIETTIVO:
// evitare che EspoCRM carichi una vecchia vista client che
// mostrava sempre il pulsante Crea Contratto.
//
// NOTA:
// La mappatura Lead resta gestita da Appuntamento.sottostato.
// Questo file controlla solo il pulsante del contratto.
//
// ROLLBACK:
// eliminare questo file oppure ripristinare il backup server
// se esisteva gia' un file nello stesso percorso.
// ========================================

/* global define */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.entityType !== 'Opportunity') {
                return;
            }

            if (!this.isCreateContractAllowed()) {
                return;
            }

            this.addMenuItem('buttons', {
                name: 'createContract',
                label: 'Crea Contratto',
                style: 'primary',
                action: 'createContract'
            });
        },

        isCreateContractAllowed: function () {
            return this.model.get('stage') === 'Closed Won';
        },

        actionCreateContract: function () {
            console.log('Create Contract clicked');
        }

    });
});
