// ========================================
// VERSIONE: 1.1.0
// DATA: 2026-05-26
// FILE: custom/Espo/Custom/Resources/client/custom/src/views/modals/select-product-for-quote.js
// ========================================
//
// Modale selezione prodotto su contratto: forza pulsante Crea se ACL lo consente.
//
/* global define */

define('custom:views/modals/select-product-for-quote', ['views/modals/select-records'], function (Dep) {

    return Dep.extend({

        setup: function () {
            this.options = this.options || {};
            this.options.entityType = 'Product';
            this.options.scope = 'Product';
            this.options.createButton = true;

            Dep.prototype.setup.call(this);

            if (this.createButton) {
                return;
            }

            if (!this.getAcl().check('Product', 'create')) {
                return;
            }

            this.createButton = true;

            this.addButton({
                name: 'create',
                position: 'right',
                onClick: function () {
                    this.create();
                },
                iconClass: 'fas fa-plus fa-sm',
                label: 'Create',
            });
        },
    });
});
