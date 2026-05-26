// ========================================
// VERSIONE: 1.3.0
// DATA: 2026-05-26
// FILE: client/custom/src/views/fields/product-category-by-brand.js
// ========================================

/* global define */

define('custom:views/fields/product-category-by-brand', ['views/fields/link'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:productBrandId change:azienda', function () {
                this.resolveBrandFromAzienda().then(function () {
                    this.reRender();
                }.bind(this));
            });

            this.resolveBrandFromAzienda();
        },

        resolveBrandFromAzienda: function () {
            if (this.model.get('productBrandId')) {
                return Promise.resolve();
            }

            var azienda = this.model.get('azienda');

            if (!azienda) {
                return Promise.resolve();
            }

            return Espo.Ajax.getRequest('ProductBrand', {
                where: [
                    {
                        type: 'equals',
                        attribute: 'name',
                        value: azienda
                    }
                ],
                maxSize: 1,
                select: ['id', 'name', 'fornitorePartnerId', 'fornitorePartnerName']
            }).then(function (response) {
                if (!response.list || !response.list.length) {
                    return;
                }

                var brand = response.list[0];
                var data = {
                    productBrandId: brand.id,
                    productBrandName: brand.name
                };

                if (brand.fornitorePartnerId && !this.model.get('fornitorePartnerId')) {
                    data.fornitorePartnerId = brand.fornitorePartnerId;
                    data.fornitorePartnerName = brand.fornitorePartnerName;
                }

                this.model.set(data, {silent: true});
            }.bind(this));
        },

        getSelectFilters: function () {
            var brandId = this.model.get('productBrandId');

            if (!brandId) {
                return;
            }

            return {
                productBrand: {
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
            };
        }
    });
});
