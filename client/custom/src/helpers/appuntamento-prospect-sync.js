/* global define */

define('custom:helpers/appuntamento-prospect-sync', [], function () {

    const PROSPECT_SELECT = [
        'name',
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

    const refreshLinkFields = function (view) {
        if (!view || typeof view.getFieldView !== 'function') {
            return;
        }

        LINK_FIELDS.forEach(name => {
            const fieldView = view.getFieldView(name);

            if (fieldView && typeof fieldView.reRender === 'function') {
                fieldView.reRender();
            }
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
            const durationView = view.getFieldView && view.getFieldView('duration');

            if (durationView && typeof durationView.enforceDefaultDuration === 'function') {
                durationView.enforceDefaultDuration();

                if (typeof durationView.updateDuration === 'function') {
                    durationView.updateDuration();
                }
            } else {
                view.model.set('dateEnd', expectedEnd, {
                    updatedByDuration: true,
                    fromField: 'duration',
                });
            }
        } finally {
            view._applyingDefaultDuration = false;
        }
    };

    const syncFromProspect = function (view) {
        const prospectId = resolveProspectId(view);

        if (!prospectId) {
            return Promise.resolve();
        }

        return Espo.Ajax.getRequest('Prospect/' + prospectId, {
            select: PROSPECT_SELECT,
        }).then(response => {
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

            view.model.set(patch, {ui: true, prospectSync: true});
            refreshLinkFields(view);
            applyDefaultDuration(view);
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

    const scheduleDefaultDuration = function (view) {
        applyDefaultDuration(view);

        if (view._defaultDurationTimer) {
            clearTimeout(view._defaultDurationTimer);
        }

        view._defaultDurationTimer = setTimeout(() => {
            applyDefaultDuration(view);
        }, 250);
    };

    const scheduleDurationGuards = function (view) {
        [300, 800, 1500, 2500].forEach(delay => {
            setTimeout(() => {
                applyDefaultDuration(view);
            }, delay);
        });
    };

    return {
        FALLBACK_DURATION_SECONDS: FALLBACK_DURATION_SECONDS,
        syncFromProspect: syncFromProspect,
        applyDefaultDuration: applyDefaultDuration,

        setupProspectSync: function (view) {
            view.listenTo(view.model, 'change:parentId change:parentType change:prospectId', () => {
                scheduleProspectSync(view);
            });

            view.once('after:render', () => {
                scheduleProspectSync(view);
                setTimeout(() => scheduleProspectSync(view), 400);
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
