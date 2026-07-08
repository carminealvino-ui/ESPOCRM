/* global define */

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

    const BRAND_SELECT = 'id,name,fornitorePartnerId,fornitorePartnerName';
    const CATEGORY_SELECT = 'id,name,productBrandId,productBrandName';
    const FALLBACK_DURATION_SECONDS = 5400;

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

    const getDefaultDurationSeconds = function (view) {
        const fromField = view.model.getFieldParam('duration', 'default');

        if (fromField !== null && fromField !== undefined && fromField !== '') {
            return parseInt(fromField, 10);
        }

        const entityType = view.model.entityType || view.model.name;
        const fromMeta = view.getMetadata().get(
            ['entityDefs', entityType, 'fields', 'duration', 'default']
        );

        if (fromMeta !== null && fromMeta !== undefined && fromMeta !== '') {
            return parseInt(fromMeta, 10);
        }

        return FALLBACK_DURATION_SECONDS;
    };

    const computeDateEnd = function (view, dateStart, seconds) {
        return view.getDateTime()
            .toMoment(dateStart)
            .add(seconds, 'seconds')
            .format(view.getDateTime().internalDateTimeFormat);
    };

    const getDateTimeUnix = function (view, value) {
        if (!value) {
            return null;
        }

        return view.getDateTime().toMoment(value).unix();
    };

    const isSameDateTime = function (view, left, right) {
        const leftUnix = getDateTimeUnix(view, left);
        const rightUnix = getDateTimeUnix(view, right);

        if (leftUnix === null || rightUnix === null) {
            return left === right;
        }

        return leftUnix === rightUnix;
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

    const applyBrandToPatch = function (patch, brand) {
        if (!brand) {
            return patch;
        }

        if (!patch.productBrandId && brand.id) {
            patch.productBrandId = brand.id;
            patch.productBrandName = brand.name || null;
        }

        if (!patch.fornitorePartnerId && brand.fornitorePartnerId) {
            patch.fornitorePartnerId = brand.fornitorePartnerId;
            patch.fornitorePartnerName = brand.fornitorePartnerName || null;
        }

        return patch;
    };

    const fetchBrandById = function (brandId) {
        if (!brandId) {
            return Promise.resolve(null);
        }

        return Espo.Ajax.getRequest('ProductBrand/' + brandId, {
            select: BRAND_SELECT,
        }).catch(() => null);
    };

    const fetchBrandByName = function (name) {
        const value = (name || '').toString().trim();

        if (!value) {
            return Promise.resolve(null);
        }

        return Espo.Ajax.getRequest('ProductBrand', {
            where: [{
                type: 'equals',
                attribute: 'name',
                value: value,
            }],
            maxSize: 1,
            select: BRAND_SELECT,
        }).then(response => (response.list && response.list[0]) || null)
            .catch(() => null);
    };

    const resolveBrandPartnerCascade = function (patch, prospectResponse) {
        let promise = Promise.resolve(patch);

        if (patch.productCategoryId && (!patch.productBrandId || !patch.fornitorePartnerId)) {
            promise = promise.then(currentPatch => Espo.Ajax.getRequest(
                'ProductCategory/' + currentPatch.productCategoryId,
                {select: CATEGORY_SELECT}
            ).then(category => {
                if (!category || !category.productBrandId) {
                    return currentPatch;
                }

                if (!currentPatch.productBrandId) {
                    currentPatch.productBrandId = category.productBrandId;
                    currentPatch.productBrandName = category.productBrandName || null;
                }

                if (currentPatch.fornitorePartnerId) {
                    return currentPatch;
                }

                return fetchBrandById(category.productBrandId).then(brand => {
                    return applyBrandToPatch(currentPatch, brand);
                });
            }).catch(() => currentPatch));
        }

        if (!patch.productBrandId && prospectResponse && prospectResponse.azienda) {
            promise = promise.then(currentPatch => {
                if (currentPatch.productBrandId) {
                    return currentPatch;
                }

                return fetchBrandByName(prospectResponse.azienda).then(brand => {
                    return applyBrandToPatch(currentPatch, brand);
                });
            });
        }

        if (patch.productBrandId && !patch.fornitorePartnerId) {
            promise = promise.then(currentPatch => {
                if (currentPatch.fornitorePartnerId) {
                    return currentPatch;
                }

                return fetchBrandById(currentPatch.productBrandId).then(brand => {
                    return applyBrandToPatch(currentPatch, brand);
                });
            });
        }

        return promise;
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
            const patch = buildProspectPatch(view, prospectId, response);

            return resolveBrandPartnerCascade(patch, response).then(resolvedPatch => {
                view.model.set(resolvedPatch, {ui: true, prospectSync: true});
                refreshLinkFields(view);
                applyDefaultDuration(view);
            });
        }).catch(error => {
            console.error('[appuntamento-prospect-sync]', error);
        });
    };

    const refreshDurationField = function (view) {
        const fieldView = view.getFieldView && view.getFieldView('duration');

        if (!fieldView || typeof fieldView.enforceDefaultDuration !== 'function') {
            return;
        }

        fieldView.enforceDefaultDuration();

        if (typeof fieldView.updateDuration === 'function') {
            fieldView.updateDuration();
        }
    };

    const applyDefaultDuration = function (view) {
        if (!view.model.isNew() || view.model.get('isAllDay')) {
            return;
        }

        const dateStart = view.model.get('dateStart');

        if (!dateStart) {
            return;
        }

        const defaultSeconds = getDefaultDurationSeconds(view);
        const expectedEnd = computeDateEnd(view, dateStart, defaultSeconds);
        const currentEnd = view.model.get('dateEnd');

        if (isSameDateTime(view, currentEnd, expectedEnd)) {
            refreshDurationField(view);

            return;
        }

        view._applyingDefaultDuration = true;

        try {
            view.model.set({
                dateEnd: expectedEnd,
                duration: defaultSeconds,
            }, {ui: true, updatedByDuration: true});
        } finally {
            view._applyingDefaultDuration = false;
        }

        refreshDurationField(view);
    };

    const scheduleProspectSync = function (view) {
        if (view._prospectSyncTimer) {
            clearTimeout(view._prospectSyncTimer);
        }

        view._prospectSyncTimer = setTimeout(() => {
            syncFromProspect(view);
        }, 0);
    };

    const scheduleDefaultDuration = function (view) {
        const run = () => applyDefaultDuration(view);

        run();

        if (view._defaultDurationTimer) {
            clearTimeout(view._defaultDurationTimer);
        }

        view._defaultDurationTimer = setTimeout(run, 250);
    };

    const scheduleDurationGuards = function (view) {
        [500, 1000, 1500].forEach(delay => {
            setTimeout(() => applyDefaultDuration(view), delay);
        });
    };

    return {
        FALLBACK_DURATION_SECONDS: FALLBACK_DURATION_SECONDS,
        getDefaultDurationSeconds: getDefaultDurationSeconds,
        computeDateEnd: computeDateEnd,
        refreshDurationField: refreshDurationField,
        applyDefaultDuration: applyDefaultDuration,
        syncFromProspect: syncFromProspect,

        setupProspectSync: function (view) {
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

        setupDefaultDuration: function (view) {
            if (!view.model.isNew() || view.model.get('isAllDay')) {
                return;
            }

            view.listenTo(view.model, 'change:dateStart', () => {
                scheduleDefaultDuration(view);
            });

            view.listenTo(view.model, 'change:dateEnd', () => {
                if (view._applyingDefaultDuration) {
                    return;
                }

                scheduleDefaultDuration(view);
            });

            view.once('after:render', () => {
                scheduleDefaultDuration(view);
                scheduleDurationGuards(view);
            });
        },
    };
});
