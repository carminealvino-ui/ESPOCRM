// ========================================
// VERSIONE: 1.0.1
// DATA: 2026-05-24
// FILE: client/custom/src/views/fields/product-category-by-brand.js
// ========================================

/* global define */

define('custom:views/fields/product-category-by-brand', ['views/fields/link'], function (Dep) {

    return Dep.extend({

        getSelectFilters: function () {
            var brandId = this.model.get('productBrandId');

            if (!brandId) {
                return;
            }

            return [
                {
                    type: 'equals',
                    attribute: 'productBrandId',
                    value: brandId
                }
            ];
        }
    });
});
