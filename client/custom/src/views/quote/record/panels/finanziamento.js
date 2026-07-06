define('custom:views/quote/record/panels/finanziamento', ['views/record/panels/bottom'], function (Dep) {

    return Dep.extend({

        templateContent: [
            '<div class="row">',
            '  <div class="col-sm-6 cell form-group" data-name="finanziamento">',
            '    <label class="control-label" data-name="finanziamento">',
            '      <span class="label-text">{{translate "finanziamento" scope="Quote" category="fields"}}</span>',
            '    </label>',
            '    <div class="field" data-name="finanziamento"></div>',
            '  </div>',
            '  <div class="col-sm-6 cell form-group" data-name="statoFinanziamento">',
            '    <label class="control-label" data-name="statoFinanziamento">',
            '      <span class="label-text">{{translate "statoFinanziamento" scope="Quote" category="fields"}}</span>',
            '    </label>',
            '    <div class="field" data-name="statoFinanziamento"></div>',
            '  </div>',
            '</div>',
            '<div class="row">',
            '  <div class="col-sm-6 cell form-group" data-name="importoSaldo">',
            '    <label class="control-label" data-name="importoSaldo">',
            '      <span class="label-text">{{translate "importoSaldo" scope="Quote" category="fields"}}</span>',
            '    </label>',
            '    <div class="field" data-name="importoSaldo"></div>',
            '  </div>',
            '</div>',
            '<div class="row">',
            '  <div class="col-sm-3 cell form-group" data-name="importoFinanziato">',
            '    <label class="control-label" data-name="importoFinanziato">',
            '      <span class="label-text">{{translate "importoFinanziato" scope="Quote" category="fields"}}</span>',
            '    </label>',
            '    <div class="field" data-name="importoFinanziato"></div>',
            '  </div>',
            '  <div class="col-sm-3 cell form-group" data-name="rataPrestito">',
            '    <label class="control-label" data-name="rataPrestito">',
            '      <span class="label-text">{{translate "rataPrestito" scope="Quote" category="fields"}}</span>',
            '    </label>',
            '    <div class="field" data-name="rataPrestito"></div>',
            '  </div>',
            '  <div class="col-sm-3 cell form-group" data-name="nrRate">',
            '    <label class="control-label" data-name="nrRate">',
            '      <span class="label-text">{{translate "nrRate" scope="Quote" category="fields"}}</span>',
            '    </label>',
            '    <div class="field" data-name="nrRate"></div>',
            '  </div>',
            '  <div class="col-sm-3 cell form-group" data-name="tassoZero">',
            '    <label class="control-label" data-name="tassoZero">',
            '      <span class="label-text">{{translate "tassoZero" scope="Quote" category="fields"}}</span>',
            '    </label>',
            '    <div class="field" data-name="tassoZero"></div>',
            '  </div>',
            '</div>',
        ].join('\n'),

        setupFields: function () {
            this.fieldList = [
                'finanziamento',
                'statoFinanziamento',
                'importoSaldo',
                'importoFinanziato',
                'rataPrestito',
                'nrRate',
                'tassoZero',
            ];
        },

        setup: function () {
            this.setupFields();

            this.fieldList = this.fieldList.map((d) => {
                let item = d;

                if (typeof item !== 'object') {
                    item = {
                        name: item,
                        viewKey: item + 'Field',
                    };
                }

                item = Espo.Utils.clone(item);
                item.viewKey = item.name + 'Field';
                item.label = item.label || item.name;

                if (this.recordHelper.getFieldStateParam(item.name, 'hidden') !== null) {
                    item.hidden = this.recordHelper.getFieldStateParam(item.name, 'hidden');
                } else {
                    this.recordHelper.setFieldStateParam(item.name, 'hidden', item.hidden || false);
                }

                return item;
            });

            this.fieldList = this.fieldList.filter((item) => {
                if (!item.name) {
                    return false;
                }

                if (!(item.name in (((this.model.defs || {}).fields) || {}))) {
                    return false;
                }

                return true;
            });
        },

        afterRender: function () {
            this.createFields();
        },
    });
});
