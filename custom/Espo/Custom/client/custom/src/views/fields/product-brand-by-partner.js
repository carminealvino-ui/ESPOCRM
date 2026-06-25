// ========================================
// VERSIONE: 1.3.0
// DATA: 2026-06-10
// FILE: client/custom/src/views/fields/product-brand-by-partner.js
// ========================================

/* global define */

define('custom:views/fields/product-brand-by-partner', ['views/fields/link'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:productBrandId', function (model, value, options) {
                if (!options || !options.ui || options.prospectSync) {
                    return;
                }

                model.set({
                    productCategoryId: null,
                    productCategoryName: null
                }, {ui: true});
            });
        },

        getSelectFilters: function () {
            var partnerId = this.model.get('fornitorePartnerId');

            if (!partnerId) {
                return;
            }

            return {
                fornitorePartner: {
                    type: 'equals',
                    attribute: 'fornitorePartnerId',
                    value: partnerId
                }
            };
        }
    });
});
