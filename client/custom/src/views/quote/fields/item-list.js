// ========================================
// VERSIONE: 1.1.0
// DATA: 2026-05-26
// FILE: custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
// ========================================

/* global define */

define('custom:views/quote/fields/item-list', ['sales:views/quote/fields/item-list'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            var originalCreateView = this.createView.bind(this);

            this.createView = function (name, view, options, callback) {
                options = options || {};

                var viewStr = typeof view === 'string' ? view : '';
                var isSelectRecords = viewStr.indexOf('select-records') !== -1;

                // Nel contesto item-list del contratto, la modale select-records
                // viene usata per i prodotti: forziamo scope Product.
                if (isSelectRecords) {
                    options.entityType = 'Product';
                    options.scope = 'Product';
                    options.createButton = true;
                    view = 'custom:views/modals/select-product-for-quote';
                }

                return originalCreateView(name, view, options, callback);
            };
        },
    });
});
