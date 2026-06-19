define('custom:views/dashlets/calls', ['views/dashlets/abstract/record-list'], function (Dep) {

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

    return Dep.extend({

        name: 'Calls',
        scope: 'Call',
        listView: 'crm:views/call/record/list-expanded',
        rowActionsView: 'crm:views/call/record/row-actions/dashlet',

        setup: function () {
            this.applyDashletDefaults();

            Dep.prototype.setup.call(this);
        },

        applyDashletDefaults: function () {
            if (!this.options.data) {
                this.options.data = {};
            }

            const data = this.options.data;

            data.searchData = Espo.Utils.cloneDeep(SEARCH_DATA);
            data.expandedLayout = Espo.Utils.cloneDeep(EXPANDED_LAYOUT);

            if (!data.displayRecords) {
                data.displayRecords = 10;
            }

            if (!data.orderBy) {
                data.orderBy = 'dateStart';
            }

            if (!data.order) {
                data.order = 'asc';
            }
        },

        getSearchData: function () {
            return Espo.Utils.cloneDeep(SEARCH_DATA);
        },
    });
});
