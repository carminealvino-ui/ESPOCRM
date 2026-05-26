// ========================================
// VERSIONE: 1.2.0
// DATA: 2026-05-26
// FILE: custom/Espo/Custom/Resources/client/custom/src/views/fields/product-category-by-brand.js
// ========================================

/* global define */

define('custom:views/fields/product-category-by-brand', ['views/fields/link'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:productBrandId', function () {
                this.reRender();
            });
        },

        getSelectFilters: function () {
            var brandId = this.model.get('productBrandId');

            if (!brandId) {
                return;
            }

            return [
                {
                    type: 'or',
                    value: [
                        {
                            type: 'equals',
                            attribute: 'productBrandId',
                            value: brandId
                        },
                        {
                            type: 'isNull',
                            attribute: 'productBrandId'
                        },
                        {
                            type: 'equals',
                            attribute: 'productBrandId',
                            value: ''
                        }
                    ]
                }
            ];
        }
    });
});
