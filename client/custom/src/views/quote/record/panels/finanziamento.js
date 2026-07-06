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
            '      <span class="label-text">{{label}}</span>',
            '    </label>',
            '    <div class="field" data-name="{{field}}"></div>',
            '  </div>',
            '  {{/each}}',
            '</div>',
            '{{/each}}',
        ].join('\n'),

        setupFields: function () {
            this.fieldList = [];

            ROWS.forEach(function (row) {
                row.cols.forEach(function (col) {
                    this.fieldList.push(col.field);
                }, this);
            }, this);
        },

        data: function () {
            var scope = this.model.entityType;

            return {
                rows: ROWS.map(function (row) {
                    return {
                        cols: row.cols.map(function (col) {
                            return {
                                field: col.field,
                                width: col.width,
                                label: this.translate(col.field, 'fields', scope),
                            };
                        }, this),
                    };
                }, this),
            };
        },
    });
});
