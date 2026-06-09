/* global define */

define('custom:helpers/appuntamento-prospect-sync', [], function () {

    const PROSPECT_SELECT = [
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
    ].join(',');

    const FALLBACK_DURATION_SECONDS = 5400;

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

    const syncBrandPartnerFromProspect = function (view) {
        if (view.model.get('parentType') !== 'Prospect') {
            return;
        }

        const prospectId = view.model.get('parentId');

        if (!prospectId) {
            return;
        }

        Espo.Ajax.getRequest('Prospect/' + prospectId, {
            select: PROSPECT_SELECT,
        }).then(response => {
            view.model.set({
                fornitorePartnerId: response.fornitorePartnerId || null,
                fornitorePartnerName: response.fornitorePartnerName || null,
                productBrandId: response.productBrandId || null,
                productBrandName: response.productBrandName || null,
                productCategoryId: response.productCategoryId || null,
                productCategoryName: response.productCategoryName || null,
            }, {ui: true, prospectSync: true});
        });
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
        const dateEnd = view.getDateTime()
            .toMoment(dateStart)
            .add(defaultSeconds, 'seconds')
            .format(view.getDateTime().internalDateTimeFormat);

        view.model.set({
            dateEnd: dateEnd,
            duration: defaultSeconds,
        }, {ui: true, defaultDuration: true});
    };

    const scheduleDefaultDuration = function (view) {
        applyDefaultDuration(view);
        setTimeout(() => applyDefaultDuration(view), 0);
        setTimeout(() => applyDefaultDuration(view), 150);
    };

    return {
        FALLBACK_DURATION_SECONDS: FALLBACK_DURATION_SECONDS,
        getDefaultDurationSeconds: getDefaultDurationSeconds,
        applyDefaultDuration: applyDefaultDuration,

        setupProspectSync: function (view) {
            view.listenTo(view.model, 'change:parentId change:parentType', () => {
                syncBrandPartnerFromProspect(view);
            });

            view.once('after:render', () => {
                syncBrandPartnerFromProspect(view);
            });
        },

        setupDefaultDuration: function (view) {
            if (!view.model.isNew() || view.model.get('isAllDay')) {
                return;
            }

            view.listenTo(view.model, 'change:dateStart', () => {
                applyDefaultDuration(view);
            });

            view.listenTo(view.model, 'change:dateEnd', (model, value, options) => {
                if (options && options.defaultDuration) {
                    return;
                }

                if (!model.isNew()) {
                    return;
                }

                applyDefaultDuration(view);
            });

            view.once('after:render', () => {
                scheduleDefaultDuration(view);
            });
        },
    };
});
