// ========================================
// VERSIONE: 1.1.0
// DATA: 2026-05-24
// FILE: client/custom/src/views/fields/fornitore-partner-cascade.js
// ========================================

/* global define */

define('custom:views/fields/fornitore-partner-cascade', ['views/fields/link'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:fornitorePartnerId', function (model, value, options) {
                if (!options || !options.ui || options.prospectSync) {
                    return;
                }

                model.set({
                    productBrandId: null,
                    productBrandName: null,
                    productCategoryId: null,
                    productCategoryName: null
                }, {ui: true});
            });
        }
    });
});
