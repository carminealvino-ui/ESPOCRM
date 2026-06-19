define('custom:views/dashlets/calls', ['crm:views/dashlets/calls'], function (Dep) {

    const DEFAULT_EXPANDED_LAYOUT = {
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

    const DEFAULT_SEARCH_DATA = {
        bool: {
            onlyMy: true,
        },
        primary: 'planned',
    };

    return Dep.extend({

        setup: function () {
            this.ensureDashletOptions();

            Dep.prototype.setup.call(this);
        },

        ensureDashletOptions: function () {
            if (!this.options.data) {
                this.options.data = {};
            }

            const data = this.options.data;

            data.expandedLayout = this.ensureDataInLayout(
                Espo.Utils.cloneDeep(data.expandedLayout || DEFAULT_EXPANDED_LAYOUT)
            );

            data.searchData = DEFAULT_SEARCH_DATA;
        },

        ensureDataInLayout: function (layout) {
            if (this.layoutHasField(layout, 'data')) {
                return layout;
            }

            if (!layout.rows) {
                layout.rows = [];
            }

            if (layout.rows.length > 1 && Array.isArray(layout.rows[1])) {
                layout.rows[1].unshift({
                    name: 'data',
                    soft: true,
                });

                return layout;
            }

            layout.rows.push([
                {
                    name: 'data',
                    soft: true,
                },
                {
                    name: 'dateStart',
                    soft: true,
                },
            ]);

            return layout;
        },

        layoutHasField: function (layout, fieldName) {
            if (!layout || !layout.rows) {
                return false;
            }

            return layout.rows.some(function (row) {
                return row.some(function (cell) {
                    return cell && cell.name === fieldName;
                });
            });
        },

        getSearchData: function () {
            return Espo.Utils.cloneDeep(DEFAULT_SEARCH_DATA);
        },
    });
});
