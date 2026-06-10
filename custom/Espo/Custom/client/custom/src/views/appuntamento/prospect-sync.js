/* global define */

define('custom:views/appuntamento/prospect-sync', [], function () {

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

    const FALLBACK_DURATION_SECONDS = 5400;

    const LINK_FIELDS = [
        'fornitorePartner',
        'productBrand',
        'productCategory',
        'prospect',
    ];

    const getProspectId = function (model) {
        if (model.get('parentType') === 'Prospect' && model.get('parentId')) {
            return model.get('parentId');
        }

        return model.get('prospectId') || null;
    };

    const resolveBrandFromAzienda = function (azienda, data) {
        if (data.productBrandId || !azienda) {
            return Promise.resolve(data);
        }

        return Espo.Ajax.getRequest('ProductBrand', {
            where: [{
                type: 'equals',
                attribute: 'name',
                value: azienda,
            }],
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

    const buildSyncData = function (prospectId, response) {
        return {
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
    };

    const refreshLinkFields = function (view) {
        LINK_FIELDS.forEach(name => {
            const fieldView = view.getFieldView && view.getFieldView(name);

            if (fieldView && typeof fieldView.reRender === 'function') {
                fieldView.reRender();
            }
        });
    };

    const applySyncData = function (view, data) {
        view.model.set({
            prospectId: data.prospectId,
            prospectName: data.prospectName,
            azienda: data.azienda,
            fornitorePartnerId: data.fornitorePartnerId,
            fornitorePartnerName: data.fornitorePartnerName,
            productBrandId: data.productBrandId,
            productBrandName: data.productBrandName,
            productCategoryId: data.productCategoryId,
            productCategoryName: data.productCategoryName,
        }, {ui: true, prospectSync: true});

        refreshLinkFields(view);
    };

    const syncFromProspect = function (view) {
        const prospectId = getProspectId(view.model);

        if (!prospectId) {
            return;
        }

        Espo.Ajax.getRequest('Prospect/' + prospectId, {
            select: PROSPECT_SELECT,
        }).then(response => {
            let data = buildSyncData(prospectId, response);

            return resolveBrandFromAzienda(data.azienda, data)
                .then(resolveBrandFromCategory)
                .then(resolvePartnerFromBrand);
        }).then(data => {
            if (!data) {
                return;
            }

            applySyncData(view, data);
        }).catch(error => {
            console.error('[prospect-sync ' + VERSION + ']', error);
        });
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
    };

    return {
        VERSION: VERSION,
        syncFromProspect: syncFromProspect,
        FALLBACK_DURATION_SECONDS: FALLBACK_DURATION_SECONDS,
        computeDateEnd: computeDateEnd,
        applyDefaultDuration: applyDefaultDuration,

        setupProspectSync: function (view) {
            view.listenTo(view.model, 'change:parentId change:parentType change:prospectId', () => {
                syncFromProspect(view);
            });

            view.once('after:render', () => {
                syncFromProspect(view);
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
