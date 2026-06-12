/* global define */

define('custom:views/opportunity/helpers/appuntamento-sync', [], function () {

    const VERSION = '1.0.7';

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
        'parentId',
        'parentType',
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

    const debounce = function (fn, wait) {
        let timer = null;

        return function () {
            const context = this;
            const args = arguments;

            if (timer) {
                window.clearTimeout(timer);
            }

            timer = window.setTimeout(() => {
                timer = null;
                fn.apply(context, args);
            }, wait);
        };
    };

    const LEAD_SOURCE_CANDIDATES = {
        TELCALL: ['Call', 'Call Center', 'TELCALL'],
        'Appuntamento Call Center': ['Call', 'Call Center', 'TELCALL'],
        'Appuntamento da Gestione Lead': ['Generazione Lead', 'Lead', 'Existing Customer', 'Other'],
        'Appuntamento da Gestione CB': ['Partner', 'Existing Customer', 'Vodafone Assegnazione CB'],
        'Referenza Personale': ['Partner', 'Existing Customer', 'Other'],
    };

    const getDateToday = function () {
        const d = new Date();

        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    };

    const parseDateParts = function (refDate) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(refDate).substring(0, 10));

        if (!match) {
            return null;
        }

        return {
            year: parseInt(match[1], 10),
            month: parseInt(match[2], 10),
        };
    };

    const LEAD_SOURCE_ALIASES = {
        'Call Center': ['Call', 'Call Center'],
        TELCALL: ['Call', 'Call Center', 'TELCALL'],
        Call: ['Call', 'Call Center'],
        Lead: ['Existing Customer', 'Lead', 'Other'],
        Partner: ['Partner', 'Existing Customer'],
        'Generazione Lead': ['Generazione Lead', 'Lead', 'Existing Customer'],
        Extractor: ['Extractor', 'Other'],
        'Assegnazione CB': ['Assegnazione CB', 'Partner', 'Vodafone Assegnazione CB'],
        'Referenza Personale': ['Referenza Personale', 'Partner', 'Existing Customer'],
    };

    const expandLeadSourceCandidates = function (values) {
        const expanded = [];

        (values || []).forEach(value => {
            const key = String(value || '').trim();

            if (!key) {
                return;
            }

            expanded.push(key);

            if (LEAD_SOURCE_ALIASES[key]) {
                expanded.push.apply(expanded, LEAD_SOURCE_ALIASES[key]);
            }
        });

        return expanded;
    };

    const matchLeadSourceOption = function (options, candidates) {
        const normalized = expandLeadSourceCandidates(candidates);

        for (let i = 0; i < normalized.length; i++) {
            const candidate = String(normalized[i] || '').trim();

            if (!candidate) {
                continue;
            }

            for (let j = 0; j < options.length; j++) {
                const option = String(options[j] || '').trim();

                if (!option) {
                    continue;
                }

                if (option.toLowerCase() === candidate.toLowerCase()) {
                    return options[j];
                }
            }
        }

        for (let i = 0; i < normalized.length; i++) {
            const candidate = String(normalized[i] || '').trim().toLowerCase();

            if (!candidate) {
                continue;
            }

            for (let j = 0; j < options.length; j++) {
                const option = String(options[j] || '').trim();

                if (!option) {
                    continue;
                }

                const optionLower = option.toLowerCase();

                if (optionLower.indexOf(candidate) !== -1 || candidate.indexOf(optionLower) !== -1) {
                    return options[j];
                }
            }
        }

        return null;
    };

    const collectLeadSourceCandidates = function (appuntamento, extraSources) {
        const candidates = [];

        if (extraSources && extraSources.length) {
            candidates.push.apply(candidates, extraSources);
        }

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

                if (String(t).toLowerCase().indexOf('call center') !== -1) {
                    candidates.push('Call', 'Call Center');
                }
            });
        }

        if (appuntamento.callCenter) {
            candidates.push(appuntamento.callCenter);
        }

        return candidates;
    };

    const resolveLeadSource = function (view, appuntamento, extraSources) {
        const current = view.model.get('leadSource');

        if (!view.model.isNew() || (current !== null && current !== undefined && current !== '')) {
            return null;
        }

        const options = view.getMetadata().get(
            ['entityDefs', 'Opportunity', 'fields', 'leadSource', 'options']
        ) || [];

        const candidates = collectLeadSourceCandidates(appuntamento, extraSources);

        return matchLeadSourceOption(options, candidates);
    };

    const resolveLeadIdFromAppuntamento = function (appuntamento) {
        if (appuntamento.leadId) {
            return appuntamento.leadId;
        }

        if (appuntamento.parentType === 'Lead' && appuntamento.parentId) {
            return appuntamento.parentId;
        }

        return null;
    };

    const fetchLeadSourceHints = function (appuntamento) {
        const requests = [];
        const values = [];

        if (appuntamento.prospectId) {
            requests.push(
                Espo.Ajax.getRequest('Prospect/' + appuntamento.prospectId, {select: 'origine'})
                    .then(r => {
                        if (r.origine) {
                            values.push(r.origine);
                        }
                    })
                    .catch(() => null)
            );
        }

        const leadId = resolveLeadIdFromAppuntamento(appuntamento);

        if (leadId) {
            requests.push(
                Espo.Ajax.getRequest('Lead/' + leadId, {select: 'source'})
                    .then(r => {
                        if (r.source) {
                            values.push(r.source);
                        }
                    })
                    .catch(() => null)
            );
        }

        if (!requests.length) {
            return Promise.resolve([]);
        }

        return Promise.all(requests).then(() => values);
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

        return data;
    };

    const applyLeadSource = function (view, appuntamento, data, extraSources) {
        const options = view.getMetadata().get(
            ['entityDefs', 'Opportunity', 'fields', 'leadSource', 'options']
        ) || [];

        let leadSource = resolveLeadSource(view, appuntamento, extraSources);

        if (!leadSource && (!extraSources || !extraSources.length)) {
            leadSource = matchLeadSourceOption(options, ['Call', 'Call Center', 'Partner', 'Existing Customer']);
        }

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

            return resolveBrandFromAzienda(data.azienda, data).then(brandData => ({
                response: response,
                data: brandData,
            }));
        }).then(payload => {
            return fetchLeadSourceHints(payload.response).then(extraSources => {
                applyLeadSource(view, payload.response, payload.data, extraSources);

                return payload.data;
            });
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

        return getDateToday();
    };

    const resolveBrandKey = function (view) {
        const name = view.model.get('productBrandName') || view.model.get('azienda') || '';

        return String(name).trim().toUpperCase();
    };

    const nameMatchesReferenceMonth = function (name, refDate) {
        if (!name || !refDate) {
            return false;
        }

        const parts = parseDateParts(refDate);

        if (!parts || !parts.month || !parts.year) {
            return false;
        }

        const label = IT_MONTHS[parts.month] + ' ' + parts.year;

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

        if (nameMatchesReferenceMonth(priceBook.name, refDate)) {
            return true;
        }

        return true;
    };

    const scoreCandidate = function (priceBook, refDate, brandKey) {
        let score = 0;
        const name = String(priceBook.name || '').toUpperCase();

        if (name.indexOf(brandKey) !== -1) {
            score += 10;
        }

        if (name.indexOf(brandKey) === 0) {
            score += 5;
        }

        if (nameMatchesReferenceMonth(priceBook.name, refDate)) {
            score += 100;
        }

        if (priceBook.dateStart) {
            score += parseInt(String(priceBook.dateStart).replace(/-/g, '').substring(0, 8), 10) || 0;
        }

        return score;
    };

    const buildPriceBookWhere = function (brandKey) {
        return [{
            type: 'contains',
            attribute: 'name',
            value: brandKey,
        }];
    };

    const pickBestPriceBook = function (list, refDate, brandKey, requireEffectiveDate) {
        let best = null;
        let bestScore = -1;

        list.forEach(item => {
            if (item.status && item.status !== 'Active') {
                return;
            }

            if (requireEffectiveDate && !isEffectiveOnDate(item, refDate)) {
                return;
            }

            const score = scoreCandidate(item, refDate, brandKey);

            if (score > bestScore) {
                bestScore = score;
                best = item;
            }
        });

        return best;
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

        return Espo.Ajax.getRequest('PriceBook', {
            where: buildPriceBookWhere(brandKey),
            select: ['id', 'name', 'dateStart', 'dateEnd', 'status'],
            maxSize: 200,
            orderBy: 'name',
            order: 'desc',
        }).then(response => {
            if (!response.list || !response.list.length) {
                return;
            }

            let best = pickBestPriceBook(response.list, refDate, brandKey, true);

            if (!best) {
                best = pickBestPriceBook(response.list, refDate, brandKey, false);
            }

            if (!best) {
                return;
            }

            view.model.set({
                priceBookId: best.id,
                priceBookName: best.name,
            }, {ui: true, prospectSync: true});

            refreshUiFields(view);
        }).catch(error => {
            console.error('[opportunity-appuntamento-sync ' + VERSION + '] priceBook', error);
        });
    };

    const setupPriceBookListeners = function (view) {
        const debounced = debounce(() => {
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
