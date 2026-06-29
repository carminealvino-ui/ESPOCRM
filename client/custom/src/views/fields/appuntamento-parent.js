/* global define */

/**
 * Campo Relazionato a — sync da Prospect (fornitore, brand, CAP, prospect link).
 * VERSIONE: 1.2.6
 */
define('custom:views/fields/appuntamento-parent', ['views/fields/link-parent'], function (Dep) {

    const VERSION = '1.2.6';

    const PROSPECT_SELECT = [
        'name',
        'azienda',
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
        'cAPId',
        'cAPName',
        'phoneNumber',
        'addressStreet',
        'addressCity',
        'addressPostalCode',
        'addressState',
        'addressCountry',
    ].join(',');

    const LINK_FIELDS = [
        'fornitorePartner',
        'productBrand',
        'productCategory',
        'prospect',
        'cAP',
        'telefono',
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
        }).catch(() => data);
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
        }).catch(() => data);
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
        }).catch(() => data);
    };

    const buildLocation = function (response) {
        const parts = [
            response.addressStreet,
            response.addressPostalCode,
            response.addressCity,
            response.addressState,
            response.addressCountry,
        ].filter(part => part !== null && part !== undefined && String(part).trim() !== '');

        if (!parts.length) {
            return null;
        }

        return parts.join(', ');
    };

    const refreshLinkFields = function (recordView) {
        if (!recordView || typeof recordView.getFieldView !== 'function') {
            return;
        }

        LINK_FIELDS.forEach(name => {
            const fieldView = recordView.getFieldView(name);

            if (fieldView && typeof fieldView.reRender === 'function') {
                fieldView.reRender();
            }
        });
    };

    const applySyncData = function (model, prospectId, response, recordView) {
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
            cAPId: response.cAPId || null,
            cAPName: response.cAPName || null,
        };

        const location = buildLocation(response);
        const patchExtra = {};

        if (location && !model.get('location')) {
            patchExtra.location = location;
        }

        return resolveBrandFromAzienda(data.azienda, data)
            .then(resolveBrandFromCategory)
            .then(resolvePartnerFromBrand)
            .then(resolved => {
                model.set(Object.assign({}, resolved, patchExtra), {ui: true, prospectSync: true});
                refreshLinkFields(recordView);
            });
    };

    const syncFromProspect = function (model, prospectId, recordView) {
        if (!prospectId) {
            return;
        }

        Espo.Ajax.getRequest('Prospect/' + prospectId, {
            select: PROSPECT_SELECT,
        }).then(response => {
            return applySyncData(model, prospectId, response, recordView);
        }).catch(error => {
            console.error('[appuntamento-parent ' + VERSION + ']', error);
        });
    };

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this._prospectSyncTimer = null;

            this.listenTo(this.model, 'change:parentId change:parentType', () => {
                this.scheduleProspectSync();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.scheduleProspectSync();
        },

        findRecordView: function () {
            let parent = this.getParentView && this.getParentView();

            while (parent) {
                if (typeof parent.getFieldView === 'function') {
                    return parent;
                }

                parent = parent.getParentView && parent.getParentView();
            }

            return null;
        },

        scheduleProspectSync: function () {
            if (this._prospectSyncTimer) {
                window.clearTimeout(this._prospectSyncTimer);
            }

            this._prospectSyncTimer = window.setTimeout(() => {
                this._prospectSyncTimer = null;
                this.runProspectSync();
            }, 250);
        },

        runProspectSync: function () {
            if (this.model.get('parentType') !== 'Prospect' || !this.model.get('parentId')) {
                return;
            }

            syncFromProspect(
                this.model,
                this.model.get('parentId'),
                this.findRecordView()
            );
        },
    });
});
