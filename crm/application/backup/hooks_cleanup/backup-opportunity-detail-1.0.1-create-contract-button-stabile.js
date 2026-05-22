// ========================================
// CREATE CONTRACT BUTTON FIX (1.0.1)
// ========================================
// PROBLEMA:
// Il bottone veniva mostrato su TUTTE le entità
//
// FIX:
// Mostriamo il bottone SOLO su Opportunity
// ========================================

define('custom:views/opportunity/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            // ========================================
            // 🔥 FIX — SOLO SU OPPORTUNITY
            // ========================================
            if (this.model.entityType !== 'Opportunity') {
                return;
            }

            // ========================================
            // BUTTON (STABILE)
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

            // qui tua logica
        }

    });
});
