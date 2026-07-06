define('custom:views/quote/record/panels/finanziamento', ['views/record/panels/bottom'], function (Dep) {

    const ROWS = [
        {
            cols: [
                {field: 'finanziamento', width: 6},
                {field: 'statoFinanziamento', width: 6},
            ],
        },
        {
            cols: [
                {field: 'importoSaldo', width: 6},
            ],
        },
        {
            cols: [
                {field: 'tassoZero', width: 3},
                {field: 'importoFinanziato', width: 3},
                {field: 'rataPrestito', width: 3},
                {field: 'nrRate', width: 3},
            ],
        },
    ];

    return Dep.extend({

        templateContent: [
            '{{#each rows}}',
            '<div class="row">',
            '  {{#each cols}}',
            '  <div class="col-sm-{{width}} cell form-group" data-name="{{field}}">',
            '    <label class="control-label" data-name="{{field}}">',
            '      <span class="label-text">{{translate field scope=../scope category="fields"}}</span>',
            '    </label>',
            '    <div class="field" data-name="{{field}}"></div>',
            '  </div>',
            '  {{/each}}',
            '</div>',
            '{{/each}}',
        ].join('\n'),

        setup: function () {
            this.fieldList = [];
            Dep.prototype.setup.call(this);
        },

        setupFields: function () {
            this.fieldList = [];
        },

        data: function () {
            var scope = this.model.entityType;

            return {
                scope: scope,
                rows: ROWS.map(function (row) {
                    return {
                        cols: row.cols.filter(function (col) {
                            return col.field;
                        }),
                    };
                }),
            };
        },

        afterRender: function () {
            ROWS.forEach(function (row) {
                row.cols.forEach(function (col) {
                    this.renderField(col.field);
                }, this);
            }, this);
        },

        renderField: function (field) {
            if (!field || !(field in ((this.model.defs || {}).fields || {}))) {
                return;
            }

            var key = field + 'Field';

            if (this.hasView(key)) {
                this.clearView(key);
            }

            this.createField(field);
        },
    });
});
