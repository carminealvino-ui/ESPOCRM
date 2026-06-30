define('custom:helpers/call-dashlet-defaults', [], function () {

    const EXPANDED_LAYOUT = {
        rows: [
            [
                {
                    name: 'name',
                    link: true,
                },
            ],
            [
                {
                    name: 'data',
                    soft: true,
                },
                {
                    name: 'dateStart',
                    soft: true,
                },
            ],
            [
                {
                    name: 'status',
                    soft: true,
                },
                {
                    name: 'parent',
                    soft: true,
                },
            ],
        ],
    };

    const SEARCH_DATA = {
        primary: 'daRiscontrare',
    };

    const applyToDashletOptions = function (options) {
        if (!options.data) {
            options.data = {};
        }

        const data = options.data;

        data.searchData = Espo.Utils.cloneDeep(SEARCH_DATA);
        data.primaryFilter = 'daRiscontrare';
        data.expandedLayout = Espo.Utils.cloneDeep(EXPANDED_LAYOUT);

        if (!data.displayRecords) {
            data.displayRecords = 10;
        }

        if (!data.orderBy && !data.sortBy) {
            data.orderBy = 'dateStart';
        }

        if (!data.order && !data.sortDirection) {
            data.order = 'asc';
        }

        return data;
    };

    const getSearchData = function () {
        return Espo.Utils.cloneDeep(SEARCH_DATA);
    };

    return {
        applyToDashletOptions: applyToDashletOptions,
        getSearchData: getSearchData,
        EXPANDED_LAYOUT: EXPANDED_LAYOUT,
    };
});
