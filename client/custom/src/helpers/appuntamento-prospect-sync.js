/* global define */

/**
 * Sync Fornitore / Brand / Categoria (e CAP/location) dal Prospect sull'Appuntamento.
 * Durata: NON ricalcola qui — resta sui view edit/edit-small e sul campo
 * appuntamento-duration (UTC già correct).
 *
 * VERSION: prospect-prefill-durata-v1
 */
define('custom:helpers/appuntamento-prospect-sync', [], function () {

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

    const resolveProspectId = function (view) {
        const prospectId = view.model.get('prospectId');

        if (prospectId) {
            return prospectId;
        }

        if (view.model.get('parentType') === 'Prospect' && view.model.get('parentId')) {
            return view.model.get('parentId');
        }

        return null;
    };

    const buildLocationFromProspect = function (response) {
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

    const buildProspectPatch = function (view, prospectId, response) {
        const patch = {
            prospectId: response.id || prospectId,
            prospectName: response.name || view.model.get('prospectName'),
            fornitorePartnerId: response.fornitorePartnerId || null,
            fornitorePartnerName: response.fornitorePartnerName || null,
            productBrandId: response.productBrandId || null,
            productBrandName: response.productBrandName || null,
            productCategoryId: response.productCategoryId || null,
            productCategoryName: response.productCategoryName || null,
            cAPId: response.cAPId || null,
            cAPName: response.cAPName || null,
        };

        if (view.model.get('parentType') === 'Prospect' && !view.model.get('parentId')) {
            patch.parentId = prospectId;
            patch.parentName = response.name || view.model.get('parentName');
        }

        const location = buildLocationFromProspect(response);

        if (location && !view.model.get('location')) {
            patch.location = location;
        }

        return patch;
    };

    const refreshLinkFields = function (view) {
        if (!view || typeof view.getFieldView !== 'function') {
            return;
        }

        ['fornitorePartner', 'productBrand', 'productCategory', 'prospect', 'cAP', 'telefono'].forEach(name => {
            const fieldView = view.getFieldView(name);

            if (fieldView && typeof fieldView.reRender === 'function') {
                fieldView.reRender();
            }
        });
    };

    const syncFromProspect = function (view) {
        const prospectId = resolveProspectId(view);

        if (!prospectId) {
            return Promise.resolve();
        }

        return Espo.Ajax.getRequest('Prospect/' + prospectId, {
            select: PROSPECT_SELECT,
        }).then(response => {
            const data = buildProspectPatch(view, prospectId, response);
            view.model.set(data, {ui: true, prospectSync: true});
            refreshLinkFields(view);
        }).catch(error => {
            console.error('[appuntamento-prospect-sync]', error);
        });
    };

    const scheduleProspectSync = function (view) {
        if (view._prospectSyncTimer) {
            clearTimeout(view._prospectSyncTimer);
        }

        view._prospectSyncTimer = setTimeout(() => {
            syncFromProspect(view);
        }, 0);
    };

    return {
        syncFromProspect: syncFromProspect,

        setupProspectSync: function (view) {
            // prospect-prefill-durata-v1
            view.listenTo(view.model, 'change:parentId change:parentType change:prospectId', () => {
                scheduleProspectSync(view);
            });

            view.once('after:render', () => {
                scheduleProspectSync(view);

                if (resolveProspectId(view)) {
                    setTimeout(() => scheduleProspectSync(view), 300);
                }
            });

            if (resolveProspectId(view)) {
                scheduleProspectSync(view);
            }
        },
    };
});
