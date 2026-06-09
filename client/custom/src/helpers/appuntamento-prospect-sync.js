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

    const computeDateEnd = function (view, dateStart, seconds) {
        return view.getDateTime()
            .toMoment(dateStart)
            .add(seconds, 'seconds')
            .format(view.getDateTime().internalDateTimeFormat);
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

    const refreshDurationField = function (view) {
        const fieldView = view.getFieldView && view.getFieldView('duration');

        if (!fieldView) {
            return;
        }

        const start = view.model.get('dateStart');
        const end = view.model.get('dateEnd');

        if (!start || !end) {
            return;
        }

        fieldView.seconds = view.getDateTime().toMoment(end).unix() -
            view.getDateTime().toMoment(start).unix();

        if (typeof fieldView.updateDuration === 'function') {
            fieldView.updateDuration();
        } else if (typeof fieldView.reRender === 'function') {
            fieldView.reRender();
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
        const dateEnd = computeDateEnd(view, dateStart, defaultSeconds);

        view.model.set({
            dateEnd: dateEnd,
            duration: defaultSeconds,
        }, {ui: true});

        refreshDurationField(view);
    };

    return {
        FALLBACK_DURATION_SECONDS: FALLBACK_DURATION_SECONDS,
        getDefaultDurationSeconds: getDefaultDurationSeconds,
        computeDateEnd: computeDateEnd,
        refreshDurationField: refreshDurationField,
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

            view.once('after:render', () => {
                applyDefaultDuration(view);
            });
        },
    };
});
