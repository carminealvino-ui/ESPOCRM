/* global define */

/**
 * Campo Relazionato a — sync autonomo (nessuna dipendenza esterna).
 * VERSIONE: 1.2.2
 */
define('custom:views/fields/appuntamento-parent', ['views/fields/link-parent'], function (Dep) {

    const VERSION = '1.2.2';

    const PROSPECT_SELECT = [
        'name',
        'azienda',
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
    ].join(',');

    const LINK_FIELDS = [
        'fornitorePartner',
        'productBrand',
        'productCategory',
        'prospect',
    ];

    const resolveBrandFromAzienda = function (azienda, data) {
        if (data.productBrandId || !azienda) {
            return Promise.resolve(data);
        }

        return Espo.Ajax.getRequest('ProductBrand', {
            where: [{type: 'equals', attribute: 'name', value: azienda}],
            maxSize: 1,
            select: ['id', 'name', 'fornitorePartnerId', 'fornitorePartnerName'],
        }).then(response => {
            if (!response.list || !response.list.length) {
                return data;
            }

            const brand = response.list[0];

            data.productBrandId = brand.id;
            data.productBrandName = brand.name;

            if (brand.fornitorePartnerId && !data.fornitorePartnerId) {
                data.fornitorePartnerId = brand.fornitorePartnerId;
                data.fornitorePartnerName = brand.fornitorePartnerName;
            }

            return data;
        });
    };

    const resolveBrandFromCategory = function (data) {
        if (data.productBrandId || !data.productCategoryId) {
            return Promise.resolve(data);
        }

        return Espo.Ajax.getRequest('ProductCategory/' + data.productCategoryId, {
            select: ['productBrandId', 'productBrandName'],
        }).then(response => {
            if (response.productBrandId) {
                data.productBrandId = response.productBrandId;
                data.productBrandName = response.productBrandName;
            }

            return data;
        });
    };

    const resolvePartnerFromBrand = function (data) {
        if (data.fornitorePartnerId || !data.productBrandId) {
            return Promise.resolve(data);
        }

        return Espo.Ajax.getRequest('ProductBrand/' + data.productBrandId, {
            select: ['fornitorePartnerId', 'fornitorePartnerName'],
        }).then(response => {
            if (response.fornitorePartnerId) {
                data.fornitorePartnerId = response.fornitorePartnerId;
                data.fornitorePartnerName = response.fornitorePartnerName;
            }

            return data;
        });
    };

    const refreshLinkFields = function (view) {
        LINK_FIELDS.forEach(name => {
            const fieldView = view.getFieldView && view.getFieldView(name);

            if (fieldView && typeof fieldView.reRender === 'function') {
                fieldView.reRender();
            }
        });
    };

    const applySyncData = function (view, prospectId, response) {
        const data = {
            prospectId: prospectId,
            prospectName: response.name || null,
            azienda: response.azienda || null,
            fornitorePartnerId: response.fornitorePartnerId || null,
            fornitorePartnerName: response.fornitorePartnerName || null,
            productBrandId: response.productBrandId || null,
            productBrandName: response.productBrandName || null,
            productCategoryId: response.productCategoryId || null,
            productCategoryName: response.productCategoryName || null,
        };

        return resolveBrandFromAzienda(data.azienda, data)
            .then(resolveBrandFromCategory)
            .then(resolvePartnerFromBrand)
            .then(resolved => {
                view.model.set({
                    prospectId: resolved.prospectId,
                    prospectName: resolved.prospectName,
                    azienda: resolved.azienda,
                    fornitorePartnerId: resolved.fornitorePartnerId,
                    fornitorePartnerName: resolved.fornitorePartnerName,
                    productBrandId: resolved.productBrandId,
                    productBrandName: resolved.productBrandName,
                    productCategoryId: resolved.productCategoryId,
                    productCategoryName: resolved.productCategoryName,
                }, {ui: true, prospectSync: true});

                refreshLinkFields(view);
            });
    };

    const syncFromProspect = function (view, prospectId) {
        if (!prospectId) {
            return;
        }

        Espo.Ajax.getRequest('Prospect/' + prospectId, {
            select: PROSPECT_SELECT,
        }).then(response => {
            return applySyncData(view, prospectId, response);
        }).catch(error => {
            console.error('[appuntamento-parent ' + VERSION + ']', error);
        });
    };

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:parentId change:parentType', () => {
                this.runProspectSync();
            });
        },

        runProspectSync: function () {
            if (this.model.get('parentType') !== 'Prospect' || !this.model.get('parentId')) {
                return;
            }

            const recordView = this.getRecordView();

            if (!recordView) {
                return;
            }

            syncFromProspect(recordView, this.model.get('parentId'));
        },
    });
});
