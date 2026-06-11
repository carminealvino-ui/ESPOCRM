/* global define */

define('custom:views/opportunity/helpers/appuntamento-sync', [], function () {

    const VERSION = '1.0.1';

    const APPUNTAMENTO_SELECT = [
        'name',
        'azienda',
        'callCenter',
        'tipo',
        'dateStart',
        'telefono',
        'prospectId',
        'prospectName',
        'leadId',
        'leadName',
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
        'cAPId',
        'cAPName',
    ].join(',');

    const LINK_FIELDS = [
        'fornitorePartner',
        'productBrand',
        'productCategory',
        'appuntamento',
        'prospect',
        'lead',
        'priceBook',
    ];

    const IT_MONTHS = [
        '', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
        'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'
    ];

    const LEAD_SOURCE_CANDIDATES = {
        TELCALL: ['Call Center', 'TELCALL', 'Call'],
        'Appuntamento Call Center': ['Call Center', 'TELCALL', 'Call'],
        'Appuntamento da Gestione Lead': ['Lead', 'Existing Customer'],
        'Appuntamento da Gestione CB': ['Partner', 'Existing Customer'],
        'Referenza Personale': ['Partner', 'Existing Customer'],
    };

    const resolveLeadSource = function (view, appuntamento) {
        if (!view.model.isNew() || view.model.get('leadSource')) {
            return null;
        }

        const options = view.getMetadata().get(
            ['entityDefs', 'Opportunity', 'fields', 'leadSource', 'options']
        ) || [];

        const candidates = [];

        if (appuntamento.callCenter && LEAD_SOURCE_CANDIDATES[appuntamento.callCenter]) {
            candidates.push.apply(candidates, LEAD_SOURCE_CANDIDATES[appuntamento.callCenter]);
        }

        const tipo = appuntamento.tipo;

        if (tipo) {
            const types = Array.isArray(tipo) ? tipo : [tipo];

            types.forEach(t => {
                if (LEAD_SOURCE_CANDIDATES[t]) {
                    candidates.push.apply(candidates, LEAD_SOURCE_CANDIDATES[t]);
                }
            });
        }

        if (appuntamento.callCenter) {
            candidates.push(appuntamento.callCenter);
        }

        for (let i = 0; i < candidates.length; i++) {
            if (options.indexOf(candidates[i]) !== -1) {
                return candidates[i];
            }
        }

        return null;
    };

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

    const buildDataFromAppuntamento = function (view, appuntamento) {
        const data = {
            appuntamentoId: appuntamento.id,
            appuntamentoName: appuntamento.name || null,
            azienda: appuntamento.azienda || null,
            fornitorePartnerId: appuntamento.fornitorePartnerId || null,
            fornitorePartnerName: appuntamento.fornitorePartnerName || null,
            productBrandId: appuntamento.productBrandId || null,
            productBrandName: appuntamento.productBrandName || null,
            productCategoryId: appuntamento.productCategoryId || null,
            productCategoryName: appuntamento.productCategoryName || null,
            prospectId: appuntamento.prospectId || null,
            prospectName: appuntamento.prospectName || null,
            telefono: appuntamento.telefono || null,
            cAPId: appuntamento.cAPId || null,
            cAPName: appuntamento.cAPName || null,
        };

        if (appuntamento.leadId) {
            data.leadId = appuntamento.leadId;
            data.leadName = appuntamento.leadName || null;
        }

        if (appuntamento.dateStart) {
            data.dataOpportunit = String(appuntamento.dateStart).substring(0, 10);
        }

        const leadSource = resolveLeadSource(view, appuntamento);

        if (leadSource) {
            data.leadSource = leadSource;
        }

        return data;
    };

    const UI_REFRESH_FIELDS = LINK_FIELDS.concat(['leadSource']);

    const refreshUiFields = function (view) {
        UI_REFRESH_FIELDS.forEach(name => {
            const fieldView = view.getFieldView && view.getFieldView(name);

            if (fieldView && typeof fieldView.reRender === 'function') {
                fieldView.reRender();
            }
        });
    };

    const applySyncData = function (view, data) {
        view.model.set(data, {ui: true, prospectSync: true});
        refreshUiFields(view);
    };

    const syncFromAppuntamento = function (view) {
        const appuntamentoId = view.model.get('appuntamentoId');

        if (!appuntamentoId) {
            return Promise.resolve();
        }

        return Espo.Ajax.getRequest('Appuntamento/' + appuntamentoId, {
            select: APPUNTAMENTO_SELECT,
        }).then(response => {
            let data = buildDataFromAppuntamento(view, response);

            return resolveBrandFromAzienda(data.azienda, data);
        }).then(data => {
            applySyncData(view, data);
            return resolvePriceBook(view);
        }).catch(error => {
            console.error('[opportunity-appuntamento-sync ' + VERSION + ']', error);
        });
    };

    const resolveReferenceDate = function (view) {
        const date = view.model.get('dataOpportunit') || view.model.get('closeDate');

        if (date) {
            return String(date).substring(0, 10);
        }

        return Espo.Utils.getDateToday();
    };

    const resolveBrandKey = function (view) {
        const name = view.model.get('productBrandName') || view.model.get('azienda') || '';

        return String(name).trim().toUpperCase();
    };

    const nameMatchesReferenceMonth = function (name, refDate) {
        if (!name || !refDate) {
            return false;
        }

        const m = Espo.Utils.getDateMoment(refDate);

        if (!m || !m.isValid()) {
            return false;
        }

        const label = IT_MONTHS[m.month() + 1] + ' ' + m.year();

        return String(name).toLowerCase().indexOf(label.toLowerCase()) !== -1;
    };

    const isEffectiveOnDate = function (priceBook, refDate) {
        const start = priceBook.dateStart ? String(priceBook.dateStart).substring(0, 10) : null;
        const end = priceBook.dateEnd ? String(priceBook.dateEnd).substring(0, 10) : null;

        if (start && start > refDate) {
            return false;
        }

        if (end && end < refDate) {
            return false;
        }

        if (start || end) {
            return true;
        }

        return nameMatchesReferenceMonth(priceBook.name, refDate);
    };

    const scoreCandidate = function (priceBook, refDate, brandKey) {
        let score = 0;
        const name = String(priceBook.name || '').toUpperCase();

        if (name.indexOf(brandKey) === 0) {
            score += 10;
        }

        if (nameMatchesReferenceMonth(priceBook.name, refDate)) {
            score += 100;
        }

        if (priceBook.dateStart) {
            score += parseInt(String(priceBook.dateStart).replace(/-/g, '').substring(0, 8), 10) || 0;
        }

        return score;
    };

    const resolvePriceBook = function (view) {
        if (!view.model.has('priceBookId')) {
            return Promise.resolve();
        }

        if (view.model.get('priceBookId') && view.model.get('_priceBookManual')) {
            return Promise.resolve();
        }

        const brandKey = resolveBrandKey(view);
        const refDate = resolveReferenceDate(view);

        if (!brandKey || !refDate) {
            return Promise.resolve();
        }

        let where = [{
            type: 'startsWith',
            attribute: 'name',
            value: brandKey,
        }];

        if (view.model.get('productBrandId')) {
            where = [{
                type: 'equals',
                attribute: 'productBrandId',
                value: view.model.get('productBrandId'),
            }];
        }

        return Espo.Ajax.getRequest('PriceBook', {
            where: where,
            select: ['id', 'name', 'dateStart', 'dateEnd', 'productBrandId'],
            maxSize: 200,
        }).then(response => {
            if (!response.list || !response.list.length) {
                return;
            }

            let best = null;
            let bestScore = -1;

            response.list.forEach(item => {
                if (!isEffectiveOnDate(item, refDate)) {
                    return;
                }

                const score = scoreCandidate(item, refDate, brandKey);

                if (score > bestScore) {
                    bestScore = score;
                    best = item;
                }
            });

            if (!best) {
                return;
            }

            view.model.set({
                priceBookId: best.id,
                priceBookName: best.name,
            }, {ui: true, prospectSync: true});

            const fieldView = view.getFieldView && view.getFieldView('priceBook');

            if (fieldView && typeof fieldView.reRender === 'function') {
                fieldView.reRender();
            }
        });
    };

    const setupPriceBookListeners = function (view) {
        const debounced = Espo.Utils.debounce(() => {
            resolvePriceBook(view);
        }, 300);

        view.listenTo(view.model, 'change:dataOpportunit', debounced);
        view.listenTo(view.model, 'change:productBrandId', debounced);
        view.listenTo(view.model, 'change:productBrandName', debounced);
        view.listenTo(view.model, 'change:azienda', debounced);
    };

    const setup = function (view) {
        setupPriceBookListeners(view);

        if (!view.model.isNew()) {
            return;
        }

        const run = () => {
            syncFromAppuntamento(view);
        };

        if (view.model.get('appuntamentoId')) {
            window.setTimeout(run, 0);
        }

        view.listenTo(view.model, 'change:appuntamentoId', () => {
            if (view.model.get('appuntamentoId')) {
                window.setTimeout(run, 0);
            }
        });
    };

    return {
        VERSION: VERSION,
        setup: setup,
        syncFromAppuntamento: syncFromAppuntamento,
        resolvePriceBook: resolvePriceBook,
        setupPriceBookListeners: setupPriceBookListeners,
        buildAttributesFromAppuntamento: function (appuntamento) {
            const data = {
                appuntamentoId: appuntamento.id,
                appuntamentoName: appuntamento.name || null,
                dataOpportunit: appuntamento.dateStart
                    ? String(appuntamento.dateStart).substring(0, 10)
                    : null,
                azienda: appuntamento.azienda || null,
                fornitorePartnerId: appuntamento.fornitorePartnerId || null,
                fornitorePartnerName: appuntamento.fornitorePartnerName || null,
                productBrandId: appuntamento.productBrandId || null,
                productBrandName: appuntamento.productBrandName || null,
                productCategoryId: appuntamento.productCategoryId || null,
                productCategoryName: appuntamento.productCategoryName || null,
                prospectId: appuntamento.prospectId || null,
                prospectName: appuntamento.prospectName || null,
                telefono: appuntamento.telefono || null,
            };

            return data;
        },
    };
});
