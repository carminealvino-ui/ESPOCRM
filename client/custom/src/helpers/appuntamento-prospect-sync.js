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

    const DEFAULT_DURATION_SECONDS = 5400;

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

        const dateEnd = view.getDateTime()
            .toMoment(dateStart)
            .add(DEFAULT_DURATION_SECONDS, 'seconds')
            .format(view.getDateTime().internalDateTimeFormat);

        view.model.set({
            dateEnd: dateEnd,
            duration: DEFAULT_DURATION_SECONDS,
        });
    };

    return {
        DEFAULT_DURATION_SECONDS: DEFAULT_DURATION_SECONDS,

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
