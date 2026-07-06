define('custom:views/quote/record/panels/finanziamento', ['views/record/panels/bottom'], function (Dep) {

  const FIELD_NAMES = [
    'finanziamento',
    'statoFinanziamento',
    'importoSaldo',
    'tassoZero',
    'importoFinanziato',
    'rataPrestito',
    'nrRate',
  ];

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
      '  <div class="col-sm-3 cell form-group" data-name="tassoZero">',
      '    <label class="control-label" data-name="tassoZero">',
      '      <span class="label-text">{{translate "tassoZero" scope="Quote" category="fields"}}</span>',
      '    </label>',
      '    <div class="field" data-name="tassoZero"></div>',
      '  </div>',
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
      '</div>',
    ].join('\n'),

    setupFields: function () {
      this.fieldList = FIELD_NAMES.slice();
    },

    setup: function () {
      this.setupFields();

      this.fieldList = this.fieldList.map((d) => {
        const name = typeof d === 'object' ? d.name : d;

        return {
          name: name,
          viewKey: name + 'Field',
          label: name,
        };
      }).filter((item) => {
        return item.name && (item.name in ((this.model.defs || {}).fields || {}));
      });
    },

    afterRender: function () {
      this.fieldList.forEach((item) => {
        this.renderField(item.name);
      });
    },

    renderField: function (field) {
      const $cell = this.$el.find('.field[data-name="' + field + '"]');

      if (!$cell.length) {
        return;
      }

      const type = this.model.getFieldType(field) || 'base';
      const viewName = this.model.getFieldParam(field, 'view') ||
        this.getFieldManager().getViewName(type);
      const key = field + 'Field';

      if (this.hasView(key)) {
        this.removeView(key);
      }

      const readOnly = this.readOnly || this.recordHelper.getFieldStateParam(field, 'readOnly');

      this.createView(key, viewName, {
        model: this.model,
        el: $cell,
        defs: { name: field, params: {} },
        mode: this.mode,
        readOnly: readOnly,
        readOnlyLocked: this.readOnlyLocked,
        inlineEditDisabled: this.inlineEditDisabled,
        recordHelper: this.recordHelper,
        disabled: this.recordHelper.getFieldStateParam(field, 'hidden') || false,
      }, function (view) {
        view.render();
      });
    },
  });
});
