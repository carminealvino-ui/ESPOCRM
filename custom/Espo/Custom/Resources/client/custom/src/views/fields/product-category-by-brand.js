// ========================================
// VERSIONE: 1.6.2
// DATA: 2026-05-27
// FILE: client/custom/src/views/fields/product-category-by-brand.js
// VERIFICA DEPLOY: grep "VERSIONE: 1.6.2" sui tre path in produzione
// ========================================
//
// FIX 1.6.2
// -----------------------------------------------------
// ARIEL/ARTEL: aggiunti BIOMASSA e FOTOVOLTAICO (nome anagrafica)
//
// FIX 1.6.1
// -----------------------------------------------------
// - Nessun filtro provvigionale (gruppo/regime)
// - Filtro OR: nomi categoria del brand O productBrandId collegato
// - Fallback: solo productBrandId, poi nessun filtro
//
// ========================================

/* global define */

define('custom:views/fields/product-category-by-brand', ['views/fields/link'], function (Dep) {

    var BRAND_CATEGORY_NAMES = {
        ARIEL: [
            'CLIMATIZZATORI',
            'CLIMATIZZAZIONE',
            'CALDAIE A GAS',
            'CALDAIE',
            'BIOMASSA',
            'STUFE',
            'STUFE A PELLET',
            'FOTOVOLTAICO'
        ],
        ARTEL: [
            'CLIMATIZZATORI',
            'CLIMATIZZAZIONE',
            'CALDAIE A GAS',
            'CALDAIE',
            'BIOMASSA',
            'STUFE',
            'STUFE A PELLET',
            'FOTOVOLTAICO'
        ],
        ARQUATI: [
            'TENDA VERTICALE',
            'TENDA A BRACCI',
            'TENDA A CUPOLA',
            'PERGOLA',
            'BIOCLIMATICA',
            'VETROTENDA',
            'VETRATA IMPACCHETTABILE',
            'VETRATA SCORREVOLE',
            'CHIUSURE VERTICALI'
        ],
        PROGETTO: [
            'TENDA VERTICALE',
            'TENDA A BRACCI',
            'TENDA A CUPOLA',
            'PERGOLA',
            'BIOCLIMATICA',
            'VETROTENDA',
            'VETRATA IMPACCHETTABILE',
            'VETRATA SCORREVOLE',
            'CHIUSURE VERTICALI'
        ],
        GFB: [
            'VODAFONE VOCE',
            'ENEL BUSINESS'
        ]
    };

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:productBrandId', function () {
                this.refreshCategoryFieldState();
            });

            this.listenTo(this.model, 'change:productBrandName', function () {
                this.refreshCategoryFieldState();
            });

            this.listenTo(this.model, 'change:azienda', function () {
                this.resolveBrandFromAzienda().then(function () {
                    this.refreshCategoryFieldState();
                }.bind(this));
            });

            this.resolveBrandFromAzienda().then(function () {
                this.refreshCategoryFieldState();
            }.bind(this));
        },

        refreshCategoryFieldState: function () {
            this.reRender();

            if (typeof this.getRecordView === 'function' && this.getRecordView()) {
                var recordView = this.getRecordView();

                if (recordView.dynamicLogic && typeof recordView.dynamicLogic.sync === 'function') {
                    recordView.dynamicLogic.sync();
                }
            }
        },

        getBrandKey: function () {
            var name = this.model.get('productBrandName') || this.model.get('azienda') || '';

            return String(name).trim().toUpperCase();
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

                this.model.set(data);

                if (data.productBrandId) {
                    this.model.trigger('change:productBrandId', this.model, data.productBrandId, {});
                }
            }.bind(this));
        },

        buildBrandScopeFilter: function (names, brandId) {
            var parts = [];

            if (names && names.length) {
                parts.push({
                    type: 'in',
                    attribute: 'name',
                    value: names
                });
            }

            if (brandId) {
                parts.push({
                    type: 'equals',
                    attribute: 'productBrandId',
                    value: brandId
                });
            }

            if (!parts.length) {
                return null;
            }

            if (parts.length === 1) {
                return parts[0];
            }

            return {
                type: 'or',
                value: parts
            };
        },

        getSelectFilters: function () {
            var brandKey = this.getBrandKey();
            var brandId = this.model.get('productBrandId');
            var names = brandKey ? BRAND_CATEGORY_NAMES[brandKey] : null;
            var scopeFilter = this.buildBrandScopeFilter(names, brandId);

            if (scopeFilter) {
                return {
                    brandScope: scopeFilter
                };
            }

            return null;
        }
    });
});
