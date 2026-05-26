// ========================================
// VERSIONE: 1.0.0
// DATA: 2026-05-26
// FILE: custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
// ========================================
//
// Mostra "Crea" nella modale Seleziona Prodotti (articoli contratto).
// Sales Pack a volte passa createButton: false; ACL Product create resta obbligatorio.
//
/* global define */

define('custom:views/quote/fields/item-list', ['sales:views/quote/fields/item-list'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            var originalCreateView = this.createView.bind(this);

            this.createView = function (name, view, options, callback) {
                if (
                    view === 'views/modals/select-records' &&
                    options &&
                    (options.entityType === 'Product' || options.scope === 'Product')
                ) {
                    options.createButton = true;
                }

                return originalCreateView(name, view, options, callback);
            };
        },
    });
});
