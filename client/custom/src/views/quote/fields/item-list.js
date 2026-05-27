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

                var scope = options.scope || options.entityType || '';
                var scopeNorm = typeof scope === 'string' ? scope.toLowerCase() : '';

                // Sales Pack in alcuni casi passa scope/entityType non identici a 'Product'.
                // Forziamo la selezione sul modal prodotti.
                var isProductSelect =
                    (scopeNorm === 'product') ||
                    (scopeNorm === 'products') ||
                    (scopeNorm.indexOf('product') !== -1);

                isProductSelect = isSelectRecords && isProductSelect;

                if (isProductSelect) {
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
