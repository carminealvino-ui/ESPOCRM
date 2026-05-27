// ========================================
// VERSIONE: 1.4.1
// DATA: 2026-05-26
// FILE: client/custom/src/views/fields/product-category-by-brand.js
// ========================================

/* global define */

define('custom:views/fields/product-category-by-brand', ['views/fields/link'], function (Dep) {

    var BRAND_CATEGORY_FILTER = {
        ARIEL: {
            names: [
                'CLIMATIZZATORI',
                'CLIMATIZZAZIONE',
                'CALDAIE A GAS',
                'CALDAIE',
                'STUFE',
                'STUFE A PELLET',
                'FOTOVOLTAICO'
            ],
            gruppoProvvigione: ['Clima e altro'],
            regimeProvvigione: ['ARIEL_2026']
        },
        ARTEL: {
            names: [
                'CLIMATIZZATORI',
                'CLIMATIZZAZIONE',
                'CALDAIE A GAS',
                'CALDAIE',
                'STUFE',
                'STUFE A PELLET',
                'FOTOVOLTAICO'
            ],
            gruppoProvvigione: ['Clima e altro']
        },
        ARQUATI: {
            names: [
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
            gruppoProvvigione: ['Tende da Sole', 'Pergole', 'Vetrate']
        },
        PROGETTO: {
            names: [
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
            gruppoProvvigione: ['Tende da Sole', 'Pergole', 'Vetrate']
        },
        GFB: {
            regimeProvvigione: [
                'GFB_VODAFONE_COEFF',
                'GFB_FASTWEB_POD',
                'GFB_RS_BIMESTRE'
            ]
        }
    };

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:productBrandId', function () {
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

        buildFilterFromConfig: function (config) {
            if (config.names && config.names.length) {
                return {
                    byCategoryName: {
                        type: 'in',
                        attribute: 'name',
                        value: config.names
                    }
                };
            }

            if (config.gruppoProvvigione && config.gruppoProvvigione.length) {
                return {
                    byGruppo: {
                        type: 'in',
                        attribute: 'gruppoProvvigione',
                        value: config.gruppoProvvigione
                    }
                };
            }

            if (config.regimeProvvigione && config.regimeProvvigione.length) {
                return {
                    byRegime: {
                        type: 'in',
                        attribute: 'regimeProvvigione',
                        value: config.regimeProvvigione
                    }
                };
            }

            return null;
        },

        getSelectFilters: function () {
            var brandKey = this.getBrandKey();
            var brandId = this.model.get('productBrandId');

            if (!brandKey && !brandId) {
                return;
            }

            if (brandKey && BRAND_CATEGORY_FILTER[brandKey]) {
                var mapped = this.buildFilterFromConfig(BRAND_CATEGORY_FILTER[brandKey]);

                if (mapped) {
                    return mapped;
                }
            }

            if (brandId) {
                return {
                    productBrandLinked: {
                        type: 'equals',
                        attribute: 'productBrandId',
                        value: brandId
                    }
                };
            }

            return;
        }
    });
});
